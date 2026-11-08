# Checklist : rappel de retrait

**Purpose** : vérifier que FR-040 est clair, borné et testé avant le feu vert.
**Created** : 2026-11-08
**Feature** : [spec.md](../spec.md)

## Clarté des exigences

- [x] CHK001 FR-040 dit quelles commandes reçoivent un rappel (particulier, confirmée, retrait demain) et lesquelles non [Spec §FR-040]
- [x] CHK002 FR-040 dit qui envoie (toucher de « Envoyer », R-01) et que l'automatique est hors périmètre [Spec §FR-040, Clarifications 2026-11-08]
- [x] CHK003 Le cas du client sans email (SMS, WhatsApp) est couvert [Spec §FR-040]
- [x] CHK004 Le cas de la confirmation envoyée la veille (le rappel ferait doublon) est couvert [Spec §FR-040]
- [ ] CHK005 Le texte du rappel est validé par Olivier (puis Youngmi pendant le pilote)

## Mesurabilité

- [x] CHK006 SC-013 (pas de double rappel, pas de rappel d'une commande annulée ou remise) se vérifie sur base fictive

## Cas limites

- [x] CHK007 Livraison de restaurant exclue
- [x] CHK008 Commande déplacée après un rappel : elle revient dans la liste, marquée « Retrait modifié » (audit n° 4, A02)
- [ ] CHK009 Retrait un lundi et dimanche fermé : à revoir avec Youngmi pendant le pilote (rappel le samedi ?)

## Audit n° 4 (2026-11-08)

- [x] CHK010 A01 à A05, A07, A09 à A11 corrigés et essayés (30 + 14 vérifications sur base fictive)
- [ ] CHK011 A06 : bouton WhatsApp retiré pour le pilote, à confirmer par Olivier
- [ ] CHK012 A08 : « Tous les échanges » au-delà de 200 événements (T057)
