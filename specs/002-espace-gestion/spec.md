# Feature Specification: Espace de gestion (clients, commandes, produits, manuel et assistant)

**Feature Branch**: `002-espace-gestion`

**Created**: 2026-11-07

**Status**: En ligne depuis le 2026-11-06 (dernière version publiée le 2026-11-07) ; spécification
reprise au format Spec Kit ; mise à jour le 2026-11-07 au soir après l'audit n° 2 (lot C1, C2, C3, C8),
puis après les échanges avec les clients (mails envoyés depuis la gestion, réponses des clients) et
le parcours de test d'Olivier du 2026-11-07

**Input**: « 2026-11-07 Claude Dossier de l'espace de gestion Madame Cho » (Drive, 91/03) : la
proposition de fichier clients, la spécification v1, le compte rendu du prototype et celui du
manuel et de l'assistant ; plus les décisions qui ont suivi (restaurants, audit du 07/11, revue
d'Olivier du 07/11). Le Drive fait foi pour les décisions ; ce texte n'en garde rien de privé.

## Clarifications

### Session 2026-11-07

- Q: Comment sauvegarder la gestion, que la sauvegarde du Drive (P-24) ne couvre pas ? → A :
  par les copies automatiques de l'hébergeur seulement, avec un essai de restauration fait une
  fois ; rien de plus à faire chaque trimestre (choix d'Olivier, 2026-11-07, FR-026).
- Q: Que voit l'assistant, et que peut-il faire ? → A : c'est un assistant interne à la gestion ;
  il lit toutes ses données, noms et coordonnées des clients compris, et prépare les modifications
  des données que Youngmi demande ; chacune attend son toucher sur « Confirmer ». Il ne change
  jamais la structure de l'outil (demandes d'Olivier et choix « Youngmi confirme d'un clic »,
  2026-11-07, FR-017, FR-027).
- Q: Comment le consulter les mains prises ? → A : par un mode vocal ; Youngmi parle, il répond à
  voix haute, en français ou en coréen selon la langue choisie dans l'outil (demandes d'Olivier,
  2026-11-07, FR-028).

### Session 2026-11-07 (soir, audit n° 2 du ChatGPT d'Olivier)

- Q: Quand l'assistant prépare « commande remise » sans que Youngmi parle du paiement, que
  devient le paiement ? → A : il reste celui de la fiche (« Pas encore payé » si rien n'est
  noté), et la carte de confirmation montre toujours le paiement et le moyen qui seront écrits ;
  jamais « payé par carte » en silence. L'assistant demande le paiement quand il ne le sait pas
  (audit n° 2, C1 ; « Tout retenir, C1-C3 et C8 d'abord », Olivier, 2026-11-07, FR-027).
- Q: Changer la date ou la quantité d'une commande change-t-il ses prix ? → A : non ; un gâteau
  déjà dans la commande garde le prix et la TVA du jour où il a été commandé, à la main comme par
  l'assistant ; seul un gâteau ajouté prend le prix de la carte (audit n° 2, C2, FR-029).
- Q: Que se passe-t-il si la fiche change entre la préparation et le toucher sur « Confirmer » ?
  → A : la modification est refusée, rien ne change, et elle se redemande ; un simple
  commentaire ajouté entre-temps ne compte pas comme un changement (audit n° 2, C3, FR-027).
- Q: Le plafond du mois est-il strict ? → A : oui ; avant chaque appel au modèle, la gestion
  réserve son coût le plus haut possible et s'arrête si le plafond serait dépassé (audit n° 2,
  C8, FR-019, SC-007).
- Q: Un paiement Tap to Pay noté par Youngmi et le versement reçu dans Qonto, est-ce la même
  chose ? → A : non ; « payé, constaté par Youngmi » (avec qui et quand) et « rapproché Qonto »
  (avec la référence de la transaction) sont deux états séparés ; le versement ne crée ni une
  seconde vente ni un second paiement ; les espèces n'ont pas de rapprochement (audit n° 2, C14 ;
  Olivier : « oui je veux préciser », 2026-11-07, FR-030). Rapprochement à la main d'abord, par la
  liaison Qonto en lecture ensuite (proposé, en attente du choix d'Olivier).

### Session 2026-11-07 (nuit, échanges avec les clients et parcours de test d'Olivier)

- Q: Qui envoie les mails aux clients ? → A : la gestion, depuis contact@madamecho.com, signés
  DKIM, seulement au toucher de Youngmi sur « Envoyer » après l'aperçu ; jamais l'assistant ni
  une IA (rulebook v0.45, R-01 complétée ; FR-005). Gmail reste le secours.
- Q: Comment les réponses des clients arrivent-elles ? → A : un script Google installé dans
  contact@, en lecture seule, transmet chaque minute les mails dont l'objet porte une référence
  MC-… ; la gestion les range dans la commande (FR-031).
- Q: Dans quel ordre montrer les échanges ? → A : comme une messagerie, le plus récent en haut ;
  ce qui n'a pas été vu est sous un trait « Nouveaux messages », puis vient « Déjà vus » (Olivier :
  « une messagerie c'est généralement les récents en haut », FR-032).
- Q: Comment Youngmi sait-elle où en est une commande ? → A : une frise des étapes reste en haut
  de l'écran et dit l'étape à faire maintenant (FR-034).
- Q: Youngmi peut-elle parler coréen pour écrire au client ? → A : oui ; deux boutons, « Dicter
  en français » et « Parler en coréen » (le texte devient du français corrigé, à relire) ;
  « Corriger mon français » reste ; aucun texte en coréen ne part au client (FR-035).
- Q: Comment effacer une commande d'essai ? → A : « Supprimer la commande » en tapant SUPPRIMER,
  seulement sans facture, sans rapprochement Qonto et sans paiement noté reçu ; une fiche client
  sans commande se supprime de la même façon ; une trace reste (FR-002, à confirmer par Olivier).

### Session 2026-11-08 (rappel de retrait, demande d'Olivier)

- Q: Le client reçoit-il un rappel avant le retrait ? → A : oui, un 5e mail « Rappel » au même
  design, dans la même conversation que la commande, la veille du retrait (FR-040).
- Q: Part-il tout seul ? → A : pas pour l'instant. Choix d'Olivier du 2026-11-08 : « au toucher
  d'abord, automatique ensuite » ; pendant le pilote, Youngmi le voit sur Aujourd'hui et touche
  « Envoyer » ; l'envoi automatique demandera une exception à R-01 décidée par Olivier (R-16).
- Q: Et un client sans email, ou qui préfère le SMS ? → A : une version courte à copier pour un
  SMS ou WhatsApp, puis « J'ai envoyé le rappel » note l'échange avec son canal (FR-040).
- Q: Et les livraisons aux restaurants ? → A : pas de rappel : elles sont régulières et convenues
  avec le restaurant.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Savoir ce qu'il y a à faire aujourd'hui (Priority: P1)

Youngmi ouvre l'espace sur son téléphone et voit d'un coup d'œil les demandes à répondre (avec
leur âge, en rouge au-delà de 24 heures), les retraits des 7 prochains jours jour par jour, les
problèmes ouverts, les acomptes attendus, les retraits passés à clôturer et les chiffres du mois.
Chaque case mène à la liste ou à la page qui la détaille.

