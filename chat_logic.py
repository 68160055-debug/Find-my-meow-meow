# chat_logic.py (Pure Python OOP - Built-in Libraries Only)
import sys
import json
from datetime import datetime

# -------------------------------------------------------------
# 1. คลาสพื้นฐานของข้อความ (Encapsulation)
# -------------------------------------------------------------
class Message:
    def __init__(self, sender_id, receiver_id, content, created_at=None):
        self._sender_id = sender_id
        self._receiver_id = receiver_id
        self._content = content
        self._time = created_at if created_at else datetime.now().strftime("%H:%M")
        self.is_read = False

    def to_dict(self):
        return {
            "sender_id": self._sender_id,
            "receiver_id": self._receiver_id,
            "content": self._content,
            "time": self._time,
            "type": "text",
            "is_read": self.is_read
        }

# -------------------------------------------------------------
# 2. คลาสข้อความไฟล์แนบ (Inheritance & Polymorphism)
# -------------------------------------------------------------
class MediaMessage(Message):
    def __init__(self, sender_id, receiver_id, content, file_url, file_type):
        super().__init__(sender_id, receiver_id, content)
        self.file_url = file_url
        self.file_type = file_type  # 'image', 'video', 'pdf'

    # Polymorphism: ปรับแต่งผลลัพธ์ให้มีข้อมูลไฟล์แนบ
    def to_dict(self):
        data = super().to_dict()
        data["type"] = self.file_type
        data["file_url"] = self.file_url
        return data

# -------------------------------------------------------------
# 3. คลาสระบบจัดการเพื่อนและห้องแชต (FriendshipSystem)
# -------------------------------------------------------------
class FriendshipSystem:
    def __init__(self, current_user_id, raw_friends_json, raw_messages_json):
        self.current_user_id = current_user_id
        self.raw_friends = json.loads(raw_friends_json) if raw_friends_json else []
        self.raw_messages = json.loads(raw_messages_json) if raw_messages_json else []
        self.friends_objects = []
        self.messages_objects = []

    # ตรวจสอบสถานะว่าเข้า กรณีที่ 1 หรือ กรณีที่ 2
    def process_state(self):
        # สร้าง Object ข้อความตามหลัก OOP
        for m in self.raw_messages:
            if m.get('file_url'):
                msg_obj = MediaMessage(m['sender_id'], m['receiver_id'], m['content'], m['file_url'], m['type'])
            else:
                msg_obj = Message(m['sender_id'], m['receiver_id'], m['content'], m.get('time'))
            self.messages_objects.append(msg_obj.to_dict())

        # ตรวจสอบว่ามีเพื่อนหรือไม่
        has_friends = len(self.raw_friends) > 0

        return {
            "case": 2 if has_friends else 1, # 1: ยังไม่มีเพื่อน, 2: มีเพื่อนแล้ว
            "has_friends": has_friends,
            "friends_list": self.raw_friends,
            "chat_history": self.messages_objects
        }

# -------------------------------------------------------------
# จุดเชื่อมต่อกับ PHP (รับ JSON Input และ Print JSON Output)
# -------------------------------------------------------------
if __name__ == '__main__':
    # รับข้อมูลจาก PHP ผ่าน Command Line Arguments
    current_uid = int(sys.argv[1]) if len(sys.argv) > 1 else 1
    friends_json = sys.argv[2] if len(sys.argv) > 2 else "[]"
    messages_json = sys.argv[3] if len(sys.argv) > 3 else "[]"

    system = FriendshipSystem(current_uid, friends_json, messages_json)
    result = system.process_state()

    # ส่ง JSON กลับไปให้ PHP
    print(json.dumps(result, ensure_ascii=False))