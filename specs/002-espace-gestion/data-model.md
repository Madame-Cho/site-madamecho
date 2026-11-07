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

## Échanges avec les clients (FR-005, FR-031 à FR-033, schéma 8)

- **Événement**, colonne ajoutée `lu` (1 par défaut ; 0 pour un mail reçu pas encore vu, remis à 1 à
  l'ouverture de la commande). Types utilisés : `email_client` (mail envoyé depuis la gestion,
  `ancien` = « type:… mid:<Message-ID> »), `email_recu` (réponse du client ou mail envoyé depuis Gmail),
  `commentaire` (échange noté, `ancien` = « par:telephone|sms|whatsapp|oral|instagram|autre »).
- **mails_recus** (nouvelle table) : `gmail_id` (une seule fois chacun), `le`, `commande_id`,
  `evenement_id`, `etat` (rangé, envoyé, copie, orphelin, ignoré) ; pour un orphelin seulement :
  expéditeur, objet, extrait, gardés 30 jours ; copies et ignorés effacés après 3 mois.
- **Paramètres** : `reponses_empreinte` (empreinte de la clé du script, jamais la clé), heure du dernier
  passage du script.

## Suppression d'un essai (FR-002, sans changement de schéma)

- Une commande sans facture, sans `qonto_ref` et dont le paiement n'est ni « Payé » ni « Acompte reçu »
  peut être supprimée : la commande, ses événements et ses lignes de `mails_recus` disparaissent ; un
  événement `suppression` (référence, motif, rattaché au client) reste.
- Une fiche client sans aucune commande peut être supprimée avec ses événements ; un événement
  `suppression` (numéro, motif) reste dans le journal.
