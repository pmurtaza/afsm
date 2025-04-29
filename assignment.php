<?php
require 'mysql_config.php';
session_start();
// Only teachers/admin create assignments, students view
if (!isset($_SESSION['role'])) header('Location: login.php');
$role = $_SESSION['role'];

// For assignment creation/editing (teacher/admin)
if (in_array($role,['teacher','admin'])) {
  // fetch batches for dropdown
  // $batches = $mysqli->query("SELECT id,name FROM afsm_batches");
  // Determine batch list based on user role
if ($_SESSION['role'] === 'teacher') {
  $stmt = $mysqli->prepare(
      "SELECT b.id, b.name
         FROM afsm_batches b
         JOIN afsm_batch_students bs
           ON b.id = bs.batch_id
        WHERE bs.user_id = ?
          AND bs.role = 'teacher'
        ORDER BY b.name"
  );
  $stmt->bind_param('i', $_SESSION['user_id']);
  $stmt->execute();
  $batches = $stmt->get_result();
  $stmt->close();
} else {
  // admin sees all batches
  $batches = $mysqli->query("SELECT id, name FROM afsm_batches ORDER BY name");
}
  // handle form submission: insert or update afsm_assignments
  // ... (similar to other pages)
}

// For students: list current assignment and submission form
if ($role==='student') {
  // fetch assignment for student's batch
  $batchId = /* lookup via junction */;
  $stmt = $mysqli->prepare("SELECT * FROM afsm_assignments WHERE batch_id = ?");
  $stmt->bind_param('i',$batchId);
  $stmt->execute();
  $assignment = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  // fetch existing submission
  $studentId = $_SESSION['user_id'];
  $stmt = $mysqli->prepare(
    "SELECT * FROM afsm_assignment_submissions
      WHERE assignment_id = ? AND student_id = ?"
  );
  $stmt->bind_param('ii',$assignment['id'],$studentId);
  $stmt->execute();
  $submission = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  // render form: file inputs, text editor (e.g. <textarea>)
  // on submit: handle uploads, insert/update submissions table
}

include 'header.php';
?>
<!-- HTML skeleton below -->
<div class="card">
  <div class="card-body">
    <h3><?= htmlspecialchars($assignment['title']) ?></h3>
    <p><?= nl2br(htmlspecialchars($assignment['instructions'])) ?></p>
    <?php if ($role==='student'): ?>
      <form method="post" enctype="multipart/form-data">
        <?php if ($assignment['allow_upload_doc']): ?>
          <div class="mb-3">
            <label>Upload DOC</label>
            <input type="file" name="doc_file" accept=".doc,.docx" class="form-control" <?= $submission?'disabled':''?>>
          </div>
        <?php endif; ?>
        <?php if ($assignment['allow_upload_pdf']): ?>
          <div class="mb-3">
            <label>Upload PDF</label>
            <input type="file" name="pdf_file" accept=".pdf" class="form-control" <?= $submission?'disabled':''?>>
          </div>
        <?php endif; ?>
        <?php if ($assignment['allow_text_input']): ?>
          <div class="mb-3">
            <label>Your Answer</label>
            <textarea name="text_content" class="form-control" rows="6" <?= $submission?'readonly':''?>>
              <?= htmlspecialchars($submission['text_content'] ?? '') ?>
            </textarea>
          </div>
        <?php endif; ?>
        <?php if (!$submission): ?>
          <button name="action" value="draft" class="btn btn-secondary">Save Draft</button>
          <button name="action" value="submit" class="btn btn-primary">Submit</button>
        <?php else: ?>
          <p class="text-muted">Submitted on <?= $submission['submitted_date'] ?></p>
        <?php endif; ?>
      </form>
    <?php endif; ?>
  </div>
</div>
