# Tasks : factures des particuliers

**Input** : [plan.md](./plan.md), [spec.md](./spec.md), [research.md](./research.md), [data-model.md](./data-model.md)
**Tests** : données fictives, scripts hors du dépôt ([quickstart.md](./quickstart.md)). « [X] » = fait sur la
branche, pas publié. Rien n'est publié avant les réponses de Florent (FR-003, FR-012, FR-013) et le feu vert d'Olivier.

## Phase 1 : Socle

- [ ] T001 Schéma 11 : tables `factures` et `factures_remises`, migration sans perte, dans `base.php`
- [ ] T002 [P] Écrivain PDF minimal (`pdf.php`) : A4, Helvetica, WinAnsi, alignement à droite, traits
- [ ] T003 [P] QR code en SVG (`qr.php`), octets, correction M, versions 1 à 10

## Phase 2 : US1, émettre la facture (P1, lot 1)

- [ ] T004 [US1] Préparation (`facture_preparer`) : vendeur, client, lignes, HT et TVA par taux, ajustement si prix final, acompte, paiement ; contrôles (payée, montant, nom, particulier)
- [ ] T005 [US1] Émission (`facture_emettre`) : numéro en transaction exclusive, PDF figé, empreinte, chaîne, `commandes.facture`, événement
- [ ] T006 [US1] Bloc « Facture » de la commande : aperçu, adresse facultative, Émettre (confirmation), PDF, Imprimer ; avertissement si un numéro Qonto est déjà noté
- [ ] T007 [US1] Pages `facture-pdf` (connecté) et Plus › Factures (liste, mois, compteur de la phase de contrôle, vérification du journal)

## Phase 3 : US2, remettre au client (P1, lot 2)

- [ ] T008 [US2] Pièce jointe dans `envoyer_email_client` (multipart/mixed)
- [ ] T009 [US2] Mail « FACTURE » (aperçu, petit mot, Envoyer au toucher, verrou, doublon), PDF joint
- [ ] T010 [US2] Lien privé : page publique `/facture/<id>-<jeton>` sans index, PDF ; réécriture courte dans `gen.py` ; désactiver
- [ ] T011 [US2] Texte prêt pour SMS ou WhatsApp (copier), QR code à montrer, « remise sur papier » ; suivi des remises

## Phase 4 : US3 et US5, corriger et alerter (lot 3)

- [ ] T012 [US3] Avoir total ou partiel (motif, lignes), série propre, PDF figé, chaîne ; commande de nouveau « à émettre » après un avoir total
- [ ] T013 [US5] Alertes de l'accueil : à émettre (payée et remise depuis 2 jours), émise pas remise, numéro Qonto noté (doublon possible), journal altéré

## Phase 5 : US4 et finitions (lot 4)

- [ ] T014 [US4] Phase de contrôle : compteur des factures vérifiées, rien d'automatique
- [ ] T015 [P] Conservation : purge à 10 ans, jetons vidés à 12 mois, `factures` hors de l'effacement des clients
- [ ] T016 [P] Coréen (`coreen.php`) et manuel FR/KO (`manuel.php`), Quoi de neuf
- [ ] T017 Régénérer ; essais unitaires et parcours ; captures FR/KO et téléphone
- [ ] T018 Consigne d'audit pour le ChatGPT d'Olivier (P-26)
- [ ] T019 Réponses de Florent : `/speckit-clarify` (FR-003, FR-012, FR-013), ajustements
- [ ] T020 Registre des traitements T2 et Confidentialité (si Olivier le valide), mail d'information FR/KO à Youngmi (brouillon)
- [ ] T021 Feu vert d'Olivier sur la version exacte, publication, Journal des publications, Registre
