-- ============================================
-- Verification Portal Database Schema
-- Trishakti Apparel
-- ============================================
-- IMPORTANT: This file creates ONLY new tables
-- with 'ver_' prefix. Existing tables are NOT modified.
-- ============================================

-- Set character set
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================
-- Table: ver_documents
-- Stores document records for verification
-- ============================================
CREATE TABLE IF NOT EXISTS `ver_documents` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `document_number` VARCHAR(100) NOT NULL COMMENT 'Unique document identifier',
    `document_title` VARCHAR(255) DEFAULT NULL COMMENT 'Optional title/description',
    `issued_by` VARCHAR(255) NOT NULL COMMENT 'Issuing authority/person',
    `issued_to` VARCHAR(255) DEFAULT NULL COMMENT 'Person/entity document issued to',
    `document_date_bs` VARCHAR(20) NOT NULL COMMENT 'Date in Bikram Sambat (BS)',
    `document_date_ad` DATE NOT NULL COMMENT 'Date in Anno Domini (AD)',
    `file_path` VARCHAR(500) NOT NULL COMMENT 'Path to uploaded file',
    `file_type` VARCHAR(10) DEFAULT NULL COMMENT 'File extension (pdf, jpg, png)',
    `remarks` TEXT DEFAULT NULL COMMENT 'Additional notes',
    `created_by` INT UNSIGNED NOT NULL COMMENT 'Admin who created (admins.id)',
    `updated_by` INT UNSIGNED DEFAULT NULL COMMENT 'Admin who last updated',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `status` ENUM('active', 'inactive', 'deleted') NOT NULL DEFAULT 'active',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_document_number` (`document_number`),
    KEY `idx_document_date_bs` (`document_date_bs`),
    KEY `idx_document_date_ad` (`document_date_ad`),
    KEY `idx_created_by` (`created_by`),
    KEY `idx_status` (`status`),
    KEY `idx_issued_by` (`issued_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Document verification records';

-- ============================================
-- Table: ver_bills
-- Stores bill records for verification
-- ============================================
CREATE TABLE IF NOT EXISTS `ver_bills` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `bill_number` VARCHAR(100) NOT NULL COMMENT 'Unique bill number',
    `bill_type` VARCHAR(50) DEFAULT 'general' COMMENT 'Type of bill',
    `vendor_name` VARCHAR(255) DEFAULT NULL COMMENT 'Vendor/supplier name',
    `pan_number` VARCHAR(20) DEFAULT NULL COMMENT 'PAN number (if applicable)',
    `is_non_pan` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 if non-PAN bill',
    `non_pan_amount` DECIMAL(15,2) DEFAULT NULL COMMENT 'Amount for non-PAN bills',
    `bill_amount` DECIMAL(15,2) DEFAULT NULL COMMENT 'Total bill amount',
    `bill_date_bs` VARCHAR(20) NOT NULL COMMENT 'Bill date in BS (required)',
    `bill_date_ad` DATE DEFAULT NULL COMMENT 'Bill date in AD (auto-converted)',
    `file_path` VARCHAR(500) DEFAULT NULL COMMENT 'Path to uploaded file (optional)',
    `file_type` VARCHAR(10) DEFAULT NULL COMMENT 'File extension',
    `remarks` TEXT DEFAULT NULL COMMENT 'Additional notes',
    `created_by` INT UNSIGNED NOT NULL COMMENT 'Admin who created (admins.id)',
    `updated_by` INT UNSIGNED DEFAULT NULL COMMENT 'Admin who last updated',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `status` ENUM('active', 'inactive', 'deleted') NOT NULL DEFAULT 'active',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_bill_number` (`bill_number`),
    KEY `idx_pan_number` (`pan_number`),
    KEY `idx_bill_date_bs` (`bill_date_bs`),
    KEY `idx_bill_date_ad` (`bill_date_ad`),
    KEY `idx_created_by` (`created_by`),
    KEY `idx_status` (`status`),
    KEY `idx_is_non_pan` (`is_non_pan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bill verification records';

-- ============================================
-- Table: ver_otp_requests
-- Stores OTP verification requests
-- ============================================
CREATE TABLE IF NOT EXISTS `ver_otp_requests` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `email` VARCHAR(255) NOT NULL COMMENT 'Email address for OTP',
    `otp_code` VARCHAR(10) NOT NULL COMMENT 'Generated OTP code',
    `verification_type` ENUM('document', 'bill') NOT NULL COMMENT 'Type of verification',
    `reference_number` VARCHAR(100) NOT NULL COMMENT 'Document/Bill number being verified',
    `expires_at` DATETIME NOT NULL COMMENT 'OTP expiry time',
    `is_verified` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 if OTP was verified',
    `verified_at` DATETIME DEFAULT NULL COMMENT 'When OTP was verified',
    `attempts` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Number of verification attempts',
    `ip_address` VARCHAR(45) NOT NULL COMMENT 'Requester IP address',
    `user_agent` VARCHAR(500) DEFAULT NULL COMMENT 'Browser user agent',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_email` (`email`),
    KEY `idx_otp_code` (`otp_code`),
    KEY `idx_reference_number` (`reference_number`),
    KEY `idx_expires_at` (`expires_at`),
    KEY `idx_is_verified` (`is_verified`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='OTP verification requests';

-- ============================================
-- Table: ver_verification_logs
-- Audit log for all verification activities
-- ============================================
CREATE TABLE IF NOT EXISTS `ver_verification_logs` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `email` VARCHAR(255) NOT NULL COMMENT 'Email used for verification',
    `verification_type` ENUM('document', 'bill') NOT NULL COMMENT 'Type of verification',
    `reference_number` VARCHAR(100) NOT NULL COMMENT 'Document/Bill number verified',
    `action` VARCHAR(50) NOT NULL DEFAULT 'view' COMMENT 'Action performed',
    `ip_address` VARCHAR(45) NOT NULL COMMENT 'Client IP address',
    `user_agent` VARCHAR(500) DEFAULT NULL COMMENT 'Browser user agent',
    `otp_request_id` INT UNSIGNED DEFAULT NULL COMMENT 'Related OTP request',
    `verified_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_email` (`email`),
    KEY `idx_verification_type` (`verification_type`),
    KEY `idx_reference_number` (`reference_number`),
    KEY `idx_verified_at` (`verified_at`),
    KEY `idx_ip_address` (`ip_address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Verification activity audit log';