**Why this priority**: c'est l'écran qui tient la promesse « réponse sous 24 heures » du site.

**Independent Test**: avec des données fictives, une demande vieille de 25 heures apparaît en
rouge dans « À répondre » ; toucher la case « À répondre » descend jusqu'à la liste.

**Acceptance Scenarios**:

1. **Given** une demande reçue il y a plus de 24 heures sans réponse, **When** Youngmi ouvre
   Aujourd'hui, **Then** elle la voit en tête, marquée en retard.
2. **Given** les cases du haut, **When** Youngmi touche « Retraits à venir », **Then** l'écran
   descend jusqu'aux retraits des 7 prochains jours.

---

### User Story 2 - Faire avancer une commande jusqu'à la remise (Priority: P1)

De la demande à la remise, Youngmi fait avancer chaque commande d'un geste : proposition envoyée
(mail envoyé depuis la gestion à son toucher, après aperçu), confirmée (heure de retrait fixée,
ajout à son agenda avec un rappel la veille), remise (prix final et paiement). Une commande peut
aussi finir sans suite ou annulée, et porter un drapeau « problème » à n'importe quelle étape.

**Why this priority**: c'est le travail quotidien que l'outil remplace.

**Independent Test**: créer une demande fictive, la mener jusqu'à « Remise » en passant par toutes
les étapes ; l'historique montre chaque changement, daté et signé.

**Acceptance Scenarios**:

1. **Given** une demande, **When** Youngmi prépare sa proposition, **Then** l'outil lui montre le
   mail tel qu'il partira, qu'elle peut modifier, et ne l'envoie qu'à son toucher sur « Envoyer ».
2. **Given** une commande avec un jour de retrait, **When** Youngmi touche « Ajouter à Google
   Agenda », **Then** son agenda s'ouvre sur l'événement du retrait, prêt à enregistrer ; le
   fichier agenda (.ics) donne le même événement avec un rappel la veille.
3. **Given** une demande ou une proposition sans échange depuis 15 jours, **When** Youngmi
   l'ouvre, **Then** l'outil lui signale qu'elle dort et lui propose de la passer « Sans suite ».
4. **Given** un problème signalé, **When** il n'est pas résolu, **Then** il reste visible dans
   toutes les listes jusqu'à « Résolu ».
5. **Given** un client qui répond au mail de la commande, **When** le script de contact@ passe,
   **Then** sa réponse apparaît en moins de 2 minutes dans les échanges de la commande, en haut,
   marquée « Nouvelle réponse » aussi dans la liste des commandes et sur Aujourd'hui.
6. **Given** une commande ouverte, **When** Youngmi fait défiler l'écran, **Then** la frise des
   étapes reste en haut et dit l'étape à faire maintenant.
7. **Given** une commande confirmée dont le retrait est demain et dont la confirmation n'est pas
   partie la veille ou le jour même, **When** Youngmi ouvre Aujourd'hui, **Then** la commande est
   dans « Rappels à envoyer » ; elle en sort dès que le rappel est envoyé ou noté.

---

### User Story 3 - Noter une demande reçue ailleurs (Priority: P1)

Une demande arrive par Instagram, par téléphone, sur un marché ou en personne. Youngmi la saisit
avec « Nouvelle commande » : client existant (retrouvé par son email, sinon son téléphone) ou
nouveau (prénom et nom), gâteaux de la carte, personnalisation, jour et moment du retrait, canal.

**Why this priority**: une partie des demandes n'arrive pas par le site.

**Independent Test**: saisir une demande pour un client déjà connu par son téléphone ; elle se
range dans sa fiche, sans créer de doublon.

