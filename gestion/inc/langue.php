<?php
/*
 * Madame Cho : espace de gestion. Langue de l'écran : coréen ou français (demande d'Olivier, 06/11/2026).
 * Youngmi voit la gestion en coréen ; un bouton passe en français, et le choix est gardé sur l'appareil.
 * Ce qu'on écrit et ce qu'on envoie reste en français : noms, adresses, gâteaux, textes des clients, commentaires,
 * emails préparés, exports, miroir et sauvegarde. Les pages sont écrites en français puis traduites une fois finies
 * (dictionnaire dans coreen.php) ; ce qui porte translate="no" n'est jamais traduit.
 */
declare(strict_types=1);
namespace MadameCho\Gestion;

const LANGUES = ['ko' => '한국어', 'fr' => 'Français'];
const LANGUE_COOKIE = 'mc_langue';

/** La langue de l'écran : celle choisie sur cet appareil ; sinon le coréen pour le compte de Youngmi et la page de connexion, le français pour les autres comptes. */
function langue(): string {
    $c = $_COOKIE[LANGUE_COOKIE] ?? '';
    if (is_string($c) && isset(LANGUES[$c])) return $c;
    $u = utilisateur_courant();
    if (!$u) return 'ko';
    return $u['email'] === GESTION_COMPTE_INITIAL ? 'ko' : 'fr';
}
function coreen_actif(): bool { return langue() === 'ko'; }

/** Le même texte en français ou en coréen, pour les rares phrases coupées par un lien ou du gras (le reste est traduit sur la page finie). */
function fr_ko(string $fr, string $ko): string { return coreen_actif() ? $ko : $fr; }

function langue_choisir(string $l): void {
    if (!isset(LANGUES[$l])) return;
    $_COOKIE[LANGUE_COOKIE] = $l;
    if (!headers_sent()) setcookie(LANGUE_COOKIE, $l, ['expires' => time() + 400 * 86400, 'path' => gestion_chemin(), 'secure' => gestion_https(), 'httponly' => true, 'samesite' => 'Lax']);
}

/** L'adresse de la page affichée, pour y revenir après avoir changé de langue. */
function adresse_courante(): string {
    $p = is_string($_GET['p'] ?? null) && preg_match('/^[a-z\-]{1,30}$/', $_GET['p']) ? $_GET['p'] : (utilisateur_courant() ? 'accueil' : 'connexion');
    $q = [];
    foreach ($_GET as $k => $v) if (is_string($k) && $k !== 'p' && $k !== 'actualiser' && is_string($v) && preg_match('/^[a-zA-Z0-9_\-]{1,30}$/', $k)) $q[$k] = mb_substr($v, 0, 200);
    return u($p, $q);
}
function lien_langue(string $l): string { return u('langue', ['l' => $l, 'retour' => adresse_courante()]); }

/** ?p=langue&l=ko|fr : garde le choix sur l'appareil et revient à la page d'où l'on vient. */
function page_langue(): void {
    $l = is_string($_GET['l'] ?? null) ? $_GET['l'] : '';
    langue_choisir($l);
    $r = is_string($_GET['retour'] ?? null) ? $_GET['retour'] : '';
    if (!preg_match('/^\?p=[a-z\-]{1,30}(&[^\s#<>"\\\\]*)?$/', $r) || str_starts_with($r, '?p=langue')) $r = u(utilisateur_courant() ? 'accueil' : 'connexion');
    header('Cache-Control: no-store, private');
    header('Location: ' . $r);
    exit;
}

/** Le petit bouton de la barre du haut : « FR » quand l'écran est en coréen, « 한국어 » quand il est en français. */
function bouton_langue(): string {
    return coreen_actif()
        ? '<a class="langue" href="' . h(lien_langue('fr')) . '" hreflang="fr" title="프랑스어로 보기 (Français)" translate="no">FR</a>'
        : '<a class="langue" href="' . h(lien_langue('ko')) . '" hreflang="ko" lang="ko" title="Afficher en coréen" translate="no">한국어</a>';
}

/** Rappel, en coréen, que ce qu'on remplit s'écrit en français (sur les pages qui ont une zone de texte). */
function rappel_francais(string $corps): string {
    if (!coreen_actif()) return '';
    if (!preg_match('/<textarea\b|<input type="text" (?![^>]*\bhidden\b)/', $corps)) return '';
    return '<p class="rappel-fr" translate="no">' . icone('crayon') . '<span><b>입력은 프랑스어로 해 주세요.</b> 적은 내용은 이메일, 청구서, 기록에 그대로 쓰여요.</span></p>';
}

/** Les allergènes en coréen, avec le mot français entre parenthèses (celui du site et des étiquettes). */
const ALLERGENES_KO = ['gluten' => '글루텐(Gluten)', 'crustaces' => '갑각류(Crustacés)', 'oeufs' => '달걀(Œufs)', 'poissons' => '생선(Poissons)',
                       'arachides' => '땅콩(Arachides)', 'soja' => '대두(Soja)', 'lait' => '우유(Lait)', 'fruits_a_coque' => '견과류(Fruits à coque)',
                       'celeri' => '셀러리(Céleri)', 'moutarde' => '겨자(Moutarde)', 'sesame' => '참깨(Sésame)', 'sulfites' => '아황산염(Sulfites)',
                       'lupin' => '루핀(Lupin)', 'mollusques' => '연체동물(Mollusques)'];
function allergene_libelle(string $code): string {
    if (!coreen_actif()) return ALLERGENES[$code] ?? $code;
    return str_replace('(', "\u{2060}(", ALLERGENES_KO[$code] ?? ALLERGENES[$code] ?? $code);   // « 달걀(Œufs) » reste sur une seule ligne
}
/** Une liste d'allergènes à afficher (l'historique, lui, les garde en français). */
function allergenes_affiches(array $codes): string { return implode(', ', array_map('MadameCho\\Gestion\\allergene_libelle', $codes)); }

/** La page finie, dans la langue de l'écran. */
function traduire_page(string $html): string {
    if (!coreen_actif()) return $html;
    require_once __DIR__ . '/coreen.php';
    try {
        return coreen_html($html);
    } catch (\Throwable $e) {   // une traduction ratée ne doit jamais bloquer l'écran : il reste en français
        error_log('madamecho gestion coreen : ' . $e->getMessage());
        return $html;
    }
}
