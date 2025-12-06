from database.db import select_merchant_id_query, insert_merchant_id_query,select_merchant_id, select_channel_id, get_open_list,get_list_by_user
import time
from services.channel_service import channel
async def get_merchant_balance(group_chat_id):
    merchant_id_data = select_merchant_id(group_chat_id)
    if not merchant_id_data:
        return "❌ 未绑定商户号，请使用 /bind 绑定商户号"
    merchant_id = merchant_id_data[0]['merchant_id']
    merchant_data = select_merchant_id_query(merchant_id)
    return  (
            f"👤 账户信息：\n\n"
            f"商户号: {merchant_data[0]['merchant_id']}\n"
            f"用户名: {merchant_data[0]['username']}\n"
            f"昵称: {merchant_data[0]['nickname']}\n"
            f"已结算: {merchant_data[0]['withdrawal']}\n"
            f"已内充: {merchant_data[0]['recharge']}\n"
            f"账户余额: {merchant_data[0]['money']}\n"
            "-------------------------------------\n"
            "更多资金详情请登录商户后台: https://user.i8pay.cc/ \n查看或导出Excel表格!"
        )

async def bind_merchant(merchant_id, chat_id, group_title):
    merchant_data = select_merchant_id_query(merchant_id)
    if not merchant_data:
        return "❌ 商户号不存在，请检查是否正确"
    
    createtime = int(time.time())  # 👈 时间戳格式，如 1721811364
    result = insert_merchant_id_query(merchant_id, chat_id, group_title, createtime)
    if result == 1:
        return f"✅ 商户号 {merchant_id} 绑定成功！"
    else:
        return f" 商户号已绑定{result}"

async def get_channel_info(group_chat_id):
    merchant_id_data = select_merchant_id(group_chat_id)
    if not merchant_id_data:
        return "❌ 未绑定商户号，请使用 /bind 绑定商户号"
    
    merchant_id = merchant_id_data[0]['merchant_id']
    user_id = select_channel_id(merchant_id)
    api_type_list = get_open_list()
    api_user_channels = get_list_by_user(user_id)
    channel_data = channel(api_type_list, api_user_channels)

    message = "通道信息：\n\n"
    for entry in channel_data:
        message += (
            f"通道名称: {entry['name']}\n"
            f"调用代码: {entry['code']}\n"
            f"金额范围: {entry['money_range']}\n"
            f"费率: {entry['rate']}\n"
            "-------------------------\n"
        )
    return message