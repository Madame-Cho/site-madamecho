<?php
/*
 * Madame Cho : espace de gestion. Écran Obligations, lot 4 (spec 004, US5 et US6) :
 * - Volumes : ce qui part chaque semaine vers les restaurants, pour la dérogation à l'agrément sanitaire (Cerfa 13982).
 *   Les kilos viennent du poids d'une pièce confirmé par Youngmi (proposé depuis la recette, jamais deviné) ;
 *   les plafonds sont ceux que la DDPP confirme, tapés dans les réglages (préremplis « à confirmer », choix d'Olivier).
 * - Documents : les preuves rangées dans le Drive, en un toucher le jour d'un contrôle ; la gestion ne garde aucune copie.
 */
declare(strict_types=1);
namespace MadameCho\Gestion;

/** Plafonds préremplis, à confirmer par la DDPP (arrêté du 8 juin 2006, annexe IV ; à vérifier, jamais tenus pour sûrs). */
const VOLUMES_DEFAUTS = ['volumes_kg' => '250', 'volumes_kg_reduit' => '100', 'volumes_part' => '30', 'volumes_km' => '80', 'volumes_alerte' => '80'];
const VOLUMES_TITRE = 'Volumes de la dérogation : plafond proche ou dépassé';
const VOLUMES_OU = 'Gestion : Plus › Obligations › Volumes. En cas de dépassement : la DDPP des Hauts-de-Seine (mise à jour de la dérogation, Cerfa 13982, ou demande d\'agrément), avant la livraison suivante.';
const VOLUMES_SEMAINES = 8;
const VOLUMES_KM_TITRE = 'Distance de livraison à vérifier avant la prochaine commande';
const VOLUMES_KM_OU = 'Gestion : Plus › Obligations › Volumes › Restaurants livrés : tapez la distance depuis la cuisine. Plus loin que la distance de la dérogation : la DDPP des Hauts-de-Seine, avant de livrer.';
const VOLUMES_KM_JOURS = 14;   // une commande confirmée dans les 14 jours rend la distance urgente (audit n° 8, constat 10)

/** Les pièces qu'on s'attend à montrer lors d'un contrôle ; une pièce sans lien est « à ajouter ». */
const DOCUMENTS_ATTENDUS = [
    'ddpp_13984' => 'Récépissé de la déclaration d\'activité (Cerfa 13984)',
    'ddpp_13982' => 'Récépissé de la dérogation à l\'agrément (Cerfa 13982)',
    'cap_patisserie' => 'CAP Pâtisserie de Youngmi',
    'cap_boulangerie' => 'CAP Boulangerie de Youngmi',
    'assurance' => 'Attestation d\'assurance responsabilité civile',
    'extincteur' => 'Rapport de vérification de l\'extincteur',
    'electricite' => 'Rapport de vérification de l\'installation électrique',
    'pms' => 'Plan de maîtrise sanitaire',
    'rgpd' => 'Registre des traitements (RGPD)',
];
const DOCUMENTS_TITRE = 'Un document de preuve arrive à échéance';
const DOCUMENTS_OU = 'Gestion : Plus › Obligations › Documents. Rangez le nouveau document dans le Drive, puis collez son lien et sa date de validité.';
const DOCUMENTS_PREAVIS = 30;

function volumes_pret(): bool { return hygiene_pret() && (bool)val("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'documents'"); }

/** Une fois : les plafonds préremplis (à confirmer) et la liste des pièces attendues. */
function volumes_preparer(): void {
    if (!volumes_pret() || (string)parametre('volumes_prepare', '') !== '') return;
    if ((string)parametre('volumes_preremplir', '1') === '1') foreach (VOLUMES_DEFAUTS as $k => $v) if (parametre($k) === null) parametre_ecrire($k, $v);
    if ((string)parametre('documents_preremplir', '1') === '1' && !val("SELECT COUNT(*) FROM documents")) {
        $now = maintenant();
        foreach (DOCUMENTS_ATTENDUS as $code => $t) q("INSERT INTO documents (attendu, titre, cree_le, cree_par, maj_le, maj_par) VALUES (?, ?, ?, 'gestion', ?, 'gestion')", [$code, $t, $now, $now]);
    }
    parametre_ecrire('volumes_prepare', maintenant());
}

