<!-- -- assignment.php -- -->
<?php
require 'mysql_config.php';
// Session is handled in mysql_config.php
// Ensure user is logged in
if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
  header('Location: login.php');
  exit;
}
$userId        = $_SESSION['user_id'];
$role          = $_SESSION['role'];
$selectedBatch = $_GET['batch_id'] ?? null;
$selectedStudent = $_POST['student_id'] ?? null;

// 1) Fetch batches based on role
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

// 2) Fetch assignment if batch selected
$assignment = null;
if ($selectedBatch) {
  $stmt = $mysqli->prepare("SELECT * FROM afsm_assignments WHERE batch_id = ? LIMIT 1");
  $stmt->bind_param('i', $selectedBatch);
  $stmt->execute();
  $assignment = $stmt->get_result()->fetch_assoc();
  $stmt->close();
}

// 3) For teacher/admin, fetch students in batch
$students = [];
if (in_array($role, ['teacher', 'admin']) && $selectedBatch) {
  $stmt = $mysqli->prepare(
    "SELECT u.id, u.name
           FROM afsm_users u
           JOIN afsm_batch_students bs ON u.id = bs.user_id
          WHERE bs.batch_id = ? AND bs.role = 'student'
          ORDER BY u.name"
  );
  $stmt->bind_param('i', $selectedBatch);
  $stmt->execute();
  $students = $stmt->get_result();
  $stmt->close();
}

// 4) Handle submission for all roles
$submission = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $assignment) {
  // Determine student ID: student uploads self, others choose
  if ($role === 'student') {
    $submitStudent = $userId;
  } else {
    $submitStudent = (int)$selectedStudent;
  }
  // Prepare upload directory
  $uploadDir = __DIR__ . "/uploads/assignments/{$selectedBatch}/{$assignment['id']}/{$submitStudent}/";
  if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
  // Paths
  $docPath = '';
  $pdfPath = '';
  // DOC
  if (!empty($_FILES['doc_file']['name']) && $_FILES['doc_file']['error'] === UPLOAD_ERR_OK) {
    $file = basename($_FILES['doc_file']['name']);
    move_uploaded_file($_FILES['doc_file']['tmp_name'], $uploadDir . $file);
    $docPath = "uploads/assignments/{$selectedBatch}/{$assignment['id']}/{$submitStudent}/{$file}";
  }
  // PDF
  if (!empty($_FILES['pdf_file']['name']) && $_FILES['pdf_file']['error'] === UPLOAD_ERR_OK) {
    $file = basename($_FILES['pdf_file']['name']);
    move_uploaded_file($_FILES['pdf_file']['tmp_name'], $uploadDir . $file);
    $pdfPath = "uploads/assignments/{$selectedBatch}/{$assignment['id']}/{$submitStudent}/{$file}";
  }
  // Text
  $textContent   = trim($_POST['text_content'] ?? '');
  $action        = $_POST['action'] ?? 'draft';
  $status        = $action === 'submit' ? 'submitted' : 'draft';
  $submittedDate = $status === 'submitted' ? date('Y-m-d H:i:s') : null;
  // Upsert
  $stmt = $mysqli->prepare(
    "INSERT INTO afsm_assignment_submissions
          (assignment_id, student_id, doc_path, pdf_path, text_content, status, submitted_date)
         VALUES (?,?,?,?,?,?,?)
         ON DUPLICATE KEY UPDATE
            doc_path       = VALUES(doc_path),
            pdf_path       = VALUES(pdf_path),
            text_content   = VALUES(text_content),
            status         = VALUES(status),
            submitted_date = VALUES(submitted_date)"
  );
  $stmt->bind_param(
    'iisssss',
    $assignment['id'],
    $submitStudent,
    $docPath,
    $pdfPath,
    $textContent,
    $status,
    $submittedDate
  );
  $stmt->execute();
  $stmt->close();
}

// 5) Fetch submission for display
if ($assignment) {
  if ($role === 'student') {
    $lookupStudent = $userId;
  } else {
    $lookupStudent = (int)($selectedStudent ?? 0);
  }
  if ($lookupStudent) {
    $stmt = $mysqli->prepare(
      "SELECT * FROM afsm_assignment_submissions WHERE assignment_id=? AND student_id=?"
    );
    $stmt->bind_param('ii', $assignment['id'], $lookupStudent);
    $stmt->execute();
    $submission = $stmt->get_result()->fetch_assoc();
    $stmt->close();
  }
}

