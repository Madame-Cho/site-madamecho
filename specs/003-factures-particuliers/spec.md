# Feature Specification: Factures des particuliers faites par la gestion

**Feature Branch**: `003-factures-particuliers` (branche de travail `claude/project-thread-0bqc8g`)

**Created**: 2026-11-08

**Status**: Lots 1 à 4 écrits sur la branche, pas publiés. Réponses de l'expert-comptable reçues le
2026-11-09 (questions 2 à 5) ; la question 1 (logiciel de caisse, FR-013) reste ouverte

**Input**: Demande d'Olivier du 2026-11-08 : « Youngmi crée ses factures manuellement dans Qonto
(B2C et B2B) ; elle aimerait que les factures soient générées automatiquement en B2C par l'outil
de gestion, puis pouvoir les envoyer par mail (ou autre canal si elle n'a pas le mail du client).
Il faudrait un système fluide et simple de bout en bout, à l'état de l'art et qui respecte la
réglementation française. » Puis : « il faut gérer les problèmes » ; « quand c'est payé au retrait
par carte elle a Tap to Pay » ; « il faudrait qu'elle contrôle l'envoi des factures dans un premier
temps » ; « on sépare la compta pour l'instant ». Plan validé par Olivier le 2026-11-08 (Claude
Doc « Plan : factures des particuliers générées par la gestion »).

## Clarifications

### Session 2026-11-08 (Olivier)

- Q: Facture ou reçu ? → A : un seul document, la facture « acquittée », faite pour chaque commande
  de particulier payée ; pas de reçu en plus, jamais de ticket papier imposé (plan validé).
  *Remplacé le 2026-11-09 : facture seulement si le client la demande, reçu à venir (voir plus bas).*
- Q: Qui envoie ? → A : la gestion, au toucher de Youngmi sur « Envoyer » après l'aperçu ; au
  début, rien ne part sans elle (phase de contrôle). Un envoi sans toucher ne viendra qu'après,
  sur décision d'Olivier et exception écrite à R-01, comme pour le rappel de retrait.
- Q: Client sans mail ? → A : Youngmi choisit à chaque fois : lien privé à envoyer par SMS ou
  WhatsApp depuis son téléphone, QR code montré sur son écran, ou PDF à imprimer.
- Q: Et les factures des particuliers dans Qonto ? → A : Youngmi arrête d'en faire dès que la
  gestion les fait ; les restaurants restent dans Qonto.
- Q: Logiciel de caisse ? → A : non. La carte passe par Tap to Pay (le relevé Qonto fait foi), les
  espèces vont dans un livre des recettes tenu à part. La gestion fait les commandes et les
  factures sans devenir la caisse (choix d'Olivier ; portée à confirmer par l'expert-comptable).
  *Précisé le 2026-11-09 : rien n'est tenu à part ; le livre des recettes des espèces est un
  extrait tableur de la base (FR-021).*
- Q: Comptabilité (export, copie au Drive, e-reporting de 2027) ? → A : hors de cette
  spécification, futur chantier ; les factures en gardent toutes les données.
