# Feature Specification: Paiement en ligne des commandes

**Feature Branch**: `006-paiement-en-ligne` (branche de travail `claude/project-thread-php79v`)

**Created**: 2026-11-21

**Status**: Brouillon, deux décisions d'Olivier à prendre (marqueurs ci-dessous) ; aucun code écrit

**Input**: Demande d'Olivier du 2026-11-21 : « ajouter le paiement direct en choix de paiement sur
le site », puis : « on a des offres à la carte qui sont fixes et des offres personnalisables qui
demandent un échange avec Youngmi. Le paiement se fait en espèces ou en Tap to Pay au retrait. Il y
a moyen de payer des acomptes mais je ne sais plus comment. J'aimerais proposer au client de
pouvoir payer dès la commande ferme passée et validée, l'intégralité en ligne. Pour ce qui est à la
carte, cela pourrait se faire au moment de la commande ; pour ce qui est personnalisable, après
validation de la proposition de Youngmi. On laisse les autres possibilités de paiement aussi. On
n'oublie pas le mail avec le reçu. »

## Clarifications

### Session 2026-11-21 (Olivier)

- Q: Payer avant ou après la validation de Youngmi pour la carte ? → A : en deux temps (« 1 ok 2
  ok »). Temps 1 : pour toutes les commandes, Youngmi valide, puis le mail de confirmation porte un
  bouton « Payer en ligne ». Temps 2 : quand l'agenda de la gestion garantit seul les créneaux, le
  client paie une commande à la carte dès qu'il la passe.
- Q: Et l'acompte ? → A : il passe par le même bouton (« Acompte parfait si ça règle le
  problème ») : le client paie en ligne l'acompte demandé, ou le tout.
- Q: Les autres moyens ? → A : ils restent : carte au retrait (Tap to Pay), espèces, virement. Le
  paiement en ligne est proposé, jamais imposé.
- Q: Le reçu ? → A : un mail avec le reçu part après le paiement en ligne (décision du 2026-11-09 :
  reçu pour chaque particulier ; facture seulement si le client la demande, spécification 003).

- Q: Qui crée le lien de paiement dans Qonto ? → A : la gestion, au toucher de Youngmi (carte de
  décision du 2026-11-21, 15:53) ; droit Qonto limité aux liens de paiement, exception à R-24 à
  inscrire au rulebook.

### Constat du 2026-11-21 (lecture seule)

- Le site dit « Au retrait, par carte bancaire, virement ou espèces » (Questions, Retrait,
  Conditions de commande § 6) ; le formulaire de demande n'a aucun choix de paiement.
- L'acompte de 30 % (personnalisation au-delà de la plaque) est demandé à l'acceptation de la
  proposition, mais aucun texte ne dit comment le payer ; la gestion note seulement qu'il est reçu
  et par quel moyen.
- Qonto, système de paiement de Madame Cho, propose des liens de paiement dans l'offre du compte
  (page des tarifs de Qonto, lue le 2026-11-21) : page de paiement hébergée par Qonto, encaissement
  par son partenaire Mollie, « à partir de 1,4 % + 0,25 € par transaction », premiers 1 000 €
  encaissés sans frais ; à comparer à 0,7 % pour une carte européenne en Tap to Pay. La fonction
  s'active depuis Qonto (création d'un compte Mollie). Remboursement, plafond et durée de vie d'un
  lien : non trouvés dans les pages publiques, à vérifier à l'activation.
- La liaison actuelle de la gestion à Qonto est en lecture seule ; créer un lien de paiement
  demande un droit de plus, limité aux liens de paiement (ni virement, ni carte).

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Payer en ligne une commande confirmée (Priority: P1)

Une cliente a accepté la proposition de Youngmi. Elle reçoit le mail de confirmation : il rappelle
la commande, le jour et l'heure du retrait, le prix, et porte un bouton « Payer en ligne ». Elle
touche le bouton, paie par carte sur une page sécurisée, et revient sur une page de Madame Cho qui
lui dit merci. Elle peut aussi ne rien faire et payer au retrait, comme aujourd'hui.

