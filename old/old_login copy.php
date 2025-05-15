<?php
// require 'config.php';
// require 'local_config.php';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    // print_r($_POST, '$_POST');
    // Fetch user from DB
    $stmt = $pdo->prepare('SELECT id, name, password FROM afsm_users WHERE email = ?');
    // $stmt = $pdo->prepare('SELECT * FROM users');
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    // print_r($user, '$user');
    // print_r($password, '$password');
    if ($user && ($password === $user['password'])) {
        // Correct login
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['name'];
        $_SESSION['email'] = $username;
        header('Location: dashboard.php');
        exit;
    } else {
        // Invalid credentials
        $error = 'Invalid username or password.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background-color: #f8f9fa; }
    .card { max-width: 400px; margin: 100px auto; padding: 20px; }
  </style>
</head>
<body>
  <div class="card shadow-sm">
    <h4 class="card-title mb-4 text-center">User Login</h4>
    <?php if($error): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($error);?></div>
    <?php endif; ?>
    <form method="post" action="">
      <div class="mb-3">
        <label for="username" class="form-label">Username</label>
        <input type="text" class="form-control" id="username" name="username" required>
      </div>
      <div class="mb-3">
        <label for="password" class="form-label">Password</label>
        <input type="password" class="form-control" id="password" name="password" required>
      </div>
      <button type="submit" class="btn btn-primary w-100">Login</button>
    </form>
  </div>
</body>
</html>

