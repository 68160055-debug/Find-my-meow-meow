<?php
// เริ่ม session และเชื่อมต่อฐานข้อมูลตามปกติของระบบ
session_start();
// include 'config/db.php'; // หรือไฟล์ config ของโปรเจกต์

// ตัวอย่างจำลองการดึง user_id ปัจจุบันและคนที่กำลังคุยด้วย (Query String เช่น adopter.php?chat_with=1)
$current_user_id = $_SESSION['user_id'] ?? 1;
$selected_user_id = isset($_GET['chat_with']) ? (int)$_GET['chat_with'] : 1;

// จัดการเมื่อมีการส่งข้อความ (Form POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['message'])) {
    $msg_text = trim($_POST['message']);
    $receiver_id = (int)$_POST['receiver_id'];
    
    if ($msg_text !== '') {
        // SQL บันทึกข้อความ (ตัวอย่าง Prepared Statement)
        // $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, message, created_at) VALUES (?, ?, ?, NOW())");
        // $stmt->bind_param("iis", $current_user_id, $receiver_id, $msg_text);
        // $stmt->execute();
        
        // ส่งเสร็จ reload หน้านั้นเพื่ออัปเดตแชต
        header("Location: adopter.php?chat_with=" . $receiver_id);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ติดต่อผู้ขอรับเลี้ยง (Adopter) - Find my meow meow</title>
    <!-- ไฟล์ CSS หลักเดิมของโปรเจกต์ -->
    <link rel="stylesheet" href="assets/css/style.css">
    
    <!-- สไตล์เฉพาะของหน้า adopter.php เพื่อแก้ปัญหา UI โดยไม่กระทบหน้าอื่น -->
    <style>
        /* จัด Layout รวมของหน้าแชต */
        .chat-main-container {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 20px;
            height: calc(100vh - 120px);
            margin-top: 15px;
            padding-right: 15px;
        }

        /* 1 & 5. ฝั่งรายชื่อเพื่อน (Contact List Sidebar) พร้อม Scroll */
        .friend-list-panel {
            background: #fff;
            border-radius: 16px;
            padding: 15px;
            border: 1px solid #ffd6de;
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .friend-list-header {
            font-size: 16px;
            font-weight: bold;
            color: #d63384;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .friend-list-scroll {
            overflow-y: auto;
            flex: 1;
            padding-right: 5px;
        }

        /* การ์ดรายชื่อเพื่อนแต่ละคน */
        .friend-card {
            display: flex;
            align-items: center;
            padding: 10px 12px;
            margin-bottom: 10px;
            border-radius: 12px;
            border: 1px solid #ffccd5;
            background: #fff;
            text-decoration: none;
            color: #333;
            transition: all 0.2s ease;
            position: relative;
        }

        .friend-card:hover {
            background-color: #fff0f3;
        }

        /* 2. สถานะ Active สำหรับคนที่กำลังคุยอยู่ */
        .friend-card.active {
            background-color: #ffe3e8;
            border: 1.5px solid #ff4d6d;
            box-shadow: 0 2px 6px rgba(255, 77, 109, 0.15);
        }

        .friend-avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            margin-right: 12px;
            object-fit: cover;
        }

        .friend-info {
            flex: 1;
        }

        .friend-name {
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 2px;
        }

        .friend-status {
            font-size: 12px;
            color: #28a745;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .friend-status::before {
            content: "";
            width: 7px;
            height: 7px;
            background-color: #28a745;
            border-radius: 50%;
            display: inline-block;
        }

        /* 1. ฝั่งกล่องข้อความ (Chat Room) */
        .chat-room-panel {
            background: #fff;
            border-radius: 16px;
            border: 1px solid #ffd6de;
            display: flex;
            flex-direction: column;
            height: 100%;
            overflow: hidden;
        }

        /* Header ของห้องแชต */
        .chat-header {
            padding: 12px 20px;
            border-bottom: 1px solid #ffe3e8;
            display: flex;
            align-items: center;
            gap: 12px;
            background: #fffafb;
        }

        /* พื้นที่แสดงข้อความ Scroll ได้ */
        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 14px;
            background: #fff;
        }

        .chat-date-badge {
            align-self: center;
            background: #f1f3f5;
            color: #6c757d;
            font-size: 12px;
            padding: 4px 12px;
            border-radius: 20px;
            margin: 6px 0;
        }

        /* 3. Message Bubbles & Spacing */
        .msg-row {
            display: flex;
            width: 100%;
        }

        .msg-row.incoming {
            justify-content: flex-start;
        }

        .msg-row.outgoing {
            justify-content: flex-end;
        }

        .msg-bubble {
            max-width: 65%;
            padding: 10px 16px;
            border-radius: 16px;
            font-size: 14px;
            line-height: 1.5;
            position: relative;
            word-break: break-word;
        }

        /* ฝั่งผู้รับ (สีขาว ขอบชมพูอ่อน) */
        .msg-row.incoming .msg-bubble {
            background-color: #fff;
            border: 1px solid #ffd6de;
            color: #333;
            border-bottom-left-radius: 4px;
        }

        /* ฝั่งเราส่งเอง (สีชมพูเข้ม) */
        .msg-row.outgoing .msg-bubble {
            background-color: #ff4d6d;
            color: #fff;
            border-bottom-right-radius: 4px;
        }

        /* Timestamp & Checkmark */
        .msg-meta {
            font-size: 11px;
            margin-top: 4px;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 4px;
        }

        .msg-row.incoming .msg-meta {
            color: #8c8c8c;
        }

        .msg-row.outgoing .msg-meta {
            color: rgba(255, 255, 255, 0.85);
        }

        /* 4. กล่องพิมพ์ข้อความชิดติดใต้แชต */
        .chat-input-panel {
            padding: 12px 18px;
            background: #fffafb;
            border-top: 1px solid #ffe3e8;
        }

        .chat-input-form {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .chat-input-field {
            flex: 1;
            padding: 12px 18px;
            border: 1px solid #ffccd5;
            border-radius: 24px;
            outline: none;
            font-size: 14px;
            background: #fff;
            transition: border-color 0.2s;
        }

        .chat-input-field:focus {
            border-color: #ff4d6d;
        }

        .btn-send {
            background-color: #ff4d6d;
            color: #fff;
            border: none;
            padding: 10px 22px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: background 0.2s ease;
        }

        .btn-send:hover {
            background-color: #e03b5b;
        }
    </style>
</head>
<body>
    <div class="layout-container">
        <!-- เมนูด้านซ้ายและ Header เดิมของโปรเจกต์ -->
        <?php include 'config/leftbar.php'; ?>

        <div class="main-content">
            <?php include 'config/search.php'; ?>

            <!-- คอนเทนเนอร์หลักของหน้าแชต -->
            <div class="chat-main-container">
                
                <!-- 1 & 5. ฝั่งรายชื่อเพื่อน -->
                <div class="friend-list-panel">
                    <div class="friend-list-header">
                        <span>🐾 รายชื่อเพื่อน</span>
                    </div>
                    
                    <div class="friend-list-scroll">
                        <!-- ตัวอย่างวนลูป Contact: เพิ่ม class active เมื่อตรงกับ selected_user_id -->
                        <a href="adopter.php?chat_with=1" class="friend-card <?= ($selected_user_id === 1) ? 'active' : '' ?>">
                            <img src="assets/images/cat.png" alt="Avatar" class="friend-avatar">
                            <div class="friend-info">
                                <div class="friend-name">Cat-testing</div>
                                <div class="friend-status">ออนไลน์</div>
                            </div>
                        </a>

                        <a href="adopter.php?chat_with=2" class="friend-card <?= ($selected_user_id === 2) ? 'active' : '' ?>">
                            <img src="assets/images/cat.png" alt="Avatar" class="friend-avatar">
                            <div class="friend-info">
                                <div class="friend-name">Cat</div>
                                <div class="friend-status">ออนไลน์</div>
                            </div>
                        </a>

                        <a href="adopter.php?chat_with=3" class="friend-card <?= ($selected_user_id === 3) ? 'active' : '' ?>">
                            <img src="assets/images/cat.png" alt="Avatar" class="friend-avatar">
                            <div class="friend-info">
                                <div class="friend-name">cat</div>
                                <div class="friend-status">ออนไลน์</div>
                            </div>
                        </a>
                    </div>
                </div>

                <!-- ฝั่งกล่องสนทนา -->
                <div class="chat-room-panel">
                    <!-- หัวห้องสนทนา -->
                    <div class="chat-header">
                        <img src="assets/images/cat.png" alt="Profile" style="width: 40px; height: 40px; border-radius: 50%;">
                        <div>
                            <div style="font-weight: 600; font-size: 15px; color: #333;">MeowLover</div>
                            <div style="font-size: 12px; color: #28a745;">● ออนไลน์ (Online)</div>
                        </div>
                    </div>

                    <!-- รายการข้อความที่คุยกัน -->
                    <div class="chat-messages" id="chatMessages">
                        <div class="chat-date-badge">วันนี้ 11:01</div>

                        <!-- ข้อความฝั่งคนอื่น (Incoming) -->
                        <div class="msg-row incoming">
                            <div class="msg-bubble">
                                สวัสดีค่ะ ยินดีที่ได้คุยกันนะคะ 🌸💕
                                <div class="msg-meta">15:42</div>
                            </div>
                        </div>

                        <!-- ข้อความฝั่งเรา (Outgoing) -->
                        <div class="msg-row outgoing">
                            <div class="msg-bubble">
                                ขอบคุณสำหรับคำแนะนำค่ะ 😊
                                <div class="msg-meta">15:42 ✓✓</div>
                            </div>
                        </div>

                        <!-- ข้อความฝั่งคนอื่น (Incoming) -->
                        <div class="msg-row incoming">
                            <div class="msg-bubble">
                                ยินดีค่ะ มีอะไรสอบถามได้เลยนะคะ
                                <div class="msg-meta">15:43</div>
                            </div>
                        </div>
                    </div>

                    <!-- 4. ช่องส่งข้อความ (รองรับการกด Enter ส่งได้ทันที) -->
                    <div class="chat-input-panel">
                        <form method="POST" action="adopter.php" class="chat-input-form">
                            <input type="hidden" name="receiver_id" value="<?= htmlspecialchars($selected_user_id) ?>">
                            <input 
                                type="text" 
                                name="message" 
                                class="chat-input-field" 
                                placeholder="พิมพ์ข้อความ... / Type a message..." 
                                autocomplete="off" 
                                required
                            >
                            <button type="submit" class="btn-send">
                                ส่ง ✈
                            </button>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Script เลื่อนกล่องแชตลงมาล่างสุดอัตโนมัติเมื่อเปิดหน้า -->
    <script>
        const chatBox = document.getElementById('chatMessages');
        if (chatBox) {
            chatBox.scrollTop = chatBox.scrollHeight;
        }
    </script>
</body>
</html>