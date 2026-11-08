<?php
/*
 * Madame Cho : espace de gestion. L'écran « Hygiène » (Plus › Hygiène ; écran Obligations, lot 2, spécification 004, US2).
 * Aujourd'hui : le froid, les réceptions, les fournées et les livraisons du jour, en quelques touches sur le téléphone.
 * Historique : les jours passés et l'export pour un contrôle. Appareils : les frigos et leurs plages, les durées de conservation.
 * Un relevé ne s'efface jamais : « Corriger » crée une nouvelle version et l'original reste visible, avec l'heure et la personne.
 * Les textes saisis ne sont jamais traduits (translate="no") ; le reste passe par inc/coreen.php.
 */
declare(strict_types=1);
namespace MadameCho\Gestion;

function hygiene_garde(): void {
    if (!obligations_acces() || !hygiene_pret()) { flash('erreur', 'L\'écran Hygiène est réservé à Youngmi et à Olivier.'); rediriger('plus'); }
}
/** Le brouillon d'un formulaire refusé (pour ne rien retaper). */
function hygiene_brouillon(string $genre): array {
    session_ouvrir();
    $b = $_SESSION['hygiene_brouillon'][$genre] ?? [];
    unset($_SESSION['hygiene_brouillon'][$genre]);
    return is_array($b) ? $b : [];
}
function hygiene_garder_brouillon(string $genre, array $v): void { session_ouvrir(); $_SESSION['hygiene_brouillon'][$genre] = array_map(fn($x) => is_scalar($x) ? (string)$x : '', $v); }

function page_hygiene(): void {
    hygiene_garde();
    hygiene_preparer();
    $auj = aujourdhui();
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        csrf_verifier();
        $a = texte('_action', 40);
        $ancre = '';
        $src = array_map(fn($x) => is_string($x) ? $x : '', $_POST);
        $liens = array_map('intval', is_array($_POST['liens'] ?? null) ? $_POST['liens'] : []);
        $err = null; $ok = '';
        if ($a === 'froid') {
            [$v, $err] = hygiene_froid_lu($src);
            if ($err === null) { hygiene_noter('froid', $v); $ok = $v['hors_plage'] ? 'Relevé noté, hors plage, avec ce que vous avez fait.' : 'Relevé noté.'; }
            $ancre = 'froid';
        } elseif ($a === 'reception') {
            [$photo, $err] = hygiene_photo_garder($_FILES['photo'] ?? null);
            if ($err === null) {
                [$v, $err] = hygiene_reception_lue($src, $photo);
                if ($err === null) { hygiene_noter('reception', $v); $ok = 'Réception notée.'; }
                elseif ($photo !== '' && ($f = hygiene_photo_chemin($photo))) @unlink($f);
            }
            $ancre = 'receptions';
        } elseif ($a === 'fournee') {
            [$v, $err] = hygiene_fournee_lue($src);
            if ($err === null) {
                if (isset($v['_retenir'])) hygiene_duree_fixer((string)$v['produit_id'], (int)$v['_retenir']);
                unset($v['_retenir']);
                $v['numero_lot'] = hygiene_numero_lot($auj, $v['produit_id'] !== '' ? (string)$v['produit_id'] : (string)$v['produit']);
                hygiene_noter('fournee', $v, $liens);
                hygiene_annuler_sans_production($auj, 'fournée notée');
                $ok = 'Fournée notée : lot ' . $v['numero_lot'] . '.';
            }
            $ancre = 'fournees';
        } elseif ($a === 'livraison') {
            [$v, $err] = hygiene_livraison_lue($src);
            if ($err === null) { hygiene_noter('livraison', $v, $liens); hygiene_annuler_sans_production($auj, 'livraison notée'); $ok = 'Départ noté. Touchez « Arrivée » une fois sur place.'; }
            $ancre = 'livraisons';
        } elseif ($a === 'arrivee') {
            $e = hygiene_arrivee((int)($src['id'] ?? 0));
            if ($e !== '') $err = $e; else $ok = 'Arrivée notée.';
            $ancre = 'livraisons';
        } elseif ($a === 'sans_production') {
            if (hygiene_jour_production_notee($auj)) $err = 'Une fournée ou une livraison est déjà notée aujourd\'hui.';
            elseif (!hygiene_jour($auj, 'sans_production')) { hygiene_noter('sans_production', []); $ok = 'C\'est noté : pas de production aujourd\'hui.'; }
        } elseif ($a === 'production_finalement') {
            hygiene_annuler_sans_production($auj, 'annulé par ' . auteur());
            $ok = 'C\'est noté : il y a une production aujourd\'hui.';
        }
        if ($err !== null) { hygiene_garder_brouillon($a, $src); flash('erreur', $err); }
        elseif ($ok !== '') flash('ok', $ok);
        rediriger('hygiene', [], $ancre);
    }
    $vue = choix('vue', array_flip(['jour', 'historique', 'appareils']), 'jour');
    $jour = date_valide((string)($_GET['jour'] ?? '')) ?? $auj;
    if ($jour > $auj) $jour = $auj;
    $onglets = '<nav class="sous-onglets" aria-label="Rubriques">';
    foreach (['jour' => 'Aujourd\'hui', 'historique' => 'Historique', 'appareils' => 'Appareils'] as $v => $l)
        $onglets .= '<a href="' . h(u('hygiene', ['vue' => $v])) . '"' . ($vue === $v && ($v !== 'jour' || $jour === $auj) ? ' aria-current="page"' : '') . '>' . h($l) . '</a>';
    $onglets .= '</nav>';
    $c = titre_page('Hygiène', 'Les relevés du jour : froid, réceptions, fournées et livraisons. Rien ne s\'efface : une erreur se corrige.', '', u('plus'));
    $aConf = array_filter(hygiene_appareils(), fn($a) => (int)$a['a_confirmer']);
    if ($aConf) $c .= '<div class="bandeau-obligations" role="note">' . icone('alerte') . '<span><b>Plages à confirmer par Youngmi.</b> <span>Les plages préremplies sont les seuils de la réglementation (pâtisserie à la crème : 3 °C au plus ; congelé : -18 °C ou moins).</span> '
        . '<a href="' . h(u('hygiene', ['vue' => 'appareils'])) . '">Vérifier les appareils</a></span></div>';
    $c .= $onglets;
    if ($vue === 'historique') $c .= vue_hygiene_historique();
    elseif ($vue === 'appareils') $c .= vue_hygiene_appareils();
    else $c .= vue_hygiene_jour($jour);
    mise_en_page('Hygiène', $c, 'hygiene', 'page-obligations page-hygiene');
}

/** Vrai si une fournée ou une livraison est déjà notée ce jour-là. */
function hygiene_jour_production_notee(string $jour): bool {
    return (bool)val("SELECT 1 FROM hygiene_releves WHERE jour = ? AND genre IN ('fournee', 'livraison') AND remplace_par IS NULL", [$jour]);
}
/** « Pas de production » touché par erreur, ou démenti par une fournée : la mention reste, marquée annulée. */
function hygiene_annuler_sans_production(string $jour, string $motif): void {
    q("UPDATE hygiene_releves SET remplace_par = 0, motif = ? WHERE jour = ? AND genre = 'sans_production' AND remplace_par IS NULL", [mb_substr($motif . ' (' . substr(maintenant(), 11, 5) . ')', 0, 120), $jour]);
}

/* ---------- Aujourd'hui (ou un jour passé, en lecture) ---------- */

