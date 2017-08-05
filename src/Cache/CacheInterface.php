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
     * @return PromiseInterface<CacheInterface>
     */
    public function start();

    /**
     * Stop cache immediately.
     *
     * @return PromiseInterface<CasheInterface>
     */
    public function stop();

    /**
     * Stop accepting new requests immediately and stop cache after executing last pending request.
     *
     * @return PromiseInterface<CasheInterface>
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
     * @return PromiseInterface<mixed>
     */
    public function set($key, $val, $ttl = 0.0);

    /**
     * Get value stored in cache or null if it does not exist.
     *
     * Get value stored in cache or null if it does not exist. Rejection is triggered when key could not be get due
     * to other factors than ist not-being set. If the value is unset, the promise will resolve with null value.
     *
     * @param string $key
     * @return PromiseInterface<mixed|null>
     */
    public function get($key);

    /**
     * Remove value stored under specified key. Returns true if key existed or false if did not.
     *
     * @param string $key
     * @return PromiseInterface<bool>
     */
    public function remove($key);

    /**
     * Check whether value exists.
     *
     * @param string $key
     * @return PromiseInterface<bool>
     */
    public function exists($key);

    /**
     * Set TTL on already existing key.
     *
     * @param string $key
     * @param float $ttl
     * @return PromiseInterface<float>
     */
    public function setTtl($key, $ttl);

    /**
     * Get TTL of already existing key. Returns 0 if there is no TTL.
     *
     * @param string $key
     * @return PromiseInterface<float>
     */
    public function getTtl($key);

    /**
     * Remove TTL of already existing key. Returns true if value existed or false if did not.
     *
     * @param string $key
     * @return PromiseInterface<bool>
     */
    public function removeTtl($key);

    /**
     * Check if TTL exists for specified key.
     *
     * @param string $key
     * @return PromiseInterface<bool>
     */
    public function existsTtl($key);

    /**
     * Get existing keys.
     *
     * @return PromiseInterface<string[]>
     */
    public function getKeys();

    /**
     * Get cache stats.
     *
     * @return PromiseInterface<array>
     */
    public function getStats();

    /**
     * Flush cache object.
     *
     * @return PromiseInterface<void>
     */
    public function flush();
}
