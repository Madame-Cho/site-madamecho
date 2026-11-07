<?php
/*
 * Madame Cho : espace de gestion. Produits (la carte du site), recettes par taille ou par parfum, coûts et marges,
 * allergènes de la recette comparés à ceux affichés sur le site (R-39), ingrédients.
 * Les textes publics et les prix restent ceux du site (donnees.py) : ici, on ne les recopie jamais.
 */
declare(strict_types=1);
namespace MadameCho\Gestion;

const TVA = [55 => '5,5 % (à emporter, consommation différée)', 100 => '10 % (à consommer sur place)', 200 => '20 %'];

/** Quantités vendues (commandes remises) par produit et par variante depuis une date. */
function ventes_par_produit(string $depuis): array {
    $v = [];
    foreach (lignes("SELECT lignes FROM commandes WHERE statut = 'remise' AND COALESCE(remise_le, recue_le) >= ?", [$depuis]) as $k) {
        foreach (json_decode($k['lignes'], true) ?: [] as $l) {
            [$pid, $code] = array_pad(explode('|', (string)($l['v'] ?? '')), 2, '');
            if ($pid === '') continue;
            $v[$pid]['q'] = ($v[$pid]['q'] ?? 0) + (int)$l['q'];
            $v[$pid]['ca'] = ($v[$pid]['ca'] ?? 0) + (int)$l['q'] * (int)$l['prix'];
            $v[$pid]['variantes'][$code] = ($v[$pid]['variantes'][$code] ?? 0) + (int)$l['q'];
        }
    }
    return $v;
}
function pourcent(int $part, int $tout): string { return $tout ? number_format($part / $tout * 100, 0, ',', ' ') . ' %' : '—'; }
function nombre_fr(float $n, int $dec = 2): string { $s = number_format($n, $dec, ',', ' '); return $dec ? rtrim(rtrim($s, '0'), ',') : $s; }
function quantite_lue(string $s): ?float {
    $s = str_replace([' ', ','], ['', '.'], trim($s));
    return preg_match('/^\d{1,6}(\.\d{1,3})?$/', $s) ? (float)$s : null;
}
function prix_unite_texte(string $unite): string { return match ($unite) { 'piece' => 'la pièce', 'ml' => 'le litre', default => 'le kg' }; }
function unite_texte(string $unite, float $q): string { return $unite === 'piece' ? ($q > 1 ? 'pièces' : 'pièce') : (UNITES[$unite] ?? $unite); }
function allergenes_texte(array $codes): string { return implode(', ', array_map(fn($a) => ALLERGENES[$a] ?? $a, $codes)); }

