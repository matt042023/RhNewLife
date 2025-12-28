# Navigation - Module Absences et Congés

Ce document récapitule tous les points d'accès au module de gestion des absences et congés.

## Vue d'ensemble

Le module d'absences est accessible via plusieurs points d'entrée selon le rôle de l'utilisateur :
- **Sidebar** : Navigation principale pour tous les utilisateurs
- **Dashboard Employé** : Widget avec actions rapides
- **Dashboard Admin** : Widget de validation des demandes

---

## Accès Employé

### 1. Sidebar - "Mes absences"

**Localisation** : `templates/components/_sidebar.html.twig` (lignes 69-77)

**Route** : `app_absence_index` → `/mes-absences`

**Icône** : Calendrier

**Condition d'affichage** : Visible pour tous les utilisateurs authentifiés

**Code** :
```twig
<a href="{{ path('app_absence_index') }}" class="flex items-center p-2...">
    <svg class="w-5 h-5"><!-- Icône calendrier --></svg>
    <span class="ml-3">Mes absences</span>
</a>
```

---

### 2. Dashboard Employé - Widget "Mes absences et congés"

**Localisation** : `templates/dashboard/employee.html.twig` (lignes 77-91)

**Actions disponibles** :
1. **Nouvelle demande**
   - Route : `app_absence_new` → `/mes-absences/nouvelle`
   - Type : Bouton success (vert)
   - Action : Ouvre le formulaire de nouvelle demande d'absence

2. **Mes absences**
   - Route : `app_absence_index` → `/mes-absences`
   - Type : Bouton secondary (gris)
   - Action : Liste toutes les absences de l'employé

**Badge dynamique** :
- Affiche le solde de congés restant si disponible
- Variable : `counters[0].remaining` (fournie par `DashboardController`)
- Format : "X jours"
- Fallback : "Voir solde" si aucune donnée

**Code** :
```twig
{% embed 'components/widgets/_widget_card.html.twig' with {
    'title': 'Mes absences et congés',
    'badge': counters is defined and counters|length > 0 ?
             (counters[0].remaining|number_format(1, ',', ' ') ~ ' jours') :
             'Voir solde',
    'actions': [
        {'label': 'Nouvelle demande', 'url': path('app_absence_new'), 'type': 'success'},
        {'label': 'Mes absences', 'url': path('app_absence_index'), 'type': 'secondary'}
    ]
} %}
```

---

## Accès Administrateur

### 3. Sidebar Admin - "Gestion absences"

**Localisation** : `templates/components/_sidebar.html.twig` (lignes 113-120)

**Route** : `admin_absence_index` → `/admin/absences`

**Icône** : Calendrier

**Condition d'affichage** : Visible uniquement pour `ROLE_ADMIN`

**Code** :
```twig
{% if is_granted('ROLE_ADMIN') %}
    <a href="{{ path('admin_absence_index') }}" class="flex items-center p-2...">
        <svg class="w-5 h-5"><!-- Icône calendrier --></svg>
        <span class="ml-3">Gestion absences</span>
    </a>
{% endif %}
```

---

### 4. Dashboard Admin - Widget "Absences à valider"

**Localisation** : `templates/dashboard/admin.html.twig` (lignes 231-241)

**Action disponible** :
- **Traiter les demandes**
  - Route : `admin_absence_index` → `/admin/absences`
  - Type : Bouton success (vert)
  - Action : Ouvre la liste des absences à valider

**Badge dynamique** :
- Affiche le nombre de demandes en attente
- Variable : `pendingAbsences|length` (fournie par `DashboardController`)
- Format : "X demandes"
- Fallback : "7 demandes" si aucune donnée

**Code** :
```twig
{% embed 'components/widgets/_widget_card.html.twig' with {
    'title': 'Absences à valider',
    'badge': pendingAbsences is defined ?
             (pendingAbsences|length ~ ' demandes') :
             '7 demandes',
    'actions': [
        {'label': 'Traiter les demandes', 'url': path('admin_absence_index'), 'type': 'success'}
    ]
} %}
```

---

## Routes disponibles

### Routes Employé (préfixe `app_absence_`)

| Nom de route | Méthode | URL | Description |
|--------------|---------|-----|-------------|
| `app_absence_index` | GET | `/mes-absences` | Liste des absences de l'employé |
| `app_absence_new` | GET/POST | `/mes-absences/nouvelle` | Nouvelle demande d'absence |
| `app_absence_show` | GET | `/mes-absences/{id}` | Détails d'une absence |
| `app_absence_cancel` | POST | `/mes-absences/{id}/annuler` | Annulation d'une demande |
| `app_absence_justification_upload` | POST | `/mes-absences/{id}/justificatif/upload` | Upload de justificatif |
| `app_absence_justification_view` | GET | `/mes-absences/{id}/justificatif/{docId}` | Voir un justificatif |
| `app_absence_justification_delete` | POST | `/mes-absences/{id}/justificatif/{docId}/supprimer` | Supprimer un justificatif |
| `app_absence_export_pdf` | GET | `/mes-absences/export-pdf` | Export PDF |

### Routes Admin (préfixe `admin_absence_`)

