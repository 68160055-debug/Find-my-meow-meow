<?php
require_once 'Auth.php';  // เดิมเป็น auth.php
require_once 'config/db.php';
require_login();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$active = 'Adopter.php';
$message = '';
$error = '';
if (isset($_GET['posted'])) {
    $message = 'โพสต์ประกาศขายแมวเรียบร้อยแล้ว!';
}

// ---------- โพสต์ขายแมว ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_cat'])) {
    check_auth_token();

    $name  = trim((string)($_POST['name'] ?? ''));
    $price = trim((string)($_POST['price'] ?? ''));
    $breed = trim((string)($_POST['breed'] ?? ''));
    $desc  = trim((string)($_POST['description'] ?? ''));
    $photo = 'cat.png';

    if ($name === '' || $breed === '') {
        $error = 'กรุณากรอกชื่อแมวและสายพันธุ์';
    } elseif (!is_numeric($price) || (float)$price < 0) {
        $error = 'กรุณากรอกราคาเป็นตัวเลข';
    }

    // อัปโหลดรูป: อนุญาตเฉพาะรูปภาพ (กันการอัปโหลดไฟล์ .php)
    if ($error === '' && isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        $is_image = @getimagesize($_FILES['photo']['tmp_name']) !== false;

        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true) || !$is_image) {
            $error = 'อนุญาตเฉพาะไฟล์รูป .jpg .jpeg .png .webp';
        } elseif ($_FILES['photo']['size'] > 5 * 1024 * 1024) {
            $error = 'ไฟล์รูปต้องมีขนาดไม่เกิน 5 MB';
        } else {
            $upload_dir = __DIR__ . '/assets/images/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            $photo = time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            if (!move_uploaded_file($_FILES['photo']['tmp_name'], $upload_dir . $photo)) {
                $error = 'ไม่สามารถอัปโหลดรูปได้';
                $photo = 'cat.png';
            }
        }
    }

    if ($error === '') {
        try {
            $price_num = (float)$price;
            $stmt = $conn->prepare("INSERT INTO Cat (Name, Price, Breed, BehaviorDescription, Photos, Status, CreatedDate) VALUES (?, ?, ?, ?, ?, 'Available', NOW())");
            $stmt->bind_param("sdsss", $name, $price_num, $breed, $desc, $photo);
            $stmt->execute();
            $stmt->close();
            header("Location: buy_cat.php?posted=1");
            exit;
        } catch (mysqli_sql_exception $e) {
            error_log($e->getMessage());
            $error = 'บันทึกข้อมูลไม่สำเร็จ กรุณาลองใหม่';
        }
    }
}

// ---------- ค้นหา / ดึงรายการแมว ----------
$q = trim($_GET['q'] ?? '');
if ($q !== '') {
    $qp = "%$q%";
    $stmt = $conn->prepare("SELECT * FROM Cat WHERE Name LIKE ? OR Breed LIKE ? OR BehaviorDescription LIKE ? ORDER BY ID DESC");
    $stmt->bind_param("sss", $qp, $qp, $qp);
} else {
    $stmt = $conn->prepare("SELECT * FROM Cat ORDER BY ID DESC");
}
$stmt->execute();
$cats = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$page_title = 'ซื้อแมว';
$active = 'buy_cat.php';
include 'layout_top.php';
?>

<div class="card">
    <h2>🛒 ซื้อแมว</h2>
    <p class="muted">ค้นหาน้องแมวที่ถูกใจ หรือโพสต์ประกาศขายแมวของคุณ</p>

    <?php if ($message): ?><div class="alert-ok"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert-err"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form method="get" class="search-row">
        <input type="text" name="q" class="search-input" placeholder="ค้นหาชื่อ สายพันธุ์ หรือนิสัย..." value="<?= htmlspecialchars($q) ?>">
        <button type="submit" class="btn">🔍 ค้นหา</button>
        <?php if ($q !== ''): ?><a href="buy_cat.php" class="btn btn-light">ล้าง</a><?php endif; ?>
    </form>
</div>

<div class="card">
    <h3>📝 โพสต์ขายแมว</h3>
    <form method="post" enctype="multipart/form-data">
        <?= csrf_input() ?>
        <div class="form-grid">
            <div class="form-group">
                <label>ชื่อแมว *</label>
                <input type="text" name="name" required maxlength="100" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>ราคา (บาท) *</label>
                <input type="number" name="price" required min="0" step="0.01" value="<?= htmlspecialchars($_POST['price'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>สายพันธุ์ *</label>
                <input type="text" name="breed" required maxlength="100" placeholder="เช่น เปอร์เซีย, สก็อตติช โฟลด์" value="<?= htmlspecialchars($_POST['breed'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>รูปแมว (.jpg .png .webp)</label>
                <input type="file" name="photo" accept=".jpg,.jpeg,.png,.webp">
            </div>
            <div class="form-group full">
                <label>รายละเอียดพฤติกรรม</label>
                <textarea name="description" placeholder="เช่น นิสัย ชอบเล่น ขี้อ้อน"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
            </div>
        </div>
        <button type="submit" name="post_cat" class="btn" style="margin-top:12px;">📤 โพสต์ประกาศ</button>
    </form>
</div>

<h3 style="color:#d81b60;">แมวทั้งหมด (<?= count($cats) ?>)</h3>
<?php if (!$cats): ?>
    <div class="card muted">ยังไม่พบแมวที่ตรงกับการค้นหา ลองคำค้นอื่น หรือโพสต์ประกาศขายแมวเป็นคนแรก</div>
<?php else: ?>
<div class="cat-grid">
    <?php foreach ($cats as $c): ?>
    <div class="cat-card">
        <img src="assets/images/<?= htmlspecialchars($c['Photos'] ?: 'cat.png') ?>" alt="<?= htmlspecialchars($c['Name']) ?>">
        <div class="cat-body">
            <h4><?= htmlspecialchars($c['Name']) ?></h4>
            <div class="price"><?= number_format((float)$c['Price'], 2) ?> ฿</div>
            <p style="margin:6px 0;"><span class="tag"><?= htmlspecialchars($c['Breed']) ?></span>
               <span class="tag"><?= $c['Status'] === 'Available' ? 'ยังหาบ้านอยู่' : htmlspecialchars($c['Status']) ?></span></p>
            <p class="muted" style="margin:6px 0 0;"><?= nl2br(htmlspecialchars((string)$c['BehaviorDescription'])) ?></p>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php include 'layout_bottom.php'; ?>