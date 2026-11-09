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
require_once __DIR__ . '/vues.php';    // le mail d'accusé de réception (mail_commande), au même dessin que les mails de la gestion
require_once __DIR__ . '/email.php';

/**
 * $d : ref, prenom, nom, email, telephone, date (AAAA-MM-JJ), moment, lignes ([p, c, q, prix (total de la ligne), plaque]),
 * perso, pro (bool), societe, besoin, via (facultatif : campagne Matomo de la visite, « qr-code packaging » après un scan du QR code).
 * Renvoie le numéro interne de la commande (0 : rien d'enregistré).
 * Ne fait rien tant que l'espace n'est pas ouvert, ni avant que l'enregistrement soit activé dans Paramètres (mise en ligne en deux temps).
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
 * Accusé de réception au client (demande d'Olivier du 09/11/2026) : dès qu'une demande du formulaire est partie à contact@, un mail court
 * dit au client qu'elle est bien arrivée et qu'il sera recontacté très rapidement. Seul mail qui part sans le toucher de Youngmi
 * (exception à R-01 décidée par Olivier) : un texte fixe, sans prix ni décision, écrit à l'avance et jamais par une IA.
 * Garde-fous contre l'envoi à l'adresse de quelqu'un d'autre : rien de ce que le visiteur a écrit n'y figure sauf un prénom fait de lettres,
 * un seul accusé par adresse et par 24 heures (empreinte de l'adresse avec le sel du serveur, effacée après 24 heures), et la limite de
 * 5 demandes par heure du formulaire. Arrêtable dans Paramètres › Demandes du site. Pas de copie à contact@, qui a déjà la demande.
 * $d : ref, prenom, nom, email (comme enregistrer_demande) ; $commande : son numéro interne (0 si la demande n'est pas enregistrée).
 * Renvoie 'envoye', 'arrete', 'deja' (même adresse il y a moins de 24 heures) ou 'echec'.
 */
function accuser_reception(array $d, int $commande = 0): string {
    if (!gestion_base_existante() || parametre('accuse_reception', '1') !== '1') return 'arrete';
    $email = mb_strtolower(trim((string)($d['email'] ?? '')));
    $ref = (string)($d['ref'] ?? '');
    if (!email_valide($email) || !preg_match('/^MC-\d{6}-[0-9A-F]{4}$/', $ref)) return 'echec';
    // Une fois par adresse et par 24 heures : la ligne est prise avant l'envoi (deux demandes à la fois n'en envoient qu'un)
    q("DELETE FROM parametres WHERE cle LIKE 'accuse:%' AND CAST(valeur AS INTEGER) < ?", [time() - 86400]);
    $cle = 'accuse:' . substr(hash_hmac('sha256', $email, sel()), 0, 32);
    $st = db()->prepare("INSERT OR IGNORE INTO parametres (cle, valeur) VALUES (?, ?)");
    $st->execute([$cle, (string)time()]);
    if ($st->rowCount() === 0) return 'deja';
    // Le prénom, seulement s'il est fait de lettres (pas de lien ni de chiffres glissés dans « Bonjour … ») ; sinon « Bonjour, »
    [$prenom, $nom] = isset($d['prenom']) ? [(string)$d['prenom'], (string)$d['nom']] : prenom_nom((string)($d['nom'] ?? ''));
    $prenom = trim($prenom);
    if (!preg_match("/^\p{L}[\p{L}\p{M}'’ -]{0,29}$/u", $prenom)) $prenom = '';
    $k = ['reference' => $ref, 'client_prenom' => $prenom, 'client_nom' => '', 'client_type' => 'particulier', 'client_contact' => ''];
    $m = mail_commande($k, 'recu');
    $mid = envoyer_email_client($email, $m['sujet'], $m['corps'], $m['html'], [], false);
    if ($mid === '') { q("DELETE FROM parametres WHERE cle = ?", [$cle]); return 'echec'; }
    // Rangé dans les Échanges de la commande (et son Message-ID gardé : la proposition suivra dans la même conversation chez le client).
    // Ni « dernier échange » ni pastille de réponse : la demande reste « à répondre » pour Youngmi.
    if ($commande > 0 && ($cid = val("SELECT client_id FROM commandes WHERE id = ?", [$commande]))) {
        evenement('email_client', 'Accusé de réception envoyé automatiquement à ' . $email . ' : « ' . $m['sujet'] . ' »', ['commande' => $commande, 'client' => (int)$cid],
                  'type:recu mid:' . $mid, $m['corps'], 'site');
    }
    return 'envoye';
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
