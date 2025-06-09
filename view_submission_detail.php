<?php
// view_submission_detail.php
require 'mysql_config.php';

// Only teachers/admins
if (empty($_SESSION['role']) || !in_array($_SESSION['role'], ['teacher','admin'])) {
    header('Location: dashboard.php'); 
    exit;
}

$submissionId = isset($_GET['submission_id']) ? (int)$_GET['submission_id'] : null;
if (!$submissionId) {
    header('Location: view_submissions.php'); 
    exit;
}

// Fetch submission record
$stmt = $mysqli->prepare(
    "SELECT sub.assessment_id, sub.student_id, sub.submitted_date,
            sub.status, sub.doc_path, sub.pdf_path, sub.text_content AS student_text
     FROM afsm_assignment_submissions sub
     WHERE sub.id = ? LIMIT 1"
);
if (!$stmt) {
    die('Prepare failed: ' . $mysqli->error);
}
$stmt->bind_param('i', $submissionId);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$data) {
    echo "<div class='alert alert-warning'>Submission not found.</div>";
    exit;
}

// Assign these for usage in the view
$status = $data['status'];
$submissionDate = $data['submitted_date'];

// Fetch student name
$stmt = $mysqli->prepare("SELECT name FROM afsm_users WHERE id=?");
if (!$stmt) {
    die('Prepare failed: ' . $mysqli->error);
}
$stmt->bind_param('i', $data['student_id']);
$stmt->execute();
$res = $stmt->get_result();
$studentNameData = $res->fetch_assoc();
$data['student_name'] = $studentNameData ? $studentNameData['name'] : 'Unknown';
$stmt->close();

// Fetch questions for this assessment
$qstmt = $mysqli->prepare(
    "SELECT id, type, question_text
     FROM afsm_questions
     WHERE assessment_id = ? ORDER BY id"
);
if (!$qstmt) {
    die('Prepare failed: ' . $mysqli->error);
}
$qstmt->bind_param('i', $data['assessment_id']);
$qstmt->execute();
$questions = $qstmt->get_result()->fetch_all(MYSQLI_ASSOC);
$qstmt->close();

// Preload all responses
$responses = [];
$stmt = $mysqli->prepare(
    "SELECT question_id, text_response
     FROM afsm_responses
     WHERE submission_id = ?"
);
if (!$stmt) {
    die('Prepare failed: ' . $mysqli->error);
}
$stmt->bind_param('i', $submissionId);
$stmt->execute();
$responsesData = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
foreach ($responsesData as $r) {
    $responses[$r['question_id']] = ['text' => $r['text_response']];
}
$stmt->close();

// Preload selected options (MCQ & match)
$stmt = $mysqli->prepare(
    "SELECT question_id, option_id
     FROM afsm_response_options
     WHERE submission_id = ?"
);
if (!$stmt) {
    die('Prepare failed: ' . $mysqli->error);
}
$stmt->bind_param('i', $submissionId);
$stmt->execute();
$responseOptionsData = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
foreach ($responseOptionsData as $ro) {
    $responses[$ro['question_id']]['options'][] = $ro['option_id'];
}
$stmt->close();

// Fetch option texts and pairs text map
foreach ($questions as $q) {
    if ($q['type'] === 'mcq') {
        $optStmt = $mysqli->prepare("SELECT id, option_text FROM afsm_question_options WHERE question_id=?");
        if (!$optStmt) {
            die('Prepare failed: ' . $mysqli->error);
        }
        $optStmt->bind_param('i', $q['id']);
        $optStmt->execute();
        $opts = $optStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $optStmt->close();
        $optionMap = [];
        foreach ($opts as $o) {
            $optionMap[$o['id']] = $o['option_text'];
        }
        $responses[$q['id']]['option_texts'] = $optionMap;
    }
    if ($q['type'] === 'match') {
        $pairStmt = $mysqli->prepare("SELECT id, left_text, right_text FROM afsm_match_pairs WHERE question_id=?");
        if (!$pairStmt) {
            die('Prepare failed: ' . $mysqli->error);
        }
        $pairStmt->bind_param('i', $q['id']);
        $pairStmt->execute();
        $pairs = $pairStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $pairStmt->close();
        $pairMap = [];
        foreach ($pairs as $p) {
            $pairMap[$p['id']] = ['left'=>$p['left_text'],'right'=>$p['right_text']];
        }
        $responses[$q['id']]['pairs'] = $pairMap;
    }
}

