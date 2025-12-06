# database/db.py

import pymysql
import json
from config.settings import settings

def connect_to_database():
    try:
        return pymysql.connect(
            host=settings.DB_HOST,
            port=settings.DB_PORT,
            user=settings.DB_USER,
            password=settings.DB_PASSWORD,
            database=settings.DB_NAME,
            cursorclass=pymysql.cursors.DictCursor
        )
    except Exception as e:
        print(f"❌ 数据库连接失败: {e}")
        return None

def query_order_status(orderno):
    conn = connect_to_database()
    try:
        with conn.cursor() as cursor:
            cursor.execute("SELECT * FROM ep_order WHERE orderno = %s", (orderno,))
            return cursor.fetchall()
    finally:
        conn.close()

def query_pay_status(orderno):
    conn = connect_to_database()
    try:
        with conn.cursor() as cursor:
            cursor.execute("SELECT * FROM ep_pay WHERE orderno LIKE %s", ('%' + orderno + '%',))
            return cursor.fetchall()
    finally:
        conn.close()

def insert_order_into(data):
    conn = connect_to_database()
    try:
        with conn.cursor() as cursor:
            sql = """
                INSERT INTO ep_telegram_bot_order
                (group_chat_id, message_id, group_title, orderno, sys_orderno, utr, status, createtime, updatetime)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s)
            """
            cursor.execute(sql, (
                data['group_chat_id'],
                data['message_id'],
                data['group_title'],
                data['orderno'],
                data['sys_orderno'],
                data['utr'],
                data['status'],
                data['createtime'],
                data['updatetime'],
            ))
            conn.commit()
            return True
    except Exception as e:
        print(f"❌ 插入订单记录失败: {e}")
        conn.rollback()
        return False
    finally:
        conn.close()

def update_order_status_and_return(sys_orderno, status):
    """
    更新订单状态，并返回 group_chat_id、message_id、orderno、utr 用于补单或防骗提示。
    不使用 RETURNING，兼容 PyMySQL。
    """
    conn = connect_to_database()
    try:
        with conn.cursor() as cursor:
            # 1. 更新订单状态
            update_sql = """
                UPDATE ep_telegram_bot_order
                SET status = %s, updatetime = UNIX_TIMESTAMP()
                WHERE sys_orderno = %s
            """
            cursor.execute(update_sql, (str(status), sys_orderno))
            conn.commit()

            # 2. 查询更新后的订单数据
            select_sql = """
                SELECT group_chat_id, message_id, orderno, utr
                FROM ep_telegram_bot_order
                WHERE sys_orderno = %s
                LIMIT 1
            """
            cursor.execute(select_sql, (sys_orderno,))
            result = cursor.fetchone()
            return result

    except Exception as e:
        print(f"❌ 更新订单状态失败: {e}")
        conn.rollback()
        return None
    finally:
        conn.close()



def select_merchant_id_query(merchant_id):
    conn = connect_to_database()
    try:
        with conn.cursor() as cursor:
            cursor.execute("SELECT * FROM ep_user WHERE merchant_id = %s", (merchant_id,))
            return cursor.fetchall()
    finally:
        conn.close()

def select_merchant_id(group_chat_id):
    conn = connect_to_database()
    try:
        with conn.cursor() as cursor:
            cursor.execute("SELECT * FROM ep_telegram_bind WHERE group_chat_id = %s", (group_chat_id,))
            return cursor.fetchall()
    finally:
        conn.close()

def insert_merchant_id_query(merchant_id, group_chat_id, group_title, createtime):
    conn = connect_to_database()
    try:
        with conn.cursor() as cursor:
            cursor.execute(
                "INSERT INTO ep_telegram_bind (merchant_id, group_chat_id, group_title, createtime, updatetime) "
                "VALUES (%s, %s, %s, %s, %s)",
                (merchant_id, group_chat_id, group_title, createtime, createtime)
            )
            conn.commit()
            return cursor.rowcount
    except Exception as e:
        print(f"⚠️ 绑定商户失败: {e}")
        conn.rollback()
        return 0
    finally:
        conn.close()

def group_chat_id_query(api_account_id):
    conn = connect_to_database()
    try:
        with conn.cursor() as cursor:
            cursor.execute("SELECT * FROM ep_api_account WHERE id = %s", (api_account_id,))
            result = cursor.fetchone()
            if result:
                return json.loads(result['params']).get('group_chat_id')
            return None
    finally:
        conn.close()

def select_channel_id(merchant_id):
    conn = connect_to_database()
    try:
        with conn.cursor() as cursor:
            cursor.execute("SELECT * FROM ep_user WHERE merchant_id=%s", (merchant_id,))
            user = cursor.fetchone()
            return user['id'] if user else []
    finally:
        conn.close()

def get_open_list():
    conn = connect_to_database()
    try:
        with conn.cursor() as cursor:
            cursor.execute("SELECT * FROM ep_api_type WHERE status='1'")
            return cursor.fetchall()
    finally:
        conn.close()

def get_list_by_user(user_id):
    conn = connect_to_database()
    try:
        with conn.cursor() as cursor:
            cursor.execute("SELECT * FROM ep_user_apichannel WHERE user_id=%s", (user_id,))
            result = cursor.fetchall()
            return {item['api_type_id']: item for item in result}
    finally:
        conn.close()

def get_channel_info_data(rule_id):
    conn = connect_to_database()
    try:
        with conn.cursor() as cursor:
            cursor.execute("SELECT * FROM ep_api_rule WHERE id=%s", (rule_id,))
            return cursor.fetchone()
    finally:
        conn.close()

def get_api_channel_data(account, api_type_id):
    conn = connect_to_database()
    try:
        with conn.cursor() as cursor:
            cursor.execute(
                "SELECT * FROM ep_api_channel WHERE api_account_id=%s AND api_type_id=%s",
                (account, api_type_id)
            )
            return cursor.fetchone()
    finally:
        conn.close()

def get_all_bound_group_ids():
    conn = connect_to_database()
    try:
        with conn.cursor() as cursor:
            cursor.execute("SELECT DISTINCT group_chat_id FROM ep_telegram_bind")
            result = cursor.fetchall()
            return [row['group_chat_id'] for row in result]
    finally:
        conn.close()

def get_group_chat_id_by_merchant(merchant_id):
    conn = connect_to_database()
    try:
        with conn.cursor() as cursor:
            sql = "SELECT * FROM ep_telegram_bind WHERE merchant_id = %s LIMIT 1"
            cursor.execute(sql, (merchant_id,))
            result = cursor.fetchall()
        return result
    finally:
        conn.close()


