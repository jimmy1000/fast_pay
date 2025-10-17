<?php

namespace addons\qrcode\library;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelHigh;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelLow;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelMedium;
use Endroid\QrCode\Label\Alignment\LabelAlignmentCenter;
use Endroid\QrCode\Label\Alignment\LabelAlignmentRight;
use Endroid\QrCode\Label\Font\Font;
use Endroid\QrCode\Label\Font\NotoSans;
use Endroid\QrCode\Label\Label;
use Endroid\QrCode\Label\Margin\Margin;
use Endroid\QrCode\Logo\Logo;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeMargin;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\Result\ResultInterface;
use Endroid\QrCode\Writer\SvgWriter;

class Service
{
    /**
     * 生成二维码
     * @param $params
     * @return ResultInterface
     */
    public static function qrcode($params)
    {
        $config = get_addon_config('qrcode');
        $params = is_array($params) ? $params : [$params];
        $params = array_merge($config, $params);

        $params['padding'] = intval($params['padding'] ?? $config['padding']);

        $params['labelfontpath'] = ROOT_PATH . 'public' . $config['labelfontpath'];
        $params['logopath'] = ROOT_PATH . 'public' . $config['logopath'];

        // 前景色
        list($r, $g, $b) = sscanf($params['foreground'] ?? $config['foreground'], "#%02x%02x%02x");
        $foregroundColor = new Color($r, $g, $b);

        // 背景色
        list($r, $g, $b) = sscanf($params['background'] ?? $config['background'], "#%02x%02x%02x");
        $backgroundColor = new Color($r, $g, $b);

        if ($params['label'] ?? '') {
            list($r, $g, $b) = sscanf($params['labelfontcolor'] ?? ($config['labelfontcolor'] ?? '#000000'), "#%02x%02x%02x");
            $labelTextColor = new Color($r, $g, $b);

            if (isset($params['labelalignment']) && in_array($params['labelalignment'], ['left', 'right', 'center'])) {
                $params['labelalignment'] = ucfirst($params['labelalignment']);
                $className = "\Endroid\QrCode\Label\Alignment\LabelAlignment{$params['labelalignment']}";
                $alignment = new $className();
            }
            //边距：上/右/下/左
            $labelMarginArr = [0, $params['padding'], $params['padding'], $params['padding']];
            if (isset($params['labelmargin'])) {
                $labelmargin = $params['labelmargin'];
                $labelMarginArrTemp = explode(' ', $labelmargin);
                if (count($labelMarginArrTemp) < 4) {
                    $labelMarginArr = array_merge($labelMarginArrTemp, array_slice($labelMarginArr, count($labelMarginArrTemp), 4 - count($labelMarginArrTemp)));
                } else {
                    $labelMarginArr = $labelMarginArrTemp;
                }
            }
            // 底边距最小2，避免文本被截断
            $labelMarginArr[2] = max(2, $labelMarginArr[2]);
            $label = Label::create($params['label'])
                ->setFont(new Font($params['labelfontpath'], $params['labelfontsize']))
                ->setAlignment($alignment ?? new LabelAlignmentCenter())
                ->setMargin(new Margin(...$labelMarginArr))
                ->setTextColor($labelTextColor);
        }

        // Logo
        if (isset($params['logo']) && $params['logo'] && $params['logopath'] && file_exists($params['logopath'])) {
            $logo = Logo::create($params['logopath'])
                ->setResizeToWidth($params['logosize']);
        }

        if (($params['errorlevel'] ?? '') && in_array($params['errorlevel'], ['low', 'medium', 'quartile', 'high'])) {
            $params['errorlevel'] = ucfirst($params['errorlevel']);
            $className = "\Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevel{$params['errorlevel']}";
            $errorCorrectionLevel = new $className();
        }

        // 二维码
        $qrcode = QrCode::create($params['text'] ?? $config['text'])
            ->setEncoding(new Encoding('UTF-8'))
            ->setErrorCorrectionLevel($errorCorrectionLevel ?? new ErrorCorrectionLevelMedium())
            ->setSize(intval($params['size'] ?? $config['size']))
            ->setMargin($params['padding'])
            ->setRoundBlockSizeMode(new RoundBlockSizeModeMargin())
            ->setForegroundColor($foregroundColor)
            ->setBackgroundColor($backgroundColor);

        $write = isset($params['format']) && $params['format'] === 'svg' ? new SvgWriter() : new PngWriter();
        $result = $write->write($qrcode, $logo ?? null, $label ?? null, ['exclude_xml_declaration' => true]);
        return $result;
    }
}
