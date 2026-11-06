<?php
/*
 * Madame Cho : espace de gestion. L'assistant de Youngmi (demande d'Olivier, 06/11/2026) : elle lui pose ses questions sur le
 * fonctionnement, lui dit ses problèmes, lui propose des améliorations. Il répond à partir du manuel (manuel.php), lui dit
 * franchement quand elle se trompe et, quand un vrai problème ou un vrai besoin est établi, prépare un rapport que Youngmi
 * envoie elle-même, d'un bouton, à contact@madamecho.com (R-01 : aucune IA n'envoie d'email). Olivier le lit et fait évoluer la gestion.
 * Il tourne avec Claude (API d'Anthropic). La clé d'accès est collée dans Réglages et vit dans la base, hors du site : jamais dans
 * ce dépôt public (R-08), jamais réaffichée, jamais dans la sauvegarde. Les échanges partent chez Anthropic : les clients y sont
 * désignés par leur numéro, jamais d'information de santé. Les conversations sont gardées 6 mois, les rapports sont gardés.
 */
declare(strict_types=1);
namespace MadameCho\Gestion;

// L'API de Claude. En essai local (MC_ESSAI=1), MC_ASSISTANT=http://127.0.0.1:8769/ pointe vers une fausse API.
define('GESTION_ASSISTANT_API', GESTION_ESSAI && getenv('MC_ASSISTANT') ? rtrim((string)getenv('MC_ASSISTANT'), '/') . '/' : 'https://api.anthropic.com/');
const GESTION_ASSISTANT_MODELE = 'claude-opus-5-5';
const GESTION_ASSISTANT_API_VERSION = '2026-10-21';
// Bêta d'Anthropic : si le modèle refuse un message (filtres de sécurité), un autre modèle répond dans le même appel.
// Si l'API ne l'accepte plus un jour, la gestion réessaie sans (voir assistant_appel).
const GESTION_ASSISTANT_REPLI = 'server-side-fallback-2026-07-01';
const GESTION_ASSISTANT_EFFORT = 'medium';     // profondeur de réflexion : low, medium, high (coût et délai croissants)
const GESTION_ASSISTANT_MAX_TOKENS = 6000;     // réflexion comprise : une réponse courte, pas un roman
const GESTION_ASSISTANT_FENETRE = 24;          // messages de la conversation rejoués à chaque question
const GESTION_ASSISTANT_MAX_JOUR = 60;         // questions par jour, tous comptes : garde-fou sur le coût
const GESTION_ASSISTANT_MAX_CAR = 2000;        // longueur d'un message
const GESTION_ASSISTANT_MOIS = 6;              // conversations gardées 6 mois (purge de nuit)
// Prix publics d'Anthropic pour ce modèle, en dollars par million de jetons : pour l'estimation affichée à Olivier
const GESTION_ASSISTANT_PRIX = ['entree' => 4.0, 'cache_ecriture' => 5.0, 'cache_lecture' => 0.20, 'sortie' => 20.0];
const RAPPORT_TYPES = ['probleme' => 'Problème', 'amelioration' => 'Amélioration', 'question' => 'Question'];

function assistant_relie(): bool { return (string)parametre('assistant_cle', '') !== ''; }

/* ---------- L'API ---------- */
/** Un appel à l'API (la clé ne passe que dans l'en-tête, jamais dans l'adresse ni dans un message). Renvoie [code HTTP, réponse décodée]. */
function assistant_http(string $methode, string $chemin, ?array $corps, string $cle, int $delai, bool $repli = false): array {
    if (!function_exists('curl_init')) throw new \RuntimeException('cURL manque sur ce serveur');
    $entetes = ['content-type: application/json', 'x-api-key: ' . $cle, 'anthropic-version: ' . GESTION_ASSISTANT_API_VERSION, 'user-agent: Gestion Madame Cho'];
    if ($repli) $entetes[] = 'anthropic-beta: ' . GESTION_ASSISTANT_REPLI;
    $ch = curl_init(GESTION_ASSISTANT_API . $chemin);
    $o = [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => $delai, CURLOPT_HTTPHEADER => $entetes];
    if ($methode === 'POST') { $o[CURLOPT_POST] = true; $o[CURLOPT_POSTFIELDS] = json_encode($corps, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); }
    curl_setopt_array($ch, $o);
    $r = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err = curl_error($ch);
    if (!is_string($r)) {
        error_log('madamecho gestion assistant : ' . $err);
        throw new \RuntimeException(stripos($err, 'timed out') !== false ? 'l\'assistant met trop de temps à répondre' : 'impossible de joindre l\'assistant');
    }
    return [$code, json_decode($r, true)];
}

