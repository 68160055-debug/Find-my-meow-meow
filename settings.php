<?php
require_once 'auth.php';
require_login();

if (isset($_POST['lang'])) {
    $_SESSION['lang'] = $_POST['lang'];
    header("Location: settings.php");
    exit;
}
$current_lang = $_SESSION['lang'] ?? 'th';
?>
<!-- แบบฟอร์มเลือกภาษาในหน้า settings.php -->
<form method="post">
    <label>🌐 เลือกภาษา (Language):</label>
    <select name="lang" onchange="this.form.submit()">
        <option value="th" <?= $current_lang == 'th' ? 'selected' : '' ?>>ภาษาไทย (Thai)</option>
        <option value="en" <?= $current_lang == 'en' ? 'selected' : '' ?>>English</option>
    </select>
</form>