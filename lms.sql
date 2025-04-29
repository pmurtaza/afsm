CREATE DATABASE IF NOT EXISTS lms;
USE lms;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100),
  email VARCHAR(100),
  password VARCHAR(255),
  role ENUM('student', 'teacher', 'admin')
);

INSERT INTO users (name, email, password, role) VALUES
('Admin User', 'admin@example.com', 'admin123', 'admin'),
('Student One', 'student1@example.com', 'pass123', 'student'),
('Teacher One', 'teacher1@example.com', 'teach123', 'teacher');

-- 1. Batches
CREATE TABLE afsm_batches (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(50) NOT NULL,        -- e.g. 'Batch 1', 'Batch 2'
  total_sessions INT NOT NULL              -- e.g. 6 or 2
);

-- 2. Sessions
CREATE TABLE afsm_sessions (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  batch_id   INT NOT NULL,
  session_no TINYINT NOT NULL,             -- 1 through total_sessions
  session_date DATE NULL,                  -- if you want to schedule dates
  FOREIGN KEY (batch_id) REFERENCES afsm_batches(id) ON DELETE CASCADE,
  UNIQUE (batch_id, session_no)
);

-- 3. Attendance
CREATE TABLE afsm_attendance (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  session_id INT NOT NULL,
  user_id    INT NOT NULL,
  status     ENUM('present','absent','excused') NOT NULL DEFAULT 'present',
  marked_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (session_id) REFERENCES afsm_sessions(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id)    REFERENCES afsm_users(id)    ON DELETE CASCADE,
  UNIQUE (session_id, user_id)
);

-- Insert your two batches
INSERT INTO afsm_batches (name, total_sessions)
VALUES 
  ('Batch 1', 6),
  ('Batch 2', 2);

-- Automatically generate sessions for each batch
-- For Batch 1
INSERT INTO afsm_sessions (batch_id, session_no)
SELECT b.id, s.num
FROM afsm_batches b
JOIN (
  SELECT 1 AS num UNION ALL SELECT 2 UNION ALL SELECT 3
  UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6
) s ON b.name = 'Batch 1';

-- For Batch 2
INSERT INTO afsm_sessions (batch_id, session_no)
SELECT b.id, s.num
FROM afsm_batches b
JOIN (
  SELECT 1 AS num UNION ALL SELECT 2
) s ON b.name = 'Batch 2';


-- 1) Dummy users (students & teachers)
INSERT INTO afsm_users (name, email, password, role) VALUES
  ('Alice Student',   'alice.student@example.com',   'pass123', 'student'),
  ('Bob Student',     'bob.student@example.com',     'pass123', 'student'),
  ('Carol Student',   'carol.student@example.com',   'pass123', 'student'),
  ('Dave Teacher',    'dave.teacher@example.com',    'teach123','teacher'),
  ('Eve Teacher',     'eve.teacher@example.com',     'teach123','teacher');

  INSERT INTO afsm_users (name, email, password, role) VALUES
  ('Student 1',  'student1@example.com',  'pass123', 'student'),
  ('Student 2',  'student2@example.com',  'pass123', 'student'),
  ('Student 3',  'student3@example.com',  'pass123', 'student'),
  ('Student 4',  'student4@example.com',  'pass123', 'student'),
  ('Student 5',  'student5@example.com',  'pass123', 'student'),
  ('Student 6',  'student6@example.com',  'pass123', 'student'),
  ('Student 7',  'student7@example.com',  'pass123', 'student'),
  ('Student 8',  'student8@example.com',  'pass123', 'student'),
  ('Student 9',  'student9@example.com',  'pass123', 'student'),
  ('Student 10', 'student10@example.com', 'pass123', 'student'),
  ('Student 11', 'student11@example.com', 'pass123', 'student'),
  ('Student 12', 'student12@example.com', 'pass123', 'student'),
  ('Student 13', 'student13@example.com', 'pass123', 'student'),
  ('Student 14', 'student14@example.com', 'pass123', 'student'),
  ('Student 15', 'student15@example.com', 'pass123', 'student'),
  ('Student 16', 'student16@example.com', 'pass123', 'student'),
  ('Student 17', 'student17@example.com', 'pass123', 'student'),
  ('Student 18', 'student18@example.com', 'pass123', 'student'),
  ('Student 19', 'student19@example.com', 'pass123', 'student'),
  ('Student 20', 'student20@example.com', 'pass123', 'student'),
  ('Teacher 1',  'teacher1@example.com',  'teach123','teacher'),
  ('Teacher 2',  'teacher2@example.com',  'teach123','teacher');


