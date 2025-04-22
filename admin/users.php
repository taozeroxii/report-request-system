<?php
session_start();

// ฟังก์ชันทำความสะอาดข้อมูล
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// Check if logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

require_once '../config/database.php';

$success_message = '';
$error_message = '';

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // ดึงข้อมูลกลุ่มผู้ใช้
    $stmt = $conn->query("SELECT * FROM user_groups ORDER BY id");
    $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // จัดการการลบผู้ใช้
    if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
        $user_id = (int)$_GET['id'];
        
        // ตรวจสอบว่ามีผู้ใช้นี้อยู่จริง
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = :id");
        $stmt->bindParam(':id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            // ลบผู้ใช้
            $stmt = $conn->prepare("DELETE FROM users WHERE id = :id");
            $stmt->bindParam(':id', $user_id, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                $success_message = 'ลบผู้ใช้เรียบร้อยแล้ว';
            } else {
                $error_message = 'เกิดข้อผิดพลาดในการลบผู้ใช้';
            }
        } else {
            $error_message = 'ไม่พบผู้ใช้ที่ต้องการลบ';
        }
    }
    
    // จัดการการเพิ่มหรือแก้ไขผู้ใช้
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['add_user'])) {
            // เพิ่มผู้ใช้ใหม่
            $username = sanitize_input($_POST['username']);
            $password = $_POST['password'];
            $email = sanitize_input($_POST['email']);
            $fullname = sanitize_input($_POST['fullname']);
            $group_id = (int)$_POST['group_id'];
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            // ตรวจสอบข้อมูล
            if (empty($username) || empty($password) || empty($email) || empty($fullname)) {
                $error_message = 'กรุณากรอกข้อมูลให้ครบถ้วน';
            } else {
                // ตรวจสอบว่าชื่อผู้ใช้หรืออีเมลซ้ำหรือไม่
                $stmt = $conn->prepare("SELECT * FROM users WHERE username = :username OR email = :email");
                $stmt->bindParam(':username', $username);
                $stmt->bindParam(':email', $email);
                $stmt->execute();
                
                if ($stmt->rowCount() > 0) {
                    $error_message = 'ชื่อผู้ใช้หรืออีเมลนี้มีอยู่ในระบบแล้ว';
                } else {
                    // เข้ารหัสรหัสผ่าน
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    
                    // ตรวจสอบว่ามีการอัปโหลดรูปโปรไฟล์หรือไม���
                    $profile_image = null;
                    
                    if (!empty($_FILES['profile_image']['name'])) {
                        $upload_dir = '../uploads/profiles/';
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
                                $profile_image = str_replace('../', '', $file_path);
                            } else {
                                $error_message = 'ไม่สามารถอัปโหลดรูปโปรไฟล์ได้';
                            }
                        } else {
                            $error_message = 'รูปแบบไฟล์ไม่ถูกต้อง กรุณาอัปโหลดไฟล์รูปภาพเท่านั้น';
                        }
                    }
                    
                    if (empty($error_message)) {
                        // เพิ่มผู้ใช้ใหม่
                        $stmt = $conn->prepare("INSERT INTO users (username, password, email, fullname, profile_image, group_id, is_active, created_at) VALUES (:username, :password, :email, :fullname, :profile_image, :group_id, :is_active, NOW())");
                        $stmt->bindParam(':username', $username);
                        $stmt->bindParam(':password', $hashed_password);
                        $stmt->bindParam(':email', $email);
                        $stmt->bindParam(':fullname', $fullname);
                        $stmt->bindParam(':profile_image', $profile_image);
                        $stmt->bindParam(':group_id', $group_id, PDO::PARAM_INT);
                        $stmt->bindParam(':is_active', $is_active, PDO::PARAM_INT);
                        
                        if ($stmt->execute()) {
                            $success_message = 'เพิ่มผู้ใช้เรียบร้อยแล้ว';
                        } else {
                            $error_message = 'เกิดข้อผิดพลาดในการเพิ่มผู้ใช้';
                        }
                    }
                }
            }
        } elseif (isset($_POST['edit_user'])) {
            // แก้ไขผู้ใช้
            $user_id = (int)$_POST['user_id'];
            $email = sanitize_input($_POST['email']);
            $fullname = sanitize_input($_POST['fullname']);
            $group_id = (int)$_POST['group_id'];
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            // ตรวจสอบข้อมูล
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
                    // ดึงข้อมูลผู้ใช้เดิม
                    $stmt = $conn->prepare("SELECT * FROM users WHERE id = :id");
                    $stmt->bindParam(':id', $user_id, PDO::PARAM_INT);
                    $stmt->execute();
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // ตรวจสอบว่ามีการอัปโหลดรูปโปรไฟล์หรือไม่
                    $profile_image = $user['profile_image']; // ใช้รูปเดิมเป็นค่าเริ่มต้น
                    
                    if (!empty($_FILES['profile_image']['name'])) {
                        $upload_dir = '../uploads/profiles/';
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
                                if (!empty($user['profile_image']) && file_exists('../' . $user['profile_image'])) {
                                    unlink('../' . $user['profile_image']);
                                }
                                
                                $profile_image = str_replace('../', '', $file_path);
                            } else {
                                $error_message = 'ไม่สามารถอัปโหลดรูปโปรไฟล์ได้';
                            }
                        } else {
                            $error_message = 'รูปแบบไฟล์ไม่ถูกต้อง กรุณาอัปโหลดไฟล์รูปภาพเท่านั้น';
                        }
                    }
                    
                    // ตรวจสอบว่ามีการเปลี่ยนรหัสผ่านหรือไม่
                    $password_sql = '';
                    $params = [
                        ':email' => $email,
                        ':fullname' => $fullname,
                        ':profile_image' => $profile_image,
                        ':group_id' => $group_id,
                        ':is_active' => $is_active,
                        ':id' => $user_id
                    ];
                    
                    if (!empty($_POST['password'])) {
                        $hashed_password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                        $password_sql = ', password = :password';
                        $params[':password'] = $hashed_password;
                    }
                    
                    if (empty($error_message)) {
                        // อัปเดตข้อมูลผู้ใช้
                        $stmt = $conn->prepare("UPDATE users SET email = :email, fullname = :fullname, profile_image = :profile_image, group_id = :group_id, is_active = :is_active, updated_at = NOW() $password_sql WHERE id = :id");
                        
                        foreach ($params as $key => $value) {
                            $stmt->bindValue($key, $value);
                        }
                        
                        if ($stmt->execute()) {
                            $success_message = 'อัปเดตข้อมูลผู้ใช้เรียบร้อยแล้ว';
                        } else {
                            $error_message = 'เกิดข้อผิดพลาดในการอัปเดตข้อมูลผู้ใช้';
                        }
                    }
                }
            }
        }
    }
    
    // ดึงข้อมูลผู้ใช้ทั้งหมด
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = 10;
    $offset = ($page - 1) * $limit;
    
    // ดึงจำนวนผู้ใช้ทั้งหมด
    $stmt = $conn->query("SELECT COUNT(*) FROM users");
    $total_users = $stmt->fetchColumn();
    $total_pages = ceil($total_users / $limit);
    
    // ดึงข้อมูลผู้ใช้ตามหน้า
    $stmt = $conn->prepare("SELECT u.*, g.group_name FROM users u LEFT JOIN user_groups g ON u.group_id = g.id ORDER BY u.id DESC LIMIT :limit OFFSET :offset");
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ดึงข้อมูลผู้ใช้ที่ต้องการแก้ไข
    $edit_user = null;
    if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
        $user_id = (int)$_GET['id'];
        
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = :id");
        $stmt->bindParam(':id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $edit_user = $stmt->fetch(PDO::FETCH_ASSOC);
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
    <title>จัดการผู้ใช้ - ระบบขอรายงาน</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .user-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .btn-edit, .btn-delete {
            padding: 0.25rem 0.5rem;
            border-radius: var(--border-radius);
            text-decoration: none;
            font-size: 0.875rem;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        .btn-edit {
            background-color: var(--primary-color);
            color: white;
        }
        
        .btn-delete {
            background-color: var(--error-color);
            color: white;
        }
        
        .btn-edit:hover, .btn-delete:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }
        
        .user-status {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 5px;
        }
        
        .user-status.active {
            background-color: var(--success-color);
        }
        
        .user-status.inactive {
            background-color: var(--gray-400);
        }
        
        .user-profile {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .user-avatar-placeholder {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--gray-300);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gray-600);
            font-size: 1.25rem;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            overflow: auto;
        }
        
        .modal-content {
            background-color: white;
            margin: 10% auto;
            padding: 2rem;
            border-radius: var(--border-radius);
            max-width: 600px;
            width: 90%;
            box-shadow: var(--shadow-lg);
            position: relative;
        }
        
        .close-modal {
            position: absolute;
            top: 1rem;
            right: 1rem;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--gray-500);
        }
        
        .close-modal:hover {
            color: var(--gray-800);
        }
        
        .modal-title {
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--gray-200);
            color: var(--gray-800);
        }
        
        .preview-profile {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            margin: 0 auto 1rem;
            display: block;
            border: 3px solid var(--primary-light);
        }
        
        .preview-placeholder {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background-color: var(--gray-300);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            color: var(--gray-600);
            font-size: 2.5rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1><i class="fas fa-file-alt"></i> ระบบขอรายงาน - หน้าผู้ดูแล</h1>
            <nav>
                <ul>
                    <li><a href="index.php"><i class="fas fa-list"></i> จัดการคำขอ</a></li>
                    <li><a href="users.php" class="active"><i class="fas fa-users"></i> จัดการผู้ใช้</a></li>
                    <li><a href="groups.php"><i class="fas fa-user-tag"></i> จัดการกลุ่มผู้ใช้</a></li>
                    <li><a href="profile.php"><i class="fas fa-user-circle"></i> โปรไฟล์</a></li>
                    <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> ออกจากระบบ</a></li>
                </ul>
            </nav>
        </header>

        <main>
            <section class="form-container">
                <h2><i class="fas fa-users"></i> จัดการผู้ใช้</h2>
                
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
                
                <button id="addUserBtn" class="btn-primary" style="margin-bottom: 1.5rem;">
                    <i class="fas fa-user-plus"></i> เพิ่มผู้ใช้ใหม่
                </button>
                
                <?php if (empty($users)): ?>
                <p>ไม่พบข้อมูลผู้ใช้</p>
                <?php else: ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>ผู้ใช้</th>
                                <th>อีเมล</th>
                                <th>กลุ่มผู้ใช้</th>
                                <th>สถานะ</th>
                                <th>วันที่สร้าง</th>
                                <th>จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo $user['id']; ?></td>
                                <td>
                                    <div class="user-profile">
                                        <?php if (!empty($user['profile_image']) && file_exists('../' . $user['profile_image'])): ?>
                                            <img src="../<?php echo htmlspecialchars($user['profile_image'], ENT_QUOTES, 'UTF-8'); ?>" alt="รูปโปรไฟล์" class="user-avatar">
                                        <?php else: ?>
                                            <div class="user-avatar-placeholder">
                                                <i class="fas fa-user"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <div><?php echo htmlspecialchars($user['fullname'], ENT_QUOTES, 'UTF-8'); ?></div>
                                            <small><?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($user['group_name'] ?? 'ไม่มีกลุ่ม', ENT_QUOTES, 'UTF-8'); ?></td>
                                <td>
                                    <?php if ($user['is_active']): ?>
                                        <span class="user-status active"></span> ใช้งาน
                                    <?php else: ?>
                                        <span class="user-status inactive"></span> ไม่ใช้งาน
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('d/m/Y H:i', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <div class="user-actions">
                                        <a href="?action=edit&id=<?php echo $user['id']; ?>" class="btn-edit">
                                            <i class="fas fa-edit"></i> แก้ไข
                                        </a>
                                        <a href="?action=delete&id=<?php echo $user['id']; ?>" class="btn-delete" onclick="return confirm('คุณต้องการลบผู้ใช้นี้ใช่หรือไม่?');">
                                            <i class="fas fa-trash-alt"></i> ลบ
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>" class="page-link">&laquo; ก่อนหน้า</a>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?php echo $i; ?>" class="page-link <?php echo $i === $page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>" class="page-link">ถัดไป &raquo;</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </section>
        </main>

        <footer>
            <p>&copy; <?php echo date('Y'); ?> ระบบขอรายงาน | พัฒนาโดย ทีมพัฒนาระบบ</p>
        </footer>
    </div>
    
    <!-- Modal เพิ่มผู้ใช้ -->
    <div id="addUserModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" id="closeAddModal">&times;</span>
            <h3 class="modal-title"><i class="fas fa-user-plus"></i> เพิ่มผู้ใช้ใหม่</h3>
            
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
                </div>
                
                <div class="form-group">
                    <label for="password">รหัสผ่าน <span class="required">*</span></label>
                    <input type="password" id="password" name="password" required>
                    <small>รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร</small>
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
                    <label for="group_id">กลุ่มผู้ใช้ <span class="required">*</span></label>
                    <select id="group_id" name="group_id" required>
                        <option value="">-- เลือกกลุ่มผู้ใช้ --</option>
                        <?php foreach ($groups as $group): ?>
                        <option value="<?php echo $group['id']; ?>"><?php echo htmlspecialchars($group['group_name'], ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="profile_image">รูปโปรไฟล์</label>
                    <input type="file" id="profile_image" name="profile_image" accept=".jpg,.jpeg,.png,.gif">
                    <small>อัปโหลดรูปภาพขนาดไม่เกิน 2MB (รองรับไฟล์ .jpg, .jpeg, .png, .gif)</small>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_active" checked> เปิดใช้งาน
                    </label>
                </div>
                
                <button type="submit" name="add_user">
                    <i class="fas fa-save"></i> บันทึกข้อมูล
                </button>
            </form>
        </div>
    </div>
    
    <!-- Modal แก้ไขผู้ใช้ -->
    <?php if ($edit_user): ?>
    <div id="editUserModal" class="modal" style="display: block;">
        <div class="modal-content">
            <span class="close-modal" id="closeEditModal">&times;</span>
            <h3 class="modal-title"><i class="fas fa-user-edit"></i> แก้ไขข้อมูลผู้ใช้</h3>
            
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="user_id" value="<?php echo $edit_user['id']; ?>">
                
                <div style="text-align: center; margin-bottom: 1rem;">
                    <?php if (!empty($edit_user['profile_image']) && file_exists('../' . $edit_user['profile_image'])): ?>
                        <img src="../<?php echo htmlspecialchars($edit_user['profile_image'], ENT_QUOTES, 'UTF-8'); ?>" alt="รูปโปรไฟล์" class="preview-profile" id="editPreviewImage">
                    <?php else: ?>
                        <div id="editPreviewPlaceholder" class="preview-placeholder">
                            <i class="fas fa-user"></i>
                        </div>
                        <div id="editPreviewContainer" style="display: none;">
                            <img id="editPreviewImage" src="#" alt="รูปโปรไฟล์" class="preview-profile">
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="edit_username">ชื่อผู้ใช้</label>
                    <input type="text" id="edit_username" value="<?php echo htmlspecialchars($edit_user['username'], ENT_QUOTES, 'UTF-8'); ?>" readonly disabled>
                    <small>ไม่สามารถเปลี่ยนชื่อผู้ใช้ได้</small>
                </div>
                
                <div class="form-group">
                    <label for="edit_password">รหัสผ่าน</label>
                    <input type="password" id="edit_password" name="password">
                    <small>เว้นว่างไว้หากไม่ต้องการเปลี่ยนรหัสผ่าน</small>
                </div>
                
                <div class="form-group">
                    <label for="edit_email">อีเมล <span class="required">*</span></label>
                    <input type="email" id="edit_email" name="email" value="<?php echo htmlspecialchars($edit_user['email'], ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_fullname">ชื่อ-นามสกุล <span class="required">*</span></label>
                    <input type="text" id="edit_fullname" name="fullname" value="<?php echo htmlspecialchars($edit_user['fullname'], ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_group_id">กลุ่มผู้ใช้ <span class="required">*</span></label>
                    <select id="edit_group_id" name="group_id" required>
                        <option value="">-- เลือกกลุ่มผู้ใช้ --</option>
                        <?php foreach ($groups as $group): ?>
                        <option value="<?php echo $group['id']; ?>" <?php echo $edit_user['group_id'] == $group['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($group['group_name'], ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="edit_profile_image">รูปโปรไฟล์</label>
                    <input type="file" id="edit_profile_image" name="profile_image" accept=".jpg,.jpeg,.png,.gif">
                    <small>อัปโหลดรูปภาพขนาดไม่เกิน 2MB (รองรับไฟล์ .jpg, .jpeg, .png, .gif)</small>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_active" <?php echo $edit_user['is_active'] ? 'checked' : ''; ?>> เปิดใช้งาน
                    </label>
                </div>
                
                <button type="submit" name="edit_user">
                    <i class="fas fa-save"></i> บันทึกข้อมูล
                </button>
            </form>
        </div>
    </div>
    <?php endif; ?>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            // เปิด Modal เพิ่มผู้ใช้
            $('#addUserBtn').on('click', function() {
                $('#addUserModal').css('display', 'block');
            });
            
            // ปิด Modal เพิ่มผู้ใช้
            $('#closeAddModal').on('click', function() {
                $('#addUserModal').css('display', 'none');
            });
            
            // ปิด Modal แก้ไขผู้ใช้
            $('#closeEditModal').on('click', function() {
                window.location.href = 'users.php';
            });
            
            // ปิด Modal เมื่อคลิกนอกกรอบ
            $(window).on('click', function(event) {
                if ($(event.target).hasClass('modal')) {
                    $('.modal').css('display', 'none');
                    
                    // ถ้าเป็น Modal แก  {
                    $('.modal').css('display', 'none');
                    
                    // ถ้าเป็น Modal แก้ไขผู้ใช้ ให้กลับไปหน้าจัดการผู้ใช้
                    if ($(event.target).is('#editUserModal')) {
                        window.location.href = 'users.php';
                    }
                }
            });
            
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
            
            // แสดงตัวอย่างรูปโปรไฟล์ก่อนอัปโหลด (สำหรับแก้ไข)
            $('#edit_profile_image').on('change', function() {
                const file = this.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        if ($('#editPreviewImage').length) {
                            $('#editPreviewImage').attr('src', e.target.result);
                        } else {
                            $('#editPreviewPlaceholder').hide();
                            $('#editPreviewContainer').show();
                            $('#editPreviewImage').attr('src', e.target.result);
                        }
                    }
                    reader.readAsDataURL(file);
                }
            });
        });
    </script>
</body>
</html>
