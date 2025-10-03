-- Enhanced Smart Waste Management Database Schema
-- This file contains all necessary tables for the complete waste management system

-- Create database (run once if not already created)
CREATE DATABASE IF NOT EXISTS smart_waste CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

-- Use the database
USE smart_waste;

-- Users table (enhanced)
DROP TABLE IF EXISTS users;
CREATE TABLE users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username VARCHAR(100) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    middle_name VARCHAR(100) NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(190) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'resident', -- resident | admin | authority | collector
    address VARCHAR(255) NOT NULL,
    phone VARCHAR(20) NULL,
    profile_image VARCHAR(255) NULL,
    eco_points INT DEFAULT 0,
    email_verified_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    UNIQUE KEY uq_users_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Waste Reports table
DROP TABLE IF EXISTS waste_reports;
CREATE TABLE waste_reports (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    report_type ENUM('overflow', 'missed_collection', 'damaged_bin', 'illegal_dumping', 'other') NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    location VARCHAR(255) NOT NULL,
    latitude DECIMAL(10, 8) NULL,
    longitude DECIMAL(11, 8) NULL,
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    status ENUM('pending', 'assigned', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
    assigned_to INT UNSIGNED NULL,
    reported_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_waste_reports_user_id (user_id),
    KEY idx_waste_reports_status (status),
    KEY idx_waste_reports_priority (priority),
    KEY idx_waste_reports_assigned_to (assigned_to),
    CONSTRAINT fk_waste_reports_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_waste_reports_assigned FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Report Images table
DROP TABLE IF EXISTS report_images;
CREATE TABLE report_images (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    report_id INT UNSIGNED NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    image_name VARCHAR(255) NOT NULL,
    uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_report_images_report_id (report_id),
    CONSTRAINT fk_report_images_report FOREIGN KEY (report_id) REFERENCES waste_reports(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Collection Schedules table
DROP TABLE IF EXISTS collection_schedules;
CREATE TABLE collection_schedules (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    area VARCHAR(255) NOT NULL,
    street_name VARCHAR(255) NOT NULL,
    latitude DECIMAL(10, 8) NULL,
    longitude DECIMAL(11, 8) NULL,
    collection_day ENUM('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday') NOT NULL,
    collection_time TIME NOT NULL,
    frequency ENUM('daily', 'weekly', 'biweekly', 'monthly') DEFAULT 'weekly',
    waste_type ENUM('general', 'recyclable', 'organic', 'hazardous') DEFAULT 'general',
    assigned_collector INT UNSIGNED NULL,
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    created_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_collection_schedules_area (area),
    KEY idx_collection_schedules_day (collection_day),
    KEY idx_collection_schedules_collector (assigned_collector),
    CONSTRAINT fk_collection_schedules_collector FOREIGN KEY (assigned_collector) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_collection_schedules_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Collection History table
DROP TABLE IF EXISTS collection_history;
CREATE TABLE collection_history (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    schedule_id INT UNSIGNED NOT NULL,
    collector_id INT UNSIGNED NOT NULL,
    collection_date DATE NOT NULL,
    collection_time TIME NULL,
    status ENUM('scheduled', 'in_progress', 'completed', 'missed', 'cancelled') DEFAULT 'scheduled',
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_collection_history_schedule (schedule_id),
    KEY idx_collection_history_collector (collector_id),
    KEY idx_collection_history_date (collection_date),
    CONSTRAINT fk_collection_history_schedule FOREIGN KEY (schedule_id) REFERENCES collection_schedules(id) ON DELETE CASCADE,
    CONSTRAINT fk_collection_history_collector FOREIGN KEY (collector_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Points Transactions table
DROP TABLE IF EXISTS points_transactions;
CREATE TABLE points_transactions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    points INT NOT NULL,
    transaction_type ENUM('earned', 'spent', 'bonus', 'penalty') NOT NULL,
    description VARCHAR(255) NOT NULL,
    reference_type ENUM('report', 'schedule', 'feedback', 'bonus', 'other') NULL,
    reference_id INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_points_transactions_user (user_id),
    KEY idx_points_transactions_type (transaction_type),
    CONSTRAINT fk_points_transactions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Feedback table
DROP TABLE IF EXISTS feedback;
CREATE TABLE feedback (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    feedback_type ENUM('suggestion', 'complaint', 'appreciation', 'bug_report') NOT NULL,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    rating INT NULL CHECK (rating >= 1 AND rating <= 5),
    status ENUM('open', 'in_progress', 'resolved', 'closed') DEFAULT 'open',
    assigned_to INT UNSIGNED NULL,
    response TEXT NULL,
    responded_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_feedback_user (user_id),
    KEY idx_feedback_status (status),
    KEY idx_feedback_type (feedback_type),
    CONSTRAINT fk_feedback_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_feedback_assigned FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Chat Messages table
DROP TABLE IF EXISTS chat_messages;
CREATE TABLE chat_messages (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    sender_id INT UNSIGNED NOT NULL,
    receiver_id INT UNSIGNED NOT NULL,
    message TEXT NOT NULL,
    message_type ENUM('text', 'image', 'file') DEFAULT 'text',
    file_path VARCHAR(255) NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_chat_messages_sender (sender_id),
    KEY idx_chat_messages_receiver (receiver_id),
    KEY idx_chat_messages_created (created_at),
    CONSTRAINT fk_chat_messages_sender FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_chat_messages_receiver FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Notifications table
DROP TABLE IF EXISTS notifications;
CREATE TABLE notifications (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'success', 'warning', 'error') DEFAULT 'info',
    is_read BOOLEAN DEFAULT FALSE,
    reference_type VARCHAR(50) NULL,
    reference_id INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_notifications_user (user_id),
    KEY idx_notifications_read (is_read),
    CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Password reset tokens table
DROP TABLE IF EXISTS password_resets;
CREATE TABLE password_resets (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    token_hash VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_password_resets_user_id (user_id),
    CONSTRAINT fk_password_resets_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Email verification tokens
DROP TABLE IF EXISTS email_verifications;
CREATE TABLE email_verifications (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    token_hash VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_email_verifications_user_id (user_id),
    CONSTRAINT fk_email_verifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Seed sample users (passwords are in plaintext for initial setup; app will upgrade to hashed on first login)
INSERT INTO users (username, first_name, middle_name, last_name, email, password, role, address, phone, eco_points, email_verified_at) VALUES
('Admin User', 'Admin', NULL, 'User', 'admin@smartwaste.local', 'Admin@123', 'admin', 'HQ', '+1234567890', 0, NOW()),
('City Authority', 'City', NULL, 'Authority', 'authority@smartwaste.local', 'Authority@123', 'authority', 'City Hall', '+1234567891', 0, NOW()),
('Lead Collector', 'Lead', NULL, 'Collector', 'collector@smartwaste.local', 'Collector@123', 'collector', 'Depot', '+1234567892', 0, NOW()),
('Demo Resident', 'Demo', NULL, 'Resident', 'resident@smartwaste.local', 'Resident@123', 'resident', '123 Green St', '+1234567893', 25, NOW());

-- Sample collection schedules
INSERT INTO collection_schedules (area, street_name, collection_day, collection_time, frequency, waste_type, created_by) VALUES
('Downtown', 'Main Street', 'monday', '08:00:00', 'weekly', 'general', 2),
('Downtown', 'Main Street', 'wednesday', '08:00:00', 'weekly', 'recyclable', 2),
('Suburbs', 'Oak Avenue', 'tuesday', '09:00:00', 'weekly', 'general', 2),
('Suburbs', 'Oak Avenue', 'thursday', '09:00:00', 'weekly', 'recyclable', 2),
('Industrial', 'Factory Road', 'friday', '07:00:00', 'weekly', 'general', 2);

-- Sample waste reports
INSERT INTO waste_reports (user_id, report_type, title, description, location, priority, status) VALUES
(4, 'overflow', 'Overflowing Bin at Main Street', 'The bin at the corner of Main St and 5th Ave is overflowing with garbage', 'Main Street & 5th Avenue', 'high', 'pending'),
(4, 'missed_collection', 'Missed Collection on Oak Ave', 'Our street was not collected yesterday as scheduled', 'Oak Avenue', 'medium', 'pending'),
(4, 'damaged_bin', 'Damaged Recycling Bin', 'The blue recycling bin at Park Road is cracked and needs replacement', 'Park Road', 'low', 'pending');

-- Sample feedback
INSERT INTO feedback (user_id, feedback_type, subject, message, rating) VALUES
(4, 'suggestion', 'More Recycling Bins', 'Could we get more recycling bins in the downtown area?', 4),
(4, 'appreciation', 'Great Service', 'The collection team is always on time and professional', 5);

-- Sample notifications
INSERT INTO notifications (user_id, title, message, type) VALUES
(4, 'Collection Tomorrow', 'Your waste collection is scheduled for tomorrow at 8:00 AM', 'info'),
(4, 'Report Submitted', 'Your waste report has been submitted successfully', 'success'),
(2, 'New Report', 'New waste report submitted by Demo Resident', 'info');
