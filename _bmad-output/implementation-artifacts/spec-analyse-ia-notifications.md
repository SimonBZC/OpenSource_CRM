---
title: 'Analyse IA : retours via les notifications'
type: 'feature'
created: '2026-10-20'
status: 'done'
baseline_commit: '05b6c3403b09537df450fdd053d7d428640a4436'
route: 'oneshot'
review_loop_iteration: 0
context:
  - '{project-root}/AGENTS.md'
  - '{project-root}/frontend/AGENTS.md'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Sur la fiche opportunité, « Analyser avec l'IA » est la seule écriture restée hors du système de notifications : pas de confirmation de succès, erreurs dans un bandeau propre.

**Approach:** Décision de Simon (2026-10-20) : l'analyse IA passe par les notifications. Succès : notification verte « Opportunité analysée » (libellé « élément + action », comme les autres écritures), le panneau de résultat reste affiché. Erreurs : notification rouge persistante, avec le même texte qu'aujourd'hui (message dédié quand l'IA n'est pas configurée, sinon `toDisplayMessage`). Le bandeau `analysisError` disparaît. Aucun changement backend.

</frozen-after-approval>

## Implementation Notes

- `frontend/src/app/opportunities/[id]/page.tsx` : `notify.success("Opportunité analysée")` ; erreurs (dont `ai_not_configured`) en `notify.error` ; état et bandeau `analysisError` retirés. Remplace la note « analyse IA hors périmètre » de `spec-notifications-unifiees.md` et tranche l'entrée correspondante de deferred-work.
- `Toaster.tsx` : une notification identique déjà affichée est remplacée au lieu d'être empilée (clics répétés sans clé IA).
- Vérifié : build, ESLint ; sur :18081, `POST /api/opportunities/{id}/analyze` → 503 `ai_not_configured` (chemin du message dédié). Pas de vérification dans un navigateur.

## Review Triage Log

- Spec minimale — false : voie oneshot (Intent + Implementation Notes).
- Vérification non consignée — low — patch : notes ci-dessus.
- Entrée deferred-work non soldée — low — tranchée ici (les entrées existantes ne sont pas modifiées).
- README — low — patch.
- Ligne vide en trop — low — patch.
- Notifications identiques empilées — low — patch : remplacement des doublons.
- Résultat précédent effacé si la réanalyse échoue — low — rejeté : comportement antérieur.
- « Opportunité analysée » même sans champ appliqué — low — rejeté : l'analyse a bien eu lieu, le panneau détaille les champs.
- Erreurs de validation non réinitialisées par l'analyse — low — rejeté : antérieur au changement.
