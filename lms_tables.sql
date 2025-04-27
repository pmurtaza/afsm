-- Table Schemas --
-- Users table
CREATE TABLE afsm_users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(100) NOT NULL,
  password VARCHAR(255) NOT NULL,
  role ENUM('student','teacher','admin') NOT NULL,
  created_by INT NULL,
  created_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_by INT NULL,
  updated_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by)  REFERENCES afsm_users(id),
  FOREIGN KEY (updated_by)  REFERENCES afsm_users(id)
);

-- Batches table
CREATE TABLE afsm_batches (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  total_sessions INT NOT NULL,
  created_by INT NULL,
  created_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_by INT NULL,
  updated_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by)  REFERENCES afsm_users(id),
  FOREIGN KEY (updated_by)  REFERENCES afsm_users(id)
);

-- Sessions table
CREATE TABLE afsm_sessions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  batch_id INT NOT NULL,
  session_no INT NOT NULL,
  created_by INT NULL,
  created_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_by INT NULL,
  updated_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (batch_id)     REFERENCES afsm_batches(id),
  FOREIGN KEY (created_by)   REFERENCES afsm_users(id),
  FOREIGN KEY (updated_by)   REFERENCES afsm_users(id)
);

-- Attendance table
CREATE TABLE afsm_attendance (
  session_id INT NOT NULL,
  user_id INT NOT NULL,
  status ENUM('present','absent') NOT NULL,
  teacher_id INT NULL,
  created_by INT NULL,
  created_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_by INT NULL,
  updated_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (session_id, user_id),
  FOREIGN KEY (session_id)  REFERENCES afsm_sessions(id),
  FOREIGN KEY (user_id)     REFERENCES afsm_users(id),
  FOREIGN KEY (teacher_id)  REFERENCES afsm_users(id),
  FOREIGN KEY (created_by)  REFERENCES afsm_users(id),
  FOREIGN KEY (updated_by)  REFERENCES afsm_users(id)
);

-- Batch-Students junction
CREATE TABLE afsm_batch_students (
  batch_id INT NOT NULL,
  student_id INT NOT NULL,
  created_by INT NULL,
  created_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_by INT NULL,
  updated_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (batch_id, student_id),
  FOREIGN KEY (batch_id)   REFERENCES afsm_batches(id),
  FOREIGN KEY (student_id) REFERENCES afsm_users(id),
  FOREIGN KEY (created_by) REFERENCES afsm_users(id),
  FOREIGN KEY (updated_by) REFERENCES afsm_users(id)
);

-- Participation table
CREATE TABLE afsm_participation (
  session_id INT NOT NULL,
  user_id INT NOT NULL,
  score INT NULL,
  teacher_id INT NULL,
  created_by INT NULL,
  created_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_by INT NULL,
  updated_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (session_id, user_id),
  FOREIGN KEY (session_id) REFERENCES afsm_sessions(id),
  FOREIGN KEY (user_id)    REFERENCES afsm_users(id),
  FOREIGN KEY (teacher_id) REFERENCES afsm_users(id),
  FOREIGN KEY (created_by) REFERENCES afsm_users(id),
  FOREIGN KEY (updated_by) REFERENCES afsm_users(id)
);

-- Participation scoring master (batch-specific)
CREATE TABLE afsm_participation_scoring (
  batch_id INT NOT NULL,
  score INT NOT NULL,
  created_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (batch_id, score),
  FOREIGN KEY (batch_id) REFERENCES afsm_batches(id)
);
