# client.py
from telegram.ext import Application
from config.settings import settings

# 创建 bot 应用实例
bot = Application.builder().token(settings.BOT_TOKEN).build()

# 为了兼容性，保留 client 变量名
client = bot
