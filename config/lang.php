<?php
// config/lang.php — พจนานุกรมภาษาไทย-อังกฤษส่วนกลางสำหรับทุกหน้า
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$current_lang = ($_SESSION['site_lang'] ?? 'th') === 'en' ? 'en' : 'th';

$txt = [
    'th' => [
        // เมนูนำทาง (Sidebar)
        'nav_home'     => '🏠 หน้าแรก',
        'nav_profile'  => '👤 ประวัติผู้ใช้งาน',
        'nav_contact'  => '✉️ ติดต่อผู้ประกาศ/แอดมิน',
        'nav_buy'      => '🛒 ซื้อแมว',
        'nav_chat'     => '💬 ติดต่อผู้ขอรับเลี้ยง',
        'nav_settings' => '⚙️ การตั้งค่า',
        'nav_logout'   => '🚪 ออกจากระบบ',

        // แถบค้นหาด้านบน (config/search.php)
        'search_ph'    => 'ค้นหาชื่อคน, ชื่อโพสต์, แอดมิน (เช่น "หาบ้าน" -ดุ หรือ ขาว OR ส้ม)...',
        'search_btn'   => 'ค้นหา',
        'notif_title'  => 'การแจ้งเตือน',
        'default_user' => 'ผู้ใช้งาน',

        // ปฏิทิน (Calendar Widget)
        'days'   => ['อา', 'จ', 'อ', 'พ', 'พฤ', 'ศ', 'ส'],
        'months' => [
            1=>'มกราคม', 2=>'กุมภาพันธ์', 3=>'มีนาคม', 4=>'เมษายน',
            5=>'พฤษภาคม', 6=>'มิถุนายน', 7=>'กรกฎาคม', 8=>'สิงหาคม',
            9=>'กันยายน', 10=>'ตุลาคม', 11=>'พฤศจิกายน', 12=>'ธันวาคม'
        ],

        // หน้า Settings (settings.php)
        'page_title'    => 'การตั้งค่า - Find my meow meow 💖',
        'heading'       => '⚙️ การตั้งค่า (Settings)',
        'sec_privacy'   => '🔒 ความเป็นส่วนตัว & ความปลอดภัย',
        'priv_title'    => 'การตั้งค่าความเป็นส่วนตัว',
        'priv_desc'     => 'ข้อมูลส่วนบุคคล เช่น อีเมลและรหัสผ่าน จะมีแค่คุณและแอดมินเท่านั้นที่เห็น',
        'sec_lang'      => '🌐 การตั้งค่าภาษา',
        'lang_title'    => 'เปลี่ยนภาษา (Select Language)',
        'lang_desc'     => 'เลือกภาษาที่คุณต้องการใช้งานในระบบ',
        'btn_save_lang' => 'บันทึกภาษา',
        'sec_admin'     => '📢 ติดต่อแอดมิน / ผู้ดูแลระบบ',
        'admin_desc'    => 'ส่งข้อความส่วนตัวถึงแอดมินเพื่อสอบถามปัญหาหรือขอความช่วยเหลือ',
        'admin_ph'      => 'พิมพ์ข้อความที่ต้องการส่งถึงแอดมินที่นี่...',
        'btn_send'      => '📨 ส่งข้อความ',
        'sec_account'   => '⚠️ บัญชี & การตั้งค่า',
        'logout_prompt' => 'กำลังจะออกจากระบบหรือไม่?',
        'btn_logout'    => '🚪 ออกจากระบบ'
    ],
    'en' => [
        // Sidebar Navigation
        'nav_home'     => '🏠 Home',
        'nav_profile'  => '👤 User Profile',
        'nav_contact'  => '✉️ Contact Us / Admin',
        'nav_buy'      => '🛒 Buy Cat',
        'nav_chat'     => '💬 Chat with Adopter',
        'nav_settings' => '⚙️ Settings',
        'nav_logout'   => '🚪 Logout',

        // Top Search Bar (config/search.php)
        'search_ph'    => 'Search people, posts, admin (e.g. "adopt" -fierce or white OR orange)...',
        'search_btn'   => 'Search',
        'notif_title'  => 'Notifications',
        'default_user' => 'User',

        // Calendar Widget
        'days'   => ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
        'months' => [
            1=>'January', 2=>'February', 3=>'March', 4=>'April',
            5=>'May', 6=>'June', 7=>'July', 8=>'August',
            9=>'September', 10=>'October', 11=>'November', 12=>'December'
        ],

        // Settings Page (settings.php)
        'page_title'    => 'Settings - Find my meow meow 💖',
        'heading'       => '⚙️ Settings',
        'sec_privacy'   => '🔒 Privacy & Security',
        'priv_title'    => 'Privacy Settings',
        'priv_desc'     => 'Personal information like email and password is only visible to you and admin.',
        'sec_lang'      => '🌐 Language Settings',
        'lang_title'    => 'Select Language',
        'lang_desc'     => 'Choose your preferred language for the system',
        'btn_save_lang' => 'Save Language',
        'sec_admin'     => '📢 Contact Admin',
        'admin_desc'    => 'Send a private message to admin for support or inquiries',
        'admin_ph'      => 'Type your message to admin here...',
        'btn_send'      => '📨 Send Message',
        'sec_account'   => '⚠️ Account & Session',
        'logout_prompt' => 'Do you want to log out of the system?',
        'btn_logout'    => '🚪 Logout'
    ]
];

$L = $txt[$current_lang];