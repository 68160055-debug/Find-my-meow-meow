<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
date_default_timezone_set('Asia/Bangkok');

require_once 'auth.php';
require_once 'config/db.php';
require_login();

$user_id = (int)$_SESSION['user_id'];
$error = '';

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
    $months = [
        1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
        5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
        9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
    ];
    $ts = strtotime($datetime);
    if (!$ts) return '';
    $d = (int)date('j', $ts);
    $m = $months[(int)date('n', $ts)] ?? '';
    $y = (int)date('Y', $ts) + 543;
    return "{$d} {$m} {$y}";
}

$server_upload_max = php_size_to_bytes((string)ini_get('upload_max_filesize'));
$server_post_max   = php_size_to_bytes((string)ini_get('post_max_size'));
$server_limit      = min($server_upload_max, $server_post_max);

$max_image_bytes = min(2 * 1024 * 1024, $server_limit);
$max_doc_bytes   = min(5 * 1024 * 1024, $server_limit);
$max_video_bytes = min(8 * 1024 * 1024, $server_limit);

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

// 3. เพิ่มเพื่อนในระบบแชท (รองรับผู้ใช้งานทุกคนทั้ง 2 ฝั่ง)
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

// 5. ส่งข้อความที่พิมพ์เอง / สติกเกอร์ / รูปภาพ / วิดีโอ / เอกสาร (รองรับผู้ใช้งานทุกคนทั้ง 2 ฝั่ง)
$active_friend_id = (int)($_GET['friend_id'] ?? ($_POST['friend_id'] ?? 0));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['send_chat']) || isset($_POST['send_sticker']) || isset($_POST['message_text']))) {
    check_auth_token();
    $friend_id = (int)($_POST['friend_id'] ?? 0);

    // ตรวจสอบว่าเป็นเพื่อนกันหรือยัง ถ้ายังให้ผูกคู่สนทนาให้อัตโนมัติหากมีผู้ใช้นี้จริง
    $is_friend = false;
    if ($friend_id > 0 && $friend_id !== $user_id) {
        $fchk = $conn->prepare("SELECT id FROM adopter_friends WHERE user_id = ? AND friend_id = ? LIMIT 1");
        $fchk->bind_param("ii", $user_id, $friend_id);
        $fchk->execute();
        $is_friend = (bool)$fchk->get_result()->fetch_assoc();
        $fchk->close();

        if (!$is_friend) {
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
    }

    if (!$is_friend) {
        $error = '⚠️ กรุณาเลือกเพื่อนก่อนเริ่มส่งข้อความ';
    } elseif (isset($_POST['send_sticker'])) {
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
                'type'  => 'image',
                'exts'  => ['jpg', 'jpeg', 'png'],
                'max'   => $max_image_bytes,
                'label' => 'รูปภาพ'
            ],
            'chat_video' => [
                'type'  => 'video',
                'exts'  => ['mp4', 'webm', 'mov'],
                'max'   => $max_video_bytes,
                'label' => 'วิดีโอ'
            ],
            'chat_doc'   => [
                'type'  => 'file',
                'exts'  => ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'txt', 'zip'],
                'max'   => $max_doc_bytes,
                'label' => 'ไฟล์เอกสาร'
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
            $stmt->execute();
            $stmt->close();
            header("Location: adopter.php?view=friends&friend_id=" . $friend_id);
            exit();
        } elseif ($error === '' && $uploaded_count === 0 && $msg_text === '') {
            $error = '⚠️ กรุณาพิมพ์ข้อความหรือเลือกไฟล์ก่อนกดส่งค่ะ';
        }
    }
}

// กำหนดโหมดการแสดงผลหน้าเว็บ (แยกหน้า PDPA กับหน้าแชทชัดเจน ไม่ซ้อนทับกัน)
$requested_view = $_GET['view'] ?? '';
$requested_step = $_GET['step'] ?? '';

if (isset($_GET['submitted'])) {
    $step = 'done';
} elseif ($requested_view === 'friends' || isset($_GET['friend_id']) || isset($_GET['q'])) {
    $step = 'friends';
} elseif ($existing_app) {
    $step = 'rules';
} elseif (!empty($_SESSION['adopter_pdpa_ok']) && $requested_step === 'form') {
    $step = 'form';
} else {
    $step = 'pdpa';
}

// โหลดข้อมูลสำหรับหน้าแชท/ค้นหาเพื่อน
$friends_list      = [];
$search_query      = trim((string)($_GET['q'] ?? ''));
$search_results    = [];
$active_friend     = null;
$chat_messages     = [];
$confirm_unsend_id = (int)($_GET['confirm_unsend'] ?? 0);
$view_media_id     = (int)($_GET['view_media'] ?? 0);
$active_media_item = null;