**Acceptance Scenarios**:

1. **Given** un client connu par son email, **When** une nouvelle demande porte le même email,
   **Then** elle est rattachée à ce client.
2. **Given** un client sans email connu par son téléphone, **When** une demande porte le même
   numéro, **Then** elle est rattachée à ce client.

---

### User Story 4 - Livrer un restaurant et préparer son relevé du mois (Priority: P2)

Pour un restaurant, chaque livraison est une commande, aux prix HT de la gamme pro. En fin de
mois, Youngmi ouvre le relevé du restaurant : seulement les livraisons livrées et pas encore
facturées, pour préparer la facture dans son outil de facturation, puis elle note le numéro de
facture, sans jamais écraser une facture déjà notée.

**Why this priority**: les restaurants sont des clients réguliers et la facturation en dépend.

**Independent Test**: avec trois livraisons fictives (une livrée, une à venir, une déjà
facturée), le relevé n'en montre qu'une.

**Acceptance Scenarios**:

1. **Given** des livraisons d'un mois, **When** Youngmi ouvre le relevé, **Then** il ne contient
   que les livraisons livrées et sans facture.
2. **Given** une livraison déjà facturée, **When** Youngmi note un autre numéro de facture,
   **Then** l'outil refuse d'écraser le premier.

---

### User Story 5 - Tenir ses clients (Priority: P2)

Youngmi retrouve un client, voit toutes ses commandes, ajoute un commentaire (jamais une donnée
de santé), demande un avis Google et note quand il est reçu, fusionne deux fiches en double,
exporte les données d'un client qui les demande, ou les efface.

**Why this priority**: la fidélité et le bouche-à-oreille, et les droits RGPD des clients.

**Independent Test**: fusionner deux fiches fictives ; toutes les commandes passent sur une
seule ; exporter puis effacer une fiche : la commande reste pour les comptes, sans nom ni
coordonnées.

**Acceptance Scenarios**:

1. **Given** deux fiches au même email, **When** Youngmi les fusionne, **Then** il n'en reste
   qu'une avec toutes les commandes.
2. **Given** une demande d'effacement, **When** Youngmi efface la fiche, **Then** les commandes
   restent, anonymes, et l'historique note l'effacement.

---

### User Story 6 - Connaître ses produits, leur coût et leurs allergènes (Priority: P2)

Les gâteaux de la carte viennent du site, sans double saisie. Pour chaque taille, Youngmi peut
écrire la recette (ingrédients et quantités) : l'outil calcule le coût sur matières, la marge
sur matières et les allergènes déduits des ingrédients, et signale un écart avec ceux affichés
sur le site. La gamme pro (prix HT, unité, TVA) se tient au même endroit, identique à
l'outil de facturation.

**Why this priority**: utile pour fixer les prix et pour la sécurité des clients, mais pas
bloquant pour suivre les commandes.

**Independent Test**: une recette fictive qui contient un ingrédient avec du lait, pour un
gâteau affiché sans lait, déclenche l'alerte d'écart.

**Acceptance Scenarios**:

1. **Given** une recette complète, **When** Youngmi l'enregistre, **Then** l'outil affiche le coût
   et la marge sur matières de cette taille.
2. **Given** un écart d'allergènes, **When** Youngmi ouvre la fiche, **Then** l'écart est signalé
   et c'est elle qui tranche.

---

### User Story 7 - Recevoir les demandes du site dans l'outil (Priority: P2)

Une fois activé dans Paramètres (après le pilote avec Youngmi), chaque demande envoyée par le
formulaire du site s'ajoute toute seule, avec son client et la même référence que l'email.

**Why this priority**: plus de double saisie, mais seulement quand Youngmi a pris l'outil en main.

**Independent Test**: réglage coupé, une demande du site n'arrive que par email ; réglage activé,
la même demande apparaît aussi dans l'outil, une seule fois.

**Acceptance Scenarios**:

1. **Given** le réglage coupé, **When** une demande arrive, **Then** l'email contient un lien qui
   permet de la créer dans l'outil d'un geste.
2. **Given** le réglage activé, **When** une demande est renvoyée deux fois, **Then** l'outil
   n'en garde qu'une.

---

### User Story 8 - Utiliser l'outil en coréen (Priority: P1)

Youngmi utilise l'outil en coréen ; Olivier en français. Le choix se fait d'un bouton, reste sur
l'appareil et pendant la session. Ce que les gens ont écrit (noms, adresses, gâteaux,
commentaires) n'est jamais traduit, et les emails préparés pour les clients restent en français.

**Why this priority**: sans le coréen, Youngmi ne peut pas être autonome.

**Independent Test**: passer en coréen, parcourir tous les écrans : aucun texte de l'outil ne
reste en français, et les noms saisis ne changent pas.

**Acceptance Scenarios**:

1. **Given** le compte de Youngmi, **When** elle se connecte pour la première fois, **Then**
   l'outil s'ouvre en coréen.
2. **Given** un navigateur qui traduit automatiquement les pages, **When** Youngmi change de
   langue dans l'outil, **Then** le navigateur ne retraduit pas l'écran par-dessus.

---

### User Story 9 - Trouver de l'aide, se faire assister, signaler un problème (Priority: P2)

