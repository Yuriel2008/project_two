-- LingoFlow v2 - MariaDB Database Schema & Seed Data
-- Version: 1.0
-- Date: 2026-02-13
-- Engineer: Gemini AI
-- Description: This file creates the database, tables, and initial data for the LingoFlow project.
-- Section 0: Database Initialization
-- --------------------------------------------------------------
-- This ensures a clean slate for every run, perfect for development.
DROP DATABASE IF EXISTS `lingoflow_db`;

CREATE DATABASE `lingoflow_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `lingoflow_db`;

SET
    FOREIGN_KEY_CHECKS = 0;

-- Section 1: Users Table
-- --------------------------------------------------------------
-- Stores core user information, credentials, and top-level stats.
CREATE TABLE `users` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(50) NOT NULL,
    `email` VARCHAR(100) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    -- Storing plaintext as requested.
    `xp` INT(11) NOT NULL DEFAULT 0,
    `streak` INT(11) NOT NULL DEFAULT 0,
    `accuracy` DECIMAL(5, 2) default 87.00,
    `rank` VARCHAR(50) DEFAULT 'Silver II',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_login` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `username` (`username`),
    UNIQUE KEY `email` (`email`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- Section 2: Lessons Table
-- --------------------------------------------------------------
-- Defines all available lessons in the learning roadmap.
-- The 'id' column uses a string to easily link with front-end JS logic.
CREATE TABLE `lessons` (
    `id` VARCHAR(50) NOT NULL,
    `title` VARCHAR(100) NOT NULL,
    `category` VARCHAR(50) NOT NULL,
    `description` VARCHAR(255) DEFAULT NULL,
    `xp_value` INT(11) NOT NULL DEFAULT 50,
    `sort_order` INT(11) NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- Section 6: Questions Table & Seed Data
-- --------------------------------------------------------------
CREATE TABLE `questions` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `lesson_id` VARCHAR(50) NOT NULL,
    `question_text` VARCHAR(255) NOT NULL,
    `option_a` VARCHAR(100) NOT NULL,
    `option_b` VARCHAR(100) NOT NULL,
    `option_c` VARCHAR(100) NOT NULL,
    `option_d` VARCHAR(100) NOT NULL,
    `correct_option` ENUM('a', 'b', 'c', 'd') NOT NULL,
    PRIMARY KEY (`id`),
    KEY `lesson_id` (`lesson_id`),
    CONSTRAINT `fk_questions_lessons` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- Section 3: User Progress Table
-- --------------------------------------------------------------
-- Tracks each user's status for every lesson, forming the core of the Skill Tree.
CREATE TABLE `user_progress` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) NOT NULL,
    `lesson_id` VARCHAR(50) NOT NULL,
    `score` INT(11) DEFAULT NULL,
    `last_attempt` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `status` ENUM('locked', 'active', 'completed') NOT NULL DEFAULT 'locked',
    PRIMARY KEY (`id`),
    UNIQUE KEY `user_lesson_unique` (`user_id`, `lesson_id`),
    KEY `user_id` (`user_id`),
    KEY `lesson_id` (`lesson_id`),
    CONSTRAINT `user_progress_ibfk_one` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `user_progress_ibfk_two` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- Section 4: Achievements & User Unlocks
-- --------------------------------------------------------------
-- Defines all possible achievements and a linking table for user unlocks.
CREATE TABLE `achievements` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `description` VARCHAR(255) NOT NULL,
    `icon_emoji` VARCHAR(10) NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE `user_achievements` (
    `user_id` INT(11) NOT NULL,
    `achievement_id` INT(11) NOT NULL,
    `unlock_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`user_id`, `achievement_id`),
    KEY `user_id` (`user_id`),
    KEY `achievement_id` (`achievement_id`),
    CONSTRAINT `user_achievements_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `user_achievements_ibfk_2` FOREIGN KEY (`achievement_id`) REFERENCES `achievements` (`id`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- Section 5: Seeding Initial Data
-- --------------------------------------------------------------
-- Populates the tables with sample data to match the UI mockups.
-- Seed Users Table
INSERT INTO
    `users` (
        `id`,
        `username`,
        `email`,
        `password`,
        `xp`,
        `streak`,
        `accuracy`,
        `rank`,
        `created_at`,
        `last_login`
    )
VALUES
    (
        1,
        'Learner',
        'learner@lingoflow.com',
        'password123',
        2450,
        12,
        87.00,
        'Silver II',
        '2024-01-10 10:00:00',
        '2026-02-13 17:00:00'
    ),
    (
        2,
        'JohnDoe_99',
        'john@example.com',
        'johndoe',
        1850,
        12,
        91.50,
        'Silver II',
        '2024-02-20 11:30:00',
        '2026-02-13 16:58:00'
    ),
    (
        3,
        'Sarah_Smith',
        'sarah@test.net',
        'sarah',
        3200,
        0,
        95.20,
        'Gold I',
        '2024-03-15 09:00:00',
        '2026-02-11 08:15:00'
    ),
    (
        4,
        'Mike_R',
        'mike@web.co',
        'mike',
        800,
        5,
        82.00,
        'Bronze III',
        '2024-05-01 18:45:00',
        '2026-02-13 17:15:00'
    );

-- Seed Lessons Table (based on roadmap.php & lesson.php)
INSERT INTO
    `lessons` (
        `id`,
        `title`,
        `category`,
        `description`,
        `xp_value`,
        `sort_order`
    )
VALUES
    (
        'basics',
        'Basics',
        'Foundations',
        'Letters, Sounds, & Pronouns',
        50,
        1
    ),
    (
        'greetings',
        'Greetings',
        'Social',
        'Hello, Goodbye & Introductions',
        75,
        2
    ),
    (
        'respect',
        'Common Phrases',
        'Daily Use',
        'Respect, Politeness & Daily Use',
        100,
        3
    ),
    (
        'numbers1',
        'Numbers 1-30',
        'Mathematics',
        'Counting 1-30 & Basic Math',
        100,
        4
    ),
    (
        'food',
        'Food & Dining',
        'Lifestyle',
        'Ordering meals & Ingredients',
        150,
        5
    ),
    (
        'travel',
        'Travel',
        'Lifestyle',
        'Directions & Transportation',
        150,
        6
    ),
    (
        'emotions',
        'Emotions',
        'Social',
        'Expressing feelings like happiness and sadness',
        120,
        7
    );

-- Seed Sample Questions
INSERT INTO
    `questions` (
        `lesson_id`,
        `question_text`,
        `option_a`,
        `option_b`,
        `option_c`,
        `option_d`,
        `correct_option`
    )
VALUES
    (
        'basics',
        'How do you say ''I'' or ''Me''?',
        'Tôi',
        'Bạn',
        'Họ',
        'Nó',
        'a'
    ),
    (
        'basics',
        'Translate ''Hello'' to Vietnamese',
        'Xin chào',
        'Tạm biệt',
        'Cảm ơn',
        'Xin lỗi',
        'a'
    ),
    (
        'greetings',
        'Translate ''Nice to meet you!''',
        'Rất vui được gặp bạn',
        'Cảm ơn bạn nhiều',
        'Tạm biệt bạn',
        'Xin lỗi bạn',
        'a'
    ),
    (
        'respect',
        'How to say ''Hello'' respectfully to an older person?',
        'Cháu chào bác',
        'Ê bạn',
        'Chào nhé',
        'Hi',
        'a'
    ),
    (
        'numbers1',
        'What is the number ''1''?',
        'Một',
        'Hai',
        'Ba',
        'Bốn',
        'a'
    );

-- Seed User Progress for 'Learner' (id=1) to match roadmap.php
INSERT INTO
    `user_progress` (`user_id`, `lesson_id`, `status`)
VALUES
    (1, 'basics', 'completed'),
    (1, 'greetings', 'completed'),
    (1, 'respect', 'active'),
    (1, 'numbers1', 'active'),
    (1, 'food', 'locked'),
    (1, 'travel', 'locked'),
    (1, 'emotions', 'locked');

-- Seed some progress for other users
INSERT INTO
    `user_progress` (`user_id`, `lesson_id`, `status`)
VALUES
    (2, 'basics', 'completed'),
    (2, 'greetings', 'completed'),
    (2, 'respect', 'active'),
    (3, 'basics', 'completed'),
    (3, 'greetings', 'completed'),
    (3, 'respect', 'completed'),
    (3, 'numbers1', 'completed'),
    (3, 'food', 'active'),
    (4, 'basics', 'completed'),
    (4, 'greetings', 'active');

-- Seed Achievements Table
INSERT INTO
    `achievements` (`id`, `name`, `description`, `icon_emoji`)
VALUES
    (1, 'First Steps', 'Complete 1 lesson', '🏁'),
    (2, 'Dedicated', 'Maintain a 7-day streak', '🔥'),
    (
        3,
        'Sharpshooter',
        'Get 100% accuracy in a lesson',
        '🎯'
    ),
    (4, 'Champion', 'Reach the Gold Rank', '🏆'),
    (5, 'On Fire', 'Maintain a 3-day streak', '🧡'),
    (
        6,
        'Defender',
        'Complete a lesson with no mistakes',
        '🛡️'
    ),
    (7, 'Royal', 'Reach the highest rank', '👑');

-- Seed User Achievements (Give some to our users)
INSERT INTO
    `user_achievements` (`user_id`, `achievement_id`)
VALUES
    (1, 1),
    (1, 2),
    (1, 5),
    (2, 1),
    (2, 5),
    (3, 1),
    (3, 2),
    (3, 3),
    (3, 4),
    (4, 1);

-- Always put space, not Enter when doing the Ctrl + Shift + F
SET FOREIGN_KEY_CHECKS = 1;

-- End of File