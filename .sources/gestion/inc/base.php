<?php
/*
 * Madame Cho : espace de gestion (clients, commandes, produits), spécification du 06/11/2026.
 * Socle commun : paramètres, base SQLite, session et comptes, aides. Inclus par index.php, outil.php,
 * menage.php et, pour l'enregistrement d'une demande, par ma-demande/envoyer.php (via enregistrer.php).
 * Jamais servi directement (.htaccess). Aucun secret dans ce fichier (dépôt public, R-08) :
 * la base, les mots de passe hachés et les jetons vivent sur le serveur, hors du dossier du site.
 */
declare(strict_types=1);
namespace MadameCho\Gestion;   // espace de noms : aucun nom ne peut entrer en conflit avec le formulaire du site, qui inclut ce fichier

use PDO;
use PDOStatement;
use RuntimeException;
use DateTimeImmutable;

if (!defined('GESTION')) define('GESTION', '1');
mb_internal_encoding('UTF-8');
date_default_timezone_set('Europe/Paris');

const GESTION_VERSION = '2026-11-13';
const GESTION_NOM = 'Gestion';
const GESTION_EXPEDITEUR = 'site@madamecho.com';
const GESTION_DOMAINE = 'madamecho.com';
const GESTION_COMPTE_INITIAL = 'contact@madamecho.com';   // seul compte qui peut naître par « mot de passe oublié » quand la base est vide
const GESTION_MDP_MIN = 12;
const GESTION_SESSION_INACTIVE = 43200;    // 12 h sans action : déconnexion
const GESTION_SOUVENIR = 30 * 86400;       // « rester connectée sur ce téléphone » : 30 jours
const GESTION_ECHECS_MAX = 10;             // 10 échecs en 15 minutes : pause d'un quart d'heure
const GESTION_SANS_SUITE_JOURS = 15;       // demande sans échange depuis 15 jours : « sans suite ? »
const GESTION_RETARD_HEURES = 24;          // le site promet une réponse sous 24 h
const GESTION_PURGE_DEMANDES_MOIS = 12;    // demande sans suite : effacée 12 mois après le dernier échange (politique de confidentialité)
const GESTION_PURGE_CLIENTS_ANS = 3;       // client sans commande ni échange depuis 3 ans : effacé (R-38)
const GESTION_JOURNAL_MOIS = 6;            // journal des connexions : 6 mois
const GESTION_ASSISTANT_MOIS = 6;          // conversations avec l'assistant : 6 mois (ici et pas dans assistant.php : la purge de nuit ne charge pas l'assistant, audit du 07/11 C1)
const GESTION_RAPPORTS_MOIS = 12;          // rapports de l'assistant : 12 mois (l'email reçu par Olivier reste la trace)
// Essai en local seulement (serveur intégré de PHP et MC_ESSAI=1) : aucun email ne part, les liens s'affichent à l'écran.
define('GESTION_ESSAI', PHP_SAPI === 'cli-server' && getenv('MC_ESSAI') === '1');
// Matomo, la mesure d'audience du site (T-06) : adresse publique ; la clé d'accès est dans la base (Paramètres), jamais dans ce dépôt
define('GESTION_MATOMO', GESTION_ESSAI && getenv('MC_MATOMO') ? (string)getenv('MC_MATOMO') : 'https://stats.madamecho.com/');
const GESTION_MATOMO_SITE = 1;             // madamecho.com dans Matomo
const GESTION_MATOMO_CACHE = 900;          // chiffres de Matomo gardés 15 minutes
define('GESTION_MATOMO_DEBUT', GESTION_ESSAI && getenv('MC_MATOMO_DEBUT') ? (string)getenv('MC_MATOMO_DEBUT') : '2026-11-06');   // mesure du site en ligne depuis le 06/11/2026 (T-06)
const GESTION_QR_CAMPAGNE = 'qr-code';     // campagne Matomo des QR codes imprimés : madamecho.com/qr renvoie vers la carte avec ce nom de campagne, mot-clé = support (packaging…)

const STATUTS = ['demande' => 'Demande reçue', 'proposition' => 'Proposition envoyée', 'confirmee' => 'Confirmée',
                 'remise' => 'Remise', 'sans_suite' => 'Sans suite', 'annulee' => 'Annulée'];
const STATUTS_COURTS = ['demande' => 'À répondre', 'proposition' => 'Proposition', 'confirmee' => 'Confirmée',
                        'remise' => 'Remise', 'sans_suite' => 'Sans suite', 'annulee' => 'Annulée'];
const STATUTS_EN_COURS = ['demande', 'proposition', 'confirmee'];
const PAIEMENTS = ['aucun' => 'Pas encore payé', 'acompte_demande' => 'Acompte demandé', 'acompte_recu' => 'Acompte reçu',
                   'paye' => 'Payé', 'rembourse' => 'Remboursé'];
const MOYENS = ['' => 'Moyen non noté', 'carte' => 'Carte (Tap to Pay)', 'virement' => 'Virement', 'especes' => 'Espèces', 'autre' => 'Autre'];
const CANAUX = ['site' => 'Site', 'qr' => 'QR code', 'email' => 'Email', 'instagram' => 'Instagram', 'telephone' => 'Téléphone',
                'marche' => 'Marché', 'sur_place' => 'Sur place', 'restaurant' => 'Restaurant', 'autre' => 'Autre'];
// Comment un échange noté à la main a eu lieu (demande d'Olivier du 07/11/2026 : ce qui ne passe pas par les mails)
const CANAUX_ECHANGE = ['telephone' => 'Téléphone', 'sms' => 'SMS', 'whatsapp' => 'WhatsApp', 'oral' => 'De vive voix', 'instagram' => 'Instagram', 'autre' => 'Autre'];
const TYPES_COMMANDE = ['carte' => 'Carte', 'perso' => 'Personnalisée', 'pro' => 'Professionnel'];
const TYPES_CLIENT = ['particulier' => 'Particulier', 'professionnel' => 'Professionnel'];
// B2C et B2B écrits partout où l'on choisit ou lit le type (demande d'Olivier, 07/11/2026 : « bien clair entre B2B et B2C »)
const TYPES_CLIENT_LONGS = ['particulier' => 'Particulier (B2C)', 'professionnel' => 'Professionnel (B2B)'];
const MOMENTS = ['matin' => 'Le matin, de 9 h à 12 h', 'midi' => 'À midi, de 12 h à 14 h',
                 'apres-midi' => 'L\'après-midi, de 14 h à 18 h', 'soir' => 'Le soir, de 18 h à 19 h'];
