<?php
require_once __DIR__ . '/../includes/db.php';
$page_title = 'User Management';

if (!is_admin()) { set_flash('danger','Admin access required.'); header('Location: /dashboard.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { set_flash('danger','Invalid request.'); header('Location: users.php'); exit; }
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name     = trim($_POST['name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $role     = in_array($_POST['role'], ['admin','staff']) ? $_POST['role'] : 'staff';

        if (!$name || !$username || !$password) { set_flash('danger','All fields are required.'); header('Location: users.php'); exit; }
        if (strlen($password) < 8) { set_flash('danger','Password must be at least 8 characters.'); header('Location: users.php'); exit; }
        $exists = db_fetch_one("SELECT id FROM users WHERE username = ?", "s", [$username]);
        if ($exists) { set_flash('danger',"Username '$username' already exists."); header('Location: users.php'); exit; }

        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        db_insert("INSERT INTO users (name, username, password, role) VALUES (?,?,?,?)", "ssss", [$name, $username, $hash, $role]);
        set_flash('success', "User '$name' created.");
    }

    if ($action === 'toggle') {
        $id = (int)$_POST['id'];
        if ($id == ($_SESSION['user_id'] ?? 0)) { set_flash('danger','Cannot deactivate yourself.'); header('Location: users.php'); exit; }
        db_execute("UPDATE users SET is_active = NOT is_active WHERE id = ?", "i", [$id]);
        set_flash('success','User status updated.');
    }

    if ($action === 'reset_password') {
        $id       = (int)$_POST['id'];
        $password = $_POST['new_password'] ?? '';
        if (strlen($password) < 8) { set_flash('danger','Password must be at least 8 characters.'); header('Location: users.php'); exit; }
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        db_execute("UPDATE users SET password = ? WHERE id = ?", "si", [$hash, $id]);
        set_flash('success','Password updated.');
    }

    header('Location: users.php'); exit;
}

$users = db_fetch_all("SELECT id, name, username, role, is_active, created_at FROM users ORDER BY created_at DESC");

require_once __DIR__ . '/../includes/header.php';
?>

<div class="row g-4">
  <div class="col-lg-4">
    <div class="card shadow-sm">
      <div class="card-header bg-white border-0"><h5 class="fw-bold mb-0"><i class="bi bi-person-plus me-2"></i>Add User</h5></div>
      <div class="card-body">
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="add">
          <div class="mb-3">
            <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control" required placeholder="Full name">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Username <span class="text-danger">*</span></label>
            <input type="text" name="username" class="form-control" required placeholder="Login username">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Password <span class="text-danger">*</span></label>
            <input type="password" name="password" class="form-control" required minlength="8" placeholder="Min 8 characters">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Role</label>
            <select name="role" class="form-select">
              <option value="staff">Staff</option>
              <option value="admin">Admin</option>
            </select>
          </div>
          <button type="submit" class="btn btn-primary w-100">Create User</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="card shadow-sm">
      <div class="card-header bg-white border-0"><h5 class="fw-bold mb-0"><i class="bi bi-people me-2 text-primary"></i>All Users</h5></div>
      <div class="card-body p-0">
        <table class="table table-hover mb-0">
          <thead class="table-light">
            <tr><th>Name</th><th>Username</th><th>Role</th><th>Status</th><th class="text-end">Actions</th></tr>
          </thead>
          <tbody>
            <?php foreach ($users as $u): ?>
            <tr>
              <td class="fw-semibold small"><?= htmlspecialchars($u['name']) ?></td>
              <td class="small"><code><?= htmlspecialchars($u['username']) ?></code></td>
              <td><span class="badge bg-<?= $u['role'] === 'admin' ? 'danger' : 'secondary' ?>"><?= ucfirst($u['role']) ?></span></td>
              <td><span class="badge bg-<?= $u['is_active'] ? 'success' : 'secondary' ?>"><?= $u['is_active'] ? 'Active' : 'Inactive' ?></span></td>
              <td class="text-end">
                <div class="d-flex justify-content-end gap-1">
                  <!-- Toggle active -->
                  <form method="post" class="d-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="id" value="<?= $u['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline-<?= $u['is_active'] ? 'warning' : 'success' ?> py-0 px-2"
                      <?= $u['id'] == ($_SESSION['user_id'] ?? 0) ? 'disabled' : '' ?>
                      title="<?= $u['is_active'] ? 'Deactivate' : 'Activate' ?>">
                      <i class="bi bi-<?= $u['is_active'] ? 'person-x' : 'person-check' ?>"></i>
                    </button>
                  </form>
                  <!-- Reset password -->
                  <button class="btn btn-sm btn-outline-secondary py-0 px-2"
                    data-bs-toggle="modal" data-bs-target="#resetPwdModal"
                    data-id="<?= $u['id'] ?>" data-name="<?= htmlspecialchars($u['name'], ENT_QUOTES) ?>"
                    title="Reset Password"><i class="bi bi-key"></i></button>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Reset Password Modal -->
<div class="modal fade" id="resetPwdModal" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <form method="post" class="modal-content">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="reset_password">
      <input type="hidden" name="id" id="resetUserId">
      <div class="modal-header">
        <h6 class="modal-title fw-bold">Reset Password — <span id="resetUserName"></span></h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <label class="form-label fw-semibold">New Password</label>
        <input type="password" name="new_password" class="form-control" minlength="8" required placeholder="Min 8 characters">
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary btn-sm">Update Password</button>
      </div>
    </form>
  </div>
</div>

<script>
document.getElementById('resetPwdModal').addEventListener('show.bs.modal', function(e) {
  const btn = e.relatedTarget;
  document.getElementById('resetUserId').value = btn.dataset.id;
  document.getElementById('resetUserName').textContent = btn.dataset.name;
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
