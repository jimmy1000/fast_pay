<?php

namespace addons\qrcode\library;

/**
 * Referer 验证器
 */
class RefererValidator
{
    /**
     * 验证 Referer 是否在允许的字符串规则列表中
     *
     * 支持四种格式：
     *   "https://example.com/login/index.html"      → 完整 URL 匹配
     *   "https://example.com/login/"      → 路径 URL 匹配
     *   "example.com"                    → 域名匹配
     *   "*.example.com"                  → 通配域名匹配
     *   "*.example.com/login/"           → 通配域名 + 路径前缀匹配
     *
     * @param string $referer 字符串数组
     * @return bool 是否通过验证
     */
    public static function validate($referer = null)
    {
        $referer = $referer ?: ($_SERVER['HTTP_REFERER'] ?? '');
        $config = get_addon_config('qrcode');
        $allowRefererList = $config['allowrefererlist'] ?? '';
        $allowedReferers = explode("\n", str_replace("\r", "", $allowRefererList));
        $allowedReferers = array_filter(array_unique($allowedReferers));

        if (empty($referer)) {
            // 判断是否允许空 Referer
            if ($config['allowemptyreferer']) {
                return true;
            } else {
                return false;
            }
        }

        // 判断是否允许所有来源
        if (in_array('*', $allowedReferers)) {
            return true;
        }

        // $allowedReferers添加当前主机
        array_unshift($allowedReferers, request()->host(true));

        foreach ($allowedReferers as $rule) {
            if (empty($rule) || !is_string($rule)) {
                continue;
            }

            $ruleParsed = null;

            // 尝试解析规则：如果含 ://，直接 parse_url；否则补 http:// 再解析
            if (strpos($rule, '://') !== false) {
                // 完整URL匹配
                if ($rule === $referer) {
                    return true;
                }
                $ruleParsed = parse_url($rule);
            } else {
                // 补协议，避免 parse_url 失败
                $testUrl = 'http://' . ltrim($rule, '/');
                $ruleParsed = parse_url($testUrl);
            }

            if (!$ruleParsed || !isset($ruleParsed['host'])) {
                continue; // 解析失败，跳过
            }

            $ruleHost = $ruleParsed['host'];
            $rulePath = $ruleParsed['path'] ?? '/';

            // 解析实际 Referer
            $refParsed = parse_url($referer);
            if (!$refParsed || !isset($refParsed['host'])) {
                continue;
            }

            $refHost = strtolower($refParsed['host']);
            $refPath = $refParsed['path'] ?? '/';

            // 匹配域名（支持通配）
            if (!self::matchDomainPattern($refHost, $ruleHost)) {
                continue;
            }

            // 匹配路径前缀
            if (strpos($refPath, $rulePath) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * 匹配域名或通配域名（*.example.com）
     */
    private static function matchDomainPattern($refererHost, $pattern)
    {
        $pattern = strtolower($pattern);

        if (strpos($pattern, '*.') === 0) {
            $suffix = substr($pattern, 2); // 去掉 "*."
            $suffixWithDot = '.' . $suffix;
            if (strlen($refererHost) <= strlen($suffixWithDot)) {
                return false;
            }
            return substr($refererHost, -strlen($suffixWithDot)) === $suffixWithDot;
        } else {
            return $refererHost === strtolower($pattern);
        }
    }
}