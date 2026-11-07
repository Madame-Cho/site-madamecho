<?php
/*
 * Madame Cho : enregistrement d'une demande du formulaire du site dans l'espace de gestion.
 * Appelé par ma-demande/envoyer.php APRÈS l'envoi de l'email à contact@ (même référence MC-…) :
 * si l'enregistrement échoue, l'email est parti quand même et le visiteur ne voit aucune différence.
 * Le formulaire y demande aussi, avant l'envoi, si le jour et le moment choisis tombent sur une indispo de l'agenda.
 * Aucun cookie, aucune session : le visiteur du site n'a pas de compte (GESTION_CONTEXTE_SITE).
 */
declare(strict_types=1);
namespace MadameCho\Gestion;

if (!defined('GESTION_CONTEXTE_SITE')) define('GESTION_CONTEXTE_SITE', true);
require_once __DIR__ . '/base.php';
require_once __DIR__ . '/modele.php';

/**
 * $d : ref, prenom, nom, email, telephone, date (AAAA-MM-JJ), moment, lignes ([p, c, q, prix (total de la ligne), plaque]),
 * perso, pro (bool), societe, besoin, via (facultatif : campagne Matomo de la visite, « qr-code packaging » après un scan du QR code).
 * Renvoie le numéro interne de la commande (0 : rien d'enregistré).
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
        // Provenance (07/11/2026) : après un scan du QR code d'un emballage, le site transmet la campagne Matomo « qr-code » ; la demande
        // (et la fiche d'un nouveau client) est alors « reçue par » le QR code, ce qui la compte dans « D'où viennent les commandes »
        $via = is_string($d['via'] ?? null) ? trim($d['via']) : '';
        $qr = str_starts_with($via, 'qr-code');
        $canal = $qr ? 'qr' : 'site';
        $support = $qr ? trim(substr($via, 7)) : '';
        $cid = client_trouver_ou_creer((string)$d['email'], $prenom, $nom, (string)$d['telephone'], $canal, (bool)$d['pro'], (string)$d['societe'], $quand);
        $lignes = [];
        foreach ($d['lignes'] as $l) {
            $q = max(1, (int)$l['q']);
            $lignes[] = ['v' => $l['p'] . '|' . $l['c'], 'q' => $q, 'prix' => intdiv((int)$l['prix'], $q), 'plaque' => (string)($l['plaque'] ?? '')];
        }
        $type = $d['pro'] ? 'pro' : (($d['perso'] !== '' || !$lignes) ? 'perso' : 'carte');
        $id = commande_creer(['reference' => $d['ref'], 'client_id' => $cid, 'canal' => $canal, 'type' => $type,
                              'retrait_date' => $d['date'], 'retrait_moment' => $d['moment'], 'lignes' => $lignes,
                              'perso' => (string)$d['perso'], 'besoin' => (string)$d['besoin'], 'recue_le' => $quand,
                              'origine' => 'Demande envoyée par le formulaire du site' . ($qr ? ' après un scan du QR code' . ($support !== '' ? ' (' . $support . ')' : '') : ''), 'auteur' => 'site']);
        $pdo->commit();
        return $id;
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

/**
 * Le retrait demandé sur le site tombe-t-il sur une indispo de l'agenda (demande d'Olivier du 07/11/2026) ? Renvoie la phrase pour le
 * visiteur, ou '' si ce jour et ce moment sont libres. Jamais le motif ni les heures de l'indispo : le jour et le moment choisis, les autres
 * moments encore libres ce jour-là, ou les jours d'une indispo d'une journée entière. Une plage ferme tout le moment qu'elle touche, comme
 * pour les commandes de la gestion (indispo_de_commande). Rien tant que l'espace de gestion n'est pas ouvert : la base n'est jamais créée ici.
 */
function indispo_pour_demande(string $date, string $moment): string {
    if (!date_valide($date) || !isset(MOMENTS_PLAGES[$moment]) || !gestion_base_existante()) return '';
    $i = indispo_de_commande(['statut' => 'demande', 'retrait_date' => $date, 'retrait_heure' => '', 'retrait_moment' => $moment]);
    if (!$i) return '';
    $duJour = indispos_entre($date, $date);
    $libres = array_keys(array_filter(MOMENTS_PLAGES, function (array $p) use ($duJour): bool {
        foreach ($duJour as $x) if (indispo_journee($x) || ($x['heure_debut'] < $p[1] && $x['heure_fin'] > $p[0])) return false;
        return true;
    }));
    if (!$libres) {
        $de = max($i['debut'], date('Y-m-d'));   // une indispo déjà commencée : à partir d'aujourd'hui
        $jours = (indispo_journee($i) && $de < $i['fin']) ? 'du ' . date_fr($de, 'jour') . ' au ' . date_fr($i['fin'], 'jour') : 'le ' . date_fr($date, 'jour');
        return 'Madame Cho n\'est pas disponible ' . $jours . ' : choisissez une autre date.';
    }
    $pas = ['matin' => 'le matin', 'midi' => 'à midi', 'apres-midi' => 'l\'après-midi', 'soir' => 'le soir'];
    $plutot = array_map(fn(string $m): string => ['matin' => 'le matin', 'midi' => 'midi', 'apres-midi' => 'l\'après-midi', 'soir' => 'le soir'][$m], $libres);
    $choix = count($plutot) > 1 ? implode(', ', array_slice($plutot, 0, -1)) . ' ou ' . end($plutot) : $plutot[0];
    return 'Le ' . date_fr($date, 'jour') . ', Madame Cho n\'est pas disponible ' . $pas[$moment] . ' : choisissez plutôt ' . $choix . ', ou une autre date.';
}
