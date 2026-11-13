<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
date_default_timezone_set('Asia/Bangkok');

require_once 'auth.php';
require_once 'config/db.php';
require_login();

$user_id = (int)$_SESSION['user_id'];
$error = '';
$success_msg = '';

// สร้างตารางเพื่อนและข้อความแชทอัตโนมัติ (หากยังไม่มี)
$conn->query("CREATE TABLE IF NOT EXISTS adopter_friends (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    friend_id INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_friend_pair (user_id, friend_id),
    CONSTRAINT fk_af_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_af_friend FOREIGN KEY (friend_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS adopter_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    message_text TEXT NULL,
    attachment_path VARCHAR(255) NULL,
    attachment_name VARCHAR(255) NULL,
    attachment_type ENUM('none','image','video','file','sticker') NOT NULL DEFAULT 'none',
    is_unsent TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_chat_pair (sender_id, receiver_id, created_at),
    CONSTRAINT fk_am_sender FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_am_receiver FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ฟังก์ชันช่วยแปลงขนาดจาก php.ini เป็น Byte
function php_size_to_bytes(string $val): int {
    $val = trim($val);
    if ($val === '') {
        return 2 * 1024 * 1024;
    }
    $last = strtolower($val[strlen($val) - 1]);
    $num = (int)$val;
    switch ($last) {
        case 'g':
            $num *= 1024;
        case 'm':
            $num *= 1024;
        case 'k':
            $num *= 1024;
    }
    return $num > 0 ? $num : (2 * 1024 * 1024);
}

function format_mb_label(int $bytes): string {
    $mb = $bytes / (1024 * 1024);
    return ($mb >= 1) ? rtrim(rtrim(number_format($mb, 1), '0'), '.') . ' MB' : round($bytes / 1024) . ' KB';
}

function get_avatar_src(?string $avatar): string {
    $file = trim((string)$avatar);
    if ($file !== '' && file_exists(__DIR__ . '/assets/images/' . $file)) {
        return 'assets/images/' . rawurlencode($file);
    }
    return 'assets/images/default_avatar.png';
}

function format_thai_date(string $datetime): string {
    $months = [1=>'มกราคม',2=>'กุมภาพันธ์',3=>'มีนาคม',4=>'เมษายน',5=>'พฤษภาคม',6=>'มิถุนายน',
               7=>'กรกฎาคม',8=>'สิงหาคม',9=>'กันยายน',10=>'ตุลาคม',11=>'พฤศจิกายน',12=>'ธันวาคม'];
    $ts = strtotime($datetime);
    if (!$ts) return '';
    $d = (int)date('j', $ts);
    $m = $months[(int)date('n', $ts)] ?? '';
    $y = (int)date('Y', $ts) + 543;
    return "{$d} {$m} {$y}";
}

// คำนวณเพดานขนาดไฟล์ที่ใช้งานได้จริงโดยไม่ต้องแก้ค่าเซิร์ฟเวอร์
$server_upload_max = php_size_to_bytes((string)ini_get('upload_max_filesize'));
$server_post_max   = php_size_to_bytes((string)ini_get('post_max_size'));
$server_limit      = min($server_upload_max, $server_post_max);

$max_image_bytes = min(2 * 1024 * 1024, $server_limit);
$max_doc_bytes   = min(5 * 1024 * 1024, $server_limit);
$max_video_bytes = min(8 * 1024 * 1024, $server_limit);

// ดักจับกรณีอัปโหลดไฟล์ใหญ่เกิน post_max_size ของเซิร์ฟเวอร์
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && empty($_FILES) && !empty($_SERVER['CONTENT_LENGTH'])) {
    $error = '⚠️ ไฟล์ที่คุณส่งมีขนาดใหญ่เกินขีดจำกัดของเซิร์ฟเวอร์ (' . format_mb_label($server_limit) . ') กรุณาลดขนาดไฟล์หรือส่งเป็นลิงก์ Google Drive แทนค่ะ';
}

// ดึงข้อมูลผู้ใช้ปัจจุบัน
$u_stmt = $conn->prepare("SELECT id, full_name, nickname, avatar FROM users WHERE id = ? LIMIT 1");
$u_stmt->bind_param("i", $user_id);
$u_stmt->execute();
$me = $u_stmt->get_result()->fetch_assoc();
$u_stmt->close();
$my_display_name = trim((string)($me['full_name'] ?? 'ผู้ใช้งาน'));
$my_avatar_src   = get_avatar_src($me['avatar'] ?? '');

// ตรวจสอบข้อมูลการสมัครผู้รับเลี้ยงในตาราง adopter
$chk = $conn->prepare("SELECT * FROM adopter WHERE user_id = ? LIMIT 1");
$chk->bind_param("i", $user_id);
$chk->execute();
$existing_app = $chk->get_result()->fetch_assoc();
$chk->close();

// 1. ยืนยัน PDPA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accept_pdpa'])) {
    check_auth_token();
    if (!empty($_POST['pdpa_read']) && !empty($_POST['pdpa_consent'])) {
        $_SESSION['adopter_pdpa_ok'] = true;
        header("Location: adopter.php?step=form");
        exit();
    } else {
        $error = '⚠️ กรุณากดติ๊กเครื่องหมายถูกทั้ง 2 ช่องเพื่อรับทราบและยินยอมประกาศความเป็นส่วนตัว (PDPA) ก่อนดำเนินการต่อ';
    }
}

