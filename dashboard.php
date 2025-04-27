<?php
require 'mysql_config.php';

// Require login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$page_title = 'Dashboard';
include 'header.php';
?>

<div class="card">
  <div class="card-body">
    <h4 class="card-title mb-3">Welcome, <?= htmlspecialchars($_SESSION['username']) ?>!</h4>
  </div>
</div>

<?php include 'footer.php'; ?>
