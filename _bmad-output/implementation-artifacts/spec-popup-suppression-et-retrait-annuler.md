---
title: 'Popup de confirmation de suppression et retrait de « Annuler » sur les relances'
type: 'feature'
created: '2026-10-20'
status: 'done'
baseline_commit: '21643e543e2ef136966220c8f82db762e3383dd0'
route: 'dispatch'
review_loop_iteration: 0
context:
  - '{project-root}/AGENTS.md'
  - '{project-root}/frontend/AGENTS.md'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Les suppressions passent par la boîte `confirm()` native du navigateur, peu lisible et hors du style de l'application. Dans la liste des relances, l'action rapide « Annuler » n'est plus voulue.

**Approach:** Un composant de popup de confirmation réutilisable, au style Tailwind du projet, remplace `confirm()` pour les suppressions. Le bouton « Annuler » de la liste des relances est retiré.

## Boundaries & Constraints

**Always:**
- La popup affiche un titre, un message nommant l'élément, un bouton « Annuler » (secondaire) et un bouton « Supprimer » (style danger rouge du projet).
- Fermeture par « Annuler », par la touche Échap ou par un clic sur le fond. Le focus est placé dans la popup à l'ouverture. Rôle `dialog` et `aria-modal`.
- Pendant la suppression, le bouton « Supprimer » est désactivé et affiche « Suppression... ». En cas d'erreur, la popup se ferme et l'erreur s'affiche dans le bandeau de la page, comme aujourd'hui.
- Le comportement après suppression ne change pas : rechargement de la liste, ou redirection depuis une fiche.

- Décision de Simon : la popup remplace les cinq `confirm()` de suppression (relance depuis la liste et la fiche, opportunité, contact, alerte).
- Décision de Simon : seul le bouton « Annuler » de la liste disparaît. Le statut « Annulée » reste choisissable dans le formulaire et filtrable.

**Never:**
- Ajouter une librairie UI.
- Modifier le backend.
- Changer la suppression elle-même (API, redirections).

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Confirmer | clic Supprimer → popup → Supprimer | élément supprimé, popup fermée | N/A |
| Renoncer | popup → Annuler / Échap / clic sur le fond | rien n'est supprimé, popup fermée | N/A |
| Échec API | popup → Supprimer, l'API répond en erreur | popup fermée, message dans le bandeau | `toDisplayMessage` |
| Liste relances | relance « À faire » | actions « Marquer comme faite » et « Supprimer », plus de « Annuler » | N/A |

</frozen-after-approval>

## Code Map

- `frontend/src/components/ui/` -- Badge, ErrorBanner, FieldError, Pagination ; aucune modale existante. Ajouter `ConfirmDialog.tsx`.
- `frontend/src/app/follow-ups/page.tsx:125-145,270-295` -- `cancelFollowUp` (à retirer avec son bouton), `deleteFollowUp` avec `confirm()`.
- `frontend/src/app/follow-ups/[id]/page.tsx:98-109` -- `handleDelete` avec `confirm()` et état `deleting`.
- `frontend/src/app/opportunities/[id]/page.tsx:111`, `contacts/[id]/page.tsx:113`, `alerts/[id]/page.tsx:87` -- autres `confirm()` de suppression.
- Styles : danger `border-red-300 text-red-700 hover:bg-red-50` ; secondaire `border-black/15` ; primaire `bg-blue-600`.

## Tasks & Acceptance

**Execution:**
- [x] `frontend/src/components/ui/ConfirmDialog.tsx` -- popup contrôlée (`open`, `title`, `message`, `confirmLabel`, `busy`, `onConfirm`, `onCancel`), avec Échap, clic sur le fond et focus -- composant unique.
- [x] `frontend/src/app/follow-ups/page.tsx` -- retirer `cancelFollowUp` et son bouton ; suppression via `ConfirmDialog` (état de la relance visée).
- [x] `app/follow-ups/[id]`, `app/opportunities/[id]`, `app/contacts/[id]`, `app/alerts/[id]` (`page.tsx`) -- remplacer `confirm()` par `ConfirmDialog`.

**Acceptance Criteria:**
- Given le frontend, when on cherche `confirm(` dans `frontend/src`, then plus aucune occurrence.
- Given la liste des relances, when une relance est « À faire », then aucun bouton « Annuler » n'apparaît.

## Implementation Notes

- Matrice vérifiée sur un frontend temporaire (:3001) branché sur l'API de test (:18081) : la confirmation supprime la relance (11 → 10) ; quand la relance a été supprimée entre-temps, l'API répond 404, la popup se ferme et le bandeau affiche « Ressource introuvable ». Les cas Annuler, Échap et clic sur le fond ont été vérifiés sur :3000 sans confirmer. Aucun test automatisé : le frontend n'a pas d'outillage de test (déjà dans deferred-work).
- Pendant la suppression, Annuler, Échap et le clic sur le fond sont ignorés, car la requête ne peut pas être interrompue.

## Spec Change Log

## Review Triage Log

Passe 1 (blind-hunter, edge-case-hunter, verification-gap). Aucun accès à la vraie base.

| # | Finding | Verdict | Preuve / route |
|---|---------|---------|----------------|
| 1 | Double clic rapide, donc deux DELETE et un 404 parasite | low | Réel tant que `busy` n'a pas été re-rendu. Garde `if (deleting) return` : patch. |
| 2 | Pas de piège à focus (Tab sort de la popup) | low | Usage local à la souris. Le correctif (piège à focus ou `<dialog>` natif) est une refonte. Rejeté. |
| 3 | Focus non restauré à la fermeture | low | Accessibilité clavier, usage local. Rejeté. |
| 4 | Page de fond qui défile sous la popup | low | Cosmétique. Rejeté. |
| 5 | Popup bloquée si la requête ne répond jamais | low | API locale. Rejeté. |
| 6 | Nom vide dans le message « » | false | `lastName` et `title` sont `NotBlank` côté API. |
| 7 | Page vide après suppression de la dernière ligne d'une page | low | Préexistant et rare. Rejeté. |
| 8 | Retrait de « Annuler » hors sujet | false | Demandé explicitement par Simon (intention figée). |
| 9 | Composant figé sur la suppression (libellé, couleur) | low | Seul usage actuel. Rejeté. |
| 10 | Erreur affichée hors de la popup, gestion différente entre liste et fiche | low | Comportement conforme à la spec (bandeau de la page). Rejeté. |
| 11 | Écouteur Échap réattaché à chaque rendu, sans stopPropagation | low | Sans effet visible aujourd'hui. Rejeté. |
| 12 | Autres suppressions sans confirmation ? | false | `grep Api.remove(` : les 5 usages passent par la popup. `companiesApi.remove` est la compensation automatique. |
| 13 | Aucun test automatisé | medium (non vérifié) | Pas d'outillage de test frontend, déjà dans deferred-work. Matrice vérifiée à la main sur :3001 + :18081. Différé. |

## Verification

**Commands:**
- `docker compose exec frontend npm run lint` -- expected : succès.
- `docker compose exec frontend npm run build` -- expected : succès.

**Manual checks:**
- Ouvrir et fermer la popup (Annuler, Échap, clic sur le fond) sur la liste des relances et sur une fiche, sans confirmer : le frontend :3000 écrit dans la vraie base. Toute confirmation réelle se fait sur un Next temporaire pointé sur `:18081`.
