---
title: 'Icônes d’action uniformes dans les tableaux'
type: 'refactor'
created: '2026-10-20'
status: 'done'
baseline_commit: 'd524affca0dc7f34ab4712ca656fb5e185b09db6'
route: 'oneshot'
review_loop_iteration: 0
context:
  - '{project-root}/AGENTS.md'
  - '{project-root}/frontend/AGENTS.md'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Les colonnes Actions des tableaux n'ont pas le même style : icônes sans bordure dans Contacts, boutons texte dans Alertes et Paramètres > Messages, icônes bordées dans Relances.

**Approach:** Décision de Simon (2026-10-20) : tous les tableaux adoptent le style des Relances (`IconButton` : icône 16 px dans un bouton bordé, couleur selon l'action, infobulle et `aria-label`). Concernés : Contacts (« Nouvelle opportunité », « Nouvelle relance », en bleu, restent des liens), Alertes (« Marquer comme faite », en vert), Paramètres > Messages (« Supprimer », en rouge). Le comportement des actions ne change pas. Opportunités n'a pas de colonne Actions et ne change pas.

</frozen-after-approval>

## Implementation Notes

- `frontend/src/components/ui/IconButton.tsx` : variante lien (`href`, rendue avec `next/link`) et icônes mallette/cloche. Tableaux convertis : `app/contacts/page.tsx`, `app/alerts/page.tsx`, `app/settings/messages/page.tsx`, chaque cellule d'actions dans `<div className="flex gap-2">` comme Relances.
- Vérifié : `npm run build` (typecheck) et ESLint OK. Aucun test frontend (pas d'outillage) ; pas de vérification visuelle dans un navigateur, à faire par Simon.

## Review Triage Log

Passe 1 (blind-hunter).

- Notes d'implémentation vides — low — patch : notes ajoutées.
- Pas de vérification visuelle — low — signalé à Simon.
- Alertes et Messages sans conteneur `flex gap-2` — low — patch.
- Double clic sur « Marquer comme faite » (Alertes) — low — rejeté : même comportement que le bouton texte d'avant.
- Nom `IconButton` alors qu'il peut rendre un lien — low — rejeté : renommer ajouterait du bruit pour un gain nul.
- `href=""` rendu comme un lien — false : aucun appelant ne passe de `href` vide.
- Branche lien non testée — low — rejeté : pas d'outillage de test frontend (déjà dans deferred-work).
- Ordre de `ICON_PATHS` et commentaires des icônes perdus — low — rejeté : cosmétique.
