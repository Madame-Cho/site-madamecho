<?php
/*
 * Madame Cho : espace de gestion. Les relevés d'hygiène du quotidien (écran Obligations, lot 2 ; spécification 004, US2).
 * Froid (appareils et températures), réceptions de matières premières, fournées (numéro de lot, date limite), livraisons aux
 * restaurants (départ, arrivée, température au départ), « pas de production aujourd'hui ».
 * Rien n'est jamais effacé à la main : une correction crée un nouveau relevé et l'original reste visible (US2, scénario 5).
 * Les plages de température et les durées de conservation viennent de Youngmi (FR-005) ; les seuils préremplis sont « à confirmer ».
 * Jamais servi directement.
 */
declare(strict_types=1);
namespace MadameCho\Gestion;

require_once __DIR__ . '/lots.php';   // lot 3 : étiquettes et problème sur un lot

const HYGIENE_GENRES = ['froid' => 'Froid', 'reception' => 'Réception', 'fournee' => 'Fournée', 'livraison' => 'Livraison', 'sans_production' => 'Pas de production'];
const HYGIENE_APPAREILS = ['frigo' => 'Réfrigérateur', 'congelateur' => 'Congélateur', 'vitrine' => 'Vitrine réfrigérée', 'caisson' => 'Caisson isotherme'];
const HYGIENE_PHOTO_MAX = 8000000;          // 8 Mo : la page réduit la photo avant l'envoi ; ceci est la limite côté serveur
const HYGIENE_PHOTO_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
const HYGIENE_CONSERVATION_ANS = 5;          // durée par défaut des relevés et des photos (FR-012), modifiable dans Paramètres
const HYGIENE_CONSERVATION_MIN = 5;          // jamais moins de 5 ans quand la gestion efface (audit n° 8, constat 9)
const HYGIENE_CONTROLE_MAX = 120;            // jours passés contrôlés au plus par nuit (relevés manquants, audit n° 8, constat 5)
// Une fournée sans réception reliée, une livraison sans lot : possible, mais dit et expliqué (audit n° 8, constat 1)
const HYGIENE_TRACES = ['aucune_reception' => 'Aucune réception notée', 'lot_inconnu' => 'Lots livrés non notés'];
// Les seuils de l'arrêté du 21/12/2009 (annexe I) : pâtisseries à la crème +3 °C, denrées congelées -18 °C ; à confirmer par Youngmi (FR-005)
const HYGIENE_SEUILS = [['Réfrigérateur', 'frigo', 0.0, 3.0], ['Congélateur', 'congelateur', null, -18.0]];
const HYGIENE_MANQUANT_TITRE = 'Relevé de froid manquant';
const HYGIENE_MANQUANT_OU = 'Gestion, Plus › Hygiène, au jour indiqué ci-dessous : touchez « C\'est fait » en écrivant dans la note ce qui s\'est passé (oubli, frigo vide, pas de production…). Un relevé ne se note jamais après coup.';

function hygiene_pret(): bool {
    static $ok = null;
    return $ok ??= (bool)val("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'hygiene_releves'");
}
/** Combien de relevés de froid par appareil et par jour de production (1 ou 2, Paramètres). */
function hygiene_par_jour(): int { return (string)parametre('hygiene_releves_jour', '1') === '2' ? 2 : 1; }
/** Années de conservation des relevés (0 : rien n'est effacé). */
function hygiene_conservation_ans(): int {
    $v = (string)parametre('hygiene_conservation_ans', (string)HYGIENE_CONSERVATION_ANS);
    $n = preg_match('/^\d{1,2}$/', $v) ? (int)$v : HYGIENE_CONSERVATION_ANS;
    return $n === 0 ? 0 : max(HYGIENE_CONSERVATION_MIN, $n);
}

/* ---------- Appareils de froid ---------- */

