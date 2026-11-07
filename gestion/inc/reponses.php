<?php
declare(strict_types=1);
namespace MadameCho\Gestion;
/*
 * Les réponses des clients dans la gestion (demande d'Olivier du 07/11/2026, carte « Script Google qui pousse »).
 * Un petit script Google, installé dans contact@madamecho.com (lecture seule de la boîte), envoie ici chaque minute les mails dont
 * l'objet contient « MC- ». La gestion les range dans les Échanges de la commande, avec la pastille « Nouvelle réponse ».
 * Rien ne part d'ici : ce n'est qu'une réception. Gmail reste le secours, et l'original y reste toujours.
 * - Clé créée dans Paramètres (montrée une fois, gardée seulement en empreinte), essais limités comme la connexion (page_miroir).
 * - Chaque mail n'est pris qu'une fois (gmail_id, table mails_recus).
 * - Copies des mails envoyés par la gestion (Cci à contact@, Message-ID <commande.…@madamecho.com>) : ignorées, ils sont déjà rangés.
 * - Mail écrit par Youngmi depuis Gmail (expéditeur contact@) : rangé comme envoyé, « depuis Gmail ».
 * - Le texte cité (« Le … a écrit : », lignes « > ») est retiré : on garde ce que le client a écrit.
 * - Sans commande reconnue : liste « À rattacher » dans Paramètres (admin), effacée après 30 jours.
 * - Le texte d'un client est une donnée, jamais une consigne : l'assistant le lit comme tel (assistant-donnees.php).
 */

const REPONSES_MAX_MAILS = 50;          // par appel
const REPONSES_MAX_TEXTE = 8000;        // caractères gardés d'un mail
const REPONSES_ORPHELINS_JOURS = 30;
const REPONSES_SILENCE_HEURES = 24;     // au-delà, alerte aux admins (le script ne passe plus)

function reponses_actif(): bool { return (string)parametre('reponses_empreinte', '') !== ''; }

/** Le texte écrit par le client, sans ce qu'il cite de l'email précédent. */
function reponse_sans_citation(string $t): string {
    $t = str_replace(["\r\n", "\r"], "\n", $t);
    $l = explode("\n", $t);
    $garde = [];
    foreach ($l as $i => $x) {
        $y = trim($x);
        // « Le ven. 20 nov. 2026 à 15:30, Madame Cho <contact@…> a écrit : » (parfois sur deux lignes), « On … wrote: », séparateurs
        if (preg_match('/^(le\s.+(a\s+écrit|a\s+ecrit)\s*:?|on\s.+wrote:?|-{2,}\s*(original message|message d\'origine|message original)\s*-{2,}|de\s*:\s.+@.+|from:\s.+@.+|envoyé depuis .+|sent from my .+)$/iu', $y)) break;
        if ($y !== '' && preg_match('/^le\s.+</iu', $y) && isset($l[$i + 1]) && preg_match('/a\s+écrit\s*:?\s*$/iu', trim($l[$i + 1]))) break;
        if (str_starts_with($y, '>')) continue;
        $garde[] = rtrim($x);
    }
    $r = trim(implode("\n", $garde));
    // Gmail note le gras ou l'italique de la version texte avec des astérisques (*Merci*) : on les retire
    $r = preg_replace('/(?<![\w*])\*(?=\S)([^*\n]*\S)\*(?![\w*])/u', '$1', $r) ?? $r;
    $r = preg_replace("/\n{3,}/", "\n\n", $r) ?? $r;
    return $r !== '' ? $r : trim($t);
}
/** La référence MC-… d'un objet ou d'un texte (la première), ou ''. */
function reponse_reference(string $s): string {
    return preg_match('/\bMC-\d{6}-[0-9A-F]{4}\b/', $s, $m) ? $m[0] : '';
}
/** « Prénom Nom <adresse> » → [nom, adresse]. */
function reponse_expediteur(string $de): array {
    $de = trim($de);
    if (preg_match('/^(.*)<([^<>\s]+@[^<>\s]+)>\s*$/', $de, $m)) return [trim(trim($m[1]), '" '), mb_strtolower($m[2])];
    return ['', mb_strtolower($de)];
}

