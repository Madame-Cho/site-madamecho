<?php
/*
 * Madame Cho : espace de gestion. L'agenda (demande d'Olivier du 07/11/2026) : les retraits et les livraisons du mois,
 * jour par jour, chaque commande menant à sa page ; les indispos de Youngmi (jours entiers ou plages horaires), à ajouter,
 * modifier ou supprimer ici. La gestion prévient quand une commande en cours tombe sur une indispo.
 * Le formulaire « Ma demande » du site en tient compte aussi (2e version, 07/11/2026) : un jour ou une plage indisponible y est refusé,
 * sans le motif (plages_pour_demande, dans enregistrer.php).
 * Les plages de retrait (spécification 007, 27/11/2026) : pour le jour choisi, chaque plage dit combien de retraits confirmés elle a
 * sur son maximum ; Youngmi peut la fermer, la rouvrir ou changer son maximum. Une plage complète ou fermée n'est plus proposée sur le site.
 */
declare(strict_types=1);
namespace MadameCho\Gestion;

const JOURS_SEMAINE = ['lun.', 'mar.', 'mer.', 'jeu.', 'ven.', 'sam.', 'dim.'];   // la semaine commence le lundi

function page_agenda(): void {
    // Le lien « Fichier agenda (.ics) » d'une commande garde son adresse (?p=agenda&id=…)
    if (isset($_GET['id']) || isset($_GET['ref'])) { page_agenda_ics(); return; }
    $auj = aujourdhui();
    $moisLu = texte('mois', 7, $_GET);
    $jour = date_valide(texte('jour', 10, $_GET));
    $mois = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $moisLu) ? $moisLu : ($jour ? substr($jour, 0, 7) : substr($auj, 0, 7));
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        csrf_verifier();
        $a = texte('_action', 40);
        $id = entier('indispo', 1);
        if ($a === 'indispo_supprimer') {
            indispo_supprimer($id);
            flash('ok', 'Indispo supprimée.');
            rediriger('agenda', ['mois' => $mois] + ($jour ? ['jour' => $jour] : []), 'indispos');
        }
        if ($a === 'indispo_ajouter' || ($a === 'indispo_modifier' && indispo($id))) {
            [$d, $erreur] = indispo_lue();
            if ($erreur !== null) {
                flash('erreur', $erreur);
                rediriger('agenda', ['mois' => $mois] + ($jour ? ['jour' => $jour] : []) + ($a === 'indispo_ajouter' ? ['ajouter' => '1'] : []), $a === 'indispo_ajouter' ? 'ajouter' : 'indispos');
            }
            indispo_enregistrer($a === 'indispo_modifier' ? $id : null, $d);
            // Les commandes en cours qui tombent dessus : à voir avec le client
            $touchees = array_filter(agenda_commandes($d['debut'], $d['fin']), fn($k) => indispo_de_commande($k) !== null);
            $n = count($touchees);
            flash($n ? 'erreur' : 'ok', ($a === 'indispo_ajouter' ? 'Indispo ajoutée.' : 'Indispo modifiée.')
                . ($n ? ' Attention : ' . $n . ($n > 1 ? ' commandes en cours tombent' : ' commande en cours tombe') . ' dessus, à voir avec le client.' : ''));
            rediriger('agenda', ['mois' => substr($d['debut'], 0, 7), 'jour' => $d['debut']], 'jour');
        }
        if ($a === 'plage') {   // fermer, rouvrir ou changer le maximum d'une plage d'un jour (spécification 007)
            $pj = date_valide(texte('plage_jour', 10));
            $code = texte('plage', 4);
            $op = choix('op', ['fermer' => 1, 'rouvrir' => 1, 'maximum' => 1, 'defaut' => 1], '');
            $n = entier('maximum', 0, 20, 0);
            // Un maximum choisi reste celui du jour, même s'il vaut le maximum par défaut : seul « Revenir à … » l'enlève (audit n° 10, C10-09)
            $max = $op === 'maximum' && $n >= 1 ? $n : null;
            if (!$pj || !isset(PLAGES[$code]) || $op === '' || ($op === 'maximum' && $n < 1)) flash('erreur', 'Choisissez un maximum entre 1 et 20.');
            elseif (plage_regler($pj, $code, $op === 'defaut' ? 'maximum' : $op, $op === 'defaut' ? null : $max)) {
                $quoi = 'La plage ' . PLAGES_COURTES[$code] . ' du ' . date_fr($pj, 'jour');
                $e = plages_du_jour($pj)[$code];
                // Les retraits déjà confirmés ne bougent jamais : on le dit (C10-09)
                $gardes = $e['n'] ? ' ' . ($e['n'] > 1 ? 'Les ' . $e['n'] . ' retraits déjà confirmés sont maintenus.' : 'Le retrait déjà confirmé est maintenu.') : '';
                flash('ok', match ($op) {
                    'fermer' => $quoi . ' est fermée aux nouvelles demandes : les clients ne peuvent plus la choisir sur le site.' . $gardes,
                    'rouvrir' => $quoi . ' est rouverte.' . ($e['indispo'] !== null ? ' Une indispo la touche encore : le site ne la propose pas.'
                                 : ($e['n'] >= $e['maximum'] ? ' Elle reste complète (' . $e['n'] . ' sur ' . $e['maximum'] . ') : augmentez son maximum pour la proposer de nouveau sur le site.' : '')),
                    default => $quoi . ' accepte maintenant ' . $e['maximum'] . ' retrait' . ($e['maximum'] > 1 ? 's' : '') . ' au plus' . ($max === null ? ' (le maximum par défaut).' : '.')
                               . ($e['n'] > $e['maximum'] ? $gardes : ''),
                });
            }
            rediriger('agenda', ['mois' => substr($pj ?? $mois, 0, 7), 'jour' => $pj ?? $jour], 'plages');
        }
        rediriger('agenda', ['mois' => $mois]);
    }
    $premier = $mois . '-01';
    $dernier = date('Y-m-t', strtotime($premier));
    if (!$jour || substr($jour, 0, 7) !== $mois) $jour = substr($auj, 0, 7) === $mois ? $auj : $premier;
    // La grille va du lundi de la première semaine au dimanche de la dernière
    $debutGrille = date('Y-m-d', strtotime($premier . ' -' . ((int)date('N', strtotime($premier)) - 1) . ' days'));
    $finGrille = date('Y-m-d', strtotime($dernier . ' +' . (7 - (int)date('N', strtotime($dernier))) . ' days'));
    $parJour = [];
    foreach (agenda_commandes($debutGrille, $finGrille) as $k) $parJour[$k['retrait_date']][] = $k;
    $indispos = indispos_entre($debutGrille, $finGrille);

    $c = titre_page('Agenda', 'Retraits, livraisons et indispos', lien_bouton(u('agenda', ['mois' => $mois, 'jour' => $jour, 'ajouter' => '1']) . '#ajouter', icone('ajouter') . 'Ajouter une indispo', 'discret'));
    // Le mois : précédent, suivant, revenir à aujourd'hui
    $prec = date('Y-m', strtotime($premier . ' -1 month'));
    $suiv = date('Y-m', strtotime($premier . ' +1 month'));
    $c .= '<nav class="agenda-nav" aria-label="Mois">'
        . '<a class="agenda-fleche" href="' . h(u('agenda', ['mois' => $prec])) . '" aria-label="Mois précédent">' . icone('retour') . '</a>'
        . '<h2 class="agenda-mois">' . h(mois_annee($premier)) . '</h2>'
        . '<a class="agenda-fleche" href="' . h(u('agenda', ['mois' => $suiv])) . '" aria-label="Mois suivant">' . icone('suivant') . '</a>'
        . ($jour !== $auj ? '<a class="btn discret agenda-auj" href="' . h(u('agenda')) . '#jour">Aujourd\'hui</a>' : '')
        . '</nav>';
    // La grille du mois : sur téléphone, un point par commande ; sur ordinateur, l'heure et le client
    $tete = '';
    foreach (JOURS_SEMAINE as $j) $tete .= '<span>' . h($j) . '</span>';
    $cases = '';
    for ($d = $debutGrille; $d <= $finGrille; $d = date('Y-m-d', strtotime($d . ' +1 day'))) {
        $kj = $parJour[$d] ?? [];
        $ij = indispos_du_jour($indispos, $d);
        $journee = (bool)array_filter($ij, fn($i) => indispo_journee($i));
        $classes = 'jour-agenda' . (substr($d, 0, 7) !== $mois ? ' hors-mois' : '') . ($d < $auj ? ' passe' : '') . ($d === $auj ? ' aujourdhui' : '') . (jour_ferme($d) !== '' ? ' jour-ferme' : '')
                 . ($d === $jour ? ' choisi' : '') . ($journee ? ' indispo-jour' : ($ij ? ' indispo-partielle' : ''));
        $evts = '';
        foreach ($ij as $i) {
            $evts .= '<span class="evt evt-indispo"><span>Indispo</span>' . (indispo_journee($i) ? '' : ' <span class="evt-h">' . h(plage_texte($i['heure_debut'], $i['heure_fin'])) . '</span>') . '</span>';
        }
        foreach (array_slice($kj, 0, 3) as $k) {
            $evts .= '<span class="evt e-' . h(str_replace('_', '-', $k['statut'])) . '">' . (($h = heure_agenda($k)) !== '' ? '<span class="evt-h">' . h($h) . '</span> ' : '')
                   . '<b translate="no">' . h(commande_client_nom($k)) . '</b></span>';
        }
        if (count($kj) > 3) $evts .= '<span class="evt-plus">+' . (count($kj) - 3) . '</span>';
        $points = '';
        foreach (array_slice($kj, 0, 4) as $k) $points .= '<i class="e-' . h(str_replace('_', '-', $k['statut'])) . '"></i>';
        if (count($kj) > 4) $points .= '<i class="e-plus"></i>';
        $resume = [date_fr($d, 'jour'), $kj ? count($kj) . (count($kj) > 1 ? ' commandes' : ' commande') : 'aucune commande'];
        if ($ij) $resume[] = 'indispo';
        $cases .= '<a class="' . $classes . '" href="' . h(u('agenda', ['mois' => substr($d, 0, 7), 'jour' => $d])) . '#jour" aria-label="' . h(implode(' · ', $resume)) . '"'
                . ($d === $auj ? ' aria-current="date"' : '') . '><span class="num">' . (int)substr($d, 8, 2) . '</span>'
                . ($evts !== '' ? '<span class="evts">' . $evts . '</span>' : '') . ($points !== '' ? '<span class="points" aria-hidden="true">' . $points . '</span>' : '') . '</a>';
    }
    $legende = '<p class="agenda-legende">';
    foreach (['demande', 'proposition', 'confirmee', 'remise'] as $s) $legende .= '<span class="l-' . h($s) . '">' . h(STATUTS_COURTS[$s]) . '</span>';
    $legende .= '<span class="l-indispo">Indispo</span></p>';
    $c .= '<div class="agenda"><div class="agenda-tete" aria-hidden="true">' . $tete . '</div><div class="agenda-jours">' . $cases . '</div></div>' . $legende;
    $sansDate = (int)val("SELECT COUNT(*) FROM commandes WHERE statut IN ('demande', 'proposition', 'confirmee') AND (retrait_date IS NULL OR retrait_date = '')");
    if ($sansDate) $c .= '<p class="aide agenda-sans-date">Sans date de retrait : <a href="' . h(u('commandes', ['vue' => 'en_cours'])) . '">' . $sansDate . ' commande(s) en cours</a></p>';

    // Le jour choisi : ses indispos, puis ses commandes (chacune ouvre sa page)
    $kj = $parJour[$jour] ?? [];
    $ij = indispos_du_jour($indispos, $jour);
    $p = '';
    foreach ($ij as $i) $p .= ligne_indispo($i);
    $p .= liste_commandes($kj, 'Aucune commande ce jour-là.');
    $actions = '';
    if (array_filter($kj, fn($k) => $k['statut'] === 'confirmee')) $actions .= lien_bouton(u('production', ['du' => $jour, 'jours' => '1']), icone('imprimer') . 'Fiche de production', 'discret');
    if (!$ij) $actions .= lien_bouton(u('agenda', ['mois' => $mois, 'jour' => $jour, 'ajouter' => '1']) . '#ajouter', icone('ajouter') . 'Indispo ce jour-là', 'discret');
    $c .= bloc(h(ucfirst(date_fr($jour, 'jour'))) . ($kj ? ' <span class="compte">' . count($kj) . '</span>' : ''), $p . ($actions !== '' ? '<div class="actions">' . $actions . '</div>' : ''), 'agenda-jour', 'jour');
    $c .= bloc_plages($jour, $auj, $kj);

    // Les indispos à venir, et le formulaire pour en ajouter une
    $aVenir = lignes("SELECT * FROM indispos WHERE fin >= ? ORDER BY debut, heure_debut, id LIMIT 60", [$auj]);
    $liste = '';
    foreach ($aVenir as $i) $liste .= ligne_indispo($i);
    $depart = max($jour, $auj);
    $nouvelle = ['debut' => $depart, 'fin' => $depart, 'heure_debut' => '', 'heure_fin' => '', 'motif' => ''];
    $ajout = '<details class="replie" id="ajouter"' . (($_GET['ajouter'] ?? '') === '1' ? ' open' : '') . '><summary>' . icone('ajouter') . 'Ajouter une indispo</summary>'
           . formulaire('indispo_ajouter', champs_indispo($nouvelle, 'n') . bouton('Ajouter l\'indispo', 'principal'), ['class' => 'form-indispo']) . '</details>';
    $c .= bloc('Indispos à venir', ($liste !== '' ? '<div class="indispos">' . $liste . '</div>' : '<p class="vide">Aucune indispo à venir.</p>')
        . '<p class="aide">Un jour entier ou une plage horaire (de 14 h à 18 h, par exemple), sur un ou plusieurs jours. La gestion prévient si une commande tombe dessus, et le formulaire du site refuse ces jours et ces moments, sans jamais dire le motif.</p>'
        . $ajout, '', 'indispos');
    mise_en_page('Agenda', $c, 'agenda', 'page-agenda');
}

