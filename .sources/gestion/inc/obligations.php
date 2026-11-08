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

require_once __DIR__ . '/hygiene.php';   // lot 2 : relevés d'hygiène (relevé manquant = démarche)
require_once __DIR__ . '/volumes.php';   // lot 4 : volumes de la dérogation et documents de preuve

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
/**
 * Le compte d'Olivier : le plus ancien compte administrateur actif autre que contact@. Un autre administrateur invité n'a pas
 * accès à l'écran et ne peut jamais valider ni agir sous le nom d'Olivier (audit n° 7, passe 3, P3-03).
 */
function obligations_olivier_id(): int {
    static $id = null;
    return $id ??= (int)(val("SELECT id FROM utilisateurs WHERE role = 'admin' AND actif = 1 AND lower(email) <> ? ORDER BY cree_le, id LIMIT 1", [GESTION_COMPTE_INITIAL]) ?? 0);
}
/**
 * Qui voit l'écran : Youngmi (contact@) et les comptes administrateurs (Olivier). Un membre invité n'y a pas accès
 * et n'agit jamais sous le nom d'Olivier (audit n° 7, passe 2, P2-04).
 */
function obligations_acces(?array $u = null): bool {
    $u = $u ?? utilisateur_courant();
    if (!$u || !obligations_pret() || !(int)($u['actif'] ?? 1)) return false;
    return strtolower((string)$u['email']) === GESTION_COMPTE_INITIAL || (($u['role'] ?? '') === 'admin' && (int)$u['id'] === obligations_olivier_id());
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
    if ($d['etat'] === 'attente') {
        // Toujours dans « En attente », mais une échéance passée se voit en rouge (audit n° 7, passe 2, P2-06)
        $dep = (string)$d['echeance'] !== '' && (string)$d['echeance'] < $auj;
        return ['niveau' => 'attente', 'rang' => 2, 'depassee' => $dep, 'texte' => 'Envoyé le ' . date_fr(substr((string)$d['fait_le'], 0, 10), 'longue') . ', réponse attendue'
              . ($dep ? ' · échéance du ' . date_fr((string)$d['echeance'], 'longue') . ' passée' : '')];
    }
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
    // Le filtre « Les miennes » passe avant la limite des 200 dernières (audit n° 7, passe 2, P2-13)
    $l = lignes("SELECT * FROM demarches WHERE etat IN ('" . implode("','", $etats) . "')" . ($qui === 'miennes' ? " AND pour_qui IN (?, 'deux')" : '')
              . ($vue === 'fait' ? " ORDER BY fait_le DESC, id DESC LIMIT 200" : ''), $qui === 'miennes' ? [$moi] : []);
    $auj = aujourdhui();
    foreach ($l as &$d) $d['_etat'] = demarche_etat($d, $auj);
    unset($d);
    if ($vue !== 'fait') usort($l, fn($a, $b) => [$a['_etat']['rang'], $a['echeance'] === '' ? '9999' : $a['echeance'], (int)$a['priorite'], (int)$a['id']]
                                          <=> [$b['_etat']['rang'], $b['echeance'] === '' ? '9999' : $b['echeance'], (int)$b['priorite'], (int)$b['id']]);
    return $l;
}