$page_title = 'Assignment';
include 'header.php';
?>

<div class="card mb-4">
  <div class="card-body">
    <!-- Batch selector -->
    <form method="get" class="row g-3 mb-4">
      <div class="col-auto"><label class="col-form-label">Batch:</label></div>
      <div class="col-auto">
        <select name="batch_id" class="form-select" onchange="this.form.submit()">
          <option value="">Select Batch</option>
          <?php while ($b = $batches->fetch_assoc()): ?>
            <option value="<?= $b['id'] ?>" <?= $b['id'] == $selectedBatch ? 'selected' : '' ?>>
              <?= htmlspecialchars($b['name']) ?>
            </option>
          <?php endwhile; ?>
        </select>
      </div>
    </form>

    <?php if ($assignment): ?>
      <?php if (in_array($role, ['teacher', 'admin'])): ?>
        <form method="get" class="row g-3 mb-4 align-items-center">
          <input type="hidden" name="batch_id" value="<?= htmlspecialchars($selectedBatch) ?>">
          <div class="col-auto"><label class="col-form-label">Student:</label></div>
          <div class="col-auto">
            <select name="student_id" class="form-select" onchange="this.form.submit()" required>
              <option value="">Choose student</option>
              <?php foreach ($students as $s): ?>
                <option value="<?= $s['id'] ?>" <?= (isset($_GET['student_id']) && $_GET['student_id'] == $s['id']) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($s['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </form>
      <?php endif; ?>
      <h3><?= htmlspecialchars($assignment['title']) ?></h3>
      <p><?= nl2br(htmlspecialchars($assignment['instructions'])) ?></p>
      <!-- Submission form -->
      <?php if (in_array($role, ['student', 'teacher', 'admin'])): ?>
        <?php $subStatus = $submission['status'] ?? null; ?>
        <form method="post" enctype="multipart/form-data">
          <!-- DOC -->
          <?php if ($assignment['allow_upload_doc']): ?>
            <div class="mb-3">
              <label>Upload DOC</label>
              <?php if (!empty($submission['doc_path'])): ?>
                <p>Existing DOC: <a href="<?= htmlspecialchars($submission['doc_path']) ?>" target="_blank"><?= htmlspecialchars(basename($submission['doc_path'])) ?></a></p>
              <?php endif; ?>
              <input type="file" name="doc_file" accept=".doc,.docx" class="form-control" <?= $subStatus === 'submitted' ? 'disabled' : '' ?>>
            </div>
          <?php endif; ?>
          <!-- PDF -->
          <?php if ($assignment['allow_upload_pdf']): ?>
            <div class="mb-3">
              <label>Upload PDF</label>
              <?php if (!empty($submission['pdf_path'])): ?>
                <p>Existing PDF: <a href="<?= htmlspecialchars($submission['pdf_path']) ?>" target="_blank"><?= htmlspecialchars(basename($submission['pdf_path'])) ?></a></p>
              <?php endif; ?>
              <input type="file" name="pdf_file" accept=".pdf" class="form-control" <?= $subStatus === 'submitted' ? 'disabled' : '' ?>>
            </div>
          <?php endif; ?>
          <!-- Text -->
          <?php if ($assignment['allow_text_input']): ?>
            <?php $textDisplay = trim($submission['text_content'] ?? ''); ?>
            <div class="mb-3">
              <label>Your Answer</label>
              <textarea name="text_content" class="form-control" rows="6" <?= $subStatus !== 'draft' ? 'disabled' : '' ?>>
<?= htmlspecialchars($textDisplay) ?>
              </textarea>
            </div>
          <?php endif; ?>
          <!-- Buttons -->
          <?php if ($subStatus !== 'submitted'): ?>
            <button name="action" value="draft" class="btn btn-secondary">Save Draft</button>
            <button name="action" value="submit" class="btn btn-primary">Submit</button>
            <?php if ($subStatus === 'draft'): ?>
              <p class="mt-2 text-muted">Draft saved on <?= htmlspecialchars($submission['submitted_date'] ?? '') ?></p>
            <?php endif; ?>
          <?php else: ?>
            <p class="text-muted">Submitted on <?= htmlspecialchars($submission['submitted_date']) ?></p>
          <?php endif; ?>
        </form>
      <?php endif; ?>

    <?php elseif ($selectedBatch): ?>
      <p>No assignment for this batch.</p>
    <?php endif; ?>
  </div>
</div>

<?php include 'footer.php'; ?>