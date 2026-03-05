<?php
require_once __DIR__ . '/../includes/db.php';
$page_title = 'Products';

$filter   = $_GET['filter'] ?? '';
$search   = trim($_GET['q'] ?? '');
$cat_id   = (int)($_GET['category'] ?? 0);
$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = 15;

// ── Handle POST actions ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { set_flash('danger','Invalid request.'); header('Location: products.php'); exit; }

    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name   = trim($_POST['name'] ?? '');
        $desc   = trim($_POST['description'] ?? '');
        $sku    = trim($_POST['sku'] ?? '') ?: null;
        $cat    = (int)($_POST['category_id'] ?? 1);
        $sup    = (int)($_POST['supplier_id'] ?? 1);
        $unit   = max(0, (int)$_POST['unit']);
        $uprice = max(0, (float)$_POST['unit_price']);
        $sprice = max(0, (float)$_POST['selling_price']);
        $alert  = max(0, (int)($_POST['low_stock_alert'] ?? 10));

        if (!$name) { set_flash('danger','Product name is required.'); header('Location: products.php'); exit; }
        if ($sprice < $uprice) { set_flash('warning','Selling price is less than cost price.'); }

        db_insert(
            "INSERT INTO products (name, description, sku, category_id, supplier_id, unit, unit_price, selling_price, low_stock_alert) VALUES (?,?,?,?,?,?,?,?,?)",
            "sssiiiddi",
            [$name, $desc, $sku, $cat, $sup, $unit, $uprice, $sprice, $alert]
        );
        // Log initial purchase if unit > 0
        if ($unit > 0) {
            $prod = db_fetch_one("SELECT id FROM products ORDER BY id DESC LIMIT 1");
            if ($prod) {
                db_insert("INSERT INTO purchases (product_id, supplier_id, quantity, unit_price, created_by) VALUES (?,?,?,?,?)",
                    "iiiid", [$prod['id'], $sup, $unit, $uprice, $_SESSION['user_id'] ?? null]);
            }
        }
        set_flash('success', "Product '$name' added successfully.");
        header('Location: products.php'); exit;
    }

    if ($action === 'edit') {
        $id     = (int)$_POST['id'];
        $name   = trim($_POST['name'] ?? '');
        $desc   = trim($_POST['description'] ?? '');
        $sku    = trim($_POST['sku'] ?? '') ?: null;
        $cat    = (int)($_POST['category_id'] ?? 1);
        $sup    = (int)($_POST['supplier_id'] ?? 1);
        $uprice = max(0, (float)$_POST['unit_price']);
        $sprice = max(0, (float)$_POST['selling_price']);
        $alert  = max(0, (int)($_POST['low_stock_alert'] ?? 10));

        if (!$name || !$id) { set_flash('danger','Invalid data.'); header('Location: products.php'); exit; }
        db_execute(
            "UPDATE products SET name=?, description=?, sku=?, category_id=?, supplier_id=?, unit_price=?, selling_price=?, low_stock_alert=? WHERE id=?",
            "sssiiiddi",
            [$name, $desc, $sku, $cat, $sup, $uprice, $sprice, $alert, $id]
        );
        set_flash('success', "Product updated.");
        header('Location: products.php'); exit;
    }

    if ($action === 'delete') {
        if (!is_admin()) { set_flash('danger','Unauthorized.'); header('Location: products.php'); exit; }
        $id = (int)$_POST['id'];
        db_execute("UPDATE products SET deleted_at = NOW() WHERE id = ?", "i", [$id]);
        set_flash('success','Product deleted.');
        header('Location: products.php'); exit;
    }
}

// ── Build query ──
$where    = ["p.deleted_at IS NULL"];
$params   = [];
$types    = '';

if ($search) {
    $where[]  = "(p.name LIKE ? OR p.sku LIKE ? OR p.description LIKE ?)";
    $like = "%$search%";
    $params   = array_merge($params, [$like, $like, $like]);
    $types   .= 'sss';
}
if ($cat_id) {
    $where[] = "p.category_id = ?";
    $params[] = $cat_id;
    $types   .= 'i';
}
if ($filter === 'low_stock') {
    $where[] = "p.unit <= p.low_stock_alert";
}
if ($filter === 'out_of_stock') {
    $where[] = "p.unit = 0";
}

$where_sql = 'WHERE ' . implode(' AND ', $where);

$total_count = db_fetch_one("SELECT COUNT(*) AS cnt FROM products p $where_sql", $types, $params)['cnt'] ?? 0;
$total_pages = max(1, (int)ceil($total_count / $per_page));
$page = min($page, $total_pages);
$offset = ($page - 1) * $per_page;

