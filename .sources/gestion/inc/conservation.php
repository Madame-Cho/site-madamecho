<?php
/*
 * Madame Cho : tâche de nuit de l'espace de gestion, lancée par ma-demande/menage.php (tâche planifiée OVH, chaque nuit vers 4 h).
 * Applique les durées de conservation de la politique de confidentialité ; ne crée jamais la base si l'espace n'est pas encore ouvert.
 */
declare(strict_types=1);
namespace MadameCho\Gestion;

require_once __DIR__ . '/base.php';
require_once __DIR__ . '/modele.php';

function tache_de_nuit(): array {
    if (!gestion_base_existante()) return [];
    $cr = conservation_executer('tâche de nuit');
    parametre_ecrire('derniere_nuit', maintenant());
    return $cr;
}
