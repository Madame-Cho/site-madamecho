<?php
/*
 * Madame Cho : espace de gestion. Les pages de l'écran « Obligations » (lot 1 : À faire et Échéances ; spécification 004).
 * Plus › Obligations : la liste de la personne (« Les miennes ») ou de tout le monde (« Toutes »), la plus urgente d'abord ;
 * une démarche s'ouvre sur sa fiche (quoi, où, pour quand, pourquoi, preuve, historique). Youngmi et Olivier ajoutent et modifient.
 * Les textes saisis ne sont jamais traduits (translate="no") ; le reste passe par inc/coreen.php.
 */
declare(strict_types=1);
namespace MadameCho\Gestion;

const OBLIGATIONS_NIVEAUX = ['retard' => 'action', 'bientot' => 'bientot', 'attente' => 'attente', 'confirmer' => 'confirmer', 'plus_tard' => 'calme', 'sans_date' => 'calme', 'fait' => 'fait', 'sans_objet' => 'fait'];

function page_obligations(): void {
    if (!obligations_acces()) { flash('erreur', 'L\'écran Obligations est réservé à Youngmi et à Olivier.'); rediriger('plus'); }
    $moi = obligations_moi();
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        csrf_verifier();
        $a = texte('_action', 40);
        if ($a === 'liste_validee') {
            parametre_ecrire('obligations_validee_' . $moi, maintenant() . ' ' . auteur());
            demarche_historique_noter('validation', null, null, 'liste', '', $moi);
            flash('ok', 'Merci : votre relecture de la liste de départ est notée.');
        }
        if ($a === 'reprise' && est_admin()) {
            $f = $_FILES['fichier'] ?? null;
            if (!is_array($f) || ($f['error'] ?? 1) !== UPLOAD_ERR_OK || (int)$f['size'] > 500000) flash('erreur', 'Choisissez un fichier CSV de moins de 500 ko.');
            else {
                $cr = obligations_reprise((string)file_get_contents((string)$f['tmp_name']));
                if (isset($cr['erreur'])) flash('erreur', $cr['erreur']);
                else flash('ok', 'Reprise : ' . $cr['demarches'] . ' démarche(s) et ' . $cr['echeances'] . ' échéance(s) ajoutée(s), ' . $cr['doublons'] . ' doublon(s) écarté(s)'
                    . ($cr['refusees'] ? ', lignes refusées : ' . implode(', ', $cr['refusees']) : '') . '.');
            }
        }
        rediriger('obligations', ['vue' => texte('vue', 12) ?: 'a_faire']);
    }
    obligations_generer();   // rattrape une nuit manquée : une démarche par période, jamais deux
    $vue = choix('vue', array_flip(['a_faire', 'echeances', 'fait']), 'a_faire');
    $qui = choix('qui', array_flip(['miennes', 'toutes']), 'miennes');
    $changees = obligations_changees();
    $nAFaire = count(demarches_lister('a_faire', $qui, $moi));
    $onglets = '<nav class="sous-onglets" aria-label="Rubriques">';
    foreach (['a_faire' => ['À faire', $nAFaire], 'echeances' => ['Échéances', (int)val("SELECT COUNT(*) FROM echeances WHERE active = 1")], 'fait' => ['Fait', 0]] as $v => [$l, $n])
        $onglets .= '<a href="' . h(u('obligations', ['vue' => $v, 'qui' => $qui])) . '"' . ($vue === $v ? ' aria-current="page"' : '') . '>' . h($l) . ($n ? ' <span class="compte">' . $n . '</span>' : '') . '</a>';
    $onglets .= '</nav>';
    $filtre = $vue === 'echeances' ? '' : '<p class="filtre-qui">'
        . '<a class="btn ' . ($qui === 'miennes' ? 'principal' : 'discret') . '" href="' . h(u('obligations', ['vue' => $vue, 'qui' => 'miennes'])) . '"' . ($qui === 'miennes' ? ' aria-current="true"' : '') . '>Les miennes</a>'
        . '<a class="btn ' . ($qui === 'toutes' ? 'principal' : 'discret') . '" href="' . h(u('obligations', ['vue' => $vue, 'qui' => 'toutes'])) . '"' . ($qui === 'toutes' ? ' aria-current="true"' : '') . '>Toutes</a></p>';
    $c = titre_page('Obligations', 'Tout ce qu\'il faut faire ou prouver pour être en règle, le plus urgent d\'abord.', lien_bouton(u('demarche', ['nouvelle' => '1']), icone('ajouter') . 'Ajouter', 'principal'), u('plus'));
    // La liste de départ n'est pas encore relue par les deux (audit n° 7, constat 6)
    $reste = obligations_liste_a_valider();
    if ((string)parametre('obligations_reprise_le', '') === '') $c .= '<div class="bandeau-obligations" role="note">' . icone('alerte') . '<span><b>Liste de départ pas encore reprise.</b> Olivier ajoute la liste vérifiée des démarches et des échéances ; d\'ici là, cette liste n\'est pas complète.</span></div>';
    elseif ($reste) $c .= '<div class="bandeau-obligations" role="note">' . icone('alerte') . '<span><b>Liste de départ à relire.</b> <span>Si une démarche manque, ajoutez-la.</span> <span>Reste à relire :</span> <span translate="no">' . h(implode(', ', array_map(fn($p) => DEMARCHE_POUR[$p], $reste))) . '</span></span>'
        . (in_array($moi, $reste, true) ? formulaire('liste_validee', '<input type="hidden" name="vue" value="' . h($vue) . '">' . bouton(icone('valider') . 'J\'ai relu la liste', 'secondaire'), [], u('obligations')) : '') . '</div>';
    $c .= $onglets . $filtre;
    $l = [];
    if ($vue === 'echeances') $c .= vue_echeances();
    elseif ($vue === 'fait') {
        $l = demarches_lister('fait', $qui, $moi);
        $c .= bloc('Faites et sans objet', $l ? '<ul class="liste-demarches">' . implode('', array_map(fn($d) => carte_demarche($d, $changees), $l)) . '</ul>' : '<p class="vide">Rien pour l\'instant.</p>');
    } else {
        $l = demarches_lister('a_faire', $qui, $moi);
        $groupes = ['Urgent' => ['retard', 'bientot'], 'En attente d\'une réponse' => ['attente'], 'Date à confirmer' => ['confirmer'], 'Plus tard' => ['plus_tard', 'sans_date']];
        $s = '';
        foreach ($groupes as $titre => $niv) {
            $g = array_filter($l, fn($d) => in_array($d['_etat']['niveau'], $niv, true));
            if ($g || $titre === 'Urgent') $s .= bloc(h($titre) . ($g ? ' <span class="compte">' . count($g) . '</span>' : ''), $g ? '<ul class="liste-demarches">' . implode('', array_map(fn($d) => carte_demarche($d, $changees), $g)) . '</ul>' : '<p class="vide">Rien d\'urgent.</p>');
        }
        $c .= $s;
    }
    if (est_admin() && $vue === 'echeances') $c .= bloc_reprise_obligations();
    // Seul ce qui vient d'être affiché devient « vu » (audit n° 7, passe 2, P2-09)
    if ($vue === 'echeances') obligations_marquer_vu('echeance', array_keys(obligations_changees('echeance')));
    else obligations_marquer_vu('demarche', array_intersect(array_keys($changees), array_map(fn($d) => (int)$d['id'], $l)));
    mise_en_page('Obligations', $c, 'obligations', 'page-obligations');
}

