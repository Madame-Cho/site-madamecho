# Tasks : Espace de gestion, lot « audit n° 2 » et lots suivants

**Input** : [plan.md](./plan.md), [spec.md](./spec.md), [research.md](./research.md), [data-model.md](./data-model.md)
**Tests** : sur données fictives, scripts hors du dépôt tant qu'AM-42 n'est pas validée ([quickstart.md](./quickstart.md)).

## Format : `[ID] [P?] [Story] Description`

- **[P]** : peut se faire en parallèle (fichiers différents). **[USn]** : récit de la spécification
  (US2 commande jusqu'à la remise, US4 restaurants, US5 clients, US9 assistant, US10 sécurité,
  US11 conservation, US12 statistiques). Les tâches ne s'exécutent qu'avec le feu vert d'Olivier
  (constitution, Démarche) ; « [X] » = fait sur la branche, pas encore publié.

## Phase 1 : Socle (schéma)

- [X] T001 [US9] Schéma 6 : colonne `empreinte` de `assistant_actions`, migration sans perte, dans `.sources/gestion/inc/base.php`

## Phase 2 : Lot « audit n° 2 », priorité 1 (décision d'Olivier du 2026-11-07)

- [X] T002 [US9] C1 : remise préparée sans paiement : paiement et moyen de la fiche (`remise_paiement_defaut`), plus jamais « payé par carte » par défaut, dans `.sources/gestion/inc/assistant-donnees.php`
- [X] T003 [US9] C1 : la carte de confirmation montre le paiement et le moyen écrits (remise, commande créée remise), dans `proposition_detail`
- [X] T004 [US9] C1 : commande créée remise sans paiement dit : « Pas encore payé » ; consigne de l'outil `commande_remise` : demander le paiement
- [X] T005 [US2] C2 : à la modification d'une commande, une ligne déjà présente garde son prix et sa TVA, dans `.sources/gestion/inc/pages-commandes.php`
- [X] T006 [US9] C3 : empreinte de la fiche à la préparation (`proposition_empreinte`), refus au toucher si elle a changé, dans `proposition_avant`
- [X] T007 [US9] C8 : coût le plus haut réservé avant chaque appel (`assistant_cout_max`), dans `.sources/gestion/inc/assistant.php`
- [X] T008 [P] [US9] Messages nouveaux en coréen dans `.sources/gestion/inc/coreen.php`
- [X] T009 Régénérer `gestion/` (`.sources/gen.py`) ; essais : 97 + 15 + 11 vérifications réussies
- [X] T010 Spécification (FR-019, FR-027, FR-029, SC-009, SC-010), constitution 1.1.0 (principe VIII), plan, recherche, modèle de données, quickstart, checklist
- [ ] T011 Feu vert d'Olivier sur la version exacte, publication, Journal des publications, Registre

## Phase 3 : Lot suivant, sûreté des données de l'assistant (C5, C6)

- [ ] T012 [P] [US9] C5 : séparer recherche résumée (sans coordonnées) et détail d'un client dans les outils de lecture ; ne rendre les coordonnées que sur demande, dans `assistant-donnees.php`
- [ ] T013 [P] [US9] C5 : essai d'injection sur données fictives (note ou message de client qui demande de changer un paiement ou un client) : la carte montre l'intention réelle
- [ ] T014 [US11] C6 : rattacher messages et propositions aux numéros des fiches lues pour l'effacement, en plus de la recherche par texte, dans `modele.php`

## Phase 4 : Lot suivant, voix (C4, C7)

- [ ] T015 [US9] C4 : afficher « J'ai compris : … » modifiable avant l'envoi en mode vocal ; plusieurs clients possibles : demander lequel, jamais deviner
- [ ] T016 [US9] C7 : mesurer, sans garder de contenu, fin de dictée, réponse et premier son, en français et en coréen, sur le téléphone de Youngmi ; décider ensuite (modèle, réponses courtes)

## Phase 5 : Lot suivant, chiffres et reprise (C9, C10)

- [ ] T017 [P] [US12] C9 : enregistrer la TVA de chaque ligne de particulier à la commande et s'en servir dans les statistiques ; réconcilier mois et produits quand le prix final diffère
- [ ] T018 [P] [US5] C10 : reprise : ne rattacher par le nom seul qu'après confirmation (liste des correspondances douteuses)

## Phase 6 : Lot suivant, site public (C11, C15)

