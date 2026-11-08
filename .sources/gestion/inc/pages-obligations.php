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
        $ancre = '';
        if (in_array($a, ['poids', 'poids_commande', 'km', 'plafonds', 'document', 'document_retirer', 'document_confirmer', 'documents_proposer'], true)) {
            $err = '';
            if ($a === 'poids') {
                $g = trim(texte('grammes', 8));
                $err = volumes_poids_fixer(texte('produit', 80), texte('variante', 40), $g === '' ? null : (preg_match('/^\d{1,5}$/', $g) ? (int)$g : -1));
                if (!$err) flash('ok', $g === '' ? 'Poids retiré.' : 'Poids enregistré.');
                $ancre = 'poids';
            } elseif ($a === 'poids_commande') {
                $g = trim(texte('grammes', 8));
                $err = volumes_poids_commande_fixer(entier('commande', 1), $g === '' ? null : (preg_match('/^\d{1,6}$/', $g) ? (int)$g : -1));
                if (!$err) flash('ok', $g === '' ? 'Poids retiré.' : 'Poids enregistré.');
                $ancre = 'sur-mesure';
            } elseif ($a === 'km') {
                $k = str_replace(',', '.', trim(texte('km', 8)));
                $err = volumes_km_fixer(entier('client', 1), $k === '' ? null : (is_numeric($k) ? (float)$k : -1.0));
                if (!$err) flash('ok', 'Distance enregistrée.');
                $ancre = 'restaurants';
            } elseif ($a === 'plafonds') {
                $err = volumes_reglages_enregistrer($_POST);
                if (!$err) flash('ok', 'Plafonds enregistrés.');
            } elseif ($a === 'document') {
                [, $err] = document_enregistrer(entier('id', 1) ?: null, $_POST);
                if (!$err) flash('ok', 'Document enregistré.');
            } elseif ($a === 'document_confirmer') {
                $err = document_confirmer(entier('id', 1)); if (!$err) flash('ok', 'Merci : le document est vérifié.');
            } elseif ($a === 'documents_proposer') {
                if (!est_admin()) $err = 'Réservé à Olivier.';
                else { $cr = documents_proposer(texte('liens', 20000)); flash($cr['proposes'] ? 'ok' : 'erreur', 'Liens proposés : ' . $cr['proposes'] . ($cr['refusees'] ? ' ; lignes refusées : ' . implode(', ', $cr['refusees']) : '') . '.'); }
            } else {
                $d = document(entier('id', 1));
                if ($d) { document_retirer((int)$d['id'], $d['retire_le'] === ''); flash('ok', $d['retire_le'] === '' ? 'Document retiré de la liste : il reste dans « Retirés ».' : 'Document remis dans la liste.'); }
            }
            if ($err) flash('erreur', $err);
        }
        rediriger('obligations', ['vue' => texte('vue', 12) ?: 'a_faire'], $ancre);
    }
    volumes_preparer();
    obligations_generer();   // rattrape une nuit manquée : une démarche par période, jamais deux
    $vue = choix('vue', array_flip(['a_faire', 'echeances', 'volumes', 'documents', 'fait']), 'a_faire');
    $qui = choix('qui', array_flip(['miennes', 'toutes']), 'miennes');
    $changees = obligations_changees();
    $nAFaire = count(demarches_lister('a_faire', $qui, $moi));
    $onglets = '<nav class="sous-onglets" aria-label="Rubriques">';
    foreach (['a_faire' => ['À faire', $nAFaire], 'echeances' => ['Échéances', (int)val("SELECT COUNT(*) FROM echeances WHERE active = 1")], 'volumes' => ['Volumes', 0],
              'documents' => ['Documents', count(array_filter(documents(), fn($d) => document_etat($d) !== 'ok'))], 'fait' => ['Fait', 0]] as $v => [$l, $n])
        $onglets .= '<a href="' . h(u('obligations', ['vue' => $v, 'qui' => $qui])) . '"' . ($vue === $v ? ' aria-current="page"' : '') . '>' . h($l) . ($n ? ' <span class="compte">' . $n . '</span>' : '') . '</a>';
    $onglets .= '</nav>';
    $filtre = in_array($vue, ['echeances', 'volumes', 'documents'], true) ? '' : '<p class="filtre-qui">'
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
    elseif ($vue === 'volumes') $c .= vue_volumes();
    elseif ($vue === 'documents') $c .= vue_documents();
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
    elseif (in_array($vue, ['volumes', 'documents'], true)) {}
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
               'Lot' => $d['ref_genre'] === 'probleme' && ($pbm = probleme((int)$d['ref_id'])) ? '<a href="' . h(u('hygiene-probleme', ['id' => (int)$pbm['id']])) . '" translate="no">' . h($pbm['numero_lot']) . '</a>' : '',
               'Semaine' => $d['ref_genre'] === 'volumes' && date_valide((string)$d['ref_id']) ? '<a href="' . h(u('obligations', ['vue' => 'volumes'])) . '">' . h(date_fr((string)$d['ref_id'], 'longue')) . '</a>' : '',
               'Document' => $d['ref_genre'] === 'document' && ($doc = document((int)$d['ref_id'])) ? '<a href="' . h(u('obligations', ['vue' => 'documents'])) . '#doc-' . (int)$doc['id'] . '"' . ($doc['attendu'] !== '' ? '' : ' translate="no"') . '>' . h(document_titre($doc)) . '</a>' : '',
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

