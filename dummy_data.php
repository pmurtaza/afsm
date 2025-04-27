<?php
// mysql_dummy_data.php

// 1) CONNECT
$host     = '127.0.0.1';
$username = 'root';
$password = '';
$database = 'your_database';
$port     = 3306;

$mysqli = new mysqli($host, $username, $password, $database, $port);
if ($mysqli->connect_errno) {
    die("Connect failed: ({$mysqli->connect_errno}) {$mysqli->connect_error}");
}

// 2) DUMMY INSERTS

// 2A) users
$users = [
    ['Alice Student',  'alice@student.com',  'pass123', 'student'],
    ['Bob Student',    'bob@student.com',    'pass123', 'student'],
    ['Cathy Teacher',  'cathy@school.com',   'teach123','teacher'],
    ['Zed Admin',      'zed@admin.com',      'admin123','admin'],
];
$stmt = $mysqli->prepare("
    INSERT INTO users (name, email, password, role)
    VALUES (?, ?, ?, ?)
");
foreach ($users as $u) {
    // For simplicity, storing plaintext; in prod, use password_hash()
    $stmt->bind_param('ssss', $u[0], $u[1], $u[2], $u[3]);
    $stmt->execute() or die("User insert failed: " . $stmt->error);
}
$stmt->close();
echo "Inserted users.\n";

// 2B) batches
$batches = [
    ['Batch 1', 6],
    ['Batch 2', 2],
];
$stmt = $mysqli->prepare("
    INSERT INTO batches (name, total_sessions)
    VALUES (?, ?)
");
foreach ($batches as $b) {
    $stmt->bind_param('si', $b[0], $b[1]);
    $stmt->execute() or die("Batch insert failed: " . $stmt->error);
}
$stmt->close();
echo "Inserted batches.\n";

// 2C) sessions
// Fetch batch IDs back
$batchMap = [];
$res = $mysqli->query("SELECT id,name,total_sessions FROM batches");
while ($row = $res->fetch_assoc()) {
    $batchMap[$row['name']] = [
        'id'             => (int)$row['id'],
        'total_sessions' => (int)$row['total_sessions']
    ];
}
$res->free();

$stmt = $mysqli->prepare("
    INSERT INTO sessions (batch_id, session_no)
    VALUES (?, ?)
");
foreach ($batchMap as $name => $info) {
    for ($i = 1; $i <= $info['total_sessions']; $i++) {
        $stmt->bind_param('ii', $info['id'], $i);
        $stmt->execute() or die("Session insert failed: " . $stmt->error);
    }
}
$stmt->close();
echo "Inserted sessions.\n";

// 2D) attendance
// We'll mark the first student present in session 1 of each batch
// Fetch one student user_id
$res = $mysqli->query("SELECT id FROM users WHERE role='student' ORDER BY id LIMIT 1");
$studentId = $res->fetch_assoc()['id'];
$res->free();

// Fetch one session_id per batch (session_no=1)
$res = $mysqli->query("
    SELECT s.id AS session_id, b.name AS batch_name
    FROM sessions s
    JOIN batches b ON s.batch_id = b.id
    WHERE s.session_no = 1
");
$sessionIds = [];
while ($row = $res->fetch_assoc()) {
    $sessionIds[] = (int)$row['session_id'];
}
$res->free();

$stmt = $mysqli->prepare("
    INSERT INTO attendance (session_id, user_id, status)
    VALUES (?, ?, ?)
");
foreach ($sessionIds as $sid) {
    $status = 'present';
    $stmt->bind_param('iis', $sid, $studentId, $status);
    $stmt->execute() or die("Attendance insert failed: " . $stmt->error);
}
$stmt->close();
echo "Inserted attendance records.\n";

$mysqli->close();