// 2. บันทึกแบบฟอร์มสมัครเป็นผู้รับเลี้ยงแมว
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_adopter_form'])) {
    check_auth_token();
    if (empty($_SESSION['adopter_pdpa_ok'])) {
        header("Location: adopter.php");
        exit();
    }

    $firstname = trim((string)($_POST['firstname'] ?? ''));
    $lastname  = trim((string)($_POST['lastname'] ?? ''));
    $b_day     = (int)($_POST['b_day'] ?? 0);
    $b_month   = (int)($_POST['b_month'] ?? 0);
    $b_year_th = (int)($_POST['b_year'] ?? 0);
    $b_year_ad = $b_year_th - 543;

    $occ_sel    = trim((string)($_POST['occupation'] ?? ''));
    $occ_other  = trim((string)($_POST['occupation_other'] ?? ''));
    $occupation = ($occ_sel === 'อื่นๆ') ? ('อื่นๆ: ' . $occ_other) : $occ_sel;

    $had_cat  = ($_POST['had_cat_before'] ?? '') === 'เคย' ? 'เคย' : 'ไม่เคย';
    $has_pets = ($_POST['has_current_pets'] ?? '') === 'มี' ? 'มี' : 'ไม่มี';

    $acc_sel       = trim((string)($_POST['accommodation_type'] ?? ''));
    $acc_other     = trim((string)($_POST['accommodation_other'] ?? ''));
    $accommodation = ($acc_sel === 'อื่นๆ') ? ('อื่นๆ: ' . $acc_other) : $acc_sel;

    $fin_sel   = trim((string)($_POST['financial_status'] ?? ''));
    $fin_other = trim((string)($_POST['financial_other'] ?? ''));
    $financial = ($fin_sel === 'อื่นๆ') ? ('อื่นๆ: ' . $fin_other) : $fin_sel;

    $pet_allowed    = in_array($_POST['pet_allowed'] ?? '', ['อนุญาต','ไม่อนุญาต','ไม่แน่ใจ'], true) ? $_POST['pet_allowed'] : 'ไม่แน่ใจ';
    $has_disease    = ($_POST['has_chronic_disease'] ?? '') === 'มี' ? 'มี' : 'ไม่มี';
    $health_details = trim((string)($_POST['health_details'] ?? ''));
    $truth_confirm  = !empty($_POST['truth_confirm']);

    if ($firstname === '' || $lastname === '') {
        $error = '⚠️ กรุณากรอกชื่อและนามสกุลให้ครบถ้วน';
    } elseif (!checkdate($b_month, $b_day, $b_year_ad)) {
        $error = '⚠️ กรุณาเลือกวัน เดือน ปีเกิดให้ถูกต้อง';
    } elseif ($occ_sel === '' || ($occ_sel === 'อื่นๆ' && $occ_other === '')) {
        $error = '⚠️ กรุณาเลือกอาชีพ (หรือระบุอาชีพอื่นๆ)';
    } elseif ($acc_sel === '' || ($acc_sel === 'อื่นๆ' && $acc_other === '')) {
        $error = '⚠️ กรุณาเลือกประเภทที่พักอาศัย (หรือระบุอื่นๆ)';
    } elseif ($fin_sel === '' || ($fin_sel === 'อื่นๆ' && $fin_other === '')) {
        $error = '⚠️ กรุณาเลือกสถานะทางการเงิน (หรือระบุอื่นๆ)';
    } elseif (!$truth_confirm) {
        $error = '⚠️ กรุณาติ๊กช่อง "ข้าพเจ้าขอยืนยันว่าข้อมูลที่กรอกเป็นความจริง" ก่อนกดสมัคร';
    } else {
        $birthdate   = sprintf('%04d-%02d-%02d', $b_year_ad, $b_month, $b_day);
        $exp_text    = "เคยเลี้ยงแมว: {$had_cat} | ปัจจุบันมีสัตว์เลี้ยง: {$has_pets}";
        $acc_text    = mb_substr("{$accommodation} (ที่พัก: {$pet_allowed})", 0, 95, 'UTF-8');
        $fin_text    = mb_substr($financial, 0, 95, 'UTF-8');
        $occ_text    = mb_substr($occupation, 0, 95, 'UTF-8');
        $health_text = "โรคประจำตัว: {$has_disease}" . ($health_details !== '' ? " | รายละเอียด: {$health_details}" : '');
        $report_cnt  = 0;

        $ins = $conn->prepare("INSERT INTO adopter
            (user_id, firstname, lastname, birthdate, adoptionexperience, accommodationtype, financialstatus, occupation, chronicdisease, pdpa_accepted, application_status, reporthistorycount, createddate)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 'pending', ?, NOW())");
        $ins->bind_param("issssssssi", $user_id, $firstname, $lastname, $birthdate, $exp_text, $acc_text, $fin_text, $occ_text, $health_text, $report_cnt);

        if ($ins->execute()) {
            unset($_SESSION['adopter_pdpa_ok']);
            header("Location: adopter.php?submitted=1");
            exit();
        } else {
            $error = '❌ เกิดข้อผิดพลาดในการบันทึกข้อมูล: ' . $conn->error;
        }
        $ins->close();
    }
}

