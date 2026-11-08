<?php
/*
 * Madame Cho : espace de gestion. Statistiques, menu « Plus », paramètres (compte, comptes, emails, conservation RGPD,
 * miroir pour le Drive, reprise des données, export complet, contrôle technique), exports CSV et miroir.
 */
declare(strict_types=1);
namespace MadameCho\Gestion;

/* ---------- Statistiques ---------- */
/** Petit graphique en barres (SVG, une seule série) : $points = [[étiquette, valeur, infobulle]]. */
function barres(array $points, string $titre, bool $valeurs = true): string {
    $max = max(1, ...array_map(fn($p) => (int)$p[1], $points));
    $n = count($points);
    $l = 100 / max(1, $n);
    $s = '';
    foreach ($points as $i => [$etiq, $v, $info]) {
        $hauteur = $v > 0 ? max(1.5, $v / $max * 100) : 0;
        $x = $i * $l + $l * 0.18;
        $s .= '<g><title>' . h($info) . '</title><rect class="fond-barre" x="' . round($i * $l, 3) . '" y="0" width="' . round($l, 3) . '" height="100"/>'
            . ($hauteur ? '<rect class="barre" x="' . round($x, 3) . '" y="' . round(100 - $hauteur, 3) . '" width="' . round($l * 0.64, 3) . '" height="' . round($hauteur, 3) . '" rx="0.8"/>' : '') . '</g>';
    }
    $etiquettes = '';
    $dernier = $n - 1;
    foreach ($points as $i => [$etiq, $v]) {
        $valeur = $valeurs && $v > 0 && ($v === $max || $i === $dernier) ? '<b>' . h($v >= 100000 ? number_format($v / 100000, 1, ',', '') . ' k€' : euros($v)) . '</b>' : '';
        $etiquettes .= '<span>' . h($etiq) . $valeur . '</span>';
    }
    return '<figure class="graphique"><svg viewBox="0 0 100 100" preserveAspectRatio="none" role="img" aria-label="' . h($titre) . '">' . $s . '<line class="axe" x1="0" y1="100" x2="100" y2="100"/></svg>'
         . '<div class="etiquettes n' . $n . '">' . $etiquettes . '</div></figure>';
}

/** Onglets de Statistiques : les ventes de la gestion, et les visites du site (Matomo). */
function onglets_statistiques(string $vue): string {
    $s = '';
    foreach (['ventes' => [u('statistiques'), 'Ventes'], 'site' => [u('statistiques', ['vue' => 'site']), 'Site internet']] as $k => [$href, $nom]) {
        $s .= '<a href="' . h($href) . '"' . ($k === $vue ? ' aria-current="page"' : '') . '>' . h($nom) . '</a>';
    }
    return '<nav class="sous-onglets" aria-label="Statistiques">' . $s . '</nav>';
}

/* ---------- HT ou TTC ---------- */
// Les montants comparés (statistiques, ventes d'un gâteau, tuiles du mois) : tous HT ou tous TTC, jamais mélangés (demande d'Olivier,
// 07/11/2026). HT par défaut ; le choix est gardé sur l'appareil, comme la langue (cookie, sinon la session).
const TAXE_COOKIE = 'mc_taxe';
const TAXES = ['ht' => 'HT', 'ttc' => 'TTC'];
function taxe(): string {
    foreach ([$_COOKIE[TAXE_COOKIE] ?? '', $_SESSION[TAXE_COOKIE] ?? ''] as $t) if (is_string($t) && isset(TAXES[$t])) return $t;
    return 'ht';
}
/** ?p=taxe&t=ht|ttc : garde le choix sur l'appareil et revient à la page d'où l'on vient. */
function page_taxe(): void {
    $t = is_string($_GET['t'] ?? null) ? $_GET['t'] : '';
    if (isset(TAXES[$t])) {
        $_COOKIE[TAXE_COOKIE] = $t;
        if (session_status() === PHP_SESSION_ACTIVE) $_SESSION[TAXE_COOKIE] = $t;
        if (!headers_sent()) setcookie(TAXE_COOKIE, $t, ['expires' => time() + 400 * 86400, 'path' => gestion_chemin(), 'secure' => gestion_https(), 'httponly' => true, 'samesite' => 'Lax']);
    }
    $r = is_string($_GET['retour'] ?? null) ? $_GET['retour'] : '';
    if (!preg_match('/^\?p=[a-z\-]{1,30}(&[^\s#<>"\\\\]*)?$/', $r) || str_starts_with($r, '?p=taxe')) $r = u('statistiques');
    header('Cache-Control: no-store, private');
    header('Location: ' . $r);
    exit;
}
/** Le choix « Montants HT | TTC », en haut des statistiques et des ventes d'un gâteau. */
function choix_taxe(): string {
    $s = '';
    foreach (TAXES as $k => $nom) $s .= '<a href="' . h(u('taxe', ['t' => $k, 'retour' => adresse_courante()])) . '"' . ($k === taxe() ? ' aria-current="true"' : '') . '>' . $nom . '</a>';
    return '<nav class="choix-taxe" aria-label="Montants"><span>Montants</span>' . $s . '</nav>';
}

/** Le chiffre d'affaires de chaque mois (demande d'Olivier, 07/11/2026) : particuliers et professionnels empilés dans la même colonne,
 *  à la même échelle et dans la même base (HT ou TTC) : la hauteur dit le total du mois, les deux couleurs la part de chacun. Le total
 *  est écrit sur chaque mois, et chaque mois mène à ses commandes remises. Sur un téléphone, les mois passent en lignes (barres
 *  horizontales) pour que chaque montant reste lisible. Tout en SVG et en classes : la politique de sécurité interdit les styles en ligne.
 *  $mois : [cle (AAAA-MM), part, pro (centimes)], du plus ancien au plus récent. */
function colonnes_ca(array $mois, string $T, callable $lien): string {
    $max = max(1, ...array_map(fn($m) => $m['part'] + $m['pro'], $mois));
    $H = 170;   // hauteur des barres (unités du dessin) ; 20 de plus au-dessus pour le montant
    $colonnes = ''; $lignes = '';
    foreach ($mois as $m) {
        $total = $m['part'] + $m['pro'];
        $mo = (int)substr($m['cle'], 5, 2);
        $info = h(ca_mois_texte($m, $T));
        $a = '<a href="' . h($lien($m['cle'])) . '" title="' . $info . '" aria-label="' . $info . '"';
        $montant = h($total > 0 ? euros_ronds($total) : '—');
        // Colonne : les professionnels en bas, les particuliers au-dessus, 2 d'écart ; bout arrondi en haut, carré sur la ligne de base
        $h = $total > 0 ? max(3.0, $total / $max * $H) : 0.0;
        $hPro = $m['pro'] > 0 ? ($m['part'] > 0 ? max(2.0, ($h - 2) * $m['pro'] / $total) : $h) : 0.0;
        $hPart = $m['part'] > 0 ? ($m['pro'] > 0 ? max(2.0, $h - 2 - $hPro) : $h) : 0.0;
        $base = 20 + $H;
        $d = '';
        $haut = function (float $y0, float $hh, string $classe): string {   // le segment du haut : coins arrondis en haut seulement
            $r = min(4.0, $hh, 12.0); $x = 8; $w = 24; $y = round($y0, 2); $b = round($y0 + $hh, 2);
            return '<path class="' . $classe . '" d="M' . $x . ' ' . $b . 'V' . round($y + $r, 2) . 'Q' . $x . ' ' . $y . ' ' . ($x + $r) . ' ' . $y . 'H' . ($x + $w - $r) . 'Q' . ($x + $w) . ' ' . $y . ' ' . ($x + $w) . ' ' . round($y + $r, 2) . 'V' . $b . 'Z"/>';
        };
        if ($hPro > 0) $d .= $hPart > 0 ? '<rect class="seg-pro" x="8" y="' . round($base - $hPro, 2) . '" width="24" height="' . round($hPro, 2) . '"/>' : $haut($base - $hPro, $hPro, 'seg-pro');
        if ($hPart > 0) $d .= $haut($base - $h, $hPart, 'seg-part');
        $d .= '<text x="20" y="' . round($base - $h - 5, 2) . '" text-anchor="middle"' . ($total > 0 ? '' : ' class="vide"') . '>' . $montant . '</text>';
        $colonnes .= '<li>' . $a . ' class="colonne"><svg viewBox="0 0 40 ' . $base . '" preserveAspectRatio="xMidYMax meet" aria-hidden="true" focusable="false">' . $d . '</svg>'
                   . '<span class="mois" aria-hidden="true">' . h(MOIS_COURTS[$mo]) . '</span></a></li>';
        // Ligne (téléphone) : la même barre, couchée ; largeur sur 100
        $w = $total > 0 ? max(1.5, $total / $max * 100) : 0.0;
        $wPro = $m['pro'] > 0 ? $w * $m['pro'] / $total : 0.0;
        $r = ($wPro > 0 ? '<rect class="seg-pro" x="0" y="0" width="' . round($wPro, 3) . '" height="12"/>' : '')
           . ($m['part'] > 0 ? '<rect class="seg-part" x="' . round($wPro > 0 ? $wPro + 0.8 : 0, 3) . '" y="0" width="' . round(max(0.6, $w - $wPro - ($wPro > 0 ? 0.8 : 0)), 3) . '" height="12"/>' : '');
        $lignes .= '<li>' . $a . ' class="ligne-ca"><span class="mois" aria-hidden="true">' . h(MOIS_COURTS[$mo]) . '</span>'
                 . '<svg viewBox="0 0 100 12" preserveAspectRatio="none" aria-hidden="true" focusable="false">' . $r . '</svg><b aria-hidden="true">' . $montant . '</b></a></li>';
    }
    return '<div class="ca-mois"><p class="legende"><span><i class="cle pro"></i>Professionnels (B2B)</span><span><i class="cle part"></i>Particuliers (B2C)</span><span class="petit">montants ' . h($T) . '</span></p>'
         . '<ol class="colonnes">' . $colonnes . '</ol><ol class="lignes-ca">' . $lignes . '</ol></div>';
}
/** « Juillet 2026 : 1 013 € HT · particuliers 120 € (12 %) · professionnels 893 € (88 %) » : l'infobulle et le nom lu de chaque mois. */
function ca_mois_texte(array $m, string $T): string {
    $mo = (int)substr($m['cle'], 5, 2);
    $nom = ucfirst(MOIS[$mo]) . ' ' . substr($m['cle'], 0, 4);
    $total = $m['part'] + $m['pro'];
    if (!$total) return $nom . ' : aucune vente remise';
    $pc = fn(int $v): string => (string)round($v / $total * 100) . ' %';
    return $nom . ' : ' . euros_ronds($total) . ' ' . $T . ' · particuliers ' . euros_ronds($m['part']) . ' (' . $pc($m['part']) . ') · professionnels ' . euros_ronds($m['pro']) . ' (' . $pc($m['pro']) . ')';
}

