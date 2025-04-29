<?php
require 'mysql_config.php';
session_start();
if ($_SESSION['role']!=='admin') header('Location: dashboard.php');

// CRUD operations: list rubrics, create/edit/delete
// rubrics table has JSON `criteria`
// Use a simple form to enter criteria as JSON or build a UI to add criteria rows
?>
<?php include 'header.php'; ?>
<div class="card">
  <div class="card-body">
    <h3>Rubric Master</h3>
    <!-- list existing rubrics -->
    <!-- form to add/edit rubric name & criteria -->
  </div>
</div>
<?php include 'footer.php'; ?>