function vue_hygiene_jour(string $jour): string {
    $auj = aujourdhui();
    $ecrit = $jour === $auj;
    $s = '';
    if (!$ecrit) $s .= '<p class="bandeau-obligations" role="note">' . icone('mois') . '<span><b>' . h(ucfirst(date_fr($jour, 'jour'))) . '</b> : <span>un jour passé ne se complète pas après coup ; un relevé faux se corrige depuis sa fiche.</span></span></p>';
    $sansProd = hygiene_jour($jour, 'sans_production');
    if ($sansProd) $s .= '<div class="bandeau-obligations calme" role="note">' . icone('valider') . '<span><b>Pas de production ce jour-là.</b> <span>Aucun relevé n\'est attendu.</span></span>'
        . ($ecrit ? formulaire('production_finalement', bouton('Finalement, je produis', 'discret'), [], u('hygiene')) : '') . '</div>';
    // Froid
    $f = '';
    $n = hygiene_par_jour();
    $b = $ecrit ? hygiene_brouillon('froid') : [];
    foreach (hygiene_appareils() as $a) {
        $rel = array_values(array_filter(hygiene_jour($jour, 'froid'), fn($r) => (int)$r['appareil_id'] === (int)$a['id']));
        $min = $a['tmin'] !== null ? (float)$a['tmin'] : null; $max = $a['tmax'] !== null ? (float)$a['tmax'] : null;
        $caisson = $a['genre'] === 'caisson';
        $etat = $caisson ? 'Au départ des livraisons' : (count($rel) >= $n ? 'Fait' : (count($rel) . ' sur ' . $n));
        $l = '';
        foreach ($rel as $r) $l .= hygiene_ligne($r);
        $form = '';
        if ($ecrit) {
            $bb = (int)($b['appareil_id'] ?? 0) === (int)$a['id'] ? $b : [];
            $form = formulaire('froid', '<input type="hidden" name="appareil_id" value="' . (int)$a['id'] . '">'
                . '<div class="ligne-champs">' . champ('temperature', 'Température lue (°C)', $bb['temperature'] ?? '', ['inputmode' => 'decimal', 'autocomplete' => 'off', 'required' => true, 'id' => 'f-t-' . (int)$a['id'],
                    'pattern' => '[−\-]?[0-9]{1,2}([,.][0-9])?', 'data-plage-min' => $min === null ? false : (string)$min, 'data-plage-max' => $max === null ? false : (string)$max, 'data-plage-action' => 'f-action-' . (int)$a['id']])
                . bouton(icone('valider') . 'Noter', 'principal') . '</div>'
                . '<div class="hors-plage" id="f-action-' . (int)$a['id'] . '-bloc"' . (($bb['action'] ?? '') === '' && !isset($bb['temperature']) ? ' hidden' : '') . '>'
                . zone('action', 'Hors plage : qu\'avez-vous fait ?', $bb['action'] ?? '', ['rows' => '2', 'maxlength' => '600', 'id' => 'f-action-' . (int)$a['id'], 'translate' => 'no'], 'Porte fermée, produits déplacés ou jetés, réglage du thermostat, réparateur appelé…') . '</div>',
                ['class' => 'form-froid'], u('hygiene'));
        }
        $f .= '<div class="carte-appareil' . (count($rel) >= $n || $caisson ? ' fait' : '') . '"><p class="l1"><b translate="no">' . h($a['nom']) . '</b><span class="chip-par">' . h(hygiene_plage($min, $max)) . '</span>'
            . ((int)$a['a_confirmer'] ? '<span class="etiq etiq-change">À confirmer</span>' : '') . '<span class="petit">' . h($etat) . '</span></p>'
            . ($l !== '' ? '<ul class="liste-releves">' . $l . '</ul>' : '') . $form . '</div>';
    }
    if ($f === '') $f = '<p class="vide">Aucun appareil : ajoutez vos frigos dans « Appareils ».</p>';
    $s .= bloc(icone('hygiene') . ' Froid', $f . '<p class="aide">' . h($n === 2 ? 'Deux relevés par appareil et par jour de production : le matin et le soir.' : 'Un relevé par appareil et par jour de production, de préférence le matin.') . '</p>', '', 'froid');
    // Réceptions
    $l = hygiene_jour($jour, 'reception');
    $r = $l ? '<ul class="liste-releves">' . implode('', array_map('MadameCho\\Gestion\\hygiene_ligne', $l)) . '</ul>' : '<p class="vide">Aucune réception notée.</p>';
    if ($ecrit) $r .= '<details class="replie"' . (($bb = hygiene_brouillon('reception')) ? ' open' : '') . '><summary>' . icone('ajouter') . 'Noter une réception</summary>' . formulaire('reception', champs_reception($bb), ['enctype' => 'multipart/form-data', 'class' => 'form-releve'], u('hygiene')) . '</details>';
    $s .= bloc(icone('produits') . ' Réceptions', $r . '<p class="aide">Pour les matières premières fragiles (crème, œufs, beurre, fruits) : le produit, le lot et la date limite, ou une photo de l\'étiquette.</p>', '', 'receptions');
    // Fournées
    $l = hygiene_jour($jour, 'fournee');
    $r = $l ? '<ul class="liste-releves">' . implode('', array_map('MadameCho\\Gestion\\hygiene_ligne', $l)) . '</ul>' : '<p class="vide">Aucune fournée notée.</p>';
    if ($ecrit) $r .= '<details class="replie"' . (($bb = hygiene_brouillon('fournee')) ? ' open' : '') . '><summary>' . icone('ajouter') . 'Noter une fournée</summary>' . formulaire('fournee', champs_fournee($bb), ['class' => 'form-releve'], u('hygiene')) . '</details>';
    $s .= bloc(icone('jour') . ' Fournées', $r . '<p class="aide">Chaque fournée reçoit son numéro de lot (date et gâteau) et sa date limite, reprise de la durée de conservation que vous avez fixée.</p>', '', 'fournees');
    // Livraisons
    $l = hygiene_jour($jour, 'livraison');
    $r = $l ? '<ul class="liste-releves">' . implode('', array_map(fn($x) => hygiene_ligne($x, $ecrit), $l)) . '</ul>' : '<p class="vide">Aucune livraison notée.</p>';
    if ($ecrit) $r .= '<details class="replie"' . (($bb = hygiene_brouillon('livraison')) ? ' open' : '') . '><summary>' . icone('envoyer') . 'Départ d\'une livraison</summary>' . formulaire('livraison', champs_livraison($bb), ['class' => 'form-releve'], u('hygiene')) . '</details>';
    $s .= bloc(icone('pro') . ' Livraisons aux restaurants', $r, '', 'livraisons');
    if ($ecrit && !$sansProd && !hygiene_jour_production_notee($jour))
        $s .= '<p class="pied-hygiene">' . formulaire('sans_production', bouton(icone('stop') . 'Pas de production aujourd\'hui', 'secondaire'), ['data-confirmer' => 'Noter « pas de production aujourd\'hui » ? Aucun relevé ne sera attendu.'], u('hygiene')) . '</p>';
    return $s;
}

/** Un relevé dans une liste : l'heure, l'essentiel, « hors plage » ou « corrigé », et la fiche en un toucher. */
function hygiene_ligne(array $r, bool $arrivee = false): string {
    $quoi = match ($r['genre']) {
        'froid' => '<b>' . h(hygiene_temperature($r['temperature'] !== null ? (float)$r['temperature'] : null)) . '</b>' . ((int)$r['hors_plage'] ? ' <span class="etiq etiq-alerte">Hors plage</span>' : ''),
        'reception' => '<b translate="no">' . h($r['produit'] !== '' ? $r['produit'] : '') . '</b>' . ($r['produit'] === '' ? '<b>Étiquette en photo</b>' : '') . ($r['fournisseur'] !== '' ? ' · <span translate="no">' . h($r['fournisseur']) . '</span>' : '') . ($r['dlc'] !== '' ? ' · ' . h('Date limite : ' . date_fr($r['dlc'], 'courte')) : '') . ($r['photo'] !== '' ? ' ' . icone('copier') : ''),
        'fournee' => '<b translate="no">' . h($r['produit']) . '</b> · <span translate="no">' . h($r['numero_lot']) . '</span>' . (($pb = probleme_du_lot((string)$r['numero_lot'])) && $pb['clos_le'] === '' ? ' <span class="etiq etiq-alerte">Problème</span>' : '') . ($r['quantite'] !== '' ? ' · <span translate="no">' . h($r['quantite']) . '</span>' : '') . ' · ' . h('Date limite : ' . date_fr($r['dlc'], 'courte')),
        'livraison' => '<b translate="no">' . h(($cl = client((int)$r['client_id'])) ? hygiene_restaurant($cl) : '') . '</b> · ' . h('Départ ' . substr($r['depart_le'], 11, 5)) . ($r['temp_depart'] !== null ? ' · ' . h(hygiene_temperature((float)$r['temp_depart'])) : '')
                       . ($r['arrivee_le'] !== '' ? ' · ' . h('Arrivée ' . substr($r['arrivee_le'], 11, 5)) : ''),
        default => h(HYGIENE_GENRES[$r['genre']] ?? ''),
    };
    $btn = $arrivee && $r['genre'] === 'livraison' && $r['arrivee_le'] === '' ? formulaire('arrivee', '<input type="hidden" name="id" value="' . (int)$r['id'] . '">' . bouton(icone('valider') . 'Arrivée', 'principal petit'), ['class' => 'form-arrivee'], u('hygiene')) : '';
    return '<li class="releve' . ((int)$r['hors_plage'] ? ' hors' : '') . '"><a href="' . h(u('hygiene-fiche', ['id' => (int)$r['id']])) . '"><span class="petit">' . h(substr($r['le'], 11, 5)) . '</span> ' . $quoi
         . ($r['remplace_id'] !== null ? ' <span class="etiq etiq-change">Corrigé</span>' : '') . '</a>' . $btn . '</li>';
}