**Why this priority**: c'est la demande ; cela marche pour la carte comme pour le personnalisé,
sans risque de devoir rembourser une date que Youngmi ne peut pas tenir.

**Independent Test**: sur une copie de la base avec des données fictives et l'environnement
d'essai du prestataire, confirmer une commande, ouvrir le mail, payer, vérifier la commande.

**Acceptance Scenarios**:

1. **Given** une commande de particulier confirmée avec un prix final, **When** Youngmi prépare le
   mail de confirmation, **Then** l'aperçu montre le bouton « Payer en ligne » avec le montant, et
   la phrase « Vous pouvez aussi payer au retrait, par carte ou en espèces ».
2. **Given** le mail reçu, **When** la cliente touche le bouton, **Then** elle arrive sur une page
   de paiement au nom de Madame Cho qui montre la référence de la commande et le montant exact.
3. **Given** le paiement réussi, **When** elle revient sur le site, **Then** une page lui confirme
   le paiement et lui rappelle le jour, l'heure et le lieu du retrait.
4. **Given** le paiement réussi, **When** Youngmi ouvre la gestion, **Then** la commande est notée
   « Payé · En ligne », avec une pastille « Nouveau », sans qu'elle ait rien saisi.
5. **Given** un paiement abandonné ou refusé, **When** la cliente revient sur le site, **Then** on
   lui dit que rien n'a été débité, qu'elle peut réessayer avec le même bouton ou payer au retrait.
6. **Given** une commande déjà payée, **When** quelqu'un rouvre le lien, **Then** aucun second
   paiement n'est possible.

---

### User Story 2 - Payer l'acompte en ligne (Priority: P1)

Pour un gâteau personnalisé au-delà de la plaque, Youngmi demande un acompte de 30 %. Le mail de
confirmation propose deux boutons : « Payer l'acompte » et « Tout payer maintenant ». Le solde, s'il
en reste un, se paie au retrait ou en ligne avec un nouveau bouton envoyé par Youngmi.

**Why this priority**: aujourd'hui personne ne sait comment l'acompte se paie ; c'est le cas où le
paiement à distance est vraiment nécessaire.

**Independent Test**: confirmer une commande avec acompte, payer l'acompte, vérifier le montant
noté, puis payer le solde.

**Acceptance Scenarios**:

1. **Given** une commande avec un acompte demandé, **When** la cliente paie l'acompte, **Then** la
   commande passe à « Acompte reçu · En ligne » et le montant restant dû est affiché.
2. **Given** un acompte reçu, **When** Youngmi touche « Envoyer le lien pour le solde », **Then**
   le mail porte un bouton du montant restant, pas davantage.
3. **Given** un acompte payé en ligne, **When** le solde est payé au retrait en espèces ou par
   Tap to Pay, **Then** la commande garde les deux moyens (acompte en ligne, solde au retrait).

---

### User Story 3 - Recevoir le reçu par mail (Priority: P1)

Dès que le paiement en ligne est constaté, la cliente reçoit un mail « Paiement reçu » avec le
reçu : référence de la commande, date, montant payé, moyen, reste dû éventuel, retrait prévu. Si
elle a demandé une facture, celle-ci suit la règle de la spécification 003.

**Why this priority**: demandé par Olivier ; un paiement à distance sans confirmation écrite
inquiète le client et amène des appels.

**Independent Test**: payer une commande d'essai, lire le mail reçu, le retrouver dans les Échanges
de la commande.

**Acceptance Scenarios**:

1. **Given** un paiement en ligne réussi, **When** il est constaté, **Then** un mail de reçu part à
   l'adresse de la commande et se range dans les Échanges.
2. **Given** un paiement d'acompte, **When** le reçu part, **Then** il dit « Acompte » et donne le
   reste à payer.
3. **Given** un même paiement constaté deux fois, **When** le second constat arrive, **Then**
   aucun second reçu ne part.

---

### User Story 4 - Suivre et corriger les paiements en ligne (Priority: P2)

Youngmi voit dans chaque commande où en est le paiement en ligne (lien envoyé, payé, expiré), peut
renvoyer ou annuler un lien, et sait quoi faire quand une commande payée en ligne est annulée.

