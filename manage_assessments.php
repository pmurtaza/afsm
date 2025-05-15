<?php
// manage_assessments.php
require 'mysql_config.php';
// Only admins
if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: dashboard.php');
    exit;
}
$userId = $_SESSION['user_id'];

// Handle create/update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'];
  if ($action === 'create') {
      $sql = "INSERT INTO afsm_assessments (batch_id, type, title, instructions, created_by) VALUES (?,?,?,?,?)";
      $stmt = $mysqli->prepare($sql);
      if (!$stmt) {
          die('Prepare failed (create): ' . $mysqli->error);
      }
      $stmt->bind_param('isssi', $_POST['batch_id'], $_POST['type'], $_POST['title'], $_POST['instructions'], $userId);
  } else {
      $sql = "UPDATE afsm_assessments SET batch_id=?, type=?, title=?, instructions=?, updated_by=? WHERE id=?";
      $stmt = $mysqli->prepare($sql);
      if (!$stmt) {
          die('Prepare failed (update): ' . $mysqli->error);
      }
      $stmt->bind_param('isssii', $_POST['batch_id'], $_POST['type'], $_POST['title'], $_POST['instructions'], $userId, $_POST['id']);
  }
  $stmt->execute();
  $stmt->close();
  header('Location: manage_assessments.php');
  exit;
}

// Handle delete
if (isset($_GET['delete'])) {
    $stmt = $mysqli->prepare("DELETE FROM afsm_assessments WHERE id = ?");
    $stmt->bind_param('i', $_GET['delete']);
    $stmt->execute();
    $stmt->close();
    header('Location: manage_assessments.php');
    exit;
}

// Fetch all assessments into array
$assessments = [];
if ($res = $mysqli->query(
    "SELECT a.id, b.id AS batch_id, b.name AS batch_name, a.type, a.title, a.instructions
     FROM afsm_assessments a
     JOIN afsm_batches b ON a.batch_id = b.id
     ORDER BY a.created_date DESC"
)) {
    while ($row = $res->fetch_assoc()) {
        $assessments[] = $row;
    }
    $res->free();
}

// Fetch batches for modal dropdown
$batches = [];
if ($res = $mysqli->query("SELECT id, name FROM afsm_batches ORDER BY name")) {
    while ($b = $res->fetch_assoc()) {
        $batches[] = $b;
    }
    $res->free();
}

$page_title = 'Manage Assessments';
include 'header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1>Manage Assessments</h1>
  <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#assessmentModal" onclick="openForm()">+ New Assessment</button>
</div>
<table class="table table-striped">
  <thead>
    <tr>
      <th>ID</th>
      <th>Batch</th>
      <th>Type</th>
      <th>Title</th>
      <th>Actions</th>
    </tr>
  </thead>
  <tbody>
  <?php if (empty($assessments)): ?>
    <tr><td colspan="5" class="text-center">No assessments found.</td></tr>
  <?php else: foreach ($assessments as $row): ?>
    <tr>
      <td><?= $row['id'] ?></td>
      <td><?= htmlspecialchars($row['batch_name']) ?></td>
      <td><?= $row['type'] ?></td>
      <td><?= htmlspecialchars($row['title']) ?></td>
      <td>
        <button class="btn btn-sm btn-primary" onclick='openForm(<?= json_encode($row) ?>)'>Edit</button>
        <a href="?delete=<?= $row['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this assessment?')">Delete</a>
      </td>
    </tr>
  <?php endforeach; endif; ?>
  </tbody>
</table>

<!-- Modal -->
<div class="modal fade" id="assessmentModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post">
        <div class="modal-header">
          <h5 class="modal-title">Assessment</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="id" id="assessment-id">
          <input type="hidden" name="action" id="assessment-action">
          <div class="mb-3">
            <label class="form-label">Batch</label>
            <select name="batch_id" id="assessment-batch" class="form-select" required>
              <option value="">Select batch</option>
              <?php foreach ($batches as $b): ?>
                <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Type</label>
            <select name="type" id="assessment-type" class="form-select" required>
              <option value="pre">Pre</option>
              <option value="post">Post</option>
              <option value="final">Final</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Title</label>
            <input type="text" name="title" id="assessment-title" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Instructions</label>
            <textarea name="instructions" id="assessment-instructions" class="form-control" rows="3"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.4.1/dist/js/bootstrap.bundle.min.js"></script>
<script>
function openForm(data = null) {
  const modal = new bootstrap.Modal(document.getElementById('assessmentModal'));
  document.getElementById('assessment-action').value = data ? 'update' : 'create';
  if (data) {
    document.getElementById('assessment-id').value = data.id;
    document.getElementById('assessment-batch').value = data.batch_id;
    document.getElementById('assessment-type').value = data.type;
    document.getElementById('assessment-title').value = data.title;
    document.getElementById('assessment-instructions').value = data.instructions;
  } else {
    ['assessment-id','assessment-batch','assessment-type','assessment-title','assessment-instructions']
      .forEach(id => document.getElementById(id).value = '');
  }
  modal.show();
}
</script>
<?php include 'footer.php'; ?>
