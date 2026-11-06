<?php
/*
 * Madame Cho : espace de gestion. Les registres : clients, commandes, produits (ingrédients, recettes),
 * et ce qu'on en tire (tableau de bord, statistiques, exports, conservation). Jamais servi directement.
 */
declare(strict_types=1);
namespace MadameCho\Gestion;

use RuntimeException;

/* ---------- Clients ---------- */
function client_numero_suivant(): string {
    $n = (int)val("SELECT MAX(CAST(SUBSTR(numero, 4) AS INTEGER)) FROM clients");
    return sprintf('CL-%04d', $n + 1);
}
function client(int $id): ?array { return ligne("SELECT * FROM clients WHERE id = ?", [$id]); }
function client_nom(array $c): string {
    if ($c['statut'] === 'efface') return $c['societe'] !== '' ? $c['societe'] . ' (contact effacé)' : 'Client effacé';
    $n = trim($c['prenom'] . ' ' . $c['nom']);
    if ($c['type'] === 'professionnel' && $c['societe'] !== '') return $c['societe'] . ($n !== '' ? ' · ' . $n : '');
    return $n !== '' ? $n : ($c['societe'] !== '' ? $c['societe'] : $c['numero']);
}
/** Sépare « Prénom Nom » du formulaire du site : le dernier mot est le nom. */
function prenom_nom(string $complet): array {
    $parts = preg_split('/\s+/u', trim($complet)) ?: [];
    if (count($parts) <= 1) return ['', $complet];
    $nom = array_pop($parts);
    return [implode(' ', $parts), $nom];
}
/** Retrouve un client par email (le plus récent non effacé), ou le crée. */
function client_trouver_ou_creer(string $email, string $nomComplet, string $tel, string $canal, bool $pro, string $societe, string $quand): int {
    $email = mb_strtolower(trim($email));
    if ($email !== '') {
        $c = ligne("SELECT * FROM clients WHERE email = ? AND statut <> 'efface' ORDER BY id DESC LIMIT 1", [$email]);
        if ($c) {
            $maj = ['dernier_echange' => max($c['dernier_echange'], $quand), 'maj_le' => maintenant()];
            if ($tel !== '' && $c['telephone'] === '') $maj['telephone'] = $tel;
            if ($pro && $societe !== '' && $c['societe'] === '') { $maj['societe'] = $societe; $maj['type'] = 'professionnel'; }
            if ($c['statut'] === 'inactif') $maj['statut'] = 'actif';
            $set = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($maj)));
            q("UPDATE clients SET $set WHERE id = :id", $maj + ['id' => $c['id']]);
            return (int)$c['id'];
        }
    }
    [$prenom, $nom] = prenom_nom($nomComplet);
    return client_creer(['type' => $pro ? 'professionnel' : 'particulier', 'nom' => $nom, 'prenom' => $prenom, 'societe' => $pro ? $societe : '',
                         'email' => $email, 'telephone' => $tel, 'canal' => $canal, 'premier_contact' => $quand, 'dernier_echange' => $quand]);
}
function client_creer(array $d): int {
    $now = maintenant();
    $d += ['type' => 'particulier', 'nom' => '', 'prenom' => '', 'societe' => '', 'contact' => '', 'email' => '', 'telephone' => '', 'ville' => '',
           'canal' => 'site', 'premier_contact' => $now, 'dernier_echange' => $now, 'qonto' => ''];
    q("INSERT INTO clients (numero, type, nom, prenom, societe, contact, email, telephone, ville, canal, premier_contact, dernier_echange, qonto, cree_le, maj_le)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
      [client_numero_suivant(), $d['type'], $d['nom'], $d['prenom'], $d['societe'], $d['contact'], mb_strtolower($d['email']), $d['telephone'], $d['ville'],
       $d['canal'], $d['premier_contact'], $d['dernier_echange'], $d['qonto'], $now, $now]);
    $id = (int)db()->lastInsertId();
    evenement('creation', 'Fiche client créée', ['client' => $id]);
    return $id;
}
function client_modifier(int $id, array $d): void {
    $c = client($id);
    if (!$c) return;
    $champs = ['type', 'nom', 'prenom', 'societe', 'contact', 'email', 'telephone', 'ville', 'canal', 'qonto', 'lettre_info'];
    $diff = [];
    foreach ($champs as $k) if (array_key_exists($k, $d) && (string)$d[$k] !== (string)$c[$k]) $diff[$k] = (string)$d[$k];
    if (!$diff) return;
    if (isset($diff['email'])) $diff['email'] = mb_strtolower($diff['email']);
    if (isset($diff['lettre_info'])) $diff['lettre_info_le'] = $diff['lettre_info'] !== '' ? aujourdhui() : null;
    $diff['maj_le'] = maintenant();
    $set = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($diff)));
    q("UPDATE clients SET $set WHERE id = :id", $diff + ['id' => $id]);
    unset($diff['maj_le'], $diff['lettre_info_le']);
    evenement('modification', 'Fiche modifiée : ' . implode(', ', array_keys($diff)), ['client' => $id]);
}
function client_echange(int $id, string $quand): void {
    q("UPDATE clients SET dernier_echange = MAX(dernier_echange, ?), statut = CASE WHEN statut = 'inactif' THEN 'actif' ELSE statut END, maj_le = ? WHERE id = ?", [$quand, maintenant(), $id]);
}
/** Chiffres d'un client : commandes, remises, total, dernière. */
function client_chiffres(int $id): array {
    $r = ligne("SELECT COUNT(*) AS n, SUM(CASE WHEN statut = 'remise' THEN 1 ELSE 0 END) AS remises,
                       SUM(CASE WHEN statut = 'remise' THEN COALESCE(prix_final, total_indicatif) ELSE 0 END) AS total,
                       MAX(CASE WHEN statut = 'remise' THEN retrait_date END) AS derniere
                FROM commandes WHERE client_id = ?", [$id]) ?? [];
    $n = (int)($r['remises'] ?? 0); $t = (int)($r['total'] ?? 0);
    return ['commandes' => (int)($r['n'] ?? 0), 'remises' => $n, 'total' => $t, 'panier' => $n ? intdiv($t, $n) : 0, 'derniere' => $r['derniere'] ?? null];
}
function clients_lister(string $recherche = '', string $filtre = 'actifs'): array {
    $w = ["1=1"]; $p = [];
    if ($filtre === 'actifs') $w[] = "c.statut <> 'efface'";
    elseif ($filtre === 'pros') $w[] = "c.type = 'professionnel' AND c.statut <> 'efface'";
    elseif ($filtre === 'particuliers') $w[] = "c.type = 'particulier' AND c.statut <> 'efface'";
    if ($recherche !== '') {
        $w[] = "(c.nom LIKE :r OR c.prenom LIKE :r OR c.societe LIKE :r OR c.email LIKE :r OR c.telephone LIKE :r OR c.numero LIKE :r OR c.ville LIKE :r)";
        $p['r'] = '%' . $recherche . '%';
    }
    return lignes("SELECT c.*, (SELECT COUNT(*) FROM commandes k WHERE k.client_id = c.id AND k.statut = 'remise') AS remises,
                           (SELECT COUNT(*) FROM commandes k WHERE k.client_id = c.id AND k.statut IN ('demande','proposition','confirmee')) AS en_cours,
                           (SELECT SUM(COALESCE(prix_final, total_indicatif)) FROM commandes k WHERE k.client_id = c.id AND k.statut = 'remise') AS total,
                           (SELECT MAX(retrait_date) FROM commandes k WHERE k.client_id = c.id AND k.statut = 'remise') AS derniere
                    FROM clients c WHERE " . implode(' AND ', $w) . " ORDER BY c.dernier_echange DESC, c.id DESC LIMIT 500", $p);
}
/** Fusionne le doublon $source dans $cible : commandes, historique, puis le doublon est marqué effacé. */
function client_fusionner(int $cible, int $source): bool {
    if ($cible === $source) return false;
    $a = client($cible); $b = client($source);
    if (!$a || !$b || $a['statut'] === 'efface') return false;
    q("UPDATE commandes SET client_id = ? WHERE client_id = ?", [$cible, $source]);
    q("UPDATE evenements SET client_id = ? WHERE client_id = ?", [$cible, $source]);
    $maj = ['dernier_echange' => max($a['dernier_echange'], $b['dernier_echange']), 'premier_contact' => min($a['premier_contact'], $b['premier_contact']), 'maj_le' => maintenant()];
    foreach (['telephone', 'ville', 'societe', 'contact', 'email'] as $k) if ($a[$k] === '' && $b[$k] !== '') $maj[$k] = $b[$k];
    $set = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($maj)));
    q("UPDATE clients SET $set WHERE id = :id", $maj + ['id' => $cible]);
    q("UPDATE clients SET statut = 'efface', nom = '', prenom = '', email = '', telephone = '', ville = '', contact = '', efface_le = ?, maj_le = ? WHERE id = ?", [maintenant(), maintenant(), $source]);
    evenement('fusion', 'Doublon ' . $b['numero'] . ' fusionné dans cette fiche', ['client' => $cible]);
    return true;
}
/** Effacement (RGPD ou fin de conservation) : la fiche reste pour les statistiques, sans rien de personnel. */
function client_effacer(int $id, string $motif, ?string $auteur = null): void {
    $c = client($id);
    if (!$c || $c['statut'] === 'efface') return;
    $now = maintenant();
    q("UPDATE clients SET statut = 'efface', nom = '', prenom = '', contact = '', email = '', telephone = '', ville = '', qonto = '',
       lettre_info = '', lettre_info_le = NULL, avis_demande_le = NULL, avis_recu_le = NULL, efface_le = ?, maj_le = ? WHERE id = ?", [$now, $now, $id]);
    q("UPDATE evenements SET texte = '(effacé)', ancien = '', nouveau = '' WHERE client_id = ? AND type IN ('commentaire', 'modification')", [$id]);
    foreach (lignes("SELECT id FROM commandes WHERE client_id = ?", [$id]) as $k) commande_anonymiser((int)$k['id'], $now);
    evenement('effacement', 'Données personnelles effacées : ' . $motif, ['client' => $id], '', '', $auteur);
}
/** Données d'un client pour une demande d'accès ou de portabilité (tableau lisible, JSON). */
function client_exporter(int $id): array {
    $c = client($id);
    if (!$c) return [];
    $commandes = [];
    foreach (lignes("SELECT * FROM commandes WHERE client_id = ? ORDER BY recue_le", [$id]) as $k) {
        $commandes[] = ['reference' => $k['reference'], 'recue_le' => $k['recue_le'], 'statut' => STATUTS[$k['statut']] ?? $k['statut'],
                        'retrait' => $k['retrait_date'], 'contenu' => commande_contenu_texte($k), 'personnalisation' => $k['perso'], 'besoin' => $k['besoin'],
                        'total_indicatif' => euros((int)$k['total_indicatif']), 'prix_final' => euros($k['prix_final'] === null ? null : (int)$k['prix_final']),
                        'paiement' => PAIEMENTS[$k['paiement']] ?? $k['paiement'],
                        'notes' => array_column(lignes("SELECT texte FROM evenements WHERE commande_id = ? AND type = 'commentaire' ORDER BY le", [$k['id']]), 'texte')];
    }
    return ['client' => ['numero' => $c['numero'], 'type' => TYPES_CLIENT[$c['type']] ?? $c['type'], 'nom' => $c['nom'], 'prenom' => $c['prenom'], 'societe' => $c['societe'],
                         'contact' => $c['contact'], 'email' => $c['email'], 'telephone' => $c['telephone'], 'ville' => $c['ville'], 'canal' => CANAUX[$c['canal']] ?? $c['canal'],
                         'premier_contact' => $c['premier_contact'], 'dernier_echange' => $c['dernier_echange'],
                         'notes' => array_column(lignes("SELECT texte FROM evenements WHERE client_id = ? AND commande_id IS NULL AND type = 'commentaire' ORDER BY le", [$id]), 'texte')],
            'commandes' => $commandes, 'exporte_le' => maintenant()];
}

/* ---------- Commandes ---------- */
function reference_nouvelle(): string {
    for ($i = 0; $i < 20; $i++) {
        $r = 'MC-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
        if (!val("SELECT 1 FROM commandes WHERE reference = ?", [$r])) return $r;
    }
    throw new RuntimeException('référence introuvable');
}
function commande(int $id): ?array {
    $k = ligne("SELECT k.*, c.numero AS client_numero, c.nom AS client_nom, c.prenom AS client_prenom, c.societe AS client_societe, c.type AS client_type,
                       c.email AS client_email, c.telephone AS client_tel, c.statut AS client_statut, c.ville AS client_ville, c.contact AS client_contact
                FROM commandes k JOIN clients c ON c.id = k.client_id WHERE k.id = ?", [$id]);
    if ($k) $k['lignes_tab'] = json_decode($k['lignes'], true) ?: [];
    return $k;
}
function commande_par_reference(string $ref): ?array { $id = val("SELECT id FROM commandes WHERE reference = ?", [$ref]); return $id ? commande((int)$id) : null; }
/** Nom du client tel qu'une ligne de liste le montre. */
function commande_client_nom(array $k): string {
    return client_nom(['statut' => $k['client_statut'], 'societe' => $k['client_societe'], 'prenom' => $k['client_prenom'], 'nom' => $k['client_nom'], 'type' => $k['client_type'], 'numero' => $k['client_numero']]);
}
/** Lignes normalisées : [['v' => 'produit|code', 'nom', 'libelle', 'q', 'prix' (unitaire), 'plaque']]. */
function lignes_normaliser(array $brut): array {
    $V = variantes(); $out = [];
    foreach ($brut as $l) {
        if (!is_array($l)) continue;
        $v = (string)($l['v'] ?? ''); $q = (int)($l['q'] ?? 0);
        if ($q < 1) continue;
        if (isset($V[$v])) $d = $V[$v]; else { $d = ['nom' => (string)($l['nom'] ?? 'Gâteau'), 'libelle' => (string)($l['libelle'] ?? ''), 'prix' => (int)($l['prix'] ?? 0), 'plaque' => true]; }
        $out[] = ['v' => $v, 'nom' => $d['nom'], 'libelle' => $d['libelle'], 'q' => min($q, 999), 'prix' => isset($l['prix']) && isset($V[$v]) ? (int)$l['prix'] : $d['prix'],
                  'plaque' => mb_substr(une_ligne((string)($l['plaque'] ?? '')), 0, 60)];
    }
    return $out;
}
function lignes_total(array $lignes): int { $t = 0; foreach ($lignes as $l) $t += $l['prix'] * $l['q']; return $t; }
function commande_contenu_texte(array $k, bool $court = false): string {
    $l = isset($k['lignes_tab']) ? $k['lignes_tab'] : (json_decode($k['lignes'] ?? '[]', true) ?: []);
    $parts = [];
    foreach ($l as $x) $parts[] = $x['q'] . ' × ' . $x['nom'] . ($court ? '' : ', ' . $x['libelle']) . (($x['plaque'] ?? '') !== '' && !$court ? ' (plaque « ' . $x['plaque'] . ' »)' : '');
    if (($k['perso'] ?? '') !== '') $parts[] = $court ? 'Personnalisé' : 'Personnalisé : ' . $k['perso'];
    if (($k['besoin'] ?? '') !== '') $parts[] = $court ? 'Demande pro' : 'Pro : ' . $k['besoin'];
    return implode($court ? ' · ' : "\n", $parts) ?: '(sans contenu)';
}
function commande_creer(array $d): int {
    $now = maintenant();
    $lignes = lignes_normaliser($d['lignes'] ?? []);
    $type = $d['type'] ?? (($d['besoin'] ?? '') !== '' ? 'pro' : ((($d['perso'] ?? '') !== '' || !$lignes) ? 'perso' : 'carte'));
    $ref = $d['reference'] ?? reference_nouvelle();
    q("INSERT INTO commandes (reference, client_id, canal, type, statut, retrait_date, retrait_moment, retrait_heure, mode, lignes, perso, besoin, total_indicatif,
       recue_le, dernier_echange, cree_le, maj_le) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
      [$ref, $d['client_id'], $d['canal'] ?? 'site', $type, $d['statut'] ?? 'demande', $d['retrait_date'] ?? null, $d['retrait_moment'] ?? '', $d['retrait_heure'] ?? '',
       $d['mode'] ?? 'retrait', json_encode($lignes, JSON_UNESCAPED_UNICODE), $d['perso'] ?? '', $d['besoin'] ?? '', lignes_total($lignes),
       $d['recue_le'] ?? $now, $d['recue_le'] ?? $now, $now, $now]);
    $id = (int)db()->lastInsertId();
    client_echange((int)$d['client_id'], $d['recue_le'] ?? $now);
    evenement('creation', $d['origine'] ?? 'Demande créée', ['commande' => $id, 'client' => (int)$d['client_id']], '', $d['statut'] ?? 'demande', $d['auteur'] ?? null);
    return $id;
}
function commande_modifier(int $id, array $d): void {
    $k = commande($id);
    if (!$k) return;
    $diff = [];
    foreach (['canal', 'type', 'retrait_date', 'retrait_moment', 'retrait_heure', 'mode', 'perso', 'besoin', 'facture', 'moyen', 'acompte', 'prix_final'] as $c) {
        if (array_key_exists($c, $d) && (string)$d[$c] !== (string)$k[$c]) $diff[$c] = $d[$c];
    }
    if (isset($d['lignes'])) {
        $l = lignes_normaliser($d['lignes']);
        $j = json_encode($l, JSON_UNESCAPED_UNICODE);
        if ($j !== $k['lignes']) { $diff['lignes'] = $j; $diff['total_indicatif'] = lignes_total($l); }
    }
    if (!$diff) return;
    $diff['maj_le'] = maintenant();
    $set = implode(', ', array_map(fn($c) => "$c = :$c", array_keys($diff)));
    q("UPDATE commandes SET $set WHERE id = :id", $diff + ['id' => $id]);
    unset($diff['maj_le'], $diff['total_indicatif']);
    evenement('modification', 'Commande modifiée : ' . implode(', ', array_keys($diff)), ['commande' => $id, 'client' => (int)$k['client_id']]);
}
/** Changement d'étape, avec ce qui va avec (date de remise, motif, retrait). */
function commande_statut(int $id, string $statut, array $extra = []): bool {
    $k = commande($id);
    if (!$k || !isset(STATUTS[$statut]) || $k['statut'] === $statut) return false;
    $now = maintenant();
    $maj = ['statut' => $statut, 'dernier_echange' => $now, 'maj_le' => $now];
    if ($statut === 'remise') { $maj['remise_le'] = $extra['remise_le'] ?? $now; if (isset($extra['prix_final'])) $maj['prix_final'] = $extra['prix_final']; }
    if ($statut === 'annulee') { $maj['annulee_le'] = $now; $maj['annulee_motif'] = $extra['motif'] ?? ''; }
    if ($statut === 'sans_suite') $maj['sans_suite_le'] = $now;
    if (in_array($statut, STATUTS_EN_COURS, true)) { $maj['remise_le'] = null; $maj['annulee_le'] = null; $maj['sans_suite_le'] = null; }
    if ($statut === 'confirmee') {
        if (!empty($extra['retrait_date'])) $maj['retrait_date'] = $extra['retrait_date'];
        if (isset($extra['retrait_heure'])) $maj['retrait_heure'] = $extra['retrait_heure'];
        if (isset($extra['prix_final'])) $maj['prix_final'] = $extra['prix_final'];
    }
    if ($statut === 'proposition' && isset($extra['prix_final'])) $maj['prix_final'] = $extra['prix_final'];
    $set = implode(', ', array_map(fn($c) => "$c = :$c", array_keys($maj)));
    q("UPDATE commandes SET $set WHERE id = :id", $maj + ['id' => $id]);
    client_echange((int)$k['client_id'], $now);
    $texte = STATUTS[$statut] . (($extra['motif'] ?? '') !== '' ? ' : ' . $extra['motif'] : '');
    evenement('statut', $texte, ['commande' => $id, 'client' => (int)$k['client_id']], $k['statut'], $statut, $extra['auteur'] ?? null);
    return true;
}
function commande_paiement(int $id, string $paiement, string $moyen, ?int $acompte, string $facture): void {
    $k = commande($id);
    if (!$k || !isset(PAIEMENTS[$paiement])) return;
    $now = maintenant();
    q("UPDATE commandes SET paiement = ?, moyen = ?, acompte = ?, facture = ?, maj_le = ? WHERE id = ?",
      [$paiement, isset(MOYENS[$moyen]) ? $moyen : '', $acompte ?? (int)$k['acompte'], $facture, $now, $id]);
    $detail = PAIEMENTS[$paiement] . ($moyen !== '' ? ' · ' . MOYENS[$moyen] : '') . ($acompte ? ' · acompte ' . euros($acompte) : '') . ($facture !== '' ? ' · facture ' . $facture : '');
    if ($k['paiement'] !== $paiement || $k['facture'] !== $facture || (int)$k['acompte'] !== ($acompte ?? (int)$k['acompte']) || $k['moyen'] !== $moyen)
        evenement('paiement', $detail, ['commande' => $id, 'client' => (int)$k['client_id']], $k['paiement'], $paiement);
}
function commande_probleme(int $id, bool $ouvert, string $note): void {
    $k = commande($id);
    if (!$k) return;
    q("UPDATE commandes SET probleme = ?, probleme_note = ?, dernier_echange = ?, maj_le = ? WHERE id = ?", [$ouvert ? 1 : 0, $ouvert ? $note : '', maintenant(), maintenant(), $id]);
    evenement('probleme', ($ouvert ? 'Problème signalé : ' : 'Problème résolu : ') . $note, ['commande' => $id, 'client' => (int)$k['client_id']], $k['probleme'] ? '1' : '0', $ouvert ? '1' : '0');
}
function commentaire(string $texte, ?int $commande, ?int $client): void {
    $texte = trim($texte);
    if ($texte === '') return;
    evenement('commentaire', $texte, ['commande' => $commande, 'client' => $client]);
    $now = maintenant();
    if ($commande) q("UPDATE commandes SET dernier_echange = ?, maj_le = ? WHERE id = ?", [$now, $now, $commande]);
    if ($client) client_echange($client, $now);
}
/** Effacement du contenu personnel d'une commande (fin de conservation) : référence, dates, montants et produits restent. */
function commande_anonymiser(int $id, string $quand): void {
    $k = commande($id);
    if (!$k || $k['efface_le']) return;
    $l = $k['lignes_tab'];
    foreach ($l as &$x) $x['plaque'] = '';
    unset($x);
    q("UPDATE commandes SET lignes = ?, perso = CASE WHEN perso = '' THEN '' ELSE '(effacé)' END, besoin = CASE WHEN besoin = '' THEN '' ELSE '(effacé)' END,
       probleme_note = '', annulee_motif = '', efface_le = ?, maj_le = ? WHERE id = ?", [json_encode($l, JSON_UNESCAPED_UNICODE), $quand, $quand, $id]);
    q("UPDATE evenements SET texte = '(effacé)', ancien = '', nouveau = '' WHERE commande_id = ? AND type IN ('commentaire', 'probleme', 'modification')", [$id]);
}
function commandes_lister(string $vue = 'en_cours', string $recherche = '', ?string $statut = null, int $limite = 300): array {
    $w = []; $p = []; $ordre = "k.recue_le DESC";
    switch ($vue) {
        case 'en_cours': $w[] = "k.statut IN ('demande', 'proposition', 'confirmee')"; $ordre = "CASE k.statut WHEN 'demande' THEN 0 WHEN 'proposition' THEN 1 ELSE 2 END, COALESCE(k.retrait_date, '9999') ASC, k.recue_le ASC"; break;
        case 'a_venir': $w[] = "k.statut = 'confirmee' AND k.retrait_date >= :auj"; $p['auj'] = aujourdhui(); $ordre = "k.retrait_date ASC, k.retrait_heure ASC, k.retrait_moment ASC"; break;
        case 'terminees': $w[] = "k.statut IN ('remise', 'annulee', 'sans_suite')"; $ordre = "k.maj_le DESC"; break;
        case 'problemes': $w[] = "k.probleme = 1"; break;
        case 'a_repondre': $w[] = "k.statut = 'demande'"; $ordre = "k.recue_le ASC"; break;
    }
    if ($statut && isset(STATUTS[$statut])) { $w[] = "k.statut = :s"; $p['s'] = $statut; }
    if ($recherche !== '') {
        $w[] = "(k.reference LIKE :r OR c.nom LIKE :r OR c.prenom LIKE :r OR c.societe LIKE :r OR c.email LIKE :r OR k.facture LIKE :r OR k.perso LIKE :r)";
        $p['r'] = '%' . $recherche . '%';
    }
    $sql = "SELECT k.*, c.numero AS client_numero, c.nom AS client_nom, c.prenom AS client_prenom, c.societe AS client_societe, c.type AS client_type, c.statut AS client_statut
            FROM commandes k JOIN clients c ON c.id = k.client_id" . ($w ? " WHERE " . implode(' AND ', $w) : '') . " ORDER BY $ordre LIMIT $limite";
    $r = lignes($sql, $p);
    foreach ($r as &$k) $k['lignes_tab'] = json_decode($k['lignes'], true) ?: [];
    return $r;
}
function commandes_du_client(int $id): array {
    $r = lignes("SELECT k.* FROM commandes k WHERE client_id = ? ORDER BY COALESCE(retrait_date, recue_le) DESC, recue_le DESC", [$id]);
    foreach ($r as &$k) $k['lignes_tab'] = json_decode($k['lignes'], true) ?: [];
    return $r;
}
function historique(?int $commande, ?int $client, ?string $produit = null): array {
    if ($commande) return lignes("SELECT * FROM evenements WHERE commande_id = ? ORDER BY le DESC, id DESC", [$commande]);
    if ($client) return lignes("SELECT e.*, k.reference FROM evenements e LEFT JOIN commandes k ON k.id = e.commande_id WHERE e.client_id = ? ORDER BY e.le DESC, e.id DESC LIMIT 200", [$client]);
    if ($produit) return lignes("SELECT * FROM evenements WHERE produit_id = ? ORDER BY le DESC, id DESC LIMIT 100", [$produit]);
    return [];
}
/** Vrai si une demande ou une proposition attend depuis trop longtemps (« sans suite ? »). */
function commande_dormante(array $k): bool {
    return in_array($k['statut'], ['demande', 'proposition'], true) && jours_depuis($k['dernier_echange']) >= GESTION_SANS_SUITE_JOURS;
}
function commande_en_retard(array $k): bool { return $k['statut'] === 'demande' && heures_depuis($k['recue_le']) >= GESTION_RETARD_HEURES; }

/* ---------- Tableau de bord ---------- */
function tableau_de_bord(): array {
    $auj = aujourdhui();
    $dans7 = date('Y-m-d', strtotime('+7 days'));
    $r = [];
    $r['a_repondre'] = commandes_lister('a_repondre');
    $r['retraits'] = lignes("SELECT k.*, c.nom AS client_nom, c.prenom AS client_prenom, c.societe AS client_societe, c.type AS client_type, c.statut AS client_statut, c.numero AS client_numero
                             FROM commandes k JOIN clients c ON c.id = k.client_id WHERE k.statut = 'confirmee' AND k.retrait_date BETWEEN ? AND ?
                             ORDER BY k.retrait_date, k.retrait_heure, k.retrait_moment", [$auj, $dans7]);
    foreach ($r['retraits'] as &$k) $k['lignes_tab'] = json_decode($k['lignes'], true) ?: [];
    unset($k);
    $r['depasses'] = lignes("SELECT k.*, c.nom AS client_nom, c.prenom AS client_prenom, c.societe AS client_societe, c.type AS client_type, c.statut AS client_statut, c.numero AS client_numero
                             FROM commandes k JOIN clients c ON c.id = k.client_id WHERE k.statut = 'confirmee' AND k.retrait_date < ? ORDER BY k.retrait_date", [$auj]);
    $r['problemes'] = commandes_lister('problemes');
    $r['acomptes'] = lignes("SELECT k.*, c.nom AS client_nom, c.prenom AS client_prenom, c.societe AS client_societe, c.type AS client_type, c.statut AS client_statut, c.numero AS client_numero
                             FROM commandes k JOIN clients c ON c.id = k.client_id WHERE k.paiement = 'acompte_demande' AND k.statut IN ('proposition', 'confirmee') ORDER BY k.retrait_date");
    $r['propositions'] = lignes("SELECT k.*, c.nom AS client_nom, c.prenom AS client_prenom, c.societe AS client_societe, c.type AS client_type, c.statut AS client_statut, c.numero AS client_numero
                             FROM commandes k JOIN clients c ON c.id = k.client_id WHERE k.statut = 'proposition' ORDER BY k.dernier_echange ASC");
    $mois = date('Y-m');
    $m = ligne("SELECT COUNT(*) AS n, SUM(COALESCE(prix_final, total_indicatif)) AS ca FROM commandes WHERE statut = 'remise' AND substr(remise_le, 1, 7) = ?", [$mois]) ?? [];
    $r['mois'] = ['n' => (int)($m['n'] ?? 0), 'ca' => (int)($m['ca'] ?? 0)];
    $r['mois']['panier'] = $r['mois']['n'] ? intdiv($r['mois']['ca'], $r['mois']['n']) : 0;
    $r['demandes_mois'] = (int)val("SELECT COUNT(*) FROM commandes WHERE substr(recue_le, 1, 7) = ?", [$mois]);
    return $r;
}

/* ---------- Produits, ingrédients, recettes ---------- */
/** Aligne le registre des produits sur le catalogue du site (la partie publique n'est jamais recopiée à la main). */
function produits_synchroniser(): void {
    $now = maintenant();
    foreach (catalogue()['produits'] as $id => $p) {
        $j = json_encode($p, JSON_UNESCAPED_UNICODE);
        $e = ligne("SELECT id, catalogue FROM produits WHERE id = ?", [$id]);
        if (!$e) q("INSERT INTO produits (id, nom, statut, catalogue, cree_le, maj_le) VALUES (?, ?, 'carte', ?, ?, ?)", [$id, $p['nom'], $j, $now, $now]);
        elseif ($e['catalogue'] !== $j) q("UPDATE produits SET nom = ?, catalogue = ?, maj_le = ? WHERE id = ?", [$p['nom'], $j, $now, $id]);
    }
}
function produits_lister(): array {
    produits_synchroniser();
    $r = lignes("SELECT p.*, (SELECT COUNT(*) FROM recettes r WHERE r.produit_id = p.id) AS lignes_recette FROM produits p ORDER BY CASE statut WHEN 'carte' THEN 0 WHEN 'demande' THEN 1 ELSE 2 END, nom");
    foreach ($r as &$p) $p['cat'] = json_decode($p['catalogue'], true) ?: [];
    return $r;
}
function produit(string $id): ?array {
    produits_synchroniser();
    $p = ligne("SELECT * FROM produits WHERE id = ?", [$id]);
    if ($p) $p['cat'] = json_decode($p['catalogue'], true) ?: [];
    return $p;
}
function produit_variantes(array $p): array {
    $c = $p['cat']; $v = [];
    if (($c['mode'] ?? '') === 'taille') foreach ($c['tailles'] ?? [] as $code => $t) $v[$code] = ['libelle' => $t['libelle'], 'prix' => (int)$t['prix']];
    else foreach ($c['parfums'] ?? [] as $code => $t) $v[$code] = ['libelle' => $t['libelle'], 'prix' => (int)($c['unite'] ?? 0)];
    return $v;
}
function produit_modifier(string $id, array $d): void {
    $p = produit($id);
    if (!$p) return;
    $diff = [];
    foreach (['statut', 'fiche_lien', 'recette', 'notes', 'emballage', 'tva', 'valide_par'] as $c) if (array_key_exists($c, $d) && (string)$d[$c] !== (string)$p[$c]) $diff[$c] = $d[$c];
    if (array_key_exists('valide_le', $d) && (string)$d['valide_le'] !== (string)$p['valide_le']) $diff['valide_le'] = $d['valide_le'] ?: null;
    if (!$diff) return;
    $diff['maj_le'] = maintenant();
    $set = implode(', ', array_map(fn($c) => "$c = :$c", array_keys($diff)));
    q("UPDATE produits SET $set WHERE id = :id", $diff + ['id' => $id]);
    unset($diff['maj_le']);
    evenement('modification', 'Fiche produit modifiée : ' . implode(', ', array_keys($diff)), ['produit' => $id]);
}
function ingredients_lister(bool $tous = false): array { return lignes("SELECT * FROM ingredients" . ($tous ? '' : ' WHERE actif = 1') . " ORDER BY nom"); }
function ingredient(int $id): ?array { return ligne("SELECT * FROM ingredients WHERE id = ?", [$id]); }
function ingredient_enregistrer(?int $id, array $d): int {
    $now = maintenant();
    $all = implode(',', array_values(array_intersect($d['allergenes'] ?? [], array_keys(ALLERGENES))));
    if ($id) {
        q("UPDATE ingredients SET nom = ?, unite = ?, prix = ?, allergenes = ?, fournisseur = ?, achat = ?, notes = ?, actif = ?, maj_le = ? WHERE id = ?",
          [$d['nom'], $d['unite'], $d['prix'], $all, $d['fournisseur'], $d['achat'], $d['notes'], $d['actif'] ? 1 : 0, $now, $id]);
        return $id;
    }
    q("INSERT INTO ingredients (nom, unite, prix, allergenes, fournisseur, achat, notes, actif, cree_le, maj_le) VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?)",
      [$d['nom'], $d['unite'], $d['prix'], $all, $d['fournisseur'], $d['achat'], $d['notes'], $now, $now]);
    return (int)db()->lastInsertId();
}
/** Recette d'un produit : [variante => [lignes avec ingrédient, quantité, coût]] et totaux. */
function recette(array $p): array {
    $r = [];
    foreach (produit_variantes($p) as $code => $v) $r[$code] = ['libelle' => $v['libelle'], 'prix' => $v['prix'], 'lignes' => [], 'cout' => 0, 'allergenes' => []];
    $lignes = lignes("SELECT r.*, i.nom, i.unite, i.prix AS prix_unite, i.allergenes FROM recettes r JOIN ingredients i ON i.id = r.ingredient_id WHERE r.produit_id = ? ORDER BY r.id", [$p['id']]);
    foreach ($lignes as $l) {
        if (!isset($r[$l['variante']])) $r[$l['variante']] = ['libelle' => $l['variante'] . ' (variante retirée)', 'prix' => 0, 'lignes' => [], 'cout' => 0, 'allergenes' => []];
        $cout = cout_ligne((float)$l['quantite'], $l['unite'], (int)$l['prix_unite']);
        $l['cout'] = $cout;
        $r[$l['variante']]['lignes'][] = $l;
        $r[$l['variante']]['cout'] += $cout;
        foreach (array_filter(explode(',', $l['allergenes'])) as $a) $r[$l['variante']]['allergenes'][$a] = true;
    }
    foreach ($r as &$v) {
        $v['cout'] += (int)$p['emballage'];
        $v['ht'] = (int)round($v['prix'] / (1 + (int)$p['tva'] / 1000));
        $v['marge'] = $v['lignes'] ? $v['ht'] - $v['cout'] : null;
        $v['allergenes'] = array_keys($v['allergenes']);
    }
    return $r;
}
/** Coût d'une ligne : le prix d'un ingrédient est donné pour 1 kg, 1 l ou 1 pièce (en centimes). */
function cout_ligne(float $quantite, string $unite, int $prixUnite): int {
    return (int)round($unite === 'piece' ? $quantite * $prixUnite : $quantite / 1000 * $prixUnite);
}
/** Allergènes déduits de la recette (toutes variantes) comparés au texte affiché sur le site. */
function allergenes_comparer(array $p, array $recette): array {
    $deduits = [];
    foreach ($recette as $v) foreach ($v['allergenes'] as $a) $deduits[$a] = true;
    $site = mb_strtolower((string)($p['cat']['allergenes'] ?? ''));
    $affiches = [];
    foreach (ALLERGENES_MOTS as $code => $mots) foreach ($mots as $m) if ($site !== '' && mb_strpos($site, $m) !== false) { $affiches[$code] = true; break; }
    return ['deduits' => array_keys($deduits), 'affiches' => array_keys($affiches),
            'manquants' => array_values(array_diff(array_keys($deduits), array_keys($affiches))),   // dans la recette, pas sur le site : à corriger vite
            'en_trop' => array_values(array_diff(array_keys($affiches), array_keys($deduits)))];     // sur le site, pas dans la recette : recette incomplète ou mention de précaution
}

/* ---------- Statistiques ---------- */
function statistiques(): array {
    $s = [];
    $s['mois'] = lignes("SELECT substr(remise_le, 1, 7) AS mois, COUNT(*) AS n, SUM(COALESCE(prix_final, total_indicatif)) AS ca FROM commandes WHERE statut = 'remise' GROUP BY mois ORDER BY mois DESC LIMIT 24");
    foreach ($s['mois'] as &$m) $m['panier'] = $m['n'] ? intdiv((int)$m['ca'], (int)$m['n']) : 0;
    unset($m);
    $s['canaux'] = lignes("SELECT canal, COUNT(*) AS n, SUM(CASE WHEN statut = 'remise' THEN 1 ELSE 0 END) AS remises, SUM(CASE WHEN statut = 'remise' THEN COALESCE(prix_final, total_indicatif) ELSE 0 END) AS ca FROM commandes GROUP BY canal ORDER BY n DESC");
    $s['statuts'] = lignes("SELECT statut, COUNT(*) AS n FROM commandes GROUP BY statut");
    $prod = [];
    foreach (lignes("SELECT lignes FROM commandes WHERE statut = 'remise'") as $k) {
        foreach (json_decode($k['lignes'], true) ?: [] as $l) {
            $pid = explode('|', (string)($l['v'] ?? ''))[0] ?: $l['nom'];
            $prod[$pid] ??= ['nom' => $l['nom'], 'q' => 0, 'ca' => 0];
            $prod[$pid]['q'] += (int)$l['q']; $prod[$pid]['ca'] += (int)$l['q'] * (int)$l['prix'];
        }
    }
    uasort($prod, fn($a, $b) => $b['ca'] <=> $a['ca']);
    $s['produits'] = $prod;
    $t = ligne("SELECT COUNT(*) AS n, SUM(CASE WHEN statut = 'remise' THEN 1 ELSE 0 END) AS remises, SUM(CASE WHEN statut IN ('sans_suite','annulee') THEN 1 ELSE 0 END) AS perdues FROM commandes") ?? [];
    $s['total'] = ['n' => (int)($t['n'] ?? 0), 'remises' => (int)($t['remises'] ?? 0), 'perdues' => (int)($t['perdues'] ?? 0)];
    $d = lignes("SELECT k.recue_le, MIN(e.le) AS repondu FROM commandes k JOIN evenements e ON e.commande_id = k.id AND e.type = 'statut' AND e.nouveau = 'proposition' WHERE k.canal = 'site' GROUP BY k.id");
    $h = [];
    foreach ($d as $x) $h[] = (strtotime($x['repondu']) - strtotime($x['recue_le'])) / 3600;
    sort($h);
    $s['reponse'] = ['n' => count($h), 'mediane' => $h ? $h[intdiv(count($h), 2)] : null, 'sous_24h' => $h ? count(array_filter($h, fn($x) => $x <= 24)) : 0];
    $s['clients'] = ligne("SELECT COUNT(*) AS n, SUM(CASE WHEN type = 'professionnel' THEN 1 ELSE 0 END) AS pros FROM clients WHERE statut <> 'efface'") ?? ['n' => 0, 'pros' => 0];
    $s['fideles'] = (int)val("SELECT COUNT(*) FROM (SELECT client_id FROM commandes WHERE statut = 'remise' GROUP BY client_id HAVING COUNT(*) >= 2)");
    return $s;
}

/* ---------- Conservation (RGPD) ---------- */
/** Ce que la purge effacerait maintenant, sans rien faire. */
function conservation_apercu(): array {
    $limiteDemandes = date('Y-m-d H:i:s', strtotime('-' . GESTION_PURGE_DEMANDES_MOIS . ' months'));
    $limiteClients = date('Y-m-d H:i:s', strtotime('-' . GESTION_PURGE_CLIENTS_ANS . ' years'));
    $demandes = lignes("SELECT id, reference, statut, dernier_echange, client_id FROM commandes WHERE efface_le IS NULL AND statut IN ('demande', 'proposition', 'sans_suite', 'annulee') AND dernier_echange < ? ORDER BY dernier_echange", [$limiteDemandes]);
    $clients = lignes("SELECT c.id, c.numero, c.dernier_echange FROM clients c WHERE c.statut <> 'efface' AND c.dernier_echange < ?
                       AND NOT EXISTS (SELECT 1 FROM commandes k WHERE k.client_id = c.id AND (k.statut IN ('demande','proposition','confirmee') OR k.dernier_echange >= ?)) ORDER BY c.dernier_echange", [$limiteClients, $limiteClients]);
    // un client dont toutes les demandes sont effacées et qui n'a jamais commandé n'a plus de raison d'être gardé
    $orphelins = lignes("SELECT c.id, c.numero, c.dernier_echange FROM clients c WHERE c.statut <> 'efface' AND c.dernier_echange < ?
                         AND NOT EXISTS (SELECT 1 FROM commandes k WHERE k.client_id = c.id AND k.statut IN ('demande','proposition','confirmee','remise'))", [$limiteDemandes]);
    $journal = (int)val("SELECT COUNT(*) FROM connexions WHERE le < ?", [date('Y-m-d H:i:s', strtotime('-' . GESTION_JOURNAL_MOIS . ' months'))]);
    return ['demandes' => $demandes, 'clients' => $clients, 'orphelins' => $orphelins, 'journal' => $journal, 'jetons' => (int)val("SELECT COUNT(*) FROM jetons WHERE expire_le < ? OR utilise_le IS NOT NULL", [maintenant()])];
}
/** Exécute la purge ; renvoie le compte rendu. */
function conservation_executer(?string $auteur = null): array {
    $a = conservation_apercu();
    $now = maintenant();
    foreach ($a['demandes'] as $k) commande_anonymiser((int)$k['id'], $now);
    $vus = [];
    foreach (array_merge($a['clients'], $a['orphelins']) as $c) { if (isset($vus[$c['id']])) continue; $vus[$c['id']] = true; client_effacer((int)$c['id'], 'fin de conservation', $auteur ?? 'système'); }
    q("DELETE FROM connexions WHERE le < ?", [date('Y-m-d H:i:s', strtotime('-' . GESTION_JOURNAL_MOIS . ' months'))]);
    q("DELETE FROM jetons WHERE expire_le < ? OR utilise_le IS NOT NULL", [$now]);
    $cr = ['demandes' => count($a['demandes']), 'clients' => count($vus), 'journal' => $a['journal'], 'le' => $now];
    parametre_ecrire('derniere_purge', json_encode($cr));
    if ($cr['demandes'] || $cr['clients'] || $cr['journal']) evenement('conservation', 'Purge : ' . $cr['demandes'] . ' demande(s) anonymisée(s), ' . $cr['clients'] . ' client(s) effacé(s), ' . $cr['journal'] . ' ligne(s) de journal', [], '', '', $auteur ?? 'système');
    return $cr;
}
/** Références des demandes du site sans suite depuis plus de 11 mois : à supprimer dans contact@ (P-02). */
function conservation_references_emails(): array {
    $l = date('Y-m-d H:i:s', strtotime('-11 months'));
    return array_column(lignes("SELECT reference FROM commandes WHERE canal = 'site' AND statut IN ('sans_suite', 'annulee', 'demande', 'proposition') AND dernier_echange < ? ORDER BY reference", [$l]), 'reference');
}

/* ---------- Exports ---------- */
function csv(array $lignes, array $entetes): string {
    $f = fopen('php://temp', 'r+');
    fwrite($f, "\u{FEFF}");   // BOM : Excel lit l'UTF-8
    fputcsv($f, $entetes, ';', '"', '');
    foreach ($lignes as $l) fputcsv($f, $l, ';', '"', '');
    rewind($f);
    return stream_get_contents($f) ?: '';
}
/** Miroir pour le Drive (Mathieu, Nestor, Claude) : réduit au nécessaire, sans email, téléphone ni textes libres. */
function miroir_commandes(): array {
    $out = [];
    foreach (lignes("SELECT k.*, c.numero AS cn, c.nom, c.prenom, c.societe, c.type AS ct, c.statut AS cs FROM commandes k JOIN clients c ON c.id = k.client_id ORDER BY k.recue_le DESC") as $k) {
        $lignes = json_decode($k['lignes'], true) ?: [];
        $out[] = [$k['reference'], substr($k['recue_le'], 0, 10), CANAUX[$k['canal']] ?? $k['canal'], TYPES_COMMANDE[$k['type']] ?? $k['type'], $k['cn'],
                  client_nom(['statut' => $k['cs'], 'societe' => $k['societe'], 'prenom' => $k['prenom'], 'nom' => $k['nom'], 'type' => $k['ct'], 'numero' => $k['cn']]),
                  $k['retrait_date'] ?? '', STATUTS[$k['statut']] ?? $k['statut'], PAIEMENTS[$k['paiement']] ?? $k['paiement'], MOYENS[$k['moyen']] ?? '',
                  number_format($k['total_indicatif'] / 100, 2, ',', ''), $k['prix_final'] === null ? '' : number_format($k['prix_final'] / 100, 2, ',', ''),
                  number_format($k['acompte'] / 100, 2, ',', ''), $k['facture'], $k['probleme'] ? 'Oui' : '', substr($k['dernier_echange'], 0, 10),
                  $k['remise_le'] ? substr($k['remise_le'], 0, 10) : '', $k['annulee_le'] ? substr($k['annulee_le'], 0, 10) : '', $k['sans_suite_le'] ? substr($k['sans_suite_le'], 0, 10) : '',
                  implode(' ; ', array_map(fn($l) => $l['q'] . ' x ' . $l['nom'] . ' ' . $l['libelle'], $lignes)), $k['efface_le'] ? 'Oui' : ''];
    }
    return $out;
}
const MIROIR_COMMANDES_ENTETES = ['Référence', 'Reçue le', 'Canal', 'Type', 'Client', 'Nom', 'Retrait', 'Statut', 'Paiement', 'Moyen', 'Total indicatif', 'Prix final', 'Acompte', 'Facture', 'Problème', 'Dernier échange', 'Remise le', 'Annulée le', 'Sans suite le', 'Gâteaux', 'Effacée'];
function miroir_clients(): array {
    $out = [];
    foreach (clients_lister('', 'tous') as $c) {
        $out[] = [$c['numero'], TYPES_CLIENT[$c['type']] ?? $c['type'], client_nom($c), $c['societe'], $c['ville'], CANAUX[$c['canal']] ?? $c['canal'],
                  substr($c['premier_contact'], 0, 10), substr($c['dernier_echange'], 0, 10), (int)$c['remises'], number_format(((int)$c['total']) / 100, 2, ',', ''),
                  $c['derniere'] ?? '', STATUTS_CLIENT[$c['statut']] ?? $c['statut'], $c['qonto'] !== '' ? 'Oui' : '', $c['avis_demande_le'] ?? '', $c['avis_recu_le'] ?? '', $c['lettre_info']];
    }
    return $out;
}
const MIROIR_CLIENTS_ENTETES = ['N°', 'Type', 'Nom', 'Société', 'Ville', 'Canal', 'Premier contact', 'Dernier échange', 'Commandes remises', 'Total TTC', 'Dernière commande', 'Statut', 'Dans Qonto', 'Avis demandé le', 'Avis reçu le', 'Lettre d\'information'];

/* ---------- Reprise de données (import d'un fichier JSON préparé hors du dépôt public) ---------- */
/**
 * Format : {"clients": [{"cle", "type", "nom", "prenom", "societe", "contact", "email", "telephone", "ville", "canal", "premier_contact", "qonto", "commentaire"}],
 *           "commandes": [{"reference", "client" (cle), "canal", "type", "statut", "recue_le", "retrait_date", "retrait_moment", "retrait_heure",
 *                          "lignes": [{"v" ou "nom"+"libelle", "q", "prix" (unitaire, centimes), "plaque"}], "perso", "besoin", "prix_final", "acompte",
 *                          "paiement", "moyen", "facture", "remise_le", "commentaire", "origine"}]}
 * Sans risque à relancer : une référence déjà présente est sautée, un client déjà connu (email, Qonto, ou même nom) est repris.
 */
function importer(array $data): array {
    $cr = ['clients_crees' => 0, 'clients_repris' => 0, 'commandes_creees' => 0, 'commandes_sautees' => 0, 'erreurs' => []];
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $cles = [];
        foreach ($data['clients'] ?? [] as $i => $c) {
            if (!is_array($c) || empty($c['cle'])) { $cr['erreurs'][] = 'Client n° ' . ($i + 1) . ' sans clé'; continue; }
            $type = isset(TYPES_CLIENT[$c['type'] ?? '']) ? $c['type'] : 'particulier';
            $email = mb_strtolower(trim((string)($c['email'] ?? '')));
            $e = null;
            if ($email !== '') $e = ligne("SELECT id FROM clients WHERE email = ? AND statut <> 'efface'", [$email]);
            if (!$e && !empty($c['qonto'])) $e = ligne("SELECT id FROM clients WHERE qonto = ? AND statut <> 'efface'", [(string)$c['qonto']]);
            if (!$e && $type === 'professionnel' && !empty($c['societe'])) $e = ligne("SELECT id FROM clients WHERE type = 'professionnel' AND societe = ? AND statut <> 'efface'", [(string)$c['societe']]);
            if (!$e && $type === 'particulier' && !empty($c['nom'])) $e = ligne("SELECT id FROM clients WHERE type = 'particulier' AND nom = ? AND prenom = ? AND statut <> 'efface'", [(string)$c['nom'], (string)($c['prenom'] ?? '')]);
            if ($e) { $cles[$c['cle']] = (int)$e['id']; $cr['clients_repris']++; continue; }
            $quand = date_valide(substr((string)($c['premier_contact'] ?? ''), 0, 10)) ? substr((string)$c['premier_contact'], 0, 10) . ' 12:00:00' : maintenant();
            $id = client_creer(['type' => $type, 'nom' => (string)($c['nom'] ?? ''), 'prenom' => (string)($c['prenom'] ?? ''), 'societe' => (string)($c['societe'] ?? ''),
                                'contact' => (string)($c['contact'] ?? ''), 'email' => $email, 'telephone' => (string)($c['telephone'] ?? ''), 'ville' => (string)($c['ville'] ?? ''),
                                'canal' => isset(CANAUX[$c['canal'] ?? '']) ? $c['canal'] : 'autre', 'premier_contact' => $quand, 'dernier_echange' => $quand, 'qonto' => (string)($c['qonto'] ?? '')]);
            if (!empty($c['commentaire'])) evenement('commentaire', (string)$c['commentaire'], ['client' => $id]);
            $cles[$c['cle']] = $id;
            $cr['clients_crees']++;
        }
        foreach ($data['commandes'] ?? [] as $i => $k) {
            if (!is_array($k) || empty($k['reference']) || !preg_match('/^MC-\d{6}-[0-9A-F]{4}$/', (string)$k['reference'])) { $cr['erreurs'][] = 'Commande n° ' . ($i + 1) . ' : référence absente ou mal écrite'; continue; }
            if (val("SELECT 1 FROM commandes WHERE reference = ?", [$k['reference']])) { $cr['commandes_sautees']++; continue; }
            $cid = $cles[$k['client'] ?? ''] ?? null;
            if (!$cid) { $cr['erreurs'][] = $k['reference'] . ' : client « ' . ($k['client'] ?? '') . ' » inconnu'; continue; }
            $recue = (string)($k['recue_le'] ?? '');
            $recue = date_valide(substr($recue, 0, 10)) ? (strlen($recue) > 10 ? substr($recue, 0, 19) : $recue . ' 12:00:00') : maintenant();
            $statut = isset(STATUTS[$k['statut'] ?? '']) ? $k['statut'] : 'remise';
            $id = commande_creer(['reference' => $k['reference'], 'client_id' => $cid, 'canal' => isset(CANAUX[$k['canal'] ?? '']) ? $k['canal'] : 'autre',
                                  'type' => isset(TYPES_COMMANDE[$k['type'] ?? '']) ? $k['type'] : null, 'statut' => $statut,
                                  'retrait_date' => date_valide((string)($k['retrait_date'] ?? '')) ? $k['retrait_date'] : null,
                                  'retrait_moment' => isset(MOMENTS[$k['retrait_moment'] ?? '']) ? $k['retrait_moment'] : '',
                                  'retrait_heure' => preg_match('/^\d{2}:\d{2}$/', (string)($k['retrait_heure'] ?? '')) ? $k['retrait_heure'] : '',
                                  'lignes' => is_array($k['lignes'] ?? null) ? $k['lignes'] : [], 'perso' => (string)($k['perso'] ?? ''), 'besoin' => (string)($k['besoin'] ?? ''),
                                  'recue_le' => $recue, 'origine' => (string)($k['origine'] ?? 'Reprise des données')]);
            $moyen = (string)($k['moyen'] ?? '');
            $maj = ['paiement' => isset(PAIEMENTS[$k['paiement'] ?? '']) ? $k['paiement'] : 'aucun', 'moyen' => isset(MOYENS[$moyen]) ? $moyen : '',
                    'facture' => (string)($k['facture'] ?? ''), 'acompte' => (int)($k['acompte'] ?? 0), 'prix_final' => isset($k['prix_final']) ? (int)$k['prix_final'] : null];
            $fin = (string)($k['remise_le'] ?? '');
            $fin = date_valide(substr($fin, 0, 10)) ? substr($fin, 0, 10) . ' 12:00:00' : $recue;
            if ($statut === 'remise') $maj['remise_le'] = $fin;
            if ($statut === 'annulee') $maj['annulee_le'] = $fin;
            if ($statut === 'sans_suite') $maj['sans_suite_le'] = $fin;
            $maj['dernier_echange'] = max($recue, $fin);
            $set = implode(', ', array_map(fn($c) => "$c = :$c", array_keys($maj)));
            q("UPDATE commandes SET $set WHERE id = :id", $maj + ['id' => $id]);
            client_echange($cid, $maj['dernier_echange']);
            if (!empty($k['commentaire'])) evenement('commentaire', (string)$k['commentaire'], ['commande' => $id, 'client' => $cid]);
            $cr['commandes_creees']++;
        }
        $pdo->commit();
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    evenement('import', 'Reprise : ' . $cr['clients_crees'] . ' client(s) créé(s), ' . $cr['clients_repris'] . ' repris, ' . $cr['commandes_creees'] . ' commande(s) créée(s), ' . $cr['commandes_sautees'] . ' déjà présente(s)');
    return $cr;
}
