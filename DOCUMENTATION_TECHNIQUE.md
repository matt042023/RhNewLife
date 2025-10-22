# 📘 Documentation Technique - Epic 1 : Onboarding RH

## 🏗️ Architecture Backend

### Entités Doctrine

#### User
```php
- id: int
- email: string (unique)
- password: string (hashed)
- firstName: string
- lastName: string
- phone: ?string
- address: ?text
- status: string (invited|onboarding|active|archived)
- position: ?string
- structure: ?string
- familyStatus: ?string
- children: ?int
- iban: ?string (34 chars max)
- bic: ?string (11 chars max)
- roles: array
- createdAt: DateTime
- updatedAt: DateTime
- cguAcceptedAt: ?DateTime
- documents: Collection<Document>
```

#### Invitation
```php
- id: int
- email: string
- firstName: string
- lastName: string
- position: ?string
- structure: ?string
- token: string (unique, 64 chars)
- expiresAt: DateTime (+7 jours)
- usedAt: ?DateTime
- status: string (pending|used|expired|error)
- createdAt: DateTime
- updatedAt: DateTime
- user: ?User
- errorMessage: ?text
```

#### Document
```php
- id: int
- fileName: string
- originalName: string
- type: string (cni|rib|domicile|honorabilite|diplome|contrat|other)
- status: string (pending|validated|rejected)
- uploadedAt: DateTime
- updatedAt: DateTime
- user: User
- comment: ?text
- mimeType: ?string
- fileSize: ?int
```

---

### Services Métier

#### InvitationManager
**Responsabilité** : Gestion du cycle de vie des invitations

**Méthodes principales :**
```php
createInvitation(email, firstName, lastName, ?position, ?structure): Invitation
sendInvitationEmail(Invitation): void
resendInvitation(Invitation): void
validateToken(string): ?Invitation
markAsUsed(Invitation, User): void
cleanExpiredInvitations(): int
sendExpirationReminders(int): int
```

**Dépendances :**
- EntityManagerInterface
- InvitationRepository
- MailerInterface
- UrlGeneratorInterface
- LoggerInterface

#### OnboardingManager
**Responsabilité** : Gestion du processus d'onboarding

**Méthodes principales :**
```php
activateAccount(Invitation, string $password, bool $acceptCGU): User
updateProfile(User, array $profileData): User
validatePassword(string): void
calculatePasswordStrength(string): int
isOnboardingComplete(User): bool
completeOnboarding(User): void
validateOnboarding(User): void
```

**Validations :**
- Mot de passe : min 12 caractères, maj+min+chiffre+spécial
- IBAN/BIC : formats valides
- Données obligatoires : phone, address, iban, bic

#### DocumentManager
**Responsabilité** : Gestion des uploads et documents

**Méthodes principales :**
```php
uploadDocument(UploadedFile, User, string $type, ?string $comment): Document
deleteDocument(Document): void
validateDocument(Document, ?string $comment): void
rejectDocument(Document, string $reason): void
getCompletionStatus(User): array
hasAllRequiredDocuments(User): bool
getDocumentPath(Document): string
```

**Contraintes :**
- Taille max : 5 Mo
- Formats autorisés : PDF, JPG, PNG
- Documents requis : CNI, RIB, DOMICILE, HONORABILITE

---

### Validators Custom

#### StrongPassword
```php
#[StrongPassword(minLength: 12)]
private string $password;
```
Vérifie : longueur, maj, min, chiffre, caractère spécial

#### ValidIBAN
```php
#[ValidIBAN(country: 'FR')]
private string $iban;
```
Vérifie : format, longueur selon pays, checksum modulo 97

---

### Voters de Sécurité

#### InvitationVoter
**Permissions :**
- `INVITATION_CREATE` : Admin seulement
- `INVITATION_VIEW` : Admin seulement
- `INVITATION_EDIT` : Admin, statut PENDING ou ERROR
- `INVITATION_DELETE` : Admin, statut ≠ USED
- `INVITATION_RESEND` : Admin, statut PENDING/EXPIRED/ERROR

#### UserVoter
**Permissions :**
- `USER_VIEW` : Self ou Admin/Director
- `USER_EDIT` : Admin seulement
- `USER_EDIT_PROFILE` : Self (champs limités) ou Admin
- `USER_VALIDATE` : Admin, statut ONBOARDING
- `USER_ARCHIVE` : Admin seulement

