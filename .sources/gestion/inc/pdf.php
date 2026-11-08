<?php
/*
 * Madame Cho : espace de gestion. Petit écrivain PDF, sans bibliothèque (spécification 003, recherche R5) : pages A4, polices
 * standard du PDF (Helvetica et Helvetica-Bold, encodage WinAnsi : accents français et « € »), texte aligné à gauche, à droite ou
 * centré, traits et aplats. Sert aux factures et aux avoirs, en français seulement (FR-016). Coordonnées en points, depuis le haut.
 */
declare(strict_types=1);
namespace MadameCho\Gestion;

const PDF_LARGEURS = [278,278,355,556,556,889,667,191,333,333,389,584,278,333,278,278,556,556,556,556,556,556,556,556,556,556,278,278,584,584,584,556,1015,667,667,722,722,667,611,778,722,278,500,667,556,833,722,778,667,778,722,667,611,722,667,944,667,667,611,278,278,278,469,556,333,556,556,500,556,556,278,556,556,222,222,500,222,833,556,556,556,556,333,500,278,556,500,722,500,500,500,334,260,334,584,761,556,556,222,556,333,1000,556,556,333,1000,667,333,1000,556,611,556,556,222,222,333,333,350,556,1000,333,1000,500,333,944,556,500,667,278,333,556,556,556,556,260,556,333,737,370,556,584,333,737,333,400,584,333,333,333,556,537,278,333,333,365,556,834,834,834,611,667,667,667,667,667,667,1000,722,667,667,667,667,278,278,278,278,722,722,778,778,778,778,778,584,778,722,722,722,722,667,667,611,556,556,556,556,556,556,889,500,556,556,556,556,278,278,278,278,556,556,556,556,556,556,556,584,611,556,556,556,556,500,556,500];
const PDF_LARGEURS_GRAS = [278,333,474,556,556,889,722,238,333,333,389,584,278,333,278,278,556,556,556,556,556,556,556,556,556,556,333,333,584,584,584,611,975,722,722,722,722,667,611,778,722,278,556,722,611,833,722,778,667,778,722,667,611,722,667,944,667,667,611,333,278,333,584,556,333,556,611,556,611,556,333,611,611,278,278,556,278,889,611,611,611,611,389,556,333,611,556,778,556,556,500,389,280,389,584,761,556,556,278,556,500,1000,556,556,333,1000,667,333,1000,556,611,556,556,278,278,500,500,350,556,1000,333,1000,556,333,944,556,500,667,278,333,556,556,556,556,280,556,333,737,370,556,584,333,737,333,400,584,333,333,333,611,556,278,333,333,365,556,834,834,834,611,722,722,722,722,722,722,1000,722,667,667,667,667,278,278,278,278,722,722,778,778,778,778,778,584,778,722,722,722,722,667,667,611,556,556,556,556,556,556,889,556,556,556,556,556,278,278,278,278,611,611,611,611,611,611,611,584,611,611,611,611,611,556,611,556];