/* ---------- Réglages ---------- */
function volumes_reglage(string $cle): ?float {
    $v = parametre($cle);
    return $v === null || $v === '' || !is_numeric($v) ? null : (float)$v;
}
function volumes_confirme(): string { return (string)parametre('volumes_confirme', ''); }
/**
 * Le plafond qui s'applique cette semaine, d'après la part des restaurants EN KILOS : le plein si elle reste sous la limite, le réduit sinon.
 * Part en kilos inconnue (gâteaux sans poids) : le réduit, par prudence ; jamais choisi d'après les montants (audit n° 8, constat 4). Null si non saisi.
 */
function volumes_plafond(?float $partKg): ?float {
    $kg = volumes_reglage('volumes_kg'); $red = volumes_reglage('volumes_kg_reduit'); $lim = volumes_reglage('volumes_part');
    if ($red !== null && $lim !== null && ($partKg === null || $partKg > $lim)) return $red;
    return $kg;
}
function volumes_reglages_enregistrer(array $src): string {
    $v = [];
    foreach (array_keys(VOLUMES_DEFAUTS) as $k) {
        $s = str_replace(',', '.', trim((string)($src[$k] ?? '')));
        if ($s === '') { $v[$k] = ''; continue; }
        if (!is_numeric($s) || (float)$s < 0 || (float)$s > 100000) return 'Un nombre positif, s\'il vous plaît (par exemple 250).';
        $v[$k] = rtrim(rtrim(number_format((float)$s, 1, '.', ''), '0'), '.');
    }
    // La confirmation vaut pour des chiffres précis : changer un chiffre sans recocher la case la retire (revue du 08/11)
    $change = (bool)array_filter($v, fn($x, $k) => (string)parametre($k, '') !== $x, ARRAY_FILTER_USE_BOTH);
    foreach ($v as $k => $x) parametre_ecrire($k, $x);
    if (!empty($src['confirme'])) parametre_ecrire('volumes_confirme', maintenant() . ' ' . auteur());
    elseif ($change || !empty($src['retirer_confirmation'])) parametre_ecrire('volumes_confirme', '');
    evenement('hygiene', 'Plafonds de la dérogation enregistrés' . (!empty($src['confirme']) ? ' (confirmés par la DDPP)' : ''));
    return '';
}

