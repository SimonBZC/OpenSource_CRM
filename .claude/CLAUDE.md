# FreeCRM — Outil personnel de prospection freelance

## Contexte

Application **strictement locale**, sans authentification, sans multi-utilisateur, sans rôles/permissions/sessions. C'est un outil personnel destiné à centraliser la prospection de missions freelance (entreprises, contacts, opportunités, relances, tâches, interactions, sources).

L'architecture doit rester simple et pragmatique, mais prévoir dès le départ l'ajout futur de couches IA (analyse/scoring d'opportunités) et d'automatisation (règles déclenchées par événements), sans coupler le domaine métier à un fournisseur LLM particulier.

Stack :
- Backend : PHP 8.2, Symfony 6.4 LTS, API Platform 4.x, Doctrine ORM, PostgreSQL, Symfony Messenger (traitements async)
- Frontend : Next.js, TypeScript, Tailwind CSS
- Infra : Docker / Docker Compose (backend + frontend + PostgreSQL), tout tourne en local

Architecture :
```
Frontend Next.js  →  API Symfony / API Platform  →  PostgreSQL
```

Les fonctionnalités IA/automatisation sont isolées derrière des abstractions (ex. `LLMProviderInterface`, `OpportunityImporterInterface`) — le domaine métier ne dépend jamais directement d'OpenAI/Claude/Mistral, et le LLM ne persiste jamais rien lui-même : il retourne des données structurées, le backend valide puis décide quoi enregistrer.

## Organisation multi-agent

Projet développé par une équipe d'agents spécialisés coordonnée par un **orchestrateur central** (`.claude/agents/`) :

| Agent | Fichier | Rôle |
|---|---|---|
| Orchestrator | `agents/orchestrator.md` | Coordination globale, seul point de contact avec l'utilisateur pour les décisions fonctionnelles |
| Symfony Expert | `agents/symfony-expert.md` | Backend/API (Symfony, API Platform, Doctrine, règles métier, IA/automatisation) |
| Next.js Expert | `agents/nextjs-expert.md` | Frontend (Next.js, TypeScript, Tailwind, consommation de l'API) |
| Unit Test Expert | `agents/unit-test-expert.md` | Tests unitaires PHPUnit (logique métier backend) |
| QA Expert | `agents/qa-expert.md` | Tests fonctionnels de l'API en boîte noire, via HTTP |

```
USER
  ↓
ORCHESTRATOR
  ↓
  ├── SYMFONY EXPERT      → Backend/API
  ├── NEXT.JS EXPERT      → Frontend
  ├── UNIT TEST EXPERT    → Tests unitaires
  └── QA EXPERT           → Tests API
          ↓
     ORCHESTRATOR
          ↓
         USER
```

## Règle fondamentale

L'**orchestrateur** est le seul agent avec une vision globale du projet. Les autres agents sont spécialisés et **ne prennent jamais seuls une décision fonctionnelle importante**.

Toute ambiguïté, contradiction, comportement inattendu, modification potentiellement destructive, évolution fonctionnelle implicite, ou conflit entre exigences et comportement existant doit remonter à l'orchestrateur, qui décide s'il peut résoudre automatiquement, déléguer à un autre agent, ou doit interrompre le workflow pour demander une décision à l'utilisateur.

**Aucun agent n'invente** une règle métier, un comportement, une API, ou une modification du besoin fonctionnel. En particulier, **aucun agent n'introduit de système de compte/login/rôles/permissions** — c'est explicitement hors-scope pour ce projet.

## Définition de "terminé"

```
CODE + UNIT TESTS + API QA + NO REGRESSION + FUNCTIONAL REQUIREMENT SATISFIED
```

Une tâche n'est jamais terminée simplement parce que le code compile ou que le build Docker passe.

## Méthode de développement

Le projet avance par phases (voir plan détaillé fourni par l'orchestrateur). Ne pas réécrire inutilement du code déjà fonctionnel. Ne pas développer plusieurs phases d'un coup sans validation intermédiaire.

## Commandes utiles

```bash
# Démarrer la stack (backend + frontend + Postgres)
docker compose up -d

# Commande dans le container backend
docker compose exec app <commande>

# Tests unitaires
docker compose exec app php bin/phpunit

# Migrations Doctrine
docker compose exec app php bin/console doctrine:migrations:migrate

# Fixtures
docker compose exec app php bin/console doctrine:fixtures:load

# API (Swagger/OpenAPI) — port réel piloté par BACKEND_PORT dans .env (18080 en local, un autre projet occupe 8080)
http://localhost:18080/api/docs

# Frontend
http://localhost:3000
```
