# services/order_service.py
from config.settings import settings
from database.db import query_order_status, query_pay_status, group_chat_id_query, insert_order_into
from utils.formatters import format_order_status, format_pay_status
from typing import Dict
import logging
import time
from PIL import Image
import pytesseract
from io import BytesIO
import re

logger = logging.getLogger(__name__)

async def get_order_status_reply(orderno: str, update) -> str:
    """获取订单状态并返回文本"""
    try:
        order_data = query_order_status(orderno)
        if order_data:
            msg = format_order_status(order_data[0])
            if order_data[0]['status'] == '1':
                return f"💰 订单状态：\n{msg}\n\n✅ 已支付成功！"
            else:
                await forward_to_forward_group(update)
                return f"⏳ 订单状态：\n{msg}\n\n⚠️ 已转发到客服群，请等待1-3分钟！"
        else:
            pay_data = query_pay_status(orderno)
            if pay_data:
                msg = format_pay_status(pay_data[0])
                if pay_data[0]['status'] == '1':
                    return f"💸 代付订单：\n{msg}"
                else:
                    await forward_to_forward_group(update)
                    return f"⏳ 代付状态：\n{msg}\n\n⚠️ 已转发到客服群，请等待1-3分钟！"
            else:
                return f"❌ 未找到订单号：{orderno}，请检查是否正确"
    except Exception as e:
        logger.exception("❗ 查询订单时异常")
        return "❌ 查询订单失败，请稍后再试"

async def forward_to_forward_group(update):
    """将消息转发到系统配置的中转群"""
    try:
        forward_id = settings.FORWARD_GROUP_ID
        if forward_id == 0:
            await update.message.reply_text("⚠️ 系统未配置中转群，无法转发")
            return
        await update.message.forward(chat_id=forward_id)
        logger.info(f"✅ 已转发消息到中转群 {forward_id}")
    except Exception as e:
        logger.exception("❗ 转发到中转群失败")
        await update.message.reply_text("⚠️ 消息转发失败")

async def handle_photo_order(update, order_data):
    """回复订单状态，并根据状态决定是否转发截图"""
    status_msg = format_order_status(order_data)
    if order_data['status'] == '1':
        await update.message.reply_text(f"📸 订单截图状态：\n{status_msg}\n\n✅ 已支付成功！")
    else:
        await update.message.reply_text(f"📸 订单截图状态：\n{status_msg}\n\n⚠️ 已转发处理，请等待1-3分钟！")
        await forward_photo_order(update, order_data)

async def forward_photo_order(update, order_data):
    up_group_chat_id = group_chat_id_query(order_data['api_account_id'])
    
    # OCR识别utr编号（内存识别）
    utr = await extract_utr_from_photo(update.message.photo[-1], update.get_bot())
    chat = update.effective_chat
    group_title = getattr(chat, 'title', '未知群组')
    
    insert_order_into({
        'group_chat_id': update.effective_chat.id,
        'message_id': update.message.message_id,
        'group_title': group_title,
        'orderno': order_data['orderno'],
        'sys_orderno': order_data['sys_orderno'],
        'utr': utr,
        'status': '0',
        'createtime': int(time.time()),
        'updatetime': int(time.time())
    })

    try:
        await update.message.forward(chat_id=int(settings.FORWARD_GROUP_ID))
    except Exception as e:
        print(f"❌ 转发到中转群失败: {e}")

    if up_group_chat_id:
        try:
            # 下载并发送图片
            photo_file = await update.get_bot().get_file(update.message.photo[-1].file_id)
            await update.get_bot().send_photo(
                chat_id=int(up_group_chat_id),
                photo=photo_file.file_id,
                caption=f"{order_data['sys_orderno']}"
            )
        except Exception as e:
            print(f"❌ 向支付网关群发送图片失败: {e}")

async def extract_utr_from_photo(photo, bot):
    """OCR 提取图片中的 utr 编号（多种格式）"""
    try:
        # 下载图片
        photo_file = await bot.get_file(photo.file_id)
        img_bytes = await photo_file.download_as_bytearray()
        
        image = Image.open(BytesIO(img_bytes))
        text = pytesseract.image_to_string(image)
        
        # 支持多个字段识别
        patterns = [
            r'UPI\s*Ref\s*No[:：]?\s*(\d{10,})',
            r'Txn\s*Ref\s*No[:：]?\s*(\d{10,})',
            r'Reference\s*ID[:：]?\s*(\d{10,})',
            r'UTR[:：]?\s*(\d{10,})',
        ]
        
        for pat in patterns:
            match = re.search(pat, text, re.IGNORECASE)
            if match:
                return match.group(1)

    except Exception as e:
        print(f"❌ OCR识别失败: {e}")
    return None