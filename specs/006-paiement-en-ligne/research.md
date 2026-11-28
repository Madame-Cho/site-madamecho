# Research : paiement en ligne des commandes

Lectures du 2026-11-28 : documentation publique de l'API Business de Qonto (liens de paiement,
connexion, portées OAuth, abonnements aux notifications, guide des notifications, guide des liens
de paiement), aide de Qonto et de Mollie sur l'activation, les moyens, les versements et les
remboursements. Ce qui n'est pas écrit dans ces pages est dit « non documenté » et traité par
prudence.

## R1. Le lien de paiement de Qonto : lien « panier », à usage unique

- **Decision** : la gestion crée un lien de type panier (`POST /v2/payment_links`, portée
  `payment_link.write`) avec une ligne par gâteau (titre, quantité, prix unitaire TTC en chaîne
  décimale, taux de TVA du produit, `type` = `good`), `reusable` = `false`, moyens proposés
  `credit_card` et `apple_pay`. Elle garde l'identifiant, l'adresse `url` et l'état du lien.
- **Rationale** : le lien « facture » demande une facture Qonto émise avant le paiement, ce qui va
  contre la règle « facture seulement si le client la demande » (spécification 003). Le panier
  montre au client ce qu'il paie. Le montant du lien est la somme des lignes : la gestion compare le
  montant renvoyé par Qonto au prix dû et annule le lien s'ils diffèrent.
- **Alternatives considered** : un seul article « Commande MC-… » au prix total (plus simple, mais le
  client ne voit pas le détail ; gardé pour une commande sur demande dont le prix final diffère de
  la somme des lignes, ce qui est fréquent après une proposition) ; PayPal et iDEAL (PayPal demande
  un compte de plus, iDEAL sert aux Pays-Bas : non proposés).

## R2. Pas de retour automatique sur le site après le paiement

- **Decision** : aucune adresse de retour n'existe dans l'API ; le client reste sur la page de
  Mollie. Au temps 2, la page du site ouvre le paiement dans un nouvel onglet et reste ouverte : elle
  demande au serveur où en est le paiement toutes les 5 secondes et affiche « Paiement reçu, votre
  commande est confirmée » ou « Paiement non abouti ». Au temps 1, le mail de reçu fait office de
  confirmation. Les pages de retour de FR-021 deviennent l'état affiché par cette page ; aucune
  donnée personnelle dans l'adresse (un jeton aléatoire de suivi, sans référence).
- **Rationale** : c'est ce que permet l'API ; le reçu automatique (FR-013) couvre le client qui
  ferme tout.
