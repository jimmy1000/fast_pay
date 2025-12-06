import logging
from telegram import Update, ChatMemberUpdated, ChatMember
from telegram.ext import ContextTypes, ChatMemberHandler
from database.db import get_all_bound_group_ids

logger = logging.getLogger(__name__)

def register_sys_events_handlers(application):
    async def welcome_new_member(update: Update, context: ContextTypes.DEFAULT_TYPE):
        """欢迎新成员"""
        # 获取所有商户群 ID
        merchant_group_ids = get_all_bound_group_ids()
        if str(update.effective_chat.id) not in map(str, merchant_group_ids):
            return  # 如果当前群不是商户群，直接跳过

        # 检查是否有新成员加入
        for change in update.chat_member.chat_member:
            if change.status in [ChatMember.MEMBER, ChatMember.ADMINISTRATOR, ChatMember.CREATOR]:
                # 新成员加入
                user = change.user
                username = user.first_name or "用户"

                chat = update.effective_chat
                group_title = getattr(chat, "title", "本群")

                welcome_text = (
                    f"👋 欢迎 {username} 加入 [{group_title}]！\n📌输入 /help 查看机器人使用说明!\n\n"
                    "🛠 *i8pay 使用指南* 🛠\n"
                    "`/start` - 启动机器人\n"
                    "`/help` - 查看帮助\n"
                    "`/bind 2025xxxx` - 绑定商户ID\n"
                    "`/balance` - 查看账户余额\n"
                    "`/channel` - 查看通道费率\n"
                    "`/order xxxxxxxxxx` - 查询订单\n"
                    "`/broadcast` - 中转群发群通知\n"
                    "`/groupid` - 查看群id\n"
                    "📸 *补单说明：*\n"
                    "请发送 \"*截图* + *商户订单号*\" 到群中，例如：\n"
                    "xxxxxxxxxxxxxx\n"
                    "并附上支付截图，系统将自动识别并处理。"
                )

                await context.bot.send_message(
                    chat_id=update.effective_chat.id,
                    text=welcome_text,
                    parse_mode='Markdown'
                )

    # 注册聊天成员更新处理器
    application.add_handler(ChatMemberHandler(welcome_new_member, ChatMemberHandler.CHAT_MEMBER))
    
    logger.info("✅ 系统事件处理器已注册")
