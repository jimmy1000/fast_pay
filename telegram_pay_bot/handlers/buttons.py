import logging
import requests
from telegram import Update, InlineKeyboardMarkup
from telegram.ext import CallbackContext
from config.settings import settings

API_URL = f"{settings.API_BASE_URL}/api/webhooks/riskhook"


async def button_handler(update: Update, context: CallbackContext):
    query = update.callback_query
    await query.answer()

    user = query.from_user
    username = f"@{user.username}" if user.username else user.full_name

    # callback_data: action:order_no:contacts
    action, order_no, contacts = query.data.split(":", 2)
    allowed_users = [c.strip() for c in contacts.split(",") if c.strip()]

    # 检查是否是授权人
    if username not in allowed_users:
        # 非授权人点击按钮无效，不提示，按钮不响应
        return

    status = "accept" if action == "accept" else "reject"

    # 调用 PHP API
    try:
        response = requests.get(
            API_URL,
            params={"orderno": order_no, "status": status},
            timeout=10
        )
        result = response.json()
    except Exception as e:
        logging.error(f"PHP接口调用失败: {e}")
        return

    if not result.get("status"):
        msg = result.get("msg", "未知错误")
        await query.message.reply_text(f"❌ 操作失败: {msg}")
        return

    # 拼接结果
    if status == "accept":
        text = f"✅ 订单 {order_no} 已接受（操作人: {username})"
    else:
        text = f"❌ 订单 {order_no} 已驳回（操作人: {username})"

    # 更新消息 → 删除按钮
    await query.edit_message_text(
        text=f"{query.message.text}\n\n{text}",
        parse_mode="HTML",
        reply_markup=InlineKeyboardMarkup([])  # 清空按钮
    )