function champs_reception(array $b): string {
    return '<label class="champ photo-etiquette" for="f-photo"><span class="etiq">Photo de l\'étiquette (facultatif)</span><input type="file" name="photo" id="f-photo" accept="image/*" capture="environment" data-reduire="1600"><span class="aide">Elle remplace le produit, le lot et la date si vous êtes pressée.</span></label>'
         . champ('produit', 'Produit', $b['produit'] ?? '', ['maxlength' => '120', 'placeholder' => 'Crème liquide 35 %', 'translate' => 'no'])
         . champ('fournisseur', 'Fournisseur (facultatif)', $b['fournisseur'] ?? '', ['maxlength' => '120', 'translate' => 'no'])
         . '<div class="ligne-champs">' . champ('lot', 'Lot', $b['lot'] ?? '', ['maxlength' => '60', 'translate' => 'no']) . champ('dlc', 'Date limite', $b['dlc'] ?? '', ['type' => 'date']) . '</div>'
         . champ('temperature', 'Température à la réception (facultatif, °C)', $b['temperature'] ?? '', ['inputmode' => 'decimal', 'autocomplete' => 'off', 'id' => 'f-t-reception'])
         . zone('note', 'Note (facultatif)', $b['note'] ?? '', ['rows' => '2', 'maxlength' => '600', 'translate' => 'no', 'id' => 'f-note-reception'])
         . bouton(icone('valider') . 'Noter la réception', 'principal');
}
function champs_fournee(array $b, ?array $avant = null): string {
    $opts = ['' => 'Choisir le gâteau'];
    $jours = [];
    foreach (produits_lister() as $p) {
        if ($p['statut'] === 'retire' && ($avant['produit_id'] ?? '') !== $p['id']) continue;
        $opts[$p['id']] = $p['nom'];
        if (($d = hygiene_duree((string)$p['id'])) !== null) $jours[$p['id']] = $d;
    }
    $opts['autre'] = 'Autre gâteau (sur mesure)';
    $pid = $b['produit_id'] ?? ($avant ? ($avant['produit_id'] !== '' ? $avant['produit_id'] : 'autre') : '');
    $sel = liste_choix('produit_id', 'Gâteau', $opts, $pid, ['required' => true, 'data-durees' => json_encode($jours, JSON_UNESCAPED_UNICODE) ?: '{}', 'data-jour' => $avant['jour'] ?? aujourdhui(), 'id' => 'f-produit-fournee']);
    $rec = '';
    foreach (lignes("SELECT * FROM hygiene_releves WHERE genre = 'reception' AND remplace_par IS NULL AND jour >= ? AND (dlc = '' OR dlc >= ?) ORDER BY le DESC LIMIT 40",
                    [date('Y-m-d', (int)strtotime('-30 days')), $avant['jour'] ?? aujourdhui()]) as $r) {
        $coche = $avant ? in_array((int)$r['id'], array_map(fn($x) => (int)$x['id'], hygiene_liens_de((int)$avant['id'])), true) : false;
        $rec .= '<label class="case"><input type="checkbox" name="liens[]" value="' . (int)$r['id'] . '"' . ($coche ? ' checked' : '') . '><span><span translate="no">' . h($r['produit'] !== '' ? $r['produit'] : 'Étiquette en photo') . ($r['lot'] !== '' ? ' · ' . $r['lot'] : '') . '</span> · ' . h(date_fr($r['jour'], 'courte')) . '</span></label>';
    }
    return $sel
         . champ('produit', 'Nom du gâteau (si « Autre »)', $b['produit'] ?? ($avant && $avant['produit_id'] === '' ? $avant['produit'] : ''), ['maxlength' => '120', 'translate' => 'no'])
         . champ('quantite', 'Quantité (facultatif)', $b['quantite'] ?? ($avant['quantite'] ?? ''), ['maxlength' => '60', 'placeholder' => '12 choux, 2 entremets 18 cm', 'translate' => 'no'])
         . '<div class="ligne-champs">' . champ('dlc', 'Date limite', $b['dlc'] ?? ($avant['dlc'] ?? ''), ['type' => 'date', 'id' => 'f-dlc-fournee'])
         . champ('jours', 'ou durée (jours)', $b['jours'] ?? '', ['type' => 'number', 'min' => '1', 'max' => '365', 'inputmode' => 'numeric', 'id' => 'f-jours-fournee']) . '</div>'
         . case_a_cocher('retenir', 'Retenir cette durée pour ce gâteau (la date limite se calculera seule)', ($b['retenir'] ?? '') === '1')
         . ($rec !== '' ? '<fieldset class="champ"><legend class="etiq">Réceptions utilisées (facultatif)</legend>' . $rec . '</fieldset>' : '')
         . zone('note', 'Note (facultatif)', $b['note'] ?? ($avant['note'] ?? ''), ['rows' => '2', 'maxlength' => '600', 'translate' => 'no', 'id' => 'f-note-fournee'])
         . ($avant ? '' : bouton(icone('valider') . 'Noter la fournée', 'principal'));
}
function champs_livraison(array $b, ?array $avant = null): string {
    $auj = $avant['jour'] ?? aujourdhui();
    $cmd = ['' => 'Sans commande'];
    foreach (lignes("SELECT k.id, k.reference, k.retrait_date, k.client_id FROM commandes k JOIN clients c ON c.id = k.client_id
                     WHERE k.statut IN ('confirmee', 'remise') AND k.retrait_date BETWEEN ? AND ? AND (k.type = 'pro' OR c.type = 'professionnel') ORDER BY k.retrait_date, k.id",
                    [date('Y-m-d', (int)strtotime("$auj -1 day")), date('Y-m-d', (int)strtotime("$auj +1 day"))]) as $k)
        if ($cl = client((int)$k['client_id'])) $cmd[(string)$k['id']] = $k['reference'] . ' · ' . hygiene_restaurant($cl) . ' · ' . date_fr((string)$k['retrait_date'], 'courte');
    $rest = ['' => 'Choisir le restaurant'];
    foreach (lignes("SELECT * FROM clients WHERE statut <> 'efface' AND type = 'professionnel' ORDER BY societe, nom") as $cl) $rest[(string)$cl['id']] = hygiene_restaurant($cl);
    $lots = '';
    $liees = $avant ? array_map(fn($x) => (int)$x['id'], hygiene_liens_de((int)$avant['id'])) : [];
    foreach (lignes("SELECT * FROM hygiene_releves WHERE genre = 'fournee' AND remplace_par IS NULL AND jour >= ? AND dlc >= ? ORDER BY le DESC LIMIT 40", [date('Y-m-d', (int)strtotime("$auj -7 days")), $auj]) as $r)
        $lots .= '<label class="case"><input type="checkbox" name="liens[]" value="' . (int)$r['id'] . '"' . (in_array((int)$r['id'], $liees, true) ? ' checked' : '') . '><span><span translate="no">' . h($r['produit'] . ' · ' . $r['numero_lot']) . '</span></span></label>';
    return liste_choix('commande_id', 'Commande (facultatif)', $cmd, $b['commande_id'] ?? ($avant['commande_id'] ?? ''), ['translate' => 'no'], 'Une commande d\'hier, d\'aujourd\'hui ou de demain ; elle choisit le restaurant.')
         . liste_choix('client_id', 'ou le restaurant', $rest, $b['client_id'] ?? ($avant['client_id'] ?? ''), ['translate' => 'no'])
         . ($lots !== '' ? '<fieldset class="champ"><legend class="etiq">Lots livrés</legend>' . $lots . '</fieldset>' : '<p class="aide">Notez d\'abord la fournée pour relier ses lots à la livraison.</p>')
         . champ('temp_depart', 'Température au départ (facultatif, °C)', $b['temp_depart'] ?? ($avant !== null && $avant['temp_depart'] !== null ? str_replace('.', ',', (string)$avant['temp_depart']) : ''), ['inputmode' => 'decimal', 'autocomplete' => 'off', 'id' => 'f-t-depart'], 'Celle du caisson isotherme ou de la boîte, au moment de partir.')
         . zone('note', 'Note (facultatif)', $b['note'] ?? ($avant['note'] ?? ''), ['rows' => '2', 'maxlength' => '600', 'translate' => 'no', 'id' => 'f-note-livraison'])
         . ($avant ? '' : bouton(icone('envoyer') . 'Noter le départ', 'principal'));
}

