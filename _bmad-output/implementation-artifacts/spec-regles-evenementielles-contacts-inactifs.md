---
title: 'Règles automatiques : ignorer les contacts inactifs'
type: 'feature'
created: '2026-10-20'
status: 'done'
baseline_commit: 'fc0c8b7d897e428c3bcf56fd3b909e62eb5501fa'
route: 'oneshot'
review_loop_iteration: 0
context:
  - '{project-root}/AGENTS.md'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Les deux règles automatiques déclenchées par événement réagissent encore aux contacts inactifs, contrairement aux trois règles configurables.

**Approach:** Décision de Simon (2026-10-20) : la règle « relance échue » (`NotifyDueFollowUpRule`) n'émet plus de notification pour une relance dont le contact est inactif, et la règle « score ≥ 80 » (`CreateAlertForHighScoreOpportunityRule`) ne crée plus d'alerte pour une opportunité dont le contact est inactif. Une relance ou une opportunité sans contact reste traitée comme aujourd'hui (même choix que la règle « Opportunité sans réponse »). Aucun changement frontend.

</frozen-after-approval>

## Implementation Notes

- `backend/src/Automation/Rule/NotifyDueFollowUpRule.php` et `CreateAlertForHighScoreOpportunityRule.php` : retour anticipé si le contact est inactif (docblocks mis à jour). Tests ajoutés dans `tests/Automation/Rule/` (inactif ignoré, sans contact ou contact actif traité). README mis à jour. Tranche l'entrée correspondante de deferred-work.
- Vérifié : PHPUnit vert. Aucun endpoint modifié ; l'IA est désactivée sur la stack de test, la règle du score n'y est pas déclenchable.

## Review Triage Log

- README, docblocks, spec non finalisée — low — patch.
- Test inactif sans preuve d'arrêt avant requête — low — patch : `expects(self::never())` sur le dépôt.
- Compteur de `app:automation:check-due-followups` incluant les relances ignorées — low — rejeté : la commande compte les relances échues, pas les notifications.
- Relance sans contact mais liée à une opportunité dont le contact est inactif — low — rejeté : la règle porte sur le contact de la relance, comme décidé.
- Pas de test explicite « opportunité sans contact » — low — rejeté : couvert par les tests existants (opportunités sans contact).
- Tests redondants, pas de rattrapage après réactivation — low — rejeté : règle événementielle par nature.
