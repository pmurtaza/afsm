<?php
// assignment.php
require 'mysql_config.php';
// Ensure user is logged in
if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    header('Location: login.php');
    exit;
}
$userId         = $_SESSION['user_id'];
$role           = $_SESSION['role'];
$selectedBatch  = $_GET['batch_id']    ?? null;
$selectedStudent= $_GET['student_id']  ?? null;

// 1) Fetch batches for dropdown
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
    $stmt = $mysqli->prepare(
        "SELECT * FROM afsm_assignments WHERE batch_id = ? LIMIT 1"
    );
    $stmt->bind_param('i', $selectedBatch);
    $stmt->execute();
    $assignment = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// 3) Determine student context
if ($role === 'student') {
    $submitStudent = $userId;
} else {
    $submitStudent = $selectedStudent ? (int)$selectedStudent : null;
}

// 4) Fetch list of students for teacher/admin
$students = [];
if (in_array($role, ['teacher','admin']) && $selectedBatch) {
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

// 5) Pre-load existing submission for display
$submission = null;
if ($assignment && $submitStudent) {
    $stmt = $mysqli->prepare(
        "SELECT * FROM afsm_assignment_submissions WHERE assignment_id = ? AND student_id = ?"
    );
    $stmt->bind_param('ii', $assignment['id'], $submitStudent);
    $stmt->execute();
    $submission = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// 6) Handle form POST for draft/submit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $assignment && $submitStudent) {
    // preserve old paths
    $docPath = $submission['doc_path'] ?? '';
    $pdfPath = $submission['pdf_path'] ?? '';
    // upload directory
    $uploadDir = __DIR__ . "/uploads/assignments/{$selectedBatch}/{$assignment['id']}/{$submitStudent}/";
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    // handle DOC upload
    if (!empty($_FILES['doc_file']['name']) && $_FILES['doc_file']['error'] === UPLOAD_ERR_OK) {
        $file = basename($_FILES['doc_file']['name']);
        move_uploaded_file($_FILES['doc_file']['tmp_name'], $uploadDir.$file);
        $docPath = "uploads/assignments/{$selectedBatch}/{$assignment['id']}/{$submitStudent}/{$file}";
    }
    // handle PDF upload
    if (!empty($_FILES['pdf_file']['name']) && $_FILES['pdf_file']['error'] === UPLOAD_ERR_OK) {
        $file = basename($_FILES['pdf_file']['name']);
        move_uploaded_file($_FILES['pdf_file']['tmp_name'], $uploadDir.$file);
        $pdfPath = "uploads/assignments/{$selectedBatch}/{$assignment['id']}/{$submitStudent}/{$file}";
    }
    // text content
    $textContent   = trim($_POST['text_content'] ?? '');
    $action        = $_POST['action'] ?? 'draft';
    $status        = $action === 'submit' ? 'submitted' : 'draft';
    $submittedDate = $status === 'submitted' ? date('Y-m-d H:i:s') : null;
    // upsert submission
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
    $stmt->bind_param('iisssss',
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
    // reload submission
    $submission['doc_path']       = $docPath;
    $submission['pdf_path']       = $pdfPath;
    $submission['text_content']   = $textContent;
    $submission['status']         = $status;
    $submission['submitted_date'] = $submittedDate;
}

// Render page
$page_title = 'Assignment';
include 'header.php';
?>

<div class="qize-section">
  <!-- Title Bar -->
  <div class="mlw_qmn_message_before">
    <h2><?= $assignment ? htmlspecialchars($assignment['title']) : 'Select a Batch' ?></h2>
    <p><?= $assignment ? htmlspecialchars($assignment['title']) : '' ?></p>
  </div>

  <!-- Batch Dropdown -->
  <form method="get" class="row g-3 mb-4">
    <div class="col-auto"><label class="col-form-label">Batch:</label></div>
    <div class="col-auto">
      <select name="batch_id" class="form-select" onchange="this.form.submit()">
        <option value="">Select Batch</option>
        <?php if ($batches): while ($b = $batches->fetch_assoc()): ?>
          <option value="<?= $b['id'] ?>" <?= $b['id']==$selectedBatch?'selected':'' ?>>
            <?= htmlspecialchars($b['name']) ?>
          </option>
        <?php endwhile; endif; ?>
      </select>
    </div>
  </form>

  <?php if ($assignment): ?>
    <!-- Instructions -->
    <div class="quiz-new-item">
      <?= $assignment['instructions'] ?>
    </div>

    <!-- Student Dropdown for teacher/admin -->
    <?php if (in_array($role, ['teacher','admin'])): ?>
      <form method="get" class="row g-3 mb-4">
        <input type="hidden" name="batch_id" value="<?= htmlspecialchars($selectedBatch) ?>">
        <div class="col-auto"><label class="col-form-label">Student:</label></div>
        <div class="col-auto">
          <select name="student_id" class="form-select" onchange="this.form.submit()" required>
            <option value="">Choose Student</option>
            <?php while ($s = $students->fetch_assoc()): ?>
              <option value="<?= $s['id'] ?>" <?= ($s['id']==$submitStudent)?'selected':''?>>
                <?= htmlspecialchars($s['name']) ?>
              </option>
            <?php endwhile; ?>
          </select>
        </div>
      </form>
    <?php endif; ?>

    <!-- Submission Form -->
    <?php if ($submitStudent): ?>
      <?php $subStatus = $submission['status'] ?? null; ?>
      <div class="taxera">
        <form method="post" enctype="multipart/form-data">
          <!-- DOC Upload -->
          <div class="quiz-new-item-file">
            <label>Upload DOC <small>(.doc, .docx)</small></label>
            <?php if (!empty($submission['doc_path'])): ?>
              <p class="existing-file">
                <a href="<?= htmlspecialchars($submission['doc_path']) ?>" target="_blank">
                  <?= htmlspecialchars(basename($submission['doc_path'])) ?>
                </a>
              </p>
            <?php endif; ?>
            <input type="file" name="doc_file" accept=".doc,.docx" class="quize-file" <?= $subStatus==='submitted'?'disabled':'' ?>>
          </div>

          <!-- PDF Upload -->
          <div class="quiz-new-item-file">
            <label>Upload PDF <small>(.pdf)</small></label>
            <?php if (!empty($submission['pdf_path'])): ?>
              <p class="existing-file">
                <a href="<?= htmlspecialchars($submission['pdf_path']) ?>" target="_blank">
                  <?= htmlspecialchars(basename($submission['pdf_path'])) ?>
                </a>
              </p>
            <?php endif; ?>
            <input type="file" name="pdf_file" accept=".pdf" class="quize-file" <?= $subStatus==='submitted'?'disabled':'' ?>>
          </div>

          <!-- OR Separator -->
          <div class="orfile">OR</div>

          <!-- Text Area -->
          <?php if ($assignment['allow_text_input']): ?>
            <textarea name="text_content" class="quiz-text" rows="8" <?= $subStatus==='submitted'?'disabled':'' ?>>
<?= htmlspecialchars(trim($submission['text_content'] ?? '')) ?>
            </textarea>
          <?php endif; ?>

          <!-- Action Buttons -->
          <button type="submit" name="action" value="draft" class="btn btn-secondary" <?= $subStatus==='submitted'?'disabled':'' ?>>Save Draft</button>
          <button type="submit" name="action" value="submit" class="btn btn-primary" <?= $subStatus==='submitted'?'disabled':'' ?>>Submit Answer</button>
        </form>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>

<?php include 'footer.php'; ?>
