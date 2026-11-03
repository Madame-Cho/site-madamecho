<?php
/*
 * Madame Cho : envoi d'une demande sans engagement (spécification du site v2, rubrique 8.1).
 * - POST seulement ; destinataire fixe ; l'adresse du client seulement en « Reply-To », après validation.
 * - Seuls les gâteaux, tailles et parfums de la carte sont acceptés ; prix et délais recalculés ici.
 * - Champ-piège et limite de tentatives contre le spam ; messages d'erreur sans donnée personnelle.
 * - Si une protection ne marche pas (dossier des limites, verrou, signature DKIM), aucun email ne part :
 *   la page le dit et propose le téléphone ou l'email (audit du site du 03/11/2026, C04).
 * - Aucun secret dans ce fichier (dépôt public, R-08). Signature DKIM : dkim.php (clé créée sur le serveur).
 */
declare(strict_types=1);
mb_internal_encoding('UTF-8');
date_default_timezone_set('Europe/Paris');

const DESTINATAIRE = 'contact@madamecho.com';
const EXPEDITEUR = 'site@madamecho.com';
const MAX_PAR_HEURE = 5;
const DUREE_EMPREINTES = 7200;   // 2 heures suffisent à la limite horaire ; avec le ménage quotidien d'OVH, jamais plus de 48 heures (politique de confidentialité)
const INDISPONIBLE = 'Le formulaire est momentanément indisponible, et votre demande n\'est pas partie.';

$CAT = require __DIR__ . '/catalogue.php';
require __DIR__ . '/dkim.php';
require __DIR__ . '/menage.php';
$enJson = stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;
$dossier = __DIR__ . '/.limites';
menage_empreintes($dossier, DUREE_EMPREINTES);   // à chaque appel, même refusé ; la tâche planifiée d'OVH le fait aussi chaque jour

function repondre(bool $ok, array $data, bool $enJson): void {
    if ($enJson) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode(['ok' => $ok] + $data, JSON_UNESCAPED_UNICODE);
        exit;
    }
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    $h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    echo '<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
       . '<meta name="robots" content="noindex"><title>Ma demande · Madame Cho</title><link rel="stylesheet" href="../assets/site.css"></head>'
       . '<body><main class="bloc premier"><div class="etroit">';
    if ($ok) {
        echo '<div class="etiquette">Demande envoyée</div><h1 class="titre">Merci, Youngmi vous répond sous 24 heures</h1>'
           . '<p>Votre demande n\'est pas encore une commande : Youngmi vous envoie une proposition écrite, que vous acceptez par écrit.</p>'
           . '<p class="ref">Référence : <b>' . $h($data['ref']) . '</b></p>';
    } else {
        echo '<h1 class="titre">La demande n\'a pas pu être envoyée</h1><p>' . $h(implode(' ', $data['erreurs'] ?? [])) . '</p>'
           . '<p>Revenez en arrière pour corriger, ou appelez le <a href="tel:+33661311779">06 61 31 17 79</a>.</p>';
    }
    echo '<p><a class="bouton" href="../">Retour à l\'accueil</a></p></div></main></body></html>';
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    repondre(false, ['erreurs' => ['Méthode non acceptée.']], $enJson);
}

/* ---------- Lecture et nettoyage ---------- */
function champ(string $nom, int $max): string {
    $v = isset($_POST[$nom]) && is_string($_POST[$nom]) ? $_POST[$nom] : '';
    $v = str_replace(["\r\n", "\r"], "\n", $v);
    $v = preg_replace('/[^\P{C}\n\t]/u', '', $v) ?? '';   // caractères de contrôle retirés (sauf retour à la ligne)
    $v = trim($v);
    return mb_substr($v, 0, $max);
}
function uneLigne(string $v): string { return trim(preg_replace('/\s+/u', ' ', $v) ?? ''); }

// Champ-piège : un robot le remplit ; on répond comme si tout allait bien, sans rien envoyer.
if (champ('site_web', 200) !== '') {
    repondre(true, ['ref' => 'MC-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(2)))], $enJson);
}

$erreurs = [];
$nom = uneLigne(champ('nom', 80));
$email = uneLigne(champ('email', 120));
$tel = uneLigne(champ('telephone', 30));
$perso = champ('perso', 1200);
$date = champ('date', 10);
$moment = champ('moment', 20);
$pro = champ('pro', 1) === '1';
$societe = $pro ? uneLigne(champ('societe', 100)) : '';
$besoin = $pro ? champ('besoin', 1200) : '';

if (mb_strlen($nom) < 2) $erreurs[] = 'Indiquez votre prénom et votre nom.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n,;]/', $email)) $erreurs[] = 'Indiquez une adresse email valide.';
if ($tel !== '' && !preg_match('/^[0-9 +().\-]{6,30}$/', $tel)) $erreurs[] = 'Le numéro de téléphone n\'est pas valide.';
if (!isset($CAT['moments'][$moment])) $erreurs[] = 'Choisissez un moment de retrait.';
if ($pro && $societe === '') $erreurs[] = 'Indiquez le nom de l\'entreprise.';

