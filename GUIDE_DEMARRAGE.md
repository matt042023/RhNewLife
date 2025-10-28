# 🚀 Guide de Démarrage - RH NewLife

## 📋 Comptes de Test

Trois utilisateurs ont été créés pour tester l'application :

### 👑 ADMINISTRATEUR (Accès complet)
- **Email** : `admin@rhnewlife.fr`
- **Password** : `Admin123!@#`
- **Rôle** : ROLE_ADMIN
- **Nom** : Marie Dubois
- **Permissions** :
  - ✅ Créer et gérer les invitations
  - ✅ Valider les dossiers d'onboarding
  - ✅ Accès à tous les profils et documents
  - ✅ Gérer tous les utilisateurs

### 🏢 DIRECTEUR
- **Email** : `directeur@rhnewlife.fr`
- **Password** : `Director123!@#`
- **Rôle** : ROLE_DIRECTOR
- **Nom** : Jean Martin
- **Permissions** :
  - ✅ Consulter les profils et documents
  - ✅ Accès au planning
  - ❌ Pas de droits d'administration

### 👨‍🏫 ÉDUCATEUR
- **Email** : `educateur@rhnewlife.fr`
- **Password** : `Educator123!@#`
- **Rôle** : ROLE_USER
- **Nom** : Sophie Bernard
- **Permissions** :
  - ✅ Accès à son profil personnel
  - ✅ Gérer ses documents
  - ❌ Pas d'accès admin

---

## 🎯 Scénario de Test Complet

### Étape 1 : Lancer l'application

```bash
# Terminal 1 : Compiler les assets
npm run dev

# Terminal 2 : Lancer le serveur Symfony
symfony serve
```

Accédez à : **http://localhost:8000**

---

### Étape 2 : Se connecter en tant qu'Admin

1. Allez sur **http://localhost:8000/login**
2. Connectez-vous avec :
   - Email : `admin@rhnewlife.fr`
   - Password : `Admin123!@#`
3. Vous arrivez sur le **Dashboard**

---

### Étape 3 : Créer une nouvelle invitation (UC01)

1. Cliquez sur **"📧 Invitations"** dans le dashboard
2. Ou allez directement sur **/admin/invitations**
3. Cliquez sur **"+ Nouvelle invitation"**
4. Remplissez le formulaire :
   - **Prénom** : Luc
   - **Nom** : Dupont
   - **Email** : `luc.dupont@example.com` (utilisez un vrai email si vous voulez recevoir le mail)
   - **Poste** : Éducateur spécialisé
   - **Structure** : Villa des Roses
5. Cliquez sur **"Envoyer l'invitation"**