/** Les plages du jour choisi (spécification 007) : « 2 sur 5 » par plage (retraits confirmés ou remis), fermer, rouvrir, maximum.
 *  Le dimanche et le 24 décembre : fermé au public, rien à régler. Un jour passé : lecture seule. */
function bloc_plages(string $jour, string $auj, array $kj = []): string {
    $ferme = jour_ferme($jour);
    if ($ferme !== '') {
        return bloc('Plages de retrait', '<p class="aide">' . ($ferme === 'dimanche'
            ? 'Le dimanche, Madame Cho est fermée : le site ne propose aucune plage. Un cas particulier convenu par téléphone se saisit dans « Nouvelle commande ».'
            : 'Jour fermé au public : le site ne propose aucune plage.') . '</p>', 'plages', 'plages');
    }
    $modifiable = $jour >= $auj;
    $defaut = plage_maximum_defaut();
    // Les commandes du jour rangées dans leur plage (FR-011, audit n° 10, C10-08) : les retraits confirmés ou remis, puis les demandes et
    // propositions en attente (elles ne prennent pas de place) ; un retrait confirmé sans plage ni heure est « à préciser »
    $parPlage = []; $sansPlage = [];
    foreach ($kj as $k) {
        if (($k['mode'] ?? 'retrait') !== 'retrait' || !empty($k['efface_le'])) continue;
        $pc = plage_de_commande($k);
        $quoi = commande_prend_place($k) ? 'places' : 'attente';
        if ($pc === null) { if ($quoi === 'places') $sansPlage[] = $k; continue; }
        $parPlage[$pc][$quoi][] = $k;
    }
    $lien = fn(array $k): string => '<a class="plage-cmd e-' . h(str_replace('_', '-', $k['statut'])) . '" href="' . h(u('commande', ['id' => $k['id']])) . '">'
        . (($hp = heure_valide((string)$k['retrait_heure'])) !== '' ? '<span class="evt-h">' . h(str_replace(':', ' h ', $hp)) . '</span> ' : '')
        . '<b translate="no">' . h(commande_client_nom($k)) . '</b></a>';
    $l = '';
    foreach (plages_du_jour($jour) as $code => $p) {
        $code = (string)$code;
        $cle = $p['fermee'] ? 'fermee' : ($p['indispo'] !== null ? 'indispo' : ($p['n'] > $p['maximum'] ? 'depassee' : ($p['n'] >= $p['maximum'] ? 'complete' : 'libre')));
        $etat = ['fermee' => 'Fermée', 'indispo' => 'Indispo', 'depassee' => 'Dépassée', 'complete' => 'Complète', 'libre' => 'Libre'][$cle];
        $cmds = '';
        if (!empty($parPlage[$code]['places'])) $cmds .= '<span class="plage-cmds-liste">' . implode('', array_map($lien, $parPlage[$code]['places'])) . '</span>';
        if (!empty($parPlage[$code]['attente'])) $cmds .= '<span class="plage-cmds-liste plage-attente"><span class="petit">En attente, sans place prise :</span> ' . implode('', array_map($lien, $parPlage[$code]['attente'])) . '</span>';
        $compte = fr_ko($p['n'] . ' sur ' . $p['maximum'], $p['n'] . '명 / 최대 ' . $p['maximum'] . '명');
        $cache = '<input type="hidden" name="plage_jour" value="' . h($jour) . '"><input type="hidden" name="plage" value="' . h($code) . '">';
        $act = '';
        if ($modifiable) {
            $act = formulaire('plage', $cache . '<input type="hidden" name="op" value="' . ($p['fermee'] ? 'rouvrir' : 'fermer') . '">'
                     . bouton($p['fermee'] ? 'Rouvrir' : 'Fermer', $p['fermee'] ? 'secondaire' : 'discret', ['aria-label' => ($p['fermee'] ? 'Rouvrir' : 'Fermer') . ' la plage ' . PLAGES_COURTES[$code]]), ['class' => 'plage-form'])
                 . '<details class="mini plage-max"><summary>Maximum</summary>'
                 . formulaire('plage', $cache . '<input type="hidden" name="op" value="maximum">'
                     . champ('maximum', 'Retraits au plus', (string)$p['maximum'], ['type' => 'number', 'min' => '1', 'max' => '20', 'step' => '1', 'inputmode' => 'numeric', 'required' => true, 'id' => 'f-max-' . $code])
                     . bouton('Enregistrer', 'secondaire'), ['class' => 'plage-form'])
                 . ($p['particulier'] ? formulaire('plage', $cache . '<input type="hidden" name="op" value="defaut">' . bouton('Revenir à ' . $defaut . ' (par défaut)', 'discret'), ['class' => 'plage-form']) : '')
                 . '</details>';
        }
        $l .= '<div class="plage-ligne p-' . $cle . '"><span class="plage-h">' . h(PLAGES_COURTES[$code]) . '</span>'
            . '<span class="plage-etat"><b>' . h($compte) . '</b> · ' . h($etat) . ($p['particulier'] ? ' · maximum du jour' : '') . '</span>'
            . ($act !== '' ? '<span class="plage-actions">' . $act . '</span>' : '')
            . ($cmds !== '' ? '<span class="plage-cmds">' . $cmds . '</span>' : '') . '</div>';
    }
    if ($sansPlage) $l .= '<div class="plage-ligne p-apreciser"><span class="plage-h">À préciser</span><span class="plage-etat">Retraits confirmés sans plage ni heure : '
                       . 'ouvrez la commande et choisissez sa plage avec le client.</span><span class="plage-cmds"><span class="plage-cmds-liste">' . implode('', array_map($lien, $sansPlage)) . '</span></span></div>';
    $aide = '<p class="aide">Retraits confirmés sur le maximum de la plage (' . $defaut . ' par défaut, à changer dans Plus › Paramètres). '
          . 'Une demande ou une proposition ne prend pas de place. Une plage complète ou fermée n\'est plus proposée sur le site ; dans la gestion, vous pouvez toujours y mettre une commande, '
          . 'en exception convenue avec le client. Fermer une plage ou baisser son maximum n\'annule aucun retrait déjà confirmé.</p>';
    return bloc('Plages de retrait', '<div class="plages-jour">' . $l . '</div>' . $aide, 'plages', 'plages');
}