Youngmi lit le manuel (en coréen ou en français) ou pose sa question à l'assistant, qui répond à
partir du manuel et des données de la gestion, prépare les modifications qu'elle lui demande
(elle les confirme d'un toucher), lui dit franchement quand elle se trompe, et prépare un rapport
quand il y a un vrai problème ou une vraie idée. Les mains prises, elle peut lui parler, et il lui
répond à voix haute. Pour un rapport, elle voit l'email tel qu'il partira, choisit de joindre ou
non les derniers messages, et l'envoie elle-même à Olivier. Sans assistant, « Écrire à Olivier »
envoie un message simple.

**Why this priority**: c'est le circuit d'amélioration continue voulu par Olivier.

**Independent Test**: avec une fausse réponse de l'assistant qui propose un rapport, l'aperçu
montre l'email exact ; rien ne part avant le clic de Youngmi. Avec une fausse réponse qui prépare
un changement d'étape, rien ne change avant « Confirmer », et l'historique de la commande le note.

**Acceptance Scenarios**:

1. **Given** l'assistant pas encore relié, **When** Youngmi ouvre Assistant, **Then** elle voit
   qu'il n'est pas encore relié, le manuel et « Écrire à Olivier ».
2. **Given** un rapport proposé, **When** Youngmi ne coche pas la case des derniers messages,
   **Then** l'email ne contient que le rapport.
3. **Given** le plafond du mois atteint, **When** Youngmi pose une question, **Then** l'assistant
   ne répond plus jusqu'au mois suivant et le dit.
4. **Given** une modification préparée par l'assistant, **When** Youngmi touche « Confirmer »,
   **Then** la gestion la fait avec les mêmes contrôles qu'à l'écran, et l'historique note
   « Préparée par l'assistant et confirmée ».
5. **Given** une modification préparée il y a plus de 24 heures, déjà faite ou écartée, **When**
   Youngmi touche « Confirmer », **Then** rien ne change et l'écran le dit.
6. **Given** l'outil en coréen et le mode vocal allumé, **When** Youngmi pose sa question à voix
   haute, **Then** l'assistant répond par écrit et à voix haute en coréen.

---

### User Story 10 - Comptes, accès et sécurité (Priority: P1)

Seules les personnes qui travaillent pour Madame Cho ont un compte ; chacun le sien. Première
connexion et mot de passe oublié par un lien valable une heure ; invitation par une
administratrice ; un changement de mot de passe ferme les autres sessions ; trop d'échecs
bloquent un moment ; chaque connexion est notée. Aucune IA n'a de compte.

**Why this priority**: l'outil contient des données personnelles de clients.

**Independent Test**: 10 mots de passe faux bloquent l'accès 15 minutes ; un lien de mot de passe
utilisé ne sert pas deux fois ; changer son mot de passe déconnecte l'autre appareil.

**Acceptance Scenarios**:

1. **Given** une session ouverte sur deux appareils, **When** le mot de passe change sur l'un,
   **Then** l'autre est déconnecté à la page suivante.
2. **Given** un membre, **When** il ouvre Paramètres, **Then** il ne voit ni les accès ni les
   données de tous les clients à exporter.

---

### User Story 11 - Garder seulement le nécessaire (Priority: P2)

Chaque nuit, l'outil efface ce qui a dépassé sa durée de conservation et Paramètres montre ce qui
le sera bientôt, avec la liste des emails de demandes à supprimer aussi dans la boîte de Madame
Cho.

**Why this priority**: c'est la promesse de la page Confidentialité du site.

**Independent Test**: une demande fictive sans suite vieille de 12 mois et un jour disparaît
après la tâche de nuit ; une demande plus récente reste.

**Acceptance Scenarios**:

1. **Given** une demande sans suite dont le dernier échange a plus de 12 mois, **When** la tâche de
   nuit passe, **Then** ses données personnelles sont effacées.
2. **Given** un client sans commande ni échange depuis 3 ans, **When** la tâche passe, **Then** ses
   coordonnées sont effacées et sa société vidée.

---

### User Story 12 - Statistiques, production et exports (Priority: P3)

Olivier et Youngmi voient les ventes par mois, par canal et par produit, le taux de demandes
transformées, le délai moyen de réponse et les visites du site à côté des ventes ; ils
impriment la fiche de production d'un jour ou d'une semaine, sans coordonnées des clients ;
ils exportent les données pour un tableur ou pour le comptable.

**Why this priority**: pour piloter, une fois que l'outil est utilisé.

**Independent Test**: avec des données fictives, les totaux des statistiques égalent ceux des
commandes remises ; la fiche de production ne contient ni email ni téléphone.

**Acceptance Scenarios**:

1. **Given** des commandes remises dans le mois, **When** on ouvre Statistiques, **Then** le
   chiffre d'affaires et le panier moyen correspondent à ces commandes.
2. **Given** la fiche de production, **When** on l'imprime, **Then** elle liste les commandes
   confirmées, leur contenu et les plaques, sans coordonnées.

### Edge Cases

- Base de données indisponible : l'outil affiche une page d'excuse en français et en coréen, et
  les demandes du site continuent d'arriver par email.
- Demande du site enregistrée deux fois (renvoi du visiteur) : une seule commande.
- Commande remise sans date de retrait : elle montre sa date de remise.
- Deux personnes modifient la même commande : le dernier enregistrement l'emporte et
  l'historique note chaque enregistrement, avec les champs changés.
- Lien de mot de passe expiré ou déjà utilisé : refusé, avec un bouton pour en recevoir un autre.
- Commentaire qui ressemble à une donnée de santé : l'écran rappelle de ne pas en écrire.
- Clé de l'assistant refusée ou absente : l'assistant se dit « pas encore relié » ; rien d'autre
  ne change.
- Fiche visée par une modification préparée qui n'existe plus : « Impossible maintenant » ; fiche
  qui a changé depuis la préparation (autre chose qu'un commentaire) : « La fiche a changé »,
  rien ne change, la modification est écartée et l'assistant le sait à la question suivante.
- Prix de la carte changé après une commande : la commande garde ses prix ; seul un gâteau
  ajouté ensuite prend le nouveau prix.
- Question qui ferait dépasser le plafond du mois : refusée avant l'appel, avec un message qui
  dit de prévenir Olivier.
- Navigateur sans reconnaissance vocale, ou micro refusé : pas de mode vocal, ou un message qui
  le dit ; l'écrit marche comme avant.
- Écran au texte non traduit en coréen : il reste lisible en français plutôt que vide.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: L'outil MUST être privé, jamais indexé, réservé aux comptes invités, et MUST NOT
  avoir de compte pour une IA.
- **FR-002**: L'outil MUST tenir trois registres reliés par leurs numéros : clients (CL-…),
  commandes (MC-AAMMJJ-XXXX, la même référence que l'email du site), produits (avec
  ingrédients et recettes), et un historique daté et signé de chaque changement ; une vraie
  commande n'est jamais supprimée : une erreur se corrige par une modification notée dans
  l'historique, et un effacement vide les données personnelles sans toucher aux montants. Seule
  une commande d'essai ou saisie par erreur, sans facture, sans rapprochement Qonto et sans
  paiement noté reçu, MAY être supprimée par une personne qui tape SUPPRIMER ; de même une fiche
  client sans aucune commande. Ce qui est supprimé MUST aller dans une corbeille 30 jours (Plus › Corbeille,
  « Restaurer » d'un toucher), puis s'effacer tout seul ; un effacement RGPD du client vide aussi
  sa corbeille ; une ligne « mise à la corbeille » (référence, motif) reste dans le journal.
- **FR-003**: Une commande MUST suivre le cycle Demande → Proposition → Confirmée → Remise, avec
  les sorties Sans suite et Annulée (motif) ; le paiement (pas encore payé, acompte demandé,
  acompte reçu, payé, remboursé) et le drapeau « problème » sont tenus à part du statut.
- **FR-004**: Aujourd'hui MUST montrer les demandes à répondre avec leur âge (en retard après
  24 heures), les retraits des 7 prochains jours, les problèmes, les acomptes attendus, les
  retraits passés à clôturer et les chiffres du mois ; chaque case MUST mener à son détail.
- **FR-005**: Chaque étape MUST préparer le mail au client (proposition, confirmation, message,
  demande d'avis), montré tel qu'il partira et modifiable ; il MUST partir de contact@, signé
  DKIM, seulement au toucher de l'utilisatrice sur « Envoyer », dans une seule conversation par
  commande (même objet, In-Reply-To) ; les boutons qui ouvrent un mail MUST dire que rien ne part
  avant « Envoyer ». L'assistant MUST NOT envoyer de mail.
- **FR-006**: Une commande en cours qui a un jour de retrait MUST pouvoir s'ajouter à l'agenda
  (Google Agenda, ou un fichier agenda avec un rappel la veille).
- **FR-007**: Une demande ou une proposition sans échange depuis 15 jours MUST être signalée et
  proposée « Sans suite ».
- **FR-008**: « Nouvelle commande » MUST rattacher la demande à un client existant par son email,
  sinon par son téléphone, ou créer un client avec son prénom et son nom.
- **FR-009**: Pour un restaurant, chaque livraison MUST être une commande aux prix HT de la gamme
  pro ; le relevé du mois MUST ne compter que les livraisons livrées et sans facture, et un
  numéro de facture MUST NOT en écraser un autre.
- **FR-010**: La fiche client MUST montrer toutes ses commandes, les commentaires, l'avis Google
  demandé ou reçu, et permettre de fusionner un doublon, d'exporter ses données et de les
  effacer (anonymisation : les commandes restent sans nom ni coordonnées).
- **FR-011**: Les produits de la carte MUST être lus depuis le catalogue du site ; la recette par
  taille MUST donner le coût et la marge sur matières et les allergènes déduits, comparés à ceux
  affichés sur le site ; les recettes ne quittent jamais l'outil.
- **FR-012**: La gamme pro (nom, prix HT, unité, TVA) MUST se tenir dans l'outil et rester
  identique à l'outil de facturation ; un changement vaut pour les livraisons suivantes.
- **FR-013**: L'enregistrement automatique des demandes du site MUST être coupé tant qu'une
  administratrice ne l'active pas ; activé, une demande renvoyée ne crée pas de doublon.
- **FR-014**: L'outil MUST être entièrement utilisable en français et en coréen ; le choix MUST
  tenir sur l'appareil et pendant la session ; le compte de Youngmi s'ouvre en coréen ; le
  texte saisi n'est jamais traduit ; les emails préparés pour les clients restent en français.
- **FR-015**: L'outil MUST demander au navigateur de ne pas traduire ses écrans.
- **FR-016**: Le manuel MUST décrire chaque écran en français et en coréen, être la seule source
  de l'assistant sur l'usage de l'outil, et dire l'état réel de l'outil.
- **FR-017**: L'assistant MUST répondre dans la langue de l'écran, à partir du manuel et des
  données de la gestion (commandes, clients avec leurs coordonnées, gâteaux, recettes,
  ingrédients, agenda, indispos, chiffres, visites du site), qu'il lit sans les changer ; il
  MUST NOT inventer un nom, un chiffre ou une date absents des données ; il MUST dire clairement
  quand l'utilisatrice se trompe et proposer un rapport seulement pour un vrai problème, une
  vraie idée ou une question sans réponse dans le manuel.
- **FR-018**: Un rapport MUST être montré tel qu'il partira, avec l'extrait des derniers messages
  joint seulement si l'utilisatrice coche la case, et partir seulement sur son clic, vers la
  boîte de Madame Cho, signé par le domaine ; « Écrire à Olivier » MUST marcher sans assistant.
- **FR-019**: L'assistant MUST avoir un plafond de questions par jour (tous comptes) et un
  plafond de coût par mois réglable par une administratrice (0 = aucun), une question ratée
  comptant aussi ; le plafond MUST être strict : avant chaque appel au modèle, son coût le plus
  haut possible est réservé, et l'appel n'a pas lieu s'il ferait dépasser le plafond ; une
  notice MUST informer l'utilisatrice avant sa première conversation (destinataire, pays,
  données lues, voix, durées, pas de donnée de santé).
- **FR-020**: Les comptes MUST être nominatifs, avec un mot de passe d'au moins 12 caractères ;
  lien de première connexion ou d'oubli valable une heure et une seule fois ; invitation par une
  administratrice ; rôles Administratrice et Membre ; 10 échecs bloquent 15 minutes ; « rester
  connectée » 30 jours ; changement de mot de passe = autres sessions fermées ; journal des
  connexions.
- **FR-021**: Chaque nuit, l'outil MUST effacer : les données personnelles d'une demande qui n'a
  pas abouti (sans suite, annulée ou restée sans réponse) 12 mois après le dernier échange ; un
  client sans commande en cours ni échange depuis 3 ans, ou qui n'a jamais commandé, 12 mois
  après son dernier échange ; le journal des connexions et les conversations de l'assistant
  après 6 mois ; les rapports après 12 mois ; les liens de connexion expirés ou utilisés.
  Paramètres MUST montrer ce qui sera effacé, et la liste des demandes du site de plus de 11 mois
  dont l'email est à supprimer aussi dans la boîte de Madame Cho. L'effacement d'un client MUST
  aussi retirer son nom et ses coordonnées des conversations de l'assistant.
- **FR-022**: Les statistiques MUST donner, par mois, par canal et par produit, les commandes
  remises, le chiffre d'affaires, le panier moyen, la marge sur matières quand les recettes sont
  remplies, le taux de transformation, le délai médian de réponse aux demandes du site et la
  part de réponses en moins de 24 heures, et les visites du site quand la clé de mesure est
  collée ; l'export MUST donner des fichiers pour un tableur.
- **FR-023**: La fiche de production MUST lister, pour un jour ou une semaine, les commandes
  confirmées, leur contenu et les plaques, sans email ni téléphone.
- **FR-024**: Les clés d'accès (assistant, mesure d'audience) MUST être collées une fois par une
  administratrice, vérifiées, jamais réaffichées et jamais exportées.
- **FR-025**: Paramètres MUST montrer un contrôle technique (version du serveur, base de données,
  données rangées hors du site, connexion chiffrée, signature des emails, carte du site,
  dernières demandes du site, mesure d'audience reliée) ; si la base ne répond pas, l'outil
  MUST l'annoncer en français et en coréen, et les demandes du site continuent d'arriver par
  email.
- **FR-026**: La sauvegarde de la gestion MUST être celle des copies automatiques de
  l'hébergeur (la base est un fichier de l'espace d'hébergement) ; un essai de restauration MUST
  être fait une fois, et son résultat noté. Une administratrice MUST aussi pouvoir télécharger un
  export complet (clients, commandes, produits, ingrédients, recettes ; ni comptes, ni clés, ni
  conversations) pour une analyse ou une reprise ; ce n'est pas une sauvegarde.
- **FR-027**: Une modification préparée par l'assistant MUST s'afficher sous sa réponse avec ce
  qui va changer, y compris ce que le formulaire écrira sans que la proposition le dise (le
  paiement et le moyen d'une remise, d'une commande créée remise), « Confirmer » et « Ne pas
  faire » ; un paiement MUST NOT changer sans que l'utilisatrice l'ait dit ou vu ; la
  modification MUST être refusée si la fiche a changé depuis sa préparation (hors commentaire) ;
  rien ne change sans le toucher de
  l'utilisatrice ; elle MUST passer par les mêmes contrôles que l'écran, s'inscrire dans
  l'historique (« Préparée par l'assistant et confirmée »), ne se faire qu'une fois et expirer
  après 24 heures. Elle peut porter sur une commande (étape, paiement, fiche, problème, échange,
  nouvelle commande), un client (fiche, échange, avis, actif, nouveau client), une indispo, la
  fiche d'un gâteau, la gamme pro, une recette ou un ingrédient. L'assistant MUST NOT toucher aux
  paramètres, aux comptes, aux clés, à l'import, à l'export, à la fusion ni à l'effacement d'un
  client, et MUST NOT envoyer d'email.
- **FR-028**: Un mode vocal MUST permettre de parler à l'assistant et de l'entendre, dans la
  langue choisie dans l'outil (français ou coréen), avec la reconnaissance et la voix du
  navigateur ; « stop », « 그만 » ou le bouton Arrêter l'arrêtent ; le micro MUST être autorisé
  pour la gestion seulement ; une modification préparée MUST attendre le toucher de
  « Confirmer », même en mode vocal.
- **FR-030**: Un paiement MUST garder qui l'a constaté et quand ; un état « rapproché Qonto »,
  séparé du paiement, MUST se noter avec la référence de la transaction Qonto, jamais par le nom
  ou le montant seuls ; les espèces MUST en être dispensées ; une liste MUST montrer les paiements
  par carte ou virement pas encore rapprochés ; un versement Qonto MUST NOT créer de vente ni de
  paiement ; Qonto reste en lecture seule (R-24).
- **FR-029**: Un gâteau déjà dans une commande MUST garder le prix et la TVA enregistrés à la
  commande quand on change la date, l'heure, le mode ou la quantité, à la main comme par
  l'assistant ; seul un gâteau ajouté prend le prix actuel de la carte.

- **FR-031**: Les réponses des clients MUST arriver dans la commande par un script installé dans
  contact@, en lecture seule, authentifié par une clé dont la gestion ne garde que l'empreinte ;
  seuls les mails dont l'objet porte une référence MC-… sont transmis (expéditeur, date, objet,
  texte, noms des pièces jointes) ; un mail sans commande reconnue est gardé 30 jours dans
  Paramètres ; 24 heures sans nouvelles du script MUST déclencher une alerte sur Aujourd'hui.