/* ---------- Historique et export ---------- */

function vue_hygiene_historique(): string {
    $debut = date('Y-m-d', (int)strtotime('-30 days'));
    $jours = lignes("SELECT jour, SUM(genre = 'froid') AS froid, SUM(genre = 'reception') AS rec, SUM(genre = 'fournee') AS fou, SUM(genre = 'livraison') AS liv,
                     SUM(genre = 'sans_production') AS sp, SUM(hors_plage) AS hors, SUM(remplace_id IS NOT NULL) AS corr
                     FROM hygiene_releves WHERE jour >= ? AND remplace_par IS NULL GROUP BY jour ORDER BY jour DESC", [$debut]);
    $s = '';
    foreach ($jours as $j) {
        $p = [];
        if ((int)$j['sp']) $p[] = 'Pas de production';
        if ((int)$j['froid']) $p[] = (int)$j['froid'] . ' froid';
        if ((int)$j['rec']) $p[] = (int)$j['rec'] . ' réception(s)';
        if ((int)$j['fou']) $p[] = (int)$j['fou'] . ' fournée(s)';
        if ((int)$j['liv']) $p[] = (int)$j['liv'] . ' livraison(s)';
        $s .= '<li><a class="carte-demarche n-' . ((int)$j['hors'] ? 'action' : 'calme') . '" href="' . h(u('hygiene', ['vue' => 'jour', 'jour' => $j['jour']])) . '"><span class="d-etat">' . icone('mois') . '<span>' . h(ucfirst(date_fr($j['jour'], 'jour'))) . '</span></span>'
            . '<b class="d-titre">' . h(implode(' · ', $p)) . '</b><span class="d-pied">' . ((int)$j['hors'] ? '<span class="etiq etiq-alerte">' . h((int)$j['hors'] . ' hors plage') . '</span>' : '')
            . ((int)$j['corr'] ? '<span class="etiq etiq-change">' . h((int)$j['corr'] . ' corrigé(s)') . '</span>' : '') . '</span></a></li>';
    }
    $ans = hygiene_conservation_ans();
    return bloc('Les 30 derniers jours', $s !== '' ? '<ul class="liste-demarches">' . $s . '</ul>' : '<p class="vide">Rien pour l\'instant.</p>')
         . bloc('Exporter pour un contrôle', '<form method="get" action="' . h(gestion_chemin()) . '" class="form-releve"><input type="hidden" name="p" value="hygiene-export">'
            . '<div class="ligne-champs">' . champ('du', 'Du', date('Y-m-01'), ['type' => 'date', 'required' => true]) . champ('au', 'Au', aujourdhui(), ['type' => 'date', 'required' => true]) . '</div>'
            . bouton(icone('imprimer') . 'Télécharger (CSV)', 'secondaire') . '</form>'
            . '<p class="aide">' . h($ans > 0 ? 'Les relevés et les photos sont gardés ' . $ans . ' ans, puis effacés par la tâche de nuit.' : 'Rien n\'est effacé pour l\'instant : la durée de conservation n\'est pas encore fixée.') . '</p>');
}
function page_hygiene_export(): void {
    hygiene_garde();
    $du = date_valide((string)($_GET['du'] ?? '')) ?? date('Y-m-01');
    $au = date_valide((string)($_GET['au'] ?? '')) ?? aujourdhui();
    if ($au < $du) [$du, $au] = [$au, $du];
    evenement('export', 'Export des relevés d\'hygiène du ' . $du . ' au ' . $au);
    entetes_securite();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="releves-hygiene-' . $du . '-' . $au . '.csv"');
    header('Cache-Control: private, no-store');
    echo hygiene_export_csv($du, $au);
    exit;
}
function page_hygiene_photo(): void {
    hygiene_garde();
    $r = hygiene_releve((int)($_GET['id'] ?? 0));
    $f = $r ? hygiene_photo_chemin((string)$r['photo']) : null;
    if (!$f) { http_response_code(404); exit; }
    $info = @getimagesize($f);
    header('Content-Type: ' . ($info['mime'] ?? 'application/octet-stream'));
    header('Content-Length: ' . (string)filesize($f));
    header('Cache-Control: private, max-age=86400');
    header('X-Content-Type-Options: nosniff');
    readfile($f);
    exit;
}

/* ---------- Appareils, durées de conservation, réglages ---------- */

function vue_hygiene_appareils(): string {
    $s = '';
    foreach (hygiene_appareils(true) as $a) {
        $s .= '<details class="replie carte-appareil' . ((int)$a['actif'] ? '' : ' fait') . '"><summary><b translate="no">' . h($a['nom']) . '</b> <span class="chip-par">' . h(hygiene_plage($a['tmin'] !== null ? (float)$a['tmin'] : null, $a['tmax'] !== null ? (float)$a['tmax'] : null)) . '</span>'
            . ((int)$a['a_confirmer'] ? '<span class="etiq etiq-change">À confirmer</span>' : '') . (!(int)$a['actif'] ? '<span class="chip-par">Arrêté</span>' : '') . '</summary>'
            . formulaire('appareil', champs_appareil($a) . case_a_cocher('actif', 'En service', (bool)$a['actif']) . bouton(icone('valider') . ((int)$a['a_confirmer'] ? 'Confirmer cette plage' : 'Enregistrer'), 'principal'), ['class' => 'form-releve'], u('hygiene-appareil', ['id' => (int)$a['id']])) . '</details>';
    }
    $s .= '<details class="replie"><summary>' . icone('ajouter') . 'Ajouter un appareil</summary>' . formulaire('appareil', champs_appareil(null) . bouton(icone('valider') . 'Ajouter', 'principal'), ['class' => 'form-releve'], u('hygiene-appareil')) . '</details>';
    $d = '';
    foreach (produits_lister() as $p) {
        if ($p['statut'] === 'retire') continue;
        $j = hygiene_duree((string)$p['id']);
        $d .= formulaire('duree', '<input type="hidden" name="produit_id" value="' . h($p['id']) . '"><div class="ligne-champs ligne-duree"><span class="nom-duree" translate="no">' . h($p['nom']) . '</span>'
            . champ('jours', 'Jours', $j === null ? '' : (string)$j, ['type' => 'number', 'min' => '0', 'max' => '365', 'inputmode' => 'numeric', 'id' => 'f-jours-' . h($p['id'])]) . bouton('Enregistrer', 'discret') . '</div>', ['class' => 'form-duree'], u('hygiene-appareil'));
    }
    $reg = formulaire('reglages', liste_choix('par_jour', 'Relevés de froid par jour de production', ['1' => 'Un (le matin)', '2' => 'Deux (le matin et le soir)'], (string)hygiene_par_jour())
            . liste_choix('ans', 'Garder les relevés et les photos', ['1' => '1 an', '2' => '2 ans', '3' => '3 ans', '5' => '5 ans', '10' => '10 ans', '0' => 'Ne rien effacer'], (string)hygiene_conservation_ans())
            . zone('exploitant', 'Exploitant sur les étiquettes', (string)parametre('etiquette_exploitant', ''), ['rows' => '2', 'maxlength' => '200', 'translate' => 'no', 'placeholder' => 'Madame Cho, numéro et rue, code postal et ville'],
                'Le nom et l\'adresse imprimés sur chaque étiquette (l\'adresse de production déclarée à la DDPP).')
            . bouton('Enregistrer', 'secondaire'), ['class' => 'form-releve'], u('hygiene-appareil'));
    return bloc('Appareils de froid', $s . '<p class="aide">La plage vient de vous : la gestion ne la devine pas. Un caisson isotherme sert aux livraisons ; il n\'a pas de relevé du matin.</p>')
         . bloc('Durées de conservation des gâteaux', '<p class="aide">En jours après la fournée. Vide : la date limite se tape à chaque fournée. 0 : à consommer le jour même.</p>' . $d)
         . bloc_etiquettes_gateaux() . bloc('Réglages', $reg);
}
function champs_appareil(?array $a): string {
    $t = fn($x) => $x === null ? '' : str_replace('.', ',', rtrim(rtrim(number_format((float)$x, 1, '.', ''), '0'), '.'));
    return champ('nom', 'Nom', $a['nom'] ?? '', ['maxlength' => '80', 'required' => true, 'placeholder' => 'Frigo de la cuisine', 'translate' => 'no', 'id' => 'f-nom-' . (int)($a['id'] ?? 0)])
         . liste_choix('genre', 'Type', HYGIENE_APPAREILS, $a['genre'] ?? 'frigo', ['id' => 'f-genre-' . (int)($a['id'] ?? 0)])
         . '<div class="ligne-champs">' . champ('tmin', 'Minimum (°C, facultatif)', $t($a['tmin'] ?? null), ['inputmode' => 'decimal', 'id' => 'f-tmin-' . (int)($a['id'] ?? 0)])
         . champ('tmax', 'Maximum (°C)', $t($a['tmax'] ?? null), ['inputmode' => 'decimal', 'id' => 'f-tmax-' . (int)($a['id'] ?? 0)]) . '</div>';
}
function page_hygiene_appareil(): void {
    hygiene_garde();
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') rediriger('hygiene', ['vue' => 'appareils']);
    csrf_verifier();
    $a = texte('_action', 40);
    if ($a === 'appareil') {
        $id = (int)($_GET['id'] ?? 0);
        $e = hygiene_appareil_enregistrer($id && hygiene_appareil($id) ? $id : null, texte('nom', 80), texte('genre', 20), texte('tmin', 8), texte('tmax', 8), !$id || isset($_POST['actif']));
        flash($e === '' ? 'ok' : 'erreur', $e === '' ? 'Appareil enregistré.' : $e);
    } elseif ($a === 'duree') {
        $pid = texte('produit_id', 80);
        $j = texte('jours', 3);
        if (!produit($pid)) flash('erreur', 'Gâteau introuvable.');
        elseif ($j === '') { hygiene_duree_fixer($pid, null); flash('ok', 'Durée retirée : la date limite se tapera à chaque fournée.'); }
        elseif (!preg_match('/^\d{1,3}$/', $j) || (int)$j > 365) flash('erreur', 'Une durée s\'écrit en jours, de 0 à 365.');
        else { hygiene_duree_fixer($pid, (int)$j); evenement('hygiene', 'Durée de conservation fixée : ' . $pid . ', ' . (int)$j . ' jour(s)', ['produit' => $pid]); flash('ok', 'Durée enregistrée.'); }
    } elseif ($a === 'etiquette') {
        $pid = texte('produit_id', 80);
        if (!produit($pid)) flash('erreur', 'Gâteau introuvable.');
        elseif (une_ligne(texte('conservation', 200)) === '') flash('erreur', 'Écrivez les conditions de conservation (par exemple : au réfrigérateur, entre 0 et 4 °C).');
        else { etiquette_fiche_enregistrer($pid, une_ligne(texte('conservation', 200)), une_ligne(texte('ingredients', 1200))); flash('ok', 'Texte de l\'étiquette relu et enregistré.'); }
    } elseif ($a === 'reglages') {
        parametre_ecrire('hygiene_releves_jour', choix('par_jour', ['1' => 1, '2' => 1], '1'));
        parametre_ecrire('hygiene_conservation_ans', choix('ans', ['0' => 1, '1' => 1, '2' => 1, '3' => 1, '5' => 1, '10' => 1], (string)HYGIENE_CONSERVATION_ANS));
        parametre_ecrire('etiquette_exploitant', une_ligne(texte('exploitant', 200)));
        evenement('hygiene', 'Réglages de l\'hygiène : ' . hygiene_par_jour() . ' relevé(s) par jour, conservation ' . hygiene_conservation_ans() . ' an(s)');
        flash('ok', 'Réglages enregistrés.');
    }
    rediriger('hygiene', ['vue' => 'appareils']);
}

/* ---------- Un relevé : fiche, versions, correction ---------- */

function page_hygiene_fiche(): void {
    hygiene_garde();
    $id = (int)($_GET['id'] ?? 0);
    $r = hygiene_releve($id);
    if (!$r) { flash('erreur', 'Relevé introuvable.'); rediriger('hygiene'); }
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        csrf_verifier();
        $a = texte('_action', 40);
        $src = array_map(fn($x) => is_string($x) ? $x : '', $_POST);
        $liens = array_map('intval', is_array($_POST['liens'] ?? null) ? $_POST['liens'] : []);
        if ($a === 'arrivee') { $e = hygiene_arrivee($id); flash($e === '' ? 'ok' : 'erreur', $e === '' ? 'Arrivée notée.' : $e); rediriger('hygiene-fiche', ['id' => $id]); }
        if ($a === 'corriger' && $r['remplace_par'] === null && $r['genre'] !== 'sans_production') {
            $photo = ''; $err = null;
            if ($r['genre'] === 'froid') [$v, $err] = hygiene_froid_lu($src + ['appareil_id' => (string)$r['appareil_id']], $r);
            elseif ($r['genre'] === 'reception') { [$photo, $err] = hygiene_photo_garder($_FILES['photo'] ?? null); if ($err === null) [$v, $err] = hygiene_reception_lue($src, $photo, $r); }
            elseif ($r['genre'] === 'fournee') { [$v, $err] = hygiene_fournee_lue($src, $r); if ($err === null) { if (isset($v['_retenir'])) hygiene_duree_fixer((string)$v['produit_id'], (int)$v['_retenir']); unset($v['_retenir']); } }
            else [$v, $err] = hygiene_livraison_lue($src, $r);
            if ($err === null) {
                [$nid, $err] = hygiene_noter($r['genre'], $v, in_array($r['genre'], ['fournee', 'livraison'], true) ? $liens : array_map(fn($x) => (int)$x['id'], hygiene_liens_de($id)), $id, texte('motif', 300));
                if ($err === null || $err === '') { flash('ok', 'Correction notée : l\'original reste visible.'); rediriger('hygiene-fiche', ['id' => $nid]); }
            }
            if ($photo !== '' && ($f = hygiene_photo_chemin($photo))) @unlink($f);
            hygiene_garder_brouillon('corriger', $src);
            flash('erreur', (string)$err);
        }
        rediriger('hygiene-fiche', ['id' => $id]);
    }
    $app = $r['appareil_id'] !== null ? hygiene_appareil((int)$r['appareil_id']) : null;
    $cl = $r['client_id'] !== null ? client((int)$r['client_id']) : null;
    $k = $r['commande_id'] !== null ? commande((int)$r['commande_id']) : null;
    $num = fn($x) => $x === null ? null : (float)$x;
    $liensDe = hygiene_liens_de($id);
    $liensVers = hygiene_liens_vers($id);
    $lien = fn(array $x) => '<a href="' . h(u('hygiene-fiche', ['id' => (int)$x['id']])) . '" translate="no">' . h($x['numero_lot'] !== '' ? $x['produit'] . ' · ' . $x['numero_lot'] : ($x['produit'] !== '' ? $x['produit'] : '#' . $x['id'])) . '</a>';
    $fiche = definition([
        'Relevé' => h(HYGIENE_GENRES[$r['genre']] ?? ''),
        'Jour' => '<a href="' . h(u('hygiene', ['vue' => 'jour', 'jour' => $r['jour']])) . '">' . h(ucfirst(date_fr($r['jour'], 'jour'))) . '</a>',
        'Noté' => h(substr($r['le'], 11, 5)) . ' · <span translate="no">' . h(explode(' ', trim($r['par']))[0] ?? '') . '</span>',
        'Appareil' => $app ? '<span translate="no">' . h($app['nom']) . '</span> · ' . h(hygiene_plage($num($r['tmin']), $num($r['tmax']))) : '',
        'Température' => $r['temperature'] !== null ? h(hygiene_temperature((float)$r['temperature'])) . ((int)$r['hors_plage'] ? ' <span class="etiq etiq-alerte">Hors plage</span>' : '') : '',
        'Ce qui a été fait' => $r['action'] !== '' ? '<span translate="no">' . nl2br(h($r['action'])) . '</span>' : '',
        'Gâteau' => $r['genre'] === 'fournee' ? '<span translate="no">' . h($r['produit']) . '</span>' : '',
        'Produit' => $r['genre'] === 'reception' && $r['produit'] !== '' ? '<span translate="no">' . h($r['produit']) . '</span>' : '',
        'Fournisseur' => $r['fournisseur'] !== '' ? '<span translate="no">' . h($r['fournisseur']) . '</span>' : '',
        'Lot' => $r['lot'] !== '' ? '<span translate="no">' . h($r['lot']) . '</span>' : '',
        'Numéro de lot' => $r['numero_lot'] !== '' ? '<b translate="no">' . h($r['numero_lot']) . '</b>' : '',
        'Quantité' => $r['quantite'] !== '' ? '<span translate="no">' . h($r['quantite']) . '</span>' : '',
        'Date limite' => $r['dlc'] !== '' ? h(date_fr($r['dlc'], 'longue')) : '',
        'Restaurant' => $cl ? '<a href="' . h(u('client', ['id' => (int)$cl['id']])) . '" translate="no">' . h(hygiene_restaurant($cl)) . '</a>' : '',
        'Commande' => $k ? '<a href="' . h(u('commande', ['id' => (int)$k['id']])) . '" translate="no">' . h($k['reference']) . '</a>' : '',
        'Départ' => $r['depart_le'] !== '' ? h(substr($r['depart_le'], 11, 5)) . ($r['temp_depart'] !== null ? ' · ' . h(hygiene_temperature((float)$r['temp_depart'])) : '') : '',
        'Arrivée' => $r['genre'] === 'livraison' ? ($r['arrivee_le'] !== '' ? h(substr($r['arrivee_le'], 11, 5)) : 'Pas encore notée') : '',
        $r['genre'] === 'livraison' ? 'Lots livrés' : 'Réceptions utilisées' => $liensDe ? implode(', ', array_map($lien, $liensDe)) : '',
        $r['genre'] === 'reception' ? 'Utilisée dans' : 'Livré à' => $liensVers ? implode(', ', array_map(fn($x) => $x['genre'] === 'livraison' && ($c2 = client((int)$x['client_id'])) ? '<a href="' . h(u('hygiene-fiche', ['id' => (int)$x['id']])) . '" translate="no">' . h(hygiene_restaurant($c2)) . '</a>' : $lien($x), $liensVers)) : '',
        'Note' => $r['note'] !== '' ? '<span translate="no">' . nl2br(h($r['note'])) . '</span>' : '',
        'Photo de l\'étiquette' => $r['photo'] !== '' && hygiene_photo_chemin((string)$r['photo']) ? '<a href="' . h(u('hygiene-photo', ['id' => $id])) . '" target="_blank" rel="noopener"><img class="photo-releve" src="' . h(u('hygiene-photo', ['id' => $id])) . '" alt="Photo de l\'étiquette" loading="lazy"></a>' : '',
    ]);
    $etat = '';
    if ($r['remplace_par'] !== null && (int)$r['remplace_par'] > 0) $etat = '<p class="bandeau-obligations" role="note">' . icone('alerte') . '<span><b>Version corrigée.</b> <a href="' . h(u('hygiene-fiche', ['id' => (int)$r['remplace_par']])) . '">Voir la dernière version</a></span></p>';
    elseif ($r['remplace_par'] !== null) $etat = '<p class="bandeau-obligations calme" role="note">' . icone('stop') . '<span><b>Annulé</b> <span translate="no">' . h($r['motif']) . '</span></span></p>';
    $actions = '';
    if ($r['remplace_par'] === null && $r['genre'] === 'livraison' && $r['arrivee_le'] === '')
        $actions .= formulaire('arrivee', bouton(icone('valider') . 'Arrivée', 'principal'), [], u('hygiene-fiche', ['id' => $id]));
    if ($r['remplace_par'] === null && $r['genre'] !== 'sans_production') {
        $b = hygiene_brouillon('corriger');
        $champs = match ($r['genre']) {
            'froid' => champ('temperature', 'Température lue (°C)', $b['temperature'] ?? str_replace('.', ',', (string)(float)$r['temperature']), ['inputmode' => 'decimal', 'required' => true, 'id' => 'f-t-corr',
                           'data-plage-min' => $r['tmin'] === null ? false : (string)$r['tmin'], 'data-plage-max' => $r['tmax'] === null ? false : (string)$r['tmax'], 'data-plage-action' => 'f-action-corr'])
                       . '<div class="hors-plage" id="f-action-corr-bloc"' . ((int)$r['hors_plage'] || ($b['action'] ?? '') !== '' ? '' : ' hidden') . '>' . zone('action', 'Hors plage : qu\'avez-vous fait ?', $b['action'] ?? $r['action'], ['rows' => '2', 'maxlength' => '600', 'id' => 'f-action-corr', 'translate' => 'no']) . '</div>',
            'reception' => champs_reception($b + ['produit' => $r['produit'], 'fournisseur' => $r['fournisseur'], 'lot' => $r['lot'], 'dlc' => $r['dlc'], 'note' => $r['note'],
                               'temperature' => $r['temperature'] !== null ? str_replace('.', ',', (string)(float)$r['temperature']) : '']),
            'fournee' => champs_fournee($b, $r),
            default => champs_livraison($b, $r),
        };
        $champs = preg_replace('/<button type="submit"[^>]*>.*?<\/button>$/s', '', $champs) ?? $champs;
        $actions .= '<details class="replie"' . ($b ? ' open' : '') . '><summary>' . icone('crayon') . 'Corriger ce relevé</summary>'
            . formulaire('corriger', $champs . champ('motif', 'Pourquoi cette correction ?', $b['motif'] ?? '', ['maxlength' => '300', 'required' => true, 'translate' => 'no', 'placeholder' => 'Erreur de frappe, mauvais appareil…'])
                . '<p class="aide">L\'original reste visible avec l\'heure et la personne ; rien n\'est effacé.</p>' . bouton(icone('valider') . 'Enregistrer la correction', 'principal'),
                ['class' => 'form-releve'] + ($r['genre'] === 'reception' ? ['enctype' => 'multipart/form-data'] : []), u('hygiene-fiche', ['id' => $id])) . '</details>';
    }
    $v = hygiene_versions($id);
    $hist = '';
    if (count($v) > 1) foreach ($v as $x) $hist .= '<li' . ((int)$x['id'] === $id ? ' aria-current="true"' : '') . '><a href="' . h(u('hygiene-fiche', ['id' => (int)$x['id']])) . '"><span class="petit">' . h(date_fr(substr($x['le'], 0, 10), 'courte') . ' · ' . substr($x['le'], 11, 5)) . ' · <span translate="no">' . h(explode(' ', trim($x['par']))[0] ?? '') . '</span></span> '
        . h($x['remplace_id'] === null ? 'Original' : 'Correction') . ($x['motif'] !== '' && $x['remplace_id'] !== null ? ' : <span translate="no">' . h($x['motif']) . '</span>' : '') . '</a></li>';
    $c = titre_page('Relevé', '', '', u('hygiene', ['vue' => 'jour', 'jour' => $r['jour']])) . $etat . bloc('', $fiche) . ($actions !== '' ? bloc('Et maintenant', $actions) : '')
       . bloc_lot($r) . ($hist !== '' ? bloc('Versions', '<ul class="historique-demarche">' . $hist . '</ul>') : '');
    mise_en_page('Relevé', $c, 'hygiene', 'page-obligations page-hygiene');
}

