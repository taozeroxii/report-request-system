<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// Check if the request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid request method'
    ]);
    exit;
}

// เพิ่มฟังก์ชันทำความสะอาดข้อมูล
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// แก้ไขส่วนตรวจสอบข้อมูลและทำความสะอาดข้อมูลก่อนบันทึก
$required_fields = ['request_id', 'user_name', 'comment'];
$sanitized_data = [];

foreach ($required_fields as $field) {
    if (empty($_POST[$field])) {
        echo json_encode([
            'status' => 'error',
            'message' => 'กรุณากรอกข้อมูลให้ครบถ้วน'
        ]);
        exit;
    }
    
    if ($field === 'request_id') {
        // ตรวจสอบว่าเป็นตัวเลขเท่านั้น
        if (!is_numeric($_POST[$field])) {
            echo json_encode([
                'status' => 'error',
                'message' => 'รูปแบบข้อมูลไม่ถูกต้อง'
            ]);
            exit;
        }
        $sanitized_data[$field] = (int)$_POST[$field];
    } else {
        $sanitized_data[$field] = sanitize_input($_POST[$field]);
    }
}

// Determine user type (admin or regular user)
$user_type = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true ? 'admin' : 'user';

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Insert comment
    $stmt = $conn->prepare("INSERT INTO request_comments (request_id, user_type, user_name, comment, created_at) 
                           VALUES (:request_id, :user_type, :user_name, :comment, NOW())");
    
    // แก้ไขส่วนการ bind parameters
    $stmt->bindParam(':request_id', $sanitized_data['request_id'], PDO::PARAM_INT);
    $stmt->bindParam(':user_type', $user_type);
    $stmt->bindParam(':user_name', $sanitized_data['user_name']);
    $stmt->bindParam(':comment', $sanitized_data['comment']);
    $stmt->execute();
    
    $comment_id = $conn->lastInsertId();
    
    // Get the newly created comment
    $stmt = $conn->prepare("SELECT * FROM request_comments WHERE id = :id");
    $stmt->bindParam(':id', $comment_id);
    $stmt->execute();
    $comment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'status' => 'success',
        'message' => 'เพิ่มความคิดเห็นเรียบร้อยแล้ว',
        'comment' => [
            'id' => $comment['id'],
            'user_type' => $comment['user_type'],
            'user_name' => $comment['user_name'],
            'comment' => $comment['comment'],
            'created_at' => $comment['created_at']
        ]
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'เกิดข้อผิดพลาดในการบันทึกข้อมูล: ' . $e->getMessage()
    ]);
}
?>
