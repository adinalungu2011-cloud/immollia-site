<?php
// ═══════════════════════════════════════════════════════════════════════════
// IMMOLLIA — Parcours « Zone de Génie » — API (PHP + MariaDB/MySQL)
//
// Application autonome : à déposer telle quelle sur l'hébergement d'immollia.fr
// (ex. sous-domaine  parcours.immollia.fr  → dossier contenant index.html,
// zg-api.php, config.php). Les tables se créent toutes seules au 1er appel.
//
// Principes :
//   • Chaque adhérente a SON carnet de bord (table zg_entries, 1 ligne par champ).
//   • Sauvegarde automatique champ par champ (pas de bouton « enregistrer »).
//   • Les binômes/trinômes sont optionnels : un groupe = un code à 6 lettres.
//     Chacune choisit, semaine par semaine, de partager ou non ses réponses
//     avec son groupe. Les membres peuvent se laisser des « notes de binôme ».
//   • Les animatrices (ZG_ADMINS) voient tous les groupes.
//
// Appels : POST zg-api.php?action=…  (JSON)  — jeton « Authorization: Bearer … »
// v1 — 2026-10-16
// ═══════════════════════════════════════════════════════════════════════════

if (!is_file(__DIR__ . '/config.php')) {
    // Pas encore installé sur le serveur : réponse propre (l'interface passe alors en « mode démo »).
    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Parcours pas encore configuré sur le serveur.'], JSON_UNESCAPED_UNICODE);
    exit;
}
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

const ZG_VERSION    = 'v1.1';
const ZG_SESSION_J  = 60;        // durée d'une session (jours)
const ZG_MAX_CHAMP  = 20000;     // taille max d'une réponse (caractères)

