<?php
/*
 * Madame Cho : envoi d'une demande sans engagement (spécification du site v2, rubrique 8.1).
 * - POST seulement ; destinataire fixe ; l'adresse du client seulement en « Reply-To », après validation.
 * - Seuls les gâteaux, tailles et parfums de la carte sont acceptés ; prix et délais recalculés ici.
 * - Champ-piège et limite de tentatives contre le spam ; messages d'erreur sans donnée personnelle.
 * - Les plages de retrait (spécification 007, 27/11/2026) : six plages du lundi au samedi, chacune « libre » ou « complet » (pleine,
 *   fermée par Youngmi ou touchée par une indispo) ; la page demande l'état des plages du jour choisi (« verifier », la date seulement),
 *   et l'envoi sur une plage complète, un dimanche ou un jour fermé est refusé ; ni nombre de clients ni motif ne sont jamais dits.
 * - Si une protection ne marche pas (dossier des limites, verrou, signature DKIM), aucun email ne part :
 *   la page le dit et propose le téléphone ou l'email (audit du site du 03/11/2026, C04).
 * - Une fois la demande partie, la gestion envoie au client un accusé de réception (demande d'Olivier du 09/11/2026, inc/accuse.php).
 * - « Commander et payer » (spécification 006, temps 2, éteint tant que Youngmi ne l'allume pas) : payer=1 avec les mêmes champs ;
 *   la gestion revérifie tout, retient la place, crée le lien de paiement de Qonto et renvoie son adresse ; suivi=<jeton> dit où en est
 *   le paiement, sans aucune donnée personnelle ; sans_payer=<jeton> transforme la commande en demande ordinaire.
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
const GESTION_ENREGISTRER = __DIR__ . '/../gestion/inc/enregistrer.php';   // espace de gestion des commandes (06/11/2026)

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
        echo '<div class="recu"><div class="etiquette">Demande envoyée</div><h1 class="titre">Merci, Madame Cho vous répond sous 24 heures</h1>'
           . '<p>Votre demande n\'est pas encore une commande : Madame Cho vous envoie une proposition écrite, que vous acceptez par écrit.</p>'
           . '<p>Sa réponse viendra de <b>' . $h(DESTINATAIRE) . '</b> : <a href="../madame-cho.vcf">ajoutez Madame Cho à vos contacts</a>, et regardez dans vos courriers indésirables si vous ne la voyez pas sous 24 heures.</p>'
           . '<p class="ref">Référence : <b>' . $h($data['ref']) . '</b></p></div>';
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
/**
 * Majuscules oubliées (demande d'Olivier du 08/11/2026) : « jean-pierre » → « Jean-Pierre », « le gall » → « Le Gall »,
 * « de la fontaine » → « de La Fontaine », « d'artagnan » → « d'Artagnan ». Seulement quand il n'y a aucune majuscule : ce qui a
 * été écrit exprès (« McDonald », « de Gaulle ») reste tel quel. Même règle que nom_majuscules() de la gestion (inc/modele.php).
 */
function nomMajuscules(string $s): string {
    $s = uneLigne($s);
    if ($s === '' || mb_strtolower($s) !== $s) return $s;
    $particules = ['de', 'du', 'des', 'van', 'von', 'der', 'den', 'di', 'da', 'del', 'della', 'dos', 'das', 'y'];
    $mots = explode(' ', $s); $n = count($mots);
    foreach ($mots as $i => $m) {
        if ($i < $n - 1 && in_array($m, $particules, true)) continue;
        $m = (string)preg_replace_callback("/(^|[-'’])(\p{Ll})/u", fn($x) => $x[1] . mb_strtoupper($x[2]), $m);
        $mots[$i] = (string)preg_replace("/^D(['’])/u", 'd$1', $m);
    }
    return implode(' ', $mots);
}

