CREATE DATABASE IF NOT EXISTS `student_task_planner`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `student_task_planner`;

CREATE TABLE IF NOT EXISTS `STUDENT` (
  `Student_ID` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `Name` VARCHAR(150) NOT NULL,
  `Email` VARCHAR(254) NOT NULL,
  `Password_Hash` VARCHAR(255) NOT NULL,
  PRIMARY KEY (`Student_ID`),
  UNIQUE KEY `uq_student_email` (`Email`)
) ENGINE=InnoDB
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `TASK` (
  `Task_ID` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `Student_ID` INT UNSIGNED NOT NULL,
  `Task_Name` VARCHAR(255) NOT NULL,
  `Description` TEXT NULL,
  `Subject` VARCHAR(150) NOT NULL,
  `Deadline` DATE NOT NULL,
  `Deadline_Time` TIME NOT NULL DEFAULT '23:59:00',
  `Priority` ENUM('Low', 'Medium', 'High') NOT NULL DEFAULT 'Medium',
  `Status` ENUM('Pending', 'In Progress', 'Completed') NOT NULL DEFAULT 'Pending',
  PRIMARY KEY (`Task_ID`),
  KEY `idx_task_student_deadline` (`Student_ID`, `Deadline`),
  KEY `idx_task_student_status_deadline` (`Student_ID`, `Status`, `Deadline`),
  KEY `idx_task_student_priority` (`Student_ID`, `Priority`),
  CONSTRAINT `fk_task_student`
    FOREIGN KEY (`Student_ID`)
    REFERENCES `STUDENT` (`Student_ID`)
    ON UPDATE CASCADE
    ON DELETE RESTRICT
) ENGINE=InnoDB
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
