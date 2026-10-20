---
title: 'Contacts inactifs'
type: 'feature'
created: '2026-10-20'
status: 'done'
baseline_commit: '06d4f14b460ad17bbb05316334b9fc1125639847'
route: 'dispatch'
review_loop_iteration: 0
context:
  - '{project-root}/AGENTS.md'
  - '{project-root}/frontend/AGENTS.md'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Les contacts avec lesquels Simon ne travaille plus encombrent la liste des contacts, les listes de choix et les règles d'alerte, sans moyen de les écarter sans les supprimer.

**Approach:** Un statut actif/inactif sur le contact. Un contact inactif est masqué par défaut de la liste, n'est plus proposé dans les listes de choix et est ignoré par les règles d'alerte. Il reste consultable et réactivable.

## Boundaries & Constraints

**Always:**
- Décisions de Simon (2026-10-20) :
  - Case « Contact actif » dans le formulaire contact (création et modification), cochée par défaut. Décocher puis enregistrer le rend inactif ; recocher le réactive.
  - Liste des contacts : filtre « Actifs » (défaut), « Inactifs », « Tous ». Badge « Inactif » sur les contacts inactifs affichés.
  - Règles d'alerte : les règles 1 (contact sans relance récente) et 3 (présentation non envoyée) ignorent les contacts inactifs ; la règle 2 (opportunité sans réponse) ignore une opportunité dont le contact est inactif (une opportunité sans contact reste évaluée).
  - Listes de choix de contact (formulaires relance, opportunité, alerte) : les inactifs ne sont pas proposés, sauf le contact déjà lié à l'élément modifié, affiché avec le suffixe « (inactif) ».
- Champ `active` (booléen, défaut vrai), lisible et modifiable par l'API, filtrable (`?active=true|false`). Migration : tous les contacts existants actifs.
- Rien d'autre ne change pour un contact inactif : fiche, relances, opportunités, alertes et messages restent accessibles ; ses alertes ouvertes ne sont pas fermées.

**Never:**
- Supprimer ou archiver des données.
- Modifier la logique des règles au-delà de l'exclusion des inactifs.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Désactiver | fiche → décocher « Contact actif » → Enregistrer | « Contact modifié », contact absent de la liste par défaut | N/A |
| Filtre Inactifs | liste → « Inactifs » | contacts inactifs, avec badge | N/A |
| Réactiver | fiche d'un inactif → cocher → Enregistrer | de retour dans la liste par défaut | N/A |
| Règle 1 / 3 | contact inactif remplissant la condition | aucune alerte | N/A |
| Règle 2 | opportunité `waiting` depuis 20 j, contact inactif | aucune alerte | N/A |
| Règle 2 sans contact | opportunité `waiting` depuis 20 j, sans contact | alerte (inchangé) | N/A |
| Sélecteur | nouvelle relance | inactifs non proposés | N/A |
| Sélecteur déjà lié | relance liée à un contact devenu inactif | contact proposé « Nom (inactif) », sélectionné | N/A |
| API | `GET /api/contacts?active=false` | uniquement les inactifs | N/A |

</frozen-after-approval>

## Code Map

- `backend/src/Entity/Contact.php` -- ajouter `active` (+ `BooleanFilter`).
- `backend/src/Repository/ContactRepository.php`, `OpportunityRepository.php`, `FollowUpRepository.php` -- requêtes des candidats utilisées par `backend/src/Automation/Scheduled/{ContactFollowUpStaleRule,ContactNotPresentedRule,OpportunityNoAnswerRule}.php`.
- `backend/tests/Automation/Scheduled/*`, `tests/Entity/ContactTest.php` -- matrice des règles.
- `backend/src/DataFixtures/AppFixtures.php` -- au moins un contact inactif.
- Dernière migration : `Version20261020153000`.
- Frontend : `lib/api/types.ts` (`Contact.active`), `components/contacts/ContactFormFields.tsx` + `app/contacts/{new,[id]}/page.tsx` (case), `app/contacts/page.tsx` (filtre, badge, `components/ui/Badge.tsx`), `components/{follow-ups/FollowUpFormFields,opportunities/OpportunityFormFields,alerts/AlertFormFields}.tsx` et leurs pages (sélecteurs ; les listes de contacts sont chargées par les pages via `contactsApi.list`).

## Tasks & Acceptance

**Execution:**
- [x] `backend/src/Entity/Contact.php` + migration -- champ `active`, filtre API, contacts existants actifs.
- [x] Dépôts et règles `Automation/Scheduled` -- exclusion des inactifs.
- [x] `backend/tests/**`, fixtures -- matrice.
- [x] Frontend contacts -- case, filtre par défaut « Actifs », badge.
- [x] Sélecteurs de contact -- masquer les inactifs sauf le contact lié.
- [x] `README.md` -- contacts inactifs.

**Acceptance Criteria:**
- Given un contact inactif, when on ouvre sa fiche, then elle reste consultable et modifiable.
- Given la liste des contacts ouverte sans action, then seuls les actifs sont listés.

## Verification

**Commands:**
- `docker compose exec app php bin/phpunit` -- vert.
- `docker compose exec frontend npm run build` et lint -- OK.
- Stack de test (:18081) : migration, PATCH `active`, `?active=false`, évaluation des règles avec un contact inactif (délais à 0).

## Implementation Notes

- Migration `backend/migrations/Version20261020170000.php` (non destructive) : `contact.active` (vrai par défaut). À lancer par Simon, puis `cache:clear` (sur `app-test`, `active` était ignoré jusqu'au vidage du cache).
- Exclusion des inactifs dans les trois règles `Automation/Scheduled` (et `findNotPresented`). Règles événementielles (relance échue, score ≥ 80) inchangées : hors du périmètre décidé.
- Sélecteurs : contacts chargés avec `active: true`, contact déjà lié réinjecté via `ensureOption`, suffixe « (inactif) ». Badge « Inactif » dans la liste et sur la fiche.
- Fixtures : Élise Fontaine inactive.
- Vérifié : PHPUnit 529 tests, build, lint ; sur :18081 : `schema:validate`, `?active=false|true`, PATCH aller-retour, POST sans `active` → vrai, règles à délai 0 (inactifs exclus, opportunité sans contact conservée). Pas de vérification dans un navigateur.

## Spec Change Log

## Review Triage Log

Passe 1 (blind-hunter, edge-case-hunter, verification-gap).

- Règles événementielles (relance échue, score ≥ 80) actives pour les inactifs — maybe-false — defer : hors périmètre décidé, question posée à Simon.
- Test « inactif » sans témoin actif, test de réactivation qui ne teste que le setter — low — patch.
- Fiche contact sans badge « Inactif » — low — patch.
- Filtre `active` et groupes de sérialisation sans test automatisé — medium — defer : pas de harnais HTTP ; vérifiés à la main sur :18081.
- `ensureOption` non testé — low — rejeté : pas d'outillage frontend (déjà dans deferred-work).
- Échec de la page d'édition si le contact lié est introuvable — false : la clé étrangère passe à NULL quand le contact est supprimé.
- Préremplissage `?contact=` d'un inactif sur une création — low — rejeté : action explicite depuis le contact.
- Double filtre dans `findNotPresented` et dans la règle, `findAll()` — low — rejeté : sans effet.
- Filtre de liste non conservé dans l'URL — low — rejeté : non demandé.
- README incomplet (listes de résolution des noms, alertes déjà ouvertes) — low — rejeté : la spec indique que rien d'autre ne change.
