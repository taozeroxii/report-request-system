-- เพิ่มคอลัมน์ profile_image ในตาราง admins
ALTER TABLE admins ADD COLUMN profile_image VARCHAR(255) DEFAULT NULL;

-- สร้างตาราง user_groups สำหรับกลุ่มผู้ใช้
CREATE TABLE IF NOT EXISTS user_groups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_name VARCHAR(100) NOT NULL,
    description TEXT,
    permissions TEXT COMMENT 'เก็บสิทธิ์การใช้งานในรูปแบบ JSON',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- สร้างตาราง users สำหรับผู้ใช้ทั่วไป
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    fullname VARCHAR(100) NOT NULL,
    profile_image VARCHAR(255) DEFAULT NULL,
    group_id INT,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES user_groups(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- เพิ่มข้อมูลกลุ่มผู้ใช้เริ่มต้น
INSERT INTO user_groups (group_name, description, permissions) VALUES
('ผู้ใช้ทั่วไป', 'ผู้ใช้ทั่วไปที่สามารถส่งคำขอและดูสถานะได้', '{"submit_request": true, "view_own_requests": true}'),
('ผู้ใช้ขั้นสูง', 'ผู้ใช้ที่สามารถดูคำขอทั้งหมดได้', '{"submit_request": true, "view_own_requests": true, "view_all_requests": true}');

-- ปรับปรุงตาราง report_requests เพื่อเชื่อมโยงกับผู้ใช้
ALTER TABLE report_requests ADD COLUMN user_id INT DEFAULT NULL;
ALTER TABLE report_requests ADD CONSTRAINT fk_request_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL;

-- ปรับปรุงตาราง request_comments เพื่อเชื่อมโยงกับผู้ใช้
ALTER TABLE request_comments ADD COLUMN user_id INT DEFAULT NULL;
ALTER TABLE request_comments ADD COLUMN admin_id INT DEFAULT NULL;
ALTER TABLE request_comments ADD CONSTRAINT fk_comment_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL;
ALTER TABLE request_comments ADD CONSTRAINT fk_comment_admin FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL;
