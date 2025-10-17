<?php

namespace addons\qrcode\library;

use think\Response;

/**
 * 图片生成器
 */
class ImageGenerator
{

    /**
     * 生成一个包含错误信息的图片
     *
     * @param string $message 错误信息
     * @return Response
     */
    public static function generate(string $message)
    {
        $config = get_addon_config('qrcode');

        // 设置字体路径（确保该字体存在且可读）
        $fontPath = ROOT_PATH . 'public' . $config['labelfontpath'];

        $width = 300;
        $height = 300;

        // 创建画布
        $im = imagecreatetruecolor($width, $height);
        $bgColor = imagecolorallocate($im, 255, 255, 255); // 白色背景
        $textColor = imagecolorallocate($im, 255, 0, 0);   // 红色文字

        imagefilledrectangle($im, 0, 0, $width, $height, $bgColor);

        // 使用 imagettftext 支持中文
        $fontSize = 14;
        $angle = 0;

        // 计算文本居中位置
        $textBox = imagettfbbox($fontSize, $angle, $fontPath, $message);
        if ($textBox === false) {
            $x = 10;
            $y = 50;
        } else {
            $textWidth = $textBox[2] - $textBox[0];
            $textHeight = $textBox[1] - $textBox[5];
            $x = ($width - $textWidth) / 2;
            $y = ($height + $textHeight) / 2;
        }

        // 绘制文本
        imagettftext($im, $fontSize, $angle, $x, $y, $textColor, $fontPath, $message);

        // 输出图片
        ob_start();
        imagepng($im);
        imagedestroy($im);
        $imageData = ob_get_clean();

        return Response::create($imageData, 'image/png')->header('Content-Type', 'image/png');
    }

}