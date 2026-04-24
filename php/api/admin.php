<?php
/* ═══════════════════════════════════════════════════════════
   KidCycle — api/admin.php
   Endpoints admin : gestion utilisateurs, SWAPS, stats
   ═══════════════════════════════════════════════════════════ */

require_once __DIR__ . '/../config.php';
startKcSession();
requireAdmin();

$action = $_GET['action'] ?? '';
$id     = cleanInt($_GET['id'] ?? 0);

$body = [];
$raw  = file_get_contents('php://input');
if ($raw) $body = json_decode($raw, true) ?? [];
$data = array_merge($_POST, $body);

match ($action) {
    'toggle-user'   => handleToggleUser($id, $data),
    'update-role'   => handleUpdateRole($id, $data),
    'credit-swaps'  => handleCreditSwaps($data),
    'user-detail'   => handleUserDetail($id),
    'delete-user'   => handleDeleteUser($id),
    'logs'          => handleLogs(),
    default         => jsonResponse(['error' => 'Action inconnue.'], 400),
};


/* ── TOGGLE USER ACTIVE ───────────────────────────────────── */
function handleToggleUser(int $id, array $d): void {
    if (!$id) jsonResponse(['error' => 'ID requis.'], 422);

    $actif = isset($d['actif']) ? (int)(bool)$d['actif'] : null;
    if ($actif === null) jsonResponse(['error' => 'Valeur actif requise.'], 422);

    $db = getDB();
    $db->prepare('UPDATE utilisateurs SET actif = ? WHERE id = ?')->execute([$actif, $id]);

    logAdmin('toggle_user', 'utilisateur', $id, 'actif=' . $actif);

    jsonResponse(['success' => true, 'message' => $actif ? 'Compte réactivé.' : 'Compte désactivé.']);
}


/* ── UPDATE ROLE ─────────────────────────────────────────── */
function handleUpdateRole(int $id, array $d): void {
    if (!$id) jsonResponse(['error' => 'ID requis.'], 422);
    $role = $d['role'] ?? '';
    if (!in_array($role, ['user','admin'])) jsonResponse(['error' => 'Rôle invalide.'], 422);

    $db = getDB();
    $db->prepare('UPDATE utilisateurs SET role = ? WHERE id = ?')->execute([$role, $id]);

    logAdmin('update_role', 'utilisateur', $id, "Nouveau rôle: $role");
    jsonResponse(['success' => true, 'message' => "Rôle mis à jour : $role"]);
}


/* ── CREDIT / DEBIT SWAPS ────────────────────────────────── */
function handleCreditSwaps(array $d): void {
    $email   = strtolower(trim($d['email'] ?? ''));
    $montant = cleanFloat($d['montant'] ?? 0);
    $type    = $d['type'] ?? 'depot';
    $desc    = clean($d['description'] ?? '');

    if (!$email) jsonResponse(['error' => 'Email requis.'], 422);
    if ($montant <= 0) jsonResponse(['error' => 'Montant invalide.'], 422);
    if (!in_array($type, ['depot','retrait'])) jsonResponse(['error' => 'Type invalide.'], 422);

    $db   = getDB();
    $stmt = $db->prepare('SELECT id, swaps FROM utilisateurs WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if (!$user) jsonResponse(['error' => 'Utilisateur introuvable.'], 404);

    if ($type === 'retrait' && $user['swaps'] < $montant) {
        jsonResponse(['error' => 'SWAPS insuffisants. Solde: ' . $user['swaps']], 422);
    }

    $op = $type === 'depot' ? '+' : '-';
    $db->prepare("UPDATE utilisateurs SET swaps = swaps $op ? WHERE id = ?")
       ->execute([$montant, $user['id']]);

    $db->prepare(
        'INSERT INTO swaps_transactions (user_id, type, montant, description) VALUES (?, ?, ?, ?)'
    )->execute([$user['id'], $type, $montant, $desc ?: "Admin: $type"]);

    logAdmin('credit_swaps', 'utilisateur', $user['id'], "$type $montant SWAPS");

    jsonResponse(['success' => true, 'message' => ucfirst($type) . " de $montant SWAPS effectué !"]);
}


/* ── USER DETAIL ─────────────────────────────────────────── */
function handleUserDetail(int $id): void {
    if (!$id) jsonResponse(['error' => 'ID requis.'], 422);

    $db   = getDB();
    $stmt = $db->prepare('SELECT id,nom,prenom,email,tel,adresse,avatar_url,swaps,role,actif,created_at FROM utilisateurs WHERE id = ?');
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    if (!$user) jsonResponse(['error' => 'Utilisateur introuvable.'], 404);

    // User's products count
    $stmt = $db->prepare('SELECT COUNT(*) FROM produits WHERE vendeur_id = ?'); $stmt->execute([$id]);
    $user['nb_produits'] = (int) $stmt->fetchColumn();

    // Orders count
    $stmt = $db->prepare('SELECT COUNT(*) FROM commandes WHERE acheteur_id = ?'); $stmt->execute([$id]);
    $user['nb_commandes'] = (int) $stmt->fetchColumn();

    jsonResponse(['success' => true, 'data' => $user]);
}


/* ── DELETE USER ─────────────────────────────────────────── */
function handleDeleteUser(int $id): void {
    if (!$id) jsonResponse(['error' => 'ID requis.'], 422);
    if ($id === (int)$_SESSION['user_id']) jsonResponse(['error' => 'Vous ne pouvez pas vous supprimer.'], 422);

    $db = getDB();
    $db->prepare('DELETE FROM utilisateurs WHERE id = ?')->execute([$id]);

    logAdmin('delete_user', 'utilisateur', $id, 'Compte supprimé');
    jsonResponse(['success' => true, 'message' => 'Compte supprimé.']);
}


/* ── ADMIN LOGS ──────────────────────────────────────────── */
function handleLogs(): void {
    $db = getDB();
    $stmt = $db->query(
        'SELECT al.*, u.nom, u.email
         FROM admin_logs al
         JOIN utilisateurs u ON u.id = al.admin_id
         ORDER BY al.created_at DESC
         LIMIT 100'
    );
    jsonResponse(['success' => true, 'data' => $stmt->fetchAll()]);
}


/* ── Helper: log admin action ────────────────────────────── */
function logAdmin(string $action, string $cibleType, int $cibleId, string $detail = ''): void {
    $db = getDB();
    $db->prepare(
        'INSERT INTO admin_logs (admin_id, action, cible_type, cible_id, detail, ip) VALUES (?, ?, ?, ?, ?, ?)'
    )->execute([
        $_SESSION['user_id'], $action, $cibleType, $cibleId, $detail,
        $_SERVER['REMOTE_ADDR'] ?? null
    ]);
}