-- 2) Dummy batches
INSERT INTO afsm_batches (name, total_sessions) VALUES
  ('Batch A', 6),
  ('Batch B', 2);

  INSERT INTO afsm_batches (name, total_sessions) VALUES
  ('Module 2', 6),
  ('Module 3', 2);

-- 3) Dummy sessions
--  Assumes afsm_batches IDs are 1 (Batch A) and 2 (Batch B)
INSERT INTO afsm_sessions (batch_id, session_no) VALUES
  -- Batch A: sessions 1 through 6
  (1, 1),(1, 2),(1, 3),(1, 4),(1, 5),(1, 6),
  -- Batch B: sessions 1 and 2
  (2, 1),(2, 2);

-- 1) Add four new batches
INSERT INTO afsm_batches (name, total_sessions) VALUES
  ('Batch C',  FLOOR(3 + RAND() * 5)),  -- 3–7 sessions
  ('Batch D',  FLOOR(1 + RAND() * 4)),  -- 1–4 sessions
  ('Batch E',  FLOOR(2 + RAND() * 6)),  -- 2–7 sessions
  ('Batch F',  FLOOR(3 + RAND() * 6));  -- 3–8 sessions

-- 2) Insert sessions for those batches
--    First, grab the new batch IDs:
SELECT @c := id FROM afsm_batches WHERE name = 'Batch C';
SELECT @d := id FROM afsm_batches WHERE name = 'Batch D';
SELECT @e := id FROM afsm_batches WHERE name = 'Batch E';
SELECT @f := id FROM afsm_batches WHERE name = 'Batch F';

-- Then, for each batch, generate its sessions:
-- Batch C
INSERT INTO afsm_sessions (batch_id, session_no)
  SELECT @c, seq
  FROM (SELECT 1 AS seq UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL
        SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL
        SELECT 7 UNION ALL SELECT 8) x
  WHERE seq <= (SELECT total_sessions FROM afsm_batches WHERE id = @c);

-- Batch D
INSERT INTO afsm_sessions (batch_id, session_no)
  SELECT @d, seq
  FROM (SELECT 1 AS seq UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL
        SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL
        SELECT 7 UNION ALL SELECT 8) x
  WHERE seq <= (SELECT total_sessions FROM afsm_batches WHERE id = @d);

-- Batch E
INSERT INTO afsm_sessions (batch_id, session_no)
  SELECT @e, seq
  FROM (SELECT 1 AS seq UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL
        SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL
        SELECT 7 UNION ALL SELECT 8) x
  WHERE seq <= (SELECT total_sessions FROM afsm_batches WHERE id = @e);

-- Batch F
INSERT INTO afsm_sessions (batch_id, session_no)
  SELECT @f, seq
  FROM (SELECT 1 AS seq UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL
        SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL
        SELECT 7 UNION ALL SELECT 8) x
  WHERE seq <= (SELECT total_sessions FROM afsm_batches WHERE id = @f);

--A. One student across all sessions of a batch
$batchId = 1;
$userId  = 15;

$sql = "
  SELECT s.session_no, COALESCE(a.status,'absent') AS status
  FROM sessions s
  LEFT JOIN attendance a 
    ON a.session_id = s.id AND a.user_id = ?
  WHERE s.batch_id = ?
  ORDER BY s.session_no
";

$stmt = $mysqli->prepare($sql);
$stmt->bind_param('ii', $userId, $batchId);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    echo "Session {$row['session_no']}: {$row['status']}<br>";
}
$stmt->close();


-- B. All students for one session

$sessionId = 3;

$sql = "
  SELECT u.id, u.name, COALESCE(a.status,'absent') AS status
  FROM users u
  JOIN users u ON u.role = 'student'
  CROSS JOIN (SELECT ?) AS sess(sid)
  LEFT JOIN attendance a 
    ON a.session_id = sess.sid AND a.user_id = u.id
  ORDER BY u.name
";

$stmt = $mysqli->prepare($sql);
$stmt->bind_param('i', $sessionId);
$stmt->execute();
$res = $stmt->get_result();

while ($r = $res->fetch_assoc()) {
    echo "{$r['name']} — {$r['status']}<br>";
}
$stmt->close();


