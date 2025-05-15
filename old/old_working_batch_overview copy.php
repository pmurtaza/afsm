<?php
// batch_overview.php
session_start();
require 'mysql_config.php';
// Only teachers and admins
if (!isset($_SESSION['user_id'], $_SESSION['role']) || !in_array($_SESSION['role'], ['teacher','admin'])) {
    header('Location: login.php'); exit;
}
$userId = $_SESSION['user_id'];
$role   = $_SESSION['role'];
$batchId = isset($_GET['batch_id']) ? (int)$_GET['batch_id'] : null;

// Fetch batches for dropdown
if ($role === 'admin') {
    $batches = $mysqli->query("SELECT id,name FROM afsm_batches ORDER BY name");
} else {
    $stmt = $mysqli->prepare(
        "SELECT b.id,b.name
           FROM afsm_batches b
           JOIN afsm_batch_students bs ON b.id=bs.batch_id
          WHERE bs.user_id=? AND bs.role='teacher'
          ORDER BY b.name"
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $batches = $stmt->get_result();
    $stmt->close();
}

include 'header.php';
?>
<div class="container mt-4">
  <h2>Batch Overview</h2>
  <form method="get" class="row gy-2 gx-3 align-items-center mb-4">
    <div class="col-auto">
      <label class="form-label">Batch:</label>
    </div>
    <div class="col-auto">
      <select name="batch_id" class="form-select" onchange="this.form.submit()">
        <option value="">Select Batch</option>
        <?php while ($batches && $b = $batches->fetch_assoc()): ?>
          <option value="<?= $b['id'] ?>" <?= ($b['id'] === $batchId) ? 'selected' : '' ?>>
            <?= htmlspecialchars($b['name']) ?>
          </option>
        <?php endwhile; ?>
      </select>
    </div>
  </form>

<?php if ($batchId):
  // Fetch students
  $stmt = $mysqli->prepare(
    "SELECT u.id,u.name
       FROM afsm_users u
       JOIN afsm_batch_students bs ON u.id=bs.user_id
      WHERE bs.batch_id=? AND bs.role='student'
      ORDER BY u.name"
  );
  $stmt->bind_param('i', $batchId);
  $stmt->execute();
  $students = $stmt->get_result();
  $stmt->close();

  // Count sessions
  $stmt = $mysqli->prepare("SELECT COUNT(*) FROM afsm_sessions WHERE batch_id=?");
  $stmt->bind_param('i', $batchId);
  $stmt->execute(); $stmt->bind_result($totalSessions); $stmt->fetch(); $stmt->close();

  echo '<div class="table-responsive"><table class="table table-bordered align-middle">';
  echo '<thead class="table-light"><tr>';
  echo '<th>Student</th><th>Attendance</th><th>Participation</th><th>Assignment</th><th>Grade</th>';
  echo '</tr></thead><tbody>';

  while ($st = $students->fetch_assoc()) {
    $sid = $st['id'];
    // Attendance count
    $stmt = $mysqli->prepare(
      "SELECT COUNT(*) FROM afsm_attendance a JOIN afsm_sessions s ON a.session_id=s.id
        WHERE s.batch_id=? AND a.user_id=? AND a.status='present'"
    );
    $stmt->bind_param('ii', $batchId, $sid);
    $stmt->execute(); $stmt->bind_result($attPresent); $stmt->fetch(); $stmt->close();
    $attText = "$attPresent / $totalSessions";
    // Participation average
    $stmt = $mysqli->prepare(
      "SELECT ROUND(AVG(p.score),1) FROM afsm_participation p
         JOIN afsm_sessions s ON p.session_id=s.id
        WHERE s.batch_id=? AND p.user_id=?"
    );
    $stmt->bind_param('ii', $batchId, $sid);
    $stmt->execute(); $stmt->bind_result($partAvg); $stmt->fetch(); $stmt->close();
    $partText = $partAvg !== null ? $partAvg : 'N/A';
    // Assignment status & grade
    $stmt = $mysqli->prepare(
      "SELECT sub.status, sub.grade_rubric
         FROM afsm_assignment_submissions sub
         JOIN afsm_assignments a ON sub.assignment_id=a.id
        WHERE a.batch_id=? AND sub.student_id=? LIMIT 1"
    );
    $stmt->bind_param('ii', $batchId, $sid);
    $stmt->execute(); $res = $stmt->get_result();
    $subStatus = 'No'; $grade = '—';
    if ($row = $res->fetch_assoc()) {
      $subStatus = ucfirst($row['status']);
      $rub = json_decode($row['grade_rubric'], true);
      if ($rub) {
        $grade = array_sum(array_column($rub,'given_score')) . '/' . array_sum(array_column($rub,'max_score'));
      }
    }
    $stmt->close();

    echo '<tr>';
    echo '<td>' . htmlspecialchars($st['name']) . '</td>';
    echo '<td class="text-center"><a href="attendance.php?batch_id='.$batchId.'">'.htmlspecialchars($attText).'</a></td>';
    echo '<td class="text-center"><a href="participation.php?batch_id='.$batchId.'">'.htmlspecialchars($partText).'</a></td>';
    echo '<td class="text-center"><a href="assignment.php?batch_id='.$batchId.'&student_id='.$sid.'">'.htmlspecialchars($subStatus).'</a></td>';
    echo '<td class="text-center"><a href="assignment.php?batch_id='.$batchId.'&student_id='.$sid.'">'.htmlspecialchars($grade).'</a></td>';
    echo '</tr>';
  }
  echo '</tbody></table></div>';
endif;
?>
<?php include 'footer.php'; ?>
