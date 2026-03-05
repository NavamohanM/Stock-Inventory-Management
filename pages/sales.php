<?php
require_once __DIR__ . '/../includes/db.php';
$page_title = 'Sales';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { set_flash('danger','Invalid request.'); header('Location: sales.php'); exit; }

    $product_id    = (int)$_POST['product_id'];
    $quantity      = (int)$_POST['quantity'];
    $customer_name = trim($_POST['customer_name'] ?? '');
    $note          = trim($_POST['note'] ?? '');

    if (!$product_id || $quantity <= 0) {
        set_flash('danger','Please select a product and enter a valid quantity.');
        header('Location: sales.php'); exit;
    }

    $product = db_fetch_one("SELECT * FROM products WHERE id = ? AND deleted_at IS NULL LIMIT 1", "i", [$product_id]);

    if (!$product) { set_flash('danger','Product not found.'); header('Location: sales.php'); exit; }
    if ($product['unit'] < $quantity) {
        set_flash('danger',"Insufficient stock. Only {$product['unit']} units available.");
        header('Location: sales.php'); exit;
    }

    $unit_price = (float)$product['selling_price'];

    $conn = get_db();
    $conn->begin_transaction();
    try {
        db_insert(
            "INSERT INTO sales (product_id, product_name, quantity, unit_price, customer_name, note, created_by) VALUES (?,?,?,?,?,?,?)",
            "isiidsi",
            [$product_id, $product['name'], $quantity, $unit_price, $customer_name ?: null, $note ?: null, $_SESSION['user_id'] ?? null]
        );
        db_execute("UPDATE products SET unit = unit - ? WHERE id = ?", "ii", [$quantity, $product_id]);
        $conn->commit();

        $total = number_format($unit_price * $quantity, 2);
        set_flash('success', "Sold $quantity × {$product['name']} for " . CURRENCY . "$total.");
    } catch (Exception $e) {
        $conn->rollback();
        set_flash('danger','Sale failed: ' . $e->getMessage());
    }
    header('Location: sales.php'); exit;
}

$products = db_fetch_all(
    "SELECT id, name, unit, selling_price, sku, low_stock_alert
     FROM products WHERE deleted_at IS NULL AND unit > 0 ORDER BY name"
);

$recent_sales = db_fetch_all(
    "SELECT s.*, u.name AS sold_by
     FROM sales s
     LEFT JOIN users u ON s.created_by = u.id
     ORDER BY s.created_at DESC LIMIT 20"
);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="row g-4">
  <!-- Sale Form -->
  <div class="col-lg-4">
    <div class="card shadow-sm">
      <div class="card-header bg-white border-0">
        <h5 class="fw-bold mb-0"><i class="bi bi-cash-coin me-2 text-success"></i>Record Sale</h5>
        <p class="text-muted small mb-0">Sell from current stock</p>
      </div>
      <div class="card-body">
        <?php if (empty($products)): ?>
        <div class="alert alert-warning small">No products in stock. <a href="purchase.php">Add stock first.</a></div>
        <?php else: ?>
        <form method="post">
          <?= csrf_field() ?>
          <div class="mb-3">
            <label class="form-label fw-semibold">Product <span class="text-danger">*</span></label>
            <select name="product_id" class="form-select" id="productSelect" required>
              <option value="">— Select product —</option>
              <?php foreach ($products as $p): ?>
              <option value="<?= $p['id'] ?>"
                data-price="<?= $p['selling_price'] ?>"
                data-unit="<?= $p['unit'] ?>"
                data-alert="<?= $p['low_stock_alert'] ?>">
                <?= htmlspecialchars($p['name']) ?>
                <?= $p['sku'] ? '(' . htmlspecialchars($p['sku']) . ')' : '' ?>
                — <?= $p['unit'] ?> in stock
              </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div id="productDetails" class="d-none">
            <div class="alert alert-info py-2 small mb-3">
              <div class="d-flex justify-content-between">
                <span>Selling Price:</span>
                <strong id="priceDisplay">—</strong>
              </div>
              <div class="d-flex justify-content-between">
                <span>Available Stock:</span>
                <strong id="stockDisplay">—</strong>
              </div>
            </div>
          </div>

          <input type="hidden" id="unitPriceHidden" name="_unit_price">

          <div class="mb-3">
            <label class="form-label fw-semibold">Quantity <span class="text-danger">*</span></label>
            <input type="number" name="quantity" class="form-control" min="1"
                   id="sellUnitInput" name="quantity" required placeholder="0">
            <div class="form-text">Max: <span id="maxUnit">—</span> units</div>
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold">Total Amount</label>
            <div class="form-control bg-light fw-bold text-success fs-5">
              <?= CURRENCY ?><span id="totalDisplay">0.00</span>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold">Customer Name</label>
            <input type="text" name="customer_name" class="form-control" placeholder="Optional">
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold">Note</label>
            <input type="text" name="note" class="form-control" placeholder="Optional">
          </div>

          <button type="submit" class="btn btn-success w-100 fw-semibold">
            <i class="bi bi-check-circle me-1"></i>Confirm Sale
          </button>
        </form>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Recent Sales -->
  <div class="col-lg-8">
    <div class="card shadow-sm">
      <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
        <h5 class="fw-bold mb-0"><i class="bi bi-receipt me-2 text-success"></i>Recent Sales</h5>
        <a href="sales_report.php" class="btn btn-sm btn-outline-success">Full Report</a>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead class="table-light">
              <tr>
                <th>Product</th>
                <th>Customer</th>
                <th class="text-center">Qty</th>
                <th class="text-end">Unit Price</th>
                <th class="text-end">Total</th>
                <th>Date</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($recent_sales)): ?>
              <tr><td colspan="6" class="text-center text-muted py-4">No sales yet.</td></tr>
              <?php else: foreach ($recent_sales as $s): ?>
              <tr>
                <td class="small fw-semibold"><?= htmlspecialchars($s['product_name']) ?></td>
                <td class="small text-muted"><?= htmlspecialchars($s['customer_name'] ?? '—') ?></td>
                <td class="small text-center"><?= $s['quantity'] ?></td>
                <td class="small text-end"><?= CURRENCY ?><?= number_format($s['unit_price'], 2) ?></td>
                <td class="small text-end fw-semibold text-success"><?= CURRENCY ?><?= number_format($s['total_price'], 2) ?></td>
                <td class="small text-muted"><?= date('d M Y, h:i A', strtotime($s['created_at'])) ?></td>
              </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