if ($step === 'friends') {
    $fl_stmt = $conn->prepare("SELECT u.id, u.full_name, u.nickname, u.avatar
        FROM adopter_friends af
        JOIN users u ON u.id = af.friend_id
        WHERE af.user_id = ?
        ORDER BY af.id DESC, u.full_name ASC");
    $fl_stmt->bind_param("i", $user_id);
    $fl_stmt->execute();
    $friends_list = $fl_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $fl_stmt->close();

    if ($search_query !== '') {
        $like = '%' . $search_query . '%';
        $sq_stmt = $conn->prepare("SELECT u.id, u.full_name, u.nickname, u.avatar,
            EXISTS(SELECT 1 FROM adopter_friends af WHERE af.user_id = ? AND af.friend_id = u.id) AS is_friend
            FROM users u
            WHERE u.id != ? AND (u.full_name LIKE ? OR u.nickname LIKE ?)
            ORDER BY u.full_name ASC
            LIMIT 20");
        $sq_stmt->bind_param("iiss", $user_id, $user_id, $like, $like);
        $sq_stmt->execute();
        $search_results = $sq_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $sq_stmt->close();
    }

    if ($active_friend_id <= 0 && !empty($friends_list)) {
        $active_friend_id = (int)$friends_list[0]['id'];
    }

    if ($active_friend_id > 0) {
        foreach ($friends_list as $fr) {
            if ((int)$fr['id'] === $active_friend_id) {
                $active_friend = $fr;
                break;
            }
        }
        if (!$active_friend && $active_friend_id !== $user_id) {
            $df_stmt = $conn->prepare("SELECT id, full_name, nickname, avatar FROM users WHERE id = ? LIMIT 1");
            $df_stmt->bind_param("i", $active_friend_id);
            $df_stmt->execute();
            $direct_user = $df_stmt->get_result()->fetch_assoc();
            $df_stmt->close();
            if ($direct_user) {
                $active_friend = $direct_user;
                array_unshift($friends_list, $direct_user);
            }
        }

        if ($active_friend) {
            $m_stmt = $conn->prepare("SELECT m.*, u.full_name AS sender_name
                FROM adopter_messages m
                JOIN users u ON u.id = m.sender_id
                WHERE (m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?)
                ORDER BY m.created_at ASC, m.id ASC");
            $m_stmt->bind_param("iiii", $user_id, $active_friend_id, $active_friend_id, $user_id);
            $m_stmt->execute();
            $chat_messages = $m_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $m_stmt->close();

            if ($view_media_id > 0) {
                foreach ($chat_messages as $cm) {
                    if ((int)$cm['id'] === $view_media_id && empty($cm['is_unsent']) && in_array($cm['attachment_type'], ['image', 'video'], true) && !empty($cm['attachment_path'])) {
                        $active_media_item = $cm;
                        break;
                    }
                }
            }
        }
    }
}

$th_months = [
    1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
    5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
    9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
];
$app_fullname = trim(($existing_app['firstname'] ?? '') . ' ' . ($existing_app['lastname'] ?? ''));
$app_status_raw = $existing_app['application_status'] ?? '';
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

        /* แถบพิมพ์ข้อความด้านล่าง */
        .chat-compose { padding: 12px 14px; border-top: 1px solid #ffd6e0; background: #fff; position: relative; z-index: 5; }
        .compose-row { display: flex; align-items: center; gap: 8px; }
        .compose-main-form { display: flex; align-items: center; gap: 8px; flex: 1; min-width: 0; margin: 0; }
        .compose-input { flex: 1 1 auto; width: 100%; min-width: 120px; padding: 10px 16px; border: 1.5px solid #ff9ebb; border-radius: 22px; font-family: 'Kanit', sans-serif; font-size: 14px; outline: none; background: #fff; color: #333; }
        .compose-input:focus { border-color: #d81b60; box-shadow: 0 0 0 3px rgba(216,27,96,0.1); }
        .icon-upload-label { flex-shrink: 0; display: inline-flex; align-items: center; justify-content: center; width: 38px; height: 38px; border-radius: 50%; background: #fff0f5; border: 1px solid #ffccd5; cursor: pointer; font-size: 17px; position: relative; overflow: hidden; }
        .icon-upload-label:hover { background: #ffe0ea; }
        .icon-upload-label input[type="file"] { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
        .sticker-picker { position: relative; flex-shrink: 0; }
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
            .compose-row, .compose-main-form { flex-wrap: wrap; }
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
                    <div style="margin-top:18px; display:flex; gap:10px; justify-content:center; flex-wrap:wrap;">
                        <a href="adopter.php" style="display:inline-block; background:#d81b60; color:#fff; padding:10px 26px; border-radius:20px; text-decoration:none; font-weight:600;">เข้าสู่หน้าผู้รับเลี้ยง (Adopter) →</a>
                        <a href="adopter.php?view=friends" style="display:inline-block; background:#2e7d32; color:#fff; padding:10px 26px; border-radius:20px; text-decoration:none; font-weight:600;">💬 ไปที่ห้องแชท →</a>
                    </div>
                </div>
            </div>

        <?php elseif ($step === 'pdpa'): ?>
            <div class="status-banner" style="background:#e8f5e9; border-color:#a5d6a7;">
                <div>
                    <strong>ต้องการพูดคุยหรือตอบแชทกับผู้รับเลี้ยง/ผู้ประกาศ?</strong> (สามารถเข้าใช้งานห้องแชทได้ทันทีโดยไม่ต้องสมัครผู้รับเลี้ยง)
                </div>
                <a href="adopter.php?view=friends" style="background:#2e7d32; color:#fff; padding:7px 18px; border-radius:18px; text-decoration:none; font-weight:600;">💬 เข้าสู่ห้องแชททันที →</a>
            </div>

            <div class="card-box">
                <h2 class="reg-title" style="color:#d81b60;">🔒 ประกาศความเป็นส่วนตัว (Privacy Notice)</h2>
                <p class="reg-sub">ตามพระราชบัญญัติคุ้มครองข้อมูลส่วนบุคคล พ.ศ. 2562 (PDPA)</p>
                <div class="pdpa-scroll">
                    <p><strong>เว็บไซต์ Find my meow meow ♡</strong> ให้ความสำคัญกับความเป็นส่วนตัวและการคุ้มครองข้อมูลส่วนบุคคลของผู้สมัครเป็นผู้รับเลี้ยงแมว โดยมีรายละเอียดดังต่อไปนี้</p>
                    <h4>1. วัตถุประสงค์ในการเก็บรวบรวมข้อมูล</h4>
                    <p>เว็บไซต์เก็บรวบรวมข้อมูลส่วนบุคคลของผู้สมัคร เช่น ชื่อ-นามสกุล วันเดือนปีเกิด อาชีพ ประสบการณ์ในการเลี้ยงแมว ประเภทที่พักอาศัย สถานะทางการเงิน และข้อมูลอื่น ๆ ที่เกี่ยวข้อง เพื่อนำมาใช้ในการตรวจสอบความน่าเชื่อถือ ประเมินความพร้อม และพิจารณาคุณสมบัติของผู้สมัครก่อนอนุมัติให้เป็นผู้รับเลี้ยงแมวเท่านั้น</p>
                    <h4>2. การนำข้อมูลไปใช้</h4>
                    <p>ข้อมูลที่ได้รับจะถูกนำไปใช้เพื่อประกอบการพิจารณาการสมัครเป็นผู้รับเลี้ยงแมว และใช้ในการติดต่อผู้สมัครเมื่อมีความจำเป็น โดยจะไม่นำข้อมูลไปใช้เพื่อวัตถุประสงค์ที่ไม่เกี่ยวข้องกับการให้บริการ หรือเปิดเผยแก่บุคคลอื่นโดยไม่มีเหตุอันเหมาะสมหรือฐานทางกฎหมายรองรับ</p>
                    <h4>3. การรักษาความปลอดภัยของข้อมูล</h4>
                    <p>เว็บไซต์จะดูแลและจัดเก็บข้อมูลส่วนบุคคลอย่างเหมาะสม โดยจำกัดการเข้าถึงข้อมูลเฉพาะผู้ที่มีหน้าที่เกี่ยวข้องกับการตรวจสอบและพิจารณาการสมัคร เพื่อป้องกันการเข้าถึง การเปิดเผย หรือการนำข้อมูลไปใช้โดยไม่ได้รับอนุญาต</p>
                    <h4>4. ระยะเวลาในการจัดเก็บข้อมูล</h4>
                    <p>เว็บไซต์จะจัดเก็บข้อมูลไว้เท่าที่จำเป็นต่อวัตถุประสงค์ในการสมัครและพิจารณาการรับเลี้ยงแมว เมื่อพ้นระยะเวลาที่จำเป็นหรือไม่มีความจำเป็นต้องใช้ข้อมูลแล้ว จะดำเนินการลบ ทำลาย หรือทำให้ข้อมูลไม่สามารถระบุตัวบุคคลได้ตามความเหมาะสมและข้อกำหนดทางกฎหมาย</p>
                    <h4>5. สิทธิของเจ้าของข้อมูล</h4>
                    <p>ผู้สมัครสามารถติดต่อผู้ดูแลเว็บไซต์เพื่อสอบถามเกี่ยวกับข้อมูลส่วนบุคคล ขอแก้ไขข้อมูลที่ไม่ถูกต้อง หรือใช้สิทธิอื่น ๆ ตามที่กฎหมายคุ้มครองข้อมูลส่วนบุคคลกำหนด</p>
                </div>

                <form method="POST" action="adopter.php">
                    <input type="hidden" name="auth_csrf" value="<?= auth_escape(auth_token()) ?>">
                    <div class="green-check-box">
                        <strong style="display:block; margin-bottom:10px; color:#1b5e20;">✅ การยืนยันและรับทราบ</strong>
                        <label class="green-check-item">
                            <input type="checkbox" name="pdpa_read" value="1" required>
                            <span>ข้าพเจ้าได้อ่านและรับทราบประกาศความเป็นส่วนตัว (Privacy Notice) รวมถึงเข้าใจวัตถุประสงค์ในการเก็บรวบรวมและใช้ข้อมูลส่วนบุคคลเพื่อประกอบการพิจารณาสมัครเป็นผู้รับเลี้ยงแมวแล้ว</span>
                        </label>
                        <label class="green-check-item">
                            <input type="checkbox" name="pdpa_consent" value="1" required>
                            <span>ข้าพเจ้าขอยืนยันว่าข้อมูลที่ให้ไว้เป็นความจริง และยินยอมให้ใช้ข้อมูลตามวัตถุประสงค์ที่แจ้งไว้</span>
                        </label>
                    </div>
                    <button type="submit" name="accept_pdpa" class="btn-primary">✔ ยืนยันและไปหน้าแบบฟอร์มผู้สมัคร →</button>
                </form>
            </div>

        <?php elseif ($step === 'form'): ?>
            <div class="card-box">
                <h2 class="reg-title">สมัครเป็นผู้รับเลี้ยงแมว</h2>
                <p class="reg-sub">Adopter Registration Form — กรุณากรอกข้อมูลเพื่อประกอบการพิจารณารับเลี้ยงแมว</p>
                <form method="POST" action="adopter.php?step=form">
                    <input type="hidden" name="auth_csrf" value="<?= auth_escape(auth_token()) ?>">
                    <div class="section-box">
                        <div class="section-title">ส่วนที่ 1 ข้อมูลส่วนตัว *</div>
                        <label class="form-label">ชื่อ *</label>
                        <input type="text" name="firstname" class="form-control" placeholder="กรอกชื่อจริง" required>
                        <label class="form-label">นามสกุล *</label>
                        <input type="text" name="lastname" class="form-control" placeholder="กรอกนามสกุล" required>
                        <label class="form-label">วันเกิด *</label>
                        <div class="dob-grid">
                            <select name="b_day" class="form-control" required>
                                <option value="">วัน</option>
                                <?php for ($d = 1; $d <= 31; $d++) echo "<option value='{$d}'>{$d}</option>"; ?>
                            </select>
                            <select name="b_month" class="form-control" required>
                                <option value="">เดือน</option>
                                <?php foreach ($th_months as $mn => $mname) echo "<option value='{$mn}'>{$mname}</option>"; ?>
                            </select>
                            <select name="b_year" class="form-control" required>
                                <option value="">ปี (พ.ศ.)</option>
                                <?php for ($y = (int)date('Y') + 543; $y >= 2480; $y--) echo "<option value='{$y}'>{$y}</option>"; ?>
                            </select>
                        </div>
                        <label class="form-label">อาชีพ *</label>
                        <select name="occupation" class="form-control" required>
                            <option value="">เลือกอาชีพ</option>
                            <option value="นักเรียน / นักศึกษา">นักเรียน / นักศึกษา</option>
                            <option value="ข้าราชการ / พนักงานรัฐวิสาหกิจ">ข้าราชการ / พนักงานรัฐวิสาหกิจ</option>
                            <option value="พนักงานบริษัทเอกชน">พนักงานบริษัทเอกชน</option>
                            <option value="ธุรกิจส่วนตัว / ค้าขาย">ธุรกิจส่วนตัว / ค้าขาย</option>
                            <option value="ฟรีแลนซ์ / อาชีพอิสระ">ฟรีแลนซ์ / อาชีพอิสระ</option>
                            <option value="ว่างงาน">ว่างงาน</option>
                            <option value="อื่นๆ">อื่นๆ (โปรดระบุด้านล่าง)</option>
                        </select>
                        <input type="text" name="occupation_other" class="form-control" placeholder="ระบุอาชีพอื่นๆ (ถ้าเลือก อื่นๆ)">
                    </div>

                    <div class="section-box">
                        <div class="section-title">ส่วนที่ 2 ประสบการณ์ในการเลี้ยงแมว *</div>
                        <label class="form-label">เคยเลี้ยงแมวมาก่อนหรือไม่ *</label>
                        <div class="radio-group">
                            <label><input type="radio" name="had_cat_before" value="เคย" required> เคย</label>
                            <label><input type="radio" name="had_cat_before" value="ไม่เคย" checked> ไม่เคย</label>
                        </div>
                        <label class="form-label">ปัจจุบันมีสัตว์เลี้ยงหรือไม่ *</label>
                        <div class="radio-group">
                            <label><input type="radio" name="has_current_pets" value="มี" required> มี</label>
                            <label><input type="radio" name="has_current_pets" value="ไม่มี" checked> ไม่มี</label>
                        </div>
                    </div>

                    <div class="section-box">
                        <div class="section-title">ส่วนที่ 3 ที่พักอาศัยและการเงิน *</div>
                        <label class="form-label">ประเภทที่พักอาศัย *</label>
                        <select name="accommodation_type" class="form-control" required>
                            <option value="">เลือกประเภทที่พักอาศัย</option>
                            <option value="บ้านเดี่ยว">บ้านเดี่ยว</option>
                            <option value="ทาวน์โฮม / ทาวน์เฮาส์">ทาวน์โฮม / ทาวน์เฮาส์</option>
                            <option value="คอนโดมิเนียม / อพาร์ตเมนต์">คอนโดมิเนียม / อพาร์ตเมนต์</option>
                            <option value="หอพัก / ห้องเช่า">หอพัก / ห้องเช่า</option>
                            <option value="อื่นๆ">อื่นๆ (โปรดระบุด้านล่าง)</option>
                        </select>
                        <input type="text" name="accommodation_other" class="form-control" placeholder="ระบุที่พักอื่นๆ (ถ้าเลือก อื่นๆ)">

                        <label class="form-label">สถานที่พักอนุญาตให้เลี้ยงสัตว์หรือไม่ *</label>
                        <div class="radio-group">
                            <label><input type="radio" name="pet_allowed" value="อนุญาต" required> อนุญาต</label>
                            <label><input type="radio" name="pet_allowed" value="ไม่อนุญาต"> ไม่อนุญาต</label>
                            <label><input type="radio" name="pet_allowed" value="ไม่แน่ใจ" checked> ไม่แน่ใจ</label>
                        </div>

                        <label class="form-label">สถานะทางการเงิน / รายได้ต่อเดือน *</label>
                        <select name="financial_status" class="form-control" required>
                            <option value="">เลือกระดับรายได้</option>
                            <option value="ไม่มีรายได้ / ผู้ปกครองดูแล">ไม่มีรายได้ / ผู้ปกครองดูแล</option>
                            <option value="ต่ำกว่า 15,000 บาท">ต่ำกว่า 15,000 บาท</option>
                            <option value="15,000 - 30,000 บาท">15,000 - 30,000 บาท</option>
                            <option value="30,001 - 50,000 บาท">30,001 - 50,000 บาท</option>
                            <option value="มากกว่า 50,000 บาท">มากกว่า 50,000 บาท</option>
                            <option value="อื่นๆ">อื่นๆ (โปรดระบุด้านล่าง)</option>
                        </select>
                        <input type="text" name="financial_other" class="form-control" placeholder="ระบุสถานะทางการเงินอื่นๆ (ถ้าเลือก อื่นๆ)">
                    </div>

                    <div class="section-box">
                        <div class="section-title">ส่วนที่ 4 สุขภาพและการยืนยันความจริง *</div>
                        <label class="form-label">มีโรคประจำตัวหรือภูมิแพ้ขนสัตว์หรือไม่ *</label>
                        <div class="radio-group">
                            <label><input type="radio" name="has_chronic_disease" value="มี" required> มี</label>
                            <label><input type="radio" name="has_chronic_disease" value="ไม่มี" checked> ไม่มี</label>
                        </div>
                        <input type="text" name="health_details" class="form-control" placeholder="รายละเอียดเพิ่มเติม (ถ้ามี)">
                        <div class="green-check-box">
                            <label class="green-check-item">
                                <input type="checkbox" name="truth_confirm" value="1" required>
                                <span>ข้าพเจ้าขอยืนยันว่าข้อมูลที่กรอกทั้งหมดเป็นความจริงทุกประการ</span>
                            </label>
                        </div>
                    </div>

                    <button type="submit" name="submit_adopter_form" class="btn-primary">🐾 ส่งใบสมัครเป็นผู้รับเลี้ยงแมว</button>
                </form>
            </div>

        <?php elseif ($step === 'rules'): ?>
            <div class="status-banner">
                <div>
                    <strong>สถานะผู้รับเลี้ยงของคุณ (<?= htmlspecialchars($app_fullname ?: $my_display_name, ENT_QUOTES, 'UTF-8') ?>):</strong>
                    <?= htmlspecialchars($app_status_label, ENT_QUOTES, 'UTF-8') ?>
                </div>
                <a href="buy_cat.php" style="color:#d81b60; text-decoration:none; font-weight:600;">🐾 ดูประกาศแมวหาบ้าน →</a>
            </div>

            <div class="card-box">
                <h2 class="reg-title" style="color:#d81b60;">📢 ประกาศสมัครเป็นผู้รับเลี้ยงแมว</h2>
                <div class="rules-grid">
                    <div class="rules-col">
                        <h3 style="margin-top:0; color:#d81b60;">คุณสมบัติของผู้สมัครเป็นผู้รับเลี้ยงแมว</h3>
                        <p><strong>1. ด้านอายุ:</strong> อายุ 18 ปีขึ้นไป หรือผู้ที่อายุต่ำกว่า 18 ปีสามารถสมัครได้ โดยต้องได้รับความยินยอมจากผู้ปกครอง</p>
                        <p><strong>2. ด้านความรับผิดชอบ:</strong> มีความรับผิดชอบและพร้อมดูแลแมวอย่างต่อเนื่อง ไม่ทอดทิ้งหรือปล่อยปละละเลย</p>
                        <p><strong>3. ด้านความพร้อม:</strong> มีเวลา สถานที่ที่เหมาะสม และสามารถรับผิดชอบค่าใช้จ่ายในการเลี้ยงดูแมวได้</p>
                    </div>
                    <div class="rules-col">
                        <h3 style="margin-top:0; color:#1976d2;">จะรู้ได้อย่างไร ว่าได้รับคัดเลือกให้เป็นผู้รับเลี้ยงแมว?</h3>
                        <p>1. ผู้ประกาศจะดูจากคุณสมบัติของผู้สมัคร ที่กำหนดไว้ข้างต้น</p>
                        <p>2. สถานะด้านบนจะเปลี่ยนเป็น <strong>ผ่านการสมัครเป็นผู้รับเลี้ยงแมว (Approved)</strong></p>
                    </div>
                </div>
                <div style="text-align:right; margin-top:20px;">
                    <a href="adopter.php?view=friends" style="display:inline-block; background:#d81b60; color:#fff; padding:11px 28px; border-radius:22px; text-decoration:none; font-weight:600;">หน้าถัดไป (เพิ่มเพื่อนและแชท) →</a>
                </div>
            </div>

        <?php else: /* $step === 'friends' */ ?>
            <div class="status-banner">
                <div>
                    <strong>สถานะของคุณ (<?= htmlspecialchars($app_fullname ?: $my_display_name, ENT_QUOTES, 'UTF-8') ?>):</strong>
                    <?= htmlspecialchars($app_status_label, ENT_QUOTES, 'UTF-8') ?>
                </div>
                <?php if ($existing_app): ?>
                    <a href="adopter.php?view=rules" style="color:#d81b60; text-decoration:none; font-weight:600;">← ย้อนกลับไปอ่านเงื่อนไขผู้รับเลี้ยง</a>
                <?php else: ?>
                    <a href="adopter.php" style="color:#d81b60; text-decoration:none; font-weight:600;">📝 สมัครเป็นผู้รับเลี้ยงแมว →</a>
                <?php endif; ?>
            </div>

            <div class="card-box">
                <!-- ช่องค้นหาเพื่อน -->
                <form method="GET" action="adopter.php" class="search-bar">
                    <input type="hidden" name="view" value="friends">
                    <span>🔍</span>
                    <input type="text" name="q" value="<?= htmlspecialchars($search_query, ENT_QUOTES, 'UTF-8') ?>" placeholder="ค้นหาชื่อเพื่อนเพื่อเพิ่มเพื่อนและแชท...">
                    <button type="submit" class="search-btn">ค้นหา</button>
                </form>

                <?php if ($search_query !== ''): ?>
                    <div style="margin-bottom:14px;">
                        <strong style="font-size:14px; color:#d81b60;">ผลการค้นหาสำหรับ "<?= htmlspecialchars($search_query, ENT_QUOTES, 'UTF-8') ?>"</strong>
                        <?php if (empty($search_results)): ?>
                            <p style="font-size:13.5px; color:#777; margin:8px 0;">ไม่พบผู้ใช้งานที่ตรงกับคำค้นหา</p>
                        <?php else: ?>
                            <div class="search-results-grid" style="margin-top:10px;">
                                <?php foreach ($search_results as $sr): ?>
                                    <div class="user-card">
                                        <div class="user-card-left">
                                            <img src="<?= htmlspecialchars(get_avatar_src($sr['avatar'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" alt="avatar" class="avatar-sm">
                                            <div style="min-width:0;">
                                                <div style="font-size:13.5px; font-weight:500; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                                                    <?= htmlspecialchars($sr['full_name'], ENT_QUOTES, 'UTF-8') ?>
                                                </div>
                                            </div>
                                        </div>
                                        <?php if (!empty($sr['is_friend'])): ?>
                                            <a href="adopter.php?view=friends&friend_id=<?= (int)$sr['id'] ?>" class="btn-add-friend" style="background:#2e7d32;">💬 แชท</a>
                                        <?php else: ?>
                                            <form method="POST" action="adopter.php?view=friends" style="margin:0;">
                                                <input type="hidden" name="auth_csrf" value="<?= auth_escape(auth_token()) ?>">
                                                <input type="hidden" name="friend_id" value="<?= (int)$sr['id'] ?>">
                                                <button type="submit" name="add_friend" class="btn-add-friend">+ เพิ่มเพื่อน</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if (empty($friends_list)): ?>
                    <div style="text-align:center; padding:45px 20px; color:#777;">
                        <div style="font-size:48px; margin-bottom:10px;">😺💬</div>
                        <h3 style="color:#d81b60; margin:0 0 8px 0;">ยังไม่มีรายชื่อเพื่อนในแชท</h3>
                        <p style="margin:0; font-size:14px;">พิมพ์ชื่อในช่องค้นหาด้านบน แล้วกดปุ่ม <strong>+ เพิ่มเพื่อน</strong> เพื่อเริ่มพูดคุยได้เลยค่ะ</p>
                    </div>
                <?php else: ?>
                    <div class="chat-shell">
                        <div class="friends-panel">
                            <div style="font-weight:600; color:#d81b60; margin-bottom:10px; font-size:14px;">💬 รายชื่อคู่สนทนาของคุณ</div>
                            <?php foreach ($friends_list as $fr): ?>
                                <?php $is_act = ((int)$fr['id'] === $active_friend_id); ?>
                                <a href="adopter.php?view=friends&friend_id=<?= (int)$fr['id'] ?>" class="friend-item <?= $is_act ? 'active' : '' ?>">
                                    <img src="<?= htmlspecialchars(get_avatar_src($fr['avatar'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" alt="avatar" class="avatar-sm">
                                    <div style="min-width:0;">
                                        <div style="font-size:13.5px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                                            <?= htmlspecialchars($fr['full_name'], ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                        <div style="font-size:11px; color:#2e7d32;">● พร้อมแชท</div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>

                        <?php if ($active_friend): ?>
                            <div class="chat-panel">
                                <div class="chat-header">
                                    <div style="display:flex; align-items:center; gap:10px;">
                                        <img src="<?= htmlspecialchars(get_avatar_src($active_friend['avatar'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" alt="avatar" class="avatar-sm">
                                        <strong><?= htmlspecialchars($active_friend['full_name'], ENT_QUOTES, 'UTF-8') ?></strong>
                                    </div>
                                    <div class="chat-me-badge">
                                        <img src="<?= htmlspecialchars($my_avatar_src, ENT_QUOTES, 'UTF-8') ?>" alt="me" style="width:28px; height:28px; border-radius:50%; object-fit:cover;">
                                        <span><?= htmlspecialchars($my_display_name, ENT_QUOTES, 'UTF-8') ?> (ฉัน)</span>
                                    </div>
                                </div>

                                <?php if ($confirm_unsend_id > 0): ?>
                                    <div class="unsend-confirm-box">
                                        <span>⚠️ <strong>คุณแน่ใจหรือไม่ที่จะยกเลิกข้อความนี้?</strong> ข้อความจะถูกลบออกจากแชท</span>
                                        <div style="display:flex; gap:8px;">
                                            <form method="POST" action="adopter.php?view=friends&friend_id=<?= (int)$active_friend_id ?>" style="margin:0;">
                                                <input type="hidden" name="auth_csrf" value="<?= auth_escape(auth_token()) ?>">
                                                <input type="hidden" name="msg_id" value="<?= $confirm_unsend_id ?>">
                                                <input type="hidden" name="friend_id" value="<?= (int)$active_friend_id ?>">
                                                <button type="submit" name="confirm_unsend_msg" style="background:#d81b60; color:#fff; border:none; padding:5px 12px; border-radius:10px; cursor:pointer; font-family:'Kanit'; font-size:12.5px;">ยืนยันยกเลิกข้อความ</button>
                                            </form>
                                            <a href="adopter.php?view=friends&friend_id=<?= (int)$active_friend_id ?>" style="background:#eee; color:#333; padding:5px 12px; border-radius:10px; text-decoration:none; font-size:12.5px;">ยกเลิก</a>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <div class="chat-messages">
                                    <?php if (empty($chat_messages)): ?>
                                        <div style="text-align:center; color:#999; margin:auto; font-size:13.5px;">
                                            เริ่มพิมพ์ข้อความทักทาย <?= htmlspecialchars($active_friend['full_name'], ENT_QUOTES, 'UTF-8') ?> ได้เลยค่ะ 🐾
                                        </div>
                                    <?php else: ?>
                                        <?php $last_date = ''; ?>
                                        <?php foreach ($chat_messages as $m): ?>
                                            <?php
                                            $cur_date = date('Y-m-d', strtotime($m['created_at']));
                                            if ($cur_date !== $last_date):
                                                $last_date = $cur_date;
                                            ?>
                                                <div class="date-divider"><span><?= htmlspecialchars(format_thai_date($m['created_at']), ENT_QUOTES, 'UTF-8') ?></span></div>
                                            <?php endif; ?>

                                            <?php $is_mine = ((int)$m['sender_id'] === $user_id); ?>
                                            <div class="msg-row <?= $is_mine ? 'mine' : 'theirs' ?>">
                                                <?php if (!empty($m['is_unsent'])): ?>
                                                    <div class="msg-bubble msg-unsent">
                                                        <?= htmlspecialchars($m['sender_name'], ENT_QUOTES, 'UTF-8') ?> ได้ยกเลิกข้อความไปแล้ว
                                                    </div>
                                                <?php else: ?>
                                                    <div class="msg-bubble">
                                                        <?php if ($m['attachment_type'] === 'image' && !empty($m['attachment_path'])): ?>
                                                            <div style="margin-bottom:6px;">
                                                                <a href="adopter.php?view=friends&friend_id=<?= (int)$active_friend_id ?>&view_media=<?= (int)$m['id'] ?>" class="media-preview-link" title="คลิกเพื่อดูรูปขนาดใหญ่">
                                                                    <img src="<?= htmlspecialchars($m['attachment_path'], ENT_QUOTES, 'UTF-8') ?>" alt="image" style="max-width:240px; max-height:220px; border-radius:10px; display:block;">
                                                                    <span class="media-zoom-badge">🔍 ขยายรูป</span>
                                                                </a>
                                                            </div>
                                                        <?php elseif ($m['attachment_type'] === 'video' && !empty($m['attachment_path'])): ?>
                                                            <div style="margin-bottom:6px;">
                                                                <video controls style="max-width:260px; max-height:200px; border-radius:12px; display:block;">
                                                                    <source src="<?= htmlspecialchars($m['attachment_path'], ENT_QUOTES, 'UTF-8') ?>">
                                                                </video>
                                                                <div style="margin-top:4px; text-align:right;">
                                                                    <a href="adopter.php?view=friends&friend_id=<?= (int)$active_friend_id ?>&view_media=<?= (int)$m['id'] ?>" style="color:inherit; font-size:12px; text-decoration:underline;">🔍 เปิดดูวิดีโอเต็มจอ</a>
                                                                </div>
                                                            </div>
                                                        <?php elseif ($m['attachment_type'] === 'file' && !empty($m['attachment_path'])): ?>
                                                            <div style="margin-bottom:6px;">
                                                                <a href="<?= htmlspecialchars($m['attachment_path'], ENT_QUOTES, 'UTF-8') ?>" download style="color:inherit; font-weight:500;">
                                                                    📎 <?= htmlspecialchars($m['attachment_name'] ?? 'ดาวน์โหลดไฟล์', ENT_QUOTES, 'UTF-8') ?>
                                                                </a>
                                                            </div>
                                                        <?php endif; ?>

                                                        <?php if (!empty($m['message_text'])): ?>
                                                            <?= nl2br(htmlspecialchars($m['message_text'], ENT_QUOTES, 'UTF-8')) ?>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>

                                                <div class="msg-meta">
                                                    <span><?= date('H:i', strtotime($m['created_at'])) ?></span>
                                                    <?php if ($is_mine && empty($m['is_unsent'])): ?>
                                                        <a href="adopter.php?view=friends&friend_id=<?= (int)$active_friend_id ?>&confirm_unsend=<?= (int)$m['id'] ?>" class="unsend-link">ยกเลิกข้อความ</a>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>

                                <!-- แถบพิมพ์ข้อความและแนบไฟล์ (แยกฟอร์มสติกเกอร์ออก เพื่อไม่ให้กด Enter แล้วส่งสติกเกอร์แทน) -->
                                <div class="chat-compose">
                                    <div class="compose-row">
                                        <form method="POST" action="adopter.php?view=friends&friend_id=<?= (int)$active_friend_id ?>" enctype="multipart/form-data" class="compose-main-form">
                                            <input type="hidden" name="auth_csrf" value="<?= auth_escape(auth_token()) ?>">
                                            <input type="hidden" name="friend_id" value="<?= (int)$active_friend_id ?>">

                                            <!-- 1. ไอคอนคลิปหนีบกระดาษ (ไฟล์เอกสาร) -->
                                            <label class="icon-upload-label" title="แนบไฟล์เอกสาร (สูงสุด <?= htmlspecialchars(format_mb_label($max_doc_bytes), ENT_QUOTES, 'UTF-8') ?>)">
                                                📎
                                                <input type="file" name="chat_doc" accept=".pdf,.doc,.docx,.ppt,.pptx,.txt,.zip">
                                            </label>

                                            <!-- 2. ไอคอนแกลลอรี่ (เฉพาะ .jpg, .jpeg, .png) -->
                                            <label class="icon-upload-label" title="แนบรูปภาพ .jpg, .jpeg, .png (สูงสุด <?= htmlspecialchars(format_mb_label($max_image_bytes), ENT_QUOTES, 'UTF-8') ?>)">
                                                🖼️
                                                <input type="file" name="chat_image" accept=".jpg,.jpeg,.png,image/jpeg,image/png">
                                            </label>

                                            <!-- 3. ไอคอนเครื่องถ่ายวิดีโอ -->
                                            <label class="icon-upload-label" title="แนบวิดีโอ .mp4, .webm, .mov (สูงสุด <?= htmlspecialchars(format_mb_label($max_video_bytes), ENT_QUOTES, 'UTF-8') ?>)">
                                                🎞️
                                                <input type="file" name="chat_video" accept=".mp4,.webm,.mov,video/mp4,video/webm,video/quicktime">
                                            </label>

                                            <!-- ช่องพิมพ์ข้อความอะไรก็ได้ -->
                                            <input type="text" name="message_text" class="compose-input" placeholder="พิมพ์ข้อความอะไรก็ได้ที่นี่..." autocomplete="off">

                                            <!-- ปุ่มส่งข้อความ -->
                                            <button type="submit" name="send_chat" value="1" class="btn-send">ส่ง ✈️</button>
                                        </form>

                                        <!-- ฟอร์มสติกเกอร์แยกต่างหาก -->
                                        <form method="POST" action="adopter.php?view=friends&friend_id=<?= (int)$active_friend_id ?>" style="margin:0; flex-shrink:0;">
                                            <input type="hidden" name="auth_csrf" value="<?= auth_escape(auth_token()) ?>">
                                            <input type="hidden" name="friend_id" value="<?= (int)$active_friend_id ?>">
                                            <details class="sticker-picker">
                                                <summary title="ส่งสติกเกอร์">😸</summary>
                                                <div class="sticker-menu">
                                                    <button type="submit" name="send_sticker" value="😸 ยินดีที่ได้คุยกันนะเมี้ยว!" class="sticker-btn">😸 ยินดีที่ได้คุยกันนะเมี้ยว!</button>
                                                    <button type="submit" name="send_sticker" value="😻 น้องแมวน่ารักมากเลย!" class="sticker-btn">😻 น้องแมวน่ารักมากเลย!</button>
                                                    <button type="submit" name="send_sticker" value="🐾 รอรับเลี้ยงน้องอยู่นะคะ" class="sticker-btn">🐾 รอรับเลี้ยงน้องอยู่นะคะ</button>
                                                    <button type="submit" name="send_sticker" value="💖 ขอบคุณมากๆ ค่ะ" class="sticker-btn">💖 ขอบคุณมากๆ ค่ะ</button>
                                                    <button type="submit" name="send_sticker" value="😿 ขอสอบถามรายละเอียดเพิ่มเติม" class="sticker-btn">😿 ขอสอบถามรายละเอียดเพิ่มเติม</button>
                                                    <button type="submit" name="send_sticker" value="🙀 จริงเหรอเนี่ย!" class="sticker-btn">🙀 จริงเหรอเนี่ย!</button>
                                                </div>
                                            </details>
                                        </form>
                                    </div>

                                    <div class="limit-hint">
                                        * ขนาดไฟล์อัปโหลดตามค่าเซิร์ฟเวอร์: รูปภาพ (.jpg, .jpeg, .png) ≤ <?= htmlspecialchars(format_mb_label($max_image_bytes), ENT_QUOTES, 'UTF-8') ?> |
                                        เอกสาร ≤ <?= htmlspecialchars(format_mb_label($max_doc_bytes), ENT_QUOTES, 'UTF-8') ?> |
                                        วิดีโอ ≤ <?= htmlspecialchars(format_mb_label($max_video_bytes), ENT_QUOTES, 'UTF-8') ?>
                                        (หากเลือกไฟล์แนบ ให้กดปุ่ม <strong>ส่ง ✈️</strong> เพื่ออัปโหลด)
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </main>
</div>

<!-- หน้าต่างแสดงรูปภาพ / วิดีโอขนาดใหญ่ พร้อมปุ่มกากบาท (✕) ปิดหน้าต่าง (HTML + CSS ล้วน) -->
<?php if ($active_media_item): ?>
    <div class="media-lightbox-overlay">
        <div class="media-lightbox-box">
            <a href="adopter.php?view=friends&friend_id=<?= (int)$active_friend_id ?>" class="media-lightbox-close" title="ปิดหน้าต่าง">✕</a>
            <?php if ($active_media_item['attachment_type'] === 'image'): ?>
                <img src="<?= htmlspecialchars($active_media_item['attachment_path'], ENT_QUOTES, 'UTF-8') ?>" alt="รูปภาพขนาดใหญ่" class="media-lightbox-content">
            <?php elseif ($active_media_item['attachment_type'] === 'video'): ?>
                <video controls autoplay class="media-lightbox-content">
                    <source src="<?= htmlspecialchars($active_media_item['attachment_path'], ENT_QUOTES, 'UTF-8') ?>">
                </video>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

</body>
</html>