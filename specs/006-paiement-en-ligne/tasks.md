---
description: "Tâches de la spécification 006, paiement en ligne des commandes"
---

# Tasks : paiement en ligne des commandes

**Input** : [spec.md](./spec.md), [plan.md](./plan.md), [research.md](./research.md), [data-model.md](./data-model.md),
[contracts/site-commander-et-payer.md](./contracts/site-commander-et-payer.md),
[contracts/notifications-qonto.md](./contracts/notifications-qonto.md), [quickstart.md](./quickstart.md)

Toutes les modifications se font dans `.sources/`, puis on régénère (principe VII). Aucune tâche ne
commence sans le feu vert d'Olivier pour le code.

## Phase 1 : fondations (bloque tout le reste)

- [x] T001 `MOYENS` + `en_ligne` ; marqueur `schema_paiement` : tables `liens_paiement` et `retenues` ; clés `paiement_*` dans `.sources/gestion/inc/base.php`
- [x] T002 `.sources/gestion/inc/paiement.php` (neuf) : `paiement_pret()`, `lien_creer()`, `lien_desactiver()`, `lien_relire()`, liste fermée des chemins Qonto autorisés, adresse de Qonto réglable seulement en mode d'essai
- [x] T003 [P] Portées `payment_link.read payment_link.write webhook` ajoutées dans `.sources/gestion/inc/qonto.php` ; message « Reliez de nouveau Qonto pour le paiement en ligne » quand les jetons n'ont pas les portées
- [x] T004 [P] Faux Qonto d'essai (hors dépôt, dans les fichiers du projet) : liens, paiements, désactivation, abonnement, notification signée ; essai de signature sur l'exemple publié par Qonto

## Phase 2 : US3, constater un paiement et envoyer le reçu (P1)

- [x] T005 [US3] Page `qonto-notification` sans session : contrôles du contrat (taille, signature, horodatage, organisation, lien connu), réponse 200 immédiate, relecture ensuite (`qonto.php`, `paiement.php`)
- [x] T006 [US3] `paiement_constater()` : une transaction (lien `paid`, commande payée moyen `en_ligne`, retenue effacée, événement, `recu_envoye_le`), garde-fou `paiement_qonto_id` unique, montant comparé (« Paiement à vérifier » sinon)
- [x] T007 [US3] Reçu automatique par le chemin de l'accusé de réception (DKIM, copie à contact@, rangé dans les Échanges) dans `.sources/gestion/inc/accuse.php` ; textes du reçu dans `vues.php` ; « Renvoyer le reçu » dans la commande
- [x] T008 [US3] Abonnement aux notifications depuis Paramètres › Qonto (« Activer le paiement en ligne »), secret tiré au hasard, gardé, jamais montré ni exporté

## Phase 3 : US1, payer une commande confirmée (P1, temps 1)

- [x] T009 [US1] Bloc « Paiement en ligne » de la commande : état, « Envoyer avec la confirmation », copier le lien, renvoyer, annuler ; aucun bouton pour un professionnel, une commande sans prix final ou sous le minimum (`pages-commandes.php`)
- [x] T010 [US1] Mail de confirmation : bouton « Payer en ligne » du montant dû et phrase des autres moyens, aperçu avant envoi ; sans bouton si le temps 1 est éteint ou si Qonto ne répond pas (`vues.php`, `pages-commandes.php`)
- [x] T011 [US1] Relecture d'un lien ouvert à l'ouverture de la commande (au plus une fois par minute)

## Phase 4 : US2, commander et payer tout de suite (P1, temps 2)

