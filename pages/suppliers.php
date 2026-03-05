<?php
require_once __DIR__ . '/../includes/db.php';
$page_title = 'Suppliers';

if (!is_admin()) { set_flash('danger','Admin access required.'); header('Location: /dashboard.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { set_flash('danger','Invalid request.'); header('Location: suppliers.php'); exit; }
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name    = trim($_POST['name'] ?? '');
        $contact = trim($_POST['contact'] ?? '');
        $phone   = trim($_POST['phone'] ?? '');
        $email   = trim($_POST['email'] ?? '');
        $address = trim($_POST['address'] ?? '');
        if (!$name) { set_flash('danger','Supplier name required.'); header('Location: suppliers.php'); exit; }
        if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) { set_flash('danger','Invalid email.'); header('Location: suppliers.php'); exit; }
        db_insert(
            "INSERT INTO suppliers (name, contact, phone, email, address) VALUES (?,?,?,?,?)",
            "sssss",
            [$name, $contact ?: null, $phone ?: null, $email ?: null, $address ?: null]
        );
        set_flash('success', "Supplier '$name' added.");
    }
    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        db_execute("UPDATE suppliers SET deleted_at = NOW() WHERE id = ?", "i", [$id]);
        set_flash('success','Supplier removed.');
    }
    header('Location: suppliers.php'); exit;
}

$suppliers = db_fetch_all("SELECT * FROM suppliers WHERE deleted_at IS NULL ORDER BY name");

require_once __DIR__ . '/../includes/header.php';
?>

<div class="row g-4">
  <div class="col-lg-4">
    <div class="card shadow-sm">
      <div class="card-header bg-white border-0"><h5 class="fw-bold mb-0"><i class="bi bi-plus-circle me-2"></i>Add Supplier</h5></div>
      <div class="card-body">
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="add">
          <div class="mb-3">
            <label class="form-label fw-semibold">Company Name <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control" required placeholder="Supplier company name">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Contact Person</label>
            <input type="text" name="contact" class="form-control" placeholder="Person name">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Phone</label>
            <input type="text" name="phone" class="form-control" placeholder="+91 XXXXX XXXXX">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Email</label>
            <input type="email" name="email" class="form-control" placeholder="supplier@email.com">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Address</label>
            <textarea name="address" class="form-control" rows="2" placeholder="Supplier address..."></textarea>
          </div>
          <button type="submit" class="btn btn-primary w-100">Add Supplier</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="card shadow-sm">
      <div class="card-header bg-white border-0"><h5 class="fw-bold mb-0"><i class="bi bi-truck me-2 text-primary"></i>All Suppliers</h5></div>
      <div class="card-body p-0">
        <table class="table table-hover mb-0">
          <thead class="table-light">
            <tr><th>Name</th><th>Contact</th><th>Phone</th><th>Email</th><th></th></tr>
          </thead>
          <tbody>
            <?php if (empty($suppliers)): ?>
            <tr><td colspan="5" class="text-center text-muted py-4">No suppliers added.</td></tr>
            <?php else: foreach ($suppliers as $s): ?>
            <tr>
              <td class="fw-semibold small"><?= htmlspecialchars($s['name']) ?></td>
              <td class="small text-muted"><?= htmlspecialchars($s['contact'] ?? '—') ?></td>
              <td class="small"><?= htmlspecialchars($s['phone'] ?? '—') ?></td>
              <td class="small"><?= htmlspecialchars($s['email'] ?? '—') ?></td>
              <td>
                <form method="post" class="d-inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= $s['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2"
                    data-confirm="Remove supplier '<?= htmlspecialchars($s['name'], ENT_QUOTES) ?>'?">
                    <i class="bi bi-trash"></i>
                  </button>
                </form>
              </td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
