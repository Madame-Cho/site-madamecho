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
- [x] CHK008 Commande déplacée après un rappel : le rappel peut être renvoyé (« Renvoyer »)
- [ ] CHK009 Retrait un lundi et dimanche fermé : à revoir avec Youngmi pendant le pilote (rappel le samedi ?)