/* ---------- Liste ---------- */
function page_produits(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        csrf_verifier();
        if (texte('_action', 40) === 'pro_ajouter') {
            $nom = une_ligne(texte('nom', 80)); $ht = centimes(texte('prix_ht', 12)); $tva = (int)texte('tva', 3);
            if ($nom === '' || $ht === null || $ht <= 0) flash('erreur', 'Indiquez le nom et le prix HT (par exemple 3,50).');
            else {
                $pid = produit_pro_enregistrer(null, $nom, une_ligne(texte('unite', 40)), $ht, isset(TVA[$tva]) ? $tva : 55);
                flash('ok', 'Ajouté à la gamme pro.');
                rediriger('produit', ['id' => $pid]);
            }
        }
        rediriger('produits');
    }
    $ps = produits_lister();
    $ventes = ventes_par_produit(date('Y-m-d', strtotime('-12 months')));
    $c = titre_page('Produits', '', lien_bouton(u('ingredients'), 'Ingrédients', 'discret'));
    $cartes = ''; $pros = '';
    foreach ($ps as $p) {
        $vars = produit_variantes($p);
        if (est_pro($p)) { $pros .= carte_produit_pro($p, $vars, $ventes); continue; }
        $rec = recette($p);
        $avecRecette = count(array_filter($rec, fn($r) => $r['lignes']));
        $prixMin = $vars ? min(array_column($vars, 'prix')) : 0;
        $offre = $p['cat']['mode'] === 'taille' ? count($vars) . ' tailles · dès ' . euros($prixMin) : count($vars) . ' parfums · ' . euros($prixMin) . ' la pièce';
        $marges = [];
        foreach ($rec as $r) if ($r['marge'] !== null && $r['ht']) $marges[] = $r['marge'] / $r['ht'];
        $etat = [];
        $etat[] = $avecRecette ? 'Recette : ' . $avecRecette . '/' . count($vars) . ($marges ? ' · marge ' . number_format(min($marges) * 100, 0) . ' à ' . number_format(max($marges) * 100, 0) . ' %' : '') : 'Recette à saisir';
        $etat[] = $p['valide_le'] ? 'Fiche validée le ' . date_fr($p['valide_le']) : 'Fiche à valider par Youngmi';
        $alerte = '';
        if ($avecRecette) {
            $cmp = allergenes_comparer($p, $rec);
            if ($cmp['manquants']) $alerte = '<span class="l5"><span class="drapeau">' . icone('alerte') . 'Allergène absent du site : ' . h(allergenes_affiches($cmp['manquants'])) . '</span></span>';
        }
        $vendu = isset($ventes[$p['id']]) ? $ventes[$p['id']]['q'] . ' vendu(s) en 12 mois · ' . euros($ventes[$p['id']]['ca']) : 'Aucune vente remise en 12 mois';
        $cartes .= '<a class="carte-cmd carte-produit" href="' . h(u('produit', ['id' => $p['id']])) . '">'
                 . '<span class="l1"><b translate="no">' . h($p['nom']) . '</b><span class="statut s-produit-' . h($p['statut']) . '">' . h(STATUTS_PRODUIT[$p['statut']] ?? $p['statut']) . '</span></span>'
                 . '<span class="l2">' . h($offre) . '</span><span class="l3">' . h(implode(' · ', $etat)) . '</span><span class="l4">' . h($vendu) . '</span>' . $alerte . '</a>';
    }
    $c .= $cartes !== '' ? '<div class="liste">' . $cartes . '</div>' : '<p class="vide">La carte du site n\'est pas encore chargée.</p>';
    $c .= '<p class="aide centre">Gâteaux, prix et textes se changent sur le site, pas ici.</p>';
    // Gamme pro : ce que les restaurants commandent, aux prix HT de Qonto Facturation ; jamais sur le site
    $ajout = '<details class="replie"><summary>' . icone('ajouter') . 'Ajouter un produit à la gamme pro</summary>'
        . formulaire('pro_ajouter', champ('nom', 'Nom (comme dans Qonto)', '', ['maxlength' => '80', 'required' => true, 'id' => 'pro-nom'])
            . '<div class="ligne-champs">' . champ('prix_ht', 'Prix HT (€)', '', ['inputmode' => 'decimal', 'placeholder' => '3,50', 'required' => true, 'id' => 'pro-ht'])
            . champ('unite', 'Vendu', 'la pièce', ['maxlength' => '40', 'placeholder' => 'la pièce, le kg, la plaque…', 'id' => 'pro-unite']) . '</div>'
            . liste_choix('tva', 'TVA', TVA, '55', ['id' => 'pro-tva']) . bouton('Ajouter', 'secondaire'), [], u('produits')) . '</details>';
    $c .= '<h2 class="titre-section" id="gamme-pro">Gamme pro (restaurants)</h2>'
        . ($pros !== '' ? '<div class="liste">' . $pros . '</div>' : '<p class="vide">Pas encore de produit pro : ajoutez-les, ou importez le catalogue de Qonto Facturation.</p>')
        . $ajout . '<p class="aide centre">Prix HT, comme dans Qonto. Jamais affichés sur le site.</p>';
    mise_en_page('Produits', $c, 'produits');
}
/** La carte d'un produit de la gamme pro dans la liste : prix HT, recette, ventes. */
function carte_produit_pro(array $p, array $vars, array $ventes): string {
    $v = $vars['u'] ?? ['libelle' => '', 'ht' => 0];
    $rec = recette($p)['u'] ?? null;
    $etat = $rec && $rec['lignes'] ? 'Recette · coût ' . euros($rec['cout']) . ($rec['marge'] !== null && $rec['ht'] ? ' · marge ' . number_format($rec['marge'] / $rec['ht'] * 100, 0) . ' %' : '') : 'Recette à saisir';
    $vendu = isset($ventes[$p['id']]) ? $ventes[$p['id']]['q'] . ' vendu(s) en 12 mois' : 'Aucune livraison en 12 mois';
    return '<a class="carte-cmd carte-produit" href="' . h(u('produit', ['id' => $p['id']])) . '">'
         . '<span class="l1"><b translate="no">' . h($p['nom']) . '</b><span class="statut s-produit-' . h($p['statut']) . '">' . h(STATUTS_PRODUIT[$p['statut']] ?? $p['statut']) . '</span></span>'
         . '<span class="l2">' . h(euros((int)$v['ht'])) . ' HT · <span translate="no">' . h($v['libelle']) . '</span></span><span class="l3">' . h($etat) . '</span><span class="l4">' . h($vendu) . '</span></a>';
}

