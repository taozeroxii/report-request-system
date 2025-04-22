<?php
session_start();

// Check if already logged in
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: index.php');
    exit;
}

// เพิ่มฟังก์ชันทำความสะอาดข้อมูล
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// Check login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../config/database.php';
    
    // แก้ไขส่วนตรวจสอบข้อมูลและทำความสะอาดข้อมูลก่อนตรวจสอบ
    $username = isset($_POST['username']) ? sanitize_input($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : ''; // ไม่ sanitize รหัสผ่านเพื่อให้ตรวจสอบได้ถูกต้อง
    
    if (empty($username) || empty($password)) {
        $error_message = 'กรุณากรอกชื่อผู้ใช้และรหัสผ่าน';
    } else {
        try {
            $db = new Database();
            $conn = $db->getConnection();
            
            $stmt = $conn->prepare("SELECT * FROM admins WHERE username = :username LIMIT 1");
            // แก้ไขส่วนการ bind parameters
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $admin = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Verify password
                if (password_verify($password, $admin['password'])) {
                    // Set session
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_id'] = $admin['id'];
                    $_SESSION['admin_name'] = $admin['name'];
                    
                    // Redirect to admin dashboard
                    header('Location: index.php');
                    exit;
                } else {
                    $error_message = 'รหัสผ่านไม่ถูกต้อง';
                }
            } else {
                $error_message = 'ไม่พบชื่อผู้ใช้นี้ในระบบ';
            }
        } catch (PDOException $e) {
            $error_message = 'เกิดข้อผิดพลาดในการเข้าสู่ระบบ: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบผู้ดูแล - ระบบขอรายงาน</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>ระบบขอรายงาน</h1>
            <nav>
                <ul>
                    <li><a href="../index.php">หน้าหลัก</a></li>
                    <li><a href="login.php" class="active">เข้าสู่ระบบผู้ดูแล</a></li>
                </ul>
            </nav>
        </header>

        <main>
            <section class="form-container">
                <h2>เข้าสู่ระบบผู้ดูแล</h2>
                
                <?php if (isset($error_message)): ?>
                <div class="message error" style="display: block;">
                    <?php echo $error_message; ?>
                </div>
                <?php endif; ?>
                
                <form method="POST" action="login.php">
                    <div class="form-group">
                        <label for="username">ชื่อผู้ใช้ <span class="required">*</span></label>
                        <input type="text" id="username" name="username" required>
                    </div>

                    <div class="form-group">
                        <label for="password">รหัสผ่าน <span class="required">*</span></label>
                        <input type="password" id="password" name="password" required>
                    </div>

                    <div class="form-group">
                        <button type="submit">เข้าสู่ระบบ</button>
                    </div>
                </form>
            </section>
        </main>

        <footer>
            <p>&copy; <?php echo date('Y'); ?> ระบบขอรายงาน</p>
        </footer>
    </div>
</body>
</html>
