# Contrat : « Commander et payer » (formulaire « Ma demande » → `ma-demande/envoyer.php`, temps 2)

## Savoir si le paiement est possible

La question existante `verifier=1&date=…` (spécification 007) renvoie en plus :

```json
{"ok": true, "plages": {"0900": "libre", "…": "…"}, "message": "", "payer": true}
```

- `payer` : `true` seulement si le temps 2 est allumé, la liaison Qonto prête et le jour ouvert. La
  page décide ensuite selon le panier (à la carte, sans personnalisation au-delà de la plaque, pas de
  professionnel, total au-dessus du minimum) ; le serveur revérifie tout à l'envoi.

## Envoyer et payer

`POST ma-demande/envoyer.php`, `Accept: application/json`, les champs actuels de la demande plus
`payer=1` et `conditions=1` (case des conditions de commande cochée).

Réponse si tout est bon (`200`) :

```json
{"ok": true, "ref": "MC-…", "paiement": {"url": "https://pay.qonto.com/…", "suivi": "<32 caractères>", "jusqua": "2026-12-15T14:32:00+01:00"}}
```

- La commande est enregistrée comme une demande (canal `site`), avec une retenue de place jusqu'à
  `jusqua` ; aucun mail n'est envoyé à ce moment, ni au client ni à Madame Cho.
- La page ouvre `url` dans un nouvel onglet et garde `suivi` pour interroger l'état.

Réponses de refus : `422` avec `erreurs` (mêmes règles qu'aujourd'hui) ; `409` avec
`{"ok": false, "payer": false, "message": "…"}` si le paiement n'est plus possible (plage prise entre
temps, temps 2 éteint, Qonto injoignable) : la page propose alors d'envoyer la même demande sans
payer, en un toucher.

## Suivre le paiement

`POST ma-demande/envoyer.php`, `Accept: application/json`, corps `suivi=<jeton>`. Toutes les 5
secondes au plus, 40 minutes au plus.

```json
{"ok": true, "etat": "attente"}
{"ok": true, "etat": "paye", "message": "Paiement reçu : votre commande est confirmée. Un mail de confirmation vous a été envoyé."}
{"ok": true, "etat": "non_abouti", "message": "Le paiement n'a pas abouti et rien n'a été débité. …"}
```

- `etat` : `attente` (ouvert ou en cours), `paye`, `non_abouti` (désactivé, expiré, échu). Le
  serveur relit Qonto au plus une fois par 15 secondes et par lien.
- La réponse ne contient ni nom, ni email, ni montant, ni référence : le jeton seul ne révèle rien.
- Jeton inconnu ou vieux de plus de 24 heures : `{"ok": true, "etat": "inconnu"}`.

## Quand le paiement est constaté (quel que soit le chemin)

Dans une seule transaction : lien `paid`, commande `confirmee` · `paye` · moyen `en_ligne`, retenue
effacée, événement `paiement_en_ligne`, `recu_envoye_le` posé ; puis le mail de confirmation et de
reçu part (signé DKIM, copie à contact@), et le mail interne « Nouvelle commande payée » part à
contact@madamecho.com comme pour une demande.
