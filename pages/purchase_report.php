<?php
require_once __DIR__ . '/../includes/db.php';
$page_title = 'Purchase Report';

$start  = $_GET['start'] ?? date('Y-m-01');
$end    = $_GET['end']   ?? date('Y-m-d');
$export = $_GET['export'] ?? '';

$rows = db_fetch_all(
    "SELECT pu.*, p.name AS product_name, s.name AS supplier_name, u.name AS recorded_by
     FROM purchases pu
     LEFT JOIN products p ON pu.product_id = p.id
     LEFT JOIN suppliers s ON pu.supplier_id = s.id
     LEFT JOIN users u ON pu.created_by = u.id
     WHERE DATE(pu.created_at) >= ? AND DATE(pu.created_at) <= ?
     ORDER BY pu.created_at DESC",
    "ss", [$start, $end]
);

$summary = db_fetch_one(
    "SELECT COUNT(*) AS txns, SUM(quantity) AS total_qty, SUM(total_cost) AS total_spent
     FROM purchases WHERE DATE(created_at) >= ? AND DATE(created_at) <= ?",
    "ss", [$start, $end]
);

if ($export === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="purchase_report_' . $start . '_to_' . $end . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID','Product','Supplier','Qty','Unit Cost','Total Cost','Note','Date','Recorded By']);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['id'], $r['product_name'], $r['supplier_name'] ?? '',
            $r['quantity'], $r['unit_price'], $r['total_cost'],
            $r['note'] ?? '', $r['created_at'], $r['recorded_by'] ?? ''
        ]);
    }
    fclose($out);
    exit;
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-3">
  <h4 class="fw-bold mb-0"><i class="bi bi-graph-down me-2 text-primary"></i>Purchase Report</h4>
  <div class="d-flex gap-2 no-print">
    <a href="?start=<?= urlencode($start) ?>&end=<?= urlencode($end) ?>&export=csv"
       class="btn btn-sm btn-outline-primary"><i class="bi bi-download me-1"></i>Export CSV</a>
    <button onclick="window.print()" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer me-1"></i>Print</button>
  </div>
</div>

<div class="card shadow-sm mb-3 no-print">
  <div class="card-body py-2">
    <form method="get" class="row g-2 align-items-end">
      <div class="col-md-4">
        <label class="form-label small mb-1">From</label>
        <input type="date" name="start" class="form-control form-control-sm" value="<?= htmlspecialchars($start) ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label small mb-1">To</label>
        <input type="date" name="end" class="form-control form-control-sm" value="<?= htmlspecialchars($end) ?>">
      </div>
      <div class="col-md-4 d-flex gap-1 align-items-end">
        <button type="submit" class="btn btn-sm btn-primary flex-fill">Filter</button>
        <a href="purchase_report.php" class="btn btn-sm btn-outline-secondary">Reset</a>
      </div>
    </form>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-md-4">
    <div class="card shadow-sm bg-primary text-white"><div class="card-body py-3">
      <div class="small opacity-75">Total Spent</div>
      <div class="fs-4 fw-bold"><?= CURRENCY ?><?= number_format($summary['total_spent'] ?? 0, 2) ?></div>
    </div></div>
  </div>
  <div class="col-md-4">
    <div class="card shadow-sm bg-success text-white"><div class="card-body py-3">
      <div class="small opacity-75">Transactions</div>
      <div class="fs-4 fw-bold"><?= number_format($summary['txns'] ?? 0) ?></div>
    </div></div>
  </div>
  <div class="col-md-4">
    <div class="card shadow-sm bg-info text-white"><div class="card-body py-3">
      <div class="small opacity-75">Units Purchased</div>
      <div class="fs-4 fw-bold"><?= number_format($summary['total_qty'] ?? 0) ?></div>
    </div></div>
  </div>
</div>

<div class="card shadow-sm">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead class="table-light">
          <tr>
            <th>#</th><th>Product</th><th>Supplier</th>
            <th class="text-center">Qty</th>
            <th class="text-end">Unit Cost</th>
            <th class="text-end">Total Cost</th>
            <th>Note</th><th>Date</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($rows)): ?>
          <tr><td colspan="8" class="text-center text-muted py-4">No purchases for this period.</td></tr>
          <?php else: foreach ($rows as $r): ?>
          <tr>
            <td class="small text-muted"><?= $r['id'] ?></td>
            <td class="small fw-semibold"><?= htmlspecialchars($r['product_name'] ?? '—') ?></td>
            <td class="small"><?= htmlspecialchars($r['supplier_name'] ?? '—') ?></td>
            <td class="small text-center"><?= $r['quantity'] ?></td>
            <td class="small text-end"><?= CURRENCY ?><?= number_format($r['unit_price'], 2) ?></td>
            <td class="small text-end fw-bold text-primary"><?= CURRENCY ?><?= number_format($r['total_cost'], 2) ?></td>
            <td class="small text-muted"><?= htmlspecialchars($r['note'] ?? '—') ?></td>
            <td class="small text-muted"><?= date('d M Y', strtotime($r['created_at'])) ?></td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
        <?php if (!empty($rows)): ?>
        <tfoot class="table-light fw-bold">
          <tr>
            <td colspan="5" class="text-end">Grand Total:</td>
            <td class="text-end text-primary"><?= CURRENCY ?><?= number_format($summary['total_spent'] ?? 0, 2) ?></td>
            <td colspan="2"></td>
          </tr>
        </tfoot>
        <?php endif; ?>
      </table>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
