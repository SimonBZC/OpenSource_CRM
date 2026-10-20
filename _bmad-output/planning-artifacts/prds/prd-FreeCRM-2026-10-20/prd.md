---
title: "FreeCRM — Nettoyage contacts, entreprises et alertes"
status: final
created: 2026-10-20
updated: 2026-10-20
---

# PRD : FreeCRM — Nettoyage contacts, entreprises et alertes

## 0. Objet du document

Ce PRD cadre une itération de nettoyage de FreeCRM, outil local et mono-utilisateur de prospection freelance de Simon. Il s'appuie sur l'application existante (voir `README.md` et `AGENTS.md`) et ne décrit que les changements. Les fonctionnalités sont regroupées, avec des exigences numérotées FR-1 à FR-N. Toutes les hypothèses initiales ont été confirmées par Simon le 2026-10-20.

## 1. Vision

Saisir un contact doit être rapide. Aujourd'hui, si l'entreprise du contact n'existe pas encore, il faut quitter le formulaire, créer l'entreprise ailleurs, puis revenir. Depuis la liste des contacts, lancer une opportunité ou programmer une relance demande aussi de passer par d'autres pages et de ressaisir le contact.

Cette itération rapproche les actions du contact : l'entreprise se crée depuis le formulaire du contact, et chaque ligne de la liste des contacts propose directement « nouvelle opportunité » et « nouvelle relance ». Elle clarifie aussi le vocabulaire : les « tâches » deviennent des « alertes ».

## 2. Utilisateur

Simon, freelance, seul utilisateur de l'outil, en local.

**Jobs to be done**
- Enregistrer un nouveau contact et son entreprise en une seule saisie.
- Éviter de créer deux fois la même entreprise.
- Passer d'un contact à une opportunité ou une relance en un clic.

**Parcours clé**
- **UJ-1. Simon rencontre un prospect d'une entreprise inconnue de son CRM.** Il ouvre « Nouveau contact », tape le nom de l'entreprise et clique sur « Rechercher » pour vérifier qu'elle n'existe pas déjà. Elle n'existe pas : il renseigne le secteur et enregistre. Le contact et l'entreprise sont créés ensemble. De retour sur la liste des contacts, il clique sur « Nouvelle relance » sur la ligne du contact. Le formulaire de relance s'ouvre avec le contact et l'entreprise déjà renseignés ; il fixe la date et enregistre.
  - **Cas limite :** l'entreprise existe déjà (même nom, casse différente). Après le clic sur « Rechercher », un message rouge s'affiche sous le champ, et Simon choisit l'entreprise existante dans la liste déroulante.

## 3. Glossaire

- **Contact** — Personne enregistrée dans le CRM, rattachée à zéro ou une Entreprise. Pour cette itération, tout Contact est considéré comme un client ; la notion de client dédiée viendra plus tard.
- **Entreprise** — Organisation, identifiée par son nom. Un nom est unique, sans tenir compte de la casse.
- **Opportunité** — Mission potentielle, liée à une Entreprise et/ou un Contact.
- **Relance** — Action de suivi datée, liée à une Entreprise, un Contact et/ou une Opportunité.
- **Alerte** — Nouveau nom de l'ancienne « Tâche ». Élément à traiter, avec un statut, une priorité et une échéance, lié à une Opportunité et/ou un Contact.
- **Doublon d'entreprise** — Nom saisi qui correspond à une Entreprise existante, sans tenir compte de la casse.

## 4. Fonctionnalités

### 4.1 Créer l'entreprise depuis le formulaire contact

**Description :** dans les formulaires de création et d'édition d'un Contact, Simon peut soit choisir une Entreprise existante (liste déroulante actuelle, inchangée), soit en créer une nouvelle avec son nom et son secteur d'activité. Avant de créer, il tape le nom et clique sur « Rechercher » pour vérifier que l'Entreprise n'existe pas déjà. La recherche sert uniquement à cette vérification ; en v1 elle passe par ce bouton, la vérification en direct à la saisie viendra plus tard. Réalise UJ-1.

#### FR-1 : Vérifier si une entreprise existe déjà

Simon peut taper un nom d'Entreprise dans le formulaire Contact et cliquer sur « Rechercher » pour savoir si une Entreprise de ce nom existe déjà.

**Conséquences (testables) :**
- Le bouton « Rechercher » est placé à côté du champ « Nom de l'entreprise ».
- La comparaison porte sur le nom exact, sans tenir compte de la casse ni des espaces en début et fin de nom.
- Si l'Entreprise existe, un message rouge sous le champ indique qu'elle existe déjà (voir FR-3).
- Si elle n'existe pas, une indication discrète confirme que le nom est disponible.

#### FR-2 : Créer l'entreprise avec le contact

