<?php
/*
 * Madame Cho : enregistrement d'une demande du formulaire du site dans l'espace de gestion.
 * Appelé par ma-demande/envoyer.php APRÈS l'envoi de l'email à contact@ (même référence MC-…) :
 * si l'enregistrement échoue, l'email est parti quand même et le visiteur ne voit aucune différence.
 * Aucun cookie, aucune session : le visiteur du site n'a pas de compte (GESTION_CONTEXTE_SITE).
 */
declare(strict_types=1);
namespace MadameCho\Gestion;

if (!defined('GESTION_CONTEXTE_SITE')) define('GESTION_CONTEXTE_SITE', true);
require_once __DIR__ . '/base.php';
require_once __DIR__ . '/modele.php';

/**
 * $d : ref, prenom, nom, email, telephone, date (AAAA-MM-JJ), moment, lignes ([p, c, q, prix (total de la ligne), plaque]),
 * perso, pro (bool), societe, besoin. Renvoie le numéro interne de la commande (0 : rien d'enregistré).
 * Ne fait rien tant que l'espace n'est pas ouvert, ni avant que l'enregistrement soit activé dans Réglages (mise en ligne en deux temps).
 */
function enregistrer_demande(array $d): int {
    if (!gestion_base_existante() || parametre('demandes_site') !== '1') return 0;
    $pdo = db();
    if (val("SELECT id FROM commandes WHERE reference = ?", [$d['ref']])) return (int)val("SELECT id FROM commandes WHERE reference = ?", [$d['ref']]);
    $quand = maintenant();
    $pdo->beginTransaction();
    try {
        // Prénom et nom séparés depuis le 07/11/2026 ; une page du formulaire restée ouverte avant envoie encore « Prénom et nom » en un seul champ
        [$prenom, $nom] = isset($d['prenom']) ? [(string)$d['prenom'], (string)$d['nom']] : prenom_nom((string)$d['nom']);
        $cid = client_trouver_ou_creer((string)$d['email'], $prenom, $nom, (string)$d['telephone'], 'site', (bool)$d['pro'], (string)$d['societe'], $quand);
        $lignes = [];
        foreach ($d['lignes'] as $l) {
            $q = max(1, (int)$l['q']);
            $lignes[] = ['v' => $l['p'] . '|' . $l['c'], 'q' => $q, 'prix' => intdiv((int)$l['prix'], $q), 'plaque' => (string)($l['plaque'] ?? '')];
        }
        $type = $d['pro'] ? 'pro' : (($d['perso'] !== '' || !$lignes) ? 'perso' : 'carte');
        $id = commande_creer(['reference' => $d['ref'], 'client_id' => $cid, 'canal' => 'site', 'type' => $type,
                              'retrait_date' => $d['date'], 'retrait_moment' => $d['moment'], 'lignes' => $lignes,
                              'perso' => (string)$d['perso'], 'besoin' => (string)$d['besoin'], 'recue_le' => $quand,
                              'origine' => 'Demande envoyée par le formulaire du site', 'auteur' => 'site']);
        $pdo->commit();
        return $id;
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}
