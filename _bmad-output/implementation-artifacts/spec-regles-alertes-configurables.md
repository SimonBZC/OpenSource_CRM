---
title: 'Règles d’alerte configurables (Paramètres > Règles) et case « Présenté » sur le contact'
type: 'feature'
created: '2026-10-20'
status: 'done'
baseline_commit: 'e82f72c29194a186d1d24209d013cbfa51ba3499'
route: 'dispatch'
review_loop_iteration: 0
context:
  - '{project-root}/AGENTS.md'
  - '{project-root}/frontend/AGENTS.md'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Simon doit repérer lui-même les contacts à relancer, les opportunités restées sans réponse et les contacts auxquels il ne s'est pas présenté. Aucune alerte ne naît d'une absence d'action dans la durée, et rien n'indique s'il s'est présenté à un contact.

**Approach:** Un catalogue de trois règles d'alerte, réglables dans Paramètres > Règles. Un bouton « Vérifier les règles » sur la page Alertes les évalue et crée les alertes manquantes. Une case « Présenté » sur le contact alimente la troisième règle.

## Boundaries & Constraints

**Always:**
- Décisions de Simon (2026-10-20) :
  - Catalogue fixe de trois types de règles ; pour chacune : activée/désactivée, délai N (jours, entier ≥ 0), priorité de l'alerte (`AlertPriority`). Pas de création ni de suppression de règle dans l'interface.
  - Évaluation **uniquement** par le bouton « Vérifier les règles » de la page Alertes ; aucune tâche planifiée ni déclenchement automatique.
  - Doublons : pas de nouvelle alerte tant qu'une alerte de la même règle pour la même cible est ouverte (`todo` ou `in_progress`). Une fois terminée ou annulée, une nouvelle peut naître si la condition est toujours vraie.
  - Case « Présenté » (`presentationSent`, booléen, défaut faux) sur le **contact**, dans le formulaire de création et de modification. Les contacts existants partent non cochés.
