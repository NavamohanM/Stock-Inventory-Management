<?php
require_once __DIR__ . '/auth.php';
require_login();
$user = current_user();
$flash = get_flash();

// Active nav helper
function nav_active(string $page): string {
    $current = basename($_SERVER['PHP_SELF'], '.php');
    return $current === $page ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? h($page_title) . ' — ' : '' ?><?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="/assets/css/app.css" rel="stylesheet">
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top shadow-sm">
  <div class="container-fluid">
    <a class="navbar-brand fw-bold" href="/dashboard.php">
      <i class="bi bi-boxes me-2"></i><?= APP_NAME ?>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navMain">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item">
          <a class="nav-link <?= nav_active('dashboard') ?>" href="/dashboard.php">
            <i class="bi bi-speedometer2 me-1"></i>Dashboard
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= nav_active('products') ?>" href="/pages/products.php">
            <i class="bi bi-box-seam me-1"></i>Products
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= nav_active('purchase') ?>" href="/pages/purchase.php">
            <i class="bi bi-cart-plus me-1"></i>Purchase
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= nav_active('sales') ?>" href="/pages/sales.php">
            <i class="bi bi-cash-coin me-1"></i>Sales
          </a>
        </li>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle <?= in_array(nav_active('sales_report') ?: nav_active('purchase_report'), ['active']) ? 'active' : '' ?>" href="#" data-bs-toggle="dropdown">
            <i class="bi bi-bar-chart me-1"></i>Reports
          </a>
          <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="/pages/sales_report.php"><i class="bi bi-graph-up me-2"></i>Sales Report</a></li>
            <li><a class="dropdown-item" href="/pages/purchase_report.php"><i class="bi bi-graph-down me-2"></i>Purchase Report</a></li>
            <li><a class="dropdown-item" href="/pages/stock_report.php"><i class="bi bi-archive me-2"></i>Stock Report</a></li>
          </ul>
        </li>
        <?php if (is_admin()): ?>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle <?= nav_active('users') === 'active' || nav_active('categories') === 'active' || nav_active('suppliers') === 'active' ? 'active' : '' ?>" href="#" data-bs-toggle="dropdown">
            <i class="bi bi-gear me-1"></i>Admin
          </a>
          <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="/pages/users.php"><i class="bi bi-people me-2"></i>Users</a></li>
            <li><a class="dropdown-item" href="/pages/categories.php"><i class="bi bi-tags me-2"></i>Categories</a></li>
            <li><a class="dropdown-item" href="/pages/suppliers.php"><i class="bi bi-truck me-2"></i>Suppliers</a></li>
          </ul>
        </li>
        <?php endif; ?>
      </ul>
      <ul class="navbar-nav ms-auto">
        <?php
        // Low stock count badge
        require_once __DIR__ . '/db.php';
        $low_stock_count = db_fetch_one("SELECT COUNT(*) as cnt FROM products WHERE unit <= ? AND deleted_at IS NULL", "i", [LOW_STOCK_THRESHOLD]);
        $lsc = (int)($low_stock_count['cnt'] ?? 0);
        ?>
        <?php if ($lsc > 0): ?>
        <li class="nav-item">
          <a class="nav-link text-warning" href="/pages/products.php?filter=low_stock" title="Low stock items">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <span class="badge bg-warning text-dark"><?= $lsc ?></span>
          </a>
        </li>
        <?php endif; ?>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
            <i class="bi bi-person-circle me-1"></i><?= h($user['name'] ?? 'User') ?>
          </a>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><span class="dropdown-item-text text-muted small"><?= h($user['role'] ?? '') ?></span></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="/pages/profile.php"><i class="bi bi-person me-2"></i>Profile</a></li>
            <li><a class="dropdown-item text-danger" href="/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
          </ul>
        </li>
      </ul>
    </div>
  </div>
</nav>

<div class="container-fluid py-3 px-4">
<?php if ($flash): ?>
<div class="alert alert-<?= h($flash['type']) ?> alert-dismissible fade show" role="alert">
  <?= h($flash['message']) ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