/** L'heure d'une commande dans la grille : l'heure convenue (« 10 h 30 »), sinon le moment demandé (« matin »). */
function heure_agenda(array $k): string {
    if (preg_match('/^(\d{2}):(\d{2})$/', (string)$k['retrait_heure'], $m)) return $m[1] . ' h ' . $m[2];
    return MOMENTS_COURTS[$k['retrait_moment']] ?? '';
}

/** Une indispo dans une liste : ses jours, sa plage, son motif ; « Modifier » ouvre le formulaire, avec « Supprimer ». */
function ligne_indispo(array $i): string {
    $id = (int)$i['id'];
    $cache = '<input type="hidden" name="indispo" value="' . $id . '">';
    $modifier = formulaire('indispo_modifier', $cache . champs_indispo($i, 'm' . $id) . bouton('Enregistrer', 'secondaire'), ['class' => 'form-indispo'])
              . formulaire('indispo_supprimer', $cache . bouton('Supprimer cette indispo', 'danger'), ['data-confirmer' => 'Supprimer cette indispo ?', 'class' => 'form-supprimer']);
    return '<div class="indispo"><div class="indispo-texte"><b>' . h(indispo_jours_texte($i)) . '</b>'
         . '<span>' . h(indispo_journee($i) ? 'Toute la journée' : plage_texte($i['heure_debut'], $i['heure_fin'])) . '</span>'
         . ($i['motif'] !== '' ? '<span class="petit" translate="no">' . h($i['motif']) . '</span>' : '') . '</div>'
         . '<details class="mini"><summary>Modifier</summary>' . $modifier . '</details></div>';
}

