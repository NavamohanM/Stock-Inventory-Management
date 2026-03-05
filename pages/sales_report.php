<?php
require_once __DIR__ . '/../includes/db.php';
$page_title = 'Sales Report';

$start     = $_GET['start'] ?? date('Y-m-01');
$end       = $_GET['end']   ?? date('Y-m-d');
$product_q = trim($_GET['product'] ?? '');
$export    = $_GET['export'] ?? '';

$where  = ["DATE(s.created_at) >= ?", "DATE(s.created_at) <= ?"];
$params = [$start, $end];
$types  = 'ss';

if ($product_q) {
    $where[] = "s.product_name LIKE ?";
    $params[] = "%$product_q%";
    $types   .= 's';
}

$where_sql = 'WHERE ' . implode(' AND ', $where);

$rows = db_fetch_all(
    "SELECT s.id, s.product_name, s.quantity, s.unit_price, s.total_price,
            s.customer_name, s.note, s.created_at, u.name AS sold_by
     FROM sales s
     LEFT JOIN users u ON s.created_by = u.id
     $where_sql
     ORDER BY s.created_at DESC",
    $types, $params
);

$summary = db_fetch_one(
    "SELECT COUNT(*) AS total_txns, SUM(s.quantity) AS total_qty, SUM(s.total_price) AS total_revenue
     FROM sales s $where_sql",
    $types, $params
);

// CSV Export
if ($export === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="sales_report_' . $start . '_to_' . $end . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID','Product','Customer','Qty','Unit Price','Total','Date','Sold By']);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['id'], $r['product_name'], $r['customer_name'] ?? '',
            $r['quantity'], $r['unit_price'], $r['total_price'],
            $r['created_at'], $r['sold_by'] ?? ''
        ]);
    }
    fclose($out);
    exit;
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-3">
  <div>
    <h4 class="fw-bold mb-0"><i class="bi bi-graph-up me-2 text-success"></i>Sales Report</h4>
  </div>
  <div class="d-flex gap-2 no-print">
    <a href="?start=<?= urlencode($start) ?>&end=<?= urlencode($end) ?>&product=<?= urlencode($product_q) ?>&export=csv"
       class="btn btn-sm btn-outline-success"><i class="bi bi-download me-1"></i>Export CSV</a>
    <button onclick="window.print()" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer me-1"></i>Print</button>
  </div>
</div>

<!-- Filter -->
<div class="card shadow-sm mb-3 no-print">
  <div class="card-body py-2">
    <form method="get" class="row g-2 align-items-center">
      <div class="col-md-3">
        <label class="form-label small mb-1">From</label>
        <input type="date" name="start" class="form-control form-control-sm" value="<?= htmlspecialchars($start) ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label small mb-1">To</label>
        <input type="date" name="end" class="form-control form-control-sm" value="<?= htmlspecialchars($end) ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label small mb-1">Product</label>
        <input type="text" name="product" class="form-control form-control-sm" value="<?= htmlspecialchars($product_q) ?>" placeholder="Filter by product...">
      </div>
      <div class="col-md-2 d-flex align-items-end gap-1">
        <button type="submit" class="btn btn-sm btn-primary flex-fill">Filter</button>
        <a href="sales_report.php" class="btn btn-sm btn-outline-secondary">Reset</a>
      </div>
    </form>
  </div>
</div>

<!-- Summary Cards -->
<div class="row g-3 mb-3">
  <div class="col-md-4">
    <div class="card shadow-sm bg-success text-white">
      <div class="card-body py-3">
        <div class="small opacity-75">Total Revenue</div>
        <div class="fs-4 fw-bold"><?= CURRENCY ?><?= number_format($summary['total_revenue'] ?? 0, 2) ?></div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card shadow-sm bg-primary text-white">
      <div class="card-body py-3">
        <div class="small opacity-75">Transactions</div>
        <div class="fs-4 fw-bold"><?= number_format($summary['total_txns'] ?? 0) ?></div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card shadow-sm bg-info text-white">
      <div class="card-body py-3">
        <div class="small opacity-75">Units Sold</div>
        <div class="fs-4 fw-bold"><?= number_format($summary['total_qty'] ?? 0) ?></div>
      </div>
    </div>
  </div>
</div>

<div class="card shadow-sm">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead class="table-light">
          <tr>
            <th>#</th>
            <th>Product</th>
            <th>Customer</th>
            <th class="text-center">Qty</th>
            <th class="text-end">Unit Price</th>
            <th class="text-end">Total</th>
            <th>Sold By</th>
            <th>Date & Time</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($rows)): ?>
          <tr><td colspan="8" class="text-center text-muted py-4">No sales found for this period.</td></tr>
          <?php else: foreach ($rows as $r): ?>
          <tr>
            <td class="small text-muted"><?= $r['id'] ?></td>
            <td class="small fw-semibold"><?= htmlspecialchars($r['product_name']) ?></td>
            <td class="small"><?= htmlspecialchars($r['customer_name'] ?? '—') ?></td>
            <td class="small text-center"><?= $r['quantity'] ?></td>
            <td class="small text-end"><?= CURRENCY ?><?= number_format($r['unit_price'], 2) ?></td>
            <td class="small text-end fw-bold text-success"><?= CURRENCY ?><?= number_format($r['total_price'], 2) ?></td>
            <td class="small text-muted"><?= htmlspecialchars($r['sold_by'] ?? '—') ?></td>
            <td class="small text-muted"><?= date('d M Y, h:i A', strtotime($r['created_at'])) ?></td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
        <?php if (!empty($rows)): ?>
        <tfoot class="table-light fw-bold">
          <tr>
            <td colspan="5" class="text-end">Grand Total:</td>
            <td class="text-end text-success"><?= CURRENCY ?><?= number_format($summary['total_revenue'] ?? 0, 2) ?></td>
            <td colspan="2"></td>
          </tr>
        </tfoot>
        <?php endif; ?>
      </table>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