/* ---------- Poids d'une pièce ---------- */
function volumes_poids_tous(): array {
    $p = [];
    foreach (lignes("SELECT produit_id, variante, grammes FROM volumes_poids") as $r) $p[$r['produit_id'] . '|' . $r['variante']] = (int)$r['grammes'];
    return $p;
}
/** Le poids proposé depuis la recette de la variante (grammes et millilitres), null sans recette pesée. */
function volumes_poids_recette(string $pid, string $code): ?int {
    $g = val("SELECT SUM(r.quantite) FROM recettes r JOIN ingredients i ON i.id = r.ingredient_id WHERE r.produit_id = ? AND r.variante = ? AND i.unite IN ('g', 'ml')", [$pid, $code]);
    return $g !== null && (float)$g > 0 ? (int)round((float)$g) : null;
}
function volumes_poids_fixer(string $pid, string $code, ?int $g): string {
    if (!isset(variantes_toutes()[$pid . '|' . $code])) return 'Gâteau introuvable.';
    if ($g === null) { q("DELETE FROM volumes_poids WHERE produit_id = ? AND variante = ?", [$pid, $code]); return ''; }
    if ($g < 1 || $g > 20000) return 'Un poids en grammes, entre 1 et 20 000.';
    q("INSERT INTO volumes_poids (produit_id, variante, grammes, maj_le, maj_par) VALUES (?, ?, ?, ?, ?)
       ON CONFLICT(produit_id, variante) DO UPDATE SET grammes = excluded.grammes, maj_le = excluded.maj_le, maj_par = excluded.maj_par", [$pid, $code, $g, maintenant(), auteur()]);
    evenement('hygiene', 'Poids d\'une pièce : ' . $g . ' g', ['produit' => $pid]);
    return '';
}

/* ---------- Semaines ---------- */
function lundi_de(string $jour): string { return date('Y-m-d', (int)strtotime($jour . ' -' . ((int)date('N', (int)strtotime($jour)) - 1) . ' days')); }
/** Les commandes remises (et, pour la semaine en cours et la suivante, confirmées) regroupées par semaine :
 *  kilos et montants des restaurants (B2B) et de tous, part des restaurants, gâteaux sans poids. */
function volumes_semaines(?string $auj = null, int $n = VOLUMES_SEMAINES): array {
    $auj = $auj ?? aujourdhui();
    $lundi = lundi_de($auj);
    $debut = date('Y-m-d', (int)strtotime("$lundi -" . ($n - 1) . ' weeks'));
    $fin = date('Y-m-d', (int)strtotime("$lundi +13 days"));
    $S = [];
    for ($d = $debut; $d <= $fin; $d = date('Y-m-d', (int)strtotime("$d +7 days")))
        $S[$d] = ['lundi' => $d, 'kg_pro' => 0.0, 'kg' => 0.0, 'ttc_pro' => 0, 'ttc' => 0, 'pieces_pro' => 0, 'pieces' => 0, 'sans_poids' => [], 'prevu' => $d >= $lundi, 'restaurants' => [],
                  'sur_mesure' => [], 'sur_mesure_sans_poids' => 0];
    $poids = volumes_poids_tous(); $V = variantes_toutes(); $PK = volumes_poids_commandes();
    foreach (lignes("SELECT k.id, k.reference, k.lignes, k.type, k.statut, k.client_id, c.type AS client_type, COALESCE(k.retrait_date, substr(k.remise_le, 1, 10), substr(k.recue_le, 1, 10)) AS jour
                     FROM commandes k JOIN clients c ON c.id = k.client_id
                     WHERE (k.statut = 'remise' OR (k.statut = 'confirmee' AND COALESCE(k.retrait_date, '') >= ?)) AND COALESCE(k.retrait_date, substr(k.remise_le, 1, 10), substr(k.recue_le, 1, 10)) BETWEEN ? AND ?",
                    [$lundi, $debut, $fin]) as $k) {
        $s = lundi_de((string)$k['jour']);
        if (!isset($S[$s])) continue;
        $pro = commande_est_pro($k);
        $lignes = array_filter(json_decode((string)$k['lignes'], true) ?: [], fn($l) => (string)($l['v'] ?? '') !== '' && (int)($l['q'] ?? 0) >= 1);
        if (!$lignes) {
            // Une commande sur mesure (sans ligne de la carte) compte aussi : son poids se tape à la main (audit n° 8, constat 11)
            $g = $PK[(int)$k['id']] ?? null;
            $S[$s]['sur_mesure'][(int)$k['id']] = ['id' => (int)$k['id'], 'reference' => (string)$k['reference'], 'pro' => $pro, 'grammes' => $g];
            $S[$s]['pieces']++;
            if ($pro) { $S[$s]['pieces_pro']++; $S[$s]['restaurants'][(int)$k['client_id']] = true; }
            if ($g !== null) { $S[$s]['kg'] += $g / 1000; if ($pro) $S[$s]['kg_pro'] += $g / 1000; }
            else $S[$s]['sur_mesure_sans_poids']++;
            continue;
        }
        foreach ($lignes as $l) {
            $v = (string)($l['v'] ?? ''); $q = (int)($l['q'] ?? 0);
            $ttc = $q * (int)($l['prix'] ?? 0);
            $S[$s]['ttc'] += $ttc; $S[$s]['pieces'] += $q;
            if ($pro) { $S[$s]['ttc_pro'] += $ttc; $S[$s]['pieces_pro'] += $q; $S[$s]['restaurants'][(int)$k['client_id']] = true; }
            if (isset($poids[$v])) { $kg = $q * $poids[$v] / 1000; $S[$s]['kg'] += $kg; if ($pro) $S[$s]['kg_pro'] += $kg; }
            else $S[$s]['sans_poids'][$v] = trim(($V[$v]['nom'] ?? ($l['nom'] ?? 'Gâteau')) . (($V[$v]['libelle'] ?? '') !== '' ? ', ' . $V[$v]['libelle'] : ''));
        }
    }
    foreach ($S as &$w) {
        $w['incomplet'] = $w['sans_poids'] || $w['sur_mesure_sans_poids'] > 0;
        $w['part_kg'] = $w['kg'] > 0 && !$w['incomplet'] ? round($w['kg_pro'] * 100 / $w['kg'], 1) : null;
        // La part en montant n'est qu'une indication : elle ne choisit jamais le plafond
        $w['part'] = $w['part_kg'] ?? ($w['ttc'] > 0 ? round($w['ttc_pro'] * 100 / $w['ttc'], 1) : null);
        $w['part_sur'] = $w['part_kg'] !== null ? 'kg' : 'ttc';
        $w['plafond'] = volumes_plafond($w['part_kg']);
        $w['niveau'] = volumes_niveau($w);
    }
    return $S;
}
/**
 * « depasse », « proche », « ok », « inconnu » (pas de plafond, ou des gâteaux sans poids vendus aux restaurants),
 * ou « non_verifie » : sous le plafond, mais plafonds pas encore confirmés par la DDPP (jamais « ok » avant ; audit n° 8, constat 4).
 */
function volumes_niveau(array $w): string {
    if ($w['plafond'] === null) return 'inconnu';
    $alerte = volumes_reglage('volumes_alerte') ?? 80.0;
    if ($w['kg_pro'] > $w['plafond']) return 'depasse';
    if ($w['kg_pro'] >= $w['plafond'] * $alerte / 100) return 'proche';
    if (($w['incomplet'] ?? (bool)$w['sans_poids']) && $w['pieces_pro'] > 0) return 'inconnu';
    return volumes_confirme() === '' ? 'non_verifie' : 'ok';
}
/** Le poids tapé des commandes sur mesure, en grammes. */
function volumes_poids_commandes(): array {
    $p = [];
    foreach (lignes("SELECT commande_id, grammes FROM volumes_commandes") as $r) $p[(int)$r['commande_id']] = (int)$r['grammes'];
    return $p;
}
function volumes_poids_commande_fixer(int $kid, ?int $g): string {
    if (!commande($kid)) return 'Commande introuvable.';
    if ($g === null) { q("DELETE FROM volumes_commandes WHERE commande_id = ?", [$kid]); return ''; }
    if ($g < 1 || $g > 200000) return 'Un poids en grammes, entre 1 et 200 000.';
    q("INSERT INTO volumes_commandes (commande_id, grammes, maj_le, maj_par) VALUES (?, ?, ?, ?)
       ON CONFLICT(commande_id) DO UPDATE SET grammes = excluded.grammes, maj_le = excluded.maj_le, maj_par = excluded.maj_par", [$kid, $g, maintenant(), auteur()]);
    evenement('hygiene', 'Poids d\'une commande sur mesure : ' . $g . ' g', ['commande' => $kid]);
    return '';
}
function kilos(float $kg): string { return str_replace('.', ',', rtrim(rtrim(number_format($kg, 1, '.', ''), '0'), '.')) . ' kg'; }

/** Les restaurants actifs (clients professionnels), avec la distance tapée. */
function volumes_restaurants(): array {
    $km = [];
    foreach (lignes("SELECT client_id, km FROM volumes_restaurants") as $r) $km[(int)$r['client_id']] = $r['km'] !== null ? (float)$r['km'] : null;
    $l = [];
    foreach (lignes("SELECT * FROM clients WHERE type = 'professionnel' AND statut = 'actif' ORDER BY societe, nom") as $c) $l[] = $c + ['km' => $km[(int)$c['id']] ?? null];
    return $l;
}
function volumes_km_fixer(int $cid, ?float $km): string {
    if (!val("SELECT 1 FROM clients WHERE id = ? AND type = 'professionnel'", [$cid])) return 'Restaurant introuvable.';
    if ($km !== null && ($km < 0 || $km > 2000)) return 'Une distance en kilomètres, entre 0 et 2 000.';
    q("INSERT INTO volumes_restaurants (client_id, km, maj_le, maj_par) VALUES (?, ?, ?, ?)
       ON CONFLICT(client_id) DO UPDATE SET km = excluded.km, maj_le = excluded.maj_le, maj_par = excluded.maj_par", [$cid, $km, maintenant(), auteur()]);
    return '';
}

/* ---------- Documents ---------- */
function documents(bool $retires = false): array {
    return lignes("SELECT * FROM documents" . ($retires ? '' : " WHERE retire_le = ''") . " ORDER BY retire_le <> '', CASE WHEN lien = '' THEN 0 ELSE 1 END, id");
}
function document(int $id): ?array { return ligne("SELECT * FROM documents WHERE id = ?", [$id]); }
/** La validité seule, que le lien soit vérifié ou proposé : « expire », « bientot », ou « » (audit n° 8, constat 7). */
function document_validite(array $d, ?string $auj = null): string {
    $auj = $auj ?? aujourdhui();
    if ($d['valide_jusqua'] !== '' && $d['valide_jusqua'] < $auj) return 'expire';
    if ($d['valide_jusqua'] !== '' && $d['valide_jusqua'] <= date('Y-m-d', (int)strtotime("$auj +" . DOCUMENTS_PREAVIS . ' days'))) return 'bientot';
    return '';
}
/** « manquant », « propose » (lien proposé par Olivier, à vérifier par Youngmi), « expire », « bientot », « ok ». */
function document_etat(array $d, ?string $auj = null): string {
    if ($d['lien'] === '') return 'manquant';
    if (($d['propose_par'] ?? '') !== '') return 'propose';
    return document_validite($d, $auj) ?: 'ok';
}
/** Le titre affiché : celui de la pièce attendue (traduit), sinon celui tapé. */
function document_titre(array $d): string { return $d['attendu'] !== '' && isset(DOCUMENTS_ATTENDUS[$d['attendu']]) ? DOCUMENTS_ATTENDUS[$d['attendu']] : (string)$d['titre']; }
function document_enregistrer(?int $id, array $src): array {
    $d = $id ? document($id) : null;
    if ($id && !$d) return [0, 'Document introuvable.'];
    $titre = $d && $d['attendu'] !== '' ? (string)$d['titre'] : une_ligne(mb_substr((string)($src['titre'] ?? ''), 0, 150));
    $lien = trim((string)($src['lien'] ?? ''));
    $val = trim((string)($src['valide_jusqua'] ?? ''));
    $note = mb_substr(trim((string)($src['note'] ?? '')), 0, 500);
    if ($titre === '') return [0, 'Donnez un titre au document.'];
    if ($lien !== '' && !lien_drive_valide($lien)) return [0, 'Le lien doit être une adresse du Drive (https://drive.google.com/… ou https://docs.google.com/…).'];
    if ($val !== '' && !date_valide($val)) return [0, 'La date de validité n\'est pas une date.'];
    $now = maintenant();
    // Enregistrer le formulaire vaut vérification d'un lien proposé, sauf par la personne qui l'a proposé avec le même lien (revue du 08/11)
    $propose = $d && $d['propose_par'] !== '' && $d['propose_par'] === auteur() && $lien === $d['lien'] ? $d['propose_par'] : '';
    if ($d) q("UPDATE documents SET titre = ?, lien = ?, valide_jusqua = ?, note = ?, maj_le = ?, maj_par = ?, propose_par = ? WHERE id = ?", [$titre, $lien, $val, $note, $now, auteur(), $propose, $id]);
    else { q("INSERT INTO documents (titre, lien, valide_jusqua, note, cree_le, cree_par, maj_le, maj_par) VALUES (?, ?, ?, ?, ?, ?, ?, ?)", [$titre, $lien, $val, $note, $now, auteur(), $now, auteur()]); $id = (int)db()->lastInsertId(); }
    evenement('hygiene', 'Document de preuve ' . ($d ? 'modifié' : 'ajouté') . ' (n° ' . $id . ')');
    return [$id, ''];
}
/** « C'est le bon document » : le lien proposé devient vérifié. */
function document_confirmer(int $id): string {
    $d = document($id);
    if (!$d || $d['propose_par'] === '') return 'Document introuvable.';
    if ($d['propose_par'] === auteur()) return 'Vous avez proposé ce lien : c\'est à Youngmi de le vérifier.';
    q("UPDATE documents SET propose_par = '', maj_le = ?, maj_par = ? WHERE id = ? AND propose_par <> ''", [maintenant(), auteur(), $id]);
    return '';
}
/** Liens proposés par Olivier (administrateur), collés une fois : « code | lien | note » par ligne. Jamais par-dessus un lien vérifié.
 *  Les liens restent hors du dépôt public : ils ne vivent que dans la base. */
function documents_proposer(string $texte): array {
    $cr = ['proposes' => 0, 'refusees' => []];
    foreach (preg_split('/\R/u', $texte) ?: [] as $i => $ligne) {
        if (trim($ligne) === '') continue;
        $c = array_map('trim', explode('|', $ligne, 3));
        $code = $c[0] ?? ''; $lien = $c[1] ?? ''; $note = mb_substr($c[2] ?? '', 0, 500);
        $d = isset(DOCUMENTS_ATTENDUS[$code]) ? ligne("SELECT * FROM documents WHERE attendu = ? AND retire_le = '' ORDER BY id LIMIT 1", [$code]) : null;
        if (!$d || !lien_drive_valide($lien) || ($d['lien'] !== '' && $d['propose_par'] === '')) { $cr['refusees'][] = $i + 1; continue; }
        q("UPDATE documents SET lien = ?, note = CASE WHEN ? <> '' THEN ? ELSE note END, propose_par = ?, maj_le = ?, maj_par = ? WHERE id = ?", [$lien, $note, $note, auteur(), maintenant(), auteur(), (int)$d['id']]);
        $cr['proposes']++;
    }
    if ($cr['proposes']) evenement('hygiene', 'Liens de documents proposés : ' . $cr['proposes']);
    return $cr;
}
/** Retirer (ou remettre) un document : rien ne s'efface, il passe dans « Retirés ». */
function document_retirer(int $id, bool $retirer): void {
    q("UPDATE documents SET retire_le = ?, retire_par = ? WHERE id = ?", [$retirer ? maintenant() : '', $retirer ? auteur() : '', $id]);
}

/* ---------- Tâche de nuit : alertes de volumes et documents à renouveler ---------- */
function volumes_nuit(?string $auj = null): array {
    if (!volumes_pret() || !obligations_pret()) return [];
    volumes_preparer();
    $auj = $auj ?? aujourdhui();
    $cr = ['volumes' => 0, 'documents' => 0];
    $lundi = lundi_de($auj);
    foreach (volumes_semaines($auj) as $s => $w) {
        if ($s < date('Y-m-d', (int)strtotime("$lundi -7 days")) || $s > $lundi || !in_array($w['niveau'], ['proche', 'depasse'], true)) continue;
        // Déjà prévenu « proche », la semaine passe « dépassé » : la démarche devient importante et prioritaire (audit n° 8, constat 12)
        $ex = ligne("SELECT * FROM demarches WHERE declencheur = ?", ['volumes:' . $s]);
        if ($ex) {
            if ($w['niveau'] === 'depasse' && (int)$ex['importante'] === 0) {
                $rouvre = in_array($ex['etat'], ['fait', 'sans_objet'], true);
                q("UPDATE demarches SET importante = 1, priorite = 1, modifie_le = ?, modifie_par = 'gestion'" . ($rouvre ? ", etat = 'a_faire', fait_le = '', fait_par = ''" : '') . " WHERE id = ?", [maintenant(), (int)$ex['id']]);
                demarche_historique_noter('modification', (int)$ex['id'], null, 'importante', '0', '1', 'système');
                if ($rouvre) demarche_historique_noter('rouverte', (int)$ex['id'], null, 'etat', (string)$ex['etat'], 'a_faire', 'système');
                $cr['volumes_aggraves'] = ($cr['volumes_aggraves'] ?? 0) + 1;
            }
            continue;
        }
        $cr['volumes'] += demarche_creer(['titre' => VOLUMES_TITRE, 'explication' => 'Semaine du ' . date_fr($s, 'longue') . '.', 'ou' => VOLUMES_OU, 'pour_qui' => 'deux',
            'echeance' => max($auj, date('Y-m-d', (int)strtotime("$s +6 days"))), 'a_confirmer' => 0, 'preavis' => OBLIGATIONS_JOURS, 'priorite' => 1,
            'importante' => $w['niveau'] === 'depasse' ? 1 : 0, 'origine' => 'Hygiène'], 'gestion', ['declencheur' => 'volumes:' . $s, 'ref_genre' => 'volumes', 'ref_id' => $s]) ? 1 : 0;
    }
    foreach (documents() as $d) {
        if ($d['lien'] === '' || document_validite($d, $auj) === '') continue;   // un lien proposé expire aussi
        
        $cr['documents'] += demarche_creer(['titre' => DOCUMENTS_TITRE, 'explication' => 'Document n° ' . (int)$d['id'] . '.', 'ou' => DOCUMENTS_OU, 'pour_qui' => 'youngmi',
            'echeance' => $d['valide_jusqua'], 'a_confirmer' => 0, 'preavis' => DOCUMENTS_PREAVIS, 'priorite' => 2, 'importante' => 0, 'origine' => 'Documents'], 'gestion',
            ['declencheur' => 'document:' . (int)$d['id'] . ':' . $d['valide_jusqua'], 'ref_genre' => 'document', 'ref_id' => (string)$d['id']]) ? 1 : 0;
    }
    $cr['distances'] = volumes_distances_nuit($auj);
    return $cr;
}
/**
 * Un restaurant actif avec une commande confirmée dans les 14 jours, dont la distance est inconnue ou au-delà de la limite :
 * une démarche à faire avant de livrer (audit n° 8, constat 10). Une seule par restaurant et par distance tapée.
 */
function volumes_distances_nuit(string $auj): int {
    $lim = volumes_reglage('volumes_km');
    $n = 0;
    foreach (volumes_restaurants() as $c) {
        if ($c['km'] !== null && ($lim === null || $c['km'] <= $lim)) continue;
        $k = ligne("SELECT MIN(retrait_date) AS j FROM commandes WHERE client_id = ? AND statut = 'confirmee' AND retrait_date BETWEEN ? AND ?",
                   [(int)$c['id'], $auj, date('Y-m-d', (int)strtotime("$auj +" . VOLUMES_KM_JOURS . ' days'))]);
        if (!$k || $k['j'] === null) continue;
        $n += demarche_creer(['titre' => VOLUMES_KM_TITRE, 'explication' => 'Restaurant n° ' . (int)$c['id'] . '.', 'ou' => VOLUMES_KM_OU, 'pour_qui' => 'deux',
            'echeance' => (string)$k['j'], 'a_confirmer' => 0, 'preavis' => OBLIGATIONS_JOURS, 'priorite' => 1, 'importante' => $c['km'] !== null ? 1 : 0, 'origine' => 'Hygiène'], 'gestion',
            ['declencheur' => 'distance:' . (int)$c['id'] . ':' . ($c['km'] === null ? 'inconnue' : rtrim(rtrim(number_format((float)$c['km'], 1, '.', ''), '0'), '.')), 'ref_genre' => 'client', 'ref_id' => (string)$c['id']]) ? 1 : 0;
    }
    return $n;
}
