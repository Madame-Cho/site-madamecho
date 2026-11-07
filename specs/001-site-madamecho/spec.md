# Feature Specification: Site madamecho.com, carte et demande sans engagement

**Feature Branch**: `001-site-madamecho`

**Created**: 2026-11-07

**Status**: En ligne depuis le 2026-11-03 ; spécification reprise au format Spec Kit

**Input**: Spécification de référence « 2026-11-02 Claude Spécification du site Madame Cho (v3, après audit) » (Drive, 91/03), avec les décisions prises depuis (séances 1 à 3 de Youngmi, audit du site du 03/11, mise en ligne du 03/11, mesure d'audience du 06/11). La version du Drive fait foi pour les décisions ; celle-ci en est la version technique, sans rien de privé (constitution, principe I).

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Demander un gâteau depuis son téléphone (Priority: P1)

Une famille de Meudon cherche un gâteau pour une date précise. Sur son téléphone, elle ouvre la
carte, choisit un gâteau, sa taille ou son parfum et la quantité, voit le prix TTC, les
allergènes et le délai, l'ajoute à « Ma demande », indique le jour et le moment du retrait
souhaités, ses coordonnées, puis envoie sa demande sans engagement.

**Why this priority**: c'est la raison d'être du site (objectif 2 de la spécification) ; sans
elle, le site n'est qu'une vitrine.

**Independent Test**: sur un téléphone, partir de l'accueil et envoyer une demande valide ; elle
arrive dans la boîte de Madame Cho avec tout son contenu, et le visiteur voit un récapitulatif
avec une référence.

**Acceptance Scenarios**:

1. **Given** la carte affichée sur un téléphone, **When** le visiteur ouvre la fiche d'un gâteau,
   **Then** il voit une photo, le nom, une phrase, chaque taille ou parfum avec son prix TTC,
   les 14 allergènes, la conservation, le délai pour commander et un bouton « Ajouter à ma
   demande ».
2. **Given** une demande remplie avec une date qui respecte le délai du gâteau, **When** le
   visiteur l'envoie, **Then** il voit un récapitulatif avec une référence MC-AAMMJJ-XXXX et la
   promesse « réponse sous 24 heures », sans jamais lire que la commande est confirmée.
3. **Given** une date plus proche que le délai le plus long des gâteaux choisis, **When** le
   visiteur l'envoie, **Then** la demande est refusée avec un message clair qui donne la
   première date possible.
4. **Given** un envoi qui échoue (réseau coupé, serveur indisponible), **When** le visiteur
   revient, **Then** son panier est toujours là et il peut copier son récapitulatif ou écrire
   par email.

---

### User Story 2 - Recevoir la demande et y répondre (Priority: P1)

Youngmi reçoit chaque demande par email dans la boîte de Madame Cho, avec la référence, les
gâteaux, les quantités, le total indicatif recalculé, la personnalisation et les coordonnées.
Elle répond par une proposition écrite ; le client l'accepte par écrit ; Youngmi confirme.

**Why this priority**: une demande qui n'arrive pas, ou qui arrive incomplète, fait perdre un
client et la promesse de réponse sous 24 heures.

**Independent Test**: envoyer une demande d'essai et vérifier dans la boîte de réception
qu'elle est arrivée, signée par le domaine, avec l'adresse du client en « répondre à ».

**Acceptance Scenarios**:

1. **Given** une demande valide, **When** elle est envoyée, **Then** un seul email arrive à la
   boîte de Madame Cho, signé par le domaine, avec l'adresse du client en « répondre à » et le
   total recalculé par le serveur.
2. **Given** un prix modifié dans le navigateur du visiteur, **When** la demande arrive, **Then**
   le prix affiché est celui de la carte, jamais celui envoyé par le navigateur.
3. **Given** un double clic ou un renvoi de la même demande, **When** elle est reçue, **Then**
   Madame Cho ne reçoit pas de doublon.

---

### User Story 3 - Un gâteau d'anniversaire personnalisé (Priority: P2)

Un parent veut un gâteau d'anniversaire pour 4 à 12 personnes, avec un prénom et un message.
Il comprend ce qui est compris dans le prix (la plaque en chocolat avec le prénom et le
message), ce qui demande un acompte (une personnalisation au-delà de la plaque : 30 %) et le
délai (au moins 2 jours), puis décrit son envie dans sa demande.

**Why this priority**: l'anniversaire est le besoin type des familles (visiteurs principaux).

**Independent Test**: depuis la section Anniversaires, envoyer une demande « gâteau
personnalisé » avec un message de plaque ; la demande arrive avec ce texte, marquée
personnalisée.

**Acceptance Scenarios**:

1. **Given** la section Anniversaires, **When** le visiteur la lit, **Then** il sait ce qui est
   compris, ce qui demande un acompte, pour combien de personnes et avec quel délai.
2. **Given** une demande personnalisée, **When** elle arrive, **Then** le texte de la
   personnalisation, message de la plaque compris, est transmis tel quel.

---

### User Story 4 - Une demande d'un professionnel (Priority: P2)

Un restaurant, un café ou une entreprise découvre ce que Madame Cho fournit déjà chaque
semaine, et envoie une demande d'un professionnel, sans prix publics.

**Why this priority**: les professionnels sont la référence de Madame Cho (objectif 4).

**Independent Test**: cocher « Je fais cette demande pour une entreprise ou un restaurant »,
remplir le nom de l'entreprise et le besoin, envoyer ; la demande arrive marquée
professionnelle, sans aucun tarif professionnel nulle part sur le site.

**Acceptance Scenarios**:

1. **Given** la section Professionnels, **When** le visiteur la lit, **Then** il voit l'offre
   et les restaurants cités avec leur accord écrit, mais aucun prix professionnel.
2. **Given** une demande professionnelle, **When** la date est à moins de 14 jours, **Then** elle
   est refusée avec la première date possible.

---

### User Story 5 - Faire confiance avant de demander (Priority: P3)

Avant de demander, le visiteur découvre Youngmi (La maison), les infos pratiques (retrait sur
rendez-vous à Meudon, tous les jours de 9 h à 20 h, paiement au retrait), les mentions
légales, la confidentialité, les conditions de commande et le compte Instagram.

**Why this priority**: le sérieux de la maison prolonge le bouche-à-oreille, mais le site peut
recevoir des demandes sans tout cela.

**Independent Test**: chaque page légale est accessible depuis le pied de page de chaque page,
et les infos pratiques sont visibles avant l'envoi d'une demande.

**Acceptance Scenarios**:

1. **Given** n'importe quelle page, **When** le visiteur va en bas, **Then** il trouve les liens
   vers les mentions légales, la confidentialité, les conditions de commande et Instagram.
2. **Given** la page Ma demande, **When** le visiteur la lit, **Then** il sait que le retrait se
   fait sur rendez-vous à Meudon et que le paiement se fait au retrait.

---

### User Story 6 - Partager et retrouver Madame Cho (Priority: P3)

Un client content partage la page d'un gâteau ; un voisin scanne le QR code d'une boîte ; une
personne cherche « pâtisserie Meudon ». Chacun arrive sur la bonne page, avec une belle image de
partage.

**Why this priority**: le bouche-à-oreille porte Madame Cho, mais il dépend aussi de la fiche
Google et des cartes imprimées, hors du site.

**Independent Test**: partager une fiche depuis un téléphone (ou copier son lien en secours) et
vérifier l'aperçu ; chaque page a un titre, une description et une adresse stable.

**Acceptance Scenarios**:

1. **Given** la fiche d'un gâteau, **When** le visiteur touche « Partager », **Then** le partage
   du téléphone s'ouvre, ou le lien est copié s'il n'existe pas.
2. **Given** un moteur de recherche, **When** il lit une page, **Then** il trouve un titre, une
   description, les données « pâtisserie » et le plan du site.

---

### User Story 7 - Mesurer les visites sans traceur (Priority: P3)

Madame Cho sait combien de personnes visitent la carte et d'où elles viennent, sans cookie ni
bandeau, et un visiteur peut refuser la mesure depuis la page Confidentialité.

**Why this priority**: utile pour décider, mais jamais au prix d'un traceur soumis au
consentement (constitution, principe IV).

**Independent Test**: une visite apparaît dans la mesure d'audience ; après le refus sur la page
Confidentialité, une nouvelle visite n'apparaît plus ; aucun cookie n'est déposé.

**Acceptance Scenarios**:

1. **Given** un visiteur qui n'a rien refusé, **When** il visite le site, **Then** sa visite est
   comptée sans cookie et sans donnée qui l'identifie.
2. **Given** un visiteur qui a refusé, **When** il revient, **Then** sa visite n'est pas comptée.

### Edge Cases

- Gâteau vendu par lot minimum (cookies et petits gâteaux du goûter à partir de 6, choux à
  partir de 10 et par 5 du même parfum) : une autre quantité est refusée avec la règle affichée.
- Jour sans retrait (par exemple le 24 décembre) : la date est refusée avec le message prévu.
- Gâteau retiré de la carte alors qu'il est dans un panier : il est signalé et retiré de la
  demande avant l'envoi.
- Navigateur sans stockage local ou sans script : la carte et les fiches restent lisibles, et le
  visiteur peut toujours écrire par email ou appeler.
- Spam : un champ piège rempli, ou trop d'envois depuis la même origine, est refusé sans message
  qui aide un robot.
- Accents et caractères coréens dans les champs libres : transmis sans être abîmés.
- Allergène dont l'information manque : le gâteau n'est pas proposé (« inconnu » n'est jamais
  « absent »).
- Champ texte très long ou retour à la ligne dans un champ d'en-tête : refusé ou borné.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Le site MUST présenter une carte de 6 à 8 gâteaux choisis par Youngmi, chacun avec
  sa propre page à adresse stable.
- **FR-002**: Chaque fiche MUST afficher une photo, le nom, une phrase, chaque taille ou parfum
  avec son prix TTC, les 14 allergènes majeurs (céréales et fruits à coque précisés), la
  conservation, le délai pour commander, le lot minimum s'il y en a un, et la mention de
  l'atelier qui utilise aussi des fruits à coque et des arachides.
- **FR-003**: Un gâteau dont une information de FR-002 manque, ou dont la fiche n'est pas validée
  par Youngmi, MUST NOT être proposé.
- **FR-004**: L'accueil MUST s'ouvrir sur une grande photo, sans grille de produits, avec un lien
  visible vers la carte dès le premier écran, puis les sections La carte, Anniversaires,
  Événements, Professionnels, La maison et Commander.
- **FR-005**: Le visiteur MUST pouvoir ajouter des gâteaux (taille ou parfum, quantité) à
  « Ma demande », modifier ou vider ce panier ; le panier ne garde que les gâteaux, tailles et
  quantités, dans le navigateur du visiteur, et expire.
- **FR-006**: La page Ma demande MUST demander le prénom, le nom, l'email, le téléphone
  (facultatif), le jour et le moment du retrait souhaités, la personnalisation (message de la
  plaque compris), et, pour un professionnel, le nom de l'entreprise et le besoin ; elle
  prévient de ne pas écrire d'information de santé.
- **FR-007**: Le serveur MUST n'accepter que les gâteaux, tailles et parfums de la carte,
  recalculer les prix, le total et le délai lui-même, borner les longueurs et les quantités,
  refuser un retour à la ligne dans un en-tête et ne jamais accepter de pièce jointe.
- **FR-008**: Le serveur MUST refuser une date plus proche que le délai le plus long des gâteaux
  choisis (2 jours pour une personnalisation, 14 jours pour un professionnel) ou tombant un jour
  sans retrait, et donner la première date possible.
- **FR-009**: Une demande valide MUST partir en un seul email vers la boîte de Madame Cho, signé
  par le domaine, avec l'adresse du client en « répondre à » ; un email qui ne peut pas être
  signé ne part pas, et le visiteur garde son panier.
- **FR-010**: Après l'envoi, le site MUST afficher un récapitulatif avec la référence
  MC-AAMMJJ-XXXX et « réponse sous 24 heures » ; il n'envoie aucun email automatique au client
  et n'annonce jamais une commande confirmée ni un créneau réservé.
- **FR-011**: Le formulaire MUST porter un champ piège et une limite d'envois par origine et par
  heure ; les messages d'erreur ne contiennent aucune donnée personnelle.
- **FR-012**: Le site MUST présenter la personnalisation des anniversaires : 4 à 12 personnes,
  plaque en chocolat avec prénom et message comprise dans le prix, acompte de 30 % au-delà de la
  plaque, commande au moins 2 jours à l'avance, pas de figurine ni de photo imprimée.
- **FR-013**: La section Professionnels MUST présenter l'offre (plateaux de petites pièces,
  boîtes individuelles, gâteaux à partager, coffrets) et les restaurants clients cités avec leur
  accord écrit, sans aucun prix professionnel ni photo de restaurant.
