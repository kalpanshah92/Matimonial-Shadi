-- Matrimonial Shadi Database Schema
-- Run this file to create the database and tables

CREATE DATABASE IF NOT EXISTS matrimonial_shadi
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE matrimonial_shadi;

-- Users Table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    profile_id VARCHAR(10) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    phone VARCHAR(15),
    password VARCHAR(255) NOT NULL,
    gender ENUM('Male', 'Female') NOT NULL,
    dob DATE NOT NULL,
    religion VARCHAR(50),
    caste VARCHAR(100),
    sub_caste VARCHAR(100),
    mother_tongue VARCHAR(50),
    marital_status VARCHAR(30) DEFAULT 'Never Married',
    state VARCHAR(50),
    city VARCHAR(100),
    profile_pic VARCHAR(255),
    about_me TEXT,
    is_verified TINYINT(1) DEFAULT 0,
    is_premium TINYINT(1) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    status ENUM('pending', 'approved', 'rejected', 'suspended') DEFAULT 'pending',
    email_verified TINYINT(1) DEFAULT 0,
    phone_verified TINYINT(1) DEFAULT 0,
    last_login DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_gender (gender),
    INDEX idx_religion (religion),
    INDEX idx_caste (caste),
    INDEX idx_state (state),
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- Profile Details Table
CREATE TABLE IF NOT EXISTS profile_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNIQUE NOT NULL,
    height INT COMMENT 'Height in cm',
    weight INT COMMENT 'Weight in kg',
    complexion VARCHAR(30),
    body_type VARCHAR(20),
    blood_group VARCHAR(5),
    disability ENUM('None', 'Physical', 'Other') DEFAULT 'None',
    education VARCHAR(100),
    education_detail VARCHAR(255),
    occupation VARCHAR(100),
    occupation_detail VARCHAR(255),
    company VARCHAR(150),
    annual_income VARCHAR(50),
    working_city VARCHAR(100),
    diet VARCHAR(30),
    smoking ENUM('No', 'Yes', 'Occasionally') DEFAULT 'No',
    drinking ENUM('No', 'Yes', 'Occasionally') DEFAULT 'No',
    hobbies TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Family Details Table
CREATE TABLE IF NOT EXISTS family_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNIQUE NOT NULL,
    father_name VARCHAR(100),
    father_occupation VARCHAR(100),
    mother_name VARCHAR(100),
    mother_occupation VARCHAR(100),
    brothers INT DEFAULT 0,
    brothers_married INT DEFAULT 0,
    sisters INT DEFAULT 0,
    sisters_married INT DEFAULT 0,
    family_type VARCHAR(30),
    family_status VARCHAR(30),
    family_values VARCHAR(30),
    family_income VARCHAR(50),
    gotra VARCHAR(100),
    family_location VARCHAR(150),
    about_family TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Partner Preferences Table
CREATE TABLE IF NOT EXISTS partner_preferences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNIQUE NOT NULL,
    min_age INT DEFAULT 18,
    max_age INT DEFAULT 60,
    min_height INT COMMENT 'in cm',
    max_height INT COMMENT 'in cm',
    marital_status VARCHAR(100) COMMENT 'Comma separated values',
    religion VARCHAR(200) COMMENT 'Comma separated values',
    caste VARCHAR(500) COMMENT 'Comma separated values',
    mother_tongue VARCHAR(200) COMMENT 'Comma separated values',
    education VARCHAR(500) COMMENT 'Comma separated values',
    occupation VARCHAR(500) COMMENT 'Comma separated values',
    min_income VARCHAR(50),
    max_income VARCHAR(50),
    state VARCHAR(200) COMMENT 'Comma separated values',
    city VARCHAR(500) COMMENT 'Comma separated values',
    diet VARCHAR(100) COMMENT 'Comma separated values',
    smoking ENUM('No', 'Yes', 'Doesn\'t Matter') DEFAULT 'Doesn\'t Matter',
    drinking ENUM('No', 'Yes', 'Doesn\'t Matter') DEFAULT 'Doesn\'t Matter',
    about_partner TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- User Photos Table
CREATE TABLE IF NOT EXISTS photos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    photo_path VARCHAR(255) NOT NULL,
    is_primary TINYINT(1) DEFAULT 0,
    is_approved TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_photos (user_id)
) ENGINE=InnoDB;

-- Connection Requests Table
CREATE TABLE IF NOT EXISTS connection_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    status ENUM('pending', 'accepted', 'declined', 'cancelled') DEFAULT 'pending',
    message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_connection (sender_id, receiver_id),
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_receiver (receiver_id),
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- Messages Table
CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_conversation (sender_id, receiver_id),
    INDEX idx_unread (receiver_id, is_read)
) ENGINE=InnoDB;