function page_statistiques(): void {
    if (($_GET['vue'] ?? '') === 'site') { page_statistiques_site(); return; }
    $s = statistiques();
    $t = taxe(); $T = TAXES[$t];
    $P = $s['groupes']['part']; $B = $s['groupes']['pro'];
    $dp = $s['demandes']['part'];
    $transfo = ($dp['remises'] + $dp['perdues']) ? $dp['remises'] / ($dp['remises'] + $dp['perdues']) : null;
    $rep = $s['reponse'];
    $avis = ligne("SELECT SUM(CASE WHEN avis_demande_le IS NOT NULL THEN 1 ELSE 0 END) AS demandes, SUM(CASE WHEN avis_recu_le IS NOT NULL THEN 1 ELSE 0 END) AS recus FROM clients WHERE statut <> 'efface'") ?? [];
    // Chaque chiffre mène à la liste qui le détaille (demande d'Olivier, 07/11/2026)
    $remises = fn(array $f = []) => u('commandes', ['vue' => 'toutes', 'statut' => 'remise'] + $f);
    $c = titre_page('Statistiques', 'Depuis le début · montants ' . $T, choix_taxe(), u('plus')) . onglets_statistiques('ventes');
    $c .= '<div class="tuiles">'
        . tuile('Chiffre d\'affaires total', euros($P[$t] + $B[$t]), $T . ', tous clients', $remises())
        . tuile('Particuliers, ' . $T, euros($P[$t]), $P['n'] . ($P['n'] > 1 ? ' commandes remises' : ' commande remise'), $remises(['groupe' => 'particuliers']))
        . tuile('Professionnels, ' . $T, euros($B[$t]), $B['n'] . ($B['n'] > 1 ? ' livraisons remises' : ' livraison remise'), $remises(['groupe' => 'pros']))
        . tuile('Panier moyen, particuliers', $P['n'] ? euros(intdiv($P[$t], $P['n'])) : '—', $T, $remises(['groupe' => 'particuliers']))
        . tuile('Livraison moyenne, professionnels', $B['n'] ? euros(intdiv($B[$t], $B['n'])) : '—', $T, $remises(['groupe' => 'pros']))
        . tuile('Demandes abouties, particuliers', $transfo === null ? '—' : number_format($transfo * 100, 0) . ' %', 'des demandes closes', u('commandes', ['vue' => 'terminees', 'groupe' => 'particuliers']))
        . tuile('Réponse aux demandes du site', $rep['mediane'] === null ? '—' : ($rep['mediane'] < 1 ? 'moins d\'1 h' : number_format($rep['mediane'], 0) . ' h'), $rep['n'] ? $rep['sous_24h'] . ' sur ' . $rep['n'] . ' en moins de 24 h' : 'médiane', u('commandes', ['vue' => 'toutes', 'canal' => 'site']))
        . tuile('Clients', (string)(int)$s['clients']['n'], (int)$s['clients']['pros'] . ' professionnel(s)', u('clients'))
        . tuile('Clients fidèles', (string)$s['fideles'], 'particuliers, 2 commandes ou plus', u('clients', ['f' => 'particuliers']))
        . tuile('Avis Google', (string)(int)($avis['recus'] ?? 0), (int)($avis['demandes'] ?? 0) . ' demandé(s)', u('clients'))
        . '</div>';
    // Chiffre d'affaires des 12 derniers mois (mois sans vente compris) : un seul graphique, particuliers et pros empilés à la même échelle
    $mois = [];
    for ($i = 11; $i >= 0; $i--) {
        $cle = date('Y-m', strtotime(date('Y-m-01') . " -$i months"));
        $x = $s['mois'][$cle] ?? null;
        $mois[] = ['cle' => $cle, 'part' => (int)($x['part'][$t] ?? 0), 'pro' => (int)($x['pro'][$t] ?? 0)];
    }
    $graphe = array_sum(array_map(fn($m) => $m['part'] + $m['pro'], $mois)) > 0 ? colonnes_ca($mois, $T, fn(string $cle) => $remises(['mois' => $cle])) : '';
    $rows = '';
    foreach ($s['mois'] as $cle => $x) {
        $mo = (int)substr((string)$cle, 5, 2);
        $rows .= '<tr><td><a href="' . h($remises(['mois' => (string)$cle])) . '">' . h(ucfirst(MOIS[$mo]) . ' ' . substr((string)$cle, 0, 4)) . '</a></td>'
               . '<td class="nombre">' . h($x['part']['n'] ? euros_ronds($x['part'][$t]) : '—') . '</td>'
               . '<td class="nombre">' . h($x['part']['n'] ? euros_ronds(intdiv($x['part'][$t], $x['part']['n'])) : '—') . '</td>'
               . '<td class="nombre">' . h($x['pro']['n'] ? euros_ronds($x['pro'][$t]) : '—') . '</td>'
               . '<td class="nombre">' . h(euros_ronds($x['part'][$t] + $x['pro'][$t])) . '</td></tr>';
    }
    $c .= bloc('Chiffre d\'affaires par mois', $graphe
        . ($rows !== '' ? '<details class="replie"' . ($graphe !== '' ? '' : ' open') . '><summary>Le tableau</summary><div class="defile"><table class="tableau"><thead><tr><th>Mois</th><th class="nombre">' . h('Particuliers, ' . $T) . '</th><th class="nombre">Panier</th><th class="nombre">' . h('Professionnels, ' . $T) . '</th><th class="nombre">' . h('Total, ' . $T) . '</th></tr></thead><tbody>' . $rows . '</tbody></table></div>'
            . '</details>' : '<p class="vide">Pas encore de commande remise.</p>'), '', 'ca-mois');
    // Canaux : les demandes de chaque canal, et ce qu'elles ont rapporté, groupe par groupe
    $rows = '';
    foreach ($s['par_canal'] as $k) {
        $m = $s['canaux'][$k['canal']] ?? [];
        $rows .= '<tr><td><a href="' . h(u('commandes', ['vue' => 'toutes', 'canal' => (string)$k['canal']])) . '">' . h(CANAUX[$k['canal']] ?? $k['canal']) . '</a></td><td class="nombre">' . (int)$k['n'] . '</td><td class="nombre">' . (int)$k['remises'] . '</td><td class="nombre">' . h(pourcent((int)$k['remises'], (int)$k['n'])) . '</td>'
               . '<td class="nombre">' . h(isset($m['part']) ? euros_ronds((int)$m['part'][$t]) : '—') . '</td><td class="nombre">' . h(isset($m['pro']) ? euros_ronds((int)$m['pro'][$t]) : '—') . '</td></tr>';
    }
    if ($rows !== '') $c .= bloc('D\'où viennent les commandes', '<div class="defile"><table class="tableau"><thead><tr><th>Canal</th><th class="nombre">Demandes</th><th class="nombre">Remises</th><th class="nombre">Abouties</th><th class="nombre">' . h('Particuliers, ' . $T) . '</th><th class="nombre">' . h('Professionnels, ' . $T) . '</th></tr></thead><tbody>' . $rows . '</tbody></table></div>');
    // Ventes par gâteau (demande d'Olivier, 07/11/2026) : particuliers (B2C) et professionnels (B2B) côte à côte, dans la même base, et depuis quand chaque gâteau est proposé
    $c .= bloc('Ventes par gâteau', tableau_ventes_gateaux($s['produits'], $t), '', 'gateaux');
    // Étapes
    $rows = '';
    usort($s['statuts'], fn($a, $b) => array_search($a['statut'], array_keys(STATUTS), true) <=> array_search($b['statut'], array_keys(STATUTS), true));
    foreach ($s['statuts'] as $k) $rows .= '<tr><td><a class="lien-chip" href="' . h(u('commandes', ['vue' => 'toutes', 'statut' => (string)$k['statut']])) . '">' . chip_statut($k['statut'], false) . '</a></td><td class="nombre">' . (int)$k['n'] . '</td></tr>';
    if ($rows !== '') $c .= bloc('Où en sont les demandes', '<table class="tableau"><tbody>' . $rows . '</tbody></table>');
    $c .= bloc('Exporter', '<p class="aide">Pour un tableur, sans email ni téléphone. Contient des noms de clients : à ranger dans le Drive de Madame Cho.</p><div class="actions">'
        . lien_bouton(u('export', ['jeu' => 'commandes']), 'Commandes (CSV)', 'discret') . lien_bouton(u('export', ['jeu' => 'clients']), 'Clients (CSV)', 'discret') . '</div>');
    mise_en_page('Statistiques', $c, 'statistiques');
}

/* ---------- Statistiques du site (Matomo) ---------- */
/** Le tableau « Ventes par gâteau » : chaque gâteau de la carte, de la gamme pro ou déjà vendu, ses ventes aux particuliers (B2C)
 *  et aux professionnels (B2B), dans la même base ($t : ht ou ttc), et depuis quand il est proposé. Trié par chiffre d'affaires, puis par nom. */
