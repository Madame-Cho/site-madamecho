<?php
/*
 * Madame Cho : espace de gestion. Étiquettes des boîtes et « Problème sur ce lot » (écran Obligations, lot 3 ; spécification 004, US3 et US4).
 * Une étiquette ne contient que ce que Youngmi a validé : fiche produit validée (R-39), allergènes du site ou de la recette,
 * texte de conservation et liste d'ingrédients relus par elle (Hygiène › Appareils › Étiquettes), date limite et numéro du lot,
 * adresse de l'exploitant saisie dans les réglages (jamais dans le dépôt public). Sinon la gestion refuse et dit quoi compléter.
 * Un problème sur un lot montre tout de suite les réceptions utilisées, les restaurants livrés et les commandes peut-être
 * concernées, les étapes du retrait à cocher, et un brouillon pour la DDPP que Youngmi envoie elle-même (R-01).
 * Jamais servi directement.
 */
declare(strict_types=1);
namespace MadameCho\Gestion;

const ETIQUETTE_FORMATS = ['a4' => 'Planche A4 (24 étiquettes de 63,5 x 33,9 mm)', 'rouleau' => 'Rouleau (étiquettes de 55 x 30 mm)'];
const PROBLEME_ETAPES = [
    'retirer' => ['Retirer le lot', 'Mettre de côté tout ce qui reste du lot (cuisine, frigo, vitrine) et ne plus le vendre ni le livrer.'],
    'clients' => ['Prévenir les clients', 'Appeler chaque restaurant livré et chaque client qui a peut-être reçu le lot : ne pas servir, ne pas manger, rapporter ou jeter.'],
    'ddpp' => ['Informer la DDPP', 'Envoyer à la DDPP des Hauts-de-Seine le brouillon ci-dessous, depuis votre messagerie (la gestion ne l\'envoie jamais).'],
    'rappelconso' => ['Déclarer le rappel sur RappelConso', 'Obligatoire dès qu\'un rappel auprès des consommateurs est décidé : le lot a pu être reçu par des particuliers, ou servi par un restaurant à ses clients. Déclarer sur rappel.conso.gouv.fr, après avoir informé la DDPP. Demandez à chaque restaurant si des gâteaux ont été servis ou s\'il les a encore. Sans objet seulement si aucun gâteau du lot n\'a pu arriver chez un consommateur (tout est encore chez vous ou chez le restaurant, ou jeté) : dites où sont les gâteaux, et ce qu\'a dit la DDPP.'],
];
const PROBLEME_TOUJOURS = ['retirer'];   // une étape qui ne peut jamais être « sans objet » (audit n° 8, constat 2)
const ETIQUETTE_MENTIONS = ['dlc' => 'À consommer jusqu\'au', 'ddm' => 'À consommer de préférence avant le'];
const PROBLEME_TITRE = 'Problème sur un lot : retrait et information';
const PROBLEME_OU = 'Gestion, Plus › Hygiène › la fiche du lot › « Problème sur ce lot » : cochez chaque étape une fois faite. Rangez les preuves (mail à la DDPP, notes d\'appel) dans le Drive.';

/* ---------- Fiche étiquette d'un gâteau ---------- */

