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

INSERT INTO users (username, email, password, role) VALUES 
('admin', 'admin@primacom.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

CREATE TABLE `specs` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(255) NOT NULL,
    `created_by` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (created_by) REFERENCES users(id)
)ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO specs (name, created_by) VALUES 
('API v1.0', 1),
('API v2.0', 1),
('REST', 1),
('SOAP', 1),
('HTTP', 1),
('JSON', 1),
('XML', 1);

CREATE TABLE `banks` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(255) NOT NULL,
    `spec_id` INT NOT NULL,
    `created_by` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (spec_id) REFERENCES specs(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
)ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `billers` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(255) NOT NULL,
    `spec_id` INT NOT NULL,
    `created_by` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (spec_id) REFERENCES specs(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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

CREATE TABLE `activity_logs` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `action_type` enum('create','update','delete','view','filter','sort','login','logout') NOT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO banks (name, spec_id, created_by) VALUES 
('Bank Mandiri', 1, 1),  -- Using API v1.0
('BCA', 3, 1),          -- Using REST
('BNI', 4, 1),          -- Using SOAP
('BRI', 2, 1),          -- Using API v2.0
('CIMB Niaga', 5, 1);   -- Using HTTP

-- Insert billers using existing specs (reusing specs from different banks)
INSERT INTO billers (name, spec_id, created_by) VALUES 
('PLN', 3, 1),          -- Using REST
('PDAM', 4, 1),         -- Using SOAP
('Telkomsel', 1, 1),    -- Using API v1.0
('XL Axiata', 5, 1),    -- Using HTTP
('Indosat', 2, 1);      -- Using API v2.0

-- Insert test connections
INSERT INTO prima_data (bank_id, biller_id, bank_spec_id, biller_spec_id, date_live, status, created_by, updated_by) VALUES 
(2, 2, 3, 4, '2024-01-15', 'active', 1, 1),
(3, 3, 4, 1, '2024-02-01', 'inactive', 1, 1),
(4, 4, 2, 5, '2024-02-15', 'active', 1, 1),
(5, 5, 5, 2, '2024-03-01', 'active', 1, 1),
(1, 2, 1, 4, '2024-03-15', 'inactive', 1, 1),
(2, 3, 3, 1, '2024-04-01', 'active', 1, 1),
(3, 4, 4, 5, '2024-04-15', 'active', 1, 1),
(4, 5, 2, 2, '2024-05-01', 'inactive', 1, 1),
(5, 1, 5, 3, '2024-05-15', 'active', 1, 1),
(1, 3, 1, 1, '2024-06-01', 'active', 1, 1),
(2, 4, 3, 5, '2024-06-15', 'inactive', 1, 1),
(3, 5, 4, 2, '2024-07-01', 'active', 1, 1),
(4, 1, 2, 3, '2024-07-15', 'active', 1, 1),
(5, 2, 5, 4, '2024-08-01', 'inactive', 1, 1),
(1, 4, 1, 5, '2024-08-15', 'active', 1, 1),
(2, 5, 3, 2, '2024-09-01', 'active', 1, 1),
(3, 1, 4, 3, '2024-09-15', 'inactive', 1, 1),
(4, 2, 2, 4, '2024-10-01', 'active', 1, 1),
(5, 3, 5, 1, '2024-10-15', 'active', 1, 1),
(1, 5, 1, 2, '2024-11-01', 'inactive', 1, 1),
(2, 1, 3, 3, '2024-11-15', 'active', 1, 1),
(3, 2, 4, 4, '2024-12-01', 'active', 1, 1),
(4, 3, 2, 1, '2024-12-15', 'inactive', 1, 1),
(5, 4, 5, 5, '2025-01-01', 'active', 1, 1),
(1, 2, 1, 4, '2025-01-15', 'active', 1, 1),
(2, 3, 3, 1, '2025-02-01', 'inactive', 1, 1),
(3, 4, 4, 5, '2025-02-15', 'active', 1, 1),
(4, 5, 2, 2, '2025-03-01', 'active', 1, 1),
(5, 1, 5, 3, '2025-03-15', 'inactive', 1, 1);