- [ ] T019 [P] C11 : la vérification d'une date du formulaire « Ma demande » ne donne que le jour et le moment demandés, limitée en nombre d'appels, dans `.sources/envoyer.php` et `inc/enregistrer.php`
- [ ] T020 [P] C15 : textes du site (« 1 gâteau » pour une sélection, livraison des professionnels, image des petits gâteaux) après validation de Youngmi, dans `.sources/donnees.py`

## Phase 7 : Démarche et décisions (C12, C13, C14)

- [ ] T021 C12 : verser les scripts d'essai dans le dépôt (AM-42) et protéger `main` et `production` (T-24, Olivier)
- [ ] T022 C13 : mettre AM-39 et AM-44 à jour avec la version 2 de l'assistant, à valider par Olivier
- [X] T023 C14 (FR-030, voulu par Olivier le 2026-11-07, « à la main d'abord, auto ensuite ») : schéma 7 (`paiement_par`, `paiement_le`, `qonto_ref`, `qonto_le`, `qonto_par`) ; « Constaté par » et état Qonto sur la commande ; Plus › Rapprochement Qonto (liste, plusieurs paiements pour une opération, tout ou rien) ; référence obligatoire, jamais un nom ou un montant seuls ; espèces dispensées ; un paiement changé est à rapprocher de nouveau ; manuel FR/KO ; essais 26 + 21 vérifications. Reste : la liaison Qonto en lecture remplira le même état (portail développeur de Qonto fermé aux inscriptions)
- [ ] T024 Pilote avec Youngmi (T-22) et relecture coréenne (T-23) avant d'ajouter des fonctions

## Phase 8 : Échanges avec les clients et retours du parcours de test (2026-11-07, nuit)

- [X] T025 [US2] FR-005 : mails au client envoyés depuis la gestion au toucher de « Envoyer », une conversation par commande (en ligne f145afc)
- [X] T026 [US2] FR-031 : page `?p=reponses`, script `.sources/scripts-google/reponses-clients.gs` en lecture seule, orphelins, alerte 24 h (en ligne f145afc) ; correctif du corps en octets (278c2b7)
- [X] T027 [US2] FR-032 : conversation façon messagerie, du plus récent en haut, traits « Nouveaux messages » et « Déjà vus », dans `inc/vues.php`
- [X] T028 [P] [US2] FR-033 : « Par » en boutons à icônes, « Ajouter la note »
- [X] T029 [US2] FR-034 : frise des étapes collée en haut, Confirmer en bas, Modifier en haut, bandeau des changements, croix des bandeaux
- [X] T030 [P] [US8] FR-035 : « Parler en coréen », garde contre un texte en coréen, drapeaux SVG
- [X] T031 [P] FR-036 : icônes du menu (ordinateur compris) et des titres de cadres
- [X] T032 [US5] FR-002 : suppression d'une commande d'essai (ni facture, ni Qonto, ni paiement reçu) et d'une fiche sans commande, en tapant SUPPRIMER
- [X] T033 [P] [US8] Manuel FR/KO et dictionnaire coréen à jour ; zones à toucher à 44 px
- [ ] T034 Feu vert d'Olivier sur la version exacte (et sur l'exception de FR-002), publication, Journal des publications, Registre
- [X] T035 Page Confidentialité du site : mails envoyés depuis la gestion, réponses des clients recopiées dans la gestion, échanges notés, correction du français (T9, principe IV)
- [X] T038 [US5] Corbeille (demande d'Olivier) : schéma 9, table `corbeille`, 30 jours, « Restaurer », purge de nuit, vidée par un effacement RGPD ; Plus › Corbeille
- [X] T039 [P] « Réglages » devient « Paramètres », icône engrenage (demande d'Olivier)
- [ ] T036 Mail « Merci » et demande d'avis à essayer en réel quand la fiche Google existera (lien dans Paramètres)
- [ ] T037 Changer la clé du script (montrée dans une capture le 2026-11-07) : nouvelle clé dans Paramètres, propriété CLE, puis « installer »

## Dependencies & Execution Order

- Phase 2 dépend de la phase 1 ; T011 clôt le lot.
- Les phases 3 à 6 sont indépendantes entre elles ; chacune se publie sur feu vert séparé.
- Phase 7 : T021 de préférence avant le lot suivant ; T023 avant tout changement du paiement.
- Phase 8 : T034 clôt le lot ; T035 avant d'annoncer les réponses des clients ; T037 dès que possible.