-- Profile Visits Table
CREATE TABLE IF NOT EXISTS profile_visits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    visitor_id INT NOT NULL,
    visited_id INT NOT NULL,
    visited_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (visitor_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (visited_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_visited (visited_id),
    INDEX idx_visitor (visitor_id)
) ENGINE=InnoDB;

-- Shortlisted Profiles Table
CREATE TABLE IF NOT EXISTS shortlisted (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    shortlisted_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_shortlist (user_id, shortlisted_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (shortlisted_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Subscription Plans Table
CREATE TABLE IF NOT EXISTS plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    duration_days INT NOT NULL,
    features TEXT COMMENT 'JSON array of features',
    max_contacts INT DEFAULT 0 COMMENT 'Max contact views per day',
    max_messages INT DEFAULT 0 COMMENT 'Max messages per day',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- User Subscriptions Table
CREATE TABLE IF NOT EXISTS subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    plan_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    payment_id VARCHAR(100),
    payment_method VARCHAR(50),
    amount DECIMAL(10, 2) NOT NULL,
    status ENUM('active', 'expired', 'cancelled') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (plan_id) REFERENCES plans(id),
    INDEX idx_user_sub (user_id, status)
) ENGINE=InnoDB;

-- Success Stories Table
CREATE TABLE IF NOT EXISTS success_stories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    partner_name VARCHAR(100),
    title VARCHAR(200),
    story TEXT NOT NULL,
    photo VARCHAR(255),
    marriage_date DATE,
    is_approved TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Notifications Table
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(200),
    message TEXT NOT NULL,
    link VARCHAR(255),
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_notif (user_id, is_read)
) ENGINE=InnoDB;

-- OTP Verifications Table
CREATE TABLE IF NOT EXISTS otp_verifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    identifier VARCHAR(150) NOT NULL COMMENT 'Email or phone number',
    otp VARCHAR(10) NOT NULL,
    purpose ENUM('registration', 'login', 'password_reset', 'phone_verify') NOT NULL,
    expires_at DATETIME NOT NULL,
    is_verified TINYINT(1) DEFAULT 0,
    attempts INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_identifier (identifier, purpose)
) ENGINE=InnoDB;

-- Admin Users Table
CREATE TABLE IF NOT EXISTS admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    name VARCHAR(100) NOT NULL,
    role ENUM('super_admin', 'admin', 'moderator') DEFAULT 'moderator',
    is_active TINYINT(1) DEFAULT 1,
    last_login DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Reports Table
CREATE TABLE IF NOT EXISTS reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reporter_id INT NOT NULL,
    reported_id INT NOT NULL,
    reason VARCHAR(255) NOT NULL,
    description TEXT,
    status ENUM('pending', 'reviewed', 'resolved', 'dismissed') DEFAULT 'pending',
    admin_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reported_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Privacy Settings Table
CREATE TABLE IF NOT EXISTS privacy_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNIQUE NOT NULL,
    show_phone ENUM('everyone', 'premium', 'connected', 'nobody') DEFAULT 'connected',
    show_email ENUM('everyone', 'premium', 'connected', 'nobody') DEFAULT 'connected',
    show_photo ENUM('everyone', 'premium', 'connected') DEFAULT 'everyone',
    show_income ENUM('everyone', 'premium', 'connected', 'nobody') DEFAULT 'premium',
    profile_visibility ENUM('everyone', 'premium', 'specific') DEFAULT 'everyone',
    visibility_religion VARCHAR(200) COMMENT 'Restrict to specific religions',
    visibility_caste VARCHAR(500) COMMENT 'Restrict to specific castes',
    visibility_location VARCHAR(200) COMMENT 'Restrict to specific locations',
    allow_messages ENUM('everyone', 'premium', 'connected') DEFAULT 'connected',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Profile Change Requests Table
CREATE TABLE IF NOT EXISTS profile_change_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    section VARCHAR(30) NOT NULL COMMENT 'basic, personal, professional, family, partner',
    old_data JSON NOT NULL,
    new_data JSON NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    admin_note TEXT,
    reviewed_by INT,
    reviewed_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_pcr_user (user_id),
    INDEX idx_pcr_status (status)
) ENGINE=InnoDB;

-- Advertisements Table (homepage banners and sponsor logos)
CREATE TABLE IF NOT EXISTS advertisements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    position ENUM('hero_left', 'hero_right', 'sponsor') NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    link_url VARCHAR(500) DEFAULT '#',
    alt_text VARCHAR(150) DEFAULT 'Advertisement',
    display_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ad_position (position),
    INDEX idx_ad_active (is_active)
) ENGINE=InnoDB;

-- Insert Default Subscription Plans
INSERT INTO plans (name, price, duration_days, features, max_contacts, max_messages) VALUES
('Male Plan - 2 Years', 1000.00, 730, '["2 Year Subscription for Male Candidates","View Contact Details","Send Unlimited Interests","Advanced Search","Chat with Matches","Live Chat","Personal Messages"]', 100, 999),
('Female Plan - 2 Years', 500.00, 730, '["2 Year Subscription for Female Candidates","View Contact Details","Send Unlimited Interests","Advanced Search","Chat with Matches","Personal Messages"]', 100, 999);

-- Insert Default Admin User (password: admin123)
INSERT INTO admin_users (username, email, password, name, role) VALUES
('admin', 'admin@matrimonialshadi.com', '$2y$10$wYzoSbodf1mf/dEaEwUS8.py/Oy1vT.2I6wBm/tELpSO6m8HSZDxW', 'Super Admin', 'super_admin');