#### DocumentVoter
**Permissions :**
- `DOCUMENT_UPLOAD` : Self ou Admin
- `DOCUMENT_VIEW` : Owner ou Admin/Director
- `DOCUMENT_DELETE` : Owner (si non validé) ou Admin
- `DOCUMENT_VALIDATE` : Admin seulement
- `DOCUMENT_DOWNLOAD` : Owner ou Admin/Director

---

### Configuration Security

**security.yaml :**
```yaml
role_hierarchy:
    ROLE_ADMIN: [ROLE_USER, ROLE_DIRECTOR]
    ROLE_DIRECTOR: ROLE_USER

access_control:
    - { path: ^/onboarding/activate, roles: PUBLIC_ACCESS }
    - { path: ^/login, roles: PUBLIC_ACCESS }
    - { path: ^/admin, roles: ROLE_ADMIN }
    - { path: ^/profile, roles: ROLE_USER }
    - { path: ^/onboarding, roles: ROLE_USER }
    - { path: ^/documents, roles: ROLE_USER }
```

---

## 🎨 Architecture Frontend

### Design System

**Variables CSS (app.css) :**
```css
--color-primary: #3b82f6
--color-success: #10b981
--color-danger: #ef4444
--color-warning: #f59e0b

--spacing-xs: 0.25rem
--spacing-sm: 0.5rem
--spacing-md: 1rem
--spacing-lg: 1.5rem

--shadow-sm: 0 1px 2px rgba(0,0,0,0.05)
--shadow-md: 0 4px 6px rgba(0,0,0,0.1)
--shadow-lg: 0 10px 15px rgba(0,0,0,0.1)
```

**Classes utilitaires :**
- `.btn-primary`, `.btn-success`, `.btn-danger`, `.btn-secondary`, `.btn-ghost`
- `.badge-success`, `.badge-warning`, `.badge-danger`, `.badge-info`, `.badge-neutral`
- `.card`, `.card-header`, `.card-title`, `.card-body`
- `.form-label`, `.form-input`, `.form-select`, `.form-textarea`
- `.alert-success`, `.alert-error`, `.alert-warning`, `.alert-info`

### Layouts

#### auth.html.twig
Layout simplifié pour onboarding et login
- Logo centré
- Container 600px max-width
- Flash messages
- Footer

### Stimulus Controllers

#### password_strength_controller.js
```javascript
Targets: input, bar, label
Actions: input->check
Calcule la force 0-100% en temps réel
Couleurs: rouge (0-39%), jaune (40-69%), vert (70-100%)
```

---

## 🔄 Workflow Complet

### 1. Admin crée une invitation (UC01)
```
POST /admin/invitations/create
    ↓
InvitationManager::createInvitation()
    ↓
Génère token unique (bin2hex(random_bytes(32)))
    ↓
Persist Invitation (status: PENDING)
    ↓
InvitationManager::sendInvitationEmail()
    ↓
MailerInterface → emails/invitation.html.twig
    ↓
Email envoyé avec lien : /onboarding/activate/{token}
```

### 2. Salarié active son compte (UC02)
```
GET /onboarding/activate/{token}
    ↓
InvitationManager::validateToken(token)
    ↓
Affiche formulaire activation
    ↓
POST /onboarding/activate/{token}
    ↓
OnboardingManager::validatePassword(password)
    ↓
OnboardingManager::activateAccount(invitation, password, acceptCGU)
    ↓
Création User (status: ONBOARDING, roles: [ROLE_USER])
    ↓
PasswordHasher::hashPassword()
    ↓
InvitationManager::markAsUsed(invitation, user)
    ↓
Email confirmation → emails/account_activated.html.twig
    ↓
Redirection → /login
```

### 3. Salarié complète son profil (UC03 - Étape 1)
```
GET /onboarding/step1
    ↓
Formulaire infos perso (phone, address, familyStatus, children, iban, bic)
    ↓
POST /onboarding/step1
    ↓
OnboardingManager::updateProfile(user, profileData)
    ↓
Validation ValidIBAN sur iban
    ↓
Persist User
    ↓
Redirection → /onboarding/step2
```

