DROP DATABASE IF EXISTS primacom_db;

-- Create database
CREATE DATABASE primacom_db;

-- Use the database
USE primacom_db;

-- Create users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('user', 'admin') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create password_resets table (for forgot password functionality)
CREATE TABLE password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Create user_sessions table (for managing active sessions)
CREATE TABLE user_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    session_token VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE `banks` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(255) NOT NULL,
    `created_by` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

CREATE TABLE `billers` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(255) NOT NULL,
    `created_by` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

CREATE TABLE `specs` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(255) NOT NULL,
    `created_by` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

CREATE TABLE `activity_logs` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `action_type` enum('create','update','delete') NOT NULL,
    `table_name` varchar(50) NOT NULL,
    `record_id` int(11) NOT NULL,
    `old_data` JSON DEFAULT NULL,
    `new_data` JSON DEFAULT NULL,
    `notes` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE `prima_data` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `bank_id` int(11) NOT NULL,
    `biller_id` int(11) NOT NULL,
    `bank_spec_id` int(11) NOT NULL,
    `biller_spec_id` int(11) NOT NULL,
    `date_live` date NOT NULL,
    `status` enum('active','inactive') NOT NULL DEFAULT 'active',
    `notes` TEXT DEFAULT NULL,
    `created_by` INT NOT NULL,
    `updated_by` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (bank_id) REFERENCES banks(id),
    FOREIGN KEY (biller_id) REFERENCES billers(id),
    FOREIGN KEY (bank_spec_id) REFERENCES specs(id),
    FOREIGN KEY (biller_spec_id) REFERENCES specs(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (updated_by) REFERENCES users(id)
);


INSERT INTO users (username, email, password, role) VALUES 
('admin', 'admin@primacom.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

INSERT INTO banks (name, created_by) VALUES 
('Bank Mandiri', 1),
('BCA', 1),
('BNI', 1),
('BRI', 1),
('CIMB Niaga', 1);

-- Insert test billers
INSERT INTO billers (name, created_by) VALUES 
('PLN', 1),
('PDAM', 1),
('Telkomsel', 1),
('XL Axiata', 1),
('Indosat', 1);

-- Insert test specs
INSERT INTO specs (name, created_by) VALUES 
('API v1.0', 1),
('API v2.0', 1),
('REST', 1),
('SOAP', 1),
('HTTP', 1);

-- Insert test connections
INSERT INTO prima_data (bank_id, biller_id, bank_spec_id, biller_spec_id, date_live, status, created_by, updated_by) VALUES 
(1, 1, 1, 2, '2024-01-01', 'active', 1, 1),
(2, 2, 2, 3, '2024-01-15', 'active', 1, 1),
(3, 3, 3, 4, '2024-02-01', 'inactive', 1, 1),
(4, 4, 4, 5, '2024-02-15', 'active', 1, 1),
(5, 5, 5, 1, '2024-03-01', 'active', 1, 1),
(1, 2, 3, 4, '2024-03-15', 'inactive', 1, 1),
(2, 3, 4, 5, '2024-04-01', 'active', 1, 1),
(3, 4, 5, 1, '2024-04-15', 'active', 1, 1),
(4, 5, 1, 2, '2024-05-01', 'inactive', 1, 1),
(5, 1, 2, 3, '2024-05-15', 'active', 1, 1);