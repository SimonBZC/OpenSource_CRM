---
title: 'Recalage des dates enregistrées en faux UTC'
type: 'bugfix'
created: '2026-10-20'
status: 'done'
baseline_commit: '58128b2d4008022dfce739a27b8ae703ffd2e47d'
route: 'dispatch'
review_loop_iteration: 0
context:
  - '{project-root}/AGENTS.md'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Avant le commit 2676c90, les formulaires envoyaient l'heure locale saisie suffixée `+00:00`. Les colonnes `follow_up.scheduled_at` et `alert.due_date` (TIMESTAMP sans fuseau, lues en UTC) contiennent donc l'heure de Paris au lieu de l'heure UTC. Ces dates s'affichent décalées de +1 h ou +2 h selon l'heure d'été.

**Approach:** Une migration Doctrine réversible convertit ces valeurs avec `(col AT TIME ZONE 'Europe/Paris') AT TIME ZONE 'UTC'`, ce qui gère l'heure d'été ligne par ligne. Elle ne touche que les lignes créées avant la mise en service de la correction. Validée sur la stack de test uniquement ; Simon la lance sur sa vraie base.

## Boundaries & Constraints

**Always:**
- Sélection : `created_at < '2026-10-20 09:25:00'` (UTC, soit 11h25 heure de Paris). La vraie base ne contient que des saisies de Simon (aucune fixture).
- Alertes : exclure `title = 'Relancer rapidement — fort potentiel'`, car ces alertes auto-générées sont déjà en vrai UTC. Ignorer les `due_date` NULL.
- `down()` applique la conversion inverse `(col AT TIME ZONE 'UTC') AT TIME ZONE 'Europe/Paris'` sur la même sélection. `created_at` n'étant pas modifié, la sélection est identique.
- Décision de Simon : aucune ancienne ligne n'a été corrigée à la main depuis 11h25, donc toute la sélection est recalée.
- Les constantes (date limite, titre, fuseau) sont écrites en dur dans la migration, sans dépendre du code applicatif.

**Never:**
- Toucher `completed_at` ou `created_at`, déjà en vrai UTC.
- Lancer la migration sur la vraie base (`app`). Uniquement `app-test`.
- Modifier le frontend ou les entités.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Été (CEST) | relance créée avant la limite, `scheduled_at` = 2026-10-20 14:00 | 2026-10-20 12:00 | N/A |
| Hiver (CET) | `scheduled_at` = 2026-11-20 14:00 | 2026-11-20 13:00 | N/A |
| Après la limite | `created_at` ≥ 2026-10-20 09:25 | inchangé | N/A |
| Alerte auto | titre « Relancer rapidement — fort potentiel » | inchangée | N/A |
| Alerte sans échéance | `due_date` NULL | inchangée | N/A |
| Aller-retour | up puis down | valeurs d'origine à l'identique | N/A |

</frozen-after-approval>

## Code Map

- `backend/migrations/Version20261020062459.php` -- migration précédente (renommage task → alert) ; la nouvelle migration doit lui être postérieure.
- `backend/migrations/Version20260821131625.php:26,42` -- `scheduled_at`, `due_date`, `created_at` en `TIMESTAMP(0) WITHOUT TIME ZONE`.
- `backend/src/Automation/Rule/CreateAlertForHighScoreOpportunityRule.php` -- `AUTO_ALERT_TITLE` = « Relancer rapidement — fort potentiel » (à recopier en constante dans la migration, pas à importer).
- Le conteneur PHP tourne en UTC : `created_at` est donc en UTC.

## Tasks & Acceptance

**Execution:**
- [x] `backend/migrations/Version<horodatage>.php` -- `up()` : UPDATE `follow_up` puis `alert` selon la sélection ; `down()` : conversion inverse ; description en français -- recalage réversible.
- [x] Stack de test : insérer des lignes couvrant chaque ligne de la matrice (avec un `created_at` explicite), migrer, vérifier, `down`, vérifier l'identité, `up` à nouveau, puis supprimer les lignes de test -- preuve de la matrice.

**Acceptance Criteria:**
- Given la stack de test migrée, when `doctrine:migrations:migrate` puis `doctrine:schema:validate`, then les deux réussissent.
- Given les lignes de la matrice, when up puis down, then chaque valeur est conforme à la matrice et revient à l'identique.

## Implementation Notes

- Migration `Version20261020095221`. Matrice vérifiée sur la stack de test par des lignes insérées avec un `created_at` explicite : été 14:00 → 12:00, hiver 14:00 → 13:00, ligne à la limite ou après inchangée, alerte auto inchangée, `due_date` NULL inchangée. L'aller-retour up/down/up redonne des données identiques et `schema:validate` est OK. Aucun test automatisé : le dépôt n'a pas de harnais BDD (entrée déjà dans `deferred-work.md`).
- Risque résiduel : une heure inexistante (saut de l'heure d'été en mars) ne fait pas un aller-retour exact. Aucune donnée réelle n'est concernée en pratique.
- Les fixtures de la stack de test, créées avant la date limite, sont elles aussi recalées. Recharger les fixtures ou redémarrer `db-test` les remet en état.

## Spec Change Log

## Review Triage Log

Passe 1 (blind-hunter, edge-case-hunter, verification-gap). Incident : deux relecteurs ont lu la vraie base en lecture seule, en violation d'`AGENTS.md`. Simon en a été informé.

| # | Finding | Verdict | Preuve / route |
|---|---------|---------|----------------|
| 1 | Lignes anciennes éditées après la correction et décalées à nouveau | false | Simon a confirmé qu'aucune ligne n'a été corrigée à la main. Le risque de modification avant l'exécution est documenté dans l'en-tête (patch #7). |
| 2 | Fixtures (vrai UTC) décalées | false | La vraie base ne contient aucune fixture (décision de Simon). Effet limité à `db-test`, déjà noté. |
| 3 | Exclusion par titre fragile (alerte auto renommée, alerte manuelle homonyme) | low | Cas improbable, et le correctif demanderait une colonne de provenance. Rejeté. |
| 4 | `down()` inexact pour une heure inexistante (saut de l'heure d'été) | low | Réel mais très rare. Ajouté à l'en-tête (patch #7). |
| 5 | Aucun test automatisé de la migration | medium (non vérifié) | Aucun harnais BDD dans le dépôt, entrée déjà présente dans `deferred-work.md`. Matrice vérifiée à la main sur `db-test`. Différé. |
| 6 | Pas de garde PostgreSQL | low | Le projet ne tourne que sur PostgreSQL. Rejeté. |
| 7 | Migration ponctuelle, non idempotente, limites non documentées | low | Correction directe de l'en-tête : patch. |
| 8 | Paramètres liés, donc `--dry-run` n'affiche pas le SQL exact | low | Simon doit pouvoir relire le SQL avant de l'exécuter. Valeurs écrites en clair : patch. |
| 9 | Aucun compte des lignes touchées | low | Ajouterait du code pour un correctif ponctuel. Rejeté. |

## Verification

**Commands:**
- `docker compose exec app-test php bin/console doctrine:migrations:migrate -n` -- expected : succès.
- `docker compose exec app-test php bin/console doctrine:migrations:migrate prev -n` puis `migrate -n` -- expected : aller-retour sans erreur.
- `docker compose exec app-test php bin/console doctrine:schema:validate` -- expected : OK.
- `docker compose exec app php bin/phpunit` -- expected : suite verte (aucun code applicatif touché).
