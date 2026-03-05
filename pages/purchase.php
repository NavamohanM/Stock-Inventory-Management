<?php
require_once __DIR__ . '/../includes/db.php';
$page_title = 'Purchase';

// Pre-select product from URL (from dashboard "Restock" link)
$preselect_product = (int)($_GET['product_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { set_flash('danger','Invalid request.'); header('Location: purchase.php'); exit; }

    $product_id  = (int)$_POST['product_id'];
    $supplier_id = (int)$_POST['supplier_id'];
    $quantity    = (int)$_POST['quantity'];
    $unit_price  = (float)$_POST['unit_price'];
    $note        = trim($_POST['note'] ?? '');

    if (!$product_id || $quantity <= 0 || $unit_price < 0) {
        set_flash('danger','Please fill all required fields correctly.');
        header('Location: purchase.php'); exit;
    }

    // Begin transaction: add to purchases + update product stock
    $conn = get_db();
    $conn->begin_transaction();
    try {
        db_insert(
            "INSERT INTO purchases (product_id, supplier_id, quantity, unit_price, note, created_by) VALUES (?,?,?,?,?,?)",
            "iiidsi",
            [$product_id, $supplier_id ?: null, $quantity, $unit_price, $note ?: null, $_SESSION['user_id'] ?? null]
        );
        db_execute(
            "UPDATE products SET unit = unit + ?, unit_price = ? WHERE id = ?",
            "idi",
            [$quantity, $unit_price, $product_id]
        );
        $conn->commit();

        $prod = db_fetch_one("SELECT name FROM products WHERE id = ?", "i", [$product_id]);
        set_flash('success', "Purchase recorded. Added $quantity units to '{$prod['name']}'.");
    } catch (Exception $e) {
        $conn->rollback();
        set_flash('danger','Purchase failed: ' . $e->getMessage());
    }
    header('Location: purchase.php'); exit;
}

$products  = db_fetch_all("SELECT id, name, unit, unit_price, sku FROM products WHERE deleted_at IS NULL ORDER BY name");
$suppliers = db_fetch_all("SELECT id, name FROM suppliers WHERE deleted_at IS NULL ORDER BY name");

// Recent purchases
$recent = db_fetch_all(
    "SELECT pu.*, p.name AS product_name, s.name AS supplier_name, u.name AS recorded_by
     FROM purchases pu
     LEFT JOIN products p ON pu.product_id = p.id
     LEFT JOIN suppliers s ON pu.supplier_id = s.id
     LEFT JOIN users u ON pu.created_by = u.id
     ORDER BY pu.created_at DESC LIMIT 20"
);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="row g-4">
  <!-- Purchase Form -->
  <div class="col-lg-4">
    <div class="card shadow-sm">
      <div class="card-header bg-white border-0">
        <h5 class="fw-bold mb-0"><i class="bi bi-cart-plus me-2 text-primary"></i>New Purchase</h5>
        <p class="text-muted small mb-0">Add stock to existing products</p>
      </div>
      <div class="card-body">
        <form method="post">
          <?= csrf_field() ?>
          <div class="mb-3">
            <label class="form-label fw-semibold">Product <span class="text-danger">*</span></label>
            <select name="product_id" class="form-select" id="purchaseProductSelect" required>
              <option value="">— Select product —</option>
              <?php foreach ($products as $p): ?>
              <option value="<?= $p['id'] ?>"
                data-price="<?= $p['unit_price'] ?>"
                data-stock="<?= $p['unit'] ?>"
                <?= $preselect_product == $p['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($p['name']) ?>
                <?= $p['sku'] ? '(' . htmlspecialchars($p['sku']) . ')' : '' ?>
                — <?= $p['unit'] ?> in stock
              </option>
              <?php endforeach; ?>
            </select>
            <div id="currentStockInfo" class="form-text text-muted mt-1"></div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Supplier</label>
            <select name="supplier_id" class="form-select">
              <option value="">— None —</option>
              <?php foreach ($suppliers as $s): ?>
              <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Quantity <span class="text-danger">*</span></label>
            <input type="number" name="quantity" class="form-control" min="1" required placeholder="0" id="purchaseQty">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Cost Price / Unit (<?= CURRENCY ?>)</label>
            <input type="number" name="unit_price" class="form-control" step="0.01" min="0" id="purchasePrice" placeholder="0.00">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Total Cost</label>
            <div class="form-control bg-light fw-bold text-success" id="purchaseTotalCost"><?= CURRENCY ?>0.00</div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Note</label>
            <input type="text" name="note" class="form-control" placeholder="Optional note...">
          </div>
          <button type="submit" class="btn btn-primary w-100">
            <i class="bi bi-cart-plus me-1"></i>Record Purchase
          </button>
        </form>
      </div>
    </div>
  </div>

  <!-- Purchase History -->
  <div class="col-lg-8">
    <div class="card shadow-sm">
      <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
        <h5 class="fw-bold mb-0"><i class="bi bi-clock-history me-2 text-success"></i>Recent Purchases</h5>
        <a href="purchase_report.php" class="btn btn-sm btn-outline-success">Full Report</a>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead class="table-light">
              <tr>
                <th>Product</th>
                <th>Supplier</th>
                <th class="text-center">Qty</th>
                <th class="text-end">Unit Cost</th>
                <th class="text-end">Total Cost</th>
                <th>Date</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($recent)): ?>
              <tr><td colspan="6" class="text-center text-muted py-4">No purchases yet.</td></tr>
              <?php else: foreach ($recent as $r): ?>
              <tr>
                <td class="small fw-semibold"><?= htmlspecialchars($r['product_name'] ?? '—') ?></td>
                <td class="small text-muted"><?= htmlspecialchars($r['supplier_name'] ?? '—') ?></td>
                <td class="small text-center"><?= $r['quantity'] ?></td>
                <td class="small text-end"><?= CURRENCY ?><?= number_format($r['unit_price'], 2) ?></td>
                <td class="small text-end fw-semibold text-primary"><?= CURRENCY ?><?= number_format($r['total_cost'], 2) ?></td>
                <td class="small text-muted"><?= date('d M Y, h:i A', strtotime($r['created_at'])) ?></td>
              </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
const purchaseProductSelect = document.getElementById('purchaseProductSelect');
const purchaseQty   = document.getElementById('purchaseQty');
const purchasePrice = document.getElementById('purchasePrice');
const totalCostEl   = document.getElementById('purchaseTotalCost');
const stockInfo     = document.getElementById('currentStockInfo');

function updateTotal() {
  const qty   = parseFloat(purchaseQty.value) || 0;
  const price = parseFloat(purchasePrice.value) || 0;
  totalCostEl.textContent = '<?= CURRENCY ?>' + (qty * price).toLocaleString('en-IN', { minimumFractionDigits: 2 });
}

purchaseProductSelect.addEventListener('change', function() {
  const opt = this.options[this.selectedIndex];
  if (opt.value) {
    purchasePrice.value = opt.dataset.price || '';
    stockInfo.textContent = 'Current stock: ' + (opt.dataset.stock || 0) + ' units';
    updateTotal();
  } else {
    stockInfo.textContent = '';
  }
});

purchaseQty.addEventListener('input', updateTotal);
purchasePrice.addEventListener('input', updateTotal);

// Auto-trigger if pre-selected
if (purchaseProductSelect.value) purchaseProductSelect.dispatchEvent(new Event('change'));
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
