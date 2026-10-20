---
title: 'Messages pré-construits (Paramètres > Messages) et popup « Message » sur les relances'
type: 'feature'
created: '2026-10-20'
status: 'done'
baseline_commit: '41c91dd690836a90697621cd9ed50af7145f2320'
route: 'dispatch'
review_loop_iteration: 0
context:
  - '{project-root}/AGENTS.md'
  - '{project-root}/frontend/AGENTS.md'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Simon réécrit à la main les mêmes messages de relance pour chaque contact. Il n'existe aucun modèle de message réutilisable, et la page Paramètres n'est qu'un placeholder « bientôt ».

**Approach:** Une ressource « modèle de message » (titre + corps contenant des indicateurs `{%x%}`), gérée dans Paramètres > Messages. Un rendu côté backend remplace les indicateurs par les données d'un contact ; premier usage : un bouton « Message » sur les relances ouvre une popup qui affiche le message rempli, prêt à copier.

## Boundaries & Constraints

**Always:**
- Décisions de Simon (2026-10-20) :
  - Paramètres contient un sous-menu « Messages » : liste, création, modification, suppression (via `ConfirmDialog`).
  - Le sous-menu est une sous-entrée « Messages », indentée sous « Paramètres » dans la barre latérale ; `/settings` redirige vers `/settings/messages`.
  - Titre unique, sans tenir compte de la casse ni des espaces en début et fin : refus 422 « Un message porte déjà ce titre. » sur `title`.
  - Éditeur : textarea du corps à gauche ; à droite, la liste des champs utilisables avec leur libellé. Un clic sur un champ insère l'indicateur à la position du curseur.
  - Indicateurs (syntaxe exacte, sensible à la casse) : `{%prenom%}` = prénom du contact, `{%nom%}` = nom du contact, `{%entreprise%}` = nom de l'entreprise du contact (jamais celle de la relance).
  - Bouton « Message » dans les actions de chaque ligne de la liste des relances et sur la fiche relance. La popup propose les messages par titre, puis affiche le message rempli avec un bouton « Copier » (presse-papiers). Aucun envoi réel.
  - Donnée manquante (pas de contact, prénom vide, contact sans entreprise) : l'indicateur reste visible tel quel et un avertissement liste les données manquantes.
- Le rendu est un service PHP pur et réutilisable (`MessageTemplateRenderer`), sans dépendance à la relance : il prend un corps et un `?Contact`.
- Le rendu ne persiste rien (GET).
- Toute erreur API est affichée (`ErrorBanner` / `toDisplayMessage`).

**Never:**
- Envoi d'email, `mailto:` ou appel réseau externe.
- Autres indicateurs que les trois ci-dessus ; indicateur inconnu (`{%poste%}`) laissé tel quel, non signalé.
- Dépendance UI ou de templating (Twig, etc.) ajoutée pour le rendu.
- Modification des entités existantes.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Complet | corps `Bonjour {%prenom%} {%nom%} de {%entreprise%}`, contact Jean Dupont / Acme | `Bonjour Jean Dupont de Acme`, `missing: []` | N/A |
| Prénom vide | contact sans prénom (null ou espaces) | `{%prenom%}` conservé, `missing: ["prenom"]` | N/A |
| Sans entreprise | contact sans entreprise | `{%entreprise%}` conservé, `missing: ["entreprise"]` | N/A |
| Sans contact | relance sans contact (`contact` absent) | corps inchangé, `missing` = indicateurs présents dans le corps | N/A |
| Indicateur répété | `{%nom%}` deux fois | remplacé partout, `nom` listé une seule fois s'il manque | N/A |
| Indicateur absent du corps | corps sans `{%entreprise%}`, contact sans entreprise | `entreprise` non listé dans `missing` | N/A |
| Modèle inconnu | `GET /api/message_templates/999/render` | 404 | JSON problème, bandeau |
| Contact inconnu | `?contact=999` | 404 | JSON problème, bandeau |
| Titre en doublon | POST/PATCH avec un titre existant (casse/espaces différents) | 422 sur `title` | message sous le champ |
| Titre/corps vide | POST/PATCH avec `title` ou `body` vide | 422 | erreurs sous les champs |
| Aucun modèle | popup ouverte, 0 modèle | message « Aucun message » + lien vers Paramètres > Messages | N/A |

</frozen-after-approval>

## Code Map

