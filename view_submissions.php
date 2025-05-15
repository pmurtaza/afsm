<?php
// view_submissions.php
$title="View Submissions";
require 'mysql_config.php';
if (empty($_SESSION['role']) || !in_array($_SESSION['role'], ['teacher','admin'])) {
    header('Location: dashboard.php');
    exit;
}

// Fetch assessments dropdown
$assessments = [];
if ($_SESSION['role'] === 'admin') {
    $res = $mysqli->query("SELECT id, title FROM afsm_assessments ORDER BY created_date DESC");
} else {
    $uid = $_SESSION['user_id'];
    $res = $mysqli->query(
        "SELECT DISTINCT a.id, a.title
         FROM afsm_assessments a
         JOIN afsm_batches b ON a.batch_id=b.id
         JOIN afsm_batch_students bs ON b.id=bs.batch_id
         WHERE bs.user_id=$uid AND bs.role='teacher'
         ORDER BY a.created_date DESC"
    );
}
while ($a = $res->fetch_assoc()) {
    $assessments[] = $a;
}
$res->free();

// Selected assessment
$selected = isset($_GET['assessment_id']) ? (int)$_GET['assessment_id'] : null;

// Fetch submissions for selected assessment
$subs = [];
if ($selected) {
    $stmt = $mysqli->prepare(
        "SELECT sub.student_id, u.name AS student_name, sub.submitted_date,
                sub.status, sub.graded_by, sub.grade_date
         FROM afsm_assignment_submissions sub
         JOIN afsm_users u ON sub.student_id = u.id
         WHERE sub.assignment_id = ?
         ORDER BY sub.submitted_date DESC"
    );
    if (!$stmt) {
        die('Prepare failed: ' . $mysqli->error);
    }
    $stmt->bind_param('i', $selected);
    $stmt->execute();
    $subs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$page_title = 'View Submissions';
include 'header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h1>View Submissions</h1>
  <?php if ($selected): ?>
    <a href="grade_assignment.php?assessment_id=<?= $selected ?>" class="btn btn-primary">
      Grade This Assessment
    </a>
  <?php endif; ?>
</div>

<div class="mb-3">
  <label class="form-label">Select Assessment:</label>
  <select class="form-select" onchange="location.href='?assessment_id='+this.value">
    <option value="">-- Choose Assessment --</option>
    <?php foreach ($assessments as $a): ?>
      <option value="<?= $a['id'] ?>" <?= $a['id']==$selected?'selected':'' ?>>
        <?= htmlspecialchars($a['title']) ?>
      </option>
    <?php endforeach; ?>
  </select>
</div>

<?php if (!$selected): ?>
  <div class="alert alert-info">Please select an assessment to view submissions.</div>
<?php else: ?>
  <table class="table table-hover">
    <thead class="table-light">
      <tr>
        <th>Student ID</th>
        <th>Student Name</th>
        <th>Submitted At</th>
        <th>Status</th>
        <th>Graded By</th>
        <th>Grade Date</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($subs)): ?>
        <tr><td colspan="7" class="text-center">No submissions yet.</td></tr>
      <?php else: foreach ($subs as $sub): ?>
        <tr>
          <td><?= $sub['student_id'] ?></td>
          <td><?= htmlspecialchars($sub['student_name']) ?></td>
          <td><?= $sub['submitted_date'] ?></td>
          <td><?= ucfirst($sub['status']) ?></td>
          <td>
            <?php if ($sub['graded_by']):
              $gstmt = $mysqli->prepare("SELECT name FROM afsm_users WHERE id = ?");
              $gstmt->bind_param('i', $sub['graded_by']);
              $gstmt->execute();
              $grader = $gstmt->get_result()->fetch_assoc()['name'];
              $gstmt->close();
              echo htmlspecialchars($grader);
            else:
              echo '-';
            endif; ?>
          </td>
          <td><?= $sub['grade_date'] ?? '-' ?></td>
          <td>
            <a href="grade_assessment.php?assessment_id=<?= $selected ?>&student_id=<?= $sub['student_id'] ?>" class="btn btn-sm btn-primary">Review & Grade</a>
            <a href="view_submission_detail.php?assessment_id=<?= $selected ?>&student_id=<?= $sub['student_id'] ?>" class="btn btn-sm btn-secondary">View Details</a>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
<?php endif; ?>

<?php include 'footer.php'; ?>