/* ---------- Les plages de retrait (agenda de la gestion, spécification 007) ---------- */
// Pas de retrait le dimanche ni les jours fermés de la carte (24 décembre) : la phrase de la page et du serveur.
function jour_ferme_texte(string $date, array $CAT): string {
    $j = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('Europe/Paris'));
    if (!$j || $j->format('Y-m-d') !== $date) return '';
    if (in_array((int)$j->format('w'), $CAT['fermesSemaine'] ?? [0], true))
        return 'Pas de retrait le dimanche : choisissez un autre jour, ou appelez Madame Cho au 06 61 31 17 79 pour un cas particulier.';
    if (in_array($j->format('m-d'), $CAT['fermes'], true)) return 'Pas de retrait le 24 décembre : choisissez une autre date.';
    return '';
}
// Les plages pleines, fermées ou indisponibles d'un jour viennent de la gestion. Si elle ne répond pas (pas encore ouverte, base illisible),
// toutes les plages restent libres : l'agenda ne bloque jamais le formulaire.
function gestion_agenda(callable $f, $sinon) {
    if (PHP_VERSION_ID < 80000 || !is_file(GESTION_ENREGISTRER)) return $sinon;   // l'espace de gestion demande PHP 8
    try {
        require_once GESTION_ENREGISTRER;
        return $f();
    } catch (\Throwable $e) {
        error_log('madamecho formulaire : agenda de la gestion non lu (' . get_class($e) . ')');
        return $sinon;
    }
}
// Une référence jamais déjà prise dans la gestion (audit n° 11, C11-02) : sinon la demande serait rattachée à une autre commande
function reference_unique(): string {
    $r = 'MC-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
    return gestion_agenda(fn() => \MadameCho\Gestion\gestion_base_existante() ? \MadameCho\Gestion\reference_nouvelle() : $r, $r);
}
function plages_libres(string $date, array $CAT): array {
    $toutes = ['plages' => array_fill_keys(array_map('strval', array_keys($CAT['plages'])), 'libre'), 'message' => ''];
    // Date invalide, passée ou au-delà de six mois : la réponse neutre du contrat, sans lire l'agenda (audit n° 10, C10-13)
    $j = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('Europe/Paris'));
    $auj = new DateTimeImmutable('today', new DateTimeZone('Europe/Paris'));
    if (!$j || $j->format('Y-m-d') !== $date || $j < $auj || $j > $auj->modify('+6 month')) return $toutes;
    $ferme = jour_ferme_texte($date, $CAT);
    if ($ferme !== '') return ['plages' => array_fill_keys(array_keys($toutes['plages']), 'complet'), 'message' => $ferme];
    return gestion_agenda(fn() => \MadameCho\Gestion\plages_pour_demande($date), $toutes);
}
// Pendant la saisie, la page demande l'état des plages du jour choisi : la date seulement, rien d'enregistré ni d'envoyé.
if (champ('verifier', 1) === '1') {
    $r = plages_libres(champ('date', 10), $CAT);
    // « Commander et payer » possible (temps 2 allumé, Qonto prêt, jour ouvert) : la page décide ensuite selon le panier (spécification 006)
    $r['payer'] = $r['message'] === '' && (bool)gestion_agenda(fn() => \MadameCho\Gestion\paiement_site_actif(), false);
    if ($r['payer']) $r['minimum'] = (int)gestion_agenda(fn() => \MadameCho\Gestion\paiement_minimum(), 500);   // en centimes
    repondre(true, $r, true);
}
// Le suivi d'un paiement en ligne (spécification 006) : le jeton seul, toutes les 5 secondes au plus ; jamais de nom, d'email ni de montant
if (($jeton = champ('suivi', 40)) !== '' || ($jeton = champ('sans_payer', 40)) !== '') {
    $sansPayer = champ('sans_payer', 40) !== '';
    repondre(true, gestion_agenda(fn() => \MadameCho\Gestion\paiement_suivi($jeton, $sansPayer), ['etat' => 'inconnu']), true);
}

