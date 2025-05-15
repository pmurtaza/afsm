<?php
// view_submission_detail.php
require 'mysql_config.php';
// Only teachers/admins
if (empty($_SESSION['role']) || !in_array($_SESSION['role'], ['teacher','admin'])) {
    header('Location: dashboard.php'); exit;
}

$submissionId = isset($_GET['submission_id']) ? (int)$_GET['submission_id'] : null;
if (!$submissionId) {
    header('Location: view_submissions.php'); exit;
}

// Fetch submission record
$stmt = $mysqli->prepare(
    "SELECT sub.assessment_id, sub.student_id, sub.submitted_date,
            sub.status, sub.doc_path, sub.pdf_path, sub.text_response AS student_text
     FROM afsm_assessment_submissions sub
     LEFT JOIN afsm_responses r ON sub.id=r.submission_id
     WHERE sub.id = ? LIMIT 1"
);
$stmt->bind_param('i', $submissionId);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$data) {
    echo "<div class='alert alert-warning'>Submission not found.</div>";
    exit;
}

// Fetch student name
$stmt = $mysqli->prepare("SELECT name FROM afsm_users WHERE id=?");
$stmt->bind_param('i', $data['student_id']);
$stmt->execute();
$data['student_name'] = $stmt->get_result()->fetch_assoc()['name'];
$stmt->close();

// Fetch questions for this assessment
$qstmt = $mysqli->prepare(
    "SELECT id, type, question_text
     FROM afsm_questions
     WHERE assessment_id = ? ORDER BY id"
);
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
$stmt->bind_param('i', $submissionId);
$stmt->execute();
foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
    $responses[$r['question_id']] = ['text' => $r['text_response']];
}
$stmt->close();

// Preload selected options (MCQ & match)
$stmt = $mysqli->prepare(
    "SELECT question_id, option_id
     FROM afsm_response_options
     WHERE submission_id = ?"
);
$stmt->bind_param('i', $submissionId);
$stmt->execute();
foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $ro) {
    $responses[$ro['question_id']]['options'][] = $ro['option_id'];
}
$stmt->close();

// Fetch option texts and pairs text map
foreach ($questions as $q) {
    if ($q['type'] === 'mcq') {
        // map option_id to text
        $optStmt = $mysqli->prepare("SELECT id, option_text FROM afsm_question_options WHERE question_id=?");
        $optStmt->bind_param('i', $q['id']);
        $optStmt->execute();
        $opts = $optStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $optStmt->close();
        $optionMap = [];
        foreach ($opts as $o) $optionMap[$o['id']] = $o['option_text'];
        $responses[$q['id']]['option_texts'] = $optionMap;
    }
    if ($q['type'] === 'match') {
        $pairStmt = $mysqli->prepare("SELECT id, left_text, right_text FROM afsm_match_pairs WHERE question_id=?");
        $pairStmt->bind_param('i', $q['id']);
        $pairStmt->execute();
        $pairs = $pairStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $pairStmt->close();
        $pairMap = [];
        foreach ($pairs as $p) $pairMap[$p['id']] = ['left'=>$p['left_text'],'right'=>$p['right_text']];
        $responses[$q['id']]['pairs'] = $pairMap;
    }
}

$page_title = 'Submission Detail';
include 'header.php';
?>
<h1>Submission Details</h1>
<p><strong>Student:</strong> <?= htmlspecialchars($data['student_name']) ?></p>
<p><strong>Submitted At:</strong> <?= $data['submitted_date'] ?></p>
<p><strong>Status:</strong> <?= ucfirst($data['status']) ?></p>
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

<?php include 'footer.php'; ?>
