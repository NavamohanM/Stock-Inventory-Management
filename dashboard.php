<?php
require_once __DIR__ . '/includes/db.php';
$page_title = 'Dashboard';

// ── Stats ──
$total_products  = db_fetch_one("SELECT COUNT(*) AS cnt FROM products WHERE deleted_at IS NULL")['cnt'] ?? 0;
$total_stock_val = db_fetch_one("SELECT COALESCE(SUM(unit * unit_price),0) AS val FROM products WHERE deleted_at IS NULL")['val'] ?? 0;
$low_stock_items = db_fetch_one("SELECT COUNT(*) AS cnt FROM products WHERE unit <= low_stock_alert AND deleted_at IS NULL")['cnt'] ?? 0;
$out_of_stock    = db_fetch_one("SELECT COUNT(*) AS cnt FROM products WHERE unit = 0 AND deleted_at IS NULL")['cnt'] ?? 0;

$today_sales = db_fetch_one(
    "SELECT COALESCE(SUM(total_price),0) AS total, COUNT(*) AS cnt FROM sales WHERE DATE(created_at) = CURDATE()"
);
$today_revenue = $today_sales['total'] ?? 0;
$today_txns    = $today_sales['cnt'] ?? 0;

$month_sales = db_fetch_one(
    "SELECT COALESCE(SUM(total_price),0) AS total FROM sales WHERE YEAR(created_at)=YEAR(NOW()) AND MONTH(created_at)=MONTH(NOW())"
);
$month_revenue = $month_sales['total'] ?? 0;

// ── Last 7 days sales chart data ──
$chart_rows = db_fetch_all(
    "SELECT DATE(created_at) AS day, SUM(total_price) AS revenue
     FROM sales
     WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
     GROUP BY DATE(created_at)
     ORDER BY day ASC"
);

$chart_labels  = [];
$chart_revenue = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $chart_labels[] = date('D d', strtotime($date));
    $found = 0;
    foreach ($chart_rows as $r) {
        if ($r['day'] === $date) { $found = $r['revenue']; break; }
    }
    $chart_revenue[] = (float)$found;
}

// ── Top 5 selling products this month ──
$top_products = db_fetch_all(
    "SELECT product_name, SUM(quantity) AS qty, SUM(total_price) AS revenue
     FROM sales
     WHERE YEAR(created_at)=YEAR(NOW()) AND MONTH(created_at)=MONTH(NOW())
     GROUP BY product_name ORDER BY qty DESC LIMIT 5"
);

// ── Stock by category (for donut chart) ──
$cat_stock = db_fetch_all(
    "SELECT c.name AS category, SUM(p.unit) AS total_units
     FROM products p
     LEFT JOIN categories c ON p.category_id = c.id
     WHERE p.deleted_at IS NULL
     GROUP BY c.name ORDER BY total_units DESC"
);

// ── Low stock items ──
$low_items = db_fetch_all(
    "SELECT p.id, p.name, p.unit, p.low_stock_alert, c.name AS category
     FROM products p
     LEFT JOIN categories c ON p.category_id = c.id
     WHERE p.unit <= p.low_stock_alert AND p.deleted_at IS NULL
     ORDER BY p.unit ASC LIMIT 8"
);

// ── Recent sales ──
$recent_sales = db_fetch_all(
    "SELECT s.id, s.product_name, s.quantity, s.total_price, s.created_at, u.name AS sold_by
     FROM sales s
     LEFT JOIN users u ON s.created_by = u.id
     ORDER BY s.created_at DESC LIMIT 8"
);

require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex align-items-center mb-4">
  <div>
    <h4 class="fw-bold mb-0">Dashboard</h4>
    <p class="text-muted small mb-0"><?= date('l, d F Y') ?></p>
  </div>
</div>

