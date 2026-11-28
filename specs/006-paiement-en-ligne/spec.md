# Feature Specification: Paiement en ligne des commandes

**Feature Branch**: `006-paiement-en-ligne` (branche de travail `claude/project-thread-ng5b6m` depuis le
2026-11-28 ; écrite d'abord sur `claude/project-thread-php79v`)

**Created**: 2026-11-21 · **Updated**: 2026-11-28 (décisions du 2026-11-27, plages de retrait en ligne)

**Status**: Spécification à jour, aucune question ouverte ; aucun code écrit

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
  ok »). Temps 1 : Youngmi valide, puis le mail de confirmation porte un bouton « Payer en ligne ».
  Temps 2 : quand l'agenda de la gestion garantit seul les places, le client paie une commande à la
  carte dès qu'il la passe.
- Q: Les autres moyens ? → A : ils restent : carte au retrait (Tap to Pay), espèces, virement. Le
  paiement en ligne est proposé, jamais imposé.
- Q: Qui crée le lien de paiement dans Qonto ? → A : la gestion (carte de décision du 2026-11-21,
  15:53) ; droit Qonto limité aux liens de paiement, exception à R-24 à inscrire au rulebook.
- ~~Q: Et l'acompte ? → A : il passe par le même bouton.~~ Remplacé le 2026-11-27 : plus d'acompte.

### Session 2026-11-27 (Olivier, pour Youngmi et lui, 23:24-23:26)

- Q: Une commande à la carte payée en ligne sur une plage libre attend-elle Youngmi ? → A : non,
  elle est confirmée sans passer par elle.
- Q: L'acompte ? → A : plus d'acompte du tout (« trop compliqué pour le client »). Une commande sur
  demande suit le parcours actuel (demande, proposition et prix de Youngmi, accord, confirmation),
  puis se paie en entier une fois tout validé.
- Q: Que garde Madame Cho si une commande payée en ligne est annulée ? → A : rien, même à moins
  de 24 heures (« tant pis ») : le remboursement est toujours intégral.
