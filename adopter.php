<?php
require_once 'auth.php';
require_once 'config/db.php';
require_login();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$user_id = (int)$_SESSION['user_id'];

function fetch_all_rows($conn, $sql, $types = "", ...$params) {
    $s = $conn->prepare($sql);
    if (!empty($types) && !empty($params)) {
        $s->bind_param($types, ...$params);
    }
    $s->execute();
    $result = $s->get_result();
    $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $s->close();
    return $rows;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_auth_token();
    $action = $_POST['action'] ?? '';
    $target = (int)($_POST['target_id'] ?? 0);
    $result = 'error';

    try {
        if ($target > 0 && $target !== $user_id) {
            if ($action === 'add') {
                $exists = fetch_all_rows($conn,
                    "SELECT id FROM friendships WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?) LIMIT 1",
                    "iiii", $user_id, $target, $target, $user_id);
                if ($exists) {
                    $result = 'exists';
                } else {
                    $s = $conn->prepare("INSERT INTO friendships (user_id, friend_id, status) VALUES (?, ?, 'pending')");
                    $s->bind_param("ii", $user_id, $target);
                    $s->execute();
                    $s->close();

                    $msg = 'มีผู้ใช้งานส่งคำขอเป็นเพื่อนถึงคุณ';
                    $s = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
                    $s->bind_param("is", $target, $msg);
                    $s->execute();
                    $s->close();
                    $result = 'sent';
                }
            } elseif ($action === 'accept') {
                $s = $conn->prepare("UPDATE friendships SET status = 'accepted' WHERE user_id = ? AND friend_id = ? AND status = 'pending'");
                $s->bind_param("ii", $target, $user_id);
                $s->execute();
                $s->close();
                $result = 'accepted';
            } elseif ($action === 'decline') {
                $s = $conn->prepare("DELETE FROM friendships WHERE user_id = ? AND friend_id = ? AND status = 'pending'");
                $s->bind_param("ii", $target, $user_id);
                $s->execute();
                $s->close();
                $result = 'declined';
            }
        }
    } catch (mysqli_sql_exception $e) {
        error_log($e->getMessage());
        $result = 'error';
    }
    header("Location: adopter.php?r=" . $result);
    exit;
}

$messages = [
    'sent'     => ['ok',  'ส่งคำขอเป็นเพื่อนแล้ว'],
    'exists'   => ['err', 'คุณมีคำขอหรือเป็นเพื่อนกับผู้ใช้นี้อยู่แล้ว'],
    'accepted' => ['ok',  'ตอบรับเป็นเพื่อนแล้ว'],
    'declined' => ['ok',  'ปฏิเสธคำขอแล้ว'],
    'error'    => ['err', 'เกิดข้อผิดพลาด กรุณาลองใหม่'],
];
$flash = $messages[$_GET['r'] ?? ''] ?? null;

$search_user = trim($_GET['search_user'] ?? '');
$users_list = [];
if ($search_user !== '') {
    $sp = "%$search_user%";
    $users_list = fetch_all_rows($conn,
        "SELECT u.id, u.full_name, u.avatar,
                (SELECT f.status FROM friendships f
                 WHERE (f.user_id = ? AND f.friend_id = u.id) OR (f.user_id = u.id AND f.friend_id = ?) LIMIT 1) AS fstatus
         FROM users u WHERE u.id != ? AND u.full_name LIKE ? LIMIT 20",
        "iiis", $user_id, $user_id, $user_id, $sp);
}

$incoming = fetch_all_rows($conn,
    "SELECT u.id, u.full_name, u.avatar FROM friendships f
     JOIN users u ON u.id = f.user_id
     WHERE f.friend_id = ? AND f.status = 'pending' ORDER BY f.id DESC",
    "i", $user_id);