/** Un libellé figé de la gestion (vérification d'un client pro) se traduit ; un texte tapé par Youngmi ou Olivier jamais (P3-05). */
function demarche_tn(string $texte): string {
    return obligations_texte_fige($texte) ? '' : ' translate="no"';
}

/** Une démarche dans une liste : l'état en toutes lettres et en couleur, le titre, pour qui, et « nouveau » ou « a changé » si l'autre y a touché. */
function carte_demarche(array $d, array $changees = []): string {
    $e = $d['_etat'] ?? demarche_etat($d);
    $chg = $changees[(int)$d['id']] ?? '';
    $etiq = $chg !== '' ? '<span class="etiq etiq-change">' . ($chg === 'nouveau' ? 'Ajoutée' : 'A changé') . '</span>' : '';
    $dep = !empty($e['depassee']);
    return '<li><a class="carte-demarche n-' . h(OBLIGATIONS_NIVEAUX[$e['niveau']]) . ($dep ? ' depassee' : '') . '" href="' . h(u('demarche', ['id' => (int)$d['id']])) . '">'
         . '<span class="d-etat">' . icone(in_array($e['niveau'], ['retard', 'bientot'], true) || $dep ? 'alerte' : ($e['niveau'] === 'fait' ? 'valider' : 'mois')) . '<span>' . h($e['texte']) . '</span></span>'
         . '<b class="d-titre"' . demarche_tn((string)$d['titre']) . '>' . h($d['titre']) . '</b>'
         . '<span class="d-pied"><span class="chip-par">' . h(DEMARCHE_POUR[$d['pour_qui']] ?? '') . '</span>'
         . ((int)$d['importante'] ? '<span class="chip-par">Importante</span>' : '') . ($d['echeance_id'] !== null ? '<span class="chip-par">Récurrente</span>' : '') . (demarche_hors_calendrier($d) ? '<span class="etiq etiq-change">Date d\'avant la modification</span>' : '') . $etiq . '</span></a></li>';
}