- Q: Les frais du paiement en ligne ? → A : acceptés, à la charge de Madame Cho.
- Q: Le reçu part-il tout seul ? → A : oui, automatiquement après le paiement (exception à R-01,
  comme l'accusé de réception), à inscrire au rulebook avec l'exception R-24 du 2026-11-21.

### Session 2026-11-28 (lecture des décisions, sans nouvelle question)

- Les plages de retrait à capacité sont en ligne depuis le 2026-11-28 (spécification 007) :
  l'agenda garantit désormais seul les places. Le temps 2 n'attend donc plus que ce code ; les deux
  temps sont livrés dans la même version, chacun avec son interrupteur, éteints par défaut.
- Au temps 2, le lien est créé par le site au moment où le client choisit « Commander et payer »,
  sans toucher de Youngmi, et la confirmation part avec le reçu, toute seule : c'est la conséquence
  directe de « confirmée sans passer par Youngmi » ; les exceptions à R-24 et R-01 à inscrire au
  rulebook le disent.
- Une place est retenue pendant le paiement (30 minutes par défaut) puis libérée si le paiement
  n'aboutit pas ; un paiement arrivé après ce délai sur une plage devenue complète est accepté et la
  plage passe « Dépassée » : Youngmi voit le cas et appelle le client si besoin.

### Constat (lecture seule, mis à jour le 2026-11-28)

- Depuis 7ed34f8 (2026-11-28), le site dit « Au retrait, par carte bancaire, virement ou espèces :
  aucun acompte n'est demandé » ; le formulaire de demande n'a aucun choix de paiement ; la gestion
  refuse tout état d'acompte (audit n° 10, C10-07).
- Qonto propose des liens de paiement dans l'offre du compte : page hébergée, encaissement par
  Mollie, cartes (CB, Visa, Mastercard, Amex), Apple Pay, frais « à partir de 1,4 % + 0,25 € ». La
  fonction s'active dans Qonto par une personne Owner ou Admin (chez Madame Cho : Youngmi). Les
  remboursements se font dans le tableau de bord Mollie, pas dans Qonto. Mollie garde en général une
  réserve glissante d'environ 20 % des paiements carte pendant 60 à 180 jours.
- La liaison actuelle de la gestion à Qonto (appli OAuth « Gestion Madame Cho ») est en lecture
  seule ; créer un lien demande les portées payment_link.read et payment_link.write, puis une
  nouvelle autorisation de Youngmi depuis la gestion.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Payer en ligne une commande confirmée (Priority: P1, temps 1)

Une cliente a accepté la proposition de Youngmi. Youngmi confirme la commande et lui envoie la
confirmation : le mail rappelle la commande, le jour et la plage de retrait, le prix, et porte un
bouton « Payer en ligne ». La cliente touche le bouton, paie par carte sur une page sécurisée, et
revient sur une page de Madame Cho qui lui dit merci. Elle peut aussi ne rien faire et payer au
retrait, comme aujourd'hui.

**Why this priority**: c'est la demande ; cela marche pour la carte comme pour le sur demande, sans
risque de devoir rembourser une date que Youngmi ne peut pas tenir.

**Independent Test**: sur une copie de la base avec des données fictives et le bac à sable de Qonto,
confirmer une commande, envoyer la confirmation, payer, vérifier la commande et le reçu.

**Acceptance Scenarios**:

1. **Given** une commande de particulier confirmée avec un prix final, **When** Youngmi ouvre
   l'envoi de la confirmation, **Then** l'aperçu montre le bouton « Payer en ligne » avec le montant,
   et la phrase « Vous pouvez aussi payer au retrait, par carte, en espèces ou par virement ».
2. **Given** le mail reçu, **When** la cliente touche le bouton, **Then** elle arrive sur une page
   de paiement au nom de Madame Cho qui montre la référence de la commande et le montant exact.
3. **Given** le paiement réussi, **When** elle revient sur le site, **Then** une page lui confirme
   le paiement et lui rappelle le jour, la plage et le lieu du retrait.
4. **Given** le paiement réussi, **When** Youngmi ouvre la gestion, **Then** la commande est notée
   « Payé · En ligne », avec une pastille « Nouveau », sans qu'elle ait rien saisi.
5. **Given** un paiement abandonné ou refusé, **When** la cliente revient sur le site, **Then** on
   lui dit que rien n'a été débité, qu'elle peut réessayer avec le même bouton ou payer au retrait.
6. **Given** une commande déjà payée, **When** quelqu'un rouvre le lien, **Then** aucun second
   paiement n'est possible.

---

### User Story 2 - Commander et payer tout de suite une commande à la carte (Priority: P1, temps 2)

Un client choisit des gâteaux de la carte, sans personnalisation au-delà de la plaque, un jour
ouvert et une plage libre. Le formulaire lui propose deux boutons : « Commander et payer » et
« Envoyer une demande sans payer ». S'il paie, sa commande est confirmée aussitôt, sans attendre
Youngmi : il reçoit un mail de confirmation qui sert aussi de reçu, et la place est prise dans la
plage.

**Why this priority**: c'est le cap d'Olivier (site d'e-commerce) ; décidé le 2026-11-27, possible
depuis que les plages à capacité sont en ligne.

**Independent Test**: passer une commande d'essai à la carte sur une plage libre, payer dans le bac
à sable, vérifier la commande « Confirmée · Payé » dans la gestion et le mail reçu ; refaire sur
une plage complète, un jour fermé, un panier personnalisé.

**Acceptance Scenarios**:

1. **Given** un panier à la carte, un jour ouvert, un délai de préparation respecté et une plage
   libre, **When** le client arrive au bas du formulaire, **Then** il voit le récapitulatif
   (gâteaux, tailles, prix TTC total, jour et plage, conditions d'annulation) et un bouton
   « Commander et payer » qui dit clairement qu'il s'engage à payer.
2. **Given** le paiement réussi, **When** le paiement est constaté, **Then** la commande arrive
   dans la gestion « Confirmée · Payé · En ligne », la place est comptée dans la plage, et le client
   reçoit tout seul le mail de confirmation et de reçu.
3. **Given** un panier avec une personnalisation au-delà de la plaque, une plage complète ou
   fermée, un jour fermé, un délai trop court ou une demande de professionnel, **When** le client
   arrive au formulaire, **Then** seul le parcours de demande sans engagement est proposé, avec une
   phrase qui dit pourquoi.
4. **Given** un client qui préfère payer au retrait, **When** il envoie sa demande, **Then** le
   parcours actuel reste disponible, sans paiement.
5. **Given** deux clients qui veulent la dernière place d'une plage, **When** le premier part
   payer, **Then** la place lui est retenue pendant le paiement ; le second ne la voit plus libre.
6. **Given** un paiement non abouti, **When** la retenue expire, **Then** la place est de nouveau
   libre et la commande non payée reste une demande que Youngmi voit, marquée « paiement non
   abouti ».

---

### User Story 3 - Recevoir le reçu par mail (Priority: P1)

Dès que le paiement en ligne est constaté, le client reçoit tout seul un mail « Paiement reçu » :
référence de la commande, date, montant payé, moyen, jour, plage et lieu du retrait, conditions
d'annulation. Au temps 2, ce mail est aussi la confirmation de la commande. S'il a demandé une
facture, celle-ci suit la spécification 003.

**Why this priority**: demandé par Olivier ; un paiement à distance sans confirmation écrite
inquiète le client et amène des appels.

**Independent Test**: payer une commande d'essai, lire le mail reçu, le retrouver dans les Échanges
de la commande.

**Acceptance Scenarios**:

1. **Given** un paiement en ligne réussi, **When** il est constaté, **Then** un mail de reçu part
   tout seul à l'adresse de la commande, avec copie à contact@madamecho.com, et se range dans les
   Échanges.
2. **Given** un même paiement constaté deux fois, **When** le second constat arrive, **Then**
   aucun second reçu ne part.
3. **Given** le reçu non parti (panne d'envoi), **When** Youngmi ouvre la commande, **Then** la
   gestion le dit et propose « Renvoyer le reçu ».

---

### User Story 4 - Suivre, annuler et rembourser (Priority: P2)

Youngmi voit dans chaque commande où en est le paiement en ligne (lien envoyé, payé, expiré,
annulé), peut renvoyer, copier ou annuler un lien, et sait quoi faire quand une commande payée en
ligne est annulée : la rembourser en entier.

**Why this priority**: sans cela, chaque cas particulier finit chez Olivier.

**Independent Test**: jouer chaque cas (prix modifié, lien expiré, annulation, double paiement) sur
des données fictives.

**Acceptance Scenarios**:

1. **Given** un lien envoyé et pas encore payé, **When** Youngmi modifie le prix de la commande,
   **Then** l'ancien lien est annulé et la gestion propose d'en envoyer un nouveau.
2. **Given** une commande payée en ligne, **When** elle est annulée, à n'importe quel moment,
   **Then** la gestion affiche « À rembourser » avec le montant total et les étapes à suivre dans
   Mollie ; Youngmi rembourse elle-même, puis note « Remboursé » (ou la gestion le constate).
3. **Given** un paiement en ligne reçu, **When** le versement arrive sur le compte Qonto, **Then**
   le rapprochement automatique le retrouve, frais et réserve de Mollie compris, sans cas « à
   vérifier » inutile.
4. **Given** le paiement en ligne coupé dans les Paramètres, **When** Youngmi confirme une
   commande, **Then** le mail part sans bouton, comme aujourd'hui, et le site ne propose plus
   « Commander et payer ».

---

### Edge Cases

- Le client paie en ligne ET au retrait par erreur : la gestion signale le double paiement et le
  montant à rembourser.
- Le lien est ouvert après le retrait, ou après l'annulation de la commande : il ne permet plus de
  payer.
- Le paiement réussit mais le client ferme la page avant de revenir sur le site : la commande est
  tout de même notée payée et le reçu part.
- Paiement arrivé après la fin de la retenue, sur une plage devenue complète : accepté, plage
  « Dépassée », la commande est signalée à Youngmi.
- Qonto ou Mollie en panne : le mail de confirmation part sans bouton, avec la phrase sur le
  paiement au retrait ; au temps 2, le site propose seulement la demande sans engagement ; rien ne
  bloque une commande.
- La commande n'a pas d'adresse email (prise par téléphone) : Youngmi peut copier le lien pour
  l'envoyer par SMS ou WhatsApp depuis son téléphone ; pas de reçu automatique, la gestion le dit.
- Montant très faible (frais fixes de 0,25 €) : seuil en dessous duquel le paiement en ligne n'est
  pas proposé, réglable dans les Paramètres.
- Commande de professionnel : pas de bouton ; les restaurants restent sur facture et virement.
- Client qui conteste un paiement auprès de sa banque : la gestion garde la trace (référence du
  paiement, date, reçu envoyé) pour répondre.
- Une ancienne commande porte encore un acompte : elle garde son état ; aucun nouveau lien n'est
  proposé pour un acompte.

## Requirements *(mandatory)*

### Functional Requirements

**Proposer le paiement (temps 1)**

- **FR-001**: Le mail de confirmation d'une commande de particulier DOIT pouvoir porter un bouton
  « Payer en ligne » du montant dû (prix final, moins ce qui est déjà reçu).
- **FR-002**: Aucun acompte n'est demandé ni proposé en ligne ; seul le montant entier dû peut être
  payé (décision du 2026-11-27).
- **FR-003**: Le paiement en ligne DOIT rester facultatif : chaque mail ou page qui le propose
  rappelle le paiement au retrait (carte, espèces) et le virement.
- **FR-004**: Youngmi DOIT pouvoir envoyer, renvoyer, copier et annuler un lien depuis la commande,
  et voir son état (envoyé, payé, expiré, annulé).
- **FR-005**: Un lien DOIT valoir pour une seule commande et un seul paiement, porter la référence
  de la commande, et cesser de fonctionner quand la commande est payée, annulée ou remise.
- **FR-006**: Si le prix change, le lien en cours DOIT être annulé avant tout nouvel envoi.
- **FR-007**: Au temps 1, le lien DOIT être créé par la gestion au toucher de Youngmi ; au temps 2,
  par le site au choix du client. Dans les deux cas, la gestion n'a chez Qonto qu'un droit limité
  aux liens de paiement (ni virement, ni carte, ni facture), par exception à R-24 inscrite au
  rulebook (décisions d'Olivier du 2026-11-21 et du 2026-11-27).

**Commander et payer (temps 2)**

- **FR-027**: Le site DOIT proposer « Commander et payer » seulement pour un panier fait de
  gâteaux de la carte sans personnalisation au-delà de la plaque, un client particulier, un jour
  ouvert, un délai de préparation respecté et une plage libre selon l'agenda (spécification 007).
- **FR-028**: Avant de payer, le client DOIT voir le récapitulatif (gâteaux, tailles, prix TTC
  total, jour et plage, conditions d'annulation) et accepter les conditions de commande par un
  bouton qui dit clairement qu'il s'engage à payer.
- **FR-029**: Une place DOIT être retenue dans la plage pendant le paiement (30 minutes par défaut,
  réglable) et libérée si le paiement n'aboutit pas ; la retenue compte comme une place prise pour
  le site, pas pour la gestion.
- **FR-030**: Le temps 2 DOIT avoir son propre interrupteur, éteint par défaut.
- **FR-031**: Une commande payée au temps 2 DOIT arriver dans la gestion « Confirmée · Payé · En
  ligne », avec la plage et une pastille « Nouveau », sans intervention de Youngmi.
- **FR-032**: Une commande dont le paiement n'a pas abouti DOIT rester une demande ordinaire,
  marquée « paiement non abouti », que Youngmi traite comme les autres.

**Constater le paiement**

- **FR-008**: La gestion DOIT noter seule le paiement en ligne (payé, moyen « En ligne », date,
  référence du paiement), sans saisie de Youngmi, y compris si le client ne revient pas sur le
  site.
- **FR-009**: Un même paiement ne DOIT jamais être compté deux fois, ni déclencher deux reçus.
- **FR-010**: La gestion ne DOIT noter « payé » que sur la foi de Qonto, jamais sur la seule
  ouverture de la page de retour.
- **FR-011**: Le rapprochement automatique DOIT reconnaître les versements des paiements en ligne,
  frais et réserve déduits, et les rattacher aux commandes.
- **FR-012**: Les frais DOIVENT être visibles par commande et par mois (Statistiques ou Factures),
  pour le suivi et pour l'expert-comptable.

**Le reçu**

- **FR-013**: Après chaque paiement en ligne constaté, un mail de reçu DOIT partir tout seul au
  client, avec copie à contact@madamecho.com (exception à R-01 décidée le 2026-11-27) ; au temps 2,
  il vaut confirmation de la commande.
- **FR-014**: Le reçu DOIT donner : Madame Cho et ses mentions, référence de la commande, date et
  montant payé, moyen, jour, plage et lieu du retrait, rappel des conditions d'annulation. Il n'est
  pas une facture ; la facture suit la spécification 003.
- **FR-015**: Le reçu DOIT être rangé dans les Échanges de la commande et pouvoir être renvoyé.

**Annuler et rembourser**

- **FR-016**: Une commande payée en ligne puis annulée DOIT apparaître « À rembourser » avec le
  montant total payé, quel que soit le moment de l'annulation.
- **FR-017**: Le remboursement DOIT être fait par Youngmi dans le tableau de bord Mollie ; la
  gestion n'en déclenche aucun et note seulement « Remboursé » quand elle le constate ou que
  Youngmi le note.
- **FR-018**: Madame Cho ne garde rien d'une commande payée en ligne et annulée, même à moins de 24
  heures du retrait (décision du 2026-11-27) ; les conditions de commande le disent.

**Le site et les textes**

- **FR-019**: Les pages Questions, Retrait et Conditions de commande DOIVENT dire que l'on peut
  payer en ligne (tout de suite pour la carte, après confirmation pour une demande), garder les
  autres moyens et dire le remboursement intégral en cas d'annulation d'une commande payée.
- **FR-020**: La page Confidentialité et le registre des traitements DOIVENT décrire le paiement en
  ligne : Qonto et Mollie, données transmises (référence, montant, nom et email si demandés),
  aucune donnée de carte chez Madame Cho.
- **FR-021**: Les pages de retour (paiement réussi, paiement non abouti) DOIVENT exister en
  français, sans donnée personnelle dans l'adresse, et ne jamais être indexées.
- **FR-022**: Le manuel de la gestion et « Quoi de neuf » DOIVENT expliquer la fonction en français
  et en coréen.

**Réglages et sécurité**

- **FR-023**: Les Paramètres DOIVENT offrir un interrupteur pour chaque temps (éteints par défaut),
  le montant minimal, la durée de la retenue, et l'état de la liaison avec Qonto.
- **FR-024**: Aucun secret de Qonto ne DOIT se trouver dans le dépôt (public), dans un export ou
  dans les données lues par l'assistant.
- **FR-025**: L'assistant de la gestion ne DOIT ni créer, ni annuler, ni envoyer un lien de
  paiement ; il peut seulement dire où en est un paiement.
- **FR-026**: La gestion ne DOIT jamais voir ni garder un numéro de carte.
- **FR-033**: Les notifications de Qonto reçues par la gestion DOIVENT être vérifiées
  (authenticité, commande existante, montant) avant de changer quoi que ce soit.

### Key Entities *(include if feature involves data)*

- **Lien de paiement** : rattaché à une commande ; montant, état (envoyé, payé, expiré, annulé),
  origine (gestion au temps 1, site au temps 2), dates, référence chez Qonto.
- **Paiement en ligne** : rattaché à un lien ; montant reçu, frais, date, référence, état
  (réussi, remboursé).
- **Retenue de place** : plage, jour, commande, échéance ; disparaît au paiement ou à l'échéance.
- **Reçu** : rattaché à un paiement ; date d'envoi, adresse, contenu figé.
- **Commande** (existante) : nouveau moyen de paiement « En ligne » ; nouvel état « paiement non
  abouti » pour une commande du temps 2 qui n'a pas été payée.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Un client paie une commande en moins de 2 minutes, sur téléphone, sans créer de
  compte.
- **SC-002**: 100 % des paiements en ligne sont notés dans la commande sans saisie de Youngmi, en
  moins de 5 minutes.
- **SC-003**: 100 % des paiements en ligne sont suivis d'un reçu, et aucun reçu n'est envoyé deux
  fois.
- **SC-004**: Aucune plage ne dépasse son maximum par le seul effet de paiements simultanés au
  temps 2.
- **SC-005**: Aucun paiement en ligne ne reste « à vérifier » dans le rapprochement plus de 3
  jours ouvrés.
- **SC-006**: Envoyer un lien de paiement demande à Youngmi au plus un toucher de plus
  qu'aujourd'hui pour confirmer une commande ; au temps 2, aucun.
- **SC-007**: Les autres moyens de paiement restent utilisables exactement comme avant.

## Assumptions

- Le prestataire est le lien de paiement de Qonto (Mollie), pour tout garder dans le système de
  paiement de Madame Cho et dans le rapprochement existant.
- L'activation des liens de paiement dans Qonto (contrat Mollie, pièces demandées) est faite par
  Youngmi, seule membre Owner ; aucune IA n'agit dans Qonto ni dans Mollie (R-24). Elle ne touche
  jamais le bouton qui déconnecte Mollie : il est définitif.
- Les frais (« à partir de 1,4 % + 0,25 € ») sont à la charge de Madame Cho ; le prix payé par le
  client est le même quel que soit le moyen.
- Seuls les particuliers sont concernés ; les professionnels restent sur facture et virement.
- Produits périssables faits à la demande : pas de droit de rétractation ; les conditions
  d'annulation du site s'appliquent, avec remboursement intégral d'une commande payée en ligne.
  Les textes de la vente à distance (information avant paiement, bouton d'engagement, médiateur)
  sont relus avant la mise en ligne du temps 2.
- La question de la caisse posée à l'expert-comptable (reçu systématique, spécification 003)
  reste ouverte ; le reçu du paiement en ligne est conçu pour ne pas dépendre de sa réponse.
- Les exceptions à R-24 (liens de paiement) et à R-01 (reçu et confirmation automatiques du
  paiement en ligne) sont inscrites au rulebook avant la mise en ligne.
- Rien n'est mis en ligne sans le feu vert d'Olivier sur la version exacte ; chaque temps a son
  interrupteur.
