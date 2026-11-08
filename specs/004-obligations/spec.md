# Feature Specification: Écran « Obligations » de la gestion

**Feature Branch**: `004-obligations` (branche de travail `claude/project-thread-q75hfy`)

**Created**: 2026-11-08

**Status**: Lot 1 cadré (2026-11-08) ; plan et tâches écrits ; aucun code écrit

**Input**: Fil « Maîtriser les risques sanitaires de la pâtisserie » du 2026-11-08. Après l'état des
lieux sanitaire, Olivier : « De manière générale on pourrait mettre tout le nécessaire dans un écran
de l'appli gestion » ; « Si toi et Youngmi êtes ok » ; « Youngmi est ok » ; « On en profite pour
nommer l'écran de l'appli gestion en réglementaire ou un truc comme ça et elle y retrouve tout. Y
compris ce qu'elle n'a pas encore fait dans nos autres fils (ex. le médiateur) » ; « Obligations
parfait » ; et pour l'extincteur : « on met un rappel dans l'appli gestion ».

Ce dépôt est public : cette spécification ne contient aucune donnée privée. Les démarches réelles,
les adresses des restaurants et les relevés vivent dans la base de la gestion seulement (R-08, R-35).

## Clarifications

### Session 2026-11-08 (Olivier)

- Q: Nom de l'écran ? → A : « Obligations » (coréen : 의무), avec la même icône partout (menu, titre).
- Q: Relevés sur papier ou dans la gestion ? → A : dans la gestion, Youngmi est d'accord.
- Q: Que contient l'écran ? → A : tout ce que Youngmi doit faire ou prouver : démarches en attente,
  y compris celles des autres chantiers (médiateur de la consommation, assurance, impôts…),
  échéances récurrentes, relevés d'hygiène du quotidien, documents de preuve.
- Q: Rappel de l'extincteur ? → A : oui, dans l'écran, comme les autres vérifications annuelles.
- Q: Restaurants ? → A : 5 aujourd'hui, d'autres viendront ; Youngmi livre elle-même avec des caisses
  prévues pour ça.

### Session 2026-11-08, lot 1 (Olivier, cartes de décision, 18:01-18:17)

- Q: Qui ajoute ou modifie une démarche ? → A : Youngmi et Olivier ; l'autre voit la pastille ambre
  « nouveau » ou « a changé ».
- Q: Les démarches d'Olivier aussi ? → A : oui, chacune porte « pour qui » (Youngmi, Olivier, les deux),
  avec un filtre « les miennes » / « toutes ».
- Q: Preuve d'une démarche faite ? → A : un lien vers le Drive et une note ; aucune photo ni pièce
  gardée dans la gestion.
- Q: Où apparaissent les rappels ? → A : pastilles dans la gestion ET une ligne dans le récap du lundi
  de Ferdinand (boîte d'Olivier) ; rien n'est envoyé à Youngmi.
- Q: Quand une échéance devient-elle « à faire » ? → A : 30 jours avant (orange), rouge une fois en
  retard, pour toutes les échéances.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Voir d'un coup d'œil ce qui reste à faire (Priority: P1)

Youngmi ouvre « Obligations ». En haut, la liste « À faire » montre chaque démarche en attente, la
plus urgente d'abord : ce qu'il faut faire, où (site, Drive, mail, appel), pour quand, et pourquoi.
Elle touche « C'est fait » quand c'est réglé, et peut noter la preuve (lien Drive et note).

**Why this priority**: c'est la demande d'Olivier, « elle y retrouve tout ».

**Independent Test**: sur une base d'essai, créer trois démarches (une en retard, une à 15 jours,
une sans date), vérifier l'ordre, marquer l'une faite avec un lien Drive, la retrouver dans « Fait ».

**Acceptance Scenarios**:

1. **Given** des démarches en attente, **When** Youngmi ouvre l'écran, **Then** elles sont triées :
   en retard (rouge), à moins de 30 jours (orange), puis les autres ; chacune dit l'outil et
   l'endroit exacts (consigne d'Olivier du 07/11 : « quel outil, quelle page »).
2. **Given** une démarche, **When** Youngmi touche « C'est fait », **Then** elle peut ajouter un
   lien Drive et une note de preuve, la date est notée, et la démarche passe dans « Fait », avec qui l'a
   faite.
3. **Given** une démarche en retard, **When** Youngmi ouvre « Aujourd'hui », **Then** une pastille
   sur « Obligations » le signale (même système de pastilles que le reste de la gestion).
4. **Given** Olivier connecté, **When** il ajoute ou modifie une démarche, **Then** elle apparaît
   pour Youngmi avec la pastille « nouveau » ; le texte est en français et en coréen.

---

### User Story 2 - Noter les relevés d'hygiène en quelques touches (Priority: P1)

Chaque jour de production, Youngmi note les températures des frigos et du congélateur, les
réceptions de matières premières, ses fournées et ses livraisons, depuis son téléphone.

**Why this priority**: c'est le cœur du plan de maîtrise sanitaire ; sans relevés, rien ne se
prouve en cas de contrôle ou de client malade.

**Independent Test**: sur une base d'essai, noter une journée complète (2 températures, 1 réception
avec photo d'étiquette, 2 fournées, 1 livraison) en moins de 3 minutes sur un téléphone, puis
retrouver le tout dans l'historique.

**Acceptance Scenarios**:

1. **Given** un appareil de froid enregistré avec sa plage (fixée par Youngmi), **When** elle tape
   une température hors plage, **Then** la gestion lui demande ce qu'elle a fait (case « problème »
   avec un texte), et le relevé est marqué.
2. **Given** une réception, **When** Youngmi la note, **Then** elle saisit le fournisseur, le
   produit, le lot et la date limite, ou prend l'étiquette en photo à la place.
3. **Given** une fournée, **When** Youngmi la note, **Then** la gestion lui donne un numéro de lot
   (date + gâteau), reprend la durée de conservation de la fiche produit et calcule la date limite.
4. **Given** une livraison à un restaurant, **When** Youngmi la note, **Then** elle touche
   « Départ » puis « Arrivée » et peut saisir la température au départ ; les lots livrés sont
   reliés au restaurant.
5. **Given** un relevé enregistré, **When** quelqu'un veut le modifier, **Then** l'original reste
   visible avec la correction, l'heure et la personne (aucun relevé n'est effacé).
6. **Given** un jour de production sans relevé de froid, **When** la journée se termine, **Then**
   la démarche « relevé manquant » apparaît dans « À faire » le lendemain.

---

### User Story 3 - Étiquettes des boîtes livrées aux restaurants (Priority: P2)

Avant une livraison, Youngmi imprime ou affiche l'étiquette de chaque boîte : nom du gâteau,
allergènes, date limite, conditions de conservation, lot, Madame Cho et son adresse.

**Why this priority**: obligatoire pour les produits cédés à un professionnel ; tout est déjà dans
les fiches produits.

**Independent Test**: créer une fournée et une livraison fictives, imprimer l'étiquette sur une
étiquette 55 x 30 mm et sur une planche A4.

**Acceptance Scenarios**:

1. **Given** une fournée, **When** Youngmi touche « Étiquettes », **Then** la gestion produit les
   étiquettes à partir de la fiche produit validée par elle (R-39), jamais d'un texte inventé.
2. **Given** une fiche produit sans allergènes ou sans conservation, **When** Youngmi demande
   l'étiquette, **Then** la gestion refuse et dit quoi compléter.

---

### User Story 4 - Retrouver un lot en cas de problème (Priority: P2)

Un client ou un restaurant signale un malaise ou un défaut. Youngmi ouvre le lot concerné et voit
aussitôt les ingrédients reçus utilisés, et à qui le lot a été vendu ou livré.

