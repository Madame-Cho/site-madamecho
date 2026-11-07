# Research : lot « audit n° 2 » (2026-11-07)

Décisions prises pour les constats retenus par Olivier (« Tout retenir, C1-C3 et C8 d'abord »).

## C1 : paiement d'une remise préparée par l'assistant

- **Decision** : sans paiement dit, la remise garde celui de la fiche (payé, acompte reçu, acompte
  demandé, sinon « Pas encore payé ») et le moyen de la fiche ; la carte montre toujours le paiement
  et le moyen écrits ; une commande créée remise sans paiement dit est « Pas encore payé » ; la
  consigne de l'assistant lui demande de poser la question.
- **Rationale** : un état de paiement faux fausse « Reste à payer » et les relances ; un « pas encore
  payé » visible se corrige d'un toucher.
- **Alternatives** : garder « payé » par défaut mais l'afficher (rejeté : un défaut optimiste reste
  un risque si la carte est lue vite, par exemple en mode vocal) ; interdire la remise sans
  paiement (rejeté : bloque une livraison de restaurant payée au relevé).

## C2 : prix recalculés quand on change une date

- **Decision** : à l'enregistrement d'une commande existante, une ligne dont le gâteau était déjà
  dans la commande garde son prix (et sa TVA de gamme pro) ; seule une ligne nouvelle prend le prix
  de la carte. Vaut pour l'écran et pour l'assistant, qui rejoue le même formulaire.
- **Rationale** : le prix est celui accepté par le client au moment de la commande.
- **Alternatives** : bouton « reprendre les prix de la carte » (reporté : aucun besoin exprimé).

## C3 : proposition périmée

- **Decision** : empreinte (condensé MD5) de la fiche visée, sans `maj_le` ni `dernier_echange`,
  enregistrée à la préparation et comparée au toucher ; différente : refus, proposition écartée,
  message FR/KO. Créations : pas d'empreinte.
- **Rationale** : un commentaire ne doit pas bloquer ; tout autre changement peut contredire
  l'intention.
- **Alternatives** : numéro de version incrémenté à chaque écriture (rejeté : toucherait toutes les
  fonctions d'écriture).

## C8 : plafond strict

- **Decision** : avant chaque appel, coût réservé = tout le texte envoyé (3 caractères par jeton,
  prix d'entrée sans cache) + la réponse la plus longue permise ; refus si dépassement.
- **Rationale** : surestimation volontaire, simple, sans dépendance au comptage des jetons.
- **Alternatives** : API de comptage des jetons (rejeté : un appel de plus à chaque tour).

## Reportés aux lots suivants (voir tasks.md)

C4 noms à la voix, C5 minimisation des résultats d'outils, C6 effacement par identifiants, C7 mesure
de la lenteur vocale, C9 TVA historique des particuliers, C10 reprise par nom, C11 point d'accès des
indispos, C12 tests dans le dépôt et protection des branches, C13 responsabilités (AM-39, AM-44),
C14 paiement constaté et rapprochement Qonto, C15 textes du site.