/** Le message d'une erreur de l'API, en clair et sans jamais recopier la clé. */
function assistant_erreur_api(int $code, mixed $j): string {
    $m = is_array($j) ? trim(strip_tags((string)($j['error']['message'] ?? ''))) : '';
    $m = preg_replace('/sk-ant-[A-Za-z0-9_\-]+/', '…', $m) ?? $m;
    return match (true) {
        $code === 401 => 'clé refusée par Anthropic',
        $code === 403 => 'cette clé n\'a pas le droit d\'utiliser ce modèle',
        $code === 404 => 'modèle introuvable chez Anthropic',
        $code === 429 => 'trop de demandes à la fois, réessayez dans une minute',
        $code === 529, $code === 503 => 'l\'assistant est surchargé, réessayez dans un moment',
        $code >= 500 => 'l\'assistant a un souci de son côté, réessayez',
        $code === 400 => 'demande refusée par l\'API' . ($m !== '' ? ' (' . mb_substr($m, 0, 160) . ')' : ''),
        default => 'réponse inattendue de l\'API (code ' . $code . ')',
    };
}

/** Vérifie une clé avant de l'enregistrer, sans rien dépenser : elle doit pouvoir lire la fiche du modèle. Renvoie '' si elle marche, sinon la raison. */
function assistant_essayer_cle(string $cle): string {
    try {
        [$code, $j] = assistant_http('GET', 'v1/models/' . GESTION_ASSISTANT_MODELE, null, $cle, 15);
        if ($code === 200 && is_array($j) && ($j['id'] ?? '') === GESTION_ASSISTANT_MODELE) return '';
        return assistant_erreur_api($code, $j);
    } catch (\Throwable $e) {
        return $e->getMessage();
    }
}

/** L'outil que le modèle appelle pour proposer un rapport (Youngmi décide ensuite de l'envoyer). */
function assistant_outils(): array {
    return [[
        'name' => 'proposer_rapport',
        'description' => 'Prépare un rapport pour Olivier quand un vrai problème de la gestion ou un vrai besoin d\'amélioration est établi avec Youngmi. Le rapport s\'affiche sous ta réponse avec un bouton « Envoyer » : c\'est Youngmi qui l\'envoie, jamais toi. Écris-le en français, complet et précis, sans donnée personnelle de client (numéros CL-… et MC-… seulement). Un seul rapport par sujet : pour le compléter, propose-le à nouveau en entier.',
        'strict' => true,
        'input_schema' => [
            'type' => 'object', 'additionalProperties' => false,
            'required' => ['type', 'titre', 'resume', 'detail', 'ecran', 'attendu'],
            'properties' => [
                'type' => ['type' => 'string', 'enum' => ['probleme', 'amelioration', 'question'], 'description' => 'probleme : la gestion ne fait pas ce que le manuel dit ; amelioration : un vrai besoin ; question : une question à laquelle le manuel ne répond pas'],
                'titre' => ['type' => 'string', 'description' => 'Une ligne, 80 caractères au plus'],
                'resume' => ['type' => 'string', 'description' => 'Deux ou trois phrases : ce qui se passe ou ce qui manque, et pourquoi cela compte pour Youngmi'],
                'detail' => ['type' => 'string', 'description' => 'Ce que Youngmi a fait, étape par étape ; ce qu\'elle attendait ; ce qui s\'est affiché ; ses mots quand ils comptent (traduits en français) ; ce que vous avez déjà vérifié ensemble'],
                'ecran' => ['type' => 'string', 'description' => 'L\'écran ou la partie de la gestion concernée, par exemple « Une commande › Confirmer la commande »'],
                'attendu' => ['type' => 'string', 'description' => 'Ce que Youngmi souhaite à la place, ou l\'amélioration proposée, dite simplement'],
            ],
        ],
    ]];
}