/* ---------- Les gâteaux : seulement ceux de la carte, prix recalculés ---------- */
$brut = json_decode(champ('lignes', 6000) ?: '[]', true);
if (!is_array($brut) || count($brut) > 30) { $brut = []; $erreurs[] = 'La liste des gâteaux n\'est pas lisible.'; }
$lignes = []; $total = 0; $delai = 0; $parProduit = [];
$texte = fn($x) => is_string($x) ? $x : '';   // un tableau ou un nombre à la place d'un identifiant est refusé, sans erreur PHP
$parCle = [];
foreach ($brut as $l) {
    if (!is_array($l)) { $erreurs[] = 'Un gâteau de la demande n\'est pas reconnu.'; continue; }
    $pid = $texte($l['p'] ?? null);
    $p = $CAT['produits'][$pid] ?? null;
    $q = is_int($l['q'] ?? null) ? $l['q'] : 0;
    if (!$p || $q < 1) { $erreurs[] = 'Un gâteau de la demande n\'est pas reconnu.'; continue; }
    if ($p['mode'] === 'taille') {
        $tid = $texte($l['t'] ?? null);
        $t = $p['tailles'][$tid] ?? null;
        $cle = $pid . '|' . $tid; $parCle[$cle] = ($parCle[$cle] ?? 0) + $q;
        if (!$t || $q > 10) { $erreurs[] = 'Une taille de la demande n\'est pas reconnue.'; continue; }
        if ($parCle[$cle] > 10) { $erreurs[] = $p['nom'] . ' : 10 au plus par taille ; pour davantage, décrivez-le dans votre message.'; continue; }
        $libelle = $t['libelle']; $prix = $t['prix'] * $q;
    } else {
        $vid = $texte($l['v'] ?? null);
        $v = $p['parfums'][$vid] ?? null;
        $cle = $pid . '|' . $vid; $parCle[$cle] = ($parCle[$cle] ?? 0) + $q;
        if (!$v || $q > 200) { $erreurs[] = 'Un parfum de la demande n\'est pas reconnu.'; continue; }
        if ($parCle[$cle] > 200) { $erreurs[] = $p['nom'] . ' : 200 au plus par parfum ; pour davantage, décrivez-le dans votre message.'; continue; }
        if ($q % $p['pas'] !== 0) { $erreurs[] = $p['nom'] . ' : par ' . $p['pas'] . ' du même parfum.'; continue; }
        $libelle = $v['libelle']; $prix = $p['unite'] * $q;
        $parProduit[$pid] = ($parProduit[$pid] ?? 0) + $q;
    }
    $plaque = ($p['plaque'] && isset($l['plaque']) && is_string($l['plaque'])) ? uneLigne(mb_substr($l['plaque'], 0, 60)) : '';
    $lignes[] = ['nom' => $p['nom'], 'libelle' => $libelle, 'q' => $q, 'prix' => $prix, 'plaque' => $plaque];
    $total += $prix; $delai = max($delai, $p['delai']);
}
foreach ($parProduit as $id => $n) {
    $p = $CAT['produits'][$id];
    if ($n < $p['minimum']) $erreurs[] = $p['nom'] . ' : ' . $p['minimum'] . ' au minimum.';
}
if (!$lignes && $perso === '' && $besoin === '') $erreurs[] = 'Ajoutez au moins un gâteau, ou décrivez votre demande.';
if ($perso !== '' || $besoin !== '' || !$lignes) $delai = max($delai, (int)$CAT['delaiPerso']);
if ($pro) $delai = max($delai, (int)($CAT['delaiPro'] ?? 14));   // demande d'un professionnel : deux semaines (audit C02)