/** Le nombre rouge de la personne : ses démarches en retard ou dans leur préavis (pas celles en attente d'une réponse). */
function obligations_urgentes(?string $moi = null): int {
    if (!obligations_pret() || ($moi === null && !obligations_acces())) return 0;
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
    // L'identifiant du compte, stable, pour savoir qui a fait quoi (le nom affiché peut changer ; audit n° 7, passe 2, P2-09)
    $u = in_array($par, ['système', 'site'], true) ? null : utilisateur_courant();
    q("INSERT INTO demarches_historique (le, auteur, auteur_id, demarche_id, echeance_id, type, champ, ancien, nouveau) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
      [maintenant(), $par ?? auteur(), $u ? (int)$u['id'] : null, $demarche, $echeance, $type, $champ, $ancien, $nouveau]);
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
/** Ce que la personne a déjà vu, démarche par démarche ou échéance par échéance : [id => dernier événement vu]. */
function obligations_vus(string $quoi): array {
    $u = utilisateur_courant();
    if (!$u) return [];
    $r = [];
    foreach (lignes("SELECT objet, jusqu_a FROM vus WHERE utilisateur_id = ? AND genre = ?", [(int)$u['id'], $quoi]) as $v) $r[(int)$v['objet']] = (int)$v['jusqu_a'];
    return $r;
}
/**
 * Les démarches (ou, avec 'echeance', les échéances) que l'autre compte a ajoutées ou changées et que la personne n'a pas encore vues :
 * [id => 'nouveau'|'change']. Seulement ce qui la concerne (pour elle ou pour les deux), comparé par compte et non par nom,
 * et un objet n'est « vu » que quand il a été affiché (audit n° 7, passe 2, P2-09).
 */
function obligations_changees(string $quoi = 'demarche'): array {
    if (!obligations_acces()) return [];
    $u = utilisateur_courant();
    [$col, $table] = $quoi === 'echeance' ? ['echeance_id', 'echeances'] : ['demarche_id', 'demarches'];
    $vus = obligations_vus($quoi);
    $r = [];
    foreach (lignes("SELECT h.id, h.$col AS obj, h.type, h.champ FROM demarches_historique h JOIN $table t ON t.id = h.$col
                     WHERE h.id > ? AND h.auteur_id IS NOT NULL AND h.auteur_id <> ? AND t.pour_qui IN (?, 'deux')" . ($quoi === 'echeance' ? ' AND h.demarche_id IS NULL' : '') . " ORDER BY h.id",
                    [obligations_vu_jusqua(), (int)$u['id'], obligations_moi($u)]) as $h) {
        $id = (int)$h['obj'];
        if ((int)$h['id'] <= ($vus[$id] ?? 0)) continue;
        $neuf = $h['type'] === 'creation' || ($h['type'] === 'regle' && $h['champ'] === '');
        $r[$id] = $neuf && !isset($r[$id]) ? 'nouveau' : ($r[$id] ?? 'change');
    }
    return $r;
}
/** Marque « vus » les objets affichés (et fixe le plancher au premier passage, pour que rien d'ancien ne s'allume d'un coup). */
function obligations_marquer_vu(string $quoi = 'demarche', array $ids = []): void {
    $u = utilisateur_courant();
    if (!$u) return;
    if (val("SELECT 1 FROM vus WHERE utilisateur_id = ? AND genre = 'obligations' AND objet = 'ecran'", [(int)$u['id']]) === null) pastilles_vu('obligations', 'ecran', obligations_vu_jusqua());
    $ids = array_values(array_unique(array_map('intval', $ids)));
    if (!$ids) return;
    $col = $quoi === 'echeance' ? 'echeance_id' : 'demarche_id';
    foreach (lignes("SELECT $col AS obj, MAX(id) AS m FROM demarches_historique WHERE $col IN (" . implode(',', $ids) . ")" . ($quoi === 'echeance' ? ' AND demarche_id IS NULL' : '') . " GROUP BY $col") as $r)
        pastilles_vu($quoi, (string)(int)$r['obj'], (int)$r['m']);
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
        'echeance' => trim(texte('echeance', 10)),
        'a_confirmer' => isset($_POST['a_confirmer']) ? 1 : 0,
        'preavis' => entier('preavis', 1, 365, OBLIGATIONS_JOURS),
        'priorite' => entier('priorite', 1, 3, 2),
        'importante' => isset($_POST['importante']) ? 1 : 0,
        'origine' => une_ligne(texte('origine', 120)),
    ];
    if ($d['a_confirmer']) $d['echeance'] = '';
    // Une date refusée n'est jamais effacée en silence (audit n° 7, passe 2, P2-10)
    if ($d['echeance'] !== '' && ($ok = date_valide($d['echeance'])) === null) { $d['echeance'] = ''; return [$d, 'Cette date n\'est pas valide : choisissez-la dans le calendrier, ou cochez « Date à confirmer ».']; }
    if ($d['echeance'] !== '') $d['echeance'] = $ok;
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
    if (in_array($etat, ['fait', 'sans_objet'], true) && function_exists(__NAMESPACE__ . '\\probleme_ouvert_de_demarche') && probleme_ouvert_de_demarche($d))
        return 'Cette démarche suit un problème sur un lot encore ouvert : clôturez-le dans Hygiène (fiche du problème) ; la démarche se clôt avec lui.';
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
        'depart' => isset($_POST['a_confirmer']) ? '' : trim(texte('depart', 10)),
        'preavis' => entier('preavis', 1, 365, OBLIGATIONS_JOURS),
        'origine' => une_ligne(texte('origine', 120)),
    ];
    if ($e['depart'] !== '') {
        $ok = date_valide($e['depart']);
        if ($ok === null) { $e['depart'] = ''; return [$e, 'Cette date n\'est pas valide : choisissez-la dans le calendrier, ou cochez « Date à confirmer ».']; }
        $e['depart'] = $ok;
    }
    if ($e['titre'] === '') return [$e, 'Écrivez ce qu\'il faut faire (le titre).'];
    if ($e['ou'] === '') return [$e, 'Dites où le faire : l\'outil, la page et l\'endroit exacts.'];
    if ($e['depart'] === '' && !isset($_POST['a_confirmer'])) return [$e, 'Donnez la date de la prochaine échéance, ou cochez « Date à confirmer ».'];
    return [$e, null];
}
/** Une période fait-elle partie du calendrier d'une échéance ? */
function echeance_a_la_periode(array $e, string $p): bool {
    if ((string)$e['depart'] === '' || !isset(ECHEANCE_PAS[$e['frequence']])) return false;
    for ($i = 0; $i < 1200; $i++) { $q = echeance_periode($e, $i); if ($q === $p) return true; if ($q > $p) return false; }
    return false;
}
/**
 * Après la modification d'une échéance, ses démarches ouvertes (à faire ou en attente) suivent : le titre, le lieu, la personne,
 * la priorité, le préavis… changent aussi chez elles (audit n° 7, passe 2, P2-05). Si la date ou la fréquence a changé, rien n'est
 * clos automatiquement (passe 3, P3-07) : celles qui ne tombent plus sur le nouveau calendrier gardent leur date, portent
 * « Date d'avant la modification » et un mot dans leur historique ; Youngmi ou Olivier décide de les clore ou de les garder.
 * La nouvelle date crée sa démarche au calcul suivant. Rend [mises à jour, à revoir].
 */