// 3. เพิ่มเพื่อนในระบบแชท (บันทึกแบบ 2 ทางเพื่อให้แชทหากันได้ทั้ง 2 ฝั่ง)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_friend'])) {
    check_auth_token();
    $target_id = (int)($_POST['friend_id'] ?? 0);
    if ($target_id > 0 && $target_id !== $user_id) {
        $f1 = $conn->prepare("INSERT IGNORE INTO adopter_friends (user_id, friend_id) VALUES (?, ?), (?, ?)");
        $f1->bind_param("iiii", $user_id, $target_id, $target_id, $user_id);
        $f1->execute();
        $f1->close();
        header("Location: adopter.php?view=friends&friend_id=" . $target_id);
        exit();
    }
}

// 4. ยืนยันยกเลิกข้อความ (Unsend)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_unsend_msg'])) {
    check_auth_token();
    $msg_id    = (int)($_POST['msg_id'] ?? 0);
    $friend_id = (int)($_POST['friend_id'] ?? 0);
    if ($msg_id > 0) {
        $uns = $conn->prepare("UPDATE adopter_messages SET is_unsent = 1, message_text = NULL, attachment_path = NULL, attachment_name = NULL WHERE id = ? AND sender_id = ?");
        $uns->bind_param("ii", $msg_id, $user_id);
        $uns->execute();
        $uns->close();
    }
    header("Location: adopter.php?view=friends&friend_id=" . $friend_id);
    exit();
}