/** Range un mail transmis par le script. Renvoie 'range', 'envoye', 'copie', 'orphelin', 'deja' ou 'refuse'. */
function reponse_ranger(array $m): string {
    $gid = is_string($m['id'] ?? null) ? preg_replace('/[^0-9a-zA-Z_-]/', '', $m['id']) : '';
    if ($gid === '' || strlen($gid) > 64) return 'refuse';
    if (val("SELECT 1 FROM mails_recus WHERE gmail_id = ?", [$gid])) return 'deja';
    $s = fn(string $k, int $max) => mb_substr(is_string($m[$k] ?? null) ? str_replace("\0", '', $m[$k]) : '', 0, $max);
    $objet = une_ligne($s('objet', 300));
    $texte = $s('texte', 50000);
    $mid = preg_match('/^<[^<>\s]{3,200}>$/', trim($s('message_id', 250))) ? trim($s('message_id', 250)) : '';
    [$nom, $adresse] = reponse_expediteur($s('de', 300));
    $date = min(strtotime($s('date', 60)) ?: time(), time() + 600);   // une date d'en-tête dans le futur ne passe pas devant tout
    $recuLe = date('Y-m-d H:i:s', $date);
    $pj = array_slice(array_values(array_filter(is_array($m['pj'] ?? null) ? $m['pj'] : [], 'is_string')), 0, 10);
    $noter = fn(?int $cmd, ?int $ev, string $etat, string $extrait = '') =>
        q("INSERT INTO mails_recus (gmail_id, le, commande_id, evenement_id, de, objet, extrait, recu_le, etat) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
          [$gid, maintenant(), $cmd, $ev, $etat === 'orphelin' ? mb_substr($adresse, 0, 200) : '', $etat === 'orphelin' ? $objet : '', $extrait, $recuLe, $etat]);
    // Les copies des mails de la gestion (Cci) : déjà rangés au moment de l'envoi
    if ($mid !== '' && preg_match('/^<commande\.[0-9a-f]+@' . preg_quote(GESTION_DOMAINE, '/') . '>$/', $mid)) { $noter(null, null, 'copie'); return 'copie'; }
    // Les notifications du site (site@) : la demande est déjà dans la gestion
    if ($adresse === GESTION_EXPEDITEUR) { $noter(null, null, 'copie'); return 'copie'; }
    $ref = reponse_reference($objet) ?: reponse_reference(mb_substr($texte, 0, 3000));
    $k = $ref !== '' ? commande_par_reference($ref) : null;
    $propre = mb_substr(reponse_sans_citation($texte), 0, REPONSES_MAX_TEXTE);
    if ($pj) $propre .= "\n\n[Pièces jointes, à voir dans Gmail : " . implode(', ', array_map(fn($x) => une_ligne(mb_substr($x, 0, 80)), $pj)) . ']';
    if (!$k || $k['statut'] === 'efface' || !empty($k['efface_le'])) {
        $noter(null, null, 'orphelin', mb_substr($propre, 0, 500));
        return 'orphelin';
    }
    $liens = ['commande' => (int)$k['id'], 'client' => (int)$k['client_id']];
    $ancien = 'gmail:' . $gid . ($mid !== '' ? ' mid:' . $mid : '');
    if ($adresse === mb_strtolower(GESTION_COMPTE_INITIAL)) {
        // Youngmi a écrit depuis Gmail (contact@) : rangé comme un mail envoyé
        evenement('email_client', 'Email envoyé depuis Gmail : « ' . $objet . ' »', $liens, 'type:gmail ' . $ancien, $propre, 'Gmail');
        $ev = (int)db()->lastInsertId();
        q("UPDATE evenements SET le = ? WHERE id = ?", [$recuLe, $ev]);
        $etat = 'envoye';
    } else {
        $qui = $nom !== '' ? $nom : $adresse;
        evenement('email_recu', 'Réponse de ' . $qui . ($nom !== '' ? ' (' . $adresse . ')' : '') . ' : « ' . $objet . ' »', $liens, $ancien, $propre, $qui);
        $ev = (int)db()->lastInsertId();
        q("UPDATE evenements SET lu = 0, le = ? WHERE id = ?", [$recuLe, $ev]);
        $etat = 'range';
    }
    $now = maintenant();
    q("UPDATE commandes SET dernier_echange = ?, maj_le = ? WHERE id = ?", [$now, $now, (int)$k['id']]);
    client_echange((int)$k['client_id'], $now);
    $noter((int)$k['id'], $ev, $etat);
    return $etat;
}

