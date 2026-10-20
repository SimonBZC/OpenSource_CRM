# FreeCRM

Outil personnel de prospection freelance : centralise entreprises, contacts, opportunités,
relances, alertes, interactions et sources.

> **Application strictement locale.** Pas d'authentification, pas de comptes, pas de rôles,
> pas de multi-utilisateur. Ce n'est pas une application destinée à être exposée sur Internet.

## Statut du projet

Les 9 phases du plan initial sont terminées :

1. Socle Docker + Symfony + Next.js
2. Modèle de données (entités, migrations, fixtures)
3. API Platform (CRUD Company/Contact/Opportunity/FollowUp/Alert/Interaction, filtres, recherche, tri, pagination)
4. Frontend CRM (listes, fiches détaillées, formulaires)
5. Dashboard orienté action, relances, alertes, filtres avancés sur les opportunités
6. Architecture IA (`LLMProviderInterface` → `OpenAiProvider`, `OpportunityAnalyzer`)
7. Analyse IA branchée sur un vrai flux (`POST /api/opportunities/{id}/analyze`, bouton frontend)
8. Architecture d'automatisation événementielle (`DomainEventBus`, `AutomationRuleInterface`, 2 règles réelles)
9. Importeurs de sources externes (`OpportunityImporterInterface`, `ManualImporter` fonctionnel, `LinkedInImporter`/`MaltImporter` en placeholders)

Voir « Fonctionnalités » et « Limites connues » plus bas pour le détail et les points encore ouverts.

## Stack

| Couche | Technologies |
|---|---|
| Backend | PHP 8.2, Symfony 6.4 LTS, API Platform 4.3, Doctrine ORM, Symfony Messenger, PHPUnit 11 |
| Frontend | Next.js 16, React 19, TypeScript 5.9, Tailwind CSS 4 |
| Base de données | PostgreSQL 16 |
| Infra | Docker Compose (3 services : `app`, `frontend`, `db`) |

```
Frontend Next.js  ->  API Symfony / API Platform  ->  PostgreSQL
   :3000                   :18080                       :5432
```

> Le port du backend vient de `BACKEND_PORT` : `8080` par défaut dans `docker-compose.yml`,
> surchargé à `18080` dans le `.env` local pour éviter un conflit avec d'autres projets.
> Les URLs ci-dessous supposent `18080`, voir « Conflit de port » plus bas.

## Prérequis

- Docker et Docker Compose v2+ (`docker compose version`)
- Rien d'autre : PHP, Composer, Node et npm tournent dans les containers.

## Installation et démarrage

```bash
git clone <url-du-depot> && cd FreeCRM

# Optionnel : uniquement si vous devez surcharger un port ou un identifiant.
cp .env.example .env

docker compose up -d --build

# Charger les fixtures de démonstration (entreprises, contacts, opportunités, relances, alertes)
docker compose exec app php bin/console doctrine:fixtures:load
```

Au premier lancement, la construction des images et l'installation des dépendances
prennent quelques minutes. Ensuite :

| Service | URL |
|---|---|
| Backend — healthcheck | http://localhost:18080/health |
| Backend — entrypoint API | http://localhost:18080/api |
| Backend — doc Swagger / OpenAPI | http://localhost:18080/api/docs |
| Frontend (dashboard, opportunités, contacts...) | http://localhost:3000 |
| PostgreSQL | `localhost:5432` (user/password/base : `freecrm`) |

Remplacez `18080` par la valeur de `BACKEND_PORT` si vous l'avez surchargée.

Vérification rapide que tout répond :

```bash
curl http://localhost:18080/health     # -> {"status":"ok"}
curl http://localhost:18080/api        # -> {"@context":"/api/contexts/Entrypoint",...}
curl -I http://localhost:18080/api/docs # -> HTTP/1.1 200 OK
curl -I http://localhost:3000          # -> HTTP/1.1 200 OK
docker compose ps                      # -> les 3 services Up, db "healthy"
```