// ─── Outils ───────────────────────────────────────────────────────────────
function rep(int $code, array $data): void {
    http_response_code($code);
    $data['_version'] = ZG_VERSION;
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
function erreur(string $msg, int $code = 400): void { rep($code, ['ok' => false, 'error' => $msg]); }
function corps(): array {
    $raw = file_get_contents('php://input');
    if ($raw === '' || $raw === false) return [];
    $d = json_decode($raw, true);
    if (!is_array($d)) erreur('Requête illisible (JSON attendu).');
    return $d;
}
function bearer(): string {
    $h = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    if ($h === '' && function_exists('getallheaders')) {
        $hh = getallheaders();
        $h  = $hh['Authorization'] ?? ($hh['authorization'] ?? '');
    }
    return preg_match('/Bearer\s+(.+)/i', $h, $m) ? trim($m[1]) : '';
}
function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    try {
        $pdo = new PDO(
            'mysql:host=' . ZG_DB_HOST . ';dbname=' . ZG_DB_NAME . ';charset=utf8mb4',
            ZG_DB_USER, ZG_DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    } catch (Throwable $e) {
        erreur('Base de données injoignable.', 500);
    }
    installer($pdo);
    return $pdo;
}
function installer(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS zg_users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(190) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        prenom VARCHAR(80) NOT NULL,
        nom VARCHAR(80) NOT NULL DEFAULT '',
        agence VARCHAR(120) NOT NULL DEFAULT '',
        photo MEDIUMTEXT NULL,
        actif TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        last_login DATETIME NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // Tables créées avant la v1.1 : on ajoute la colonne photo si elle manque.
    try { $pdo->query('SELECT photo FROM zg_users LIMIT 1'); } catch (Throwable $e) { $pdo->exec('ALTER TABLE zg_users ADD COLUMN photo MEDIUMTEXT NULL'); }
    $pdo->exec("CREATE TABLE IF NOT EXISTS zg_sessions (
        token CHAR(64) PRIMARY KEY,
        user_id INT NOT NULL,
        expires_at DATETIME NOT NULL,
        INDEX (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS zg_entries (
        user_id INT NOT NULL,
        cle VARCHAR(60) NOT NULL,
        valeur MEDIUMTEXT NOT NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (user_id, cle)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS zg_groups (
        id INT AUTO_INCREMENT PRIMARY KEY,
        code CHAR(6) NOT NULL UNIQUE,
        nom VARCHAR(120) NOT NULL,
        createur_id INT NOT NULL,
        visio_jour VARCHAR(120) NOT NULL DEFAULT '',
        visio_lien VARCHAR(500) NOT NULL DEFAULT '',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS zg_group_members (
        group_id INT NOT NULL,
        user_id INT NOT NULL,
        joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (group_id, user_id),
        INDEX (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // Notes de binôme / de coach : « auteur » écrit « à propos de » cible, pour une semaine donnée.
    $pdo->exec("CREATE TABLE IF NOT EXISTS zg_notes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        group_id INT NOT NULL,
        auteur_id INT NOT NULL,
        cible_id INT NOT NULL,
        semaine TINYINT NOT NULL,
        texte TEXT NOT NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY u (group_id, auteur_id, cible_id, semaine)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
function est_admin(string $email): bool {
    $l = array_filter(array_map('trim', explode(',', strtolower(defined('ZG_ADMINS') ? ZG_ADMINS : ''))));
    return in_array(strtolower($email), $l, true);
}
function utilisateur(): array {
    $t = bearer();
    if ($t === '' || strlen($t) !== 64) erreur('Connexion requise.', 401);
    $s = db()->prepare('SELECT u.id, u.email, u.prenom, u.nom, u.agence, u.photo, u.actif
                        FROM zg_sessions s JOIN zg_users u ON u.id = s.user_id
                        WHERE s.token = ? AND s.expires_at > NOW()');
    $s->execute([$t]);
    $u = $s->fetch();
    if (!$u) erreur('Session expirée, reconnecte-toi.', 401);
    if (!(int)$u['actif']) erreur('Compte désactivé.', 403);
    $u['admin'] = est_admin($u['email']);
    return $u;
}
function profil_public(array $u): array {
    return ['id' => (int)$u['id'], 'prenom' => $u['prenom'], 'nom' => $u['nom'], 'agence' => $u['agence'], 'email' => $u['email'], 'photo' => $u['photo'] ?? null];
}
function ouvrir_session(int $uid): string {
    $t = bin2hex(random_bytes(32));
    db()->prepare('INSERT INTO zg_sessions (token, user_id, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? DAY))')
        ->execute([$t, $uid, ZG_SESSION_J]);
    db()->prepare('UPDATE zg_users SET last_login = NOW() WHERE id = ?')->execute([$uid]);
    return $t;
}
// Clés autorisées dans le carnet (empêche de stocker n'importe quoi).
function cle_valide(string $k): bool { return (bool)preg_match('/^[a-z0-9_]{2,60}$/', $k); }
function cles_partagees(PDO $db, int $uid): array {
    $s = $db->prepare("SELECT cle, valeur FROM zg_entries WHERE user_id = ? AND cle LIKE 'partage_s%'");
    $s->execute([$uid]);
    $out = [];
    foreach ($s as $r) if ($r['valeur'] === '1') $out[] = (int)substr($r['cle'], 9); // partage_s1 → 1
    return $out;
}

$action = $_GET['action'] ?? ($_POST['action'] ?? '');
$body   = corps();

// ─── Comptes ──────────────────────────────────────────────────────────────
if ($action === 'ping') rep(200, ['ok' => true]);

if ($action === 'register') {
    $email  = strtolower(trim((string)($body['email'] ?? '')));
    $pass   = (string)($body['password'] ?? '');
    $prenom = trim((string)($body['prenom'] ?? ''));
    $nom    = trim((string)($body['nom'] ?? ''));
    $agence = trim((string)($body['agence'] ?? ''));
    $code   = trim((string)($body['code'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) erreur('Adresse e-mail invalide.');
    if (strlen($pass) < 8) erreur('Mot de passe : 8 caractères minimum.');
    if ($prenom === '') erreur('Le prénom est obligatoire.');
    if (!hash_equals(ZG_CODE_COMMUNAUTE, $code)) erreur('Code communauté incorrect. Il est remis aux adhérentes Immollia.', 403);
    $db = db();
    $s = $db->prepare('SELECT id FROM zg_users WHERE email = ?'); $s->execute([$email]);
    if ($s->fetch()) erreur('Un compte existe déjà avec cet e-mail — connecte-toi.', 409);
    $db->prepare('INSERT INTO zg_users (email, password_hash, prenom, nom, agence) VALUES (?,?,?,?,?)')
       ->execute([$email, password_hash($pass, PASSWORD_DEFAULT), mb_substr($prenom, 0, 80), mb_substr($nom, 0, 80), mb_substr($agence, 0, 120)]);
    $uid = (int)$db->lastInsertId();
    rep(200, ['ok' => true, 'token' => ouvrir_session($uid), 'user' => ['id' => $uid, 'email' => $email, 'prenom' => $prenom, 'nom' => $nom, 'agence' => $agence, 'admin' => est_admin($email)]]);
}

if ($action === 'login') {
    $email = strtolower(trim((string)($body['email'] ?? '')));
    $pass  = (string)($body['password'] ?? '');
    $db = db();
    // Anti-force brute très simple : 1 s d'attente à chaque échec.
    $s = $db->prepare('SELECT * FROM zg_users WHERE email = ?'); $s->execute([$email]);
    $u = $s->fetch();
    if (!$u || !password_verify($pass, $u['password_hash'])) { sleep(1); erreur('E-mail ou mot de passe incorrect.', 401); }
    if (!(int)$u['actif']) erreur('Compte désactivé.', 403);
    rep(200, ['ok' => true, 'token' => ouvrir_session((int)$u['id']), 'user' => profil_public($u) + ['admin' => est_admin($u['email'])]]);
}

if ($action === 'logout') {
    $t = bearer();
    if ($t !== '') db()->prepare('DELETE FROM zg_sessions WHERE token = ?')->execute([$t]);
    rep(200, ['ok' => true]);
}

// ─── Tout ce qui suit demande d'être connectée ────────────────────────────
$me = utilisateur();
$db = db();

if ($action === 'me') {
    rep(200, ['ok' => true, 'user' => profil_public($me) + ['admin' => $me['admin']]]);
}

if ($action === 'profil') {
    $prenom = trim((string)($body['prenom'] ?? $me['prenom']));
    $nom    = trim((string)($body['nom'] ?? $me['nom']));
    $agence = trim((string)($body['agence'] ?? $me['agence']));
    if ($prenom === '') erreur('Le prénom est obligatoire.');
    $db->prepare('UPDATE zg_users SET prenom = ?, nom = ?, agence = ? WHERE id = ?')
       ->execute([mb_substr($prenom, 0, 80), mb_substr($nom, 0, 80), mb_substr($agence, 0, 120), $me['id']]);
    // Photo de profil : data URL JPEG/PNG/WebP, réduite côté navigateur (≈ 300 px), 400 Ko max.
    if (array_key_exists('photo', $body)) {
        $ph = (string)($body['photo'] ?? '');
        if ($ph === '') {
            $db->prepare('UPDATE zg_users SET photo = NULL WHERE id = ?')->execute([$me['id']]);
        } else {
            if (!preg_match('#^data:image/(jpeg|png|webp);base64,[A-Za-z0-9+/=]+$#', $ph)) erreur('Image non reconnue.');
            if (strlen($ph) > 400000) erreur('Photo trop lourde (400 Ko max).');
            $db->prepare('UPDATE zg_users SET photo = ? WHERE id = ?')->execute([$ph, $me['id']]);
        }
    }
    if (!empty($body['password'])) {
        if (strlen((string)$body['password']) < 8) erreur('Mot de passe : 8 caractères minimum.');
        $db->prepare('UPDATE zg_users SET password_hash = ? WHERE id = ?')->execute([password_hash((string)$body['password'], PASSWORD_DEFAULT), $me['id']]);
    }
    rep(200, ['ok' => true]);
}

// Carnet de bord : lecture complète
if ($action === 'carnet') {
    $s = $db->prepare('SELECT cle, valeur, updated_at FROM zg_entries WHERE user_id = ?');
    $s->execute([$me['id']]);
    $e = []; $maj = null;
    foreach ($s as $r) { $e[$r['cle']] = $r['valeur']; if ($maj === null || $r['updated_at'] > $maj) $maj = $r['updated_at']; }
    rep(200, ['ok' => true, 'entries' => $e, 'updated_at' => $maj]);
}

// Carnet de bord : sauvegarde (une ou plusieurs clés d'un coup)
if ($action === 'sauver') {
    $entries = $body['entries'] ?? null;
    if (!is_array($entries) || !$entries) erreur('Rien à enregistrer.');
    $st = $db->prepare('INSERT INTO zg_entries (user_id, cle, valeur) VALUES (?,?,?) ON DUPLICATE KEY UPDATE valeur = VALUES(valeur)');
    $n = 0;
    foreach ($entries as $k => $v) {
        if (!cle_valide((string)$k)) continue;
        $v = is_string($v) ? $v : json_encode($v, JSON_UNESCAPED_UNICODE);
        if (mb_strlen($v) > ZG_MAX_CHAMP) $v = mb_substr($v, 0, ZG_MAX_CHAMP);
        $st->execute([$me['id'], $k, $v]); $n++;
    }
    rep(200, ['ok' => true, 'n' => $n, 'updated_at' => date('Y-m-d H:i:s')]);
}

// ─── Groupes (binômes / trinômes) ─────────────────────────────────────────
function mes_groupes(PDO $db, array $me): array {
    if ($me['admin']) {
        $s = $db->query('SELECT g.* FROM zg_groups g ORDER BY g.created_at DESC');
    } else {
        $s = $db->prepare('SELECT g.* FROM zg_groups g JOIN zg_group_members m ON m.group_id = g.id WHERE m.user_id = ? ORDER BY m.joined_at DESC');
        $s->execute([$me['id']]);
    }
    $out = [];
    foreach ($s as $g) {
        $c = $db->prepare('SELECT COUNT(*) FROM zg_group_members WHERE group_id = ?'); $c->execute([$g['id']]);
        $out[] = ['id' => (int)$g['id'], 'code' => $g['code'], 'nom' => $g['nom'], 'visio_jour' => $g['visio_jour'], 'visio_lien' => $g['visio_lien'], 'membres' => (int)$c->fetchColumn(), 'createur' => (int)$g['createur_id'] === (int)$me['id']];
    }
    return $out;
}
function membre_de(PDO $db, array $me, int $gid): bool {
    if ($me['admin']) return true;
    $s = $db->prepare('SELECT 1 FROM zg_group_members WHERE group_id = ? AND user_id = ?'); $s->execute([$gid, $me['id']]);
    return (bool)$s->fetch();
}

if ($action === 'groupes') rep(200, ['ok' => true, 'groupes' => mes_groupes($db, $me)]);

if ($action === 'groupe_creer') {
    $nom = trim((string)($body['nom'] ?? ''));
    if ($nom === '') $nom = 'Binôme de ' . $me['prenom'];
    $alpha = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    do {
        $code = '';
        for ($i = 0; $i < 6; $i++) $code .= $alpha[random_int(0, strlen($alpha) - 1)];
        $s = $db->prepare('SELECT 1 FROM zg_groups WHERE code = ?'); $s->execute([$code]);
    } while ($s->fetch());
    $db->prepare('INSERT INTO zg_groups (code, nom, createur_id) VALUES (?,?,?)')->execute([$code, mb_substr($nom, 0, 120), $me['id']]);
    $gid = (int)$db->lastInsertId();
    $db->prepare('INSERT INTO zg_group_members (group_id, user_id) VALUES (?,?)')->execute([$gid, $me['id']]);
    rep(200, ['ok' => true, 'id' => $gid, 'code' => $code, 'groupes' => mes_groupes($db, $me)]);
}

if ($action === 'groupe_rejoindre') {
    $code = strtoupper(trim((string)($body['code'] ?? '')));
    $s = $db->prepare('SELECT id FROM zg_groups WHERE code = ?'); $s->execute([$code]);
    $g = $s->fetch();
    if (!$g) erreur('Aucun groupe avec ce code.', 404);
    $c = $db->prepare('SELECT COUNT(*) FROM zg_group_members WHERE group_id = ?'); $c->execute([$g['id']]);
    if ((int)$c->fetchColumn() >= 4) erreur('Ce groupe est complet (4 personnes maximum).', 409);
    $db->prepare('INSERT IGNORE INTO zg_group_members (group_id, user_id) VALUES (?,?)')->execute([$g['id'], $me['id']]);
    rep(200, ['ok' => true, 'id' => (int)$g['id'], 'groupes' => mes_groupes($db, $me)]);
}

if ($action === 'groupe_quitter') {
    $gid = (int)($body['id'] ?? 0);
    $db->prepare('DELETE FROM zg_group_members WHERE group_id = ? AND user_id = ?')->execute([$gid, $me['id']]);
    $c = $db->prepare('SELECT COUNT(*) FROM zg_group_members WHERE group_id = ?'); $c->execute([$gid]);
    if ((int)$c->fetchColumn() === 0) { // groupe vide → on le supprime
        $db->prepare('DELETE FROM zg_groups WHERE id = ?')->execute([$gid]);
        $db->prepare('DELETE FROM zg_notes WHERE group_id = ?')->execute([$gid]);
    }
    rep(200, ['ok' => true, 'groupes' => mes_groupes($db, $me)]);
}

if ($action === 'groupe_visio') {
    $gid = (int)($body['id'] ?? 0);
    if (!membre_de($db, $me, $gid)) erreur('Tu ne fais pas partie de ce groupe.', 403);
    $db->prepare('UPDATE zg_groups SET visio_jour = ?, visio_lien = ? WHERE id = ?')
       ->execute([mb_substr(trim((string)($body['visio_jour'] ?? '')), 0, 120), mb_substr(trim((string)($body['visio_lien'] ?? '')), 0, 500), $gid]);
    rep(200, ['ok' => true]);
}

// Vue groupe : membres, leurs réponses PARTAGÉES, les notes de binôme.
if ($action === 'groupe') {
    $gid = (int)($_GET['id'] ?? ($body['id'] ?? 0));
    if (!membre_de($db, $me, $gid)) erreur('Tu ne fais pas partie de ce groupe.', 403);
    $s = $db->prepare('SELECT * FROM zg_groups WHERE id = ?'); $s->execute([$gid]);
    $g = $s->fetch();
    if (!$g) erreur('Groupe introuvable.', 404);
    $s = $db->prepare('SELECT u.id, u.prenom, u.nom, u.agence, u.email, u.photo FROM zg_group_members m JOIN zg_users u ON u.id = m.user_id WHERE m.group_id = ? ORDER BY m.joined_at');
    $s->execute([$gid]);
    $membres = [];
    foreach ($s as $u) {
        $uid = (int)$u['id'];
        $sem = cles_partagees($db, $uid);
        $entries = [];
        if ($uid === (int)$me['id'] || $sem) {
            $e = $db->prepare('SELECT cle, valeur FROM zg_entries WHERE user_id = ?'); $e->execute([$uid]);
            foreach ($e as $r) {
                // s1_xxx → semaine 1 ; on ne renvoie que les semaines partagées (ou tout pour soi-même).
                if ($uid === (int)$me['id']) { $entries[$r['cle']] = $r['valeur']; continue; }
                if (preg_match('/^s([1-4])_/', $r['cle'], $m) && in_array((int)$m[1], $sem, true)) $entries[$r['cle']] = $r['valeur'];
            }
        }
        $membres[] = profil_public($u) + ['partage' => $sem, 'entries' => $entries, 'moi' => $uid === (int)$me['id']];
    }
    $n = $db->prepare('SELECT auteur_id, cible_id, semaine, texte, updated_at FROM zg_notes WHERE group_id = ?'); $n->execute([$gid]);
    $notes = [];
    foreach ($n as $r) $notes[] = ['auteur' => (int)$r['auteur_id'], 'cible' => (int)$r['cible_id'], 'semaine' => (int)$r['semaine'], 'texte' => $r['texte'], 'updated_at' => $r['updated_at']];
    rep(200, ['ok' => true, 'groupe' => ['id' => (int)$g['id'], 'code' => $g['code'], 'nom' => $g['nom'], 'visio_jour' => $g['visio_jour'], 'visio_lien' => $g['visio_lien']], 'membres' => $membres, 'notes' => $notes]);
}

if ($action === 'note') {
    $gid = (int)($body['group_id'] ?? 0);
    $cible = (int)($body['cible'] ?? 0);
    $sem = (int)($body['semaine'] ?? 0);
    $texte = (string)($body['texte'] ?? '');
    if (!membre_de($db, $me, $gid)) erreur('Tu ne fais pas partie de ce groupe.', 403);
    if ($sem < 1 || $sem > 4) erreur('Semaine invalide.');
    if (mb_strlen($texte) > ZG_MAX_CHAMP) $texte = mb_substr($texte, 0, ZG_MAX_CHAMP);
    $db->prepare('INSERT INTO zg_notes (group_id, auteur_id, cible_id, semaine, texte) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE texte = VALUES(texte)')
       ->execute([$gid, $me['id'], $cible, $sem, $texte]);
    rep(200, ['ok' => true]);
}

// Animatrice : liste de toutes les adhérentes et leur avancement.
if ($action === 'admin_adherentes') {
    if (!$me['admin']) erreur('Réservé aux animatrices.', 403);
    $s = $db->query('SELECT u.id, u.prenom, u.nom, u.agence, u.email, u.created_at, u.last_login,
                       (SELECT COUNT(*) FROM zg_entries e WHERE e.user_id = u.id AND e.cle LIKE "s%" AND e.valeur <> "") AS champs
                     FROM zg_users u ORDER BY u.created_at DESC');
    rep(200, ['ok' => true, 'adherentes' => $s->fetchAll()]);
}

erreur('Action inconnue.', 404);
