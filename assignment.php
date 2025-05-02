<?php
// assignment.php
session_start();
require 'mysql_config.php';

// Ensure user is logged in
if (empty($_SESSION['user_id']) || empty($_SESSION['role'])) {
    header('Location: login.php');
    exit;
}
$userId         = $_SESSION['user_id'];
$role           = $_SESSION['role'];
$selectedBatch  = isset($_GET['batch_id']) ? (int)$_GET['batch_id'] : null;
$selectedStudent= isset($_GET['student_id']) ? (int)$_GET['student_id'] : null;

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
    $stmt = $mysqli->prepare("SELECT * FROM afsm_assignments WHERE batch_id = ? LIMIT 1");
    $stmt->bind_param('i', $selectedBatch);
    $stmt->execute();
    $assignment = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
}

// 3) Determine student context
if ($role === 'student') {
    $submitStudent = $userId;
} else {
    $submitStudent = $selectedStudent;
}

// 4) Fetch students list for teacher/admin
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

// 5) Load existing submission with defaults
$submission = [
    'doc_path'     => '',
    'pdf_path'     => '',
    'text_content' => '',
    'status'       => '',
    'submitted_date' => ''
];
if ($assignment && $submitStudent) {
    $stmt = $mysqli->prepare(
        "SELECT *
           FROM afsm_assignment_submissions
          WHERE assignment_id = ? AND student_id = ?"
    );
    $stmt->bind_param('ii', $assignment['id'], $submitStudent);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if ($row) {
        $submission = array_merge($submission, $row);
    }
    $stmt->close();
}

// 6) Handle form POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $assignment && $submitStudent) {
    // preserve previous paths
    $docPath = $submission['doc_path'];
    $pdfPath = $submission['pdf_path'];
    // upload directory
    $dir = __DIR__ . "/uploads/assignments/{$selectedBatch}/{$assignment['id']}/{$submitStudent}/";
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    // handle DOC upload
    if (!empty($_FILES['doc_file']['name']) && $_FILES['doc_file']['error'] === UPLOAD_ERR_OK) {
        $file = basename($_FILES['doc_file']['name']);
        move_uploaded_file($_FILES['doc_file']['tmp_name'], $dir . $file);
        $docPath = "uploads/assignments/{$selectedBatch}/{$assignment['id']}/{$submitStudent}/{$file}";
    }
    // handle PDF upload
    if (!empty($_FILES['pdf_file']['name']) && $_FILES['pdf_file']['error'] === UPLOAD_ERR_OK) {
        $file = basename($_FILES['pdf_file']['name']);
        move_uploaded_file($_FILES['pdf_file']['tmp_name'], $dir . $file);
        $pdfPath = "uploads/assignments/{$selectedBatch}/{$assignment['id']}/{$submitStudent}/{$file}";
    }
    // text content
    $textContent = trim($_POST['text_content'] ?? '');
    $action       = $_POST['action'] ?? 'draft';
    $status       = $action === 'submit' ? 'submitted' : 'draft';
    $submittedDate = $status === 'submitted' ? date('Y-m-d H:i:s') : null;
    // upsert
    $stmt = $mysqli->prepare(
        "INSERT INTO afsm_assignment_submissions
         (assignment_id, student_id, doc_path, pdf_path, text_content, status, submitted_date)
         VALUES (?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            doc_path = VALUES(doc_path),
            pdf_path = VALUES(pdf_path),
            text_content = VALUES(text_content),
            status = VALUES(status),
            submitted_date = VALUES(submitted_date)"
    );
    $stmt->bind_param(
        'iisssss',
        $assignment['id'], $submitStudent,
        $docPath, $pdfPath, $textContent,
        $status, $submittedDate
    );
    $stmt->execute();
    $stmt->close();
    // reload submission
    $submission = [
        'doc_path'     => $docPath,
        'pdf_path'     => $pdfPath,
        'text_content' => $textContent,
        'status'       => $status,
        'submitted_date' => $submittedDate
    ];
}