-- ============================================
-- Table: ver_admin_logs
-- Logs admin actions for accountability
-- ============================================
CREATE TABLE IF NOT EXISTS `ver_admin_logs` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `admin_id` INT UNSIGNED NOT NULL COMMENT 'Admin user ID (admins.id)',
    `action` VARCHAR(100) NOT NULL COMMENT 'Action performed',
    `table_name` VARCHAR(50) DEFAULT NULL COMMENT 'Table affected',
    `record_id` INT UNSIGNED DEFAULT NULL COMMENT 'Record ID affected',
    `old_values` JSON DEFAULT NULL COMMENT 'Previous values (for updates)',
    `new_values` JSON DEFAULT NULL COMMENT 'New values',
    `ip_address` VARCHAR(45) NOT NULL,
    `user_agent` VARCHAR(500) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_admin_id` (`admin_id`),
    KEY `idx_action` (`action`),
    KEY `idx_table_name` (`table_name`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Admin activity audit log';

-- ============================================
-- Table: ver_settings
-- Stores system settings (SMTP, OTP template, etc.)
-- ============================================
CREATE TABLE IF NOT EXISTS `ver_settings` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `setting_key` VARCHAR(100) NOT NULL COMMENT 'Setting identifier',
    `setting_value` TEXT DEFAULT NULL COMMENT 'Setting value',
    `setting_type` VARCHAR(50) DEFAULT 'text' COMMENT 'Type: text, email, number, textarea, password',
    `setting_group` VARCHAR(50) DEFAULT 'general' COMMENT 'Group: smtp, otp, general',
    `description` VARCHAR(255) DEFAULT NULL COMMENT 'Setting description',
    `updated_by` INT UNSIGNED DEFAULT NULL,
    `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_setting_key` (`setting_key`),
    KEY `idx_setting_group` (`setting_group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='System settings';

-- Default settings
INSERT INTO `ver_settings` (`setting_key`, `setting_value`, `setting_type`, `setting_group`, `description`) VALUES
('smtp_host', 'localhost', 'text', 'smtp', 'SMTP Server Host'),
('smtp_port', '587', 'number', 'smtp', 'SMTP Port'),
('smtp_username', '', 'text', 'smtp', 'SMTP Username'),
('smtp_password', '', 'password', 'smtp', 'SMTP Password'),
('smtp_encryption', 'tls', 'text', 'smtp', 'SMTP Encryption (tls/ssl/none)'),
('smtp_from_email', 'noreply@trishaktiapparel.com', 'email', 'smtp', 'From Email Address'),
('smtp_from_name', 'Trishakti Apparel', 'text', 'smtp', 'From Name'),
('otp_length', '6', 'number', 'otp', 'OTP Code Length'),
('otp_expiry_minutes', '10', 'number', 'otp', 'OTP Expiry (Minutes)'),
('otp_email_subject', 'Your Verification OTP - Trishakti Apparel', 'text', 'otp', 'OTP Email Subject'),
('otp_email_template', '<div style=\"font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;\">\n<h2 style=\"color: #333;\">Verification Code</h2>\n<p>Your OTP for {type} verification is:</p>\n<div style=\"background: #f5f5f5; padding: 20px; text-align: center; font-size: 32px; font-weight: bold; letter-spacing: 5px; color: #333;\">{otp}</div>\n<p style=\"color: #666; margin-top: 20px;\">This code will expire in {expiry} minutes.</p>\n<p style=\"color: #999; font-size: 12px;\">If you did not request this code, please ignore this email.</p>\n<hr style=\"border: none; border-top: 1px solid #eee; margin: 20px 0;\">\n<p style=\"color: #999; font-size: 12px;\">Trishakti Apparel Pvt. Ltd.</p>\n</div>', 'textarea', 'otp', 'OTP Email Template (HTML)')
ON DUPLICATE KEY UPDATE `setting_key` = `setting_key`;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================
-- END OF SCHEMA
-- ============================================
-- NOTE: This script does NOT create or modify
-- the existing 'admins' table. The verification
-- system will use the existing admins table
-- for authentication.
-- ============================================