/** Les champs d'une indispo (ajout ou modification) ; $id rend chaque champ unique dans la page. */
function champs_indispo(array $i, string $id): string {
    $plage = !indispo_journee($i);
    return '<div class="ligne-champs">' . champ('debut', 'Du', $i['debut'], ['type' => 'date', 'required' => true, 'id' => $id . '-debut'])
         . champ('fin', 'Jusqu\'au (compris)', $i['fin'] !== $i['debut'] ? $i['fin'] : '', ['type' => 'date', 'id' => $id . '-fin'], 'Vide : un seul jour.') . '</div>'
         . liste_choix('quand', 'Quand', ['journee' => 'Toute la journée', 'plage' => 'Une plage horaire'], $plage ? 'plage' : 'journee', ['id' => $id . '-quand'])
         . '<div class="ligne-champs" data-si="' . h($id) . '-quand=plage">'
         . champ('heure_debut', 'De', $i['heure_debut'], ['type' => 'time', 'step' => '900', 'id' => $id . '-hd'])
         . champ('heure_fin', 'À', $i['heure_fin'], ['type' => 'time', 'step' => '900', 'id' => $id . '-hf']) . '</div>'
         . champ('motif', 'Motif (facultatif)', $i['motif'], ['maxlength' => '80', 'placeholder' => 'Vacances, salon, déjà complet…', 'id' => $id . '-motif'], 'Visible dans la gestion seulement.');
}