function echeance_repercuter(array $avant, array $apres): array {
    $copie = ['titre', 'explication', 'ou', 'pour_qui', 'priorite', 'importante', 'preavis', 'origine'];
    $chg = array_values(array_filter($copie, fn($c) => (string)$avant[$c] !== (string)$apres[$c]));
    $calendrier = (string)$avant['depart'] !== (string)$apres['depart'] || (string)$avant['frequence'] !== (string)$apres['frequence'];
    $maj = 0; $revoir = 0;
    foreach (lignes("SELECT * FROM demarches WHERE echeance_id = ? AND etat IN ('a_faire', 'attente')", [(int)$avant['id']]) as $d) {
        if ($chg) {
            $v = $d; foreach ($chg as $c) $v[$c] = $apres[$c];
            demarche_modifier((int)$d['id'], $v);
            $maj++;
        }
        if ($calendrier && $d['periode'] !== 'a-confirmer' && !echeance_a_la_periode($apres, (string)$d['periode'])) {
            demarche_historique_noter('modification', (int)$d['id'], null, 'calendrier', (string)$d['echeance'], 'échéance modifiée : à revoir');
            $revoir++;
        }
    }
    return [$maj, $revoir];
}
/** La démarche vient d'une échéance dont le calendrier a changé depuis : sa date est celle d'avant (P3-07). */
function demarche_hors_calendrier(array $d): bool {
    if ($d['echeance_id'] === null || $d['periode'] === '' || $d['periode'] === 'a-confirmer' || !in_array($d['etat'], ['a_faire', 'attente'], true)) return false;
    $e = echeance((int)$d['echeance_id']);
    return $e && (string)$e['depart'] !== '' && !echeance_a_la_periode($e, (string)$d['periode']);
}
function echeance_enregistrer(?int $id, array $e, ?array &$compte = null): int {
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
        $compte = echeance_repercuter($avant, ['id' => $id] + $e);
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
    if ($e['frequence'] === 'premier_lundi') {
        // Le premier lundi du mois de départ s'il ne tombe pas avant la date donnée, sinon celui du mois suivant (P3-10)
        $decale = premier_lundi((string)$e['depart']) < (string)$e['depart'] ? 1 : 0;
        return premier_lundi(date_plus_mois(substr((string)$e['depart'], 0, 8) . '01', $n + $decale));
    }
    return date_plus_mois((string)$e['depart'], $n * ECHEANCE_PAS[$e['frequence']]);
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
        // Les périodes dont le préavis a commencé, rien d'avant la création de l'échéance. Le plafond porte sur les périodes
        // MANQUANTES, et ce sont les plus récentes qui sont créées : une règle ancienne continue de produire ses rappels
        // (audit n° 7, passe 2, P2-01).
        $creee = substr((string)$e['cree_le'], 0, 10);
        // Curseur : on ne revient jamais en arrière de la dernière période déjà créée (pas de flot de démarches passées)
        // (seules comptent les périodes du calendrier en vigueur : après un changement de date, l'ancienne ne bloque rien)
        $dernier = '';
        foreach (array_column(lignes("SELECT periode FROM demarches WHERE echeance_id = ? AND periode <> 'a-confirmer' AND etat <> 'sans_objet'", [(int)$e['id']]), 'periode') as $pp)
            if ($pp > $dernier && echeance_a_la_periode($e, (string)$pp)) $dernier = (string)$pp;
        $existantes = array_flip(array_column(lignes("SELECT periode FROM demarches WHERE echeance_id = ?", [(int)$e['id']]), 'periode'));
        $manquantes = [];
        for ($i = 0; $i < 1200; $i++) {
            $p = echeance_periode($e, $i);
            if (date('Y-m-d', (int)strtotime($p . ' -' . (int)$e['preavis'] . ' days')) > $auj) break;
            if ($p < $creee && $p < date('Y-m-d', (int)strtotime($auj . ' -' . (int)$e['preavis'] . ' days'))) continue;
            if ($p > $dernier && !isset($existantes[$p])) $manquantes[] = $p;
        }
        foreach (array_slice($manquantes, -OBLIGATIONS_RATTRAPAGE) as $p)
            if (demarche_creer($commun + ['echeance' => $p, 'a_confirmer' => 0], 'système', ['echeance_id' => (int)$e['id'], 'periode' => $p])) $n++;
    }
    $n += obligations_clients_pro();
    parametre_ecrire('obligations_calcul', maintenant());
    return $n;
}

