# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**RhNewLife** - Application SIRH (Système d'Information Ressources Humaines) pour l'Association NewLife, un lieu de vie accueillant des enfants.

- **Framework**: Symfony 7.3 (PHP 8.2+)
- **Base de données**: MariaDB 10.11
- **Frontend**: Tailwind CSS 4 + Flowbite + Stimulus/Turbo + FullCalendar
- **Build**: Webpack Encore
- **Email**: Symfony Mailer avec MailHog (dev)

## Development Commands

### Docker Environment

```bash
# Start environment
docker compose up -d

# Stop environment
docker compose down

# View logs
docker compose logs -f php
```

### Symfony Commands (run inside php container)

```bash
docker compose exec php php bin/console cache:clear
docker compose exec php php bin/console doctrine:migrations:migrate
docker compose exec php php bin/console make:entity
docker compose exec php php bin/console make:controller
```

### Frontend Assets

```bash
npm install           # Install dependencies
npm run dev           # Build once
npm run watch         # Watch mode
npm run build         # Production build
```

### Testing

```bash
# Run all tests
docker compose exec php php bin/phpunit

# Run specific test file
docker compose exec php php bin/phpunit tests/Controller/HomeControllerTest.php

# Run by folder (E2E, Integration, Security, Service, Unit)
docker compose exec php php bin/phpunit tests/Unit/
```

## Code Architecture

### Source Structure

```
src/
├── Command/           # Symfony console commands (cron jobs)
├── Controller/        # HTTP controllers
│   ├── Admin/         # Admin-only routes (/admin/*)
│   ├── Api/           # JSON API endpoints (/api/*)
│   └── Director/      # Director-only routes
├── DataFixtures/      # Doctrine fixtures
├── Domain/            # Domain logic (Events, ValueObjects)
├── DTO/               # Data Transfer Objects
├── Entity/            # Doctrine entities
├── Enum/              # PHP enums
├── EventListener/     # Doctrine/Symfony event listeners
├── EventSubscriber/   # Event subscribers
├── Exception/         # Custom exceptions
├── Form/              # Symfony form types
├── Repository/        # Doctrine repositories
├── Security/          # Voters, authenticators
├── Service/           # Business logic services
├── Twig/              # Twig extensions
├── Validator/         # Custom validation constraints
└── ValueObject/       # Immutable value objects
```

### Controller Organization

- **Root controllers** (`src/Controller/*.php`): Employee-facing features (profile, absences, documents)
- **Admin controllers** (`src/Controller/Admin/`): Full CRUD for all entities (users, contracts, planning)
- **API controllers** (`src/Controller/Api/`): JSON endpoints for frontend (planning, absences, notifications)
- **Director controllers** (`src/Controller/Director/`): Limited admin access for director role

### Key Services

- `DocumentManager`: File upload, versioning, secure storage
- `ContractManager`: Contract lifecycle, generation, signature
- `OnboardingManager`: Invitation flow, account creation
- `AnnualDayCounterService`: CP/RTT/absence counters calculation
- `DirectUserCreationService`: User creation with validation

### Console Commands (Cron Jobs)

```bash
# Absence reminders
app:absence:echeance-depassee    # Expired deadlines
app:absence:rappel-justificatif  # Missing documents reminder

# Payroll
app:payroll:consolidate          # Monthly consolidation
app:payroll:reminder             # Element submission reminders

# CP counters
app:cp:credit-monthly            # Monthly credit
app:cp:new-period                # New period initialization

# Data maintenance
app:purge:notifications          # Clean old notifications
app:purge:messages               # Clean old messages
app:purge:annonces               # Clean old announcements
```

### Frontend / Stimulus Controllers

Key controllers in `assets/controllers/`:

- `planning_assignment_controller.js`: FullCalendar-based planning editor
- `dashboard_planning_controller.js`: Dashboard planning display
- `direct_user_creation_controller.js`: User creation form
- `document_*_controller.js`: Document upload, list, modal, preview
- `notification_bell_controller.js`: Real-time notification badge

### Entity Relationships

- `User` ↔ `Contract` (1:n, one active at a time)
- `User` ↔ `Document` (1:n)
- `User` ↔ `Absence` (1:n)
- `User` ↔ `Affectation` (planning assignments)
- `Villa` ↔ `Affectation` (1:n)

### Security Roles

- `ROLE_USER`: Employee (limited consultation)
- `ROLE_DIRECTOR`: Director (HR management, no system config)
- `ROLE_ADMIN`: Administrator (full access)

## URLs (Development)

- Application: http://localhost:8080/
- PHPMyAdmin: http://localhost:8081/
- MailHog: http://localhost:8025/

## Database

- **Host**: `mariadb` (Docker) or `localhost:3307` (host)
- **Database**: `rhnewlife`
- **User/Password**: `root/root`

## Test Organization

```
tests/
├── E2E/           # End-to-end browser tests
├── Integration/   # Controller/repository integration
├── Security/      # Authentication/authorization tests
├── Service/       # Service unit tests
└── Unit/          # Pure unit tests
```

## Conventions

- Routes defined via PHP 8 attributes
- Templates in `templates/` with Twig, organized by controller
- Forms in `src/Form/`, one FormType per entity operation
- API endpoints return JSON, prefix `/api/`
- Admin routes prefix `/admin/`
