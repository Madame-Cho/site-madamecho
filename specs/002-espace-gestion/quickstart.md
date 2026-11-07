# Quickstart : rejouer les essais du lot « audit n° 2 »

Données fictives seulement (principe I). Scripts gardés hors du dépôt en attendant AM-42.

1. Régénérer le site : `python3 -I -B .sources/gen.py . --en-ligne`.
2. Copier la base d'essai `assistant/base-essai.sqlite` en `../.madamecho-donnees/gestion.sqlite`.
3. Lancer la fausse API, le faux Matomo et le serveur d'essai (`MC_ESSAI=1`), comme décrit dans le
   README des scripts d'essai.
4. `node assistant/donnees.js <dossier>` : 97 vérifications (assistant, propositions, mode vocal).
5. `MC_ESSAI=1 php audit2/corrections.php <dépôt>` : 15 vérifications (C1, C3, C8).
6. `node audit2/parcours.js` : 11 vérifications dans le navigateur (C1 carte, C2 prix gardés, C3 refus
   puis confirmation).

Résultat attendu : « TOUT EST BON » pour chacun.
