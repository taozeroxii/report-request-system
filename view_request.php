<?php
session_start();

// ฟังก์ชันทำความสะอาดข้อมูล
function sanitize_input($data)
{
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// ตรวจสอบและทำความสะอาดข้อมูล request_id
if (!isset($_GET['id']) || empty($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: requests.php');
    exit;
}

$request_id = (int)$_GET['id'];
require_once 'config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();

    // ใช้ Prepared Statement เพื่อป้องกัน SQL Injection
    $stmt = $conn->prepare("SELECT * FROM report_requests WHERE id = :id LIMIT 1");
    $stmt->bindParam(':id', $request_id, PDO::PARAM_INT);
    $stmt->execute();

    if ($stmt->rowCount() === 0) {
        header('Location: requests.php');
        exit;
    }

    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    // ใช้ Prepared Statement เพื่อป้องกัน SQL Injection
    $stmt = $conn->prepare("SELECT * FROM request_attachments WHERE request_id = :request_id");
    $stmt->bindParam(':request_id', $request_id, PDO::PARAM_INT);
    $stmt->execute();
    $attachments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = 'เกิดข้อผิดพลาดในการดึงข้อมูล: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายละเอียดคำขอ - ระบบขอรายงาน</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .request-details {
            margin-bottom: 30px;
        }

        .request-details dl {
            display: grid;
            grid-template-columns: 150px 1fr;
            gap: 10px;
        }

        .request-details dt {
            font-weight: bold;
        }

        .attachments-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .attachment-item {
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 10px;
            text-align: center;
        }

        .attachment-item img {
            max-width: 100%;
            max-height: 150px;
            object-fit: contain;
            margin-bottom: 10px;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .attachment-item img:hover {
            transform: scale(1.05);
        }

        .attachment-item .file-name {
            font-size: 14px;
            word-break: break-all;
        }

        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: var(--secondary-color);
            text-decoration: none;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        /* Modal styles */
        .image-modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.9);
            overflow: auto;
        }

        .modal-content {
            display: block;
            position: relative;
            margin: auto;
            max-width: 90%;
            max-height: 90vh;
            top: 50%;
            transform: translateY(-50%);
        }

        .modal-content img {
            display: block;
            margin: 0 auto;
            max-width: 100%;
            max-height: 90vh;
            object-fit: contain;
        }

        .close-modal {
            position: absolute;
            top: 15px;
            right: 15px;
            color: white;
            font-size: 30px;
            font-weight: bold;
            cursor: pointer;
            z-index: 1001;
            background-color: rgba(0, 0, 0, 0.5);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background-color 0.2s;
        }

        .close-modal:hover {
            background-color: rgba(255, 0, 0, 0.7);
        }

        .modal-caption {
            color: white;
            text-align: center;
            padding: 10px;
            margin-top: 10px;
            background-color: rgba(0, 0, 0, 0.5);
            border-radius: 4px;
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
                    <li><a href="admin/login.php"><i class="fas fa-user-shield"></i> เข้าสู่ระบบผู้ดูแล</a></li>
                </ul>
            </nav>
        </header>

        <main>
            <section class="form-container">
                <h2><i class="fas fa-info-circle"></i> รายละเอียดคำขอรายงาน #<?php echo $request_id; ?></h2>

                <?php if (isset($error_message)): ?>
                    <div class="message error" style="display: block;">
                        <?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>

                <div class="request-details">
                    <dl>
                        <dt>ชื่อผู้ขอ:</dt>
                        <dd><?php echo htmlspecialchars($request['fullname'], ENT_QUOTES, 'UTF-8'); ?></dd>

                        <dt>ชื่อรายงาน:</dt>
                        <dd><?php echo htmlspecialchars($request['report_name'], ENT_QUOTES, 'UTF-8'); ?></dd>

                        <dt>วันที่ขอ:</dt>
                        <dd><?php echo date('d/m/Y H:i', strtotime($request['created_at'])); ?></dd>

                        <dt>สถานะ:</dt>
                        <dd>
                            <span class="status status-<?php echo htmlspecialchars($request['status'], ENT_QUOTES, 'UTF-8'); ?>">
                                <?php
                                switch ($request['status']) {
                                    case 'pending':
                                        echo 'รอดำเนินการ';
                                        break;
                                    case 'in-progress':
                                        echo 'กำลังดำเนินการ';
                                        break;
                                    case 'completed':
                                        echo 'เสร็จสิ้น';
                                        break;
                                    default:
                                        echo htmlspecialchars($request['status'], ENT_QUOTES, 'UTF-8');
                                }
                                ?>
                            </span>
                        </dd>
                    </dl>

                    <h3>รายละเอียดข้อมูลที่ต้องการ:</h3>
                    <div class="details-content">
                        <?php echo nl2br(htmlspecialchars($request['details'], ENT_QUOTES, 'UTF-8')); ?>
                    </div>
                </div>

                <?php if (!empty($attachments)): ?>
                    <div class="attachments">
                        <h3>ไฟล์แนบ (<?php echo count($attachments); ?> ไฟล์):</h3>
                        <div class="attachments-grid">
                            <?php foreach ($attachments as $index => $attachment): ?>
                                <div class="attachment-item">
                                    <?php
                                    $file_path = $attachment['file_path'];
                                    $file_name = htmlspecialchars($attachment['file_name'], ENT_QUOTES, 'UTF-8');
                                    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                                    $image_exts = ['jpg', 'jpeg', 'png', 'gif'];

                                    if (in_array($file_ext, $image_exts)):
                                        $safe_path = htmlspecialchars(str_replace('../', '', $file_path), ENT_QUOTES, 'UTF-8');
                                    ?>
                                        <img src="<?php echo $safe_path; ?>" alt="<?php echo $file_name; ?>" class="preview-image" data-index="<?php echo $index; ?>">
                                    <?php else: ?>
                                        <i class="fas fa-file fa-4x"></i>
                                    <?php endif; ?>

                                    <div class="file-name"><?php echo $file_name; ?></div>
                                    <a href="<?php echo htmlspecialchars(str_replace('../', '', $file_path), ENT_QUOTES, 'UTF-8'); ?>" target="_blank" class="download-link">
                                        <i class="fas fa-download"></i> ดาวน์โหลด
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- เพิ่มส่วนแสดงความคิดเห็น -->
                <div class="comments-section">
                    <h3><i class="fas fa-comments"></i> ความคิดเห็น</h3>
                    <div id="comments-list" class="comments-list">
                        <!-- ความคิดเห็นจะถูกโหลดด้วย JavaScript -->
                        <div class="no-comments">กำลังโหลดความคิดเห็น...</div>
                    </div>

                    <div class="comment-form">
                        <h4><i class="fas fa-comment-dots"></i> แสดงความคิดเห็น</h4>
                        <form id="commentForm">
                            <input type="hidden" name="request_id" value="<?php echo $request_id; ?>">

                            <div class="form-group">
                                <label for="user_name">ชื่อของคุณ <span class="required">*</span></label>
                                <input type="text" id="user_name" name="user_name" required>
                            </div>

                            <div class="form-group">
                                <label for="comment">ข้อความ <span class="required">*</span></label>
                                <textarea id="comment" name="comment" rows="4" required></textarea>
                            </div>

                            <button type="submit" id="submitCommentBtn"><i class="fas fa-paper-plane"></i> ส่งความคิดเห็น</button>
                        </form>
                        <div id="commentMessage" class="message"></div>
                    </div>
                </div>

                <a href="requests.php" class="back-link">
                    <i class="fas fa-arrow-left"></i> กลับไปยังรายการคำขอ
                </a>
            </section>
        </main>

        <footer>
            <p>&copy; <?php echo date('Y'); ?> ระบบขอรายงาน | พัฒนาโดย ทีมพัฒนาระบบ</p>
        </footer>
    </div>

    <!-- Modal สำหรับแสดงรูปภาพขนาดใหญ่ -->
    <div id="imageModal" class="image-modal">
        <span class="close-modal">&times;</span>
        <div class="modal-content">
            <img id="modalImage" src="/placeholder.svg" alt="">
            <div id="modalCaption" class="modal-caption"></div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            // ฟังก์ชันสำหรับทำความสะอาดข้อมูลเพื่อป้องกัน XSS
            function escapeHtml(text) {
                return text
                    .replace(/&/g, "&amp;")
                    .replace(/</g, "&lt;")
                    .replace(/>/g, "&gt;")
                    .replace(/"/g, "&quot;")
                    .replace(/'/g, "&#039;");
            }

            // ฟังก์ชันตรวจสอบความถูกต้องของข้อมูล
            function validateCommentForm() {
                let isValid = true;
                const userName = $("#user_name").val().trim();
                const comment = $("#comment").val().trim();

                if (userName === "") {
                    isValid = false;
                    $("#user_name").addClass("error-input");
                } else {
                    $("#user_name").removeClass("error-input");
                }

                if (comment === "") {
                    isValid = false;
                    $("#comment").addClass("error-input");
                } else {
                    $("#comment").removeClass("error-input");
                }

                return isValid;
            }

            // โหลดความคิดเห็นเมื่อโหลดหน้า
            loadComments();

            // ส่งฟอร์มความคิดเห็นด้วย AJAX
            $('#commentForm').on('submit', function(e) {
                e.preventDefault();

                // ตรวจสอบความถูกต้องของข้อมูล
                if (!validateCommentForm()) {
                    $("#commentMessage").addClass("error").html("กรุณากรอกข้อมูลให้ครบถ้วน").show();
                    return;
                }

                const formData = new FormData(this);
                const submitBtn = $('#submitCommentBtn');
                const commentMessage = $('#commentMessage');

                // ปิดปุ่มและแสดงสถานะกำลังโหลด
                submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> กำลังส่งข้อมูล...');
                commentMessage.removeClass('success error').html('');

                $.ajax({
                    url: 'api/add_comment.php',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        try {
                            const data = typeof response === 'string' ? JSON.parse(response) : response;

                            if (data.status === 'success') {
                                commentMessage.addClass('success').html(data.message);
                                $('#commentForm')[0].reset();

                                // โหลดความคิดเห็นใหม่
                                loadComments();
                            } else {
                                commentMessage.addClass('error').html(data.message);
                            }
                        } catch (e) {
                            commentMessage.addClass('error').html('เกิดข้อผิดพลาดในการประมวลผลข้อมูล');
                        }
                    },
                    error: function(xhr, status, error) {
                        commentMessage.addClass('error').html('เกิดข้อผิดพลาดในการส่งข้อมูล: ' + error);
                    },
                    complete: function() {
                        // เปิดปุ่มอีกครั้ง
                        submitBtn.prop('disabled', false).html('<i class="fas fa-paper-plane"></i> ส่งความคิดเห็น');
                    }
                });
            });

            // ฟังก์ชันโหลดความคิดเห็น
            function loadComments() {
                $.ajax({
                    url: 'api/get_comments.php',
                    type: 'GET',
                    data: {
                        request_id: <?php echo $request_id; ?>
                    },
                    success: function(response) {
                        try {
                            const data = typeof response === 'string' ? JSON.parse(response) : response;
                            const commentsList = $('#comments-list');

                            if (data.status === 'success') {
                                if (data.comments.length > 0) {
                                    let commentsHtml = '';

                                    data.comments.forEach(function(comment) {
                                        const date = new Date(comment.created_at);
                                        const formattedDate = date.toLocaleDateString('th-TH', {
                                            year: 'numeric',
                                            month: 'long',
                                            day: 'numeric',
                                            hour: '2-digit',
                                            minute: '2-digit'
                                        });

                                        // ใช้ escapeHtml เพื่อป้องกัน XSS
                                        const userName = escapeHtml(comment.user_name);
                                        const commentText = escapeHtml(comment.comment).replace(/\n/g, '<br>');

                                        commentsHtml += `
                                            <div class="comment-item ${comment.user_type}">
                                                <div class="comment-header">
                                                    <span class="comment-user ${comment.user_type}">
                                                        ${comment.user_type === 'admin' ? '<i class="fas fa-user-shield"></i> ' : ''}
                                                        ${userName}
                                                    </span>
                                                    <span class="comment-date">${formattedDate}</span>
                                                </div>
                                                <div class="comment-content">
                                                    ${commentText}
                                                </div>
                                            </div>
                                        `;
                                    });

                                    commentsList.html(commentsHtml);
                                } else {
                                    commentsList.html('<div class="no-comments">ยังไม่มีความคิดเห็น</div>');
                                }
                            } else {
                                commentsList.html('<div class="no-comments">เกิดข้อผิดพลาดในการโหลดความคิดเห็น</div>');
                            }
                        } catch (e) {
                            $('#comments-list').html('<div class="no-comments">เกิดข้อผิดพลาดในการประมวลผลข้อมูล</div>');
                        }
                    },
                    error: function(xhr, status, error) {
                        $('#comments-list').html('<div class="no-comments">เกิดข้อผิดพลาดในการโหลดความคิดเห็น: ' + error + '</div>');
                    }
                });
            }

            // เพิ่ม CSS สำหรับแสดงข้อผิดพลาดใน input
            $("<style>")
                .prop("type", "text/css")
                .html(`
                    .error-input {
                        border: 1px solid #ef4444 !important;
                        box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.2) !important;
                    }
                `)
                .appendTo("head");

            // ฟังก์ชันสำหรับแสดงรูปภาพขนาดใหญ่
            $('.preview-image').on('click', function() {
                const imgSrc = $(this).attr('src');
                const imgAlt = $(this).attr('alt');

                $('#modalImage').attr('src', imgSrc);
                $('#modalCaption').text(imgAlt);
                $('#imageModal').fadeIn(300);
            });

            // ปิด Modal เมื่อคลิกที่ปุ่มปิด
            $('.close-modal').on('click', function() {
                $('#imageModal').fadeOut(300);
            });

            // ปิด Modal เมื่อคลิกที่พื้นหลัง
            $(window).on('click', function(event) {
                if ($(event.target).is('#imageModal')) {
                    $('#imageModal').fadeOut(300);
                }
            });

            // ปิด Modal เมื่อกด ESC
            $(document).on('keydown', function(event) {
                if (event.key === "Escape") {
                    $('#imageModal').fadeOut(300);
                }
            });
        });
    </script>
</body>

</html>