$products = db_fetch_all(
    "SELECT p.*, c.name AS category, s.name AS supplier
     FROM products p
     LEFT JOIN categories c ON p.category_id = c.id
     LEFT JOIN suppliers s ON p.supplier_id = s.id
     $where_sql
     ORDER BY p.created_at DESC
     LIMIT $per_page OFFSET $offset",
    $types, $params
);

$categories = db_fetch_all("SELECT * FROM categories ORDER BY name");
$suppliers  = db_fetch_all("SELECT * FROM suppliers WHERE deleted_at IS NULL ORDER BY name");

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-2">
  <div>
    <h4 class="fw-bold mb-0"><i class="bi bi-box-seam me-2 text-primary"></i>Products</h4>
    <p class="text-muted small mb-0"><?= $total_count ?> product<?= $total_count != 1 ? 's' : '' ?></p>
  </div>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProductModal">
    <i class="bi bi-plus-lg me-1"></i>Add Product
  </button>
</div>

<!-- Filter bar -->
<div class="card shadow-sm mb-3">
  <div class="card-body py-2">
    <form method="get" class="row g-2 align-items-center">
      <div class="col-md-4">
        <div class="input-group input-group-sm">
          <span class="input-group-text"><i class="bi bi-search"></i></span>
          <input type="text" name="q" class="form-control" placeholder="Search name, SKU..." value="<?= htmlspecialchars($search) ?>">
        </div>
      </div>
      <div class="col-md-3">
        <select name="category" class="form-select form-select-sm">
          <option value="">All Categories</option>
          <?php foreach ($categories as $c): ?>
          <option value="<?= $c['id'] ?>" <?= $cat_id == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <select name="filter" class="form-select form-select-sm">
          <option value="">All Stock</option>
          <option value="low_stock" <?= $filter === 'low_stock' ? 'selected' : '' ?>>Low Stock</option>
          <option value="out_of_stock" <?= $filter === 'out_of_stock' ? 'selected' : '' ?>>Out of Stock</option>
        </select>
      </div>
      <div class="col-md-2 d-flex gap-1">
        <button type="submit" class="btn btn-sm btn-primary flex-fill">Filter</button>
        <a href="products.php" class="btn btn-sm btn-outline-secondary">Reset</a>
      </div>
    </form>
  </div>
</div>

<div class="card shadow-sm">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead class="table-light">
          <tr>
            <th>#</th>
            <th>SKU</th>
            <th>Product</th>
            <th>Category</th>
            <th class="text-end">Cost Price</th>
            <th class="text-end">Sell Price</th>
            <th class="text-center">Stock</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($products)): ?>
          <tr><td colspan="8" class="text-center text-muted py-4">No products found.</td></tr>
          <?php else: foreach ($products as $p):
            $stock_class = $p['unit'] == 0 ? 'badge-stock-out' : ($p['unit'] <= $p['low_stock_alert'] ? 'badge-stock-low' : 'badge-stock-ok');
          ?>
          <tr>
            <td class="small text-muted"><?= $p['id'] ?></td>
            <td class="small"><code><?= htmlspecialchars($p['sku'] ?? '—') ?></code></td>
            <td>
              <div class="fw-semibold small"><?= htmlspecialchars($p['name']) ?></div>
              <?php if ($p['description']): ?>
              <div class="text-muted" style="font-size:0.75rem"><?= htmlspecialchars(substr($p['description'],0,50)) ?></div>
              <?php endif; ?>
            </td>
            <td class="small"><?= htmlspecialchars($p['category'] ?? '—') ?></td>
            <td class="small text-end"><?= CURRENCY ?><?= number_format($p['unit_price'], 2) ?></td>
            <td class="small text-end"><?= CURRENCY ?><?= number_format($p['selling_price'], 2) ?></td>
            <td class="text-center">
              <span class="badge <?= $stock_class ?>"><?= $p['unit'] ?></span>
            </td>
            <td class="text-end">
              <div class="d-flex justify-content-end gap-1">
                <button class="btn btn-sm btn-outline-primary py-0 px-2"
                  data-bs-toggle="modal" data-bs-target="#editProductModal"
                  data-id="<?= $p['id'] ?>"
                  data-name="<?= htmlspecialchars($p['name'], ENT_QUOTES) ?>"
                  data-description="<?= htmlspecialchars($p['description'] ?? '', ENT_QUOTES) ?>"
                  data-sku="<?= htmlspecialchars($p['sku'] ?? '', ENT_QUOTES) ?>"
                  data-category_id="<?= $p['category_id'] ?>"
                  data-supplier_id="<?= $p['supplier_id'] ?>"
                  data-unit_price="<?= $p['unit_price'] ?>"
                  data-selling_price="<?= $p['selling_price'] ?>"
                  data-low_stock_alert="<?= $p['low_stock_alert'] ?>">
                  <i class="bi bi-pencil"></i>
                </button>
                <?php if (is_admin()): ?>
                <form method="post" class="d-inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= $p['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2"
                    data-confirm="Delete '<?= htmlspecialchars($p['name'], ENT_QUOTES) ?>'? This cannot be undone.">
                    <i class="bi bi-trash"></i>
                  </button>
                </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php if ($total_pages > 1): ?>
  <div class="card-footer bg-white border-top d-flex justify-content-between align-items-center">
    <span class="text-muted small">Page <?= $page ?> of <?= $total_pages ?></span>
    <nav>
      <ul class="pagination pagination-sm mb-0">
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
          <a class="page-link" href="?page=<?= $i ?>&q=<?= urlencode($search) ?>&category=<?= $cat_id ?>&filter=<?= urlencode($filter) ?>"><?= $i ?></a>
        </li>
        <?php endfor; ?>
      </ul>
    </nav>
  </div>
  <?php endif; ?>