/* ---------- Lot 3 : étiquettes des boîtes et problème sur un lot (US3, US4) ---------- */

/** Sur la fiche d'une fournée : les étiquettes (ou ce qui manque) et « Problème sur ce lot ». */
function bloc_lot(array $r): string {
    if ($r['genre'] !== 'fournee' || $r['remplace_par'] !== null) return '';
    $e = etiquette_contenu($r);
    if ($e['manque']) $et = '<p class="erreur">' . icone('alerte') . ' <b>Étiquette impossible pour l\'instant.</b></p><ul class="puces">' . implode('', array_map(fn($m) => '<li>' . h($m) . '</li>', $e['manque'])) . '</ul>';
    else $et = '<form method="get" action="' . h(gestion_chemin()) . '" class="form-releve"><input type="hidden" name="p" value="hygiene-etiquettes"><input type="hidden" name="id" value="' . (int)$r['id'] . '">'
        . '<div class="ligne-champs">' . liste_choix('format', 'Format', ETIQUETTE_FORMATS, 'a4', ['id' => 'f-format-etiq'])
        . champ('n', 'Nombre', '1', ['type' => 'number', 'min' => '1', 'max' => '96', 'inputmode' => 'numeric', 'id' => 'f-n-etiq']) . '</div>'
        . ($e['sans_ingredients'] ? '<p class="aide">' . icone('alerte') . ' Sans liste d\'ingrédients : saisissez la recette du gâteau, puis relisez le texte de l\'étiquette.</p>' : '')
        . bouton(icone('imprimer') . 'Préparer les étiquettes', 'principal') . '</form>';
    $pb = probleme_du_lot((string)$r['numero_lot']);
    $p = $pb && $pb['clos_le'] === '' ? '<p class="bandeau-obligations" role="note">' . icone('alerte') . '<span><b>Problème en cours sur ce lot.</b> <a href="' . h(u('hygiene-probleme', ['id' => (int)$pb['id']])) . '">Suivre les étapes</a></span></p>'
        : ($pb ? '<p class="petit"><a href="' . h(u('hygiene-probleme', ['id' => (int)$pb['id']])) . '">Problème clos le ' . h(date_fr(substr($pb['clos_le'], 0, 10), 'longue')) . '</a></p>' : '')
        . '<details class="replie"><summary>' . icone('alerte') . 'Problème sur ce lot</summary>'
        . formulaire('probleme', zone('description', 'Que s\'est-il passé ?', '', ['rows' => '3', 'maxlength' => '1500', 'required' => true, 'translate' => 'no', 'id' => 'f-pb-desc'],
              'Qui l\'a signalé, quoi, quand : un restaurant, un client malade, un défaut vu en cuisine… Pas de nom de particulier.')
          . bouton(icone('alerte') . 'Ouvrir le suivi du problème', 'principal'), ['data-confirmer' => 'Ouvrir un problème sur ce lot ? La gestion montre qui l\'a reçu et les étapes du retrait.'], u('hygiene-probleme', ['lot' => (int)$r['id']])) . '</details>';
    return bloc(icone('imprimer') . ' Étiquettes des boîtes', $et) . bloc(icone('alerte') . ' En cas de problème', $p);
}

