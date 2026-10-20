---
title: 'Alertes « À traiter » par défaut'
type: 'feature'
created: '2026-10-20'
status: 'done'
baseline_commit: '01ba7713b754f09a70d6f6558f69917d5c29edcc'
route: 'oneshot'
review_loop_iteration: 0
context:
  - '{project-root}/AGENTS.md'
  - '{project-root}/frontend/AGENTS.md'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** La liste des alertes s'ouvre sur tous les statuts, alors que Simon veut d'abord voir celles qui restent à traiter (comme pour les relances).

**Approach:** Décision de Simon (2026-10-20) : le filtre de statut de la liste des alertes gagne une option « À traiter (à faire + en cours) », sélectionnée par défaut, qui envoie `status[]=todo&status[]=in_progress`. « Tous les statuts » et chaque statut restent choisissables. Aucun changement backend.

</frozen-after-approval>

## Implementation Notes

- `frontend/src/app/alerts/page.tsx` : option `open` (« À traiter (à faire + en cours) ») par défaut → `status[]=todo&status[]=in_progress` ; liste vide : « Aucune alerte à traiter. » quand ce filtre est actif.
- Vérifié : build, ESLint ; sur :18081, le filtre renvoie uniquement `todo`/`in_progress` (6 alertes sur 10). Pas de vérification dans un navigateur.

## Review Triage Log

- Spec non finalisée — low — patch : notes ci-dessus.
- Liste vide trompeuse avec le filtre par défaut — low — patch.
- « Alerte ouverte » définie à plusieurs endroits — low — rejeté : sans divergence aujourd'hui.
- Page vide après « Marquer comme faite » sur la dernière alerte d'une page — low — rejeté : comportement antérieur.
- Filtre non conservé dans l'URL — low — rejeté : non demandé.
- Type du filtre répété, libellé en dur — low — rejeté : cosmétique.
- Pas de test automatisé — low — rejeté : pas d'outillage frontend (déjà dans deferred-work).
