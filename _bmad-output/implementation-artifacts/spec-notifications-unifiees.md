---
title: 'Notifications unifiées de succès et d’erreur'
type: 'feature'
created: '2026-10-20'
status: 'done'
baseline_commit: '441b3ac946ec957843b71d647c157d7df983b1fa'
route: 'dispatch'
review_loop_iteration: 0
context:
  - '{project-root}/AGENTS.md'
  - '{project-root}/frontend/AGENTS.md'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Après un enregistrement, une modification ou une suppression, l'application ne confirme presque jamais la réussite, et les rares retours (« Enregistré », « Copié », bandeau de « Vérifier les règles ») ont chacun leur forme. Les erreurs d'action s'affichent dans des bandeaux propres à chaque page.

**Approach:** Un système de notifications flottantes unique, utilisé par toutes les écritures de l'application pour confirmer un succès ou signaler une erreur.

## Boundaries & Constraints

**Always:**
- Décisions de Simon (2026-10-20) :
  - Notifications flottantes en bas à droite, empilées. Succès en vert, disparition automatique après 4 s. Erreur en rouge, visible jusqu'à fermeture. Croix de fermeture sur chacune.
  - Une notification émise juste avant une redirection (ex. création → fiche, suppression → liste) s'affiche sur la page d'arrivée.
  - Les erreurs d'action (création, modification, suppression, marquer comme faite, vérifier les règles, rendu/copie de message) passent en notification rouge, avec le texte de `toDisplayMessage`. Les erreurs de validation (422) restent sous les champs. Les erreurs de chargement d'une page restent dans son `ErrorBanner`.
  - Toutes les écritures confirment leur réussite. Libellé « élément + action », accordé : « Contact créé », « Relance modifiée », « Opportunité supprimée », « Alerte créée », « Message créé », « Règle enregistrée », « Relance marquée comme faite », « Alerte marquée comme faite », « Message copié ». « Vérifier les règles » : « 3 alertes créées » ou « Aucune nouvelle alerte ».
- Les retours locaux remplacés disparaissent : « Enregistré » des règles, « Copié » de la popup Message, bandeau de résultat de la page Alertes.
- Accessibilité : succès en `role="status"`, erreurs en `role="alert"`.
- Composant et contexte uniques (ex. `components/ui/Toaster.tsx` + hook `useNotify`), montés une seule fois dans `app/layout.tsx`.

**Never:**
- Ajouter une librairie de notifications.
- Modifier le backend ou les messages d'erreur de `lib/api/errors.ts`.
- Afficher une erreur API silencieusement (règle AGENTS.md) : aucun `catch` ne doit avaler une erreur d'action.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Création | « Nouveau contact » → Enregistrer | redirection vers la fiche + « Contact créé » | N/A |
| Modification | fiche relance → Enregistrer | « Relance modifiée » | N/A |
| Suppression depuis fiche | fiche opportunité → Supprimer → confirmer | retour à la liste + « Opportunité supprimée » | N/A |
| Échec API | suppression refusée (409 relance terminée) | notification rouge avec le détail, reste affichée | fermeture par la croix |
| Validation | titre vide | erreur sous le champ, aucune notification | N/A |
| Plusieurs | deux actions rapides | deux notifications empilées | N/A |

</frozen-after-approval>

## Code Map

- `frontend/src/app/layout.tsx` -- monter le fournisseur (composant client).
- `frontend/src/components/ui/ErrorBanner.tsx` -- conservé pour les erreurs de chargement.
- `frontend/src/lib/api/errors.ts` -- `toDisplayMessage`, `ApiValidationError`.
- Pages à brancher (états `actionError`/`error` d'action, `router.push` après écriture) : `app/{contacts,opportunities,follow-ups,alerts}/{new,[id]}/page.tsx`, `app/{follow-ups,alerts}/page.tsx` (marquer comme faite, suppression, vérifier les règles), `app/settings/messages/{page,new/page,[id]/page}.tsx`, `app/settings/rules/page.tsx`, `components/follow-ups/MessageDialog.tsx`, `components/contacts/useInlineCompany.ts` (message de compensation : notification rouge).

## Tasks & Acceptance

**Execution:**
- [x] `frontend/src/components/ui/Toaster.tsx` (+ hook) -- pile, durées, fermeture, rôles ARIA, survie à la navigation côté client.
- [x] `frontend/src/app/layout.tsx` -- montage.
- [x] Pages et composants de la Code Map -- succès et erreurs d'action via le hook ; retrait des retours locaux remplacés.
- [x] `README.md` -- une ligne sur les notifications.

**Acceptance Criteria:**
- Given chaque écriture listée, when elle réussit, then une notification verte au libellé décidé apparaît puis disparaît.
- Given `grep -rn "actionError" frontend/src`, then aucun bandeau d'erreur d'action ne subsiste.

## Verification

**Commands:**
- `docker compose exec frontend npm run build` et ESLint -- OK.

**Manual checks:**
- Frontend temporaire (:3001) branché sur la stack de test (:18081) : un scénario par ligne de la matrice, au moins une écriture par entité.

## Implementation Notes

- `frontend/src/components/ui/Toaster.tsx` (fournisseur + `useNotify`), monté dans `app/layout.tsx` : il survit à la navigation côté client, donc une notification émise avant `router.push` s'affiche à l'arrivée. Au plus 5 notifications, colonne défilante, pleine largeur sur petit écran.
- « Vérifier les règles » affiche désormais « N alertes créées » / « Aucune nouvelle alerte », sans le détail par règle (libellé décidé).
- Erreur d'enregistrement d'une règle préfixée par le nom de la règle.
- Analyse IA de l'opportunité laissée hors périmètre (absente de la Code Map) : bandeau `analysisError` conservé, pas de notification de succès.
- Vérifié : build, ESLint ; frontend temporaire :3001 → :18081, une écriture par entité et chaque ligne de la matrice (création → fiche, modification, suppression → liste, 409 en rouge persistant, 422 sous les champs, empilement). Fixtures de test rechargées ensuite.

## Spec Change Log

## Review Triage Log

Passe 1 (blind-hunter, edge-case-hunter, verification-gap).

- Fiche contact : erreur de chargement partiel plus affichée — medium — patch : `ErrorBanner` rétabli pour le chargement.
- Pile illimitée, débordement en haut de l'écran — low — patch : 5 au plus, `max-h` + défilement.
- Débordement sur écran étroit — low — patch : `left-4` / `sm:left-auto`.
- Erreur de règle sans nom de règle — low — patch : préfixe.
- Commentaire déplacé (`follow-ups/new`) — low — patch.
- Détail par règle retiré de « Vérifier les règles » — false : libellé décidé par Simon.
- Analyse IA non migrée — maybe-false — defer : à trancher par Simon.
- Erreurs sans contexte d'action — low — rejeté : texte `toDisplayMessage` décidé.
- Régions ARIA, pause au survol, Échap — low — rejeté : utilisateur unique, sans lecteur d'écran.
- README « copie » comptée comme écriture — low — rejeté : formulation sans effet.
- Aucun test automatisé du Toaster — medium — defer : pas d'outillage de test frontend (déjà dans deferred-work).
