# Implementation Plan : paiement en ligne des commandes

**Branch** : `006-paiement-en-ligne` (branche de travail `claude/project-thread-ng5b6m`) | **Date** : 2026-11-28 | **Spec** : [spec.md](./spec.md)

**Input** : spécification `specs/006-paiement-en-ligne/spec.md` à jour des décisions du 2026-11-27 ;
demande d'Olivier du 2026-11-28 (« on fait la suite ? Qonto etc. »). Le code attend son feu vert
(jamais `/speckit-implement` sans lui) ; audit par son ChatGPT ensuite (P-26) ; publication sur feu
vert (R-19), interrupteurs éteints.

## Summary

La gestion crée des liens de paiement Qonto (encaissement Mollie) avec un droit limité aux liens.
Temps 1 : le mail de confirmation envoyé par Youngmi porte un bouton « Payer en ligne » du montant
entier. Temps 2 : pour un panier à la carte sur une plage libre, le site propose « Commander et
payer » ; une place est retenue 30 minutes, le client paie dans un nouvel onglet, la page du site
suit le paiement, et la commande arrive confirmée et payée. Dans les deux cas, le paiement est
constaté par une notification signée de Qonto puis une relecture, noté sans saisie, et suivi d'un
reçu automatique. Annulation : « À rembourser » en entier, remboursement par Youngmi dans Mollie.
Deux interrupteurs, éteints par défaut.

## Technical Context

**Language/Version** : PHP 8 (OVH mutualisé), JavaScript sans bibliothèque, Python 3 pour la génération.

**Primary Dependencies** : aucune nouvelle bibliothèque. Service : liens de paiement de Qonto (déjà
dans l'offre du compte, activation par Youngmi) ; appels HTTP par la fonction `qonto_http` existante.

**Storage** : SQLite ; tables neuves `liens_paiement` et `retenues`, clés neuves dans `parametres`
([data-model.md](./data-model.md)).

**Testing** : scripts PHP en ligne de commande sur une base d'essai ; faux Qonto local qui répond comme
l'API et envoie des notifications signées ; parcours Playwright (site et gestion, téléphone et
ordinateur, français et coréen) ; relevé coréen ([quickstart.md](./quickstart.md)).

**Target Platform** : téléphone de Youngmi (Galaxy S25), ordinateur ; visiteurs du site sur téléphone.

**Project Type** : site statique généré + formulaire PHP + application PHP (gestion).

**Performance Goals** : réponse à une notification en moins d'une seconde (exigence de Qonto) ; suivi
du paiement côté client toutes les 5 secondes, une relecture de Qonto au plus toutes les 15 secondes
par lien.

**Constraints** : CSP inchangée (la page de paiement s'ouvre dans un onglet, rien de Qonto n'est
chargé dans le site) ; aucun secret dans le dépôt public ; aucune écriture d'argent par le code
(seulement créer et désactiver un lien) ; aucune adresse de retour possible chez Qonto
([research.md](./research.md), R2) ; tâche planifiée OVH au mieux toutes les heures.

**Scale/Scope** : quelques paiements par jour ; une personne qui règle.

## Constitution Check

| Principe | Vérification | État |
| --- | --- | --- |
| I. Dépôt public | Secrets (jetons, secret des notifications) en base hors du dossier servi ; essais sur données fictives et faux Qonto | OK |
| II. Rien d'irréversible | Le code crée et désactive des liens, ne déplace jamais d'argent ; reçu et confirmation automatiques = exceptions à R-01 et R-24 décidées par Olivier (2026-11-21, 2026-11-27), **à inscrire au rulebook puis dans cette constitution avant la publication** ; publication sur feu vert | OK sous condition |
| III. Produits | Prix et TVA recalculés depuis la carte et les fiches validées ; aucun prix venu du navigateur | OK |
| IV. Données personnelles | Ni carte ni IBAN chez Madame Cho ; email du payeur gardé avec la commande ; registre des traitements et page Confidentialité mis à jour dans la même version | OK |
| V. Simple et sobre | Aucun nouveau service : les liens font partie de l'offre Qonto ; frais acceptés par Olivier le 2026-11-27 ; ligne à ajouter dans « Outils digitaux » (Mollie, par Qonto) | OK sous condition |
| VI. Youngmi, téléphone, coréen | Textes dans `coreen.php`, manuel et « Quoi de neuf » FR/KO ; écrans pensés pour le téléphone | OK |
| VII. Sources, régénération, preuves | Modifications dans `.sources/`, régénération, essais du quickstart avant le feu vert | OK |
| VIII. Écritures de l'IA | L'assistant lit l'état d'un paiement, ne crée ni n'annule de lien (FR-025) | OK |

