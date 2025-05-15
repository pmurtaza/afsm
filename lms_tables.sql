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

-- assignments table: defines an assignment per batch
CREATE TABLE afsm_assignments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  batch_id INT NOT NULL,
  title VARCHAR(255) NOT NULL,
  instructions TEXT NOT NULL,
  allow_upload_doc BOOLEAN NOT NULL DEFAULT TRUE,
  allow_upload_pdf BOOLEAN NOT NULL DEFAULT TRUE,
  allow_text_input BOOLEAN NOT NULL DEFAULT TRUE,
  created_by INT NOT NULL,
  created_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_by INT NULL,
  updated_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (batch_id) REFERENCES afsm_batches(id),
  FOREIGN KEY (created_by) REFERENCES afsm_users(id),
  FOREIGN KEY (updated_by) REFERENCES afsm_users(id)
);

-- submissions table: student submissions
CREATE TABLE afsm_assignment_submissions (
  assignment_id INT NOT NULL,
  student_id INT NOT NULL,
  doc_path VARCHAR(255) NULL,
  pdf_path VARCHAR(255) NULL,
  text_content TEXT NULL,
  status ENUM('draft','submitted') NOT NULL DEFAULT 'draft',
  submitted_date DATETIME NULL,
  graded_by INT NULL,
  grade_rubric JSON NULL,
  grade_date DATETIME NULL,
  PRIMARY KEY (assignment_id, student_id),
  FOREIGN KEY (assignment_id) REFERENCES afsm_assignments(id),
  FOREIGN KEY (student_id) REFERENCES afsm_users(id),
  FOREIGN KEY (graded_by) REFERENCES afsm_users(id)
);

CREATE TABLE afsm_rubric_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  batch_id INT NOT NULL,
  criterion_text TEXT NOT NULL,
  level1 TEXT NOT NULL,
  level2 TEXT NOT NULL,
  level3 TEXT NOT NULL,
  max_score INT NOT NULL DEFAULT 3,
  created_by INT,
  created_date DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_by INT,
  updated_date DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);


DROP TABLE IF EXISTS afsm_submission_scores;
CREATE TABLE afsm_submission_scores (
  assignment_id    INT NOT NULL,
  student_id       INT NOT NULL,
  rubric_item_id   INT NOT NULL,
  score            INT NOT NULL,
  PRIMARY KEY (assignment_id, student_id, rubric_item_id),
  FOREIGN KEY (assignment_id, student_id)
    REFERENCES afsm_assignment_submissions(assignment_id, student_id),
  FOREIGN KEY (rubric_item_id) REFERENCES afsm_rubric_items(id)
);


-- 1) Define assessment/test instances per batch
CREATE TABLE afsm_assessments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  batch_id INT NOT NULL,
  type ENUM('pre','post','final') NOT NULL,
  title VARCHAR(255),
  instructions TEXT,
  created_by INT,
  created_date DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_by INT,
  updated_date DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (batch_id) REFERENCES afsm_batches(id)
);

-- 2) Questions in each assessment
CREATE TABLE afsm_questions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  assessment_id INT NOT NULL,
  type ENUM('mcq','fill_blank','match','short','long') NOT NULL,
  question_text TEXT NOT NULL,
  weight INT NOT NULL DEFAULT 1,
  created_by INT,
  created_date DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (assessment_id) REFERENCES afsm_assessments(id)
);

-- 3) MCQ options
CREATE TABLE afsm_question_options (
  id INT AUTO_INCREMENT PRIMARY KEY,
  question_id INT NOT NULL,
  option_text VARCHAR(255) NOT NULL,
  is_correct BOOLEAN NOT NULL DEFAULT FALSE,
  FOREIGN KEY (question_id) REFERENCES afsm_questions(id)
);

-- 4) Match-the-columns pairs
CREATE TABLE afsm_match_pairs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  question_id INT NOT NULL,
  left_text VARCHAR(255) NOT NULL,
  right_text VARCHAR(255) NOT NULL,
  FOREIGN KEY (question_id) REFERENCES afsm_questions(id)
);

-- 5) Student responses
CREATE TABLE afsm_assessment_submissions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  assessment_id INT NOT NULL,
  student_id INT NOT NULL,
  submitted_date DATETIME DEFAULT CURRENT_TIMESTAMP,
  status ENUM('in_progress','submitted') NOT NULL DEFAULT 'in_progress',
  FOREIGN KEY (assessment_id) REFERENCES afsm_assessments(id),
  FOREIGN KEY (student_id) REFERENCES afsm_users(id)
);

