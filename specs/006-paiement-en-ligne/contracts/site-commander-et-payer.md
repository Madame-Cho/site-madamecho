# Contrat : « Commander et payer » (formulaire « Ma demande » → `ma-demande/envoyer.php`, temps 2)

## Savoir si le paiement est possible

La question existante `verifier=1&date=…` (spécification 007) renvoie en plus :

```json
{"ok": true, "plages": {"0900": "libre", "…": "…"}, "message": "", "payer": true, "minimum": 500}
```

- `payer` : `true` seulement si le temps 2 est allumé, la liaison Qonto prête et le jour ouvert. La
  page décide ensuite selon le panier (à la carte, sans personnalisation au-delà de la plaque, pas de
  professionnel, total au-dessus de `minimum`, en centimes) ; le serveur revérifie tout à l'envoi.

## Envoyer et payer

`POST ma-demande/envoyer.php`, `Accept: application/json`, les champs actuels de la demande plus
`payer=1` et `conditions=1` (case des conditions de commande cochée).

Réponse si tout est bon (`200`) :

```json
{"ok": true, "ref": "MC-…", "paiement": {"url": "https://pay.qonto.com/…", "suivi": "<32 caractères>", "jusqua": "2026-12-15T14:32:00+01:00"}}
```

- La commande est enregistrée tout de suite comme une demande (canal `site`, origine « Commande de la
  carte passée sur le site, paiement en ligne en cours »), avec une retenue de place jusqu'à
  `jusqua` ; aucun mail n'est envoyé à ce moment, ni au client ni à Madame Cho.
- La page ouvre un onglet vide au toucher (sinon le navigateur le bloque), y charge `url`, et garde
  `suivi` (aussi dans `sessionStorage`, pour reprendre le suivi si la page est rechargée).
- Un double toucher renvoie la même réponse (empreinte de la demande, comme aujourd'hui).

Réponse `200` sans `paiement` : `{"ok": true, "ref": "MC-…", "message": "…"}` quand la commande a pu
être enregistrée mais que le paiement n'a pas pu commencer (erreur technique après l'enregistrement) :
elle devient une demande ordinaire (mail à Madame Cho, accusé de réception au client) et la page le
dit.

Réponses de refus : `422` avec `erreurs` (mêmes règles qu'aujourd'hui) ; `409` avec
`{"ok": false, "payer": false, "message": "…"}` si le paiement n'est plus possible (conditions non
acceptées, plage prise entre temps, temps 2 éteint, panier hors carte, Qonto injoignable) : rien n'est
enregistré, l'envoi n'est pas compté dans la limite par adresse, et la page propose d'envoyer la même
demande sans payer, en un toucher.

## Suivre le paiement

`POST ma-demande/envoyer.php`, `Accept: application/json`, corps `suivi=<jeton>`. Toutes les 5
secondes au plus, 40 minutes au plus.

```json
{"ok": true, "etat": "attente"}
{"ok": true, "etat": "paye", "message": "Paiement reçu : votre commande est confirmée. Un mail de confirmation, qui vaut reçu, vous a été envoyé."}
{"ok": true, "etat": "non_abouti", "message": "Le paiement n'a pas abouti et rien n'a été débité. …"}
```

- `etat` : `attente` (ouvert ou en cours), `paye`, `non_abouti` (désactivé, expiré, échu). Le
  serveur relit Qonto au plus une fois par 15 secondes et par lien.
- La réponse ne contient ni nom, ni email, ni montant, ni référence : le jeton seul ne révèle rien.
- Jeton inconnu ou vieux de plus de 24 heures : `{"ok": true, "etat": "inconnu"}`.
- Chaque interrogation fait aussi échoir les retenues dépassées (lien désactivé, place libérée).

## Envoyer sans payer, depuis le suivi

`POST ma-demande/envoyer.php`, corps `sans_payer=<jeton>` (lien « Finalement, envoyer ma demande sans
payer ») : le lien est relu (un paiement déjà arrivé l'emporte), sinon désactivé ; la retenue est
effacée, la commande reste une demande marquée « paiement en ligne non abouti » ; Madame Cho reçoit le
mail d'une demande et le client l'accusé de réception. Réponse : `etat` `non_abouti` avec le message
« Votre demande est envoyée, sans paiement… », ou `attente` si Qonto dit le paiement en cours.

## Quand le paiement n'aboutit pas (retenue échue, lien expiré ou désactivé)

Une seule fois par commande : lien désactivé, retenue effacée, événement « Paiement en ligne non
abouti (raison) », puis le mail ordinaire d'une demande à Madame Cho (sujet « … (paiement en ligne non
abouti) ») et l'accusé de réception au client (exception à R-01 du 11/11/2026).

## Quand le paiement est constaté (quel que soit le chemin)

Dans une seule transaction : lien `paid`, commande `confirmee` · `paye` · moyen `en_ligne`, retenue
effacée, événement `paiement_en_ligne`, `recu_envoye_le` posé ; puis le mail de confirmation et de
reçu part (signé DKIM, copie à contact@), et le mail interne « Commande payée en ligne … » part à
contact@madamecho.com.

- Montant différent de celui du lien : rien n'est noté payé, la commande reçoit « Paiement à
  vérifier » (problème ouvert), pas de reçu.
- Commande déjà payée autrement ou annulée : rien n'est écrasé ; la commande est signalée « Payée deux
  fois » ou « Payée en ligne après son annulation », avec « À rembourser » dans Mollie.
- Plage devenue complète entre-temps (paiement arrivé après la retenue) : la commande est acceptée et
  signalée « Plage dépassée par une commande payée en ligne ».
