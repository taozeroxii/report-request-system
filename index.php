<?php
session_start();
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบขอรายงาน</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>
    <div class="container">
        <header>
            <h1><i class="fas fa-file-alt"></i> ระบบขอรายงาน</h1>
            <nav>
                <ul>
                    <li><a href="index.php" class="active"><i class="fas fa-home"></i> หน้าหลัก</a></li>
                    <li><a href="requests.php"><i class="fas fa-list"></i> รายการคำขอ</a></li>
                    <li><a href="admin/login.php"><i class="fas fa-user-shield"></i> เข้าสู่ระบบผู้ดูแล</a></li>
                </ul>
            </nav>
        </header>

        <main>
            <section class="form-container">
                <h2><i class="fas fa-edit"></i> แบบฟอร์มขอรายงาน</h2>
                <form id="reportRequestForm" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="fullname">ชื่อ-นามสกุลผู้ขอ <span class="required">*</span></label>
                        <input type="text" id="fullname" name="fullname" required>
                    </div>

                    <div class="form-group">
                        <label for="report_name">ชื่อรายงาน <span class="required">*</span></label>
                        <input type="text" id="report_name" name="report_name" required>
                    </div>

                    <div class="form-group">
                        <label for="details">รายละเอียดข้อมูลที่ต้องการ <span class="required">*</span></label>
                        <textarea id="details" name="details" rows="5" placeholder="โปรดระบุ column ที่ต้องการ และข้อมูลอื่นๆ ที่เกี่ยวข้อง" required></textarea>
                    </div>

                    <div class="form-group">
                        <label for="attachments">แนบไฟล์ประกอบ (สามารถเลือกได้หลายไฟล์)</label>
                        <input type="file" id="attachments" name="attachments[]" multiple
                            accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.xls,.xlsx,.csv">
                        <div id="filePreview" class="file-preview"></div>
                    </div>

                    <div class="form-group">
                        <button type="submit" id="submitBtn"><i class="fas fa-paper-plane"></i> ส่งคำขอ</button>
                    </div>

                    <div id="formMessage" class="message"></div>
                </form>
            </section>
        </main>

        <footer>
            <p>&copy; <?php echo date('Y'); ?> ระบบขอรายงาน | พัฒนาโดย ทีมพัฒนาระบบ</p>
        </footer>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="js/main.js"></script>
</body>

</html>