# utils/formatters.py

from typing import Dict

def format_order_status(order_data: Dict) -> str:
    """格式化代收订单状态信息"""
    status_mapping = {
        '0': '未支付',
        '1': '支付成功',
        '2': '扣量订单',
    }
    notify_mapping = {
        '0': '未通知',
        '1': '通知成功',
        '2': '通知失败',
    }

    return (
        f"系统订单号：{order_data.get('sys_orderno', '')}\n"
        f"商户订单号：{order_data.get('orderno', '')}\n"
        f"订单金额：{order_data.get('total_money', '')}\n"
        f"扣除手续费后金额：{order_data.get('have_money', '')}\n"
        f"upi：{order_data.get('upi', '')}\n"
        f"订单状态：{status_mapping.get(str(order_data.get('status')), '未知')}\n"
        f"通知状态：{notify_mapping.get(str(order_data.get('notify_status')), '未知')}\n"
        f"订单类型：代收"
    )


def format_pay_status(pay_data: Dict) -> str:
    """格式化代付订单状态信息"""
    status_mapping = {
        '0': '申请中',
        '1': '已支付',
        '2': '冻结',
        '3': '取消',
        '4': '失败',
    }
    notify_mapping = {
        '0': '未通知',
        '1': '通知成功',
        '2': '通知失败',
    }

    return (
        f"金额：{pay_data.get('money', '')}\n"
        f"手续费：{pay_data.get('charge', '')}\n"
        f"商户订单号：{pay_data.get('orderno', '')}\n"
        f"订单状态：{status_mapping.get(str(pay_data.get('status')), '未知')}\n"
        f"订单信息：{pay_data.get('msg', '')}\n"
        f"utr：{pay_data.get('utr', '')}\n"
        f"通知状态：{notify_mapping.get(str(pay_data.get('notify_status')), '未知')}\n"
        f"订单类型：代付"
    )
