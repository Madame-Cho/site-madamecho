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
- [X] T040 [US5] Audit n° 3, C01 : réponse d'une autre adresse que celle du client rangée mais marquée « à vérifier » (FR-037)
- [X] T041 [US5] Audit n° 3, C02 : blocage par appareil après 10 clés fausses en 15 min, la réception continue pour le script (FR-037)
- [X] T042 [US5] Audit n° 3, C04 et C05 : pas de double envoi en moins de 2 minutes, message d'erreur qui renvoie aux Échanges et à Gmail ; provenance des textes des clients pour l'assistant (FR-037)
- [X] T043 [US5] Audit n° 3, C12 : « Tous les échanges » sur la fiche client, journal détaillé replié (FR-038)
- [X] T044 [P] Couleurs B2B violet et B2C bleu-vert, distinctes du marron des filtres (FR-039) ; manuel FR/KO à jour (corbeille, Paramètres, icônes des titres, échanges, couleurs)
- [ ] T036 Mail « Merci » et demande d'avis à essayer en réel quand la fiche Google existera (lien dans Paramètres)
- [ ] T037 Changer la clé du script (montrée dans une capture le 2026-11-07) : nouvelle clé dans Paramètres, propriété CLE, puis « installer »

## Phase 9 : Rappel de retrait (2026-11-08, demande d'Olivier, « au toucher d'abord, automatique ensuite »)

- [X] T045 [US2] FR-040 : type de mail « Rappel » dans `inc/vues.php` (étiquette, phrase d'accueil selon le jour, encadré, empêchement, fin « À demain »), version courte SMS/WhatsApp
- [X] T046 [US1] FR-040 : liste « Rappels à envoyer » dans `tableau_de_bord()` (`inc/modele.php`) et bloc sur Aujourd'hui (`inc/pages-commandes.php`), tuile cliquable
- [X] T047 [US2] FR-040 : cadre « Envoyer le rappel au client » sur la commande confirmée (retrait aujourd'hui, demain ou après-demain), actions `envoyer_rappel` et `rappel_note` (canal), « À faire maintenant » qui le dit
- [X] T048 [P] [US8] Dictionnaire coréen, traduction de l'historique (rappel), manuel FR/KO, styles de l'aperçu (`apercu_mail_css`)
- [X] T049 Essais sur base fictive (liste, envoi, double toucher, noté par SMS, client sans email, livraison de restaurant exclue, confirmation de la veille) et captures FR/KO
- [X] T052 Audit n° 4, A01 : « déjà réglé » pour une commande payée (rappel et confirmation, encadré et texte), `reste_a_regler()` et `total_email()`
- [X] T053 Audit n° 4, A02 et A03 : clé du rendez-vous (`rdv_cle()`) gardée avec la confirmation et le rappel ; `rappel_etat()` ; retour « Retrait modifié » ; note libre sans effet ; `rappel_noter()`
- [X] T054 Audit n° 4, A04 et A05 : verrou de 2 minutes par commande et par type (deux onglets), « Renvoyer » exprès, empreinte de l'aperçu vérifiée au toucher (`empreinte_mail()`, `apercu_a_jour()`), « Envoyer » attend l'aperçu à jour (`gestion.js`)
- [X] T055 Audit n° 4, A06, A07, A10, A11 : bouton WhatsApp retiré pour le pilote, rappel déclaré aussi par Gmail avec le texte envoyé facultatif, « heure à convenir » dans le SMS, retrait du jour sans rappel proposé
- [X] T056 Audit n° 4, A09 : `envoyer_email_client()` rend '' (et non false) quand DKIM manque : message d'erreur au lieu d'un plantage
- [ ] T057 Audit n° 4, A08 : « Tous les échanges » filtré avant la limite, puis pagination (lot suivant)
- [ ] T050 Audit du ChatGPT d'Olivier sur la version (P-26), puis feu vert d'Olivier sur la version exacte, publication, Journal des publications, Registre
- [ ] T051 Plus tard, sur décision d'Olivier (exception à R-01, R-16) : envoi automatique la veille à 10 h par une tâche planifiée OVH, « Ne pas envoyer » sur Aujourd'hui, case « Rappel automatique » sur la commande

## Phase 10 : Pastilles « nouveau » et « a changé » (2026-11-08, demande d'Olivier, FR-041)

- [ ] T058 [US1] Schéma 10 : table `vus`, colonne `utilisateurs.pastilles_depuis` (`inc/base.php`) ; texte en clair des modifications de commande (`inc/modele.php`)
- [ ] T059 [US1] `inc/pastilles.php` : signaux par personne, « vu », « Tout marquer comme vu », nouveautés de l'outil, page « Quoi de neuf »
- [ ] T060 [US1] Menu (haut et bas), titre « (n) », icône de l'application (`gestion.js`), bloc « Depuis votre dernière visite » sur Aujourd'hui
- [ ] T061 [US2] Étiquette et liseré des cartes de commande et de client ; encadré « Changé depuis votre dernière visite » sur la commande ; « Nouvelle réponse » propre à la personne
- [ ] T062 [P] [US8] Coréen (textes et motifs), manuel FR/KO (section « Les pastilles »), styles
- [ ] T063 Essais sur base fictive à deux comptes (Youngmi, Olivier) et captures FR/KO
- [ ] T064 Audit du ChatGPT d'Olivier (P-26), puis feu vert d'Olivier sur la version exacte, publication, Journal des publications, Registre

## Dependencies & Execution Order

- Phase 2 dépend de la phase 1 ; T011 clôt le lot.
- Les phases 3 à 6 sont indépendantes entre elles ; chacune se publie sur feu vert séparé.
- Phase 7 : T021 de préférence avant le lot suivant ; T023 avant tout changement du paiement.
- Phase 8 : T034 clôt le lot ; T035 avant d'annoncer les réponses des clients ; T037 dès que possible.
- Phase 10 : T058 avant T059 à T062 ; T064 clôt le lot.
- Phase 9 : T045 avant T046 et T047 ; T050 clôt le lot ; T051 seulement après la décision d'Olivier et la fin du pilote (T024).