/* ---------- Un produit ---------- */
function page_produit(): void {
    $id = is_string($_GET['id'] ?? null) ? $_GET['id'] : '';
    $p = preg_match('/^[a-z0-9\-]{1,60}$/', $id) ? produit($id) : null;
    if (!$p) rediriger('produits');
    $vars = produit_variantes($p);
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        csrf_verifier();
        $a = texte('_action', 40);
        switch ($a) {
            case 'pro':
                if (!est_pro($p)) break;
                $nom = une_ligne(texte('nom', 80)); $ht = centimes(texte('prix_ht', 12)); $tva = (int)texte('tva', 3);
                if ($nom === '' || $ht === null || $ht <= 0) { flash('erreur', 'Indiquez le nom et le prix HT (par exemple 3,50).'); break; }
                produit_pro_enregistrer($id, $nom, une_ligne(texte('unite', 40)), $ht, isset(TVA[$tva]) ? $tva : (int)$p['tva'], (string)($p['cat']['qonto'] ?? ''));
                produit_modifier($id, ['statut' => choix('statut', ['pro' => 1, 'retire' => 1], $p['statut'])]);
                flash('ok', 'Gamme pro enregistrée.');
                break;
            case 'fiche':
                $lien = une_ligne(texte('fiche_lien', 300));
                if ($lien !== '' && !preg_match('#^https://#', $lien)) { flash('erreur', 'Le lien de la fiche technique doit commencer par https://'); break; }
                $tva = isset($_POST['tva']) ? (int)texte('tva', 3) : (int)$p['tva'];
                produit_modifier($id, ['statut' => est_pro($p) ? $p['statut'] : choix('statut', array_diff_key(STATUTS_PRODUIT, ['pro' => 1]), $p['statut']), 'fiche_lien' => $lien, 'valide_le' => date_valide(texte('valide_le', 10)) ?? '',
                                       'valide_par' => une_ligne(texte('valide_par', 60)), 'emballage' => centimes(texte('emballage', 12)) ?? 0,
                                       'tva' => isset(TVA[$tva]) ? $tva : 55, 'notes' => texte('notes', 3000), 'recette' => texte('recette', 8000)]);
                flash('ok', 'Fiche enregistrée.');
                break;
            case 'ligne_ajouter':
                $v = texte('variante', 40);
                $qt = quantite_lue(texte('quantite', 12));
                if (!isset($vars[$v]) || $qt === null || $qt <= 0) { flash('erreur', 'Choisissez la taille ou le parfum, et une quantité (par exemple 120 ou 0,5).'); break; }
                $iid = texte('ingredient', 12);
                if ($iid === 'nouveau') {
                    $nom = une_ligne(texte('nouveau_nom', 80));
                    if ($nom === '') { flash('erreur', 'Donnez un nom au nouvel ingrédient.'); break; }
                    $iid = (string)ingredient_enregistrer(null, ['nom' => $nom, 'unite' => choix('nouveau_unite', UNITES, 'g'), 'prix' => centimes(texte('nouveau_prix', 12)) ?? 0,
                                                               'allergenes' => [], 'fournisseur' => '', 'achat' => '', 'notes' => '', 'actif' => true]);
                }
                if (!ctype_digit($iid) || !ingredient((int)$iid)) { flash('erreur', 'Choisissez un ingrédient.'); break; }
                q("INSERT INTO recettes (produit_id, variante, ingredient_id, quantite, note) VALUES (?, ?, ?, ?, ?)", [$id, $v, (int)$iid, $qt, une_ligne(texte('note', 120))]);
                evenement('recette', 'Recette ' . $vars[$v]['libelle'] . ' : ' . ingredient((int)$iid)['nom'] . ' ajouté', ['produit' => $id]);
                rediriger('produit', ['id' => $id, 'v' => $v], 'v-' . $v);
            case 'ligne_modifier':
                $lid = entier('ligne', 1);
                $qt = quantite_lue(texte('quantite', 12));
                $l = ligne("SELECT * FROM recettes WHERE id = ? AND produit_id = ?", [$lid, $id]);
                if ($l && $qt !== null && $qt > 0) q("UPDATE recettes SET quantite = ?, note = ? WHERE id = ?", [$qt, une_ligne(texte('note', 120)), $lid]);
                if ($l) rediriger('produit', ['id' => $id, 'v' => $l['variante']], 'v-' . $l['variante']);
                break;
            case 'ligne_supprimer':
                $lid = entier('ligne', 1);
                $l = ligne("SELECT r.*, i.nom FROM recettes r JOIN ingredients i ON i.id = r.ingredient_id WHERE r.id = ? AND r.produit_id = ?", [$lid, $id]);
                if ($l) {
                    q("DELETE FROM recettes WHERE id = ?", [$lid]);
                    evenement('recette', 'Recette ' . ($vars[$l['variante']]['libelle'] ?? $l['variante']) . ' : ' . $l['nom'] . ' retiré', ['produit' => $id]);
                    rediriger('produit', ['id' => $id, 'v' => $l['variante']], 'v-' . $l['variante']);
                }
                break;
            case 'copier':
                $de = texte('de', 40); $vers = texte('vers', 40);
                $coef = quantite_lue(texte('coefficient', 8));
                if (!isset($vars[$de], $vars[$vers]) || $de === $vers || !$coef) { flash('erreur', 'Choisissez deux tailles ou parfums différents et un coefficient.'); break; }
                $src = lignes("SELECT * FROM recettes WHERE produit_id = ? AND variante = ? ORDER BY id", [$id, $de]);
                if (!$src) { flash('erreur', 'La recette « ' . $vars[$de]['libelle'] . ' » est vide.'); break; }
                db()->beginTransaction();
                q("DELETE FROM recettes WHERE produit_id = ? AND variante = ?", [$id, $vers]);
                foreach ($src as $l) q("INSERT INTO recettes (produit_id, variante, ingredient_id, quantite, note) VALUES (?, ?, ?, ?, ?)", [$id, $vers, $l['ingredient_id'], round((float)$l['quantite'] * $coef, 2), $l['note']]);
                db()->commit();
                evenement('recette', 'Recette ' . $vars[$vers]['libelle'] . ' copiée de ' . $vars[$de]['libelle'] . ' × ' . nombre_fr($coef), ['produit' => $id]);
                flash('ok', 'Recette copiée : vérifiez les quantités (les œufs, les fruits entiers…).');
                rediriger('produit', ['id' => $id, 'v' => $vers], 'v-' . $vers);
        }
        rediriger('produit', ['id' => $id]);
    }
    $p = produit($id);
    $cat = $p['cat'];
    $rec = recette($p);
    $ventes = ventes_par_produit(date('Y-m-d', strtotime('-12 months')))[$id] ?? ['q' => 0, 'ca' => 0, 'variantes' => []];
    $pro = est_pro($p);
    $c = titre_page('<span translate="no">' . h($p['nom']) . '</span>', '<span class="statut s-produit-' . h($p['statut']) . '">' . h(STATUTS_PRODUIT[$p['statut']] ?? '') . '</span> · '
        . ($pro ? 'restaurants, prix HT' : '<a href="../' . h($cat['url'] ?? ('carte/' . $id . '/')) . '" target="_blank" rel="noopener">Voir sur le site</a>'), '', $pro ? u('produits') . '#gamme-pro' : u('produits'));
    if ($pro) {
        // Gamme pro : le prix HT des factures Qonto, l'unité, la TVA
        $v = $vars['u'];
        $f = champ('nom', 'Nom (comme dans Qonto)', $p['nom'], ['maxlength' => '80', 'required' => true])
           . '<div class="ligne-champs">' . champ('prix_ht', 'Prix HT (€)', number_format((int)$v['ht'] / 100, 2, ',', ''), ['inputmode' => 'decimal', 'required' => true])
           . champ('unite', 'Vendu', $v['libelle'], ['maxlength' => '40', 'placeholder' => 'la pièce, le kg, la plaque…']) . '</div>'
           . '<div class="ligne-champs">' . liste_choix('tva', 'TVA', TVA, (string)$p['tva']) . liste_choix('statut', 'Dans la gamme', ['pro' => 'Gamme pro', 'retire' => 'Retiré'], $p['statut']) . '</div>'
           . bouton('Enregistrer', 'secondaire');
        $c .= bloc('Gamme pro', definition(['Prix HT' => '<b>' . h(euros((int)$v['ht'])) . '</b> · <span translate="no">' . h($v['libelle']) . '</span>', 'TVA' => h(nombre_tva((int)$p['tva'])),
                                            'Prix TTC' => h(euros((int)$v['prix'])), 'Dans Qonto' => !empty($cat['qonto']) ? 'Oui' : ''])
            . '<p class="aide">Le prix doit rester le même que dans Qonto Facturation. Un changement vaut pour les prochaines livraisons ; celles déjà notées gardent leur prix.</p>'
            . '<details class="replie"><summary>Modifier le prix, l\'unité ou la TVA</summary>' . formulaire('pro', $f) . '</details>');
    }
    // Ce que le site affiche
    $prix = '';
    foreach ($vars as $code => $v) $prix .= '<li><span translate="no">' . h($v['libelle']) . '</span> · <b>' . h(euros($v['prix'])) . '</b>' . ($cat['mode'] !== 'taille' ? ' la pièce' : '') . '</li>';
    $tel_quel = fn(string $t): string => $t !== '' ? '<span translate="no" lang="fr">' . h($t) . '</span>' : '';   // le texte du site, tel que le lisent les clients
    $site = definition(['Présentation' => $tel_quel($cat['phrase'] ?? ''), 'Composition' => !empty($cat['compo']) ? $tel_quel(implode(' · ', $cat['compo'])) : '',
                        'Allergènes' => $tel_quel($cat['allergenes'] ?? ''), 'Conservation' => $tel_quel($cat['conservation'] ?? ''),
                        'À commander' => h(isset($cat['delai']) ? $cat['delai'] . ' jour(s) avant le retrait' : '') . (!empty($cat['minimum']) ? h(' · au moins ' . $cat['minimum'] . ' pièces') : '')])
          . '<ul class="puces">' . $prix . '</ul><p class="aide">Textes et prix du site : pour les changer, demandez une modification du site.</p>';
    if (!$pro) $c .= bloc('Sur le site', $site);
    // Allergènes : recette contre site (gamme pro : la recette seule, à donner au restaurant)
    $avecRecette = array_filter($rec, fn($r) => $r['lignes']);
    if ($avecRecette && $pro) {
        $cmp = allergenes_comparer($p, $rec);
        $c .= bloc('Allergènes', '<p>D\'après la recette : <b>' . h($cmp['deduits'] ? allergenes_affiches($cmp['deduits']) : 'aucun allergène') . '</b>.</p>'
            . '<p class="aide">À donner au restaurant avec la livraison : il doit pouvoir les indiquer à ses clients. C\'est Youngmi qui tranche un doute (R-39).</p>');
    } elseif ($avecRecette) {
        $cmp = allergenes_comparer($p, $rec);
        $t = '<p>D\'après la recette : <b>' . h($cmp['deduits'] ? allergenes_affiches($cmp['deduits']) : 'aucun allergène') . '</b>.</p>';
        if ($cmp['manquants']) $t .= '<p class="erreur">' . icone('alerte') . ' ' . fr_ko('La recette contient <b>' . h(allergenes_affiches($cmp['manquants'])) . '</b>, que le site n\'indique pas. À corriger sur le site avant de vendre ce gâteau, et à faire confirmer par Youngmi.',
            '레시피에는 <b>' . h(allergenes_affiches($cmp['manquants'])) . '</b>이(가) 들어 있는데 사이트에는 적혀 있지 않아요. 이 케이크를 팔기 전에 사이트를 고치고 Youngmi에게 확인을 받으세요.') . '</p>';
        if ($cmp['en_trop']) $t .= '<p class="aide">Le site indique aussi ' . h(allergenes_affiches($cmp['en_trop'])) . ' : une mention de précaution à garder, ou un ingrédient pas encore saisi dans la recette.</p>';
        if (!$cmp['manquants'] && !$cmp['en_trop']) $t .= '<p class="ok">La recette et le site disent la même chose.</p>';
        $t .= '<p class="aide">' . fr_ko('La comparaison lit les mots du site (« lait », « noisette »…) : elle aide, mais c\'est Youngmi qui tranche un doute (R-39). Les allergènes de chaque ingrédient se règlent dans <a href="' . h(u('ingredients')) . '">Ingrédients</a>.',
            '비교는 사이트에 적힌 단어(« lait », « noisette »…)를 읽어서 해요. 참고용이고, 애매하면 Youngmi가 정해요 (R-39). 재료별 알레르기 유발 성분은 <a href="' . h(u('ingredients')) . '">재료</a>에서 정해요.') . '</p>';
        $c .= bloc('Allergènes', $t, $cmp['manquants'] ? 'alerte' : '');
    }
    // Recettes par taille ou parfum
    $ings = ingredients_lister();
    $optIng = ['' => '— Ingrédient —'];
    foreach ($ings as $i) $optIng[(string)$i['id']] = $i['nom'] . ' (' . UNITES[$i['unite']] . ')';
    $optIng['nouveau'] = '+ Nouvel ingrédient…';
    $optVars = [];
    foreach ($vars as $code => $v) $optVars[$code] = $v['libelle'];
    $vOuvert = is_string($_GET['v'] ?? null) ? $_GET['v'] : '';
    $r = '<p class="aide">Pour ' . ($pro ? 'une unité vendue' : (($cat['mode'] ?? '') === 'taille' ? 'un gâteau entier de chaque taille' : 'une pièce de chaque parfum')) . ', en g, ml ou pièces.</p>';
    foreach ($rec as $code => $v) {
        $rows = '';
        foreach ($v['lignes'] as $l) {
            $rows .= '<tr><td translate="no">' . h($l['nom']) . ($l['note'] !== '' ? '<br><span class="petit">' . h($l['note']) . '</span>' : '') . '</td>'
                   . '<td class="nombre">' . h(nombre_fr((float)$l['quantite'])) . ' ' . h(unite_texte($l['unite'], (float)$l['quantite'])) . '</td>'
                   . '<td class="nombre">' . h((int)$l['prix_unite'] ? euros((int)$l['cout']) : 'prix ?') . '</td>'
                   . '<td class="actions-ligne"><details class="mini"><summary aria-label="Modifier ' . h($l['nom']) . '">Modifier</summary>'
                   . formulaire('ligne_modifier', '<input type="hidden" name="ligne" value="' . (int)$l['id'] . '">' . champ('quantite', 'Quantité', nombre_fr((float)$l['quantite']), ['inputmode' => 'decimal', 'id' => 'q' . $l['id']])
                        . champ('note', 'Note', $l['note'], ['maxlength' => '120', 'id' => 'n' . $l['id']]) . bouton('OK', 'secondaire'))
                   . formulaire('ligne_supprimer', '<input type="hidden" name="ligne" value="' . (int)$l['id'] . '">' . bouton('Retirer', 'discret'))
                   . '</details></td></tr>';
        }
        $sansPrix = count(array_filter($v['lignes'], fn($l) => !(int)$l['prix_unite']));
        $tot = $v['lignes'] ? '<tfoot><tr><td colspan="2">Coût matières' . ((int)$p['emballage'] ? ' et emballage (' . h(euros((int)$p['emballage'])) . ')' : '') . '</td><td class="nombre"><b>' . h(euros($v['cout'])) . '</b></td><td></td></tr></tfoot>' : '';
        $marge = '';
        if ($v['lignes'] && $v['prix']) {
            $coef = $v['cout'] ? $v['ht'] / $v['cout'] : 0;
            $marge = '<div class="tuiles petites">' . tuile('Prix TTC', euros($v['prix'])) . tuile('Prix HT', euros($v['ht']), 'TVA ' . nombre_fr((int)$p['tva'] / 10, 1) . ' %')
                   . tuile('Marge sur matières', euros((int)$v['marge']), pourcent((int)$v['marge'], $v['ht']) . ' du prix HT') . tuile('Coefficient', $coef ? '× ' . nombre_fr($coef, 1) : '—', 'prix HT ÷ coût') . '</div>'
                   . ($sansPrix ? '<p class="aide">' . $sansPrix . ' ingrédient(s) sans prix : le coût est sous-estimé.</p>' : '')
                   . '<p class="aide">Marge sur les matières et l\'emballage seulement : le travail, l\'énergie, les pertes et les charges ne sont pas comptés.</p>';
        }
        $ajout = formulaire('ligne_ajouter', '<input type="hidden" name="variante" value="' . h($code) . '">'
            . '<div class="ligne-champs">' . liste_choix('ingredient', 'Ingrédient', $optIng, '', ['id' => 'i-' . $code, 'data-nouveau' => 'nv-' . $code])
            . champ('quantite', 'Quantité', '', ['inputmode' => 'decimal', 'id' => 'qa-' . $code, 'placeholder' => '120', 'required' => true]) . '</div>'
            . '<div class="nouvel-ingredient" id="nv-' . h($code) . '" hidden><div class="ligne-champs">' . champ('nouveau_nom', 'Nom du nouvel ingrédient', '', ['id' => 'nn-' . $code])
            . liste_choix('nouveau_unite', 'Compté en', UNITES, 'g', ['id' => 'nu-' . $code]) . champ('nouveau_prix', 'Prix (€ le kg, le litre ou la pièce)', '', ['inputmode' => 'decimal', 'id' => 'np-' . $code]) . '</div></div>'
            . champ('note', 'Note (facultatif)', '', ['maxlength' => '120', 'id' => 'na-' . $code, 'placeholder' => 'pour la crème, pour le décor…']) . bouton('Ajouter à la recette', 'secondaire'), ['class' => 'form-ajout']);
        $vendus = (int)($ventes['variantes'][$code] ?? 0);
        $r .= '<details class="variante" id="v-' . h($code) . '"' . ($vOuvert === (string)$code || count($rec) === 1 ? ' open' : '') . '><summary><b translate="no">' . h($v['libelle']) . '</b><span class="petit">'
            . h(euros($v['prix'])) . ' · ' . ($v['lignes'] ? count($v['lignes']) . ' ingrédient(s), coût ' . euros($v['cout']) : 'recette vide') . ($vendus ? ' · ' . $vendus . ' vendu(s)' : '') . '</span></summary>'
            . ($rows !== '' ? '<table class="tableau recette"><thead><tr><th>Ingrédient</th><th class="nombre">Quantité</th><th class="nombre">Coût</th><th><span class="sr">Actions</span></th></tr></thead><tbody>' . $rows . '</tbody>' . $tot . '</table>' : '')
            . $marge . $ajout . '</details>';
    }
    if (count($vars) > 1) {
        $codes = array_keys($vars);
        $num = array_filter($codes, fn($x) => ctype_digit((string)$x));
        $aide = count($num) === count($codes) ? 'Pour passer d\'un diamètre à un autre, multipliez par le rapport des surfaces : de 16 à 20 cm, (20 ÷ 16)² = 1,56.' : 'Multiplie toutes les quantités.';
        $r .= '<details class="replie"><summary>Copier une recette</summary>'
            . formulaire('copier', '<div class="ligne-champs">' . liste_choix('de', 'Depuis', $optVars, (string)$codes[0]) . liste_choix('vers', 'Vers (remplace sa recette)', $optVars, (string)($codes[1] ?? $codes[0])) . '</div>'
                . champ('coefficient', 'Coefficient', '1', ['inputmode' => 'decimal'], $aide) . bouton('Copier', 'secondaire'), ['data-confirmer' => 'Remplacer la recette de destination ?']) . '</details>';
    }
    $c .= bloc('Recettes, coûts et marges', $r);
    // Fiche technique et validation
    $f = ($pro ? '' : '<div class="ligne-champs">' . liste_choix('statut', 'Sur la carte', array_diff_key(STATUTS_PRODUIT, ['pro' => 1]), $p['statut']) . liste_choix('tva', 'TVA', TVA, (string)$p['tva']) . '</div>')
       . champ('fiche_lien', 'Lien de la fiche technique (dossier 10 du Drive)', $p['fiche_lien'], ['type' => 'url', 'placeholder' => 'https://docs.google.com/…'])
       . '<div class="ligne-champs">' . champ('valide_le', 'Fiche validée par Youngmi le', (string)$p['valide_le'], ['type' => 'date']) . champ('valide_par', 'Validée par', $p['valide_par'] !== '' ? $p['valide_par'] : '', ['placeholder' => 'Youngmi Cho']) . '</div>'
       . champ('emballage', 'Coût de l\'emballage (€ par gâteau ou par pièce)', (int)$p['emballage'] ? number_format((int)$p['emballage'] / 100, 2, ',', '') : '', ['inputmode' => 'decimal', 'placeholder' => '1,20'])
       . zone('recette', 'Méthode (étapes, cuissons, montage)', $p['recette'], ['rows' => '6', 'maxlength' => '8000'])
       . zone('notes', 'Notes', $p['notes'], ['rows' => '3', 'maxlength' => '3000'], 'Fournisseurs, astuces, ce qui a changé…')
       . bouton('Enregistrer la fiche', 'secondaire');
    $c .= bloc('Fiche technique', ($p['fiche_lien'] !== '' ? '<p>' . lien_bouton($p['fiche_lien'], 'Ouvrir la fiche technique', 'discret', ['target' => '_blank', 'rel' => 'noopener']) . '</p>' : '') . formulaire('fiche', $f));
    // Ventes
    $vt = '';
    foreach ($vars as $code => $v) if (!empty($ventes['variantes'][$code])) $vt .= '<tr><td translate="no">' . h($v['libelle']) . '</td><td class="nombre">' . (int)$ventes['variantes'][$code] . '</td></tr>';
    $c .= bloc('Ventes des 12 derniers mois', $vt !== '' ? '<table class="tableau"><tbody>' . $vt . '</tbody><tfoot><tr><td>Total</td><td class="nombre">' . (int)$ventes['q'] . ' · ' . h(euros((int)$ventes['ca'])) . '</td></tr></tfoot></table>'
        . '<p>' . lien_bouton(u('commandes', ['vue' => 'toutes', 'r' => $p['nom']]), 'Voir les commandes', 'discret') . '</p>' : '<p class="vide">Aucune commande remise.</p>');
    $c .= bloc('Historique', fil_historique(historique(null, null, $id)));
    mise_en_page($p['nom'], $c, 'produits');
}

