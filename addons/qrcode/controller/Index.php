<?php

namespace addons\qrcode\controller;

use addons\qrcode\library\ImageGenerator;
use addons\qrcode\library\RefererValidator;
use addons\qrcode\library\RegexValidator;
use think\addons\Controller;
use think\exception\HttpResponseException;
use think\Response;

/**
 * 二维码生成
 *
 */
class Index extends Controller
{
    public function index()
    {
        $config = get_addon_config('qrcode');
        $this->assign('config', $config);
        return $this->view->fetch();
    }

    /**
     * 生成二维码图片
     *
     * @return Response
     */
    public function build()
    {
        $config = get_addon_config('qrcode');

        // 限制来源
        if (isset($config['limitreferer']) && $config['limitreferer']) {
            $referer = $this->request->server('HTTP_REFERER', '');

            if (!RefererValidator::validate($referer)) {
                return ImageGenerator::generate('暂无权限:来源不匹配');
            }
        }

        $params = $this->request->get();
        $params = array_intersect_key($params, array_flip(['text', 'size', 'padding', 'errorlevel', 'foreground', 'background', 'logo', 'logosize', 'logopath', 'label', 'labelmargin', 'labelfontsize', 'labelalignment', 'labelfontcolor']));

        $text = $this->request->get('text', $config['text'], 'trim');
        $label = $this->request->get('label', $config['label'], 'trim');

        $limitTextRegex = $config['limittextregex'] ?? '';
        $limitLabelRegex = $config['limitlabelregex'] ?? '';

        // 验证字符串是否符合正则
        try {
            if ($limitTextRegex) {
                RegexValidator::validate($text, $limitTextRegex);
            }
            if ($limitLabelRegex) {
                RegexValidator::validate($label, $limitLabelRegex);
            }
        } catch (HttpResponseException $e) {
            $response = $e->getResponse();
            return ImageGenerator::generate($response->getContent());
        } catch (\Exception $e) {
            // 生成错误提示图片并输出
            return ImageGenerator::generate($e->getMessage());
        }

        $params['text'] = $text;
        $params['label'] = $label;

        try {
            $qrCode = \addons\qrcode\library\Service::qrcode($params);
        } catch (\Exception $e) {
            return ImageGenerator::generate($e->getMessage());
        }

        $mimetype = $config['format'] == 'png' ? 'image/png' : 'image/svg+xml';

        $response = Response::create()->header("Content-Type", $mimetype);

        header('Content-Type: ' . $qrCode->getMimeType());
        $response->content($qrCode->getString());

        // 写入文件
        if ($config['writefile']) {
            $qrcodePath = ROOT_PATH . 'public/uploads/qrcode/';
            if (!is_dir($qrcodePath)) {
                @mkdir($qrcodePath);
            }
            if (is_really_writable($qrcodePath)) {
                $filePath = $qrcodePath . md5(implode('', $params)) . '.' . $config['format'];
                $qrCode->saveToFile($filePath);
            }
        }

        return $response;
    }
}
