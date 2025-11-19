<?php

namespace google;

/**
 * Google Authenticator 工具类
 * 实现 TOTP (Time-based One-Time Password) 算法
 */
class GoogleAuthenticator
{
    /**
     * 字符集（Base32编码）
     */
    private static $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * 生成随机密钥（16字符，Base32编码）
     * 
     * @param int $length 密钥长度，默认16
     * @return string
     */
    public static function generateSecret($length = 16)
    {
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= self::$chars[random_int(0, 31)];
        }
        return $secret;
    }

    /**
     * 生成二维码URL（用于Google Authenticator扫描）
     * 
     * @param string $name 账户名称（如：admin@fastadmin.com）
     * @param string $secret 密钥
     * @param string $issuer 发行者名称（如：FastAdmin）
     * @return string
     */
    public static function getQRCodeUrl($name, $secret, $issuer = 'FastAdmin')
    {
        $url = sprintf(
            'otpauth://totp/%s?secret=%s&issuer=%s',
            rawurlencode($name),
            $secret,
            rawurlencode($issuer)
        );
        return $url;
    }

    /**
     * 生成二维码图片URL（使用在线服务）
     * 
     * @param string $name 账户名称
     * @param string $secret 密钥
     * @param string $issuer 发行者名称
     * @param int $size 二维码尺寸，默认200
     * @return string
     */
    public static function getQRCodeImageUrl($name, $secret, $issuer = 'FastAdmin', $size = 200)
    {
        $url = self::getQRCodeUrl($name, $secret, $issuer);
        // 使用在线服务生成二维码图片
        return sprintf(
            'https://api.qrserver.com/v1/create-qr-code/?size=%dx%d&data=%s',
            $size,
            $size,
            rawurlencode($url)
        );
    }

    /**
     * 验证验证码
     * 
     * @param string $secret 密钥
     * @param string $code 用户输入的验证码
     * @param int $discrepancy 允许的时间窗口偏差（默认1，即前后各30秒）
     * @return bool
     */
    public static function verifyCode($secret, $code, $discrepancy = 1)
    {
        if (empty($secret) || empty($code)) {
            return false;
        }

        // 验证码必须是6位数字
        if (!preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        // 计算当前时间窗口
        $timeSlice = floor(time() / 30);

        // 检查当前时间窗口及前后各discrepancy个窗口
        for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
            $calculatedCode = self::getCode($secret, $timeSlice + $i);
            if (hash_equals($calculatedCode, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 获取指定时间窗口的验证码
     * 
     * @param string $secret 密钥
     * @param int $timeSlice 时间窗口
     * @return string 6位验证码
     */
    private static function getCode($secret, $timeSlice = null)
    {
        if ($timeSlice === null) {
            $timeSlice = floor(time() / 30);
        }

        // Base32解码
        $secretKey = self::base32Decode($secret);

        // 将时间窗口转换为8字节大端序
        $time = pack('N*', 0) . pack('N*', $timeSlice);

        // 使用HMAC-SHA1计算哈希
        $hash = hash_hmac('sha1', $time, $secretKey, true);

        // 动态截断
        $offset = ord($hash[19]) & 0xf;
        $code = (
            ((ord($hash[$offset + 0]) & 0x7f) << 24) |
            ((ord($hash[$offset + 1]) & 0xff) << 16) |
            ((ord($hash[$offset + 2]) & 0xff) << 8) |
            (ord($hash[$offset + 3]) & 0xff)
        ) % 1000000;

        // 返回6位数字，不足补0
        return str_pad($code, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Base32解码
     * 
     * @param string $secret Base32编码的字符串
     * @return string 二进制字符串
     */
    private static function base32Decode($secret)
    {
        if (empty($secret)) {
            return '';
        }

        $secret = strtoupper($secret);
        $secret = rtrim($secret, '=');
        $binaryString = '';

        for ($i = 0; $i < strlen($secret); $i += 8) {
            $chunk = substr($secret, $i, 8);
            $bits = 0;
            $value = 0;

            for ($j = 0; $j < strlen($chunk); $j++) {
                $char = $chunk[$j];
                $pos = strpos(self::$chars, $char);
                if ($pos === false) {
                    continue;
                }
                $value = ($value << 5) | $pos;
                $bits += 5;
            }

            $bytes = floor($bits / 8);
            for ($j = 0; $j < $bytes; $j++) {
                $binaryString .= chr(($value >> (8 * ($bytes - $j - 1))) & 0xFF);
            }
        }

        return $binaryString;
    }

    /**
     * 获取当前验证码（用于测试）
     * 
     * @param string $secret 密钥
     * @return string 6位验证码
     */
    public static function getCurrentCode($secret)
    {
        return self::getCode($secret);
    }
}

