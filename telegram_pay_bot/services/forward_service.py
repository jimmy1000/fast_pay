# services/forward_service.py
from database.db import update_order_status_and_return
from database.db import get_all_bound_group_ids
##处理中转群转发
waiting_users = {}  # 存储 user_id -> reply_to_msg_id

def set_waiting_user(user_id, msg_id):
    waiting_users[user_id] = msg_id

def pop_waiting_user(user_id):
    return waiting_users.pop(user_id, None)

def get_waiting_message_id(user_id):
    return waiting_users.get(user_id)

def is_waiting_user(user_id):
    return user_id in waiting_users  # ✅ 添加这个函数

async def do_broadcast(update):
    group_ids = get_all_bound_group_ids()
    sent = 0
    for gid in group_ids:
        try:
            await update.message.forward(chat_id=int(gid))
            sent += 1
        except Exception as e:
            print(f"❌ 群发失败 {gid}: {e}")
    await update.message.reply_text(f"✅ 已转发给 {sent} 个商户群")

#处理补单转发
async def handle_reorder_notice(update, status_text: str):
    reply_msg = update.message.reply_to_message
    if not reply_msg or not reply_msg.text:
        return

    order_no = reply_msg.text.strip()
    status = 1 if status_text == "补单成功" else 2
    result = update_order_status_and_return(order_no, str(status))
    if not result:
        return

    group_chat_id = int(result['group_chat_id'])
    message_id = int(result['message_id'])
    print(result)
    if status == 1:
        msg = f"✅ 补单成功\nutr: {result['utr']}\n商户端订单号: {result['orderno']}"
    elif status == 2:
        msg = f"查单utr: {result['utr']}\nutr未找到\n请警惕骗单!"

    await update.get_bot().send_message(
        chat_id=group_chat_id, 
        text=msg, 
        reply_to_message_id=message_id
    )