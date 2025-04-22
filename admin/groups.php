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
    
    // จัดการการลบกลุ่มผู้ใช้
    if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
        $group_id = (int)$_GET['id'];
        
        // ตรวจสอบว่ามีผู้ใช้ในกลุ่มนี้หรือไม่
        $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE group_id = :group_id");
        $stmt->bindParam(':group_id', $group_id, PDO::PARAM_INT);
        $stmt->execute();
        $user_count = $stmt->fetchColumn();
        
        if ($user_count > 0) {
            $error_message = 'ไม่สามารถลบกลุ่มผู้ใช้นี้ได้ เนื่องจากมีผู้ใช้อยู่ในกลุ่มนี้';
        } else {
            // ลบกลุ่มผู้ใช้
            $stmt = $conn->prepare("DELETE FROM user_groups WHERE id = :id");
            $stmt->bindParam(':id', $group_id, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                $success_message = 'ลบกลุ่มผู้ใช้เรียบร้อยแล้ว';
            } else {
                $error_message = 'เกิดข้อผิดพลาดในการลบกลุ่มผู้ใช้';
            }
        }
    }
    
    // จัดการการเพิ่มหรือแก้ไขกลุ่มผู้ใช้
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['add_group'])) {
            // เพิ่มกลุ่มผู้ใช้ใหม่
            $group_name = sanitize_input($_POST['group_name']);
            $description = sanitize_input($_POST['description']);
            
            // สร้าง JSON สำหรับสิทธิ์การใช้งาน
            $permissions = [
                'submit_request' => isset($_POST['perm_submit_request']),
                'view_own_requests' => isset($_POST['perm_view_own_requests']),
                'view_all_requests' => isset($_POST['perm_view_all_requests']),
                'comment' => isset($_POST['perm_comment'])
            ];
            $permissions_json = json_encode($permissions);
            
            // ตรวจสอบข้อมูล
            if (empty($group_name)) {
                $error_message = 'กรุณากรอกชื่อกลุ่มผู้ใช้';
            } else {
                // ตรวจสอบว่าชื่อกลุ่มซ้ำหรือไม่
                $stmt = $conn->prepare("SELECT * FROM user_groups WHERE group_name = :group_name");
                $stmt->bindParam(':group_name', $group_name);
                $stmt->execute();
                
                if ($stmt->rowCount() > 0) {
                    $error_message = 'ชื่อกลุ่มผู้ใช้นี้มีอยู่ในระบบแล้ว';
                } else {
                    // เพิ่มกลุ่มผู้ใช้ใหม่
                    $stmt = $conn->prepare("INSERT INTO user_groups (group_name, description, permissions, created_at) VALUES (:group_name, :description, :permissions, NOW())");
                    $stmt->bindParam(':group_name', $group_name);
                    $stmt->bindParam(':description', $description);
                    $stmt->bindParam(':permissions', $permissions_json);
                    
                    if ($stmt->execute()) {
                        $success_message = 'เพิ่มกลุ่มผู้ใช้เรียบร้อยแล้ว';
                    } else {
                        $error_message = 'เกิดข้อผิดพลาดในการเพิ่มกลุ่มผู้ใช้';
                    }
                }
            }
        } elseif (isset($_POST['edit_group'])) {
            // แก้ไขกลุ่มผู้ใช้
            $group_id = (int)$_POST['group_id'];
            $group_name = sanitize_input($_POST['group_name']);
            $description = sanitize_input($_POST['description']);
            
            // สร้าง JSON สำหรับสิทธิ์การใช้งาน
            $permissions = [
                'submit_request' => isset($_POST['perm_submit_request']),
                'view_own_requests' => isset($_POST['perm_view_own_requests']),
                'view_all_requests' => isset($_POST['perm_view_all_requests']),
                'comment' => isset($_POST['perm_comment'])
            ];
            $permissions_json = json_encode($permissions);
            
            // ตรวจสอบข้อมูล
            if (empty($group_name)) {
                $error_message = 'กรุณากรอกชื่อกลุ่มผู้ใช้';
            } else {
                // ตรวจสอบว่าชื่อกลุ่มซ้ำกับกลุ่มอื่นหรือไม่
                $stmt = $conn->prepare("SELECT * FROM user_groups WHERE group_name = :group_name AND id != :id");
                $stmt->bindParam(':group_name', $group_name);
                $stmt->bindParam(':id', $group_id, PDO::PARAM_INT);
                $stmt->execute();
                
                if ($stmt->rowCount() > 0) {
                    $error_message = 'ชื่อกลุ่มผู้ใช้นี้มีอยู่ในระบบแล้ว';
                } else {
                    // อัปเดตข้อมูลกลุ่มผู้ใช้
                    $stmt = $conn->prepare("UPDATE user_groups SET group_name = :group_name, description = :description, permissions = :permissions, updated_at = NOW() WHERE id = :id");
                    $stmt->bindParam(':group_name', $group_name);
                    $stmt->bindParam(':description', $description);
                    $stmt->bindParam(':permissions', $permissions_json);
                    $stmt->bindParam(':id', $group_id, PDO::PARAM_INT);
                    
                    if ($stmt->execute()) {
                        $success_message = 'อัปเดตข้อมูลกลุ่มผู้ใช้เรียบร้อยแล้ว';
                    } else {
                        $error_message = 'เกิดข้อผิดพลาดในการอัปเดตข้อมูลกลุ่มผู้ใช้';
                    }
                }
            }
        }
    }
    
    // ดึงข้อมูลกลุ่มผู้ใช้ทั้งหมด
    $stmt = $conn->query("SELECT g.*, (SELECT COUNT(*) FROM users WHERE group_id = g.id) AS user_count FROM user_groups g ORDER BY g.id");
    $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ดึงข้อมูลกลุ่มผู้ใช้ที่ต้องการแก้ไข
    $edit_group = null;
    if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
        $group_id = (int)$_GET['id'];
        
        $stmt = $conn->prepare("SELECT * FROM user_groups WHERE id = :id");
        $stmt->bindParam(':id', $group_id, PDO::PARAM_INT);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $edit_group = $stmt->fetch(PDO::FETCH_ASSOC);
            $edit_group['permissions'] = json_decode($edit_group['permissions'], true);
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
    <title>จัดการกลุ่มผู้ใช้ - ระบบขอรายงาน</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .group-actions {
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
        
        .permissions-container {
            background-color: var(--gray-100);
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
        }
        
        .permissions-title {
            font-weight: 500;
            margin-bottom: 0.75rem;
            color: var(--gray-700);
        }
        
        .permission-item {
            margin-bottom: 0.5rem;
        }
        
        .permission-item label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: normal;
        }
        
        .permission-item input[type="checkbox"] {
            width: auto;
        }
        
        .user-count {
            background-color: var(--primary-light);
            color: var(--primary-dark);
            padding: 0.25rem 0.5rem;
            border-radius: 1rem;
            font-size: 0.75rem;
            font-weight: 500;
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
                    <li><a href="users.php"><i class="fas fa-users"></i> จัดการผู้ใช้</a></li>
                    <li><a href="groups.php" class="active"><i class="fas fa-user-tag"></i> จัดการกลุ่มผู้ใช้</a></li>
                    <li><a href="profile.php"><i class="fas fa-user-circle"></i> โปรไฟล์</a></li>
                    <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> ออกจากระบบ</a></li>
                </ul>
            </nav>
        </header>

        <main>
            <section class="form-container">
                <h2><i class="fas fa-user-tag"></i> จัดการกลุ่มผู้ใช้</h2>
                
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
                
                <button id="addGroupBtn" class="btn-primary" style="margin-bottom: 1.5rem;">
                    <i class="fas fa-plus"></i> เพิ่มกลุ่มผู้ใช้ใหม่
                </button>
                
                <?php if (empty($groups)): ?>
                <p>ไม่พบข้อมูลกลุ่มผู้ใช้</p>
                <?php else: ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>ชื่อกลุ่ม</th>
                                <th>คำอธิบาย</th>
                                <th>จำนวนผู้ใช้</th>
                                <th>วันที่สร้าง</th>
                                <th>จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($groups as $group): ?>
                            <tr>
                                <td><?php echo $group['id']; ?></td>
                                <td><?php echo htmlspecialchars($group['group_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($group['description'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><span class="user-count"><?php echo $group['user_count']; ?></span></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($group['created_at'])); ?></td>
                                <td>
                                    <div class="group-actions">
                                        <a href="?action=edit&id=<?php echo $group['id']; ?>" class="btn-edit">
                                            <i class="fas fa-edit"></i> แก้ไข
                                        </a>
                                        <a href="?action=delete&id=<?php echo $group['id']; ?>" class="btn-delete" onclick="return confirm('คุณต้องการลบกลุ่มผู้ใช้นี้ใช่หรือไม่?');">
                                            <i class="fas fa-trash-alt"></i> ลบ
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </section>
        </main>

        <footer>
            <p>&copy; <?php echo date('Y'); ?> ระบบขอรายงาน | พัฒนาโดย ทีมพัฒนาระบบ</p>
        </footer>
    </div>
    
    <!-- Modal เพิ่มกลุ่มผู้ใช้ -->
    <div id="addGroupModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" id="closeAddModal">&times;</span>
            <h3 class="modal-title"><i class="fas fa-plus"></i> เพิ่มกลุ่มผู้ใช้ใหม่</h3>
            
            <form method="POST">
                <div class="form-group">
                    <label for="group_name">ชื่อกลุ่มผู้ใช้ <span class="required">*</span></label>
                    <input type="text" id="group_name" name="group_name" required>
                </div>
                
                <div class="form-group">
                    <label for="description">คำอธิบาย</label>
                    <textarea id="description" name="description" rows="3"></textarea>
                </div>
                
                <div class="permissions-container">
                    <div class="permissions-title">สิทธิ์การใช้งาน</div>
                    
                    <div class="permission-item">
                        <label>
                            <input type="checkbox" name="perm_submit_request" checked> สามารถส่งคำขอรายงานได้
                        </label>
                    </div>
                    
                    <div class="permission-item">
                        <label>
                            <input type="checkbox" name="perm_view_own_requests" checked> สามารถดูคำขอของตนเองได้
                        </label>
                    </div>
                    
                    <div class="permission-item">
                        <label>
                            <input type="checkbox" name="perm_view_all_requests"> สามารถดูคำขอทั้งหมดได้
                        </label>
                    </div>
                    
                    <div class="permission-item">
                        <label>
                            <input type="checkbox" name="perm_comment" checked> สามารถแสดงความคิดเห็นได้
                        </label>
                    </div>
                </div>
                
                <button type="submit" name="add_group">
                    <i class="fas fa-save"></i> บันทึกข้อมูล
                </button>
            </form>
        </div>
    </div>
    
    <!-- Modal แก้ไขกลุ่มผู้ใช้ -->
    <?php if ($edit_group): ?>
    <div id="editGroupModal" class="modal" style="display: block;">
        <div class="modal-content">
            <span class="close-modal" id="closeEditModal">&times;</span>
            <h3 class="modal-title"><i class="fas fa-edit"></i> แก้ไขข้อมูลกลุ่มผู้ใช้</h3>
            
            <form method="POST">
                <input type="hidden" name="group_id" value="<?php echo $edit_group['id']; ?>">
                
                <div class="form-group">
                    <label for="edit_group_name">ชื่อกลุ่มผู้ใช้ <span class="required">*</span></label>
                    <input type="text" id="edit_group_name" name="group_name" value="<?php echo htmlspecialchars($edit_group['group_name'], ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_description">คำอธิบาย</label>
                    <textarea id="edit_description" name="description" rows="3"><?php echo htmlspecialchars($edit_group['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
                
                <div class="permissions-container">
                    <div class="permissions-title">สิทธิ์การใช้งาน</div>
                    
                    <div class="permission-item">
                        <label>
                            <input type="checkbox" name="perm_submit_request" <?php echo isset($edit_group['permissions']['submit_request']) && $edit_group['permissions']['submit_request'] ? 'checked' : ''; ?>> สามารถส่งคำขอรายงานได้
                        </label>
                    </div>
                    
                    <div class="permission-item">
                        <label>
                            <input type="checkbox" name="perm_view_own_requests" <?php echo isset($edit_group['permissions']['view_own_requests']) && $edit_group['permissions']['view_own_requests'] ? 'checked' : ''; ?>> สามารถดูคำขอของตนเองได้
                        </label>
                    </div>
                    
                    <div class="permission-item">
                        <label>
                            <input type="checkbox" name="perm_view_all_requests" <?php echo isset($edit_group['permissions']['view_all_requests']) && $edit_group['permissions']['view_all_requests'] ? 'checked' : ''; ?>> สามารถดูคำขอทั้งหมดได้
                        </label>
                    </div>
                    
                    <div class="permission-item">
                        <label>
                            <input type="checkbox" name="perm_comment" <?php echo isset($edit_group['permissions']['comment']) && $edit_group['permissions']['comment'] ? 'checked' : ''; ?>> สามารถแสดงความคิดเห็นได้
                        </label>
                    </div>
                </div>
                
                <button type="submit" name="edit_group">
                    <i class="fas fa-save"></i> บันทึกข้อมูล
                </button>
            </form>
        </div>
    </div>
    <?php endif; ?>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            // เปิด Modal เพิ่มกลุ่มผู้ใช้
            $('#addGroupBtn').on('click', function() {
                $('#addGroupModal').css('display', 'block');
            });
            
            // ปิด Modal เพิ่มกลุ่มผู้ใช้
            $('#closeAddModal').on('click', function() {
                $('#addGroupModal').css('display', 'none');
            });
            
            // ปิด Modal แก้ไขกลุ่มผู้ใช้
            $('#closeEditModal').on('click', function() {
                window.location.href = 'groups.php';
            });
            
            // ปิด Modal เมื่อคลิกนอกกรอบ
            $(window).on('click', function(event) {
                if ($(event.target).hasClass('modal')) {
                    $('.modal').css('display', 'none');
                    
                    // ถ้าเป็น Modal แก้ไขกลุ่มผู้ใช้ ให้กลับไปหน้าจัดการกลุ่มผู้ใช้
                    if ($(event.target).is('#editGroupModal')) {
                        window.location.href = 'groups.php';
                    }
                }
            });
        });
    </script>
</body>
</html>
