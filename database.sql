-- Create gameskid database if it does not exist
CREATE DATABASE IF NOT EXISTS `gameskid` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `gameskid`;

-- Create students table if it does not exist
CREATE TABLE IF NOT EXISTS `students` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `cls` VARCHAR(50) DEFAULT '-',
    `score` VARCHAR(50) DEFAULT '-',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
