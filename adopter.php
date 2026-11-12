<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
date_default_timezone_set('Asia/Bangkok');

require_once 'auth.php';
require_once 'config/db.php';
require_login();

$user_id = (int)$_SESSION['user_id'];
$error = '';

// ตรวจสอบว่าเคยสมัครในตาราง adopter เดิมแล้วหรือยัง
$chk = $conn->prepare("SELECT * FROM adopter WHERE user_id = ? LIMIT 1");
$chk->bind_param("i", $user_id);
$chk->execute();
$existing_app = $chk->get_result()->fetch_assoc();
$chk->close();

// ขั้นตอนที่ 1: ยืนยัน PDPA Privacy Notice
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accept_pdpa'])) {
    check_auth_token();
    if (!empty($_POST['pdpa_read']) && !empty($_POST['pdpa_consent'])) {
        $_SESSION['adopter_pdpa_ok'] = true;
        header("Location: adopter_register.php?step=form");
        exit();
    } else {
        $error = '⚠️ กรุณากดติ๊กเครื่องหมายถูกทั้ง 2 ช่องเพื่อรับทราบและยินยอมประกาศความเป็นส่วนตัว (PDPA) ก่อนดำเนินการต่อ';
    }
}

// ขั้นตอนที่ 2: บันทึกแบบฟอร์มสมัครเป็นผู้รับเลี้ยงแมวลงตาราง adopter เดิม
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_adopter_form'])) {
    check_auth_token();
    if (empty($_SESSION['adopter_pdpa_ok'])) {
        header("Location: adopter_register.php");
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
            header("Location: adopter_register.php?submitted=1");
            exit();
        } else {
            $error = '❌ เกิดข้อผิดพลาดในการบันทึกข้อมูล: ' . $conn->error;
        }
        $ins->close();
    }
}

$step = (!empty($_SESSION['adopter_pdpa_ok']) && ($_GET['step'] ?? '') === 'form') ? 'form' : 'pdpa';
if (isset($_GET['submitted']) || $existing_app) {
    $step = 'done';
}