function tableau_ventes_gateaux(array $sp, string $t = 'ht'): string {
    $T = TAXES[$t] ?? 'HT';
    $L = [];
    foreach (produits_lister() as $p) {
        $vendu = isset($sp['part'][$p['id']]) || isset($sp['pro'][$p['id']]);
        if ($p['statut'] === 'retire' && !$vendu) continue;
        $L[(string)$p['id']] = ['nom' => (string)$p['nom'], 'fiche' => true, 'depuis' => produit_propose_depuis($p), 'retire' => $p['statut'] === 'retire'];
    }
    // Gâteau sans fiche (ancien produit des factures reprises) : « proposé depuis » d'après la première commande qui le contient
    $premieres = produits_premieres_demandes();
    foreach (['part', 'pro'] as $g) foreach ($sp[$g] as $pid => $x) $L[(string)$pid] ??= ['nom' => (string)$x['nom'], 'fiche' => false,
        'depuis' => isset($premieres[(string)$pid]) ? ['date' => $premieres[(string)$pid], 'source' => 'demande'] : null, 'retire' => false];
    $vide = ['q' => 0, 'ht' => 0, 'ttc' => 0];
    foreach ($L as $pid => &$l) {
        $l['part'] = ($sp['part'][$pid] ?? []) + $vide; $l['pro'] = ($sp['pro'][$pid] ?? []) + $vide;
        $l['tri'] = (int)$l['part'][$t] + (int)$l['pro'][$t];
    }
    unset($l);
    uasort($L, fn($a, $b) => [$b['tri'], mb_strtolower($a['nom'])] <=> [$a['tri'], mb_strtolower($b['nom'])]);
    if (!$L) return '<p class="vide">Pas encore de gâteau ni de vente.</p>';
    $cellule = fn(array $x): string => (int)$x['q'] ? '<b>' . (int)$x['q'] . '</b><br><span class="petit">' . h(euros_ronds((int)$x[$t]) . ' ' . $T) . '</span>' : '—';
    $rows = ''; $tot = ['part' => $vide, 'pro' => $vide]; $estime = false;
    foreach ($L as $pid => $l) {
        $nom = $l['fiche'] && preg_match('/^[a-z0-9\-]{1,60}$/', (string)$pid) ? '<a href="' . h(u('produit', ['id' => (string)$pid])) . '">' . h($l['nom']) . '</a>' : h($l['nom']);
        $d = $l['depuis'];
        if ($d && $d['source'] === 'demande') $estime = true;
        $depuis = $d ? h(mois_annee($d['date'])) . ($d['source'] === 'demande' ? '<sup>*</sup>' : '') . '<br><span class="petit">' . h(duree_depuis($d['date'])) . '</span>' : '—';
        $rows .= '<tr><td><span translate="no">' . $nom . '</span>' . ($l['retire'] ? ' <span class="petit">(retiré)</span>' : '') . '</td>'
               . '<td class="nombre">' . $cellule($l['part']) . '</td><td class="nombre">' . $cellule($l['pro']) . '</td><td>' . $depuis . '</td></tr>';
        foreach (['part', 'pro'] as $g) foreach ($vide as $k => $_) $tot[$g][$k] += (int)$l[$g][$k];
    }
    return '<div class="defile"><table class="tableau ventes-gateaux"><thead><tr><th>Gâteau</th><th class="nombre">Particuliers (B2C)</th><th class="nombre">Pros (B2B)</th><th>Proposé depuis</th></tr></thead>'
        . '<tbody>' . $rows . '</tbody><tfoot><tr><td>Total</td><td class="nombre">' . $cellule($tot['part']) . '</td><td class="nombre">' . $cellule($tot['pro']) . '</td><td></td></tr></tfoot></table></div>'
        . '<p class="aide">' . h('Commandes remises, depuis le début, montants ' . $T . '.') . ' ' . h('Gâteaux personnalisés hors carte, remises et régularisations : comptés seulement dans le chiffre d\'affaires.') . '</p>'
        . ($estime ? '<p class="aide">* D\'après la première demande qui contient ce gâteau ; la date exacte se note dans sa fiche technique.</p>' : '');
}
function entier_fr(float $n): string { return number_format($n, 0, ',', "\u{202F}"); }
function visites_texte(int $v): string { return entier_fr($v) . ' visite' . ($v > 1 ? 's' : ''); }
/** Durée lisible : 75 s → « 1 min 15 s ». */
function duree_lisible(float $s): string {
    $s = (int)round($s);
    if ($s < 60) return $s . ' s';
    return intdiv($s, 60) . ' min' . ($s % 60 ? ' ' . ($s % 60) . ' s' : '');
}
/** Titre d'une page du site, sans la fin commune « · Madame Cho ». */
function titre_page_site(string $t): string {
    $t = trim(html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if (preg_match('/^Madame Cho, pâtisserie/u', $t)) return 'Accueil';
    $t = preg_replace(['/\s*·\s*Madame Cho$/u', '/ sur commande à Meudon$/u'], '', $t) ?? $t;
    return $t !== '' ? $t : 'Page sans titre';
}
/** Tableau de parts (origine des visites, appareils) : libellé et petite jauge, visites, part. */
function tableau_parts(mixed $lignes, string $entete, int $max = 8, array $noms = []): string {
    $lignes = array_values(array_filter(is_array($lignes) ? $lignes : [], fn($x) => is_array($x) && matomo_val($x, 'nb_visits') > 0));
    if (!$lignes) return '<p class="vide">Pas encore de visite sur cette période.</p>';
    $total = array_sum(array_map(fn($x) => matomo_val($x, 'nb_visits'), $lignes));
    $rows = '';
    foreach (array_slice($lignes, 0, $max) as $x) {
        $v = matomo_val($x, 'nb_visits');
        $part = $total > 0 ? $v / $total * 100 : 0;
        $nom = trim((string)($x['label'] ?? ''));
        $rows .= '<tr><td>' . h($noms[$nom] ?? $nom) . '<svg class="jauge" viewBox="0 0 100 6" preserveAspectRatio="none" aria-hidden="true"><rect class="fond" width="100" height="6" rx="3"/>'
               . '<rect class="plein" width="' . round(max(2, $part), 1) . '" height="6" rx="3"/></svg></td><td class="nombre">' . h(entier_fr($v)) . '</td><td class="nombre">' . h(number_format($part, 0, ',', ' ') . ' %') . '</td></tr>';
    }
    return '<table class="tableau"><thead><tr><th>' . h($entete) . '</th><th class="nombre">Visites</th><th class="nombre">Part</th></tr></thead><tbody>' . $rows . '</tbody></table>';
}

function page_statistiques_site(): void {
    $admin = est_admin();
    $m = matomo_chiffres(isset($_GET['actualiser']));
    $c = titre_page('Statistiques', '30 derniers jours, d\'après Matomo', '', u('plus')) . onglets_statistiques('site');
    $actions = fn() => '<div class="actions">' . lien_bouton(u('statistiques', ['vue' => 'site', 'actualiser' => '1']), 'Actualiser', 'discret')
        . lien_bouton(GESTION_MATOMO, 'Ouvrir Matomo', 'discret', ['target' => '_blank', 'rel' => 'noopener']) . '</div>';
    if (($m['etat'] ?? '') === 'non-relie') {
        $reglages = '<a href="' . h(u('reglages')) . '#matomo">' . h(fr_ko('Paramètres', '설정')) . '</a>';
        $c .= bloc('Les visites du site', '<p>' . fr_ko('Les visites de madamecho.com sont mesurées par Matomo, sans cookie. Pour les voir ici, '
            . ($admin ? 'collez une clé d\'accès de Matomo dans ' . $reglages . ' : la marche à suivre y est, elle prend deux minutes.' : 'demandez à Youngmi de relier Matomo dans Paramètres.'),
              'madamecho.com 방문은 Matomo가 쿠키 없이 측정해요. 여기에서 보려면 '
            . ($admin ? $reglages . '에 Matomo 접근 키를 붙여 넣으세요. 방법이 거기 적혀 있고, 2분이면 돼요.' : 'Youngmi에게 설정에서 Matomo를 연결해 달라고 하세요.')) . '</p>');
        mise_en_page('Statistiques', $c, 'statistiques');
        return;
    }
    if (($m['etat'] ?? '') !== 'ok') {
        $cle = $admin && str_contains((string)($m['erreur'] ?? ''), 'clé') ? ' <a href="' . h(u('reglages')) . '#matomo">Remplacer la clé dans Paramètres</a>.' : ' Réessayez dans quelques minutes.';
        $c .= bloc('Les visites du site', '<p class="erreur">Lecture impossible pour l\'instant : ' . h((string)($m['erreur'] ?? '')) . '.' . $cle . '</p>' . $actions());
        mise_en_page('Statistiques', $c, 'statistiques');
        return;
    }
    $s7 = $m['j7'] ?? []; $s30 = $m['j30'] ?? [];
    $v30 = matomo_val($s30, 'nb_visits');
    $d30 = matomo_val($m['buts30'] ?? [], 'nb_conversions');
    $c .= '<div class="tuiles">'
        . tuile('Visites aujourd\'hui', entier_fr(matomo_val($m['aujourdhui'] ?? [], 'nb_visits')), 'depuis minuit')
        . tuile('Visites sur 7 jours', entier_fr(matomo_val($s7, 'nb_visits')), entier_fr(matomo_val($m['buts7'] ?? [], 'nb_conversions')) . ' demande(s) envoyée(s)')
        . tuile('Visites sur 30 jours', entier_fr($v30), entier_fr(matomo_val($m['actions'] ?? [], 'nb_pageviews')) . ' pages vues')
        . tuile('Demandes envoyées', entier_fr($d30), $v30 > 0 ? number_format($d30 / $v30 * 100, 1, ',', ' ') . ' % des visites, 30 jours' : '30 derniers jours')
        . tuile('Temps moyen', duree_lisible(matomo_val($s30, 'avg_time_on_site')), 'par visite, 30 jours')
        . tuile('Visites d\'une seule page', number_format(matomo_val($s30, 'bounce_rate'), 0, ',', ' ') . ' %', 'repartent sans voir d\'autre page')
        . '</div>';
    // Visites par jour (les lundis sont datés)
    $jours = is_array($m['jours'] ?? null) ? $m['jours'] : [];
    ksort($jours);
    $pts = []; $meilleur = null; $premier = '';
    foreach ($jours as $date => $x) {
        $t = strtotime((string)$date);
        if ($t === false) continue;
        $v = (int)matomo_val($x, 'nb_visits');
        $jour = date('Y-m-d', $t);
        if ($premier === '') $premier = $jour;
        $pts[] = [(int)date('N', $t) === 1 ? fr_ko(date('j/n', $t), date('n/j', $t)) : '', $v, ucfirst(date_fr($jour, 'jour')) . ' : ' . visites_texte($v)];
        if ($v > 0 && ($meilleur === null || $v >= $meilleur[1])) $meilleur = [$jour, $v];
    }
    $phrase = [];
    if ($meilleur) $phrase[] = 'Meilleur jour : ' . date_fr($meilleur[0], 'jour') . ', ' . visites_texte($meilleur[1]) . '.';
    if ($pts) $phrase[] = 'Aujourd\'hui : ' . visites_texte((int)end($pts)[1]) . '.';
    if ($premier !== '' && $premier < GESTION_MATOMO_DEBUT) $phrase[] = 'Mesure depuis le ' . date_fr(GESTION_MATOMO_DEBUT, 'jour') . '.';
    $c .= bloc('Visites par jour', $pts && $v30 > 0 ? barres($pts, 'Visites par jour, 30 derniers jours', false) . '<p class="aide">' . h(implode(' ', $phrase)) . '</p>'
                                                    : '<p class="vide">Pas encore de visite sur les 30 derniers jours.</p>');
    // D'où viennent les visiteurs
    $src = array_values(array_filter(is_array($m['sources'] ?? null) ? $m['sources'] : [], fn($x) => is_array($x) && matomo_val($x, 'nb_visits') > 0));
    usort($src, fn($a, $b) => matomo_val($b, 'nb_visits') <=> matomo_val($a, 'nb_visits'));
    $c .= bloc('D\'où viennent les visiteurs', tableau_parts($m['types'] ?? [], 'Origine')
        . ($src ? '<p class="aide">En détail : ' . h(implode(' · ', array_map(fn($x) => trim((string)($x['label'] ?? '')) . ' (' . entier_fr(matomo_val($x, 'nb_visits')) . ')', array_slice($src, 0, 8)))) . '</p>' : ''));
    // QR code imprimé sur les emballages : madamecho.com/qr renvoie vers la carte, Matomo compte le scan dans la campagne « qr-code » (07/11/2026)
    $qr30 = matomo_qr($m['qr30'] ?? null);
    if ($qr30 !== null) {
        $qr7 = matomo_qr($m['qr7'] ?? null);
        $buts = $m['qrbuts30'] ?? null;
        // Les demandes que le formulaire du site a marquées « reçues par le QR code » (provenance transmise par le site), comptées par la gestion elle-même
        $qrDemandes = (int)val("SELECT COUNT(*) FROM commandes WHERE canal = 'qr' AND recue_le >= ?", [date('Y-m-d H:i:s', strtotime('-30 days'))]);
        // Les trois compteurs restent affichés même à zéro (demande d'Olivier du 07/11/2026), pour voir ce qui sera compté
        $c .= bloc('QR code sur les emballages', '<div class="tuiles petites">' . tuile('Scans sur 7 jours', entier_fr($qr7['visites'] ?? 0.0))
                  . tuile('Scans sur 30 jours', entier_fr($qr30['visites']), is_array($buts) ? entier_fr(matomo_somme($buts, 'nb_conversions')) . ' demande(s) envoyée(s) après un scan' : '')
                  . tuile('Demandes reçues par le QR code', entier_fr($qrDemandes), 'dans la gestion, 30 jours') . '</div>'
            . '<p class="aide">Chaque scan passe par madamecho.com/qr (campagne Matomo « qr-code »).</p>'
            . '<p class="aide">Demandes envoyées par le formulaire du site après un scan.</p>');
    }
    // Pages les plus vues
    $rows = '';
    foreach (array_slice(array_values(array_filter(is_array($m['pages'] ?? null) ? $m['pages'] : [], fn($x) => is_array($x) && matomo_val($x, 'nb_hits') > 0)), 0, 10) as $x) {
        $rows .= '<tr><td translate="no">' . h(titre_page_site((string)($x['label'] ?? ''))) . '</td><td class="nombre">' . h(entier_fr(matomo_val($x, 'nb_hits'))) . '</td></tr>';
    }
    $c .= bloc('Pages les plus vues', $rows !== '' ? '<table class="tableau"><thead><tr><th>Page</th><th class="nombre">Vues</th></tr></thead><tbody>' . $rows . '</tbody></table>' : '<p class="vide">Pas encore de page vue.</p>');
    // Appareils
    $c .= bloc('Téléphone ou ordinateur', tableau_parts($m['appareils'] ?? [], 'Appareil', 8, ['Smartphone' => 'Téléphone portable', 'Phablette' => 'Grand téléphone']));
    $le = (int)($m['le'] ?? time());
    $c .= (!empty($m['erreur']) ? '<p class="erreur">Lecture impossible pour l\'instant (' . h((string)$m['erreur']) . ') : voici les chiffres lus à ' . date('H \\h i', $le) . '.</p>' : '')
        . '<div class="site-maj"><p class="aide">Matomo, lu à ' . date('H \\h i', $le) . '. Sans cookie ; les refus de mesure ne sont pas comptés.</p>' . $actions() . '</div>';
    mise_en_page('Statistiques', $c, 'statistiques');
}

/* ---------- Plus ---------- */
function page_plus(): void {
    $u = utilisateur_courant();
    $l = [[u('statistiques'), 'stats', 'Statistiques', 'Ventes et visites du site'],
          [u('production'), 'imprimer', 'Fiche de production', 'À préparer, jour par jour'],
          [u('ingredients'), 'produits', 'Ingrédients', 'Prix et allergènes'],
          [u('rapprochement'), 'paiement', 'Rapprochement Qonto', 'Paiements à retrouver dans Qonto'],
          [u('corbeille'), 'supprimer', 'Corbeille', 'Commandes et fiches supprimées, 30 jours pour les restaurer'],
          [u('manuel'), 'livre', 'Manuel', 'Mode d\'emploi, écran par écran'],
          [u('assistant'), 'ia', 'Assistant', 'Une IA : questions, problèmes, idées'],
          [u('reglages'), 'reglages', 'Paramètres', 'Langue, mot de passe, emails, données']];
    $s = '';
    // « Quoi de neuf » en tête, avec le point chocolat d'une nouveauté de l'outil (FR-041)
    $s = '<a class="ligne-menu" href="' . h(u('nouveautes')) . '">' . icone('cloche') . '<span><b>Quoi de neuf' . pastille_menu(0, pastilles()['outil'] ? 'outil' : '') . '</b><span class="petit">Ce qui est nouveau ou a changé, et les nouveautés de l\'outil</span></span></a>';
    // Obligations (spécification 004), juste après « Quoi de neuf », avec son chiffre rouge ou son point ambre
    if (function_exists(__NAMESPACE__ . '\\obligations_pret') && obligations_pret()) {
        $nOb = obligations_urgentes();
        $s .= '<a class="ligne-menu" href="' . h(u('obligations')) . '">' . icone('obligations') . '<span><b>Obligations' . pastille_menu($nOb, $nOb === 0 && obligations_changees() ? 'change' : '') . '</b><span class="petit">Démarches et échéances pour être en règle</span></span></a>';
    }
    foreach ($l as [$href, $ic, $titre, $sous]) $s .= '<a class="ligne-menu" href="' . h($href) . '">' . icone($ic) . '<span><b>' . h($titre) . '</b><span class="petit">' . h($sous) . '</span></span></a>';
    $s .= '<a class="ligne-menu" href="' . h(u('deconnexion', ['j' => csrf()])) . '">' . icone('sortir') . '<span><b>Se déconnecter</b><span class="petit">' . h($u['email']) . '</span></span></a>';
    $c = titre_page('Plus', '') . '<nav class="menu" aria-label="Plus">' . $s . '</nav><p class="aide centre">Gestion Madame Cho · version du ' . h(date_fr(GESTION_VERSION, 'longue')) . '</p>';
    mise_en_page('Plus', $c, 'plus');
}

/* ---------- Corbeille (demande d'Olivier, 07/11/2026) : 30 jours pour restaurer une commande ou une fiche supprimée ---------- */
function page_corbeille(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        csrf_verifier();
        if (texte('_action', 40) === 'restaurer') {
            $e = corbeille_restaurer(entier('id', 1));
            flash($e === '' ? 'ok' : 'erreur', $e === '' ? 'Restauré : c\'est de nouveau à sa place.' : $e);
        }
        rediriger('corbeille');
    }
    $l = lignes("SELECT id, le, par, genre, reference, libelle, motif FROM corbeille ORDER BY le DESC");
    $s = '';
    foreach ($l as $e) {
        $reste = max(0, CORBEILLE_JOURS - intdiv(time() - strtotime($e['le']), 86400));
        $s .= '<li class="ligne-corbeille"><div>' . icone($e['genre'] === 'commande' ? 'commandes' : 'clients') . '<b translate="no">' . h($e['reference']) . '</b> '
            . '<span translate="no">' . h($e['libelle']) . '</span>'
            . '<span class="petit">' . h(($e['genre'] === 'commande' ? 'Commande' : 'Fiche client') . ' · Supprimée le ' . date_fr($e['le'], 'longue') . ' · Effacée dans ' . $reste . ' j') . '</span>'
            . '<span class="petit" translate="no">' . h($e['par'] . ($e['motif'] !== '' ? ' · ' . $e['motif'] : '')) . '</span></div>'
            . formulaire('restaurer', '<input type="hidden" name="id" value="' . (int)$e['id'] . '">' . bouton(icone('retour') . 'Restaurer', 'secondaire'), ['data-confirmer' => 'Restaurer ' . $e['reference'] . ' ?']) . '</li>';
    }
    $c = titre_page(icone('supprimer') . 'Corbeille', 'Ce qui a été supprimé reste ici 30 jours, puis s\'efface tout seul.', '', u('plus'))
        . bloc('À restaurer', $s !== '' ? '<ul class="liste-corbeille">' . $s . '</ul>' : '<p class="vide">La corbeille est vide.</p>');
    mise_en_page('Corbeille', $c, 'plus');
}

