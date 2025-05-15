<?php
// manage_match_pairs.php
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
        $sql = "INSERT INTO afsm_match_pairs (question_id, left_text, right_text) VALUES (?,?,?)";
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) { die('Prepare failed (create): ' . $mysqli->error); }
        $stmt->bind_param('iss', $_POST['question_id'], $_POST['left_text'], $_POST['right_text']);
    } else {
        $sql = "UPDATE afsm_match_pairs SET left_text=?, right_text=? WHERE id=?";
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) { die('Prepare failed (update): ' . $mysqli->error); }
        $stmt->bind_param('ssi', $_POST['left_text'], $_POST['right_text'], $_POST['id']);
    }
    $stmt->execute(); $stmt->close();
    header('Location: manage_match_pairs.php?question_id=' . $_POST['question_id']); exit;
}

// Handle delete
if (isset($_GET['delete']) && isset($_GET['question_id'])) {
    $qid = (int)$_GET['question_id'];
    $id  = (int)$_GET['delete'];
    $stmt = $mysqli->prepare("DELETE FROM afsm_match_pairs WHERE id=?");
    $stmt->bind_param('i', $id); $stmt->execute(); $stmt->close();
    header('Location: manage_match_pairs.php?question_id=' . $qid); exit;
}

// Fetch questions dropdown (only match-type questions)
$questions = [];
if ($res = $mysqli->query("SELECT id, question_text FROM afsm_questions WHERE type='match' ORDER BY created_date DESC")) {
    while ($q = $res->fetch_assoc()) { $questions[] = $q; }
    $res->free();
}

// Selected question
$selected = isset($_GET['question_id']) ? (int)$_GET['question_id'] : null;

// Fetch pairs for selected question
$pairs = [];
if ($selected) {
    $stmt = $mysqli->prepare("SELECT id, left_text, right_text FROM afsm_match_pairs WHERE question_id=?");
    $stmt->bind_param('i', $selected);
    $stmt->execute();
    $pairs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$page_title = 'Manage Match Pairs';
include 'header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1>Manage Match Pairs</h1>
  <?php if ($selected): ?>
    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#pairModal" onclick="openForm()">+ New Pair</button>
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
  <div class="alert alert-info">Please select a question to manage its match pairs.</div>
<?php else: ?>
  <table class="table table-striped">
    <thead><tr><th>ID</th><th>Left Text</th><th>Right Text</th><th>Actions</th></tr></thead>
    <tbody>
    <?php if (empty($pairs)): ?>
      <tr><td colspan="4" class="text-center">No pairs found.</td></tr>
    <?php else: foreach ($pairs as $pair): ?>
      <tr>
        <td><?= $pair['id'] ?></td>
        <td><?= htmlspecialchars($pair['left_text']) ?></td>
        <td><?= htmlspecialchars($pair['right_text']) ?></td>
        <td>
          <button class="btn btn-sm btn-primary" onclick='openForm(<?= json_encode($pair) ?>)'>Edit</button>
          <a href="?question_id=<?= $selected ?>&delete=<?= $pair['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete pair?')">Delete</a>
        </td>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>

  <!-- Modal -->
  <div class="modal fade" id="pairModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <form method="post">
          <div class="modal-header">
            <h5 class="modal-title">Match Pair</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" name="id" id="pair-id">
            <input type="hidden" name="action" id="pair-action">
            <input type="hidden" name="question_id" value="<?= $selected ?>">
            <div class="mb-3">
              <label class="form-label">Left Text</label>
              <input type="text" name="left_text" id="pair-left" class="form-control" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Right Text</label>
              <input type="text" name="right_text" id="pair-right" class="form-control" required>
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
    const modal = new bootstrap.Modal(document.getElementById('pairModal'));
    document.getElementById('pair-action').value = data ? 'update' : 'create';
    if (data) {
      document.getElementById('pair-id').value = data.id;
      document.getElementById('pair-left').value = data.left_text;
      document.getElementById('pair-right').value = data.right_text;
    } else {
      document.getElementById('pair-id').value = '';
      document.getElementById('pair-left').value = '';
      document.getElementById('pair-right').value = '';
    }
    modal.show();
  }
  </script>
<?php endif; ?>

<?php include 'footer.php'; ?>
  