$friends = fetch_all_rows($conn,
    "SELECT u.id, u.full_name, u.avatar FROM friendships f
     JOIN users u ON u.id = IF(f.user_id = ?, f.friend_id, f.user_id)
     WHERE (f.user_id = ? OR f.friend_id = ?) AND f.status = 'accepted'",
    "iii", $user_id, $user_id, $user_id);

function user_avatar($u) {
    return 'assets/images/' . htmlspecialchars(!empty($u['avatar']) ? $u['avatar'] : 'student.jpg');
}

function action_form($action, $target, $label, $class = 'btn-pink') {
    return '<form method="post" style="display:inline-block; margin:0 4px;">' . csrf_input()
        . '<input type="hidden" name="action" value="' . $action . '">'
        . '<input type="hidden" name="target_id" value="' . (int)$target . '">'
        . '<button type="submit" class="' . $class . '">' . $label . '</button></form>';
}

$active = 'adopter.php';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ติดต่อผู้ขอรับเลี้ยง - Find My Meow Meow</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/crud.css">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #fff0f3; font-family: 'Kanit', sans-serif; color: #444; }
        .dashboard-layout { display: grid; grid-template-columns: 260px 1fr 310px; min-height: 100vh; }
        .dash-sidebar { background: #ffffff; border-right: 1px solid #ffccd5; padding: 25px 20px; display: flex; flex-direction: column; gap: 12px; position: sticky; top: 0; height: 100vh; }
        .dash-brand { font-weight: bold; font-size: 20px; color: #d81b60; margin-bottom: 15px; }
        .dash-nav-item { display: flex; align-items: center; gap: 12px; padding: 12px 16px; border-radius: 14px; text-decoration: none; color: #555; font-weight: 500; font-size: 15px; transition: all 0.2s; }
        .dash-nav-item.active, .dash-nav-item:hover { background: #ff758c; color: white; box-shadow: 0 4px 12px rgba(255, 117, 140, 0.3); }
        .dash-main { padding: 25px 30px; overflow-y: auto; }
        .card { background: #ffffff; border-radius: 20px; padding: 25px; margin-bottom: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.02); border: 1px solid #ffccd5; }
        .search-row { display: flex; gap: 10px; margin-bottom: 16px; }
        .search-input { flex: 1; padding: 10px 16px; border: 1px solid #ffccd5; border-radius: 20px; outline: none; background: #fff8f9; font-family: 'Kanit', sans-serif; }
        .btn-pink { background-color: #d81b60; color: white; border: none; border-radius: 20px; padding: 8px 18px; cursor: pointer; font-weight: 500; font-family: 'Kanit', sans-serif; }
        .btn-pink:hover { background-color: #ad1457; }
        .btn-light { background-color: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; border-radius: 20px; padding: 8px 18px; cursor: pointer; font-family: 'Kanit', sans-serif; }
        .user-row { display: flex; align-items: center; padding: 12px 0; border-bottom: 1px solid #ffeef2; }
        .user-row:last-child { border-bottom: none; }
        .user-row img { width: 44px; height: 44px; border-radius: 50%; object-fit: cover; margin-right: 14px; border: 2px solid #ffb6c1; }
        .user-row .name { flex: 1; font-weight: 500; }
        .tag { background-color: #ffe4e6; color: #e11d48; padding: 4px 12px; border-radius: 12px; font-size: 0.85rem; }
        .alert-ok { background: #dcfce7; color: #166534; padding: 12px; border-radius: 12px; margin-bottom: 16px; }
        .alert-err { background: #ffe4e6; color: #9f1239; padding: 12px; border-radius: 12px; margin-bottom: 16px; }
    </style>
</head>
<body>

<div class="dashboard-layout">
    <aside class="dash-sidebar">
        <div class="dash-brand">🐾 Find my meow meow</div>
        <a href="index.php" class="dash-nav-item">🏠 หน้าแรก</a>
        <a href="profile.php" class="dash-nav-item">👤 ประวัติผู้ใช้งาน</a>
        <a href="contact.php" class="dash-nav-item">✉️ ติดต่อผู้ประกาศ/แอดมิน</a>
        <a href="buy_cat.php" class="dash-nav-item">🛒 ซื้อแมว</a>
        <a href="adopter.php" class="dash-nav-item active">💬 ติดต่อผู้ขอรับเลี้ยง</a>
        <a href="settings.php" class="dash-nav-item">⚙️ การตั้งค่า</a>
        <div style="margin-top: auto;">
            <a href="logout.php" class="dash-nav-item" style="background: #fff0f3; color: #d81b60; border: 1px solid #ffccd5;">🚪 ออกจากระบบ</a>
        </div>
    </aside>

    <main class="dash-main">
        <?php if ($flash): ?>
            <div class="<?= $flash[0] === 'ok' ? 'alert-ok' : 'alert-err' ?>"><?= htmlspecialchars($flash[1]) ?></div>
        <?php endif; ?>

        <div class="card">
            <h2 style="color: #d81b60; margin-top: 0;">💬 ติดต่อผู้ขอรับเลี้ยง</h2>
            <p style="color: #777; font-size: 14px;">ค้นหาผู้ใช้งานด้วยชื่อ แล้วส่งคำขอเป็นเพื่อนเพื่อพูดคุยเรื่องการรับเลี้ยงแมว</p>
            <form method="get" class="search-row">
                <input type="text" name="search_user" class="search-input" placeholder="พิมพ์ชื่อผู้ใช้งาน..." value="<?= htmlspecialchars($search_user) ?>">
                <button type="submit" class="btn-pink">🔍 ค้นหา</button>
            </form>

            <?php if ($search_user !== ''): ?>
                <?php if (!$users_list): ?>
                    <p style="color: #999;">ไม่พบผู้ใช้ที่ชื่อตรงกับ “<?= htmlspecialchars($search_user) ?>”</p>
                <?php endif; ?>
                <?php foreach ($users_list as $u): ?>
                    <div class="user-row">
                        <img src="<?= user_avatar($u) ?>" alt="">
                        <span class="name"><?= htmlspecialchars($u['full_name']) ?></span>
                        <?php if ($u['fstatus'] === 'accepted'): ?>
                            <span class="tag">เป็นเพื่อนแล้ว</span>
                        <?php elseif ($u['fstatus'] === 'pending'): ?>
                            <span class="tag">รอการตอบรับ</span>
                        <?php else: ?>
                            <?= action_form('add', $u['id'], '+ เพิ่มเพื่อน') ?>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3 style="color: #d81b60; margin-top: 0;">📩 คำขอเป็นเพื่อนที่รอตอบรับ (<?= count($incoming) ?>)</h3>
            <?php if (!$incoming): ?>
                <p style="color: #999;">ยังไม่มีคำขอใหม่</p>
            <?php endif; ?>
            <?php foreach ($incoming as $u): ?>
                <div class="user-row">
                    <img src="<?= user_avatar($u) ?>" alt="">
                    <span class="name"><?= htmlspecialchars($u['full_name']) ?></span>
                    <?= action_form('accept', $u['id'], 'ตอบรับ') ?>
                    <?= action_form('decline', $u['id'], 'ปฏิเสธ', 'btn-light') ?>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="card">
            <h3 style="color: #d81b60; margin-top: 0;">🐱 เพื่อนของฉัน (<?= count($friends) ?>)</h3>
            <?php if (!$friends): ?>
                <p style="color: #999;">ยังไม่มีเพื่อน ลองค้นหาชื่อด้านบนเพื่อเริ่มต้น</p>
            <?php endif; ?>
            <?php foreach ($friends as $u): ?>
                <div class="user-row">
                    <img src="<?= user_avatar($u) ?>" alt="">
                    <span class="name"><?= htmlspecialchars($u['full_name']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </main>

    <aside style="background: #ffffff; border-left: 1px solid #ffccd5; padding: 25px 20px;"></aside>
</div>
</body>
</html>