<?php
/*
 * Madame Cho : espace de gestion. L'écran « Obligations », lot 1 : les démarches à faire et les échéances
 * (demande d'Olivier du 08/11/2026, spécification specs/004-obligations ; corrections de l'audit n° 7, passe 1).
 * Une démarche dit quoi faire, où (outil, page, endroit), pour qui, pour quand et pourquoi. Elle n'est jamais effacée par une
 * personne : « C'est fait » (lien Drive et note), « Envoyé, j'attends la réponse » ou « Sans objet » (raison) ; la tâche de nuit
 * l'efface 10 ans après sa clôture. Une échéance récurrente crée une démarche par période, à son préavis (30 jours par défaut).
 * Aucune démarche réelle dans ce dépôt public : la liste de départ entre par la reprise (CSV), faite par Olivier.
 */
declare(strict_types=1);
namespace MadameCho\Gestion;

const OBLIGATIONS_JOURS = 30;              // préavis par défaut (choix d'Olivier ; chaque échéance a le sien, audit n° 7, constat 2)
const OBLIGATIONS_CONSERVATION_ANS = 10;   // démarches closes effacées 10 ans après leur clôture (audit n° 7, constat 8)
const OBLIGATIONS_RATTRAPAGE = 12;         // au plus 12 périodes rattrapées d'un coup pour une échéance
const DEMARCHE_ETATS = ['a_faire' => 'À faire', 'attente' => 'Envoyé, réponse attendue', 'fait' => 'Fait', 'sans_objet' => 'Sans objet'];
const DEMARCHE_POUR = ['youngmi' => 'Youngmi', 'olivier' => 'Olivier', 'deux' => 'Youngmi et Olivier'];
const DEMARCHE_PRIORITES = [1 => 'Haute', 2 => 'Normale', 3 => 'Basse'];
const ECHEANCE_FREQUENCES = ['annuelle' => 'Chaque année', 'trimestrielle' => 'Chaque trimestre', 'mensuelle' => 'Chaque mois', 'premier_lundi' => 'Le premier lundi du mois'];
const ECHEANCE_PAS = ['annuelle' => 12, 'trimestrielle' => 3, 'mensuelle' => 1, 'premier_lundi' => 1];

/* ---------- Qui est connecté ---------- */

/** « youngmi » pour le compte de contact@ (le compte de Youngmi), « olivier » pour un autre compte : la gestion n'a que ces deux personnes. */
function obligations_moi(?array $u = null): string {
    $u = $u ?? utilisateur_courant();
    return $u && strtolower((string)$u['email']) === GESTION_COMPTE_INITIAL ? 'youngmi' : 'olivier';
}
function obligations_pour_moi(array $d, string $moi): bool { return $d['pour_qui'] === $moi || $d['pour_qui'] === 'deux'; }

/* ---------- Lecture ---------- */

function demarche(int $id): ?array { return ligne("SELECT * FROM demarches WHERE id = ?", [$id]); }
function echeance(int $id): ?array { return ligne("SELECT * FROM echeances WHERE id = ?", [$id]); }

/**
 * L'état affiché d'une démarche : un niveau (retard, bientot, attente, confirmer, plus_tard, sans_date, fait, sans_objet),
 * son rang pour l'ordre de la liste, et une phrase qui dit l'état en toutes lettres (audit n° 7, constat 9).
 */
