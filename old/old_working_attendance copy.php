<?php
require 'mysql_config.php';
if (!isset($_SESSION['user_id'], $_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
  header('Location: login.php');
  exit;
}
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['status']) && is_array($_POST['status'])) {
  $stmt = $mysqli->prepare(
    "INSERT INTO afsm_attendance (session_id,user_id,status) VALUES (?,?,?) ON DUPLICATE KEY UPDATE status=VALUES(status)"
  );
  foreach ($_POST['status'] as $uid => $sessions) {
    foreach ($sessions as $sid => $stat) {
      $s = ($stat === 'present' ? 'present' : 'absent');
      $stmt->bind_param('iis', $sid, $uid, $s);
      $stmt->execute();
    }
  }
  $stmt->close();
  $message = 'Saved!';
}
$batches = $mysqli->query("SELECT id,name FROM afsm_batches");
$selectedBatch = $_GET['batch_id'] ?? null;
$sessions = [];
if ($selectedBatch) {
  $stmt = $mysqli->prepare("SELECT id,session_no FROM afsm_sessions WHERE batch_id=? ORDER BY session_no");
  $stmt->bind_param('i', $selectedBatch);
  $stmt->execute();
  $sessions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
// old: pulls ALL students
// $students = $selectedBatch ? $mysqli->query("SELECT id,name FROM afsm_users WHERE role='student'") : [];
if ($selectedBatch) {
  $stmt = $mysqli->prepare(
    "SELECT u.id, u.name
     FROM afsm_users u
     JOIN afsm_batch_students bs
       ON u.id = bs.student_id
     WHERE bs.batch_id = ?
       AND u.role = 'student'
     ORDER BY u.name"
  );
  $stmt->bind_param('i', $selectedBatch);
  $stmt->execute();
  $students = $stmt->get_result();
  $stmt->close();
} else {
  $students = [];
}
//
$existing = [];
if ($sessions) {
  foreach ($sessions as $s) {
    $stmt = $mysqli->prepare("SELECT user_id,status FROM afsm_attendance WHERE session_id=?");
    $stmt->bind_param('i', $s['id']);
    $stmt->execute();
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
      $existing[$r['user_id']][$s['id']] = $r['status'];
    }
    $stmt->close();
  }
}
$page_title = 'Attendance';
include 'header.php';
?>
<div class="container">
  <h2>Attendance</h2>
  <?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
  <form method="get" class="mb-3">
    <select name="batch_id" onchange="this.form.submit()" class="form-select w-auto d-inline-block">
      <option value="">Select Batch</option>
      <?php while ($b = $batches->fetch_assoc()): ?>
        <option <?= $selectedBatch == $b['id'] ? 'selected' : '' ?> value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
      <?php endwhile; ?>
    </select>
  </form>
  <?php if ($selectedBatch && $sessions): ?>
    <form method="post">
      <input type="hidden" name="batch_id" value="<?= $selectedBatch ?>">
      <table class="table table-bordered">
        <thead>
          <tr>
            <th>Student</th><?php foreach ($sessions as $s): ?><th>Session <?= $s['session_no'] ?></th><?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php while ($st = $students->fetch_assoc()): ?><tr>
              <td><?= htmlspecialchars($st['name']) ?></td><?php foreach ($sessions as $s): ?>
                <?php
                                                            // Determine if this cell has already been marked
                                                            $cellMarked = isset($existing[$st['id']][$s['id']]);
                                                            $stat       = $existing[$st['id']][$s['id']] ?? null;
                ?>
                <td class="text-center">
                  <div class="form-check form-check-inline">
                    <input
                      class="form-check-input"
                      type="radio"
                      name="status[<?= $st['id'] ?>][<?= $s['id'] ?>]"
                      id="pres-<?= $st['id'] ?>-<?= $s['id'] ?>"
                      value="present"
                      <?= ($cellMarked && $stat === 'present') ? 'checked' : '' ?>
                      <?= $cellMarked ? 'disabled' : '' ?>>
                    <label class="form-check-label" for="pres-<?= $st['id'] ?>-<?= $s['id'] ?>">P</label>
                  </div>

                  <div class="form-check form-check-inline">
                    <input
                      class="form-check-input"
                      type="radio"
                      name="status[<?= $st['id'] ?>][<?= $s['id'] ?>]"
                      id="abs-<?= $st['id'] ?>-<?= $s['id'] ?>"
                      value="absent"
                      <?= ($cellMarked && $stat === 'absent') ? 'checked' : '' ?>
                      <?= $cellMarked ? 'disabled' : '' ?>>
                    <label class="form-check-label" for="abs-<?= $st['id'] ?>-<?= $s['id'] ?>">A</label>
                  </div>
                </td>

              <?php endforeach; ?>
            </tr><?php endwhile; ?>
        </tbody>
      </table>
      <button class="btn btn-primary" <?= $message ? 'disabled' : '' ?>>Save</button>
    </form>
  <?php endif; ?>
</div>
<?php include 'footer.php'; ?>