- **FR-014**: Chaque page MUST donner accès aux mentions légales, à la confidentialité, aux
  conditions de commande et à Instagram ; les infos pratiques (retrait sur rendez-vous, jours et
  heures, paiement au retrait) MUST être visibles avant l'envoi d'une demande.
- **FR-015**: Les conditions de commande MUST couvrir la demande sans engagement, la proposition
  et son acceptation, l'acompte, l'annulation jusqu'à 24 heures avant, le retrait, le paiement,
  les réclamations, le médiateur de la consommation et l'exception au droit de rétractation.
- **FR-016**: Chaque fiche MUST pouvoir être partagée (partage du téléphone, sinon copie du
  lien), avec une image de partage propre au gâteau.
- **FR-017**: Chaque page MUST avoir un titre, une description et une image de partage ; l'accueil
  et les fiches portent les données structurées d'une pâtisserie ; le site publie un plan du
  site et un fichier robots.
- **FR-018**: Les textes visibles du site MUST être en français.
- **FR-019**: La mesure d'audience MUST fonctionner sans cookie ni donnée identifiante, dans le
  cadre de l'exemption de la CNIL, et un visiteur MUST pouvoir la refuser depuis la page
  Confidentialité.
- **FR-020**: Les pages MUST NOT charger de script, de police ou d'image d'un service tiers, en
  dehors de la mesure d'audience de Madame Cho.