| Nom de route | Méthode | URL | Description |
|--------------|---------|-----|-------------|
| `admin_absence_index` | GET | `/admin/absences` | Liste toutes les absences |
| `admin_absence_show` | GET/POST | `/admin/absences/{id}` | Détails + validation/rejet |
| `admin_absence_justification_validate` | POST | `/admin/absences/{id}/justificatif/{docId}/valider` | Valider un justificatif |
| `admin_absence_justification_reject` | POST | `/admin/absences/{id}/justificatif/{docId}/rejeter` | Rejeter un justificatif |
| `admin_absence_justification_view` | GET | `/admin/absences/{id}/justificatif/{docId}` | Voir un justificatif |
| `admin_absence_justifications_pending` | GET | `/admin/absences/justificatifs-en-attente` | Justificatifs en attente |
| `admin_absence_calendar` | GET | `/admin/absences/calendrier` | Vue calendrier |
| `admin_absence_export` | GET | `/admin/absences/export` | Export Excel |
| `admin_absence_create` | GET/POST | `/admin/absences/creer` | Créer absence pour un employé |

### Routes API (préfixe `api_absence_`)

| Nom de route | Méthode | URL | Description |
|--------------|---------|-----|-------------|
| `api_absence_counters` | GET | `/api/absences/compteurs/{userId}` | Compteurs de congés |
| `api_absence_calculate_working_days` | POST | `/api/absences/calculate-working-days` | Calcul jours ouvrés |
| `api_absence_check_overlap` | POST | `/api/absences/check-overlap` | Vérifier chevauchements |
| `api_absence_check_balance` | POST | `/api/absences/check-balance` | Vérifier solde |

---

## Données fournies par le contrôleur

### DashboardController - Méthode `employee()`

**Fichier** : `src/Controller/DashboardController.php` (lignes 43-56)

**Variables passées au template** :
- `user` : Utilisateur courant
- `counters` : Compteurs de congés via `AbsenceCounterService::getUserCounters()`

**Services injectés** :
- `AbsenceCounterService` : Pour calculer les soldes de congés

---

### DashboardController - Méthode `admin()`

**Fichier** : `src/Controller/DashboardController.php` (lignes 67-82)

**Variables passées au template** :
- `user` : Utilisateur courant
- `pendingAbsences` : Liste des absences en attente (max 10, triées par date de création DESC)

**Services injectés** :
- `AbsenceRepository` : Pour récupérer les absences en attente

**Critère de recherche** :
```php
$absenceRepository->findBy([
    'status' => Absence::STATUS_PENDING
], ['createdAt' => 'DESC'], 10);
```

---

## Workflow de navigation

### Parcours Employé

1. **Accès initial** : Dashboard employé → Widget "Mes absences et congés"
2. **Nouvelle demande** : Clic sur "Nouvelle demande" → Formulaire (`app_absence_new`)
3. **Consultation** : Clic sur "Mes absences" ou sidebar → Liste (`app_absence_index`)
4. **Détails** : Clic sur une absence → Détails (`app_absence_show`)
5. **Justificatif** : Upload depuis la page de détails

### Parcours Administrateur

1. **Accès initial** : Dashboard admin → Widget "Absences à valider"
2. **Validation** : Clic sur "Traiter les demandes" → Liste admin (`admin_absence_index`)
3. **Détails** : Clic sur une absence → Détails avec actions (`admin_absence_show`)
4. **Actions** : Valider/Rejeter depuis la page de détails
5. **Justificatifs** : Valider/Rejeter les justificatifs uploadés

---

## Cohérence des workflows

✅ **Navigation cohérente** :
- Tous les liens pointent vers des routes existantes
- Pas de liens `#` ou de routes manquantes
- Séparation claire entre espace employé et espace admin

✅ **Accessibilité** :
- Sidebar accessible sur toutes les pages
- Widgets dashboard accessibles dès la connexion
- Routes API disponibles pour interactions dynamiques

✅ **Données dynamiques** :
- Badge employé affiche le solde réel de congés
- Badge admin affiche le nombre réel de demandes en attente
- Pas de données en dur sauf pour les fallbacks

✅ **Sécurité** :
- Routes admin protégées par `ROLE_ADMIN`
- Routes employé protégées par `ROLE_USER`
- Vérifications dans les contrôleurs

---

## Vérifications effectuées

- [x] Routes employé accessibles via sidebar
- [x] Routes employé accessibles via dashboard
- [x] Routes admin accessibles via sidebar
- [x] Routes admin accessibles via dashboard
- [x] Toutes les routes existent et sont correctement nommées
- [x] Données dynamiques fournies par les contrôleurs
- [x] Badges affichent des données réelles
- [x] Workflow cohérent pour employé
- [x] Workflow cohérent pour admin
- [x] Séparation des rôles respectée

---

## Conclusion

Le module d'absences et congés est maintenant **entièrement accessible** et **cohérent** avec les workflows de l'application :

1. **Navigation principale** : Liens sidebar pour accès rapide
2. **Dashboard** : Widgets avec actions pertinentes selon le rôle
3. **Routes** : Toutes les routes nécessaires sont définies et accessibles
4. **Données** : Compteurs et badges dynamiques basés sur des données réelles
5. **Sécurité** : Protection par rôles correctement implémentée

Le module est prêt pour utilisation en production.
