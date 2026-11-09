<?php
require_once 'auth.php';
require_once 'config/db.php';
require_login();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
header("Location: Adopter.php?r=" . $result);  // เปลี่ยนเป็นตัว A ใหญ่
$active = 'Adopter.php';
$user_id = (int)$_SESSION['user_id'];

function fetch_all_rows($conn, $sql, $types, ...$params) {
    $s = $conn->prepare($sql);
    $s->bind_param($types, ...$params);
    $s->execute();
    $rows = $s->get_result()->fetch_all(MYSQLI_ASSOC);
    $s->close();
    return $rows;
}

// ---------- จัดการคำขอเพื่อน (POST + CSRF แทน GET) ----------
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

// ---------- ข้อมูลสำหรับแสดงผล ----------
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
    return 'assets/images/' . htmlspecialchars($u['avatar'] ?: 'default.jpg');
}
function action_form($action, $target, $label, $class = 'btn') {
    return '<form method="post" class="inline">' . csrf_input()
        . '<input type="hidden" name="action" value="' . $action . '">'
        . '<input type="hidden" name="target_id" value="' . (int)$target . '">'
        . '<button type="submit" class="' . $class . '">' . $label . '</button></form>';
}

$page_title = 'ติดต่อผู้ขอรับเลี้ยง';
$active = 'adopter.php';
include 'layout_top.php';
?>

<?php if ($flash): ?>
    <div class="<?= $flash[0] === 'ok' ? 'alert-ok' : 'alert-err' ?>"><?= htmlspecialchars($flash[1]) ?></div>
<?php endif; ?>

<div class="card">
    <h2>💬 ติดต่อผู้ขอรับเลี้ยง</h2>
    <p class="muted">ค้นหาผู้ใช้งานด้วยชื่อ แล้วส่งคำขอเป็นเพื่อนเพื่อพูดคุยเรื่องการรับเลี้ยงแมว</p>
    <form method="get" class="search-row">
        <input type="text" name="search_user" class="search-input" placeholder="พิมพ์ชื่อผู้ใช้งาน..." value="<?= htmlspecialchars($search_user) ?>">
        <button type="submit" class="btn">🔍 ค้นหา</button>
    </form>

    <?php if ($search_user !== ''): ?>
        <?php if (!$users_list): ?>
            <p class="muted">ไม่พบผู้ใช้ที่ชื่อตรงกับ “<?= htmlspecialchars($search_user) ?>” ลองพิมพ์ชื่อบางส่วน</p>
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
    <h3>📩 คำขอเป็นเพื่อนที่รอตอบรับ (<?= count($incoming) ?>)</h3>
    <?php if (!$incoming): ?>
        <p class="muted">ยังไม่มีคำขอใหม่</p>
    <?php endif; ?>
    <?php foreach ($incoming as $u): ?>
        <div class="user-row">
            <img src="<?= user_avatar($u) ?>" alt="">
            <span class="name"><?= htmlspecialchars($u['full_name']) ?></span>
            <?= action_form('accept', $u['id'], 'ตอบรับ') ?>
            <?= action_form('decline', $u['id'], 'ปฏิเสธ', 'btn btn-light') ?>
        </div>
    <?php endforeach; ?>
</div>

<div class="card">
    <h3>🐱 เพื่อนของฉัน (<?= count($friends) ?>)</h3>
    <?php if (!$friends): ?>
        <p class="muted">ยังไม่มีเพื่อน ลองค้นหาชื่อด้านบนเพื่อเริ่มต้น</p>
    <?php endif; ?>
    <?php foreach ($friends as $u): ?>
        <div class="user-row">
            <img src="<?= user_avatar($u) ?>" alt="">
            <span class="name"><?= htmlspecialchars($u['full_name']) ?></span>
        </div>
    <?php endforeach; ?>
</div>

<?php include 'layout_bottom.php'; ?>