- `backend/src/Entity/FollowUp.php` -- modèle d'entité API Platform (groupes `x:read`/`x:write`, Assert, `createdAt` non inscriptible) à reproduire.
- `backend/src/State/CompanyUniqueNameProcessor.php` + `CompanyRepository::findOneByNameIgnoreCase` + `tests/State/CompanyUniqueNameProcessorTest.php` -- modèle exact de la règle d'unicité (LOWER/TRIM, `excludeId`, `previous_data` sur PUT) à reproduire pour `title`.
- `backend/src/Controller/OpportunityAnalysisController.php` -- modèle de contrôleur Symfony sous `/api` avec `errorResponse` JSON problème ; à reproduire pour `render`.
- `backend/src/Entity/Contact.php` -- `getFirstName()`, `getLastName()`, `getCompany()?->getName()`.
- `backend/migrations/` -- dernière : `Version20261020095221`. Générer via `doctrine:migrations:diff` sur `app-test`.
- `backend/src/DataFixtures/AppFixtures.php` -- ajouter deux modèles pour la stack de test.
- `frontend/src/lib/api/resources.ts`, `types.ts` -- `createResourceClient`, types écriture `Partial<Pick<…>>`.
- `frontend/src/components/ui/ConfirmDialog.tsx` -- modèle de popup (Échap, fond, focus, `role="dialog"`).
- `frontend/src/components/layout/Sidebar.tsx` -- retirer `comingSoon` de Paramètres ; ajouter la sous-entrée « Messages » (`/settings/messages`), toujours visible.
- `frontend/src/app/settings/page.tsx` -- placeholder `ComingSoon` à remplacer.
- `frontend/src/app/follow-ups/page.tsx:263-281` -- actions de ligne ; `follow-ups/[id]/page.tsx:134-150` -- en-tête de fiche avec « Supprimer ».

## Tasks & Acceptance

**Execution:**
- [x] `backend/src/Entity/MessageTemplate.php` + `Repository/MessageTemplateRepository.php` -- entité `title` (255, NotBlank), `body` (TEXT, NotBlank), `createdAt` ; ressource `/api/message_templates` CRUD, `OrderFilter` sur `title` ; `findOneByTitleIgnoreCase`.
- [x] `backend/src/State/MessageTemplateUniqueTitleProcessor.php` -- unicité du titre sur Post/Put/Patch.
- [x] `backend/src/Messaging/MessageTemplateRenderer.php` + `RenderedMessage.php` -- `render(string $body, ?Contact $contact): RenderedMessage(body, missing)` ; constante `PLACEHOLDERS` (clé → libellé).
- [x] `backend/src/Controller/MessageTemplateRenderController.php` -- `GET /api/message_templates/{id}/render?contact={id}` → `{title, body, missing}` ; 404 modèle/contact.
- [x] `backend/migrations/VersionXXXX.php` -- création de `message_template`.
- [x] `backend/tests/Messaging/MessageTemplateRendererTest.php`, `tests/Entity/MessageTemplateTest.php`, `tests/Controller/MessageTemplateRenderControllerTest.php`, `tests/State/MessageTemplateUniqueTitleProcessorTest.php` -- toute la matrice + validation.
- [x] `frontend/src/lib/api/types.ts`, `resources.ts` -- `MessageTemplate`, `RenderedMessage`, `messageTemplatesApi`, `renderMessageTemplate(id, contactId?)`.
- [x] `frontend/src/lib/messagePlaceholders.ts` -- liste des indicateurs (clé, libellé), miroir de `PLACEHOLDERS`.
- [x] `frontend/src/components/layout/Sidebar.tsx` -- sous-entrée indentée « Messages » ; « Paramètres » sans « bientôt ».
- [x] `frontend/src/app/settings/page.tsx` (redirection), `settings/messages/{page,new/page,[id]/page}.tsx` + `components/settings/MessageTemplateForm.tsx` -- liste, nouveau, édition, suppression ; éditeur deux colonnes avec insertion au curseur.
- [x] `frontend/src/components/follow-ups/MessageDialog.tsx` -- popup : sélection par titre, rendu, avertissement des manquants, « Copier » (confirmation « Copié »), « Fermer ».
- [x] `frontend/src/app/follow-ups/page.tsx`, `follow-ups/[id]/page.tsx` -- bouton « Message » ouvrant `MessageDialog` avec le contact enregistré de la relance.

**Acceptance Criteria:**
- Given un message créé dans Paramètres > Messages, when on clique « Message » sur une relance liée à un contact complet, then la popup affiche le texte sans aucun `{%…%}` et « Copier » le place dans le presse-papiers.
- Given le curseur au milieu du corps, when on clique « Prénom » dans la colonne de droite, then `{%prenom%}` est inséré à cet endroit.
- Given la barre latérale, when on l'affiche, then « Paramètres » n'a plus la mention « bientôt ».

## Design Notes

- Rendu côté backend pour que la règle de substitution soit couverte par PHPUnit (le frontend n'a pas d'outillage de test) et réutilisable par d'autres écrans : il suffit d'un `contact`.
- Fiche relance : la popup utilise le contact enregistré, pas une modification non sauvegardée du formulaire.
- Libellés des manquants : `prenom` → « prénom », `nom` → « nom », `entreprise` → « entreprise ».