**Why this priority**: c'est ce qu'exige un retrait ou un rappel (procédure P-17 du rulebook).

**Independent Test**: sur une base d'essai, déclarer un problème sur un lot livré à deux restaurants
et vérifier la liste des destinataires et le brouillon proposé.

**Acceptance Scenarios**:

1. **Given** un lot, **When** Youngmi touche « Problème sur ce lot », **Then** la gestion affiche
   les réceptions liées, les commandes et livraisons concernées, et les étapes de P-17 (retirer le
   lot, prévenir les clients, informer la DDPP, RappelConso) à cocher.
2. **Given** ce problème, **When** Youngmi le demande, **Then** la gestion prépare un brouillon pour
   la DDPP, qu'elle envoie elle-même (R-01) ; la gestion ne l'envoie jamais seule.

---

### User Story 5 - Échéances et volumes de la dérogation (Priority: P2)

La rubrique « Échéances » rappelle les dates récurrentes : vérification de l'extincteur et de
l'installation électrique chaque année, renouvellement de l'assurance, comptes et impôts repris
du calendrier des obligations. La rubrique « Volumes » montre ce qui est vendu chaque semaine aux
restaurants, pour la dérogation à l'agrément sanitaire.

**Why this priority**: les vérifications annuelles conditionnent l'assurance ; la dérogation a des
plafonds et doit être mise à jour à chaque nouveau restaurant régulier.

**Independent Test**: créer une échéance annuelle passée, vérifier qu'elle devient une démarche
« À faire » ; créer un nouveau client professionnel et vérifier le rappel de mise à jour.

**Acceptance Scenarios**:

1. **Given** une échéance récurrente, **When** sa date approche (30 jours), **Then** elle devient une
   démarche « À faire » ; une fois faite, la suivante est créée (année suivante).
2. **Given** un nouveau client professionnel qui reçoit une deuxième commande, **When** elle est
   confirmée, **Then** la démarche « mettre à jour la dérogation (Cerfa 13982) » apparaît.
3. **Given** les commandes des restaurants, **When** Youngmi ouvre « Volumes », **Then** elle voit
   par semaine la part des ventes aux restaurants et, si les poids sont renseignés dans les fiches
   produits, les kilos par catégorie, avec une alerte avant les plafonds confirmés par la DDPP
   (saisis dans Paramètres, jamais devinés).

---

### User Story 6 - Documents de preuve (Priority: P3)

La rubrique « Documents » donne les liens vers les preuves rangées dans le Drive : récépissés de
la DDPP, diplômes, attestation d'assurance, rapports de vérification, registre RGPD.

**Why this priority**: utile au contrôle, mais les fichiers existent déjà dans le Drive.

**Acceptance Scenarios**:

1. **Given** un contrôle, **When** Youngmi ouvre « Documents », **Then** elle ouvre chaque preuve
   en un toucher (lien Drive) ; la gestion ne garde pas de copie des pièces.

### Edge Cases

- Pas de réseau en cuisine : un relevé saisi hors ligne est gardé sur le téléphone et envoyé au
  retour du réseau, avec son heure réelle.
- Deux frigos, puis un troisième : les appareils se gèrent dans Paramètres, sans code.
- Jour sans production : Youngmi touche « Pas de production aujourd'hui », aucun relevé manquant.
- Restaurant qui arrête : sa fiche passe « inactif », il sort des volumes et de la dérogation.
- Démarche qui ne concerne plus Madame Cho : « Sans objet », avec une raison.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Un écran « Obligations » (coréen 의무), dans le menu Plus et en lien direct sur grand
  écran, avec quatre rubriques : À faire, Hygiène au quotidien, Échéances, Documents.
- **FR-002**: Une démarche porte : titre, explication, outil et endroit exacts, échéance éventuelle,
  priorité, origine (chantier ou fil), état (à faire, fait, sans objet), preuve, auteur et dates.
