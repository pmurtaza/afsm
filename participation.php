<!-- -- participation.php -- -->
<?php
require 'mysql_config.php';
// Restrict access to teachers and admins
if (!isset($_SESSION['user_id'], $_SESSION['role']) || !in_array($_SESSION['role'], ['teacher','admin'])) {
    header('Location: login.php');
    exit;
}

$message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['participation'])) {
    $teacherId = $_SESSION['user_id'];
    $stmt = $mysqli->prepare(
        "INSERT INTO afsm_participation
            (session_id, user_id, score, teacher_id, created_by)
         VALUES (?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            score        = VALUES(score),
            teacher_id   = VALUES(teacher_id),
            updated_by   = VALUES(teacher_id),
            updated_date = NOW()"
    );
    if (!$stmt) die("Prepare failed: ({$mysqli->errno}) {$mysqli->error}");

    foreach ($_POST['participation'] as $userId => $sessions) {
        foreach ($sessions as $sessionId => $scoreInput) {
            // Skip blank inputs so we only insert marked cells
            if ($scoreInput === '' || !is_numeric($scoreInput)) {
                continue;
            }
            $score = (int)$scoreInput;
            $stmt->bind_param('iiiii', $sessionId, $userId, $score, $teacherId, $teacherId);
            $stmt->execute();
        }
    }
    $stmt->close();
    $message = 'Participation saved by ' . htmlspecialchars($_SESSION['username']) . '!';
}

// Fetch batches
// $batches = $mysqli->query("SELECT id, name FROM afsm_batches ORDER BY name");
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
$selectedBatch = $_GET['batch_id'] ?? null;

// Fetch batch-specific scoring options
$scoreOptions = [];
if ($selectedBatch) {
    $stmt = $mysqli->prepare(
        "SELECT score
           FROM afsm_participation_scoring
          WHERE batch_id = ?
          ORDER BY score"
    );
    $stmt->bind_param('i', $selectedBatch);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $scoreOptions[] = (int)$row['score'];
    }
    $stmt->close();
}

// Fetch sessions for selected batch
$sessions = [];
if ($selectedBatch) {
    $stmt = $mysqli->prepare(
        "SELECT id, session_no
         FROM afsm_sessions
         WHERE batch_id = ?
         ORDER BY session_no"
    );
    $stmt->bind_param('i', $selectedBatch);
    $stmt->execute();
    $sessions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Fetch students linked to this batch
$students = [];
if ($selectedBatch) {
    $stmt = $mysqli->prepare(
        "SELECT u.id, u.name
         FROM afsm_users u
         JOIN afsm_batch_students bs ON u.id = bs.user_id 
         WHERE bs.batch_id = ?
           AND bs.role = 'student'
         ORDER BY u.name"
    );
    $stmt->bind_param('i', $selectedBatch);
    $stmt->execute();
    $students = $stmt->get_result();
    $stmt->close();
}

// Fetch existing participation in one query for the batch
$existing = [];
if ($selectedBatch && !empty($sessions)) {
    $stmt = $mysqli->prepare(
        "SELECT p.session_id, p.user_id, p.score
         FROM afsm_participation p
         JOIN afsm_sessions    s ON p.session_id = s.id
         WHERE s.batch_id = ?"
    );
    $stmt->bind_param('i', $selectedBatch);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $existing[(int)$row['user_id']][(int)$row['session_id']] = (int)$row['score'];
    }
    $stmt->close();
}

// Render page
$page_title = 'AFSM - Participation';
include 'header.php';
?>
<div class="card mb-4">
  <div class="card-body">
    <h2 class="card-title">Mark Participation</h2>
    <?php if ($message): ?>
      <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <form method="get" class="row g-3 mb-4 align-items-center">
      <div class="col-auto">
        <label class="col-form-label">Batch:</label>
      </div>
      <div class="col-auto">
        <select name="batch_id" class="form-select" onchange="this.form.submit()">
          <option value="">Select Batch</option>
          <?php while ($b = $batches->fetch_assoc()): ?>
            <option value="<?= $b['id'] ?>" <?= $selectedBatch == $b['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($b['name']) ?>
            </option>
          <?php endwhile; ?>
        </select>
      </div>
    </form>

    <?php if ($selectedBatch && $sessions): ?>
    <form method="post">
      <input type="hidden" name="batch_id" value="<?= $selectedBatch ?>">
      <div class="table-responsive">
        <table class="table table-striped table-hover align-middle">
          <thead class="table-light">
            <tr>
              <th>Student</th>
              <?php foreach ($sessions as $sess): ?>
                <th class="text-center">Session <?= htmlspecialchars($sess['session_no']) ?></th>
              <?php endforeach; ?>
              <th class="text-center">Avg Score</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($st = $students->fetch_assoc()): 
                $sum   = 0;
                $count = count($sessions);
            ?>
            <tr>
              <td><?= htmlspecialchars($st['name']) ?></td>
              <?php foreach ($sessions as $sess):
                $sid    = $sess['id'];
                $marked = isset($existing[$st['id']][$sid]);
                $val    = $marked ? $existing[$st['id']][$sid] : null;
                if ($marked) $sum += $val;
              ?>
              <td class="text-center">
                <select name="participation[<?= $st['id'] ?>][<?= $sid ?>]"
                        class="form-select form-select-sm"
                        <?= $marked ? 'disabled' : '' ?>>
                  <option value=""></option>
                  <?php foreach ($scoreOptions as $opt): ?>
                    <option value="<?= $opt ?>"
                      <?= ($marked && $val === $opt) ? 'selected' : '' ?>>
                      <?= $opt ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </td>
              <?php endforeach; ?>
              <td class="text-center"><?= $count ? round($sum / $count, 1) : '0.0' ?></td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
      <button class="btn btn-primary mt-3" <?= $message ? 'disabled' : '' ?>>Save Participation</button>
    </form>
    <?php endif; ?>
  </div>
</div>

<?php include 'footer.php'; ?>
