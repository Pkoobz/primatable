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
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `bank_id` VARCHAR(10) UNIQUE NOT NULL,
    `name` varchar(255) NOT NULL,
    `spec_id` INT NOT NULL,
    `created_by` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (spec_id) REFERENCES specs(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
)ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `billers` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `biller_id` VARCHAR(10) UNIQUE NOT NULL,
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
    `bank_id` BIGINT NOT NULL,
    `biller_id` BIGINT NOT NULL,
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

CREATE TABLE `channels` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `created_by` INT NOT NULL,
    `updated_by` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (updated_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `connection_channels` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `prima_data_id` int(11) NOT NULL,
    `channel_id` int(11) NOT NULL,
    `date_live` date NOT NULL,
    `status` enum('active','inactive') NOT NULL DEFAULT 'active',
    `created_by` INT NOT NULL,
    `updated_by` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (prima_data_id) REFERENCES prima_data(id),
    FOREIGN KEY (channel_id) REFERENCES channels(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (updated_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO banks (bank_id, name, spec_id, created_by) VALUES 
('002008001', 'Bank Mandiri', 1, 1),
('014008001', 'BCA', 3, 1),
('009008001', 'BNI', 4, 1),
('002008002', 'BRI', 2, 1),
('022008001', 'CIMB Niaga', 5, 1),
('200008001', 'BTN', 3, 1),
('011008001', 'Bank Danamon', 1, 1),
('013008001', 'Bank Permata', 4, 1),
('028008001', 'Bank OCBC NISP', 2, 1),
('019008001', 'Bank Panin', 5, 1);

-- Update biller inserts with real biller IDs
INSERT INTO billers (biller_id, name, spec_id, created_by) VALUES 
('123451001', 'PLN', 3, 1),
('123452001', 'PDAM', 4, 1),
('123453001', 'Telkomsel', 1, 1),
('123454001', 'XL Axiata', 5, 1),
('123455001', 'Indosat', 2, 1),
('123456001', 'BPJS Kesehatan', 3, 1),
('123457001', 'BPJS TK', 4, 1),
('123458001', 'PGN', 1, 1),
('123459001', 'Smartfren', 2, 1),
('123460001', 'Pertamina', 5, 1);

-- Insert 20 prima_data records
INSERT INTO prima_data (bank_id, biller_id, bank_spec_id, biller_spec_id, date_live, status, created_by, updated_by) VALUES 
(1, 1, 1, 3, '2024-01-15', 'active', 1, 1),
(2, 2, 3, 4, '2024-01-20', 'active', 1, 1),
(3, 3, 4, 1, '2024-01-25', 'active', 1, 1),
(4, 4, 2, 5, '2024-02-01', 'active', 1, 1),
(5, 5, 5, 2, '2024-02-05', 'active', 1, 1),
(6, 6, 3, 3, '2024-02-10', 'active', 1, 1),
(7, 7, 1, 4, '2024-02-15', 'active', 1, 1),
(8, 8, 4, 1, '2024-02-20', 'active', 1, 1),
(9, 9, 2, 2, '2024-02-25', 'active', 1, 1),
(10, 10, 5, 5, '2024-03-01', 'active', 1, 1),
(1, 6, 1, 3, '2024-03-05', 'active', 1, 1),
(2, 7, 3, 4, '2024-03-10', 'active', 1, 1),
(3, 8, 4, 1, '2024-03-15', 'active', 1, 1),
(4, 9, 2, 2, '2024-03-20', 'active', 1, 1),
(5, 10, 5, 5, '2024-03-25', 'active', 1, 1),
(6, 1, 3, 3, '2024-04-01', 'active', 1, 1),
(7, 2, 1, 4, '2024-04-05', 'active', 1, 1),
(8, 3, 4, 1, '2024-04-10', 'inactive', 1, 1),
(9, 4, 2, 5, '2024-04-15', 'inactive', 1, 1),
(10, 5, 5, 2, '2024-04-20', 'inactive', 1, 1);

-- Fourth, insert channels data
INSERT INTO channels (name, description, created_by, updated_by) VALUES 
('ATM', 'Automated Teller Machine', 1, 1),
('IB', 'Internet Banking', 1, 1),
('MB', 'Mobile Banking', 1, 1),
('Branch', 'Physical Bank Branch', 1, 1),
('USSD', 'USSD Banking', 1, 1);

INSERT INTO connection_channels (prima_data_id, channel_id, date_live, created_by, updated_by) VALUES 
-- Connection 1: ATM, IB, MB
(1, 1, '2024-01-15', 1, 1),
(1, 2, '2024-02-01', 1, 1),
(1, 3, '2024-02-15', 1, 1),

-- Connection 2: ATM, Branch
(2, 1, '2024-01-20', 1, 1),
(2, 4, '2024-02-05', 1, 1),

-- Connection 3: IB, MB, USSD
(3, 2, '2024-01-25', 1, 1),
(3, 3, '2024-02-10', 1, 1),
(3, 5, '2024-02-25', 1, 1),

-- Connection 4: All channels
(4, 1, '2024-02-01', 1, 1),
(4, 2, '2024-02-15', 1, 1),
(4, 3, '2024-03-01', 1, 1),
(4, 4, '2024-03-15', 1, 1),
(4, 5, '2024-03-30', 1, 1),

-- Connection 5: MB, USSD
(5, 3, '2024-02-05', 1, 1),
(5, 5, '2024-02-20', 1, 1),

-- Connection 6: ATM, IB, Branch
(6, 1, '2024-02-10', 1, 1),
(6, 2, '2024-02-25', 1, 1),
(6, 4, '2024-03-10', 1, 1),

-- Connection 7: IB, MB
(7, 2, '2024-02-15', 1, 1),
(7, 3, '2024-03-01', 1, 1),

-- Connection 8: ATM, Branch, USSD
(8, 1, '2024-02-20', 1, 1),
(8, 4, '2024-03-05', 1, 1),
(8, 5, '2024-03-20', 1, 1),

-- Connection 9: All channels except Branch
(9, 1, '2024-02-25', 1, 1),
(9, 2, '2024-03-10', 1, 1),
(9, 3, '2024-03-25', 1, 1),
(9, 5, '2024-04-10', 1, 1),

-- Connection 10: IB, MB, Branch
(10, 2, '2024-03-01', 1, 1),
(10, 3, '2024-03-15', 1, 1),
(10, 4, '2024-03-30', 1, 1),

-- Connection 11: ATM, USSD
(11, 1, '2024-03-05', 1, 1),
(11, 5, '2024-03-20', 1, 1),

-- Connection 12: All channels
(12, 1, '2024-03-10', 1, 1),
(12, 2, '2024-03-25', 1, 1),
(12, 3, '2024-04-10', 1, 1),
(12, 4, '2024-04-25', 1, 1),
(12, 5, '2024-05-10', 1, 1),

-- Connection 13: MB, Branch, USSD
(13, 3, '2024-03-15', 1, 1),
(13, 4, '2024-03-30', 1, 1),
(13, 5, '2024-04-15', 1, 1),

-- Connection 14: ATM, IB
(14, 1, '2024-03-20', 1, 1),
(14, 2, '2024-04-05', 1, 1),

-- Connection 15: All channels except ATM
(15, 2, '2024-03-25', 1, 1),
(15, 3, '2024-04-10', 1, 1),
(15, 4, '2024-04-25', 1, 1),
(15, 5, '2024-05-10', 1, 1),

-- Connection 16: IB, MB, Branch
(16, 2, '2024-04-01', 1, 1),
(16, 3, '2024-04-15', 1, 1),
(16, 4, '2024-04-30', 1, 1),

-- Connection 17: ATM, USSD
(17, 1, '2024-04-05', 1, 1),
(17, 5, '2024-04-20', 1, 1),

-- Connection 18: All channels
(18, 1, '2024-04-10', 1, 1),
(18, 2, '2024-04-25', 1, 1),
(18, 3, '2024-05-10', 1, 1),
(18, 4, '2024-05-25', 1, 1),
(18, 5, '2024-06-10', 1, 1),

-- Connection 19: MB, Branch
(19, 3, '2024-04-15', 1, 1),
(19, 4, '2024-04-30', 1, 1),

-- Connection 20: IB, USSD
(20, 2, '2024-04-20', 1, 1),
(20, 5, '2024-05-05', 1, 1);