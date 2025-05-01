<?php
// https://chatgpt.com/c/6808c8e5-489c-8008-b9d5-880cf5edeef2
// mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require 'mysql_config.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
  header('Location: dashboard.php');
  exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = trim($_POST['username']);
  $password = trim($_POST['password']);

  // Prepare statement
  // $sql = "SELECT id, name AS username, password, role FROM afsm_users WHERE email = ?";
  $sql = "SELECT id, name AS username, password, role
    FROM afsm_users
   WHERE mumin_its = ? OR email = ?";
  // error_log("DEBUG SQL: $sql, PARAMS: [$username,$username]");
  $stmt = $mysqli->prepare($sql);
  if (!$stmt) {
    die("Prepare failed: ({$mysqli->errno}) {$mysqli->error}");
  }
  // Bind the same input to both placeholders
  if (!$stmt->bind_param('ss', $username, $username)) {
    die("Bind failed: ({$stmt->errno}) {$stmt->error}");
  }
  // Execute and check
  if (!$stmt->execute()) {
    die("Execute failed: ({$stmt->errno}) {$stmt->error}");
  }

  $res  = $stmt->get_result();
  $user = $res->fetch_assoc();
  $stmt->close();

  // Verify credentials
  if (!empty($user) && $user['password'] === $password) {
    $_SESSION['user_id']  = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role']     = $user['role'];
    header('Location: dashboard.php');
    exit;
  } else {
    $error = "Invalid username($username) or password($password).";
  }
}

$page_title = 'Login';
include 'header.php';
?>

<div class="card mx-auto shadow-sm" style="max-width: 400px; margin-top: 100px;">
  <div class="card-body">
    <h4 class="card-title text-center mb-4">User Login</h4>
    <?php if ($error): ?>
      <!-- <div class="alert alert-danger"><?= print_r($password, "password") ?></div> -->
      <!-- <div class="alert alert-danger"><?= print_r($res, "result") ?></div> -->
      <!-- <div class="alert alert-danger"><?= print_r($res->fetch_assoc(), "fetch_assoc") ?></div> -->
      <!-- <div class="alert alert-danger"><?= print_r($user, "user") ?></div> -->
      <!-- <div class="alert alert-danger"><?= print_r($_POST, "_POST") ?></div> -->
      <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="post" action="">
      <div class="mb-3">
        <label for="username" class="form-label">Username(Email or ITS)</label>
        <input type="text" class="form-control" id="username" name="username" required>
      </div>
      <div class="mb-3">
        <label for="password" class="form-label">Password</label>
        <input type="password" class="form-control" id="password" name="password" required>
      </div>
      <button type="submit" class="btn btn-primary w-100">Login</button>
    </form>
  </div>
</div>

<?php include 'footer.php'; ?>