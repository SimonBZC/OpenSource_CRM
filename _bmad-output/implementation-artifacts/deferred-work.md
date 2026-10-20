- source_spec: `_bmad-output/implementation-artifacts/spec-nettoyage-contacts-entreprises-alertes.md`
  summary: Mettre en place un harnais de tests BDD/HTTP sur la stack de test (KernelTestCase ou tests API sur db-test) pour couvrir CompanyRepository::findOneByNameIgnoreCase, le câblage du processor d'unicité, le contrat /api/alerts et les migrations.
  evidence: Toute la suite PHPUnit est unitaire. Retirer TRIM/LOWER, la branche excludeId ou le processor d'un verbe laisserait la suite verte. Aujourd'hui, seule une vérification manuelle sur :18081 le détecterait.
- source_spec: `_bmad-output/implementation-artifacts/spec-nettoyage-contacts-entreprises-alertes.md`
  summary: Ajouter un outillage de tests frontend pour couvrir useInlineCompany (compensation, doublon 422) et lib/prefill.ts.
  evidence: frontend/package.json n'a aucun script de test. Supprimer l'appel companiesApi.remove de compensation ne ferait échouer aucun test.
