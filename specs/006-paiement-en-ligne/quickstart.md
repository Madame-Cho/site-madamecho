# Quickstart : prouver le paiement en ligne

Données fictives seulement. Les essais automatiques parlent à un faux Qonto local ; aucun secret
dans le dépôt ni dans la conversation.

## Préparation

1. Base d'essai : copier la base fictive des essais de la gestion dans `~/.madamecho-donnees/`.
2. Faux Qonto : lancer le serveur d'essai (projet, hors dépôt) qui répond comme
   `/v2/payment_links`, `/v2/payment_links/{id}`, `/v2/payment_links/{id}/payments`,
   `/v2/payment_links/{id}/deactivate` et `/v2/webhook_subscriptions`, et qui peut envoyer une
   notification signée.
3. Site et gestion : `MC_ESSAI=1 php -S 127.0.0.1:8767 -t <dépôt>` avec l'adresse de Qonto pointée
   sur le faux Qonto (variable d'essai, sans effet en ligne).
4. Paramètres › Qonto : liaison d'essai, « Activer le paiement en ligne », temps 1 et temps 2
   allumés.

## Scénarios

| # | Scénario | Attendu |
| --- | --- | --- |
| 1 | Signature : charge `{"test":"data"}`, secret `test-secret`, `t=1704110400` | même `v1` que l'exemple publié par Qonto |
| 2 | Temps 1 : confirmer une commande fictive, ouvrir « Envoyer la confirmation » | aperçu avec « Payer en ligne » et le montant ; phrase des autres moyens |
| 3 | Le faux Qonto passe le lien à `paid` et notifie | commande « Payé · En ligne », événement, pastille, un reçu dans les Échanges |
| 4 | Même notification rejouée deux fois | aucun second reçu, aucun double paiement |
| 5 | Notification mal signée, ou vieille de 11 minutes | `401`, rien d'écrit |
| 6 | Prix modifié avec un lien ouvert | lien désactivé chez le faux Qonto, nouvel envoi proposé |
| 7 | Temps 2 : panier à la carte, plage libre, « Commander et payer » | retenue créée, page de suivi « en attente », la plage n'est plus libre pour un second visiteur si c'était la dernière place |
| 8 | Temps 2 payé | commande « Confirmée · Payé · En ligne », place comptée, mail de confirmation et de reçu, page de suivi « Paiement reçu » |
| 9 | Temps 2, retenue échue sans paiement | lien désactivé, place libérée, commande « paiement non abouti » |
| 10 | Temps 2 refusé : personnalisation, professionnel, plage complète, dimanche, total sous le minimum | seul le parcours de demande est proposé |
| 11 | Commande payée puis annulée | « À rembourser » avec le montant entier et les étapes Mollie ; « Remboursé » noté par Youngmi |
| 12 | Interrupteurs éteints | aucun bouton, aucune option sur le site, comme avant |
| 13 | Faux Qonto en panne | confirmation sans bouton ; au temps 2, demande sans paiement proposée |
| 14 | Coréen | tous les nouveaux textes de la gestion traduits ; relevé des pages en coréen sans nouveau reste en français |
| 15 | Téléphone 320 px | aucun défilement horizontal ; zones à toucher d'environ 44 px |

## Après publication (interrupteurs éteints)

1. Youngmi active les liens de paiement dans Qonto, puis relie de nouveau Qonto dans la gestion.
2. Olivier allume le temps 1, paie 1 € sur une commande d'essai, vérifie reçu et gestion ; Youngmi
   rembourse dans Mollie et note « Remboursé ».
3. Même essai au temps 2 ; puis décision d'Olivier d'allumer pour de bon.