/** La rubrique Échéances : les règles récurrentes, leur fréquence, la prochaine date et leur préavis. */
function vue_echeances(): string {
    $l = lignes("SELECT * FROM echeances ORDER BY active DESC, depart = '', depart, titre");
    $chgR = obligations_changees('echeance');
    $s = '';
    foreach ($l as $e) {
        $prochaine = '';
        if ($e['depart'] !== '') {
            for ($i = 0; $i < 600; $i++) { $p = echeance_periode($e, $i); if ($p >= aujourdhui()) { $prochaine = $p; break; } }
        }
        $s .= '<li><a class="carte-demarche n-' . ((int)$e['active'] ? ($e['depart'] === '' ? 'confirmer' : 'calme') : 'fait') . '" href="' . h(u('echeance', ['id' => (int)$e['id']])) . '">'
            . '<span class="d-etat">' . icone('mois') . '<span>' . h(!(int)$e['active'] ? 'Arrêtée' : ($e['depart'] === '' ? 'Date à confirmer' : 'Prochaine : ' . date_fr($prochaine, 'longue'))) . '</span></span>'
            . '<b class="d-titre" translate="no">' . h($e['titre']) . '</b>'
            . '<span class="d-pied"><span class="chip-par">' . h(ECHEANCE_FREQUENCES[$e['frequence']] ?? '') . '</span><span class="chip-par">' . h('Rappel ' . (int)$e['preavis'] . ' jours avant') . '</span>'
            . '<span class="chip-par">' . h(DEMARCHE_POUR[$e['pour_qui']] ?? '') . '</span>'
            . (isset($chgR[(int)$e['id']]) ? '<span class="etiq etiq-change">' . ($chgR[(int)$e['id']] === 'nouveau' ? 'Ajoutée' : 'A changé') . '</span>' : '') . '</span></a></li>';
    }
    return bloc('Échéances qui reviennent', ($s !== '' ? '<ul class="liste-demarches">' . $s . '</ul>' : '<p class="vide">Aucune échéance pour l\'instant.</p>')
        . '<p class="aide">Chaque échéance crée sa démarche dans « À faire » quand son rappel commence (30 jours avant, sauf autre choix), une fois par période. Une date inconnue reste « à confirmer » : elle n\'est jamais devinée.</p>'
        . '<p>' . lien_bouton(u('echeance', ['nouvelle' => '1']), icone('ajouter') . 'Ajouter une échéance', 'secondaire') . '</p>');
}

/** La reprise de la liste de départ (administrateur ; le fichier vient d'Olivier, jamais du dépôt). */
function bloc_reprise_obligations(): string {
    $dernier = (string)parametre('obligations_reprise_le', '');
    return bloc('Reprise de la liste de départ', '<p class="aide">Un fichier CSV (séparateur « ; »), une ligne par démarche ou par échéance. Colonnes : <span translate="no">' . h(implode(';', OBLIGATIONS_CSV)) . '</span>. '
        . 'Genre : <span translate="no">demarche</span> ou <span translate="no">echeance</span> ; date au format AAAA-MM-JJ ; pour_qui : <span translate="no">youngmi, olivier, deux</span> ; '
        . 'fréquence : <span translate="no">annuelle, trimestrielle, mensuelle, premier_lundi</span>. Les doublons sont écartés.</p>'
        . ($dernier !== '' ? '<p class="petit">Dernière reprise : ' . h(date_fr(substr($dernier, 0, 10), 'longue')) . '</p>' : '')
        . formulaire('reprise', '<input type="hidden" name="vue" value="echeances"><label class="champ"><span class="etiq">Fichier CSV</span><input type="file" name="fichier" accept=".csv,text/csv" required></label>'
            . bouton('Reprendre ce fichier', 'secondaire'), ['enctype' => 'multipart/form-data'], u('obligations')));
}