- **FR-003**: Les démarches et échéances initiales sont saisies dans la base au lancement, à partir
  de la Mémoire du projet (« Tâches et questions »), du calendrier des obligations (20/02) et des
  fils en cours, chacune vérifiée dans le Drive avant saisie ; jamais dans le dépôt.
- **FR-004**: Les relevés de froid, réceptions, fournées et livraisons sont horodatés, attribués,
  jamais effacés ; une correction garde l'original.
- **FR-005**: Les plages de température, durées de conservation et plafonds viennent de Youngmi ou
  de la DDPP, saisis dans Paramètres ou dans la fiche produit ; la gestion n'en propose aucun par
  défaut (rulebook, partie 4.1).
- **FR-006**: Numéro de lot automatique par fournée, relié aux réceptions et aux livraisons.
- **FR-007**: Étiquettes imprimables (55 x 30 mm et planche A4) tirées des fiches produits.
- **FR-008**: Bouton « Problème sur ce lot » qui suit P-17 et prépare un brouillon, jamais envoyé
  sans le toucher de Youngmi (R-01).
- **FR-009**: Pastilles sur « Obligations » pour les démarches en retard, les relevés manquants et
  les nouveautés, avec le système de pastilles existant.
- **FR-010**: Tous les textes en français et en coréen (dictionnaire de `inc/coreen.php`) ; les
  textes saisis ne sont jamais traduits.
- **FR-011**: Export des relevés d'une période en PDF ou CSV, pour un contrôle ou l'expert-comptable.
- **FR-013**: Une démarche porte « pour qui » (Youngmi, Olivier, les deux) ; l'écran ouvre sur « les
  miennes » (y compris « les deux ») avec un filtre « toutes ». Youngmi et Olivier peuvent ajouter,
  modifier, marquer faite ou sans objet ; une démarche n'est jamais effacée.
- **FR-014**: Chaque lundi, les démarches en retard ou à moins de 30 jours rejoignent le récap
  hebdomadaire de Ferdinand dans la boîte d'Olivier ; rien n'est envoyé à Youngmi (R-01).
- **FR-012**: Durées de conservation des relevés fixées avant la mise en ligne, avec la tâche de
  nuit existante ; rien n'est purgé avant d'avoir une durée confirmée.

### Key Entities

- **Démarche** : une chose à faire ou à prouver, unique ou issue d'une échéance.
- **Échéance** : une règle récurrente (annuelle, à chaque nouveau restaurant…) qui crée des démarches.
- **Appareil de froid** : nom, plage fixée par Youngmi, actif ou non.
- **Relevé de froid** : appareil, température, heure, problème éventuel et action.
- **Réception** : fournisseur, produit, lot, date limite, photo d'étiquette.
- **Fournée (lot)** : gâteau, numéro, date, date limite, réceptions utilisées.
- **Livraison** : restaurant, lots, départ, arrivée, température au départ.
- **Document** : titre, lien Drive, date de validité éventuelle.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Youngmi note une journée de relevés en moins de 3 minutes sur son téléphone.
- **SC-002**: En cas de problème, la liste des destinataires d'un lot s'obtient en moins d'une minute.
- **SC-003**: Aucune démarche ouverte dans les autres fils n'est absente de « À faire » au lancement.
- **SC-004**: Lors d'un contrôle, chaque preuve demandée (relevés, déclarations, diplômes, assurance,
  vérifications) s'ouvre depuis l'écran.

## Assumptions

- Les fiches produits ont, ou auront, leur durée de conservation et leurs allergènes validés par
  Youngmi ; le poids par portion est facultatif et ne sert qu'aux volumes.
- Les relevés numériques horodatés et non effaçables sont acceptés en contrôle (à confirmer avec la
  DDPP dans la réponse au mail du 2026-11-08).
- Construction par lots : 1) À faire et Échéances, 2) Hygiène au quotidien, 3) Étiquettes et lots,
  4) Volumes et Documents ; chaque lot publié sur feu vert d'Olivier.