function etiquette_fiche(string $pid): array {
    $r = ligne("SELECT * FROM hygiene_durees WHERE produit_id = ?", [$pid]) ?? [];
    return ['conservation' => (string)($r['conservation'] ?? ''), 'ingredients' => (string)($r['ingredients'] ?? ''), 'etiquette_le' => (string)($r['etiquette_le'] ?? ''), 'etiquette_par' => (string)($r['etiquette_par'] ?? ''),
            'date_mention' => (string)($r['date_mention'] ?? ''), 'poids_net' => (string)($r['poids_net'] ?? '')];
}
function etiquette_fiche_enregistrer(string $pid, string $conservation, string $ingredients, string $mention = 'dlc', string $poids = ''): void {
    $now = maintenant();
    $mention = isset(ETIQUETTE_MENTIONS[$mention]) ? $mention : 'dlc';
    q("INSERT INTO hygiene_durees (produit_id, jours, maj_le, maj_par, conservation, ingredients, etiquette_le, etiquette_par, date_mention, poids_net) VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?, ?)
       ON CONFLICT(produit_id) DO UPDATE SET conservation = excluded.conservation, ingredients = excluded.ingredients, etiquette_le = excluded.etiquette_le, etiquette_par = excluded.etiquette_par,
       date_mention = excluded.date_mention, poids_net = excluded.poids_net",
      [$pid, $now, auteur(), $conservation, $ingredients, $now, auteur(), $mention, mb_substr(une_ligne($poids), 0, 40)]);
    evenement('hygiene', 'Texte d\'étiquette relu : ' . $pid, ['produit' => $pid]);
}
/** Ce que la recette propose comme liste d'ingrédients : du plus lourd au plus léger (grammes et millilitres ; les pièces à la fin, à placer par Youngmi). */
function etiquette_ingredients_proposes(array $p): string {
    $best = [];
    foreach (recette($p) as $v) if (count($v['lignes']) > count($best)) $best = $v['lignes'];
    if (!$best) return '';
    $poids = []; $pieces = [];
    foreach ($best as $l) {
        $nom = trim((string)$l['nom']);
        if ($l['unite'] === 'piece') $pieces[$nom] = true;
        else $poids[$nom] = ($poids[$nom] ?? 0) + (float)$l['quantite'];
    }
    arsort($poids);
    return implode(', ', array_merge(array_keys($poids), array_keys($pieces)));
}
/** Les allergènes de l'étiquette : le texte du site (validé), sinon ceux de la recette. Null : inconnus. */
function etiquette_allergenes(array $p): ?string {
    $t = trim((string)($p['cat']['allergenes'] ?? ''));
    if ($t !== '') return rtrim($t, '.');
    $rec = array_filter(recette($p), fn($v) => $v['lignes']);
    if (!$rec) return null;
    $c = allergenes_comparer($p, $rec)['deduits'];
    return $c ? implode(', ', array_map(fn($a) => ALLERGENES[$a] ?? $a, $c)) : 'Aucun des 14 allergènes majeurs';
}
/** Le contenu d'une étiquette pour une fournée, ou la liste de ce qui manque (rien n'est inventé). */
function etiquette_contenu(array $f): array {
    $manque = [];
    $p = $f['produit_id'] !== '' ? produit((string)$f['produit_id']) : null;
    if (!$p) return ['manque' => ['Ce gâteau n\'a pas de fiche produit : une étiquette ne se fait que pour un gâteau de la carte ou de la gamme pro.']];
    if (empty($p['valide_le'])) $manque[] = 'La fiche du gâteau n\'est pas validée par Youngmi (Produits › le gâteau › « Fiche validée le »).';
    $all = etiquette_allergenes($p);
    if ($all === null) $manque[] = 'Les allergènes ne sont pas connus : ni texte sur le site, ni recette dans la gestion.';
    $fiche = etiquette_fiche((string)$p['id']);
    $cons = $fiche['conservation'] !== '' ? $fiche['conservation'] : trim((string)($p['cat']['conservation'] ?? ''));
    if ($fiche['etiquette_le'] === '') $manque[] = 'Le texte de l\'étiquette n\'est pas encore relu (Hygiène › Appareils › Étiquettes des gâteaux).';
    elseif ($cons === '') $manque[] = 'Les conditions de conservation manquent (Hygiène › Appareils › Étiquettes des gâteaux).';
    $adr = trim((string)parametre('etiquette_exploitant', ''));
    if ($adr === '') $manque[] = 'Le nom et l\'adresse de l\'exploitant manquent (Hygiène › Appareils › Réglages).';
    if ($f['dlc'] === '' || $f['numero_lot'] === '') $manque[] = 'La fournée n\'a pas de date limite ou de numéro de lot.';
    // Les ingrédients sont toujours attendus : une étiquette sans eux ne s'imprime pas (audit n° 8, constat 3 ; plus de réglage caché, contre-expertise n° 9, constat 5)
    if ($fiche['etiquette_le'] !== '' && $fiche['ingredients'] === '') $manque[] = 'La liste d\'ingrédients manque : saisissez la recette du gâteau, puis relisez le texte de l\'étiquette (Hygiène › Appareils › Étiquettes des gâteaux).';
    // Gluten annoncé mais aucune céréale en gras dans les ingrédients (« Farine T45 ») : à préciser (INCO, revue du 08/11)
    if ($fiche['ingredients'] !== '' && $all !== null && stripos($all, 'gluten') !== false
        && !preg_match('/\b\p{L}*(blé|froment|seigle|orge|avoine|épeautre|kamut|gluten)\p{L}*/iu', $fiche['ingredients']))
        $manque[] = 'Le gâteau contient du gluten : dans les ingrédients, écrivez la céréale (par exemple « farine de blé ») pour qu\'elle s\'imprime en gras.';
    if ($fiche['etiquette_le'] !== '' && !isset(ETIQUETTE_MENTIONS[$fiche['date_mention']])) $manque[] = 'Choisissez la mention de la date (date limite de consommation ou de préférence) dans Hygiène › Appareils › Étiquettes des gâteaux.';
    if (probleme_ouvert_du_lot((string)$f['numero_lot'])) $manque[] = 'Un problème est en cours sur ce lot : il ne se vend plus et ne se livre plus, ses étiquettes ne s\'impriment plus jusqu\'à la clôture du problème.';
    if ($manque) return ['manque' => $manque];
    return ['manque' => [], 'nom' => (string)$p['nom'], 'allergenes' => (string)$all, 'conservation' => rtrim($cons, '.'), 'dlc' => (string)$f['dlc'], 'lot' => (string)$f['numero_lot'],
            'exploitant' => $adr, 'ingredients' => $fiche['ingredients'],
            'mention' => ETIQUETTE_MENTIONS[$fiche['date_mention']], 'poids_net' => $fiche['poids_net']];
}
/** Les ingrédients avec les allergènes en gras (mots du site, comme la comparaison des fiches). */
function etiquette_ingredients_html(string $t): string {
    $h = h($t);
    $mots = [];
    // Les mots du site, plus ceux d'une liste d'ingrédients (beurre et crème pour le lait, autres céréales à gluten)
    foreach (array_merge(...array_values(ALLERGENES_MOTS)) + [] as $m) $mots[] = preg_quote(h($m), '/');
    foreach (['beurre', 'crème', 'fromage', 'mascarpone', 'lactose', 'froment', 'seigle', 'orge', 'avoine', 'épeautre', 'kamut'] as $m) $mots[] = preg_quote(h($m), '/');
    return preg_replace('/\b(\p{L}*(?:' . implode('|', $mots) . ')\p{L}*)/iu', '<b>$1</b>', $h) ?? $h;
}