- Q: Liaison automatique avec Qonto ? → A : toujours impossible (portail développeurs fermé aux
  nouvelles inscriptions, essai d'Olivier du 2026-11-08) ; le rapprochement à la main de FR-030
  (spécification 002) reste la voie. *2026-11-09 : Qonto annonce sur son site la migration du
  compte développeurs, accessible sous quelques heures (information d'Olivier) ; à réessayer.*

### Session 2026-11-09 (Florent, l'expert-comptable ; puis Olivier)

- Q2: Série à part pour les particuliers ? → A (Florent) : oui, à condition que la numérotation
  soit continue et que les dates suivent l'ordre des numéros. Retenu : `P-AAAA-NNNN` et
  `AV-AAAA-NNNN` (FR-003).
- Q3: Acompte ? → A (Florent) : une facture finale qui montre l'acompte et le solde (FR-012).
- Q4: Livre des recettes des espèces ? → A (Florent) : lui dire quelles factures sont réglées en
  espèces. Olivier : tout reste dans la base, des extraits tableur quand il faut (FR-021).
- Q5: Facture sans nom (« Client particulier ») ? → A (Florent) : non ; prénom, nom et adresse du
  client sont obligatoires (FR-002, FR-020).
- Q1: Logiciel de caisse ? → pas de réponse : Florent parle de l'e-reporting des ventes aux
  particuliers (totaux par jour, à partir du 2027-09-01 pour une PME, par une plateforme agréée).
  Question reposée (FR-013).
- Q: Faut-il une facture pour chaque particulier ? → A (Olivier, choix C) : non. **Facture
  seulement si le client la demande** : sur le site, une case facultative « Je souhaite une
  facture » ouvre un petit formulaire (adresse postale) que Youngmi retrouve dans la gestion ; par
  téléphone ou un autre canal, Youngmi demande au client s'il en veut une et note elle-même les
  informations. À revérifier avec Florent (FR-001, FR-019).
- Q: Reçu ? → A (Olivier) : quand Qonto sera branché, un reçu sera systématique pour chaque
  particulier, envoyé par Youngmi par mail ou un autre moyen ; la gestion l'aide à le produire.
  Pas encore écrit : il attend la liaison Qonto et la réponse à FR-013 (FR-022).

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Émettre la facture d'une commande payée (Priority: P1)

Youngmi remet un gâteau à une cliente et note le paiement (carte, espèces ou virement). La gestion
lui propose aussitôt la facture, déjà remplie à partir de la commande. Youngmi regarde l'aperçu et
touche « Émettre » : la facture reçoit son numéro et ne change plus.

**Why this priority**: c'est le cœur de la demande ; sans elle, Youngmi continue à tout retaper
dans Qonto.

**Independent Test**: sur une copie de la base avec des données fictives, remettre et payer une
commande de particulier, émettre la facture, et vérifier le PDF (mentions, totaux, numéro).

**Acceptance Scenarios**:

1. **Given** une commande de particulier remise et notée « Payé », dont le client a demandé une
   facture, **When** Youngmi ouvre la commande, **Then** un bloc « Facture » propose « Voir l'aperçu » avec les gâteaux, quantités,
   prix TTC et HT, TVA par taux, acompte déjà versé et total, sans numéro encore.
2. **Given** l'aperçu, **When** Youngmi touche « Émettre », **Then** la facture reçoit le numéro
   suivant de la série des particuliers, sa date d'émission, et son PDF est figé.
3. **Given** une facture émise, **When** quelqu'un modifie la commande, **Then** la facture ne
   change pas et la commande signale « La facture ne correspond plus : faire un avoir ».
4. **Given** une commande de professionnel, **When** elle est remise, **Then** aucune facture
   n'est proposée par cette fonction (les restaurants restent dans Qonto).
5. **Given** une commande pas encore payée, **When** Youngmi l'ouvre, **Then** « Émettre » n'est
   pas proposé ; l'aperçu reste possible et porte « non payée ».

---

### User Story 2 - Remettre la facture au client (Priority: P1)

Une fois la facture émise, Youngmi la donne au client par le moyen qui lui convient : mail s'il y
en a un, sinon lien par SMS ou WhatsApp, QR code sur son écran, ou PDF imprimé.

**Why this priority**: la facture ne sert que si le client la reçoit ; c'est la deuxième moitié du
parcours demandé.

**Independent Test**: émettre une facture fictive, l'envoyer à une adresse d'essai, ouvrir le
lien privé sur un autre téléphone, scanner le QR code, imprimer le PDF.

**Acceptance Scenarios**:

1. **Given** une facture émise et un client avec mail, **When** Youngmi touche « Envoyer »,
   **Then** elle voit d'abord le mail tel que le client le recevra (même style que les autres
   mails, étiquette FACTURE, PDF joint), puis le mail part après sa confirmation.
2. **Given** un client sans mail, **When** Youngmi ouvre « Remettre la facture », **Then** elle a
   trois choix : « Copier le lien » (avec un texte prêt pour SMS ou WhatsApp), « Montrer le QR
   code », « Imprimer ».
3. **Given** un lien privé, **When** le client l'ouvre, **Then** il voit et télécharge sa facture
   seule, sans se connecter, et la page n'est pas indexée.
4. **Given** une facture remise, **When** Youngmi revient sur la commande, **Then** elle voit
   comment et quand la facture a été remise, et peut la remettre à nouveau.

---

### User Story 3 - Corriger sans jamais modifier (Priority: P1)

Une erreur sur une facture (nom, gâteau, prix), une annulation ou un remboursement se règlent par
un avoir lié à la facture, puis par une nouvelle facture si besoin.

**Why this priority**: « il faut gérer les problèmes » ; et la loi interdit de modifier une
facture émise.

**Independent Test**: émettre une facture fictive, faire un avoir total puis une nouvelle facture,
puis un avoir partiel sur une autre ; vérifier numéros, liens et totaux.

**Acceptance Scenarios**:

1. **Given** une facture émise, **When** Youngmi touche « Corriger », **Then** la gestion prépare
   un avoir du montant total et une nouvelle facture à partir de la commande corrigée ; les deux
   ne sont émis qu'après son aperçu et son toucher.
2. **Given** une commande annulée après facture, **When** Youngmi confirme l'annulation, **Then**
   la gestion propose l'avoir total.
3. **Given** un remboursement partiel, **When** Youngmi choisit les lignes et le montant, **Then**
   un avoir partiel est émis ; le remboursement lui-même se fait par Youngmi dans Qonto ou en
   espèces, jamais par la gestion.
4. **Given** un avoir émis, **When** on ouvre la facture d'origine, **Then** elle porte un lien
   vers l'avoir et le reste à payer est recalculé.

---

### User Story 4 - Phase de contrôle (Priority: P1)

Au début, Youngmi contrôle tout : aucune facture n'est émise ni envoyée sans son toucher. La
gestion compte les factures vérifiées sans correction.

**Why this priority**: demande explicite d'Olivier pour vérifier que tout marche avant tout
automatisme.

**Independent Test**: vérifier qu'aucun chemin (assistant, tâche de nuit, formulaire du site)
n'émet ni n'envoie de facture sans toucher.

**Acceptance Scenarios**:

1. **Given** la phase de contrôle, **When** une commande est payée, **Then** la facture reste
   « à émettre » jusqu'au toucher de Youngmi.
2. **Given** Plus › Factures, **When** Youngmi l'ouvre, **Then** elle voit les factures à émettre,
   émises, remises, non remises, les avoirs, et le compteur « vérifiées sans correction ».
3. **Given** l'assistant, **When** Youngmi lui demande de faire une facture, **Then** il prépare
   l'aperçu et l'emmène au bouton ; il n'émet et n'envoie jamais lui-même.

---

### User Story 5 - Être alertée quand quelque chose coince (Priority: P2)

Mail refusé ou revenu, commande payée sans facture depuis plusieurs jours, facture qui ne
correspond plus à la commande, doublon possible avec une facture faite dans Qonto : la gestion
le dit sur l'accueil.

**Why this priority**: sans alerte, un problème passe inaperçu jusqu'à la réclamation du client.

**Independent Test**: provoquer chaque cas sur des données fictives et vérifier l'alerte.

**Acceptance Scenarios**:

1. **Given** un mail de facture qui ne part pas, **When** l'envoi échoue, **Then** la facture est
   marquée « Non remise » et l'accueil le signale avec « Renvoyer » et « Autre moyen ».
2. **Given** une commande de particulier avec facture demandée, payée et remise depuis plus de
   2 jours sans facture,
   **When** Youngmi ouvre l'accueil, **Then** elle la voit dans « Factures à faire ».
3. **Given** une facture émise puis une commande modifiée, **When** Youngmi ouvre l'accueil,
   **Then** elle voit « Facture à corriger ».
4. **Given** une commande dont le champ « facture » porte déjà un numéro Qonto (F-…), **When**
   Youngmi veut émettre, **Then** la gestion avertit d'un doublon possible et demande de
   confirmer.

---

### Edge Cases

- Client qui refuse de donner son nom ou son adresse : pas de facture (prénom, nom et adresse sont
  obligatoires, réponse de l'expert-comptable du 2026-11-09) ; un reçu restera possible (FR-022).
- Client qui ne demande pas de facture : rien à émettre ; s'il la demande après coup, Youngmi le
  note sur la commande (« Le client veut une facture ») et la facture se prépare.
- Client qui change d'avis avant l'émission : « Finalement, pas de facture » ; après l'émission,
  seul un avoir l'annule.
- Client professionnel sur le site : pas de case facture (les restaurants restent dans Qonto).
- Panne de la gestion le jour du retrait : la facture s'émet après coup ; la date de vente reste
  celle du retrait, la date d'émission celle du jour.
- Deux personnes qui touchent « Émettre » en même temps : un seul numéro est donné, jamais deux
  factures pour la même commande, jamais de trou dans la série.
- Commande payée en plusieurs fois (acompte puis solde) : une seule facture à la remise, qui montre
  l'acompte (date, moyen, montant) et le solde (FR-012).
- Remise ou geste commercial : ligne de réduction visible sur la facture.
- Facture en coréen : non ; la facture est toujours en français (R-22), l'écran de Youngmi peut
  être en coréen.
- Client effacé au bout de 3 ans (conservation de la spécification 002) : ses factures restent
  10 ans, avec le nom tel qu'il était sur la facture.
- Lien privé transféré à quelqu'un d'autre : il ne montre que cette facture, rien d'autre ; il
  peut être désactivé par Youngmi.
- Commande « sans suite » ou « annulée » avant paiement : jamais de facture.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: La gestion MUST préparer une facture pour toute commande de client particulier
  (B2C) dont le client a demandé une facture (FR-019), notée « Payé » et remise, à partir des
  lignes, prix, TVA et acompte de la commande, sans retaper. Sans demande, rien n'est préparé.
- **FR-002**: Une facture MUST porter toutes les mentions obligatoires : date d'émission, numéro
  unique, date de la vente (date de remise), nom, adresse, SIREN et numéro de TVA de Madame Cho,
  prénom, nom et adresse postale du client (obligatoires, en lettres latines ; pas de « Client
  particulier », réponse de l'expert-comptable du 2026-11-09), désignation, quantité, prix unitaire HT, taux de TVA, totaux HT,
  TVA et TTC, réductions, date et moyen du règlement, mention « Acquittée ». Les données de Madame
  Cho viennent des Réglages, jamais écrites dans le code.
- **FR-003**: Le numéro MUST être donné au moment de l'émission seulement, dans une série propre
  aux particuliers, chronologique, continue et sans trou, jamais réutilisé, y compris en cas
  d'envois simultanés. Format : `P-AAAA-NNNN` (avoirs `AV-AAAA-NNNN`), à côté de F-AAAA-NNN de
  Qonto ; série à part validée par l'expert-comptable le 2026-11-09, à condition que les dates
  suivent l'ordre des numéros (le numéro est donné à l'émission, dans l'ordre des émissions).
- **FR-004**: Une facture émise MUST être figée : son contenu et son PDF ne changent plus ; seules
  les informations de remise (moyen, date) s'y ajoutent.
- **FR-005**: Toute correction MUST passer par un avoir (total ou partiel), numéroté dans sa propre
  suite continue, lié à la facture d'origine, puis par une nouvelle facture si besoin.
- **FR-006**: Rien n'est émis ni envoyé sans le toucher de Youngmi (ou d'une personne connectée)
  sur « Émettre » puis « Envoyer », après l'aperçu (R-01, phase de contrôle). L'assistant prépare
  et emmène au bouton, il n'émet ni n'envoie jamais (principe VIII de la constitution).
- **FR-007**: L'envoi par mail MUST utiliser le circuit des mails de la spécification 002 (même
  style, étiquette FACTURE, signé DKIM, rangé dans les Échanges de la commande), avec le PDF joint.
- **FR-008**: Pour un client sans mail ou au choix de Youngmi, la gestion MUST offrir : un lien
  privé (adresse longue et imprévisible, non indexée, désactivable), un texte prêt pour SMS ou
  WhatsApp, un QR code de ce lien, et le PDF à imprimer.
- **FR-009**: La gestion MUST garder pour chaque facture comment et quand elle a été remise
  (mail, lien copié, QR montré, imprimée), et l'échec d'un mail.
- **FR-010**: L'accueil MUST signaler : factures à faire (payée et remise depuis plus de 2 jours),
  non remises, à corriger, doublon possible avec un numéro Qonto noté sur la commande.
- **FR-011**: Plus › Factures MUST lister factures et avoirs (filtre par mois et par état), avec le
  compteur de la phase de contrôle.
- **FR-012**: Un acompte reçu MUST apparaître sur la facture finale, avec sa date, son moyen et son
  montant, puis le solde réglé (date, moyen, montant) : une seule facture à la remise, pas de
  facture d'acompte (réponse de l'expert-comptable du 2026-11-09). Un acompte noté plus grand que
  le total bloque l'émission.
