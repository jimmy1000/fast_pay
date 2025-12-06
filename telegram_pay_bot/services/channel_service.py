# services/channel_service.py

from database.db import get_channel_info_data, get_api_channel_data, select_channel_id, get_open_list, get_list_by_user, select_merchant_id

def channel(api_type_list, api_user_channels):
    result = []
    for type in api_type_list:
        type_id = type['id']
        rule_id = type['api_rule_id']
        rate_flag = False

        if type_id in api_user_channels:
            user_channel = api_user_channels[type_id]
            rule_id = user_channel['api_rule_id'] if user_channel['api_rule_id'] != 0 else rule_id
            rate_flag = user_channel['rate'] > 0

            if user_channel['status'] == '0':
                continue

        if rule_id == 0:
            continue

        channel_info = get_channel_info(rule_id)

        if not channel_info:
            continue

        if rate_flag:
            channel_info['rate_list'] = [user_channel['rate']]

        type_text = ''
        if channel_info['info']['type'] == '0':
            type_text = '单通道模式'
        elif channel_info['info']['type'] == '1':
            type_text = '顺序轮询'
        elif channel_info['info']['type'] == '2':
            type_text = '随机轮询'

        result.append({
            'name': type['name'],
            'id': type['id'],
            'code': type['code'],
            'rule_type': channel_info['info']['type'],
            'rule_type_text': type_text,
            'rate': ','.join(map(str, channel_info['rate_list'])),
            'money_range': ','.join(map(str, channel_info['money_range_list'])),
            'total': channel_info['total'],
            'has': channel_info['has']
        })
    return result


def get_channel_info(id, is_sort=True, is_usable=False, money=0):
    row = get_channel_info_data(id)
    if row is None:
        return {}

    if not isinstance(row, dict):
        row = dict(row)

    if not row.get('api_account_ids'):
        return {}

    account_list_str  = row['api_account_ids']
    account_list = [item.split(':')[0] for item in account_list_str.split(',')]

    rate_list = []
    money_range_list = []
    total = 0
    has = 0

    for key, account in enumerate(account_list):
        channel_model = get_api_channel_data(account, row['api_type_id'])
        if channel_model is None:
            continue

        if is_usable:
            flag = False
            if channel_model['daymoney'] > 0 and (channel_model['todaymoney'] + money) >= float(channel_model['daymoney']):
                flag = True
            if channel_model['minmoney'] > 0 and money < channel_model['minmoney']:
                flag = True
            if channel_model['maxmoney'] > 0 and money > channel_model['maxmoney']:
                flag = True
            if flag:
                continue

        rate_list.append(channel_model['rate'])
        money_range_list.append(f"{channel_model['minmoney']}-{channel_model['maxmoney']}")
        total += channel_model['daymoney']
        has += channel_model['todaymoney']

    if is_sort:
        rate_list = sorted(set(rate_list))

    money_range_list = list(set(money_range_list))

    return {
        'info': row,
        'rate_list': rate_list,
        'money_range_list': money_range_list,
        'total': total,
        'has': has
    }


async def get_channel_info_text(group_chat_id):
    merchant_id_data = select_merchant_id(group_chat_id)
    if not merchant_id_data:
        return "❌ 未绑定商户号，请使用 /bind 绑定商户号"

    merchant_id = merchant_id_data[0]['merchant_id']
    user_id = select_channel_id(merchant_id)
    api_type_list = get_open_list()
    api_user_channels = get_list_by_user(user_id)
    channel_data = channel(api_type_list, api_user_channels)

    if not channel_data:
        return "⚠️ 没有可用通道"

    message = "📡 通道信息：\n\n"
    for entry in channel_data:
        message += (
            f"通道名称: {entry['name']}\n"
            f"调用代码: {entry['code']}\n"
            f"金额范围: {entry['money_range']}\n"
            f"费率: {entry['rate']}\n"
            "-------------------------\n"
        )
    return message