/* ---------- Ingrédients ---------- */
function ingredient_depuis_post(): array {
    return ['nom' => une_ligne(texte('nom', 80)), 'unite' => choix('unite', UNITES, 'g'), 'prix' => centimes(texte('prix', 12)) ?? 0,
            'allergenes' => array_values(array_filter(is_array($_POST['allergenes'] ?? null) ? $_POST['allergenes'] : [], 'is_string')),
            'fournisseur' => une_ligne(texte('fournisseur', 100)), 'achat' => une_ligne(texte('achat', 200)), 'notes' => texte('notes', 1000), 'actif' => empty($_POST['retire'])];
}
function formulaire_ingredient(?array $i): string {
    $all = $i ? array_filter(explode(',', $i['allergenes'])) : [];
    $cases = '';
    foreach (ALLERGENES as $k => $l) $cases .= '<label class="case"><input type="checkbox" name="allergenes[]" value="' . h($k) . '"' . (in_array($k, $all, true) ? ' checked' : '') . '><span>' . h(allergene_libelle($k)) . '</span></label>';
    return champ('nom', 'Nom', $i['nom'] ?? '', ['required' => true, 'maxlength' => '80', 'placeholder' => 'Chocolat noir 70 %'])
         . '<div class="ligne-champs">' . liste_choix('unite', 'Compté en', UNITES, $i['unite'] ?? 'g', [])
         . champ('prix', 'Prix d\'achat TTC (€ le kg, le litre ou la pièce)', $i && (int)$i['prix'] ? number_format((int)$i['prix'] / 100, 2, ',', '') : '', ['inputmode' => 'decimal', 'placeholder' => '12,50']) . '</div>'
         . '<fieldset class="cases"><legend>Allergènes que contient cet ingrédient</legend><div class="grille-cases">' . $cases . '</div></fieldset>'
         . '<div class="ligne-champs">' . champ('fournisseur', 'Fournisseur', $i['fournisseur'] ?? '', ['maxlength' => '100']) . champ('achat', 'Où et comment l\'acheter', $i['achat'] ?? '', ['maxlength' => '200']) . '</div>'
         . zone('notes', 'Notes', $i['notes'] ?? '', ['rows' => '2', 'maxlength' => '1000'], 'Marque, référence, étiquette (allergènes)…');
}
function page_ingredients(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        csrf_verifier();
        $d = ingredient_depuis_post();
        if ($d['nom'] === '') flash('erreur', 'Donnez un nom à l\'ingrédient.');
        elseif (val("SELECT 1 FROM ingredients WHERE nom = ? COLLATE NOCASE", [$d['nom']])) flash('erreur', '« ' . $d['nom'] . ' » existe déjà.');
        else { ingredient_enregistrer(null, $d); flash('ok', 'Ingrédient ajouté.'); }
        rediriger('ingredients');
    }
    $tous = !empty($_GET['tous']);
    $is = ingredients_lister($tous);
    $usage = [];
    foreach (lignes("SELECT ingredient_id, COUNT(DISTINCT produit_id) AS n FROM recettes GROUP BY ingredient_id") as $x) $usage[(int)$x['ingredient_id']] = (int)$x['n'];
    $c = titre_page('Ingrédients', count($is) . (count($is) > 1 ? ' ingrédients' : ' ingrédient'), '', u('produits'));
    $rows = '';
    foreach ($is as $i) {
        $rows .= '<tr' . ($i['actif'] ? '' : ' class="retire"') . '><td translate="no"><a href="' . h(u('ingredient', ['id' => $i['id']])) . '">' . h($i['nom']) . '</a>' . ($i['fournisseur'] !== '' ? '<br><span class="petit">' . h($i['fournisseur']) . '</span>' : '') . '</td>'
               . '<td class="nombre">' . ((int)$i['prix'] ? h(euros((int)$i['prix'])) . '<br><span class="petit">' . h(prix_unite_texte($i['unite'])) . '</span>' : '<span class="retard">prix ?</span>') . '</td>'
               . '<td>' . h(allergenes_affiches(array_filter(explode(',', $i['allergenes'])))) . '</td><td class="nombre">' . ($usage[(int)$i['id']] ?? 0) . '</td></tr>';
    }
    $c .= bloc('', $rows !== '' ? '<div class="defile"><table class="tableau"><thead><tr><th>Ingrédient</th><th class="nombre">Prix</th><th>Allergènes</th><th class="nombre">Produits</th></tr></thead><tbody>' . $rows . '</tbody></table></div>'
        . '<p>' . ($tous ? '<a href="' . h(u('ingredients')) . '">Cacher les ingrédients retirés</a>' : '<a href="' . h(u('ingredients', ['tous' => 1])) . '">Voir aussi les ingrédients retirés</a>') . '</p>'
        : '<p class="vide">Aucun ingrédient : ajoutez le premier ci-dessous, ou directement depuis la recette d\'un produit.</p>');
    $c .= bloc('Nouvel ingrédient', formulaire('ajouter', formulaire_ingredient(null) . bouton('Ajouter', 'principal')));
    mise_en_page('Ingrédients', $c, 'ingredients');
}
function page_ingredient(): void {
    $id = entier('id', 1);
    $i = $id ? ingredient($id) : null;
    if (!$i) rediriger('ingredients');
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        csrf_verifier();
        $d = ingredient_depuis_post();
        if ($d['nom'] === '') flash('erreur', 'Le nom ne peut pas être vide.');
        else {
            ingredient_enregistrer($id, $d);
            $chg = [];
            if ((int)$i['prix'] !== $d['prix']) $chg[] = 'prix ' . euros((int)$i['prix']) . ' → ' . euros($d['prix']);
            $a = implode(',', array_values(array_intersect($d['allergenes'], array_keys(ALLERGENES))));
            if ($a !== $i['allergenes']) $chg[] = 'allergènes : ' . (allergenes_texte(array_filter(explode(',', $a))) ?: 'aucun');
            foreach (lignes("SELECT DISTINCT produit_id FROM recettes WHERE ingredient_id = ?", [$id]) as $r) if ($chg) evenement('recette', $d['nom'] . ' : ' . implode(', ', $chg), ['produit' => $r['produit_id']]);
            flash('ok', 'Ingrédient enregistré.' . ($chg ? ' Les coûts et les allergènes des recettes suivent.' : ''));
        }
        rediriger('ingredient', ['id' => $id]);
    }
    $c = titre_page('<span translate="no">' . h($i['nom']) . '</span>', h((int)$i['prix'] ? euros((int)$i['prix']) . ' ' . prix_unite_texte($i['unite']) : 'Prix à indiquer'), '', u('ingredients'));
    $us = lignes("SELECT r.produit_id, r.variante, r.quantite, p.nom, p.catalogue FROM recettes r JOIN produits p ON p.id = r.produit_id WHERE r.ingredient_id = ? ORDER BY p.nom", [$id]);
    $l = '';
    foreach ($us as $x) {
        $pv = produit_variantes(['cat' => json_decode($x['catalogue'], true) ?: []]);
        $l .= '<li><span translate="no"><a href="' . h(u('produit', ['id' => $x['produit_id'], 'v' => $x['variante']])) . '">' . h($x['nom']) . '</a>, ' . h($pv[$x['variante']]['libelle'] ?? $x['variante']) . '</span> : <span>' . h(nombre_fr((float)$x['quantite']) . ' ' . unite_texte($i['unite'], (float)$x['quantite'])) . '</span></li>';
    }
    $c .= bloc('Utilisé dans', $l !== '' ? '<ul class="puces">' . $l . '</ul>' : '<p class="vide">Dans aucune recette.</p>');
    $c .= bloc('Modifier', formulaire('enregistrer', formulaire_ingredient($i) . case_a_cocher('retire', 'Retiré (plus acheté)', !$i['actif']) . bouton('Enregistrer', 'principal')));
    mise_en_page($i['nom'], $c, 'ingredients');
}
