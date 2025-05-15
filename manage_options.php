<?php
// manage_options.php
require 'mysql_config.php';
// Only teachers and admins
if (empty($_SESSION['role']) || !in_array($_SESSION['role'], ['teacher','admin'])) {
    header('Location: dashboard.php'); exit;
}
$userId = $_SESSION['user_id'];

// Handle create/update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    if ($action === 'create') {
        $sql = "INSERT INTO afsm_question_options (question_id, option_text, is_correct) VALUES (?,?,?)";
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) { die('Prepare failed (create): ' . $mysqli->error); }
        $stmt->bind_param('isi', $_POST['question_id'], $_POST['option_text'], $_POST['is_correct']);
    } else {
        $sql = "UPDATE afsm_question_options SET option_text=?, is_correct=? WHERE id=?";
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) { die('Prepare failed (update): ' . $mysqli->error); }
        $stmt->bind_param('sii', $_POST['option_text'], $_POST['is_correct'], $_POST['id']);
    }
    $stmt->execute(); $stmt->close();
    header('Location: manage_options.php?question_id=' . $_POST['question_id']); exit;
}

// Handle delete
if (isset($_GET['delete']) && isset($_GET['question_id'])) {
    $qid = (int)$_GET['question_id'];
    $id  = (int)$_GET['delete'];
    $stmt = $mysqli->prepare("DELETE FROM afsm_question_options WHERE id=?");
    $stmt->bind_param('i', $id); $stmt->execute(); $stmt->close();
    header('Location: manage_options.php?question_id=' . $qid); exit;
}

// Fetch questions dropdown (only MCQ questions)
$questions = [];
if ($res = $mysqli->query("SELECT id, question_text FROM afsm_questions WHERE type='mcq' ORDER BY created_date DESC")) {
    while ($q = $res->fetch_assoc()) { $questions[] = $q; }
    $res->free();
}

// Selected question
$selected = isset($_GET['question_id']) ? (int)$_GET['question_id'] : null;

// Fetch options for selected question
$options = [];
if ($selected) {
    $stmt = $mysqli->prepare("SELECT id, option_text, is_correct FROM afsm_question_options WHERE question_id=?");
    $stmt->bind_param('i', $selected);
    $stmt->execute();
    $options = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$page_title = 'Manage MCQ Options';
include 'header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1>Manage MCQ Options</h1>
  <?php if ($selected): ?>
    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#optionModal" onclick="openForm()">+ New Option</button>
  <?php endif; ?>
</div>

<div class="mb-3">
  <label class="form-label">Select Question:</label>
  <select class="form-select" onchange="location.href='?question_id='+this.value">
    <option value="">-- Choose --</option>
    <?php foreach ($questions as $q): ?>
      <option value="<?= $q['id'] ?>" <?= $q['id']==$selected?'selected':'' ?>>
        <?= htmlspecialchars($q['question_text']) ?>
      </option>
    <?php endforeach; ?>
  </select>
</div>

<?php if (!$selected): ?>
  <div class="alert alert-info">Please select a question to manage its MCQ options.</div>
<?php else: ?>
  <table class="table table-striped">
    <thead><tr><th>ID</th><th>Option Text</th><th>Correct?</th><th>Actions</th></tr></thead>
    <tbody>
    <?php if (empty($options)): ?>
      <tr><td colspan="4" class="text-center">No options found.</td></tr>
    <?php else: foreach ($options as $opt): ?>
      <tr>
        <td><?= $opt['id'] ?></td>
        <td><?= htmlspecialchars($opt['option_text']) ?></td>
        <td><?= $opt['is_correct']?'Yes':'No' ?></td>
        <td>
          <button class="btn btn-sm btn-primary" onclick='openForm(<?= json_encode($opt) ?>)'>Edit</button>
          <a href="?question_id=<?= $selected ?>&delete=<?= $opt['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete option?')">Delete</a>
        </td>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>

  <!-- Modal -->
  <div class="modal fade" id="optionModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <form method="post">
          <div class="modal-header">
            <h5 class="modal-title">MCQ Option</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" name="id" id="opt-id">
            <input type="hidden" name="action" id="opt-action">
            <input type="hidden" name="question_id" value="<?= $selected ?>">
            <div class="mb-3">
              <label class="form-label">Option Text</label>
              <input type="text" name="option_text" id="opt-text" class="form-control" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Is Correct?</label>
              <select name="is_correct" id="opt-correct" class="form-select">
                <option value="0">No</option>
                <option value="1">Yes</option>
              </select>
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
    const modal = new bootstrap.Modal(document.getElementById('optionModal'));
    document.getElementById('opt-action').value = data ? 'update' : 'create';
    if (data) {
      document.getElementById('opt-id').value = data.id;
      document.getElementById('opt-text').value = data.option_text;
      document.getElementById('opt-correct').value = data.is_correct;
    } else {
      document.getElementById('opt-id').value = '';
      document.getElementById('opt-text').value = '';
      document.getElementById('opt-correct').value = '0';
    }
    modal.show();
  }
  </script>
<?php endif; ?>

<?php include 'footer.php'; ?>
