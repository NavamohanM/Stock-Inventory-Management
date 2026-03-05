<?php
require_once __DIR__ . '/../includes/db.php';
$page_title = 'My Profile';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { set_flash('danger','Invalid request.'); header('Location: profile.php'); exit; }

    $name        = trim($_POST['name'] ?? '');
    $current_pwd = $_POST['current_password'] ?? '';
    $new_pwd     = $_POST['new_password'] ?? '';
    $uid         = (int)$_SESSION['user_id'];

    if (!$name) { set_flash('danger','Name cannot be empty.'); header('Location: profile.php'); exit; }

    if ($new_pwd) {
        if (strlen($new_pwd) < 8) { set_flash('danger','New password must be at least 8 characters.'); header('Location: profile.php'); exit; }
        $user = db_fetch_one("SELECT password FROM users WHERE id = ?", "i", [$uid]);
        if (!password_verify($current_pwd, $user['password'])) {
            set_flash('danger','Current password is incorrect.'); header('Location: profile.php'); exit;
        }
        $hash = password_hash($new_pwd, PASSWORD_BCRYPT, ['cost' => 12]);
        db_execute("UPDATE users SET name = ?, password = ? WHERE id = ?", "ssi", [$name, $hash, $uid]);
    } else {
        db_execute("UPDATE users SET name = ? WHERE id = ?", "si", [$name, $uid]);
    }

    $_SESSION['user']['name'] = $name;
    set_flash('success','Profile updated.');
    header('Location: profile.php'); exit;
}

$user = db_fetch_one("SELECT id, name, username, role, created_at FROM users WHERE id = ?", "i", [$_SESSION['user_id']]);
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
  <div class="col-md-6">
    <div class="card shadow-sm">
      <div class="card-header bg-white border-0">
        <h5 class="fw-bold mb-0"><i class="bi bi-person-circle me-2 text-primary"></i>My Profile</h5>
      </div>
      <div class="card-body">
        <div class="alert alert-light border small mb-4">
          <strong>Username:</strong> <?= htmlspecialchars($user['username']) ?> &nbsp;|&nbsp;
          <strong>Role:</strong> <?= ucfirst($user['role']) ?> &nbsp;|&nbsp;
          <strong>Joined:</strong> <?= date('d M Y', strtotime($user['created_at'])) ?>
        </div>
        <form method="post">
          <?= csrf_field() ?>
          <div class="mb-3">
            <label class="form-label fw-semibold">Full Name</label>
            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($user['name']) ?>" required>
          </div>
          <hr class="my-3">
          <p class="text-muted small">Leave password fields blank to keep current password.</p>
          <div class="mb-3">
            <label class="form-label fw-semibold">Current Password</label>
            <input type="password" name="current_password" class="form-control" placeholder="Required to change password">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">New Password</label>
            <input type="password" name="new_password" class="form-control" minlength="8" placeholder="Min 8 characters">
          </div>
          <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save Changes</button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