/* ---------- La date de retrait ---------- */
$jour = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('Europe/Paris'));
$auj = new DateTimeImmutable('today', new DateTimeZone('Europe/Paris'));
if (!$jour || $jour->format('Y-m-d') !== $date) {
    $erreurs[] = 'Choisissez une date de retrait.';
} else {
    $min = $auj->modify('+' . $delai . ' day');
    if ($jour < $min) $erreurs[] = 'La date de retrait doit être au plus tôt le ' . $min->format('d/m/Y') . '.';
    if (in_array($jour->format('m-d'), $CAT['fermes'], true)) $erreurs[] = 'Pas de retrait le 24 décembre.';
    if ($jour > $auj->modify('+6 month')) $erreurs[] = 'Choisissez une date dans les six prochains mois.';
}

if ($erreurs) {
    http_response_code(422);
    repondre(false, ['erreurs' => array_values(array_unique($erreurs))], $enJson);
}

/* ---------- Limite de tentatives et double envoi ---------- */
// Sans protection en état de marche, rien ne part (C04) : l'incident technique seul est noté dans le journal d'erreurs d'OVH.
function indisponible(string $cause, bool $enJson, $fh = null): void {
    if ($fh) { flock($fh, LOCK_UN); fclose($fh); }
    error_log('madamecho formulaire : ' . $cause);   // jamais de donnée du client
    http_response_code(503);
    repondre(false, ['erreurs' => [INDISPONIBLE]], $enJson);
}
if (!is_dir($dossier) && !@mkdir($dossier, 0700, true)) indisponible('dossier des limites absent', $enJson);
$sel = sel_empreintes($dossier);
if ($sel === null) indisponible('clé des empreintes illisible', $enJson);
// Empreinte pseudonymisée de l'adresse IP : HMAC avec une clé secrète du serveur, jamais l'adresse elle-même.
// Sans date dans l'empreinte, la limite de 5 envois est bien glissante sur une heure, même à minuit.
$ipHash = hash_hmac('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? ''), $sel);
$empreinte = hash('sha256', (string)json_encode([$nom, $email, $date, $moment, $lignes, $perso, $besoin]));
$fichier = $dossier . '/' . substr($ipHash, 0, 32) . '.json';
$etat = ['envois' => [], 'dernier' => null];
$fh = @fopen($fichier, 'c+');
if (!$fh) indisponible('fichier des limites non ouvert', $enJson);
if (!flock($fh, LOCK_EX)) indisponible('verrou non obtenu', $enJson, $fh);
$lu = json_decode(stream_get_contents($fh) ?: '', true);
if (is_array($lu)) {
    if (isset($lu['envois']) && is_array($lu['envois'])) $etat['envois'] = array_values(array_filter($lu['envois'], 'is_int'));
    $d = $lu['dernier'] ?? null;
    if (is_array($d) && is_string($d['e'] ?? null) && is_int($d['t'] ?? null) && is_string($d['ref'] ?? null)) $etat['dernier'] = $d;
}
$etat['envois'] = array_values(array_filter($etat['envois'], fn($t) => $t > time() - 3600));
if ($etat['dernier'] && $etat['dernier']['e'] === $empreinte && $etat['dernier']['t'] > time() - 120) {
    flock($fh, LOCK_UN); fclose($fh);
    repondre(true, ['ref' => $etat['dernier']['ref']], $enJson);   // double clic : même référence, pas de second email
}
if (count($etat['envois']) >= MAX_PAR_HEURE) {
    flock($fh, LOCK_UN); fclose($fh);
    http_response_code(429);
    repondre(false, ['erreurs' => ['Trop de demandes envoyées en peu de temps. Réessayez dans une heure, ou appelez le 06 61 31 17 79.']], $enJson);
}
function ecrire_etat($fh, array $etat): bool {
    return ftruncate($fh, 0) && rewind($fh) && fwrite($fh, (string)json_encode($etat)) !== false && fflush($fh);
}
// L'envoi est compté avant de partir : si le compteur ne peut pas être écrit, rien ne part.
$etat['envois'][] = time();
if (!ecrire_etat($fh, $etat)) indisponible('compteur non écrit', $enJson, $fh);

/* ---------- L'email à Madame Cho ---------- */
$ref = 'MC-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
$jours = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
$mois = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
$dateTexte = $jours[(int)$jour->format('w')] . ' ' . (int)$jour->format('j') . ' ' . $mois[(int)$jour->format('n')] . ' ' . $jour->format('Y');
$euros = fn(int $c) => ($c % 100) ? intdiv($c, 100) . ',' . sprintf('%02d', $c % 100) . ' €' : intdiv($c, 100) . ' €';

$t = [];
$t[] = 'Nouvelle demande sans engagement reçue par le site madamecho.com.';
$t[] = 'Référence : ' . $ref . ' (envoyée le ' . date('d/m/Y à H:i') . ')';
$t[] = '';
$t[] = 'RETRAIT SOUHAITÉ';
$t[] = ucfirst($dateTexte) . ', ' . mb_strtolower($CAT['moments'][$moment]);
$t[] = '';
if ($lignes) {
    $t[] = 'GÂTEAUX (prix de la carte, TTC)';
    foreach ($lignes as $l) {
        $t[] = '- ' . $l['q'] . ' × ' . $l['nom'] . ', ' . $l['libelle'] . ' : ' . $euros($l['prix']);
        if ($l['plaque'] !== '') $t[] = '  Plaque en chocolat : « ' . $l['plaque'] . ' »';
    }
    $t[] = 'Total indicatif : ' . $euros($total) . ' TTC';
    $t[] = '';
}
if ($perso !== '') { $t[] = 'GÂTEAU PERSONNALISÉ'; $t[] = $perso; $t[] = ''; }
if ($pro) { $t[] = 'DEMANDE D\'UN PROFESSIONNEL'; $t[] = 'Entreprise : ' . $societe; if ($besoin !== '') $t[] = $besoin; $t[] = ''; }
$t[] = 'CLIENT';
$t[] = 'Nom : ' . $nom;
$t[] = 'Email : ' . $email;
if ($tel !== '') $t[] = 'Téléphone : ' . $tel;
$t[] = '';
$t[] = 'Répondre à cet email écrit directement au client. Rappel : une proposition écrite (prix TTC total, jour et heure du retrait, acompte éventuel), son accord écrit, puis votre confirmation.';
$corps = implode("\r\n", $t);

$sujet = 'Demande ' . $ref . ' : ' . $nom . ', retrait le ' . $jour->format('d/m');
$sujetEnc = mb_encode_mimeheader($sujet, 'UTF-8', 'B');
// Corps en base64 : rien ne peut le modifier en chemin, la signature DKIM reste valable.
$corpsEnc = rtrim(chunk_split(base64_encode($corps), 76, "\r\n"), "\r\n") . "\r\n";
$signes = [
    'From' => mb_encode_mimeheader('Site Madame Cho', 'UTF-8', 'B') . ' <' . EXPEDITEUR . '>',
    'To' => DESTINATAIRE,
    'Subject' => $sujetEnc,
    'Date' => date('r'),
    'Message-ID' => '<' . strtolower($ref) . '.' . bin2hex(random_bytes(6)) . '@' . DKIM_DOMAINE . '>',
    'Reply-To' => $email,
    'MIME-Version' => '1.0',
    'Content-Type' => 'text/plain; charset=UTF-8',
    'Content-Transfer-Encoding' => 'base64',
];
$lignesEntetes = [];
$signature = dkim_signer($signes, $corpsEnc);
if (!$signature) {   // sans signature, l'email risquerait les indésirables (DMARC p=quarantine) : rien ne part
    array_pop($etat['envois']); ecrire_etat($fh, $etat);
    indisponible('signature DKIM impossible', $enJson, $fh);
}
$lignesEntetes[] = $signature;
foreach ($signes as $n => $v) if ($n !== 'To' && $n !== 'Subject') $lignesEntetes[] = $n . ': ' . $v;   // To et Subject : ajoutés par mail()
$lignesEntetes[] = 'X-Mailer: madamecho.com';
$entetes = implode("\r\n", $lignesEntetes);
// Expéditeur d'enveloppe sur madamecho.com ; si l'hébergement refuse -f, envoi sans.
$envoye = @mail(DESTINATAIRE, $sujetEnc, $corpsEnc, $entetes, '-f' . EXPEDITEUR);
if (!$envoye) $envoye = @mail(DESTINATAIRE, $sujetEnc, $corpsEnc, $entetes);

if ($envoye) $etat['dernier'] = ['e' => $empreinte, 't' => time(), 'ref' => $ref];
else array_pop($etat['envois']);
ecrire_etat($fh, $etat);
flock($fh, LOCK_UN); fclose($fh);
if (!$envoye) {
    error_log('madamecho formulaire : mail() a échoué');
    http_response_code(503);
    repondre(false, ['erreurs' => ['L\'envoi n\'a pas abouti de notre côté, et votre demande n\'est pas partie.']], $enJson);
}
repondre(true, ['ref' => $ref], $enJson);
