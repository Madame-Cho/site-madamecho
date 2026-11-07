# Checklist : lot « échanges avec les clients » et retours du parcours de test

**Purpose** : vérifier que les exigences du lot sont écrites clairement et testées avant le feu vert.
**Created** : 2026-11-07
**Feature** : [spec.md](../spec.md)

## Clarté des exigences

- [x] CHK001 FR-005 dit qui envoie, depuis quelle adresse, et que rien ne part avant « Envoyer » [Spec §FR-005]
- [x] CHK002 FR-031 dit ce que le script lit, ce qu'il transmet et ce qu'il ne peut pas faire [Spec §FR-031]
- [x] CHK003 FR-032 fixe l'ordre (plus récent en haut) et le repère du nouveau [Spec §FR-032]
- [x] CHK004 FR-002 borne la suppression (essai, ni facture, ni Qonto, ni paiement reçu) [Spec §FR-002]
- [ ] CHK005 L'exception de FR-002 est confirmée par Olivier (T034)

## Mesurabilité

- [x] CHK006 SC-011 (moins de 2 minutes) mesuré sur un vrai mail le 2026-11-07
- [x] CHK007 SC-012 tenu par construction (commande_supprimable) et vérifié par un essai

## Cas limites

- [x] CHK008 Mail envoyé depuis Gmail : rangé comme « Mail envoyé depuis Gmail », pas comme une réponse
- [x] CHK009 Copie Cci de la gestion ignorée ; mail sans référence connue : orphelin 30 jours
- [x] CHK010 Mail récent envoyé après une réponse non lue : la réponse reste repérée par le trait « Nouveaux messages »
- [ ] CHK011 Mail « Merci » et avis en réel (T036, après la fiche Google)
- [ ] CHK012 L'heure choisie à la confirmation est bien celle enregistrée (09 h au lieu de 10 h vu une fois en ligne, pas reproduit en essai : à revérifier après publication)

## Langue, téléphone et constitution

- [x] CHK013 Coréen complet (263 pages, 0 texte en français)
- [x] CHK014 Zones à toucher d'au moins 44 px pour les nouveaux boutons [Principe VI]
- [ ] CHK015 Page Confidentialité du site complétée (T035) [Principe IV]
- [ ] CHK016 Relecture coréenne humaine des nouveaux messages (T-23)