- Règles (date de référence comparée à « maintenant − N jours », strictement antérieure) :
  1. **Contact sans relance récente** (`contact_follow_up_stale`, défaut 90 j) : contact sans aucune relance « À faire » ; référence = `completedAt` de sa dernière relance « Terminée », ou sa date de création s'il n'en a aucune (relances annulées ignorées). Cible : contact.
  2. **Opportunité sans réponse** (`opportunity_no_answer`, défaut 15 j) : opportunité au statut `contacted` ou `waiting` ; référence = date du dernier changement de statut (`statusChangedAt`). Cible : opportunité (et son contact s'il existe).
  3. **Présentation non envoyée** (`contact_not_presented`, défaut 7 j) : contact non « Présenté » ; référence = date de création ; N = 0 vise tous les contacts non présentés. Cible : contact.
- `Opportunity.statusChangedAt` : mis à jour à chaque changement effectif de statut (pas sur une autre modification) ; initialisé à la création ; migration : `updated_at` pour l'existant.
- Les trois règles sont livrées activées, aux délais par défaut, priorité `medium`.
- Titre d'alerte fixe par type, nommant la cible ; description rappelant le délai. Ex. « Relancer Jean Dupont », « Pas de réponse : Développeur Symfony », « Se présenter à Jean Dupont ».
- La réponse du bouton indique le nombre d'alertes créées, affiché sur la page Alertes, qui se recharge.
- Logique d'évaluation dans `backend/src/Automation/` (une classe par type), testée par PHPUnit sans base (dépôt simulé ou critères purs).

**Never:**
- Scheduler, cron, Messenger asynchrone ou évaluation à l'ouverture d'une page.
- Constructeur libre de règles ; règles sur d'autres entités.
- Modifier les règles d'automatisation existantes (score ≥ 80, relances échues).
- Écrire sur la vraie base : la migration est lancée par Simon.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| R1 dernière relance vieille | relance terminée il y a 100 j, aucune à faire, N=90 | 1 alerte sur le contact | N/A |
| R1 relance à faire | idem + une relance « À faire » | aucune alerte | N/A |
| R1 jamais relancé | contact créé il y a 100 j, aucune relance | 1 alerte | N/A |
| R1 récent | relance terminée il y a 10 j | aucune | N/A |
| R2 en attente | statut `waiting` depuis 20 j, N=15 | 1 alerte sur l'opportunité | N/A |
| R2 autre statut | statut `interview` depuis 60 j | aucune | N/A |
| R2 modif sans changement de statut | titre modifié hier, statut `contacted` depuis 20 j | 1 alerte (référence inchangée) | N/A |
| R3 non présenté | contact non coché créé il y a 8 j, N=7 | 1 alerte | N/A |
| R3 présenté | contact coché | aucune | N/A |
| Doublon ouvert | condition vraie, alerte de la règle ouverte pour la cible | aucune nouvelle | N/A |
| Après clôture | alerte précédente `done` | nouvelle alerte | N/A |
| Règle désactivée | `enabled = false` | aucune alerte de ce type | N/A |
| Délai invalide | PATCH `delayDays` = -1 | 422 | erreur sous le champ |
| Rien à créer | aucune condition vraie | « Aucune nouvelle alerte » | N/A |

</frozen-after-approval>

## Code Map

- `backend/src/Entity/Alert.php` -- liens `opportunity`/`contact` existants ; ajouter `ruleType` (nullable) pour la déduplication.
- `backend/src/Repository/AlertRepository.php:31` -- `hasActiveAutoGeneratedAlertForOpportunity` : modèle de requête « alerte ouverte » à généraliser par `ruleType` + cible.
- `backend/src/Automation/` -- règles événementielles existantes (`AutomationRuleInterface`, `DomainEventBus`) à **ne pas** modifier ; les règles planifiables forment un ensemble distinct (interface propre, tag d'autoconfiguration, une classe par type).
- `backend/src/Entity/Opportunity.php:151-180` -- `updatedAt` / `onPreUpdate` ; ajouter `statusChangedAt` (setter de statut).
- `backend/src/Entity/Contact.php` -- ajouter `presentationSent`.
- `backend/src/Repository/FollowUpRepository.php`, `ContactRepository.php`, `OpportunityRepository.php` -- requêtes des candidats.
- `backend/src/Controller/MessageTemplateRenderController.php` + `config/services.yaml` (`public: true`) -- modèle de contrôleur sous `/api`.
- `frontend/src/components/layout/Sidebar.tsx:24` -- sous-entrées de Paramètres ; ajouter « Règles ».
- `frontend/src/app/settings/messages/` -- modèle de page Paramètres.
- `frontend/src/app/alerts/page.tsx` -- page Alertes (bouton + bandeau de résultat).
- `frontend/src/components/contacts/ContactFormFields.tsx`, `app/contacts/new`, `app/contacts/[id]` -- case « Présenté ».
- Dernière migration : `Version20261020140000`.

## Tasks & Acceptance

**Execution:**
- [x] `backend/src/Entity/AlertRule.php` + dépôt + `Enum/AlertRuleType.php` -- `type` (unique), `enabled`, `delayDays` (≥ 0), `priority` ; API `GET` collection/item et `PATCH` seulement.
- [x] `backend/src/Entity/Alert.php`, `Contact.php`, `Opportunity.php` -- `ruleType`, `presentationSent`, `statusChangedAt`.
- [x] `backend/migrations/VersionXXXX.php` -- table `alert_rule` + 3 lignes par défaut, colonnes ajoutées, `status_changed_at = updated_at`.
- [x] `backend/src/Automation/Scheduled/*` -- une classe par type + `AlertRuleEvaluator` (déduplication, création, compteur).
- [x] `backend/src/Controller/AlertRuleEvaluationController.php` -- `POST /api/alert_rules/evaluate` → `{created, byRule}`.
- [x] `backend/tests/**` -- toute la matrice + `statusChangedAt` + validation.
- [x] `frontend/src/lib/api/*`, `lib/labels.ts` -- types, clients, libellés des règles.
- [x] `frontend/src/app/settings/rules/page.tsx` + `Sidebar.tsx` -- Paramètres > Règles : une ligne par règle (activée, délai, priorité, « Enregistrer »).
- [x] `frontend/src/app/alerts/page.tsx` -- bouton « Vérifier les règles », résultat, rechargement.
- [x] `frontend/src/components/contacts/ContactFormFields.tsx` (+ pages) -- case « Je me suis présenté ».
- [x] `README.md` -- règles, bouton, migration.

**Acceptance Criteria:**
- Given Paramètres > Règles, when on désactive une règle et enregistre, then la vérification suivante ne crée aucune alerte de ce type.
- Given deux clics successifs sur « Vérifier les règles », then le second ne crée aucune alerte.
- Given le formulaire contact, when on coche « Je me suis présenté » et enregistre, then la valeur est conservée et la règle 3 ignore ce contact.

## Verification

**Commands:**
- `docker compose exec app php bin/phpunit` -- suite verte.
- `docker compose exec frontend npm run build` -- OK.
- Stack de test (:18081) : migration (3 règles créées, `status_changed_at` rempli), PATCH règle (dont 422), `POST /api/alert_rules/evaluate` deux fois (création puis 0), désactivation, contact `presentationSent`.

## Implementation Notes

- Migration `backend/migrations/Version20261020153000.php` (non destructive) : table `alert_rule` + 3 règles actives (90/15/7 j, `medium`), `alert.rule_type`, `contact.presentation_sent` (faux), `opportunity.status_changed_at` (= `updated_at`).
- Évaluation dans `backend/src/Automation/Scheduled/` (interface taguée, une classe par type, `AlertRuleEvaluator`), endpoint `POST /api/alert_rules/evaluate` (`public: true` dans `services.yaml`). Règles événementielles existantes inchangées.
- Choix d'interprétation : relance terminée sans `completedAt` ignorée ; seules les relances liées directement au contact comptent ; alertes créées sans échéance.
- `delayDays` borné à 3650 (évite le dépassement INT).
- Vérifié sur :18081 : 3 règles créées, PATCH -1 et 3651 → 422, POST/DELETE → 405, evaluate deux fois (créations puis 0), déduplication (en cours bloque, terminée/annulée débloque), règle désactivée, `presentationSent`, `statusChangedAt`. PHPUnit 523 tests verts, build et ESLint OK. Pas de vérification dans un navigateur (frontend branché sur l'API réelle non migrée).

## Spec Change Log

## Review Triage Log

Passe 1 (blind-hunter, edge-case-hunter, verification-gap). Un GET en lecture sur l'API réelle (:18080) a été fait par l'agent d'implémentation, contraire à la consigne ; signalé à Simon.

| # | Finding | Verdict | Preuve / route |
|---|---------|---------|----------------|
| 1 | `delayDays` sans borne haute → 500 | low | patch : `LessThanOrEqual(3650)`, test, 422 vérifié sur :18081 |
| 2 | Câblage tag/évaluateur non testé | medium | patch : `AlertRuleEvaluatorWiringTest` (une règle par type) |
| 3 | Requêtes de déduplication et de candidats seulement simulées | medium | defer : pas de harnais BDD ; vérifiées à la main sur :18081 |
| 4 | Premier clic : alerte pour chaque contact non présenté | false | décision approuvée (règles livrées actives), prévenu dans la spec |
| 5 | `statusChangedAt` initialisé à `updated_at` | false | décision de la spec |
| 6 | Alertes non fermées quand la condition disparaît | maybe-false | defer : non spécifié ; question posée à Simon |
| 7 | Relances liées seulement à l'opportunité ignorées | maybe-false | defer : interprétation à valider par Simon |
| 8 | Délai non entier → 400 et non 422 | low | rejeté : saisie via un champ numérique entier |
| 9 | Évaluations concurrentes → doublons | low | rejeté : bouton unique, mono-utilisateur |
| 10 | Brouillon écrasé pendant l'enregistrement d'une ligne | low | rejeté : cas marginal |
| 11 | Libellés des alertes limités aux 100 premiers contacts | low | rejeté : comportement existant de la page |
| 12 | Type de règle sans classe ignoré silencieusement | low | rejeté : couvert par le test de câblage |
| 13 | Fuseau de `MAX(completedAt)` | maybe-false | rejeté : conteneurs en UTC, faible impact |
| 14 | `ruleType` et `presentationSent` non affichés dans les listes | low | rejeté : non demandé |
| 15 | Code défensif redondant, `findAll()` | low | rejeté : sans effet |
| 16 | Fixtures ne déclenchant les règles qu'avec N = 0 | low | rejeté : vérification faite avec des délais adaptés |
