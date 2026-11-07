# Data model : changements du lot « audit n° 2 »

## assistant_actions (modifications préparées par l'assistant)

- Nouvelle colonne `empreinte` (texte, vide par défaut) : condensé de la fiche visée au moment de la
  préparation. Schéma de la base : version 6 (migration automatique à la première page ouverte).
- États inchangés : `propose`, `fait`, `annule`, `expire`, `remplace`. Une fiche changée avant le
  toucher passe la proposition à `annule`, avec la raison dans `retour`.

## commandes.lignes (gâteaux d'une commande, JSON)

- Inchangé dans sa forme. Règle nouvelle : à la modification d'une commande, une ligne dont la
  variante était déjà présente garde `prix` (et `ht`, `tva` pour la gamme pro) ; seules les lignes
  nouvelles reçoivent le prix de la carte.

## commandes.paiement / moyen

- Une remise préparée par l'assistant n'écrit plus « payé » ni « carte » par défaut : elle garde
  ceux de la fiche, sauf si la proposition les donne.