/* ---------- Lot 4 : Volumes de la dérogation (US5) ---------- */
const VOLUMES_NIVEAUX = ['depasse' => ['Plafond dépassé', 'retard'], 'proche' => ['Plafond proche', 'bientot'], 'ok' => ['Sous le seuil saisi', 'fait'], 'inconnu' => ['À compléter', 'confirmer'], 'non_verifie' => ['Non vérifié', 'confirmer']];
function vue_volumes(): string {
    $S = array_reverse(volumes_semaines(), true);
    $kgS = volumes_reglage('volumes_kg'); $conf = volumes_confirme();
    $c = '';
    if ($kgS === null) $c .= '<div class="bandeau-obligations" role="note">' . icone('alerte') . '<span><b>Plafonds pas encore saisis.</b> Tapez ceux que la DDPP a confirmés dans « Plafonds de la dérogation », plus bas. D\'ici là, aucune alerte.</span></div>';
    elseif ($conf === '') $c .= '<div class="bandeau-obligations" role="note">' . icone('alerte') . '<span><b>Plafonds à confirmer par la DDPP.</b> Les chiffres préremplis sont ceux de la réglementation, à vérifier : changez-les ou confirmez-les dans « Plafonds de la dérogation ». D\'ici là, une semaine sous le plafond reste « non vérifiée ».</span></div>';
    // Les plafonds de l'annexe III sont par catégorie de produits : tant que la catégorie n'est pas fixée (T051), un total global n'est jamais une preuve (contre-expertise n° 9, constat 2)
    if ($kgS !== null) $c .= '<p class="aide">' . icone('aide') . ' Un seul total pour tous les gâteaux : la réglementation fixe un plafond par catégorie de produits. Demandez à la DDPP dans quelle catégorie vont vos gâteaux. D\'ici là, « Sous le seuil saisi » veut dire seulement : sous le chiffre tapé plus bas, pas « en règle ».</p>';
    $sans = []; $mesure = [];
    $li = '';
    foreach ($S as $s => $w) {
        [$etat, $n] = VOLUMES_NIVEAUX[$w['niveau']];
        $kgT = fn(float $kg, bool $vide): string => $vide ? '—' : kilos($kg);
        $incomplet = $w['incomplet'];
        if ($w['pieces'] === 0 && $w['niveau'] !== 'depasse') { $etat = 'Aucune vente'; $n = 'sans_date'; }
        foreach ($w['sans_poids'] as $v => $nom) $sans[$v] = $nom;
        $li .= '<li class="semaine-volumes n-' . h($n) . '"><span class="sv-tete"><b>' . h('Semaine du ' . date_fr($s, 'longue')) . '</b>' . ($w['prevu'] ? '<span class="chip-par">Prévu</span>' : '')
            . '<span class="etiq ' . ($w['niveau'] === 'depasse' ? 'etiq-alerte' : (in_array($w['niveau'], ['proche', 'inconnu', 'non_verifie'], true) ? 'etiq-change' : 'etiq-outil')) . '">' . h($etat) . '</span></span>'
            . '<span class="sv-chiffres"><span><span class="petit">Restaurants</span> <b>' . h($kgT($w['kg_pro'], $incomplet && $w['kg_pro'] == 0 && $w['pieces_pro'] > 0)) . '</b></span><span><span class="petit">En tout</span> <b>' . h($kgT($w['kg'], $incomplet && $w['kg'] == 0)) . '</b></span>'
            . '<span><span class="petit">Part des restaurants</span> <b>' . ($w['part'] !== null ? h(str_replace('.', ',', (string)$w['part']) . ' %') : '—') . '</b>' . ($w['part'] !== null && $w['part_sur'] === 'ttc' ? ' <span class="petit">en montant, indicatif</span>' : '') . '</span>'
            . '<span><span class="petit">Plafond</span> <b>' . ($w['plafond'] !== null ? h(kilos($w['plafond'])) : '—') . '</b></span></span>'
            . ($w['sans_poids'] && $w['pieces'] ? '<span class="petit">' . h(count($w['sans_poids']) . ' gâteau(x) sans poids : kilos incomplets') . '</span>' : '')
            . ($w['sur_mesure_sans_poids'] ? '<span class="petit">' . h($w['sur_mesure_sans_poids'] . ' commande(s) sur mesure sans poids : kilos incomplets') . '</span>' : '')
            . '</li>';
        foreach ($w['sur_mesure'] as $m) $mesure[$m['id']] = $m;
    }
    $c .= bloc('Ventes par semaine', '<p class="aide">Les kilos vendus aux restaurants (B2B) et à tous, chaque semaine, d\'après le poids d\'une pièce de chaque gâteau. La semaine en cours et la suivante comptent aussi les commandes confirmées. Une alerte apparaît dans « À faire » quand les restaurants approchent le plafond.</p><ul class="liste-semaines">' . $li . '</ul>');
    // Commandes sur mesure : le poids se tape à la main (audit n° 8, constat 11)
    if ($mesure) {
        $m = '';
        foreach ($mesure as $x) $m .= '<li class="ligne-poids' . ($x['grammes'] === null ? ' a-faire' : '') . '"><span><a href="' . h(u('commande', ['id' => $x['id']])) . '" translate="no">' . h($x['reference']) . '</a>'
            . ($x['pro'] ? ' <span class="chip-par">Restaurant</span>' : '') . '</span>'
            . formulaire('poids_commande', '<input type="hidden" name="vue" value="volumes"><input type="hidden" name="commande" value="' . $x['id'] . '">'
                . champ('grammes', 'Poids total (g)', $x['grammes'] !== null ? (string)$x['grammes'] : '', ['inputmode' => 'numeric', 'id' => 'f-gk-' . $x['id'], 'size' => '7'])
                . bouton('Enregistrer', $x['grammes'] === null ? 'principal' : 'secondaire'), ['class' => 'form-poids'], u('obligations')) . '</li>';
        $c .= bloc('Commandes sur mesure', '<p class="aide">Ces commandes n\'ont pas de gâteau de la carte : tapez leur poids total pour que les kilos de la semaine soient complets.</p><ul class="liste-poids">' . $m . '</ul>', '', 'sur-mesure');
    }
    // Restaurants et distance
    $km = volumes_reglage('volumes_km');
    $r = '';
    foreach (volumes_restaurants() as $cl) {
        $loin = $km !== null && $cl['km'] !== null && $cl['km'] > $km;
        $r .= '<li class="ligne-restaurant' . ($loin ? ' loin' : '') . '"><a href="' . h(u('client', ['id' => (int)$cl['id']])) . '" translate="no">' . h(hygiene_restaurant($cl)) . '</a>'
            . ($cl['ville'] !== '' ? ' <span class="petit" translate="no">' . h($cl['ville']) . '</span>' : '')
            . formulaire('km', '<input type="hidden" name="vue" value="volumes"><input type="hidden" name="client" value="' . (int)$cl['id'] . '">'
                . champ('km', 'Distance (km)', $cl['km'] !== null ? str_replace('.', ',', rtrim(rtrim(number_format((float)$cl['km'], 1, '.', ''), '0'), '.')) : '', ['inputmode' => 'decimal', 'id' => 'f-km-' . (int)$cl['id'], 'size' => '5'])
                . bouton('Enregistrer', 'secondaire'), ['class' => 'form-km'], u('obligations'))
            . ($loin ? '<span class="etiq etiq-alerte">Plus loin que ' . h(str_replace('.', ',', (string)$km)) . ' km</span>' : ($cl['km'] === null ? '<span class="etiq etiq-change">Distance à saisir</span>' : '')) . '</li>';
    }
    $c .= bloc('Restaurants livrés', '<p class="aide">Les clients professionnels actifs. La dérogation limite la distance de livraison ; tapez la distance depuis la cuisine (un itinéraire Google Maps suffit). Un restaurant qui arrête passe « inactif » dans sa fiche et sort de cette liste.</p>'
        . ($r !== '' ? '<ul class="liste-restaurants">' . $r . '</ul>' : '<p class="vide">Aucun client professionnel actif.</p>'), '', 'restaurants');
    // Poids d'une pièce
    // D'abord les gâteaux vendus ces dernières semaines sans poids : les seuls à saisir pour que les kilos soient complets (revue du 08/11)
    $P = volumes_poids_tous(); $p = ''; $autres = ''; $n = 0;
    foreach (variantes_toutes() as $v => $d) {
        [$pid, $code] = array_pad(explode('|', $v, 2), 2, '');
        $g = $P[$v] ?? null; $prop = $g === null ? volumes_poids_recette($pid, $code) : null;
        $urgent = $g === null && isset($sans[$v]);
        if ($urgent) $n++;
        $ligne = '<li class="ligne-poids' . ($g === null ? ' a-faire' : '') . (isset($sans[$v]) ? ' vendu' : '') . '"><span><b translate="no">' . h($d['nom']) . '</b>' . (($d['libelle'] ?? '') !== '' ? ' <span class="petit" translate="no">' . h($d['libelle']) . '</span>' : '') . '</span>'
            . formulaire('poids', '<input type="hidden" name="vue" value="volumes"><input type="hidden" name="produit" value="' . h($pid) . '"><input type="hidden" name="variante" value="' . h($code) . '">'
                . champ('grammes', 'Poids d\'une pièce (g)', $g !== null ? (string)$g : '', ['inputmode' => 'numeric', 'id' => 'f-g-' . preg_replace('/[^a-z0-9]+/i', '-', $v), 'size' => '6'] + ($prop !== null ? ['placeholder' => (string)$prop] : []),
                    $g === null ? ($prop !== null ? h('Proposé d\'après la recette : ' . $prop . ' g. Pesez une pièce finie si possible.') : 'Pas de recette pesée : pesez une pièce finie.') : '')
                . bouton($g === null ? 'Confirmer' : 'Enregistrer', $g === null ? 'principal' : 'secondaire'), ['class' => 'form-poids'], u('obligations')) . '</li>';
        if ($urgent || $g !== null) $p .= $ligne; else $autres .= $ligne;
    }
    $c .= '<details class="replie bloc" id="poids"><summary><b>Poids d\'une pièce</b>' . ($n ? ' <span class="etiq etiq-change">' . h($n . ' à saisir') . '</span>' : '') . '</summary>'
        . '<p class="aide">Les kilos se calculent avec ce poids, jamais avec un poids deviné. La recette propose un poids (la somme de ses ingrédients en grammes) : pesez de préférence une pièce finie, puis touchez « Confirmer ».</p>'
        . ($p !== '' ? '<ul class="liste-poids">' . $p . '</ul>' : '<p class="vide">Aucun gâteau vendu sans poids.</p>')
        . ($autres !== '' ? '<details class="replie"><summary>Les autres gâteaux (pas vendus ces dernières semaines)</summary><ul class="liste-poids">' . $autres . '</ul></details>' : '') . '</details>';
    // Plafonds
    $f = '<input type="hidden" name="vue" value="volumes">';
    foreach (['volumes_kg' => 'Plafond par semaine (kg)', 'volumes_kg_reduit' => 'Plafond si la part des restaurants dépasse la limite (kg)', 'volumes_part' => 'Limite de la part des restaurants (%)', 'volumes_km' => 'Distance maximale (km)', 'volumes_alerte' => 'Alerte à partir de (% du plafond)'] as $k => $l) {
        $x = parametre($k, '');
        $f .= champ($k, h($l), str_replace('.', ',', (string)$x), ['inputmode' => 'decimal', 'id' => 'f-' . $k]);
    }
    $f .= case_a_cocher('confirme', 'Ces chiffres sont confirmés par la DDPP pour la catégorie de mes gâteaux (à cocher à chaque changement)', false)
        . ($conf !== '' ? case_a_cocher('retirer_confirmation', 'Retirer la confirmation', false) : '') . bouton(icone('valider') . 'Enregistrer', 'principal');
    $c .= '<details class="replie bloc" id="plafonds"><summary><b>Plafonds de la dérogation</b>' . ($conf === '' ? ' <span class="etiq etiq-change">À confirmer</span>' : '') . '</summary>'
        . '<p class="aide">Préremplis avec les chiffres de la réglementation (arrêté du 8 juin 2006), à faire confirmer par la DDPP des Hauts-de-Seine : la gestion ne les tient jamais pour sûrs.</p>'
        . ($conf !== '' ? '<p class="petit">' . h('Confirmés le ' . date_fr(substr($conf, 0, 10), 'longue')) . '</p>' : '')
        . formulaire('plafonds', $f, ['class' => 'form-releve'], u('obligations')) . '</details>';
    return $c;
}

