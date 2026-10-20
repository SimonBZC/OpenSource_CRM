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
- L'Entreprise créée est rattachée au Contact et exposée par l'API (`/api/companies`), avec son nom et son secteur. Elle n'a pas de page dédiée (voir FR-11).

#### FR-3 : Signaler un doublon d'entreprise

Le système signale un Doublon d'entreprise et empêche sa création.

**Conséquences (testables) :**
- Si le nom saisi correspond à une Entreprise existante sans tenir compte de la casse (« Acme » = « ACME »), un message rouge sous le champ indique qu'elle existe déjà.
- La création est refusée ; Simon choisit alors l'Entreprise existante dans la liste déroulante.
- Le contrôle s'applique aussi à l'enregistrement du Contact, même si Simon n'a pas cliqué sur « Rechercher » : l'enregistrement est refusé avec le même message rouge.
- L'API refuse aussi la création d'une Entreprise en doublon (POST /api/companies et création depuis le Contact), pas seulement l'interface.
- Les espaces en début et fin de nom sont ignorés dans la comparaison.

### 4.2 Colonne Actions dans la liste des contacts

**Description :** la liste `/contacts` gagne une colonne « Actions » en fin de tableau. Chaque ligne y propose, sous forme d'icônes avec infobulle (mallette pour l'Opportunité, cloche pour la Relance), de créer une Opportunité ou une Relance pour ce Contact. Réalise UJ-1.

#### FR-4 : Créer une opportunité depuis un contact

Simon peut, depuis une ligne de la liste des Contacts, ouvrir la création d'une Opportunité pour ce Contact.

**Conséquences (testables) :**
- La colonne Actions est la dernière colonne et contient une icône « Nouvelle opportunité » (infobulle et libellé accessible).
- Le formulaire de création d'Opportunité s'ouvre avec le Contact déjà renseigné, ainsi que son Entreprise s'il en a une.
- Les autres champs restent vides ; Simon peut modifier les champs pré-remplis.
- Après création, Simon arrive sur la fiche de l'Opportunité créée.

#### FR-5 : Créer une relance depuis un contact

Simon peut, depuis une ligne de la liste des Contacts, ouvrir la création d'une Relance pour ce Contact.

**Conséquences (testables) :**
- La colonne Actions contient une icône « Nouvelle relance » (infobulle et libellé accessible).
- Le formulaire de création de Relance s'ouvre avec le Contact déjà renseigné, ainsi que son Entreprise s'il en a une.
- Il est aussi pré-rempli avec le titre « Relance commercial », le statut « À faire » et la date prévue à 14h00 le jour même, ou le lendemain à 14h00 s'il est déjà 14h00 ou plus. Tous ces champs restent modifiables.
- Après création, Simon revient sur la liste des Contacts. Une Relance créée depuis la page Relances ouvre toujours sa fiche.

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

### 4.4 Relances, navigation et entreprises

**Description :** ajustements livrés après la première itération, à la demande de Simon.

#### FR-9 : Supprimer une relance depuis la liste

Simon peut supprimer une Relance directement depuis la liste `/follow-ups`, quel que soit son statut.

**Conséquences (testables) :**
- Chaque ligne de la liste propose « Supprimer » ; une confirmation est demandée avant la suppression définitive.
- La suppression reste aussi possible depuis la fiche de la Relance.

#### FR-10 : Ordre du menu

**Conséquences (testables) :**
- Le menu affiche, dans cet ordre : Dashboard, Contacts, Relances, Opportunités, Alertes, Paramètres.
- Le logo « FreeCRM » mène au Dashboard.

#### FR-11 : Pas de page Entreprises

Les Entreprises n'ont plus de page dédiée dans l'interface ; elles se créent depuis le formulaire Contact (FR-2). L'API `/api/companies` et la règle d'unicité (FR-3) sont conservées.

**Conséquences (testables) :**
- Le menu n'affiche plus « Entreprises » ; `/companies`, `/companies/new` et `/companies/{id}` répondent 404.
- Le nom de l'Entreprise reste affiché, sans lien, sur la fiche Contact et dans la liste des Relances.

**Notes :** `[NOTE FOR PM]` Il n'y a plus d'écran pour renommer ou supprimer une Entreprise, ni pour modifier son site, son secteur ou ses notes. Avec la règle d'unicité, une Entreprise mal nommée ne peut plus être corrigée ni recréée. Ce point est consigné dans `deferred-work.md` et doit être tranché par Simon.

## 5. Exigences transverses

- **Dates et heures :** l'heure saisie dans un formulaire (Relance, Alerte) est celle affichée partout, dans l'heure locale du navigateur. L'API reçoit et renvoie de l'UTC. Les dates saisies avant cette correction restent décalées de +1/+2 h, et leur recalage est consigné dans `deferred-work.md`.

## 6. Hors périmètre

- La notion de client distincte du Contact (itération future).
- La vérification d'entreprise en direct à la saisie : le bouton suffit en v1, le direct suit.
- La fusion d'Entreprises déjà en doublon dans la base.
- Toute autre action dans la colonne Actions (édition, suppression, interaction…).
- La redirection de `/tasks` vers `/alerts` : application locale, sans liens externes.

## 7. Critère de succès

Simon crée un contact avec une nouvelle entreprise, puis une relance pour ce contact, sans quitter la liste ni le formulaire et sans ressaisir le contact. Aucune entreprise en double n'est créée.

## 8. Questions ouvertes

1. Faut-il un moyen de corriger ou supprimer une Entreprise maintenant que sa page est retirée (FR-11) ? Par exemple, une édition légère depuis la fiche Contact. À trancher par Simon.
2. Faut-il recaler les dates de Relances et d'Alertes saisies avant la correction UTC ? Seules les lignes saisies via le formulaire sont concernées, pas celles créées par l'automatisation. À trancher par Simon.

Pour mémoire : Simon a confirmé que la base ne contient aucune Entreprise en doublon. La règle d'unicité s'applique donc sans nettoyage préalable.
