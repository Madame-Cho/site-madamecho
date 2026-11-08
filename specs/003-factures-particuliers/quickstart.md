# Quickstart : rejouer les essais des factures

1. Régénérer : `python3 -I -B .sources/gen.py . --en-ligne` (ou `tests-gestion/regen.sh`).
2. Base d'essai : copier `tests-gestion/assistant/base-essai.sqlite` vers `~/.madamecho-donnees/gestion.sqlite`
   (supprimer les fichiers `-wal` et `-shm`).
3. Serveur : `MC_ESSAI=1 php -S 127.0.0.1:8767 -t <dépôt>` (les mails restent dans la session).
4. `php tests-gestion/factures/prep.php` puis `php tests-gestion/factures/unitaires.php` (numéros sous
   20 émissions simultanées, chaîne, altération détectée, PDF valide) et
   `node tests-gestion/factures/test.js` (parcours Youngmi FR/KO, captures, téléphone).