// 5. ส่งข้อความที่พิมพ์เอง / สติกเกอร์ / รูปภาพ / วิดีโอ / เอกสาร (รองรับทั้ง 2 ฝั่ง)
$active_friend_id = (int)($_GET['friend_id'] ?? ($_POST['friend_id'] ?? 0));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['send_chat']) || isset($_POST['send_sticker']) || isset($_POST['message_text']))) {
    check_auth_token();
    $friend_id = (int)($_POST['friend_id'] ?? 0);

    // ตรวจสอบว่าเป็นเพื่อนกันหรือมีตัวตนจริงในระบบหรือไม่ ถ้ามีให้ผูกความเป็นเพื่อน 2 ทางอัตโนมัติ
    $is_friend = false;
    if ($friend_id > 0 && $friend_id !== $user_id) {
        $uchk = $conn->prepare("SELECT id FROM users WHERE id = ? LIMIT 1");
        $uchk->bind_param("i", $friend_id);
        $uchk->execute();
        if ($uchk->get_result()->fetch_assoc()) {
            $add_auto = $conn->prepare("INSERT IGNORE INTO adopter_friends (user_id, friend_id) VALUES (?, ?), (?, ?)");
            $add_auto->bind_param("iiii", $user_id, $friend_id, $friend_id, $user_id);
            $add_auto->execute();
            $add_auto->close();
            $is_friend = true;
        }
        $uchk->close();
    }

    if (!$is_friend) {
        $error = '⚠️ กรุณาเลือกคู่สนทนาก่อนเริ่มส่งข้อความ';
    } elseif (isset($_POST['send_sticker'])) {
        // กรณีกดส่งสติกเกอร์ (แยกฟอร์มออกมาแล้ว จึงไม่ชนกับปุ่ม Enter)
        $sticker_val = trim((string)($_POST['send_sticker'] ?? ''));
        $allowed_stickers = [
            '😸 ยินดีที่ได้คุยกันนะเมี้ยว!',
            '😻 น้องแมวน่ารักมากเลย!',
            '🐾 รอรับเลี้ยงน้องอยู่นะคะ',
            '💖 ขอบคุณมากๆ ค่ะ',
            '😿 ขอสอบถามรายละเอียดเพิ่มเติม',
            '🙀 จริงเหรอเนี่ย!'
        ];
        if (in_array($sticker_val, $allowed_stickers, true)) {
            $stmt = $conn->prepare("INSERT INTO adopter_messages (sender_id, receiver_id, message_text, attachment_type) VALUES (?, ?, ?, 'sticker')");
            $stmt->bind_param("iis", $user_id, $friend_id, $sticker_val);
            $stmt->execute();
            $stmt->close();
            header("Location: adopter.php?view=friends&friend_id=" . $friend_id);
            exit();
        }
    } else {
        // กรณีพิมพ์ข้อความเอง หรือแนบไฟล์ส่ง
        $msg_text = trim((string)($_POST['message_text'] ?? ''));
        $att_path = null;
        $att_name = null;
        $att_type = 'none';

        $upload_dir = __DIR__ . '/uploads/chat/';
        if (!is_dir($upload_dir)) {
            @mkdir($upload_dir, 0755, true);
        }

        $file_inputs = [
            'chat_image' => [
                'type' => 'image',
                'exts' => ['jpg', 'jpeg', 'png'],
                'max'  => $max_image_bytes,
                'label'=> 'รูปภาพ'
            ],
            'chat_video' => [
                'type' => 'video',
                'exts' => ['mp4', 'webm', 'mov'],
                'max'  => $max_video_bytes,
                'label'=> 'วิดีโอ'
            ],
            'chat_doc'   => [
                'type' => 'file',
                'exts' => ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'txt', 'zip'],
                'max'  => $max_doc_bytes,
                'label'=> 'ไฟล์เอกสาร'
            ],
        ];

        $uploaded_count = 0;
        foreach ($file_inputs as $field => $cfg) {
            if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $uploaded_count++;
            $f = $_FILES[$field];

            if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE) {
                $error = "⚠️ {$cfg['label']}มีขนาดใหญ่เกินขีดจำกัดของเซิร์ฟเวอร์ (สูงสุด " . format_mb_label($cfg['max']) . ") แนะนำให้ส่งเป็นลิงก์ Google Drive แทนค่ะ";
                break;
            }
            if ($f['error'] !== UPLOAD_ERR_OK) {
                $error = "⚠️ ไม่สามารถอัปโหลด{$cfg['label']}ได้ (รหัสข้อผิดพลาด: {$f['error']})";
                break;
            }
            if ($f['size'] > $cfg['max']) {
                $error = "⚠️ {$cfg['label']}ต้องมีขนาดไม่เกิน " . format_mb_label($cfg['max']) . " (หากไฟล์ใหญ่กว่านี้แนะนำให้ส่งเป็นลิงก์ Google Drive ค่ะ)";
                break;
            }

            $orig_name = basename((string)$f['name']);
            $ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
            if (!in_array($ext, $cfg['exts'], true)) {
                $error = "⚠️ {$cfg['label']}รองรับเฉพาะนามสกุล: ." . implode(', .', $cfg['exts']) . " เท่านั้น";
                break;
            }

            if ($cfg['type'] === 'image' && @getimagesize($f['tmp_name']) === false) {
                $error = "⚠️ ไฟล์รูปภาพไม่ถูกต้องหรือเสียหาย กรุณาเลือกไฟล์ .jpg, .jpeg หรือ .png";
                break;
            }

            $safe_name = time() . '_' . $user_id . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $orig_name);
            $dest = $upload_dir . $safe_name;
            if (move_uploaded_file($f['tmp_name'], $dest)) {
                @chmod($dest, 0644);
                $att_path = 'uploads/chat/' . $safe_name;
                $att_name = $orig_name;
                $att_type = $cfg['type'];
            } else {
                $error = '❌ บันทึกไฟล์ไม่สำเร็จ กรุณาตรวจสอบสิทธิ์โฟลเดอร์ uploads/chat';
            }
            break;
        }

        if ($error === '' && ($msg_text !== '' || $att_path !== null)) {
            $stmt = $conn->prepare("INSERT INTO adopter_messages (sender_id, receiver_id, message_text, attachment_path, attachment_name, attachment_type) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iissss", $user_id, $friend_id, $msg_text, $att_path, $att_name, $att_type);
            if ($stmt->execute()) {
                $stmt->close();
                header("Location: adopter.php?view=friends&friend_id=" . $friend_id);
                exit();
            } else {
                $error = '❌ ส่งข้อความไม่สำเร็จ: ' . $stmt->error;
                $stmt->close();
            }
        } elseif ($error === '' && $uploaded_count === 0 && $msg_text === '') {
            $error = '⚠️ กรุณาพิมพ์ข้อความหรือเลือกไฟล์ก่อนกดส่งค่ะ';
        }
    }
}

// ตรวจสอบว่าผู้ใช้เคยมีเพื่อนหรือข้อความแชทในระบบหรือยัง (รวมถึงกรณีอีกฝั่งทักมา)
$has_chat_chk = $conn->prepare("SELECT 1 FROM adopter_friends WHERE user_id = ? OR friend_id = ? LIMIT 1");
$has_chat_chk->bind_param("ii", $user_id, $user_id);
$has_chat_chk->execute();
$has_any_friends = (bool)$has_chat_chk->get_result()->fetch_assoc();
$has_chat_chk->close();

// กำหนดหน้าแสดงผลปัจจุบัน
$requested_view = $_GET['view'] ?? '';
$step = (!empty($_SESSION['adopter_pdpa_ok']) && ($_GET['step'] ?? '') === 'form') ? 'form' : 'pdpa';

if (isset($_GET['submitted'])) {
    $step = 'done';
} elseif ($requested_view === 'friends' || isset($_GET['friend_id']) || isset($_GET['q'])) {
    $step = 'friends';
} elseif ($existing_app) {
    $step = ($requested_view === 'friends') ? 'friends' : 'rules';
} elseif ($has_any_friends && ($_GET['step'] ?? '') !== 'pdpa' && ($_GET['step'] ?? '') !== 'form') {
    $step = 'friends';
}