function demarche_etat(array $d, ?string $auj = null): array {
    $auj = $auj ?? aujourdhui();
    if ($d['etat'] === 'fait') return ['niveau' => 'fait', 'rang' => 8, 'texte' => 'Fait le ' . date_fr(substr((string)$d['fait_le'], 0, 10), 'longue')];
    if ($d['etat'] === 'sans_objet') return ['niveau' => 'sans_objet', 'rang' => 9, 'texte' => 'Sans objet'];
    if ($d['etat'] === 'attente') return ['niveau' => 'attente', 'rang' => 2, 'texte' => 'Envoyé le ' . date_fr(substr((string)$d['fait_le'], 0, 10), 'longue') . ', réponse attendue'];
    if ((int)$d['a_confirmer'] === 1) return ['niveau' => 'confirmer', 'rang' => 3, 'texte' => 'Date à confirmer'];
    $e = (string)$d['echeance'];
    if ($e === '') return ['niveau' => 'sans_date', 'rang' => 5, 'texte' => 'Sans date'];
    if ($e < $auj) return ['niveau' => 'retard', 'rang' => 0, 'texte' => 'En retard depuis le ' . date_fr($e, 'longue')];
    $jours = intdiv((int)strtotime($e) - (int)strtotime($auj), 86400);
    $preavis = (int)$d['preavis'] > 0 ? (int)$d['preavis'] : OBLIGATIONS_JOURS;
    if ($jours <= $preavis) return ['niveau' => 'bientot', 'rang' => 1, 'texte' => ($jours === 0 ? 'Aujourd\'hui, ' : 'Avant le ') . date_fr($e, 'longue')];
    return ['niveau' => 'plus_tard', 'rang' => 4, 'texte' => 'Pour le ' . date_fr($e, 'longue')];
}

/** Les démarches d'une vue (« a_faire » = à faire et en attente ; « fait » = faites et sans objet), triées : en retard, bientôt, en attente, à confirmer, plus tard, sans date. */
function demarches_lister(string $vue, string $qui, string $moi): array {
    $etats = $vue === 'fait' ? ['fait', 'sans_objet'] : ['a_faire', 'attente'];
    $l = lignes("SELECT * FROM demarches WHERE etat IN ('" . implode("','", $etats) . "')" . ($vue === 'fait' ? " ORDER BY fait_le DESC, id DESC LIMIT 200" : ''));
    if ($qui === 'miennes') $l = array_values(array_filter($l, fn($d) => obligations_pour_moi($d, $moi)));
    $auj = aujourdhui();
    foreach ($l as &$d) $d['_etat'] = demarche_etat($d, $auj);
    unset($d);
    if ($vue !== 'fait') usort($l, fn($a, $b) => [$a['_etat']['rang'], $a['echeance'] === '' ? '9999' : $a['echeance'], (int)$a['priorite'], (int)$a['id']]
                                          <=> [$b['_etat']['rang'], $b['echeance'] === '' ? '9999' : $b['echeance'], (int)$b['priorite'], (int)$b['id']]);
    return $l;
}

/** Le nombre rouge de la personne : ses démarches en retard ou dans leur préavis (pas celles en attente d'une réponse). */
function obligations_urgentes(?string $moi = null): int {
    if (!obligations_pret()) return 0;
    $moi = $moi ?? obligations_moi();
    $n = 0;
    $auj = aujourdhui();
    foreach (lignes("SELECT * FROM demarches WHERE etat = 'a_faire'") as $d) {
        if (!obligations_pour_moi($d, $moi)) continue;
        if (in_array(demarche_etat($d, $auj)['niveau'], ['retard', 'bientot'], true)) $n++;
    }
    return $n;
}
/** Les tables existent (la migration est passée) : sinon l'écran et les pastilles se taisent. */
function obligations_pret(): bool {
    static $ok = null;
    return $ok ??= (bool)val("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'demarches'");
}

/* ---------- Historique et « a changé » ---------- */

function demarche_historique_noter(string $type, ?int $demarche, ?int $echeance, string $champ = '', string $ancien = '', string $nouveau = '', ?string $par = null): void {
    q("INSERT INTO demarches_historique (le, auteur, demarche_id, echeance_id, type, champ, ancien, nouveau) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
      [maintenant(), $par ?? auteur(), $demarche, $echeance, $type, $champ, $ancien, $nouveau]);
}
function demarche_historique(int $id): array { return lignes("SELECT * FROM demarches_historique WHERE demarche_id = ? ORDER BY id DESC LIMIT 50", [$id]); }

/** Le dernier changement vu par la personne sur l'écran Obligations (« vu par personne », comme les autres pastilles). */
function obligations_vu_jusqua(): int {
    $u = utilisateur_courant();
    if (!$u) return PHP_INT_MAX;
    $v = val("SELECT jusqu_a FROM vus WHERE utilisateur_id = ? AND genre = 'obligations' AND objet = 'ecran'", [(int)$u['id']]);
    if ($v !== null) return (int)$v;
    // Premier passage : rien d'ancien ne s'allume d'un coup ; seul ce qui a moins de 30 jours compte
    return (int)(val("SELECT COALESCE(MAX(id), 0) FROM demarches_historique WHERE le < ?", [date('Y-m-d H:i:s', time() - PASTILLES_JOURS * 86400)]) ?? 0);
}
/** Les démarches que l'autre personne a ajoutées ou changées depuis la dernière visite : [id => 'nouveau'|'change']. */
function obligations_changees(): array {
    if (!obligations_pret()) return [];
    $u = utilisateur_courant();
    if (!$u) return [];
    $r = [];
    foreach (lignes("SELECT demarche_id, type FROM demarches_historique WHERE id > ? AND demarche_id IS NOT NULL AND auteur NOT IN (?, 'système') ORDER BY id",
                    [obligations_vu_jusqua(), (string)$u['nom']]) as $h) {
        $id = (int)$h['demarche_id'];
        $r[$id] = $h['type'] === 'creation' && !isset($r[$id]) ? 'nouveau' : ($r[$id] ?? 'change');
    }
    return $r;
}
function obligations_marquer_vu(): void {
    $max = (int)(val("SELECT COALESCE(MAX(id), 0) FROM demarches_historique") ?? 0);
    pastilles_vu('obligations', 'ecran', $max);
}

/* ---------- Écriture ---------- */

const DEMARCHE_CHAMPS = ['titre', 'explication', 'ou', 'pour_qui', 'echeance', 'a_confirmer', 'preavis', 'priorite', 'importante', 'origine'];

/** Un lien de preuve accepté : une adresse du Drive ou des Docs Google, rien d'autre (les pièces restent dans le Drive). */
function lien_drive_valide(string $l): bool {
    return $l !== '' && (bool)preg_match('~^https://(drive|docs)\.google\.com/[^\s<>"]+$~', $l) && strlen($l) <= 400;
}

/** Les champs d'une démarche lus du formulaire, avec une erreur éventuelle. */
function demarche_lue(): array {
    $d = [
        'titre' => une_ligne(texte('titre', 160)),
        'explication' => texte('explication', 1500),
        'ou' => texte('ou', 600),
        'pour_qui' => choix('pour_qui', DEMARCHE_POUR, 'youngmi'),
        'echeance' => date_valide(texte('echeance', 10)) ?? '',
        'a_confirmer' => isset($_POST['a_confirmer']) ? 1 : 0,
        'preavis' => entier('preavis', 1, 365, OBLIGATIONS_JOURS),
        'priorite' => entier('priorite', 1, 3, 2),
        'importante' => isset($_POST['importante']) ? 1 : 0,
        'origine' => une_ligne(texte('origine', 120)),
    ];
    if ($d['a_confirmer']) $d['echeance'] = '';
    if ($d['titre'] === '') return [$d, 'Écrivez ce qu\'il faut faire (le titre).'];
    if ($d['ou'] === '') return [$d, 'Dites où le faire : l\'outil, la page et l\'endroit exacts.'];
    return [$d, null];
}

function demarche_creer(array $d, ?string $par = null, array $extra = []): int {
    $now = maintenant();
    $par = $par ?? auteur();
    $v = $d + $extra + ['etat' => 'a_faire', 'echeance_id' => null, 'periode' => '', 'declencheur' => '', 'ref_genre' => '', 'ref_id' => ''];
    $cols = array_merge(DEMARCHE_CHAMPS, ['etat', 'echeance_id', 'periode', 'declencheur', 'ref_genre', 'ref_id']);
    $vals = array_map(fn($c) => $v[$c] ?? '', $cols);
    $vals[array_search('echeance_id', $cols, true)] = $v['echeance_id'];
    q("INSERT OR IGNORE INTO demarches (" . implode(', ', $cols) . ", cree_le, cree_par, modifie_le, modifie_par) VALUES (" . implode(', ', array_fill(0, count($cols) + 4, '?')) . ")",
      array_merge($vals, [$now, $par, $now, $par]));
    if (q("SELECT changes()")->fetchColumn() < 1) return 0;   // déjà là (même échéance et même période, ou même déclencheur)
    $id = (int)db()->lastInsertId();
    demarche_historique_noter('creation', $id, $v['echeance_id'] !== null ? (int)$v['echeance_id'] : null, '', '', (string)$d['titre'], $par);
    return $id;
}

function demarche_modifier(int $id, array $d): void {
    $avant = demarche($id);
    if (!$avant) return;
    $chg = [];
    foreach (DEMARCHE_CHAMPS as $c) if ((string)$avant[$c] !== (string)$d[$c]) $chg[$c] = [(string)$avant[$c], (string)$d[$c]];
    if (!$chg) return;
    $set = implode(', ', array_map(fn($c) => "$c = :$c", array_keys($chg)));
    q("UPDATE demarches SET $set, modifie_le = :le, modifie_par = :par WHERE id = :id",
      array_map(fn($p) => $p[1], $chg) + ['le' => maintenant(), 'par' => auteur(), 'id' => $id]);
    foreach ($chg as $c => [$a, $n]) demarche_historique_noter('modification', $id, null, $c, $a, $n);
}

/**
 * Change l'état : « fait », « attente » (envoyé, réponse attendue), « sans_objet » (raison obligatoire) ou « a_faire » (rouvrir).
 * Une démarche importante ne passe « fait » qu'avec un lien de preuve (audit n° 7, constat 7). Rend une erreur, ou ''.
 */
function demarche_changer_etat(int $id, string $etat, string $lien = '', string $note = '', string $raison = ''): string {
    $d = demarche($id);
    if (!$d || !isset(DEMARCHE_ETATS[$etat])) return 'Démarche introuvable.';
    if ($lien !== '' && !lien_drive_valide($lien)) return 'Le lien doit être une adresse du Drive (https://drive.google.com/… ou https://docs.google.com/…).';
    if ($etat === 'fait' && (int)$d['importante'] === 1 && $lien === '' && $d['preuve_lien'] === '')
        return 'Cette démarche est importante : collez le lien de la preuve (récépissé, attestation, rapport) rangée dans le Drive.';
    if ($etat === 'sans_objet' && trim($raison) === '') return 'Dites pourquoi elle est sans objet.';
    $now = maintenant();
    $maj = ['etat' => $etat, 'modifie_le' => $now, 'modifie_par' => auteur()];
    if ($etat === 'a_faire') { $maj['fait_le'] = ''; $maj['fait_par'] = ''; }
    else { $maj['fait_le'] = $now; $maj['fait_par'] = auteur(); }
    if ($lien !== '') $maj['preuve_lien'] = $lien;
    if ($note !== '') $maj['preuve_note'] = trim($note);
    if ($etat === 'sans_objet') $maj['raison'] = trim($raison);
    $set = implode(', ', array_map(fn($c) => "$c = :$c", array_keys($maj)));
    q("UPDATE demarches SET $set WHERE id = :id", $maj + ['id' => $id]);
    $type = ['fait' => 'fait', 'attente' => 'attente', 'sans_objet' => 'sans_objet', 'a_faire' => 'rouverte'][$etat];
    demarche_historique_noter($type, $id, null, 'etat', (string)$d['etat'], $etat, null);
    return '';
}

/* ---------- Échéances récurrentes ---------- */

const ECHEANCE_CHAMPS = ['titre', 'explication', 'ou', 'pour_qui', 'priorite', 'importante', 'frequence', 'depart', 'preavis', 'origine'];

function echeance_lue(): array {
    $e = [
        'titre' => une_ligne(texte('titre', 160)),
        'explication' => texte('explication', 1500),
        'ou' => texte('ou', 600),
        'pour_qui' => choix('pour_qui', DEMARCHE_POUR, 'youngmi'),
        'priorite' => entier('priorite', 1, 3, 2),
        'importante' => isset($_POST['importante']) ? 1 : 0,
        'frequence' => choix('frequence', ECHEANCE_FREQUENCES, 'annuelle'),
        'depart' => isset($_POST['a_confirmer']) ? '' : (date_valide(texte('depart', 10)) ?? ''),
        'preavis' => entier('preavis', 1, 365, OBLIGATIONS_JOURS),
        'origine' => une_ligne(texte('origine', 120)),
    ];
    if ($e['titre'] === '') return [$e, 'Écrivez ce qu\'il faut faire (le titre).'];
    if ($e['ou'] === '') return [$e, 'Dites où le faire : l\'outil, la page et l\'endroit exacts.'];
    if ($e['depart'] === '' && !isset($_POST['a_confirmer'])) return [$e, 'Donnez la date de la prochaine échéance, ou cochez « Date à confirmer ».'];
    return [$e, null];
}
function echeance_enregistrer(?int $id, array $e): int {
    $now = maintenant();
    if ($id === null) {
        q("INSERT INTO echeances (" . implode(', ', ECHEANCE_CHAMPS) . ", active, cree_le, cree_par, modifie_le, modifie_par) VALUES (" . implode(', ', array_fill(0, count(ECHEANCE_CHAMPS) + 5, '?')) . ")",
          array_merge(array_map(fn($c) => $e[$c], ECHEANCE_CHAMPS), [1, $now, auteur(), $now, auteur()]));
        $id = (int)db()->lastInsertId();
        demarche_historique_noter('regle', null, $id, '', '', (string)$e['titre']);
    } else {
        $avant = echeance($id);
        if (!$avant) return 0;
        $set = implode(', ', array_map(fn($c) => "$c = :$c", ECHEANCE_CHAMPS));
        q("UPDATE echeances SET $set, modifie_le = :le, modifie_par = :par WHERE id = :id", array_intersect_key($e, array_flip(ECHEANCE_CHAMPS)) + ['le' => $now, 'par' => auteur(), 'id' => $id]);
        foreach (ECHEANCE_CHAMPS as $c) if ((string)$avant[$c] !== (string)$e[$c]) demarche_historique_noter('regle', null, $id, $c, (string)$avant[$c], (string)$e[$c]);
    }
    obligations_generer();
    return $id;
}
function echeance_arreter(int $id, bool $active): void {
    q("UPDATE echeances SET active = ?, modifie_le = ?, modifie_par = ? WHERE id = ?", [$active ? 1 : 0, maintenant(), auteur(), $id]);
    demarche_historique_noter('regle', null, $id, 'active', $active ? '0' : '1', $active ? '1' : '0');
}

/** Ajoute $mois mois à une date en gardant le jour d'origine, ramené au dernier jour du mois si besoin (31 → 30, 29 février → 28). */
function date_plus_mois(string $depart, int $mois): string {
    [$a, $m, $j] = array_map('intval', explode('-', $depart));
    $t = $a * 12 + ($m - 1) + $mois;
    $a2 = intdiv($t, 12);
    $m2 = $t % 12 + 1;
    $dernier = (int)date('t', (int)mktime(12, 0, 0, $m2, 1, $a2));
    return sprintf('%04d-%02d-%02d', $a2, $m2, min($j, $dernier));
}
/** Le premier lundi du mois d'une date. */
function premier_lundi(string $date): string {
    $d = substr($date, 0, 8) . '01';
    $n = (int)date('N', (int)strtotime($d));
    return date('Y-m-d', (int)strtotime($d . ' +' . ((8 - $n) % 7) . ' days'));
}
/** La date de la période n d'une échéance (n = 0 : la date de départ). */
function echeance_periode(array $e, int $n): string {
    $d = date_plus_mois((string)$e['depart'], $n * ECHEANCE_PAS[$e['frequence']]);
    return $e['frequence'] === 'premier_lundi' ? premier_lundi($d) : $d;
}

/**
 * Crée les démarches des échéances dont le préavis a commencé : une seule par échéance et par période (UNIQUE en base),
 * que la précédente soit close ou non, même si la tâche de nuit a sauté ou passe deux fois (audit n° 7, constats 3 et 4).
 * Une échéance sans date donne une démarche « date à confirmer », qui reçoit sa date quand l'échéance en a une. Rend le nombre créé.
 */
