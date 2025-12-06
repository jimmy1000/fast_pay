import logging
import sys
import asyncio
import uvicorn
from telegram.ext import Application, CallbackQueryHandler
from config.settings import settings
from handlers.commands import register_command_handlers
from handlers.recognizers import register_recognizer_handlers
from handlers.system_events import register_sys_events_handlers
from handlers.buttons import button_handler
from webhooks.notify_server import app as webhook_app  # FastAPI 应用

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s - %(levelname)s - %(message)s",
    stream=sys.stdout
)


def setup_bot_commands_sync(application):
    import requests
    commands = [
        {"command": "start", "description": "🚀 启动机器人"},
        {"command": "help", "description": "📖 查看帮助信息"},
        {"command": "bind", "description": "🔗 绑定商户ID"},
        {"command": "balance", "description": "💰 查看账户余额"},
        {"command": "channel", "description": "📡 查看通道费率"},
        {"command": "order", "description": "📋 查询订单状态"},
        {"command": "broadcast", "description": "📢 中转群发通知"},
        {"command": "groupid", "description": "🆔 查看群组ID"},
    ]
    try:
        url = f"https://api.telegram.org/bot{settings.BOT_TOKEN}/setMyCommands"
        response = requests.post(url, json={"commands": commands})
        if response.status_code == 200:
            logging.info("✅ Bot命令菜单设置成功")
        else:
            logging.error(f"❌ 设置Bot命令菜单失败: {response.text}")
    except Exception as e:
        logging.error(f"❌ 设置Bot命令菜单失败: {e}")


async def start_bot():
    application = Application.builder().token(settings.BOT_TOKEN).build()

    register_command_handlers(application)
    register_recognizer_handlers(application)
    register_sys_events_handlers(application)

    # 注册按钮事件
    application.add_handler(CallbackQueryHandler(button_handler))

    setup_bot_commands_sync(application)
    logging.info("🤖 Bot 启动中...")

    await application.initialize()
    await application.start()
    await application.updater.start_polling()

    return application


async def start_webhook():
    config = uvicorn.Config(webhook_app, host="0.0.0.0", port=9000, log_level="info")
    server = uvicorn.Server(config)
    await server.serve()


async def main_async():
    application = await start_bot()
    await start_webhook()

    await application.updater.stop()
    await application.stop()
    await application.shutdown()


if __name__ == "__main__":
    try:
        asyncio.run(main_async())
    except KeyboardInterrupt:
        logging.info("🛑 收到中断信号，正在关闭服务...")