// โหลดข้อมูลสำหรับหน้าแชท/ค้นหาเพื่อน
$friends_list = [];
$search_query = trim((string)($_GET['q'] ?? ''));
$search_results = [];
$active_friend = null;
$chat_messages = [];
$confirm_unsend_id = (int)($_GET['confirm_unsend'] ?? 0);
$view_media_id = (int)($_GET['view_media'] ?? 0);$active_media_item = null;

if ($step === 'friends') {
    // ดึงรายชื่อคู่สนทนาทั้งหมด (ทั้งฝั่งที่เราเพิ่ม และฝั่งที่ทักแชทมาหาเรา)
    $fl_stmt =$conn->prepare("SELECT DISTINCT u.id, u.full_name, u.nickname, u.avatar,
        (SELECT MAX(m.created_at) FROM adopter_messages m
         WHERE (m.sender_id = ? AND m.receiver_id = u.id) OR (m.sender_id = u.id AND m.receiver_id = ?)) AS last_msg_time
        FROM users u
        WHERE u.id != ? AND (
            EXISTS (SELECT 1 FROM adopter_friends af WHERE (af.user_id = ? AND af.friend_id = u.id) OR (af.user_id = u.id AND af.friend_id = ?))
            OR EXISTS (SELECT 1 FROM adopter_messages am WHERE (am.sender_id = ? AND am.receiver_id = u.id) OR (am.sender_id = u.id AND am.receiver_id = ?))
        )
        ORDER BY last_msg_time DESC, u.full_name ASC");
    $fl_stmt->bind_param("iiiiiii", $user_id, $user_id,$user_id, $user_id,$user_id, $user_id,$user_id);
    $fl_stmt->execute();$friends_list = $fl_stmt->get_result()->fetch_all(MYSQLI_ASSOC);$fl_stmt->close();

    if ($search_query !== '') {
        $like = '%' .$search_query . '%';
        $sq_stmt =$conn->prepare("SELECT u.id, u.full_name, u.nickname, u.avatar,
            EXISTS(SELECT 1 FROM adopter_friends af WHERE (af.user_id = ? AND af.friend_id = u.id) OR (af.user_id = u.id AND af.friend_id = ?)) AS is_friend
            FROM users u
            WHERE u.id != ? AND (u.full_name LIKE ? OR u.nickname LIKE ?)
            ORDER BY u.full_name ASC
            LIMIT 20");
        $sq_stmt->bind_param("iiiss", $user_id, $user_id,$user_id, $like,$like);
        $sq_stmt->execute();$search_results = $sq_stmt->get_result()->fetch_all(MYSQLI_ASSOC);$sq_stmt->close();
    }

    if ($active_friend_id <= 0 && !empty($friends_list)) {
        $active_friend_id = (int)$friends_list[0]['id'];
    }

    if ($active_friend_id > 0) {
        foreach ($friends_list as$fr) {
            if ((int)$fr['id'] ===$active_friend_id) {
                $active_friend =$fr;
                break;
            }
        }
        // กรณีเปิดลิงก์ตรง ?view=friends&friend_id=9 แม้ยังไม่เคยกดปุ่มเพิ่มเพื่อน ก็ดึงข้อมูลคู่สนทนามาแชทได้เลย
        if (!$active_friend && $active_friend_id !==$user_id) {
            $df_stmt =$conn->prepare("SELECT id, full_name, nickname, avatar FROM users WHERE id = ? LIMIT 1");
            $df_stmt->bind_param("i", $active_friend_id);
            $df_stmt->execute();$direct_user = $df_stmt->get_result()->fetch_assoc();$df_stmt->close();
            if ($direct_user) {
                $active_friend =$direct_user;
                array_unshift($friends_list,$direct_user);
            }
        }

        if ($active_friend) {
            $m_stmt =$conn->prepare("SELECT m.*, u.full_name AS sender_name
                FROM adopter_messages m
                JOIN users u ON u.id = m.sender_id
                WHERE (m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?)
                ORDER BY m.created_at ASC, m.id ASC");
            $m_stmt->bind_param("iiii", $user_id,$active_friend_id, $active_friend_id,$user_id);
            $m_stmt->execute();$chat_messages = $m_stmt->get_result()->fetch_all(MYSQLI_ASSOC);$m_stmt->close();

            if ($view_media_id > 0) {
                foreach ($chat_messages as$cm) {
                    if ((int)$cm['id'] === $view_media_id && empty($cm['is_unsent']) && in_array($cm['attachment_type'], ['image', 'video'], true) && !empty($cm['attachment_path'])) {
                        $active_media_item =$cm;
                        break;
                    }
                }
            }
        }
    }
}

$th_months = [1=>'มกราคม',2=>'กุมภาพันธ์',3=>'มีนาคม',4=>'เมษายน',5=>'พฤษภาคม',6=>'มิถุนายน',7=>'กรกฎาคม',8=>'สิงหาคม',9=>'กันยายน',10=>'ตุลาคม',11=>'พฤศจิกายน',12=>'ธันวาคม'];
$app_fullname = trim(($existing_app['firstname'] ?? '') . ' ' . ($existing_app['lastname'] ?? ''));
$app_status_raw =$existing_app['application_status'] ?? '';
$app_status_label = [
    'pending'  => '⏳ รอการพิจารณาจากผู้ประกาศ/ผู้ดูแลระบบ (Pending)',
    'approved' => '✅ ผ่านการสมัครเป็นผู้รับเลี้ยงแมว (Approved)',
    'rejected' => '❌ ไม่ผ่านการพิจารณา (Rejected)'
][$app_status_raw] ?? '💬 ผู้ใช้งานทั่วไป / ผู้ประกาศหาบ้านให้แมว';
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ผู้รับเลี้ยงแมวและแชท - Find my meow meow 💖</title>
    <link rel="stylesheet" href="assets/css/style.css?v=2">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #fff0f3; font-family: 'Kanit', sans-serif; color: #333; }
        .layout-wrap { display: flex; min-height: 100vh; }
        .sidebar { width: 250px; background: #fff; border-right: 1px solid #ffd6e0; padding: 20px 14px; display: flex; flex-direction: column; justify-content: space-between; flex-shrink: 0; }
        .brand { font-size: 20px; font-weight: 600; color: #d81b60; margin-bottom: 22px; padding-left: 8px; }
        .nav-link { display: flex; align-items: center; gap: 10px; padding: 11px 14px; border-radius: 14px; text-decoration: none; color: #444; font-size: 14px; margin-bottom: 6px; }
        .nav-link:hover { background: #fff5f8; color: #d81b60; }
        .nav-link.active { background: #ff6f91; color: #fff; font-weight: 500; }
        .main-content { flex: 1; padding: 22px; max-width: 1150px; margin: 0 auto; width: 100%; }

        .card-box { background: #fff; border-radius: 22px; padding: 26px; border: 1px solid #ffccd5; box-shadow: 0 6px 22px rgba(255,117,140,0.08); }
        .reg-title { text-align: center; color: #1976d2; margin: 0 0 4px 0; font-size: 24px; }
        .reg-sub { text-align: center; color: #777; font-size: 13.5px; margin: 0 0 20px 0; }
        .pdpa-scroll { max-height: 320px; overflow-y: auto; background: #fff8f9; border: 1px solid #ffccd5; border-radius: 16px; padding: 18px; font-size: 13.5px; line-height: 1.7; margin-bottom: 16px; }
        .pdpa-scroll h4 { color: #d81b60; margin: 12px 0 4px 0; }
        .section-box { border-top: 1px solid #eee; padding-top: 16px; margin-top: 16px; }
        .section-title { font-size: 16px; font-weight: 600; color: #222; margin: 0 0 12px 0; }
        .form-label { display: block; font-size: 13.5px; font-weight: 500; color: #444; margin-bottom: 6px; }
        .form-control { width: 100%; padding: 10px 15px; border: 1px solid #dcdcdc; border-radius: 22px; font-family: 'Kanit', sans-serif; font-size: 14px; outline: none; background: #fff; margin-bottom: 12px; }
        .dob-grid { display: grid; grid-template-columns: 1fr 1.5fr 1fr; gap: 10px; }
        .radio-group { display: flex; gap: 20px; margin-bottom: 14px; align-items: center; flex-wrap: wrap; }
        .radio-group label { display: inline-flex; align-items: center; gap: 8px; font-size: 14px; }
        .green-check-box { background: #f8fff8; border: 1px solid #a5d6a7; border-radius: 16px; padding: 16px; margin-top: 14px; }
        .green-check-item { display: flex; align-items: flex-start; gap: 10px; margin-bottom: 10px; font-size: 13.5px; }
        .btn-primary { width: 100%; background: #d81b60; color: #fff; border: none; padding: 12px; border-radius: 24px; font-size: 15px; font-weight: 600; cursor: pointer; font-family: 'Kanit', sans-serif; margin-top: 12px; }
        .btn-primary:hover { background: #ad1457; }
        .alert-err { background: #ffebee; color: #b71c1c; border: 1px solid #ef9a9a; padding: 12px 16px; border-radius: 14px; margin-bottom: 16px; font-size: 13.5px; }
        .alert-ok { background: #e8f5e9; color: #1b5e20; border: 1px solid #a5d6a7; padding: 20px; border-radius: 16px; text-align: center; font-size: 15px; line-height: 1.7; }

        .status-banner { background: #fffde7; border: 1px solid #ffe082; border-radius: 16px; padding: 14px 20px; margin-bottom: 18px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; font-size: 14px; }
        .rules-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 16px; }
        .rules-col { background: #fff9fb; border: 1px solid #ffd6e0; border-radius: 18px; padding: 20px; font-size: 14px; line-height: 1.75; }

        /* Chat & Search */
        .search-bar { display: flex; gap: 10px; background: #fff; border: 1px solid #ffccd5; border-radius: 30px; padding: 8px 14px; margin-bottom: 16px; align-items: center; }
        .search-bar input { flex: 1; border: none; outline: none; font-family: 'Kanit', sans-serif; font-size: 14px; }
        .search-btn { background: #d81b60; color: #fff; border: none; padding: 8px 20px; border-radius: 20px; cursor: pointer; font-family: 'Kanit', sans-serif; font-weight: 500; }
        .search-results-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 12px; margin-bottom: 18px; }
        .user-card { display: flex; align-items: center; justify-content: space-between; gap: 10px; background: #fff8fa; border: 1px solid #ffd6e0; border-radius: 16px; padding: 10px 14px; }
        .user-card-left { display: flex; align-items: center; gap: 10px; min-width: 0; }
        .avatar-sm { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid #ffccd5; flex-shrink: 0; }
        .btn-add-friend { background: #ff4f8b; color: #fff; border: none; padding: 6px 12px; border-radius: 14px; font-size: 12.5px; cursor: pointer; font-family: 'Kanit', sans-serif; text-decoration: none; white-space: nowrap; }

        .chat-shell { display: grid; grid-template-columns: 270px 1fr; gap: 16px; min-height: 540px; }
        .friends-panel { border-right: 1px solid #ffe4ec; padding-right: 12px; }
        .friend-item { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 14px; text-decoration: none; color: #333; margin-bottom: 8px; border: 1px solid transparent; }
        .friend-item:hover { background: #fff5f8; }
        .friend-item.active { background: #ffe4ec; border-color: #ff9ebb; font-weight: 500; }

        .chat-panel { display: flex; flex-direction: column; background: #fffdfd; border: 1px solid #ffe4ec; border-radius: 18px; overflow: hidden; }
        .chat-header { display: flex; justify-content: space-between; align-items: center; padding: 12px 18px; background: #fff5f8; border-bottom: 1px solid #ffd6e0; }
        .chat-me-badge { display: flex; align-items: center; gap: 8px; background: #fff; padding: 4px 12px 4px 6px; border-radius: 20px; border: 1px solid #ffccd5; font-size: 13px; }
        .chat-messages { flex: 1; padding: 16px; overflow-y: auto; max-height: 400px; display: flex; flex-direction: column; gap: 10px; }
        .date-divider { text-align: center; margin: 8px 0; }
        .date-divider span { background: #ffeef2; color: #b03a62; font-size: 12px; padding: 4px 14px; border-radius: 14px; }

        .msg-row { display: flex; flex-direction: column; max-width: 75%; }
        .msg-row.mine { align-self: flex-end; align-items: flex-end; }
        .msg-row.theirs { align-self: flex-start; align-items: flex-start; }
        .msg-bubble { padding: 10px 14px; border-radius: 16px; font-size: 14px; line-height: 1.5; word-break: break-word; }
        .msg-row.mine .msg-bubble { background: #ff6f91; color: #fff; border-bottom-right-radius: 4px; }
        .msg-row.theirs .msg-bubble { background: #f3f4f6; color: #222; border-bottom-left-radius: 4px; }
        .msg-unsent { font-style: italic; font-size: 13px; background: #f9f9f9 !important; border: 1px dashed #ddd; color: #777 !important; }
        .msg-meta { font-size: 11px; color: #888; margin-top: 3px; display: flex; gap: 8px; align-items: center; }
        .unsend-link { color: #d81b60; text-decoration: none; font-size: 11px; }
        .unsend-confirm-box { background: #fff3cd; border: 1px solid #ffeeba; padding: 10px 14px; border-radius: 12px; margin: 10px 16px 0; font-size: 13px; display: flex; justify-content: space-between; align-items: center; gap: 10px; flex-wrap: wrap; }

        /* สไตล์รูปภาพและวิดีโอในแชทที่กดขยายดูได้ */
        .media-preview-link { display: inline-block; position: relative; border-radius: 12px; overflow: hidden; text-decoration: none; border: 2px solid rgba(255,255,255,0.4); }
        .media-preview-link:hover { opacity: 0.92; }
        .media-zoom-badge { position: absolute; bottom: 8px; right: 8px; background: rgba(0,0,0,0.65); color: #fff; font-size: 11px; padding: 2px 8px; border-radius: 10px; }

        /* หน้าต่าง POPUP (CSS + HTML ล้วน) สำหรับดูรูปภาพ/วิดีโอ และปุ่มกากบาทปิด */
        .media-lightbox-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.85);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .media-lightbox-box {
            position: relative;
            background: #111;
            border: 3px solid #fff;
            border-radius: 16px;
            max-width: 90vw;
            max-height: 88vh;
            padding: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 40px rgba(0,0,0,0.6);
        }
        .media-lightbox-close {
            position: absolute;
            top: -18px;
            right: -18px;
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: #ff1744;
            color: #fff;
            border: 3px solid #fff;
            font-size: 20px;
            font-weight: 700;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.4);
            z-index: 10000;
        }
        .media-lightbox-close:hover {
            background: #d50000;
            transform: scale(1.06);
        }
        .media-lightbox-content {
            max-width: 84vw;
            max-height: 80vh;
            border-radius: 10px;
            display: block;
        }

        /* แถบพิมพ์ข้อความด้านล่าง (แยกฟอร์มข้อความกับฟอร์มสติกเกอร์ออกจากกัน) */
        .chat-compose { padding: 12px 14px; border-top: 1px solid #ffd6e0; background: #fff; position: relative; z-index: 5; }
        .compose-wrapper { display: flex; align-items: center; gap: 8px; }
        .compose-main-form { display: flex; align-items: center; gap: 8px; flex: 1; margin: 0; }
        .compose-input { flex: 1 1 auto; width: 100%; min-width: 140px; padding: 10px 16px; border: 1.5px solid #ff9ebb; border-radius: 22px; font-family: 'Kanit', sans-serif; font-size: 14px; outline: none; background: #fff; color: #333; }
        .compose-input:focus { border-color: #d81b60; box-shadow: 0 0 0 3px rgba(216,27,96,0.1); }
        .icon-upload-label { flex-shrink: 0; display: inline-flex; align-items: center; justify-content: center; width: 38px; height: 38px; border-radius: 50%; background: #fff0f5; border: 1px solid #ffccd5; cursor: pointer; font-size: 17px; position: relative; overflow: hidden; }
        .icon-upload-label:hover { background: #ffe0ea; }
        .icon-upload-label input[type="file"] { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
        .sticker-form { margin: 0; flex-shrink: 0; }
        .sticker-picker { position: relative; }
        .sticker-picker summary { list-style: none; display: inline-flex; align-items: center; justify-content: center; width: 38px; height: 38px; border-radius: 50%; background: #fff0f5; border: 1px solid #ffccd5; cursor: pointer; font-size: 18px; }
        .sticker-menu { position: absolute; bottom: 46px; right: 0; width: 250px; background: #fff; border: 1px solid #ffccd5; border-radius: 16px; padding: 10px; box-shadow: 0 8px 24px rgba(0,0,0,0.12); display: grid; grid-template-columns: 1fr; gap: 6px; z-index: 20; }
        .sticker-btn { background: #fff7fa; border: 1px solid #ffd6e0; border-radius: 10px; padding: 6px 10px; text-align: left; cursor: pointer; font-family: 'Kanit', sans-serif; font-size: 13px; }
        .sticker-btn:hover { background: #ffe4ec; }
        .btn-send { flex-shrink: 0; background: #d81b60; color: #fff; border: none; padding: 10px 20px; border-radius: 22px; cursor: pointer; font-family: 'Kanit', sans-serif; font-weight: 500; font-size: 14px; white-space: nowrap; }
        .btn-send:hover { background: #ad1457; }
        .limit-hint { font-size: 11.5px; color: #777; margin-top: 6px; }

        @media (max-width: 900px) {
            .layout-wrap { flex-direction: column; }
            .sidebar { width: 100%; border-right: none; border-bottom: 1px solid #ffd6e0; }
            .rules-grid, .chat-shell, .dob-grid { grid-template-columns: 1fr; }
            .friends-panel { border-right: none; border-bottom: 1px solid #ffe4ec; padding-right: 0; padding-bottom: 12px; }
            .compose-wrapper, .compose-main-form { flex-wrap: wrap; }
        }
    </style>
</head>
<body>
<div class="layout-wrap">
    <aside class="sidebar">
        <div>
            <div class="brand">🐾 Find my meow meow</div>
            <a href="index.php" class="nav-link">🏠 หน้าแรก</a>
            <a href="profile.php" class="nav-link">👤 ประวัติผู้ใช้งาน</a>
            <a href="contact.php" class="nav-link">✉️ ติดต่อผู้ประกาศ/แอดมิน</a>
            <a href="buy_cat.php" class="nav-link">🛒 ซื้อแมว</a>
            <a href="adopter.php" class="nav-link <?= $step !== 'friends' ? 'active' : '' ?>">📝 สมัครเป็นผู้รับเลี้ยงแมว</a>
            <a href="adopter.php?view=friends" class="nav-link <?= $step === 'friends' ? 'active' : '' ?>">💬 ห้องแชทและเพิ่มเพื่อน</a>
            <a href="settings.php" class="nav-link">⚙️ การตั้งค่า</a>
        </div>
        <form method="POST" action="logout.php" style="margin-top:16px;">
            <input type="hidden" name="auth_csrf" value="<?= auth_escape(auth_token()) ?>">
            <button type="submit" class="nav-link" style="width:100%; border:1px solid #ffccd5; background:#fff; cursor:pointer; color:#d81b60; font-family:'Kanit',sans-serif;">🚪 ออกจากระบบ</button>
        </form>
    </aside>

    <main class="main-content">
        <?php if ($error): ?>
            <div class="alert-err"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <?php if ($step === 'done'): ?>
            <div class="card-box">
                <div class="alert-ok">
                    <div style="font-size:40px; margin-bottom:8px;">💖🐾</div>
                    <strong>สมัครเป็นผู้รับเลี้ยงเรียบร้อยแล้ว โปรดรอการพิจารณาจากผู้ประกาศ/ผู้ดูแลระบบอีกครั้ง</strong><br>
                    <span style="font-size:13px; color:#2e7d32;">สถานะคำขอปัจจุบัน: <?= htmlspecialchars($app_status_label, ENT_QUOTES, 'UTF-8') ?></span>
                    <div style="margin-top:18px; display:flex; gap:10px; justify-content: