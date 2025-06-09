<?php
// grade_assessment.php
require 'mysql_config.php';

// Only teachers/admins allowed
if (empty($_SESSION['role']) || !in_array($_SESSION['role'], ['teacher', 'admin'])) {
    header('Location: dashboard.php');
    exit;
}

// Get IDs from GET or POST (preserve on POST)
$assessmentId = (int)($_REQUEST['assessment_id'] ?? 0);
$studentId = (int)($_REQUEST['student_id'] ?? 0);

if (!$assessmentId || !$studentId) {
    echo "<div class='alert alert-warning'>Missing assessment or student.</div>";
    exit;
}

$userId = $_SESSION['user_id'];

// Fetch submission info
$stmt = $mysqli->prepare("
  SELECT id, status, submitted_date
    FROM afsm_assessment_submissions
   WHERE assessment_id=? AND student_id=?
");
$stmt->bind_param('ii', $assessmentId, $studentId);
$stmt->execute();
$stmt->bind_result($submissionId, $status, $submissionDate);
if (!$stmt->fetch()) {
    include 'header.php';
    echo "<div class='alert alert-warning'>This student hasn't submitted yet.</div>";
    echo '<p><a href="view_submissions.php?assessment_id=' . $assessmentId . '" class="btn btn-secondary">Back to Submissions</a></p>';
    include 'footer.php';
    exit;
}
$stmt->close();

// Fetch the uploaded file for the given question
// $stmt = $mysqli->prepare("
//     SELECT file_path
//     FROM afsm_responses
//     WHERE submission_id = ? AND question_id = ? AND file_path IS NOT NULL
// ");
// $stmt->bind_param('ii', $submissionId, $q['id']);
// $stmt->execute();
// $fileResult = $stmt->get_result();
// $file = $fileResult->fetch_assoc();
// $stmt->close();
// if ($file) {
//     // Display the uploaded file
//     echo "<p>Uploaded File: <a href='" . htmlspecialchars($file['file_path']) . "' target='_blank'>View File</a></p>";
// }
// Fetch batch, assessment and student info
$stmt = $mysqli->prepare("
  SELECT a.type, a.title, b.name as batch_name, b.id as batch_id,
         u.name as student_name
    FROM afsm_assessments a
    JOIN afsm_batches b ON a.batch_id = b.id
    JOIN afsm_users u ON u.id = ?
   WHERE a.id = ?
");
$stmt->bind_param('ii', $studentId, $assessmentId);
$stmt->execute();
$info = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Fetch questions and student responses
$stmt = $mysqli->prepare("
  SELECT q.id, q.type, q.question_text, q.weight, q.allow_multiple,
         r.text_response,
         GROUP_CONCAT(ro.option_id) AS sel_opts,
         r.obtained_marks
    FROM afsm_questions q
    LEFT JOIN afsm_responses r 
      ON r.submission_id = ? AND r.question_id = q.id
    LEFT JOIN afsm_response_options ro
      ON ro.submission_id = ? AND ro.question_id = q.id
   WHERE q.assessment_id = ?
   GROUP BY q.id
   ORDER BY q.id
");
$stmt->bind_param('iii', $submissionId, $submissionId, $assessmentId);
$stmt->execute();
$questions = [];
foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
    $row['selected'] = $row['sel_opts'] ? explode(',', $row['sel_opts']) : [];
    $questions[] = $row;
}
$stmt->close();

// Handle grading POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $updateStmt = $mysqli->prepare("
      UPDATE afsm_responses
         SET obtained_marks=?
       WHERE submission_id=? AND question_id=?
    ");
    $insertStmt = $mysqli->prepare("
      INSERT INTO afsm_responses (submission_id, question_id, obtained_marks)
      VALUES (?, ?, ?)
    ");

    foreach ($questions as $q) {
        $qid = $q['id'];
        $mark = isset($_POST['marks'][$qid]) ? (float)$_POST['marks'][$qid] : 0;

        // MCQ auto calculation skipped here — can add if needed

        // Try UPDATE first
        $updateStmt->bind_param('dii', $mark, $submissionId, $qid);
        $updateStmt->execute();

        // If no rows updated, insert new
        if ($updateStmt->affected_rows === 0) {
            $insertStmt->bind_param('iid', $submissionId, $qid, $mark);
            $insertStmt->execute();
        }
    }
    $updateStmt->close();
    $insertStmt->close();

    // Redirect to avoid form resubmission, can add a success message in session or GET param
    header("Location: view_submissions.php?assessment_id=$assessmentId&student_id=$studentId&graded=1&saved=1");
    exit;
}

include 'header.php';
?>
<div class="container mt-4" style="max-width: 900px;">
  <nav aria-label="breadcrumb">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="view_submissions.php?assessment_id=<?= $assessmentId ?>"><?= 'Batch: ' . htmlspecialchars($info['batch_name']) ?></a></li>
      <li class="breadcrumb-item active"><?= ucfirst($info['type']) . ' Assessment: ' . htmlspecialchars($info['title']) ?></li>
    </ol>
  </nav>

  <div class="card mb-4">
    <div class="card-body">
      <h4>Student: <?= htmlspecialchars($info['student_name']) ?></h4>
      <p class="text-muted">
        Assessment Type: <?= ucfirst($info['type']) ?> • 
        Status: <?= ucfirst($status) ?> •
        <?php if ($status === 'submitted'): ?>
          Submitted on: <?= (new DateTime($submissionDate))->format('M d, Y h:i A') ?>
        <?php endif; ?>
      </p>
      <?php if (isset($_GET['saved'])): ?>
        <div class="alert alert-success">Grades saved successfully!</div>
      <?php endif; ?>
    </div>
  </div>

  <h5>Assessment Questions & Responses</h5>

  <form method="post">
    <?php foreach ($questions as $i => $q): ?>
      <div class="mb-4">
        <p><strong><?= ($i + 1) ?>. <?= htmlspecialchars($q['question_text']) ?> (Max <?= $q['weight'] ?> pts)</strong></p>
        <?php if ($q['type'] === 'mcq'):
            // Show selected options text
            if ($q['selected']):
                $placeholders = implode(',', array_fill(0, count($q['selected']), '?'));
                $optStmt = $mysqli->prepare("
                  SELECT option_text FROM afsm_question_options
                  WHERE question_id = ? AND id IN ($placeholders)
                ");
                $types = 'i' . str_repeat('i', count($q['selected']));
                $optStmt->bind_param($types, $q['id'], ...$q['selected']);
                $optStmt->execute();
                $texts = $optStmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $optStmt->close();
                foreach ($texts as $t) {
                    echo "<div>" . htmlspecialchars($t['option_text']) . "</div>";
                }
            else:
                echo "<div><em>No selection</em></div>";
            endif;
        ?>
        <div class="form-check form-switch mt-2">
          <input class="form-check-input" type="checkbox"
                 name="override[<?= $q['id'] ?>]" id="ovr-<?= $q['id'] ?>">
          <label class="form-check-label" for="ovr-<?= $q['id'] ?>">Override auto-mark</label>
        </div>
        <?php elseif (in_array($q['type'], ['short', 'long', 'fill_blank'])): ?>
          <div class="border p-2 mb-2"><?= nl2br(htmlspecialchars($q['text_response'] ?? '')) ?></div>
        <?php elseif ($q['type'] === 'match'): 
          if ($q['selected']):
            $placeholders = implode(',', array_fill(0, count($q['selected']), '?'));
            $pairStmt = $mysqli->prepare("
              SELECT left_text, right_text FROM afsm_match_pairs
              WHERE question_id = ? AND id IN ($placeholders)
            ");
            $types = 'i' . str_repeat('i', count($q['selected']));
            $pairStmt->bind_param($types, $q['id'], ...$q['selected']);
            $pairStmt->execute();
            foreach ($pairStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $p) {
                echo "<div>" . htmlspecialchars($p['left_text']) . " → " . htmlspecialchars($p['right_text']) . "</div>";
            }
            $pairStmt->close();
          else:
            echo "<div><em>No matches</em></div>";
          endif;
        endif; ?>
        <div class="mt-2">
          <label>Score (0–<?= $q['weight'] ?>)</label>
          <input type="number" name="marks[<?= $q['id'] ?>]"
                 min="0" max="<?= $q['weight'] ?>" step="0.1"
                 class="form-control"
                 value="<?= htmlspecialchars($q['obtained_marks'] ?? '') ?>">
        </div>
      </div>
    <?php endforeach; ?>
    <button class="btn btn-primary">Save Grades</button>
  </form>
</div>

<?php include 'footer.php'; ?>