/* ---------- Problème sur un lot ---------- */

function probleme(int $id): ?array { return ligne("SELECT * FROM hygiene_problemes WHERE id = ?", [$id]); }
/** Le problème encore ouvert sur ce lot, s'il y en a un : le lot ne se livre plus et ses étiquettes ne s'impriment plus (contre-expertise n° 9, constat 1). */
function probleme_ouvert_du_lot(string $numero): ?array { return $numero === '' ? null : ligne("SELECT * FROM hygiene_problemes WHERE numero_lot = ? AND clos_le = '' ORDER BY id DESC LIMIT 1", [$numero]); }
function probleme_du_lot(string $numero): ?array { return ligne("SELECT * FROM hygiene_problemes WHERE numero_lot = ? ORDER BY clos_le = '' DESC, id DESC LIMIT 1", [$numero]); }
/** Ouvre un problème sur un lot (un seul ouvert par lot) et la démarche importante qui le suit dans Obligations. */
function probleme_ouvrir(int $fid, string $description): array {
    $f = hygiene_releve($fid);
    if (!$f || $f['genre'] !== 'fournee') return [0, 'Lot introuvable.'];
    if (une_ligne($description) === '') return [0, 'Décrivez le problème en une phrase (qui l\'a signalé, quoi, quand).'];
    if (($e = probleme_du_lot((string)$f['numero_lot'])) && $e['clos_le'] === '') return [(int)$e['id'], ''];
    q("INSERT INTO hygiene_problemes (fournee_id, numero_lot, ouvert_le, ouvert_par, description) VALUES (?, ?, ?, ?, ?)", [$fid, $f['numero_lot'], maintenant(), auteur(), mb_substr(trim($description), 0, 1500)]);
    $id = (int)db()->lastInsertId();
    $did = demarche_creer(['titre' => PROBLEME_TITRE, 'explication' => 'Lot ' . $f['numero_lot'] . '.', 'ou' => PROBLEME_OU, 'pour_qui' => 'deux', 'echeance' => aujourdhui(),
                           'a_confirmer' => 0, 'preavis' => OBLIGATIONS_JOURS, 'priorite' => 1, 'importante' => 1, 'origine' => 'Hygiène'], null,
                          ['declencheur' => 'probleme:' . $id, 'ref_genre' => 'probleme', 'ref_id' => (string)$id]);
    q("UPDATE hygiene_problemes SET demarche_id = ? WHERE id = ?", [$did ?: null, $id]);
    evenement('hygiene', 'Problème ouvert sur le lot ' . $f['numero_lot']);
    return [$id, ''];
}
function probleme_etapes(array $pb): array { $e = json_decode((string)$pb['etapes'], true); return is_array($e) ? $e : []; }
/** Coche (ou décoche) une étape, faite ou « sans objet » avec sa raison : l'heure et la personne restent (audit n° 8, constat 2). */
function probleme_cocher(int $id, string $etape, bool $fait, string $note = '', bool $sansObjet = false): string {
    $pb = probleme($id);
    if (!$pb || !isset(PROBLEME_ETAPES[$etape])) return 'Étape inconnue.';
    if ($pb['clos_le'] !== '') return 'Ce problème est clos.';
    if ($fait && $sansObjet && in_array($etape, PROBLEME_TOUJOURS, true)) return 'Retirer le lot est toujours à faire.';
    if ($fait && $sansObjet && une_ligne($note) === '') return 'Dites pourquoi cette étape est sans objet (et qui l\'a dit : la DDPP, par exemple).';
    $e = probleme_etapes($pb);
    if ($fait) $e[$etape] = ['le' => maintenant(), 'par' => auteur(), 'note' => mb_substr(une_ligne($note), 0, 300)] + ($sansObjet ? ['sans_objet' => 1] : []);
    else unset($e[$etape]);
    q("UPDATE hygiene_problemes SET etapes = ? WHERE id = ?", [json_encode($e, JSON_UNESCAPED_UNICODE), $id]);
    return '';
}
/**
 * Clôt le problème : chaque étape faite ou « sans objet » avec sa raison, et un bilan. La démarche d'Obligations se clôt avec lui,
 * avec le lien de la preuve (audit n° 8, constats 2 et 8).
 */