**Why this priority**: sans cela, chaque cas particulier finit chez Olivier.

**Independent Test**: jouer chaque cas (prix modifié, lien expiré, annulation) sur des données
fictives.

**Acceptance Scenarios**:

1. **Given** un lien envoyé et pas encore payé, **When** Youngmi modifie le prix de la commande,
   **Then** l'ancien lien est annulé et la gestion propose d'en envoyer un nouveau.
2. **Given** une commande payée en ligne, **When** elle est annulée dans le délai des conditions,
   **Then** la gestion affiche « À rembourser » avec le montant et les étapes à suivre dans Qonto ;
   Youngmi fait le remboursement elle-même, puis note « Remboursé ».
3. **Given** un paiement en ligne reçu, **When** le versement arrive sur le compte, **Then** le
   rapprochement automatique le retrouve, frais déduits, sans cas « à vérifier » inutile.
4. **Given** le paiement en ligne coupé dans les Paramètres, **When** Youngmi confirme une
   commande, **Then** le mail part sans bouton, comme aujourd'hui.

---

### User Story 5 - Payer une commande à la carte dès qu'on la passe (Priority: P3, temps 2)

Pour un panier fait seulement de gâteaux de la carte, sans personnalisation au-delà de la plaque,
avec un créneau de retrait que l'agenda donne comme libre, le site propose « Commander et payer
maintenant ». La commande est ferme dès le paiement ; Youngmi la reçoit déjà payée.