/** Les consignes et le manuel. Le premier bloc ne change pas d'une question à l'autre : Anthropic le garde en cache (moins cher, plus rapide). */
function assistant_systeme(array $u): array {
    $consignes = <<<'TXT'
Tu es l'assistant de l'espace de gestion de Madame Cho (madamecho.com/gestion), la pâtisserie de Youngmi Cho à Meudon. Tu parles avec Youngmi, ou avec une personne qu'elle a invitée. Olivier Dion, son mari, a construit la gestion avec son IA et la fait évoluer : c'est lui qui lit les rapports.

Ton rôle
1. Expliquer comment fonctionne la gestion, à partir du manuel ci-dessous. C'est ta seule source : n'invente jamais un écran, un bouton, un réglage ou un comportement qui n'y est pas. Si le manuel ne dit rien, dis-le simplement.
2. Aider quand quelque chose ne marche pas : pose les questions utiles (quel écran, quelle commande ou quel client, par son numéro ; ce qu'elle a fait ; ce qu'elle attendait ; ce qui s'est affiché), puis distingue une erreur de manipulation d'un vrai défaut de la gestion.
3. Recueillir ses idées d'amélioration et les discuter honnêtement : ce que ça apporterait, ce que ça compliquerait, si ça respecte les règles.
4. Quand un vrai problème ou un vrai besoin d'amélioration est établi, appelle l'outil proposer_rapport. Le rapport s'affiche sous ta réponse avec un bouton : c'est Youngmi qui l'envoie à Olivier. Tu ne peux rien envoyer toi-même : ne dis jamais que tu as envoyé ou transmis quelque chose. Pas de rapport pour une simple question de fonctionnement, pour une erreur de manipulation, ni pour une idée contraire aux règles. Une question à laquelle le manuel ne répond pas peut mériter un rapport de type « question ». Avant de proposer un rapport, assure-toi d'avoir compris : au besoin, une ou deux questions d'abord.

Franchise
Si Youngmi se trompe (sur le fonctionnement, sur une règle, ou si ce qu'elle demande va contre les règles), dis-le clairement et gentiment, explique pourquoi, et propose la bonne façon de faire. Ne lui donne pas raison pour lui faire plaisir, ne flatte pas, ne t'excuse pas sans raison.

Langue
Réponds dans la langue de son message : en coréen (해요체, naturel et simple) si elle écrit en coréen, sinon en français (vouvoiement). Quand tu cites un écran, un bouton ou un réglage, utilise le libellé dans la langue de son écran (il est indiqué plus bas), avec le libellé français entre parenthèses quand c'est utile. Les rapports sont toujours écrits en français.

Style
Réponses courtes et concrètes. Quand il y a quelque chose à faire, les étapes dans l'ordre. Pas de jargon, pas de titres. Une liste « - » quand elle aide. Le gras **…** pour un bouton ou un mot important. Une seule question à la fois.

Les règles de la maison, à connaître et à faire respecter
- Aucun email ne part tout seul : la gestion prépare les textes, Youngmi les envoie depuis contact@madamecho.com.
- Ce qu'on écrit dans la gestion (noms, gâteaux, commentaires) est en français : ça part tel quel dans les emails, les factures et l'historique. L'écran, lui, peut être en coréen, et elle peut t'écrire en coréen.
- Jamais d'information de santé : pour une allergie, seulement l'ingrédient à éviter.
- Un client qui revient est reconnu par son email ou son téléphone, jamais par son nom.
- Données personnelles : parle des clients et des commandes par leur numéro (CL-…, MC-…). Ne recopie jamais de coordonnées dans un rapport. Tu ne vois ni les commandes ni les clients : si elle te les décrit, ne demande que le nécessaire.
- Les mots de passe et les clés ne se partagent jamais, même avec toi. Si elle t'en donne un, dis-lui de le changer tout de suite.
- Olivier s'occupe de la technique, des données et des factures ; Mathieu fait le point des commandes avec elle le mardi et le vendredi.

Ce que tu ne fais pas
Tu ne peux rien faire dans la gestion à sa place (ni modifier, ni envoyer). Tu ne donnes pas de conseil juridique, comptable ou médical : renvoie à Olivier ou au comptable. Hors de la gestion et de l'activité de Madame Cho, réponds en une phrase que ce n'est pas ton rôle.
TXT;
    $manuel = "Le manuel de la gestion, en français (les libellés de l'écran en français) :\n\n" . manuel_texte('fr')
            . "\n\nLe même manuel en coréen (les libellés de l'écran en coréen) :\n\n" . manuel_texte('ko');
    $contexte = 'Maintenant : ' . date_fr(maintenant(), 'heure') . '. La personne connectée : ' . $u['nom'] . '. Son écran est en ' . (coreen_actif() ? 'coréen' : 'français')
              . '. Version de la gestion : ' . GESTION_VERSION . '.';
    return [
        ['type' => 'text', 'text' => $consignes . "\n\n" . $manuel, 'cache_control' => ['type' => 'ephemeral']],
        ['type' => 'text', 'text' => $contexte],
    ];
}

/** L'appel au modèle. Si l'API refuse l'option de repli (bêta), on réessaie sans elle. */
function assistant_appel(array $corps, string $cle): array {
    @set_time_limit(150);
    $corps['model'] = GESTION_ASSISTANT_MODELE;
    $corps['max_tokens'] = GESTION_ASSISTANT_MAX_TOKENS;
    $corps['output_config'] = ['effort' => GESTION_ASSISTANT_EFFORT];
    $corps['fallbacks'] = 'default';
    [$code, $j] = assistant_http('POST', 'v1/messages', $corps, $cle, 120, true);
    if ($code === 400 && is_array($j) && preg_match('/fallback|anthropic-beta/i', (string)($j['error']['message'] ?? ''))) {
        unset($corps['fallbacks']);
        [$code, $j] = assistant_http('POST', 'v1/messages', $corps, $cle, 120, false);
    }
    if ($code !== 200 || !is_array($j) || !is_array($j['content'] ?? null)) throw new \RuntimeException(assistant_erreur_api($code, $j));
    return $j;
}

/* ---------- La conversation (base) ---------- */
/** Le numéro de la conversation en cours d'une personne (une nouvelle en commence une autre, les anciennes restent 6 mois). */
function assistant_fil(int $uid): int {
    $max = (int)(val("SELECT MAX(fil) FROM assistant_messages WHERE utilisateur_id = ?", [$uid]) ?? 0);
    return max(1, $max, (int)parametre('assistant_fil_' . $uid, '0'));
}
function assistant_noter(int $uid, int $fil, string $role, array $contenu, bool $question = false, array $usage = []): int {
    q("INSERT INTO assistant_messages (utilisateur_id, fil, le, role, contenu, question, usage) VALUES (?, ?, ?, ?, ?, ?, ?)",
      [$uid, $fil, maintenant(), $role, json_encode($contenu, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $question ? 1 : 0, $usage ? json_encode($usage) : '']);
    return (int)db()->lastInsertId();
}
function assistant_messages(int $uid, int $fil): array {
    $l = lignes("SELECT * FROM assistant_messages WHERE utilisateur_id = ? AND fil = ? ORDER BY id", [$uid, $fil]);
    foreach ($l as &$m) { $c = json_decode($m['contenu'], true); $m['blocs'] = is_array($c) ? $c : []; }
    return $l;
}
/** Les derniers messages à rejouer, en commençant par une vraie question (jamais au milieu d'un appel d'outil). */
function assistant_fenetre(array $messages): array {
    $l = array_slice($messages, -GESTION_ASSISTANT_FENETRE);
    while ($l && !($l[0]['role'] === 'user' && (int)$l[0]['question'] === 1)) array_shift($l);
    return array_map(fn($m) => ['role' => $m['role'], 'content' => $m['blocs']], $l);
}
function assistant_texte(array $blocs): string {
    $t = '';
    foreach ($blocs as $b) if (($b['type'] ?? '') === 'text') $t .= (string)($b['text'] ?? '');
    return trim($t);
}

/** Une question de Youngmi : on l'enregistre, on interroge le modèle (deux fois s'il propose un rapport), on enregistre la réponse. Renvoie la raison si ça n'a pas marché. */
function assistant_repondre(int $uid, int $fil, string $texte, array $u): string {
    $cle = (string)parametre('assistant_cle', '');
    if ($cle === '') return 'l\'assistant n\'est pas relié';
    $jour = (int)val("SELECT COUNT(*) FROM assistant_messages WHERE role = 'user' AND question = 1 AND le >= ?", [aujourdhui() . ' 00:00:00']);
    if ($jour >= GESTION_ASSISTANT_MAX_JOUR) return 'le nombre de questions du jour est atteint (' . GESTION_ASSISTANT_MAX_JOUR . ') : à demain, ou prévenez Olivier';
    $idQuestion = assistant_noter($uid, $fil, 'user', [['type' => 'text', 'text' => $texte]], true);
    $corps = ['system' => assistant_systeme($u), 'tools' => assistant_outils(), 'messages' => assistant_fenetre(assistant_messages($uid, $fil))];
    $notes = [];   // les messages enregistrés pendant ce tour, pour les retirer si ça échoue
    $rapport = null;
    try {
        for ($tour = 0; $tour < 3; $tour++) {
            $rep = assistant_appel($corps, $cle);
            $contenu = $rep['content'];
            $usage = array_intersect_key(is_array($rep['usage'] ?? null) ? $rep['usage'] : [], array_flip(['input_tokens', 'cache_creation_input_tokens', 'cache_read_input_tokens', 'output_tokens']));
            $stop = (string)($rep['stop_reason'] ?? '');
            if ($stop === 'refusal') throw new \RuntimeException('l\'assistant n\'a pas pu répondre à ce message');
            // On garde le texte et les appels d'outil ; la réflexion (thinking) n'est rejouée que dans ce tour
            $garde = array_values(array_filter($contenu, fn($b) => is_array($b) && (($b['type'] ?? '') === 'tool_use' || (($b['type'] ?? '') === 'text' && trim((string)($b['text'] ?? '')) !== ''))));
            if ($stop === 'max_tokens') foreach ($garde as &$b) if ($b['type'] === 'text') { $b['text'] = rtrim($b['text']) . ' […]'; break; }
            unset($b);
            if (!$garde) $garde = [['type' => 'text', 'text' => '…']];
            $notes[] = assistant_noter($uid, $fil, 'assistant', $garde, false, $usage);
            $outils = array_values(array_filter($contenu, fn($b) => is_array($b) && ($b['type'] ?? '') === 'tool_use'));
            if ($stop !== 'tool_use' || !$outils) break;
            $resultats = [];
            foreach ($outils as $o) {
                $r = ($o['name'] ?? '') === 'proposer_rapport' ? rapport_proposer($uid, $fil, is_array($o['input'] ?? null) ? $o['input'] : []) : null;
                if ($r !== null) $rapport = $r;
                $resultats[] = ['type' => 'tool_result', 'tool_use_id' => (string)($o['id'] ?? ''), 'is_error' => $r === null,
                                'content' => $r === null ? 'Outil inconnu ou champs manquants : réessaie avec les six champs.'
                                           : 'Le rapport « ' . $r['titre'] . ' » est affiché sous ta réponse, avec le bouton pour l\'envoyer à Olivier. Dis-le à Youngmi en une phrase, sans le recopier, et propose-lui de le compléter si elle veut.'];
            }
            $notes[] = assistant_noter($uid, $fil, 'user', $resultats);
            $corps['messages'][] = ['role' => 'assistant', 'content' => $contenu];   // réflexion comprise : l'API l'exige dans le même tour
            $corps['messages'][] = ['role' => 'user', 'content' => $resultats];
        }
    } catch (\Throwable $e) {
        // Rien n'est gardé : Youngmi retrouve son message et peut réessayer
        q("DELETE FROM assistant_messages WHERE id IN (" . implode(',', array_map('intval', array_merge([$idQuestion], $notes))) . ")");
        if ($rapport) q("DELETE FROM rapports WHERE id = ?", [(int)$rapport['id']]);
        return $e->getMessage();
    }
    if ($rapport && $notes) q("UPDATE assistant_messages SET rapport_id = ? WHERE id = ?", [(int)$rapport['id'], (int)end($notes)]);
    return '';
}

/* ---------- Les rapports ---------- */
function rapport_proposer(int $uid, int $fil, array $in): ?array {
    $r = [];
    foreach (['type', 'titre', 'resume', 'detail', 'ecran', 'attendu'] as $k) {
        if (!is_string($in[$k] ?? null) || trim($in[$k]) === '') return null;
        $r[$k] = mb_substr(trim(str_replace(["\r\n", "\r"], "\n", $in[$k])), 0, $k === 'titre' ? 120 : 4000);
    }
    if (!isset(RAPPORT_TYPES[$r['type']])) $r['type'] = 'question';
    q("UPDATE rapports SET etat = 'remplace' WHERE utilisateur_id = ? AND fil = ? AND etat = 'propose'", [$uid, $fil]);
    q("INSERT INTO rapports (utilisateur_id, fil, le, type, titre, resume, detail, ecran, attendu, etat) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'propose')",
      [$uid, $fil, maintenant(), $r['type'], $r['titre'], $r['resume'], $r['detail'], $r['ecran'], $r['attendu']]);
    $r['id'] = (int)db()->lastInsertId();
    return $r;
}

/** Le texte de l'email du rapport, en français, avec les derniers échanges pour qu'Olivier ait le contexte. */
function rapport_email(array $r, array $u): array {
    $extrait = '';
    foreach (array_slice(array_filter(assistant_messages((int)$r['utilisateur_id'], (int)$r['fil']), fn($m) => assistant_texte($m['blocs']) !== ''), -10) as $m) {
        $extrait .= ($m['role'] === 'user' ? $u['nom'] : 'Assistant') . ' : ' . assistant_texte($m['blocs']) . "\n\n";
    }
    $corps = 'Rapport envoyé depuis l\'espace de gestion par ' . $u['nom'] . ' (' . $u['email'] . '), le ' . date_fr(maintenant(), 'heure') . ".\n"
           . 'Type : ' . RAPPORT_TYPES[$r['type']] . "\n" . 'Écran : ' . $r['ecran'] . "\n\n"
           . 'TITRE : ' . $r['titre'] . "\n\n" . "Résumé\n" . $r['resume'] . "\n\n" . "Détail\n" . $r['detail'] . "\n\n" . "Ce qui est souhaité\n" . $r['attendu'] . "\n\n"
           . "Extrait de la conversation\n" . trim($extrait) . "\n\n"
           . '— Gestion Madame Cho · version du ' . date_fr(GESTION_VERSION, 'longue') . ' · écran en ' . (coreen_actif() ? 'coréen' : 'français') . ' · rapport n° ' . $r['id'] . "\n";
    return ['sujet' => 'Gestion · ' . RAPPORT_TYPES[$r['type']] . ' : ' . $r['titre'], 'corps' => $corps];
}

/* ---------- L'affichage ---------- */
/** Le texte de l'assistant en HTML sûr : paragraphes, listes, gras. Rien d'autre. */
function assistant_html(string $t): string {
    $gras = fn(string $s): string => preg_replace('/\*\*(.+?)\*\*/su', '<b>$1</b>', h($s)) ?? h($s);
    $h = '';
    foreach (preg_split("/\n{2,}/", trim(str_replace(["\r\n", "\r"], "\n", $t))) ?: [] as $bloc) {
        $lignes = array_values(array_filter(array_map('trim', explode("\n", $bloc)), fn($l) => $l !== ''));
        if (!$lignes) continue;
        $puces = count(array_filter($lignes, fn($l) => preg_match('/^(?:[-*•]|\d+[.)])\s+/u', $l))) === count($lignes);
        if ($puces) {
            $num = preg_match('/^\d+[.)]\s+/u', $lignes[0]);
            $h .= '<' . ($num ? 'ol' : 'ul') . '>';
            foreach ($lignes as $l) $h .= '<li>' . $gras(preg_replace('/^(?:[-*•]|\d+[.)])\s+/u', '', $l) ?? $l) . '</li>';
            $h .= '</' . ($num ? 'ol' : 'ul') . '>';
        } else {
            $h .= '<p>' . implode('<br>', array_map(fn($l) => $gras(preg_replace('/^#+\s+/u', '', $l) ?? $l), $lignes)) . '</p>';
        }
    }
    return $h;
}

function rapport_type_libelle(string $t): string {
    return fr_ko(RAPPORT_TYPES[$t] ?? $t, ['probleme' => '문제', 'amelioration' => '개선', 'question' => '질문'][$t] ?? $t);
}

/** La carte d'un rapport sous la réponse de l'assistant : à envoyer, envoyé, ou mis de côté. */
function carte_rapport(array $r): string {
    $etat = (string)$r['etat'];
    $h = '<div class="rapport' . ($etat === 'propose' ? ' a-envoyer' : '') . '"><h3><span class="statut s-rapport-' . h($r['type']) . '">' . rapport_type_libelle($r['type']) . '</span><span translate="no">' . h($r['titre']) . '</span></h3>'
       . '<p translate="no">' . nl2br(h($r['resume'])) . '</p>'
       . '<details class="mini"><summary>' . fr_ko('Voir le rapport complet', '보고서 전체 보기') . '</summary><pre translate="no">' . h("Écran : " . $r['ecran'] . "\n\n" . $r['detail'] . "\n\nCe qui est souhaité :\n" . $r['attendu']) . '</pre></details>';
    if ($etat === 'propose') {
        $h .= '<p class="aide">' . fr_ko('Le rapport part à contact@madamecho.com ; Olivier le lit et regarde comment améliorer la gestion. Pour le compléter, répondez à l\'assistant.',
                                        '보고서는 contact@madamecho.com으로 가요. Olivier가 읽고 관리 시스템을 어떻게 고칠지 봐요. 보태고 싶은 게 있으면 어시스턴트에게 답해 주세요.') . '</p>'
            . '<div class="actions">' . formulaire('rapport_envoyer', '<input type="hidden" name="id" value="' . (int)$r['id'] . '">' . bouton(icone('email') . 'Envoyer le rapport à Olivier', 'principal'))
            . formulaire('rapport_ignorer', '<input type="hidden" name="id" value="' . (int)$r['id'] . '">' . bouton('Pas maintenant', 'discret')) . '</div>';
    } elseif ($etat === 'envoye') {
        $h .= '<p class="ok">' . icone('email') . ' Envoyé le ' . h(date_fr((string)$r['envoye_le'], 'heure')) . ' à contact@madamecho.com</p>';
    } elseif ($etat === 'ignore') {
        $h .= '<p class="petit">Non envoyé</p>';
    } else {
        $h .= '<p class="petit">Remplacé par le rapport suivant</p>';
    }
    return $h . '</div>';
}

/** Le coût estimé du mois (prix publics d'Anthropic), pour qu'Olivier garde un œil dessus. */
function assistant_mois(): array {
    $n = 0; $usd = 0.0;
    foreach (lignes("SELECT role, question, usage FROM assistant_messages WHERE le >= ?", [date('Y-m-01 00:00:00')]) as $m) {
        if ($m['role'] === 'user' && (int)$m['question'] === 1) $n++;
        $u = json_decode((string)$m['usage'], true);
        if (!is_array($u)) continue;
        $usd += ((int)($u['input_tokens'] ?? 0) * GESTION_ASSISTANT_PRIX['entree'] + (int)($u['cache_creation_input_tokens'] ?? 0) * GESTION_ASSISTANT_PRIX['cache_ecriture']
               + (int)($u['cache_read_input_tokens'] ?? 0) * GESTION_ASSISTANT_PRIX['cache_lecture'] + (int)($u['output_tokens'] ?? 0) * GESTION_ASSISTANT_PRIX['sortie']) / 1e6;
    }
    return ['questions' => $n, 'usd' => $usd];
}
function assistant_mois_texte(): string {
    $m = assistant_mois();
    return 'Ce mois-ci : ' . $m['questions'] . ' question(s) · ≈ ' . number_format($m['usd'], 2, ',', ' ') . ' $';
}

/** ?p=assistant : la conversation avec l'assistant, et les rapports. */
function page_assistant(): void {
    $u = utilisateur_courant();
    $uid = (int)$u['id'];
    $fil = assistant_fil($uid);
    session_ouvrir();
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        csrf_verifier();
        switch (texte('_action', 40)) {
            case 'message':
                $t = texte('message', GESTION_ASSISTANT_MAX_CAR);
                if ($t === '') break;
                if (!assistant_relie()) { flash('erreur', 'L\'assistant n\'est pas encore relié : demandez à Olivier de coller la clé dans Réglages.'); break; }
                $pb = assistant_repondre($uid, $fil, $t, $u);
                if ($pb !== '') { $_SESSION['assistant_brouillon'] = $t; flash('erreur', 'Pas de réponse : ' . $pb . '.'); }
                break;
            case 'nouveau':
                if (assistant_messages($uid, $fil)) parametre_ecrire('assistant_fil_' . $uid, (string)($fil + 1));
                rediriger('assistant');
            case 'rapport_envoyer':
            case 'rapport_ignorer':
                $r = ligne("SELECT * FROM rapports WHERE id = ? AND utilisateur_id = ? AND etat = 'propose'", [entier('id', 1), $uid]);
                if (!$r) break;
                if (texte('_action', 40) === 'rapport_ignorer') { q("UPDATE rapports SET etat = 'ignore' WHERE id = ?", [(int)$r['id']]); break; }
                $e = rapport_email($r, $u);
                if (!envoyer_email(GESTION_COMPTE_INITIAL, $e['sujet'], $e['corps'])) { flash('erreur', 'L\'email n\'est pas parti : réessayez, ou prévenez Olivier.'); break; }
                q("UPDATE rapports SET etat = 'envoye', envoye_le = ? WHERE id = ?", [maintenant(), (int)$r['id']]);
                evenement('rapport', 'Rapport envoyé à Olivier : ' . $r['titre']);
                flash('ok', 'Rapport envoyé à contact@madamecho.com.');
                break;
        }
        rediriger('assistant', [], 'bas');
    }
    $brouillon = (string)($_SESSION['assistant_brouillon'] ?? ''); unset($_SESSION['assistant_brouillon']);
    $messages = assistant_messages($uid, $fil);
    $rapports = [];
    foreach (lignes("SELECT * FROM rapports WHERE utilisateur_id = ? ORDER BY id", [$uid]) as $r) $rapports[(int)$r['id']] = $r;
    $actions = $messages ? formulaire('nouveau', bouton('Nouvelle conversation', 'discret')) : '';
    $c = titre_page('Assistant', fr_ko('Questions, problèmes, idées : il connaît le manuel', '질문, 문제, 아이디어: 설명서를 알고 있어요'), $actions, u('plus'));
    if (!assistant_relie()) {
        $c .= bloc('Pas encore relié', '<p>' . fr_ko('L\'assistant marche avec une clé d\'accès que seul Olivier peut coller, dans Réglages. En attendant, le <a href="' . h(u('manuel')) . '">manuel</a> répond déjà à beaucoup de questions.',
                                                   '어시스턴트는 Olivier만 설정에 붙여 넣을 수 있는 접근 키가 있어야 작동해요. 그동안은 <a href="' . h(u('manuel')) . '">설명서</a>가 많은 질문에 답해 줘요.') . '</p>'
            . (est_admin() ? '<p>' . lien_bouton(u('reglages') . '#assistant', 'Relier l\'assistant dans Réglages', 'secondaire') . '</p>' : ''), 'alerte');
    } elseif (!$messages) {
        $c .= bloc('', '<p>' . fr_ko('Posez une question sur la gestion, décrivez ce qui ne marche pas, ou proposez une amélioration. L\'assistant connaît le <a href="' . h(u('manuel')) . '">manuel</a>, rien d\'autre : il ne voit ni vos commandes ni vos clients. Vous pouvez écrire en coréen.',
                                      '관리 시스템에 대해 질문하거나, 안 되는 것을 설명하거나, 개선 아이디어를 말해 보세요. 어시스턴트는 <a href="' . h(u('manuel')) . '">설명서</a>만 알아요. 주문이나 고객 정보는 보지 못해요. 한국어로 써도 돼요.') . '</p>'
            . '<p class="aide">' . fr_ko('Parlez des clients et des commandes par leur numéro (CL-…, MC-…), jamais d\'information de santé. Quand un vrai problème ou un vrai besoin apparaît, l\'assistant prépare un rapport que vous envoyez à Olivier d\'un bouton.',
                                         '고객과 주문은 번호(CL-…, MC-…)로 말해 주세요. 건강 정보는 적지 마세요. 진짜 문제나 필요한 개선이 보이면 어시스턴트가 보고서를 준비하고, 버튼 하나로 Olivier에게 보낼 수 있어요.') . '</p>');
    }
    $chat = '';
    foreach ($messages as $m) {
        $t = assistant_texte($m['blocs']);
        if ($m['role'] === 'user') {
            if ((int)$m['question'] === 1 && $t !== '') $chat .= '<div class="bulle bulle-moi" translate="no">' . nl2br(h($t)) . '</div>';
        } else {
            if ($t !== '') $chat .= '<div class="bulle bulle-ia" translate="no" lang="' . (preg_match('/\p{Hangul}/u', $t) ? 'ko' : 'fr') . '">' . assistant_html($t) . '</div>';
            if (!empty($m['rapport_id']) && isset($rapports[(int)$m['rapport_id']])) $chat .= carte_rapport($rapports[(int)$m['rapport_id']]);
        }
    }
    if (GESTION_ESSAI && !empty($_SESSION['essai_email'])) {
        $e = $_SESSION['essai_email']; unset($_SESSION['essai_email']);
        $chat .= '<div class="essai-email"><p><b>Essai : l\'email qui serait parti à ' . h($e['a']) . '</b></p><pre>' . h($e['sujet'] . "\n\n" . $e['corps']) . '</pre></div>';
    }
    if ($chat !== '') $c .= '<div class="chat" id="chat">' . $chat . '</div>';
    $c .= '<span id="bas"></span>';
    if (assistant_relie()) {
        $c .= formulaire('message', zone('message', 'Votre message', $brouillon, ['rows' => '2', 'maxlength' => (string)GESTION_ASSISTANT_MAX_CAR, 'required' => true, 'data-langue-libre' => true,
                                                                             'placeholder' => fr_ko('Par exemple : comment je change la date d\'un retrait ?', '예: 픽업 날짜는 어떻게 바꿔요?')])
            . bouton(icone('email') . 'Envoyer', 'principal', ['data-attente' => fr_ko('L\'assistant réfléchit…', '어시스턴트가 생각 중이에요…')]), ['class' => 'form-assistant']);
    }
    $envoyes = array_values(array_filter($rapports, fn($r) => $r['etat'] === 'envoye'));
    if ($envoyes) {
        $rows = '';
        foreach (array_reverse(array_slice($envoyes, -12)) as $r) $rows .= '<tr><td>' . h(date_fr((string)$r['envoye_le'])) . '</td><td>' . rapport_type_libelle($r['type']) . '</td><td translate="no">' . h($r['titre']) . '</td></tr>';
        $c .= bloc('Rapports envoyés', '<table class="tableau"><tbody>' . $rows . '</tbody></table>');
    }
    if (est_admin() && assistant_relie()) $c .= '<p class="aide centre"><span>' . h(assistant_mois_texte()) . '</span> · <a href="' . h(u('reglages')) . '#assistant">Réglages</a></p>';
    mise_en_page('Assistant', $c, 'assistant', 'page-assistant');
}