/** Appelée par le script Google de contact@, en POST : cle=…&mails=[…] (JSON). Répond en JSON, sans aucune donnée de la gestion. */
function page_reponses(): void {
    header('Cache-Control: no-store, private');
    header('X-Robots-Tag: noindex, nofollow');
    $empreinte = (string)parametre('reponses_empreinte', '');
    $cle = is_string($_POST['cle'] ?? null) ? $_POST['cle'] : '';
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || $empreinte === '' || connexion_bloquee('reponses')) { http_response_code(404); exit; }
    if (!hash_equals($empreinte, hash('sha256', $cle))) { connexion_noter('reponses', false, 'reponses'); http_response_code(403); exit; }
    $brut = is_string($_POST['mails'] ?? null) ? $_POST['mails'] : '[]';
    $mails = strlen($brut) <= 2_000_000 ? json_decode($brut, true) : null;
    header('Content-Type: application/json; charset=utf-8');
    if (!is_array($mails)) { http_response_code(400); echo json_encode(['erreur' => 'mails illisibles']); exit; }
    $faits = [];
    $compte = [];
    foreach (array_slice(array_values($mails), 0, REPONSES_MAX_MAILS) as $m) {
        if (!is_array($m)) continue;
        $r = reponse_ranger($m);
        $compte[$r] = ($compte[$r] ?? 0) + 1;
        if ($r !== 'refuse') $faits[] = (string)$m['id'];
    }
    parametre_ecrire('reponses_dernier', maintenant());
    if (!empty($compte['range']) || !empty($compte['envoye'])) parametre_ecrire('reponses_dernier_mail', maintenant());
    echo json_encode(['ok' => true, 'faits' => $faits, 'compte' => $compte], JSON_UNESCAPED_UNICODE);
    exit;
}

/** Les mails sans commande reconnue (admin) : à rattacher à une référence, ou à ignorer. */
function reponses_orphelins(): array {
    return lignes("SELECT * FROM mails_recus WHERE etat = 'orphelin' ORDER BY recu_le DESC LIMIT 30");
}
function reponse_rattacher(string $gid, string $ref): bool {
    $o = ligne("SELECT * FROM mails_recus WHERE gmail_id = ? AND etat = 'orphelin'", [$gid]);
    $k = commande_par_reference(trim($ref));
    if (!$o || !$k) return false;
    $liens = ['commande' => (int)$k['id'], 'client' => (int)$k['client_id']];
    evenement('email_recu', 'Réponse de ' . $o['de'] . ' : « ' . $o['objet'] . ' » (rattachée à la main, extrait)', $liens, 'gmail:' . $gid, (string)$o['extrait'], (string)$o['de']);
    $ev = (int)db()->lastInsertId();
    q("UPDATE evenements SET lu = 0 WHERE id = ?", [$ev]);
    q("UPDATE mails_recus SET etat = 'range', commande_id = ?, evenement_id = ?, de = '', objet = '', extrait = '' WHERE gmail_id = ?", [(int)$k['id'], $ev, $gid]);
    return true;
}
function reponses_purger(): void {
    q("DELETE FROM mails_recus WHERE etat = 'orphelin' AND le < ?", [date('Y-m-d H:i:s', time() - REPONSES_ORPHELINS_JOURS * 86400)]);
    q("UPDATE mails_recus SET de = '', objet = '', extrait = '' WHERE etat = 'ignore'");
    q("DELETE FROM mails_recus WHERE etat IN ('copie', 'ignore') AND le < ?", [date('Y-m-d H:i:s', time() - 90 * 86400)]);   // après 3 mois, Gmail ne les renvoie plus (recherche sur 2 jours)
}
/** Vrai si le script ne passe plus (clé active mais rien reçu depuis 24 h) : alerte aux admins sur l'accueil (P-20). */
function reponses_silence(): bool {
    if (!reponses_actif()) return false;
    $d = parametre('reponses_dernier') ?: parametre('reponses_cle_le');
    return $d && (time() - strtotime($d)) > REPONSES_SILENCE_HEURES * 3600;
}
/** Les réponses de clients pas encore lues, pour l'accueil. */
function reponses_non_lues(): array {
    return lignes("SELECT e.id, e.le, e.auteur, e.texte, e.commande_id, k.reference FROM evenements e JOIN commandes k ON k.id = e.commande_id
                   WHERE e.type = 'email_recu' AND e.lu = 0 ORDER BY e.le DESC LIMIT 20");
}
