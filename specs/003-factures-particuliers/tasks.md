# Tasks : factures des particuliers

**Input** : [plan.md](./plan.md), [spec.md](./spec.md), [research.md](./research.md), [data-model.md](./data-model.md)
**Tests** : données fictives, scripts hors du dépôt ([quickstart.md](./quickstart.md)). « [X] » = fait sur la
branche, pas publié. Rien n'est publié sans le feu vert d'Olivier sur la version exacte ; FR-013 attend encore Florent.

## Phase 1 : Socle

- [X] T001 Schéma 11 : tables `factures` et `factures_remises`, migration sans perte, dans `base.php`
- [X] T002 [P] Écrivain PDF minimal (`pdf.php`) : A4, Helvetica, WinAnsi, alignement à droite, traits
- [X] T003 [P] QR code en SVG (`qr.php`), octets, correction M, versions 1 à 10

## Phase 2 : US1, émettre la facture (P1, lot 1)

- [X] T004 [US1] Préparation (`facture_preparer`) : vendeur, client, lignes, HT et TVA par taux, ajustement si prix final, acompte, paiement ; contrôles (payée, montant, nom, particulier)
- [X] T005 [US1] Émission (`facture_emettre`) : numéro en transaction exclusive, PDF figé, empreinte, chaîne, `commandes.facture`, événement
- [X] T006 [US1] Bloc « Facture » de la commande : aperçu, adresse facultative, Émettre (confirmation), PDF, Imprimer ; avertissement si un numéro Qonto est déjà noté
- [X] T007 [US1] Pages `facture-pdf` (connecté) et Plus › Factures (liste, mois, compteur de la phase de contrôle, vérification du journal)

## Phase 3 : US2, remettre au client (P1, lot 2)

- [X] T008 [US2] Pièce jointe dans `envoyer_email_client` (multipart/mixed)
- [X] T009 [US2] Mail « FACTURE » (aperçu, petit mot, Envoyer au toucher, verrou, doublon), PDF joint
- [X] T010 [US2] Lien privé : page publique `/facture/<id>-<jeton>` sans index, PDF ; réécriture courte dans `gen.py` ; désactiver
- [X] T011 [US2] Texte prêt pour SMS ou WhatsApp (copier), QR code à montrer, « remise sur papier » ; suivi des remises

## Phase 4 : US3 et US5, corriger et alerter (lot 3)

- [X] T012 [US3] Avoir total ou partiel (motif, lignes), série propre, PDF figé, chaîne ; commande de nouveau « à émettre » après un avoir total
- [X] T013 [US5] Alertes de l'accueil : à émettre (payée et remise depuis 2 jours), émise pas remise, numéro Qonto noté (doublon possible), journal altéré

## Phase 5 : US4 et finitions (lot 4)