/** Au premier usage, deux appareils avec les seuils réglementaires, marqués « à confirmer » (sauf si Olivier a choisi de partir vides). */
function hygiene_preparer(): void {
    if (!hygiene_pret() || (string)parametre('hygiene_prepare', '') !== '') return;
    if ((string)parametre('hygiene_preremplir', '1') === '1' && !val("SELECT COUNT(*) FROM hygiene_appareils")) {
        foreach (HYGIENE_SEUILS as [$nom, $genre, $min, $max])
            q("INSERT INTO hygiene_appareils (nom, genre, tmin, tmax, a_confirmer, cree_le, cree_par) VALUES (?, ?, ?, ?, 1, ?, 'gestion')", [$nom, $genre, $min, $max, maintenant()]);
    }
    parametre_ecrire('hygiene_prepare', maintenant());
    if ((string)parametre('hygiene_debut', '') === '') parametre_ecrire('hygiene_debut', aujourdhui());
}
function hygiene_appareils(bool $tous = false): array {
    return lignes("SELECT * FROM hygiene_appareils" . ($tous ? '' : ' WHERE actif = 1') . " ORDER BY actif DESC, id");
}
function hygiene_appareil(int $id): ?array { return ligne("SELECT * FROM hygiene_appareils WHERE id = ?", [$id]); }
/** « 0 à 3 °C », « -18 °C ou moins », « 3 °C ou moins ». */
function hygiene_plage(?float $min, ?float $max): string {
    $f = fn(float $t): string => str_replace('.', ',', rtrim(rtrim(number_format($t, 1, '.', ''), '0'), '.')) . ' °C';
    if ($min !== null && $max !== null) return $f($min) . ' à ' . $f($max);
    if ($max !== null) return $f($max) . ' ou moins';
    if ($min !== null) return $f($min) . ' ou plus';
    return 'Plage à saisir';
}
function hygiene_temperature(?float $t): string { return $t === null ? '' : str_replace('.', ',', rtrim(rtrim(number_format($t, 1, '.', ''), '0'), '.')) . ' °C'; }
/** Une température tapée sur le téléphone (« 3,5 », « -18 », « −18 ») ; null si ce n'est pas un nombre plausible. */
function hygiene_lire_temperature(string $s): ?float {
    $s = str_replace([',', '−', ' ', '°', 'C'], ['.', '-', '', '', ''], trim($s));
    if (!preg_match('/^-?\d{1,2}(\.\d)?$/', $s)) return null;
    $t = (float)$s;
    return $t >= -40 && $t <= 40 ? $t : null;
}
function hygiene_hors_plage(?float $t, ?float $min, ?float $max): bool {
    return $t !== null && (($min !== null && $t < $min) || ($max !== null && $t > $max));
}
/** Ajout ou modification d'un appareil : rend une erreur, ou ''. Une plage enregistrée par une personne n'est plus « à confirmer ». */
function hygiene_appareil_enregistrer(?int $id, string $nom, string $genre, string $min, string $max, bool $actif = true): string {
    $nom = une_ligne($nom);
    if ($nom === '') return 'Donnez un nom à l\'appareil (par exemple : frigo de la cuisine).';
    if (!isset(HYGIENE_APPAREILS[$genre])) $genre = 'frigo';
    $tmin = trim($min) === '' ? null : hygiene_lire_temperature($min);
    $tmax = trim($max) === '' ? null : hygiene_lire_temperature($max);
    if ((trim($min) !== '' && $tmin === null) || (trim($max) !== '' && $tmax === null)) return 'Une température s\'écrit en chiffres, par exemple 3 ou -18.';
    if ($tmin === null && $tmax === null) return 'Saisissez au moins la température maximale.';
    if ($tmin !== null && $tmax !== null && $tmin >= $tmax) return 'La température minimale doit être plus basse que la maximale.';
    $now = maintenant();
    if ($id) q("UPDATE hygiene_appareils SET nom = ?, genre = ?, tmin = ?, tmax = ?, a_confirmer = 0, actif = ?, modifie_le = ?, modifie_par = ? WHERE id = ?", [$nom, $genre, $tmin, $tmax, $actif ? 1 : 0, $now, auteur(), $id]);
    else q("INSERT INTO hygiene_appareils (nom, genre, tmin, tmax, a_confirmer, actif, cree_le, cree_par) VALUES (?, ?, ?, ?, 0, 1, ?, ?)", [$nom, $genre, $tmin, $tmax, $now, auteur()]);
    evenement('hygiene', ($id ? 'Appareil modifié : ' : 'Appareil ajouté : ') . $nom . ' (' . hygiene_plage($tmin, $tmax) . ')');
    return '';
}

/* ---------- Relevés ---------- */

