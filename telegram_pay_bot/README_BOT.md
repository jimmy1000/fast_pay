# i8Pay Telegram Bot

## 🚀 从 Telethon Userbot 转换为 python-telegram-bot

本项目已成功从 Telethon Userbot 转换为 python-telegram-bot，现在支持完整的内联按钮功能！

## ✨ 主要改进

- ✅ **内联按钮支持** - 风控通知现在显示 "✅ 接受" 和 "❌ 驳回" 按钮
- ✅ **官方 Bot API** - 使用 Telegram 官方支持的 Bot API
- ✅ **更稳定可靠** - 不会因为协议变化而失效
- ✅ **功能完整** - 保留了所有原有功能

## 📋 功能列表

### 基础命令
- `/start` - 启动机器人
- `/help` - 查看帮助
- `/bind 商户ID` - 绑定商户ID
- `/balance` - 查看账户余额
- `/channel` - 查看通道费率
- `/order 订单号` - 查询订单
- `/broadcast` - 中转群发群通知
- `/groupid` - 查看群ID

### 风控通知
- 自动发送风控通知到指定群组
- 包含内联按钮：✅ 接受 / ❌ 驳回
- 点击按钮自动更新订单状态
- 支持订单状态追踪

### 图片识别
- 自动识别订单截图
- OCR 提取 UTR 编号
- 自动转发到相关群组

## 🛠 安装和配置

### 1. 安装依赖
```bash
pip install -r requirements.txt
```

### 2. 配置环境变量
编辑 `dev.env` 文件：
```env
BOT_TOKEN=8334472162:AAEpklOtEHxrtXAedmNTuiyxEFZd3nB1glg
DB_HOST=127.0.0.1
DB_PORT=3306
DB_USER=india_espay
DB_PASSWORD=D4SSSz6C8tnyBenx
DB_NAME=india_espay
FORWARD_GROUP_ID=-4965686740
```

### 3. 启动机器人
```bash
# 启动 Bot
python main.py
```

## 🔘 内联按钮功能

### 风控通知按钮
当风控通知发送后，用户会看到两个按钮：
- **✅ 接受** - 点击后订单状态更新为"已接受"
- **❌ 驳回** - 点击后订单状态更新为"已驳回"

### 按钮回调处理
- 按钮点击后自动更新数据库
- 显示操作结果和操作人信息
- 支持实时状态更新

## 📊 测试

### 测试导入
```bash
python test_imports.py
```

## 🔧 技术架构

### 核心组件
- **Bot 应用** - `client.py` - 创建和管理 Bot 实例
- **命令处理器** - `handlers/commands.py` - 处理所有命令
- **回调查询处理器** - `handlers/callback.py` - 处理按钮点击
- **消息识别器** - `handlers/recognizers.py` - 识别图片和关键词
- **系统事件处理器** - `handlers/system_events.py` - 处理群组事件

### 服务层
- **订单服务** - `services/order_service.py` - 订单相关业务逻辑
- **商户服务** - `services/merchant_service.py` - 商户管理
- **转发服务** - `services/forward_service.py` - 消息转发

## 🚨 注意事项

1. **Bot Token 安全** - 请妥善保管您的 Bot Token，不要泄露给他人
2. **数据库配置** - 确保数据库连接配置正确
3. **群组权限** - Bot 需要被添加到群组并具有发送消息权限
4. **OCR 依赖** - 如果使用图片识别功能，需要安装 tesseract-ocr

## 📞 支持

如有问题，请检查：
1. Bot Token 是否正确
2. 数据库连接是否正常
3. Bot 是否已添加到群组
4. 日志输出中的错误信息

## 🎉 转换完成！

现在您的机器人支持完整的内联按钮功能，用户体验将大大提升！