/** Les étiquettes prêtes à imprimer (planche A4 ou rouleau) ; en français, la langue de l'étiquette. */
function page_hygiene_etiquettes(): void {
    hygiene_garde();
    $f = hygiene_releve((int)($_GET['id'] ?? 0));
    if (!$f || $f['genre'] !== 'fournee') { flash('erreur', 'Lot introuvable.'); rediriger('hygiene'); }
    $e = etiquette_contenu($f);
    if ($e['manque']) { flash('erreur', 'Étiquette impossible pour l\'instant : voyez ce qui manque sur la fiche du lot.'); rediriger('hygiene-fiche', ['id' => (int)$f['id']]); }
    $format = choix('format', ETIQUETTE_FORMATS, 'a4');
    $n = max(1, min(96, (int)($_GET['n'] ?? 1)));
    $un = '<div class="etiquette" translate="no" lang="fr"><b class="e-nom">' . h($e['nom']) . '</b>'
        . ($e['ingredients'] !== '' ? '<span class="e-ing">Ingrédients : ' . etiquette_ingredients_html($e['ingredients']) . '.</span>' : '')
        . '<span class="e-all"><b>Allergènes :</b> ' . h($e['allergenes']) . '.</span>'
        . '<span class="e-dlc"><b>À consommer jusqu\'au ' . h(date('d/m/Y', (int)strtotime($e['dlc']))) . '</b> · <span class="e-lot">Lot ' . h($e['lot']) . '</span></span>'
        . '<span class="e-cons">' . h($e['conservation']) . '.</span><span class="e-exp">' . h($e['exploitant']) . '</span></div>';
    $c = titre_page('Étiquettes', '<span translate="no">' . h($e['nom'] . ' · lot ' . $e['lot']) . '</span>', '<button type="button" class="btn principal" data-imprimer>' . icone('imprimer') . 'Imprimer</button>', u('hygiene-fiche', ['id' => (int)$f['id']]))
       . '<p class="aide ecran-seul">' . h($format === 'a4' ? 'Planche A4 de 24 étiquettes (3 x 8, 63,5 x 33,9 mm). À l\'impression : taille réelle (100 %), sans marges ajoutées.' : 'Rouleau 55 x 30 mm : une étiquette par page. À l\'impression, choisissez l\'imprimante à étiquettes et le format 55 x 30 mm.') . '</p>'
       . '<div class="planche planche-' . h($format) . '">' . str_repeat($un, $n) . '</div>';
    evenement('hygiene', 'Étiquettes préparées : lot ' . $e['lot'] . ' (' . $n . ', ' . $format . ')');
    mise_en_page('Étiquettes', $c, 'hygiene', 'page-obligations page-etiquettes etiquettes-' . $format);
}

