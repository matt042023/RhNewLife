# Configuration des CRON Jobs - Module Absences

Ce document décrit la configuration des tâches planifiées (CRON jobs) pour le module de gestion des absences et congés.

## Vue d'ensemble

Le module d'absences utilise deux commandes Symfony pour automatiser les notifications:

1. **Rappels J-1** : Envoi de rappels aux employés dont l'échéance de justificatif arrive dans 24h
2. **Échéances dépassées** : Alertes au service RH pour les justificatifs non fournis après l'échéance

## Commandes disponibles

### 1. Rappel Justificatifs (J-1)

**Commande:** `app:absence:rappel-justificatif`

**Description:** Envoie des emails de rappel aux employés qui doivent fournir un justificatif médical et dont l'échéance arrive dans les 24 heures.

**Utilisation:**
```bash
# Exécution normale
php bin/console app:absence:rappel-justificatif

# Mode simulation (test sans envoi d'emails)
php bin/console app:absence:rappel-justificatif --dry-run

# Personnaliser la fenêtre de rappel (48h au lieu de 24h)
php bin/console app:absence:rappel-justificatif --hours=48

# Aide détaillée
php bin/console app:absence:rappel-justificatif --help
```

**Options:**
- `--dry-run` : Simule l'exécution sans envoyer d'emails (utile pour tester)
- `--hours=X` : Nombre d'heures avant l'échéance pour envoyer le rappel (défaut: 24)

**Sortie:**
```
Rappel Justificatifs d'Absence
==============================

Recherche des absences avec échéance dans les 24h
--------------------------------------------------

Date actuelle: 26/11/2025 08:00
Fenêtre de rappel: 27/11/2025 08:00 - 27/11/2025 09:00

Trouvé 3 absence(s) nécessitant un rappel

 3/3 [============================] 100%

Résumé
------

 --------------- -------
  Métrique        Valeur
 --------------- -------
  Absences        3
  Rappels         3
  Erreurs         0
  Taux succès     100%
 --------------- -------

[OK] 3 rappel(s) envoyé(s) avec succès
```

---

### 2. Échéances Dépassées

**Commande:** `app:absence:echeance-depassee`

**Description:** Détecte les justificatifs dont l'échéance est dépassée et envoie une alerte au service RH avec la liste des absences concernées.

**Utilisation:**
```bash
# Exécution normale
php bin/console app:absence:echeance-depassee

# Mode simulation
php bin/console app:absence:echeance-depassee --dry-run

# Limiter le nombre d'alertes
php bin/console app:absence:echeance-depassee --limit=10

# Aide détaillée
php bin/console app:absence:echeance-depassee --help
```

**Options:**
- `--dry-run` : Simule l'exécution sans envoyer d'emails
- `--limit=X` : Limite le nombre d'alertes envoyées

**Sortie:**
```
Traitement des Échéances de Justificatifs Dépassées
====================================================

Recherche des échéances dépassées
----------------------------------

Date actuelle: 26/11/2025 09:00
Trouvé 2 échéance(s) dépassée(s)

Détails des échéances dépassées
--------------------------------

 ---- -------------- --------- ------------------ -------- -----------
  ID   Employé        Type      Échéance           Retard   Statut
 ---- -------------- --------- ------------------ -------- -----------
  42   Jean Dupont    Maladie   24/11/2025 18:00   2 jours  pending
  51   Marie Martin   AT        25/11/2025 12:00   1 jour   rejected
 ---- -------------- --------- ------------------ -------- -----------

Envoi des alertes au service RH
--------------------------------

 2/2 [============================] 100%

Résumé
------

 --------------------- -------
  Métrique              Valeur
 --------------------- -------
  Échéances dépassées   2
  Alertes envoyées      2
  Erreurs               0
  Taux de succès        100%
 --------------------- -------

Actions recommandées pour le service RH
----------------------------------------

 * Contacter les employés concernés
 * Vérifier si les justificatifs peuvent être acceptés malgré le retard
 * Appliquer les procédures disciplinaires si nécessaire
 * Mettre à jour le statut des absences

[OK] 2 alerte(s) envoyée(s) au service RH
```

---

## Configuration CRON sur le serveur

### Linux/Unix (crontab)

Ouvrir le fichier crontab:
```bash
crontab -e
```

Ajouter les lignes suivantes:
```cron
# Rappel justificatifs J-1 - tous les jours à 8h00
0 8 * * * cd /var/www/rhnewlife && php bin/console app:absence:rappel-justificatif >> /var/log/rhnewlife/cron-rappel.log 2>&1

# Échéances dépassées - tous les jours à 9h00
0 9 * * * cd /var/www/rhnewlife && php bin/console app:absence:echeance-depassee >> /var/log/rhnewlife/cron-echeance.log 2>&1
```

**Important:** Remplacer `/var/www/rhnewlife` par le chemin réel de votre projet.

### Windows (Task Scheduler)

