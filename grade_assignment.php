<?php
// grade_assignment.php
session_start();
require 'mysql_config.php';
// Only teachers/admins
if (empty($_SESSION['user_id']) || empty($_SESSION['role']) || !in_array($_SESSION['role'], ['teacher', 'admin'])) {
  header('Location: login.php');
  exit;
}
$userId    = $_SESSION['user_id'];
$role      = $_SESSION['role'];
$batchId   = isset($_GET['batch_id']) ? (int)$_GET['batch_id'] : null;
$studentId = isset($_GET['student_id']) ? (int)$_GET['student_id'] : null;

// 1) Fetch batches
$batchesArr = [];
if ($role === 'admin') {
  $res = $mysqli->query("SELECT id,name FROM afsm_batches ORDER BY name");
  while ($row = $res->fetch_assoc()) $batchesArr[] = $row;
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
  $res = $stmt->get_result();
  while ($row = $res->fetch_assoc()) $batchesArr[] = $row;
  $stmt->close();
}
// 2) Fetch students
$studentsArr = [];
if ($batchId) {
  $stmt = $mysqli->prepare(
    "SELECT u.id,u.name
           FROM afsm_users u
           JOIN afsm_batch_students bs ON u.id=bs.user_id
          WHERE bs.batch_id=? AND bs.role='student'
          ORDER BY u.name"
  );
  $stmt->bind_param('i', $batchId);
  $stmt->execute();
  $res = $stmt->get_result();
  while ($row = $res->fetch_assoc()) $studentsArr[] = $row;
  $stmt->close();
}
include 'header.php';
?>
<div class="container mt-4">
  <h2>Grade Assignment</h2>
  <form method="get" class="row g-3 mb-4 align-items-center">
    <div class="col-auto"><label class="form-label">Batch:</label></div>
    <div class="col-auto">
      <select name="batch_id" class="form-select" onchange="this.form.submit()">
        <option value="">Select Batch</option>
        <?php foreach ($batchesArr as $b): ?>
          <option value="<?= $b['id'] ?>" <?= $b['id'] == $batchId ? 'selected' : '' ?>><?= htmlspecialchars($b['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-auto"><label class="form-label">Student:</label></div>
    <div class="col-auto">
      <select name="student_id" class="form-select" onchange="this.form.submit()">
        <option value="">Select Student</option>
        <?php foreach ($studentsArr as $s): ?>
          <option value="<?= $s['id'] ?>" <?= $s['id'] == $studentId ? 'selected' : '' ?>><?= htmlspecialchars($s['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </form>

  <?php if (!$batchId || !$studentId): ?>
    <div class="alert alert-info">Please select both a batch and a student to grade.</div>
  <?php else:
    // get assignment
    $stmt = $mysqli->prepare("SELECT id FROM afsm_assignments WHERE batch_id=? LIMIT 1");
    $stmt->bind_param('i', $batchId);
    $stmt->execute();
    $stmt->bind_result($assignmentId);
    if (!$stmt->fetch()) {
      echo '<div class="alert alert-warning">No assignment for this batch.</div>';
      include 'footer.php';
      exit;
    }
    $stmt->close();
    // check submission
    $stmt = $mysqli->prepare("SELECT 1 FROM afsm_assignment_submissions WHERE assignment_id=? AND student_id=?");
    $stmt->bind_param('ii', $assignmentId, $studentId);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows === 0) {
      echo '<div class="alert alert-warning">No submission found.</div>';
      include 'footer.php';
      exit;
    }
    $stmt->close();
    // load rubric
    $stmt = $mysqli->prepare(
      "SELECT id,criterion_text,level1,level2,level3,max_score
         FROM afsm_rubric_items WHERE batch_id=? ORDER BY id"
    );
    $stmt->bind_param('i', $batchId);
    $stmt->execute();
    $rubricItems = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    // load existing scores
    $existing = [];
    if ($rubricItems) {
      $stmt = $mysqli->prepare(
        "SELECT rubric_item_id,score FROM afsm_submission_scores
             WHERE assignment_id=? AND student_id=?"
      );
      $stmt->bind_param('ii', $assignmentId, $studentId);
      $stmt->execute();
      foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $existing[$row['rubric_item_id']] = $row['score'];
      }
      $stmt->close();
    }
    // handle POST
    $errors = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      // validate
      foreach ($rubricItems as $item) {
        $rid = $item['id'];
        $sc  = isset($_POST['scores'][$rid]) ? (int)$_POST['scores'][$rid] : 0;
        if ($sc < 0 || $sc > $item['max_score']) {
          $errors[] = "Score for '" . htmlspecialchars($item['criterion_text']) . "' must be between 0 and {$item['max_score']}";
        }
      }
      if (empty($errors)) {
        // upsert
        $ins = $mysqli->prepare(
          "INSERT INTO afsm_submission_scores (assignment_id,student_id,rubric_item_id,score)
                 VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE score=VALUES(score)"
        );
        foreach ($rubricItems as $item) {
          $rid = $item['id'];
          $sc  = (int)$_POST['scores'][$rid];
          $ins->bind_param('iiii', $assignmentId, $studentId, $rid, $sc);
          $ins->execute();
          $existing[$rid] = $sc;
        }
        $ins->close();
        // mark graded
        $stmt = $mysqli->prepare(
          "UPDATE afsm_assignment_submissions SET graded_by=?,grade_date=NOW() WHERE assignment_id=? AND student_id=?"
        );
        $stmt->bind_param('iii', $userId, $assignmentId, $studentId);
        $stmt->execute();
        $stmt->close();
        echo '<div class="alert alert-success">Grades saved.</div>';
      } else {
        echo '<div class="alert alert-danger"><ul><li>' . implode('</li><li>', $errors) . '</li></ul></div>';
      }
    }
    // render form
  ?>
    <form method="post">
      <div class="table-responsive">
        <table class="table table-bordered align-middle">
          <thead class="table-light">
            <tr>
              <th>No</th>
              <th>Criteria</th>
              <th>1-Fair</th>
              <th>2-Good</th>
              <th>3-Excellent</th>
              <th>Score</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rubricItems as $i => $item): ?>
              <tr>
                <td><?= $i + 1 ?>.</td>
                <td><?= htmlspecialchars($item['criterion_text']) ?></td>
                <td><?= htmlspecialchars($item['level1']) ?></td>
                <td><?= htmlspecialchars($item['level2']) ?></td>
                <td><?= htmlspecialchars($item['level3']) ?></td>
                <td>
                  <input type="number"
                    name="scores[<?= $item['id'] ?>]"
                    min="0" max="<?= $item['max_score'] ?>"
                    required
                    class="form-control"
                    value="<?= htmlspecialchars($existing[$item['id']] ?? '') ?>"
                    oninvalid="this.setCustomValidity('Please enter a score between 0 and <?= $item['max_score'] ?>')"
                    oninput="this.setCustomValidity('')">
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <button type="submit" class="btn btn-primary">Save Grades</button>
      </div>
    </form>
  <?php endif; ?>
</div>
<?php include 'footer.php'; ?>