<?php
use think\Env;

return [
    'connector' => 'Redis',
    'default'   => Env::get('queue.default', 'default'),
    'host'      => Env::get('queue.host', '127.0.0.1'),
    'port'      => Env::get('queue.port', 6379),
    'password'  => Env::get('queue.password', ''),
    'select'    => Env::get('queue.select', 0),
    'timeout'   => Env::get('queue.timeout', 0),
    'persistent'=> Env::get('queue.persistent', false),
];