<!-- ── Stat Cards ── -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <a href="/pages/products.php" class="text-decoration-none">
      <div class="card stat-card shadow-sm h-100">
        <div class="card-body d-flex align-items-center gap-3">
          <div class="stat-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-box-seam"></i></div>
          <div>
            <div class="fs-4 fw-bold text-dark"><?= number_format($total_products) ?></div>
            <div class="text-muted small">Total Products <i class="bi bi-arrow-right ms-1"></i></div>
          </div>
        </div>
      </div>
    </a>
  </div>
  <div class="col-6 col-md-3">
    <a href="/pages/sales_report.php?start=<?= date('Y-m-01') ?>&end=<?= date('Y-m-d') ?>" class="text-decoration-none">
      <div class="card stat-card shadow-sm h-100">
        <div class="card-body d-flex align-items-center gap-3">
          <div class="stat-icon bg-success bg-opacity-10 text-success"><i class="bi bi-currency-rupee"></i></div>
          <div>
            <div class="fs-4 fw-bold text-dark"><?= CURRENCY ?><?= number_format($month_revenue, 0) ?></div>
            <div class="text-muted small">This Month Revenue <i class="bi bi-arrow-right ms-1"></i></div>
          </div>
        </div>
      </div>
    </a>
  </div>
  <div class="col-6 col-md-3">
    <a href="/pages/products.php?filter=low_stock" class="text-decoration-none">
      <div class="card stat-card shadow-sm h-100">
        <div class="card-body d-flex align-items-center gap-3">
          <div class="stat-icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-exclamation-triangle"></i></div>
          <div>
            <div class="fs-4 fw-bold text-dark"><?= number_format($low_stock_items) ?></div>
            <div class="text-muted small">Low Stock Items <i class="bi bi-arrow-right ms-1"></i></div>
          </div>
        </div>
      </div>
    </a>
  </div>
  <div class="col-6 col-md-3">
    <a href="/pages/stock_report.php" class="text-decoration-none">
      <div class="card stat-card shadow-sm h-100">
        <div class="card-body d-flex align-items-center gap-3">
          <div class="stat-icon bg-info bg-opacity-10 text-info"><i class="bi bi-cash-stack"></i></div>
          <div>
            <div class="fs-4 fw-bold text-dark"><?= CURRENCY ?><?= number_format($total_stock_val, 0) ?></div>
            <div class="text-muted small">Stock Value <i class="bi bi-arrow-right ms-1"></i></div>
          </div>
        </div>
      </div>
    </a>
  </div>
</div>

<!-- ── Today stats ── -->
<div class="row g-3 mb-4">
  <div class="col-md-6 col-lg-3">
    <a href="/pages/sales_report.php?start=<?= date('Y-m-d') ?>&end=<?= date('Y-m-d') ?>" class="text-decoration-none">
      <div class="alert alert-success mb-0 py-2 px-3 d-flex justify-content-between align-items-center">
        <span class="small fw-semibold"><i class="bi bi-sun me-1"></i>Today's Revenue <i class="bi bi-arrow-right ms-1"></i></span>
        <strong><?= CURRENCY ?><?= number_format($today_revenue, 2) ?></strong>
      </div>
    </a>
  </div>
  <div class="col-md-6 col-lg-3">
    <a href="/pages/sales_report.php?start=<?= date('Y-m-d') ?>&end=<?= date('Y-m-d') ?>" class="text-decoration-none">
      <div class="alert alert-info mb-0 py-2 px-3 d-flex justify-content-between align-items-center">
        <span class="small fw-semibold"><i class="bi bi-receipt me-1"></i>Today's Transactions <i class="bi bi-arrow-right ms-1"></i></span>
        <strong><?= $today_txns ?></strong>
      </div>
    </a>
  </div>
  <div class="col-md-6 col-lg-3">
    <a href="/pages/products.php?filter=out_of_stock" class="text-decoration-none">
      <div class="alert alert-danger mb-0 py-2 px-3 d-flex justify-content-between align-items-center">
        <span class="small fw-semibold"><i class="bi bi-x-circle me-1"></i>Out of Stock <i class="bi bi-arrow-right ms-1"></i></span>
        <strong><?= $out_of_stock ?></strong>
      </div>
    </a>
  </div>
  <div class="col-md-6 col-lg-3">
    <div class="alert alert-secondary mb-0 py-2 px-3 d-flex justify-content-between align-items-center">
      <span class="small fw-semibold"><i class="bi bi-arrow-right-circle me-1"></i>Quick Actions</span>
      <a href="/pages/sales.php" class="btn btn-sm btn-success">New Sale</a>
    </div>
  </div>