- **FR-013**: La gestion MUST rester un outil de commandes et de factures, pas un logiciel de
  caisse : elle n'encaisse rien, et le paiement qu'elle note sert à la facture et au suivi de la
  commande. [NEEDS CLARIFICATION: le fait de noter « payé le … » et le moyen pour un particulier,
  d'en tirer le livre des recettes des espèces (FR-021) et demain un reçu pour chaque particulier
  (FR-022) la fait-il entrer dans l'obligation de logiciel ou système de caisse sécurisé
  (article 286, I-3° bis du CGI) ? Si oui, quoi changer ? Question 1, sans réponse le 2026-11-09,
  reposée à l'expert-comptable].
- **FR-014**: Les factures et avoirs MUST être conservés 10 ans, même quand la fiche du client est
  effacée ; la tâche de nuit ne les efface jamais.
- **FR-015**: Factures, avoirs et leurs remises MUST être inscrits dans un journal que personne ne
  peut réécrire depuis l'outil (chaque ligne liée à la précédente pour qu'une altération se voie),
  vérifiable depuis Plus › Factures.
- **FR-016**: Les écrans de cette fonction MUST exister en français et en coréen (dictionnaire de la
  gestion) ; la facture, le mail et la page du lien privé restent en français.
- **FR-017**: Le manuel de la gestion MUST décrire la fonction dans la même version (FR et KO).
- **FR-018**: Le registre des traitements (T2 facturation) et la page Confidentialité MUST être mis
  à jour dans la même version si un nouveau destinataire ou une nouvelle donnée apparaît (lien
  privé, QR code, adresse postale du client, tableurs remis à l'expert-comptable).
- **FR-019**: La facture MUST être demandée par le client (décision d'Olivier du 2026-11-09) : sur
  le formulaire du site, une case facultative « Je souhaite une facture » (pas pour une
  entreprise) ouvre le champ de l'adresse postale, exigée si la case est cochée ; la demande et
  l'adresse arrivent dans le mail à contact@ et sur la commande (« Facture demandée »). Pour une
  commande prise autrement, Youngmi le note dans le cadre Facture de la commande (« Le client veut
  une facture », avec l'adresse) ; « Finalement, pas de facture » l'enlève tant qu'elle n'est pas
  émise. La liste des commandes montre « Facture demandée » tant que la facture n'est pas faite.
- **FR-020**: L'adresse postale du client MUST être gardée sur sa fiche (champ « Adresse
  (factures) », modifiable, effacée avec la fiche, reprise par une fusion et dans l'export du
  droit d'accès) ; une adresse écrite au moment d'émettre y est gardée. La facture garde l'adresse
  qu'elle porte, 10 ans.
- **FR-021**: Plus › Factures MUST offrir, pour chacun des trois derniers mois, deux tableurs pour
  l'expert-comptable, tirés de la base (rien n'est tenu à part) : « Factures et avoirs » (montants
  par taux de TVA, date et moyen de chaque règlement, acompte et solde) et « Espèces encaissées »
  (le livre des recettes : chaque somme reçue en espèces, particuliers et restaurants, avec ou sans
  facture, l'acompte et le solde chacun à sa date et avec son moyen). Chaque téléchargement est
  noté dans l'historique.
- **FR-022** *(à venir, pas dans cette version)*: Quand Qonto sera branché à la gestion, un reçu
  MUST être fait pour chaque paiement d'un particulier, que Youngmi relit puis envoie par mail ou
  un autre moyen (au toucher, R-01) ; la gestion le prépare. Il attend la liaison Qonto
  (spécification à venir) et la réponse de l'expert-comptable à FR-013.

### Key Entities *(include if feature involves data)*

- **Demande de facture** : sur la commande (`facture_demandee`), notée par le site ou par Youngmi.
- **Facture** : numéro, commande, client (prénom, nom et adresse tels qu'émis), dates
  (vente, émission, règlement), moyen de règlement, lignes figées (désignation, quantité, prix
  unitaire HT, taux, montants), réductions, acompte déduit, totaux, état (à émettre, émise,
  remise, non remise, annulée par avoir), empreinte du PDF.
- **Avoir** : numéro, facture d'origine, lignes et montants repris, motif, dates.
- **Remise** : facture, moyen (mail, lien, QR, impression), date, personne, résultat.
- **Lien privé** : facture, jeton, actif ou désactivé, date de création, dernière ouverture.
- **Journal des factures** : une ligne par émission, avoir, remise, désactivation de lien, liée à
  la précédente.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: De la commande payée à la facture remise par mail : 3 touchers au plus et moins de
  1 minute sur téléphone.
- **SC-002**: Zéro facture de particulier saisie dans Qonto après la mise en service.
- **SC-003**: 100 % des factures d'essai portent toutes les mentions de FR-002 (contrôle
  automatique).
- **SC-004**: Aucun trou ni doublon dans les séries de factures et d'avoirs, y compris sous 20
  émissions simultanées (essai automatique).
- **SC-005**: Un client sans mail reçoit sa facture par l'un des trois moyens en moins de 30
  secondes.
- **SC-006**: Toute altération d'une ligne du journal est détectée par la vérification (essai
  automatique).
- **SC-007**: Pendant la phase de contrôle, au moins 20 factures réelles vérifiées par Youngmi
  avant toute proposition d'envoi sans toucher.

## Assumptions

- Seules les commandes de particuliers sont concernées ; les restaurants restent dans Qonto.
- TVA à 5,5 % sur les gâteaux à emporter, comme aujourd'hui ; un autre taux reste possible ligne
  par ligne.
- La date de vente est la date de remise (retrait ou livraison).
- Le PDF est produit par la gestion elle-même, sans service extérieur ni bibliothèque chargée par
  les pages (principe V).
- Pas de facture électronique structurée (Factur-X) : non exigée pour les particuliers.
- Comptabilité (copie au Drive en 04/04, e-reporting à partir du 2027-09-01) : futur chantier,
  hors de cette spécification. Les tableurs de FR-021 en sont le premier pas. L'e-reporting des
  ventes aux particuliers (totaux par jour, par une plateforme agréée, à partir du 2027-09-01 pour
  une PME) partira des données de la base ; la réception des factures électroniques des
  fournisseurs (obligatoire depuis le 2026-09-01) relève de la comptabilité, pas de la gestion.
- La facture des restaurants, l'envoi sans toucher et la liaison automatique avec Qonto sont hors
  de cette spécification.
- La réponse de l'expert-comptable à la question 1 peut encore changer FR-013, FR-021 et FR-022 ;
  le choix « facture sur demande » est à revérifier avec lui.
