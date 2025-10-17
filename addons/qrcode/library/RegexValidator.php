<?php

namespace addons\qrcode\library;

use think\exception\HttpResponseException;
use think\Response;

/**
 * 正则验证器
 */
class RegexValidator
{

    /**
     * 验证字符串是否符合至少一个正则表达式
     * @param string $input       要验证的字符串
     * @param string $regexConfig 多行正则表达式字符串
     * @throws HttpResponseException
     */
    public static function validate(string $input, string $regexConfig): void
    {
        $regexList = explode("\n", str_replace("\r", "", $regexConfig));
        $regexList = array_unique(array_filter($regexList)); // 去除空行并去重

        if (empty($regexList)) {
            return; // 没有配置正则时跳过验证
        }

        foreach ($regexList as $pattern) {
            set_error_handler(function (int $errno, string $errstr) use ($pattern) {
                throw new \RuntimeException("系统错误:正则表达式错误");
            });

            try {
                $result = @preg_match($pattern, $input);
                restore_error_handler();

                if ($result === false) {
                    // 正则语法错误
                    $response = Response::create('系统错误:服务器内部错误', 'html', 500);
                    throw new HttpResponseException($response);
                }

                if ($result === 1) {
                    // 匹配成功，直接返回，不再继续判断
                    return;
                }
            } catch (\Throwable $e) {
                restore_error_handler();
                throw $e;
            }
        }

        // 所有正则都不匹配，抛出错误
        $response = Response::create('参数错误:正则不匹配', 'html', 400);
        throw new HttpResponseException($response);
    }

}