- **FR-032**: Les échanges d'une commande MUST se lire comme une messagerie : le plus récent en
  haut, le client d'un côté, Madame Cho de l'autre, les échanges notés au milieu, chacun avec son
  icône et son heure ; ce qui n'a pas été vu MUST être sous un trait « Nouveaux messages », suivi
  d'un trait « Déjà vus » ; un long mail se replie.
- **FR-033**: « Noter un échange » MUST garder ce qui ne passe pas par les mails, avec « Par »
  (téléphone, SMS, WhatsApp, de vive voix, Instagram, autre), choisi d'un toucher avec la même
  icône que dans la conversation ; rien ne s'enregistre avant « Ajouter la note ».
- **FR-034**: Une commande de particulier en cours MUST montrer en haut, et garder visible en
  défilant, la frise de ses étapes (demande reçue, proposition envoyée, commande confirmée,
  confirmation envoyée, remise, avis demandé) avec l'étape à faire maintenant ; « Confirmer la
  commande » MUST être aussi en bas des échanges, et « Modifier la commande » en haut ; après une
  modification, le bandeau MUST dire ce qui a changé ; chaque bandeau se ferme d'une croix.
- **FR-035**: Sous chaque texte destiné à un client, l'outil MUST proposer « Dicter en français »,
  « Parler en coréen » (le texte dicté devient du français corrigé, à relire) et « Corriger mon
  français » (texte corrigé et son sens en coréen ; « Utiliser ce texte » ou « Garder le mien ») ;
  un texte en coréen MUST NOT partir au client. Les drapeaux de langue sont dessinés en SVG.