/* ---------- Nouveau client professionnel : vérifier les obligations (audit n° 7, passe 1 constat 1, passe 2 P2-02 et P2-14) ---------- */

const OBLIGATIONS_PRO_TITRE = 'Nouveau client professionnel : vérifier les obligations';
const OBLIGATIONS_PRO_TITRE_GATEAU = 'Nouveau gâteau pour un client professionnel : vérifier les obligations';
const OBLIGATIONS_PRO_TITRE_MESURE = 'Gâteau sur mesure pour un client professionnel : vérifier les obligations';
const OBLIGATIONS_PRO_TITRE_LIVRAISON = 'Première livraison pour un client professionnel : vérifier les obligations';
const OBLIGATIONS_PRO_OU = 'Gestion : la fiche du client (lien ci-dessus). mesdemarches.agriculture.gouv.fr › Mes démarches : déclaration d\'activité (Cerfa 13984) et dérogation à l\'agrément (Cerfa 13982). Les récépissés dans le Drive : 01 › 09-Certifications, labels et agréments. En cas de doute : la DDPP des Hauts-de-Seine.';
const OBLIGATIONS_PRO_POURQUOI = 'Vendre à un professionnel peut créer de nouvelles obligations ; ce n\'est pas toujours le cas. Avant de confirmer ou de livrer, vérifiez : 1) ce qu\'il fait des gâteaux : un restaurant ou un commerce qui les sert ou les revend (la dérogation à l\'agrément, Cerfa 13982, doit peut-être le compter parmi les destinataires), ou une entreprise qui les offre ou les sert à ses équipes (à vérifier aussi) ; 2) les produits, les quantités par semaine et la distance de livraison ; 3) la déclaration d\'activité et la dérogation déjà faites auprès de la DDPP ; 4) la facture professionnelle (mentions obligatoires). Si une démarche a été faite, collez le lien du récépissé et touchez « C\'est fait ». S\'il n\'y a rien à faire, touchez « Sans objet » et écrivez la raison et sa source (DDPP, Ithea, page officielle).';

