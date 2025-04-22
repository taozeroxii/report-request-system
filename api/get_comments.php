<?php
require_once '../config/database.php';

header('Content-Type: application/json');

// เพิ่มการตรวจสอบและทำความสะอาดข้อมูล request_id
if (!isset($_GET['request_id']) || empty($_GET['request_id']) || !is_numeric($_GET['request_id'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'ไม่พบรหัสคำขอหรือรูปแบบไม่ถูกต้อง'
    ]);
    exit;
}

$request_id = (int)$_GET['request_id'];

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Get comments for the request
    $stmt = $conn->prepare("SELECT * FROM request_comments WHERE request_id = :request_id ORDER BY created_at ASC");
    $stmt->bindParam(':request_id', $request_id);
    $stmt->execute();

    // แก้ไขส่วนการแสดงผลข้อมูลในการส่งกลับ JSON
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ทำความสะอาดข้อมูลก่อนส่งกลับ
    foreach ($comments as &$comment) {
        $comment['user_name'] = htmlspecialchars($comment['user_name'], ENT_QUOTES, 'UTF-8');
        $comment['comment'] = htmlspecialchars($comment['comment'], ENT_QUOTES, 'UTF-8');
    }
    
    echo json_encode([
        'status' => 'success',
        'comments' => $comments
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'เกิดข้อผิดพลาดในการดึงข้อมูล: ' . $e->getMessage()
    ]);
}
?>