</div>

<!-- Add Product Modal -->
<div class="modal fade" id="addProductModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <form method="post" class="modal-content">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add">
      <div class="modal-header">
        <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2"></i>Add New Product</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-8">
            <label class="form-label fw-semibold">Product Name <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control" required placeholder="e.g. Cotton Shirt">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">SKU</label>
            <input type="text" name="sku" class="form-control" placeholder="e.g. SKU-001">
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold">Description</label>
            <textarea name="description" class="form-control" rows="2" placeholder="Short description..."></textarea>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Category</label>
            <select name="category_id" class="form-select">
              <?php foreach ($categories as $c): ?>
              <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Supplier</label>
            <select name="supplier_id" class="form-select">
              <?php foreach ($suppliers as $s): ?>
              <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label fw-semibold">Opening Stock</label>
            <input type="number" name="unit" class="form-control" value="0" min="0">
          </div>
          <div class="col-md-3">
            <label class="form-label fw-semibold">Cost Price (<?= CURRENCY ?>)</label>
            <input type="number" name="unit_price" class="form-control" step="0.01" value="0" min="0" required>
          </div>
          <div class="col-md-3">
            <label class="form-label fw-semibold">Selling Price (<?= CURRENCY ?>)</label>
            <input type="number" name="selling_price" class="form-control" step="0.01" value="0" min="0" required>
          </div>
          <div class="col-md-3">
            <label class="form-label fw-semibold">Low Stock Alert</label>
            <input type="number" name="low_stock_alert" class="form-control" value="10" min="0">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Add Product</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Product Modal -->
<div class="modal fade" id="editProductModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <form method="post" class="modal-content" id="editProductForm">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="id" id="edit_id">
      <div class="modal-header">
        <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>Edit Product</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-8">
            <label class="form-label fw-semibold">Product Name <span class="text-danger">*</span></label>
            <input type="text" name="name" id="edit_name" class="form-control" required>
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">SKU</label>
            <input type="text" name="sku" id="edit_sku" class="form-control">
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold">Description</label>
            <textarea name="description" id="edit_description" class="form-control" rows="2"></textarea>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Category</label>
            <select name="category_id" id="edit_category_id" class="form-select">
              <?php foreach ($categories as $c): ?>
              <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Supplier</label>
            <select name="supplier_id" id="edit_supplier_id" class="form-select">
              <?php foreach ($suppliers as $s): ?>
              <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Cost Price (<?= CURRENCY ?>)</label>
            <input type="number" name="unit_price" id="edit_unit_price" class="form-control" step="0.01" min="0" required>
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Selling Price (<?= CURRENCY ?>)</label>
            <input type="number" name="selling_price" id="edit_selling_price" class="form-control" step="0.01" min="0" required>
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Low Stock Alert</label>
            <input type="number" name="low_stock_alert" id="edit_low_stock_alert" class="form-control" min="0">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save Changes</button>
      </div>
    </form>
  </div>
</div>

<script>
// Populate edit modal with row data
document.getElementById('editProductModal').addEventListener('show.bs.modal', function(e) {
  const btn = e.relatedTarget;
  const d = btn.dataset;
  document.getElementById('edit_id').value             = d.id;
  document.getElementById('edit_name').value           = d.name;
  document.getElementById('edit_description').value    = d.description;
  document.getElementById('edit_sku').value            = d.sku;
  document.getElementById('edit_unit_price').value     = d.unit_price;
  document.getElementById('edit_selling_price').value  = d.selling_price;
  document.getElementById('edit_low_stock_alert').value= d.low_stock_alert;
  document.getElementById('edit_category_id').value    = d.category_id;
  document.getElementById('edit_supplier_id').value    = d.supplier_id;
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
