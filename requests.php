<?php
session_start();
require_once 'config/database.php';

// Get requests with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Get total count
    $stmt = $conn->query("SELECT COUNT(*) FROM report_requests");
    $total_requests = $stmt->fetchColumn();
    $total_pages = ceil($total_requests / $limit);

    // Get requests for current page
    $stmt = $conn->prepare("SELECT * FROM report_requests ORDER BY created_at DESC LIMIT :limit OFFSET :offset");
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = 'เกิดข้อผิดพลาดในการดึงข้อมูล: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายการคำขอรายงาน - ระบบขอรายงาน</title>
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
                    <li><a href="requests.php" class="active"><i class="fas fa-list"></i> รายการคำขอ</a></li>
                    <li><a href="admin/login.php"><i class="fas fa-user-shield"></i> เข้าสู่ระบบผู้ดูแล</a></li>
                </ul>
            </nav>
        </header>

        <main>
            <section class="form-container">
                <h2><i class="fas fa-list-alt"></i> รายการคำขอรายงานทั้งหมด</h2>

                <?php if (isset($error_message)): ?>
                    <div class="message error" style="display: block;">
                        <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>

                <?php if (empty($requests)): ?>
                    <div class="no-comments">
                        <i class="fas fa-info-circle fa-2x"></i>
                        <p>ไม่พบรายการคำขอ</p>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>ชื่อผู้ขอ</th>
                                    <th>ชื่อรายงาน</th>
                                    <th>วันที่ขอ</th>
                                    <th>สถานะ</th>
                                    <th>รายละเอียด</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($requests as $request): ?>
                                    <tr>
                                        <td><?php echo $request['id']; ?></td>
                                        <td><?php echo htmlspecialchars($request['fullname']); ?></td>
                                        <td><?php echo htmlspecialchars($request['report_name']); ?></td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($request['created_at'])); ?></td>
                                        <td>
                                            <span class="status status-<?php echo $request['status']; ?>">
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
                                                        echo $request['status'];
                                                }
                                                ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="view_request.php?id=<?php echo $request['id']; ?>" class="btn-view">
                                                <i class="fas fa-eye"></i> ดูรายละเอียด
                                            </a>
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
                                <a href="?page=<?php echo $page - 1; ?>" class="page-link"><i class="fas fa-chevron-left"></i> ก่อนหน้า</a>
                            <?php endif; ?>

                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <a href="?page=<?php echo $i; ?>" class="page-link <?php echo $i === $page ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?php echo $page + 1; ?>" class="page-link">ถัดไป <i class="fas fa-chevron-right"></i></a>
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
</body>

</html>