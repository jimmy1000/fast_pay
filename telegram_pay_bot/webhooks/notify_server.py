import logging
from fastapi import FastAPI, HTTPException
from pydantic import BaseModel
from client import client
from database.db import get_group_chat_id_by_merchant
from datetime import datetime
from telegram import InlineKeyboardButton, InlineKeyboardMarkup
from config.settings import settings

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s - %(levelname)s - %(message)s"
)

app = FastAPI(title="Telegram Webhook Server")


class NotifyPayload(BaseModel):
    create_time: int
    money: float
    merchantId: int
    risk_money: float
    username: str
    order_no: str
    contacts: str  # 格式: @aaa,@bbb


class RepayNotifyPayload(BaseModel):
    merchant_id: str
    username: str
    money: float
    usdt_rate: float
    usdt_amount: float
    usdt_address: str


class RepayErrorNotifyPayload(BaseModel):
    merchant_id: str
    daifuid: str
    order_id: str = ""
    error_message: str


@app.post("/notify")
async def notify(payload: NotifyPayload):
    logging.info(f"📩 收到通知请求: {payload.dict()}")

    group_info = get_group_chat_id_by_merchant(payload.merchantId)
    if not group_info:
        raise HTTPException(status_code=404, detail="商户未绑定TG群")

    group_chat_id = int(group_info[0]['group_chat_id'])
    create_time_str = datetime.fromtimestamp(payload.create_time).strftime("%Y-%m-%d %H:%M:%S")

    message = (
        f"💰 代付风控通知\n"
        f"商户: {payload.merchantId} ({payload.username})\n"
        f"金额: {payload.money}\n"
        f"风控金额: {payload.risk_money}\n"
        f"订单号: {payload.order_no}\n"
        f"操作授权tg: {payload.contacts}\n"
        f"创建时间: {create_time_str}"
    )

    # 添加按钮（携带允许的用户列表）
    keyboard = [
        [
            InlineKeyboardButton(
                "✅ 接受",
                callback_data=f"accept:{payload.order_no}:{payload.contacts}"
            ),
            InlineKeyboardButton(
                "❌ 驳回",
                callback_data=f"reject:{payload.order_no}:{payload.contacts}"
            )
        ]
    ]

    try:
        await client.bot.send_message(
            chat_id=group_chat_id,
            text=message,
            reply_markup=InlineKeyboardMarkup(keyboard)
        )
        logging.info(f"✅ 消息已发送到群 {group_chat_id}")
    except Exception as e:
        logging.error(f"❌ 发送TG消息失败: {e}")
        raise HTTPException(status_code=500, detail=f"发送TG消息失败: {e}")

    return {"status": "success"}


@app.post("/repay_notify")
async def repay_notify(payload: RepayNotifyPayload):
    """USDT下发通知接口"""
    logging.info(f"💰 收到USDT下发通知请求: {payload.dict()}")

    # 根据商户ID获取群组ID
    group_info = get_group_chat_id_by_merchant(payload.merchant_id)
    if not group_info:
        raise HTTPException(status_code=404, detail="商户未绑定TG群")

    group_chat_id = int(group_info[0]['group_chat_id'])

    # 格式化消息内容
    message = (
        f"💰 *账户USDT下发提交* 💰\n\n"
        f"🏪 商户号: `{payload.merchant_id}`\n"
        f"👤 商户名称: {payload.username}\n"
        f"💵 下发金额: `{payload.money}`\n"
        f"💱 USDT汇率: `{payload.usdt_rate}`\n"
        f"🪙 USDT数量: `{payload.usdt_amount}`\n"
        f"📍 USDT地址: `{payload.usdt_address}`\n\n"
        f"✅ 请商户提交人员确认订单信息..."
    )

    try:
        await client.bot.send_message(
            chat_id=group_chat_id,
            text=message,
            parse_mode='Markdown'
        )
        logging.info(f"✅ USDT下发通知已发送到群组 {group_chat_id}")
        return {
            "success": True,
            "message": "下发通知发送成功",
            "merchant_id": payload.merchant_id,
            "group_chat_id": group_chat_id
        }
    except Exception as e:
        logging.error(f"❌ 发送USDT下发通知失败: {e}")
        raise HTTPException(status_code=500, detail=f"发送下发通知失败: {e}")


@app.post("/repay_error_notify")
async def repay_error_notify(payload: RepayErrorNotifyPayload):
    """自动代付异常通知到中转群"""
    logging.info(f"❗ 收到自动代付异常通知请求: {payload.dict()}")

    forward_group_id = settings.FORWARD_GROUP_ID
    if not forward_group_id:
        raise HTTPException(status_code=500, detail="未配置 FORWARD_GROUP_ID，无法发送错误通知")

    # 构造消息
    parts = [
        "❗ 自动代付异常通知",
        f"🏪 商户号: `{payload.merchant_id}`",
        f"🆙 上游ID: `{payload.daifuid}`",
        f"🧾 订单号: `{payload.order_id}`" if payload.order_id else None,
        f"📣 异常信息: {payload.error_message}",
        "",
        "请客服及时切换代付通道❗️",
        "请客服及时切换代付通道❗️",
        "请客服及时切换代付通道❗️"
    ]
    message = "\n".join([p for p in parts if p])

    try:
        await client.bot.send_message(
            chat_id=forward_group_id,
            text=message,
            parse_mode='Markdown'
        )
        logging.info(f"✅ 自动代付异常已通知到群组 {forward_group_id}")
        return {
            "success": True,
            "message": "异常通知发送成功",
            "forward_group_id": forward_group_id
        }
    except Exception as e:
        logging.error(f"❌ 发送自动代付异常通知失败: {e}")
        raise HTTPException(status_code=500, detail=f"发送异常通知失败: {e}")


@app.get("/health")
async def health_check():
    """健康检查接口"""
    return {"status": "healthy", "service": "telegram_webhook_server"}