function probleme_clore(int $id, string $bilan, string $lien = ''): string {
    $pb = probleme($id);
    if (!$pb || $pb['clos_le'] !== '') return 'Ce problème est déjà clos.';
    $reste = array_diff(array_keys(PROBLEME_ETAPES), array_keys(probleme_etapes($pb)));
    if ($reste) return 'Il reste des étapes : marquez chacune « C\'est fait » ou « Sans objet » avec sa raison.';
    if (une_ligne($bilan) === '') return 'Écrivez le bilan : ce qui s\'est passé, ce qui a été fait, ce que la DDPP a répondu.';
    $d = $pb['demarche_id'] !== null ? demarche((int)$pb['demarche_id']) : null;
    if ($lien !== '' && !lien_drive_valide($lien)) return 'Le lien doit être une adresse du Drive (https://drive.google.com/… ou https://docs.google.com/…).';
    if ($d && in_array($d['etat'], ['a_faire', 'attente'], true) && $lien === '' && $d['preuve_lien'] === '')
        return 'Collez le lien de la preuve rangée dans le Drive (mail à la DDPP, notes d\'appel) : la démarche d\'Obligations se clôt avec le problème.';
    $pdo = db();
    $pdo->beginTransaction();
    try {
        q("UPDATE hygiene_problemes SET clos_le = ?, clos_par = ?, bilan = ? WHERE id = ?", [maintenant(), auteur(), mb_substr(trim($bilan), 0, 1500), $id]);
        if ($d && in_array($d['etat'], ['a_faire', 'attente'], true)) {
            $e = demarche_changer_etat((int)$d['id'], 'fait', $lien, 'Problème clos dans Hygiène.');
            if ($e !== '') throw new \RuntimeException($e);
        }
        $pdo->commit();
    } catch (\RuntimeException $x) { $pdo->rollBack(); return $x->getMessage(); }
    catch (\Throwable $x) { $pdo->rollBack(); throw $x; }
    evenement('hygiene', 'Problème clos sur le lot ' . $pb['numero_lot']);
    return '';
}
/** Vrai si la démarche suit un problème encore ouvert : elle ne se clôt qu'avec lui (audit n° 8, constat 8). */
function probleme_ouvert_de_demarche(array $d): bool {
    if ($d['ref_genre'] !== 'probleme') return false;
    $pb = probleme((int)$d['ref_id']);
    return $pb !== null && $pb['clos_le'] === '';
}
/** Tout ce que touche un lot : réceptions, livraisons (restaurants), commandes de particuliers peut-être concernées. */
function probleme_perimetre(array $f): array {
    $liv = array_values(array_filter(hygiene_liens_vers((int)$f['id']), fn($x) => $x['genre'] === 'livraison'));
    $kLiv = array_filter(array_map(fn($x) => $x['commande_id'] !== null ? (int)$x['commande_id'] : 0, $liv));
    $part = [];
    if ($f['produit_id'] !== '')
        foreach (lignes("SELECT k.* FROM commandes k WHERE k.statut IN ('confirmee', 'remise') AND k.retrait_date BETWEEN ? AND ? AND k.lignes LIKE ? ORDER BY k.retrait_date",
                        [$f['jour'], $f['dlc'] !== '' ? $f['dlc'] : $f['jour'], '%"v":"' . str_replace(['%', '_'], ['\\%', '\\_'], (string)$f['produit_id']) . '|%']) as $k)
            if (!in_array((int)$k['id'], $kLiv, true)) $part[] = $k;
    // Les commandes sur mesure de la période (sans ligne de la carte) : peut-être ce lot, à vérifier (audit n° 8, constat 1)
    $vus = array_map(fn($k) => (int)$k['id'], $part);
    foreach (lignes("SELECT k.* FROM commandes k WHERE k.statut IN ('confirmee', 'remise') AND k.retrait_date BETWEEN ? AND ? AND (k.lignes IN ('', '[]') OR k.perso <> '' OR k.besoin <> '') ORDER BY k.retrait_date",
                    [$f['jour'], $f['dlc'] !== '' ? $f['dlc'] : $f['jour']]) as $k)
        if (!in_array((int)$k['id'], $kLiv, true) && !in_array((int)$k['id'], $vus, true)) $part[] = $k;
    // Les livraisons de la période dont les lots n'ont pas été notés : peut-être ce lot
    $inconnues = lignes("SELECT * FROM hygiene_releves WHERE genre = 'livraison' AND remplace_par IS NULL AND trace = 'lot_inconnu' AND jour BETWEEN ? AND ? ORDER BY jour, le",
                        [$f['jour'], $f['dlc'] !== '' ? $f['dlc'] : $f['jour']]);
    return ['receptions' => hygiene_liens_de((int)$f['id']), 'livraisons' => $liv, 'commandes' => $part, 'livraisons_inconnues' => $inconnues, 'trace' => (string)($f['trace'] ?? '')];
}
/** Le brouillon pour la DDPP : faits seulement, aucun nom de particulier (un nombre), envoyé par Youngmi elle-même. */
function probleme_brouillon(array $pb, array $f): string {
    $per = probleme_perimetre($f);
    $nomR = fn($x) => ($c = client((int)$x['client_id'])) ? (trim((string)$c['societe']) !== '' ? $c['societe'] : client_nom($c)) : '';
    $restos = array_values(array_unique(array_filter(array_map($nomR, $per['livraisons']))));
    $doute = array_values(array_unique(array_filter(array_map($nomR, $per['livraisons_inconnues']))));
    // Jamais « aucun » quand on ne sait pas : on dit ce que les relevés montrent, et ce qui reste à vérifier (audit n° 8, constat 1)
    $txtR = ($restos ? implode(', ', $restos) : 'aucun restaurant relié à ce lot dans nos relevés') . ($doute ? ' ; à vérifier, livraisons sans lot noté pendant la période : ' . implode(', ', $doute) : '');
    $txtM = $per['receptions'] ? implode(', ', array_map(fn($r) => trim($r['produit'] . ($r['lot'] !== '' ? ' (lot ' . $r['lot'] . ')' : '') . ($r['fournisseur'] !== '' ? ', ' . $r['fournisseur'] : '')) ?: 'étiquette en photo', $per['receptions']))
          : ($per['trace'] === 'aucune_reception' ? 'aucune réception reliée à la fournée (note : ' . une_ligne((string)$f['note']) . ')' : 'non relevées');
    $e = probleme_etapes($pb);
    $adr = trim((string)parametre('etiquette_exploitant', ''));
    $t = "Objet : Madame Cho, problème sur le lot " . $f['numero_lot'] . "\n\nMadame, Monsieur,\n\n"
       . "Je vous informe d'un problème sur un lot de pâtisseries fabriqué par Madame Cho" . ($adr !== '' ? " (" . $adr . ")" : '') . ".\n\n"
       . "- Produit : " . $f['produit'] . "\n- Numéro de lot : " . $f['numero_lot'] . "\n- Fabriqué le : " . date_fr($f['jour'], 'longue')
       . "\n- Date limite : " . ($f['dlc'] !== '' ? date_fr($f['dlc'], 'longue') : 'non indiquée') . ($f['quantite'] !== '' ? "\n- Quantité : " . $f['quantite'] : '')
       . "\n- Problème signalé le " . date_fr(substr($pb['ouvert_le'], 0, 10), 'longue') . " : " . une_ligne((string)$pb['description'])
       . "\n- Restaurants livrés : " . $txtR . "\n- Commandes peut-être concernées (à vérifier une par une) : " . count($per['commandes'])
       . "\n- Matières premières utilisées : " . $txtM
       . "\n\nMesures déjà prises :\n";
    $faites = 0;
    foreach (PROBLEME_ETAPES as $k => [$titre]) if (isset($e[$k]) && empty($e[$k]['sans_objet'])) { $faites++; $t .= '- ' . $titre . ' (' . date_fr(substr($e[$k]['le'], 0, 10), 'courte') . ' à ' . substr($e[$k]['le'], 11, 5) . ')' . ($e[$k]['note'] !== '' ? ' : ' . $e[$k]['note'] : '') . "\n"; }
    if (!$faites) $t .= "- (à compléter)\n";
    return $t . "\nJe reste à votre disposition pour toute information et pour les suites à donner.\n\nYoungmi Cho\nMadame Cho\n";
}
