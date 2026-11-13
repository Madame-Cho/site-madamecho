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

## Rapprochement automatique avec Qonto (2026-11-13, demande d'Olivier, FR-030)

- **Commande** : `qonto_ref` peut réunir plusieurs opérations (« id + id » : l'acompte puis le solde, dans
  l'ordre des montants attendus) ou valoir « Hors Qonto » (vérifiée autrement, avec le motif dans
  l'historique) ; `qonto_par` vaut « Qonto (automatique) » quand la gestion l'a noté toute seule.
- **Paramètres** : `qonto_cache` (version 2 : encaissements lus, erreurs, lecture des comptes Qonto complète
  ou non), `qonto_lu_ok` et `qonto_lu_ok_tap` (dernière lecture complète de tous les comptes, ou des seuls
  comptes Qonto), `qonto_refus` (paires « commande|opération » retirées ou refusées par une personne, jamais
  reprises toutes seules, 2 000 au plus), `qonto_ecartes` (encaissements mis de côté : « Vente sans
  commande », « Pas une vente », avec le montant, la personne et l'heure, 1 000 au plus).
- Montants attendus d'une commande : l'acompte reçu à part (jour et moyen de son événement « Acompte
  reçu ») puis le solde, ou le prix ; les espèces ne se cherchent pas (un acompte par carte d'une commande
  soldée en espèces, si). Jour : `paiement_le`, sinon la remise, le retrait ou la demande.
- Suivi : seuls les paiements notés depuis le 2026-11-07 et les encaissements depuis cette date peuvent être
  signalés ; l'absence d'un paiement ne se signale qu'après une lecture complète de moins de 3 jours.
- Les événements notés tout seuls ont pour auteur « système » (aucune pastille) et ne changent pas `maj_le`.
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

## Corbeille (FR-002, schéma 9)

- **corbeille** (nouvelle table) : `le`, `par`, `genre` (commande ou client), `reference`, `libelle`, `motif`, `client_id`,
  `donnees` (les lignes d'origine en JSON : commande, événements, mails reçus ; ou fiche et événements). « Restaurer » les
  remet telles quelles (identifiant gardé s'il est libre) ; une commande ne se restaure qu'après sa fiche client. La tâche
  de nuit efface ce qui a plus de 30 jours ; l'effacement RGPD d'un client efface aussi ses lignes de corbeille.

## Pastilles « nouveau » et « a changé » (FR-041, schéma 10)

- **vus** (nouvelle table) : `utilisateur_id`, `genre` (`commande`, `client` ou `outil`), `objet` (identifiant, en texte),
  `le`, `jusqu_a` (numéro du dernier événement affiché) ; clé (utilisateur, genre, objet). Une ligne est écrite quand la
  personne ouvre la commande, la fiche client ou « Quoi de neuf ». C'est une trace de consultation d'un compte
  (donnée personnelle de l'utilisatrice, registre T8) : la purge de nuit efface les lignes de plus de 60 jours, celles
  d'un compte désactivé et celles d'une commande ou d'une fiche disparue (audit n° 5, constat 8).
- **utilisateurs.pastilles_id** et **pastilles_depuis** (nouvelles colonnes) : le point de départ des pastilles de la
  personne (numéro d'événement, et date pour les nouveautés de l'outil) ; posé à la migration au dernier événement, ou
  juste avant la plus ancienne réponse de client encore non lue de moins de 30 jours, et remis au dernier événement par
  « Tout marquer comme vu ».
- Les pastilles se calculent à partir des **événements** : un événement d'une commande ou d'une fiche est signalé à la
  personne si son numéro dépasse `pastilles_id` et `vus.jusqu_a`, s'il a moins de 30 jours, et s'il n'est pas d'elle
  (auteur différent de son nom, ni « système », ni « Gmail ») ; une réponse de client compte toujours. Les numéros, et
  non les heures, ordonnent les événements (audit n° 5, constats 1 à 4) ; chaque objet garde ses 10 derniers changements
  et ses rouges, avec son total (constat 5).
- Rouge (à agir) : `creation` par le site (nouvelle demande), `email_recu`, `probleme` signalé. Ambre (a changé) :
  `statut`, `paiement`, `rapprochement`, `modification`, `assistant`, `commentaire`, `email_client`, `avis`,
  `restauration`, `creation` à la main ; pour une fiche sans commande : `creation`, `modification`, `commentaire`,
  `fusion`, `avis`, `restauration`.
- **evenements.nouveau** d'une modification de commande garde désormais le texte en clair (« jour du retrait : … → … »),
  pour l'encadré « Changé depuis votre dernière visite ». Les anciennes modifications n'ont que les noms des champs.
- Nouveautés de l'outil : une liste dans le code (`inc/pastilles.php`, `NOUVEAUTES`), identifiant, date, titre, texte,
  lien ; signalée 21 jours au plus, et seulement si elle est postérieure à la création du compte.
