<?php
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
function sanitize_input($data)
{
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// แก้ไขส่วนตรวจสอบข้อมูลและทำความสะอาดข้อมูลก่อนบันทึก
$required_fields = ['fullname', 'report_name', 'details'];
$sanitized_data = [];

foreach ($required_fields as $field) {
    if (empty($_POST[$field])) {
        echo json_encode([
            'status' => 'error',
            'message' => 'กรุณากรอกข้อมูลให้ครบถ้วน'
        ]);
        exit;
    }
    $sanitized_data[$field] = sanitize_input($_POST[$field]);
}
try {
    $db = new Database();
    $conn = $db->getConnection();

    // Begin transaction
    $conn->beginTransaction();

    // Insert request data
    $stmt = $conn->prepare("INSERT INTO report_requests (fullname, report_name, details, status, created_at) 
                           VALUES (:fullname, :report_name, :details, 'pending', NOW())");
    $stmt->bindParam(':fullname', $sanitized_data['fullname']);
    $stmt->bindParam(':report_name', $sanitized_data['report_name']);
    $stmt->bindParam(':details', $sanitized_data['details']);
    $stmt->execute();

    $request_id = $conn->lastInsertId();

    // Handle file uploads if any
    $error_files = [];

    if (!empty($_FILES['attachments']['name'][0])) {
        $upload_dir = '../uploads/';
        $allowed_extensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif'];

        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        foreach ($_FILES['attachments']['name'] as $key => $name) {
            if ($_FILES['attachments']['error'][$key] === UPLOAD_ERR_OK) {
                $tmp_name = $_FILES['attachments']['tmp_name'][$key];
                $original_name = basename($name);
                $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

                if (in_array($extension, $allowed_extensions)) {
                    $file_name = time() . '_' . $original_name;
                    $file_path = $upload_dir . $file_name;

                    if (move_uploaded_file($tmp_name, $file_path)) {
                        $stmt = $conn->prepare("INSERT INTO request_attachments (request_id, file_name, file_path, uploaded_at) 
                                               VALUES (:request_id, :file_name, :file_path, NOW())");

                        $stmt->bindParam(':request_id', $request_id);
                        $stmt->bindParam(':file_name', $original_name);
                        $stmt->bindParam(':file_path', $file_path);
                        $stmt->execute();
                    }
                } else {
                    $error_files[] = $original_name;
                }
            }
        }
    }

    // ถ้ามีไฟล์ผิดประเภท ยกเลิกทั้งหมด
    if (!empty($error_files)) {
        $conn->rollBack();
        echo json_encode([
            'status' => 'error',
            'message' => 'ไม่สามารถอัปโหลดไฟล์ต่อไปนี้ได้: ' . implode(', ', $error_files)
        ]);
        exit;
    }

    // ถ้าไม่มีข้อผิดพลาด
    $conn->commit();
    echo json_encode([
        'status' => 'success',
        'message' => 'ส่งคำขอรายงานเรียบร้อยแล้ว',
        'request_id' => $request_id
    ]);
    exit;
} catch (PDOException $e) {
    if (isset($conn)) {
        $conn->rollBack();
    }

    echo json_encode([
        'status' => 'error',
        'message' => 'เกิดข้อผิดพลาดในการบันทึกข้อมูล: ' . $e->getMessage()
    ]);
}
