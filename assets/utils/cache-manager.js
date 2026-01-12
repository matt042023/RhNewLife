/**
 * Cache Manager for Planning Assignment Data
 * Uses IndexedDB for persistent client-side caching with stale-while-revalidate strategy
 */

const DB_NAME = 'rhnewlife_planning_cache';
const DB_VERSION = 1;
const STORE_NAME = 'planning_months';
const DEFAULT_TTL = 5 * 60 * 1000; // 5 minutes

export class CacheManager {
    constructor() {
        this.db = null;
        this.initPromise = this.initDB();
    }

    /**
     * Initialize IndexedDB
     */
    async initDB() {
        return new Promise((resolve, reject) => {
            if (!window.indexedDB) {
                console.warn('IndexedDB not supported, cache will be disabled');
                resolve(null);
                return;
            }

            const request = indexedDB.open(DB_NAME, DB_VERSION);

            request.onerror = () => {
                console.error('IndexedDB error:', request.error);
                resolve(null); // Graceful degradation
            };

            request.onsuccess = () => {
                this.db = request.result;
                console.log('✅ Cache IndexedDB initialized');
                resolve(this.db);
            };

            request.onupgradeneeded = (event) => {
                const db = event.target.result;

                // Create object store if doesn't exist
                if (!db.objectStoreNames.contains(STORE_NAME)) {
                    const store = db.createObjectStore(STORE_NAME, { keyPath: 'monthKey' });
                    store.createIndex('timestamp', 'timestamp', { unique: false });
                    console.log('📦 Created cache object store');
                }
            };
        });
    }

    /**
     * Get cached data for a specific month
     * @param {string} monthKey - Format: "YYYY-MM" (e.g., "2025-01")
     * @param {number} ttl - Time-to-live in milliseconds (default: 5 min)
     * @returns {Object|null} - { data, timestamp, stale } or null if not found
     */
    async get(monthKey, ttl = DEFAULT_TTL) {
        await this.initPromise;

        if (!this.db) {
            return null; // Cache disabled
        }

        return new Promise((resolve) => {
            try {
                const transaction = this.db.transaction(STORE_NAME, 'readonly');
                const store = transaction.objectStore(STORE_NAME);
                const request = store.get(monthKey);

                request.onsuccess = () => {
                    const cached = request.result;

                    if (!cached) {
                        console.log(`📭 Cache MISS: ${monthKey}`);
                        resolve(null);
                        return;
                    }

                    const age = Date.now() - cached.timestamp;
                    const isStale = age > ttl;

                    console.log(`📬 Cache HIT: ${monthKey} (age: ${Math.round(age / 1000)}s, stale: ${isStale})`);

                    resolve({
                        data: cached.data,
                        timestamp: cached.timestamp,
                        stale: isStale
                    });
                };

                request.onerror = () => {
                    console.error('Cache read error:', request.error);
                    resolve(null);
                };
            } catch (error) {
                console.error('Cache get error:', error);
                resolve(null);
            }
        });
    }

    /**
     * Store data in cache
     * @param {string} monthKey - Format: "YYYY-MM"
     * @param {Object} data - Data to cache
     */
    async set(monthKey, data) {
        await this.initPromise;

        if (!this.db) {
            return false;
        }

        return new Promise((resolve) => {
            try {
                const transaction = this.db.transaction(STORE_NAME, 'readwrite');
                const store = transaction.objectStore(STORE_NAME);

                const cacheEntry = {
                    monthKey,
                    data,
                    timestamp: Date.now()
                };

                const request = store.put(cacheEntry);

                request.onsuccess = () => {
                    console.log(`💾 Cache SET: ${monthKey}`);
                    resolve(true);
                };

                request.onerror = () => {
                    console.error('Cache write error:', request.error);
                    resolve(false);
                };
            } catch (error) {
                console.error('Cache set error:', error);
                resolve(false);
            }
        });
    }

