# Research : plages de retrait à capacité

## R1. Où ranger la plage d'une commande

- **Decision** : dans la colonne existante `commandes.retrait_moment`, avec des codes neufs (`0900`, `1030`,
  `1200`, `1400`, `1600`, `1800`, l'heure de début). Les anciennes valeurs (`matin`, `midi`, `apres-midi`,
  `soir`) restent valables et sont lues comme avant.
- **Rationale** : aucune migration des commandes ; l'historique, les exports et les filtres continuent de
  marcher ; `MOMENTS_PLAGES` (début, fin) sert déjà à l'agenda du client et aux indispos : on y ajoute les plages.
- **Alternatives considered** : une colonne `plage` neuve (migration, deux champs à tenir cohérents) ; recalculer
  la plage depuis l'heure seule (perd le choix du client quand Youngmi n'a pas noté d'heure).

## R2. À quelle plage compte une commande

- **Decision** : `plage_de_commande()` : l'heure précise d'abord, dans la plage qui la contient (avant 9 h : la
  première ; à 19 h ou après : la dernière) ; sinon le code de plage ; sinon l'ancien moment, dans la
  première plage qu'il couvre. À l'enregistrement, `plage_alignee()` remet le code de la plage qui contient
  l'heure, pour que l'Agenda, les places et les mails disent la même chose (audit n° 10, C10-02 : avant,
  le code l'emportait sur l'heure). Comptent : `mode = retrait`, statut `confirmee` ou `remise`, non effacée.
- **Rationale** : décision d'Olivier (« une demande ne prend pas de place ») ; une commande remise a occupé
  sa plage ce jour-là.
- **Alternatives considered** : compter les propositions (refusé par Olivier).

## R3. Réglage d'une plage

- **Decision** : table `plages_reglages (jour, plage, maximum NULL, fermee 0|1, maj_le, maj_par)`, une ligne
  seulement quand Youngmi a changé quelque chose ; « Rouvrir » remet `fermee = 0`, « Maximum par défaut »
  efface le maximum particulier ; une ligne revenue à l'état par défaut est supprimée. Chaque changement est
  noté dans le journal des événements (type `agenda`).
- **Rationale** : rien à créer pour les jours ordinaires ; un seul endroit à lire.

## R4. Ce que le site demande et ce qu'on lui répond

- **Decision** : `POST verifier=1&date=AAAA-MM-JJ` → `{"ok":true,"plages":{"0900":"libre","1030":"complet",…},"message":""}`.
  « complet » vaut pour une plage pleine, fermée ou touchée par une indispo ; `message` donne la phrase d'une
  indispo de la journée entière (sans motif), ou celle du dimanche. Contrat : [contracts/plages-du-jour.md](./contracts/plages-du-jour.md).
- **Rationale** : le visiteur n'apprend ni un nombre de clients ni un motif (principe IV) ; une seule question
  par date, et l'envoi revérifie la plage choisie.
- **Alternatives considered** : publier l'état des plages dans un fichier statique (pas à jour, et visible de tous).

## R5. Le dimanche

- **Decision** : `JOURS_SEMAINE_FERMES = [0]` dans `donnees.py`, copié dans le catalogue : refus dans la page,
  dans `envoyer.php` et dans la réponse aux plages ; dans la gestion, un avertissement sans blocage.
  Les horaires lus par les moteurs passent à « Monday » à « Saturday ».
- **Rationale** : décision de Youngmi (cas particuliers par téléphone) ; le champ date du navigateur ne sait
  pas griser un jour de la semaine, d'où la phrase sous la date.

## R6. Acompte

- **Decision** : retiré des textes du site et des formulaires de proposition et de confirmation de la gestion ;
  le champ et les états `acompte_demande` / `acompte_recu` restent pour les commandes qui en ont déjà un.
- **Rationale** : décision d'Olivier ; rien à migrer, l'historique reste juste.