$th_months = [1=>'มกราคม',2=>'กุมภาพันธ์',3=>'มีนาคม',4=>'เมษายน',5=>'พฤษภาคม',6=>'มิถุนายน',7=>'กรกฎาคม',8=>'สิงหาคม',9=>'กันยายน',10=>'ตุลาคม',11=>'พฤศจิกายน',12=>'ธันวาคม'];
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>สมัครเป็นผู้รับเลี้ยงแมว - Find my meow meow 💖</title>
    <link rel="stylesheet" href="assets/css/style.css?v=2">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #fff0f3; font-family: 'Kanit', sans-serif; color: #333; padding: 25px 15px; }
        .reg-container { max-width: 780px; margin: 0 auto; background: #ffffff; border-radius: 24px; padding: 32px; border: 1px solid #ffccd5; box-shadow: 0 8px 25px rgba(255,117,140,0.08); }
        .reg-title { text-align: center; color: #1976d2; margin: 0 0 4px 0; font-size: 24px; }
        .reg-sub { text-align: center; color: #777; font-size: 13.5px; margin: 0 0 22px 0; }
        .pdpa-scroll { max-height: 330px; overflow-y: auto; background: #fff8f9; border: 1px solid #ffccd5; border-radius: 16px; padding: 20px; font-size: 13.5px; line-height: 1.7; margin-bottom: 18px; }
        .pdpa-scroll h4 { color: #d81b60; margin: 12px 0 4px 0; }
        .section-box { border-top: 1px solid #eee; padding-top: 18px; margin-top: 18px; }
        .section-title { font-size: 16px; font-weight: 600; color: #222; margin: 0 0 14px 0; }
        .form-label { display: block; font-size: 13.5px; font-weight: 500; color: #444; margin-bottom: 6px; }
        .form-control { width: 100%; padding: 11px 16px; border: 1px solid #dcdcdc; border-radius: 24px; font-family: 'Kanit', sans-serif; font-size: 14px; outline: none; background: #fff; margin-bottom: 12px; }
        .form-control:focus { border-color: #ff758c; }
        .dob-grid { display: grid; grid-template-columns: 1fr 1.5fr 1fr; gap: 10px; }
        .other-row { display: flex; gap: 10px; align-items: center; margin-bottom: 12px; }
        .other-row input { flex: 1; padding: 9px 14px; border: 1px solid #dcdcdc; border-radius: 20px; font-family: 'Kanit'; font-size: 13.5px; }
        .radio-group { display: flex; gap: 22px; margin-bottom: 14px; align-items: center; flex-wrap: wrap; }
        .radio-group label { display: inline-flex; align-items: center; gap: 8px; cursor: pointer; font-size: 14px; }
        .green-check-box { background: #f8fff8; border: 1px solid #a5d6a7; border-radius: 16px; padding: 16px 18px; margin-top: 16px; }
        .green-check-item { display: flex; align-items: flex-start; gap: 10px; margin-bottom: 10px; font-size: 13.5px; cursor: pointer; }
        .green-check-item input[type="checkbox"] { accent-color: #2e7d32; width: 18px; height: 18px; margin-top: 2px; cursor: pointer; }
        .btn-primary { width: 100%; background: #d81b60; color: white; border: none; padding: 13px; border-radius: 24px; font-size: 15.5px; font-weight: 600; cursor: pointer; font-family: 'Kanit', sans-serif; margin-top: 14px; }
        .btn-primary:hover { background: #ad1457; }
        .alert-err { background: #ffebee; color: #b71c1c; border: 1px solid #ef9a9a; padding: 12px 16px; border-radius: 14px; margin-bottom: 16px; font-size: 13.5px; }
        .alert-ok { background: #e8f5e9; color: #1b5e20; border: 1px solid #a5d6a7; padding: 20px; border-radius: 16px; text-align: center; font-size: 15px; line-height: 1.7; }
        .qual-box { background: #f5f9ff; border: 1px solid #bbdefb; border-radius: 16px; padding: 16px 20px; font-size: 13px; line-height: 1.65; margin-bottom: 18px; }
        @media (max-width: 600px) { .dob-grid { grid-template-columns: 1fr; } .reg-container { padding: 20px 16px; } }
    </style>
</head>
<body>
<div class="reg-container">
    <a href="index.php" style="color:#d81b60; text-decoration:none; font-size:13.5px; font-weight:600;">← กลับหน้าแรก</a>

    <?php if ($error): ?>
        <div class="alert-err" style="margin-top:12px;"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if ($step === 'done'): ?>
        <div class="alert-ok" style="margin-top:20px;">
            <div style="font-size:40px; margin-bottom:8px;">💖🐾</div>
            <strong>สมัครเป็นผู้รับเลี้ยงเรียบร้อยแล้ว โปรดรอการพิจารณาจากผู้ประกาศ/ผู้ดูแลระบบอีกครั้ง</strong><br>
            <span style="font-size:13px; color:#2e7d32;">สถานะคำขอปัจจุบัน: <?= htmlspecialchars($existing_app['application_status'] ?? 'pending', ENT_QUOTES, 'UTF-8') ?></span>
            <div style="margin-top:18px;">
                <a href="adopter.php" style="display:inline-block; background:#d81b60; color:white; padding:10px 26px; border-radius:20px; text-decoration:none; font-weight:600;">เข้าสู่หน้าผู้รับเลี้ยง (Adopter) →</a>
            </div>
        </div>

    <?php elseif ($step === 'pdpa'): ?>
        <h2 class="reg-title" style="color:#d81b60; margin-top:10px;">🔒 ประกาศความเป็นส่วนตัว (Privacy Notice)</h2>
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

        <form method="POST" action="adopter_register.php">
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

    <?php else: ?>
        <h2 class="reg-title">สมัครเป็นผู้รับเลี้ยงแมว</h2>
        <p class="reg-sub">Adopter Registration Form — กรุณากรอกข้อมูลเพื่อประกอบการพิจารณารับเลี้ยงแมว</p>

        <div class="qual-box">
            <p style="margin-top:0;">แบบฟอร์มนี้จัดทำขึ้นเพื่อเปิดรับสมัครบุคคลทั่วไปและผู้ที่มีความสนใจเป็นผู้รับเลี้ยงแมว โดยมีวัตถุประสงค์เพื่อรวบรวมข้อมูลส่วนบุคคลและข้อมูลที่เกี่ยวข้องกับผู้สมัคร สำหรับใช้ในการตรวจสอบความน่าเชื่อถือ ประเมินความพร้อม และพิจารณาคุณสมบัติเบื้องต้นก่อนอนุมัติให้เป็นผู้รับเลี้ยงแมวเท่านั้น</p>
            <strong>คุณสมบัติของผู้สมัครเป็นผู้รับเลี้ยงแมว</strong>
            <ol style="margin:6px 0; padding-left:20px;">
                <li><strong>ด้านอายุ:</strong> ผู้สมัครควรมีอายุ 18 ปีขึ้นไป หรือเป็นผู้บรรลุนิติภาวะตามกฎหมาย อย่างไรก็ตาม ผู้ที่มีอายุต่ำกว่า 18 ปี เช่น นักเรียนระดับมัธยมศึกษาตอนปลาย สามารถสมัครได้ โดยต้องได้รับความยินยอมและมีผู้ปกครองให้การรับรองในการรับผิดชอบดูแลแมว</li>
                <li><strong>ด้านความรับผิดชอบ:</strong> ผู้สมัครต้องมีความพร้อมและความรับผิดชอบในการดูแลแมว ทั้งด้านอาหาร ที่อยู่อาศัย สุขภาพ ความสะอาด และความปลอดภัย รวมถึงพร้อมดูแลแมวอย่างต่อเนื่อง ไม่ทอดทิ้งหรือปล่อยปละละเลย</li>
                <li><strong>ด้านความพร้อมในการดูแล:</strong> ผู้สมัครควรมีเวลาและความสามารถในการจัดเตรียมสภาพแวดล้อมที่เหมาะสมสำหรับการเลี้ยงแมว รวมถึงสามารถรับผิดชอบค่าใช้จ่ายที่จำเป็นในการดูแลได้</li>
            </ol>
            <small style="color:#d81b60;"><strong>หมายเหตุ:</strong> การกรอกแบบฟอร์มและส่งข้อมูลสมัครไม่ได้หมายความว่าจะได้รับอนุมัติให้เป็นผู้รับเลี้ยงโดยอัตโนมัติ ผู้ดูแลระบบจะพิจารณาจากข้อมูลและคุณสมบัติของผู้สมัครก่อนดำเนินการอนุมัติ</small>
        </div>

        <form method="POST" action="adopter_register.php?step=form">
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
                        <?php for ($d=1; $d<=31; $d++) echo "<option value='{$d}'>{$d}</option>"; ?>
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
                    <option value="อื่นๆ">อื่นๆ (โปรดระบุช่องด้านล่าง)</option>
                </select>
                <div class="other-row">
                    <span style="font-size:13px; color:#666;">อื่นๆ:</span>
                    <input type="text" name="occupation_other" placeholder="ระบุอาชีพอื่นๆ (กรณีเลือกอื่นๆ)">
                </div>
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
                <div class="section-title">ส่วนที่ 3 ความพร้อมในการเลี้ยง *</div>
                <label class="form-label">ประเภทที่พักอาศัย *</label>
                <select name="accommodation_type" class="form-control" required>
                    <option value="">เลือกประเภทที่พัก</option>
                    <option value="บ้านเดี่ยว">บ้านเดี่ยว</option>
                    <option value="ทาวน์เฮาส์ / ทาวน์โฮม">ทาวน์เฮาส์ / ทาวน์โฮม</option>
                    <option value="คอนโดมิเนียม">คอนโดมิเนียม</option>
                    <option value="หอพัก / อพาร์ตเมนต์">หอพัก / อพาร์ตเมนต์</option>
                    <option value="อื่นๆ">อื่นๆ (โปรดระบุช่องด้านล่าง)</option>
                </select>
                <div class="other-row">
                    <span style="font-size:13px; color:#666;">อื่นๆ:</span>
                    <input type="text" name="accommodation_other" placeholder="ระบุประเภทที่พักอื่นๆ (กรณีเลือกอื่นๆ)">
                </div>

                <label class="form-label">สถานะทางการเงิน *</label>
                <select name="financial_status" class="form-control" required>
                    <option value="">เลือกสถานะทางการเงิน</option>
                    <option value="มีรายได้ประจำ">มีรายได้ประจำ</option>
                    <option value="มีรายได้ไม่ประจำ">มีรายได้ไม่ประจำ</option>
                    <option value="นักเรียน / นักศึกษา">นักเรียน / นักศึกษา</option>
                    <option value="ผู้ปกครองสนับสนุน">ผู้ปกครองสนับสนุน</option>
                    <option value="อื่นๆ">อื่นๆ (โปรดระบุช่องด้านล่าง)</option>
                </select>
                <div class="other-row">
                    <span style="font-size:13px; color:#666;">อื่นๆ:</span>
                    <input type="text" name="financial_other" placeholder="ระบุสถานะทางการเงินอื่นๆ (กรณีเลือกอื่นๆ)">
                </div>

                <label class="form-label">ที่พักอนุญาตให้เลี้ยงสัตว์หรือไม่ *</label>
                <div class="radio-group">
                    <label><input type="radio" name="pet_allowed" value="อนุญาต" checked required> อนุญาต</label>
                    <label><input type="radio" name="pet_allowed" value="ไม่อนุญาต"> ไม่อนุญาต</label>
                    <label><input type="radio" name="pet_allowed" value="ไม่แน่ใจ"> ไม่แน่ใจ</label>
                </div>
            </div>

            <div class="section-box">
                <div class="section-title">ส่วนที่ 4 ข้อมูลสุขภาพ</div>
                <label class="form-label">มีโรคประจำตัวหรือไม่ (ไม่บังคับ)</label>
                <div class="radio-group">
                    <label><input type="radio" name="has_chronic_disease" value="มี"> มี</label>
                    <label><input type="radio" name="has_chronic_disease" value="ไม่มี" checked> ไม่มี</label>
                </div>

                <label class="form-label">รายละเอียดเพิ่มเติม (ไม่บังคับ)</label>
                <textarea name="health_details" class="form-control" rows="3" style="border-radius:16px;" placeholder="ระบุข้อมูลเพิ่มเติม หากต้องการ"></textarea>
                <small style="color:#777; display:block; margin-bottom:14px;">ข้อมูลสุขภาพควรเก็บเท่าที่จำเป็น พร้อมแจ้งวัตถุประสงค์การใช้งานและจำกัดผู้ที่เข้าถึงข้อมูล</small>
            </div>

            <div class="green-check-box">
                <label class="green-check-item" style="margin-bottom:0;">
                    <input type="checkbox" name="truth_confirm" value="1" required>
                    <span><strong>ข้าพเจ้าขอยืนยันว่าข้อมูลที่กรอกเป็นความจริง</strong> เพื่อใช้ในการสมัครเป็นผู้รับเลี้ยงเท่านั้น *</span>
                </label>
            </div>

            <button type="submit" name="submit_adopter_form" class="btn-primary">🐾 สมัครเป็นผู้รับเลี้ยง</button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>