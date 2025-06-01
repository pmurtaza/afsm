<?php
// view_submissions.php
$title="View Submissions";
require 'mysql_config.php';

// Only teachers & admins
if (empty($_SESSION['role']) || !in_array($_SESSION['role'], ['teacher', 'admin'])) {
  header('Location: dashboard.php');
  exit;
}

// Build assessments list for dropdown
$assessments = [];
if ($_SESSION['role'] === 'admin') {
  $res = $mysqli->query("SELECT id,title FROM afsm_assessments ORDER BY created_date DESC");
} else {
  $uid = (int)$_SESSION['user_id'];
  $res = $mysqli->query("
      SELECT DISTINCT a.id,a.title
        FROM afsm_assessments a
        JOIN afsm_batches b ON a.batch_id=b.id
        JOIN afsm_batch_students bs ON b.id=bs.batch_id
       WHERE bs.user_id={$uid} AND bs.role='teacher'
       ORDER BY a.created_date DESC
    ");
}
while ($a = $res->fetch_assoc()) {
  $assessments[] = $a;
}
$res->free();

// Picked assessment?
$selected = isset($_GET['assessment_id']) ? (int)$_GET['assessment_id'] : 0;

// Fetch only latest submission per student + total marks
$subs = [];
if ($selected) {
  $stmt = $mysqli->prepare("
      SELECT 
        sub.id            AS submission_id,
        sub.student_id,
        u.name            AS student_name,
        sub.submitted_date,
        sub.status,
        COALESCE(SUM(r.obtained_marks),0) AS total_marks
      FROM afsm_assessment_submissions sub
      LEFT JOIN afsm_responses        r ON sub.id = r.submission_id
      LEFT JOIN afsm_users            u ON sub.student_id = u.id
     WHERE sub.assessment_id = ?
     GROUP BY sub.id, sub.student_id, sub.submitted_date, sub.status
     ORDER BY sub.submitted_date DESC
    ");
  $stmt->bind_param('i', $selected);
  $stmt->execute();
  $subs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();
}

$page_title = 'View Submissions';
include 'header.php';
?>
<div class="container mt-4" style="max-width: 900px;">

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1>View Submissions</h1>
    <!-- <?php if ($selected): ?>
    <a href="grade_assessment.php?assessment_id=<?= $selected ?>" class="btn btn-primary">
      Grade This Assessment
    </a>
  <?php endif; ?> -->
  </div>
  <?php if (isset($_GET['graded']) && $_GET['graded'] == 1): ?>
    <div class="alert alert-success">
      Grades have been saved successfully!
    </div>
  <?php endif; ?>

  <div class="mb-3">
    <label class="form-label">Select Assessment:</label>
    <select class="form-select" onchange="location.href='?assessment_id='+this.value">
      <option value="">-- Choose Assessment --</option>
      <?php foreach ($assessments as $a): ?>
        <option value="<?= $a['id'] ?>" <?= $a['id'] == $selected ? 'selected' : '' ?>>
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
          <th>Marks</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($subs)): ?>
          <tr>
            <td colspan="6" class="text-center">No submissions yet.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($subs as $sub): ?>
            <tr>
              <td><?= $sub['student_id'] ?></td>
              <td><?= htmlspecialchars($sub['student_name']) ?></td>
              <td><?= $sub['submitted_date'] ?></td>
              <td><?= ucfirst($sub['status']) ?></td>
              <td><?= $sub['total_marks'] ?></td>
              <td>
                <!-- Correctly pass both IDs here: -->
                <a href="grade_assessment.php?assessment_id=<?= $selected ?>&student_id=<?= $sub['student_id'] ?>"
                  class="btn btn-sm btn-primary">Review & Grade</a>
                <a href="view_submission_detail.php?submission_id=<?= $sub['submission_id'] ?>"
                  class="btn btn-sm btn-secondary">View Details</a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php include 'footer.php'; ?>