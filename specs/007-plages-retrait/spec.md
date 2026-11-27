# Feature Specification: Plages de retrait à capacité

**Feature Branch**: `007-plages-retrait` (branche de travail `claude/project-thread-ng5b6m`)

**Created**: 2026-11-27

**Status**: Spécification écrite (2026-11-27) ; plan et tâches à faire ; aucun code écrit

**Input**: Fil « Revoir les retraits et le paiement avec Youngmi » des 25 et 27/11/2026. Olivier, le
25/11 : « la gestion de l'agenda pour les retraits sur place (on aimerait découper chaque journée en
plages horaires, par exemple 2 le matin, et permettre à plusieurs clients de venir retirer leur commande
sur une même plage horaire) ». Réponses de Youngmi, transmises par Olivier le 27/11 : « soit un nombre
(par exemple 3 ou 4), ou qu'elle ferme la plage horaire quand elle estime qu'il y a déjà assez de monde
en même temps, et dans les 2 cas si la plage est fermée, les clients ne peuvent plus la prendre » ;
« Par défaut le maximum est de 5 sur une plage mais donc Youngmi peut modifier » ; « Youngmi va par
contre fermer le dimanche, il y aura uniquement des cas particuliers sur appel. Samedi mêmes plages.
pas de maximum par jour » ; « on va aussi mettre une plage de 12 à 14 tous les jours sauf le
dimanche ». Paiement, même soir : « sur demande on oublie l'acompte, trop compliqué ».

Ce dépôt est public : cette spécification ne contient aucune donnée privée (ni client, ni montant
réel). Les commandes et les réglages vivent dans la base de la gestion seulement (R-08, R-35).

## Clarifications

### Session 2026-11-27 (Olivier, pour Youngmi et lui)

- Q: Quelles plages ? → A : six plages, du lundi au samedi : 9 h à 10 h 30, 10 h 30 à 12 h,
  12 h à 14 h, 14 h à 16 h, 16 h à 18 h, 18 h à 19 h. Mêmes plages le samedi.
- Q: Combien de clients par plage ? → A : 5 par défaut ; Youngmi peut changer ce nombre, ou fermer
  une plage quand elle juge qu'il y a assez de monde. Une plage pleine ou fermée n'est plus proposée.
- Q: Maximum par jour ? → A : non.
- Q: Jours sans retrait ? → A : le dimanche est fermé ; seulement des cas particuliers, par téléphone.
  Le 24 décembre reste fermé (déjà en ligne).
- Q: Une demande qui attend la proposition de Youngmi prend-elle une place ? → A : non. Seules les
  commandes confirmées ou payées comptent.
- Q: Acompte ? → A : supprimé. Une commande sur demande se paie en entier une fois validée
  (paiement : spécification 006).
- Q: Les nouvelles pages (Retrait, Questions, Conditions de commande) ? → A : elles conviennent à
  Youngmi.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Le client choisit une plage libre (Priority: P1)

Sur « Ma demande », après avoir choisi la date, le client voit les plages de ce jour et n'en peut
choisir qu'une encore ouverte. Les plages pleines ou fermées n'apparaissent pas comme choisissables ;
le dimanche et le 24 décembre ne sont pas proposés. Le délai de commande de chaque gâteau reste
appliqué comme aujourd'hui.

**Why this priority**: c'est ce qui empêche que trop de clients viennent en même temps, et ce sur quoi
s'appuiera la commande payée tout de suite (spécification 006, temps 2).

**Independent Test**: avec une base d'essai où une plage a 5 commandes confirmées et une autre est
fermée, remplir le formulaire pour ce jour : seules les autres plages sont proposées, et un envoi forcé
sur la plage pleine est refusé par le serveur.

**Acceptance Scenarios**:

1. **Given** un mardi sans commande, **When** le client choisit ce mardi, **Then** les six plages sont
   proposées.
2. **Given** la plage 10 h 30 à 12 h d'un samedi compte 5 commandes confirmées (maximum 5),
   **When** le client choisit ce samedi, **Then** cette plage est montrée « complète » et ne peut pas
   être choisie ; les cinq autres le peuvent.
