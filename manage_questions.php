<?php
// manage_questions.php
require 'mysql_config.php';
// Only teachers and admins

if (empty($_SESSION['role']) || !in_array($_SESSION['role'], ['teacher', 'admin'])) {
  header('Location: dashboard.php');
  exit;
}
$userId = $_SESSION['user_id'];

// Handle create/update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'];
  $assessment_id       = $_POST['assessment_id'];
  $type                = $_POST['type'];
  $question_text       = $_POST['question_text'];
  $weight              = $_POST['weight'];
  $allow_multiple      = $_POST['allow_multiple'];
  $allowed_file_types  = $_POST['allowed_file_types'] ?? null;
  $max_file_size_mb    = $_POST['max_file_size_mb'] ?? null;
  $max_file_count      = $_POST['max_file_count'] ?? null;
  if ($action === 'create') {
    $sql = "INSERT INTO afsm_questions 
              (assessment_id, type, question_text, weight, allow_multiple, created_by, allowed_file_types, max_file_size_mb, max_file_count)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
      die('Prepare failed (create): ' . $mysqli->error);
    }
    $stmt->bind_param(
      'issiiiisi',
      $assessment_id,
      $type,
      $question_text,
      $weight,
      $allow_multiple,
      $userId,
      $allowed_file_types,
      $max_file_size_mb,
      $max_file_count
    );
  } else {
    $sql = "UPDATE afsm_questions SET 
              assessment_id=?, type=?, question_text=?, weight=?, allow_multiple=?, 
              allowed_file_types=?, max_file_size_mb=?, max_file_count=?
            WHERE id=?";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
      die('Prepare failed (update): ' . $mysqli->error);
    }
    $id = $_POST['id'];
    $stmt->bind_param(
      'issiiiisii',
      $assessment_id,
      $type,
      $question_text,
      $weight,
      $allow_multiple,
      $allowed_file_types,
      $max_file_size_mb,
      $max_file_count,
      $id
    );
  }
  $stmt->execute();
  $stmt->close();
  header('Location: manage_questions.php');
  exit;
}

// Handle delete
if (isset($_GET['delete'])) {
  $stmt = $mysqli->prepare("DELETE FROM afsm_questions WHERE id=?");
  $stmt->bind_param('i', $_GET['delete']);
  $stmt->execute();
  $stmt->close();
  header('Location: manage_questions.php');
  exit;
}

// Fetch assessments for dropdown
$assessments = [];
if ($res = $mysqli->query("SELECT id, title FROM afsm_assessments ORDER BY created_date DESC")) {
  while ($a = $res->fetch_assoc()) {
    $assessments[] = $a;
  }
  $res->free();
}

// Fetch all questions
$questions = [];
$sql = "SELECT q.id, q.assessment_id, a.title AS assessment_title, q.type, q.question_text, q.weight, q.allow_multiple, 
               q.allowed_file_types, q.max_file_size_mb, q.max_file_count
          FROM afsm_questions q
          JOIN afsm_assessments a ON q.assessment_id = a.id
         ORDER BY q.created_date DESC";
if ($res = $mysqli->query($sql)) {
  while ($row = $res->fetch_assoc()) {
    $questions[] = $row;
  }
  $res->free();
}

$page_title = 'Manage Questions';
include 'header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1>Manage Questions</h1>
  <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#questionModal" onclick="openForm()">+ New Question</button>
</div>
<table class="table table-striped">
  <thead>
    <tr>
      <th>ID</th>
      <th>Assessment</th>
      <th>Type</th>
      <th>Question</th>
      <th>Weight</th>
      <th>Multiple?</th>
      <th>Allowed File Types</th>
      <th>Max File Size (MB)</th>
      <th>Max File Count</th>
      <th>Actions</th>
    </tr>
  </thead>
  <tbody>
    <?php if (empty($questions)): ?>
      <tr>
        <td colspan="10" class="text-center">No questions found.</td>
      </tr>
      <?php else: foreach ($questions as $q): ?>
        <tr>
          <td><?= $q['id'] ?></td>
          <td><?= htmlspecialchars($q['assessment_title']) ?></td>
          <td><?= htmlspecialchars($q['type']) ?></td>
          <td><?= htmlspecialchars($q['question_text']) ?></td>
          <td><?= $q['weight'] ?></td>
          <td><?= $q['allow_multiple'] ? 'Yes' : 'No' ?></td>
          <td><?= htmlspecialchars($q['allowed_file_types'] ?? '') ?></td>
          <td><?= htmlspecialchars($q['max_file_size_mb'] ?? '') ?></td>
          <td><?= htmlspecialchars($q['max_file_count'] ?? '') ?></td>
          <td>
            <button class="btn btn-sm btn-primary" onclick='openForm(<?= json_encode($q) ?>)'>Edit</button>
            <a href="?delete=<?= $q['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete question?')">Delete</a>
          </td>
        </tr>
    <?php endforeach;
    endif; ?>
  </tbody>
