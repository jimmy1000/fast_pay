<?php
namespace app\api\library\traits;

use fast\Rsa;

trait Api{


    /**
     * 验签
     * @param $params
     * @param $md5
     * @param $pub_key
     * @return bool
     */
    public function verifySign($params,$md5,$pub_key){

        //如果存在gateway和bankcode 移除掉
        if(!empty($params['gateway']) && !empty($params['bankcode'])){
            unset($params['gateway'],$params['bankcode']);
        }

        return verifyApiSign($params,$md5,$pub_key);
    }



    /**
     * 安全IP检测，支持IP段检测
     * @param string $ip 要检测的IP
     * @param string|array $ips  白名单IP或者黑名单IP
     * @return boolean true 在白名单或者黑名单中，否则不在
     */
    public function ip_match($ip,$ips=""){

        if($ips){
            if(is_string($ips)){ //ip用"," 例如白名单IP：192.168.1.13,123.23.23.44,193.134.*.*
                $ips = explode(",", $ips);
            }
        }else{
            //读取后台配置 白名单IP
            $ips = explode("\r\n", config('site.forbiddenip'));
        }
        if(in_array($ip, $ips)){
            return true;
        }
        $ipregexp = implode('|', str_replace( array('*','.'), array('\d+','\.') ,$ips));
        $rs = preg_match("/^(".$ipregexp.")$/", $ip);
        if($rs) return true;
        return false;
    }
}