1. Ouvrir le "Planificateur de tâches" (Task Scheduler)
2. Créer une nouvelle tâche de base
3. Configurer le déclencheur (trigger):
   - **Rappels**: Quotidien à 8h00
   - **Échéances**: Quotidien à 9h00
4. Action: "Démarrer un programme"
   - Programme: `C:\PHP\php.exe`
   - Arguments: `bin/console app:absence:rappel-justificatif`
   - Répertoire: `C:\www\rhnewlife\`

---

## Monitoring et Logs

### Logs des exécutions

Les logs sont automatiquement générés dans:
- **Prod**: `/var/log/rhnewlife/cron-rappel.log` et `/var/log/rhnewlife/cron-echeance.log`
- **Dev**: `var/log/dev.log`

### Vérifier les logs

```bash
# Dernières exécutions
tail -n 50 /var/log/rhnewlife/cron-rappel.log

# Rechercher les erreurs
grep -i "error" /var/log/rhnewlife/cron-rappel.log

# Compter les rappels envoyés aujourd'hui
grep "rappel(s) envoyé(s)" /var/log/rhnewlife/cron-rappel.log | grep $(date +%Y-%m-%d) | wc -l
```

### Alertes email en cas d'erreur

Pour recevoir les erreurs par email, modifier le CRON:

```cron
MAILTO=admin@rhnewlife.com

0 8 * * * cd /var/www/rhnewlife && php bin/console app:absence:rappel-justificatif 2>&1 | tee -a /var/log/rhnewlife/cron-rappel.log || echo "CRON rappel failed"
```

---

## Tests avant mise en production

### 1. Test en mode simulation

```bash
# Tester la commande de rappel
php bin/console app:absence:rappel-justificatif --dry-run

# Tester la commande d'échéances
php bin/console app:absence:echeance-depassee --dry-run
```

### 2. Test manuel avec envoi réel

Créer une absence de test avec une échéance J+1:

```bash
# Exécuter manuellement
php bin/console app:absence:rappel-justificatif

# Vérifier dans les logs email (MailHog en dev)
# Vérifier la réception de l'email
```

### 3. Vérifier les horaires d'exécution

```bash
# Simuler une exécution à une heure spécifique
php bin/console app:absence:rappel-justificatif --hours=0  # Pour test immédiat
```

---

## Fréquence recommandée

| Commande | Fréquence | Horaire | Raison |
|----------|-----------|---------|--------|
| `rappel-justificatif` | Quotidienne | 8h00 | Laisse la journée à l'employé pour envoyer le justificatif |
| `echeance-depassee` | Quotidienne | 9h00 | Permet au RH de traiter les retards en début de journée |

---

## Dépannage

### La commande ne s'exécute pas

1. Vérifier les permissions:
   ```bash
   ls -l bin/console
   chmod +x bin/console
   ```

2. Vérifier le chemin PHP dans le CRON:
   ```bash
   which php
   ```

3. Tester manuellement:
   ```bash
   cd /var/www/rhnewlife
   php bin/console app:absence:rappel-justificatif
   ```

### Aucun email n'est envoyé

1. Vérifier la configuration mailer dans `.env`:
   ```
   MAILER_DSN=smtp://localhost:1025
   ```

2. Tester l'envoi d'email:
   ```bash
   php bin/console debug:config framework mailer
   ```

3. Vérifier les logs Symfony:
   ```bash
   tail -f var/log/dev.log
   ```

### Trop d'emails envoyés

1. Vérifier qu'il n'y a pas de doublons dans le CRON:
   ```bash
   crontab -l | grep absence
   ```

2. Vérifier la fenêtre de rappel (option `--hours`)

3. Limiter temporairement avec `--limit`:
   ```bash
   php bin/console app:absence:echeance-depassee --limit=5
   ```

---

## Maintenance

### Désactiver temporairement

Commenter les lignes dans le crontab:
```cron
# 0 8 * * * cd /var/www/rhnewlife && php bin/console app:absence:rappel-justificatif
```

### Modifier les horaires

Éditer le crontab et ajuster les horaires selon le format:
```
* * * * *
│ │ │ │ │
│ │ │ │ └─ Jour de la semaine (0-7, dimanche = 0 ou 7)
│ │ │ └─── Mois (1-12)
│ │ └───── Jour du mois (1-31)
│ └─────── Heure (0-23)
└───────── Minute (0-59)
```

Exemples:
- `0 8 * * *` = Tous les jours à 8h00
- `0 8 * * 1-5` = Du lundi au vendredi à 8h00
- `0 */2 * * *` = Toutes les 2 heures
- `30 7 * * 1` = Tous les lundis à 7h30

---

## Support

Pour toute question ou problème:
- **Documentation Symfony Console**: https://symfony.com/doc/current/console.html
- **Documentation CRON**: https://crontab.guru/
- **Contact**: admin@rhnewlife.com
