<?php
// take_assessment.php
require 'mysql_config.php';
// Only students
if (empty($_SESSION['role']) || !in_array($_SESSION['role'], ['student', 'admin'])) {
  header('Location: dashboard.php');
  exit;
}
$studentId = $_SESSION['user_id'];
$assessmentId = isset($_GET['assessment_id']) ? (int)$_GET['assessment_id'] : null;
if (!$assessmentId) {
  header('Location: student_assessments.php');
  exit;
}

// Fetch assessment
$stmt = $mysqli->prepare("SELECT a.id, a.title, a.instructions, a.batch_id FROM afsm_assessments a WHERE a.id = ?");
$stmt->bind_param('i', $assessmentId);
$stmt->execute();
$assessment = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$assessment) {
  header('Location: student_assessments.php');
  exit;
}

// Fetch questions
$stmt = $mysqli->prepare(
  "SELECT id, type, question_text, weight, allow_multiple
     FROM afsm_questions
     WHERE assessment_id = ?
     ORDER BY id"
);
$stmt->bind_param('i', $assessmentId);
$stmt->execute();
$questions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch options and pairs grouped
$options = [];
$pairs = [];
foreach ($questions as $q) {
  if ($q['type'] === 'mcq') {
    $stmt = $mysqli->prepare("SELECT id, option_text FROM afsm_question_options WHERE question_id = ?");
    $stmt->bind_param('i', $q['id']);
    $stmt->execute();
    $options[$q['id']] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
  } elseif ($q['type'] === 'match') {
    $stmt = $mysqli->prepare("SELECT id, left_text, right_text FROM afsm_match_pairs WHERE question_id = ?");
    $stmt->bind_param('i', $q['id']);
    $stmt->execute();
    $pairs[$q['id']] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
  }
}

// Handle submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // create submission
  $stmt = $mysqli->prepare(
    "INSERT INTO afsm_assessment_submissions (assessment_id, student_id, status) VALUES (?, ?, 'submitted')"
  );
  $stmt->bind_param('ii', $assessmentId, $studentId);
  $stmt->execute();
  $subId = $mysqli->insert_id;
  $stmt->close();

  // insert responses
  foreach ($questions as $q) {
    $qid = $q['id'];
    // text/short/long/fill
    $text = isset($_POST['resp'][$qid]) ? trim($_POST['resp'][$qid]) : null;
    $stmt = $mysqli->prepare(
      "INSERT INTO afsm_responses (submission_id, question_id, text_response) VALUES (?, ?, ?)"
    );
    $stmt->bind_param('iis', $subId, $qid, $text);
    $stmt->execute();
    $stmt->close();
    // MCQ selected options
    if ($q['type'] === 'mcq') {
      $selected = $_POST['opt'][$qid] ?? [];
      if (!is_array($selected)) $selected = [$selected];
      $stmt = $mysqli->prepare(
        "INSERT INTO afsm_response_options (submission_id, question_id, option_id) VALUES (?, ?, ?)"
      );
      foreach ($selected as $optId) {
        $stmt->bind_param('iii', $subId, $qid, $optId);
        $stmt->execute();
      }
      $stmt->close();
    }
    // match pairs
    if ($q['type'] === 'match') {
      $matches = $_POST['match'][$qid] ?? [];
      $stmt = $mysqli->prepare(
        "INSERT INTO afsm_response_options (submission_id, question_id, option_id) VALUES (?, ?, ?)"
      );
      foreach ($matches as $pairId => $matchedRightId) {
        if ($matchedRightId) {
          $stmt->bind_param('iii', $subId, $qid, $matchedRightId);
          $stmt->execute();
        }
      }
      $stmt->close();
    }
  }
  header('Location: student_assessments.php?submitted=1');
  exit;
}

$page_title = 'Take Assessment';
include 'header.php';
?>
<div>
  <h1><?= htmlspecialchars($assessment['title']) ?></h1>
  <p><?= nl2br(htmlspecialchars($assessment['instructions'])) ?></p>
  <form method="post">
    <?php foreach ($questions as $i => $q): ?>
      <div class="mb-4">
        <p><strong><?= $i + 1 ?>. <?= htmlspecialchars($q['question_text']) ?> (<?= $q['weight'] ?> pts)</strong></p>
        <?php if ($q['type'] === 'mcq'): ?>
          <?php foreach ($options[$q['id']] as $opt): ?>
            <div class="form-check">
              <input class="form-check-input" type="<?= $q['allow_multiple'] ? 'checkbox' : 'radio' ?>"
                name="opt[<?= $q['id'] ?>]<?= $q['allow_multiple'] ? '[]' : '' ?>"
                id="opt-<?= $opt['id'] ?>" value="<?= $opt['id'] ?>">
              <label class="form-check-label" for="opt-<?= $opt['id'] ?>"><?= htmlspecialchars($opt['option_text']) ?></label>
            </div>
          <?php endforeach; ?>
        <?php elseif ($q['type'] === 'fill_blank' || $q['type'] === 'short' || $q['type'] === 'long'): ?>
          <textarea name="resp[<?= $q['id'] ?>]" class="form-control" rows="3"></textarea>
        <?php elseif ($q['type'] === 'match'): ?>
          <div class="row">
            <div class="col-6">
              <?php foreach ($pairs[$q['id']] as $p): ?>
                <p><?= htmlspecialchars($p['left_text']) ?></p>
              <?php endforeach; ?>
            </div>
            <div class="col-6">
              <?php foreach ($pairs[$q['id']] as $p): ?>
                <select name="match[<?= $q['id'] ?>][<?= $p['id'] ?>]" class="form-select mb-2">
                  <option value="">-- match --</option>
                  <?php foreach ($pairs[$q['id']] as $r): ?>
                    <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['right_text']) ?></option>
                  <?php endforeach; ?>
                </select>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>
        <?php if ($q['type'] === 'file_upload'): ?>
          <label>Upload Files (max <?= $q['max_file_count'] ?? 1 ?> files)</label>
          <input type="file" name="file_upload_<?= $q['id'] ?>[]" multiple
            accept="<?= implode(',', array_map(fn($ext) => '.' . trim($ext), explode(',', $q['allowed_file_types'] ?? ''))) ?>"
            <?= ($q['max_file_count'] ?? 1) > 1 ? '' : 'multiple' ?>>
          <small>Max size per file: <?= $q['max_file_size_mb'] ?? 5 ?> MB</small>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
    <button type="submit" class="btn btn-primary">Submit Assessment</button>
  </form>
</div>

<?php include 'footer.php'; ?>