/* ---------- Rapprochement avec Qonto (audit n° 2, C14, FR-030) ----------
 * À la main pour l'instant : Youngmi retrouve le paiement dans Qonto et colle la référence de l'opération.
 * Un versement Qonto ne crée jamais de vente ni de paiement ; Qonto reste en lecture seule (R-24). */
function page_rapprochement(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        csrf_verifier();
        if (texte('_action', 40) === 'rapprocher') {
            $ids = array_values(array_unique(array_filter(array_map('intval', is_array($_POST['ids'] ?? null) ? $_POST['ids'] : []))));
            $ref = une_ligne(texte('qonto_ref', 120));
            $erreur = !$ids ? 'Cochez au moins un paiement.' : '';
            foreach ($ids as $i) {   // tout ou rien : on vérifie chaque commande avant d'écrire
                if ($erreur !== '') break;
                $k = commande($i);
                if (!$k || !rapprochement_attendu($k) || (string)$k['qonto_ref'] !== '') $erreur = 'Un des paiements cochés n\'est plus à rapprocher : rechargez la page.';
                elseif (($e = reference_qonto_erreur($ref, $k)) !== null) $erreur = $e;
            }
            if ($erreur === '') {
                foreach ($ids as $i) commande_rapprocher($i, $ref);
                flash('ok', count($ids) . ' paiement(s) rapproché(s) avec Qonto.');
            } else flash('erreur', $erreur . ' Rien n\'a été noté.');
        }
        rediriger('rapprochement');
    }
    $l = a_rapprocher();
    $total = array_sum(array_map('MadameCho\\Gestion\\rapprochement_montant', $l));
    $c = titre_page('Rapprochement Qonto', 'Payé (constaté par vous) et retrouvé dans Qonto sont deux choses séparées', '', u('plus'));
    $c .= '<div class="tuiles">' . tuile('À rapprocher', (string)count($l), euros($total)) . '</div>';
    if (!$l) $c .= '<p class="ok">Tous les paiements par carte et virement sont retrouvés dans Qonto.</p>';
    else {
        $rows = '';
        foreach ($l as $k) {
            $rows .= '<label class="case ligne-rapprochement"><input type="checkbox" name="ids[]" value="' . (int)$k['id'] . '"><span>'
                   . '<b>' . h(euros(rapprochement_montant($k))) . '</b> · ' . h(PAIEMENTS[$k['paiement']] ?? '') . ' · ' . h(MOYENS[$k['moyen']] ?? '')
                   . '<br><span class="petit"><span translate="no">' . h(commande_client_nom($k)) . '</span> · <a href="' . h(u('commande', ['id' => $k['id']])) . '">' . h($k['reference']) . '</a> · '
                   . h(date_fr($k['paiement_le'] ?: ($k['remise_le'] ?: $k['maj_le']), 'heure')) . '</span></span></label>';
        }
        $c .= bloc('Paiements pas encore retrouvés dans Qonto', formulaire('rapprocher', '<div class="liste-cases">' . $rows . '</div>'
            . champ('qonto_ref', 'Référence de l\'opération dans Qonto', '', ['maxlength' => '120', 'autocomplete' => 'off', 'required' => true],
                'Cochez le ou les paiements que cette opération Qonto regroupe (un versement Tap to Pay peut en réunir plusieurs), puis collez la référence de l\'opération. Un nom ou un montant seuls ne suffisent pas.')
            . bouton('Noter rapprochés', 'principal')));
    }
    $r = rapprochements_recents();
    if ($r) {
        $rows = '';
        foreach ($r as $k) $rows .= '<li><a href="' . h(u('commande', ['id' => $k['id']])) . '">' . h($k['reference']) . '</a> · ' . h(euros(rapprochement_montant($k))) . ' · <span translate="no">' . h($k['qonto_ref']) . '</span> · ' . h(date_fr($k['qonto_le'], 'heure')) . '</li>';
        $c .= '<details class="replie"><summary>Derniers rapprochements</summary><ul class="liste-simple">' . $rows . '</ul></details>';
    }
    $c .= '<p class="aide">Les espèces ne passent pas par Qonto : elles ne sont pas dans cette liste. Si un paiement change, son rapprochement est à refaire.</p>';
    mise_en_page('Rapprochement Qonto', $c, 'plus');
}

