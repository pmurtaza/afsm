<?php
// student_assessments.php
require 'mysql_config.php';
// Only students
if (empty($_SESSION['role']) || !in_array($_SESSION['role'], ['student','admin'])) {
    header('Location: dashboard.php'); exit;
}
$studentId = $_SESSION['user_id'];

// Fetch batches student belongs to
$batches = [];
$stmt = $mysqli->prepare(
    "SELECT b.id, b.name
     FROM afsm_batches b
     JOIN afsm_batch_students bs ON b.id = bs.batch_id
    WHERE bs.user_id = ? AND bs.role = 'student'"
);
$stmt->bind_param('i', $studentId);
$stmt->execute();
$batches = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch assessments (pre, post, final) for those batches
$assessments = [];
if (!empty($batches)) {
    $batchIds = array_column($batches, 'id');
    $in  = implode(',', array_fill(0, count($batchIds), '?'));
    $types = ['pre','post','final'];
    $sql = "SELECT id, batch_id, type, title, instructions
            FROM afsm_assessments
           WHERE batch_id IN ($in)
             AND type IN ('pre','post','final')
           ORDER BY FIELD(type,'pre','post','final'), created_date DESC";
    $stmt = $mysqli->prepare($sql);
    // bind dynamic params
    $typesAndIds = array_merge($batchIds);
    $typesAndIds = array_values($batchIds);
    $stmt->bind_param(str_repeat('i', count($batchIds)), ...$batchIds);
    $stmt->execute();
    $assessments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$page_title = 'Your Assessments';
include 'header.php';
?>
<h1>Your Assessments</h1>
<?php if (empty($batches)): ?>
  <div class="alert alert-info">You are not enrolled in any batches.</div>
<?php elseif (empty($assessments)): ?>
  <div class="alert alert-info">No assessments available yet.</div>
<?php else: ?>
  <div class="row row-cols-1 row-cols-md-2 g-4">
    <?php foreach ($assessments as $a): ?>
      <div class="col">
        <div class="card h-100">
          <div class="card-body">
            <h5 class="card-title"><?= htmlspecialchars($a['title']) ?> <small class="text-muted">(<?= ucfirst($a['type']) ?>)</small></h5>
            <p class="card-text"><?= nl2br(htmlspecialchars($a['instructions'])) ?></p>
            <a href="take_assessment.php?assessment_id=<?= $a['id'] ?>" class="btn btn-primary">Take <?= ucfirst($a['type']) ?></a>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php include 'footer.php'; ?>