final class Pdf {
    public const L = 595.28;   // A4 en points
    public const H = 841.89;
    private array $pages = [];
    private int $page = -1;
    public function __construct(private string $titre = '', private string $date = '') { $this->nouvelle_page(); }
    public function nouvelle_page(): void { $this->pages[] = ''; $this->page = count($this->pages) - 1; }
    public function nombre_pages(): int { return count($this->pages); }
    public function aller_page(int $n): void { $this->page = max(0, min($n, count($this->pages) - 1)); }
    /** UTF-8 vers WinAnsi ; ce qui n'y entre pas devient « ? » (le coréen n'entre jamais dans une facture). */
    public static function winansi(string $s): string {
        $s = strtr($s, ["\u{202F}" => "\u{A0}", "\u{2212}" => '-', "\u{2009}" => ' ', "\u{2011}" => '-', "\t" => ' ', "\r" => '', "\n" => ' ']);
        $r = @mb_convert_encoding($s, 'Windows-1252', 'UTF-8');
        return is_string($r) ? $r : preg_replace('/[^\x20-\x7E]/', '?', $s);
    }
    /** Le texte s'écrit-il tel quel dans le PDF ? (polices standard : pas de lettres coréennes, d'émoji…, qui sortiraient en « ? ») */
    public static function ecrivable(string $s): bool {
        $n = strtr($s, ["\u{202F}" => "\u{A0}", "\u{2212}" => '-', "\u{2009}" => ' ', "\u{2011}" => '-', "\t" => ' ', "\r" => '', "\n" => ' ']);
        $r = @mb_convert_encoding($n, 'Windows-1252', 'UTF-8');
        return is_string($r) && mb_convert_encoding($r, 'UTF-8', 'Windows-1252') === $n;
    }
    public static function largeur(string $s, float $taille, bool $gras = false): float {
        $w = 0; $t = $gras ? PDF_LARGEURS_GRAS : PDF_LARGEURS;
        foreach (str_split(self::winansi($s)) as $ch) { $o = ord($ch); $w += $o >= 32 ? $t[$o - 32] : 0; }
        return $w * $taille / 1000;
    }
    /** Coupe un texte en lignes qui tiennent dans $max points (mots entiers, un mot trop long est coupé). */
    public static function couper(string $s, float $max, float $taille, bool $gras = false): array {
        $l = []; $cour = '';
        foreach (preg_split('/\s+/u', trim($s)) ?: [] as $mot) {
            $essai = $cour === '' ? $mot : $cour . ' ' . $mot;
            if (self::largeur($essai, $taille, $gras) <= $max) { $cour = $essai; continue; }
            if ($cour !== '') $l[] = $cour;
            while (self::largeur($mot, $taille, $gras) > $max && mb_strlen($mot) > 1) {
                $n = mb_strlen($mot);
                while ($n > 1 && self::largeur(mb_substr($mot, 0, $n), $taille, $gras) > $max) $n--;
                $l[] = mb_substr($mot, 0, $n); $mot = mb_substr($mot, $n);
            }
            $cour = $mot;
        }
        if ($cour !== '') $l[] = $cour;
        return $l ?: [''];
    }
    private static function nb(float $n): string { return rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.') ?: '0'; }
    private static function couleur(array $c): string { return implode(' ', array_map(fn($v) => self::nb($v / 255), $c)); }
    /** $align : g (gauche, x = début), d (droite, x = fin), c (centré sur x). $y : ligne de base, depuis le haut. */
    public function texte(float $x, float $y, string $s, float $taille = 10, bool $gras = false, string $align = 'g', array $couleur = [43, 38, 35]): void {
        if ($s === '') return;
        if ($align !== 'g') { $w = self::largeur($s, $taille, $gras); $x -= $align === 'd' ? $w : $w / 2; }
        $e = strtr(self::winansi($s), ['\\' => '\\\\', '(' => '\\(', ')' => '\\)']);
        $this->pages[$this->page] .= 'BT ' . self::couleur($couleur) . ' rg /' . ($gras ? 'F2' : 'F1') . ' ' . self::nb($taille) . ' Tf '
            . self::nb($x) . ' ' . self::nb(self::H - $y) . ' Td (' . $e . ") Tj ET\n";
    }
    public function trait(float $x1, float $y1, float $x2, float $y2, float $epaisseur = 0.5, array $couleur = [221, 211, 198]): void {
        $this->pages[$this->page] .= self::couleur($couleur) . ' RG ' . self::nb($epaisseur) . ' w ' . self::nb($x1) . ' ' . self::nb(self::H - $y1) . ' m '
            . self::nb($x2) . ' ' . self::nb(self::H - $y2) . " l S\n";
    }
    /** Aplat : $y = haut du rectangle. */
    public function aplat(float $x, float $y, float $l, float $h, array $couleur = [243, 237, 228]): void {
        $this->pages[$this->page] .= self::couleur($couleur) . ' rg ' . self::nb($x) . ' ' . self::nb(self::H - $y - $h) . ' ' . self::nb($l) . ' ' . self::nb($h) . " re f\n";
    }
    /** Le fichier PDF (1.4), flux compressés ; même contenu et même date, mêmes octets (rien d'aléatoire). */
    public function sortie(): string {
        $obj = [];
        $obj[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $n = count($this->pages); $kids = [];
        $obj[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $obj[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        $d = preg_replace('/[^0-9]/', '', $this->date);
        $obj[5] = '<< /Title (' . strtr(self::winansi($this->titre), ['\\' => '\\\\', '(' => '\\(', ')' => '\\)']) . ') /Producer (Gestion Madame Cho)'
                . (strlen($d) >= 14 ? ' /CreationDate (D:' . substr($d, 0, 14) . ')' : '') . ' >>';
        foreach ($this->pages as $i => $flux) {
            $p = 6 + 2 * $i; $kids[] = $p . ' 0 R';
            $z = (string)gzcompress($flux, 9);
            $obj[$p] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . self::nb(self::L) . ' ' . self::nb(self::H) . '] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents ' . ($p + 1) . ' 0 R >>';
            $obj[$p + 1] = "<< /Length " . strlen($z) . " /Filter /FlateDecode >>\nstream\n" . $z . "\nendstream";
        }
        $obj[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $n . ' >>';
        ksort($obj);
        $out = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n"; $pos = [];
        foreach ($obj as $i => $o) { $pos[$i] = strlen($out); $out .= $i . " 0 obj\n" . $o . "\nendobj\n"; }
        $x = strlen($out);
        $out .= "xref\n0 " . (count($obj) + 1) . "\n0000000000 65535 f \n";
        foreach ($obj as $i => $o) $out .= sprintf("%010d 00000 n \n", $pos[$i]);
        return $out . "trailer\n<< /Size " . (count($obj) + 1) . " /Root 1 0 R /Info 5 0 R >>\nstartxref\n" . $x . "\n%%EOF\n";
    }
}