$page_title = 'Submission Detail';

// Fetch assessment & batch for breadcrumb
$stmt = $mysqli->prepare(
  "SELECT a.type, a.title, b.name AS batch_name, b.id AS batch_id
     FROM afsm_assessments a
     JOIN afsm_batches b ON a.batch_id = b.id
    WHERE a.id = ?"
);
if (!$stmt) {
    die('Prepare failed: ' . $mysqli->error);
}
$stmt->bind_param('i', $data['assessment_id']);
$stmt->execute();
$info = $stmt->get_result()->fetch_assoc();
$stmt->close();

include 'header.php';
?>
<div class="container mt-4">
  <nav aria-label="breadcrumb">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="batch_overview.php?batch_id=<?= htmlspecialchars($info['batch_id']) ?>"><?= "Batch: " . htmlspecialchars($info['batch_name']) ?></a></li>
      <li class="breadcrumb-item active" aria-current="page"><?= ucfirst(htmlspecialchars($info['type'])) . " Assessment: " . htmlspecialchars($info['title']) ?></li>
    </ol>
  </nav>

  <div class="card mb-4">
    <div class="card-body">
      <h4>Student: <?= htmlspecialchars($data['student_name']) ?></h4>
      <p class="text-muted">
        Assessment Type: <?= ucfirst(htmlspecialchars($info['type'])) ?> •
        Status: <?= ucfirst(htmlspecialchars($status)) ?> •
        <?php if ($status === 'submitted'): ?>
          Submitted on: <?= (new DateTime($submissionDate))->format('M d, Y h:i A') ?>
        <?php endif; ?>
      </p>
    </div>
  </div>

  <h5>Submission Files & Text</h5>
  <?php if ($data['doc_path']): ?>
    <p>DOC: <a href="<?= htmlspecialchars($data['doc_path']) ?>" target="_blank"><?= basename($data['doc_path']) ?></a></p>
  <?php endif; ?>
  <?php if ($data['pdf_path']): ?>
    <p>PDF: <a href="<?= htmlspecialchars($data['pdf_path']) ?>" target="_blank"><?= basename($data['pdf_path']) ?></a></p>
  <?php endif; ?>
  <?php if (!empty($data['student_text'])): ?>
    <div class="border p-3 mb-4">
      <h5>Text Response</h5>
      <?= nl2br(htmlspecialchars($data['student_text'])) ?>
    </div>
  <?php endif; ?>

  <hr>
  <h5>Assessment Questions & Responses</h5>
  <?php foreach ($questions as $i => $q): ?>
    <div class="mb-4">
      <p><strong><?= $i+1 ?>. <?= htmlspecialchars($q['question_text']) ?></strong></p>
      <?php $resp = $responses[$q['id']] ?? []; ?>
      <?php if ($q['type'] === 'mcq'): ?>
        <ul>
        <?php foreach ($resp['options'] ?? [] as $optId): ?>
          <li><?= htmlspecialchars($resp['option_texts'][$optId] ?? '') ?></li>
        <?php endforeach; ?>
        </ul>
      <?php elseif (in_array($q['type'], ['fill_blank','short','long'])): ?>
        <p><?= nl2br(htmlspecialchars($resp['text'] ?? '')) ?></p>
      <?php elseif ($q['type'] === 'match'): ?>
        <table class="table table-sm">
          <thead><tr><th>Left</th><th>Matched Right</th></tr></thead>
          <tbody>
          <?php foreach ($resp['options'] ?? [] as $pairId): ?>
            <?php $pair = $resp['pairs'][$pairId] ?? null; ?>
            <?php if ($pair): ?>
            <tr><td><?= htmlspecialchars($pair['left']) ?></td><td><?= htmlspecialchars($pair['right']) ?></td></tr>
            <?php endif; ?>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>

</div>

<?php include 'footer.php'; ?>
