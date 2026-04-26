<?php
/* ═══════════════════════════════════════════════════════════
   KidCycle — admin/admin.php
   Dashboard Admin complet (Articles, Commandes, Profils, SWAPS)
   Basé sur les maquettes KS Admin
   ═══════════════════════════════════════════════════════════ */

require_once __DIR__ . '/../php/config.php';
startKcSession();

// Redirect if not admin
if (!isAdmin()) {
    header('Location: ../Connexion.html?redirect=admin');
    exit;
}

$db   = getDB();
$page = $_GET['page'] ?? 'dashboard';
$user = currentUser();

// ── Quick stats ───────────────────────────────────────────
function getStats(PDO $db): array {
    return [
        'total_users'     => (int) $db->query('SELECT COUNT(*) FROM utilisateurs WHERE role="user"')->fetchColumn(),
        'total_produits'  => (int) $db->query('SELECT COUNT(*) FROM produits WHERE statut="actif"')->fetchColumn(),
        'total_commandes' => (int) $db->query('SELECT COUNT(*) FROM commandes')->fetchColumn(),
        'pending_orders'  => (int) $db->query('SELECT COUNT(*) FROM commandes WHERE statut="en_attente"')->fetchColumn(),
        'total_swaps'     => round((float) $db->query('SELECT COALESCE(SUM(total_swaps),0) FROM commandes WHERE statut!="annulee"')->fetchColumn(), 2),
        'produits_vendus' => (int) $db->query('SELECT COUNT(*) FROM produits WHERE statut="vendu"')->fetchColumn(),
    ];
}

