-- Create database
CREATE DATABASE IF NOT EXISTS report_request_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE report_request_system;

-- Create tables
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS report_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fullname VARCHAR(100) NOT NULL,
    report_name VARCHAR(200) NOT NULL,
    details TEXT NOT NULL,
    status ENUM('pending', 'in-progress', 'completed') DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS request_attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (request_id) REFERENCES report_requests(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Insert default admin user (password: admin123)
INSERT INTO admins (username, password, name, email) VALUES
('admin', '$2y$10$8MNXAOYaOLqGxMjVQkGVZeRbMBn0qGQQxj5hB5Nh2Q4SVemZ7YK4e', 'ผู้ดูแลระบบ', 'admin@example.com');