</div>

<div class="row g-4 mb-4">
  <!-- Revenue Bar + Line Chart -->
  <div class="col-lg-8">
    <div class="card shadow-sm h-100">
      <div class="card-header bg-white border-0 pb-0 d-flex justify-content-between align-items-center">
        <h6 class="fw-bold mb-0"><i class="bi bi-bar-chart me-2 text-primary"></i>Revenue — Last 7 Days</h6>
        <span class="badge bg-primary bg-opacity-10 text-primary small">
          <?= CURRENCY ?><?= number_format(array_sum($chart_revenue), 0) ?> total
        </span>
      </div>
      <div class="card-body">
        <div class="chart-container">
          <canvas id="revenueChart"></canvas>
        </div>
      </div>
    </div>
  </div>

  <!-- Stock by Category Donut -->
  <div class="col-lg-4">
    <div class="card shadow-sm h-100">
      <div class="card-header bg-white border-0 pb-0">
        <h6 class="fw-bold mb-0"><i class="bi bi-pie-chart me-2 text-info"></i>Stock by Category</h6>
      </div>
      <div class="card-body d-flex flex-column align-items-center justify-content-center">
        <div style="position:relative; height:180px; width:180px;">
          <canvas id="stockDonut"></canvas>
        </div>
        <div id="donutLegend" class="mt-3 w-100 px-2" style="font-size:0.8rem; overflow:hidden;"></div>
      </div>
    </div>
  </div>
</div>

<div class="row g-4 mb-4">
  <!-- Top Products -->
  <div class="col-lg-6">
    <div class="card shadow-sm h-100">
      <div class="card-header bg-white border-0 pb-0">
        <h6 class="fw-bold mb-0"><i class="bi bi-trophy me-2 text-warning"></i>Top Selling (This Month)</h6>
      </div>
      <div class="card-body p-0">
        <?php if (empty($top_products)): ?>
        <p class="text-muted small p-3 mb-0">No sales this month yet.</p>
        <?php else: ?>
        <div class="p-3">
          <?php
          $max_qty = max(array_column($top_products, 'qty'));
          foreach ($top_products as $i => $p):
            $pct = $max_qty > 0 ? round(($p['qty'] / $max_qty) * 100) : 0;
            $colors = ['primary','success','info','warning','danger'];
            $color  = $colors[$i % count($colors)];
          ?>
          <div class="mb-3">
            <div class="d-flex justify-content-between mb-1">
              <span class="small fw-semibold"><?= htmlspecialchars($p['product_name']) ?></span>
              <span class="small text-muted"><?= number_format($p['qty']) ?> units &nbsp;|&nbsp; <?= CURRENCY ?><?= number_format($p['revenue'], 0) ?></span>
            </div>
            <div class="progress" style="height:8px;">
              <div class="progress-bar bg-<?= $color ?>" style="width:<?= $pct ?>%"></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Sales Trend mini sparkline -->
  <div class="col-lg-6">
    <div class="card shadow-sm h-100">
      <div class="card-header bg-white border-0 pb-0">
        <h6 class="fw-bold mb-0"><i class="bi bi-graph-up-arrow me-2 text-success"></i>Units Sold — Last 7 Days</h6>
      </div>
      <div class="card-body">
        <div class="chart-container">
          <canvas id="unitsChart"></canvas>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="row g-4">
  <!-- Low Stock Alert -->
  <div class="col-lg-5">
    <div class="card shadow-sm h-100">
      <div class="card-header bg-white border-0 pb-0 d-flex justify-content-between">
        <h6 class="fw-bold mb-0"><i class="bi bi-exclamation-triangle text-warning me-2"></i>Low Stock Alerts</h6>
        <a href="/pages/products.php?filter=low_stock" class="btn btn-sm btn-outline-warning">View All</a>
      </div>
      <div class="card-body p-0">
        <?php if (empty($low_items)): ?>
        <p class="text-success small p-3 mb-0"><i class="bi bi-check-circle me-1"></i>All stock levels are healthy.</p>
        <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm mb-0">
            <tbody>
              <?php foreach ($low_items as $item): ?>
              <tr class="<?= $item['unit'] == 0 ? 'table-danger' : 'table-warning' ?>">
                <td class="small fw-semibold"><?= htmlspecialchars($item['name']) ?></td>
                <td class="small text-muted"><?= htmlspecialchars($item['category'] ?? '—') ?></td>
                <td class="small text-end">
                  <span class="badge <?= $item['unit'] == 0 ? 'bg-danger' : 'bg-warning text-dark' ?>">
                    <?= $item['unit'] ?> left
                  </span>
                </td>
                <td class="small text-end">
                  <a href="/pages/purchase.php?product_id=<?= $item['id'] ?>" class="btn btn-xs btn-sm btn-outline-primary py-0 px-1" style="font-size:0.75rem">Restock</a>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Recent Sales -->
  <div class="col-lg-7">
    <div class="card shadow-sm h-100">
      <div class="card-header bg-white border-0 pb-0 d-flex justify-content-between">
        <h6 class="fw-bold mb-0"><i class="bi bi-clock-history text-success me-2"></i>Recent Sales</h6>
        <a href="/pages/sales_report.php" class="btn btn-sm btn-outline-success">Full Report</a>
      </div>
      <div class="card-body p-0">
        <?php if (empty($recent_sales)): ?>
        <p class="text-muted small p-3 mb-0">No sales yet.</p>
        <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
              <tr>
                <th>Product</th><th>Qty</th><th>Total</th><th>Date</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recent_sales as $s): ?>
              <tr>
                <td class="small"><?= htmlspecialchars($s['product_name']) ?></td>
                <td class="small"><?= $s['quantity'] ?></td>
                <td class="small fw-semibold text-success"><?= CURRENCY ?><?= number_format($s['total_price'], 2) ?></td>
                <td class="small text-muted"><?= date('d M, h:i A', strtotime($s['created_at'])) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php
// Units sold per day for sparkline
$units_rows = db_fetch_all(
    "SELECT DATE(created_at) AS day, SUM(quantity) AS units
     FROM sales
     WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
     GROUP BY DATE(created_at)
     ORDER BY day ASC"
);
$chart_units = [];
for ($i = 6; $i >= 0; $i--) {
    $date  = date('Y-m-d', strtotime("-$i days"));
    $found = 0;
    foreach ($units_rows as $r) {
        if ($r['day'] === $date) { $found = (int)$r['units']; break; }
    }
    $chart_units[] = $found;
}

