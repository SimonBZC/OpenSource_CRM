---
title: 'Opportunités : TJM unique, suppression de l’URL, score retiré de la liste'
type: 'refactor'
created: '2026-10-20'
status: 'done'
baseline_commit: 'f27ae4ea0be78acb4eb2c46839c064fa16f23e25'
route: 'dispatch'
review_loop_iteration: 0
context:
  - '{project-root}/AGENTS.md'
  - '{project-root}/frontend/AGENTS.md'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Une opportunité porte un TJM min et un TJM max ainsi qu'une URL, dont Simon n'a pas l'usage. La liste des opportunités propose aussi un filtre et une colonne Score qu'il ne veut plus.

**Approach:** Remplacer `dailyRateMin`/`dailyRateMax` par un seul `dailyRate` (« TJM »), supprimer définitivement le champ `url`, et retirer le filtre et la colonne Score de la liste des opportunités.

## Boundaries & Constraints

**Always:**
- Décisions de Simon (2026-10-20) :
  - `url` est supprimé partout (entité, API, formulaires, fiche, import, analyse IA, fixtures) et la colonne est supprimée en base : les URL existantes sont perdues.
  - TJM unique `dailyRate` (entier, mêmes contraintes de positivité que les anciens champs). Pour les données existantes : `dailyRate = dailyRateMax`, sinon `dailyRateMin`.
  - Analyse IA : le résultat LLM garde `tjmMin`/`tjmMax` (schéma et panneau d'analyse inchangés) ; `OpportunityAnalysisApplier` remplit `dailyRate` avec `tjmMax ?? tjmMin`, uniquement si `dailyRate` est vide (même politique « ne pas écraser » qu'avant), et le signale `dailyRate` dans `appliedFields`.
  - Filtre de la liste : « TJM ≥ » et « TJM ≤ » (bornes facultatives) sur `dailyRate` ; tri/RangeFilter API sur `dailyRate`.
  - Score : retirer de la liste des opportunités les champs score min/max et la colonne Score. Le score reste sur la fiche, dans l'API (RangeFilter `score` utilisé par le Dashboard, `score[gte]=70`) et pour l'automatisation.
- Import : sans URL, le doublon se détecte par la règle titre + entreprise existante uniquement.
- Les tests existants qui portent sur `url` ou `dailyRateMin/Max` changent parce que le fonctionnel change (demande de Simon) ; chaque test modifié garde l'intention de la règle qu'il couvre.
- Migration unique, réversible au mieux : `down` recrée `daily_rate_min`, `daily_rate_max` (= `daily_rate`) et `url` (NULL). Lancée par Simon sur la vraie base après sauvegarde.