function obligations_generer(?string $auj = null): int {
    if (!obligations_pret()) return 0;
    $auj = $auj ?? aujourdhui();
    $n = 0;
    foreach (lignes("SELECT * FROM echeances WHERE active = 1") as $e) {
        $commun = ['titre' => $e['titre'], 'explication' => $e['explication'], 'ou' => $e['ou'], 'pour_qui' => $e['pour_qui'], 'preavis' => (int)$e['preavis'],
                   'priorite' => (int)$e['priorite'], 'importante' => (int)$e['importante'], 'origine' => $e['origine']];
        if ((string)$e['depart'] === '') {
            if (demarche_creer($commun + ['echeance' => '', 'a_confirmer' => 1], 'système', ['echeance_id' => (int)$e['id'], 'periode' => 'a-confirmer'])) $n++;
            continue;
        }
        // La date est connue : la démarche « à confirmer » encore ouverte prend la première période (si elle n'existe pas déjà)
        $premiere = echeance_periode($e, 0);
        $ac = ligne("SELECT id FROM demarches WHERE echeance_id = ? AND periode = 'a-confirmer' AND etat IN ('a_faire', 'attente')", [(int)$e['id']]);
        if ($ac && !val("SELECT 1 FROM demarches WHERE echeance_id = ? AND periode = ?", [(int)$e['id'], $premiere])) {
            q("UPDATE demarches SET periode = ?, echeance = ?, a_confirmer = 0, modifie_le = ?, modifie_par = 'système' WHERE id = ?", [$premiere, $premiere, maintenant(), (int)$ac['id']]);
            demarche_historique_noter('modification', (int)$ac['id'], (int)$e['id'], 'echeance', '', $premiere, 'système');
        }
        // Les périodes dont le préavis a commencé ; rien d'avant la création de l'échéance, au plus 12 d'un coup
        $creee = substr((string)$e['cree_le'], 0, 10);
        $faites = 0;
        for ($i = 0; $i < 600; $i++) {
            $p = echeance_periode($e, $i);
            if (date('Y-m-d', (int)strtotime($p . ' -' . (int)$e['preavis'] . ' days')) > $auj) break;
            if ($p < $creee && $p < date('Y-m-d', (int)strtotime($auj . ' -' . (int)$e['preavis'] . ' days'))) continue;
            if (++$faites > OBLIGATIONS_RATTRAPAGE) break;
            if (demarche_creer($commun + ['echeance' => $p, 'a_confirmer' => 0], 'système', ['echeance_id' => (int)$e['id'], 'periode' => $p])) $n++;
        }
    }
    parametre_ecrire('obligations_calcul', maintenant());
    return $n;
}

/* ---------- Nouveau restaurant : la dérogation avant la première livraison (audit n° 7, constat 1) ---------- */

/**
 * Appelée quand une commande passe « confirmée » : si c'est la première commande confirmée d'un client professionnel, une démarche
 * importante rappelle de vérifier la dérogation (Cerfa 13982) avant de le livrer, pour la veille de la livraison. Une seule fois par client.
 */
function obligations_nouveau_restaurant(array $k): void {
    if (!obligations_pret() || (($k['type'] ?? '') !== 'pro' && ($k['client_type'] ?? '') !== 'pro')) return;
    $cid = (int)$k['client_id'];
    if (val("SELECT 1 FROM commandes WHERE client_id = ? AND id <> ? AND statut IN ('confirmee', 'remise')", [$cid, (int)$k['id']])) return;
    $c = client($cid);
    $nom = $c ? client_nom($c) : 'ce restaurant';
    $date = (string)($k['retrait_date'] ?? '');
    $veille = $date !== '' ? date('Y-m-d', (int)strtotime($date . ' -1 day')) : aujourdhui();
    if ($veille < aujourdhui()) $veille = aujourdhui();
    demarche_creer([
        'titre' => 'Vérifier la dérogation (Cerfa 13982) avant de livrer ' . $nom,
        'explication' => 'Avant de livrer un nouveau restaurant, la dérogation à l\'agrément sanitaire doit le compter parmi les destinataires (adresse, distance, produits, quantités par semaine). S\'il y est déjà, collez le lien du récépissé et touchez « C\'est fait » ; sinon, mettez la dérogation à jour avant la livraison.',
        'ou' => 'mesdemarches.agriculture.gouv.fr › Mes démarches › Dérogation à l\'obligation d\'agrément (Cerfa 13982) ; le récépissé dans le Drive : 01 › 09-Certifications, labels et agréments.',
        'pour_qui' => 'youngmi', 'echeance' => $veille, 'a_confirmer' => 0, 'preavis' => OBLIGATIONS_JOURS, 'priorite' => 1, 'importante' => 1,
        'origine' => 'Commande ' . $k['reference'],
    ], 'système', ['declencheur' => 'restaurant:' . $cid]);
}

/* ---------- Tâche de nuit : occurrences, conservation, mail interne du lundi ---------- */

function obligations_nuit(): array {
    if (!obligations_pret()) return [];
    $cr = ['crees' => obligations_generer()];
    // Conservation : les démarches closes depuis plus de 10 ans, et leur historique (FR-017)
    $limite = date('Y-m-d H:i:s', (int)strtotime('-' . OBLIGATIONS_CONSERVATION_ANS . ' years'));
    $vieilles = array_column(lignes("SELECT id FROM demarches WHERE etat IN ('fait', 'sans_objet') AND fait_le <> '' AND fait_le < ?", [$limite]), 'id');
    foreach ($vieilles as $id) { q("DELETE FROM demarches_historique WHERE demarche_id = ?", [(int)$id]); q("DELETE FROM demarches WHERE id = ?", [(int)$id]); }
    $cr['effacees'] = count($vieilles);
    $cr['mail'] = obligations_mail_lundi();
    return $cr;
}

/** Les lignes du mail du lundi : ce qui est en retard, à faire bientôt, à dater ou en attente, pour chaque personne. */
function obligations_resume(): array {
    $l = demarches_lister('a_faire', 'toutes', 'youngmi');
    return array_values(array_filter($l, fn($d) => in_array($d['_etat']['niveau'], ['retard', 'bientot', 'attente', 'confirmer'], true)));
}
function obligations_mail_texte(array $l): string {
    $t = "Obligations de la semaine (gestion Madame Cho, " . date_fr(aujourdhui(), 'longue') . ")\n\n";
    if (!$l) return $t . "Rien en retard ni à faire dans les " . OBLIGATIONS_JOURS . " prochains jours.\n";
    foreach (['retard' => 'En retard', 'bientot' => 'À faire bientôt', 'confirmer' => 'Date à confirmer', 'attente' => 'Envoyé, réponse attendue'] as $niv => $titre) {
        $g = array_filter($l, fn($d) => $d['_etat']['niveau'] === $niv);
        if (!$g) continue;
        $t .= mb_strtoupper($titre) . "\n";
        foreach ($g as $d) $t .= '- ' . $d['titre'] . ' (' . DEMARCHE_POUR[$d['pour_qui']] . ') : ' . $d['_etat']['texte'] . "\n";
        $t .= "\n";
    }
    return $t . "Le détail : gestion, Plus › Obligations.\nMail interne de la gestion, envoyé à contact@ seulement, chaque lundi (choix d'Olivier du 08/11/2026).\n";
}
/**
 * Le lundi, un mail interne de site@ à contact@ seulement, pour le récap de Ferdinand (choix d'Olivier du 08/11/2026, exception à R-01
 * limitée à ce mail ; jamais à un client ; signé DKIM « site »). Une fois par semaine au plus. Rend 'envoye', 'deja', 'pas_lundi' ou 'echec'.
 */
function obligations_mail_lundi(?string $auj = null): string {
    $auj = $auj ?? aujourdhui();
    if ((int)date('N', (int)strtotime($auj)) !== 1) return 'pas_lundi';
    $semaine = date('o-\SW', (int)strtotime($auj));
    if (parametre('obligations_mail_semaine') === $semaine) return 'deja';
    $texte = obligations_mail_texte(obligations_resume());
    if (GESTION_ESSAI) { parametre_ecrire('essai_mail_obligations', $texte); $ok = true; }
    else $ok = envoyer_email(GESTION_COMPTE_INITIAL, 'Obligations de la semaine · gestion Madame Cho', $texte);
    if (!$ok) return 'echec';
    parametre_ecrire('obligations_mail_semaine', $semaine);
    return 'envoye';
}

/* ---------- Reprise de la liste de départ (CSV, faite par Olivier ; jamais dans le dépôt) ---------- */

const OBLIGATIONS_CSV = ['genre', 'titre', 'explication', 'ou', 'pour_qui', 'date', 'a_confirmer', 'preavis', 'priorite', 'importante', 'origine', 'frequence'];

/**
 * Lit un CSV (séparateur ; ou ,) : une ligne par démarche (genre « demarche ») ou par échéance récurrente (genre « echeance »).
 * Refuse les doublons (même titre et même date pour une démarche, même titre pour une échéance active). Rend le compte rendu.
 */
function obligations_reprise(string $contenu): array {
    $contenu = preg_replace('/^\xEF\xBB\xBF/', '', $contenu) ?? $contenu;
    $lignes = preg_split('/\r\n|\n|\r/', trim($contenu)) ?: [];
    if (!$lignes) return ['erreur' => 'Fichier vide.'];
    $sep = substr_count($lignes[0], ';') >= substr_count($lignes[0], ',') ? ';' : ',';
    $tete = array_map(fn($c) => strtolower(trim($c)), str_getcsv(array_shift($lignes), $sep, '"', '\\'));
    if (array_diff(['genre', 'titre', 'ou'], $tete)) return ['erreur' => 'Colonnes attendues : ' . implode($sep, OBLIGATIONS_CSV) . '.'];
    $cr = ['demarches' => 0, 'echeances' => 0, 'doublons' => 0, 'refusees' => []];
    db()->beginTransaction();
    try {
        foreach ($lignes as $i => $brut) {
            if (trim($brut) === '') continue;
            $v = array_combine($tete, array_pad(array_slice(str_getcsv($brut, $sep, '"', '\\'), 0, count($tete)), count($tete), ''));
            $g = fn(string $c) => trim((string)($v[$c] ?? ''));
            $titre = une_ligne($g('titre'));
            $date = $g('date');
            $dateOk = $date === '' ? '' : date_valide($date);
            if ($titre === '' || $g('ou') === '' || $dateOk === null || !isset(DEMARCHE_POUR[$g('pour_qui') ?: 'youngmi'])) { $cr['refusees'][] = $i + 2; continue; }
            $commun = ['titre' => mb_substr($titre, 0, 160), 'explication' => mb_substr($g('explication'), 0, 1500), 'ou' => mb_substr($g('ou'), 0, 600),
                       'pour_qui' => $g('pour_qui') ?: 'youngmi', 'preavis' => max(1, min(365, (int)($g('preavis') ?: OBLIGATIONS_JOURS))),
                       'priorite' => max(1, min(3, (int)($g('priorite') ?: 2))), 'importante' => $g('importante') === '1' ? 1 : 0, 'origine' => mb_substr($g('origine'), 0, 120)];
            if ($g('genre') === 'echeance') {
                if (!isset(ECHEANCE_FREQUENCES[$g('frequence')])) { $cr['refusees'][] = $i + 2; continue; }
                if (val("SELECT 1 FROM echeances WHERE titre = ? AND active = 1", [$commun['titre']])) { $cr['doublons']++; continue; }
                echeance_enregistrer(null, $commun + ['frequence' => $g('frequence'), 'depart' => $g('a_confirmer') === '1' ? '' : $dateOk]);
                $cr['echeances']++;
            } elseif ($g('genre') === 'demarche') {
                $ac = $g('a_confirmer') === '1' ? 1 : 0;
                $ech = $ac ? '' : $dateOk;
                if (val("SELECT 1 FROM demarches WHERE titre = ? AND echeance = ?", [$commun['titre'], $ech])) { $cr['doublons']++; continue; }
                demarche_creer($commun + ['echeance' => $ech, 'a_confirmer' => $ac], null, ['ref_genre' => 'reprise']);
                $cr['demarches']++;
            } else $cr['refusees'][] = $i + 2;
        }
        db()->commit();
    } catch (\Throwable $e) { db()->rollBack(); throw $e; }
    if ($cr['demarches'] || $cr['echeances']) { parametre_ecrire('obligations_reprise_le', maintenant()); parametre_ecrire('obligations_validee_youngmi', ''); parametre_ecrire('obligations_validee_olivier', ''); }
    return $cr;
}
/** La liste de départ n'est pas encore relue par les deux (bandeau, audit n° 7, constat 6). */
function obligations_liste_a_valider(): array {
    if ((string)parametre('obligations_reprise_le', '') === '') return ['youngmi', 'olivier'];
    return array_values(array_filter(['youngmi', 'olivier'], fn($p) => (string)parametre('obligations_validee_' . $p, '') === ''));
}