const MOMENTS_COURTS = ['matin' => 'matin', 'midi' => 'midi', 'apres-midi' => 'après-midi', 'soir' => 'soir'];
const MOMENTS_PLAGES = ['matin' => ['09:00', '12:00'], 'midi' => ['12:00', '14:00'], 'apres-midi' => ['14:00', '18:00'], 'soir' => ['18:00', '19:00']];
// L'ordre d'une journée : l'heure convenue, sinon le début du moment demandé (les commandes sans heure ni moment en dernier)
const ORDRE_DANS_LA_JOURNEE = "CASE WHEN k.retrait_heure <> '' THEN k.retrait_heure ELSE CASE k.retrait_moment WHEN 'matin' THEN '09:00' WHEN 'midi' THEN '12:00' WHEN 'apres-midi' THEN '14:00' WHEN 'soir' THEN '18:00' ELSE '99' END END";
const MODES = ['retrait' => 'Retrait à Meudon', 'livraison' => 'Livraison'];
const STATUTS_CLIENT = ['actif' => 'Actif', 'inactif' => 'Inactif', 'efface' => 'Effacé'];
const STATUTS_PRODUIT = ['carte' => 'À la carte', 'demande' => 'Sur demande seulement', 'pro' => 'Gamme pro', 'retire' => 'Retiré'];
const UNITES = ['g' => 'g', 'ml' => 'ml', 'piece' => 'pièce'];
// Les 14 allergènes à déclaration obligatoire (règlement UE 1169/2011, annexe II)
const ALLERGENES = ['gluten' => 'Gluten', 'crustaces' => 'Crustacés', 'oeufs' => 'Œufs', 'poissons' => 'Poissons',
                    'arachides' => 'Arachides', 'soja' => 'Soja', 'lait' => 'Lait', 'fruits_a_coque' => 'Fruits à coque',
                    'celeri' => 'Céleri', 'moutarde' => 'Moutarde', 'sesame' => 'Sésame', 'sulfites' => 'Sulfites',
                    'lupin' => 'Lupin', 'mollusques' => 'Mollusques'];
// Mots qui trahissent chaque allergène dans le texte affiché sur le site (comparaison recette / site)
const ALLERGENES_MOTS = ['gluten' => ['gluten', 'blé'], 'crustaces' => ['crustac'], 'oeufs' => ['œuf', 'oeuf'], 'poissons' => ['poisson'],
                         'arachides' => ['arachide', 'cacahu'], 'soja' => ['soja'], 'lait' => ['lait'],
                         'fruits_a_coque' => ['fruits à coque', 'noisette', 'noix', 'amande', 'pécan', 'pistache'],
                         'celeri' => ['céleri'], 'moutarde' => ['moutarde'], 'sesame' => ['sésame'], 'sulfites' => ['sulfite'],
                         'lupin' => ['lupin'], 'mollusques' => ['mollusque']];

/* ---------- Aides ---------- */
function h(mixed $s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function maintenant(): string { return date('Y-m-d H:i:s'); }
function aujourdhui(): string { return date('Y-m-d'); }
/** Montant arrondi à l'euro, avec séparateur de milliers (tableaux de statistiques, lisibles sur téléphone). */
function euros_ronds(int $c): string { return number_format((int)round($c / 100), 0, ',', "\u{202F}") . ' €'; }
function euros(?int $c, string $vide = '—'): string {
    if ($c === null) return $vide;
    $neg = $c < 0 ? '−' : ''; $c = abs($c);
    return $neg . (($c % 100) ? intdiv($c, 100) . ',' . sprintf('%02d', $c % 100) : (string)intdiv($c, 100)) . ' €';
}
/** « 12,50 » ou « 12.5 » ou « 12 » en centimes ; null si vide ou illisible. */
function centimes(string $s): ?int {
    $s = str_replace([' ', "\u{a0}", '€'], '', trim($s));
    if ($s === '') return null;
    if (!preg_match('/^-?\d{1,6}([.,]\d{1,2})?$/', $s)) return null;
    return (int)round((float)str_replace(',', '.', $s) * 100);
}
const JOURS = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
const MOIS = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
const MOIS_COURTS = ['', 'janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
/** Date lisible : « jeudi 7 novembre », « 7 nov. 2026 », « 07/11/2026 ». */
function date_fr(?string $d, string $forme = 'courte'): string {
    if (!$d) return '—';
    $t = strtotime($d);
    if ($t === false) return h($d);
    $j = (int)date('j', $t); $m = (int)date('n', $t); $a = date('Y', $t);
    return match ($forme) {
        'jour' => JOURS[(int)date('w', $t)] . ' ' . $j . ' ' . MOIS[$m] . ($a !== date('Y') ? ' ' . $a : ''),
        'longue' => $j . ' ' . MOIS[$m] . ' ' . $a,
        'heure' => date('d/m/Y', $t) . ' à ' . date('H', $t) . ' h ' . date('i', $t),
        'relative' => date_relative($t),
        default => $j . ' ' . MOIS_COURTS[$m] . ($a !== date('Y') ? ' ' . $a : ''),
    };
}
function date_relative(int $t): string {
    $d = time() - $t;
    if ($d < 3600) return 'il y a ' . max(1, intdiv($d, 60)) . ' min';
    if ($d < 86400) return 'il y a ' . intdiv($d, 3600) . ' h';
    if ($d < 86400 * 30) return 'il y a ' . intdiv($d, 86400) . ' j';
    return 'le ' . date('d/m/Y', $t);
}
function jours_depuis(?string $d): int { return $d ? intdiv(time() - (strtotime($d) ?: time()), 86400) : 0; }
function heures_depuis(?string $d): int { return $d ? intdiv(time() - (strtotime($d) ?: time()), 3600) : 0; }
function texte(string $nom, int $max, ?array $src = null): string {
    $src ??= $_POST;
    $v = isset($src[$nom]) && is_string($src[$nom]) ? $src[$nom] : '';
    $v = str_replace(["\r\n", "\r"], "\n", $v);
    $v = preg_replace('/[^\P{C}\n\t]/u', '', $v) ?? '';
    return mb_substr(trim($v), 0, $max);
}
function une_ligne(string $v): string { return trim(preg_replace('/\s+/u', ' ', $v) ?? ''); }
function choix(string $nom, array $options, string $defaut = ''): string {
    $v = $_POST[$nom] ?? $_GET[$nom] ?? $defaut;
    return (is_string($v) && array_key_exists($v, $options)) ? $v : $defaut;
}
function entier(string $nom, int $min = 0, int $max = PHP_INT_MAX, int $defaut = 0): int {
    $v = $_POST[$nom] ?? $_GET[$nom] ?? null;
    if (!is_string($v) || !preg_match('/^-?\d{1,9}$/', $v)) return $defaut;
    return max($min, min($max, (int)$v));
}
function date_valide(string $s): ?string {
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $s);
    return ($d && $d->format('Y-m-d') === $s) ? $s : null;
}
function email_valide(string $e): bool { return $e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL) !== false && !preg_match('/[\r\n,;]/', $e); }

