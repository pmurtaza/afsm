<?php
// grade_assessment.php
require 'mysql_config.php';
// only teachers/admins
if (empty($_SESSION['role']) || !in_array($_SESSION['role'], ['teacher','admin'])) {
  header('Location: dashboard.php'); exit;
}

$assessmentId = (int)($_GET['assessment_id'] ?? 0);
$studentId    = (int)($_GET['student_id']    ?? 0);
if (!$assessmentId || !$studentId) {
  echo "<div class='alert alert-warning'>Missing assessment or student.</div>";
  exit;
}
$userId = $_SESSION['user_id'];

// fetch submission ID
$stmt = $mysqli->prepare("
  SELECT id,status 
    FROM afsm_assessment_submissions 
   WHERE assessment_id=? AND student_id=?
");
$stmt->bind_param('ii',$assessmentId,$studentId);
$stmt->execute();
$stmt->bind_result($submissionId,$status);
if (!$stmt->fetch()) {
    include 'header.php';
    echo "<div class='alert alert-warning'>This student hasn't submitted yet.</div>";
    echo '<p><a href="view_submissions.php?assessment_id='.$assessmentId.'" class="btn btn-secondary">Back to Submissions</a></p>';
    include 'footer.php';
    exit;
  }
$stmt->close();

// fetch questions & student responses
$stmt = $mysqli->prepare("
  SELECT q.id,q.type,q.question_text,q.weight,q.allow_multiple,
         r.text_response,
         GROUP_CONCAT(ro.option_id) AS sel_opts
    FROM afsm_questions q
    LEFT JOIN afsm_responses r 
      ON r.submission_id=? AND r.question_id=q.id
    LEFT JOIN afsm_response_options ro
      ON ro.submission_id=? AND ro.question_id=q.id
   WHERE q.assessment_id=?
   GROUP BY q.id
   ORDER BY q.id
");
$stmt->bind_param('iii',$submissionId,$submissionId,$assessmentId);
$stmt->execute();
$questions = [];
foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
  // split selected options
  $row['selected'] = $row['sel_opts'] ? explode(',',$row['sel_opts']) : [];
  $questions[] = $row;
}
$stmt->close();

// handle grading POST
if ($_SERVER['REQUEST_METHOD']==='POST') {
  // upsert marks for each question
  $up = $mysqli->prepare("
    UPDATE afsm_responses
       SET obtained_marks=?
     WHERE submission_id=? AND question_id=?
  ");
  foreach ($questions as $q) {
    $qid   = $q['id'];
    $mark  = isset($_POST['marks'][$qid]) ? (float)$_POST['marks'][$qid] : 0;
    // for MCQ, compute automatically if not overridden
    if ($q['type']==='mcq' && !isset($_POST['override'][$qid])) {
      // fetch correct count & total
      $optStmt = $mysqli->prepare("
        SELECT COUNT(*) 
          FROM afsm_question_options 
         WHERE question_id=? AND is_correct=1
      ");
      $optStmt->bind_param('i',$qid);
      $optStmt->execute();
      $optStmt->bind_result($correctCount);
      $optStmt->fetch(); $optStmt->close();
      // count how many student selected correctly
      $sel = $q['selected'];
      $in = str_repeat('?,',count($sel)-1).'?';
      if ($sel) {
        $sql = "SELECT COUNT(*) FROM afsm_question_options 
                WHERE question_id=? AND is_correct=1
                  AND id IN ($in)";
        $optStmt = $mysqli->prepare($sql);
        $types  = 'i'.str_repeat('i',count($sel));
        $optStmt->bind_param($types,$qid,...$sel);
        $optStmt->execute();
        $optStmt->bind_result($got);
        $optStmt->fetch(); $optStmt->close();
      } else $got = 0;
      // score proportionally
      $mark = $correctCount 
            ? ($q['weight'] * $got / $correctCount) 
            : 0;
    }
    $up->bind_param('dii',$mark,$submissionId,$qid);
    $up->execute();
  }
  $up->close();
  // mark graded
  $g = $mysqli->prepare("
    UPDATE afsm_assignment_submissions 
       SET graded_by=?, grade_date=NOW() 
     WHERE id=?
  ");
  $g->bind_param('ii',$userId,$submissionId);
  $g->execute(); $g->close();
  echo '<div class="alert alert-success">Grades saved.</div>';
}

// render
include 'header.php';
?>
<h1>Grade Submission</h1>
<p>Assessment #<?= $assessmentId ?> • Student #<?= $studentId ?></p>

<form method="post">
  <?php foreach ($questions as $i=>$q): ?>
    <div class="mb-4">
      <p><strong><?= $i+1 ?>. <?= htmlspecialchars($q['question_text']) ?> 
         (Max <?= $q['weight'] ?> pts)</strong></p>
      <?php if ($q['type']==='mcq'): 
        // show selected options
        $optStmt = $mysqli->prepare("
          SELECT option_text FROM afsm_question_options 
           WHERE question_id=? AND id IN (".str_repeat('?,',count($q['selected'])-1)."?)");
        if ($q['selected']) {
          $types='i'.str_repeat('i',count($q['selected']));
          $optStmt->bind_param($types,$q['id'],...$q['selected']);
          $optStmt->execute();
          $texts = $optStmt->get_result()->fetch_all(MYSQLI_ASSOC);
          foreach ($texts as $t) echo "<div>".htmlspecialchars($t['option_text'])."</div>";
          $optStmt->close();
        } else {
          echo "<div><em>No selection</em></div>";
        }
        ?>
        <div class="form-check form-switch mt-2">
          <input class="form-check-input" type="checkbox" 
                 name="override[<?= $q['id'] ?>]" id="ovr-<?= $q['id'] ?>">
          <label class="form-check-label" for="ovr-<?= $q['id'] ?>">
            Override auto-mark
          </label>
        </div>
      <?php elseif (in_array($q['type'],['short','long','fill_blank'])): ?>
        <div class="border p-2 mb-2">
          <?= nl2br(htmlspecialchars($q['text_response'] ?? '')) ?>
        </div>
      <?php elseif ($q['type']==='match'): 
        // list pairs
        $pairStmt = $mysqli->prepare("
          SELECT left_text,right_text 
            FROM afsm_match_pairs 
           WHERE question_id=? AND id IN (".str_repeat('?,',count($q['selected'])-1)."?)");
        if ($q['selected']) {
          $types='i'.str_repeat('i',count($q['selected']));
          $pairStmt->bind_param($types,$q['id'],...$q['selected']);
          $pairStmt->execute();
          foreach ($pairStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $p) {
            echo "<div>".htmlspecialchars($p['left_text'])." → ".htmlspecialchars($p['right_text'])."</div>";
          }
          $pairStmt->close();
        } else echo "<div><em>No matches</em></div>";
        ?>
      <?php endif; ?>
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

<?php include 'footer.php'; ?>
