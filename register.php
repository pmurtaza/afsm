<?php
require 'mysql_config.php';

// Require login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$page_title = 'AFSM - Registration';
include 'header.php';
?>

<div class="card">
  <div class="card-body">
    <h4 class="card-title mb-3">Welcome, <?= htmlspecialchars($_SESSION['username']) ?>!</h4>
    <!-- Embed Typeform using iframe -->
    <iframe src="https://form.typeform.com/to/dYqx01so" width="100%" height="600" frameborder="0" allowfullscreen>
    </iframe>
  </div>
</div>

<?php include 'footer.php'; ?>