/** Le suivi d'un problème sur un lot : périmètre, étapes à cocher, brouillon pour la DDPP, clôture. */
function page_hygiene_probleme(): void {
    hygiene_garde();
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        csrf_verifier();
        $a = texte('_action', 40);
        if ($a === 'probleme') {
            [$id, $err] = probleme_ouvrir((int)($_GET['lot'] ?? 0), texte('description', 1500));
            if ($err !== '') { flash('erreur', $err); rediriger('hygiene-fiche', ['id' => (int)($_GET['lot'] ?? 0)]); }
            flash('ok', 'Problème ouvert : suivez les étapes, la démarche est aussi dans Obligations.');
            rediriger('hygiene-probleme', ['id' => $id]);
        }
        $id = (int)($_GET['id'] ?? 0);
        if ($a === 'etape') { $err = probleme_cocher($id, texte('etape', 20), texte('fait', 1) === '1', texte('note', 300)); flash($err === '' ? 'ok' : 'erreur', $err === '' ? 'Étape notée.' : $err); }
        if ($a === 'clore') { $err = probleme_clore($id, texte('bilan', 1500)); flash($err === '' ? 'ok' : 'erreur', $err === '' ? 'Problème clos. Pensez à clore aussi la démarche dans Obligations, avec le lien de la preuve.' : $err); }
        rediriger('hygiene-probleme', ['id' => $id]);
    }
    $pb = probleme((int)($_GET['id'] ?? 0));
    $f = $pb ? hygiene_releve((int)$pb['fournee_id']) : null;
    if (!$pb || !$f) { flash('erreur', 'Problème introuvable.'); rediriger('hygiene'); }
    while ($f['remplace_par'] !== null && (int)$f['remplace_par'] > 0 && ($g = hygiene_releve((int)$f['remplace_par']))) $f = $g;
    $per = probleme_perimetre($f);
    $ouvert = $pb['clos_le'] === '';
    $lien = fn(array $x, string $t) => '<li><a href="' . h(u('hygiene-fiche', ['id' => (int)$x['id']])) . '"><span translate="no">' . h($t) . '</span></a></li>';
    $quoi = definition([
        'Lot' => '<a href="' . h(u('hygiene-fiche', ['id' => (int)$f['id']])) . '" translate="no">' . h($f['produit'] . ' · ' . $f['numero_lot']) . '</a>',
        'Fabriqué le' => h(date_fr($f['jour'], 'longue')),
        'Date limite' => $f['dlc'] !== '' ? h(date_fr($f['dlc'], 'longue')) : '',
        'Ouvert' => h(date_fr(substr($pb['ouvert_le'], 0, 10), 'longue') . ' · ' . substr($pb['ouvert_le'], 11, 5)) . ' · <span translate="no">' . h(explode(' ', trim($pb['ouvert_par']))[0] ?? '') . '</span>',
        'Que s\'est-il passé ?' => '<span translate="no">' . nl2br(h($pb['description'])) . '</span>',
        'Clos' => !$ouvert ? h(date_fr(substr($pb['clos_le'], 0, 10), 'longue')) . ($pb['bilan'] !== '' ? ' · <span translate="no">' . h($pb['bilan']) . '</span>' : '') : '',
    ]);
    $qui = '<h3>Restaurants livrés</h3>' . ($per['livraisons'] ? '<ul class="puces">' . implode('', array_map(function ($x) {
                $c = client((int)$x['client_id']);
                return '<li><span translate="no">' . ($c ? '<a href="' . h(u('client', ['id' => (int)$c['id']])) . '">' . h(hygiene_restaurant($c)) . '</a>' : '') . '</span>'
                     . ' · ' . h(date_fr((string)$x['jour'], 'courte') . ' · Départ ' . substr($x['depart_le'], 11, 5)) . ($c && $c['telephone'] !== '' ? ' · <a href="tel:' . h($c['telephone']) . '" translate="no">' . h($c['telephone']) . '</a>' : '') . '</li>';
            }, $per['livraisons'])) . '</ul>' : '<p class="vide">Aucune livraison reliée à ce lot.</p>')
         . '<h3>Commandes peut-être concernées</h3><p class="aide">Les commandes de ce gâteau retirées ou livrées entre la fournée et sa date limite : à vérifier une par une.</p>'
         . ($per['commandes'] ? '<ul class="puces">' . implode('', array_map(fn($k) => '<li><a href="' . h(u('commande', ['id' => (int)$k['id']])) . '" translate="no">' . h($k['reference']) . '</a> · ' . h(date_fr((string)$k['retrait_date'], 'courte')) . '</li>', $per['commandes'])) . '</ul>' : '<p class="vide">Aucune.</p>')
         . '<h3>Matières premières utilisées</h3>' . ($per['receptions'] ? '<ul class="puces">' . implode('', array_map(fn($x) => $lien($x, trim(($x['produit'] !== '' ? $x['produit'] : 'Étiquette en photo') . ($x['lot'] !== '' ? ' · ' . $x['lot'] : '') . ($x['fournisseur'] !== '' ? ' · ' . $x['fournisseur'] : ''))), $per['receptions'])) . '</ul>' : '<p class="vide">Aucune réception reliée à ce lot.</p>');
    $et = probleme_etapes($pb);
    $s = '<ol class="etapes-probleme">';
    foreach (PROBLEME_ETAPES as $k => [$titre, $aide]) {
        $fait = isset($et[$k]);
        $s .= '<li class="' . ($fait ? 'fait' : '') . '"><b>' . h($titre) . '</b><span class="aide">' . h($aide) . '</span>'
            . ($fait ? '<span class="petit">' . h('Fait le ' . date_fr(substr($et[$k]['le'], 0, 10), 'courte') . ' à ' . substr($et[$k]['le'], 11, 5)) . ' · <span translate="no">' . h(explode(' ', trim($et[$k]['par']))[0] ?? '') . ($et[$k]['note'] !== '' ? ' · ' . h($et[$k]['note']) : '') . '</span></span>' : '')
            . ($ouvert ? formulaire('etape', '<input type="hidden" name="etape" value="' . h($k) . '"><input type="hidden" name="fait" value="' . ($fait ? '0' : '1') . '">'
                . ($fait ? '' : champ('note', 'Note (facultatif)', '', ['maxlength' => '300', 'translate' => 'no', 'id' => 'f-note-' . $k]))
                . bouton($fait ? 'Décocher' : icone('valider') . 'C\'est fait', $fait ? 'discret' : 'secondaire'), ['class' => 'form-etape'], u('hygiene-probleme', ['id' => (int)$pb['id']])) : '') . '</li>';
    }
    $s .= '</ol>';
    $br = '<pre id="brouillon-ddpp" class="texte-client" translate="no" lang="fr">' . h(probleme_brouillon($pb, $f)) . '</pre>'
        . '<p><button type="button" class="btn secondaire" data-copier="brouillon-ddpp">' . icone('copier') . 'Copier le texte</button></p>'
        . '<p class="aide">À coller dans un mail à la DDPP des Hauts-de-Seine, envoyé par vous depuis contact@ (la gestion n\'envoie rien). Relisez-le avant : il reprend les étapes déjà cochées.</p>';
    $c = titre_page('Problème sur un lot', '', '', u('hygiene-fiche', ['id' => (int)$f['id']]))
       . ($ouvert ? '<p class="bandeau-obligations" role="note">' . icone('alerte') . '<span><b>Ne vendez plus et ne livrez plus ce lot.</b> <span>En cas de malaise grave, le 15 d\'abord.</span></span></p>' : '')
       . bloc('', $quoi) . bloc('Qui a reçu ce lot ?', $qui) . bloc('Les étapes du retrait', $s) . bloc('Brouillon pour la DDPP', $br);
    if ($ouvert) $c .= bloc('Clore', formulaire('clore', zone('bilan', 'Bilan (obligatoire s\'il reste des étapes)', '', ['rows' => '2', 'maxlength' => '1500', 'translate' => 'no', 'id' => 'f-bilan'], 'Par exemple : la DDPP n\'a pas demandé de rappel public.')
          . bouton('Clore le problème', 'secondaire'), ['data-confirmer' => 'Clore ce problème ?'], u('hygiene-probleme', ['id' => (int)$pb['id']])));
    mise_en_page('Problème sur un lot', $c, 'hygiene', 'page-obligations page-hygiene');
}