/* ---------- Une démarche : fiche, formulaires ---------- */

function page_demarche(): void {
    if (!obligations_acces()) { flash('erreur', 'L\'écran Obligations est réservé à Youngmi et à Olivier.'); rediriger('plus'); }
    $id = (int)($_GET['id'] ?? 0);
    $d = $id ? demarche($id) : null;
    $nouvelle = !$d;
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        csrf_verifier();
        $a = texte('_action', 40);
        if ($a === 'enregistrer') {
            [$v, $err] = demarche_lue();
            if ($err !== null) { flash('erreur', $err); $_SESSION['demarche_brouillon'] = $v; rediriger('demarche', $d ? ['id' => $id, 'modifier' => '1'] : ['nouvelle' => '1']); }
            if ($d) { demarche_modifier($id, $v); flash('ok', 'Démarche modifiée.'); }
            else { $id = demarche_creer($v); flash('ok', 'Démarche ajoutée.'); }
            rediriger('demarche', ['id' => $id]);
        }
        if ($d && in_array($a, ['fait', 'attente', 'sans_objet', 'rouvrir'], true)) {
            $etat = $a === 'rouvrir' ? 'a_faire' : $a;
            $err = demarche_changer_etat($id, $etat, trim(texte('preuve_lien', 400)), texte('preuve_note', 1000), texte('raison', 300));
            flash($err === '' ? 'ok' : 'erreur', $err !== '' ? $err : ['fait' => 'C\'est noté : fait.', 'attente' => 'C\'est noté : envoyé, réponse attendue.', 'sans_objet' => 'C\'est noté : sans objet.', 'a_faire' => 'La démarche est de nouveau à faire.'][$etat]);
            rediriger('demarche', ['id' => $id]);
        }
        rediriger('obligations');
    }
    if ($nouvelle || ($_GET['modifier'] ?? '') === '1') {
        session_ouvrir();
        $v = $_SESSION['demarche_brouillon'] ?? ($d ?? ['titre' => '', 'explication' => '', 'ou' => '', 'pour_qui' => obligations_moi(), 'echeance' => '', 'a_confirmer' => 0, 'preavis' => OBLIGATIONS_JOURS, 'priorite' => 2, 'importante' => 0, 'origine' => '']);
        unset($_SESSION['demarche_brouillon']);
        $c = titre_page(($nouvelle ? 'Nouvelle démarche' : 'Modifier la démarche'), '', '', $nouvelle ? u('obligations') : u('demarche', ['id' => $id]))
           . bloc('', formulaire('enregistrer', champs_demarche($v) . bouton($nouvelle ? 'Ajouter la démarche' : 'Enregistrer', 'principal'), ['class' => 'form-demarche'], u('demarche', $d ? ['id' => $id] : ['nouvelle' => '1'])));
        mise_en_page($nouvelle ? 'Nouvelle démarche' : 'Modifier la démarche', $c, 'obligations', 'page-obligations');
    }
    $e = demarche_etat($d);
    $ouverte = in_array($d['etat'], ['a_faire', 'attente'], true);
    $fiche = '<p class="d-etat-grand n-' . h(OBLIGATIONS_NIVEAUX[$e['niveau']]) . (!empty($e['depassee']) ? ' depassee' : '') . '">' . icone(in_array($e['niveau'], ['retard', 'bientot'], true) || !empty($e['depassee']) ? 'alerte' : 'mois') . '<span>' . h($e['texte']) . '</span></p>'
           . definition([
               'Quoi' => '<span' . demarche_tn((string)$d['titre']) . '>' . nl2br(h($d['titre'])) . '</span>',
               'Client' => $d['ref_genre'] === 'client' && ($cl = client((int)$d['ref_id'])) ? '<a href="' . h(u('client', ['id' => (int)$cl['id']])) . '" translate="no">' . h(client_nom($cl)) . '</a>' : '',
               'Jour concerné' => $d['ref_genre'] === 'hygiene' && date_valide((string)$d['ref_id']) ? '<a href="' . h(u('hygiene', ['vue' => 'jour', 'jour' => (string)$d['ref_id']])) . '">' . h(ucfirst(date_fr((string)$d['ref_id'], 'jour'))) . '</a>' : '',
               'Où le faire ?' => '<span' . demarche_tn((string)$d['ou']) . '>' . nl2br(h($d['ou'])) . '</span>',
               'Pourquoi' => $d['explication'] !== '' ? '<span' . demarche_tn((string)$d['explication']) . '>' . nl2br(h($d['explication'])) . '</span>' : '',
               'Pour qui' => h(DEMARCHE_POUR[$d['pour_qui']] ?? ''),
               'Échéance' => $d['echeance'] !== '' ? h(date_fr($d['echeance'], 'longue')) : ((int)$d['a_confirmer'] ? 'À confirmer' : 'Sans date'),
               'Calendrier' => demarche_hors_calendrier($d) ? 'Date d\'avant la modification de l\'échéance : gardez-la si elle est toujours due, sinon « Sans objet ».' : '',
               'Rappel avant l\'échéance' => h((int)$d['preavis'] . ' jours avant'),
               'Priorité' => h(DEMARCHE_PRIORITES[(int)$d['priorite']] ?? '') . ((int)$d['importante'] ? ' · importante : preuve obligatoire' : ''),
               'D\'où vient-elle' => $d['origine'] !== '' ? '<span' . demarche_tn((string)$d['origine']) . '>' . h($d['origine']) . '</span>' : '',
               'Preuve' => $d['preuve_lien'] !== '' ? '<a href="' . h($d['preuve_lien']) . '" target="_blank" rel="noopener noreferrer">Ouvrir la preuve dans le Drive</a>' : '',
               'Note' => $d['preuve_note'] !== '' ? '<span translate="no">' . nl2br(h($d['preuve_note'])) . '</span>' : '',
               'Raison' => $d['raison'] !== '' ? '<span translate="no">' . h($d['raison']) . '</span>' : '',
           ]);
    $actions = '';
    if ($ouverte) {
        $preuve = champ('preuve_lien', 'Lien de la preuve dans le Drive' . ((int)$d['importante'] ? '' : ' (facultatif)'), $d['preuve_lien'], ['type' => 'url', 'inputmode' => 'url', 'placeholder' => 'https://drive.google.com/…', 'id' => 'f-preuve-fait'] + ((int)$d['importante'] && $d['preuve_lien'] === '' ? ['required' => true] : []),
                    'Rangez d\'abord la pièce dans le Drive (récépissé, attestation, rapport), puis copiez son lien.')
                . zone('preuve_note', 'Note (facultatif)', '', ['rows' => '2', 'maxlength' => '1000', 'id' => 'f-note-fait'], 'Jamais de nom ni de donnée d\'un client particulier.');
        $actions .= '<details class="replie" open><summary>' . icone('valider') . 'C\'est fait</summary>' . formulaire('fait', $preuve . bouton(icone('valider') . 'C\'est fait', 'principal'), [], u('demarche', ['id' => $id])) . '</details>';
        if ($d['etat'] !== 'attente') $actions .= '<details class="replie"><summary>' . icone('envoyer') . 'Envoyé, j\'attends la réponse</summary>'
            . formulaire('attente', zone('preuve_note', 'Note (facultatif)', '', ['rows' => '2', 'maxlength' => '1000', 'id' => 'f-note-attente'], 'Par exemple : envoyé en ligne, numéro de dossier noté dans la fiche d\'identité.')
                . bouton('Noter « envoyé »', 'secondaire'), [], u('demarche', ['id' => $id])) . '</details>';
        $actions .= '<details class="replie"><summary>' . icone('stop') . 'Sans objet</summary>'
            . formulaire('sans_objet', champ('raison', 'Pourquoi ?', '', ['maxlength' => '300', 'required' => true, 'id' => 'f-raison']) . bouton('Marquer sans objet', 'secondaire'), [], u('demarche', ['id' => $id])) . '</details>';
    } else $actions .= formulaire('rouvrir', bouton(icone('retour') . 'Rouvrir', 'secondaire'), ['data-confirmer' => 'Remettre cette démarche à faire ?'], u('demarche', ['id' => $id]));
    $c = titre_page('Démarche', '', lien_bouton(u('demarche', ['id' => $id, 'modifier' => '1']), icone('crayon') . 'Modifier', 'discret'), u('obligations'))
       . bloc('', $fiche) . bloc('Et maintenant', $actions) . bloc('Historique', historique_demarche($id), 'replie-historique');
    obligations_marquer_vu('demarche', [$id]);
    mise_en_page('Démarche', $c, 'obligations', 'page-obligations');
}

