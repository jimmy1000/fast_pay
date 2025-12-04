<?php

return array (
  0 => 
  array (
    'name' => 'secretkey',
    'title' => '安全码',
    'type' => 'string',
    'content' => 
    array (
    ),
    'value' => 'BJmFuVMPUl51zGKb',
    'rule' => 'required',
    'msg' => '',
    'tip' => '监听app需要该值，请注意一定不要泄露。',
    'ok' => '',
    'extend' => '',
  ),
  1 => 
  array (
    'name' => 'vietqrpaymax',
    'title' => 'VietQR随机立减',
    'type' => 'string',
    'content' => 
    array (
    ),
    'value' => '100',
    'rule' => 'required',
    'msg' => '',
    'tip' => 'VietQR随机立减金额，单位为1 ',
    'ok' => '',
    'extend' => '',
  ),
  2 => 
  array (
    'name' => 'alipaymax',
    'title' => '支付宝随机立减金额',
    'type' => 'string',
    'content' => 
    array (
    ),
    'value' => '100',
    'rule' => 'required',
    'msg' => '',
    'tip' => '支付宝随机立减金额，单位为0.01 ',
    'ok' => '',
    'extend' => '',
  ),
  3 => 
  array (
    'name' => 'orderValidity',
    'title' => '订单金额有效期（分钟）',
    'type' => 'string',
    'content' => 
    array (
    ),
    'value' => '3',
    'rule' => 'required',
    'msg' => '',
    'tip' => '订单金额有效期，超过这个时间释放金额',
    'ok' => '',
    'extend' => '',
  ),
  4 => 
  array (
    'name' => 'domain',
    'title' => '域名列表',
    'type' => 'text',
    'content' => 
    array (
    ),
    'value' => 'http://eapay.com',
    'rule' => 'required',
    'msg' => '',
    'tip' => '域名列表,一行一个',
    'ok' => '',
    'extend' => '',
  ),
  5 => 
  array (
    'name' => 'theme',
    'title' => '皮肤',
    'type' => 'string',
    'content' => 
    array (
    ),
    'value' => 'default',
    'rule' => 'required',
    'msg' => '',
    'tip' => '',
    'ok' => '',
    'extend' => '',
  ),
);
