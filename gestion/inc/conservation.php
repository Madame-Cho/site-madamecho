<?php
/*
 * Madame Cho : tâche de nuit de l'espace de gestion, lancée par ma-demande/menage.php (tâche planifiée OVH, chaque nuit vers 4 h).
 * Applique les durées de conservation de la politique de confidentialité ; ne crée jamais la base si l'espace n'est pas encore ouvert.
 */
declare(strict_types=1);
namespace MadameCho\Gestion;

require_once __DIR__ . '/base.php';
require_once __DIR__ . '/modele.php';
require_once __DIR__ . '/reponses.php';   // reponses_purger() : sans lui, la tâche de nuit s'arrêtait en chemin (trouvé le 08/11/2026)

function tache_de_nuit(): array {
    if (!gestion_base_existante()) return [];
    $cr = conservation_executer('tâche de nuit');
    noms_majuscules_reprise();   // une seule fois : majuscules oubliées dans les noms déjà enregistrés (demande d'Olivier du 08/11/2026)
    parametre_ecrire('derniere_nuit', maintenant());
    return $cr;
}
