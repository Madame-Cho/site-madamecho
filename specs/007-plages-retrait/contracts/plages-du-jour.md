# Contrat : les plages d'un jour (formulaire « Ma demande » → `ma-demande/envoyer.php`)

## Question

`POST ma-demande/envoyer.php`, en-tête `Accept: application/json`, corps `verifier=1&date=AAAA-MM-JJ`.
Rien n'est enregistré ni envoyé ; la limite d'envois du formulaire ne s'applique pas.

## Réponse

```json
{"ok": true, "plages": {"0900": "libre", "1030": "complet", "1200": "libre", "1400": "libre", "1600": "libre", "1800": "libre"}, "message": ""}
```

- `plages` : chaque code de plage, « libre » ou « complet ». « complet » couvre une plage pleine, fermée par
  Youngmi ou touchée par une indispo ; jamais de nombre, jamais de motif.
- `message` : vide, ou une phrase à afficher sous la date quand aucune plage n'est libre (journée indisponible,
  dimanche, jour fermé), sans motif.
- Date invalide, hors délai ou hors des six mois : toutes les plages « libre » et `message` vide (la page
  a déjà sa propre phrase ; l'envoi revérifie tout).
- Gestion pas encore ouverte ou base illisible : toutes les plages « libre » (le formulaire reste utilisable).

## À l'envoi

`plage` remplace `moment` dans le formulaire. L'envoi est refusé (`422`, champ `indispo` avec la phrase) si la
plage n'est pas un code connu, si le jour est un dimanche ou un jour fermé, ou si la plage est « complet ».
