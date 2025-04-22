<?php
session_start();

// ถ้าเข้าสู่ระบบแล้ว ให้ไปที่หน้าหลัก
if (isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true) {
    header('Location: index.php');
    exit;
}

// ฟังก์ชันทำความสะอาดข้อมูล
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

require_once 'config/database.php';

$success_message = '';
$error_message = '';

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // ดึงข้อมูลกลุ่มผู้ใช้
    $stmt = $conn->query("SELECT * FROM user_groups ORDER BY id");
    $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // จัดการการลงทะเบียน
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = sanitize_input($_POST['username']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $email = sanitize_input($_POST['email']);
        $fullname = sanitize_input($_POST['fullname']);
        
        // ตรวจสอบข้อมูล
        if (empty($username) || empty($password) || empty($confirm_password) || empty($email) || empty($fullname)) {
            $error_message = 'กรุณากรอกข้อมูลให้ครบถ้วน';
        } elseif ($password !== $confirm_password) {
            $error_message = 'รหัสผ่านและยืนยันรหัสผ่านไม่ตรงกัน';
        } elseif (strlen($password) < 6) {
            $error_message = 'รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร';
        } else {
            // ตรวจสอบว่าชื่อผู้ใช้หรืออีเมลซ้ำหรือไม่
            $stmt = $conn->prepare("SELECT * FROM users WHERE username = :username OR email = :email");
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $error_message = 'ชื่อผู้ใช้หรืออีเมลนี้มีอยู่ในระบบแล้ว';
            } else {
                // ตรวจสอบว่ามีการอัปโหลดรูปโปรไฟล์หรือไม่
                $profile_image = null;
                
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
                        $new_file_name = 'user_' . time() . '_' . mt_rand(1000, 9999) . '.' . $file_ext;
                        $file_path = $upload_dir . $new_file_name;
                        
                        if (move_uploaded_file($file_tmp, $file_path)) {
                            $profile_image = $file_path;
                        } else {
                            $error_message = 'ไม่สามารถอัปโหลดรูปโปรไฟล์ได้';
                        }
                    } else {
                        $error_message = 'รูปแบบไฟล์ไม่ถูกต้อง กรุณาอัปโหลดไฟล์รูปภาพเท่านั้น';
                    }
                }
                
                if (empty($error_message)) {
                    // เข้ารหัสรหัสผ่าน
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    
                    // กำหนดกลุ่มผู้ใช้เริ่มต้น (กลุ่มผู้ใช้ทั่วไป)
                    $group_id = 1; // ค่าเริ่มต้นคือกลุ่มผู้ใช้ทั่วไป
                    
                    // เพิ่มผู้ใช้ใหม่
                    $stmt = $conn->prepare("INSERT INTO users (username, password, email, fullname, profile_image, group_id, is_active, created_at) VALUES (:username, :password, :email, :fullname, :profile_image, :group_id, 1, NOW())");
                    $stmt->bindParam(':username', $username);
                    $stmt->bindParam(':password', $hashed_password);
                    $stmt->bindParam(':email', $email);
                    $stmt->bindParam(':fullname', $fullname);
                    $stmt->bindParam(':profile_image', $profile_image);
                    $stmt->bindParam(':group_id', $group_id, PDO::PARAM_INT);
                    
                    if ($stmt->execute()) {
                        $success_message = 'ลงทะเบียนเรียบร้อยแล้ว คุณสามารถเข้าสู่ระบบได้ทันที';
                    } else {
                        $error_message = 'เกิดข้อผิดพลาดในการลงทะเบียน';
                    }
                }
            }
        }
    }
} catch (PDOException $e) {
    $error_message = 'เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ลงทะเบียน - ระบบขอรายงาน</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .preview-profile {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            margin: 0 auto 1rem;
            display: block;
            border: 3px solid var(--primary-light);
        }
        
        .preview-placeholder {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background-color: var(--gray-300);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            color: var(--gray-600);
            font-size: 3rem;
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
                    <li><a href="login.php"><i class="fas fa-sign-in-alt"></i> เข้าสู่ระบบ</a></li>
                    <li><a href="register.php" class="active"><i class="fas fa-user-plus"></i> ลงทะเบียน</a></li>
                    <li><a href="admin/login.php"><i class="fas fa-user-shield"></i> เข้าสู่ระบบผู้ดูแล</a></li>
                </ul>
            </nav>
        </header>

        <main>
            <section class="form-container">
                <h2><i class="fas fa-user-plus"></i> ลงทะเบียนผู้ใช้ใหม่</h2>
                
                <?php if (!empty($success_message)): ?>
                <div class="message success" style="display: block;">
                    <?php echo htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8'); ?>
                    <p>คลิก <a href="login.php">ที่นี่</a> เพื่อเข้าสู่ระบบ</p>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($error_message)): ?>
                <div class="message error" style="display: block;">
                    <?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?>
                </div>
                <?php endif; ?>
                
                <form method="POST" enctype="multipart/form-data">
                    <div style="text-align: center; margin-bottom: 1rem;">
                        <div id="previewPlaceholder" class="preview-placeholder">
                            <i class="fas fa-user"></i>
                        </div>
                        <div id="previewContainer" style="display: none;">
                            <img id="previewImage" src="#" alt="รูปโปรไฟล์" class="preview-profile">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="username">ชื่อผู้ใช้ <span class="required">*</span></label>
                        <input type="text" id="username" name="username" required>
                        <small>ชื่อผู้ใช้ต้องไม่ซ้ำกับผู้ใช้อื่น</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">รหัสผ่าน <span class="required">*</span></label>
                        <input type="password" id="password" name="password" required>
                        <small>รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">ยืนยันรหัสผ่าน <span class="required">*</span></label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">อีเมล <span class="required">*</span></label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="fullname">ชื่อ-นามสกุล <span class="required">*</span></label>
                        <input type="text" id="fullname" name="fullname" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="profile_image">รูปโปรไฟล์</label>
                        <input type="file" id="profile_image" name="profile_image" accept=".jpg,.jpeg,.png,.gif">
                        <small>อัปโหลดรูปภาพขนาดไม่เกิน 2MB (รองรับไฟล์ .jpg, .jpeg, .png, .gif)</small>
                    </div>
                    
                    <button type="submit">
                        <i class="fas fa-user-plus"></i> ลงทะเบียน
                    </button>
                    
                    <p style="margin-top: 1rem; text-align: center;">
                        มีบัญชีอยู่แล้ว? <a href="login.php">เข้าสู่ระบบ</a>
                    </p>
                </form>
            </section>
        </main>

        <footer>
            <p>&copy; <?php echo date('Y'); ?> ระบบขอรายงาน | พัฒนาโดย ทีมพัฒนาระบบ</p>
        </footer>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            // แสดงตัวอย่างรูปโปรไฟล์ก่อนอัปโหลด
            $('#profile_image').on('change', function() {
                const file = this.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        $('#previewImage').attr('src', e.target.result);
                        $('#previewContainer').show();
                        $('#previewPlaceholder').hide();
                    }
                    reader.readAsDataURL(file);
                } else {
                    $('#previewContainer').hide();
                    $('#previewPlaceholder').show();
                }
            });
        });
    </script>
</body>
</html>