// Champ-piège : un robot le remplit ; on répond comme si tout allait bien, sans rien envoyer.
if (champ('site_web', 200) !== '') {
    repondre(true, ['ref' => 'MC-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(2)))], $enJson);
}

$erreurs = [];
// Prénom et nom en deux champs depuis le 07/11/2026 ; une page du formulaire restée ouverte avant envoie encore « Prénom et nom » en un seul champ
$deuxChamps = isset($_POST['prenom']);
$prenom = $deuxChamps ? nomMajuscules(champ('prenom', 60)) : '';
$nom = nomMajuscules(champ('nom', 80));
$nomComplet = trim($prenom . ' ' . $nom);
$email = uneLigne(champ('email', 120));
$tel = uneLigne(champ('telephone', 30));
$perso = champ('perso', 1200);
$date = champ('date', 10);
$plage = champ('plage', 4);
// Une page du formulaire restée ouverte avant le 27/11/2026 envoie encore un « moment » : la première plage encore libre de ce moment,
// notée « à préciser avec le client » dans le mail à Madame Cho (audit n° 10, C10-06)
$momentAncien = '';
if ($plage === '' && isset($_POST['moment'])) {
    $parMoment = ['matin' => ['0900', '1030'], 'midi' => ['1200'], 'apres-midi' => ['1400', '1600'], 'soir' => ['1800']][champ('moment', 20)] ?? [];
    if ($parMoment) {
        $momentAncien = ['matin' => 'le matin', 'midi' => 'le midi', 'apres-midi' => 'l\'après-midi', 'soir' => 'le soir'][champ('moment', 20)];
        $etats = plages_libres($date, $CAT)['plages'];
        foreach ($parMoment as $c) if (($etats[$c] ?? 'libre') === 'libre') { $plage = $c; break; }
        if ($plage === '') $plage = $parMoment[0];   // aucune libre : l'envoi est refusé plus bas, avec la phrase de la plage
    }
}
$pro = champ('pro', 1) === '1';
$societe = $pro ? uneLigne(champ('societe', 100)) : '';
$besoin = $pro ? champ('besoin', 1200) : '';
// Provenance (07/11/2026) : la campagne Matomo de la visite, « qr-code packaging » après un scan du QR code d'un emballage ; vide sinon
$via = uneLigne(champ('via', 90));
if (!preg_match('/^[\w.-]{1,40}(?: [\w.-]{1,40})?$/', $via)) $via = '';
// Demande déjà commencée (14/11/2026) : le bouton de l'accueil par lequel elle a commencé (anniversaire, Number cake, événement…),
// seulement une clé connue de la carte ; vide sinon
$depuis = champ('depuis', 30);
if (!isset($CAT['envies'][$depuis])) $depuis = '';
// « Comment avez-vous connu Madame Cho ? » (14/11/2026) : question facultative, seulement une des réponses proposées ; vide sinon
$connu = champ('connu', 20);
if (!isset($CAT['connu'][$connu])) $connu = '';

if ($deuxChamps) {
    if ($prenom === '') $erreurs[] = 'Indiquez votre prénom.';
    if ($nom === '') $erreurs[] = 'Indiquez votre nom.';
} elseif (mb_strlen($nom) < 2) $erreurs[] = 'Indiquez votre prénom et votre nom.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n,;]/', $email)) $erreurs[] = 'Indiquez une adresse email valide.';
if ($tel !== '' && !preg_match('/^[0-9 +().\-]{6,30}$/', $tel)) $erreurs[] = 'Le numéro de téléphone n\'est pas valide.';
if (!isset($CAT['plages'][$plage])) $erreurs[] = 'Choisissez une plage horaire de retrait.';
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
    $lignes[] = ['nom' => $p['nom'], 'libelle' => $libelle, 'q' => $q, 'prix' => $prix, 'plaque' => $plaque, 'p' => $pid, 'c' => $p['mode'] === 'taille' ? $tid : $vid];
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
$indispo = '';   // la phrase de l'agenda de la gestion, si ce jour ou cette plage n'est pas libre
$jour = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('Europe/Paris'));
$auj = new DateTimeImmutable('today', new DateTimeZone('Europe/Paris'));
if (!$jour || $jour->format('Y-m-d') !== $date) {
    $erreurs[] = 'Choisissez une date de retrait.';
} else {
    $min = $auj->modify('+' . $delai . ' day');
    $avant = count($erreurs);
    if ($jour < $min) $erreurs[] = 'La date de retrait doit être au plus tôt le ' . $min->format('d/m/Y') . '.';
    $ferme = jour_ferme_texte($date, $CAT);   // dimanche, 24 décembre
    if ($ferme !== '') $indispo = $ferme;
    if ($jour > $auj->modify('+6 month')) $erreurs[] = 'Choisissez une date dans les six prochains mois.';
    // Une date possible, mais une plage pleine, fermée ou indisponible dans l'agenda de la gestion : refusée aussi (la page l'a dit pendant la saisie)
    // Sans réponse de l'agenda, la demande passe (une demande ne prend pas de place), mais le mail à Madame Cho le dit (C10-05)
    if ($indispo === '' && count($erreurs) === $avant && isset($CAT['plages'][$plage])) {
        $r = gestion_agenda(fn() => \MadameCho\Gestion\plage_refusee($date, $plage), null);
        if ($r === null) $plageNonVerifiee = true;
        else $indispo = $r;
    }
    if ($indispo !== '') $erreurs[] = $indispo;
}