- **Alternatives considered** : demander à Qonto une adresse de retour (non documenté, non
  disponible) ; un autre prestataire (décision d'Olivier contraire : tout reste chez Qonto).

## R3. Savoir qu'un lien est payé : notifications signées, puis lecture de contrôle

- **Decision** : la gestion s'abonne aux notifications `v1/payment-links` (`POST
  /v2/webhook_subscriptions`, portées `webhook` et `payment_link.read`) vers
  `https://madamecho.com/gestion/?p=qonto-notification`. À chaque notification : vérifier l'en-tête
  `X-Qonto-Signature` (`t=…,v1=…`, HMAC-SHA256 de `{t}.{corps brut}` avec le secret de
  l'abonnement), refuser un horodatage de plus de 10 minutes, répondre 200 en moins d'une seconde,
  puis relire le lien (`GET /v2/payment_links/{id}`) et ses paiements (`GET
  /v2/payment_links/{id}/payments`) avant de noter quoi que ce soit (FR-010, FR-033). En secours, la
  même relecture a lieu quand la page de suivi du client interroge le serveur, quand Youngmi ouvre
  la commande, et à chaque tâche planifiée (toutes les heures chez OVH) pour les liens ouverts.
- **Rationale** : les notifications ne disent pas si `payment_links.updated` part au paiement (non
  documenté) ; la relecture rend le résultat sûr quoi qu'il arrive, et l'état payé n'est noté que
  sur la foi de Qonto. Le test de signature publié par Qonto (charge `{"test":"data"}`, secret
  `test-secret`) sert d'essai automatique. Qonto réessaie pendant quelques jours tant qu'il ne reçoit
  pas de 2xx.
- **Alternatives considered** : relecture seule, sans notification (le paiement serait noté
  jusqu'à une heure plus tard si le client ferme sa page) ; notification seule (non documentée pour
  l'état payé).

## R4. Retenir une place pendant le paiement (temps 2)

- **Decision** : une table `retenues` garde (jour, plage, commande, échéance). Le site compte une
  retenue non échue comme une place prise ; la gestion ne la compte pas (elle ne voit que les
  commandes confirmées). À l'échéance (30 minutes, paramètre `paiement_retenue_minutes`), si le
  lien n'est ni payé ni en cours (`processing`), la gestion le désactive (`PATCH
  /v2/payment_links/{id}/deactivate`), efface la retenue et marque la commande « paiement non
  abouti ». Un paiement constaté après l'échéance est accepté : la commande est confirmée et la plage
  peut passer « Dépassée » (cas signalé à Youngmi).
- **Rationale** : la durée de vie par défaut d'un lien n'est pas documentée et ne se règle pas ;
  la désactivation ferme la porte. SC-004 est tenu tant que les paiements arrivent dans le délai.
- **Alternatives considered** : prendre la place seulement au paiement (deux clients pourraient
  payer la dernière place) ; confirmer la commande avant le paiement (une place serait prise sans
  paiement).

## R5. Droits demandés à Qonto et exceptions au rulebook

- **Decision** : l'appli OAuth « Gestion Madame Cho » ajoute les portées `payment_link.read`,
  `payment_link.write` et `webhook`. Youngmi relie de nouveau Qonto depuis Paramètres › Qonto ; la
  gestion n'appelle que les cinq chemins des liens et de l'abonnement (liste fermée dans le code).
  `payment_link.write` permet aussi de connecter Mollie : la gestion ne l'appelle jamais ;
  l'activation se fait par Youngmi dans Qonto (« Compte professionnel › Liens de paiement »).
- **Rationale** : exception à R-24 limitée aux liens (décision d'Olivier du 2026-11-21), et rien
  d'autre ; ni virement, ni carte, ni facture.
- **Alternatives considered** : clé API simple (elle permet des virements : refusée depuis le
  2026-11-13).

## R6. Frais et versements

- **Decision** : l'API ne donne ni les frais ni les versements. La gestion estime les frais par
  paiement selon le barème des Paramètres (1,4 % + 0,25 € par défaut, étiquette « estimés ») ; le
  rapprochement automatique reconnaît les versements de Mollie sur le compte Qonto (libellé du
  partenaire) et les rattache en bloc aux paiements en ligne de la période, frais réels = payé moins
  versé. La réserve glissante de Mollie (environ 20 % des paiements carte, rendue après 60 à 180
  jours) est dite dans l'aide de la gestion pour que Youngmi ne cherche pas l'argent qui manque.
- **Rationale** : FR-011 et FR-012 sans donnée inventée.
- **Alternatives considered** : lire le tableau de bord de Mollie (pas d'accès, et ce serait un
  service de plus).

## R7. Remboursements

- **Decision** : aucun remboursement par l'API (non documenté) : Youngmi rembourse dans le tableau
  de bord de Mollie (« Transactions › Payments › le paiement › Create refund ») ; la gestion affiche
  « À rembourser » avec le montant entier et ces étapes, et Youngmi note « Remboursé ».
- **Rationale** : FR-016 à FR-018 ; aucune écriture d'argent par le code (R-24).

## R8. Mails automatiques : reçu et confirmation

- **Decision** : le reçu (et, au temps 2, la confirmation) part par le même chemin que l'accusé de
  réception (`accuse.php`, signé DKIM, copie à contact@, rangé dans les Échanges), au moment où le
  paiement est constaté, une seule fois par paiement (champ `recu_envoye_le` posé dans la même
  transaction que l'état payé).
- **Rationale** : exception à R-01 décidée le 2026-11-27, comme l'accusé de réception.

## R9. Prix et TVA

- **Decision** : au temps 2, le serveur recalcule chaque prix depuis la carte (comme
  `envoyer.php` le fait déjà) ; le taux de TVA vient de la fiche produit de la gestion (`tva`, 5,5 %
  par défaut). Au temps 1, le montant est le prix final de la commande moins ce qui est déjà reçu ;
  une seule ligne « Commande MC-… » au taux de TVA dominant des lignes si le prix final diffère de
  la somme des lignes.
- **Rationale** : jamais un prix venu du navigateur ; jamais un montant différent de celui de la
  commande.

## R10. Essais

- **Decision** : trois niveaux. (1) Un faux Qonto local (petit serveur PHP qui répond comme l'API et
  envoie des notifications signées) pour tous les essais automatiques. (2) Le bac à sable de Qonto
  (`thirdparty-sandbox.staging.qonto.co`, en-tête `X-Qonto-Staging-Token`, carte d'essai, état
  simulé « paid ») si Olivier y crée un accès ; les secrets restent dans son Bitwarden et sur le
  serveur, jamais dans le dépôt ni dans la conversation. (3) Après publication, interrupteurs
  éteints, un paiement réel de 1 € par Olivier sur une commande d'essai, puis remboursement par
  Youngmi dans Mollie.
- **Rationale** : principe VII (on prouve) sans donnée réelle ni secret dans le dépôt.

## R11. Ce qui reste hors de cette version

- PayPal, iDEAL, paiement des professionnels, factures payables en ligne, remboursement par l'API,
  paiement d'une livraison : non.
