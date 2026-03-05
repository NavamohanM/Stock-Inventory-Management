<?php
require_once __DIR__ . '/../includes/db.php';
$page_title = 'Stock Report';

$export = $_GET['export'] ?? '';

$products = db_fetch_all(
    "SELECT p.*, c.name AS category, s.name AS supplier,
            (p.unit * p.unit_price) AS stock_value,
            (p.unit * p.selling_price) AS retail_value
     FROM products p
     LEFT JOIN categories c ON p.category_id = c.id
     LEFT JOIN suppliers s ON p.supplier_id = s.id
     WHERE p.deleted_at IS NULL
     ORDER BY p.name"
);

$totals = db_fetch_one(
    "SELECT COUNT(*) AS total_products,
            SUM(unit) AS total_units,
            SUM(unit * unit_price) AS total_cost_value,
            SUM(unit * selling_price) AS total_retail_value
     FROM products WHERE deleted_at IS NULL"
);

if ($export === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="stock_report_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID','SKU','Product','Category','Supplier','Units','Cost Price','Selling Price','Stock Cost Value','Stock Retail Value','Low Stock Alert','Status']);
    foreach ($products as $p) {
        $status = $p['unit'] == 0 ? 'Out of Stock' : ($p['unit'] <= $p['low_stock_alert'] ? 'Low Stock' : 'OK');
        fputcsv($out, [
            $p['id'], $p['sku'], $p['name'], $p['category'], $p['supplier'],
            $p['unit'], $p['unit_price'], $p['selling_price'],
            $p['stock_value'], $p['retail_value'], $p['low_stock_alert'], $status
        ]);
    }
    fclose($out);
    exit;
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-3">
  <h4 class="fw-bold mb-0"><i class="bi bi-archive me-2 text-primary"></i>Stock Report</h4>
  <div class="d-flex gap-2 no-print">
    <a href="?export=csv" class="btn btn-sm btn-outline-success"><i class="bi bi-download me-1"></i>Export CSV</a>
    <button onclick="window.print()" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer me-1"></i>Print</button>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="card shadow-sm"><div class="card-body py-3 text-center">
      <div class="fs-3 fw-bold text-primary"><?= number_format($totals['total_products']) ?></div>
      <div class="small text-muted">Total Products</div>
    </div></div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card shadow-sm"><div class="card-body py-3 text-center">
      <div class="fs-3 fw-bold text-success"><?= number_format($totals['total_units']) ?></div>
      <div class="small text-muted">Total Units</div>
    </div></div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card shadow-sm"><div class="card-body py-3 text-center">
      <div class="fs-3 fw-bold text-info"><?= CURRENCY ?><?= number_format($totals['total_cost_value']) ?></div>
      <div class="small text-muted">Stock Cost Value</div>
    </div></div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card shadow-sm"><div class="card-body py-3 text-center">
      <div class="fs-3 fw-bold text-warning"><?= CURRENCY ?><?= number_format($totals['total_retail_value']) ?></div>
      <div class="small text-muted">Retail Value</div>
    </div></div>
  </div>
</div>

<div class="card shadow-sm">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead class="table-light">
          <tr>
            <th>SKU</th><th>Product</th><th>Category</th>
            <th class="text-center">Units</th>
            <th class="text-end">Cost Price</th>
            <th class="text-end">Sell Price</th>
            <th class="text-end">Stock Value</th>
            <th class="text-center">Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($products as $p):
            $status = $p['unit'] == 0 ? ['out','Out of Stock','danger'] : ($p['unit'] <= $p['low_stock_alert'] ? ['low','Low Stock','warning'] : ['ok','In Stock','success']);
          ?>
          <tr>
            <td class="small"><code><?= htmlspecialchars($p['sku'] ?? '—') ?></code></td>
            <td class="small fw-semibold"><?= htmlspecialchars($p['name']) ?></td>
            <td class="small text-muted"><?= htmlspecialchars($p['category'] ?? '—') ?></td>
            <td class="small text-center fw-bold"><?= $p['unit'] ?></td>
            <td class="small text-end"><?= CURRENCY ?><?= number_format($p['unit_price'], 2) ?></td>
            <td class="small text-end"><?= CURRENCY ?><?= number_format($p['selling_price'], 2) ?></td>
            <td class="small text-end fw-semibold"><?= CURRENCY ?><?= number_format($p['stock_value'], 2) ?></td>
            <td class="text-center"><span class="badge bg-<?= $status[2] ?>"><?= $status[1] ?></span></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot class="table-light fw-bold">
          <tr>
            <td colspan="3" class="text-end">Totals:</td>
            <td class="text-center"><?= number_format($totals['total_units']) ?></td>
            <td colspan="2"></td>
            <td class="text-end text-info"><?= CURRENCY ?><?= number_format($totals['total_cost_value']) ?></td>
            <td></td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