/* ---------- Exports ---------- */
function page_export(): void {
    $jeu = is_string($_GET['jeu'] ?? null) ? $_GET['jeu'] : '';
    if ($jeu === 'sauvegarde') {
        if (!est_admin()) rediriger('reglages');
        $d = ['madamecho_gestion' => GESTION_VERSION, 'exportee_le' => maintenant()];
        foreach (['clients', 'commandes', 'evenements', 'produits', 'ingredients', 'recettes', 'indispos'] as $t) $d[$t] = q("SELECT * FROM $t")->fetchAll();
        $d['parametres'] = q("SELECT * FROM parametres WHERE cle IN ('signature', 'lien_avis', 'schema')")->fetchAll();
        evenement('export', 'Export complet téléchargé');
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="export-gestion-madamecho-' . date('Y-m-d') . '.json"');
        header('Cache-Control: no-store, private');
        echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    [$l, $e, $nom] = match ($jeu) {
        'clients' => [miroir_clients(), MIROIR_CLIENTS_ENTETES, 'clients'],
        default => [miroir_commandes(), MIROIR_COMMANDES_ENTETES, 'commandes'],
    };
    evenement('export', 'Export CSV des ' . $nom);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="madamecho-' . $nom . '-' . date('Y-m-d') . '.csv"');
    header('Cache-Control: no-store, private');
    echo csv($l, $e);
    exit;
}

/** Miroir pour le Google Sheet du Drive (dossier 10) : appelé par un script Google en POST, avec la clé créée dans Paramètres. */
function page_miroir(): void {
    header('Cache-Control: no-store, private');
    header('X-Robots-Tag: noindex, nofollow');
    $empreinte = (string)parametre('miroir_empreinte', '');
    $cle = is_string($_POST['cle'] ?? null) ? $_POST['cle'] : '';
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || $empreinte === '' || connexion_bloquee('miroir')) { http_response_code(404); exit; }
    if (!hash_equals($empreinte, hash('sha256', $cle))) { connexion_noter('miroir', false, 'miroir'); http_response_code(403); exit; }
    $jeu = is_string($_POST['jeu'] ?? null) ? $_POST['jeu'] : 'commandes';
    [$l, $e] = $jeu === 'clients' ? [miroir_clients(), MIROIR_CLIENTS_ENTETES] : [miroir_commandes(), MIROIR_COMMANDES_ENTETES];
    parametre_ecrire('miroir_dernier', maintenant());
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['jeu' => $jeu === 'clients' ? 'clients' : 'commandes', 'le' => maintenant(), 'entetes' => $e, 'lignes' => $l], JSON_UNESCAPED_UNICODE);
    exit;
}
/** « Dernières reprises » (demande d'Olivier, 07/11/2026) : chaque import de fichier, réussi ou non, avec la date, qui, le fichier
 *  et ce qui s'est passé. Les reprises d'avant le 07/11/2026 n'ont pas d'état : elles ne sont notées qu'une fois réussies. */
function liste_reprises(): string {
    $l = reprises_dernieres(5);
    if (!$l) return '<h3 class="sous-titre-graphique">Dernières reprises</h3><p class="vide">Aucune reprise pour l\'instant.</p>';
    $s = '';
    foreach ($l as $r) {
        $etat = isset(REPRISE_ETATS[$r['etat']]) ? $r['etat'] : 'ok';
        // Les reprises d'avant le 07/11/2026 : même phrase que les nouvelles
        $texte = preg_replace(['/^Reprise : /u', '/, (\d+) repris,/u', '/, gamme pro : (\d+) produit\(s\) ajouté\(s\), (\d+) mis à jour/u', '/, (\d+) ligne\(s\) rattachée\(s\)$/u'],
                              ['', ', $1 déjà connu(s),', ', $1 produit(s) pro ajouté(s), $2 prix pro mis à jour', ', $1 ligne(s) rattachée(s) à la gamme pro'], (string)$r['texte']) ?? '';
        [$resume, $revoir] = array_pad(explode("\nÀ revoir : ", $texte, 2), 2, '');
        $s .= '<li><p class="reprise-tete"><span class="reprise-etat e-' . h(str_replace('_', '-', $etat)) . '">' . h(REPRISE_ETATS[$etat]) . '</span> '
            . '<span>' . h(ucfirst(date_fr((string)$r['le'], 'heure'))) . '</span> <span class="petit">· ' . h((string)$r['auteur']) . '</span></p>'
            . ($r['fichier'] !== '' ? '<p class="petit" translate="no">' . h((string)$r['fichier']) . '</p>' : '')
            . '<p>' . h(rtrim($resume, '.') . '.') . '</p>'
            . ($revoir !== '' ? '<p class="petit">' . h('À revoir : ' . $revoir) . '</p>' : '') . '</li>';
    }
    return '<h3 class="sous-titre-graphique">Dernières reprises</h3><ul class="reprises">' . $s . '</ul>';
}