**Why this priority**: c'est le cap d'Olivier (site d'e-commerce), mais cela suppose que l'agenda
(jours fermés, délais, capacité) soit tenu à jour ; à ouvrir sur décision d'Olivier, après le
temps 1.

**Independent Test**: passer une commande d'essai à la carte sur un créneau libre, payer, vérifier
la commande confirmée et payée dans la gestion ; refaire sur un créneau fermé.

**Acceptance Scenarios**:

1. **Given** un panier à la carte et un créneau libre, **When** le client choisit « Commander et
   payer maintenant », **Then** il paie le total, la commande arrive « Confirmée · Payé » et il
   reçoit la confirmation et le reçu.
2. **Given** un panier avec une personnalisation au-delà de la plaque, un créneau non garanti ou
   une demande de professionnel, **When** le client arrive au formulaire, **Then** seul le parcours
   actuel (demande sans engagement) est proposé.
3. **Given** un client qui préfère payer au retrait, **When** il passe sa demande, **Then** le
   parcours actuel reste disponible, sans paiement.
4. **Given** une commande payée que Youngmi ne peut pas tenir, **When** elle la refuse, **Then** le
   remboursement intégral suit le scénario 2 de la User Story 4.

---

### Edge Cases

- Le client paie en ligne ET au retrait par erreur : la gestion signale le double paiement et le
  montant à rembourser.
- Le lien est ouvert après le retrait, ou après l'annulation de la commande : il ne permet plus de
  payer.
- Le paiement réussit mais le client ferme la page avant de revenir sur le site : la commande est
  tout de même notée payée et le reçu part.
- Le prestataire est en panne : le mail de confirmation part sans bouton, avec la phrase sur le
  paiement au retrait ; rien ne bloque la confirmation.
- La commande n'a pas d'adresse email (prise par téléphone) : Youngmi peut copier le lien pour
  l'envoyer par SMS ou WhatsApp depuis son téléphone.
- Montant très faible (frais fixes de 0,25 €) : seuil en dessous duquel le bouton n'est pas proposé,
  réglable dans les Paramètres.
- Commande de professionnel : pas de bouton ; les restaurants restent sur facture et virement.
- Client qui conteste un paiement auprès de sa banque : la gestion garde la trace (référence du
  paiement, date, reçu envoyé) pour répondre.

## Requirements *(mandatory)*

### Functional Requirements

**Proposer le paiement**

- **FR-001**: Le mail de confirmation d'une commande de particulier DOIT pouvoir porter un bouton
  « Payer en ligne » du montant dû (prix final, moins ce qui est déjà reçu).
- **FR-002**: Quand un acompte est demandé, le mail DOIT proposer de payer l'acompte seul ou le
  tout.
- **FR-003**: Le paiement en ligne DOIT rester facultatif : chaque mail qui le propose rappelle le
  paiement au retrait (carte, espèces) et le virement.
- **FR-004**: Youngmi DOIT pouvoir envoyer, renvoyer, copier et annuler un lien depuis la commande,
  et voir son état (envoyé, payé, expiré, annulé).
- **FR-005**: Un lien DOIT valoir pour une seule commande et un seul paiement, porter la référence
  de la commande, et cesser de fonctionner quand la commande est payée, annulée ou remise.
- **FR-006**: Si le prix ou l'acompte change, le lien en cours DOIT être annulé avant tout nouvel
  envoi.
- **FR-007**: Le lien de paiement DOIT être créé par la gestion, au toucher de Youngmi, jamais
  sans elle au temps 1 ; la gestion reçoit pour cela un droit Qonto limité aux liens de paiement
  (ni virement, ni carte, ni facture), par exception à R-24 inscrite au rulebook (décision
  d'Olivier du 2026-11-21).

**Constater le paiement**

- **FR-008**: La gestion DOIT noter seule le paiement en ligne (payé ou acompte reçu, moyen « En
  ligne », date, référence du paiement), sans saisie de Youngmi, y compris si le client ne revient
  pas sur le site.
- **FR-009**: Un même paiement ne DOIT jamais être compté deux fois, ni déclencher deux reçus.
- **FR-010**: La gestion ne DOIT noter « payé » que sur la foi du prestataire, jamais sur la seule
  ouverture de la page de retour.
- **FR-011**: Le rapprochement automatique DOIT reconnaître les versements des paiements en ligne,
  frais déduits, et les rattacher aux commandes.
- **FR-012**: Les frais du prestataire DOIVENT être visibles par commande et par mois (Statistiques
  ou Factures), pour le suivi et pour l'expert-comptable.

**Le reçu**

- **FR-013**: Après chaque paiement en ligne constaté, un mail de reçu DOIT partir au client
  [NEEDS CLARIFICATION: tout seul, sans toucher de Youngmi (nouvelle exception à R-01, comme
  l'accusé de réception), ou préparé par la gestion et envoyé au toucher de Youngmi ?]
- **FR-014**: Le reçu DOIT donner : Madame Cho et ses mentions, référence de la commande, date et
  montant payé, moyen, reste dû, jour, heure et lieu du retrait, rappel des conditions
  d'annulation. Il n'est pas une facture ; la facture suit la spécification 003.
- **FR-015**: Le reçu DOIT être rangé dans les Échanges de la commande et pouvoir être renvoyé.

**Annuler et rembourser**

- **FR-016**: Une commande payée en ligne puis annulée DOIT apparaître « À rembourser » avec le
  montant, selon les conditions de commande (sans frais jusqu'à 24 heures avant le retrait ; passé
  ce délai, l'acompte reste acquis).
- **FR-017**: Le remboursement DOIT être fait par Youngmi chez le prestataire ; la gestion n'en
  déclenche aucun et note seulement « Remboursé » quand elle le constate ou que Youngmi le note.
- **FR-018**: [NEEDS CLARIFICATION: en cas d'annulation après un paiement intégral en ligne et
  passé le délai de 24 heures, que garde Madame Cho : rien n'est remboursé, ou seulement l'acompte
  de 30 % est gardé quand il y en avait un ?]

**Le site et les textes**

- **FR-019**: Les pages Questions, Retrait et Conditions de commande DOIVENT dire que l'on peut
  payer en ligne après la confirmation (acompte ou totalité), et garder les autres moyens.
- **FR-020**: La page Confidentialité et le registre des traitements DOIVENT décrire le paiement en
  ligne : prestataire, données transmises (référence, montant), aucune donnée de carte chez Madame
  Cho.
- **FR-021**: Les pages de retour (paiement réussi, paiement non abouti) DOIVENT exister en
  français, sans donnée personnelle dans l'adresse, et ne jamais être indexées.
- **FR-022**: Le manuel de la gestion et « Quoi de neuf » DOIVENT expliquer la fonction en français
  et en coréen.

**Réglages et sécurité**

- **FR-023**: Les Paramètres DOIVENT offrir un interrupteur du paiement en ligne (éteint par
  défaut), le montant minimal, et l'état de la liaison avec le prestataire.
- **FR-024**: Aucun secret du prestataire ne DOIT se trouver dans le dépôt (public), dans un export
  ou dans les données lues par l'assistant.
- **FR-025**: L'assistant de la gestion ne DOIT ni créer, ni annuler, ni envoyer un lien de
  paiement ; il peut seulement dire où en est un paiement.
- **FR-026**: La gestion ne DOIT jamais voir ni garder un numéro de carte.

**Temps 2 (sur décision d'Olivier)**

- **FR-027**: Le site DOIT pouvoir proposer « Commander et payer maintenant » pour un panier à la
  carte sans personnalisation au-delà de la plaque, sur un créneau que l'agenda garantit.
- **FR-028**: Avant de payer, le client DOIT voir le récapitulatif (gâteaux, tailles, prix TTC
  total, jour et heure du retrait, conditions d'annulation) et accepter les conditions de commande
  par un bouton qui dit clairement qu'il s'engage à payer.
- **FR-029**: Un créneau DOIT être retenu pendant le paiement et libéré si le paiement n'aboutit
  pas, pour que deux clients ne paient pas la dernière place.
- **FR-030**: Le temps 2 DOIT avoir son propre interrupteur, éteint par défaut.

### Key Entities *(include if feature involves data)*

- **Lien de paiement** : rattaché à une commande ; objet (acompte, solde, totalité), montant,
  état (envoyé, payé, expiré, annulé), dates, référence chez le prestataire.
- **Paiement en ligne** : rattaché à un lien ; montant reçu, frais, date, référence, état
  (réussi, remboursé).
- **Reçu** : rattaché à un paiement ; date d'envoi, adresse, contenu figé.
- **Commande** (existante) : nouveau moyen de paiement « En ligne » ; peut porter deux moyens
  (acompte et solde).

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Un client paie une commande confirmée en moins de 2 minutes depuis le mail, sur
  téléphone, sans créer de compte.
- **SC-002**: 100 % des paiements en ligne sont notés dans la commande sans saisie de Youngmi, en
  moins de 5 minutes.
- **SC-003**: 100 % des paiements en ligne sont suivis d'un reçu, et aucun reçu n'est envoyé deux
  fois.
- **SC-004**: Tous les acomptes demandés après la mise en ligne ont un moyen de paiement écrit
  dans le mail de confirmation.
- **SC-005**: Aucun paiement en ligne ne reste « à vérifier » dans le rapprochement plus de 3
  jours ouvrés.
- **SC-006**: Envoyer un lien de paiement demande à Youngmi au plus un toucher de plus
  qu'aujourd'hui pour confirmer une commande.
- **SC-007**: Les autres moyens de paiement restent utilisables exactement comme avant.

## Assumptions

- Le prestataire retenu est le lien de paiement de Qonto (Mollie), pour tout garder dans le système
  de paiement de Madame Cho et dans le rapprochement existant ; un autre prestataire demanderait
  une décision d'Olivier et un contrat de plus.
- L'activation des liens de paiement dans Qonto (compte Mollie, pièces demandées) est faite par
  Youngmi, propriétaire du compte ; aucune IA n'agit dans Qonto (R-24).
- Les frais (« à partir de 1,4 % + 0,25 € ») sont à la charge de Madame Cho ; le prix payé par le
  client est le même quel que soit le moyen.
- Seuls les particuliers sont concernés ; les professionnels restent sur facture et virement.
- Produits périssables faits à la demande : pas de droit de rétractation ; les conditions
  d'annulation du site s'appliquent. Les textes de la vente à distance (information avant
  paiement, bouton d'engagement, médiateur) sont relus avant le temps 2.
- La question de la caisse posée à l'expert-comptable (reçu systématique, spécification 003)
  reste ouverte ; le reçu du paiement en ligne est conçu pour ne pas dépendre de sa réponse.
- Rien n'est mis en ligne sans le feu vert d'Olivier sur la version exacte ; les deux temps ont
  chacun leur interrupteur.