/** Les champs d'une démarche (ajout et modification). */
function champs_demarche(array $v): string {
    return champ('titre', 'Quoi faire', $v['titre'], ['maxlength' => '160', 'required' => true, 'placeholder' => 'Faire vérifier l\'extincteur'], 'Une phrase courte qui commence par un verbe.')
         . zone('ou', 'Où le faire', $v['ou'], ['rows' => '2', 'maxlength' => '600', 'required' => true], 'L\'outil, la page et l\'endroit exacts : site, Drive (dossier), mail, appel…')
         . zone('explication', 'Pourquoi (facultatif)', $v['explication'], ['rows' => '3', 'maxlength' => '1500'], 'Jamais de nom ni de donnée d\'un client particulier.')
         . liste_choix('pour_qui', 'Pour qui', DEMARCHE_POUR, $v['pour_qui'])
         . '<div class="ligne-champs">' . champ('echeance', 'Pour le', $v['echeance'], ['type' => 'date']) . champ('preavis', 'Rappel (jours avant)', (string)$v['preavis'], ['type' => 'number', 'min' => '1', 'max' => '365', 'inputmode' => 'numeric']) . '</div>'
         . case_a_cocher('a_confirmer', 'Date à confirmer (inconnue pour l\'instant)', (bool)$v['a_confirmer'])
         . liste_choix('priorite', 'Priorité', DEMARCHE_PRIORITES, (string)$v['priorite'])
         . case_a_cocher('importante', 'Importante : la preuve est obligatoire pour la clore (DDPP, assurance, impôts…)', (bool)$v['importante'])
         . champ('origine', 'D\'où vient-elle (facultatif)', $v['origine'], ['maxlength' => '120', 'placeholder' => 'Mail de la DDPP, contrat AXA, Olivier…']);
}