## Verification

**Commands:**
- `docker compose exec app php bin/phpunit` -- expected: suite verte.
- `docker compose exec frontend npm run build` -- expected: build et typecheck OK.
- Stack de test (:18081) : CRUD `/api/message_templates`, 422 sur titre vide, `render` avec contact complet, sans prénom, sans contact, modèle/contact inconnus (404).

**Manual checks:**
- Frontend temporaire branché sur :18081 : création/édition/suppression d'un message, insertion au curseur, popup depuis la liste et la fiche, copie.

## Implementation Notes

- Migration `backend/migrations/Version20261020101953.php` (création de `message_template` seule), générée par `migrations:diff` sur `app-test`. À lancer par Simon sur la vraie base : `docker compose exec app php bin/console doctrine:migrations:migrate`.
- `config/services.yaml` : `MessageTemplateRenderController` déclaré `public: true`, comme `OpportunityAnalysisController` (sinon 500).
- Rendu en une passe (`strtr`) : une valeur contenant `{%…%}` n'est pas réinterprétée. `?contact=` vide = sans contact ; `?contact=abc` = 404.
- Copie : `navigator.clipboard.writeText`, repli `execCommand('copy')` sur le textarea en lecture seule (le panneau navigateur de test refuse l'API presse-papiers). À confirmer dans un vrai navigateur.
- `http.ts` affiche « Ressource introuvable » pour tout 404 (détail backend ignoré) : laissé tel quel.
- Ligne « Aucun modèle » de la matrice vérifiée à la main (frontend :3001 → :18081) ; pas d'outillage de test frontend (déjà dans deferred-work).
- `components/layout/ComingSoon.tsx` n'est plus utilisé, conservé.
- Vérifié sur :18081 : CRUD, doublons 422 (POST/PATCH, casse/espaces), titre/corps vides 422, rendu (complet, sans contact, sans prénom ni entreprise, répété, inconnu), 404 modèle/contact. UI vérifiée sur :3001 branché sur :18081.

## Spec Change Log

## Review Triage Log

Passe 1 (blind-hunter, edge-case-hunter, verification-gap). Aucun accès à la vraie base.

| # | Finding | Verdict | Preuve / route |
|---|---------|---------|----------------|
| 1 | Titre/corps faits d'espaces acceptés | medium | `NotBlank` sans normalizer ; contredit la ligne « Titre/corps vide → 422 ». patch : `NotBlank(normalizer: 'trim')`, tests ajoutés, 422 vérifié sur :18081 |
| 2 | Titre stocké non trimé (≠ `Company::setName`) | low | rejeté : cosmétique ; le `trim()` faisait échouer un test du processor, retiré |
| 3 | Pas d'index unique en base / course POST concurrents | low | rejeté : outil mono-utilisateur, même choix que Company |
| 4 | Listes plafonnées à 100 messages | low | rejeté : volume irréaliste, il faudrait ajouter de la pagination |
| 5 | Pas de retour visuel après enregistrement sur l'édition | false | même comportement que `follow-ups/[id]/page.tsx:84-91` |
| 6 | README non mis à jour | low | patch : paragraphe « Messages pré-construits » |
| 7 | Route render absente de l'OpenAPI | low | rejeté : il faudrait une fabrique OpenAPI dédiée |
| 8 | Liste d'indicateurs dupliquée front/back | low | defer : pas d'outillage de test frontend |
| 9 | `nom` vide non testé | low | patch : `testBlankLastNameKeepsPlaceholderAndIsReported` |
| 10 | DQL LOWER/TRIM et routage non couverts par la suite | medium | defer : pas de harnais BDD/HTTP (déjà dans deferred-work) ; vérifiés à la main sur :18081 |
| 11 | Popup sans piège de focus ni verrouillage du défilement | low | rejeté : même comportement que `ConfirmDialog` |
| 12 | Détails des 404 jamais affichés | low | rejeté : `http.ts` existant, noté dans Implementation Notes |
| 13 | Retour à « Choisir un message… » pendant un rendu : rendu obsolète affiché | low | patch : le compteur de requêtes est incrémenté aussi pour `null` |
| 14 | id/contact > INT4 → 500 | low | rejeté : inatteignable depuis l'UI |
| 15 | Bords tab/newline : `trim` PHP vs `TRIM` DQL | low | rejeté : cas marginal |
| 16 | Suppression d'un message déjà supprimé : ligne restante | low | rejeté : même comportement que la liste des relances |
| 17 | `ComingSoon.tsx` désormais inutilisé | low | rejeté : le mécanisme `comingSoon` de la barre latérale est conservé pour de futures sections |