    /**
     * Invalidate (delete) cached data for a specific month
     * @param {string} monthKey - Format: "YYYY-MM"
     */
    async invalidate(monthKey) {
        await this.initPromise;

        if (!this.db) {
            return false;
        }

        return new Promise((resolve) => {
            try {
                const transaction = this.db.transaction(STORE_NAME, 'readwrite');
                const store = transaction.objectStore(STORE_NAME);
                const request = store.delete(monthKey);

                request.onsuccess = () => {
                    console.log(`🗑️ Cache INVALIDATED: ${monthKey}`);
                    resolve(true);
                };

                request.onerror = () => {
                    console.error('Cache invalidation error:', request.error);
                    resolve(false);
                };
            } catch (error) {
                console.error('Cache invalidate error:', error);
                resolve(false);
            }
        });
    }

    /**
     * Invalidate all cached months
     */
    async invalidateAll() {
        await this.initPromise;

        if (!this.db) {
            return false;
        }

        return new Promise((resolve) => {
            try {
                const transaction = this.db.transaction(STORE_NAME, 'readwrite');
                const store = transaction.objectStore(STORE_NAME);
                const request = store.clear();

                request.onsuccess = () => {
                    console.log('🗑️ Cache CLEARED (all months)');
                    resolve(true);
                };

                request.onerror = () => {
                    console.error('Cache clear error:', request.error);
                    resolve(false);
                };
            } catch (error) {
                console.error('Cache invalidateAll error:', error);
                resolve(false);
            }
        });
    }

    /**
     * Invalidate current month and adjacent months (used after modifications)
     * @param {number} year
     * @param {number} month
     */
    async invalidateRange(year, month) {
        const months = this.getAdjacentMonths(year, month);
        const promises = months.map(m => this.invalidate(m.monthKey));
        await Promise.all(promises);
        console.log(`🗑️ Cache INVALIDATED range: ${year}-${month} ± 1 month`);
    }

    /**
     * Get adjacent months (prev, current, next)
     * @param {number} year
     * @param {number} month
     * @returns {Array} Array of { year, month, monthKey }
     */
    getAdjacentMonths(year, month) {
        const months = [];

        // Previous month
        const prevMonth = month === 1 ? 12 : month - 1;
        const prevYear = month === 1 ? year - 1 : year;
        months.push({
            year: prevYear,
            month: prevMonth,
            monthKey: `${prevYear}-${String(prevMonth).padStart(2, '0')}`
        });

        // Current month
        months.push({
            year,
            month,
            monthKey: `${year}-${String(month).padStart(2, '0')}`
        });

        // Next month
        const nextMonth = month === 12 ? 1 : month + 1;
        const nextYear = month === 12 ? year + 1 : year;
        months.push({
            year: nextYear,
            month: nextMonth,
            monthKey: `${nextYear}-${String(nextMonth).padStart(2, '0')}`
        });

        return months;
    }

    /**
     * Clean old entries (older than 1 hour)
     */
    async cleanOldEntries() {
        await this.initPromise;

        if (!this.db) {
            return;
        }

        const maxAge = 60 * 60 * 1000; // 1 hour
        const cutoff = Date.now() - maxAge;

        return new Promise((resolve) => {
            try {
                const transaction = this.db.transaction(STORE_NAME, 'readwrite');
                const store = transaction.objectStore(STORE_NAME);
                const index = store.index('timestamp');
                const request = index.openCursor(IDBKeyRange.upperBound(cutoff));

                let deletedCount = 0;

                request.onsuccess = (event) => {
                    const cursor = event.target.result;
                    if (cursor) {
                        cursor.delete();
                        deletedCount++;
                        cursor.continue();
                    } else {
                        if (deletedCount > 0) {
                            console.log(`🧹 Cleaned ${deletedCount} old cache entries`);
                        }
                        resolve(deletedCount);
                    }
                };

                request.onerror = () => {
                    console.error('Cache cleanup error:', request.error);
                    resolve(0);
                };
            } catch (error) {
                console.error('Cache cleanOldEntries error:', error);
                resolve(0);
            }
        });
    }
}

// Export singleton instance
export const cacheManager = new CacheManager();

// Clean old entries on initialization
cacheManager.initPromise.then(() => {
    cacheManager.cleanOldEntries();
});