function historique_demarche(int $id): string {
    $l = demarche_historique($id);
    if (!$l) return '<p class="vide">Rien pour l\'instant.</p>';
    $types = ['creation' => 'Ajoutée', 'modification' => 'Modifiée', 'fait' => 'Faite', 'attente' => 'Envoyée, réponse attendue', 'sans_objet' => 'Sans objet', 'rouverte' => 'Rouverte'];
    $champs = ['titre' => 'quoi', 'ou' => 'où', 'explication' => 'pourquoi', 'pour_qui' => 'pour qui', 'echeance' => 'échéance', 'a_confirmer' => 'date à confirmer', 'preavis' => 'rappel', 'priorite' => 'priorité', 'importante' => 'importante', 'origine' => 'origine', 'etat' => 'état'];
    $s = '';
    foreach ($l as $h) $s .= '<li><span class="petit">' . h(date_fr(substr($h['le'], 0, 10), 'longue') . ' · ' . substr($h['le'], 11, 5)) . ' · <span translate="no">' . h(explode(' ', trim($h['auteur']))[0] ?? '') . '</span></span> '
        . h(($types[$h['type']] ?? $h['type']) . ($h['type'] === 'modification' && isset($champs[$h['champ']]) ? ' : ' . $champs[$h['champ']] : '')) . '</li>';
    return '<ul class="historique-demarche">' . $s . '</ul>';
}

/* ---------- Une échéance récurrente ---------- */

