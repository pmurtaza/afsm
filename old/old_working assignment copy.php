<?php
require 'mysql_config.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);
// Ensure user is logged in and variables are set
if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
  header('Location: login.php');
  exit;
}
$userId        = $_SESSION['user_id'];
$role          = $_SESSION['role'];
$selectedBatch = $_GET['batch_id'] ?? null;

// 1) Fetch batches dropdown based on role
if ($role === 'admin') {
  $batches = $mysqli->query("SELECT id, name FROM afsm_batches ORDER BY name");
} else {
  $stmt = $mysqli->prepare(
    "SELECT b.id, b.name
           FROM afsm_batches b
           JOIN afsm_batch_students bs ON b.id = bs.batch_id
          WHERE bs.user_id = ? AND bs.role = ?
          ORDER BY b.name"
  );
  $stmt->bind_param('is', $userId, $role);
  $stmt->execute();
  $batches = $stmt->get_result();
  $stmt->close();
}

// 2) Fetch assignment for selected batch
$assignment = null;
if ($selectedBatch) {
  $stmt = $mysqli->prepare("SELECT * FROM afsm_assignments WHERE batch_id = ? LIMIT 1");
  $stmt->bind_param('i', $selectedBatch);
  $stmt->execute();
  $assignment = $stmt->get_result()->fetch_assoc();
  $stmt->close();
}

// 3) Handle form submission after assignment is set
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $role === 'student' && $selectedBatch && $assignment) {
  error_log('POST: ' . print_r($_POST, true));
  $studentId = $userId;
  $uploadDir = __DIR__ . "/uploads/assignments/{$selectedBatch}/{$assignment['id']}/{$studentId}/";
  if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
  $docPath = $submission['doc_path'] ?? null;
  $pdfPath = $submission['pdf_path'] ?? null;
  if (!empty($_FILES['doc_file']['name'])) {
    $target = $uploadDir . basename($_FILES['doc_file']['name']);
    move_uploaded_file($_FILES['doc_file']['tmp_name'], $target);
    $docPath = "uploads/assignments/{$selectedBatch}/{$assignment['id']}/{$studentId}/" . basename($_FILES['doc_file']['name']);
  }
  if (!empty($_FILES['pdf_file']['name'])) {
    $target = $uploadDir . basename($_FILES['pdf_file']['name']);
    move_uploaded_file($_FILES['pdf_file']['tmp_name'], $target);
    $pdfPath = "uploads/assignments/{$selectedBatch}/{$assignment['id']}/{$studentId}/" . basename($_FILES['pdf_file']['name']);
  }
  $textContent   = trim($_POST['text_content'] ?? '');
  $status        = ($_POST['action'] ?? '') === 'submit' ? 'submitted' : 'draft';
  $submittedDate = $status === 'submitted' ? date('Y-m-d H:i:s') : null;
  $stmt = $mysqli->prepare(
    "INSERT INTO afsm_assignment_submissions
            (assignment_id, student_id, doc_path, pdf_path, text_content, status, submitted_date)
         VALUES (?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            doc_path       = VALUES(doc_path),
            pdf_path       = VALUES(pdf_path),
            text_content   = VALUES(text_content),
            status         = VALUES(status),
            submitted_date = VALUES(submitted_date)"
  );
  if (!$stmt) die("Prepare failed: ({$mysqli->errno}) {$mysqli->error}");
  $stmt->bind_param('iisssss', $assignment['id'], $studentId, $docPath, $pdfPath, $textContent, $status, $submittedDate);
  if (!$stmt->execute()) {
    die("Execute failed: ({$stmt->errno}) {$stmt->error}");
  }
  $stmt->close();
}

// 4) Fetch submission for display
$submission = null;
if ($role === 'student' && $assignment) {
  $stmt = $mysqli->prepare(
    "SELECT * FROM afsm_assignment_submissions WHERE assignment_id = ? AND student_id = ?"
  );
  $stmt->bind_param('ii', $assignment['id'], $userId);
  $stmt->execute();
  $submission = $stmt->get_result()->fetch_assoc();
  $stmt->close();
}

$page_title = 'AFSM - Assignment';
include 'header.php';
?>
<div class="card mb-4">
  <div class="card-body">
    <form method="get" class="row g-3 mb-4 align-items-center">
      <div class="col-auto">
        <label class="col-form-label">Batch:</label>
      </div>
      <div class="col-auto">
        <select name="batch_id" class="form-select" onchange="this.form.submit()">
          <option value="">Select Batch</option>
          <?php if ($batches): while ($b = $batches->fetch_assoc()): ?>
              <option value="<?= $b['id'] ?>" <?= $selectedBatch == $b['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($b['name']) ?>
              </option>
          <?php endwhile;
          endif; ?>
        </select>
      </div>
    </form>

    <?php if ($assignment): ?>
      <h3><?= htmlspecialchars($assignment['title']) ?></h3>
      <p><?= nl2br(htmlspecialchars($assignment['instructions'])) ?></p>

      <?php if (TRUE || $role === 'student'): ?>
        <?php $subStatus = $submission['status'] ?? null; ?>
        <form method="post" enctype="multipart/form-data">
          <?php if ($assignment['allow_upload_doc']): ?>
            <div class="mb-3">
              <label>Upload DOC</label>
              <input type="file" name="doc_file" accept=".doc,.docx" class="form-control"
                <?= $subStatus === 'submitted' ? 'disabled' : '' ?>>
              <?php if (!empty($submission['doc_path'])): ?>
                <p class="mt-2">Existing DOC: <a href="<?= htmlspecialchars($submission['doc_path']) ?>" target="_blank"><?= basename($submission['doc_path']) ?></a></p>
              <?php endif; ?>
            </div>
          <?php endif; ?>
          <?php if ($assignment['allow_upload_pdf']): ?>
            <div class="mb-3">
              <label>Upload PDF</label>
              <input type="file" name="pdf_file" accept=".pdf" class="form-control"
                <?= $subStatus === 'submitted' ? 'disabled' : '' ?>>
              <?php if (!empty($submission['pdf_path'])): ?>
                <p class="mt-2">Existing PDF: <a href="<?= htmlspecialchars($submission['pdf_path']) ?>" target="_blank"><?= basename($submission['pdf_path']) ?></a></p>
              <?php endif; ?>
            </div>
          <?php endif; ?>
          <?php if ($assignment['allow_text_input']): ?>
            <div class="mb-3">
              <label>Your Answer</label>
              <textarea name="text_content" class="form-control" rows="6" <?= $submission ? 'readonly' : '' ?>>
              <?= htmlspecialchars($submission['text_content'] ?? '') ?>
            </textarea>
            </div>
          <?php endif; ?>
          <?php if ($subStatus !== 'submitted'): ?>
            <button name="action" value="draft" class="btn btn-secondary">Save Draft</button>
            <button name="action" value="submit" class="btn btn-primary">Submit</button>
            <?php if ($subStatus === 'draft'): ?>
              <p class="text-muted mt-2">Draft saved on <?= htmlspecialchars($submission['submitted_date'] ?? '') ?></p>
            <?php endif; ?>
          <?php else: ?>
            <p class="text-muted">Submitted on <?= htmlspecialchars($submission['submitted_date']) ?></p>
          <?php endif; ?>
        <?php endif; ?>

      <?php elseif ($selectedBatch): ?>
        <p>No assignment configured for this batch.</p>
      <?php endif; ?>
  </div>
</div>
<?php include 'footer.php'; ?>