<?php
session_start();

// ฟังก์ชันทำความสะอาดข้อมูล
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// ตรวจสอบว่าเข้าสู่ระบบแล้วหรือไม่
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

require_once 'config/database.php';

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // ดึงข้อมูลผู้ใช้
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->bindParam(':id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        header('Location: index.php');
        exit;
    }
    
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // ดึงข้อมูลกลุ่มผู้ใช้
    $stmt = $conn->prepare("SELECT * FROM user_groups WHERE id = :group_id");
    $stmt->bindParam(':group_id', $user['group_id'], PDO::PARAM_INT);
    $stmt->execute();
    $group = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // จัดการการอัปเดตโปรไฟล์
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // ตรวจสอบว่าเป็นการอัปเดตข้อมูลทั่วไปหรือรหัสผ่าน
        if (isset($_POST['update_profile'])) {
            $email = sanitize_input($_POST['email']);
            $fullname = sanitize_input($_POST['fullname']);
            
            if (empty($email) || empty($fullname)) {
                $error_message = 'กรุณากรอกข้อมูลให้ครบถ้วน';
            } else {
                // ตรวจสอบว่าอีเมลซ้ำกับผู้ใช้อื่นหรือไม่
                $stmt = $conn->prepare("SELECT * FROM users WHERE email = :email AND id != :id");
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':id', $user_id, PDO::PARAM_INT);
                $stmt->execute();
                
                if ($stmt->rowCount() > 0) {
                    $error_message = 'อีเมลนี้มีอยู่ในระบบแล้ว';
                } else {
                    // ตรวจสอบว่ามีการอัปโหลดรูปโปรไฟล์หรือไม่
                    $profile_image = $user['profile_image']; // ใช้รูปเดิมเป็นค่าเริ่มต้น
                    
                    if (!empty($_FILES['profile_image']['name'])) {
                        $upload_dir = 'uploads/profiles/';
                        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                        
                        if (!file_exists($upload_dir)) {
                            mkdir($upload_dir, 0777, true);
                        }
                        
                        $file_name = $_FILES['profile_image']['name'];
                        $file_tmp = $_FILES['profile_image']['tmp_name'];
                        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                        
                        if (in_array($file_ext, $allowed_extensions)) {
                            $new_file_name = 'user_' . $user_id . '_' . time() . '.' . $file_ext;
                            $file_path = $upload_dir . $new_file_name;
                            
                            if (move_uploaded_file($file_tmp, $file_path)) {
                                // ลบรูปเก่าถ้ามี
                                if (!empty($user['profile_image']) && file_exists($user['profile_image'])) {
                                    unlink($user['profile_image']);
                                }
                                
                                $profile_image = $file_path;
                            } else {
                                $error_message = 'ไม่สามารถอัปโหลดรูปโปรไฟล์ได้';
                            }
                        } else {
                            $error_message = 'รูปแบบไฟล์ไม่ถูกต้อง กรุณาอัปโหลดไฟล์รูปภาพเท่านั้น';
                        }
                    }
                    
                    if (empty($error_message)) {
                        // อัปเดตข้อมูลในฐานข้อมูล
                        $stmt = $conn->prepare("UPDATE users SET email = :email, fullname = :fullname, profile_image = :profile_image, updated_at = NOW() WHERE id = :id");
                        $stmt->bindParam(':email', $email);
                        $stmt->bindParam(':fullname', $fullname);
                        $stmt->bindParam(':profile_image', $profile_image);
                        $stmt->bindParam(':id', $user_id, PDO::PARAM_INT);
                        
                        if ($stmt->execute()) {
                            $success_message = 'อัปเดตข้อมูลโปรไฟล์เรียบร้อยแล้ว';
                            
                            // อัปเดตข้อมูลใน session
                            $_SESSION['user_name'] = $fullname;
                            
                            // ดึงข้อมูลใหม่
                            $stmt = $conn->prepare("SELECT * FROM users WHERE id = :id");
                            $stmt->bindParam(':id', $user_id, PDO::PARAM_INT);
                            $stmt->execute();
                            $user = $stmt->fetch(PDO::FETCH_ASSOC);
                        } else {
                            $error_message = 'เกิดข้อผิดพลาดในการอัปเดตข้อมูล';
                        }
                    }
                }
            }
        } elseif (isset($_POST['update_password'])) {
            $current_password = $_POST['current_password'];
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];
            
            if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                $error_message = 'กรุณากรอกข้อมูลให้ครบถ้วน';
            } elseif ($new_password !== $confirm_password) {
                $error_message = 'รหัสผ่านใหม่และยืนยันรหัสผ่านไม่ตรงกัน';
            } elseif (strlen($new_password) < 6) {
                $error_message = 'รหัสผ่านใหม่ต้องมีความยาวอย่างน้อย 6 ตัวอักษร';
            } else {
                // ตรวจสอบรหัสผ่านปัจจุบัน
                if (password_verify($current_password, $user['password'])) {
                    // เข้ารหัสรหัสผ่านใหม่
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    
                    // อัปเดตรหัสผ่านในฐานข้อมูล
                    $stmt = $conn->prepare("UPDATE users SET password = :password, updated_at = NOW() WHERE id = :id");
                    $stmt->bindParam(':password', $hashed_password);
                    $stmt->bindParam(':id', $user_id, PDO::PARAM_INT);
                    
                    if ($stmt->execute()) {
                        $success_message = 'อัปเดตรหัสผ่านเรียบร้อยแล้ว';
                    } else {
                        $error_message = 'เกิดข้อผิดพลาดในการอัปเดตรหัสผ่าน';
                    }
                } else {
                    $error_message = 'รหัสผ่านปัจจุบันไม่ถูกต้อง';
                }
            }
        }
    }
} catch (PDOException $e) {
    $error_message = 'เกิดข้อผิดพลาดในการดึงข้อมูล: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>โปรไฟล์ - ระบบขอรายงาน</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .profile-container {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 2rem;
        }
        
        .profile-sidebar {
            text-align: center;
        }
        
        .profile-image {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            margin: 0 auto 1rem;
            border: 3px solid var(--primary-color);
            box-shadow: var(--shadow-md);
        }
        
        .profile-image-placeholder {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background-color: var(--gray-300);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            border: 3px solid var(--primary-color);
            box-shadow: var(--shadow-md);
            color: var(--gray-600);
            font-size: 3rem;
        }
        
        .profile-tabs {
            display: flex;
            margin-bottom: 1.5rem;
            border-bottom: 1px solid var(--gray-300);
        }
        
        .profile-tab {
            padding: 0.75rem 1.5rem;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: var(--transition);
        }
        
        .profile-tab.active {
            border-bottom: 2px solid var(--primary-color);
            color: var(--primary-color);
            font-weight: 500;
        }
        
        .profile-tab:hover:not(.active) {
            border-bottom: 2px solid var(--gray-400);
        }
        
        .profile-content {
            display: none;
        }
        
        .profile-content.active {
            display: block;
            animation: fadeIn 0.3s ease-out;
        }
        
        .user-group-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            background-color: var(--primary-light);
            color: var(--primary-dark);
            border-radius: 1rem;
            font-size: 0.875rem;
            font-weight: 500;
            margin-top: 0.5rem;
        }
        
        @media (max-width: 768px) {
            .profile-container {
                grid-template-columns: 1fr;
            }
            
            .profile-sidebar {
                margin-bottom: 2rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1><i class="fas fa-file-alt"></i> ระบบขอรายงาน</h1>
            <nav>
                <ul>
                    <li><a href="index.php"><i class="fas fa-home"></i> หน้าหลัก</a></li>
                    <li><a href="requests.php"><i class="fas fa-list"></i> รายการคำขอ</a></li>
                    <li><a href="profile.php" class="active"><i class="fas fa-user-circle"></i> โปรไฟล์</a></li>
                    <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> ออกจากระบบ</a></li>
                    <li><a href="admin/login.php"><i class="fas fa-user-shield"></i> เข้าสู่ระบบผู้ดูแล</a></li>
                </ul>
            </nav>
        </header>

        <main>
            <section class="form-container">
                <h2><i class="fas fa-user-circle"></i> โปรไฟล์ของฉัน</h2>
                
                <?php if (!empty($success_message)): ?>
                <div class="message success" style="display: block;">
                    <?php echo htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8'); ?>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($error_message)): ?>
                <div class="message error" style="display: block;">
                    <?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?>
                </div>
                <?php endif; ?>
                
                <div class="profile-container">
                    <div class="profile-sidebar">
                        <?php if (!empty($user['profile_image']) && file_exists($user['profile_image'])): ?>
                            <img src="<?php echo htmlspecialchars($user['profile_image'], ENT_QUOTES, 'UTF-8'); ?>" alt="รูปโปรไฟล์" class="profile-image">
                        <?php else: ?>
                            <div class="profile-image-placeholder">
                                <i class="fas fa-user"></i>
                            </div>
                        <?php endif; ?>
                        <h3><?php echo htmlspecialchars($user['fullname'], ENT_QUOTES, 'UTF-8'); ?></h3>
                        <div class="user-group-badge">
                            <i class="fas fa-users"></i> <?php echo htmlspecialchars($group['group_name'] ?? 'ไม่มีกลุ่ม', ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                    </div>
                    
                    <div class="profile-main">
                        <div class="profile-tabs">
                            <div class="profile-tab active" data-tab="profile-info">
                                <i class="fas fa-user"></i> ข้อมูลทั่วไป
                            </div>
                            <div class="profile-tab" data-tab="change-password">
                                <i class="fas fa-key"></i> เปลี่ยนรหัสผ่าน
                            </div>
                        </div>
                        
                        <div id="profile-info" class="profile-content active">
                            <form method="POST" enctype="multipart/form-data">
                                <div class="form-group">
                                    <label for="username">ชื่อผู้ใช้</label>
                                    <input type="text" id="username" value="<?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?>" readonly disabled>
                                    <small>ไม่สามารถเปลี่ยนชื่อผู้ใช้ได้</small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="fullname">ชื่อ-นามสกุล <span class="required">*</span></label>
                                    <input type="text" id="fullname" name="fullname" value="<?php echo htmlspecialchars($user['fullname'], ENT_QUOTES, 'UTF-8'); ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="email">อีเมล <span class="required">*</span></label>
                                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8'); ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="profile_image">รูปโปรไฟล์</label>
                                    <input type="file" id="profile_image" name="profile_image" accept=".jpg,.jpeg,.png,.gif">
                                    <small>อัปโหลดรูปภาพขนาดไม่เกิน 2MB (รองรับไฟล์ .jpg, .jpeg, .png, .gif)</small>
                                </div>
                                
                                <button type="submit" name="update_profile">
                                    <i class="fas fa-save"></i> บันทึกข้อมูล
                                </button>
                            </form>
                        </div>
                        
                        <div id="change-password" class="profile-content">
                            <form method="POST">
                                <div class="form-group">
                                    <label for="current_password">รหัสผ่านปัจจุบัน <span class="required">*</span></label>
                                    <input type="password" id="current_password" name="current_password" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="new_password">รหัสผ่านใหม่ <span class="required">*</span></label>
                                    <input type="password" id="new_password" name="new_password" required>
                                    <small>รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร</small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="confirm_password">ยืนยันรหัสผ่านใหม่ <span class="required">*</span></label>
                                    <input type="password" id="confirm_password" name="confirm_password" required>
                                </div>
                                
                                <button type="submit" name="update_password">
                                    <i class="fas fa-key"></i> เปลี่ยนรหัสผ่าน
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <footer>
            <p>&copy; <?php echo date('Y'); ?> ระบบขอรายงาน | พัฒนาโดย ทีมพัฒนาระบบ</p>
        </footer>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            // สลับแท็บ
            $('.profile-tab').on('click', function() {
                const tabId = $(this).data('tab');
                
                // เปลี่ยนแท็บที่แอคทีฟ
                $('.profile-tab').removeClass('active');
                $(this).addClass('active');
                
                // แสดงเนื้อหาที่เกี่ยวข้อง
                $('.profile-content').removeClass('active');
                $('#' + tabId).addClass('active');
            });
        });
    </script>
</body>
</html>
