<?php
/*
 * Madame Cho : espace de gestion. Les factures des particuliers (spécification 003, demande d'Olivier du 08/11/2026) : la gestion
 * prépare la facture d'une commande payée et remise, Youngmi la relit et l'émet d'un toucher ; le numéro est donné à l'émission dans
 * une série propre (P-AAAA-NNNN, provisoire tant que l'expert-comptable n'a pas répondu), le PDF est figé en base avec son empreinte,
 * et chaque document est lié au précédent (chaîne) pour qu'une retouche se voie. Une erreur se corrige par un avoir, jamais en
 * réécrivant. La gestion n'encaisse rien : ce n'est pas une caisse (choix d'Olivier « 2 » ; Tap to Pay et livre des recettes à part).
 * Les restaurants restent facturés dans Qonto (relevé du mois). Rien n'est émis ni envoyé sans le toucher d'une personne (R-01).
 */
declare(strict_types=1);
namespace MadameCho\Gestion;

require_once __DIR__ . '/pdf.php';

const FACTURE_TVA_DEFAUT = 55;          // pâtisserie à emporter : 5,5 % (en dixièmes)
const FACTURE_LIEN_MOIS = 12;           // le lien privé d'une facture s'éteint 12 mois après l'émission (le client garde le PDF)
const FACTURE_CONSERVATION_ANS = 10;    // obligation de conservation des factures (FR-014)
const FACTURE_CONTROLE_MIN = 20;        // phase de contrôle : au moins 20 factures vérifiées avant de parler d'automatique (SC-007)
const FACTURE_A_FAIRE_JOURS = 2;        // payée et remise depuis 2 jours sans facture : signalée à l'accueil (FR-010)
const FACTURE_COLONNES = 'id, genre, numero, prefixe, annee, seq, commande_id, client_id, facture_id, emise_le, emise_par, total_ttc, total_ht, total_tva, donnees, empreinte, chaine, jeton, motif, verifiee_le, verifiee_par';
/** Comment une facture a été remise au client (FR-009). */
const REMISE_CANAUX = ['email' => 'Mail avec le PDF', 'sms' => 'Lien par SMS', 'whatsapp' => 'Lien par WhatsApp', 'qr' => 'QR code montré', 'papier' => 'Sur papier', 'autre' => 'Autrement'];
/** L'identité de Madame Cho sur les factures : saisie dans Plus › Factures (FR-002 : jamais écrite dans le code) ; la suggestion reprend les mentions légales du site. */
const VENDEUR_CHAMPS = ['nom' => 'Raison sociale', 'forme' => 'Forme et capital', 'adresse' => 'Adresse du siège', 'siren' => 'SIREN', 'rcs' => 'Ville du RCS',
                        'tva' => 'N° de TVA intracommunautaire', 'tel' => 'Téléphone', 'email' => 'Email', 'site' => 'Site'];
const VENDEUR_OBLIGATOIRES = ['nom', 'forme', 'adresse', 'siren', 'rcs', 'tva'];
const VENDEUR_SUGGESTION = ['nom' => 'MADAME CHO', 'forme' => 'SASU au capital de 1 000 €', 'adresse' => '4 avenue du Château, 92190 Meudon', 'siren' => '941 761 918',
                            'rcs' => 'Nanterre', 'tva' => 'FR37941761918', 'tel' => '06 61 31 17 79', 'email' => 'contact@madamecho.com', 'site' => 'madamecho.com'];
const MOYENS_FACTURE = ['carte' => 'carte bancaire', 'virement' => 'virement', 'especes' => 'espèces', 'autre' => 'autre moyen'];

/* ---------- Réglages ---------- */
function vendeur(): ?array {
    $v = json_decode((string)parametre('vendeur', ''), true);
    if (!is_array($v)) return null;
    foreach (VENDEUR_OBLIGATOIRES as $c) if (trim((string)($v[$c] ?? '')) === '') return null;
    return array_map('strval', array_intersect_key($v + array_fill_keys(array_keys(VENDEUR_CHAMPS), ''), VENDEUR_CHAMPS));
}
/** La phase de contrôle dure tant que moins de 20 factures ont été vérifiées : pendant ce temps, rien ne se remet avant « J'ai vérifié » (constat 5). */
function facture_controle_actif(): bool { return (int)val("SELECT COUNT(*) FROM factures WHERE genre = 'facture' AND verifiee_le IS NOT NULL") < FACTURE_CONTROLE_MIN; }
/** À remettre seulement après vérification ? (phase de contrôle, document pas encore vérifié) */
function facture_attend_verification(array $f): bool { return !$f['verifiee_le'] && facture_controle_actif(); }
/** SIREN : neuf chiffres et la clé de Luhn (audit n° 6, constat 9). */
function siren_valide(string $s): bool {
    $s = (string)preg_replace('/\s+/u', '', $s);
    if (!preg_match('/^\d{9}$/', $s)) return false;
    $t = 0;
    for ($i = 0; $i < 9; $i++) { $n = (int)$s[$i] * ($i % 2 ? 2 : 1); $t += $n > 9 ? $n - 9 : $n; }
    return $t % 10 === 0;
}
/** Numéro de TVA français : FR, la clé calculée sur le SIREN, puis le SIREN lui-même. */
function tva_fr_valide(string $tva, string $siren): bool {
    $tva = strtoupper((string)preg_replace('/\s+/u', '', $tva)); $siren = (string)preg_replace('/\s+/u', '', $siren);
    return (bool)preg_match('/^FR(\d{2})(\d{9})$/', $tva, $m) && $m[2] === $siren && (int)$m[1] === (12 + 3 * ((int)$siren % 97)) % 97;
}
function facture_prefixe(string $genre): string {
    $p = (string)parametre($genre === 'avoir' ? 'avoir_prefixe' : 'facture_prefixe', '');
    return $p !== '' ? $p : ($genre === 'avoir' ? 'AV-' : 'P-');
}
/** Un préfixe ne change plus dès qu'un document de l'année porte l'ancien (la série resterait continue, mais on ne joue pas avec). */
function facture_prefixes_figes(): bool { return (bool)val("SELECT 1 FROM factures WHERE annee = ? LIMIT 1", [(int)date('Y')]); }

/* ---------- Lecture ---------- */
function facture(int $id, bool $avecPdf = false): ?array {
    $f = ligne("SELECT " . ($avecPdf ? '*' : FACTURE_COLONNES) . " FROM factures WHERE id = ?", [$id]);
    if ($f) $f['d'] = json_decode($f['donnees'], true) ?: [];
    return $f;
}
function factures_de_commande(int $commandeId): array {
    return array_map(function ($f) { $f['d'] = json_decode($f['donnees'], true) ?: []; return $f; },
                     lignes("SELECT " . FACTURE_COLONNES . " FROM factures WHERE commande_id = ? ORDER BY id", [$commandeId]));
}
function facture_avoirs(int $factureId): array { return lignes("SELECT " . FACTURE_COLONNES . " FROM factures WHERE genre = 'avoir' AND facture_id = ? ORDER BY id", [$factureId]); }
/** Ce qui reste de la facture après ses avoirs (en centimes TTC). */
function facture_reste(array $f): int { return (int)$f['total_ttc'] + (int)val("SELECT COALESCE(SUM(total_ttc), 0) FROM factures WHERE genre = 'avoir' AND facture_id = ?", [(int)$f['id']]); }
/** Ce qui reste d'une facture taux par taux (TTC en centimes, avoirs déduits) : un avoir partiel se fait à un taux de la facture. */
function facture_reste_par_taux(array $f): array {
    $r = [];
    $d = $f['d'] ?? (json_decode((string)$f['donnees'], true) ?: []);
    foreach (($d['par_taux'] ?? []) as $x) $r[(int)$x['taux']] = (int)$x['ttc'];
    foreach (facture_avoirs((int)$f['id']) as $av) foreach ((json_decode((string)$av['donnees'], true) ?: [])['par_taux'] ?? [] as $x) $r[(int)$x['taux']] = ($r[(int)$x['taux']] ?? 0) + (int)$x['ttc'];
    return $r;
}
/** La facture en vigueur d'une commande : la dernière qui n'est pas entièrement annulée par des avoirs. */
function facture_en_vigueur(int $commandeId): ?array {
    foreach (array_reverse(factures_de_commande($commandeId)) as $f) if ($f['genre'] === 'facture' && facture_reste($f) > 0) return $f;
    return null;
}
function facture_remises(int $factureId): array { return lignes("SELECT * FROM factures_remises WHERE facture_id = ? ORDER BY id", [$factureId]); }
/** Une commande de particulier (les restaurants restent facturés dans Qonto). */
function facture_concernee(array $k): bool { return !commande_est_pro($k); }

/**
 * Où en est la facture d'une commande : pro, aucune (annulée, sans suite), non_demandee (le client n'a pas demandé de facture),
 * pas_payee, pas_remise, a_emettre (avec ce qui manque), emise (et a_corriger si le montant de la commande a changé depuis).
 */
function facture_etat(array $k): array {
    if (!facture_concernee($k)) return ['etat' => 'pro'];
    $f = facture_en_vigueur((int)$k['id']);
    if ($f) {
        $reste = facture_reste($f);
        // À corriger : le montant de la commande a changé depuis l'émission, ou la commande a été annulée (un avoir est à faire)
        return ['etat' => 'emise', 'facture' => $f, 'reste' => $reste, 'a_corriger' => $reste !== (in_array($k['statut'], ['annulee', 'sans_suite'], true) || $k['paiement'] === 'rembourse' ? 0 : montant_commande($k))];
    }
    if (in_array($k['statut'], ['annulee', 'sans_suite'], true)) return ['etat' => 'aucune'];
    // Une facture seulement si le client la demande (décision d'Olivier du 09/11/2026, après la réponse de l'expert-comptable)
    if (empty($k['facture_demandee'])) return ['etat' => 'non_demandee'];
    if ($k['paiement'] !== 'paye') return ['etat' => 'pas_payee'];
    if ($k['statut'] !== 'remise') return ['etat' => 'pas_remise'];
    $manque = [];
    if (!vendeur()) $manque[] = 'identite';
    if (montant_commande($k) <= 0) $manque[] = 'montant';
    if (!isset(MOYENS_FACTURE[$k['moyen']])) $manque[] = 'moyen';
    $qonto = trim((string)$k['facture']);
    return ['etat' => 'a_emettre', 'manque' => $manque, 'qonto' => $qonto !== '' && !str_starts_with($qonto, facture_prefixe('facture')) ? $qonto : '',
            'avoir' => (bool)factures_de_commande((int)$k['id'])];
}
const FACTURE_MANQUES = ['identite' => 'L\'identité de Madame Cho n\'est pas encore enregistrée (Plus › Factures, en bas).',
                         'montant' => 'Le prix final n\'est pas fixé (Montant et paiement).',
                         'moyen' => 'Le moyen de paiement n\'est pas noté (Montant et paiement › Modifier le paiement).'];

/* ---------- Préparation ---------- */
/** « 1er novembre 2026 », « 8 novembre 2026 » : la date écrite sur la facture. */
function date_facture(?string $d): string { return preg_replace('/^1 /', '1er ', date_fr($d, 'longue')) ?? ''; }
function montant_facture(int $c): string {
    return ($c < 0 ? '-' : '') . number_format(abs($c) / 100, 2, ',', "\u{A0}") . "\u{A0}€";
}
/**
 * Le prix unitaire HT écrit sur la facture : au centime si la ligne tombe juste, sinon avec les décimales utiles (quatre au plus), pour que
 * quantité × prix unitaire HT redonne le total HT de la ligne (6 × 3,3175 € = 19,91 €, pas 6 × 3,32 € ; audit n° 6, constat 12).
 */
function pu_ht_facture(array $l): string {
    // Au centime quand quantité × prix unitaire HT tombe déjà juste (une seule pièce, une remise, 10 × 2,37 €)
    if (isset($l['q'], $l['pu_ht'], $l['total_ht']) && (int)$l['q'] * (int)$l['pu_ht'] === (int)$l['total_ht']) return montant_facture((int)$l['pu_ht']);
    $ttc = (int)$l['pu_ttc']; $t = (int)$l['tva'];
    $n = intdiv(abs($ttc) * 100000 * 2 + (1000 + $t), (1000 + $t) * 2);   // dix-millièmes d'euro, arrondis
    $s = (string)preg_replace('/(,\d{2}\d*?)0+$/', '$1', number_format($n / 10000, 4, ',', "\u{A0}"));
    return ($ttc < 0 ? '-' : '') . $s . "\u{A0}€";
}
function taux_facture(int $t): string { return str_replace('.', ',', rtrim(rtrim(number_format($t / 10, 1, '.', ''), '0'), '.')) . "\u{A0}%"; }
/** Les lignes de la facture, depuis la commande telle qu'elle est enregistrée (prix gardés à la commande, jamais la carte du jour). */
function facture_lignes(array $k): array {
    $out = []; $somme = 0;
    foreach ($k['lignes_tab'] as $x) {
        $tva = isset($x['tva']) ? (int)$x['tva'] : FACTURE_TVA_DEFAUT;
        $q = max(1, (int)$x['q']); $pu = (int)$x['prix'];
        // Une plaque écrite en coréen (ou avec un émoji) ne s'écrit pas dans le PDF : « plaque personnalisée » à la place
        $plaque = trim((string)($x['plaque'] ?? ''));
        $des = $x['nom'] . (($x['libelle'] ?? '') !== '' ? ', ' . $x['libelle'] : '') . ($plaque !== '' ? (Pdf::ecrivable($plaque) ? ' (plaque « ' . $plaque . ' »)' : ' (plaque personnalisée)') : '');
        $out[] = ['designation' => une_ligne($des), 'q' => $q, 'pu_ttc' => $pu, 'tva' => $tva, 'total_ttc' => $pu * $q];
        $somme += $pu * $q;
    }
    $montant = montant_commande($k);
    $perso = trim(une_ligne((string)$k['perso']));
    if (!Pdf::ecrivable($perso)) $perso = '';   // texte libre en coréen : la ligne garde son libellé, sans le texte
    if (!$out) $out[] = ['designation' => 'Création personnalisée' . ($perso !== '' ? ' : ' . mb_substr($perso, 0, 160) : ''), 'q' => 1, 'pu_ttc' => $montant, 'tva' => FACTURE_TVA_DEFAUT, 'total_ttc' => $montant];
    elseif ($montant !== $somme) {
        $diff = $montant - $somme;
        $out[] = ['designation' => $diff < 0 ? 'Remise accordée' : ($perso !== '' ? 'Personnalisation : ' . mb_substr($perso, 0, 140) : 'Supplément convenu'), 'q' => 1, 'pu_ttc' => $diff, 'tva' => FACTURE_TVA_DEFAUT, 'total_ttc' => $diff];
    }
    foreach ($out as &$l) { $l['pu_ht'] = ht_de_ttc($l['pu_ttc'], $l['tva']); $l['total_ht'] = ht_de_ttc($l['total_ttc'], $l['tva']); }
    return $out;
}
/** HT et TVA par taux, calculés sur le TTC de chaque taux (vente à des particuliers, prix affichés TTC). */
function facture_totaux(array $lignes): array {
    $par = [];
    foreach ($lignes as $l) $par[$l['tva']] = ($par[$l['tva']] ?? 0) + $l['total_ttc'];
    ksort($par);
    $tva = []; $t = ['ht' => 0, 'tva' => 0, 'ttc' => 0];
    foreach ($par as $taux => $ttc) {
        $ht = ht_de_ttc($ttc, (int)$taux);
        $tva[] = ['taux' => (int)$taux, 'ht' => $ht, 'tva' => $ttc - $ht, 'ttc' => $ttc];
        $t['ht'] += $ht; $t['tva'] += $ttc - $ht; $t['ttc'] += $ttc;
    }
    return ['par_taux' => $tva, 'totaux' => $t];
}
/**
 * Le nom du client sur la facture : prénom et nom de la fiche, ou le nom écrit à l'émission (un nom en coréen s'écrit en lettres
 * latines : la facture est en français et le PDF ne sait pas écrire le hangeul). Prénom, nom et adresse sont obligatoires
 * (réponse de l'expert-comptable du 09/11/2026 : plus de « Client particulier »).
 */
function facture_nom_client(array $k, string $ecrit = ''): string {
    return trim($ecrit) !== '' ? trim($ecrit) : trim(trim((string)$k['client_prenom']) . ' ' . trim((string)$k['client_nom']));
}
/** Prénom et nom : deux mots au moins (« Sophie Laurent », « Kim Minji »). */
function facture_nom_complet(string $n): bool { return count(preg_split('/\s+/u', trim($n), -1, PREG_SPLIT_NO_EMPTY) ?: []) >= 2; }
/**
 * L'acompte d'une commande : quand il a été noté « reçu », et par quel moyen (lus dans l'historique de la commande). Vide quand il a
 * été noté en même temps que le paiement complet : la facture dit alors « versé à la commande ».
 */
function facture_acompte_info(int $commandeId): array {
    $e = ligne("SELECT le, texte FROM evenements WHERE commande_id = ? AND type = 'paiement' AND nouveau = 'acompte_recu' ORDER BY id DESC LIMIT 1", [$commandeId]);
    if (!$e) return ['', ''];
    $moyen = '';
    foreach (MOYENS as $cle => $lib) if ($cle !== '' && str_contains((string)$e['texte'], ' · ' . $lib)) { $moyen = MOYENS_FACTURE[$cle] ?? ''; break; }
    return [substr((string)$e['le'], 0, 10), $moyen];
}
/** Tout ce que la facture dira, figé au moment de l'émission (le JSON est gardé avec le PDF). $o : nom, adresse (sinon celle de la fiche). */
function facture_donnees(array $k, array $o = []): array {
    $lignes = facture_lignes($k);
    $vente = $k['remise_le'] ? substr((string)$k['remise_le'], 0, 10) : ((string)$k['retrait_date'] !== '' ? (string)$k['retrait_date'] : aujourdhui());
    $paye = $k['paiement_le'] ? substr((string)$k['paiement_le'], 0, 10) : $vente;
    $adresse = trim((string)($o['adresse'] ?? '')) !== '' ? trim((string)$o['adresse']) : trim((string)($k['client_adresse'] ?? ''));
    $t = facture_totaux($lignes);
    // L'acompte et le solde, chacun avec sa date (réponse de l'expert-comptable du 09/11/2026 : une facture finale qui montre les deux)
    $pa = ['le' => $paye, 'moyen' => MOYENS_FACTURE[$k['moyen']] ?? '', 'acompte' => (int)$k['acompte']];
    if ((int)$k['acompte'] > 0) {
        [$pa['acompte_le'], $pa['acompte_moyen']] = facture_acompte_info((int)$k['id']);
        $pa['solde'] = $t['totaux']['ttc'] - (int)$k['acompte'];
    }
    return ['v' => 1, 'genre' => 'facture', 'vendeur' => vendeur() ?? VENDEUR_SUGGESTION,
            'client' => ['nom' => facture_nom_client($k, mb_substr((string)($o['nom'] ?? ''), 0, 120)), 'adresse' => mb_substr($adresse, 0, 200)],
            'commande' => ['id' => (int)$k['id'], 'reference' => (string)$k['reference']],
            'date_vente' => $vente, 'lignes' => $lignes] + $t + ['paiement' => $pa];
}

/**
 * Le règlement écrit sur la facture. Sans acompte : « Facture acquittée : réglée le … par … ». Avec un acompte : l'acompte et le solde,
 * chacun avec son montant, sa date et son moyen quand ils sont connus (réponse de l'expert-comptable du 09/11/2026).
 */
function facture_reglement_lignes(array $pa): array {
    $par = fn(string $m): string => $m !== '' ? ' par ' . $m : '';
    if ((int)($pa['acompte'] ?? 0) <= 0) return ['Facture acquittée : réglée le ' . date_facture($pa['le']) . $par((string)$pa['moyen']) . '.'];
    $l = ['Facture acquittée.'];
    $l[] = ((string)($pa['acompte_le'] ?? '') !== '' ? 'Acompte versé le ' . date_facture($pa['acompte_le']) . $par((string)($pa['acompte_moyen'] ?? '')) : 'Acompte versé à la commande')
         . ' : ' . montant_facture((int)$pa['acompte']);
    if (isset($pa['solde'])) $l[] = ((int)$pa['solde'] > 0 ? 'Solde réglé le ' . date_facture($pa['le']) . $par((string)$pa['moyen']) . ' : ' . montant_facture((int)$pa['solde'])
                                                           : 'Solde : ' . montant_facture(0) . ' (l\'acompte règle toute la facture)');
    return $l;
}

/**
 * Le client veut une facture (ou n'en veut plus, tant qu'aucune n'est émise). L'adresse donnée en même temps va sur sa fiche.
 * Renvoie '' ou un message d'erreur.
 */
function facture_demande_noter(int $commandeId, bool $oui, string $adresse = ''): string {
    $k = commande($commandeId);
    if (!$k || !facture_concernee($k)) return 'Commande introuvable.';
    if (!$oui && facture_en_vigueur($commandeId)) return 'Une facture est déjà émise pour cette commande : pour l\'annuler, faites un avoir.';
    $adresse = mb_substr(trim(une_ligne($adresse)), 0, 200);
    if ($adresse !== '' && !Pdf::ecrivable($adresse)) return 'L\'adresse contient des caractères que la facture ne sait pas écrire (coréen, émoji…) : écrivez-la en lettres latines.';
    if ((int)$k['facture_demandee'] !== (int)$oui) {
        q("UPDATE commandes SET facture_demandee = ?, maj_le = ? WHERE id = ?", [(int)$oui, maintenant(), $commandeId]);
        evenement('facture', $oui ? 'Le client veut une facture' : 'Finalement pas de facture', ['commande' => $commandeId, 'client' => (int)$k['client_id']]);
    }
    if ($oui && $adresse !== '' && $adresse !== trim((string)($k['client_adresse'] ?? ''))) client_modifier((int)$k['client_id'], ['adresse' => $adresse]);
    return '';
}

/* ---------- Émission ---------- */
/**
 * La chaîne : chaque document est lié au précédent ; changer, retirer ou intercaler une ligne se voit à la vérification (FR-015).
 * Elle couvre le PDF (son empreinte) et les données figées à côté (client, lignes, vendeur : elles servent au mail ; audit n° 6, constat 6).
 */
function facture_chainer(string $precedente, array $f): string {
    return hash('sha256', implode('|', [$precedente, $f['genre'], $f['numero'], $f['emise_le'], (string)(int)$f['total_ttc'], (string)(int)($f['commande_id'] ?? 0), (string)(int)($f['facture_id'] ?? 0), $f['empreinte'],
                                         hash('sha256', (string)($f['donnees'] ?? ''))]));
}
/** L'empreinte de ce que Youngmi a relu dans l'aperçu : la facture émise doit être celle-là (audit n° 6, constat 4). */
function facture_empreinte_apercu(array $d): string { return hash('sha256', json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); }
/**
 * Émet un document (facture ou avoir) : numéro suivant de la série dans une transaction exclusive (deux touchers en même temps ne
 * donnent ni deux numéros pour une commande ni de trou), PDF figé, empreinte, chaîne, sceau. Renvoie la ligne, ou ['erreur' => …].
 * $preparer : relit tout DANS la transaction et renvoie les données du document, ou ['erreur' => message] (audit n° 6, constat 4 :
 * rien de ce qui a pu changer entre l'aperçu et le toucher ne passe sans être revu).
 */
function document_emettre(string $genre, callable $preparer, array $liens, string $motif = ''): array {
    $pdo = db();
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        $d = $preparer();
        if (isset($d['erreur'])) { $pdo->exec('ROLLBACK'); return ['erreur' => (string)$d['erreur']]; }
        $now = maintenant(); $annee = (int)substr($now, 0, 4);
        $prefixe = facture_prefixe($genre);
        $seq = 1 + (int)val("SELECT COALESCE(MAX(seq), 0) FROM factures WHERE prefixe = ? AND annee = ?", [$prefixe, $annee]);
        $numero = sprintf('%s%d-%04d', $prefixe, $annee, $seq);
        $d['numero'] = $numero; $d['emise_le'] = $now;
        $pdf = facture_pdf($d);
        $json = (string)json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $f = ['genre' => $genre, 'numero' => $numero, 'emise_le' => $now, 'total_ttc' => $d['totaux']['ttc'], 'commande_id' => $liens['commande'] ?? null,
              'facture_id' => $liens['facture'] ?? null, 'empreinte' => hash('sha256', $pdf), 'donnees' => $json];
        $prec = (string)(val("SELECT chaine FROM factures ORDER BY id DESC LIMIT 1") ?? '');
        $f['chaine'] = facture_chainer($prec !== '' ? $prec : str_repeat('0', 64), $f);
        $st = $pdo->prepare("INSERT INTO factures (genre, numero, prefixe, annee, seq, commande_id, client_id, facture_id, emise_le, emise_par, total_ttc, total_ht, total_tva,
                             donnees, pdf, empreinte, chaine, jeton, motif) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $st->bindValue(1, $genre); $st->bindValue(2, $numero); $st->bindValue(3, $prefixe); $st->bindValue(4, $annee, \PDO::PARAM_INT); $st->bindValue(5, $seq, \PDO::PARAM_INT);
        $st->bindValue(6, $f['commande_id']); $st->bindValue(7, $liens['client'] ?? null); $st->bindValue(8, $f['facture_id']); $st->bindValue(9, $now); $st->bindValue(10, auteur());
        $st->bindValue(11, $d['totaux']['ttc'], \PDO::PARAM_INT); $st->bindValue(12, $d['totaux']['ht'], \PDO::PARAM_INT); $st->bindValue(13, $d['totaux']['tva'], \PDO::PARAM_INT);
        $st->bindValue(14, $json); $st->bindValue(15, $pdf, \PDO::PARAM_LOB); $st->bindValue(16, $f['empreinte']);
        $st->bindValue(17, $f['chaine']); $st->bindValue(18, bin2hex(random_bytes(12))); $st->bindValue(19, $motif);
        $st->execute();
        $id = (int)$pdo->lastInsertId();
        // Le sceau : combien de documents et la dernière chaîne ; retirer la dernière ligne se voit aussi
        q("INSERT OR REPLACE INTO parametres (cle, valeur) VALUES ('factures_sceau', ?)", [(int)val("SELECT COUNT(*) FROM factures") . '|' . $f['chaine']]);
        if ($genre === 'facture' && !empty($liens['commande'])) q("UPDATE commandes SET facture = ?, maj_le = ? WHERE id = ?", [$numero, $now, (int)$liens['commande']]);
        evenement('facture', ($genre === 'avoir' ? 'Avoir ' : 'Facture ') . $numero . ' émis' . ($genre === 'avoir' ? '' : 'e') . ' (' . euros((int)$d['totaux']['ttc']) . ')'
                  . ($motif !== '' ? ' : ' . $motif : ''), ['commande' => $liens['commande'] ?? null, 'client' => $liens['client'] ?? null], '', $numero);
        $pdo->exec('COMMIT');
        return facture($id);
    } catch (\Throwable $e) {
        try { $pdo->exec('ROLLBACK'); } catch (\Throwable) {}
        throw $e;
    }
}
/** Les données de la facture d'une commande, ou ['erreur' => …] si elle ne peut pas encore s'émettre. $o : nom, adresse, doublon_ok. */
function facture_preparer(array $k, array $o = []): array {
    $e = facture_etat($k);
    if ($e['etat'] === 'emise') return ['erreur' => 'Cette commande a déjà sa facture (' . $e['facture']['numero'] . ').'];
    if ($e['etat'] !== 'a_emettre') return ['erreur' => 'La facture se fait quand la commande est payée et remise.'];
    if ($e['manque']) return ['erreur' => implode(' ', array_map(fn($m) => FACTURE_MANQUES[$m], $e['manque']))];
    if ($e['qonto'] !== '' && empty($o['doublon_ok'])) return ['erreur' => 'Une facture Qonto (' . $e['qonto'] . ') est déjà notée sur la commande : cochez la case pour confirmer que ce n\'est pas un doublon.'];
    $d = facture_donnees($k, $o);
    // Ce que le PDF ne saurait pas écrire (hangeul, émoji) sortirait en « ? » : on le dit avant de donner un numéro
    if (!Pdf::ecrivable($d['client']['nom'])) return ['erreur' => 'Le nom du client est écrit avec des lettres que la facture ne sait pas écrire (coréen, émoji…) : écrivez-le en lettres latines dans « Prénom et nom sur la facture ».'];
    // Prénom, nom et adresse : obligatoires sur la facture (réponse de l'expert-comptable du 09/11/2026)
    if (!facture_nom_complet($d['client']['nom'])) return ['erreur' => 'Le prénom et le nom du client sont obligatoires sur la facture : complétez sa fiche (« Corriger le nom ou les coordonnées »), ou écrivez-les dans « Prénom et nom sur la facture ».'];
    if ($d['client']['adresse'] === '') return ['erreur' => 'L\'adresse postale du client est obligatoire sur la facture : écrivez-la (numéro, rue, code postal et ville).'];
    if (!Pdf::ecrivable($d['client']['adresse'])) return ['erreur' => 'L\'adresse contient des caractères que la facture ne sait pas écrire (coréen, émoji…) : écrivez-la en lettres latines.'];
    if ((int)$d['paiement']['acompte'] > (int)$d['totaux']['ttc']) return ['erreur' => 'L\'acompte noté (' . montant_facture((int)$d['paiement']['acompte']) . ') dépasse le total de la facture (' . montant_facture((int)$d['totaux']['ttc']) . ') : corrigez-le dans Montant et paiement.'];
    foreach ($d['lignes'] as $l) if (!Pdf::ecrivable($l['designation'])) return ['erreur' => 'Un produit de la commande a un nom que la facture ne sait pas écrire (' . $l['designation'] . ') : corrigez-le dans la commande.'];
    return $d;
}
/**
 * Émet la facture d'une commande de particulier, au toucher de Youngmi (FR-006). $o : adresse, nom, doublon_ok, et
 * empreinte (celle de l'aperçu relu : si la commande a changé depuis, rien n'est émis et Youngmi relit la nouvelle version).
 * Une adresse écrite à l'émission est gardée sur la fiche du client, pour ses factures suivantes.
 */
function facture_emettre(int $commandeId, array $o = []): array {
    $k = commande($commandeId);
    if (!$k) return ['erreur' => 'Commande introuvable.'];
    $d = facture_preparer($k, $o);
    if (isset($d['erreur'])) return $d;
    $r = document_emettre('facture', function () use ($commandeId, $o): array {
        if (facture_en_vigueur($commandeId)) return ['erreur' => 'Une facture vient d\'être émise pour cette commande (deux touchers en même temps ?) : rien n\'a été fait en double.'];
        $k = commande($commandeId);   // relue sous le verrou : prix, produits, paiement, remise
        if (!$k) return ['erreur' => 'Commande introuvable.'];
        if ((string)($o['empreinte'] ?? '') !== '' && !hash_equals(facture_empreinte_apercu(facture_donnees($k)), (string)$o['empreinte']))
            return ['erreur' => 'La commande a changé depuis l\'aperçu (produits, prix, paiement ou identité) : relisez la nouvelle version, puis émettez.'];
        return facture_preparer($k, $o);
    }, ['commande' => $commandeId, 'client' => (int)$k['client_id']]);
    $adresse = mb_substr(trim((string)($o['adresse'] ?? '')), 0, 200);
    if (!isset($r['erreur']) && $adresse !== '' && $adresse !== trim((string)($k['client_adresse'] ?? ''))) client_modifier((int)$k['client_id'], ['adresse' => $adresse]);
    return $r;
}
/**
 * Un avoir sur une facture (FR-005) : total (tout le reste de la facture) ou partiel (un montant TTC), avec son motif. Les lignes
 * de l'avoir reprennent celles de la facture en négatif (total), ou une ligne « Correction » par taux de TVA : un avoir partiel se fait
 * au taux choisi parmi ceux de la facture, jamais au premier venu (audit n° 6, constat 3).
 */
function avoir_emettre(int $factureId, string $motif, ?int $montant = null, ?int $taux = null): array {
    $f = facture($factureId);
    if (!$f || $f['genre'] !== 'facture') return ['erreur' => 'Facture introuvable.'];
    $motif = mb_substr(trim(une_ligne($motif)), 0, 200);
    if ($motif === '') return ['erreur' => 'Écrivez la raison de l\'avoir (elle figure sur le document).'];
    if (!Pdf::ecrivable($motif)) return ['erreur' => 'La raison contient des caractères que l\'avoir ne sait pas écrire (coréen, émoji…) : écrivez-la en lettres latines.'];
    $reste = facture_reste($f);
    if ($reste <= 0) return ['erreur' => 'Cette facture est déjà entièrement annulée par un avoir.'];
    $total = $montant === null || $montant >= $reste;
    if (!$total && $montant <= 0) return ['erreur' => 'Le montant de l\'avoir doit être positif.'];
    $preparer = function () use ($factureId, $motif, $montant, $taux, $reste, $total): array {
        $f = facture($factureId);
        if (!$f || facture_reste($f) !== $reste) return ['erreur' => 'Un avoir vient d\'être fait sur cette facture : rien n\'a été fait en double.'];
        $d0 = $f['d'];
        $correction = fn(int $m, int $t) => ['designation' => 'Correction de la facture ' . $f['numero'], 'q' => 1, 'pu_ttc' => -$m, 'tva' => $t, 'total_ttc' => -$m, 'pu_ht' => -ht_de_ttc($m, $t), 'total_ht' => -ht_de_ttc($m, $t)];
        $restes = array_filter(facture_reste_par_taux($f), fn($v) => $v > 0);
        if ($total && $reste === (int)$f['total_ttc']) $lignes = array_map(fn($l) => ['designation' => $l['designation'], 'q' => $l['q'], 'pu_ttc' => -$l['pu_ttc'], 'tva' => $l['tva'],
            'total_ttc' => -$l['total_ttc'], 'pu_ht' => -$l['pu_ht'], 'total_ht' => -$l['total_ht']], $d0['lignes']);
        elseif ($total) {   // tout le reste, taux par taux (après un premier avoir partiel)
            $lignes = [];
            foreach ($restes as $t => $v) $lignes[] = $correction($v, (int)$t);
        } else {
            if ($taux !== null && !isset($restes[$taux]) || $taux === null && count($restes) > 1) return ['erreur' => 'Cette facture a plusieurs taux de TVA : choisissez celui de la correction.'];
            $t = $taux ?? (int)array_key_first($restes);
            if ($montant > ($restes[$t] ?? 0)) return ['erreur' => 'La correction dépasse ce qui reste de la facture à ce taux de TVA (' . montant_facture((int)($restes[$t] ?? 0)) . ').'];
            $lignes = [$correction((int)$montant, $t)];
        }
        return ['v' => 1, 'genre' => 'avoir', 'vendeur' => vendeur() ?? $d0['vendeur'], 'client' => $d0['client'], 'commande' => $d0['commande'],
                'date_vente' => $d0['date_vente'], 'lignes' => $lignes] + facture_totaux($lignes)
             + ['origine' => ['numero' => $f['numero'], 'le' => substr($f['emise_le'], 0, 10)], 'motif' => $motif, 'total' => $total];
    };
    $r = document_emettre('avoir', $preparer, ['commande' => $f['commande_id'], 'client' => $f['client_id'], 'facture' => $factureId], $motif);
    // Avoir total : la commande n'a plus de facture en vigueur, le numéro noté sur la commande s'efface (une nouvelle facture pourra se faire)
    if (!isset($r['erreur']) && $total && $f['commande_id']) q("UPDATE commandes SET facture = '' WHERE id = ? AND facture = ?", [(int)$f['commande_id'], $f['numero']]);
    return $r;
}

/* ---------- Vérification du journal (FR-015) ---------- */
/** Recalcule empreintes, chaîne, continuité des séries et sceau ; renvoie ['n' => nombre, 'problemes' => [texte…]]. */
function factures_verifier(): array {
    $pb = []; $prec = str_repeat('0', 64); $n = 0; $series = [];
    $st = q("SELECT id, genre, numero, prefixe, annee, seq, commande_id, facture_id, emise_le, total_ttc, donnees, pdf, empreinte, chaine FROM factures ORDER BY id");
    while ($f = $st->fetch()) {
        $n++;
        if (hash('sha256', (string)$f['pdf']) !== $f['empreinte']) $pb[] = $f['numero'] . ' : le PDF a été modifié.';
        $attendu = facture_chainer($prec, $f);
        if (!hash_equals($attendu, (string)$f['chaine'])) $pb[] = $f['numero'] . ' : la ligne a été modifiée, ou une ligne avant elle a été retirée.';
        $s = $f['prefixe'] . '|' . $f['annee'];
        $suivant = ($series[$s] ?? 0) + 1;
        if ((int)$f['seq'] !== $suivant) $pb[] = $f['numero'] . ' : la série saute (numéro ' . $suivant . ' attendu).';
        $series[$s] = (int)$f['seq'];
        $prec = (string)$f['chaine'];
    }
    $sceau = (string)parametre('factures_sceau', '');
    if ($n && $sceau !== $n . '|' . $prec) $pb[] = 'Le dernier document ne correspond plus au sceau : une facture ou un avoir a pu être retiré.';
    if (!$n && $sceau !== '' && $sceau !== '0|') $pb[] = 'Des factures ont été retirées de la gestion.';
    return ['n' => $n, 'problemes' => $pb];
}

/* ---------- Remises au client (FR-009) ---------- */
function facture_remise_noter(int $factureId, string $canal, string $detail = '', string $mid = '', bool $ok = true): void {
    if (!isset(REMISE_CANAUX[$canal])) return;
    $f = facture($factureId);
    if (!$f) return;
    q("INSERT INTO factures_remises (facture_id, le, par, canal, detail, mid, ok) VALUES (?, ?, ?, ?, ?, ?, ?)", [$factureId, maintenant(), auteur(), $canal, mb_substr($detail, 0, 200), $mid, $ok ? 1 : 0]);
    // Un mail accepté par le serveur est « envoyé » : la gestion ne sait pas s'il est arrivé ni s'il a été lu (audit n° 6, constat 8)
    $e = $f['genre'] === 'avoir' ? '' : 'e';
    evenement('facture', ($f['genre'] === 'avoir' ? 'Avoir ' : 'Facture ') . $f['numero'] . ($ok ? ($canal === 'email' ? ' envoyé' . $e . ' par mail : ' : ' remis' . $e . ' au client : ') : ' : échec de la remise, ') . REMISE_CANAUX[$canal] . ($detail !== '' ? ' (' . $detail . ')' : ''),
              ['commande' => $f['commande_id'], 'client' => $f['client_id']], 'remise:' . $canal . ($mid !== '' ? ' mid:' . $mid : ''), '');
}
/** Remise réussie ? La dernière remise réussie, ou null. */
function facture_remise_derniere(int $factureId): ?array { return ligne("SELECT * FROM factures_remises WHERE facture_id = ? AND ok = 1 ORDER BY id DESC LIMIT 1", [$factureId]); }

/* ---------- Lien privé (FR-008) ---------- */
function facture_lien_actif(array $f): bool { return (string)$f['jeton'] !== '' && strtotime($f['emise_le']) > strtotime('-' . FACTURE_LIEN_MOIS . ' months'); }
function facture_lien(array $f): string { return 'https://' . GESTION_DOMAINE . '/facture/' . (int)$f['id'] . '-' . $f['jeton']; }

/* ---------- Le PDF ---------- */
/** Le document, en français seulement (FR-016), depuis les données figées. */
function facture_pdf(array $d): string {
    $avoir = ($d['genre'] ?? 'facture') === 'avoir';
    $v = $d['vendeur'];
    $titre = ($avoir ? 'Avoir ' : 'Facture ') . $d['numero'];
    $p = new Pdf($titre . ' · ' . $v['nom'], (string)$d['emise_le']);
    $M = 50.0; $D = Pdf::L - 50; $gris = [115, 106, 100]; $choco = [59, 29, 14];
    // En-tête : Madame Cho à gauche, le document à droite
    $p->texte($M, 72, $v['nom'], 20, true, 'g', $choco);
    $y = 90;
    foreach (array_filter([$v['adresse'], trim(($v['tel'] !== '' ? 'Tél. ' . $v['tel'] : '') . ($v['tel'] !== '' && $v['email'] !== '' ? ' · ' : '') . $v['email']), $v['site']]) as $l) { $p->texte($M, $y, $l, 9, false, 'g', $gris); $y += 12; }
    $p->texte($D, 72, $avoir ? 'AVOIR' : 'FACTURE', 18, true, 'd', $choco);
    $p->texte($D, 90, 'N° ' . $d['numero'], 10, true, 'd');
    $p->texte($D, 104, 'Date d\'émission : ' . date_facture($d['emise_le']), 9, false, 'd', $gris);
    $p->texte($D, 116, 'Date de la vente : ' . date_facture($d['date_vente']), 9, false, 'd', $gris);
    $p->texte($D, 128, 'Commande ' . $d['commande']['reference'], 9, false, 'd', $gris);
    // Client
    $p->aplat($M, 150, 240, 62);
    $p->texte($M + 12, 168, 'Client', 8, false, 'g', $gris);
    $p->texte($M + 12, 184, $d['client']['nom'], 11, true);
    $yc = 198;
    foreach (Pdf::couper((string)$d['client']['adresse'], 216, 9) as $l) { if ($l === '') break; $p->texte($M + 12, $yc, $l, 9); $yc += 11; }
    $y = 236;
    if ($avoir) {
        $p->texte($M, $y, 'Avoir sur la facture ' . $d['origine']['numero'] . ' du ' . date_facture($d['origine']['le']) . ($d['total'] ? ' (annulation totale)' : ' (correction partielle)'), 10, true);
        $y += 14;
        foreach (Pdf::couper('Motif : ' . $d['motif'], $D - $M, 9) as $l) { $p->texte($M, $y, $l, 9); $y += 12; }
        $y += 8;
    }
    // Lignes : désignation, quantité, prix unitaire HT, TVA, total HT, total TTC
    $cols = [['Désignation', $M + 6, 'g'], ['Qté', 300, 'd'], ['PU HT', 362, 'd'], ['TVA', 405, 'd'], ['Total HT', 470, 'd'], ['Total TTC', $D - 6, 'd']];
    $entete = function (float $y) use ($p, $cols, $M, $D, $choco): float {
        $p->aplat($M, $y, $D - $M, 20, [59, 29, 14]);
        foreach ($cols as [$t, $x, $a]) $p->texte($x, $y + 13.5, $t, 8.5, true, $a, [255, 255, 255]);
        return $y + 20;
    };
    $y = $entete($y);
    foreach ($d['lignes'] as $l) {
        $des = Pdf::couper($l['designation'], 300 - 30 - $M - 6, 9);
        $h = 8 + 11 * count($des);
        if ($y + $h > 640) { $p->nouvelle_page(); $y = $entete(60); }
        foreach ($des as $i => $t) $p->texte($M + 6, $y + 13 + 11 * $i, $t, 9);
        $p->texte(300, $y + 13, (string)$l['q'], 9, false, 'd');
        $p->texte(362, $y + 13, pu_ht_facture($l), 9, false, 'd');
        $p->texte(405, $y + 13, taux_facture($l['tva']), 9, false, 'd');
        $p->texte(470, $y + 13, montant_facture($l['total_ht']), 9, false, 'd');
        $p->texte($D - 6, $y + 13, montant_facture($l['total_ttc']), 9, false, 'd');
        $y += $h;
        $p->trait($M, $y, $D, $y);
    }
    // Totaux
    if ($y > 620) { $p->nouvelle_page(); $y = 60; }
    $y += 18; $xl = 360;
    $p->texte($xl, $y, 'Total HT', 9.5); $p->texte($D - 6, $y, montant_facture($d['totaux']['ht']), 9.5, false, 'd'); $y += 14;
    foreach ($d['par_taux'] as $t) { $p->texte($xl, $y, 'TVA ' . taux_facture($t['taux']) . ' sur ' . montant_facture($t['ht']), 9.5); $p->texte($D - 6, $y, montant_facture($t['tva']), 9.5, false, 'd'); $y += 14; }
    $p->aplat($xl - 8, $y - 4, $D - $xl + 8, 22, [243, 237, 228]);
    $p->texte($xl, $y + 11, $avoir ? 'Total de l\'avoir TTC' : 'Total TTC', 11, true, 'g', $choco); $p->texte($D - 6, $y + 11, montant_facture($d['totaux']['ttc']), 11, true, 'd', $choco);
    $y += 44;
    // Règlement
    if (!$avoir) {
        $pa = $d['paiement'];
        foreach (facture_reglement_lignes($pa) as $i => $t) { $p->texte($M, $y, $t, $i ? 9.5 : 10, !$i); $y += $i ? 13 : 14; }
    } else {
        $p->texte($M, $y, 'Cet avoir annule ' . ($d['total'] ? 'la facture ' : 'une partie de la facture ') . $d['origine']['numero'] . '.', 10, true); $y += 14;
    }
    // Pied, sur chaque page
    $n = $p->nombre_pages();
    for ($i = 0; $i < $n; $i++) {
        $p->aller_page($i);
        $p->trait($M, 776, $D, 776);
        $p->texte(Pdf::L / 2, 790, $v['nom'] . ', ' . $v['forme'] . ' · Siège : ' . $v['adresse'], 7.5, false, 'c', $gris);
        $p->texte(Pdf::L / 2, 800, 'SIREN ' . $v['siren'] . ' · RCS ' . $v['rcs'] . ' · TVA intracommunautaire ' . $v['tva'], 7.5, false, 'c', $gris);
        $p->texte($D, 816, $d['numero'] . ' · page ' . ($i + 1) . '/' . $n, 7.5, false, 'd', $gris);
    }
    return $p->sortie();
}

/* ---------- Aperçu à l'écran (avant l'émission) ---------- */
/** Le même document en HTML, pour le relire avant d'émettre : le numéro n'est pas encore donné. */
function facture_apercu_html(array $d): string {
    $avoir = ($d['genre'] ?? 'facture') === 'avoir';
    $v = $d['vendeur'];
    $l = '';
    foreach ($d['lignes'] as $x) $l .= '<tr><td>' . h($x['designation']) . '</td><td class="n">' . (int)$x['q'] . '</td><td class="n">' . h(pu_ht_facture($x)) . '</td><td class="n">'
        . h(taux_facture($x['tva'])) . '</td><td class="n">' . h(montant_facture($x['total_ht'])) . '</td><td class="n">' . h(montant_facture($x['total_ttc'])) . '</td></tr>';
    $t = '<tr><td colspan="5">Total HT</td><td class="n">' . h(montant_facture($d['totaux']['ht'])) . '</td></tr>';
    foreach ($d['par_taux'] as $x) $t .= '<tr><td colspan="5">TVA ' . h(taux_facture($x['taux'])) . '</td><td class="n">' . h(montant_facture($x['tva'])) . '</td></tr>';
    $t .= '<tr class="total"><td colspan="5">Total TTC</td><td class="n">' . h(montant_facture($d['totaux']['ttc'])) . '</td></tr>';
    $pa = $d['paiement'] ?? null;
    return '<div class="apercu-facture" translate="no" lang="fr">'
        . '<div class="af-tete"><div><b>' . h($v['nom']) . '</b><br><span>' . h($v['adresse']) . '</span></div><div class="af-doc"><b>' . ($avoir ? 'AVOIR' : 'FACTURE') . '</b><br><span>N° ' . h($d['numero'] ?? 'donné à l\'émission') . '</span><br>'
        . '<span>Vente du ' . h(date_facture($d['date_vente'])) . ' · ' . h($d['commande']['reference']) . '</span></div></div>'
        . '<p class="af-client"><span>Client</span><b>' . h($d['client']['nom']) . '</b>' . ($d['client']['adresse'] !== '' ? '<br>' . h($d['client']['adresse']) : '') . '</p>'
        . '<div class="table-defile"><table class="af-lignes"><thead><tr><th>Désignation</th><th>Qté</th><th>PU HT</th><th>TVA</th><th>Total HT</th><th>Total TTC</th></tr></thead><tbody>' . $l . '</tbody><tfoot>' . $t . '</tfoot></table></div>'
        . ($pa && !$avoir ? '<p class="af-regle">' . implode('<br>', array_map('MadameCho\Gestion\h', facture_reglement_lignes($pa))) . '</p>' : '')
        . '<p class="af-pied">' . h($v['nom'] . ', ' . $v['forme'] . ' · SIREN ' . $v['siren'] . ' · RCS ' . $v['rcs'] . ' · TVA ' . $v['tva']) . '</p></div>';
}

/* ---------- Accueil (FR-010) ---------- */
/** Ce que l'accueil signale : à émettre (payée et remise depuis 2 jours), émises pas remises, à corriger, remise échouée, journal altéré. */
function factures_alertes(): array {
    $r = ['a_emettre' => [], 'pas_remises' => [], 'a_corriger' => [], 'echecs' => []];
    $limite = date('Y-m-d H:i:s', time() - FACTURE_A_FAIRE_JOURS * 86400);
    foreach (lignes("SELECT k.id FROM commandes k JOIN clients c ON c.id = k.client_id WHERE k.efface_le IS NULL AND k.statut = 'remise' AND k.paiement = 'paye'
                     AND k.facture_demandee = 1 AND k.type <> 'pro' AND c.type <> 'professionnel' AND COALESCE(k.remise_le, k.maj_le) < ? AND k.remise_le >= ?
                     AND NOT EXISTS (SELECT 1 FROM factures f WHERE f.commande_id = k.id AND f.genre = 'facture') ORDER BY k.remise_le",
                    [$limite, (string)parametre('factures_depuis', '9999')]) as $x) $r['a_emettre'][] = commande((int)$x['id']);
    foreach (lignes("SELECT f.id FROM factures f WHERE f.genre = 'facture' AND f.emise_le > ? AND NOT EXISTS (SELECT 1 FROM factures_remises x WHERE x.facture_id = f.id AND x.ok = 1)
                     ORDER BY f.id", [date('Y-m-d H:i:s', strtotime('-60 days'))]) as $x) {
        $f = facture((int)$x['id']);
        if (facture_reste($f) <= 0) continue;
        if (val("SELECT 1 FROM factures_remises WHERE facture_id = ? AND ok = 0", [$f['id']])) $r['echecs'][] = $f; else $r['pas_remises'][] = $f;
    }
    foreach (lignes("SELECT DISTINCT commande_id FROM factures WHERE genre = 'facture' AND commande_id IS NOT NULL AND emise_le > ?", [date('Y-m-d H:i:s', strtotime('-1 year'))]) as $x) {
        $k = commande((int)$x['commande_id']);
        if (!$k || $k['efface_le']) continue;
        $e = facture_etat($k);
        if (($e['etat'] ?? '') === 'emise' && $e['a_corriger']) $r['a_corriger'][] = ['commande' => $k, 'facture' => $e['facture'], 'reste' => $e['reste']];
    }
    return $r;
}

/* ---------- Conservation (FR-014) ---------- */
/** Lancée par la tâche de nuit : les liens privés s'éteignent après 12 mois, les documents s'effacent après 10 ans pleins. */
function factures_conservation(): void {
    q("UPDATE factures SET jeton = '' WHERE jeton <> '' AND emise_le < ?", [date('Y-m-d H:i:s', strtotime('-' . FACTURE_LIEN_MOIS . ' months'))]);
    // 10 ans à compter de la fin de l'exercice : on garde une année de plus par prudence, et la chaîne repart du sceau (pas d'effacement tant que rien n'a 11 ans)
    $limite = (date('Y') - FACTURE_CONSERVATION_ANS - 1) . '-01-01';
    if (val("SELECT 1 FROM factures WHERE emise_le < ? LIMIT 1", [$limite])) evenement('conservation', 'Des factures ont plus de 10 ans : à archiver avec Olivier avant de les effacer', [], '', '', 'système');
}
