# config/settings.py
import os
from dotenv import load_dotenv

# 加载环境变量
load_dotenv('dev.env')

class Settings:
    # 数据库配置
    DB_HOST = os.getenv('DB_HOST', 'localhost')
    DB_PORT = int(os.getenv('DB_PORT', 3306))
    DB_USER = os.getenv('DB_USER', 'root')
    DB_PASSWORD = os.getenv('DB_PASSWORD', '123456')
    DB_NAME = os.getenv('DB_NAME', 'espay')
    
    # Telegram Bot Token
    BOT_TOKEN = os.getenv('BOT_TOKEN')
    
    # 中转群ID
    FORWARD_GROUP_ID = int(os.getenv('FORWARD_GROUP_ID', 0))
    
    # API基础URL
    API_BASE_URL = os.getenv('API_BASE_URL', 'http://espay.com')

settings = Settings()
