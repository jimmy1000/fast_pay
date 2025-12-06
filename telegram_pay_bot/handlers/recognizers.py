import re
import logging
from telegram import Update
from telegram.ext import ContextTypes, MessageHandler, filters
from services.order_service import handle_photo_order
from config.settings import settings
from database.db import query_order_status
from services.forward_service import is_waiting_user, get_waiting_message_id, pop_waiting_user, do_broadcast
from services.forward_service import handle_reorder_notice

logger = logging.getLogger(__name__)

ORDER_PATTERN = r'[a-zA-Z0-9_]{8,}'

def register_recognizer_handlers(application):
    async def handle_order_photo_event(update: Update, context: ContextTypes.DEFAULT_TYPE):
        """识别带截图+说明文字的订单号并处理"""
        if not update.message.photo or not update.message.caption:
            return
            
        caption = update.message.caption or ""
        if re.search(r'[\u4e00-\u9fff]', caption):
            return
            
        order_match = re.search(ORDER_PATTERN, caption)
        if not order_match:
            return
            
        order_no = order_match.group(0)
        if order_data := query_order_status(order_no):
            await handle_photo_order(update, order_data[0])

    async def broadcast_reply_listener(update: Update, context: ContextTypes.DEFAULT_TYPE):
        """处理中转群群发"""
        if not update.message.reply_to_message:
            return
            
        user_id = update.effective_user.id
        expected_msg_id = get_waiting_message_id(user_id)
        if not expected_msg_id:
            return
            
        reply_to = update.message.reply_to_message
        if reply_to.message_id != expected_msg_id:
            return
            
        pop_waiting_user(user_id)
        await do_broadcast(update)

    async def handle_reorder_messages(update: Update, context: ContextTypes.DEFAULT_TYPE):
        """处理补单关键词和骗单关键词"""
        text = update.message.text or ""
        
        # 补单关键词和骗单关键词（可随时扩展）
        REORDER_KEYWORDS = r"(补单成功|补单完成|已补单|补单已处理)"
        WARNING_KEYWORDS = r"(UTR未找到|UTR未查到|骗单行为)"
        
        if re.search(REORDER_KEYWORDS, text):
            await handle_reorder_notice(update, "补单成功")
        elif re.search(WARNING_KEYWORDS, text):
            await handle_reorder_notice(update, "骗单警告")

    # 注册消息处理器
    application.add_handler(MessageHandler(filters.PHOTO & filters.CaptionRegex(ORDER_PATTERN), handle_order_photo_event))
    application.add_handler(MessageHandler(filters.REPLY & filters.ChatType.GROUPS, broadcast_reply_listener))
    application.add_handler(MessageHandler(filters.TEXT & filters.ChatType.GROUPS, handle_reorder_messages))
    
    logger.info("✅ 识别器处理器已注册")