<?php
/*
 * Madame Cho : espace de gestion. QR code dessiné par la gestion elle-même (spécification 003, recherche R7) : le lien privé d'une
 * facture ne passe jamais par un service extérieur. Mode octets, correction M (15 %), versions 1 à 10 (jusqu'à 213 octets : un lien
 * de facture en fait une soixantaine). Sortie en SVG, avec la marge blanche de 4 modules.
 */
declare(strict_types=1);
namespace MadameCho\Gestion;

/** Par version (1 à 10), correction M : [codes de correction par bloc, [nombre de blocs, octets de données par bloc]…]. */
const QR_BLOCS_M = [1 => [10, [1, 16]], 2 => [16, [1, 28]], 3 => [26, [1, 44]], 4 => [18, [2, 32]], 5 => [24, [2, 43]], 6 => [16, [4, 27]],
                    7 => [18, [4, 31]], 8 => [22, [2, 38], [2, 39]], 9 => [22, [3, 36], [2, 37]], 10 => [26, [4, 43], [1, 44]]];
const QR_ALIGNEMENTS = [1 => [], 2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30], 6 => [6, 34], 7 => [6, 22, 38], 8 => [6, 24, 42], 9 => [6, 26, 46], 10 => [6, 28, 50]];

/** La matrice du QR code (tableau de lignes de booléens, true = module sombre), ou null si le texte est trop long. */
function qr_matrice(string $texte, ?int $masqueImpose = null): ?array {
    $n = strlen($texte);
    $version = 0;
    foreach (QR_BLOCS_M as $v => $b) {
        $donnees = 0; foreach (array_slice($b, 1) as [$nb, $oct]) $donnees += $nb * $oct;
        if (4 + ($v < 10 ? 8 : 16) + 8 * $n <= 8 * $donnees) { $version = $v; break; }
    }
    if (!$version) return null;
    $b = QR_BLOCS_M[$version];
    $capacite = 0; foreach (array_slice($b, 1) as [$nb, $oct]) $capacite += $nb * $oct;
    // Bits : mode octets (0100), longueur, octets, fin, remplissage
    $bits = '0100' . str_pad(decbin($n), $version < 10 ? 8 : 16, '0', STR_PAD_LEFT);
    for ($i = 0; $i < $n; $i++) $bits .= str_pad(decbin(ord($texte[$i])), 8, '0', STR_PAD_LEFT);
    $bits .= str_repeat('0', min(4, 8 * $capacite - strlen($bits)));
    $bits .= str_repeat('0', (8 - strlen($bits) % 8) % 8);
    $oct = array_map('bindec', str_split($bits, 8));
    for ($i = 0; count($oct) < $capacite; $i++) $oct[] = $i % 2 ? 0x11 : 0xEC;
    // Blocs et correction d'erreurs (Reed-Solomon sur GF(256), polynôme 0x11D)
    $ec = $b[0]; $blocs = []; $corr = []; $pos = 0;
    foreach (array_slice($b, 1) as [$nb, $taille]) for ($j = 0; $j < $nb; $j++) { $d = array_slice($oct, $pos, $taille); $pos += $taille; $blocs[] = $d; $corr[] = qr_rs($d, $ec); }
    $final = [];
    $max = max(array_map('count', $blocs));
    for ($i = 0; $i < $max; $i++) foreach ($blocs as $d) if (isset($d[$i])) $final[] = $d[$i];
    for ($i = 0; $i < $ec; $i++) foreach ($corr as $c) $final[] = $c[$i];
    // Matrice : motifs fixes, puis données en zigzag
    $t = 17 + 4 * $version;
    $m = array_fill(0, $t, array_fill(0, $t, false));
    $fixe = array_fill(0, $t, array_fill(0, $t, false));
    $poser = function (int $x, int $y, bool $noir) use (&$m, &$fixe, $t) { if ($x >= 0 && $y >= 0 && $x < $t && $y < $t) { $m[$y][$x] = $noir; $fixe[$y][$x] = true; } };
    foreach ([[3, 3], [$t - 4, 3], [3, $t - 4]] as [$cx, $cy])
        for ($dy = -4; $dy <= 4; $dy++) for ($dx = -4; $dx <= 4; $dx++) { $d = max(abs($dx), abs($dy)); $poser($cx + $dx, $cy + $dy, $d !== 2 && $d !== 4); }
    $al = QR_ALIGNEMENTS[$version]; $na = count($al);
    for ($i = 0; $i < $na; $i++) for ($j = 0; $j < $na; $j++) {
        if (($i === 0 && $j === 0) || ($i === 0 && $j === $na - 1) || ($i === $na - 1 && $j === 0)) continue;
        for ($dy = -2; $dy <= 2; $dy++) for ($dx = -2; $dx <= 2; $dx++) $poser($al[$i] + $dx, $al[$j] + $dy, max(abs($dx), abs($dy)) !== 1);
    }
    for ($i = 0; $i < 9; $i++) { $poser(8, $i, false); $poser($i, 8, false); }   // réservés pour le format (écrits plus bas)
    for ($i = 0; $i < 8; $i++) { $poser($t - 1 - $i, 8, false); $poser(8, $t - 1 - $i, false); }
    for ($i = 8; $i < $t - 8; $i++) { $poser($i, 6, $i % 2 === 0); $poser(6, $i, $i % 2 === 0); }
    $poser(8, $t - 8, true);   // module sombre
    if ($version >= 7) {
        $r = $version; for ($i = 0; $i < 12; $i++) $r = ($r << 1) ^ (($r >> 11) * 0x1F25);
        $vi = $version << 12 | $r;
        for ($i = 0; $i < 18; $i++) { $bit = (($vi >> $i) & 1) === 1; $a = $t - 11 + $i % 3; $c = intdiv($i, 3); $poser($a, $c, $bit); $poser($c, $a, $bit); }
    }
    $flux = '';
    foreach ($final as $o) $flux .= str_pad(decbin($o), 8, '0', STR_PAD_LEFT);
    $k = 0; $L = strlen($flux);
    for ($droite = $t - 1; $droite >= 1; $droite -= 2) {
        if ($droite === 6) $droite = 5;
        for ($v = 0; $v < $t; $v++) for ($j = 0; $j < 2; $j++) {
            $x = $droite - $j;
            $haut = (($droite + 1) & 2) === 0;
            $y = $haut ? $t - 1 - $v : $v;
            if (!$fixe[$y][$x]) { $m[$y][$x] = $k < $L && $flux[$k] === '1'; $k++; }
        }
    }
    // Masque : le moins pénalisant des 8
    $meilleur = null; $score = PHP_INT_MAX;
    for ($masque = 0; $masque < 8; $masque++) {
        if ($masqueImpose !== null && $masque !== $masqueImpose) continue;
        $e = qr_masquer($m, $fixe, $masque, $t);
        qr_format($e, $masque, $t);
        $s = qr_penalite($e, $t);
        if ($s < $score) { $score = $s; $meilleur = $e; }
    }
    return $meilleur;
}
function qr_masquer(array $m, array $fixe, int $masque, int $t): array {
    for ($y = 0; $y < $t; $y++) for ($x = 0; $x < $t; $x++) {
        if ($fixe[$y][$x]) continue;
        $inv = match ($masque) {
            0 => ($x + $y) % 2 === 0, 1 => $y % 2 === 0, 2 => $x % 3 === 0, 3 => ($x + $y) % 3 === 0,
            4 => (intdiv($x, 3) + intdiv($y, 2)) % 2 === 0, 5 => ($x * $y) % 2 + ($x * $y) % 3 === 0,
            6 => (($x * $y) % 2 + ($x * $y) % 3) % 2 === 0, default => (($x + $y) % 2 + ($x * $y) % 3) % 2 === 0,
        };
        if ($inv) $m[$y][$x] = !$m[$y][$x];
    }
    return $m;
}
/** Les 15 bits du format (correction M = 00, masque), écrits aux deux endroits. */
function qr_format(array &$m, int $masque, int $t): void {
    $d = (0 << 3) | $masque;
    $r = $d; for ($i = 0; $i < 10; $i++) $r = ($r << 1) ^ (($r >> 9) * 0x537);
    $f = (($d << 10) | $r) ^ 0x5412;
    $bit = fn(int $i) => (($f >> $i) & 1) === 1;
    for ($i = 0; $i <= 5; $i++) $m[$i][8] = $bit($i);
    $m[7][8] = $bit(6); $m[8][8] = $bit(7); $m[8][7] = $bit(8);
    for ($i = 9; $i < 15; $i++) $m[8][14 - $i] = $bit($i);
    for ($i = 0; $i < 8; $i++) $m[8][$t - 1 - $i] = $bit($i);
    for ($i = 8; $i < 15; $i++) $m[$t - 15 + $i][8] = $bit($i);
    $m[$t - 8][8] = true;
}
/** Pénalité simplifiée (suites de même couleur, carrés 2×2, équilibre) : suffit à éviter les masques les plus difficiles à lire. */
function qr_penalite(array $m, int $t): int {
    $p = 0; $noirs = 0;
    for ($y = 0; $y < $t; $y++) {
        $sx = 1; $sy = 1;
        for ($x = 0; $x < $t; $x++) {
            if ($m[$y][$x]) $noirs++;
            if ($x > 0) { if ($m[$y][$x] === $m[$y][$x - 1]) { $sx++; if ($sx === 5) $p += 3; elseif ($sx > 5) $p++; } else $sx = 1; }
            if ($x > 0) { if ($m[$x][$y] === $m[$x - 1][$y]) { $sy++; if ($sy === 5) $p += 3; elseif ($sy > 5) $p++; } else $sy = 1; }
            if ($x > 0 && $y > 0 && $m[$y][$x] === $m[$y - 1][$x] && $m[$y][$x] === $m[$y][$x - 1] && $m[$y][$x] === $m[$y - 1][$x - 1]) $p += 3;
        }
    }
    return $p + intdiv(abs(20 * $noirs - 10 * $t * $t), $t * $t) * 10;
}
/** Codes de correction Reed-Solomon de $donnees ($n octets). */
function qr_rs(array $donnees, int $n): array {
    static $exp = null, $log = null;
    if ($exp === null) {
        $exp = array_fill(0, 512, 0); $log = array_fill(0, 256, 0); $x = 1;
        for ($i = 0; $i < 255; $i++) { $exp[$i] = $x; $log[$x] = $i; $x <<= 1; if ($x & 0x100) $x ^= 0x11D; }
        for ($i = 255; $i < 512; $i++) $exp[$i] = $exp[$i - 255];
    }
    $mul = fn(int $a, int $b) => $a && $b ? $exp[$log[$a] + $log[$b]] : 0;
    $g = [1];   // générateur : produit des (x - α^i)
    for ($i = 0; $i < $n; $i++) {
        $ng = array_fill(0, count($g) + 1, 0);
        foreach ($g as $j => $c) { $ng[$j] ^= $c; $ng[$j + 1] ^= $mul($c, $exp[$i]); }
        $g = $ng;
    }
    $r = array_fill(0, $n, 0);
    foreach ($donnees as $d) {
        $f = $d ^ $r[0];
        array_shift($r); $r[] = 0;
        for ($j = 0; $j < $n; $j++) $r[$j] ^= $mul($g[$j + 1], $f);
    }
    return $r;
}
/** Le QR code en SVG (modules carrés, marge de 4), à la taille voulue en pixels. */
function qr_svg(string $texte, int $taille = 240, string $titre = 'QR code'): string {
    $m = qr_matrice($texte);
    if (!$m) return '';
    $t = count($m); $c = $t + 8; $d = '';
    foreach ($m as $y => $l) foreach ($l as $x => $noir) if ($noir) $d .= 'M' . ($x + 4) . ' ' . ($y + 4) . 'h1v1h-1z';
    return '<svg class="qr" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $c . ' ' . $c . '" width="' . $taille . '" height="' . $taille . '" role="img" aria-label="' . h($titre) . '" shape-rendering="crispEdges">'
         . '<rect width="' . $c . '" height="' . $c . '" fill="#fff"/><path d="' . $d . '" fill="#000"/></svg>';
}
