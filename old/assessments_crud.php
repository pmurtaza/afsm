<?php
// assessments_crud.php
require 'mysql_config.php';
$uid = $_SESSION['user_id'];

// CREATE / UPDATE
if ($_SERVER['REQUEST_METHOD']==='POST') {
  if ($_POST['action']==='create') {
    $stmt = $mysqli->prepare(
      "INSERT INTO afsm_assessments(batch_id,type,title,instructions,created_by)
       VALUES(?,?,?,?,?)"
    );
    $stmt->bind_param('isssi', $_POST['batch_id'], $_POST['type'], $_POST['title'], $_POST['instructions'], $uid);
  } else {
    $stmt = $mysqli->prepare(
      "UPDATE afsm_assessments
         SET batch_id=?, type=?, title=?, instructions=?, updated_by=?
       WHERE id=?"
    );
    $stmt->bind_param('isssii', $_POST['batch_id'], $_POST['type'], $_POST['title'], $_POST['instructions'], $uid, $_POST['id']);
  }
  $stmt->execute(); $stmt->close();
  header('Location: assessments_crud.php'); exit;
}

// DELETE
if (isset($_GET['delete'])) {
  $stmt = $mysqli->prepare("DELETE FROM afsm_assessments WHERE id=?");
  $stmt->bind_param('i', $_GET['delete']); $stmt->execute(); $stmt->close();
  header('Location: assessments_crud.php'); exit;
}

// READ ALL
$result = $mysqli->query("SELECT a.*, b.name AS batch_name
  FROM afsm_assessments a
  JOIN afsm_batches b ON a.batch_id=b.id
  ORDER BY a.created_date DESC");
?>
<!-- UI -->
<!DOCTYPE html><html><head><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.4.1/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="p-4">
<div class="container">
  <h1>Assessments</h1>
  <button class="btn btn-success mb-3" data-bs-toggle="modal" data-bs-target="#editModal" onclick="openForm()">New Assessment</button>
  <table class="table table-striped">
    <thead><tr><th>ID</th><th>Batch</th><th>Type</th><th>Title</th><th></th></tr></thead>
    <tbody><?php while($r=$result->fetch_assoc()): ?>
      <tr>
        <td><?=$r['id']?></td>
        <td><?=htmlspecialchars($r['batch_name'])?></td>
        <td><?=$r['type']?></td>
        <td><?=htmlspecialchars($r['title'])?></td>
        <td>
          <button class="btn btn-sm btn-primary" onclick="openForm(<?=$r['id']?>)">Edit</button>
          <a href="?delete=<?=$r['id']?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete?')">Del</a>
        </td>
      </tr>
    <?php endwhile; ?></tbody>
  </table>
</div>

<!-- Modal Form -->
<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content">
    <form method="post">
      <div class="modal-header"><h5>Edit Assessment</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <input type="hidden" name="id" id="aid">
        <input type="hidden" name="action" id="act">
        <div class="mb-2"><label>Batch ID</label><input type="number" class="form-control" name="batch_id" id="batch_id"></div>
        <div class="mb-2"><label>Type</label><select class="form-select" name="type" id="type"><option>pre</option><option>post</option><option>final</option></select></div>
        <div class="mb-2"><label>Title</label><input class="form-control" name="title" id="title"></div>
        <div class="mb-2"><label>Instructions</label><textarea class="form-control" name="instructions" id="instructions"></textarea></div>
      </div>
      <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Close</button><button type="submit" class="btn btn-primary">Save</button></div>
    </form>
  </div></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.4.1/dist/js/bootstrap.bundle.min.js"></script>
<script>
function openForm(id=0) {
  document.getElementById('act').value = id? 'update' : 'create';
  if (id) {
    // fetch row data via AJAX (not shown) then fill form
  } else {
    document.getElementById('aid').value = '';
    ['batch_id','type','title','instructions'].forEach(e=>document.getElementById(e).value='');
  }
  var m = new bootstrap.Modal(document.getElementById('editModal'));
  m.show();
}
</script>
</body></html>