3. **Given** un dimanche, **When** le client le choisit, **Then** le site refuse la date et dit que le
   dimanche se fait seulement par téléphone, avec le numéro.
4. **Given** toutes les plages d'un jour sont pleines, fermées ou indisponibles, **When** le client
   choisit ce jour, **Then** le site le dit et l'invite à choisir un autre jour, sans donner de motif.
5. **Given** le client a choisi une plage, **When** elle se remplit avant qu'il envoie, **Then** l'envoi
   est refusé avec un message clair et la liste des plages est mise à jour.

---

### User Story 2 - Youngmi voit et règle ses plages (Priority: P1)

Dans l'Agenda de la gestion, chaque jour montre ses plages avec le compte des retraits (par exemple
« 2 sur 5 »). Youngmi peut, pour une plage d'un jour donné, changer le maximum ou la fermer, puis la
rouvrir. Le maximum par défaut (5) se règle dans les Paramètres.

**Why this priority**: Youngmi garde la main sur l'affluence, ce qu'elle a demandé.

**Independent Test**: fermer la plage 16 h à 18 h d'un jeudi dans l'Agenda, puis vérifier sur le site
qu'elle n'est plus proposée ; la rouvrir et vérifier qu'elle revient.

**Acceptance Scenarios**:

1. **Given** le maximum par défaut est 5, **When** Youngmi le passe à 4 dans les Paramètres,
   **Then** toutes les plages sans réglage particulier acceptent 4 retraits.
2. **Given** une plage d'un jour précis, **When** Youngmi met son maximum à 2, **Then** ce jour-là
   seulement, la plage est complète à 2 retraits.
3. **Given** une plage ouverte, **When** Youngmi la ferme, **Then** le site ne la propose plus, même si
   elle n'est pas pleine ; les commandes déjà confirmées dessus ne bougent pas.
4. **Given** une indisponibilité qui touche une partie d'une plage, **When** le client regarde ce jour,
   **Then** toute la plage touchée est fermée, comme aujourd'hui pour les « moments ».

---

### User Story 3 - La plage dans les mails et l'agenda du client (Priority: P2)

La proposition, la confirmation et le rappel de la veille donnent la plage (« samedi 5 décembre,
entre 10 h 30 et 12 h ») au lieu d'une heure exacte. Le lien « Ajouter à votre agenda » couvre la plage.
Youngmi n'a plus d'heure à fixer avec chaque client ; elle peut encore noter une heure précise pour un
cas particulier.

**Why this priority**: c'est ce qui simplifie le travail de Youngmi, mais le site peut ouvrir les
plages avant que tous les mails soient retouchés.

**Independent Test**: confirmer une commande d'essai sur une plage et lire l'aperçu de la confirmation
et du rappel, en français et dans la gestion en coréen.

**Acceptance Scenarios**:

1. **Given** une commande confirmée sur 14 h à 16 h, **When** Youngmi ouvre l'aperçu de la
   confirmation, **Then** il dit « entre 14 h et 16 h ».
2. **Given** la même commande, **When** le client ajoute le retrait à son agenda, **Then** l'événement va
   de 14 h à 16 h.
3. **Given** une commande avec une heure précise notée par Youngmi, **When** un mail part, **Then** il
   donne l'heure précise, comme aujourd'hui.

---

### User Story 4 - Une commande hors plage par téléphone (Priority: P2)

Un client appelle pour un dimanche, ou pour une plage déjà pleine. Youngmi saisit la commande dans la
gestion : c'est possible, avec un avertissement clair (« dimanche, fermé au public » ou « plage déjà
complète : 5 sur 5 »), et la commande compte ensuite dans la plage.

**Why this priority**: Youngmi a prévu ces cas particuliers ; ils sont rares.

**Independent Test**: créer une commande d'essai un dimanche, puis une sur une plage pleine : les deux
s'enregistrent avec l'avertissement ; le site continue de refuser ces créneaux.

**Acceptance Scenarios**:

1. **Given** une plage complète, **When** Youngmi confirme une demande sur cette plage, **Then** la
   gestion la prévient et lui laisse le choix : confirmer quand même ou changer de plage.

---

### User Story 5 - Les textes d'aujourd'hui suivent les décisions (Priority: P1)