function hygiene_releve(int $id): ?array { return ligne("SELECT * FROM hygiene_releves WHERE id = ?", [$id]); }
/** Les relevés d'un jour (sans ceux qui ont été corrigés : c'est la correction qui compte), du plus ancien au plus récent. */
function hygiene_jour(string $jour, ?string $genre = null): array {
    return lignes("SELECT r.*, a.nom AS appareil_nom FROM hygiene_releves r LEFT JOIN hygiene_appareils a ON a.id = r.appareil_id
                   WHERE r.jour = ? AND r.remplace_par IS NULL" . ($genre ? ' AND r.genre = ?' : '') . " ORDER BY r.le, r.id", $genre ? [$jour, $genre] : [$jour]);
}
/** Enregistre un relevé (ou la correction d'un relevé : l'original reste, marqué « corrigé »). Rend [id, erreur]. */
function hygiene_noter(string $genre, array $v, array $liens = [], ?int $corrige = null, string $motif = ''): array {
    if (!isset(HYGIENE_GENRES[$genre])) return [0, 'Relevé inconnu.'];
    $u = utilisateur_courant();
    $avant = $corrige ? hygiene_releve($corrige) : null;
    if ($corrige && (!$avant || $avant['remplace_par'] !== null || $avant['genre'] !== $genre)) return [0, 'Ce relevé a déjà été corrigé : ouvrez sa dernière version.'];
    if ($corrige && une_ligne($motif) === '') return [0, 'Dites pourquoi vous corrigez ce relevé.'];
    $jour = $avant['jour'] ?? aujourdhui();
    $cols = ['appareil_id', 'temperature', 'tmin', 'tmax', 'hors_plage', 'action', 'fournisseur', 'produit', 'lot', 'dlc', 'photo', 'produit_id', 'quantite',
             'numero_lot', 'commande_id', 'client_id', 'depart_le', 'arrivee_le', 'temp_depart', 'note', 'trace'];
    $base = array_fill_keys($cols, '') + [];
    foreach (['appareil_id', 'temperature', 'tmin', 'tmax', 'commande_id', 'client_id', 'temp_depart'] as $c) $base[$c] = null;
    $base['hors_plage'] = 0;
    if ($avant) foreach ($cols as $c) $base[$c] = $avant[$c];   // une correction part de l'original : ce qui n'est pas retouché reste
    $v = array_intersect_key($v, $base) + $base;
    $pdo = db();
    $pdo->beginTransaction();
    try {
        // Réservé (le numéro de lot reste unique) ; deux corrections en même temps : la seconde est refusée (revue du 08/11)
        if ($avant && q("UPDATE hygiene_releves SET remplace_par = -1 WHERE id = ? AND remplace_par IS NULL", [$corrige])->rowCount() !== 1) { $pdo->rollBack(); return [0, 'Ce relevé a déjà été corrigé : ouvrez sa dernière version.']; }
        q("INSERT INTO hygiene_releves (genre, jour, le, par, par_id, " . implode(', ', $cols) . ", remplace_id, motif) VALUES (?, ?, ?, ?, ?, " . implode(', ', array_fill(0, count($cols), '?')) . ", ?, ?)",
          array_merge([$genre, $jour, maintenant(), auteur(), $u ? (int)$u['id'] : null], array_map(fn($c) => $v[$c], $cols), [$corrige, une_ligne($motif)]));
        $id = (int)$pdo->lastInsertId();
        if ($avant) {
            q("UPDATE hygiene_releves SET remplace_par = ? WHERE id = ?", [$id, $corrige]);
            q("INSERT OR IGNORE INTO hygiene_liens (de, vers) SELECT de, ? FROM hygiene_liens WHERE vers = ?", [$id, $corrige]);   // ce qui pointait vers l'original suit la correction
        }
        foreach (array_unique(array_map('intval', $liens)) as $l) if ($l > 0 && $l !== $id) q("INSERT OR IGNORE INTO hygiene_liens (de, vers) VALUES (?, ?)", [$id, $l]);
        $pdo->commit();
    } catch (\Throwable $e) { $pdo->rollBack(); throw $e; }
    return [$id, ''];
}
/**
 * Une fournée sans réception reliée, ou une livraison sans lot : refusée, sauf si la case « aucune réception notée » (ou « lots non notés »)
 * est cochée avec une note qui explique (audit n° 8, constat 1). Rend [trace, erreur] ; trace vide quand tout est relié.
 */
function hygiene_trace(string $genre, array $src, array $liens): array {
    if (!in_array($genre, ['fournee', 'livraison'], true) || array_filter(array_map('intval', $liens), fn($l) => $l > 0)) return ['', null];
    $cle = $genre === 'fournee' ? 'aucune_reception' : 'lot_inconnu';
    if ((string)($src[$cle] ?? '') !== '1')
        return ['', $genre === 'fournee' ? 'Cochez les réceptions utilisées, ou « Aucune réception notée » en expliquant pourquoi dans la note.'
                                         : 'Cochez les lots livrés, ou « Lots livrés non notés » en expliquant pourquoi dans la note.'];
    if (une_ligne((string)($src['note'] ?? '')) === '') return ['', 'Écrivez dans la note pourquoi rien n\'est relié (produits du placard, réception non relevée, fournée non notée…).'];
    return [$cle, null];
}
/** Note une nouvelle fournée avec son numéro de lot, unique même si deux téléphones notent en même temps (audit n° 8, constat 13). */
function hygiene_noter_fournee(array $v, array $liens, string $jour): array {
    for ($i = 0; $i < 5; $i++) {
        $v['numero_lot'] = hygiene_numero_lot($jour, $v['produit_id'] !== '' ? (string)$v['produit_id'] : (string)$v['produit']);
        try { [$id, $e] = hygiene_noter('fournee', $v, $liens); return [$id, $e, $v['numero_lot']]; }
        catch (\PDOException $x) { if (!str_contains($x->getMessage(), 'UNIQUE')) throw $x; }
    }
    return [0, 'Le numéro de lot n\'a pas pu être attribué : réessayez.', ''];
}
/** Les relevés reliés (réceptions d'une fournée, fournées d'une livraison), dans leur dernière version. */
function hygiene_liens_de(int $id): array {
    $l = [];
    foreach (lignes("SELECT r.* FROM hygiene_liens k JOIN hygiene_releves r ON r.id = k.vers WHERE k.de = ? ORDER BY r.le", [$id]) as $r) {
        while ($r && $r['remplace_par'] !== null && (int)$r['remplace_par'] > 0) $r = hygiene_releve((int)$r['remplace_par']);
        if ($r) $l[(int)$r['id']] = $r;
    }
    return array_values($l);
}
/** Ce qui pointe vers ce relevé (les fournées qui ont utilisé une réception, les livraisons d'une fournée). */
function hygiene_liens_vers(int $id): array {
    return lignes("SELECT r.* FROM hygiene_liens k JOIN hygiene_releves r ON r.id = k.de WHERE k.vers = ? AND r.remplace_par IS NULL ORDER BY r.le", [$id]);
}
/** La suite des versions d'un relevé, de l'original à la dernière correction. */
function hygiene_versions(int $id): array {
    $r = hygiene_releve($id);
    if (!$r) return [];
    while ($r['remplace_id'] !== null && ($p = hygiene_releve((int)$r['remplace_id']))) $r = $p;
    $l = [$r];
    while ($r['remplace_par'] !== null && (int)$r['remplace_par'] > 0 && ($r = hygiene_releve((int)$r['remplace_par']))) $l[] = $r;
    return $l;
}

/** Un relevé de froid ; hors plage, ce qu'elle a fait est obligatoire (US2, scénario 1). */
function hygiene_froid_lu(array $src, ?array $avant = null): array {
    $a = hygiene_appareil((int)($src['appareil_id'] ?? ($avant['appareil_id'] ?? 0)));
    if (!$a || (!(int)$a['actif'] && !$avant)) return [null, 'Choisissez un appareil.'];
    $t = hygiene_lire_temperature((string)($src['temperature'] ?? ''));
    if ($t === null) return [null, 'Tapez la température lue, en chiffres (par exemple 3 ou -18).'];
    // Corriger un relevé garde la plage en vigueur ce jour-là (sauf si l'appareil change)
    $ref = $avant && (int)$avant['appareil_id'] === (int)$a['id'] ? $avant : $a;
    $min = $ref['tmin'] !== null ? (float)$ref['tmin'] : null;
    $max = $ref['tmax'] !== null ? (float)$ref['tmax'] : null;
    $hors = hygiene_hors_plage($t, $min, $max);
    $action = mb_substr(trim((string)($src['action'] ?? '')), 0, 600);
    $v = ['appareil_id' => (int)$a['id'], 'temperature' => $t, 'tmin' => $min, 'tmax' => $max, 'hors_plage' => $hors ? 1 : 0, 'action' => $hors ? $action : ''];
    if ($hors && $action === '') return [$v, 'Température hors plage (' . hygiene_plage($min, $max) . ') : écrivez ce que vous avez fait (porte fermée, produits déplacés ou jetés, réglage…).'];
    return [$v, null];
}
/** Une réception : le produit et son lot ou sa date limite, ou la photo de l'étiquette à la place (US2, scénario 2). */
function hygiene_reception_lue(array $src, string $photo, ?array $avant = null): array {
    $v = ['fournisseur' => mb_substr(une_ligne((string)($src['fournisseur'] ?? '')), 0, 120), 'produit' => mb_substr(une_ligne((string)($src['produit'] ?? '')), 0, 120),
          'lot' => mb_substr(une_ligne((string)($src['lot'] ?? '')), 0, 60), 'dlc' => (string)($src['dlc'] ?? ''), 'note' => mb_substr(trim((string)($src['note'] ?? '')), 0, 600),
          'photo' => $photo !== '' ? $photo : (string)($avant['photo'] ?? '')];
    if ($v['dlc'] !== '' && !date_valide($v['dlc'])) return [$v, 'Cette date n\'est pas valide : choisissez-la dans le calendrier.'];
    if ($v['photo'] === '' && ($v['produit'] === '' || ($v['lot'] === '' && $v['dlc'] === '')))
        return [$v, 'Notez le produit avec son lot ou sa date limite, ou prenez l\'étiquette en photo.'];
    $t = trim((string)($src['temperature'] ?? ''));
    if ($t !== '') { $v['temperature'] = hygiene_lire_temperature($t); if ($v['temperature'] === null) return [$v, 'Une température s\'écrit en chiffres, par exemple 3 ou -18.']; }
    return [$v, null];
}
/** La durée de conservation d'un gâteau, fixée par Youngmi (jamais devinée). */
function hygiene_duree(string $pid): ?int { $j = val("SELECT jours FROM hygiene_durees WHERE produit_id = ?", [$pid]); return $j === null || $j === false ? null : (int)$j; }
function hygiene_duree_fixer(string $pid, ?int $jours): void {
    q("INSERT INTO hygiene_durees (produit_id, jours, maj_le, maj_par) VALUES (?, ?, ?, ?) ON CONFLICT(produit_id) DO UPDATE SET jours = excluded.jours, maj_le = excluded.maj_le, maj_par = excluded.maj_par",
      [$pid, $jours, maintenant(), auteur()]);
}
/** Le prochain numéro de lot : AAMMJJ-GATEAU-n (date + gâteau, US2, scénario 3). */
function hygiene_numero_lot(string $jour, string $pid): string {
    $code = strtoupper(substr(preg_replace('/[^a-z0-9]+/i', '', str_replace('-', '', $pid)) ?: 'AUTRE', 0, 10));
    $base = date('ymd', (int)strtotime($jour)) . '-' . $code . '-';
    $n = 1;
    while (val("SELECT 1 FROM hygiene_releves WHERE numero_lot = ?", [$base . $n])) $n++;
    return $base . $n;
}
/** Une fournée : le gâteau, la quantité, la date limite (reprise de la durée fixée par Youngmi, sinon tapée). */
function hygiene_fournee_lue(array $src, ?array $avant = null): array {
    $pid = (string)($src['produit_id'] ?? ($avant['produit_id'] ?? ''));
    $p = $pid !== '' && $pid !== 'autre' ? produit($pid) : null;
    $nom = $p ? (string)$p['nom'] : mb_substr(une_ligne((string)($src['produit'] ?? '')), 0, 120);
    $v = ['produit_id' => $p ? $pid : '', 'produit' => $nom, 'quantite' => mb_substr(une_ligne((string)($src['quantite'] ?? '')), 0, 60),
          'dlc' => (string)($src['dlc'] ?? ''), 'note' => mb_substr(trim((string)($src['note'] ?? '')), 0, 600)];
    if ($nom === '') return [$v, 'Choisissez le gâteau.'];
    $duree = $p ? hygiene_duree($pid) : null;
    $retenir = (string)($src['retenir'] ?? '') === '1';
    $jours = (string)($src['jours'] ?? '');
    if ($v['dlc'] === '' && $duree !== null) $v['dlc'] = date('Y-m-d', (int)strtotime(($avant['jour'] ?? aujourdhui()) . ' +' . $duree . ' days'));
    if ($v['dlc'] === '' && preg_match('/^\d{1,3}$/', $jours) && (int)$jours >= 1) $v['dlc'] = date('Y-m-d', (int)strtotime(($avant['jour'] ?? aujourdhui()) . ' +' . (int)$jours . ' days'));
    if ($v['dlc'] === '') return [$v, 'Indiquez la date limite de ce gâteau (ou sa durée de conservation en jours) : la gestion ne la devine jamais.'];
    if (!date_valide($v['dlc'])) return [$v, 'Cette date n\'est pas valide : choisissez-la dans le calendrier.'];
    if ($v['dlc'] < ($avant['jour'] ?? aujourdhui())) return [$v, 'La date limite ne peut pas être avant le jour de la fournée.'];
    if ($p && $retenir && preg_match('/^\d{1,3}$/', $jours) && (int)$jours >= 1 && (int)$jours <= 365) $v['_retenir'] = (int)$jours;
    return [$v, null];
}
/** Une livraison au départ : le restaurant (client professionnel), la commande éventuelle, la température au départ. */
function hygiene_livraison_lue(array $src, ?array $avant = null): array {
    $cid = (int)($src['client_id'] ?? ($avant['client_id'] ?? 0));
    $kid = (int)($src['commande_id'] ?? 0);
    $k = $kid ? commande($kid) : null;
    if ($k) $cid = (int)$k['client_id'];
    $cl = $cid ? client($cid) : null;
    $v = ['client_id' => $cl ? $cid : null, 'commande_id' => $k ? $kid : null, 'note' => mb_substr(trim((string)($src['note'] ?? '')), 0, 600)];
    if (!$cl) return [$v, 'Choisissez le restaurant (ou la commande).'];
    $t = trim((string)($src['temp_depart'] ?? ''));
    if ($t !== '') { $v['temp_depart'] = hygiene_lire_temperature($t); if ($v['temp_depart'] === null) return [$v, 'Une température s\'écrit en chiffres, par exemple 3 ou -18.']; }
    if (!$avant) $v['depart_le'] = maintenant();
    return [$v, null];
}
/** L'arrivée d'une livraison : une seule fois, à l'heure du toucher. */
function hygiene_arrivee(int $id): string {
    $r = hygiene_releve($id);
    if (!$r || $r['genre'] !== 'livraison' || $r['remplace_par'] !== null) return 'Livraison introuvable.';
    if ($r['arrivee_le'] !== '') return 'L\'arrivée est déjà notée.';
    q("UPDATE hygiene_releves SET arrivee_le = ? WHERE id = ? AND arrivee_le = ''", [maintenant(), $id]);
    return '';
}

/** Le nom d'un restaurant : sa société d'abord, puis le contact (« Le Bistrot · Paul Durand »). */
function hygiene_restaurant(array $c): string {
    $n = client_nom($c);
    return $c['statut'] !== 'efface' && trim((string)$c['societe']) !== '' && !str_contains($n, (string)$c['societe']) ? $c['societe'] . ' · ' . $n : $n;
}

/* ---------- Photos d'étiquettes (hors du site, jamais servies directement) ---------- */

function hygiene_dossier_photos(): string {
    $d = gestion_dossier() . '/hygiene-photos';
    if (!is_dir($d)) @mkdir($d, 0700, true);
    return $d;
}
/** Garde la photo envoyée ; rend [nom du fichier, erreur]. Le type est vérifié sur le contenu, jamais sur le nom. */
function hygiene_photo_garder(?array $f): array {
    if (!is_array($f) || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return ['', null];
    if (($f['error'] ?? 1) !== UPLOAD_ERR_OK || (int)$f['size'] > HYGIENE_PHOTO_MAX) return ['', 'La photo n\'a pas pu être envoyée (8 Mo au plus) : réessayez.'];
    $info = @getimagesize((string)$f['tmp_name']);
    $ext = $info ? (HYGIENE_PHOTO_TYPES[$info['mime']] ?? null) : null;
    if (!$ext) return ['', 'Ce fichier n\'est pas une photo (JPEG, PNG ou WebP).'];
    $nom = bin2hex(random_bytes(12)) . '.' . $ext;
    $cible = hygiene_dossier_photos() . '/' . $nom;
    if (!(is_uploaded_file((string)$f['tmp_name']) ? move_uploaded_file((string)$f['tmp_name'], $cible) : @rename((string)$f['tmp_name'], $cible))) return ['', 'La photo n\'a pas pu être gardée : réessayez.'];
    @chmod($cible, 0600);
    return [$nom, null];
}
function hygiene_photo_chemin(string $nom): ?string {
    if (!preg_match('/^[a-f0-9]{24}\.(jpg|png|webp)$/', $nom)) return null;
    $f = hygiene_dossier_photos() . '/' . $nom;
    return is_file($f) ? $f : null;
}

/* ---------- Jours de production, relevés manquants, tâche de nuit ---------- */

/** Les commandes confirmées ou remises ce jour-là : des gâteaux sont passés par le froid. */
function hygiene_commandes_du_jour(string $jour): int { return (int)val("SELECT COUNT(*) FROM commandes WHERE retrait_date = ? AND statut IN ('confirmee', 'remise')", [$jour]); }
/**
 * Un jour compte comme jour de production s'il a une fournée, une livraison, ou une commande retirée ou livrée ce jour-là.
 * « Pas de production » ne cache jamais un jour qui a des commandes (audit n° 8, constat 6).
 */
function hygiene_jour_production(string $jour): bool {
    if (val("SELECT 1 FROM hygiene_releves WHERE jour = ? AND genre IN ('fournee', 'livraison') AND remplace_par IS NULL", [$jour])) return true;
    if (hygiene_commandes_du_jour($jour) > 0) return true;
    return false;
}
/** Les appareils dont il manque des relevés ce jour-là : [appareil, notés, attendus]. */
function hygiene_manques(string $jour): array {
    $m = [];
    $n = hygiene_par_jour();
    // Deux relevés par jour seulement depuis le jour où le réglage a changé : jamais rétroactif (revue du 08/11)
    if ($n === 2 && $jour < (string)parametre('hygiene_releves_jour_depuis', '')) $n = 1;
    foreach (lignes("SELECT * FROM hygiene_appareils WHERE actif = 1 AND genre <> 'caisson' AND substr(cree_le, 1, 10) <= ?", [$jour]) as $a) {
        $k = (int)val("SELECT COUNT(*) FROM hygiene_releves WHERE jour = ? AND genre = 'froid' AND appareil_id = ? AND remplace_par IS NULL", [$jour, (int)$a['id']]);
        if ($k < $n) $m[] = [$a, $k, $n];
    }
    return $m;
}
/** Pour chaque jour de production passé sans relevé de froid complet, la démarche « relevé manquant » (US2, scénario 6) ; une seule par jour. */
function hygiene_manquants(?string $auj = null): int {
    if (!hygiene_pret() || !obligations_pret()) return 0;
    $debut = (string)parametre('hygiene_debut', '');
    if ($debut === '') return 0;
    $auj = $auj ?? aujourdhui();
    $n = 0;
    // Tous les jours depuis le dernier contrôle (au plus 120 par nuit), et toujours les 7 derniers : une nuit manquée ne perd rien (audit n° 8, constat 5)
    $hier = date('Y-m-d', (int)strtotime("$auj -1 day"));
    $cur = (string)parametre('hygiene_controle_jusqua', '');
    $de = max($debut, min($cur !== '' ? date('Y-m-d', (int)strtotime("$cur +1 day")) : $debut, date('Y-m-d', (int)strtotime("$auj -7 days"))));
    $fin = min($hier, date('Y-m-d', (int)strtotime("$de +" . (HYGIENE_CONTROLE_MAX - 1) . ' days')));
    for ($j = $de; $j <= $fin; $j = date('Y-m-d', (int)strtotime("$j +1 day"))) {
        if (!hygiene_jour_production($j) || !hygiene_manques($j)) continue;
        $n += demarche_creer(['titre' => HYGIENE_MANQUANT_TITRE, 'explication' => 'Jour concerné : ' . date_fr($j, 'longue') . '.', 'ou' => HYGIENE_MANQUANT_OU,
                              'pour_qui' => 'youngmi', 'echeance' => date('Y-m-d', (int)strtotime("$j +1 day")), 'a_confirmer' => 0, 'preavis' => OBLIGATIONS_JOURS,
                              'priorite' => 2, 'importante' => 0, 'origine' => 'Hygiène'], 'gestion', ['declencheur' => 'hygiene:froid:' . $j, 'ref_genre' => 'hygiene', 'ref_id' => $j]) ? 1 : 0;
    }
    if ($fin >= $de && $fin > $cur) parametre_ecrire('hygiene_controle_jusqua', $fin);
    return $n;
}
/** La tâche de nuit : relevés manquants, puis effacement des relevés et des photos plus vieux que la durée choisie (FR-012). */
function hygiene_nuit(): array {
    if (!hygiene_pret()) return [];
    $cr = ['manquants' => hygiene_manquants(), 'effaces' => 0];
    $ans = hygiene_conservation_ans();
    if ($ans > 0) {
        $limite = date('Y-m-d', (int)strtotime('-' . $ans . ' years'));
        $garder = hygiene_releves_des_problemes($limite);
        foreach (lignes("SELECT id, photo FROM hygiene_releves WHERE jour < ?", [$limite]) as $r) {
            if (isset($garder[(int)$r['id']])) continue;
            if ($r['photo'] !== '' && !val("SELECT 1 FROM hygiene_releves WHERE photo = ? AND jour >= ?", [$r['photo'], $limite]) && ($f = hygiene_photo_chemin((string)$r['photo']))) @unlink($f);
            q("DELETE FROM hygiene_liens WHERE de = ? OR vers = ?", [(int)$r['id'], (int)$r['id']]);
            q("DELETE FROM hygiene_releves WHERE id = ?", [(int)$r['id']]);
            $cr['effaces']++;
        }
    }
    return $cr;
}

/**
 * Les relevés d'un problème ouvert, ou clos depuis moins longtemps que la durée de conservation : jamais effacés (audit n° 8, constat 9).
 * La fournée (toutes ses versions), ses réceptions et ses livraisons.
 */
function hygiene_releves_des_problemes(string $limite): array {
    $g = [];
    foreach (lignes("SELECT numero_lot FROM hygiene_problemes WHERE clos_le = '' OR substr(clos_le, 1, 10) >= ?", [$limite]) as $pb)
        foreach (lignes("SELECT id FROM hygiene_releves WHERE numero_lot = ?", [$pb['numero_lot']]) as $f) {
            $g[(int)$f['id']] = true;
            foreach (lignes("SELECT de, vers FROM hygiene_liens WHERE de = ? OR vers = ?", [(int)$f['id'], (int)$f['id']]) as $l) { $g[(int)$l['de']] = true; $g[(int)$l['vers']] = true; }
        }
    return $g;
}

/* ---------- Export pour un contrôle ou l'expert-comptable (FR-011) ---------- */

/** Un texte tapé ne s'ouvre jamais comme une formule dans un tableur (audit n° 8, constat 15). */
function csv_texte(?string $t): string {
    $t = (string)$t;
    return preg_match('/^[=+\-@\t\r]/', $t) ? "'" . $t : $t;
}

function hygiene_export_csv(string $du, string $au): string {
    $o = fopen('php://temp', 'r+');
    fwrite($o, "\xEF\xBB\xBF");
    fputcsv($o, ['Jour', 'Heure', 'Relevé', 'Par', 'Appareil', 'Température', 'Plage', 'Hors plage', 'Ce qui a été fait', 'Fournisseur', 'Produit', 'Lot fournisseur',
                 'Date limite', 'Photo', 'Numéro de lot', 'Quantité', 'Restaurant', 'Commande', 'Départ', 'Arrivée', 'Température au départ', 'Liens', 'Note', 'Version'], ';');
    foreach (lignes("SELECT r.*, a.nom AS appareil_nom FROM hygiene_releves r LEFT JOIN hygiene_appareils a ON a.id = r.appareil_id WHERE r.jour BETWEEN ? AND ? ORDER BY r.jour, r.le, r.id", [$du, $au]) as $r) {
        $cl = $r['client_id'] !== null ? client((int)$r['client_id']) : null;
        $k = $r['commande_id'] !== null ? commande((int)$r['commande_id']) : null;
        $liens = implode(', ', array_map(fn($x) => $x['numero_lot'] !== '' ? $x['numero_lot'] : '#' . $x['id'], hygiene_liens_de((int)$r['id'])));
        $x = fn(string $c) => csv_texte((string)$r[$c]);
        fputcsv($o, [$r['jour'], substr($r['le'], 11, 5), HYGIENE_GENRES[$r['genre']] ?? $r['genre'], $x('par'), csv_texte((string)$r['appareil_nom']),
                     $r['temperature'] !== null ? hygiene_temperature((float)$r['temperature']) : '', $r['genre'] === 'froid' ? hygiene_plage($r['tmin'] !== null ? (float)$r['tmin'] : null, $r['tmax'] !== null ? (float)$r['tmax'] : null) : '',
                     (int)$r['hors_plage'] ? 'oui' : '', $x('action'), $x('fournisseur'), $x('produit'), $x('lot'), $r['dlc'], $r['photo'] !== '' ? 'oui' : '', $x('numero_lot'), $x('quantite'),
                     $cl ? csv_texte(hygiene_restaurant($cl)) : '', $k ? csv_texte((string)$k['reference']) : '', substr($r['depart_le'], 11, 5), substr($r['arrivee_le'], 11, 5),
                     $r['temp_depart'] !== null ? hygiene_temperature((float)$r['temp_depart']) : '', csv_texte($liens . ($r['trace'] !== '' ? ($liens !== '' ? ' ; ' : '') . (HYGIENE_TRACES[$r['trace']] ?? '') : '')), $x('note'),
                     ($r['remplace_par'] === null ? 'en cours' : ((int)$r['remplace_par'] > 0 ? 'corrigé (voir #' . (int)$r['remplace_par'] . ')' : 'annulé')) . ($r['remplace_id'] !== null ? ', corrige #' . (int)$r['remplace_id'] . ' : ' . $r['motif'] : '')], ';');
    }
    rewind($o);
    $s = (string)stream_get_contents($o);
    fclose($o);
    return $s;
}

/** Pour l'accueil : les relevés de froid du jour, notés sur attendus (rien si l'hygiène n'a pas commencé). */
function hygiene_resume_jour(): ?array {
    if (!hygiene_pret() || (string)parametre('hygiene_prepare', '') === '') return null;
    $auj = aujourdhui();
    if (hygiene_commandes_du_jour($auj) === 0 && val("SELECT 1 FROM hygiene_releves WHERE jour = ? AND genre = 'sans_production' AND remplace_par IS NULL", [$auj])) return ['sans_production' => true, 'notes' => 0, 'attendus' => 0, 'hors' => 0];
    $app = lignes("SELECT id FROM hygiene_appareils WHERE actif = 1 AND genre <> 'caisson'");
    $n = hygiene_par_jour();
    $notes = 0;
    foreach ($app as $a) $notes += min($n, (int)val("SELECT COUNT(*) FROM hygiene_releves WHERE jour = ? AND genre = 'froid' AND appareil_id = ? AND remplace_par IS NULL", [$auj, (int)$a['id']]));
    $hors = (int)val("SELECT COUNT(*) FROM hygiene_releves WHERE jour = ? AND genre = 'froid' AND hors_plage = 1 AND remplace_par IS NULL", [$auj]);
    return ['sans_production' => false, 'notes' => $notes, 'attendus' => count($app) * $n, 'hors' => $hors];
}
