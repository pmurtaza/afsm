<?php
// header.php: Site header with navigation menu
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
?>
<!DOCTYPE html>
<html lang="en" class="h-100">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($page_title ?? 'My App') ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="d-flex flex-column min-vh-100">
  <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
      <a class="navbar-brand" href="dashboard.php">AFSM</a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMenu">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navbarMenu">
        <ul class="navbar-nav me-auto mb-2 mb-lg-0">
          <li class="nav-item"><a class="nav-link" href="dashboard.php">Home</a></li>
          <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['teacher', 'admin'])): ?>
            <li class="nav-item"><a class="nav-link" href="attendance.php">Attendance</a></li>
            <li class="nav-item"><a class="nav-link" href="participation.php">Participation</a></li>
          <?php elseif (isset($_SESSION['role']) && $_SESSION['role'] === 'student'): ?>
            <li class="nav-item"><a class="nav-link" href="register.php">Register</a></li>
          <?php endif; ?>
          <?php if (isset($_SESSION['role'])): ?>
            <?php if (in_array($_SESSION['role'], ['teacher', 'admin'])): ?> <!-- Teachers/Admins can create and manage assignments -->
              <li class="nav-item"><a class="nav-link" href="assignment.php">Manage Assignments</a></li>
            <?php elseif ($_SESSION['role'] === 'student'): ?> <!-- Students can only submit assignments -->
              <li class="nav-item"><a class="nav-link" href="assignment.php">My Assignment</a></li>
            <?php endif; ?>
          <?php endif; ?>
        </ul>
        <?php if (isset($_SESSION['username'])): ?>
          <span class="navbar-text text-white me-3">
            Salaam, <?= htmlspecialchars($_SESSION['username']) ?>
          </span>
          <a href="logout.php" class="btn btn-outline-light">Logout</a>
        <?php endif; ?>
      </div>
    </div>
  </nav>
  <main class="container my-4 flex-fill">