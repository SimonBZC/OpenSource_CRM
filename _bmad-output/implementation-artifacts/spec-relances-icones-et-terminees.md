---
title: 'Relances : actions en icônes, ni suppression ni message pour une relance terminée'
type: 'feature'
created: '2026-10-20'
status: 'done'
baseline_commit: '2d9668022482667caf35eb0bc0d5a38337a9e6e4'
route: 'oneshot'
review_loop_iteration: 0
context:
  - '{project-root}/AGENTS.md'
  - '{project-root}/frontend/AGENTS.md'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Dans la liste des relances, les actions sont des boutons texte qui encombrent la colonne. Une relance « Terminée » peut encore être supprimée ou servir à préparer un message, ce que Simon ne veut pas.

**Approach:** Les actions de la liste des relances deviennent des icônes (« Marquer comme faite », « Message », « Supprimer »). Pour une relance au statut « Terminée », les actions « Message » et « Supprimer » n'apparaissent ni dans la liste ni sur la fiche. L'API refuse la suppression d'une relance terminée.

Décisions de Simon (2026-10-20) :
- Pour une relance terminée, les actions sont masquées, pas grisées.
- L'API refuse `DELETE /api/follow_ups/{id}` quand la relance est terminée (`done`) ; la règle est couverte par PHPUnit. L'interdiction du message reste une règle d'interface.
- La fiche relance est concernée elle aussi : « Message » et « Supprimer » y sont masqués pour une relance terminée, selon le statut enregistré.
- Une relance « Annulée » garde les deux actions.

Choix d'implémentation : les icônes sont des SVG inline, sans librairie, avec `aria-label` et une infobulle (`title`) reprenant le libellé ; les couleurs actuelles sont conservées (vert, bleu, rouge). Sur la fiche, les boutons restent textuels. Le refus de l'API est un 409 dont le détail est « Une relance terminée ne peut pas être supprimée. », affiché dans le bandeau d'erreur existant.

</frozen-after-approval>

## Implementation Notes

- Backend : `backend/src/State/FollowUpDeletionGuardProcessor.php` (décore `remove_processor`, `ConflictHttpException` 409) branché sur `Delete` dans `backend/src/Entity/FollowUp.php` ; tests `backend/tests/State/FollowUpDeletionGuardProcessorTest.php` (terminée refusée sans suppression ; à faire et annulée transmises).
- Frontend : `frontend/src/components/ui/IconButton.tsx` (SVG inline, `aria-label` + `title`) ; `app/follow-ups/page.tsx` (icônes, Message/Supprimer masqués si `done`) ; `app/follow-ups/[id]/page.tsx` (boutons masqués si le statut enregistré est `done`).
- Vérifié sur :18081 : DELETE d'une relance terminée → 409 « Une relance terminée ne peut pas être supprimée. », relance toujours présente ; DELETE d'une relance annulée → 204. Fixtures rechargées sur `app-test`. PHPUnit 492 tests verts, build et ESLint OK. Pas de vérification visuelle des icônes dans un navigateur.

## Review Triage Log

Passe 1 (blind-hunter). Aucun accès à la vraie base.

- PRD FR-9 dit encore « quel que soit son statut » — medium — defer (modification d'un document de planification).
- Une relance terminée repassée « À faire » redevient supprimable — maybe-false — defer : la réouverture d'une relance terminée n'a pas été tranchée ; question à poser à Simon.
- Branchement du processor sur `Delete` non couvert par PHPUnit — medium — defer : pas de harnais HTTP/kernel (déjà dans deferred-work) ; vérifié à la main sur :18081 (409 puis 204).
- 409 absent de la doc OpenAPI — low — rejeté : il faudrait une configuration OpenAPI dédiée pour un outil mono-utilisateur.
- `aria-label` identiques d'une ligne à l'autre — low — rejeté : Simon, seul utilisateur, n'utilise pas de lecteur d'écran ; l'infobulle reste lisible.
- Pas de focus visible sur `IconButton` — low — patch : `focus-visible:outline`.
- Pas de `disabled` pendant « Marquer comme faite » — low — rejeté : même comportement que le bouton texte d'avant.
- Spec `in-progress` et pas de vérification visuelle — low — spec finalisée ; la vérification visuelle reste à faire par Simon (signalée).
- Commentaires flous sur la partie imposée par l'API — low — patch : commentaires précisés.

- Décision de Simon (2026-10-20) : une relance terminée peut être rouverte ; elle redevient alors supprimable. Comportement voulu, aucune modification. L'entrée correspondante de deferred-work est donc tranchée.