### 4. Salarié uploade les documents (UC03 - Étape 2)
```
GET /onboarding/step2
    ↓
Affiche formulaire upload + tracking complétion
    ↓
POST /documents/upload (API JSON)
    ↓
DocumentManager::validateFile(file) → max 5Mo, PDF/JPG/PNG
    ↓
Génère fileName unique : {slug}-{uniqid}.{ext}
    ↓
file->move(uploadsDirectory/users/{userId}/, fileName)
    ↓
Persist Document (status: PENDING)
    ↓
Return JSON { success, document }
    ↓
Frontend met à jour barre progression
    ↓
POST /onboarding/complete (quand 100%)
    ↓
OnboardingManager::completeOnboarding(user)
    ↓
Email admin → emails/onboarding_completed.html.twig
    ↓
Redirection → /onboarding/completed
```

### 5. Admin valide le dossier (UC04)
```
GET /admin/validation
    ↓
Liste Users (status: ONBOARDING)
    ↓
GET /admin/validation/{userId}
    ↓
Affiche infos + documents + statut complétion
    ↓
POST /admin/validation/{userId} (action: validate)
    ↓
OnboardingManager::validateOnboarding(user)
    ↓
user->setStatus(User::STATUS_ACTIVE)
    ↓
Email bienvenue → emails/onboarding_validated.html.twig
    ↓
Redirection → /admin/validation
```

---

## 📦 Dépendances

### PHP
- symfony/framework-bundle: 7.3.*
- symfony/security-bundle: 7.3.*
- symfony/mailer: 7.3.*
- symfony/form: 7.3.*
- symfony/validator: 7.3.*
- doctrine/orm: ^3.5
- doctrine/doctrine-bundle: ^2.18
- doctrine/doctrine-migrations-bundle: ^3.5
- doctrine/doctrine-fixtures-bundle: ^4.3 (dev)

### JavaScript
- @hotwired/stimulus
- @hotwired/turbo
- tailwindcss v4
- flowbite

---

## 🗄️ Structure de la BDD

```sql
user (
    id INT PRIMARY KEY,
    email VARCHAR(180) UNIQUE,
    password VARCHAR(255),
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    phone VARCHAR(20),
    address TEXT,
    status VARCHAR(20) DEFAULT 'invited',
    position VARCHAR(100),
    structure VARCHAR(100),
    family_status VARCHAR(50),
    children INT,
    iban VARCHAR(34),
    bic VARCHAR(11),
    roles JSON,
    created_at DATETIME,
    updated_at DATETIME,
    cgu_accepted_at DATETIME
)

invitation (
    id INT PRIMARY KEY,
    user_id INT NULL,
    email VARCHAR(180),
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    position VARCHAR(100),
    structure VARCHAR(100),
    token VARCHAR(64) UNIQUE,
    expires_at DATETIME,
    used_at DATETIME,
    status VARCHAR(20) DEFAULT 'pending',
    created_at DATETIME,
    updated_at DATETIME,
    error_message TEXT,
    FOREIGN KEY (user_id) REFERENCES user(id) ON DELETE SET NULL
)

document (
    id INT PRIMARY KEY,
    user_id INT NOT NULL,
    file_name VARCHAR(255),
    original_name VARCHAR(255),
    type VARCHAR(50),
    status VARCHAR(20) DEFAULT 'pending',
    uploaded_at DATETIME,
    updated_at DATETIME,
    comment TEXT,
    mime_type VARCHAR(100),
    file_size INT,
    FOREIGN KEY (user_id) REFERENCES user(id) ON DELETE CASCADE
)
```

---

## 🔐 Sécurité

### Implémentations
✅ CSRF tokens sur tous les formulaires
✅ Password hashing (bcrypt/argon2)
✅ Voters pour contrôle d'accès fin
✅ Validation serveur + client
✅ Token unique d'invitation (64 caractères)
✅ Expiration automatique (7 jours)
✅ Upload sécurisé (taille, type MIME)
✅ Isolation des fichiers par utilisateur
✅ HTTPS requis en production
✅ Rate limiting (à implémenter en prod)

### Best Practices
✅ Aucun mot de passe en clair
✅ Logs d'audit sur actions sensibles
✅ Emails sans info sensible
✅ Tokens non réutilisables
✅ RGPD : consentement CGU tracé

---

## 📊 Métriques

- **52 fichiers** créés
- **~5000 lignes** PHP
- **~1500 lignes** Twig
- **~500 lignes** CSS/JS
- **3 entités** Doctrine
- **6 controllers** (25+ routes)
- **5 emails** templated
- **3 services** métier
- **2 validators** custom
- **3 voters** sécurité

---

🎉 **Documentation complète pour l'Epic 1 - Onboarding RH**