Simon peut créer une nouvelle Entreprise (nom obligatoire, secteur d'activité facultatif) depuis le formulaire Contact, sans quitter la page.

**Conséquences (testables) :**
- Quand aucune Entreprise existante n'est choisie et qu'un nom est saisi, un champ « Secteur d'activité » apparaît.
- À l'enregistrement, l'Entreprise est créée puis le Contact y est rattaché. Si la création du Contact échoue, l'Entreprise n'est pas créée non plus.
- L'Entreprise créée apparaît dans `/companies`, avec son nom et son secteur.

#### FR-3 : Signaler un doublon d'entreprise

Le système signale un Doublon d'entreprise et empêche sa création.

**Conséquences (testables) :**
- Si le nom saisi correspond à une Entreprise existante sans tenir compte de la casse (« Acme » = « ACME »), un message rouge sous le champ indique qu'elle existe déjà.
- La création est refusée ; Simon choisit alors l'Entreprise existante dans la liste déroulante.
- Le contrôle s'applique aussi à l'enregistrement du Contact, même si Simon n'a pas cliqué sur « Rechercher » : l'enregistrement est refusé avec le même message rouge.
- L'API refuse aussi la création d'une Entreprise en doublon (POST /api/companies et création depuis le Contact), pas seulement l'interface.
- Les espaces en début et fin de nom sont ignorés dans la comparaison.

### 4.2 Colonne Actions dans la liste des contacts

**Description :** la liste `/contacts` gagne une colonne « Actions » en fin de tableau. Chaque ligne y propose de créer une Opportunité ou une Relance pour ce Contact. Réalise UJ-1.

#### FR-4 : Créer une opportunité depuis un contact

Simon peut, depuis une ligne de la liste des Contacts, ouvrir la création d'une Opportunité pour ce Contact.

**Conséquences (testables) :**
- La colonne Actions est la dernière colonne et contient une action « Nouvelle opportunité ».
- Le formulaire de création d'Opportunité s'ouvre avec le Contact déjà renseigné, ainsi que son Entreprise s'il en a une.
- Les autres champs restent vides ; Simon peut modifier les champs pré-remplis.

#### FR-5 : Créer une relance depuis un contact

Simon peut, depuis une ligne de la liste des Contacts, ouvrir la création d'une Relance pour ce Contact.

**Conséquences (testables) :**
- La colonne Actions contient une action « Nouvelle relance ».
- Le formulaire de création de Relance s'ouvre avec le Contact déjà renseigné, ainsi que son Entreprise s'il en a une.

### 4.3 Renommer « Tâches » en « Alertes »

**Description :** la notion de Tâche devient Alerte, partout : interface, API, code et base de données. Le comportement ne change pas, et les Tâches existantes deviennent des Alertes sans perte de données.

#### FR-6 : Renommer dans l'interface

**Conséquences (testables) :**
- Le menu affiche « Alertes » et pointe vers `/alerts` (liste, création, détail). `/tasks` n'existe plus.
- Le dashboard affiche « Alertes » à la place des « tâches à effectuer ».
- Aucun libellé « tâche » ne subsiste dans l'interface.

#### FR-7 : Renommer dans l'API et le code

**Conséquences (testables) :**
- La ressource API devient `/api/alerts`, avec les mêmes champs, filtres et comportements ; `/api/tasks` répond 404.
- L'entité, les enums (statut, priorité), le repository, les fixtures et les tests sont renommés.
- La règle d'automatisation « opportunité notée 80 ou plus → création automatique » crée désormais une Alerte, avec le même déclencheur.

#### FR-8 : Migrer les données existantes

**Conséquences (testables) :**
- Une migration Doctrine renomme la table et les colonnes, et conserve toutes les Tâches existantes (nombre identique avant et après, mêmes valeurs).
- C'est Simon qui lance la migration sur sa vraie base (règle de `AGENTS.md`) ; elle est d'abord validée sur la stack de test.

## 5. Hors périmètre

- La notion de client distincte du Contact (itération future).
- La vérification d'entreprise en direct à la saisie : le bouton suffit en v1, le direct suit.
- La fusion d'Entreprises déjà en doublon dans la base.
- Toute autre action dans la colonne Actions (édition, suppression, interaction…).
- La redirection de `/tasks` vers `/alerts` : application locale, sans liens externes.

## 6. Critère de succès

Simon crée un contact avec une nouvelle entreprise, puis une relance pour ce contact, sans quitter la liste ni le formulaire et sans ressaisir le contact. Aucune entreprise en double n'est créée.

## 7. Questions ouvertes

Aucune. Simon a confirmé que la base ne contient aucune Entreprise en doublon : la règle d'unicité peut s'appliquer sans nettoyage préalable.
