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

$error_message = '';

// จัดการการเข้าสู่ระบบ
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'config/database.php';
    
    $username = sanitize_input($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error_message = 'กรุณากรอกชื่อผู้ใช้และรหัสผ่าน';
    } else {
        try {
            $db = new Database();
            $conn = $db->getConnection();
            
            // ตรวจสอบข้อมูลผู้ใช้
            $stmt = $conn->prepare("SELECT * FROM users WHERE username = :username AND is_active = 1 LIMIT 1");
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // ตรวจสอบรหัสผ่าน
                if (password_verify($password, $user['password'])) {
                    // ดึงข้อมูลกลุ่มผู้ใช้
                    $stmt = $conn->prepare("SELECT * FROM user_groups WHERE id = :group_id");
                    $stmt->bindParam(':group_id', $user['group_id'], PDO::PARAM_INT);
                    $stmt->execute();
                    $group = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // เก็บข้อมูลผู้ใช้ใน session
                    $_SESSION['user_logged_in'] = true;
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['fullname'];
                    $_SESSION['user_group_id'] = $user['group_id'];
                    $_SESSION['user_group_name'] = $group['group_name'] ?? 'ไม่มีกลุ่ม';
                    $_SESSION['user_permissions'] = json_decode($group['permissions'] ?? '{}', true);
                    
                    // ไปที่หน้าหลัก
                    header('Location: index.php');
                    exit;
                } else {
                    $error_message = 'รหัสผ่านไม่ถูกต้อง';
                }
            } else {
                $error_message = 'ไม่พบชื่อผู้ใช้นี้ในระบบหรือบัญชีถูกระงับ';
            }
        } catch (PDOException $e) {
            $error_message = 'เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ - ระบบขอรายงาน</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <header>
            <h1><i class="fas fa-file-alt"></i> ระบบขอรายงาน</h1>
            <nav>
                <ul>
                    <li><a href="index.php"><i class="fas fa-home"></i> หน้าหลัก</a></li>
                    <li><a href="requests.php"><i class="fas fa-list"></i> รายการคำขอ</a></li>
                    <li><a href="login.php" class="active"><i class="fas fa-sign-in-alt"></i> เข้าสู่ระบบ</a></li>
                    <li><a href="register.php"><i class="fas fa-user-plus"></i> ลงทะเบียน</a></li>
                    <li><a href="admin/login.php"><i class="fas fa-user-shield"></i> เข้าสู่ระบบผู้ดูแล</a></li>
                </ul>
            </nav>
        </header>

        <main>
            <section class="form-container">
                <h2><i class="fas fa-sign-in-alt"></i> เข้าสู่ระบบ</h2>
                
                <?php if (!empty($error_message)): ?>
                <div class="message error" style="display: block;">
                    <?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?>
                </div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="form-group">
                        <label for="username">ชื่อผู้ใช้ <span class="required">*</span></label>
                        <input type="text" id="username" name="username" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">รหัสผ่าน <span class="required">*</span></label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    
                    <button type="submit">
                        <i class="fas fa-sign-in-alt"></i> เข้าสู่ระบบ
                    </button>
                    
                    <p style="margin-top: 1rem; text-align: center;">
                        ยังไม่มีบัญชี? <a href="register.php">ลงทะเบียน</a>
                    </p>
                </form>
            </section>
        </main>

        <footer>
            <p>&copy; <?php echo date('Y'); ?> ระบบขอรายงาน | พัฒนาโดย ทีมพัฒนาระบบ</p>
        </footer>
    </div>
</body>
</html>
