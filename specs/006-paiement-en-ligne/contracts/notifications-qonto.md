# Contrat : notifications de Qonto (`gestion/?p=qonto-notification`)

## Entrée

`POST https://madamecho.com/gestion/?p=qonto-notification`, sans session, corps JSON de Qonto,
type `v1/payment-links` (`payment_links.created`, `payment_links.updated`), en-tête
`X-Qonto-Signature: t=<horodatage>,v1=<hmac>`.

## Contrôles, dans l'ordre

1. Méthode POST, corps de 64 Kio au plus, sinon `400`.
2. Signature : HMAC-SHA256 de `<t>.<corps brut>` avec le secret de l'abonnement
   (`qonto_notification_secret`), comparé en temps constant à `v1` ; sinon `401`, rien n'est lu.
3. Horodatage à moins de 10 minutes de l'heure du serveur ; sinon `401`.
4. `organization_id` = l'espace Qonto relié ; `data.payment_link_id` connu dans `liens_paiement` ;
   sinon `200` sans effet (Qonto ne réessaie pas, rien n'est écrit).
5. Réponse `200` immédiate ; puis relecture du lien et de ses paiements chez Qonto ; seul ce que dit
   la relecture est noté (jamais le corps de la notification seul).

## Effets possibles

- Lien relu `paid` avec un paiement `paid` du bon montant : effets « paiement constaté » (voir
  [site-commander-et-payer.md](./site-commander-et-payer.md)), une seule fois grâce à
  `paiement_qonto_id` unique.
- Montant payé différent du montant du lien : rien n'est noté payé ; la commande reçoit
  « Paiement à vérifier » et une pastille.
- `expired` ou `canceled` : état noté ; au temps 2, la commande devient « paiement non abouti ».

## Journal

Chaque notification acceptée ou refusée laisse une ligne courte dans le journal d'erreurs du serveur
(type, résultat, identifiant du lien), sans le corps ni aucune donnée personnelle.

## Abonnement

Créé par la gestion (Paramètres › Paiement en ligne › « Activer le paiement en ligne »), au toucher
de Youngmi, après la nouvelle liaison : `POST /v2/webhook_subscriptions` avec un corps à plat
(`callback_url`, `types = ["v1/payment-links"]`, `secret`, `description`) et un secret de 64 caractères
tiré au hasard par le serveur. Le secret est gardé dans `parametres`, jamais montré, jamais exporté.
« Couper le paiement en ligne » retire l'abonnement (`DELETE /v2/webhook_subscriptions/{id}`) et
éteint les deux interrupteurs ; les liens déjà envoyés restent relus chaque heure par la tâche
planifiée jusqu'à leur échéance.