**Never:**
- Toucher au score lui-même (calcul, stockage, Dashboard, règle d'alerte ≥ 80).
- Modifier le contrat de sortie du LLM.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Migration min+max | 500 / 600 | `daily_rate` = 600 | N/A |
| Migration min seul | 500 / NULL | 500 | N/A |
| Migration max seul | NULL / 650 | 650 | N/A |
| Migration vide | NULL / NULL | NULL | N/A |
| IA, TJM vide | `dailyRate` NULL, IA 500–600 | `dailyRate` = 600, `appliedFields` contient `dailyRate` | N/A |
| IA, min seul | IA 500 / null | 500 | N/A |
| IA, TJM déjà saisi | `dailyRate` 550, IA 500–600 | inchangé, absent d'`appliedFields` | N/A |
| API écriture | POST/PATCH avec `url` ou `dailyRateMin` | champ ignoré (inconnu), pas de colonne | N/A |
| TJM négatif | `dailyRate` = -1 | 422 | erreur sous le champ |
| Import doublon | même titre + entreprise | DUPLICATE | N/A |

</frozen-after-approval>

## Code Map

- `backend/src/Entity/Opportunity.php:64-69,106-118,274-320` -- `url`, `dailyRateMin/Max`, RangeFilter/OrderFilter, éventuelle contrainte min ≤ max à retirer.
- `backend/src/Ai/OpportunityAnalysisApplier.php:19-80` -- application `dailyRateMin/Max` → `dailyRate`. `Ai/Dto/OpportunityAnalysisInput.php`, `OpportunityAnalysisInputFactory.php`, `OpportunityAnalyzer.php` (prompt) -- retirer `url` et passer le TJM unique en entrée. `Ai/Dto/OpportunityAnalysisResult.php` -- inchangé (`tjmMin`/`tjmMax`).
- `backend/src/Controller/OpportunityAnalysisController.php` -- normalisation de la réponse.
- `backend/src/Import/RawOpportunityData.php`, `ManualImporter.php:23-131`, `Repository/OpportunityRepository.php:23-35` (`findOneByUrl` à supprimer), `Command/ImportOpportunityCommand.php:30,69` (exemples JSON).
- `backend/src/DataFixtures/AppFixtures.php` -- fixtures d'opportunités.
- Tests : `tests/Ai/*`, `tests/Controller/OpportunityAnalysisControllerTest.php`, `tests/Import/*`, `tests/Entity/OpportunityTest.php`, `tests/Automation/Rule/CreateAlertForHighScoreOpportunityRuleTest.php`.
- Frontend : `lib/api/types.ts:107-108,214-215`, `lib/format.ts:65` (`formatDailyRate`), `components/opportunities/OpportunityFormFields.tsx`, `OpportunityAnalysisPanel.tsx`, `app/opportunities/page.tsx` (filtres 65-121,283-320, colonne Score 447, TJM 419-450), `app/opportunities/[id]/page.tsx`, `app/opportunities/new/page.tsx`.
- `README.md` -- mentions de TJM min/max, URL, filtre score.
- Dernière migration : `Version20261020101953`.

## Tasks & Acceptance

**Execution:**
- [x] `backend/src/Entity/Opportunity.php` -- `dailyRate` remplace min/max, `url` supprimé, filtres mis à jour.
- [x] `backend/migrations/VersionXXXX.php` -- ajout `daily_rate`, `UPDATE … = COALESCE(daily_rate_max, daily_rate_min)`, suppression de `daily_rate_min`, `daily_rate_max`, `url` ; `down` documenté.
- [x] `backend/src/Ai/*` + contrôleur -- entrée sans `url`, TJM unique ; applier `tjmMax ?? tjmMin`.
- [x] `backend/src/Import/*`, `Repository/OpportunityRepository.php`, `Command/ImportOpportunityCommand.php`, `DataFixtures/AppFixtures.php` -- plus d'`url`, TJM unique.
- [x] `backend/tests/**` -- adapter les tests concernés ; couvrir la matrice (applier, validation, import).
- [x] `frontend/src/lib/**`, `components/opportunities/**`, `app/opportunities/**` -- champ « TJM » unique, plus d'URL, filtres TJM ≥/≤, sans filtre ni colonne Score dans la liste.
- [x] `README.md` -- refléter les changements.

**Acceptance Criteria:**
- Given la liste des opportunités, when on l'affiche, then ni filtre score ni colonne Score, et une colonne TJM à valeur unique.
- Given le formulaire d'opportunité, when on crée ou modifie, then un seul champ « TJM » et aucun champ URL.
- Given `grep -rn "dailyRateMin\|dailyRateMax\|getUrl\|findOneByUrl" backend/src frontend/src`, then aucune occurrence.

## Verification

**Commands:**
- `docker compose exec app php bin/phpunit` -- suite verte.
- `docker compose exec frontend npm run build` -- OK.
- Stack de test : `doctrine:migrations:migrate` sur `app-test` depuis une base contenant des opportunités min/max (fixtures chargées avant la nouvelle migration), contrôle des quatre cas de la matrice ; `migrations:migrate prev` puis re-migrate ; `doctrine:schema:validate` ; POST/PATCH/filtres `dailyRate[gte|lte]` sur :18081.

## Implementation Notes

- Migration `backend/migrations/Version20261020140000.php` vérifiée sur `db-test` (quatre cas de la matrice, 11 lignes de fixtures, `migrate prev` puis re-migrate, `schema:validate`). Destructive (URL perdues) : Simon la lance sur la vraie base après sauvegarde, puis `docker compose exec app php bin/console cache:clear` (sur `app-test`, la collection omettait `dailyRate` jusqu'au vidage du cache).
- Positivité : les anciens champs n'avaient pas de contrainte ; `#[Assert\PositiveOrZero]` sur `dailyRate` (comme `tjmMin`/`tjmMax` dans `OpportunityAnalysisResult`) pour satisfaire la ligne « TJM négatif → 422 ». 0 est accepté.
- Tests adaptés (fonctionnel modifié à la demande de Simon) : tests de validation d'URL supprimés ; doublon par URL remplacé par doublon titre + entreprise ; URL invalide à l'import remplacée par TJM négatif.
- Vérifié sur :18081 : POST/PATCH avec `url`/`dailyRateMin` ignorés, -1 → 422, filtres `dailyRate[gte|lte]`, `order[dailyRate]`, `score[gte]=70` OK, import avec `daily_rate`. Interface non vérifiée dans un navigateur.

## Spec Change Log

## Review Triage Log

Passe 1 (blind-hunter, edge-case-hunter, verification-gap). Aucun accès à la vraie base.

| # | Finding | Verdict | Preuve / route |
|---|---------|---------|----------------|
| 1 | « undefined €/j » pour une opportunité sans TJM | high | API Platform omet les champs nuls (`skip_null_values`) ; `formatDailyRate` ne testait que `null`. patch : `undefined` traité comme absent |
| 2 | Message de violation en anglais | low | patch : message « Le TJM doit être positif ou nul. », assertion ajoutée, vérifié sur :18081 |
| 3 | Champs TJM sans `min`/`step` (formulaire et filtres) | low | patch : `min={0}`, `step={1}` |
| 4 | Clés d'import `daily_rate_min/max`/`url` ignorées sans avertissement | low | rejeté : import manuel ponctuel, mapping supplémentaire non demandé |
| 5 | Valeurs négatives existantes → 422 à la prochaine édition | maybe-false | defer : dépend du contenu de la vraie base (inaccessible) ; à vérifier par Simon avant migration |
| 6 | Mapping COALESCE de la migration sans test automatisé | medium | defer : pas de harnais de test de migration ; vérifié à la main sur db-test |
| 7 | Filtres API `dailyRate`/`score` non testés automatiquement | medium | defer : pas de harnais HTTP (déjà dans deferred-work) ; vérifiés sur :18081 |
| 8 | `cache:clear` après migration non documenté | low | patch : consigne donnée à Simon avec la migration |
| 9 | Doublons d'import non détectés sans URL | low | rejeté : conséquence assumée de la décision de Simon |
| 10 | Filtres TJM masquant les opportunités sans TJM, bornes inversées | low | rejeté : comportement attendu d'un filtre par plage |
| 11 | Prompt IA : cas sans TJM non testé, formulation | low | rejeté : contrat LLM inchangé |
| 12 | `formatDailyRate(0)` affiche « 0 €/j » | false | cohérent avec `PositiveOrZero` : 0 est une valeur saisie |