function page_echeance(): void {
    if (!obligations_acces()) { flash('erreur', 'L\'écran Obligations est réservé à Youngmi et à Olivier.'); rediriger('plus'); }
    $id = (int)($_GET['id'] ?? 0);
    $e = $id ? echeance($id) : null;
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        csrf_verifier();
        $a = texte('_action', 40);
        if ($a === 'enregistrer') {
            [$v, $err] = echeance_lue();
            if ($err !== null) { flash('erreur', $err); session_ouvrir(); $_SESSION['echeance_brouillon'] = $v; rediriger('echeance', $e ? ['id' => $id] : ['nouvelle' => '1']); }
            $cpt = null;
            $id = echeance_enregistrer($e ? $id : null, $v, $cpt);
            if (!$e) flash('ok', 'Échéance ajoutée : sa démarche apparaîtra dans « À faire » quand son rappel commencera.');
            else {
                [$maj, $revoir] = $cpt ?? [0, 0];
                flash('ok', 'Échéance modifiée.' . ($maj ? ' Démarches ouvertes mises à jour : ' . $maj . '.' : '') . ($revoir ? ' Démarches à revoir (date d\'avant) : ' . $revoir . '.' : ''));
            }
        }
        if ($e && in_array($a, ['arreter', 'reprendre'], true)) { echeance_arreter($id, $a === 'reprendre'); flash('ok', $a === 'arreter' ? 'Échéance arrêtée : plus de nouvelle démarche.' : 'Échéance reprise.'); }
        rediriger('obligations', ['vue' => 'echeances']);
    }
    session_ouvrir();
    $v = $_SESSION['echeance_brouillon'] ?? null;
    unset($_SESSION['echeance_brouillon']);
    $v = ($v !== null ? $v + ($e ?? []) : null) ?? $e ?? ['titre' => '', 'explication' => '', 'ou' => '', 'pour_qui' => 'youngmi', 'priorite' => 2, 'importante' => 0, 'frequence' => 'annuelle', 'depart' => '', 'preavis' => OBLIGATIONS_JOURS, 'origine' => ''];
    $champs = champ('titre', 'Quoi faire', $v['titre'], ['maxlength' => '160', 'required' => true, 'placeholder' => 'Faire vérifier l\'extincteur'], 'Une phrase courte qui commence par un verbe, jamais un nom de client.')
            . zone('ou', 'Où le faire', $v['ou'], ['rows' => '2', 'maxlength' => '600', 'required' => true], 'L\'outil, la page et l\'endroit exacts.')
            . zone('explication', 'Pourquoi (facultatif)', $v['explication'], ['rows' => '3', 'maxlength' => '1500'])
            . liste_choix('frequence', 'Revient', ECHEANCE_FREQUENCES, $v['frequence'])
            . '<div class="ligne-champs">' . champ('depart', 'Prochaine échéance', $v['depart'], ['type' => 'date']) . champ('preavis', 'Rappel (jours avant)', (string)$v['preavis'], ['type' => 'number', 'min' => '1', 'max' => '365', 'inputmode' => 'numeric']) . '</div>'
            . case_a_cocher('a_confirmer', 'Date à confirmer (inconnue pour l\'instant)', $e !== null && $v['depart'] === '')
            . liste_choix('pour_qui', 'Pour qui', DEMARCHE_POUR, $v['pour_qui'])
            . liste_choix('priorite', 'Priorité', DEMARCHE_PRIORITES, (string)$v['priorite'])
            . case_a_cocher('importante', 'Importante : la preuve est obligatoire pour clore chaque démarche', (bool)$v['importante'])
            . champ('origine', 'D\'où vient-elle (facultatif)', $v['origine'], ['maxlength' => '120']);
    $c = titre_page(($e ? 'Modifier l\'échéance' : 'Nouvelle échéance'), '', '', u('obligations', ['vue' => 'echeances']))
       . bloc('', formulaire('enregistrer', $champs . bouton($e ? 'Enregistrer' : 'Ajouter l\'échéance', 'principal'), ['class' => 'form-demarche'], u('echeance', $e ? ['id' => $id] : ['nouvelle' => '1'])));
    if ($e) $c .= bloc('', formulaire((int)$e['active'] ? 'arreter' : 'reprendre', bouton((int)$e['active'] ? 'Arrêter cette échéance' : 'Reprendre cette échéance', 'secondaire'),
                  (int)$e['active'] ? ['data-confirmer' => 'Arrêter cette échéance ? Les démarches déjà créées restent.'] : [], u('echeance', ['id' => $id])));
    if ($e) obligations_marquer_vu('echeance', [$id]);
    mise_en_page($e ? 'Modifier l\'échéance' : 'Nouvelle échéance', $c, 'obligations', 'page-obligations');
}