</table>

<!-- Modal -->
<div class="modal fade" id="questionModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="post">
        <div class="modal-header">
          <h5 class="modal-title">Question</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="id" id="question-id">
          <input type="hidden" name="action" id="question-action">

          <div class="mb-3">
            <label class="form-label">Assessment</label>
            <select name="assessment_id" id="question-assessment" class="form-select" required>
              <option value="">Select assessment</option>
              <?php foreach ($assessments as $a): ?>
                <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['title']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Type</label>
            <select name="type" id="question-type" class="form-select" required>
              <option value="mcq">Multiple Choice / Fill in the Blanks</option>
              <option value="match">Match Columns</option>
              <option value="short">Short Answer</option>
              <option value="long">Long Answer</option>
              <option value="file_upload">File Upload</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Question Text</label>
            <textarea name="question_text" id="question-text" class="form-control" rows="3" required></textarea>
          </div>
          <div class="mb-3 row">
            <div class="col-md-6">
              <label class="form-label">Weight</label>
              <input type="number" name="weight" id="question-weight" class="form-control" min="1" value="1" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Allow Multiple Answers?</label>
              <select name="allow_multiple" id="question-multiple" class="form-select" required>
                <option value="0">No</option>
                <option value="1">Yes</option>
              </select>
            </div>
          </div>

          <div id="fileSettings" style="display:none;">
            <div class="mb-3">
              <label>Allowed File Types (comma separated, e.g. pdf,doc,docx)</label>
              <input type="text" name="allowed_file_types" id="allowed_file_types" class="form-control">
            </div>
            <div class="mb-3">
              <label>Max File Size (MB)</label>
              <input type="number" name="max_file_size_mb" id="max_file_size_mb" class="form-control" min="1" value="5">
            </div>
            <div class="mb-3">
              <label>Max Number of Files</label>
              <input type="number" name="max_file_count" id="max_file_count" class="form-control" min="1" value="1">
            </div>
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
  function toggleFileSettings() {
    const type = document.getElementById('question-type').value;
    document.getElementById('fileSettings').style.display = (type === 'file_upload') ? 'block' : 'none';
  }

  document.addEventListener('DOMContentLoaded', function() {
    const select = document.getElementById('question-type');
    select.addEventListener('change', toggleFileSettings);
    toggleFileSettings();
  });

  function openForm(data = null) {
    const modal = new bootstrap.Modal(document.getElementById('questionModal'));
    document.getElementById('question-action').value = data ? 'update' : 'create';
    if (data) {
      document.getElementById('question-id').value = data.id;
      document.getElementById('question-assessment').value = data.assessment_id;
      document.getElementById('question-type').value = data.type;
      document.getElementById('question-text').value = data.question_text;
      document.getElementById('question-weight').value = data.weight;
      document.getElementById('question-multiple').value = data.allow_multiple;
      document.getElementById('allowed_file_types').value = data.allowed_file_types ?? '';
      document.getElementById('max_file_size_mb').value = data.max_file_size_mb ?? 5;
      document.getElementById('max_file_count').value = data.max_file_count ?? 1;
    } else {
      document.getElementById('question-id').value = '';
      document.getElementById('question-assessment').value = '';
      document.getElementById('question-type').value = 'mcq';
      document.getElementById('question-text').value = '';
      document.getElementById('question-weight').value = '1';
      document.getElementById('question-multiple').value = '0';
      document.getElementById('allowed_file_types').value = '';
      document.getElementById('max_file_size_mb').value = 5;
      document.getElementById('max_file_count').value = 1;
    }
    toggleFileSettings();
    modal.show();
  }
</script>
<?php include 'footer.php'; ?>