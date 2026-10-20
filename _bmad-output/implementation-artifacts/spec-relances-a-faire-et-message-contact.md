---
title: 'Relances « À faire » par défaut, bouton Message sur les contacts'
type: 'feature'
created: '2026-10-20'
status: 'done'
baseline_commit: '5d80dc63494616f1e5998e395ca890429c060f05'
route: 'oneshot'
review_loop_iteration: 0
context:
  - '{project-root}/AGENTS.md'
  - '{project-root}/frontend/AGENTS.md'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** La liste des relances s'ouvre sur tous les statuts alors que Simon veut d'abord voir celles à faire. Les messages pré-construits ne sont accessibles que depuis les relances, pas depuis un contact.

**Approach:** Décisions de Simon (2026-10-20) :
- La liste des relances s'ouvre avec le filtre de statut sur « À faire » ; « Tous les statuts » et les autres valeurs restent choisissables.
- Un bouton « Message » ouvre la popup de messages existante (`MessageDialog`) pour le contact : icône dans la colonne Actions de la liste des contacts (style `IconButton`, bleu, comme sur les relances) et bouton « Message » sur la fiche contact. Le message est rempli avec les données de ce contact.
Aucun changement backend.

</frozen-after-approval>

## Implementation Notes

- `frontend/src/app/follow-ups/page.tsx` : statut initial `todo`. `app/contacts/page.tsx` : icône « Message » (colonne Actions). `app/contacts/[id]/page.tsx` : bouton « Message ». `components/follow-ups/MessageDialog.tsx` : commentaires généralisés (relances et contacts).
- La fiche contact utilise le contact enregistré, comme la fiche relance.
- Vérifié : build et ESLint. Pas de vérification dans un navigateur.

## Review Triage Log

- Modifications non enregistrées ignorées par le message — low — rejeté : même règle que la fiche relance (contact enregistré).
- Tuile Dashboard « Relances en retard » ouvrant la liste sans filtre « En retard » — low — defer.
- Filtre non conservé entre deux visites — low — rejeté : non demandé.
- Texte « relance » si aucun contact n'est passé — false : les écrans contact passent toujours un id.
- `MessageDialog` rangé sous `follow-ups/` — low — rejeté : déplacement sans effet.
- Popup sans nom du contact — low — rejeté : il faudrait une nouvelle prop, non demandée.
- « Message » actif pendant une suppression — low — rejeté : cas marginal, erreur affichée en notification.
- Spec et vérification non consignées — low — patch : notes ci-dessus.
- Aucun test automatisé — low — rejeté : pas d'outillage frontend (déjà dans deferred-work).