$page_title = 'Assignment';
include 'header.php';
?>
<style>
.qize-section { background: #fff; padding: 40px; box-shadow: rgba(0,0,0,0.05) 0 6px 24px, rgba(0,0,0,0.08) 0 0 0 1px; }
.mlw_qmn_message_before h2 { color: #fff; font-size: 23px; text-align: center; background: #90281e; padding: 10px; border-radius: 5px; font-weight:700; margin-bottom:20px; }
.quiz-new-item { font-size: 17px; margin: 0 0 20px 19px; font-weight:500; }
.taxera { margin-top: 20px; }
.quize-file { width:100%; margin-bottom:10px; }
.quiz-text { width:100%; margin:10px 0; }
.orfile { text-align:center; margin:20px 0; font-weight:bold; }
.existing-file { font-size:14px; margin-bottom:8px; }
</style>
<div class="qize-section">
  <form method="get" class="row g-3 mb-4 align-items-center">
    <div class="col-auto"><label class="col-form-label">Batch:</label></div>
    <div class="col-auto">
      <select name="batch_id" class="form-select" onchange="this.form.submit()">
        <option value="">Select Batch</option>
        <?php while ($batches && $b = $batches->fetch_assoc()): ?>
          <option value="<?= $b['id'] ?>" <?= ($b['id']==$selectedBatch)?'selected':'' ?>>
            <?= htmlspecialchars($b['name']) ?>
          </option>
        <?php endwhile; ?>
      </select>
    </div>
    <?php if (in_array($role, ['teacher','admin']) && $selectedBatch): ?>
      <div class="col-auto"><label class="col-form-label">Student:</label></div>
      <div class="col-auto">
        <select name="student_id" class="form-select" onchange="this.form.submit()">
          <option value="">Select Student</option>
          <?php while ($students && $s = $students->fetch_assoc()): ?>
            <option value="<?= $s['id'] ?>" <?= ($s['id']==$submitStudent)?'selected':'' ?>>
              <?= htmlspecialchars($s['name']) ?>
            </option>
          <?php endwhile; ?>
        </select>
      </div>
    <?php endif; ?>
  </form>

  <div class="mlw_qmn_message_before">
    <?php if ($assignment): ?>
        <h2><?= htmlspecialchars($assignment['title']) ?></h2>
    <?php else: ?>
        <h2>Please select a batch to view the assignment</h2>
    <?php endif; ?>
</div>

  <div class="quiz-new-item">
    <?php if ($assignment): ?>
        <?= $assignment['instructions'] ?>
    <?php else: ?>
        <p>No assignment instructions available.</p>
    <?php endif; ?>
</div>

  <?php if ($submitStudent && $assignment): ?>
  <div class="taxera">
    <form method="post" enctype="multipart/form-data">
      <div class="quiz-new-item-file">
        <label>Upload DOC (required)</label>
        <?php if (!empty($submission['doc_path'])): ?>
          <p class="existing-file"><a href="<?= htmlspecialchars($submission['doc_path']) ?>" target="_blank"><?= htmlspecialchars(basename($submission['doc_path'])) ?></a></p>
        <?php endif; ?>
        <input type="file" name="doc_file" accept=".doc,.docx,.txt" class="quize-file" <?= ($submission['status']==='submitted')?'disabled':'' ?> required>
      </div>

      <div class="quiz-new-item-file">
        <label>Upload PDF (optional)</label>
        <?php if (!empty($submission['pdf_path'])): ?>
          <p class="existing-file"><a href="<?= htmlspecialchars($submission['pdf_path']) ?>" target="_blank"><?= htmlspecialchars(basename($submission['pdf_path'])) ?></a></p>
        <?php endif; ?>
        <input type="file" name="pdf_file" accept=".pdf" class="quize-file" <?= ($submission['status']==='submitted')?'disabled':'' ?> >
      </div>

      <div class="orfile">OR</div>

      <?php if (!empty($assignment['allow_text_input'] ?? false)): ?>
        <textarea name="text_content" class="quiz-text" rows="8" <?= ($submission['status']==='submitted')?'disabled':'' ?>>
<?= htmlspecialchars(trim($submission['text_content'] ?? '')) ?>
        </textarea>
      <?php endif; ?>

      <div class="mt-4">
        <button type="submit" name="action" value="draft" class="btn btn-secondary" <?= ($submission['status']==='submitted')?'disabled':'' ?>>Save Draft</button>
        <button type="submit" name="action" value="submit" class="btn btn-primary" <?= ($submission['status']==='submitted')?'disabled':'' ?>>Submit Answer</button>
      </div>
    </form>
  </div>
  <?php endif; ?>
</div>
<?php include 'footer.php'; ?>