// Category donut data
$donut_labels = array_column($cat_stock, 'category');
$donut_data   = array_map('intval', array_column($cat_stock, 'total_units'));
?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<script>
// All charts initialised after Chart.js (in footer) is loaded
window.addEventListener('load', function () {

  // ── 1. Revenue Bar + Line combo ──
  const revenueCtx = document.getElementById('revenueChart');
  if (revenueCtx) {
    new Chart(revenueCtx, {
      type: 'bar',
      data: {
        labels: <?= json_encode($chart_labels) ?>,
        datasets: [
          {
            type: 'bar',
            label: 'Revenue',
            data: <?= json_encode($chart_revenue) ?>,
            backgroundColor: 'rgba(13,110,253,0.18)',
            borderColor: 'rgba(13,110,253,0.85)',
            borderWidth: 2,
            borderRadius: 8,
            order: 2,
          },
          {
            type: 'line',
            label: 'Trend',
            data: <?= json_encode($chart_revenue) ?>,
            borderColor: 'rgba(25,135,84,0.9)',
            backgroundColor: 'transparent',
            borderWidth: 2.5,
            pointBackgroundColor: 'rgba(25,135,84,1)',
            pointRadius: 4,
            pointHoverRadius: 6,
            tension: 0.4,
            order: 1,
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: { display: true, position: 'top', labels: { boxWidth: 12, font: { size: 11 } } },
          tooltip: {
            callbacks: {
              label: ctx => ' <?= CURRENCY ?>' + ctx.parsed.y.toLocaleString('en-IN', { minimumFractionDigits: 2 })
            }
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            grid: { color: 'rgba(0,0,0,0.05)' },
            ticks: {
              callback: v => '<?= CURRENCY ?>' + (v >= 1000 ? (v/1000).toFixed(1)+'K' : v),
              font: { size: 11 }
            }
          },
          x: { grid: { display: false }, ticks: { font: { size: 11 } } }
        }
      }
    });
  }

  // ── 2. Stock by Category Donut ──
  const donutCtx = document.getElementById('stockDonut');
  if (donutCtx) {
    const donutColors = [
      '#0d6efd','#198754','#0dcaf0','#ffc107','#dc3545',
      '#6f42c1','#fd7e14','#20c997','#6c757d','#d63384'
    ];
    const labels = <?= json_encode($donut_labels) ?>;
    const data   = <?= json_encode($donut_data) ?>;

    new Chart(donutCtx, {
      type: 'doughnut',
      data: {
        labels: labels,
        datasets: [{
          data: data,
          backgroundColor: donutColors.slice(0, labels.length),
          borderWidth: 2,
          borderColor: '#fff',
          hoverOffset: 8,
        }]
      },
      options: {
        responsive: false,
        cutout: '65%',
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: ctx => ' ' + ctx.label + ': ' + ctx.parsed + ' units'
            }
          }
        }
      }
    });

    // Custom legend
    const legend = document.getElementById('donutLegend');
    if (legend) {
      legend.innerHTML = labels.map((l, i) =>
        `<div class="d-flex align-items-center gap-2 mb-1" style="min-width:0">
          <span style="width:10px;height:10px;border-radius:50%;background:${donutColors[i]};display:inline-block;flex-shrink:0"></span>
          <span class="text-muted text-truncate" style="flex:1;min-width:0">${l}</span>
          <span class="fw-semibold flex-shrink-0">${data[i]} units</span>
        </div>`
      ).join('');
    }
  }

  // ── 3. Units Sold Sparkline ──
  const unitsCtx = document.getElementById('unitsChart');
  if (unitsCtx) {
    new Chart(unitsCtx, {
      type: 'line',
      data: {
        labels: <?= json_encode($chart_labels) ?>,
        datasets: [{
          label: 'Units Sold',
          data: <?= json_encode($chart_units) ?>,
          borderColor: 'rgba(25,135,84,0.9)',
          backgroundColor: 'rgba(25,135,84,0.08)',
          borderWidth: 2.5,
          fill: true,
          tension: 0.4,
          pointBackgroundColor: 'rgba(25,135,84,1)',
          pointRadius: 5,
          pointHoverRadius: 7,
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: { label: ctx => ' ' + ctx.parsed.y + ' units sold' }
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            grid: { color: 'rgba(0,0,0,0.05)' },
            ticks: { font: { size: 11 }, precision: 0 }
          },
          x: { grid: { display: false }, ticks: { font: { size: 11 } } }
        }
      }
    });
  }

});
</script>