Après la conception : inchangé. Les deux conditions (rulebook et constitution, ligne d'outil) sont
des tâches de la phase finale.

## Project Structure

```text
specs/006-paiement-en-ligne/
├── spec.md, plan.md, research.md, data-model.md, quickstart.md, tasks.md
├── contracts/site-commander-et-payer.md   # le formulaire, la retenue, le suivi
├── contracts/notifications-qonto.md       # la notification signée et ses effets
└── checklists/requirements.md

.sources/
├── donnees.py          # textes Questions, Retrait, Conditions § 6 (payer en ligne, remboursement intégral)
├── gen.py              # formulaire : récapitulatif, case des conditions, « Commander et payer » ; Confidentialité ; date de mise à jour
├── site.js, site.css   # choix payer ou demander, ouverture du paiement, page de suivi
├── envoyer.php         # payer=1 (lien + retenue), suivi=<jeton>, payer dans la réponse de verifier
├── menage.php          # tâche planifiée : liens ouverts relus, retenues échues
└── gestion/inc/
    ├── base.php        # MOYENS en_ligne, schéma liens_paiement et retenues, paramètres
    ├── paiement.php    # NEUF : créer, désactiver, relire un lien ; constater un paiement ; reçu ; frais estimés
    ├── qonto.php       # portées ajoutées, liste fermée des chemins, abonnement, page qonto-notification
    ├── enregistrer.php # retenues comptées pour le site ; commande du temps 2
    ├── accuse.php      # mail de reçu et de confirmation automatique (même chemin que l'accusé)
    ├── vues.php        # bouton « Payer en ligne » dans la confirmation ; textes du reçu
    ├── pages-commandes.php # bloc « Paiement en ligne » : état, envoyer, renvoyer, copier, annuler ; « À rembourser »
    ├── pages-autres.php    # Paramètres : interrupteurs, minimum, retenue, barème, état de la liaison
    ├── rapprochement.php   # versements Mollie reconnus
    ├── assistant-donnees.php # état du paiement lisible, jamais d'action
    ├── manuel.php, coreen.php, pastilles.php
```

**Structure Decision** : un seul fichier neuf (`paiement.php`) pour tout ce qui parle des liens ; le
reste s'insère dans les écrans et chemins existants.

## Phases

1. **Fondations** : schéma, paramètres, liste fermée des chemins Qonto, faux Qonto d'essai.
2. **Constater un paiement** (US3 d'abord, car tout en dépend) : notification signée, relecture,
   effets en une transaction, reçu automatique.
3. **Temps 1** (US1) : bouton dans la confirmation, bloc « Paiement en ligne » de la commande.
4. **Temps 2** (US2) : retenue, `payer=1`, page de suivi, échéance, commande confirmée et payée.
5. **Suivi et remboursement** (US4) : prix modifié, annulation, « À rembourser », rapprochement.
6. **Textes et finitions** : site (Questions, Retrait, Conditions, Confidentialité), manuel,
   « Quoi de neuf », coréen, registre des traitements ; puis essais complets, audit, rulebook.

## Ce qu'il faut hors du code

- **Youngmi** : activer les liens de paiement dans Qonto (« Compte professionnel › Liens de
  paiement › Activer » : site, téléphone, description, contrat Mollie), jamais le bouton de
  déconnexion ; plus tard relier de nouveau Qonto dans la gestion.
- **Olivier** : ajouter les portées `payment_link.read`, `payment_link.write` et `webhook` à l'appli
  « Gestion Madame Cho » dans le portail développeur de Qonto ; créer une tâche planifiée OVH horaire
  pour `menage.php` ; si possible un accès au bac à sable (jeton gardé dans son Bitwarden) ; décider
  de l'inscription des exceptions R-24 et R-01 au rulebook.

## Complexity Tracking

| Écart | Pourquoi | Alternative plus simple écartée |
| --- | --- | --- |
| Mails automatiques et lien créé sans toucher (temps 2) | Décision d'Olivier du 2026-11-27 : carte payée confirmée sans Youngmi, reçu automatique | Temps 1 seul (refusé : c'est le cap e-commerce) |
| Page de suivi qui interroge le serveur | Qonto ne renvoie pas le client sur le site | Laisser le client sans retour (il ne saurait pas si sa commande est prise) |
