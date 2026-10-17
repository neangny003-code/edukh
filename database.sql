-- GamesKid Database Schema
-- Compatible with MySQL 5.7+ / MariaDB 10+ / XAMPP

CREATE DATABASE IF NOT EXISTS `gameskid` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `gameskid`;

-- 1. Admins / Users Table
CREATE TABLE IF NOT EXISTS `admins` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `email` VARCHAR(100) DEFAULT NULL,
    `full_name` VARCHAR(100) DEFAULT 'Administrator',
    `role` VARCHAR(50) DEFAULT 'admin',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Insert Default Admin User
-- Username: admin
-- Password: admin123
INSERT INTO `admins` (`id`, `username`, `password`, `email`, `full_name`, `role`)
VALUES (1, 'admin', '$2y$10$fr0qfFGpm0ay6gw3vMErXuACSh4QohdJCS3lLEYKylDdN5jXo1enq', 'admin@gameskid.local', 'Super Admin', 'admin')
ON DUPLICATE KEY UPDATE `username` = VALUES(`username`);

-- 3. Students / Game Scores Table
CREATE TABLE IF NOT EXISTS `students` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `cls` VARCHAR(50) DEFAULT '-',
    `score` VARCHAR(50) DEFAULT '-',
    `game_name` VARCHAR(100) DEFAULT 'General',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Game Activity Logs Table
CREATE TABLE IF NOT EXISTS `game_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `game_key` VARCHAR(100) NOT NULL,
    `player_name` VARCHAR(255) DEFAULT 'Guest',
    `action` VARCHAR(100) NOT NULL,
    `details` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sample Student Data for testing
INSERT INTO `students` (`name`, `cls`, `score`, `game_name`) VALUES
('Sokha', 'Grade 3A', '95', 'khmer_word_collection'),
('Dara', 'Grade 3B', '88', 'math_quiz_grade'),
('Bopha', 'Grade 4A', '100', 'spelling_bee'),
('Vireak', 'Grade 2C', '92', 'fast_math')
ON DUPLICATE KEY UPDATE `id`=`id`;
