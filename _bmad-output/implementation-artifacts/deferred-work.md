- source_spec: `_bmad-output/implementation-artifacts/spec-nettoyage-contacts-entreprises-alertes.md`
  summary: Mettre en place un harnais de tests BDD/HTTP sur la stack de test (KernelTestCase ou tests API sur db-test) pour couvrir CompanyRepository::findOneByNameIgnoreCase, le câblage du processor d'unicité, le contrat /api/alerts et les migrations.
  evidence: Toute la suite PHPUnit est unitaire. Retirer TRIM/LOWER, la branche excludeId ou le processor d'un verbe laisserait la suite verte. Aujourd'hui, seule une vérification manuelle sur :18081 le détecterait.
- source_spec: `_bmad-output/implementation-artifacts/spec-nettoyage-contacts-entreprises-alertes.md`
  summary: Ajouter un outillage de tests frontend pour couvrir useInlineCompany (compensation, doublon 422) et lib/prefill.ts.
  evidence: frontend/package.json n'a aucun script de test. Supprimer l'appel companiesApi.remove de compensation ne ferait échouer aucun test.

## Deferred from: code review (2026-10-20)

- Dates de relances/alertes saisies avant la correction UTC (2676c90) : stockées en « faux UTC » (heure locale étiquetée +00:00), elles s'affichent décalées de +1/+2 h et sont réenregistrées décalées si on les édite. Une migration de recalage (`AT TIME ZONE 'Europe/Paris'`) ne doit viser que les lignes saisies via le formulaire, pas celles créées par l'automatisation ou les fixtures (déjà en vrai UTC) : la sélection des lignes est à décider avec Simon. [frontend/src/lib/format.ts:36-54]
- Retrait des pages Entreprises (1e24609) : plus aucune UI pour renommer ou supprimer une entreprise, ni pour éditer site, secteur, localisation, description, notes, source. Avec la règle d'unicité, une entreprise mal nommée ne peut plus être corrigée ni recréée. À confirmer avec Simon (édition légère depuis la fiche contact ?). [frontend/src/app/companies/ (supprimé)]
- Pas de tests pour toDateTimeInputValue / fromDateTimeInputValue (aller-retour avec TZ=Europe/Paris) ni pour defaultFollowUpScheduledAt (13:59, 14:00, fin de mois) : le frontend n'a aucun runner de test (voir l'entrée outillage frontend plus haut). [frontend/src/lib/format.ts, frontend/src/lib/prefill.ts]
- source_spec: `_bmad-output/implementation-artifacts/spec-recalage-dates-faux-utc.md`
  summary: Couvrir la migration de recalage Version20261020095221 par un test BDD (été et hiver, date limite, alerte auto, due_date NULL, aller-retour up/down).
  evidence: Aucun harnais BDD. Inverser :from et :to, passer < en <= ou retirer le filtre sur le titre ne ferait échouer aucun test. Vérifié à la main sur db-test uniquement.
- source_spec: `_bmad-output/implementation-artifacts/spec-messages-predefinis.md`
  summary: Aligner automatiquement la liste des indicateurs du frontend (lib/messagePlaceholders.ts) sur MessageTemplateRenderer::PLACEHOLDERS (endpoint d'exposition ou test croisé).
  evidence: Les deux listes sont maintenues à la main ; un indicateur ajouté côté backend n'apparaîtrait pas dans l'éditeur, sans qu'aucun test échoue.
- source_spec: `_bmad-output/implementation-artifacts/spec-messages-predefinis.md`
  summary: Couvrir MessageTemplateRepository::findOneByTitleIgnoreCase et la route GET /api/message_templates/{id}/render par des tests sur base/HTTP.
  evidence: Le processor est testé avec un repository simulé et le contrôleur est appelé directement ; retirer LOWER/TRIM, inverser excludeId ou casser la route laisserait la suite verte.
- source_spec: `_bmad-output/implementation-artifacts/spec-relances-icones-et-terminees.md`
  summary: Mettre à jour FR-9 du PRD : plus de suppression ni de message pour une relance terminée, et actions en icônes dans la liste.
  evidence: FR-9 indique encore qu'une relance se supprime « quel que soit son statut ».
- source_spec: `_bmad-output/implementation-artifacts/spec-relances-icones-et-terminees.md`
  summary: Décider avec Simon si une relance terminée peut être rouverte (repassée « À faire » ou « Annulée »), ce qui la rend de nouveau supprimable.
  evidence: Le formulaire de la fiche et PATCH /api/follow_ups/{id} permettent de changer le statut d'une relance terminée ; le refus 409 ne porte que sur DELETE.
- source_spec: `_bmad-output/implementation-artifacts/spec-relances-icones-et-terminees.md`
  summary: Tester le branchement de FollowUpDeletionGuardProcessor sur l'opération Delete de FollowUp (test kernel ou HTTP).
  evidence: Le test unitaire construit le processor à la main ; retirer `processor:` de l'opération Delete laisserait la suite verte.
- source_spec: `_bmad-output/implementation-artifacts/spec-opportunites-tjm-unique-sans-url.md`
  summary: Tester automatiquement la migration Version20261020140000 (quatre cas COALESCE, aller-retour up/down) et les filtres API dailyRate/score.
  evidence: Aucun harnais de test de migration ni HTTP ; inverser le COALESCE ou retirer `score` du RangeFilter laisserait la suite verte.
- source_spec: `_bmad-output/implementation-artifacts/spec-opportunites-tjm-unique-sans-url.md`
  summary: Vérifier l'absence de TJM négatifs dans la vraie base (non vérifié, medium si présents) ; sinon les opportunités concernées renvoient 422 à la prochaine modification.
  evidence: Les anciens champs n'avaient aucune contrainte de signe ; la migration copie les valeurs telles quelles.
- source_spec: `_bmad-output/implementation-artifacts/spec-regles-alertes-configurables.md`
  summary: Tester sur base les requêtes des règles d'alerte (hasOpenAlertForRule, findLastCompletedAtByContact, findContactIdsWithTodoFollowUp, findNotPresented).
  evidence: Tous les tests simulent les dépôts ; retirer le filtre de statut de la déduplication ou compter les relances annulées laisserait la suite verte.
- source_spec: `_bmad-output/implementation-artifacts/spec-regles-alertes-configurables.md`
  summary: Décider avec Simon si une alerte de règle se clôt d'elle-même quand sa condition disparaît, et si les relances liées à une opportunité du contact comptent pour la règle « Contact sans relance récente ».
  evidence: Non spécifié ; aujourd'hui l'alerte reste ouverte et seules les relances liées directement au contact comptent.
- source_spec: `_bmad-output/implementation-artifacts/spec-notifications-unifiees.md`
  summary: Décider si l'analyse IA d'une opportunité passe par les notifications (succès vert, erreurs en rouge) ou garde son bandeau.
  evidence: `app/opportunities/[id]/page.tsx` (`handleAnalyze`) écrit l'opportunité mais garde `analysisError` en bandeau et n'émet aucune notification de succès.