/* ---------- Lot 4 : Documents de preuve (US6) ---------- */
const DOCUMENTS_ETATS = ['manquant' => ['Lien à ajouter', 'etiq-change'], 'propose' => ['Lien proposé, à vérifier', 'etiq-change'], 'expire' => ['Expiré', 'etiq-alerte'], 'bientot' => ['À renouveler bientôt', 'etiq-change'], 'ok' => ['Prêt', 'etiq-outil']];
function vue_documents(): string {
    $li = ''; $ret = '';
    foreach (documents(true) as $d) {
        $retire = $d['retire_le'] !== '';
        [$etat, $cl] = DOCUMENTS_ETATS[document_etat($d)];
        $tn = $d['attendu'] !== '' ? '' : ' translate="no"';
        $form = '<input type="hidden" name="vue" value="documents"><input type="hidden" name="id" value="' . (int)$d['id'] . '">'
            . ($d['attendu'] === '' ? champ('titre', 'Titre', $d['titre'], ['required' => true, 'maxlength' => '150', 'id' => 'f-titre-' . (int)$d['id'], 'translate' => 'no']) : '')
            . champ('lien', 'Lien dans le Drive', $d['lien'], ['type' => 'url', 'inputmode' => 'url', 'placeholder' => 'https://drive.google.com/…', 'id' => 'f-lien-' . (int)$d['id']])
            . champ('valide_jusqua', 'Valable jusqu\'au (facultatif)', $d['valide_jusqua'], ['type' => 'date', 'id' => 'f-val-' . (int)$d['id']])
            . zone('note', 'Note (facultatif)', $d['note'], ['rows' => '2', 'maxlength' => '500', 'id' => 'f-note-' . (int)$d['id'], 'translate' => 'no'])
            . bouton(icone('valider') . 'Enregistrer', 'principal');
        $va = document_etat($d) === 'propose' ? document_validite($d) : '';
        $item = '<li class="carte-document" id="doc-' . (int)$d['id'] . '"><span class="doc-tete"><b' . $tn . '>' . h(document_titre($d)) . '</b>' . ($retire ? '<span class="chip-par">Retiré</span>' : '<span class="etiq ' . $cl . '">' . h($etat) . '</span>'
            . ($va !== '' ? '<span class="etiq ' . DOCUMENTS_ETATS[$va][1] . '">' . h(DOCUMENTS_ETATS[$va][0]) . '</span>' : '')) . '</span>'
            . ($d['valide_jusqua'] !== '' ? '<span class="petit">' . h('Valable jusqu\'au ' . date_fr($d['valide_jusqua'], 'longue')) . '</span>' : '')
            . ($d['note'] !== '' ? '<span class="petit" translate="no">' . h($d['note']) . '</span>' : '')
            . ($d['lien'] !== '' ? '<span class="doc-actions">' . lien_bouton($d['lien'], icone('suivant') . 'Ouvrir', 'secondaire', ['target' => '_blank', 'rel' => 'noopener noreferrer'])
                . (!$retire && $d['propose_par'] !== '' ? formulaire('document_confirmer', '<input type="hidden" name="vue" value="documents"><input type="hidden" name="id" value="' . (int)$d['id'] . '">' . bouton(icone('valider') . 'C\'est le bon document', 'principal'), [], u('obligations')) : '') . '</span>' : '')
            . (!$retire && $d['propose_par'] !== '' ? '<span class="petit">Ouvrez-le pour vérifier ; si ce n\'est pas le bon, touchez « Modifier » et collez le bon lien.</span>' : '')
            . '<details class="replie"><summary>Modifier</summary>' . formulaire('document', $form, ['class' => 'form-releve'], u('obligations'))
            . formulaire('document_retirer', '<input type="hidden" name="vue" value="documents"><input type="hidden" name="id" value="' . (int)$d['id'] . '">' . bouton($retire ? 'Remettre dans la liste' : 'Retirer de la liste', 'discret'), $retire ? [] : ['data-confirmer' => 'Retirer ce document de la liste ? Il restera dans « Retirés ».'], u('obligations'))
            . '</details></li>';
        if ($retire) $ret .= $item; else $li .= $item;
    }
    $c = bloc('Documents de preuve', '<p class="aide">Les pièces à montrer lors d\'un contrôle, rangées dans le Drive ; la gestion garde seulement le lien, jamais de copie. Une pièce sans lien est « à ajouter ». Avec une date de validité, la gestion prévient 30 jours avant dans « À faire ».</p>'
        . ($li !== '' ? '<ul class="liste-documents">' . $li . '</ul>' : '<p class="vide">Aucun document.</p>'));
    $c .= '<details class="replie bloc"><summary><b>' . icone('ajouter') . 'Ajouter un document</b></summary>' . formulaire('document', '<input type="hidden" name="vue" value="documents">'
        . champ('titre', 'Titre', '', ['required' => true, 'maxlength' => '150', 'id' => 'f-titre-nouveau'])
        . champ('lien', 'Lien dans le Drive', '', ['type' => 'url', 'inputmode' => 'url', 'placeholder' => 'https://drive.google.com/…', 'id' => 'f-lien-nouveau'])
        . champ('valide_jusqua', 'Valable jusqu\'au (facultatif)', '', ['type' => 'date', 'id' => 'f-val-nouveau'])
        . zone('note', 'Note (facultatif)', '', ['rows' => '2', 'maxlength' => '500', 'id' => 'f-note-nouveau'])
        . bouton(icone('ajouter') . 'Ajouter', 'principal'), ['class' => 'form-releve'], u('obligations')) . '</details>';
    if (est_admin()) $c .= '<details class="replie bloc"><summary><b>Proposer des liens (Olivier)</b></summary><p class="aide">Une ligne par pièce : le code, le lien du Drive, une note facultative, séparés par une barre verticale. Un lien vérifié par Youngmi n\'est jamais remplacé.</p>'
        . formulaire('documents_proposer', '<input type="hidden" name="vue" value="documents">' . zone('liens', 'Liens', '', ['rows' => '6', 'id' => 'f-liens-proposes', 'translate' => 'no']) . '<p class="petit" translate="no">' . h(implode(' · ', array_keys(DOCUMENTS_ATTENDUS))) . '</p>' . bouton('Proposer', 'secondaire'), ['class' => 'form-releve'], u('obligations')) . '</details>';
    if ($ret !== '') $c .= '<details class="replie bloc"><summary><b>Retirés</b></summary><ul class="liste-documents">' . $ret . '</ul></details>';
    return $c;
}
