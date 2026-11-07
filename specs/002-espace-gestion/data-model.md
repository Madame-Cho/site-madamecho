# Data model : changements du lot « audit n° 2 »

## assistant_actions (modifications préparées par l'assistant)

- Nouvelle colonne `empreinte` (texte, vide par défaut) : condensé de la fiche visée au moment de la
  préparation. Schéma de la base : version 7 (migration automatique à la première page ouverte).
- États inchangés : `propose`, `fait`, `annule`, `expire`, `remplace`. Une fiche changée avant le
  toucher passe la proposition à `annule`, avec la raison dans `retour`.

## commandes.lignes (gâteaux d'une commande, JSON)

- Inchangé dans sa forme. Règle nouvelle : à la modification d'une commande, une ligne dont la
  variante était déjà présente garde `prix` (et `ht`, `tva` pour la gamme pro) ; seules les lignes
  nouvelles reçoivent le prix de la carte.

## commandes.paiement / moyen

- Une remise préparée par l'assistant n'écrit plus « payé » ni « carte » par défaut : elle garde
  ceux de la fiche, sauf si la proposition les donne.

## Rapprochement avec Qonto (C14, FR-030, schéma 7)

- **Commande**, colonnes ajoutées : `paiement_par` (qui a constaté le paiement, « (avec l'assistant) » si la
  modification venait de l'assistant), `paiement_le` (quand) ; `qonto_ref` (référence de l'opération Qonto,
  vide = pas rapprochée), `qonto_le`, `qonto_par`.
- Règles : un rapprochement n'est attendu que pour un paiement « Payé » ou « Acompte reçu » qui n'est pas en
  espèces, sur une commande ni annulée ni effacée ; le montant à retrouver est l'acompte ou le prix ; changer le
  paiement ou le moyen efface le rapprochement (événement « Rapprochement Qonto à refaire ») ; une référence
  est refusée si elle n'est qu'un montant ou que le nom du client. Aucune vente ni aucun paiement ne naît d'un
  rapprochement ; Qonto n'est jamais écrit (R-24).
- Événements : type `rapprochement` (rapprochée, retiré, à refaire).
