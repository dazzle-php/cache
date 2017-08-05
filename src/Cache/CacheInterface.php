<?php

namespace Dazzle\Cache;

use Dazzle\Event\EventEmitterInterface;
use Dazzle\Loop\LoopResourceInterface;
use Dazzle\Promise\PromiseInterface;

/**
 * @event start : callable(CacheInterface)
 * @event stop  : callable(CacheInterface)
 * @event error : callable(CacheInterface, Error|Exception)
 */
interface CacheInterface extends EventEmitterInterface, LoopResourceInterface
{
    /**
     * Check if cache is started or not.
     *
     * @return bool
     */
    public function isStarted();

    /**
     * Start cache.
     *
     * @return PromiseInterface
     */
    public function start();

    /**
     * Stop cache immediately.
     *
     * @return PromiseInterface
     */
    public function stop();

    /**
     * Stop accepting new requests immediately and stop cache after executing last pending request.
     *
     * @return PromiseInterface
     */
    public function end();

    /**
     * Set new cache value. Setting ttl other than 0 will result in creating a timeout in this key.
     *
     * Set new cache value. Setting ttl other than 0 will result in creating a timeout in this key. Rejection is
     * triggered when value could not be saved.
     *
     * @param string $key
     * @param string|int|object|array $val
     * @param float $ttl
     * @return PromiseInterface
     */
    public function set($key, $val, $ttl = 0.0);

    /**
     * Get value stored in cache or null if it does not exist.
     *
     * Get value stored in cache or null if it does not exist. Rejection is triggered when key could not be get due
     * to other factors than ist not-being set. If the value is unset, the promise will resolve with null value.
     *
     * @param string $key
     * @return PromiseInterface
     */
    public function get($key);

    /**
     * Remove value stored under specified key. Returns true if key existed or false if did not.
     *
     * @param string $key
     * @return PromiseInterface
     */
    public function remove($key);

    /**
     * Check whether value exists.
     *
     * @param string $key
     * @return PromiseInterface
     */
    public function exists($key);

    /**
     * Set TTL on already existing key.
     *
     * @param string $key
     * @param float $ttl
     * @return PromiseInterface
     */
    public function setTtl($key, $ttl);

    /**
     * Get TTL of already existing key. Returns 0 if there is no TTL.
     *
     * @param string $key
     * @return PromiseInterface
     */
    public function getTtl($key);

    /**
     * Remove TTL of already existing key. Returns true if value existed or false if did not.
     *
     * @param string $key
     * @return PromiseInterface
     */
    public function removeTtl($key);

    /**
     * Check if TTL exists for specified key.
     *
     * @param string $key
     * @return PromiseInterface
     */
    public function existsTtl($key);

    /**
     * Get existing keys.
     *
     * @return PromiseInterface
     */
    public function getKeys();

    /**
     * Get cache stats.
     *
     * @return PromiseInterface
     */
    public function getStats();

    /**
     * Flush cache object.
     *
     * @return PromiseInterface
     */
    public function flush();
}