/* ---------- Paramètres ---------- */
function page_reglages(): void {
    $u = utilisateur_courant();
    $admin = est_admin();
    $info = '';
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        csrf_verifier();
        $a = texte('_action', 40);
        $ancre = '';
        switch ($a) {
            case 'mdp':
                $ancien = is_string($_POST['ancien'] ?? null) ? $_POST['ancien'] : '';
                $m1 = is_string($_POST['mdp1'] ?? null) ? $_POST['mdp1'] : '';
                $m2 = is_string($_POST['mdp2'] ?? null) ? $_POST['mdp2'] : '';
                $ancre = 'compte';
                if (!password_verify($ancien, $u['mdp'])) { flash('erreur', 'Le mot de passe actuel n\'est pas le bon.'); break; }
                if ($m1 !== $m2) { flash('erreur', 'Les deux nouveaux mots de passe ne sont pas identiques.'); break; }
                if ($e = mdp_acceptable($m1)) { flash('erreur', $e); break; }
                mdp_changer((int)$u['id'], $m1);
                session_pour($u, !empty($_COOKIE['mc_souvenir']));
                evenement('compte', 'Mot de passe changé');
                flash('ok', 'Mot de passe changé. Les autres appareils devront se reconnecter.');
                break;
            case 'emails':
                $lien = une_ligne(texte('lien_avis', 300));
                $ancre = 'avis';
                if ($lien !== '' && !preg_match('#^https://(g\.page|search\.google\.com|www\.google\.[a-z.]+|maps\.app\.goo\.gl|goo\.gl)/#', $lien)) { flash('erreur', 'Le lien d\'avis doit être un lien Google (https://g.page/r/…/review).'); break; }
                parametre_ecrire('signature', texte('signature', 500));
                parametre_ecrire('lien_avis', $lien);
                evenement('reglage', 'Signature des emails ou lien d\'avis modifié');
                flash('ok', 'Enregistré.');
                break;
            case 'inviter':
                if (!$admin) break;
                $ancre = 'comptes';
                $email = mb_strtolower(une_ligne(texte('email', 120)));
                $nom = une_ligne(texte('nom', 60));
                if (!email_valide($email) || $nom === '') { flash('erreur', 'Indiquez un email valide et un nom.'); break; }
                $ex = ligne("SELECT * FROM utilisateurs WHERE email = ?", [$email]);
                if ($ex && $ex['mdp'] !== '') { flash('erreur', 'Ce compte existe déjà.'); break; }
                if (!$ex) { q("INSERT INTO utilisateurs (email, nom, role, cree_le) VALUES (?, ?, ?, ?)", [$email, $nom, choix('role', ['membre' => 1, 'admin' => 1], 'membre'), maintenant()]); $ex = ligne("SELECT * FROM utilisateurs WHERE email = ?", [$email]); }
                q("UPDATE jetons SET utilise_le = ? WHERE utilisateur_id = ? AND type = 'mdp' AND utilise_le IS NULL", [maintenant(), $ex['id']]);
                $ok = email_lien_mot_de_passe($ex, jeton_creer((int)$ex['id'], 'mdp', 3 * 86400), true);
                evenement('compte', 'Invitation envoyée à ' . $email);
                flash($ok ? 'ok' : 'erreur', $ok ? 'Invitation envoyée à ' . $email . ' (lien valable 3 jours).' : 'L\'email d\'invitation n\'a pas pu partir.');
                break;
            case 'compte_actif':
                if (!$admin) break;
                $ancre = 'comptes';
                $cid = entier('compte', 1);
                if ($cid && $cid !== (int)$u['id']) {
                    q("UPDATE utilisateurs SET actif = 1 - actif WHERE id = ?", [$cid]);
                    q("DELETE FROM jetons WHERE utilisateur_id = ? AND type = 'session'", [$cid]);
                    evenement('compte', 'Compte ' . val("SELECT email FROM utilisateurs WHERE id = ?", [$cid]) . (val("SELECT actif FROM utilisateurs WHERE id = ?", [$cid]) ? ' réactivé' : ' désactivé'));
                }
                break;
            case 'demandes_site':
                if (!$admin) break;
                $ancre = 'demandes-site';
                $on = parametre('demandes_site') !== '1';
                parametre_ecrire('demandes_site', $on ? '1' : '0');
                evenement('reglage', $on ? 'Enregistrement des demandes du site activé' : 'Enregistrement des demandes du site arrêté');
                flash('ok', $on ? 'Les nouvelles demandes du site s\'ajouteront toutes seules ici (l\'email à contact@ continue d\'arriver).' : 'Les demandes du site ne s\'ajoutent plus ici ; elles arrivent toujours par email.');
                break;
            case 'conservation':
                if (!$admin) break;
                $ancre = 'conservation';
                $cr = conservation_executer(auteur());
                flash('ok', 'Fait : ' . $cr['demandes'] . ' demande(s) anonymisée(s), ' . $cr['clients'] . ' client(s) effacé(s), ' . $cr['journal'] . ' ligne(s) de journal supprimée(s).');
                break;
            case 'miroir':
                if (!$admin) break;
                $ancre = 'miroir';
                if (!empty($_POST['arreter'])) { parametre_ecrire('miroir_empreinte', ''); evenement('reglage', 'Miroir pour le Drive arrêté'); flash('ok', 'Miroir arrêté : l\'ancienne clé ne marche plus.'); break; }
                $cle = bin2hex(random_bytes(24));
                parametre_ecrire('miroir_empreinte', hash('sha256', $cle));
                evenement('reglage', 'Nouvelle clé du miroir pour le Drive');
                session_ouvrir();
                $_SESSION['cle_miroir'] = $cle;   // montrée une seule fois
                break;
            case 'reponses':   // les réponses des clients (script Google de contact@)
                if (!$admin) break;
                $ancre = 'reponses';
                if (!empty($_POST['arreter'])) { parametre_ecrire('reponses_empreinte', ''); evenement('reglage', 'Réponses des clients : script arrêté'); flash('ok', 'Arrêté : l\'ancienne clé ne marche plus, les réponses restent dans Gmail.'); break; }
                if (($g = texte('gmail_id', 64)) !== '') {
                    if (texte('quoi', 20) === 'ignorer') { q("UPDATE mails_recus SET etat = 'ignore', de = '', objet = '', extrait = '' WHERE gmail_id = ? AND etat = 'orphelin'", [$g]); flash('ok', 'Mail ignoré.'); }
                    elseif (reponse_rattacher($g, mb_strtoupper(texte('reference', 20)))) flash('ok', 'Mail rattaché à la commande.');
                    else flash('erreur', 'Référence inconnue : elle ressemble à MC-261105-5D17.');
                    break;
                }
                $cle = bin2hex(random_bytes(24));
                parametre_ecrire('reponses_empreinte', hash('sha256', $cle));
                parametre_ecrire('reponses_cle_le', maintenant());
                evenement('reglage', 'Réponses des clients : nouvelle clé du script de contact@');
                session_ouvrir();
                $_SESSION['cle_reponses'] = $cle;   // montrée une seule fois
                break;
            case 'matomo':
                if (!$admin) break;
                $ancre = 'matomo';
                if (!empty($_POST['retirer'])) {
                    parametre_ecrire('matomo_jeton', ''); parametre_ecrire('matomo_cache', '');
                    evenement('reglage', 'Clé de Matomo retirée (statistiques du site)');
                    flash('ok', 'Matomo n\'est plus relié : les chiffres du site ne s\'affichent plus ici.');
                    break;
                }
                $jeton = preg_replace('/\s+/', '', texte('matomo_jeton', 200)) ?? '';
                if (!preg_match('/^[A-Za-z0-9]{20,100}$/', $jeton)) { flash('erreur', 'Collez la clé telle que Matomo l\'affiche : une suite de lettres et de chiffres, sans espace.'); break; }
                if (($pb = matomo_essayer_cle($jeton)) !== '') { flash('erreur', 'Clé non enregistrée : ' . $pb . '.'); break; }
                parametre_ecrire('matomo_jeton', $jeton); parametre_ecrire('matomo_cache', '');
                evenement('reglage', 'Matomo relié (statistiques du site)');
                flash('ok', 'Matomo est relié : les visites du site sont dans Statistiques › Site internet.');
                break;
            case 'assistant':
                if (!$admin) break;
                $ancre = 'assistant';
                if (!empty($_POST['retirer'])) {
                    parametre_ecrire('assistant_cle', '');
                    evenement('reglage', 'Clé de l\'assistant retirée');
                    flash('ok', 'L\'assistant n\'est plus relié.');
                    break;
                }
                $cle = preg_replace('/\s+/', '', texte('assistant_cle', 300)) ?? '';
                if (!preg_match('/^sk-ant-[A-Za-z0-9_\-]{30,250}$/', $cle)) { flash('erreur', 'Collez la clé telle qu\'Anthropic l\'affiche : elle commence par sk-ant-, sans espace.'); break; }
                if (($pb = assistant_essayer_cle($cle)) !== '') { flash('erreur', 'Clé non enregistrée : ' . $pb . '.'); break; }
                parametre_ecrire('assistant_cle', $cle);
                evenement('reglage', 'Assistant relié (clé Anthropic)');
                flash('ok', 'L\'assistant est relié : il répond dans Plus › Assistant.');
                break;
            case 'assistant_plafond':
                if (!$admin) break;
                $ancre = 'assistant';
                $p = max(0, min(500, (int)texte('assistant_plafond', 6)));
                parametre_ecrire('assistant_plafond', (string)$p);
                evenement('reglage', 'Plafond du mois de l\'assistant : ' . ($p ? $p . ' $' : 'aucun'));
                flash('ok', $p ? 'Plafond enregistré : ' . $p . ' $ par mois.' : 'Plafond enregistré : aucun.');
                break;
            case 'importer':
                if (!$admin) break;
                $ancre = 'reprise';
                // Chaque essai est noté (réussi, à revoir, échoué) et relu dans « Dernières reprises » (demande d'Olivier, 07/11/2026)
                $f = $_FILES['fichier'] ?? null;
                $fichier = is_array($f) ? une_ligne(basename((string)($f['name'] ?? ''))) : '';
                if (!is_array($f) || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) { flash('erreur', 'Choisissez le fichier JSON de reprise (2 Mo au plus).'); break; }
                $echec = function (string $raison, string $detail = '') use ($fichier): void {
                    reprise_noter('echec', $fichier, 'Rien n\'a été enregistré : ' . $raison . '.');
                    flash('erreur', 'Import échoué, rien n\'a été enregistré : ' . $raison . '.' . $detail);
                };
                if (($f['error'] ?? 1) !== UPLOAD_ERR_OK || ($f['size'] ?? 0) > 2_000_000) { $echec('le fichier n\'a pas pu être lu (2 Mo au plus)'); break; }
                $data = json_decode((string)file_get_contents($f['tmp_name']), true);
                if (!is_array($data)) { $echec('ce fichier n\'est pas un JSON lisible'); break; }
                try { $cr = importer($data); }
                catch (\Throwable $e) { error_log('madamecho gestion import : ' . $e->getMessage()); $echec('erreur pendant l\'enregistrement', GESTION_ESSAI ? ' ' . $e->getMessage() : ''); break; }
                $resume = reprise_resume($cr);
                $revoir = $cr['erreurs'] ? "\nÀ revoir : " . implode(' ; ', array_slice($cr['erreurs'], 0, 10)) . (count($cr['erreurs']) > 10 ? ' ; …' : '') : '';
                reprise_noter($cr['erreurs'] ? 'a_revoir' : 'ok', $fichier, $resume . '.' . $revoir);
                flash($cr['erreurs'] ? 'erreur' : 'ok', ($cr['erreurs'] ? 'Import fait, avec ' . count($cr['erreurs']) . ' point(s) à revoir : ' : 'Import réussi : ') . $resume . '.' . $revoir);
                break;
        }
        rediriger('reglages', [], $ancre);
    }
    $c = titre_page('Paramètres', h($u['nom']) . ' · ' . h($u['email']), '', u('plus'));
    // Langue de l'écran (gardée sur cet appareil)
    $ko = coreen_actif();
    $c .= bloc('Langue de l\'écran', '<p class="aide">Gardé sur cet appareil. Notes, fiches et emails préparés restent en français.</p>'
        . '<div class="actions">' . lien_bouton(lien_langue('ko'), '한국어', $ko ? 'principal' : 'discret', ['lang' => 'ko', 'translate' => 'no', 'aria-current' => $ko ? 'true' : null])
        . lien_bouton(lien_langue('fr'), 'Français', $ko ? 'discret' : 'principal', ['lang' => 'fr', 'translate' => 'no', 'aria-current' => $ko ? null : 'true']) . '</div>', '', 'langue');
    // Mon compte
    $c .= bloc('Mon mot de passe', formulaire('mdp', '<input type="text" name="username" value="' . h($u['email']) . '" autocomplete="username" hidden>'
        . champ('ancien', 'Mot de passe actuel', '', ['type' => 'password', 'autocomplete' => 'current-password', 'required' => true])
        . '<div class="ligne-champs">' . champ('mdp1', 'Nouveau mot de passe', '', ['type' => 'password', 'autocomplete' => 'new-password', 'required' => true, 'minlength' => (string)GESTION_MDP_MIN])
        . champ('mdp2', 'Le même, une seconde fois', '', ['type' => 'password', 'autocomplete' => 'new-password', 'required' => true, 'minlength' => (string)GESTION_MDP_MIN]) . '</div>'
        . '<p class="aide">Au moins ' . GESTION_MDP_MIN . ' caractères, à garder dans Bitwarden.</p>'
        . bouton('Changer le mot de passe', 'secondaire')), '', 'compte');
    // Emails préparés et avis
    $c .= bloc('Emails préparés et avis Google', formulaire('emails',
          zone('signature', 'Signature des emails préparés', signature(), ['rows' => '3', 'maxlength' => '500'])
        . champ('lien_avis', 'Lien pour laisser un avis Google', (string)parametre('lien_avis', ''), ['type' => 'url', 'placeholder' => 'https://g.page/r/…/review'],
                'Fiche Google de Madame Cho › « Demander des avis » › copier le lien.')
        . bouton('Enregistrer', 'secondaire')), '', 'avis');
    // Comptes
    if ($admin) {
        $rows = '';
        foreach (lignes("SELECT * FROM utilisateurs ORDER BY id") as $x) {
            $etat = !$x['actif'] ? 'désactivé' : ($x['mdp'] === '' ? 'invitation en attente' : ($x['derniere_connexion'] ? 'vu ' . date_fr($x['derniere_connexion'], 'relative') : 'jamais connecté'));
            $rows .= '<tr><td><b>' . h($x['nom']) . '</b><br><span class="petit">' . h($x['email']) . '</span></td><td>' . h($x['role'] === 'admin' ? 'Administratrice' : 'Membre') . '<br><span class="petit">' . h($etat) . '</span></td><td>'
                   . ((int)$x['id'] !== (int)$u['id'] ? formulaire('compte_actif', '<input type="hidden" name="compte" value="' . (int)$x['id'] . '">' . bouton($x['actif'] ? 'Désactiver' : 'Réactiver', 'discret')) : '') . '</td></tr>';
        }
        $c .= bloc('Qui a accès', '<table class="tableau"><tbody>' . $rows . '</tbody></table>'
            . '<details class="replie"><summary>Donner un accès à quelqu\'un</summary><p class="aide">Pour une personne de Madame Cho seulement, jamais une IA. Elle reçoit un lien par email.</p>'
            . formulaire('inviter', '<div class="ligne-champs">' . champ('email', 'Email', '', ['type' => 'email', 'required' => true]) . champ('nom', 'Nom', '', ['required' => true]) . '</div>'
                . liste_choix('role', 'Rôle', ['membre' => 'Membre (commandes, clients, produits)', 'admin' => 'Administratrice (aussi les accès et les données)'], 'membre') . bouton('Envoyer l\'invitation', 'secondaire')) . '</details>', '', 'comptes');
    }
    // Demandes du site
    if ($admin) {
        $on = parametre('demandes_site') === '1';
        $c .= bloc('Demandes du site', '<p>' . ($on ? '<span class="ok">' . fr_ko('Activé', '켜짐') . '</span> ' . fr_ko(': les demandes du site arrivent ici toutes seules.',
                                                                                           ': 사이트 문의가 여기에 자동으로 들어와요.')
                : '<span class="dormant">' . fr_ko('Pas encore activé', '아직 꺼져 있음') . '</span> ' . fr_ko(': les demandes du site arrivent par email, avec un lien pour les créer ici.',
                                                                                           ': 사이트 문의는 이메일로 오고, 이메일의 링크로 여기에 만들 수 있어요.')) . '</p>'
            . formulaire('demandes_site', bouton($on ? 'Arrêter l\'enregistrement' : 'Activer l\'enregistrement des demandes du site', $on ? 'discret' : 'secondaire')), '', 'demandes-site');
    }
    // Statistiques du site (Matomo)
    if ($admin) {
        $relie = matomo_relie();
        $cache = json_decode((string)parametre('matomo_cache', ''), true);
        $lu = is_array($cache) && !empty($cache['le']) ? date_fr(date('Y-m-d H:i:s', (int)$cache['le']), 'heure') : 'pas encore';
        $t = '<p>Les visites du site (Statistiques › Site internet) viennent de Matomo.</p>'
           . definition(['État' => $relie ? '<span class="ok">relié</span>' : '<span class="dormant">pas encore relié</span>', 'Dernière lecture' => $relie ? h($lu) : '']);
        $t .= '<details class="replie"' . ($relie ? '' : ' open') . '><summary>' . ($relie ? 'Remplacer la clé' : 'Relier Matomo') . '</summary>'
            . '<ol class="aide marche">'
            . '<li>' . fr_ko('Ouvrez <a href="' . h(GESTION_MATOMO) . '" target="_blank" rel="noopener">stats.madamecho.com</a> et connectez-vous (compte YoungmiCho, dans Bitwarden).',
                             '<a href="' . h(GESTION_MATOMO) . '" target="_blank" rel="noopener">stats.madamecho.com</a>을 열고 로그인하세요 (YoungmiCho 계정, Bitwarden에 있어요).') . '</li>'
            . '<li>Roue dentée « Administration », puis « Personnel » › « Sécurité ».</li>'
            . '<li>En bas, « Jetons d\'authentification » : « Créer un nouveau jeton ». Matomo redemande le mot de passe.</li>'
            . '<li>Description : « Gestion Madame Cho ». Copiez le jeton affiché et collez-le ici.</li></ol>'
            . formulaire('matomo', champ('matomo_jeton', 'Clé d\'accès de Matomo', '', ['autocomplete' => 'off', 'spellcheck' => 'false', 'autocapitalize' => 'off', 'required' => true, 'maxlength' => '200'],
                   'Gardée hors du site, jamais réaffichée.')
                . bouton('Vérifier et enregistrer', 'secondaire'))
            . '</details>';
        if ($relie) $t .= formulaire('matomo', '<input type="hidden" name="retirer" value="1">' . bouton('Retirer la clé', 'discret'), ['data-confirmer' => 'Retirer la clé de Matomo ? Les visites du site ne s\'afficheront plus dans la gestion.']);
        $c .= bloc('Statistiques du site (Matomo)', $t, '', 'matomo');
    }
    // L'assistant de Youngmi (clé Anthropic)
    if ($admin) {
        $relie = assistant_relie();
        $t = '<p>' . fr_ko('L\'assistant répond avec Claude (Anthropic), grâce à une clé créée et payée par Olivier. Quelques centimes par question.',
                           '어시스턴트는 Anthropic의 Claude로 답해요. Olivier가 만들고 결제하는 키가 필요해요. 질문당 몇 센트예요.') . '</p>'
           . definition(['État' => $relie ? '<span class="ok">relié</span>' : '<span class="dormant">pas encore relié</span>', 'Modèle' => '<span translate="no">' . h(GESTION_ASSISTANT_MODELE) . '</span>',
                         'Ce mois-ci' => $relie ? h(mb_substr(assistant_mois_texte(), mb_strlen('Ce mois-ci : '))) : '']);
        $t .= '<details class="replie"' . ($relie ? '' : ' open') . '><summary>' . ($relie ? 'Remplacer la clé' : 'Relier l\'assistant') . '</summary>'
            . '<ol class="aide marche">'
            . '<li>' . fr_ko('Ouvrez <a href="https://console.anthropic.com/" target="_blank" rel="noopener">console.anthropic.com</a> avec le compte d\'Olivier, puis « API keys » › « Create key ».',
                             'Olivier의 계정으로 <a href="https://console.anthropic.com/" target="_blank" rel="noopener">console.anthropic.com</a>을 열고 « API keys » › « Create key ».') . '</li>'
            . '<li>Nom : « Gestion Madame Cho ». Copiez la clé affichée (elle commence par sk-ant-) : Anthropic ne la montre qu\'une fois.</li>'
            . '<li>Collez-la ici. La gestion vérifie qu\'elle marche avant de l\'enregistrer.</li></ol>'
            . formulaire('assistant', champ('assistant_cle', 'Clé d\'accès Anthropic', '', ['autocomplete' => 'off', 'spellcheck' => 'false', 'autocapitalize' => 'off', 'required' => true, 'maxlength' => '300'],
                   'Gardée hors du site, jamais réaffichée.')
                . bouton('Vérifier et enregistrer', 'secondaire'))
            . '</details>';
        if ($relie) $t .= formulaire('assistant', '<input type="hidden" name="retirer" value="1">' . bouton('Retirer la clé', 'discret'), ['data-confirmer' => 'Retirer la clé de l\'assistant ? Il ne répondra plus.']);
        // Plafond du mois (audit du 07/11, C9) : au-delà, l'assistant ne répond plus jusqu'au mois suivant
        $t .= '<p class="aide">' . h(assistant_mois_texte()) . '</p>'
            . formulaire('assistant_plafond', '<div class="ligne-champs">' . champ('assistant_plafond', 'Plafond du mois, en dollars (0 = aucun)', (string)(int)parametre('assistant_plafond', (string)GESTION_ASSISTANT_PLAFOND), ['type' => 'number', 'min' => '0', 'max' => '500', 'step' => '1', 'inputmode' => 'numeric']) . '</div>'
                . bouton('Enregistrer le plafond', 'secondaire'));
        $c .= bloc('Assistant', $t, '', 'assistant');
    }
    // Conservation et RGPD
    if ($admin) {
        $ap = conservation_apercu();
        $der = json_decode((string)parametre('derniere_purge', ''), true);
        $nuit = parametre('derniere_nuit');
        $refs = conservation_references_emails();
        $t = '<p>Chaque nuit : demandes sans suite effacées après 12 mois, clients inactifs après 3 ans, connexions après 6 mois.</p>'
           . definition(['Dernier passage de nuit' => h($nuit ? date_fr($nuit, 'heure') : 'pas encore'), 'Dernier effacement' => h(is_array($der) ? date_fr($der['le'], 'heure') . ' : ' . $der['demandes'] . ' demande(s), ' . $der['clients'] . ' client(s)' : 'aucun'),
                         'À effacer maintenant' => h(count($ap['demandes']) . ' demande(s), ' . count(array_unique(array_merge(array_column($ap['clients'], 'id'), array_column($ap['orphelins'], 'id')))) . ' client(s), ' . $ap['journal'] . ' ligne(s) de journal')]);
        if ($nuit && (time() - strtotime($nuit)) > 2 * 86400) $t .= '<p class="erreur">La tâche de nuit ne passe plus depuis le ' . h(date_fr($nuit)) . ' : prévenez Olivier (tâche planifiée OVH).</p>';
        if ($refs) $t .= '<p class="aide">Demandes du site sans suite depuis plus de 11 mois : supprimez aussi leurs emails dans contact@ (P-02, premier lundi du mois) :</p><p class="refs">' . h(implode(' · ', $refs)) . '</p>';
        $t .= formulaire('conservation', bouton('Lancer l\'effacement maintenant', 'discret'), ['data-confirmer' => 'Effacer maintenant ce qui a dépassé sa durée de conservation ?']);
        $c .= bloc('Conservation des données (RGPD)', $t, '', 'conservation');
        // Miroir
        $cle = '';
        session_ouvrir();
        if (!empty($_SESSION['cle_miroir'])) { $cle = (string)$_SESSION['cle_miroir']; unset($_SESSION['cle_miroir']); }
        $actif = (string)parametre('miroir_empreinte', '') !== '';
        $m = '<p>Chaque nuit, une copie réduite (sans email, téléphone ni notes) part dans un tableur du Drive (dossier 10).</p>'
           . definition(['État' => h($actif ? 'actif' : 'arrêté'), 'Dernière copie' => h(($d = parametre('miroir_dernier')) ? date_fr($d, 'heure') : 'jamais'), 'Adresse' => '<code>' . h(gestion_adresse() . '?p=miroir') . '</code>']);
        if ($cle !== '') $m .= '<div class="cle-miroir"><p><b>Clé à coller dans le script Google</b> (elle ne sera plus jamais affichée) :</p><pre id="cle-miroir">' . h($cle) . '</pre><button type="button" class="btn" data-copier="cle-miroir">' . icone('copier') . 'Copier</button></div>';
        $m .= formulaire('miroir', bouton($actif ? 'Créer une nouvelle clé (l\'ancienne cesse de marcher)' : 'Créer la clé du miroir', 'discret')) . ($actif ? formulaire('miroir', '<input type="hidden" name="arreter" value="1">' . bouton('Arrêter le miroir', 'discret')) : '');
        $c .= bloc('Miroir pour le Drive', $m, '', 'miroir');
        // Réponses des clients (script Google de contact@, demande d'Olivier du 07/11/2026)
        $cle = '';
        if (!empty($_SESSION['cle_reponses'])) { $cle = (string)$_SESSION['cle_reponses']; unset($_SESSION['cle_reponses']); }
        $actif = reponses_actif();
        $m = '<p>Un petit script Google, dans contact@madamecho.com, lit (sans rien modifier) les mails dont l\'objet contient « MC- » et les range ici dans les Échanges de la commande. Gmail garde toujours l\'original. Installation : guide dans le Drive (15/04).</p>'
           . definition(['État' => h($actif ? 'actif' : 'arrêté'), 'Dernier passage' => h(($d = parametre('reponses_dernier')) ? date_fr($d, 'heure') : 'jamais'),
                         'Dernière réponse rangée' => h(($d = parametre('reponses_dernier_mail')) ? date_fr($d, 'heure') : 'aucune'), 'Adresse' => '<code>' . h(gestion_adresse() . '?p=reponses') . '</code>']);
        if (reponses_silence()) $m .= '<p class="erreur">Rien reçu depuis plus de 24 h : vérifiez le script dans contact@ (script.google.com › Exécutions).</p>';
        if ($cle !== '') $m .= '<div class="cle-miroir"><p><b>Clé à coller dans le script Google</b> (Paramètres du projet › Propriétés du script › CLE ; elle ne sera plus jamais affichée) :</p><pre id="cle-reponses">' . h($cle) . '</pre><button type="button" class="btn" data-copier="cle-reponses">' . icone('copier') . 'Copier</button></div>';
        $m .= formulaire('reponses', bouton($actif ? 'Créer une nouvelle clé (l\'ancienne cesse de marcher)' : 'Créer la clé du script', 'discret')) . ($actif ? formulaire('reponses', '<input type="hidden" name="arreter" value="1">' . bouton('Arrêter', 'discret')) : '');
        $orph = reponses_orphelins();
        if ($orph) {
            $l = '';
            foreach ($orph as $o) $l .= '<li><div class="ev-texte"><b translate="no">' . h($o['de']) . '</b> · « <span translate="no">' . h($o['objet']) . '</span> »<pre class="corps" translate="no" lang="fr">' . h($o['extrait']) . '</pre>'
                . formulaire('reponses', '<input type="hidden" name="gmail_id" value="' . h($o['gmail_id']) . '"><div class="ligne-champs">' . champ('reference', 'Rattacher à la commande', '', ['placeholder' => 'MC-261105-5D17', 'maxlength' => '20', 'id' => 'f-ref-' . h($o['gmail_id'])]) . '</div>'
                    . '<div class="actions">' . bouton('Rattacher', 'secondaire') . '<button type="submit" class="btn discret" name="quoi" value="ignorer">Ignorer</button></div>')
                . '</div><div class="ev-meta">' . h(date_fr($o['recu_le'], 'heure')) . '</div></li>';
            $m .= '<h3 class="sous-titre-graphique">À rattacher <span class="compte">' . count($orph) . '</span></h3><p class="aide">Des mails avec « MC- » dans l\'objet, mais sans commande reconnue. Effacés d\'ici après 30 jours (ils restent dans Gmail).</p><ol class="fil">' . $l . '</ol>';
        }
        $c .= bloc('Réponses des clients (Gmail)', $m, '', 'reponses');
        // Reprise et export complet
        $c .= bloc('Reprise des données et export', '<p class="aide">Reprise : fichier JSON préparé depuis Qonto (jamais dans le dépôt du site). Sans risque à relancer.</p>'
            . formulaire('importer', '<label class="champ"><span class="etiq">Fichier de reprise (.json)</span><input type="file" name="fichier" accept="application/json,.json" required></label>' . bouton('Importer', 'secondaire'), ['enctype' => 'multipart/form-data'])
            . liste_reprises()
            . '<p class="aide">Export complet en JSON (sans comptes, clé ni conversations), pour une analyse. Ce n\'est pas une sauvegarde : celle-ci est chez OVH. Contient des données de clients : à ranger dans le Drive de Madame Cho.</p>'
            . '<p>' . lien_bouton(u('export', ['jeu' => 'sauvegarde']), 'Télécharger l\'export complet', 'discret') . '</p>', '', 'reprise');
    }
    // Contrôle technique
    $sqlite = (string)val('SELECT sqlite_version()');
    $taille = is_file(gestion_fichier_base()) ? filesize(gestion_fichier_base()) : 0;
    $dkim = is_file(gestion_site() . '/ma-demande/dkim.php');
    $cat = catalogue();
    $site = ligne("SELECT MAX(recue_le) AS d, COUNT(*) AS n FROM commandes WHERE canal = 'site'") ?? [];
    $ok = fn(bool $b, string $oui, string $non) => '<span class="' . ($b ? 'ok' : 'retard') . '">' . h($b ? $oui : $non) . '</span>';
    $c .= bloc('Contrôle technique', definition([
        'PHP' => $ok(PHP_VERSION_ID >= 80000, PHP_VERSION, PHP_VERSION . ' (8.0 au moins)'),
        'Base de données' => $ok($sqlite !== '', 'SQLite ' . $sqlite . ' · ' . number_format($taille / 1024, 0, ',', ' ') . ' Ko', 'indisponible'),
        'Données rangées' => $ok(gestion_hors_site(), 'hors du dossier du site', 'dans un dossier fermé du site (repli)'),
        'Connexion chiffrée' => $ok(gestion_https() || GESTION_ESSAI, gestion_https() ? 'https' : 'essai local', 'pas de https'),
        'Emails signés (DKIM)' => $ok($dkim, 'signature du site présente', 'dkim.php absent'),
        'Carte du site' => $ok(count($cat['produits']) > 0, count($cat['produits']) . ' produits · version ' . ($cat['version'] ?? '?'), 'carte absente'),
        'Demandes du site' => h(((int)($site['n'] ?? 0)) . ' enregistrée(s)' . (!empty($site['d']) ? ', dernière le ' . date_fr($site['d'], 'heure') : '')),
        'Statistiques du site' => h(matomo_relie() ? 'Matomo relié' : 'Matomo pas encore relié'),
    ]));
    // Journal des connexions
    if ($admin) {
        $rows = '';
        foreach (lignes("SELECT * FROM connexions ORDER BY id DESC LIMIT 25") as $x) $rows .= '<tr><td>' . h(date_fr($x['le'], 'heure')) . '</td><td>' . h($x['email']) . '</td><td>' . ($x['ok'] ? 'oui' : '<span class="retard">non</span>') . ($x['detail'] !== '' ? ' · ' . h($x['detail']) : '') . '</td></tr>';
        $c .= bloc('Dernières connexions', $rows !== '' ? '<div class="defile"><table class="tableau"><thead><tr><th>Quand</th><th>Email</th><th>Réussie</th></tr></thead><tbody>' . $rows . '</tbody></table></div><p class="aide">Gardées 6 mois. Connexion inconnue : changez le mot de passe et prévenez Olivier.</p>' : '<p class="vide">Aucune.</p>');
    }
    mise_en_page('Paramètres', $c, 'reglages');
}