Avant même les plages, le site et la gestion cessent de dire « tous les jours » et de parler d'acompte.
Le site dit : retrait au 4 avenue du Château, du lundi au samedi, de 9 h à 19 h, sur la plage choisie ;
fermé le dimanche (cas particuliers par téléphone) et le 24 décembre. Plus aucune mention d'acompte :
ni sur le formulaire, ni sur les fiches des gâteaux, ni dans Questions ni dans Conditions de commande.

**Why this priority**: la fiche Google va annoncer le dimanche fermé ; le site doit dire la même chose,
et une condition de vente annoncée doit être celle qu'on applique.

**Independent Test**: chercher « tous les jours » et « acompte » dans les pages publiées : aucune
occurrence ; les données lues par les moteurs annoncent du lundi au samedi, 9 h à 19 h.

**Acceptance Scenarios**:

1. **Given** la nouvelle version, **When** on lit Retrait, Questions, Conditions de commande, le pied de
   page et une fiche de gâteau, **Then** ils disent « du lundi au samedi » et ne parlent pas d'acompte.
2. **Given** la nouvelle version, **When** Youngmi fait une proposition dans la gestion, **Then** aucun
   champ ni mail ne lui propose de demander un acompte ; une commande ancienne qui en a un reste lisible.

---

### Edge Cases

- Une commande confirmée est annulée : sa place se libère aussitôt sur le site.
- Youngmi baisse le maximum sous le nombre de retraits déjà confirmés : rien n'est annulé ; la plage
  est simplement complète, et l'Agenda montre « 6 sur 4 » avec une couleur d'alerte.
- Une commande ancienne avec un « moment » (matin, midi…) ou une heure : elle reste affichée telle quelle,
  et compte dans la plage où tombe son heure, ou, sans heure, dans la première plage de son moment.
- Une livraison de la gamme pro ne compte dans aucune plage.
- Plusieurs demandes arrivent pour la même plage : elles passent toutes, puisqu'une demande ne prend
  pas de place ; c'est à la confirmation que Youngmi est avertie si la plage s'est remplie. La course
  pour la dernière place ne se pose qu'avec la commande payée tout de suite (spécification 006).
- La gestion n'est pas encore ouverte ou sa base est illisible : le formulaire reste utilisable, comme
  aujourd'hui pour les indisponibilités, avec toutes les plages du jour proposées.
- Le formulaire est ouvert depuis longtemps : la disponibilité est revérifiée à l'envoi.

## Requirements *(mandatory)*

### Functional Requirements

**Plages**

- **FR-001**: Une journée de retrait DOIT compter six plages : 9 h à 10 h 30, 10 h 30 à 12 h, 12 h à
  14 h, 14 h à 16 h, 16 h à 18 h, 18 h à 19 h, du lundi au samedi.
- **FR-002**: Le dimanche et les jours fermés (24 décembre) NE DOIVENT avoir aucune plage sur le site.
- **FR-003**: Le maximum par défaut d'une plage DOIT être 5 et se régler dans les Paramètres de la gestion
  (de 1 à 20).
- **FR-004**: Youngmi DOIT pouvoir, pour une plage d'un jour donné, changer le maximum, fermer la plage
  ou la rouvrir, depuis l'Agenda ; chaque changement est noté (qui, quand) et réversible.
- **FR-005**: Une plage DOIT compter les commandes à retirer confirmées ou remises, et celles payées en
  ligne quand la spécification 006 existera ; NE comptent pas : les demandes, les propositions
  envoyées, les commandes sans suite ou annulées, les livraisons.
- **FR-006**: Une indisponibilité de Youngmi DOIT fermer toutes les plages qu'elle touche, même en
  partie.

**Site**

- **FR-007**: Le formulaire « Ma demande » DOIT remplacer le choix du « moment » par le choix d'une
  plage, et ne laisser choisir qu'une plage ouverte qui n'est pas complète, pour la date choisie.
- **FR-008**: Le serveur DOIT refuser un envoi sur un dimanche, un jour fermé, une plage fermée,
  complète ou indisponible, avec un message qui ne dit jamais le motif d'une indisponibilité.