- [x] T012 [US2] Retenues : création, comptage pour le site dans `plages_pour_demande()` et `plage_refusee()`, jamais dans `plages_du_jour()` (`enregistrer.php`, `modele.php`)
- [x] T013 [US2] `envoyer.php` : `payer` dans la réponse de `verifier` ; `payer=1&conditions=1` (contrôles du panier, plage revérifiée, commande enregistrée, retenue, lien créé avec une ligne par gâteau et la TVA de la fiche) ; `409` si le paiement n'est plus possible ; `suivi=<jeton>`
- [x] T014 [US2] Formulaire : récapitulatif avant paiement, case des conditions, deux boutons « Commander et payer » et « Envoyer une demande sans payer », phrase qui dit pourquoi le paiement n'est pas proposé (`gen.py`, `site.js`, `site.css`)
- [x] T015 [US2] Page de suivi dans le formulaire : onglet de paiement, interrogation toutes les 5 secondes, états « en attente », « Paiement reçu », « non abouti » avec la demande sans paiement en un toucher (`site.js`)
- [x] T016 [US2] Échéance des retenues (lien désactivé, place libérée, « paiement non abouti ») à chaque appel du formulaire, à chaque notification et dans la tâche planifiée (`paiement.php`, `menage.php`)
- [x] T017 [US2] Paiement constaté au temps 2 : commande `confirmee` avec sa plage, mail de confirmation qui vaut reçu, mail interne « Nouvelle commande payée » à contact@, pastille « Nouveau »

## Phase 5 : US4, suivre, annuler, rembourser (P2)

- [x] T018 [US4] Prix modifié : lien ouvert désactivé, nouvel envoi proposé ; commande annulée ou remise : lien désactivé (`pages-commandes.php`)
- [x] T019 [US4] « À rembourser » (montant entier, étapes dans Mollie) pour une commande payée en ligne puis annulée ; « Remboursé » noté par Youngmi ; double paiement signalé
- [x] T020 [US4] Rapprochement : versements de Mollie reconnus, frais réels du mois, frais estimés par commande (`rapprochement.php`, Statistiques)
- [x] T021 [P] [US4] Assistant : état du paiement en ligne lisible, aucune action sur les liens (`assistant-donnees.php`)

## Phase 6 : réglages, textes, finitions

- [x] T022 Paramètres : interrupteurs des deux temps (éteints), minimum, durée de retenue, barème, état de la liaison et de l'abonnement (`pages-autres.php`)
- [x] T023 [P] Site : Questions, Retrait, Conditions § 6 (payer en ligne, remboursement intégral d'une commande payée), Confidentialité (Qonto, Mollie), dates de mise à jour (`donnees.py`, `gen.py`)
- [x] T024 [P] Manuel, « Quoi de neuf », dictionnaire coréen, pastilles (`manuel.php`, `pastilles.php`, `coreen.php`)
- [x] T025 Essais du quickstart (1 à 15) en ligne de commande et Playwright, relevé coréen, captures ; régénération (`regen.sh`)
- [ ] T026 Registre des traitements (20/01) : ligne « paiement en ligne » ; ligne d'outil « Liens de paiement Qonto (Mollie) » proposée pour « Outils digitaux »
- [ ] T027 Exceptions à R-24 (liens de paiement) et R-01 (reçu et confirmation automatiques) : texte proposé à Olivier pour le rulebook, puis principe II de la constitution mis à jour
- [ ] T028 Audit par le ChatGPT d'Olivier (P-26), corrections, puis feu vert de publication (interrupteurs éteints) et recette réelle à 1 € (quickstart, après publication) : audit n° 11 rendu le 28/11 (15 constats), « Tout corriger avant publication » (Olivier, 28/11 03:42), corrections T029 faites
- [x] T029 Corrections de l'audit n° 11 : un seul lien ouvert par commande, créé sous verrou et jamais après un paiement arrivé ; montant revérifié contre la commande ; suivi « à vérifier » ; places en paiement comptées dans la gestion et jamais libérées sur la seule horloge ; chaque paiement à rembourser ; reçu pris avant l'envoi ; liens de paiement retirés de ce que lit l'assistant ; TVA ventilée ; statistiques sans « frais réels » ; total vu revérifié ; email du payeur plus gardé (`paiement.php`, `modele.php`, `enregistrer.php`, `pages-*.php`, `qonto.php`, `vues.php`, `assistant-donnees.php`, `envoyer.php`, `site.js`, `gen.py`, `coreen.php`, `manuel.php`)

## Dépendances

Phase 1 → Phase 2 → (Phase 3 et Phase 4 en parallèle) → Phase 5 → Phase 6. T004 sert à toutes les
phases d'essai. T027 doit être fait avant la publication.