✅ **Résultat attendu** :
- Message de succès "Invitation envoyée à luc.dupont@example.com"
- L'invitation apparaît dans la liste avec statut "En attente"
- Un email a été envoyé (vérifiez Mailpit sur http://localhost:8025)

---

### Étape 4 : Activer le compte via le lien (UC02)

**Option A : Via Mailpit (si configuré)**
1. Ouvrez **http://localhost:8025** (Mailpit)
2. Ouvrez l'email d'invitation
3. Cliquez sur le bouton "✨ Activer mon compte"

**Option B : Récupérer le token manuellement**
1. Dans la liste des invitations, cliquez sur l'invitation
2. Copiez le token
3. Allez sur : `http://localhost:8000/onboarding/activate/{TOKEN}`

**Sur la page d'activation :**
1. Le formulaire affiche les infos pré-remplies (Luc Dupont, email, poste)
2. Créez un mot de passe fort :
   - Minimum 12 caractères
   - Une majuscule, minuscule, chiffre, caractère spécial
   - Exemple : `LucDupont2024!@`
3. La **barre de force** se met à jour en temps réel (rouge→jaune→vert)
4. Confirmez le mot de passe
5. Cochez **"J'accepte les CGU"**
6. Cliquez sur **"Activer mon compte"**

✅ **Résultat attendu** :
- Message de succès "Votre compte a été activé avec succès ! Bienvenue, Luc !"
- Redirection vers `/login`
- Email de confirmation envoyé

---

### Étape 5 : Connectez-vous avec le nouveau compte

1. Sur **/login**, connectez-vous avec :
   - Email : `luc.dupont@example.com`
   - Password : `LucDupont2024!@`
2. Vous êtes redirigé automatiquement vers **Étape 1 du onboarding**

---

### Étape 6 : Compléter les informations personnelles (UC03 - Étape 1)

1. Remplissez le formulaire :
   - **Téléphone** : 06 12 34 56 78
   - **Adresse** : 15 Rue de la Paix, 75002 Paris
   - **Situation familiale** : Marié
   - **Enfants à charge** : 1
   - **IBAN** : FR76 3000 6000 0112 3456 7890 189
   - **BIC** : AGRIFRPP
2. Cliquez sur **"Suivant"**

✅ **Résultat attendu** :
- Message "Vos informations ont été enregistrées"
- Redirection vers **Étape 2 : Téléversement des justificatifs**

---

### Étape 7 : Uploader les documents (UC03 - Étape 2)

**Documents requis :**
- 📄 CNI (Carte d'identité)
- 💳 RIB
- 🏠 Justificatif de domicile
- ✅ Attestation d'honorabilité

**Pour chaque document :**
1. Cliquez sur **"Téléverser"** ou faites un **drag & drop**
2. Sélectionnez un fichier PDF ou image (max 5 Mo)
3. Le document apparaît avec une miniature
4. La **barre de progression** se met à jour (0% → 25% → 50% → 75% → 100%)

Une fois **tous les documents uploadés (100%)** :
1. Cliquez sur **"Terminer l'onboarding"**

✅ **Résultat attendu** :
- Message "Votre dossier a été soumis avec succès !"
- Email envoyé à l'admin : "Nouveau dossier à valider"
- Redirection vers page de confirmation

---

### Étape 8 : Validation Admin (UC04)

1. **Déconnectez-vous** (bouton en haut à droite)
2. **Reconnectez-vous en tant qu'admin** :
   - Email : `admin@rhnewlife.fr`
   - Password : `Admin123!@#`
3. Allez sur **"✅ Validations"** ou **/admin/validation**
4. Vous voyez **"Luc Dupont"** dans la liste des dossiers en attente
5. Cliquez sur **"Examiner le dossier"**

**Sur la page de validation :**
- Consultez les informations personnelles
- Vérifiez les 4 documents uploadés
- (Optionnel) Validez/rejetez chaque document individuellement
- Cliquez sur **"Valider l'onboarding"**

✅ **Résultat attendu** :
- Message "Dossier validé ! L'utilisateur a été activé."
- Le statut de Luc passe à **ACTIVE**
- Email de bienvenue envoyé à Luc : "🎉 Bienvenue dans l'équipe !"

---

### Étape 9 : Vérification finale

1. Reconnectez-vous avec le compte de **Luc** :
   - Email : `luc.dupont@example.com`
   - Password : `LucDupont2024!@`
2. Vous arrivez maintenant sur le **Dashboard complet** (plus d'onboarding)
3. Vous avez accès à :
   - ✅ Votre profil
   - ✅ Vos documents
   - ✅ (À venir) Planning, absences, etc.

---

## 📧 Emails Envoyés (via Mailpit)

Consultez **http://localhost:8025** pour voir tous les emails :

1. **Invitation** → `luc.dupont@example.com` (lien d'activation)
2. **Compte activé** → `luc.dupont@example.com` (confirmation)
3. **Dossier complet** → `admin@rhnewlife.fr` (notification admin)
4. **Bienvenue** → `luc.dupont@example.com` (après validation)

---

## 🗂️ Fonctionnalités Testées

### ✅ Use Cases Couverts
- **UC01** : Envoyer une invitation (Admin)
- **UC02** : Accepter l'invitation et créer son compte (Salarié)
- **UC03** : Compléter ses informations personnelles et justificatifs (Salarié)
- **UC04** : Valider l'onboarding (Admin)

### ✅ Fonctionnalités Techniques
- Authentification (login/logout)
- Gestion des rôles (ADMIN, DIRECTOR, USER)
- Voters de sécurité (accès restreints)
- Validation de mot de passe fort (temps réel)
- Upload de fichiers sécurisé
- Envoi d'emails templated
- Flash messages
- Redirections intelligentes

---

## 🐛 Recharger les Fixtures

Si vous voulez recommencer les tests :

```bash
# Recharger les 3 utilisateurs de base
php bin/console doctrine:fixtures:load --no-interaction
```

---

## 🎨 Routes Disponibles

| Route | Accès | Description |
|-------|-------|-------------|
| `/login` | Public | Connexion |
| `/logout` | Auth | Déconnexion |
| `/dashboard` | Auth | Tableau de bord |
| `/onboarding/activate/{token}` | Public | Activation compte |
| `/onboarding/step1` | User (onboarding) | Formulaire infos perso |
| `/onboarding/step2` | User (onboarding) | Upload documents |
| `/admin/invitations` | Admin | Gestion invitations |
| `/admin/invitations/create` | Admin | Créer invitation |
| `/admin/validation` | Admin | Liste dossiers à valider |
| `/admin/validation/{id}` | Admin | Valider un dossier |
| `/documents` | User | Liste documents |
| `/documents/upload` | User | Upload document (API) |

---

## 💡 Conseils de Test

1. **Utilisez un email réel** si vous voulez recevoir les vrais emails
2. **Ouvrez Mailpit** (http://localhost:8025) pour voir les emails en développement
3. **Testez les erreurs** :
   - Mot de passe trop faible
   - Document trop volumineux (>5Mo)
   - Lien expiré (modifiez `expiresAt` en BDD)
4. **Testez les permissions** :
   - Connectez-vous en tant qu'éducateur → essayez d'accéder à `/admin/invitations` (403 Forbidden)
   - Un user ne peut voir que SES documents

---

## 🚀 Prochaines Étapes

Une fois l'Epic 1 testé et validé, nous pouvons passer à :

- **EP-02** : Fiche Salarié & Contrat (CRUD complet, avenants)
- **EP-03** : Gestion documentaire avancée (viewer PDF, archivage)
- **EP-04** : Planning & Affectations 48h (2 villas)
- **EP-05** : Congés & Absences
- ... et tous les autres Epics !

---

🎉 **Bon test !**
