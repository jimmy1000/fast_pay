import re
import logging
import asyncio
from telegram import Update
from telegram.ext import ContextTypes, CommandHandler
from telegram.error import TimedOut, NetworkError, RetryAfter
from services.merchant_service import get_merchant_balance, bind_merchant, get_channel_info
from services.order_service import get_order_status_reply
from config.settings import settings
from services.forward_service import set_waiting_user

logger = logging.getLogger(__name__)

def register_command_handlers(application):
    async def start_handler(update: Update, context: ContextTypes.DEFAULT_TYPE):
        await update.message.reply_text("👋 欢迎使用 i8Pay Bot，请使用 /help 查看命令列表")
    
    async def help_handler(update: Update, context: ContextTypes.DEFAULT_TYPE):
        help_text = (
            "🛠 *i8pay 使用指南* 🛠\n\n"
            "`/start` - 启动机器人\n"
            "`/help` - 查看帮助\n"
            "`/bind 2025xxxx` - 绑定商户ID\n"
            "`/balance` - 查看账户余额\n"
            "`/channel` - 查看通道费率\n"
            "`/order xxxxxxxxxx` - 查询订单\n"
            "`/broadcast` - 中转群发群通知\n"
            "`/groupid` - 查看群id\n"
            "📸 *订单补单说明：*\n"
            "请发送 \"*截图* + *商户订单号*\" 到群中，例如：\n"
            "xxxxxxxxxxxxxx\n"
            "并附上支付截图，系统将自动识别并处理。"
        )
        await update.message.reply_text(help_text, parse_mode='Markdown')
    
    async def bind_handler(update: Update, context: ContextTypes.DEFAULT_TYPE):
        if not context.args:
            await update.message.reply_text("❌ 请提供商户ID，例如：/bind 20240727")
            return
        
        merchant_id = context.args[0]
        chat = update.effective_chat
        chat_id = chat.id
        title = getattr(chat, "title", "私聊")
        msg = await bind_merchant(merchant_id, chat_id, title)
        await update.message.reply_text(str(msg))
    
    async def balance_handler(update: Update, context: ContextTypes.DEFAULT_TYPE):
        message = await get_merchant_balance(update.effective_chat.id)
        await update.message.reply_text(message)
    
    async def channel_handler(update: Update, context: ContextTypes.DEFAULT_TYPE):
        message = await get_channel_info(update.effective_chat.id)
        await update.message.reply_text(message)
    
    async def groupid_handler(update: Update, context: ContextTypes.DEFAULT_TYPE):
        chat = update.effective_chat
        chat_id = chat.id
        title = getattr(chat, "title", "私聊")
        message = f"🆔 群聊名称：{title}\n🆔 群ID：`{chat_id}`"
        await update.message.reply_text(message, parse_mode='Markdown')
    
    async def order_handler(update: Update, context: ContextTypes.DEFAULT_TYPE):
        if not context.args:
            await update.message.reply_text("❌ 请提供订单号，例如：/order test123")
            return
        
        orderno = context.args[0]
        message = await get_order_status_reply(orderno, update)
        await update.message.reply_text(message)
    
    async def broadcast_handler(update: Update, context: ContextTypes.DEFAULT_TYPE):
        if update.effective_chat.id != settings.FORWARD_GROUP_ID:
            return
        tip = await update.message.reply_text("📢 请 *回复此消息(Reply)*，群发消息给商户", parse_mode='Markdown')
        set_waiting_user(update.effective_user.id, tip.message_id)
    
    # 注册所有命令处理器
    application.add_handler(CommandHandler("start", start_handler))
    application.add_handler(CommandHandler("help", help_handler))
    application.add_handler(CommandHandler("bind", bind_handler))
    application.add_handler(CommandHandler("balance", balance_handler))
    application.add_handler(CommandHandler("channel", channel_handler))
    application.add_handler(CommandHandler("groupid", groupid_handler))
    application.add_handler(CommandHandler("order", order_handler))
    application.add_handler(CommandHandler("broadcast", broadcast_handler))
    
    logger.info("✅ 命令处理器已注册")