/** Les libellés écrits par la gestion elle-même, jamais modifiables : eux seuls vont dans le mail et se traduisent (P3-01, P3-05). */
function obligations_textes_figes(): array {
    return [OBLIGATIONS_PRO_TITRE, OBLIGATIONS_PRO_TITRE_GATEAU, OBLIGATIONS_PRO_TITRE_MESURE, OBLIGATIONS_PRO_TITRE_LIVRAISON, OBLIGATIONS_PRO_OU, OBLIGATIONS_PRO_POURQUOI,
            'Nouveau client professionnel', 'Nouveau gâteau pour un client professionnel', 'Gâteau sur mesure pour un client professionnel', 'Première livraison pour un client professionnel',
            HYGIENE_MANQUANT_TITRE, HYGIENE_MANQUANT_OU, PROBLEME_TITRE, PROBLEME_OU, VOLUMES_TITRE, VOLUMES_OU, DOCUMENTS_TITRE, DOCUMENTS_OU, VOLUMES_KM_TITRE, VOLUMES_KM_OU];
}
/** Un texte écrit par la gestion (traduit en coréen), jamais un texte tapé : les libellés figés et le jour d'un relevé manquant. */
function obligations_texte_fige(string $t): bool {
    return in_array($t, obligations_textes_figes(), true) || (bool)preg_match('/^Jour concerné : \d{1,2} \p{L}+ \d{4}\.$/u', $t) || (bool)preg_match('/^Lot \d{6}-[A-Z0-9]+-\d+\.$/', $t)
        || (bool)preg_match('/^Semaine du \d{1,2} \p{L}+ \d{4}\.$/u', $t) || (bool)preg_match('/^Document n° \d+\.$/u', $t)
        || (bool)preg_match('/^Restaurant n° \d+\.$/u', $t);
}

/** La date pour vérifier : la veille du prochain retrait ou de la prochaine livraison de ce client, sinon dans 7 jours ; jamais avant aujourd'hui. */
function obligations_pro_date(int $cid): string {
    $auj = aujourdhui();
    $p = val("SELECT MIN(retrait_date) FROM commandes WHERE client_id = ? AND statut IN ('demande', 'proposition', 'confirmee') AND retrait_date IS NOT NULL AND retrait_date >= ?", [$cid, $auj]);
    $d = $p ? date('Y-m-d', (int)strtotime($p . ' -1 day')) : date('Y-m-d', (int)strtotime($auj . ' +7 days'));
    return max($d, $auj);
}
/** Un client est-il professionnel (fiche « professionnel » ou une commande pro qui n'est ni annulée ni sans suite) ? */
function obligations_client_est_pro(int $cid): bool {
    return (bool)val("SELECT 1 FROM clients WHERE id = ? AND statut <> 'efface' AND (type = 'professionnel'
                      OR EXISTS (SELECT 1 FROM commandes WHERE client_id = clients.id AND type = 'pro' AND statut NOT IN ('annulee', 'sans_suite')))", [$cid]);
}
/**
 * Dès qu'un client est reconnu professionnel, une seule démarche « vérifier les obligations », importante, pour la veille de sa
 * prochaine livraison. Le titre ne contient aucun nom : le client est relié par son identifiant et son nom ne s'affiche que dans la
 * gestion (jamais dans le mail du lundi). Rend l'identifiant de la démarche créée, ou 0.
 */