$stats = getStats($db);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin — KidCycle</title>
  <link rel="stylesheet" href="../Shared.css">
  <style>
    :root {
      --purple: #9b8ec4;
      --purple-light: #b8aed8;
      --purple-bg: #ede9f7;
      --sidebar-w: 240px;
      --header-h: 64px;
    }
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Nunito', sans-serif; background: #f4f4f6; display: flex; min-height: 100vh; }

    /* ── Sidebar ── */
    .adm-sidebar {
      width: var(--sidebar-w);
      background: #1a1a2e;
      color: #ccc;
      display: flex;
      flex-direction: column;
      position: fixed;
      top: 0; left: 0; bottom: 0;
      z-index: 200;
    }
    .adm-logo {
      padding: 22px 20px;
      font-size: 20px;
      font-weight: 900;
      color: var(--purple-light);
      border-bottom: 1px solid rgba(255,255,255,.08);
      letter-spacing: -0.5px;
    }
    .adm-logo span { color: #fff; }
    .adm-nav { flex: 1; padding: 14px 0; overflow-y: auto; }
    .adm-nav-item {
      display: flex; align-items: center; gap: 12px;
      padding: 12px 20px; font-size: 14px; font-weight: 600;
      color: #aaa; text-decoration: none; cursor: pointer;
      border-left: 3px solid transparent;
      transition: background .15s, color .15s;
    }
    .adm-nav-item:hover, .adm-nav-item.active {
      background: rgba(155,142,196,.15);
      color: #fff;
      border-left-color: var(--purple-light);
    }
    .adm-nav-item .ico { font-size: 18px; width: 22px; text-align: center; }
    .adm-nav-label { font-size: 11px; font-weight: 700; color: #555; padding: 14px 20px 6px; text-transform: uppercase; letter-spacing: 1px; }
    .adm-user-row {
      padding: 14px 20px;
      border-top: 1px solid rgba(255,255,255,.08);
      display: flex; align-items: center; gap: 10px;
      font-size: 12px; color: #aaa;
    }
    .adm-user-row strong { color: #fff; display: block; font-size: 13px; }
    .adm-avatar { width: 36px; height: 36px; border-radius: 50%; background: var(--purple-bg); display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: 800; color: var(--purple); }

    /* ── Main ── */
    .adm-main {
      margin-left: var(--sidebar-w);
      flex: 1;
      display: flex;
      flex-direction: column;
    }
    .adm-topbar {
      background: #fff;
      height: var(--header-h);
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0 28px;
      box-shadow: 0 1px 6px rgba(0,0,0,.06);
      position: sticky; top: 0; z-index: 100;
    }
    .adm-page-title { font-size: 18px; font-weight: 800; color: #333; }
    .adm-topbar-right { display: flex; align-items: center; gap: 14px; font-size: 13px; color: #888; }
    .adm-content { padding: 28px; flex: 1; }

    /* ── Stat Cards ── */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 18px;
      margin-bottom: 28px;
    }
    .stat-card {
      background: #fff;
      border-radius: 16px;
      padding: 20px 22px;
      box-shadow: 0 2px 10px rgba(0,0,0,.05);
    }
    .stat-card .stat-label { font-size: 12px; font-weight: 700; color: #aaa; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 8px; }
    .stat-card .stat-value { font-size: 28px; font-weight: 900; color: #333; }
    .stat-card .stat-sub { font-size: 12px; color: #aaa; margin-top: 4px; }
    .stat-card.purple { background: var(--purple); }
    .stat-card.purple .stat-label, .stat-card.purple .stat-value, .stat-card.purple .stat-sub { color: #fff; }

    /* ── Section Card ── */
    .adm-card {
      background: #fff;
      border-radius: 16px;
      box-shadow: 0 2px 10px rgba(0,0,0,.05);
      padding: 24px;
      margin-bottom: 24px;
    }
    .adm-card-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 18px;
    }
    .adm-card-title { font-size: 16px; font-weight: 800; color: #333; }
    .adm-btn {
      padding: 8px 16px; border-radius: 10px; font-size: 13px;
      font-weight: 700; cursor: pointer; border: none; transition: background .2s;
      text-decoration: none; display: inline-block;
    }
    .adm-btn-primary { background: var(--purple); color: #fff; }
    .adm-btn-primary:hover { background: #8577b5; }
    .adm-btn-danger { background: #fee2e2; color: #e04040; }
    .adm-btn-danger:hover { background: #fca5a5; }
    .adm-btn-sm { padding: 5px 10px; font-size: 11px; border-radius: 7px; }

    /* ── Table ── */
    .adm-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .adm-table th { text-align: left; padding: 10px 14px; font-size: 11px; font-weight: 700; color: #aaa; text-transform: uppercase; letter-spacing: .5px; border-bottom: 2px solid #f0eef8; }
    .adm-table td { padding: 12px 14px; border-bottom: 1px solid #f5f5f5; vertical-align: middle; }
    .adm-table tr:hover td { background: #fafaf9; }
    .adm-table .td-img { width: 40px; height: 40px; object-fit: contain; border-radius: 8px; background: #f5f4fb; padding: 4px; }

    /* ── Badges ── */
    .badge-status {
      display: inline-block; padding: 3px 10px; border-radius: 20px;
      font-size: 11px; font-weight: 700;
    }
    .badge-en_attente { background: #fff8e1; color: #f59e0b; }
    .badge-confirmee  { background: #e0f2fe; color: #0284c7; }
    .badge-expediee   { background: #e0e7ff; color: #4f46e5; }
    .badge-livree     { background: #dcfce7; color: #16a34a; }
    .badge-annulee    { background: #fee2e2; color: #dc2626; }
    .badge-actif      { background: #dcfce7; color: #16a34a; }
    .badge-vendu      { background: #fee2e2; color: #dc2626; }
    .badge-user       { background: #f0eef8; color: var(--purple); }
    .badge-admin      { background: #1a1a2e; color: #fff; }

    /* ── Filters Bar ── */
    .filters-bar { display: flex; gap: 10px; margin-bottom: 18px; flex-wrap: wrap; }
    .filter-tab { padding: 7px 16px; border-radius: 20px; font-size: 12px; font-weight: 700; cursor: pointer; border: none; background: #f0eef8; color: var(--purple); transition: background .15s; }
    .filter-tab.active, .filter-tab:hover { background: var(--purple); color: #fff; }
    .search-input {
      padding: 8px 14px; border-radius: 10px; border: 1.5px solid #e5e5e5;
      font-size: 13px; outline: none; width: 220px;
    }
    .search-input:focus { border-color: var(--purple); }

    /* ── Form ── */
    .adm-form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
    .adm-form-group { display: flex; flex-direction: column; gap: 6px; }
    .adm-form-group label { font-size: 12px; font-weight: 700; color: #666; }
    .adm-form-group input, .adm-form-group select, .adm-form-group textarea {
      padding: 10px 14px; border: 1.5px solid #e5e5e5; border-radius: 10px;
      font-size: 13px; font-family: inherit; outline: none; transition: border-color .2s;
    }
    .adm-form-group input:focus, .adm-form-group select:focus, .adm-form-group textarea:focus {
      border-color: var(--purple);
    }
    .adm-form-group.full { grid-column: 1/-1; }

    /* ── Modal ── */
    .adm-modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.5); z-index: 1000; align-items: center; justify-content: center; }
    .adm-modal-overlay.open { display: flex; }
    .adm-modal { background: #fff; border-radius: 20px; padding: 28px; width: 90%; max-width: 520px; max-height: 90vh; overflow-y: auto; }
    .adm-modal h3 { font-size: 18px; font-weight: 800; margin-bottom: 20px; color: #333; }
    .adm-modal-footer { display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px; }

    /* ── Tabs ── */
    .adm-tabs { display: flex; gap: 4px; border-bottom: 2px solid #f0eef8; margin-bottom: 20px; }
    .adm-tab { padding: 10px 18px; font-size: 13px; font-weight: 700; color: #aaa; cursor: pointer; border-bottom: 2px solid transparent; margin-bottom: -2px; transition: color .2s; }
    .adm-tab.active { color: var(--purple); border-bottom-color: var(--purple); }

    /* ── Toast ── */
    #adm-toast { position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%) translateY(80px); background: #1a1a2e; color: #fff; padding: 11px 22px; border-radius: 10px; font-size: 13px; font-weight: 700; z-index: 9999; opacity: 0; transition: all .3s; pointer-events: none; }
    #adm-toast.show { transform: translateX(-50%) translateY(0); opacity: 1; }

    /* ── Pagination ── */
    .adm-pag { display: flex; gap: 6px; margin-top: 18px; justify-content: flex-end; flex-wrap: wrap; }
    .adm-pag a { padding: 6px 12px; border-radius: 8px; font-size: 12px; font-weight: 700; text-decoration: none; background: #f0eef8; color: var(--purple); transition: background .15s; }
    .adm-pag a.active, .adm-pag a:hover { background: var(--purple); color: #fff; }
  </style>
</head>
<body>

<!-- ══ SIDEBAR ══ -->
<aside class="adm-sidebar">
  <div class="adm-logo">Kid<span>Cycle</span> <small style="font-size:10px;opacity:.5">Admin</small></div>
  <nav class="adm-nav">
    <div class="adm-nav-label">Principal</div>
    <a class="adm-nav-item <?= $page==='dashboard'?'active':'' ?>" href="?page=dashboard">
      <span class="ico">📊</span> Dashboard
    </a>
    <a class="adm-nav-item <?= $page==='articles'?'active':'' ?>" href="?page=articles">
      <span class="ico">👕</span> Articles
    </a>
    <a class="adm-nav-item <?= $page==='commandes'?'active':'' ?>" href="?page=commandes">
      <span class="ico">📦</span> Commandes
      <?php if($stats['pending_orders']): ?>
        <span style="margin-left:auto;background:#e04040;color:#fff;border-radius:50%;width:20px;height:20px;display:inline-flex;align-items:center;justify-content:center;font-size:10px"><?= $stats['pending_orders'] ?></span>
      <?php endif; ?>
    </a>
    <a class="adm-nav-item <?= $page==='profils'?'active':'' ?>" href="?page=profils">
      <span class="ico">👥</span> Profils
    </a>
    <a class="adm-nav-item <?= $page==='swaps'?'active':'' ?>" href="?page=swaps">
      <span class="ico">💱</span> SWAPS
    </a>
    <div class="adm-nav-label">Accès rapide</div>
    <a class="adm-nav-item" href="../index.html" target="_blank">
      <span class="ico">🌐</span> Voir le site
    </a>
  </nav>
  <div class="adm-user-row">
    <div class="adm-avatar"><?= strtoupper(substr($user['prenom'] ?: $user['nom'], 0, 1)) ?></div>
    <div>
      <strong><?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?></strong>
      Administrateur
    </div>
  </div>
</aside>

<!-- ══ MAIN ══ -->
<div class="adm-main">

  <!-- Topbar -->
  <div class="adm-topbar">
    <div class="adm-page-title">
      <?= match($page) {
        'dashboard' => '📊 Dashboard',
        'articles'  => '👕 Articles',
        'commandes' => '📦 Logistique &amp; Commandes',
        'profils'   => '👥 Profils Utilisateurs',
        'swaps'     => '💱 SWAPS',
        default     => 'Dashboard',
      } ?>
    </div>
    <div class="adm-topbar-right">
      <span>Bonjour, <?= htmlspecialchars($user['prenom'] ?: $user['nom']) ?> 👋</span>
      <a href="../php/api/auth.php?action=logout" class="adm-btn adm-btn-danger adm-btn-sm">Déconnexion</a>
    </div>
  </div>

  <div class="adm-content">

    <?php if ($page === 'dashboard'): ?>
    <!-- ════ DASHBOARD ════ -->

    <!-- Stats -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-label">Utilisateurs</div>
        <div class="stat-value"><?= number_format($stats['total_users']) ?></div>
        <div class="stat-sub">Membres actifs</div>
      </div>
      <div class="stat-card purple">
        <div class="stat-label">Articles actifs</div>
        <div class="stat-value"><?= number_format($stats['total_produits']) ?></div>
        <div class="stat-sub"><?= $stats['produits_vendus'] ?> vendus</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Commandes</div>
        <div class="stat-value"><?= number_format($stats['total_commandes']) ?></div>
        <div class="stat-sub"><?= $stats['pending_orders'] ?> en attente</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">SWAPS échangés</div>
        <div class="stat-value"><?= number_format($stats['total_swaps']) ?></div>
        <div class="stat-sub">Total cumulé</div>
      </div>
    </div>

    <!-- Recent orders -->
    <?php
    $recentOrders = $db->query(
        'SELECT c.*, u.nom, u.email FROM commandes c JOIN utilisateurs u ON u.id=c.acheteur_id ORDER BY c.created_at DESC LIMIT 8'
    )->fetchAll();
    ?>
    <div class="adm-card">
      <div class="adm-card-header">
        <div class="adm-card-title">Commandes récentes</div>
        <a href="?page=commandes" class="adm-btn adm-btn-primary adm-btn-sm">Voir tout</a>
      </div>
      <table class="adm-table">
        <thead><tr><th>Référence</th><th>Client</th><th>Articles</th><th>Total SWAPS</th><th>Statut</th><th>Date</th></tr></thead>
        <tbody>
          <?php foreach ($recentOrders as $o): ?>
          <tr>
            <td><strong><?= htmlspecialchars($o['reference']) ?></strong></td>
            <td><?= htmlspecialchars($o['nom']) ?><br><small><?= htmlspecialchars($o['email']) ?></small></td>
            <td><?= htmlspecialchars($o['total_swaps'] ?? '—') ?> S</td>
            <td><?= htmlspecialchars($o['total_swaps']) ?> <span class="coin">S</span></td>
            <td><span class="badge-status badge-<?= $o['statut'] ?>"><?= ucfirst(str_replace('_',' ',$o['statut'])) ?></span></td>
            <td><?= date('d/m/Y', strtotime($o['created_at'])) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Recent products -->
    <?php
    $recentProds = $db->query(
        'SELECT p.*, u.nom AS vendeur FROM produits p JOIN utilisateurs u ON u.id=p.vendeur_id ORDER BY p.created_at DESC LIMIT 6'
    )->fetchAll();
    ?>
    <div class="adm-card">
      <div class="adm-card-header">
        <div class="adm-card-title">Articles récents</div>
        <a href="?page=articles" class="adm-btn adm-btn-primary adm-btn-sm">Voir tout</a>
      </div>
      <table class="adm-table">
        <thead><tr><th>Photo</th><th>Titre</th><th>Vendeur</th><th>Catégorie</th><th>Prix</th><th>Statut</th></tr></thead>
        <tbody>
          <?php foreach ($recentProds as $p): ?>
          <tr>
            <td><img class="td-img" src="../<?= htmlspecialchars($p['image_url']) ?>" alt=""></td>
            <td><strong><?= htmlspecialchars($p['titre']) ?></strong></td>
            <td><?= htmlspecialchars($p['vendeur']) ?></td>
            <td><?= htmlspecialchars($p['categorie']) ?></td>
            <td><?= $p['prix_swaps'] ?> <span class="coin">S</span></td>
            <td><span class="badge-status badge-<?= $p['statut'] ?>"><?= $p['statut'] ?></span></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php elseif ($page === 'articles'): ?>
    <!-- ════ ARTICLES ════ -->
    <?php
    $filterStatut = $_GET['statut'] ?? '';
    $filterCat    = $_GET['cat'] ?? '';
    $search       = trim($_GET['s'] ?? '');
    $pageNum      = max(1, (int)($_GET['p'] ?? 1));
    $perPage      = 15;
    $offset       = ($pageNum - 1) * $perPage;

    $where  = ['1=1'];
    $params = [];
    if ($filterStatut) { $where[] = 'p.statut = ?'; $params[] = $filterStatut; }
    if ($filterCat)    { $where[] = 'p.categorie = ?'; $params[] = $filterCat; }
    if ($search)       { $where[] = 'p.titre LIKE ?'; $params[] = '%'.$search.'%'; }

    $whereSQL  = 'WHERE ' . implode(' AND ', $where);
    $stmt      = $db->prepare("SELECT COUNT(*) FROM produits p $whereSQL");
    $stmt->execute($params);
    $totalItems = (int) $stmt->fetchColumn();
    $totalPages = (int) ceil($totalItems / $perPage);

    $stmt = $db->prepare("SELECT p.*, u.nom AS vendeur FROM produits p JOIN utilisateurs u ON u.id=p.vendeur_id $whereSQL ORDER BY p.created_at DESC LIMIT $perPage OFFSET $offset");
    $stmt->execute($params);
    $articles = $stmt->fetchAll();
    ?>

    <div class="filters-bar">
      <button class="filter-tab <?= !$filterStatut?'active':'' ?>" onclick="filterArticles('')">Tous (<?= $totalItems ?>)</button>
      <button class="filter-tab <?= $filterStatut==='actif'?'active':'' ?>" onclick="filterArticles('actif')">Actifs</button>
      <button class="filter-tab <?= $filterStatut==='vendu'?'active':'' ?>" onclick="filterArticles('vendu')">Vendus</button>
      <button class="filter-tab <?= $filterStatut==='archive'?'active':'' ?>" onclick="filterArticles('archive')">Archivés</button>
      <input type="text" class="search-input" placeholder="Rechercher un article..." value="<?= htmlspecialchars($search) ?>" onchange="searchArticles(this.value)">
    </div>

    <div class="adm-card">
      <div class="adm-card-header">
        <div class="adm-card-title">Articles (<?= $totalItems ?>)</div>
      </div>
      <table class="adm-table">
        <thead>
          <tr>
            <th>Photo</th>
            <th>Titre</th>
            <th>Vendeur</th>
            <th>Catégorie</th>
            <th>Taille</th>
            <th>État</th>
            <th>Prix SWAPS</th>
            <th>Vues</th>
            <th>Statut</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($articles as $a): ?>
          <tr>
            <td><img class="td-img" src="../<?= htmlspecialchars($a['image_url']) ?>" alt=""></td>
            <td><strong><?= htmlspecialchars($a['titre']) ?></strong><br><small style="color:#aaa">#<?= $a['id'] ?></small></td>
            <td><?= htmlspecialchars($a['vendeur']) ?></td>
            <td><?= htmlspecialchars($a['categorie']) ?></td>
            <td><?= htmlspecialchars($a['taille']) ?></td>
            <td><?= htmlspecialchars($a['etat']) ?></td>
            <td><?= $a['prix_swaps'] ?> <span class="coin">S</span></td>
            <td><?= $a['vues'] ?></td>
            <td><span class="badge-status badge-<?= $a['statut'] ?>"><?= $a['statut'] ?></span></td>
            <td>
              <button class="adm-btn adm-btn-sm" onclick="editArticle(<?= $a['id'] ?>)" style="background:#f0eef8;color:var(--purple)">✏ Éditer</button>
              <button class="adm-btn adm-btn-danger adm-btn-sm" onclick="deleteArticle(<?= $a['id'] ?>, '<?= htmlspecialchars($a['titre'], ENT_QUOTES) ?>')">🗑</button>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <!-- Pagination -->
      <?php if ($totalPages > 1): ?>
      <div class="adm-pag">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
          <a href="?page=articles&p=<?= $i ?>&statut=<?= urlencode($filterStatut) ?>&s=<?= urlencode($search) ?>" class="<?= $i == $pageNum ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
      </div>
      <?php endif; ?>
    </div>

    <?php elseif ($page === 'commandes'): ?>
    <!-- ════ COMMANDES ════ -->
    <?php
    $filterStatut = $_GET['statut'] ?? '';
    $pageNum  = max(1, (int)($_GET['p'] ?? 1));
    $perPage  = 15;
    $offset   = ($pageNum - 1) * $perPage;

    $where  = ['1=1'];
    $params = [];
    if ($filterStatut) { $where[] = 'c.statut = ?'; $params[] = $filterStatut; }

    $whereSQL = 'WHERE ' . implode(' AND ', $where);
    $stmt = $db->prepare("SELECT COUNT(*) FROM commandes c $whereSQL");
    $stmt->execute($params);
    $totalItems = (int) $stmt->fetchColumn();
    $totalPages = (int) ceil($totalItems / $perPage);

    $stmt = $db->prepare(
        "SELECT c.*, u.nom, u.email, u.tel,
                COUNT(cl.id) AS nb_articles
         FROM commandes c
         JOIN utilisateurs u ON u.id = c.acheteur_id
         LEFT JOIN commande_lignes cl ON cl.commande_id = c.id
         $whereSQL
         GROUP BY c.id
         ORDER BY c.created_at DESC
         LIMIT $perPage OFFSET $offset"
    );
    $stmt->execute($params);
    $commandes = $stmt->fetchAll();
    ?>

    <div class="filters-bar">
      <button class="filter-tab <?= !$filterStatut?'active':'' ?>" onclick="filterCmd('')">Toutes</button>
      <button class="filter-tab <?= $filterStatut==='en_attente'?'active':'' ?>" onclick="filterCmd('en_attente')">En attente (<?= $stats['pending_orders'] ?>)</button>
      <button class="filter-tab <?= $filterStatut==='confirmee'?'active':'' ?>" onclick="filterCmd('confirmee')">Confirmées</button>
      <button class="filter-tab <?= $filterStatut==='expediee'?'active':'' ?>" onclick="filterCmd('expediee')">Expédiées</button>
      <button class="filter-tab <?= $filterStatut==='livree'?'active':'' ?>" onclick="filterCmd('livree')">Livrées</button>
      <button class="filter-tab <?= $filterStatut==='annulee'?'active':'' ?>" onclick="filterCmd('annulee')">Annulées</button>
    </div>

    <div class="adm-card">
      <div class="adm-card-header">
        <div class="adm-card-title">Commandes (<?= $totalItems ?>)</div>
      </div>
      <table class="adm-table">
        <thead>
          <tr><th>Référence</th><th>Client</th><th>Adresse</th><th>Articles</th><th>Total</th><th>Livraison</th><th>Statut</th><th>Date</th><th>Actions</th></tr>
        </thead>
        <tbody>
          <?php foreach ($commandes as $c): ?>
          <tr>
            <td><strong><?= htmlspecialchars($c['reference']) ?></strong></td>
            <td>
              <?= htmlspecialchars($c['nom']) ?><br>
              <small style="color:#aaa"><?= htmlspecialchars($c['email']) ?></small><br>
              <?php if($c['tel']): ?><small><?= htmlspecialchars($c['tel']) ?></small><?php endif; ?>
            </td>
            <td style="font-size:12px;max-width:160px"><?= htmlspecialchars(substr($c['adresse_livr']??'—',0,60)) ?></td>
            <td><?= $c['nb_articles'] ?> article(s)</td>
            <td><?= $c['total_swaps'] ?> <span class="coin">S</span></td>
            <td><?= htmlspecialchars($c['mode_expedition'] ?? '—') ?></td>
            <td>
              <select class="adm-btn adm-btn-sm" style="background:#f0eef8;border:none;cursor:pointer" onchange="updateStatut(<?= $c['id'] ?>, this.value)">
                <?php foreach(['en_attente','confirmee','expediee','livree','annulee'] as $s): ?>
                  <option value="<?= $s ?>" <?= $c['statut']===$s?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option>
                <?php endforeach; ?>
              </select>
            </td>
            <td><?= date('d/m/Y H:i', strtotime($c['created_at'])) ?></td>
            <td>
              <button class="adm-btn adm-btn-sm" onclick="viewCommande(<?= $c['id'] ?>)" style="background:#f0eef8;color:var(--purple)">👁 Détail</button>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php if ($totalPages > 1): ?>
      <div class="adm-pag">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
          <a href="?page=commandes&p=<?= $i ?>&statut=<?= urlencode($filterStatut) ?>" class="<?= $i==$pageNum?'active':'' ?>"><?= $i ?></a>
        <?php endfor; ?>
      </div>
      <?php endif; ?>
    </div>

    <?php elseif ($page === 'profils'): ?>
    <!-- ════ PROFILS ════ -->
    <?php
    $search  = trim($_GET['s'] ?? '');
    $role    = $_GET['role'] ?? '';
    $pageNum = max(1, (int)($_GET['p'] ?? 1));
    $perPage = 15;
    $offset  = ($pageNum - 1) * $perPage;

    $where  = ['1=1'];
    $params = [];
    if ($search) { $where[] = '(nom LIKE ? OR email LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }
    if ($role)   { $where[] = 'role = ?'; $params[] = $role; }

    $whereSQL = 'WHERE ' . implode(' AND ', $where);
    $stmt = $db->prepare("SELECT COUNT(*) FROM utilisateurs $whereSQL");
    $stmt->execute($params);
    $totalItems = (int) $stmt->fetchColumn();
    $totalPages = (int) ceil($totalItems / $perPage);

    $stmt = $db->prepare("SELECT * FROM utilisateurs $whereSQL ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
    $stmt->execute($params);
    $profils = $stmt->fetchAll();
    ?>

    <div class="filters-bar">
      <button class="filter-tab <?= !$role?'active':'' ?>" onclick="filterProfils('')">Tous (<?= $totalItems ?>)</button>
      <button class="filter-tab <?= $role==='user'?'active':'' ?>" onclick="filterProfils('user')">Utilisateurs</button>
      <button class="filter-tab <?= $role==='admin'?'active':'' ?>" onclick="filterProfils('admin')">Admins</button>
      <input type="text" class="search-input" placeholder="Chercher par nom ou email..." value="<?= htmlspecialchars($search) ?>" onchange="searchProfils(this.value)">
    </div>

    <div class="adm-card">
      <div class="adm-card-header">
        <div class="adm-card-title">Profils (<?= $totalItems ?>)</div>
      </div>
      <table class="adm-table">
        <thead>
          <tr><th>Avatar</th><th>Nom</th><th>Email</th><th>Tél</th><th>SWAPS</th><th>Rôle</th><th>Actif</th><th>Inscrit</th><th>Actions</th></tr>
        </thead>
        <tbody>
          <?php foreach ($profils as $u): ?>
          <tr>
            <td>
              <?php if($u['avatar_url']): ?>
                <img src="../<?= htmlspecialchars($u['avatar_url']) ?>" style="width:36px;height:36px;border-radius:50%;object-fit:cover">
              <?php else: ?>
                <div style="width:36px;height:36px;border-radius:50%;background:var(--purple-bg);display:flex;align-items:center;justify-content:center;font-weight:800;color:var(--purple)"><?= strtoupper(substr($u['prenom'].$u['nom'],0,1)) ?></div>
              <?php endif; ?>
            </td>
            <td><strong><?= htmlspecialchars($u['prenom'].' '.$u['nom']) ?></strong></td>
            <td><?= htmlspecialchars($u['email']) ?></td>
            <td><?= htmlspecialchars($u['tel'] ?? '—') ?></td>
            <td><strong><?= number_format((float)$u['swaps'], 2) ?></strong> S</td>
            <td><span class="badge-status badge-<?= $u['role'] ?>"><?= $u['role'] ?></span></td>
            <td><?= $u['actif'] ? '✅' : '❌' ?></td>
            <td><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
            <td>
              <button class="adm-btn adm-btn-sm" onclick="editUser(<?= $u['id'] ?>)" style="background:#f0eef8;color:var(--purple)">✏</button>
              <?php if ($u['id'] != $_SESSION['user_id']): ?>
              <button class="adm-btn adm-btn-danger adm-btn-sm" onclick="toggleUser(<?= $u['id'] ?>, <?= $u['actif'] ?>)">
                <?= $u['actif'] ? '🚫' : '✅' ?>
              </button>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php if ($totalPages > 1): ?>
      <div class="adm-pag">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
          <a href="?page=profils&p=<?= $i ?>&s=<?= urlencode($search) ?>&role=<?= urlencode($role) ?>" class="<?= $i==$pageNum?'active':'' ?>"><?= $i ?></a>
        <?php endfor; ?>
      </div>
      <?php endif; ?>
    </div>

    <?php elseif ($page === 'swaps'): ?>
    <!-- ════ SWAPS ════ -->
    <?php
    $topUsers = $db->query(
        'SELECT id, nom, prenom, email, swaps FROM utilisateurs WHERE role="user" ORDER BY swaps DESC LIMIT 10'
    )->fetchAll();
    $totalSwapsInCirculation = (float) $db->query('SELECT COALESCE(SUM(swaps),0) FROM utilisateurs')->fetchColumn();
    $recentTx = $db->query(
        'SELECT st.*, u.nom, u.email FROM swaps_transactions st JOIN utilisateurs u ON u.id=st.user_id ORDER BY created_at DESC LIMIT 20'
    )->fetchAll();
    ?>
    <div class="stats-grid" style="grid-template-columns:repeat(3,1fr)">
      <div class="stat-card purple">
        <div class="stat-label">SWAPS en circulation</div>
        <div class="stat-value"><?= number_format($totalSwapsInCirculation, 2) ?></div>
        <div class="stat-sub">Total chez tous les users</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">SWAPS échangés</div>
        <div class="stat-value"><?= number_format($stats['total_swaps'], 2) ?></div>
        <div class="stat-sub">Via commandes</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Valeur en €</div>
        <div class="stat-value"><?= number_format($stats['total_swaps'] * 0.20, 2) ?> €</div>
        <div class="stat-sub">1 SWAP = 0.20€</div>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px">
      <div class="adm-card">
        <div class="adm-card-header"><div class="adm-card-title">Top 10 détenteurs SWAPS</div></div>
        <table class="adm-table">
          <thead><tr><th>#</th><th>Utilisateur</th><th>SWAPS</th></tr></thead>
          <tbody>
            <?php foreach ($topUsers as $i => $u): ?>
            <tr>
              <td style="font-weight:800;color:var(--purple)"><?= $i+1 ?></td>
              <td><?= htmlspecialchars($u['prenom'].' '.$u['nom']) ?></td>
              <td><strong><?= number_format((float)$u['swaps'],2) ?></strong> <span class="coin">S</span></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="adm-card">
        <div class="adm-card-header"><div class="adm-card-title">Transactions récentes</div></div>
        <table class="adm-table">
          <thead><tr><th>User</th><th>Type</th><th>Montant</th><th>Date</th></tr></thead>
          <tbody>
            <?php foreach ($recentTx as $tx): ?>
            <tr>
              <td style="font-size:12px"><?= htmlspecialchars($tx['nom']) ?></td>
              <td><span class="badge-status" style="background:#f0eef8;color:var(--purple)"><?= $tx['type'] ?></span></td>
              <td><?= $tx['type']==='achat' ? '-':'+'?><?= number_format((float)$tx['montant'],2) ?> S</td>
              <td style="font-size:11px;color:#aaa"><?= date('d/m H:i', strtotime($tx['created_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Ajouter SWAPS manuellement -->
    <div class="adm-card">
      <div class="adm-card-header"><div class="adm-card-title">Créditer / Débiter des SWAPS</div></div>
      <div class="adm-form-grid" style="max-width:560px">
        <div class="adm-form-group">
          <label>Utilisateur (email)</label>
          <input type="email" id="swap-email" placeholder="user@example.com">
        </div>
        <div class="adm-form-group">
          <label>Montant SWAPS</label>
          <input type="number" id="swap-montant" placeholder="50" step="0.01">
        </div>
        <div class="adm-form-group">
          <label>Type</label>
          <select id="swap-type">
            <option value="depot">Dépôt (créditer)</option>
            <option value="retrait">Retrait (débiter)</option>
          </select>
        </div>
        <div class="adm-form-group">
          <label>Description</label>
          <input type="text" id="swap-desc" placeholder="Motif...">
        </div>
      </div>
      <button class="adm-btn adm-btn-primary" style="margin-top:14px" onclick="creditSwaps()">💱 Appliquer</button>
    </div>

    <?php endif; ?>

  </div><!-- /adm-content -->
</div><!-- /adm-main -->

<!-- ══ MODALS ══ -->
<!-- Confirm delete -->
<div class="adm-modal-overlay" id="modal-confirm">
  <div class="adm-modal">
    <h3>Confirmer la suppression</h3>
    <p id="confirm-text" style="color:#666;margin-bottom:10px"></p>
    <div class="adm-modal-footer">
      <button class="adm-btn" onclick="closeModal('modal-confirm')" style="background:#f0eef8;color:#666">Annuler</button>
      <button class="adm-btn adm-btn-danger" id="confirm-btn">🗑 Supprimer</button>
    </div>
  </div>
</div>

<!-- Detail commande -->
<div class="adm-modal-overlay" id="modal-commande">
  <div class="adm-modal" style="max-width:680px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px">
      <h3 style="margin:0">Détail commande</h3>
      <button class="adm-btn adm-btn-sm" onclick="closeModal('modal-commande')" style="background:#f0eef8;color:#666">✕</button>
    </div>
    <div id="commande-detail-content" style="color:#555;font-size:14px">Chargement...</div>
  </div>
</div>

<!-- Toast -->
<div id="adm-toast"></div>

<script>
/* ── Toast ── */
function toast(msg, ok=true) {
  var t = document.getElementById('adm-toast');
  t.textContent = msg;
  t.style.background = ok ? '#1a1a2e' : '#e04040';
  t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), 2800);
}

/* ── Modal ── */
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
function openModal(id)  { document.getElementById(id).classList.add('open'); }

/* ── Filters (articles) ── */
function filterArticles(s) { location.href = '?page=articles&statut=' + encodeURIComponent(s); }
function searchArticles(q) { location.href = '?page=articles&s=' + encodeURIComponent(q); }

/* ── Filters (commandes) ── */
function filterCmd(s) { location.href = '?page=commandes&statut=' + encodeURIComponent(s); }

/* ── Filters (profils) ── */
function filterProfils(r) { location.href = '?page=profils&role=' + encodeURIComponent(r); }
function searchProfils(q) { location.href = '?page=profils&s=' + encodeURIComponent(q); }

/* ── Update statut commande ── */
async function updateStatut(id, statut) {
  const r = await fetch('../php/api/commandes.php?action=update-statut&id=' + id, {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({statut})
  });
  const d = await r.json();
  d.success ? toast('✅ Statut mis à jour !') : toast('❌ ' + d.error, false);
}

/* ── Delete article ── */
function deleteArticle(id, titre) {
  document.getElementById('confirm-text').textContent = 'Supprimer "' + titre + '" ?';
  document.getElementById('confirm-btn').onclick = async function() {
    closeModal('modal-confirm');
    const r = await fetch('../php/api/produits.php?action=delete&id=' + id, { method: 'DELETE' });
    const d = await r.json();
    if (d.success) { toast('✅ Article supprimé !'); setTimeout(() => location.reload(), 800); }
    else toast('❌ ' + d.error, false);
  };
  openModal('modal-confirm');
}

function editArticle(id) { location.href = '?page=articles&edit=' + id; }

/* ── Toggle user active ── */
async function toggleUser(id, actif) {
  if (!confirm(actif ? 'Désactiver ce compte ?' : 'Réactiver ce compte ?')) return;
  const r = await fetch('../php/api/admin.php?action=toggle-user&id=' + id, {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({actif: actif ? 0 : 1})
  });
  const d = await r.json();
  if (d.success) { toast('✅ Mis à jour !'); setTimeout(() => location.reload(), 700); }
  else toast('❌ ' + d.error, false);
}

/* ── View commande detail ── */
async function viewCommande(id) {
  openModal('modal-commande');
  const r = await fetch('../php/api/commandes.php?action=detail&id=' + id);
  const d = await r.json();
  if (!d.success) { document.getElementById('commande-detail-content').textContent = 'Erreur.'; return; }
  const c = d.data;
  document.getElementById('commande-detail-content').innerHTML = `
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:18px">
      <div><strong>Référence:</strong> ${c.reference}</div>
      <div><strong>Statut:</strong> <span class="badge-status badge-${c.statut}">${c.statut}</span></div>
      <div><strong>Adresse:</strong> ${c.adresse_livr || '—'}</div>
      <div><strong>Téléphone:</strong> ${c.telephone || '—'}</div>
      <div><strong>Mode:</strong> ${c.mode_expedition || '—'}</div>
      <div><strong>Total:</strong> ${c.total_swaps} SWAPS</div>
      <div><strong>Date:</strong> ${new Date(c.created_at).toLocaleString('fr-FR')}</div>
    </div>
    <strong>Articles commandés:</strong>
    <table class="adm-table" style="margin-top:10px">
      <thead><tr><th>Photo</th><th>Titre</th><th>Taille</th><th>Qté</th><th>Prix</th></tr></thead>
      <tbody>${(c.lignes||[]).map(l => `
        <tr>
          <td><img src="../${l.image_snap||'cl1.png'}" style="width:40px;height:40px;object-fit:contain;background:#f5f4fb;border-radius:6px;padding:4px"></td>
          <td>${l.titre_snap}</td>
          <td>${l.taille||'—'}</td>
          <td>${l.quantite}</td>
          <td>${l.prix_swaps} S</td>
        </tr>`).join('')}
      </tbody>
    </table>
  `;
}

/* ── Credit SWAPS ── */
async function creditSwaps() {
  const email   = document.getElementById('swap-email').value.trim();
  const montant = parseFloat(document.getElementById('swap-montant').value);
  const type    = document.getElementById('swap-type').value;
  const desc    = document.getElementById('swap-desc').value.trim();

  if (!email || !montant) { toast('Email et montant requis.', false); return; }

  const r = await fetch('../php/api/admin.php?action=credit-swaps', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({email, montant, type, description: desc})
  });
  const d = await r.json();
  d.success ? toast('✅ ' + d.message) : toast('❌ ' + d.error, false);
}

/* ── Close modals on overlay click ── */
document.querySelectorAll('.adm-modal-overlay').forEach(el => {
  el.addEventListener('click', function(e) {
    if (e.target === el) el.classList.remove('open');
  });
});
</script>

</body>
</html>
