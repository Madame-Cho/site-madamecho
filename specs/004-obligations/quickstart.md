# Quickstart : rejouer les essais du lot 1 « Obligations »

1. Régénérer : `python3 -I -B .sources/gen.py . --en-ligne` (ou `tests-gestion/regen.sh`).
2. Base d'essai : copier `tests-gestion/assistant/base-essai.sqlite` vers `~/.madamecho-donnees/gestion.sqlite`
   (supprimer les fichiers `-wal` et `-shm`) ; la migration crée les trois tables.
3. Serveur : `MC_ESSAI=1 php -S 127.0.0.1:8767 -t <dépôt>`.
4. `php tests-gestion/obligations/prep.php` (démarches fictives : une en retard, une à 15 jours, une sans
   date, une d'Olivier, une règle annuelle à 20 jours, un restaurant fictif à sa 2e commande), puis
   `php tests-gestion/obligations/unitaires.php` (couleurs, ordre, règle qui avance d'un an, déclencheur
   unique, reprise sans doublon, migration depuis 10 et depuis 11) et `node tests-gestion/obligations/test.js`
   (parcours Youngmi et Olivier FR/KO, pastilles, captures, téléphone 390 px).