function obligations_client_pro(int $cid): int {
    if (!obligations_pret() || !obligations_client_est_pro($cid)) return 0;
    return demarche_creer([
        'titre' => OBLIGATIONS_PRO_TITRE, 'explication' => OBLIGATIONS_PRO_POURQUOI, 'ou' => OBLIGATIONS_PRO_OU,
        'pour_qui' => 'youngmi', 'echeance' => obligations_pro_date($cid), 'a_confirmer' => 0, 'preavis' => OBLIGATIONS_JOURS, 'priorite' => 1, 'importante' => 1,
        'origine' => 'Nouveau client professionnel',
    ], 'système', ['declencheur' => 'pro:' . $cid, 'ref_genre' => 'client', 'ref_id' => (string)$cid]);
}
/**
 * À la création et à la confirmation d'une commande : la vérification du client pro si elle n'existe pas encore, puis, une fois cette
 * première vérification close, une nouvelle vérification pour chaque gâteau qu'il n'a encore jamais commandé (changement important :
 * produit, quantités, conservation). Une seule par client et par gâteau, jamais de rappel répété.
 */
function obligations_commande_pro(array $k): void {
    if (!obligations_pret()) return;
    $cid = (int)$k['client_id'];
    if (!obligations_client_est_pro($cid)) return;
    obligations_client_pro($cid);
    $base = ligne("SELECT * FROM demarches WHERE declencheur = ?", ['pro:' . $cid]);
    if (!$base || in_array($base['etat'], ['a_faire', 'attente'], true)) return;   // la première vérification couvre encore tout
    // Ce qui a été vu à la première vérification : les commandes de ce client déjà reçues quand elle a été close (P3-09)
    $deja = [];
    foreach (lignes("SELECT lignes FROM commandes WHERE client_id = ? AND statut NOT IN ('annulee', 'sans_suite') AND (cree_le <= ? OR (id <> ? AND statut IN ('confirmee', 'remise')))",
                    [$cid, (string)$base['fait_le'], (int)$k['id']]) as $o)
        foreach (json_decode((string)$o['lignes'], true) ?: [] as $l) $deja[explode('|', (string)($l['v'] ?? ''))[0]] = true;
    $verifier = function (string $suffixe, string $titre, string $origine) use ($cid): void {
        demarche_creer(['titre' => $titre, 'explication' => OBLIGATIONS_PRO_POURQUOI, 'ou' => OBLIGATIONS_PRO_OU, 'pour_qui' => 'youngmi', 'echeance' => obligations_pro_date($cid),
                        'a_confirmer' => 0, 'preavis' => OBLIGATIONS_JOURS, 'priorite' => 1, 'importante' => 1, 'origine' => $origine],
                       'système', ['declencheur' => 'pro:' . $cid . ':' . $suffixe, 'ref_genre' => 'client', 'ref_id' => (string)$cid]);
    };
    $mesure = trim((string)($k['perso'] ?? '')) !== '' || trim((string)($k['besoin'] ?? '')) !== '';
    foreach (json_decode((string)($k['lignes'] ?? '[]'), true) ?: [] as $l) {
        $prod = explode('|', (string)($l['v'] ?? ''))[0];
        if ($prod === '') { $mesure = true; continue; }           // gâteau sans fiche : traité comme sur mesure (P3-02)
        if (isset($deja[$prod])) continue;
        $deja[$prod] = true;
        $verifier($prod, OBLIGATIONS_PRO_TITRE_GATEAU, 'Nouveau gâteau pour un client professionnel');
    }
    if ($mesure) $verifier('mesure:' . (int)$k['id'], OBLIGATIONS_PRO_TITRE_MESURE, 'Gâteau sur mesure pour un client professionnel');
    // Première livraison (la distance compte pour la dérogation) : une fois par client
    if (($k['mode'] ?? '') === 'livraison' && !val("SELECT 1 FROM commandes WHERE client_id = ? AND id <> ? AND mode = 'livraison' AND statut IN ('confirmee', 'remise') AND cree_le <= ?", [$cid, (int)$k['id'], (string)$base['fait_le']]))
        $verifier('livraison', OBLIGATIONS_PRO_TITRE_LIVRAISON, 'Première livraison pour un client professionnel');
}
/** Rattrapage (nuit et ouverture de l'écran) : chaque client professionnel a sa vérification, même s'il l'est devenu par sa fiche. */
function obligations_clients_pro(): int {
    $n = 0;
    foreach (lignes("SELECT id FROM clients c WHERE statut <> 'efface' AND (type = 'professionnel'
                     OR EXISTS (SELECT 1 FROM commandes WHERE client_id = c.id AND type = 'pro' AND statut NOT IN ('annulee', 'sans_suite')))
                     AND NOT EXISTS (SELECT 1 FROM demarches WHERE declencheur = 'pro:' || c.id)") as $c)
        if (obligations_client_pro((int)$c['id'])) $n++;
    return $n;
}

/* ---------- Tâche de nuit : occurrences, conservation, mail interne du lundi ---------- */

function obligations_nuit(): array {
    if (!obligations_pret()) return [];
    $cr = ['crees' => obligations_generer()];
    try { $cr['hygiene'] = hygiene_nuit(); }   // relevés manquants et conservation des relevés (lot 2)
    catch (\Throwable $e) { error_log('madamecho gestion : hygiène : ' . $e->getMessage()); $cr['hygiene'] = ['erreur' => $e->getMessage()]; }
    try { $cr['volumes'] = volumes_nuit(); }   // alertes de volumes et documents à renouveler (lot 4)
    catch (\Throwable $e) { error_log('madamecho gestion : volumes : ' . $e->getMessage()); $cr['volumes'] = ['erreur' => $e->getMessage()]; }
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
/**
 * Le titre d'une démarche dans le mail du lundi : seulement un titre contrôlé (écrit par la gestion, venu d'une échéance ou de la
 * liste de départ relue par Youngmi et Olivier) ; une démarche tapée à la main n'y figure que par « Démarche ajoutée à la main ».
 * Jamais d'explication, d'origine, de note ni de nom de client (exception R-01 limitée ; audit n° 7, passe 2, P2-03).
 */
function obligations_titre_mail(array $d): string {
    // Seulement un libellé figé de la gestion ; tout titre tapé (démarche, échéance, liste reprise) devient « Démarche n° … » (P3-01)
    return in_array((string)$d['titre'], obligations_textes_figes(), true) ? (string)$d['titre'] : 'Démarche n° ' . (int)$d['id'];
}
function obligations_mail_texte(array $l): string {
    $t = "Obligations de la semaine (gestion Madame Cho, " . date_fr(aujourdhui(), 'longue') . ")\n\n";
    // Liste de départ pas encore reprise ou relue : ne jamais conclure « rien à faire » (P3-08)
    if (obligations_liste_a_valider()) $t .= "ATTENTION : la liste de départ n'est pas encore " . ((string)parametre('obligations_reprise_le', '') === '' ? 'reprise' : 'relue par Youngmi et Olivier') . " : cette liste n'est pas complète, aucune conclusion possible.\n\n";
    if (!$l) return $t . "Rien en retard ni à faire dans les " . OBLIGATIONS_JOURS . " prochains jours parmi les démarches connues.\nLe détail : gestion, Plus › Obligations.\n";
    foreach (['retard' => 'En retard', 'bientot' => 'À faire bientôt', 'confirmer' => 'Date à confirmer', 'attente' => 'Envoyé, réponse attendue'] as $niv => $titre) {
        $g = array_filter($l, fn($d) => $d['_etat']['niveau'] === $niv);
        if (!$g) continue;
        $t .= mb_strtoupper($titre) . "\n";
        foreach ($g as $d) $t .= '- ' . obligations_titre_mail($d) . ' (' . DEMARCHE_POUR[$d['pour_qui']] . ') : ' . $d['_etat']['texte'] . "\n";
        $t .= "\n";
    }
    return $t . "Le détail (et le client concerné, s'il y en a un) : gestion, Plus › Obligations.\nMail interne de la gestion, envoyé à contact@ seulement, chaque lundi (choix d'Olivier du 08/11/2026).\n";
}
/**
 * Le lundi, un mail interne de site@ à contact@ seulement, pour le récap de Ferdinand (choix d'Olivier du 08/11/2026, exception à R-01
 * limitée à ce mail ; jamais à un client ; signé DKIM « site »). Une fois par semaine au plus. Rend 'envoye', 'deja', 'pas_lundi' ou 'echec'.
 */
function obligations_mail_lundi(?string $auj = null): string {
    $auj = $auj ?? aujourdhui();
    if ((int)date('N', (int)strtotime($auj)) !== 1) return 'pas_lundi';
    $cle = 'obligations_mail_' . date('o-\SW', (int)strtotime($auj));
    // La semaine est réservée AVANT l'envoi, d'un seul geste en base : deux passages en même temps n'envoient jamais deux mails
    // (audit n° 7, passe 2, P2-07). Un échec garde la réservation (pas de nouvel essai la même semaine) et se voit dans Paramètres.
    q("INSERT OR IGNORE INTO parametres (cle, valeur) VALUES (?, ?)", [$cle, 'en cours ' . maintenant()]);
    if ((int)q("SELECT changes()")->fetchColumn() !== 1) return 'deja';
    $texte = obligations_mail_texte(obligations_resume());
    if (GESTION_ESSAI) { parametre_ecrire('essai_mail_obligations', $texte); $ok = true; }
    else $ok = envoyer_email(GESTION_COMPTE_INITIAL, 'Obligations de la semaine · gestion Madame Cho', $texte);
    parametre_ecrire($cle, ($ok ? 'envoyé ' : 'échec ') . maintenant());
    return $ok ? 'envoye' : 'echec';
}

/** L'état du dernier mail du lundi, pour Paramètres (P3-06) : [semaine, valeur] ou null. */
function obligations_mail_etat(): ?array {
    $r = ligne("SELECT cle, valeur FROM parametres WHERE cle LIKE 'obligations_mail_____-S__' ORDER BY cle DESC LIMIT 1");
    return $r ? [substr((string)$r['cle'], 17), (string)$r['valeur']] : null;
}
/**
 * Renvoi À LA MAIN (toucher d'un administrateur dans Paramètres) du mail de la semaine dont l'envoi a échoué. Une seule fois :
 * la réservation repasse « en cours » d'un seul geste, seulement si elle était en échec. Rend 'envoye', 'echec' ou 'rien'.
 */
function obligations_mail_relancer(): string {
    $e = obligations_mail_etat();
    if (!$e || !str_starts_with($e[1], 'échec')) return 'rien';
    $cle = 'obligations_mail_' . $e[0];
    q("UPDATE parametres SET valeur = ? WHERE cle = ? AND valeur LIKE 'échec%'", ['en cours ' . maintenant(), $cle]);
    if ((int)q("SELECT changes()")->fetchColumn() !== 1) return 'rien';
    $texte = obligations_mail_texte(obligations_resume());
    if (GESTION_ESSAI) { parametre_ecrire('essai_mail_obligations', $texte); $ok = true; }
    else $ok = envoyer_email(GESTION_COMPTE_INITIAL, 'Obligations de la semaine · gestion Madame Cho', $texte);
    parametre_ecrire($cle, ($ok ? 'envoyé ' : 'échec ') . maintenant());
    return $ok ? 'envoye' : 'echec';
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