Ajouts faits en route : « Mettre le prix de la commande à … » quand facture et commande n'ont plus le même montant (geste
commercial) ; date « 1er » sur les documents ; nouveauté « Les factures des particuliers » (sa date `le` sera celle de la
publication) ; tâche de nuit : `conservation.php` charge `reponses.php` (sans lui, elle s'arrêtait depuis f145afc, 07/11).


- [X] T014 [US4] Phase de contrôle : compteur des factures vérifiées, rien d'automatique
- [X] T015 [P] Conservation : purge à 10 ans, jetons vidés à 12 mois, `factures` hors de l'effacement des clients
- [X] T016 [P] Coréen (`coreen.php`) et manuel FR/KO (`manuel.php`), Quoi de neuf
- [X] T017 Régénérer ; essais unitaires (50/50) et parcours (44/44) ; captures FR/KO et téléphone ; non-régression : rappel 30/30, pastilles 60/60 (« Tout voir (10) » : une nouveauté de plus), relevé 16/16, audit n° 2 15/15, C14
- [X] T017a Relecture avant l'audit : nom de client ou plaque en coréen (le PDF aux polices standard les écrirait « ? ») : « Nom sur la facture » en lettres latines ou « Client particulier », adresse et produits contrôlés avant le numéro, « plaque personnalisée » ; « RCS » plus doublé. Essais 57/57, parcours 44/44, nom coréen 6/6
- [X] T018 Audit n° 6 préparé pour le ChatGPT d'Olivier (P-26) : commande et rapport vide en 92/02/02, consigne en 92/04/03 (08/11)
- [X] T018a Migration : la base au schéma 10 (production) reçoit les tables des factures (garde `>= 11`), essai sur copie
- [X] T018b Audit n° 6 rendu (13 constats, « Tout retenir » d'Olivier, 08/11) ; corrections : avoir partiel au taux choisi parmi ceux de la facture, avoir total taux par taux (C3) ; la facture émise est celle de l'aperçu relu, tout relu sous le verrou (C4) ; pendant la phase de contrôle, rien ne se remet avant « J'ai vérifié », qui demande d'avoir ouvert le PDF (C5) ; la chaîne couvre aussi les données figées, message « Aucune modification détectée », pas de certification (C6) ; mail « envoyé », sans preuve de lecture (C8) ; SIREN (Luhn) et TVA (clé) contrôlés, mentions écrivables (C9) ; raison d'avoir contrôlée, documents d'avant visibles après un avoir total (C11) ; prix unitaire HT avec les décimales utiles quand la ligne ne tombe pas juste (C12) ; date de bascule dans Plus › Factures (C13). C1, C2, C7 : réponses de Florent (questions 1, 5, 3) ; C10 : T020. Essais : audit6.php 42/42, unitaires 57/57, parcours 58/58, nom coréen 6/6 ; non-régression pastilles 60/60, rappel 30/30 et 14/14, noms 45/45 et 10/10
- [X] T018c Fusion de main (4e9b4eb : majuscules des noms, écrans Obligations et Hygiène) dans la branche, sans rebase (08/11) : garde de migration `>= 11` gardée (les tables des Obligations et de l'Hygiène sont hors numérotation) ; tâche de nuit : factures et Obligations chacune dans son `try` ; la spécification 003 ajoutée à ce que lit l'assistant (`GESTION_ASSISTANT_SPECS`) ; essais des factures, des noms, du rappel, des Obligations et de l'Hygiène relancés (hors dépôt)
- [X] T019 Réponses de Florent (09/11) : série P-/AV- validée (FR-003) ; acompte et solde sur la facture finale (FR-012) ; prénom, nom et adresse obligatoires, plus de « Client particulier », adresse gardée sur la fiche (FR-002, FR-020) ; acompte plus grand que le total bloqué
- [X] T019a Choix C d'Olivier (09/11) : facture seulement si demandée (FR-019) : case du formulaire du site et adresse (`gen.py`, `site.js`, `envoyer.php`, `enregistrer.php`), « Le client veut une facture » et « Finalement, pas de facture » dans la gestion, pastille de la liste, alertes limitées aux demandes ; colonnes `clients.adresse` et `commandes.facture_demandee` ajoutées dans l'étape 11 de la migration ; Confidentialité et Conditions de commande mises à jour (9 novembre)
- [X] T019b Tableurs pour l'expert-comptable tirés de la base (FR-021) : « Factures et avoirs » et « Espèces encaissées » (livre des recettes, acompte et solde chacun à sa date), trois derniers mois, FR/KO, manuel ; essais florent.php 91/91, unitaires 62/62, audit6 42/42, parcours 58/58, nom coréen 7/7, choix C 11/11, formulaire du site 11/11 ; non-régression noms 45/45 et 10/10, rappel 30/30 et 14/14, Obligations 47/47 et 95/97, Hygiène 45/45, 30/30, 22/22, 48/49, 29/30, 45/45, 23/23 (les KO attendus : essais de main qui supposent le schéma < 11), pastilles 59/60 (« Tout voir (13) » à mettre à jour ; essai daté du 08/11)
- [ ] T019c Question 1 (logiciel de caisse, FR-013) reposée à Florent (brouillon dans la boîte d'Olivier), avec le reçu de FR-022 et la plateforme d'e-reporting
- [ ] T019d Reçu systématique pour les particuliers (FR-022), après la liaison Qonto et la réponse à FR-013
- [ ] T020 Registre des traitements T2 et Confidentialité (si Olivier le valide), mail d'information FR/KO à Youngmi (brouillon)
- [ ] T021 Feu vert d'Olivier sur la version exacte, publication, Journal des publications, Registre
