<?php

namespace fast;

use RuntimeException;

/**
 * Simple Redis based distributed lock helper.
 */
class RedisLock
{
    /**
     * @var \Redis
     */
    protected $redis;

    /**
     * @var array env没有就去这里的默认值
     */
    protected $options = [
        'host'       => '127.0.0.1',
        'port'       => 6379,
        'timeout'    => 0,
        'password'   => '',
        'select'     => 0,
        'prefix'     => 'lock:',
    ];

    /**
     * RedisLock constructor.
     *
     * @param array $options
     * @throws RuntimeException
     */
    public function __construct(array $options = [])
    {
        if (!extension_loaded('redis')) {
            throw new RuntimeException('Redis extension is not installed.');
        }

        $this->options = array_merge($this->options, $options);
        $this->redis   = new \Redis();
        $connected      = $this->redis->connect($this->options['host'], $this->options['port'], $this->options['timeout']);
        if (!$connected) {
            throw new RuntimeException('Unable to connect to Redis server.');
        }
        if (!empty($this->options['password'])) {
            if (!$this->redis->auth($this->options['password'])) {
                throw new RuntimeException('Redis authentication failed.');
            }
        }
        if (isset($this->options['select'])) {
            $this->redis->select((int)$this->options['select']);
        }
    }

    /**
     * Acquire a lock.
     *
     * @param string $name
     * @param int    $ttl    TTL in milliseconds
     * @return array|false
     */
    public function lock(string $name, int $ttl = 3000)
    {
        $key   = $this->options['prefix'] . $name;
        $token = uniqid('', true);

        $result = $this->redis->set($key, $token, ['nx', 'px' => $ttl]);
        if ($result) {
            return ['key' => $key, 'token' => $token];
        }

        return false;
    }

    /**
     * Release a lock.
     *
     * @param array $lock
     * @return bool
     */
    public function unlock(array $lock): bool
    {
        if (empty($lock['key']) || empty($lock['token'])) {
            return false;
        }

        $current = $this->redis->get($lock['key']);
        if ($current === $lock['token']) {
            $this->redis->del($lock['key']);
            return true;
        }

        return false;
    }
}