/* ---------- Chemins et base de données ---------- */
/** Racine du site (dossier servi) : toujours déduite de l'emplacement de ce fichier (gestion/inc/), pour que le site et la tâche de nuit trouvent la même base. */
function gestion_site(): string { return dirname(__DIR__, 2); }
/** Dossier des données : à côté du dossier du site (jamais servi), sinon un dossier caché de gestion/, fermé au web. */
function gestion_dossier(): string {
    static $d = null;
    if ($d !== null) return $d;
    $hors = dirname(gestion_site()) . '/.madamecho-donnees';
    if ((is_dir($hors) || @mkdir($hors, 0700, true)) && is_writable($hors)) return $d = $hors;
    $repli = dirname(__DIR__) . '/.donnees';
    if ((is_dir($repli) || @mkdir($repli, 0700, true)) && is_writable($repli)) {
        if (!is_file($repli . '/.htaccess')) @file_put_contents($repli . '/.htaccess', "Require all denied\n");
        return $d = $repli;
    }
    throw new RuntimeException('Le dossier des données ne peut pas être créé.');
}
/** Vrai si la base existe déjà (sans la créer) : le formulaire du site et la tâche de nuit ne font rien tant que l'espace n'est pas ouvert. */
function gestion_base_existante(): bool {
    return is_file(dirname(gestion_site()) . '/.madamecho-donnees/gestion.sqlite') || is_file(dirname(__DIR__) . '/.donnees/gestion.sqlite');
}
function gestion_hors_site(): bool { return strpos(gestion_dossier(), '/.madamecho-donnees') !== false; }
function gestion_fichier_base(): string { return gestion_dossier() . '/gestion.sqlite'; }
function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $f = gestion_fichier_base();
    $neuf = !is_file($f);
    $pdo = new PDO('sqlite:' . $f, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    if ($neuf) @chmod($f, 0600);
    $pdo->exec('PRAGMA journal_mode=WAL');
    $pdo->exec('PRAGMA foreign_keys=ON');
    $pdo->exec('PRAGMA busy_timeout=5000');
    gestion_schema($pdo);
    return $pdo;
}
function gestion_schema(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS parametres (cle TEXT PRIMARY KEY, valeur TEXT NOT NULL)");
    obligations_schema($pdo);   // écran Obligations (spécification 004) : hors de la numérotation, pour ne pas gêner l'étape 11 des factures
    hygiene_schema($pdo);       // relevés d'hygiène (spécification 004, lot 2), même principe : marqueur à part
    photos_schema($pdo);        // photos des clients (10/11/2026), même principe : marqueur à part
    $v = (int)($pdo->query("SELECT valeur FROM parametres WHERE cle='schema'")->fetchColumn() ?: 0);
    if ($v >= 10) return;
    if ($v < 1) $pdo->exec("
CREATE TABLE IF NOT EXISTS utilisateurs (id INTEGER PRIMARY KEY, email TEXT NOT NULL UNIQUE, nom TEXT NOT NULL, mdp TEXT NOT NULL DEFAULT '',
  role TEXT NOT NULL DEFAULT 'membre', actif INTEGER NOT NULL DEFAULT 1, cree_le TEXT NOT NULL, derniere_connexion TEXT, mdp_change_le TEXT);
CREATE TABLE IF NOT EXISTS jetons (id INTEGER PRIMARY KEY, utilisateur_id INTEGER NOT NULL REFERENCES utilisateurs(id) ON DELETE CASCADE,
  type TEXT NOT NULL, empreinte TEXT NOT NULL, expire_le TEXT NOT NULL, utilise_le TEXT, cree_le TEXT NOT NULL);
CREATE TABLE IF NOT EXISTS connexions (id INTEGER PRIMARY KEY, le TEXT NOT NULL, email TEXT NOT NULL DEFAULT '', ok INTEGER NOT NULL,
  ip TEXT NOT NULL DEFAULT '', detail TEXT NOT NULL DEFAULT '');
CREATE INDEX IF NOT EXISTS connexions_le ON connexions(le);
CREATE TABLE IF NOT EXISTS clients (id INTEGER PRIMARY KEY, numero TEXT NOT NULL UNIQUE, type TEXT NOT NULL DEFAULT 'particulier',
  nom TEXT NOT NULL DEFAULT '', prenom TEXT NOT NULL DEFAULT '', societe TEXT NOT NULL DEFAULT '', contact TEXT NOT NULL DEFAULT '',
  email TEXT NOT NULL DEFAULT '', telephone TEXT NOT NULL DEFAULT '', ville TEXT NOT NULL DEFAULT '', canal TEXT NOT NULL DEFAULT 'site',
  premier_contact TEXT NOT NULL, dernier_echange TEXT NOT NULL, statut TEXT NOT NULL DEFAULT 'actif',
  qonto TEXT NOT NULL DEFAULT '', avis_demande_le TEXT, avis_recu_le TEXT, lettre_info TEXT NOT NULL DEFAULT '', lettre_info_le TEXT,
  cree_le TEXT NOT NULL, maj_le TEXT NOT NULL, efface_le TEXT);
CREATE INDEX IF NOT EXISTS clients_email ON clients(email);
CREATE TABLE IF NOT EXISTS commandes (id INTEGER PRIMARY KEY, reference TEXT NOT NULL UNIQUE,
  client_id INTEGER NOT NULL REFERENCES clients(id), canal TEXT NOT NULL DEFAULT 'site', type TEXT NOT NULL DEFAULT 'carte',
  statut TEXT NOT NULL DEFAULT 'demande', paiement TEXT NOT NULL DEFAULT 'aucun', moyen TEXT NOT NULL DEFAULT '', facture TEXT NOT NULL DEFAULT '',
  probleme INTEGER NOT NULL DEFAULT 0, probleme_note TEXT NOT NULL DEFAULT '',
  retrait_date TEXT, retrait_moment TEXT NOT NULL DEFAULT '', retrait_heure TEXT NOT NULL DEFAULT '', mode TEXT NOT NULL DEFAULT 'retrait',
  lignes TEXT NOT NULL DEFAULT '[]', perso TEXT NOT NULL DEFAULT '', besoin TEXT NOT NULL DEFAULT '',
  total_indicatif INTEGER NOT NULL DEFAULT 0, prix_final INTEGER, acompte INTEGER NOT NULL DEFAULT 0,
  recue_le TEXT NOT NULL, dernier_echange TEXT NOT NULL, remise_le TEXT, annulee_le TEXT, annulee_motif TEXT NOT NULL DEFAULT '', sans_suite_le TEXT,
  cree_le TEXT NOT NULL, maj_le TEXT NOT NULL, efface_le TEXT);
CREATE INDEX IF NOT EXISTS commandes_client ON commandes(client_id);
CREATE INDEX IF NOT EXISTS commandes_statut ON commandes(statut, retrait_date);
CREATE TABLE IF NOT EXISTS evenements (id INTEGER PRIMARY KEY, le TEXT NOT NULL, auteur TEXT NOT NULL, type TEXT NOT NULL,
  commande_id INTEGER, client_id INTEGER, produit_id TEXT, texte TEXT NOT NULL DEFAULT '', ancien TEXT NOT NULL DEFAULT '', nouveau TEXT NOT NULL DEFAULT '');
CREATE INDEX IF NOT EXISTS evenements_commande ON evenements(commande_id);
CREATE INDEX IF NOT EXISTS evenements_client ON evenements(client_id);
CREATE TABLE IF NOT EXISTS produits (id TEXT PRIMARY KEY, nom TEXT NOT NULL, statut TEXT NOT NULL DEFAULT 'carte',
  valide_le TEXT, valide_par TEXT NOT NULL DEFAULT '', fiche_lien TEXT NOT NULL DEFAULT '', recette TEXT NOT NULL DEFAULT '',
  notes TEXT NOT NULL DEFAULT '', emballage INTEGER NOT NULL DEFAULT 0, tva INTEGER NOT NULL DEFAULT 55, catalogue TEXT NOT NULL DEFAULT '{}',
  cree_le TEXT NOT NULL, maj_le TEXT NOT NULL);
CREATE TABLE IF NOT EXISTS ingredients (id INTEGER PRIMARY KEY, nom TEXT NOT NULL, unite TEXT NOT NULL DEFAULT 'g',
  prix INTEGER NOT NULL DEFAULT 0, allergenes TEXT NOT NULL DEFAULT '', fournisseur TEXT NOT NULL DEFAULT '', achat TEXT NOT NULL DEFAULT '',
  notes TEXT NOT NULL DEFAULT '', actif INTEGER NOT NULL DEFAULT 1, cree_le TEXT NOT NULL, maj_le TEXT NOT NULL);
CREATE TABLE IF NOT EXISTS recettes (id INTEGER PRIMARY KEY, produit_id TEXT NOT NULL REFERENCES produits(id) ON DELETE CASCADE,
  variante TEXT NOT NULL, ingredient_id INTEGER NOT NULL REFERENCES ingredients(id), quantite REAL NOT NULL, note TEXT NOT NULL DEFAULT '');
CREATE INDEX IF NOT EXISTS recettes_produit ON recettes(produit_id, variante);
");
    // Version 2 (07/11/2026) : l'assistant de Youngmi (conversations) et ses rapports pour Olivier
    $pdo->exec("
CREATE TABLE IF NOT EXISTS assistant_messages (id INTEGER PRIMARY KEY, utilisateur_id INTEGER NOT NULL, fil INTEGER NOT NULL, le TEXT NOT NULL,
  role TEXT NOT NULL, contenu TEXT NOT NULL, question INTEGER NOT NULL DEFAULT 0, usage TEXT NOT NULL DEFAULT '', rapport_id INTEGER, echec INTEGER NOT NULL DEFAULT 0);
CREATE INDEX IF NOT EXISTS assistant_messages_fil ON assistant_messages(utilisateur_id, fil);
CREATE TABLE IF NOT EXISTS rapports (id INTEGER PRIMARY KEY, utilisateur_id INTEGER NOT NULL, fil INTEGER NOT NULL, le TEXT NOT NULL,
  type TEXT NOT NULL, titre TEXT NOT NULL, resume TEXT NOT NULL DEFAULT '', detail TEXT NOT NULL DEFAULT '', ecran TEXT NOT NULL DEFAULT '',
  attendu TEXT NOT NULL DEFAULT '', etat TEXT NOT NULL DEFAULT 'propose', envoye_le TEXT, pour_vous TEXT NOT NULL DEFAULT '');
");
    // Version 3 (07/11/2026, audit C4 et C9) : une question ratée reste comptée dans le coût (echec) ; le rapport porte un résumé dans la langue de l'utilisatrice (pour_vous)
    if ($v === 2) {
        $pdo->exec("ALTER TABLE assistant_messages ADD COLUMN echec INTEGER NOT NULL DEFAULT 0");
        $pdo->exec("ALTER TABLE rapports ADD COLUMN pour_vous TEXT NOT NULL DEFAULT ''");
    }
    // Version 4 (07/11/2026, demande d'Olivier) : les indispos de l'agenda (jours entiers ou plages horaires, du jour « debut »
    // au jour « fin » compris ; heures vides = toute la journée) et la date depuis laquelle chaque gâteau est proposé
    $pdo->exec("
CREATE TABLE IF NOT EXISTS indispos (id INTEGER PRIMARY KEY, debut TEXT NOT NULL, fin TEXT NOT NULL, heure_debut TEXT NOT NULL DEFAULT '',
  heure_fin TEXT NOT NULL DEFAULT '', motif TEXT NOT NULL DEFAULT '', auteur TEXT NOT NULL DEFAULT '', cree_le TEXT NOT NULL, maj_le TEXT NOT NULL);
CREATE INDEX IF NOT EXISTS indispos_dates ON indispos(debut, fin);
");
    $colonnes = array_column($pdo->query("PRAGMA table_info(produits)")->fetchAll(PDO::FETCH_ASSOC), 'name');
    if (!in_array('propose_depuis', $colonnes, true)) {
        try { $pdo->exec("ALTER TABLE produits ADD COLUMN propose_depuis TEXT"); }
        catch (\PDOException $e) { if (!str_contains($e->getMessage(), 'duplicate column')) throw $e; }   // deux pages ouvertes en même temps
    }
    // Version 5 (07/11/2026, demande d'Olivier) : les modifications que l'assistant prépare et que la personne confirme d'un toucher
    // (genre = l'action, cible = la fiche, champs = ce qui change ; etat : propose, fait, annule, expire, remplace ; lien = la fiche après coup)
    $pdo->exec("
CREATE TABLE IF NOT EXISTS assistant_actions (id INTEGER PRIMARY KEY, utilisateur_id INTEGER NOT NULL, fil INTEGER NOT NULL, message_id INTEGER, le TEXT NOT NULL,
  genre TEXT NOT NULL, cible TEXT NOT NULL DEFAULT '', champs TEXT NOT NULL DEFAULT '{}', resume TEXT NOT NULL DEFAULT '', etat TEXT NOT NULL DEFAULT 'propose',
  fait_le TEXT, retour TEXT NOT NULL DEFAULT '', lien TEXT NOT NULL DEFAULT '');
CREATE INDEX IF NOT EXISTS assistant_actions_fil ON assistant_actions(utilisateur_id, fil);
");
    // Version 6 (07/11/2026, audit n° 2, C3) : l'empreinte de la fiche au moment où l'assistant prépare la modification ; si la fiche a
    // changé avant « Confirmer », la modification est refusée et se redemande
    $colonnes = array_column($pdo->query("PRAGMA table_info(assistant_actions)")->fetchAll(PDO::FETCH_ASSOC), 'name');
    if (!in_array('empreinte', $colonnes, true)) {
        try { $pdo->exec("ALTER TABLE assistant_actions ADD COLUMN empreinte TEXT NOT NULL DEFAULT ''"); }
        catch (\PDOException $e) { if (!str_contains($e->getMessage(), 'duplicate column')) throw $e; }
    }
    // Version 7 (07/11/2026, audit n° 2, C14, FR-030) : qui a constaté le paiement et quand ; le rapprochement avec Qonto, séparé du paiement
    // (référence de l'opération Qonto, qui l'a noté et quand) ; les espèces n'en ont pas besoin
    $colonnes = array_column($pdo->query("PRAGMA table_info(commandes)")->fetchAll(PDO::FETCH_ASSOC), 'name');
    foreach (['paiement_par' => "TEXT NOT NULL DEFAULT ''", 'paiement_le' => 'TEXT', 'qonto_ref' => "TEXT NOT NULL DEFAULT ''", 'qonto_le' => 'TEXT', 'qonto_par' => "TEXT NOT NULL DEFAULT ''"] as $col => $def) {
        if (in_array($col, $colonnes, true)) continue;
        try { $pdo->exec("ALTER TABLE commandes ADD COLUMN $col $def"); }
        catch (\PDOException $e) { if (!str_contains($e->getMessage(), 'duplicate column')) throw $e; }
    }
    // Version 8 (07/11/2026, demande d'Olivier : les réponses des clients dans la gestion) : un mail reçu non lu (lu = 0, les anciens
    // événements sont tous lus) ; les mails que le script de contact@ a déjà transmis (gmail_id, une seule fois chacun) ; ceux sans
    // commande reconnue gardent l'expéditeur, l'objet et un extrait, 30 jours, pour qu'un admin les rattache ou les ignore
    $colonnes = array_column($pdo->query("PRAGMA table_info(evenements)")->fetchAll(PDO::FETCH_ASSOC), 'name');
    if (!in_array('lu', $colonnes, true)) {
        try { $pdo->exec("ALTER TABLE evenements ADD COLUMN lu INTEGER NOT NULL DEFAULT 1"); }
        catch (\PDOException $e) { if (!str_contains($e->getMessage(), 'duplicate column')) throw $e; }
    }
    $pdo->exec("
CREATE TABLE IF NOT EXISTS mails_recus (gmail_id TEXT PRIMARY KEY, le TEXT NOT NULL, commande_id INTEGER, evenement_id INTEGER,
  de TEXT NOT NULL DEFAULT '', objet TEXT NOT NULL DEFAULT '', extrait TEXT NOT NULL DEFAULT '', recu_le TEXT NOT NULL DEFAULT '', etat TEXT NOT NULL DEFAULT '');
CREATE INDEX IF NOT EXISTS mails_recus_le ON mails_recus(le);
");
    // Version 9 (07/11/2026, demande d'Olivier : « une corbeille… au cas où Youngmi s'est trompée ») : ce qui est supprimé y reste 30 jours,
    // avec ses lignes d'origine (JSON), pour être restauré tel quel ; la tâche de nuit vide ce qui a plus de 30 jours
    $pdo->exec("
CREATE TABLE IF NOT EXISTS corbeille (id INTEGER PRIMARY KEY, le TEXT NOT NULL, par TEXT NOT NULL DEFAULT '', genre TEXT NOT NULL, reference TEXT NOT NULL DEFAULT '',
  libelle TEXT NOT NULL DEFAULT '', motif TEXT NOT NULL DEFAULT '', client_id INTEGER, donnees TEXT NOT NULL DEFAULT '{}');
CREATE INDEX IF NOT EXISTS corbeille_le ON corbeille(le);
");
    // Version 10 (08/11/2026, demande d'Olivier : des pastilles sur ce qui est nouveau ou a changé, FR-041) : ce que chaque personne
    // a vu (commande, fiche, nouveauté de l'outil) et son point de départ ; posé maintenant pour que rien d'ancien ne s'allume d'un coup
    // (jusqu_a : le numéro du dernier événement vu ; on compare des numéros, jamais des heures : audit n° 5, constat 4)
    $pdo->exec("CREATE TABLE IF NOT EXISTS vus (utilisateur_id INTEGER NOT NULL, genre TEXT NOT NULL, objet TEXT NOT NULL, le TEXT NOT NULL,
  jusqu_a INTEGER NOT NULL DEFAULT 0, PRIMARY KEY (utilisateur_id, genre, objet))");
    $colU = array_column($pdo->query("PRAGMA table_info(utilisateurs)")->fetchAll(PDO::FETCH_ASSOC), 'name');
    foreach (['pastilles_depuis' => 'TEXT', 'pastilles_id' => 'INTEGER'] as $col => $def) {
        if (in_array($col, $colU, true)) continue;
        try { $pdo->exec("ALTER TABLE utilisateurs ADD COLUMN $col $def"); }
        catch (\PDOException $e) { if (stripos($e->getMessage(), 'duplicate column') === false) throw $e; }
    }
    // Point de départ : le dernier événement existant ; mais une réponse de client pas encore ouverte au moment de la mise en ligne
    // reste signalée (on part juste avant la plus ancienne d'entre elles, si elle a moins de 30 jours)
    $max = (int)$pdo->query("SELECT COALESCE(MAX(id), 0) FROM evenements")->fetchColumn();
    $st = $pdo->prepare("SELECT MIN(id) FROM evenements WHERE type = 'email_recu' AND lu = 0 AND le > ?");
    $st->execute([date('Y-m-d H:i:s', time() - 30 * 86400)]);
    $nonLu = (int)$st->fetchColumn();
    $pdo->prepare("UPDATE utilisateurs SET pastilles_id = ? WHERE pastilles_id IS NULL")->execute([$nonLu ? min($max, $nonLu - 1) : $max]);
    $pdo->prepare("UPDATE utilisateurs SET pastilles_depuis = ? WHERE pastilles_depuis IS NULL")->execute([date('Y-m-d H:i:s')]);
    $pdo->exec("CREATE INDEX IF NOT EXISTS evenements_le ON evenements(le)");
    $pdo->exec("INSERT OR REPLACE INTO parametres (cle, valeur) VALUES ('schema', '10')");
}
/**
 * Les tables de l'écran Obligations (spécification 004, lot 1, 08/11/2026). Elles ne prennent pas de numéro de schéma : la branche
 * des factures prend le 11 et s'arrête dès que le numéro l'atteint ; un numéro 12 posé ici avant elle l'aurait fait sauter
 * (audit n° 7, constat 11). Un repère à part, « schema_obligations », dit que c'est fait ; tout est idempotent.
 */
function obligations_schema(PDO $pdo): void {
    $fait = $pdo->query("SELECT valeur FROM parametres WHERE cle = 'schema_obligations'")->fetchColumn();
    if ($fait === '2') return;
    if ($fait === '1') {
        // Étape 2 (audit n° 7, passe 3, P3-04) : une base créée par la première version reçoit la colonne auteur_id
        $cols = array_column($pdo->query("PRAGMA table_info(demarches_historique)")->fetchAll(PDO::FETCH_ASSOC), 'name');
        if (!in_array('auteur_id', $cols, true)) $pdo->exec("ALTER TABLE demarches_historique ADD COLUMN auteur_id INTEGER");
        $pdo->exec("INSERT OR REPLACE INTO parametres (cle, valeur) VALUES ('schema_obligations', '2')");
        return;
    }
    $pdo->exec("
CREATE TABLE IF NOT EXISTS demarches (id INTEGER PRIMARY KEY, titre TEXT NOT NULL, explication TEXT NOT NULL DEFAULT '', ou TEXT NOT NULL DEFAULT '',
  pour_qui TEXT NOT NULL DEFAULT 'youngmi', echeance TEXT NOT NULL DEFAULT '', a_confirmer INTEGER NOT NULL DEFAULT 0, preavis INTEGER NOT NULL DEFAULT 30,
  priorite INTEGER NOT NULL DEFAULT 2, importante INTEGER NOT NULL DEFAULT 0, origine TEXT NOT NULL DEFAULT '', etat TEXT NOT NULL DEFAULT 'a_faire',
  preuve_lien TEXT NOT NULL DEFAULT '', preuve_note TEXT NOT NULL DEFAULT '', raison TEXT NOT NULL DEFAULT '',
  echeance_id INTEGER, periode TEXT NOT NULL DEFAULT '', declencheur TEXT NOT NULL DEFAULT '', ref_genre TEXT NOT NULL DEFAULT '', ref_id TEXT NOT NULL DEFAULT '',
  cree_le TEXT NOT NULL, cree_par TEXT NOT NULL DEFAULT '', modifie_le TEXT NOT NULL DEFAULT '', modifie_par TEXT NOT NULL DEFAULT '',
  fait_le TEXT NOT NULL DEFAULT '', fait_par TEXT NOT NULL DEFAULT '');
CREATE INDEX IF NOT EXISTS demarches_etat ON demarches(etat, echeance);
CREATE UNIQUE INDEX IF NOT EXISTS demarches_periode ON demarches(echeance_id, periode) WHERE echeance_id IS NOT NULL;
CREATE UNIQUE INDEX IF NOT EXISTS demarches_declencheur ON demarches(declencheur) WHERE declencheur <> '';
CREATE TABLE IF NOT EXISTS echeances (id INTEGER PRIMARY KEY, titre TEXT NOT NULL, explication TEXT NOT NULL DEFAULT '', ou TEXT NOT NULL DEFAULT '',
  pour_qui TEXT NOT NULL DEFAULT 'youngmi', priorite INTEGER NOT NULL DEFAULT 2, importante INTEGER NOT NULL DEFAULT 0, frequence TEXT NOT NULL DEFAULT 'annuelle',
  depart TEXT NOT NULL DEFAULT '', preavis INTEGER NOT NULL DEFAULT 30, origine TEXT NOT NULL DEFAULT '', active INTEGER NOT NULL DEFAULT 1,
  cree_le TEXT NOT NULL, cree_par TEXT NOT NULL DEFAULT '', modifie_le TEXT NOT NULL DEFAULT '', modifie_par TEXT NOT NULL DEFAULT '');
CREATE TABLE IF NOT EXISTS demarches_historique (id INTEGER PRIMARY KEY, le TEXT NOT NULL, auteur TEXT NOT NULL, auteur_id INTEGER, demarche_id INTEGER, echeance_id INTEGER,
  type TEXT NOT NULL, champ TEXT NOT NULL DEFAULT '', ancien TEXT NOT NULL DEFAULT '', nouveau TEXT NOT NULL DEFAULT '');
CREATE INDEX IF NOT EXISTS demarches_historique_d ON demarches_historique(demarche_id);
");
    $pdo->exec("INSERT OR REPLACE INTO parametres (cle, valeur) VALUES ('schema_obligations', '2')");
}
/** Les photos des clients (demande d'Olivier du 10/11/2026, inc/photos.php) : une table à part, marqueur schema_photos. */
function photos_schema(PDO $pdo): void {
    if ($pdo->query("SELECT valeur FROM parametres WHERE cle = 'schema_photos'")->fetchColumn() === '1') return;
    $pdo->exec("CREATE TABLE IF NOT EXISTS photos_clients (client_id INTEGER PRIMARY KEY, email TEXT NOT NULL DEFAULT '', image BLOB,
  verifie_le TEXT, maj_le TEXT NOT NULL, masquee INTEGER NOT NULL DEFAULT 0)");
    $pdo->exec("INSERT OR REPLACE INTO parametres (cle, valeur) VALUES ('schema_photos', '1')");
}
/** Les relevés d'hygiène (spécification 004, lot 2) : tables à part, marqueur schema_hygiene. */
function hygiene_schema(PDO $pdo): void {
    if ($pdo->query("SELECT valeur FROM parametres WHERE cle = 'schema_hygiene'")->fetchColumn() === '3') return;
    $pdo->exec("
CREATE TABLE IF NOT EXISTS hygiene_appareils (id INTEGER PRIMARY KEY, nom TEXT NOT NULL, genre TEXT NOT NULL DEFAULT 'frigo', tmin REAL, tmax REAL,
  a_confirmer INTEGER NOT NULL DEFAULT 0, actif INTEGER NOT NULL DEFAULT 1, cree_le TEXT NOT NULL, cree_par TEXT NOT NULL DEFAULT '',
  modifie_le TEXT NOT NULL DEFAULT '', modifie_par TEXT NOT NULL DEFAULT '');
CREATE TABLE IF NOT EXISTS hygiene_releves (id INTEGER PRIMARY KEY, genre TEXT NOT NULL, jour TEXT NOT NULL, le TEXT NOT NULL, par TEXT NOT NULL DEFAULT '', par_id INTEGER,
  appareil_id INTEGER, temperature REAL, tmin REAL, tmax REAL, hors_plage INTEGER NOT NULL DEFAULT 0, action TEXT NOT NULL DEFAULT '',
  fournisseur TEXT NOT NULL DEFAULT '', produit TEXT NOT NULL DEFAULT '', lot TEXT NOT NULL DEFAULT '', dlc TEXT NOT NULL DEFAULT '', photo TEXT NOT NULL DEFAULT '',
  produit_id TEXT NOT NULL DEFAULT '', quantite TEXT NOT NULL DEFAULT '', numero_lot TEXT NOT NULL DEFAULT '',
  commande_id INTEGER, client_id INTEGER, depart_le TEXT NOT NULL DEFAULT '', arrivee_le TEXT NOT NULL DEFAULT '', temp_depart REAL,
  note TEXT NOT NULL DEFAULT '', remplace_id INTEGER, remplace_par INTEGER, motif TEXT NOT NULL DEFAULT '', trace TEXT NOT NULL DEFAULT '');
CREATE INDEX IF NOT EXISTS hygiene_releves_jour ON hygiene_releves(jour, genre);
CREATE TABLE IF NOT EXISTS hygiene_liens (de INTEGER NOT NULL, vers INTEGER NOT NULL, PRIMARY KEY (de, vers));
CREATE TABLE IF NOT EXISTS hygiene_durees (produit_id TEXT PRIMARY KEY, jours INTEGER, maj_le TEXT NOT NULL, maj_par TEXT NOT NULL DEFAULT '',
  conservation TEXT NOT NULL DEFAULT '', ingredients TEXT NOT NULL DEFAULT '', etiquette_le TEXT NOT NULL DEFAULT '', etiquette_par TEXT NOT NULL DEFAULT '',
  date_mention TEXT NOT NULL DEFAULT '', poids_net TEXT NOT NULL DEFAULT '');
CREATE TABLE IF NOT EXISTS hygiene_problemes (id INTEGER PRIMARY KEY, fournee_id INTEGER NOT NULL, numero_lot TEXT NOT NULL, ouvert_le TEXT NOT NULL, ouvert_par TEXT NOT NULL DEFAULT '',
  description TEXT NOT NULL DEFAULT '', etapes TEXT NOT NULL DEFAULT '{}', demarche_id INTEGER, clos_le TEXT NOT NULL DEFAULT '', clos_par TEXT NOT NULL DEFAULT '', bilan TEXT NOT NULL DEFAULT '');
CREATE TABLE IF NOT EXISTS volumes_poids (produit_id TEXT NOT NULL, variante TEXT NOT NULL, grammes INTEGER NOT NULL, maj_le TEXT NOT NULL, maj_par TEXT NOT NULL DEFAULT '',
  PRIMARY KEY (produit_id, variante));
CREATE TABLE IF NOT EXISTS volumes_restaurants (client_id INTEGER PRIMARY KEY, km REAL, maj_le TEXT NOT NULL, maj_par TEXT NOT NULL DEFAULT '');
CREATE TABLE IF NOT EXISTS documents (id INTEGER PRIMARY KEY, attendu TEXT NOT NULL DEFAULT '', titre TEXT NOT NULL, lien TEXT NOT NULL DEFAULT '', valide_jusqua TEXT NOT NULL DEFAULT '',
  note TEXT NOT NULL DEFAULT '', cree_le TEXT NOT NULL, cree_par TEXT NOT NULL DEFAULT '', maj_le TEXT NOT NULL, maj_par TEXT NOT NULL DEFAULT '', retire_le TEXT NOT NULL DEFAULT '', retire_par TEXT NOT NULL DEFAULT '',
  propose_par TEXT NOT NULL DEFAULT '');
CREATE TABLE IF NOT EXISTS volumes_commandes (commande_id INTEGER PRIMARY KEY, grammes INTEGER NOT NULL, maj_le TEXT NOT NULL, maj_par TEXT NOT NULL DEFAULT '');
");
    // Repère 3 (audit n° 8) : traçabilité incomplète assumée (trace), mention et poids net des étiquettes, numéro de lot unique
    $cols = fn(string $t) => array_column($pdo->query("PRAGMA table_info($t)")->fetchAll(PDO::FETCH_ASSOC), 'name');
    if (!in_array('trace', $cols('hygiene_releves'), true)) $pdo->exec("ALTER TABLE hygiene_releves ADD COLUMN trace TEXT NOT NULL DEFAULT ''");
    $d = $cols('hygiene_durees');
    if (!in_array('date_mention', $d, true)) $pdo->exec("ALTER TABLE hygiene_durees ADD COLUMN date_mention TEXT NOT NULL DEFAULT ''");
    if (!in_array('poids_net', $d, true)) $pdo->exec("ALTER TABLE hygiene_durees ADD COLUMN poids_net TEXT NOT NULL DEFAULT ''");
    $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS hygiene_releves_lot ON hygiene_releves(numero_lot) WHERE numero_lot <> '' AND remplace_par IS NULL");
    $pdo->exec("INSERT OR REPLACE INTO parametres (cle, valeur) VALUES ('schema_hygiene', '3')");
}

function q(string $sql, array $p = []): PDOStatement { $s = db()->prepare($sql); $s->execute($p); return $s; }
// Colonnes entières : PHP 8.0 rend les nombres de SQLite en texte, PHP 8.1 et plus en nombres ; on les rend toujours en entiers.
const COLONNES_ENTIERES = ['id' => 1, 'client_id' => 1, 'commande_id' => 1, 'utilisateur_id' => 1, 'ingredient_id' => 1, 'probleme' => 1, 'total_indicatif' => 1,
    'prix_final' => 1, 'acompte' => 1, 'prix' => 1, 'prix_unite' => 1, 'actif' => 1, 'emballage' => 1, 'tva' => 1, 'ok' => 1, 'n' => 1, 'remises' => 1,
    'en_cours' => 1, 'total' => 1, 'ca' => 1, 'lignes_recette' => 1, 'perdues' => 1, 'pros' => 1, 'fil' => 1, 'question' => 1, 'rapport_id' => 1, 'message_id' => 1, 'lu' => 1, 'evenement_id' => 1];
function entiers(array $r): array {
    foreach ($r as $k => $v) if (is_string($v) && isset(COLONNES_ENTIERES[$k]) && preg_match('/^-?\d+$/', $v)) $r[$k] = (int)$v;
    return $r;
}
function ligne(string $sql, array $p = []): ?array { $r = q($sql, $p)->fetch(); return $r === false ? null : entiers($r); }
function lignes(string $sql, array $p = []): array { return array_map(__NAMESPACE__ . '\\entiers', q($sql, $p)->fetchAll()); }
function val(string $sql, array $p = []): mixed { $v = q($sql, $p)->fetchColumn(); return $v === false ? null : $v; }
function parametre(string $cle, ?string $defaut = null): ?string { return val("SELECT valeur FROM parametres WHERE cle = ?", [$cle]) ?? $defaut; }
function parametre_ecrire(string $cle, string $valeur): void { q("INSERT OR REPLACE INTO parametres (cle, valeur) VALUES (?, ?)", [$cle, $valeur]); }
/** Sel secret du serveur (empreintes d'IP) : créé au premier appel, jamais affiché. */
function sel(): string {
    $s = parametre('sel');
    if ($s === null) { $s = bin2hex(random_bytes(32)); parametre_ecrire('sel', $s); }
    return $s;
}
function ip_empreinte(): string { return substr(hash_hmac('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? ''), sel()), 0, 24); }

/* ---------- Session et comptes ---------- */
function gestion_https(): bool { return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') == 443); }
/** Chemin de l'espace sur le site : « /gestion/ » (ou autre si le dossier est renommé). */
function gestion_chemin(): string {
    $s = $_SERVER['SCRIPT_NAME'] ?? '/gestion/index.php';
    $d = rtrim(str_replace('\\', '/', dirname($s)), '/');
    return $d . '/';
}
function gestion_adresse(): string {
    $hote = $_SERVER['HTTP_HOST'] ?? GESTION_DOMAINE;
    if (!preg_match('/^[a-z0-9.\-]+(:\d+)?$/i', $hote)) $hote = GESTION_DOMAINE;
    return (gestion_https() ? 'https' : 'http') . '://' . $hote . gestion_chemin();
}
/** Vrai quand le code tourne pour le formulaire public (ma-demande/envoyer.php) : ni session ni cookie pour les visiteurs du site. */
function contexte_site(): bool { return defined('GESTION_CONTEXTE_SITE'); }
function session_ouvrir(): void {
    if (session_status() === PHP_SESSION_ACTIVE || PHP_SAPI === 'cli' || contexte_site()) return;
    session_name('mc_gestion');
    session_set_cookie_params(['lifetime' => 0, 'path' => gestion_chemin(), 'secure' => gestion_https(), 'httponly' => true, 'samesite' => 'Lax']);
    session_start();
}
function utilisateur_courant(): ?array {
    static $u = false;
    if ($u !== false) return $u;
    $u = null;
    if (PHP_SAPI === 'cli' || contexte_site()) return null;
    session_ouvrir();
    $uid = $_SESSION['uid'] ?? null;
    if ($uid && (time() - (int)($_SESSION['vu'] ?? 0)) > GESTION_SESSION_INACTIVE) {   // 12 h sans rien : la session s'arrête (le jeton « rester connectée » reste valable)
        $_SESSION = []; session_regenerate_id(true); $uid = null;
    }
    if (!$uid && !empty($_COOKIE['mc_souvenir'])) {   // « rester connectée » : jeton de 30 jours
        $j = jeton_verifier((string)$_COOKIE['mc_souvenir'], 'session');
        if ($j) { $uid = (int)$j['utilisateur_id']; $_SESSION['uid'] = $uid; $_SESSION['depuis'] = maintenant(); }
    }
    if (!$uid) return null;
    $u = ligne("SELECT * FROM utilisateurs WHERE id = ? AND actif = 1", [$uid]);
    if (!$u) { session_fermer(); return null; }
    // Une session ouverte avant le dernier changement de mot de passe s'arrête : les autres appareils se reconnectent (audit du 07/11, C2)
    if (empty($_SESSION['depuis'])) $_SESSION['depuis'] = maintenant();
    if (!empty($u['mdp_change_le']) && $_SESSION['depuis'] < $u['mdp_change_le']) { $u = null; session_fermer(); return null; }
    $_SESSION['vu'] = time();
    return $u;
}
function session_fermer(): void {
    session_ouvrir();
    if (!empty($_COOKIE['mc_souvenir'])) {
        $j = jeton_verifier((string)$_COOKIE['mc_souvenir'], 'session');
        if ($j) q("DELETE FROM jetons WHERE id = ?", [$j['id']]);
        setcookie('mc_souvenir', '', ['expires' => time() - 3600, 'path' => gestion_chemin(), 'secure' => gestion_https(), 'httponly' => true, 'samesite' => 'Lax']);
    }
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) session_destroy();
}
function auteur(): string {
    if (contexte_site()) return 'site';
    if (PHP_SAPI === 'cli') return 'système';
    $u = utilisateur_courant();
    return $u ? $u['nom'] : 'site';
}
function est_admin(): bool { $u = utilisateur_courant(); return $u && $u['role'] === 'admin'; }
/** Trop d'échecs récents pour cet email ou cette adresse : vrai si la connexion doit attendre. */
function connexion_bloquee(string $email): bool {
    $n = (int)val("SELECT COUNT(*) FROM connexions WHERE ok = 0 AND le > ? AND (email = ? OR ip = ?)",
                  [date('Y-m-d H:i:s', time() - 900), $email, ip_empreinte()]);
    return $n >= GESTION_ECHECS_MAX;
}
function connexion_noter(string $email, bool $ok, string $detail = ''): void {
    q("INSERT INTO connexions (le, email, ok, ip, detail) VALUES (?, ?, ?, ?, ?)", [maintenant(), $email, $ok ? 1 : 0, ip_empreinte(), $detail]);
}
/** Tente la connexion ; renvoie l'utilisateur ou null. */
function connexion_tenter(string $email, string $mdp, bool $souvenir): ?array {
    $email = mb_strtolower(trim($email));
    if (connexion_bloquee($email)) { connexion_noter($email, false, 'bloqué'); return null; }
    $u = ligne("SELECT * FROM utilisateurs WHERE email = ? AND actif = 1", [$email]);
    if (!$u || $u['mdp'] === '' || !password_verify($mdp, $u['mdp'])) { connexion_noter($email, false); return null; }
    if (password_needs_rehash($u['mdp'], PASSWORD_DEFAULT)) q("UPDATE utilisateurs SET mdp = ? WHERE id = ?", [password_hash($mdp, PASSWORD_DEFAULT), $u['id']]);
    session_pour($u, $souvenir);
    connexion_noter($email, true);
    return $u;
}
/** Ouvre la session de $u (nouvel identifiant de session), et le jeton « rester connectée » si demandé. */
function session_pour(array $u, bool $souvenir): void {
    session_ouvrir();
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$u['id']; $_SESSION['vu'] = time(); $_SESSION['depuis'] = maintenant();
    q("UPDATE utilisateurs SET derniere_connexion = ? WHERE id = ?", [maintenant(), $u['id']]);
    if ($souvenir) {
        $j = jeton_creer((int)$u['id'], 'session', GESTION_SOUVENIR);
        setcookie('mc_souvenir', $j, ['expires' => time() + GESTION_SOUVENIR, 'path' => gestion_chemin(), 'secure' => gestion_https(), 'httponly' => true, 'samesite' => 'Lax']);
    }
}
/** Jeton « id.secret » : seul son empreinte est gardée en base. */
function jeton_creer(int $uid, string $type, int $duree): string {
    $secret = bin2hex(random_bytes(24));
    q("INSERT INTO jetons (utilisateur_id, type, empreinte, expire_le, cree_le) VALUES (?, ?, ?, ?, ?)",
      [$uid, $type, hash('sha256', $secret), date('Y-m-d H:i:s', time() + $duree), maintenant()]);
    return db()->lastInsertId() . '.' . $secret;
}
function jeton_verifier(string $j, string $type): ?array {
    if (!preg_match('/^(\d{1,9})\.([a-f0-9]{48})$/', $j, $m)) return null;
    $r = ligne("SELECT * FROM jetons WHERE id = ? AND type = ? AND utilise_le IS NULL AND expire_le > ?", [(int)$m[1], $type, maintenant()]);
    if (!$r || !hash_equals($r['empreinte'], hash('sha256', $m[2]))) return null;
    return $r;
}
function mdp_acceptable(string $mdp): ?string {
    if (mb_strlen($mdp) < GESTION_MDP_MIN) return 'Le mot de passe doit faire au moins ' . GESTION_MDP_MIN . ' caractères (une phrase facile à retenir, par exemple).';
    if (mb_strlen($mdp) > 200) return 'Le mot de passe est trop long.';
    if (preg_match('/^(.)\1+$/u', $mdp) || stripos($mdp, 'madamecho') !== false || stripos($mdp, 'motdepasse') !== false) return 'Ce mot de passe est trop facile à deviner.';
    return null;
}
function mdp_changer(int $uid, string $mdp): void {
    $now = maintenant();
    q("UPDATE utilisateurs SET mdp = ?, mdp_change_le = ? WHERE id = ?", [password_hash($mdp, PASSWORD_DEFAULT), $now, $uid]);
    q("DELETE FROM jetons WHERE utilisateur_id = ? AND type = 'session'", [$uid]);   // les jetons « rester connectée » des autres appareils
    // Cette session continue ; les autres sessions ouvertes de ce compte s'arrêtent à leur prochaine page (utilisateur_courant)
    if (session_status() === PHP_SESSION_ACTIVE && (int)($_SESSION['uid'] ?? 0) === $uid) $_SESSION['depuis'] = $now;
}

/* ---------- Protection des formulaires et messages ---------- */
function csrf(): string {
    session_ouvrir();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}
function csrf_champ(): string { return '<input type="hidden" name="_jeton" value="' . h(csrf()) . '">'; }
function csrf_verifier(): void {
    $j = $_POST['_jeton'] ?? '';
    if (!is_string($j) || !hash_equals(csrf(), $j)) {
        http_response_code(400);
        exit(function_exists(__NAMESPACE__ . '\\coreen_actif') && coreen_actif() ? '입력 시간이 지났어요. 뒤로 가서 다시 해 주세요. (Formulaire expiré)' : 'Formulaire expiré : revenez en arrière et recommencez.');
    }
}
function flash(string $type, string $texte): void { session_ouvrir(); $_SESSION['flash'] = [$type, $texte]; }
function flash_lire(): ?array { session_ouvrir(); $f = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $f; }
function u(string $page, array $params = []): string {
    $p = ['p' => $page] + $params;
    return '?' . http_build_query($p);
}
function rediriger(string $page, array $params = [], string $ancre = ''): void {
    // Une modification préparée par l'assistant et confirmée : on la note faite (ou refusée) et on revient à l'assistant (assistant-donnees.php)
    if (!empty($GLOBALS['mc_proposition']) && function_exists(__NAMESPACE__ . '\\proposition_apres')) [$page, $params, $ancre] = proposition_apres($page, $params, $ancre);
    header('Location: ' . u($page, $params) . ($ancre !== '' ? '#' . rawurlencode($ancre) : ''));
    exit;
}

/* ---------- Historique ---------- */
function evenement(string $type, string $texte = '', array $liens = [], string $ancien = '', string $nouveau = '', ?string $auteur = null): void {
    q("INSERT INTO evenements (le, auteur, type, commande_id, client_id, produit_id, texte, ancien, nouveau) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
      [maintenant(), $auteur ?? auteur(), $type, $liens['commande'] ?? null, $liens['client'] ?? null, $liens['produit'] ?? null, $texte, $ancien, $nouveau]);
}

/* ---------- Catalogue du site (généré par gen.py) ---------- */
function catalogue(): array {
    static $c = null;
    if ($c !== null) return $c;
    foreach ([__DIR__ . '/catalogue.php', gestion_site() . '/ma-demande/catalogue.php'] as $f) {
        if (is_file($f)) { $c = require $f; if (is_array($c)) return $c; }
    }
    return $c = ['produits' => [], 'moments' => MOMENTS];
}
/** Toutes les variantes vendables : « produit|code » => [nom, libelle, prix, produit, code]. */
function variantes(): array {
    $v = [];
    foreach (catalogue()['produits'] as $pid => $p) {
        if ($p['mode'] === 'taille') {
            foreach ($p['tailles'] as $c => $t) $v["$pid|$c"] = ['produit' => $pid, 'code' => $c, 'nom' => $p['nom'], 'libelle' => $t['libelle'], 'prix' => (int)$t['prix'], 'plaque' => !empty($p['plaque'])];
        } else {
            foreach ($p['parfums'] as $c => $t) $v["$pid|$c"] = ['produit' => $pid, 'code' => $c, 'nom' => $p['nom'], 'libelle' => $t['libelle'], 'prix' => (int)$p['unite'], 'plaque' => false];
        }
    }
    return $v;
}