- **FR-036**: La même chose (commande, client, produit, échanges, agenda, canaux) MUST avoir partout
  la même icône, dans le menu (ordinateur compris), devant le titre de chaque page et de chaque
  cadre ; les paramètres s'appellent « Paramètres », avec l'engrenage, comme sur la plupart des sites.
- **FR-037**: (audit n° 3, 2026-11-07) Une réponse dont l'adresse d'expédition n'est pas celle de la
  fiche du client MUST être rangée avec la commande mais marquée « Expéditeur différent du client :
  à vérifier » ; des clés fausses MUST bloquer seulement l'appareil qui se trompe, jamais la
  réception pour tous ; un même mail touché deux fois en moins de 2 minutes MUST NOT partir deux
  fois, et un envoi en erreur MUST dire de regarder les Échanges ou Gmail avant de réessayer ;
  chaque texte de client donné à l'assistant MUST porter sa provenance (une donnée, jamais une consigne).
- **FR-038**: La fiche d'un client MUST montrer « Tous les échanges » de toutes ses commandes, le plus
  récent en haut, chaque message avec la référence MC-… qui ouvre sa commande ; le journal
  détaillé (étapes, paiements, modifications) reste à part, replié.
- **FR-039**: Les groupes MUST avoir chacun sa couleur, différente du marron des boutons et des
  filtres et des couleurs d'étape : professionnels (B2B) en violet, particuliers (B2C) en bleu-vert,
  les mêmes dans les étiquettes, la gamme pro et les statistiques (demande d'Olivier, 2026-11-07).
- **FR-040**: (demande d'Olivier, 2026-11-08) Une commande de particulier confirmée MUST pouvoir
  recevoir un mail « Rappel » (étiquette RAPPEL · MC-…, même objet que la commande) qui redit le
  jour, l'heure, l'adresse, les gâteaux et le reste à régler, et invite à répondre en cas
  d'empêchement. Aujourd'hui MUST lister les rappels à envoyer : commandes confirmées, retrait
  demain, pas de livraison de restaurant, ni rappel déjà envoyé ou noté, ni confirmation partie la
  veille du retrait ou après. Le rappel MUST partir au toucher de « Envoyer » après l'aperçu,
  comme les autres mails (R-01) ; une version courte MUST être proposée à copier pour un SMS ou
  WhatsApp, et « J'ai envoyé le rappel » MUST le noter avec son canal. L'envoi automatique est
  hors périmètre tant qu'Olivier ne l'a pas décidé.
  Audit n° 4 (2026-11-08) : un rappel ou une confirmation MUST valoir pour un rendez-vous précis
  (jour, heure, moment) ; si le rendez-vous change, la commande MUST revenir dans la liste,
  marquée « Retrait modifié » ; seule l'action « J'ai envoyé le rappel » (SMS, WhatsApp,
  téléphone, Gmail) MUST compter comme rappel déclaré, jamais une note libre ; un rappel déjà
  fait MUST NOT repartir sans « Renvoyer » ; un mail MUST NOT partir si la commande a changé
  depuis l'aperçu relu ; une commande déjà payée MUST NOT recevoir « à régler au retrait » ; un
  retrait du jour sans rappel MUST rester proposé ; pas de lien qui ouvre WhatsApp avec le texte
  prérempli pendant le pilote.

### Key Entities *(include if feature involves data)*

- **Client** : numéro CL-…, particulier ou professionnel, prénom et nom ou société et contact,
  email, téléphone, ville, canal d'origine, premier contact, dernier échange, avis Google,
  accord pour une lettre d'information (prévu), statut (actif, inactif, effacé).
- **Commande** : référence, client, canal, type (carte, personnalisée, professionnel), statut,
  paiement, moyen, facture, problème, retrait (date, moment, heure, mode), lignes, plaque et
  personnalisation, besoin d'un professionnel, montants (indicatif, final, acompte), dates.
- **Événement** : une ligne datée et signée de l'historique d'une commande, d'un client ou d'un
  produit (changement, commentaire avec son canal, mail envoyé, mail reçu et « vu », effacement,
  suppression d'un essai).
- **Mail reçu** : identifiant Gmail, date, commande rattachée ou orphelin (30 jours).
- **Produit** et **variante** : gâteau de la carte ou de la gamme pro, statut, fiche validée,
  TVA, emballage, recette par variante.
- **Ingrédient** : nom, unité, prix d'achat, allergènes, fournisseur.
- **Compte** : email, nom, rôle, état, dernière connexion ; **jeton** de première connexion,
  d'oubli ou d'invitation.
- **Conversation de l'assistant**, **rapport** : messages, coût, rapport proposé ou envoyé.
- **Modification préparée** : action, fiche visée, champs, résumé, état (à confirmer, faite, pas
  faite, expirée, remplacée), dates, retour de la gestion.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Pendant le pilote, Youngmi mène seule, en coréen et sur son téléphone, une demande
  de la saisie à la remise en moins de 3 minutes de manipulations.
- **SC-002**: Au moins 90 % des demandes reçoivent une proposition en moins de 24 heures, mesuré
  par l'outil lui-même.
- **SC-003**: 0 texte de l'outil resté en français sur les écrans en coréen, et 0 texte saisi
  traduit.
- **SC-004**: Le relevé du mois d'un restaurant égale, à l'euro près, la facture préparée à
  partir de lui, et ne contient jamais une livraison déjà facturée.
- **SC-005**: 100 % des données qui ont dépassé leur durée sont effacées dans les 24 heures.
- **SC-006**: 0 email envoyé à un client sans le toucher de l'utilisatrice sur « Envoyer » ; 0
  rapport envoyé sans son clic ; 0 mail envoyé par l'assistant.
- **SC-011**: La réponse d'un client apparaît dans sa commande en moins de 2 minutes (mesuré le
  2026-11-07 sur un vrai mail).
- **SC-012**: 0 vente payée, facturée ou rapprochée supprimable.
- **SC-013**: 0 rappel envoyé deux fois pour la même commande sans le toucher « Renvoyer », et 0
  rappel proposé pour une commande annulée, remise ou sans jour fixé.
- **SC-007**: Le coût de l'assistant ne dépasse jamais le plafond du mois fixé dans Paramètres.
- **SC-008**: 0 donnée changée par l'assistant sans le toucher de « Confirmer » de l'utilisatrice.
- **SC-009**: 0 paiement changé par une modification de l'assistant sans être affiché sur sa carte
  avant « Confirmer ».
- **SC-010**: 0 montant d'une commande existante changé par un simple changement de date.

## Assumptions

- Youngmi travaille surtout sur son téléphone, en coréen ; Olivier sur ordinateur, en français.
- Volume : quelques demandes par jour au plus, quelques centaines de clients ; deux ou trois
  comptes. Aucun besoin de montée en charge.
- Le pilote de 30 à 45 minutes avec Youngmi a lieu avant d'activer l'assistant et les demandes du
  site ; la clé de l'assistant est payée par Olivier, comme ses autres outils informatiques.
- La reconnaissance vocale est celle du navigateur (Google sur Chrome, Apple sur Safari) : la voix
  y est transcrite en texte ; la gestion ne garde que le texte, comme une question écrite.
- Le miroir pour le Drive (copie réduite pour les agents) est prêt mais pas branché, et le point
  commandes de l'agent Mathieu est prévu mais pas en place : ils ne font pas partie de cette
  version.
- La double authentification, le paiement en ligne et la lettre d'information viendront plus
  tard ; l'accord pour la lettre est seulement prévu dans la fiche client.
- Ce que Youngmi envoie depuis son Gmail personnel « en tant que » contact@ ne passe pas par la
  boîte contact@ et ne revient donc pas dans la gestion.
- Deux demandes d'Olivier du 07/11/2026 sont à cadrer avant toute spécification : un assistant
  qui dépend de la personne connectée, et un écran Agenda relié au Google Agenda de Youngmi.
