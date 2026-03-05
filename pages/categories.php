<?php
require_once __DIR__ . '/../includes/db.php';
$page_title = 'Categories';

if (!is_admin()) { set_flash('danger','Admin access required.'); header('Location: /dashboard.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { set_flash('danger','Invalid request.'); header('Location: categories.php'); exit; }
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        if (!$name) { set_flash('danger','Category name required.'); header('Location: categories.php'); exit; }
        db_insert("INSERT INTO categories (name, description) VALUES (?,?)", "ss", [$name, $desc ?: null]);
        set_flash('success', "Category '$name' added.");
    }
    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        $count = db_fetch_one("SELECT COUNT(*) AS cnt FROM products WHERE category_id = ? AND deleted_at IS NULL", "i", [$id])['cnt'];
        if ($count > 0) { set_flash('danger',"Cannot delete — $count products use this category."); }
        else { db_execute("DELETE FROM categories WHERE id = ?", "i", [$id]); set_flash('success','Category deleted.'); }
    }
    header('Location: categories.php'); exit;
}

$categories = db_fetch_all(
    "SELECT c.*, COUNT(p.id) AS product_count
     FROM categories c
     LEFT JOIN products p ON c.id = p.category_id AND p.deleted_at IS NULL
     GROUP BY c.id ORDER BY c.name"
);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="row g-4">
  <div class="col-lg-4">
    <div class="card shadow-sm">
      <div class="card-header bg-white border-0"><h5 class="fw-bold mb-0"><i class="bi bi-plus-circle me-2"></i>Add Category</h5></div>
      <div class="card-body">
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="add">
          <div class="mb-3">
            <label class="form-label fw-semibold">Name <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control" required placeholder="Category name">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Description</label>
            <textarea name="description" class="form-control" rows="2" placeholder="Optional..."></textarea>
          </div>
          <button type="submit" class="btn btn-primary w-100">Add Category</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="card shadow-sm">
      <div class="card-header bg-white border-0"><h5 class="fw-bold mb-0"><i class="bi bi-tags me-2 text-primary"></i>All Categories</h5></div>
      <div class="card-body p-0">
        <table class="table table-hover mb-0">
          <thead class="table-light">
            <tr><th>#</th><th>Name</th><th>Description</th><th class="text-center">Products</th><th></th></tr>
          </thead>
          <tbody>
            <?php foreach ($categories as $c): ?>
            <tr>
              <td class="small text-muted"><?= $c['id'] ?></td>
              <td class="fw-semibold small"><?= htmlspecialchars($c['name']) ?></td>
              <td class="small text-muted"><?= htmlspecialchars($c['description'] ?? '—') ?></td>
              <td class="text-center"><span class="badge bg-primary"><?= $c['product_count'] ?></span></td>
              <td>
                <form method="post" class="d-inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= $c['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2"
                    data-confirm="Delete category '<?= htmlspecialchars($c['name'], ENT_QUOTES) ?>'?">
                    <i class="bi bi-trash"></i>
                  </button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