- **FR-009**: Le site DOIT demander les plages libres d'un jour sans rien enregistrer ni dévoiler
  d'autre que « libre » ou « complète » pour chaque plage (pas de nombre de clients).
- **FR-010**: Les pages Retrait, Questions, Conditions de commande, le pied de page et les informations
  lues par les moteurs (horaires) DOIVENT annoncer : du lundi au samedi, 9 h à 19 h, sur la plage choisie ;
  fermé le dimanche, sauf cas particulier par téléphone ; pas de retrait le 24 décembre.

**Gestion**

- **FR-011**: L'Agenda DOIT montrer, pour chaque jour, ses plages avec « retraits sur maximum », les
  plages fermées et les commandes de chaque plage, chacune menant à sa page.
- **FR-012**: La saisie et la modification d'une commande DOIVENT proposer la plage (et en option une
  heure précise) au lieu du « moment », et avertir sans bloquer pour un dimanche, un jour fermé ou une
  plage complète.
- **FR-013**: La confirmation d'une demande DOIT avertir quand la plage est déjà complète.
- **FR-014**: Les mails au client (proposition, confirmation, rappel) et le lien d'agenda DOIVENT donner
  la plage, ou l'heure précise si Youngmi en a noté une.
- **FR-015**: L'assistant de la gestion DOIT pouvoir lire les plages et leur remplissage, sans pouvoir
  les changer.
- **FR-016**: Tout texte nouveau de la gestion DOIT exister en coréen ; le manuel et « Quoi de neuf »
  DOIVENT l'expliquer en français et en coréen.

**Acompte supprimé**

- **FR-017**: Le site NE DOIT plus mentionner d'acompte (formulaire, fiches des gâteaux, Questions,
  Conditions de commande) ; le paiement reste « au retrait, par carte bancaire, virement ou espèces »
  jusqu'à la spécification 006.
- **FR-018**: La gestion NE DOIT plus proposer de demander un acompte (proposition, confirmation,
  Aujourd'hui) ; les commandes qui en ont déjà un restent lisibles et se règlent comme aujourd'hui.

### Key Entities

- **Plage**: un intervalle fixe d'une journée (début, fin), identique du lundi au samedi.
- **Réglage d'une plage**: pour une date et une plage, un maximum particulier ou l'état « fermée ».
  Sans réglage, la plage est ouverte avec le maximum par défaut.
- **Commande**: porte maintenant une plage (et garde son heure précise facultative) ; les anciennes
  gardent leur « moment ».

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Sur une base d'essai, le site ne propose et n'accepte jamais une plage complète, fermée ou
  indisponible, ni un dimanche ou un jour fermé (100 % des cas d'essai).
- **SC-002**: Fermer ou rouvrir une plage prend à Youngmi au plus 3 touchers depuis l'Agenda, et le
  site en tient compte au chargement suivant.
- **SC-003**: Aucune page publiée ne contient « tous les jours » pour le retrait, ni « acompte ».
- **SC-004**: Les mails de proposition, de confirmation et de rappel donnent la plage dans 100 % des
  commandes qui en ont une, en français.
- **SC-005**: Le formulaire reste utilisable sur téléphone : choisir une date et une plage tient sur
  un écran de 360 px de large sans défilement horizontal.

## Assumptions

- Les plages sont les mêmes tous les jours ouverts ; si Youngmi veut un jour d'autres plages, on en
  reparlera (pas de réglage des plages elles-mêmes dans cette version).
- Le dimanche est annoncé fermé au public ; le texte du site invite à appeler pour un cas particulier.
- Les délais de commande (la veille, 2 jours, 14 jours pour un professionnel) ne changent pas.
- La fiche Google et la fiche Apple sont mises à jour à la main par Olivier ou Youngmi (pas dans le code).
- La place n'est pas retenue pendant une demande : Youngmi confirme et voit l'avertissement si la plage
  s'est remplie entre-temps.
- La retenue d'une place pendant un paiement en ligne appartient à la spécification 006 (FR-029) et
  s'appuiera sur le compte des plages de celle-ci.
- La publication passe par le feu vert d'Olivier (R-19), après un audit par son ChatGPT (P-26).