/** Hygiène › Appareils : le texte d'étiquette de chaque gâteau, relu par Youngmi (proposé depuis le site et la recette). */
function bloc_etiquettes_gateaux(): string {
    $s = '';
    foreach (produits_lister() as $p) {
        if ($p['statut'] === 'retire') continue;
        $fi = etiquette_fiche((string)$p['id']);
        $cons = $fi['conservation'] !== '' ? $fi['conservation'] : trim((string)($p['cat']['conservation'] ?? ''));
        $ing = $fi['ingredients'] !== '' ? $fi['ingredients'] : etiquette_ingredients_proposes($p);
        $all = etiquette_allergenes($p);
        $s .= '<details class="replie carte-appareil"><summary><b translate="no">' . h($p['nom']) . '</b> ' . ($fi['etiquette_le'] !== '' ? '<span class="chip-par">Relu</span>' : '<span class="etiq etiq-change">À relire</span>')
            . (empty($p['valide_le']) ? '<span class="etiq etiq-alerte">Fiche non validée</span>' : '') . '</summary>'
            . '<p class="petit"><b>Allergènes</b> : <span translate="no">' . h($all ?? 'inconnus (ni site, ni recette)') . '</span></p>'
            . formulaire('etiquette', '<input type="hidden" name="produit_id" value="' . h($p['id']) . '">'
                . zone('conservation', 'Conservation', $cons, ['rows' => '2', 'maxlength' => '200', 'translate' => 'no', 'id' => 'f-cons-' . h($p['id'])], 'Par exemple : au réfrigérateur, entre 0 et 4 °C.')
                . zone('ingredients', 'Ingrédients, du plus lourd au plus léger', $ing, ['rows' => '3', 'maxlength' => '1200', 'translate' => 'no', 'id' => 'f-ing-' . h($p['id'])],
                    'Proposés depuis la recette : vérifiez les noms et l\'ordre (les ingrédients comptés à la pièce, comme les œufs, sont à la fin : placez-les selon leur poids). Les allergènes s\'impriment en gras.')
                . bouton(icone('valider') . 'J\'ai relu ce texte', 'principal'), ['class' => 'form-releve'], u('hygiene-appareil')) . '</details>';
    }
    return bloc('Étiquettes des gâteaux', '<p class="aide">L\'étiquette ne reprend que ce que vous avez relu ici, avec les allergènes du site (ou de la recette pour la gamme pro), la date limite et le numéro du lot.</p>' . $s);
}