if ($erreurs) {
    http_response_code(422);
    repondre(false, ['erreurs' => array_values(array_unique($erreurs))] + ($indispo !== '' ? ['indispo' => $indispo] : []), $enJson);
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
$payer = $enJson && champ('payer', 1) === '1';   // « Commander et payer » (spécification 006) : seulement depuis la page, jamais sans JavaScript
$empreinte = hash('sha256', (string)json_encode([$nomComplet, $email, $date, $plage, $lignes, $perso, $besoin, $payer]));
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
    // double clic : même référence, pas de second email ni de second lien de paiement
    repondre(true, ['ref' => $etat['dernier']['ref']] + (is_array($etat['dernier']['p'] ?? null) ? ['paiement' => $etat['dernier']['p']] : []), $enJson);
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

/* ---------- « Commander et payer » (spécification 006, temps 2) ---------- */
// La gestion revérifie le panier, le minimum et la plage (places retenues comprises), crée le lien de paiement de Qonto, enregistre
// la commande comme une demande et retient sa place. Aucun mail à ce moment : la confirmation (qui vaut reçu) part quand le paiement
// est constaté ; sinon la commande redevient une demande ordinaire (mail à Madame Cho, accusé de réception au client).
if ($payer) {
    $refuser = function (string $message, array $plus = []) use ($fh, &$etat): void {
        array_pop($etat['envois']); ecrire_etat($fh, $etat);   // un paiement refusé ne compte pas dans la limite
        flock($fh, LOCK_UN); fclose($fh);
        http_response_code(409);
        repondre(false, ['payer' => false, 'message' => $message] + $plus, true);
    };
    if (champ('conditions', 1) !== '1') $refuser('Cochez « J\'accepte les conditions de commande » pour commander et payer.');
    if (PHP_VERSION_ID < 80000 || !is_file(GESTION_ENREGISTRER)) $refuser('Le paiement en ligne n\'est pas proposé pour le moment : envoyez votre demande sans payer.');
    // Le client paie le total qu'il a vu et accepté, jamais un autre (audit n° 11, C11-12) : un prix changé entre-temps l'arrête
    $vu = champ('total_vu', 12);
    if ($vu !== '' && (!ctype_digit($vu) || (int)$vu !== $total)) {
        $prixTexte = ($total % 100) ? intdiv($total, 100) . ',' . sprintf('%02d', $total % 100) . ' €' : intdiv($total, 100) . ' €';
        $refuser('Le prix de votre panier a changé : il fait maintenant ' . $prixTexte . ' TTC. Rechargez la page pour voir les prix à jour, puis commandez de nouveau. Rien n\'a été débité.', ['prix' => true]);
    }
    $ref = reference_unique();
    try {
        require_once GESTION_ENREGISTRER;
        $r = \MadameCho\Gestion\commander_et_payer(($deuxChamps ? ['prenom' => $prenom] : []) + ['ref' => $ref, 'nom' => $nom, 'email' => $email, 'telephone' => $tel, 'date' => $date, 'plage' => $plage,
            'lignes' => $lignes, 'perso' => $perso, 'pro' => $pro, 'societe' => $societe, 'besoin' => $besoin, 'via' => $via,
            'depuis' => $depuis !== '' ? $CAT['envies'][$depuis]['origine'] : '', 'connu' => $connu, 'total' => $total]);
    } catch (\Throwable $e) {
        error_log('madamecho formulaire : commander et payer (' . get_class($e) . ')');
        $r = ['ok' => false, 'message' => 'Le paiement en ligne ne répond pas pour le moment, et rien n\'a été débité : envoyez votre demande sans payer.'];
    }
    if (empty($r['ok'])) {
        if (!empty($r['envoyee'])) {   // la demande est arrivée sans paiement possible : comme un envoi ordinaire
            $etat['dernier'] = ['e' => $empreinte, 't' => time(), 'ref' => $ref]; ecrire_etat($fh, $etat); flock($fh, LOCK_UN); fclose($fh);
            repondre(true, ['ref' => $ref, 'message' => (string)$r['message']], true);
        }
        $refuser((string)($r['message'] ?? 'Le paiement en ligne n\'est pas possible pour le moment : envoyez votre demande sans payer.'));
    }
    $paiement = ['url' => (string)$r['url'], 'suivi' => (string)$r['suivi'], 'jusqua' => (string)$r['jusqua']];
    $etat['dernier'] = ['e' => $empreinte, 't' => time(), 'ref' => $ref, 'p' => $paiement];
    ecrire_etat($fh, $etat);
    flock($fh, LOCK_UN); fclose($fh);
    repondre(true, ['ref' => $ref, 'paiement' => $paiement], true);
}

/* ---------- L'email à Madame Cho ---------- */
$ref = reference_unique();
$jours = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
$mois = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
$dateTexte = $jours[(int)$jour->format('w')] . ' ' . (int)$jour->format('j') . ' ' . $mois[(int)$jour->format('n')] . ' ' . $jour->format('Y');
$euros = fn(int $c) => ($c % 100) ? intdiv($c, 100) . ',' . sprintf('%02d', $c % 100) . ' €' : intdiv($c, 100) . ' €';

$t = [];
$t[] = 'Nouvelle demande sans engagement reçue par le site madamecho.com'
     . (strncmp($via, 'qr-code', 7) === 0 ? ', après un scan du QR code d\'un emballage' : (strncmp($via, 'instagram', 9) === 0 ? ', après une arrivée par le lien de la page Instagram' : ''))
     . ($depuis !== '' ? ', commencée par ' . $CAT['envies'][$depuis]['origine'] : '') . '.';
$t[] = 'Référence : ' . $ref . ' (envoyée le ' . date('d/m/Y à H:i') . ')';
$t[] = '';
$t[] = 'RETRAIT SOUHAITÉ';
$t[] = ucfirst($dateTexte) . ', ' . mb_strtolower($CAT['plages'][$plage]);
if ($momentAncien !== '') $t[] = 'Envoyée depuis une ancienne page du site : le client avait demandé ' . $momentAncien . ' ; plage à préciser avec lui.';
if (!empty($plageNonVerifiee)) $t[] = 'Plage pas vérifiée : l\'agenda de la gestion n\'a pas répondu. Vérifiez-la avant de confirmer.';
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
if ($deuxChamps) { $t[] = 'Prénom : ' . $prenom; $t[] = 'Nom : ' . $nom; } else $t[] = 'Nom : ' . $nom;
$t[] = 'Email : ' . $email;
if ($tel !== '') $t[] = 'Téléphone : ' . $tel;
if ($connu !== '') $t[] = 'Réponse à « Comment avez-vous connu Madame Cho ? » : ' . $CAT['connu'][$connu];
$t[] = '';
// Pas de « Répondre » : la réponse citerait cet email interne, avec un « Re: » que le client n'a jamais reçu, et risquerait ses indésirables (07/11/2026)
$t[] = is_file(GESTION_ENREGISTRER)
    ? 'Pour répondre au client, ouvrez la demande dans la gestion (lien ci-dessous) puis « Préparer la proposition » : un nouveau message, envoyé depuis ' . DESTINATAIRE . '. Évitez « Répondre » ici : votre réponse citerait cet email et risquerait les indésirables du client.'
    : 'Pour répondre au client, écrivez-lui un nouveau message depuis ' . DESTINATAIRE . '. Évitez « Répondre » ici : votre réponse citerait cet email et risquerait les indésirables du client.';
$t[] = '고객에게는 « 답장 » 대신 ' . DESTINATAIRE . '에서 새 메일로 보내 주세요' . (is_file(GESTION_ENREGISTRER) ? ' (관리 화면의 « 견적 이메일 준비 »)' : '') . '. 답장하면 이 메일이 인용되어 고객의 스팸함으로 갈 수 있어요.';
$t[] = 'Rappel : une proposition écrite (prix TTC total, jour et plage horaire du retrait), son accord écrit, puis votre confirmation : le retrait n\'est réservé qu\'à ce moment-là.';
// Espace de gestion : la demande y est aussi enregistrée (voir plus bas) ; le lien ouvre sa fiche, ou propose de la créer
if (is_file(GESTION_ENREGISTRER)) { $t[] = ''; $t[] = 'Dans la gestion : https://' . DKIM_DOMAINE . '/gestion/?p=commande&ref=' . $ref; }
$corps = implode("\r\n", $t);

$sujet = 'Demande ' . $ref . ' : ' . $nomComplet . ', retrait le ' . $jour->format('d/m');
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
// Enregistrement dans l'espace de gestion, seulement une fois l'email parti, et sans jamais gêner le visiteur :
// en cas d'échec, l'email suffit (Youngmi crée la commande depuis son lien) et l'incident est noté dans le journal du serveur.
if (PHP_VERSION_ID >= 80000 && is_file(GESTION_ENREGISTRER)) {   // l'espace de gestion demande PHP 8
    try {
        require_once GESTION_ENREGISTRER;
        $commande = \MadameCho\Gestion\enregistrer_demande(($deuxChamps ? ['prenom' => $prenom] : []) + ['ref' => $ref, 'nom' => $nom, 'email' => $email, 'telephone' => $tel, 'date' => $date, 'plage' => $plage,
            'lignes' => $lignes, 'perso' => $perso, 'pro' => $pro, 'societe' => $societe, 'besoin' => $besoin, 'via' => $via,
            'depuis' => $depuis !== '' ? $CAT['envies'][$depuis]['origine'] : '', 'connu' => $connu]);
    } catch (\Throwable $e) {
        $commande = 0;
        error_log('madamecho formulaire : demande ' . $ref . ' non enregistrée dans la gestion (' . get_class($e) . ')');
    }
    // Accusé de réception au client (demandes d'Olivier du 09/11 et du 11/11/2026) : un mail fixe « commande bien reçue, traitée dans les plus brefs délais »,
    // rangé dans les Échanges de la commande ; s'il ne part pas, le visiteur ne voit aucune différence (sa demande est arrivée)
    try {
        $accuse = \MadameCho\Gestion\accuser_reception(($deuxChamps ? ['prenom' => $prenom] : []) + ['ref' => $ref, 'nom' => $nom, 'email' => $email], (int)$commande);
        if ($accuse === 'echec') error_log('madamecho formulaire : accusé de réception de ' . $ref . ' non envoyé');
    } catch (\Throwable $e) {
        error_log('madamecho formulaire : accusé de réception de ' . $ref . ' non envoyé (' . get_class($e) . ')');
    }
}
repondre(true, ['ref' => $ref], $enJson);
