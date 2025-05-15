<?php
require 'config.php';
// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
  <nav class="navbar navbar-expand-lg navbar-light bg-light">
    <div class="container-fluid">
      <a class="navbar-brand" href="#">Dashboard</a>
      <div class="d-flex">
        <span class="navbar-text me-3">Hello, <?= htmlspecialchars($_SESSION['username']) ?></span>
        <a href="logout.php" class="btn btn-outline-danger">Logout</a>
      </div>
    </div>
  </nav>
  <div class="container mt-4">
    <!-- Embed Typeform using iframe -->
    <iframe src="https://form.typeform.com/to/dYqx01so" width="100%" height="600" frameborder="0" allow="fullscreen"></iframe>
  </div>
</body>
</html>