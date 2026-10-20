<!-- bmad:context -->
<!-- Vérifié le 2026-10-20 sur 0fc2578. Géré par bmad-project-context ; les modifications dans ce bloc sont remplacées au refresh. Garder hors des marqueurs ce qui doit être préservé. -->

## FreeCRM

Outil personnel de prospection freelance de Simon, strictement local et mono-utilisateur. Backend PHP 8.2 / Symfony 6.4 / API Platform 4 / Doctrine / PostgreSQL 16, frontend Next.js 16 / React 19 / TypeScript / Tailwind 4, le tout sous Docker Compose. Planification BMad dans `_bmad-output/`, usage et variables d'environnement dans `README.md`.

## Policy

- N'introduis jamais de comptes, login, rôles, permissions ou sessions, même minimaux : hors scope du projet.
- `docker/db-data/` contient la vraie base de prospection de Simon (services `db` et `app`). N'y accède jamais (`psql`, SQL, lecture des fichiers) et ne la supprime jamais : pas de `doctrine:fixtures:load`, `doctrine:schema:*`, `doctrine:database:drop` via `exec app`, ni `docker compose down -v`, ni `rm`. Une migration sur la vraie base (`exec app ... doctrine:migrations:migrate`) se propose à Simon, qui la lance.
- N'envoie aucune requête d'écriture (POST/PUT/PATCH/DELETE) à l'API réelle (`BACKEND_PORT`, 18080) : elle écrit dans la vraie base. Les tests API vont sur la stack de test (voir Running and verifying).
- Ne stage jamais `docker/db-data/` (pas de `git add -A` ni `git add .`) : ajoute les fichiers un par un. Postgres ouvert pendant un commit/pull de ce dossier corrompt la base — `docker compose stop db` d'abord, à la demande de Simon uniquement.
- N'invente aucune règle métier, comportement, endpoint ou champ d'API ; en cas d'ambiguïté, de contradiction avec l'existant ou de changement destructif, arrête et demande à Simon.
- Ne modifie jamais un test pour le faire passer. Si un test existant doit changer sans évolution fonctionnelle demandée, explique pourquoi et demande à Simon.
- Ne réécris pas du code qui fonctionne sans raison ; une évolution à la fois, validée avant la suivante.
- Terminé = code + tests PHPUnit couvrant la règle métier + suite verte sans régression + endpoints touchés vérifiés sur la stack de test + exigence fonctionnelle satisfaite. Un build qui passe ne suffit pas.
- Écris commentaires, libellés UI, docs et messages de commit en français ; identifiants de code en anglais, comme l'existant.

## Where things are

- IA : `backend/src/Ai/`. Le domaine ne dépend que de `LLMProviderInterface` ; aucun SDK/fournisseur (OpenAI, Claude, Mistral) hors de `Ai/Provider/`. Le LLM ne persiste rien : il renvoie des données structurées, `OpportunityAnalysisApplier` décide quoi appliquer, l'appelant flush. L'IA reste optionnelle — sans clé, le CRM fonctionne (503 sur l'analyse).
- Automatisation : nouvelle règle = nouvelle classe `AutomationRuleInterface` dans `backend/src/Automation/Rule/`, événements via `DomainEventBus`.
- Import : nouvelle source = implémentation de `OpportunityImporterInterface` dans `backend/src/Import/`. `LinkedInImporter`/`MaltImporter` sont des placeholders voulus : aucun scraping ni appel réseau.
- Appels API du frontend : `frontend/src/lib/api/`. N'affiche jamais une erreur API silencieusement.
- Dans `frontend/` : Next.js 16 diffère de tes connaissances ; lis le guide concerné dans `frontend/node_modules/next/dist/docs/` avant d'écrire (voir `frontend/AGENTS.md`).

## Running and verifying

- PHP, Composer, Node et npm tournent uniquement dans les conteneurs : `docker compose exec app …` (backend), `docker compose exec frontend …` (frontend).
- Tests unitaires : `docker compose exec app php bin/phpunit` — ~1 s, ne touche aucune base.
- Tests API : stack de test isolée (Postgres en mémoire, IA désactivée) sur `http://localhost:18081` (`BACKEND_TEST_PORT`). Démarrer : `docker compose --profile test up -d db-test app-test`, puis `docker compose exec app-test php bin/console doctrine:migrations:migrate -n` et `doctrine:fixtures:load -n` — toujours `app-test`, jamais `app`. La base est vidée à chaque arrêt de `db-test`.
- Typecheck frontend : `docker compose exec frontend npm run build` ; `tsc --noEmit` échoue tant qu'aucun build n'a généré `.next/types`.
- Le port backend réel vient de `BACKEND_PORT` dans `.env` : 18080 sur la machine de Simon, 8080 par défaut dans `docker-compose.yml`. Lis `.env` plutôt que de supposer.
- Messenger tourne en `sync://` (surchargé par `docker-compose.yml`) : rien n'est réellement asynchrone.

<!-- /bmad:context -->