- **FR-021**: Le site MUST NOT afficher d'avis de clients tant qu'aucun avis n'a été recueilli
  avec l'accord écrit du client ; le jour où un avis est publié, une page « Avis : comment nous
  les recueillons » MUST l'accompagner (code de la consommation, L111-7-2 et D111-9 à D111-12).
- **FR-022**: Les photos MUST montrer les vrais produits et rester fidèles au produit réel,
  même retouchées (lumière, cadrage, fond) ; une image entièrement générée par IA qui serait
  publiée MUST être signalée comme telle.
- **FR-023**: Quand l'enregistrement des demandes du site est activé dans l'espace de gestion,
  chaque demande valide MUST y être enregistrée avec la même référence, et l'email MUST contenir
  le lien « Dans la gestion » ; si l'enregistrement échoue, l'email part quand même.
- **FR-024**: Les demandes restées sans suite MUST disparaître au plus tard 12 mois après le
  dernier échange, y compris leurs emails dans la boîte de Madame Cho (liste mensuelle).

### Key Entities *(include if feature involves data)*

- **Gâteau** : identifiant stable (adresse de sa page), nom, phrase, photo, allergènes,
  conservation, délai, lot minimum, statut sur la carte, fiche validée par Youngmi.
- **Variante** : une taille (nombre de personnes) ou un parfum d'un gâteau, avec son prix TTC.
- **Panier** : gâteaux, variantes et quantités choisis par un visiteur, dans son navigateur
  seulement.
