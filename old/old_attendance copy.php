<?php
require 'mysql_config.php';
// Restrict to teachers
if (!isset($_SESSION['user_id'], $_SESSION['role']) || !in_array($_SESSION['role'], ['teacher','admin'])) {
    header('Location: login.php');
    exit;
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['status'])) {
    $teacherId = $_SESSION['user_id'];
    // Insert or update attendance with audit fields
    $stmt = $mysqli->prepare(
        "INSERT INTO afsm_attendance
            (session_id, user_id, status, teacher_id, created_by)
         VALUES (?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            status = VALUES(status),
            teacher_id = VALUES(teacher_id),
            updated_by = VALUES(created_by),
            updated_date = NOW()"
    );
    if (!$stmt) {
      die("Attendance INSERT prepare failed: ({$mysqli->errno}) {$mysqli->error}");
    }
    //
    foreach ($_POST['status'] as $uid => $sessions) {
        foreach ($sessions as $sid => $stat) {
            $s = ($stat === 'present' ? 'present' : 'absent');
            $stmt->bind_param('iisis',
                $sid,
                $uid,
                $s,
                $teacherId,
                $teacherId
            );
            $stmt->execute();
        }
    }
    $stmt->close();
    // $message = 'Attendance saved by Teacher #' . $teacherId;
    $message = 'Attendance saved by ' . htmlspecialchars($_SESSION['username']) . '!';

}

// Fetch batches
$batches = $mysqli->query("SELECT id, name FROM afsm_batches ORDER BY name");
$selectedBatch = $_GET['batch_id'] ?? null;

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
         JOIN afsm_batch_students bs ON u.id = bs.student_id
         WHERE bs.batch_id = ?
           AND u.role = 'student'
         ORDER BY u.name"
    );
    $stmt->bind_param('i', $selectedBatch);
    $stmt->execute();
    $students = $stmt->get_result();
    $stmt->close();
}

// Fetch existing attendance
$existing = [];
foreach ($sessions as $sess) {
    $stmt = $mysqli->prepare(
        "SELECT user_id, status
         FROM afsm_attendance
         WHERE session_id = ?"
    );
    $stmt->bind_param('i', $sess['id']);
    $stmt->execute();
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $existing[$row['user_id']][$sess['id']] = $row['status'];
    }
    $stmt->close();
}

$page_title = 'Attendance';
include 'header.php';
?>
<div class="card mb-4">
  <div class="card-body">
    <h2 class="card-title">Mark Attendance</h2>
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
                <th class="text-center">Session <?= $sess['session_no'] ?></th>
              <?php endforeach; ?>
              <th class="text-center">Total P</th>
              <th class="text-center">Total A</th>
              <th class="text-center">Score</th>
              <th class="text-center">%</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($st = $students->fetch_assoc()): 
                $p = $a = 0;
                $total = count($sessions);
            ?>
            <tr>
              <td><?= htmlspecialchars($st['name']) ?></td>
              <?php foreach ($sessions as $sess):
                $marked = isset($existing[$st['id']][$sess['id']]);
                $stat   = $existing[$st['id']][$sess['id']] ?? null;
                if ($marked) {
                    $stat === 'present' ? $p++ : $a++;
                }
              ?>
              <td class="text-center text-nowrap">
                <div class="form-check form-check-inline">
                  <input class="form-check-input" type="radio"
                         name="status[<?= $st['id'] ?>][<?= $sess['id'] ?>]"
                         id="p-<?= $st['id'] ?>-<?= $sess['id'] ?>"
                         value="present"
                         <?= ($marked && $stat === 'present') ? 'checked' : '' ?>
                         <?= $marked ? 'disabled' : '' ?>>
                  <label class="form-check-label" for="p-<?= $st['id'] ?>-<?= $sess['id'] ?>">P</label>
                </div>
                <div class="form-check form-check-inline">
                  <input class="form-check-input" type="radio"
                         name="status[<?= $st['id'] ?>][<?= $sess['id'] ?>]"
                         id="a-<?= $st['id'] ?>-<?= $sess['id'] ?>"
                         value="absent"
                         <?= ($marked && $stat === 'absent') ? 'checked' : '' ?>
                         <?= $marked ? 'disabled' : '' ?>>
                  <label class="form-check-label" for="a-<?= $st['id'] ?>-<?= $sess['id'] ?>">A</label>
                </div>
              </td>
              <?php endforeach; ?>
              <td class="text-center"><?= $p ?></td>
              <td class="text-center"><?= $a ?></td>
              <td class="text-center"><?= $p * 10 ?></td>
              <td class="text-center"><?= $total ? round($p / $total * 100, 1) . '%' : '0%' ?></td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
      <button class="btn btn-primary mt-3" <?= $message ? 'disabled' : '' ?>>Save Attendance</button>
    </form>
    <?php endif; ?>
  </div>
</div>
<?php include 'footer.php'; ?>