-- 6) Individual answers
CREATE TABLE afsm_responses (
  submission_id INT NOT NULL,
  question_id INT NOT NULL,
  selected_option_id INT NULL,
  text_response TEXT NULL,
  obtained_marks INT NULL,
  PRIMARY KEY (submission_id, question_id),
  FOREIGN KEY (submission_id) REFERENCES afsm_assessment_submissions(id),
  FOREIGN KEY (question_id) REFERENCES afsm_questions(id),
  FOREIGN KEY (selected_option_id) REFERENCES afsm_question_options(id)
);
---
-- 1) Define assessment/test instances per batch
CREATE TABLE afsm_assessments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  batch_id INT NOT NULL,
  type ENUM('pre','post','final') NOT NULL,
  title VARCHAR(255),
  instructions TEXT,
  created_by INT,
  created_date DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_by INT,
  updated_date DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (batch_id) REFERENCES afsm_batches(id)
);

-- 2) Questions in each assessment
CREATE TABLE afsm_questions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  assessment_id INT NOT NULL,
  type ENUM('mcq','fill_blank','match','short','long') NOT NULL,
  question_text TEXT NOT NULL,
  weight INT NOT NULL DEFAULT 1,
  allow_multiple BOOLEAN NOT NULL DEFAULT FALSE,
  created_by INT,
  created_date DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (assessment_id) REFERENCES afsm_assessments(id)
);

-- 3) MCQ options
CREATE TABLE afsm_question_options (
  id INT AUTO_INCREMENT PRIMARY KEY,
  question_id INT NOT NULL,
  option_text VARCHAR(255) NOT NULL,
  is_correct BOOLEAN NOT NULL DEFAULT FALSE,
  FOREIGN KEY (question_id) REFERENCES afsm_questions(id)
);

-- 4) Match-the-columns pairs
CREATE TABLE afsm_match_pairs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  question_id INT NOT NULL,
  left_text VARCHAR(255) NOT NULL,
  right_text VARCHAR(255) NOT NULL,
  FOREIGN KEY (question_id) REFERENCES afsm_questions(id)
);

-- 5) Student submissions
CREATE TABLE afsm_assessment_submissions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  assessment_id INT NOT NULL,
  student_id INT NOT NULL,
  submitted_date DATETIME DEFAULT CURRENT_TIMESTAMP,
  status ENUM('in_progress','submitted') NOT NULL DEFAULT 'in_progress',
  FOREIGN KEY (assessment_id) REFERENCES afsm_assessments(id),
  FOREIGN KEY (student_id) REFERENCES afsm_users(id)
);

-- 6) Individual responses (text-based & marks)
CREATE TABLE afsm_responses (
  submission_id INT NOT NULL,
  question_id INT NOT NULL,
  text_response TEXT NULL,
  obtained_marks INT NULL,
  PRIMARY KEY (submission_id, question_id),
  FOREIGN KEY (submission_id) REFERENCES afsm_assessment_submissions(id),
  FOREIGN KEY (question_id) REFERENCES afsm_questions(id)
);

-- 7) MCQ selected options (to handle single or multiple answers)
CREATE TABLE afsm_response_options (
  submission_id INT NOT NULL,
  question_id INT NOT NULL,
  option_id INT NOT NULL,
  PRIMARY KEY (submission_id, question_id, option_id),
  FOREIGN KEY (submission_id, question_id) REFERENCES afsm_responses(submission_id, question_id),
  FOREIGN KEY (option_id) REFERENCES afsm_question_options(id)
);

erDiagram
  afsm_batches ||--o{ afsm_assessments : contains
  afsm_assessments ||--o{ afsm_questions : has
  afsm_questions ||--o{ afsm_question_options : offers
  afsm_questions ||--o{ afsm_match_pairs : pairs
  afsm_assessments ||--o{ afsm_assessment_submissions : "student attempts"
  afsm_responses ||--o{ afsm_response_options : "selected options"
  afsm_assessment_submissions ||--o{ afsm_responses : records
  afsm_users ||--o{ afsm_assessment_submissions : "takes"
  afsm_users ||--o{ afsm_assessments : creates