CREATE TABLE afsm_batch_students (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  batch_id   INT NOT NULL,
  student_id INT NOT NULL,
  FOREIGN KEY (batch_id)   REFERENCES afsm_batches(id),
  FOREIGN KEY (student_id)  REFERENCES afsm_users(id),
  UNIQUE(batch_id, student_id)
);


-- Assuming student user_ids 1 through 20 correspond to Student 1–Student 20
INSERT IGNORE INTO afsm_batch_students (batch_id, user_id, role) VALUES
  -- Batch 1: Students 1–4
  (1, 1, 'student'),
  (1, 2, 'student'),
  (1, 3, 'student'),
  (1, 4, 'student'),
  -- Batch 2: Students 5–8
  (2, 5, 'student'),
  (2, 6, 'student'),
  (2, 7, 'student'),
  (2, 8, 'student'),
  -- Batch 3: Students 9–12
  (3, 9, 'student'),
  (3, 10, 'student'),
  (3, 11, 'student'),
  (3, 12, 'student'),
  -- Batch 4: Students 13–16
  (4, 13, 'student'),
  (4, 14, 'student'),
  (4, 15, 'student'),
  (4, 16, 'student'),
  -- Batch 5: Students 17–19
  (5, 17, 'student'),
  (5, 18, 'student'),
  (5, 19, 'student'),
  -- Batch 6: Student 20
  (6, 20, 'student');


ALTER TABLE afsm_users
  ADD COLUMN created_by INT NULL,
  ADD COLUMN created_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN updated_by INT NULL,
  ADD COLUMN updated_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE afsm_batches
  ADD COLUMN created_by INT NULL,
  ADD COLUMN created_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN updated_by INT NULL,
  ADD COLUMN updated_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE afsm_sessions
  ADD COLUMN created_by INT NULL,
  ADD COLUMN created_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN updated_by INT NULL,
  ADD COLUMN updated_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE afsm_attendance
  ADD COLUMN created_by INT NULL,
  ADD COLUMN created_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN updated_by INT NULL,
  ADD COLUMN updated_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE afsm_batch_students
  ADD COLUMN created_by INT NULL,
  ADD COLUMN created_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN updated_by INT NULL,
  ADD COLUMN updated_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

  ALTER TABLE afsm_attendance
  ADD COLUMN teacher_id   INT        NULL,
  ADD FOREIGN KEY (teacher_id) REFERENCES afsm_users(id),
  ADD FOREIGN KEY (created_by) REFERENCES afsm_users(id),
  ADD FOREIGN KEY (updated_by) REFERENCES afsm_users(id);

INSERT INTO afsm_participation_scoring (batch_id, score) VALUES
  (1, 0),(1, 1),(1, 2),(1, 3),(1, 4),(1, 5),
  (2, 0),(2, 1),(2, 2),(2, 3),(2, 4),(2, 5),
  (3, 0),(3, 1),(3, 2),(3, 3),(3, 4),(3, 5),
  (4, 0),(4, 1),(4, 2),(4, 3),(4, 4),(4, 5),
  (5, 0),(5, 1),(5, 2),(5, 3),(5, 4),(5, 5),
  (6, 0),(6, 1),(6, 2),(6, 3),(6, 4),(6, 5);


  INSERT INTO afsm_batch_students (batch_id, user_id, role) VALUES
  (1, 4, 'teacher'),  -- Batch 1 assigned to Teacher with user_id=4
  (2, 4, 'teacher'),  -- Batch 2 assigned to same teacher
  (3, 5, 'teacher'),  -- Batch 3 assigned to Teacher user_id=5
  (4, 5, 'teacher'),
  (5, 4, 'teacher'),
  (6, 5, 'teacher');

-- Rename 'student_id' to 'user_id' to associate any user (student or teacher) with a batch
CREATE TABLE afsm_batch_students (
  batch_id INT NOT NULL,
  user_id  INT NOT NULL,
  role     ENUM('student','teacher') NOT NULL,
  created_by   INT NULL,
  created_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_by   INT NULL,
  updated_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (batch_id, user_id),
  FOREIGN KEY (batch_id) REFERENCES afsm_batches(id),
  FOREIGN KEY (user_id)    REFERENCES afsm_users(id),
  FOREIGN KEY (created_by) REFERENCES afsm_users(id),
  FOREIGN KEY (updated_by) REFERENCES afsm_users(id)
);