- **Demande** : référence MC-AAMMJJ-XXXX, lignes, total indicatif recalculé, jour et moment du
  retrait souhaités, personnalisation (message de la plaque compris), besoin d'un
  professionnel, coordonnées du
  demandeur ; elle vit dans la boîte de Madame Cho, et dans l'espace de gestion quand
  l'enregistrement est activé.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Sur un téléphone, 4 personnes sur 5 essayées trouvent un gâteau, choisissent sa
  taille, comprennent le retrait et envoient une demande en moins de 2 minutes.
- **SC-002**: 100 % des demandes valides d'essai arrivent dans la boîte de Madame Cho, complètes
  et signées par le domaine ; 0 doublon après un double clic.
- **SC-003**: 0 gâteau affiché sans ses 14 allergènes, son prix TTC et son délai.
- **SC-004**: Madame Cho répond à au moins 90 % des demandes en moins de 24 heures, mesuré chaque
  mois dans l'espace de gestion.
- **SC-005**: Aucune page ne défile horizontalement à 320 pixels de large ; tout texte courant a
  un contraste d'au moins 4,5:1 ; les zones à toucher font environ 44 pixels.
- **SC-006**: Sur un téléphone en 4G, la photo d'ouverture de l'accueil s'affiche en moins de
  2,5 secondes.
- **SC-007**: Le visiteur n'a jamais de bandeau de cookies à accepter, et aucun appel vers un
  service tiers n'est fait à part la mesure d'audience de Madame Cho.

## Assumptions

- Version 1 sans paiement en ligne ni livraison : on retire sa commande sur rendez-vous à
  Meudon ; la commande et le paiement en ligne viendront dans une version 2.
- Youngmi lit les demandes chaque jour et répond sous 24 heures ; Olivier prend le relais quand
  elle est absente (décision d'Olivier du 02/11/2026).
- Les photos de lancement sont de vraies photos des gâteaux, retouchées (lumière et fond) ;
  décision d'Olivier du 03/11/2026 : pas de mention sur le site, puisqu'aucune n'est entièrement
  générée (FR-022) ; elles seront remplacées par de nouvelles photos au fil des commandes.
- La section Professionnels est publiée (décision prise à l'audit du site du 03/11/2026) ; la
  confirmation du régime sanitaire des ventes aux professionnels par la DDPP reste à obtenir.
- Le médiateur de la consommation (CM2C) est choisi ; l'adhésion de Madame Cho est en cours, et
  ses coordonnées entrent dans les conditions de commande dès qu'elle est faite.
- La redirection du QR code des emballages et la provenance des visites sont traitées par un
  autre chantier (QR code des emballages) et ne font pas partie de cette spécification.
- La fiche Google de Madame Cho est créée par Youngmi, hors du site ; le site lui donne seulement
  un lien stable.