`/api/docs` fait de la négociation de contenu : ouvert dans un navigateur
(`Accept: text/html`) il sert l'interface Swagger UI ; appelé sans header il
renvoie la description JSON-LD/Hydra.

## Fonctionnalités

### CRM

- **Dashboard** (`/dashboard`) orienté action : opportunités à traiter, relances du jour /
  en retard, alertes à traiter, opportunités à fort potentiel, opportunités récentes.
- **Contacts, opportunités** (`/contacts`, `/opportunities`) :
  listes filtrables/triables, fiches détaillées, création/édition.
- **Entreprises** : pas de page dédiée. Elles se créent depuis le formulaire contact et restent
  exposées par l'API (`/api/companies`).
- **Unicité des entreprises** : deux entreprises ne peuvent pas porter le même nom (casse et
  espaces en début/fin ignorés). L'API refuse un doublon sur POST/PUT/PATCH `/api/companies`
  (422, violation sur `name`).
- **Création d'entreprise depuis le formulaire contact** : si aucune entreprise n'est choisie,
  « Ou créer une entreprise » + « Rechercher » vérifie le nom, puis l'entreprise (nom + secteur)
  est créée avec le contact. Si l'enregistrement du contact échoue, l'entreprise est supprimée.
- **Actions sur la liste des contacts** : « Nouvelle opportunité » et « Nouvelle relance » ouvrent
  le formulaire pré-rempli via `?contact={id}&company={id}` (`company` omis si le contact n'en a pas).
- **Opportunités** : statut (9 valeurs, extensible), source (7 valeurs, extensible), TJM
  min/max, remote, technologies, score, raison du score. Filtres avancés : statut, source,
  score, technologie, remote, TJM, dates, recherche textuelle.
- **Messages pré-construits** (Paramètres > Messages, `/settings/messages`) : titre (unique, casse
  et espaces en début/fin ignorés, 422 sur `title`) et corps avec indicateurs `{%prenom%}`, `{%nom%}`,
  `{%entreprise%}` (entreprise du contact). API : CRUD `/api/message_templates` et
  `GET /api/message_templates/{id}/render?contact={id}` → `{title, body, missing}` (lecture seule ;
  donnée manquante = indicateur laissé tel quel et listé dans `missing`). Sur les relances, le bouton
  « Message » affiche le message rempli pour le contact de la relance, avec « Copier ». Aucun envoi.
- **Relances** (`/follow-ups`) et **alertes** (`/alerts`) : filtres par statut/priorité/échéance,
  actions rapides (marquer comme faite/annulée).

### IA (optionnelle, jamais obligatoire)

- Domaine découplé de tout fournisseur LLM via `App\Ai\LLMProviderInterface`
  (implémentation par défaut : `OpenAiProvider`, clé via `OPENAI_API_KEY`).
- `POST /api/opportunities/{id}/analyze` déclenche une analyse IA d'une opportunité
  existante. Le LLM ne persiste jamais rien lui-même : le backend valide la réponse
  structurée puis décide quoi appliquer (`score`/`scoreReason` toujours mis à jour,
  les autres champs — technologies, TJM, localisation... — uniquement s'ils étaient vides,
  pour ne jamais écraser une saisie manuelle).
- Sans `OPENAI_API_KEY` configurée, l'endpoint répond `503` proprement — le reste du CRM
  fonctionne normalement.

### Automatisation

- Bus d'événements applicatif (`App\Automation\DomainEventBus`) et interface
  `AutomationRuleInterface` : ajouter une règle = ajouter une classe, sans toucher au
  code existant.
- Événements émis : `OpportunityCreated`, `OpportunityAnalyzed`, `FollowUpDue`.
- Règles actives : score d'opportunité ≥ 80 après analyse → création automatique d'une
  alerte de relance ; relance échue et toujours à faire → notification (stub loggé,
  canal réel à brancher plus tard sans changer l'architecture).
- Commande `php bin/console app:automation:check-due-followups [--dry-run]` pour scanner
  les relances du jour (pas encore de scheduler/cron branché — à faire lors d'une
  itération future).

### Sources externes

- `App\Import\OpportunityImporterInterface` : reçoit une opportunité standardisée
  (`RawOpportunityData`) et retourne un résultat d'import structuré.
- `ManualImporter` : fonctionnel, réutilise le chemin de création standard.
- `LinkedInImporter` / `MaltImporter` : **placeholders volontaires**, lèvent une
  exception explicite (`NotImplementedImporterException`), aucun appel réseau ni
  scraping — conformément au choix de ne pas automatiser LinkedIn dans ce MVP.
- Commande : `php bin/console app:import:opportunity <source> [options]`.

## Variables d'environnement

Toutes les variables ont une valeur par défaut fonctionnelle dans `docker-compose.yml` :
le fichier `.env` à la racine n'est nécessaire que pour **surcharger**. Voir `.env.example`.

| Variable | Défaut | Rôle |
|---|---|---|
| `BACKEND_PORT` | `8080` (`18080` dans le `.env` local) | Port du backend sur l'hôte |
| `FRONTEND_PORT` | `3000` | Port du frontend sur l'hôte |
| `POSTGRES_PORT` | `5432` | Port PostgreSQL sur l'hôte |
| `POSTGRES_DB` / `POSTGRES_USER` / `POSTGRES_PASSWORD` | `freecrm` | Identifiants de la base |
| `APP_ENV` / `APP_DEBUG` | `dev` / `1` | Environnement Symfony |
| `CORS_ALLOW_ORIGIN` | `^https?://(localhost\|127\.0\.0\.1)(:[0-9]+)?$` | Origines autorisées à appeler l'API depuis un navigateur (NelmioCorsBundle) |
| `MESSENGER_TRANSPORT_DSN` | `sync://` | Transport Messenger (synchrone) |
| `OPENAI_API_KEY` | *(vide)* | Clé API OpenAI — absente = fonctionnalités IA désactivées proprement (503) |
| `OPENAI_MODEL` | `gpt-4o-mini` | Modèle utilisé par `OpenAiProvider` |
| `OPENAI_TIMEOUT` | `30` | Timeout (secondes) des appels au provider IA |
| `NEXT_PUBLIC_API_URL` | `http://localhost:8080` (doit suivre `BACKEND_PORT`) | URL de l'API appelée **depuis le navigateur** |

`NEXT_PUBLIC_API_URL` est résolue par le navigateur de l'hôte, pas par le réseau Docker :
elle doit donc pointer vers `localhost`, jamais vers le nom de service `app`. Si vous
changez `BACKEND_PORT`, changez-la en conséquence.

### Conflit de port

Si `18080` (ou `3000`) est déjà pris par un autre projet, surchargez dans `.env` :

```dotenv
BACKEND_PORT=28080
NEXT_PUBLIC_API_URL=http://localhost:28080
```

Les deux variables doivent rester cohérentes : `NEXT_PUBLIC_API_URL` est résolue
par le navigateur, donc elle doit refléter le port réellement publié sur l'hôte.
Vérifiez ce qui occupe un port avec `docker ps` ou `lsof -i :18080`.

## Développement

Le code source est bind-mounté : le **hot-reload fonctionne des deux côtés**, sans
reconstruire les images. Modifier un fichier dans `backend/` ou `frontend/` suffit.

```bash
docker compose logs -f app         # logs backend
docker compose logs -f frontend    # logs frontend
docker compose down                # arrêter (les données sont conservées)
docker compose down -v             # arrêter et supprimer les volumes anonymes (la base, en bind mount, n'est PAS supprimée)
```

### Commandes backend

```bash
docker compose exec app php bin/console        # console Symfony
docker compose exec app php bin/console debug:router                  # routes exposées
docker compose exec app php bin/phpunit        # tests unitaires
docker compose exec app composer install       # dépendances
docker compose exec app composer audit         # advisories de sécurité
docker compose exec app php bin/console doctrine:migrations:migrate   # migrations
docker compose exec app php bin/console doctrine:fixtures:load        # fixtures de démo
docker compose exec app php bin/console app:automation:check-due-followups --dry-run
docker compose exec app php bin/console app:import:opportunity manual --title="..." --company="..."
```

### Commandes frontend

```bash
docker compose exec frontend npm run build     # build + vérification TypeScript
docker compose exec frontend npm run lint
docker compose exec frontend npm install <pkg>
```

Le typecheck s'effectue via `npm run build` : Next.js 16 génère certains types dans
`.next/types`, donc `tsc --noEmit` échoue sur un dépôt fraîchement cloné tant qu'aucun
build n'a été lancé.

### Stack de test API

Une base Postgres jetable (en mémoire) et une seconde instance du backend, isolées de la
vraie base, pour tester l'API en écriture sans risque :

```bash
docker compose --profile test up -d db-test app-test
docker compose exec app-test php bin/console doctrine:migrations:migrate -n
docker compose exec app-test php bin/console doctrine:fixtures:load -n
curl http://localhost:18081/api      # port surchargeable via BACKEND_TEST_PORT
docker compose --profile test stop db-test app-test   # la base de test est effacée
```

### Base de données

```bash
docker compose exec db psql -U freecrm -d freecrm
```

Les données persistent dans `docker/db-data/` (bind mount **versionné dans git**).
Toujours faire `docker compose stop db` avant un commit ou un pull qui touche ce dossier :
Postgres ouvert pendant l'opération corrompt la base.

## Structure

```
.
├── backend/
│   ├── src/
│   │   ├── Entity/         Company, Contact, Opportunity, FollowUp, Alert, Interaction
│   │   ├── Enum/           Statuts/sources/types extensibles (backed enums)
│   │   ├── Filter/         Filtres API Platform personnalisés (technologie, recherche...)
│   │   ├── Ai/             Abstraction IA : LLMProviderInterface, OpenAiProvider, OpportunityAnalyzer
│   │   ├── Automation/     DomainEventBus, AutomationRuleInterface, événements, règles
│   │   ├── Import/         OpportunityImporterInterface, ManualImporter, LinkedIn/Malt (placeholders)
│   │   └── Controller/     Endpoints hors CRUD API Platform standard (ex. /health, /analyze)
│   ├── migrations/
│   └── tests/
├── frontend/
│   └── src/app/            dashboard/, contacts/, opportunities/, follow-ups/, alerts/, settings/
├── docker-compose.yml      Les 3 services
├── .env.example            Variables surchargeables
└── README.md
```

## Limites connues / dette assumée

- **Pas de scheduler branché** : `app:automation:check-due-followups` existe et fonctionne,
  mais rien ne l'exécute périodiquement — à brancher sur un cron/systemd timer si besoin.
- **Notifications de relances échues = stub loggé**, pas de canal réel (email/push).
  Remplacer `LoggerNotifier` par une vraie implémentation de `NotifierInterface` suffit.
- **`monolog-bundle` n'est pas installé** : les logs applicatifs (dont ceux de
  l'automatisation) partent sur le logger minimal de Symfony, pas de canaux dédiés ni
  de rotation. À ajouter si l'observabilité devient un vrai besoin.
- **`LinkedInImporter` / `MaltImporter` sont des coquilles volontaires** (choix produit :
  pas d'automatisation agressive de LinkedIn dans ce MVP) — l'architecture est prête,
  rien n'est branché.
- **`OpportunityCreated` n'a pas encore de règle abonnée** (ex. "analyse IA automatique à
  la création") — extensibilité assumée, à ajouter au besoin en créant une nouvelle règle.
- **`APP_ENV=dev` / `APP_DEBUG=1` par défaut** : les erreurs HTTP renvoient des traces
  détaillées. Cohérent avec une application strictement locale jamais exposée ; à revoir
  si ce n'est plus le cas.
