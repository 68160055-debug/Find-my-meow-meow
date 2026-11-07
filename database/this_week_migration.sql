-- WEEK 3: Run on existing database after previous week's CRUD Lab.
-- This script is NON-DESTRUCTIVE. Do not rerun the old setup.sql (it DROPS data).
-- Select your existing resume database in phpMyAdmin first.
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE users ADD COLUMN gender VARCHAR(20) DEFAULT NULL;