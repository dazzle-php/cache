<?php

namespace Dazzle\Cache\Test\TUnit\Memory;

use Dazzle\Cache\Memory\MemoryCache;
use Dazzle\Cache\CacheInterface;
use Dazzle\Cache\Test\TUnit;
use Dazzle\Loop\Loop;
use Dazzle\Loop\LoopInterface;
use Dazzle\Promise\Promise;
use Dazzle\Promise\PromiseInterface;

class MemoryCacheTest extends TUnit
{
    /**
     *
     */
    public function testApiConstructor_CreatesProperInstance()
    {
        $cache = $this->createCache();
        $this->assertInstanceOf(CacheInterface::class, $cache);
    }

    /**
     *
     */
    public function testApiDestructor_DoesNotThrow()
    {
        $cache = $this->createCache();
        unset($cache);
    }

    /**
     *
     */
    public function testApiSet_RejectsPromise_WhenCacheIsNotStarted()
    {
        $cache = $this->createCache();
        $this->assertTrue($cache->set('KEY', 'VAL')->isRejected());
    }

    /**
     *
     */
    public function testApiGet_RejectsPromise_WhenCacheIsNotStarted()
    {
        $cache = $this->createCache();
        $this->assertTrue($cache->get('KEY')->isRejected());
    }

    /**
     *
     */
    public function testApiRemove_RejectsPromise_WhenCacheIsNotStarted()
    {
        $cache = $this->createCache();
        $this->assertTrue($cache->remove('KEY')->isRejected());
    }

    /**
     *
     */
    public function testApiExists_RejectsPromise_WhenCacheIsNotStarted()
    {
        $cache = $this->createCache();
        $this->assertTrue($cache->exists('KEY')->isRejected());
    }

    /**
     *
     */
    public function testApiSetTtl_RejectsPromise_WhenCacheIsNotStarted()
    {
        $cache = $this->createCache();
        $this->assertTrue($cache->setTtl('KEY', 1)->isRejected());
    }

    /**
     *
     */
    public function testApiGetTtl_RejectsPromise_WhenCacheIsNotStarted()
    {
        $cache = $this->createCache();
        $this->assertTrue($cache->getTtl('KEY')->isRejected());
    }

    /**
     *
     */
    public function testApiRemoveTtl_RejectsPromise_WhenCacheIsNotStarted()
    {
        $cache = $this->createCache();
        $this->assertTrue($cache->removeTtl('KEY')->isRejected());
    }

    /**
     *
     */
    public function testApiExistsTtl_RejectsPromise_WhenCacheIsNotStarted()
    {
        $cache = $this->createCache();
        $this->assertTrue($cache->existsTtl('KEY')->isRejected());
    }

    /**
     *
     */
    public function testApiGetKeys_RejectsPromise_WhenCacheIsNotStarted()
    {
        $cache = $this->createCache();
        $this->assertTrue($cache->getKeys()->isRejected());
    }

    /**
     *
     */
    public function testApiGetStats_RejectsPromise_WhenCacheIsNotStarted()
    {
        $cache = $this->createCache();
        $this->assertTrue($cache->getStats()->isRejected());
    }

    /**
     *
     */
    public function testApiFlush_RejectsPromise_WhenCacheIsNotStarted()
    {
        $cache = $this->createCache();
        $this->assertTrue($cache->flush()->isRejected());
    }

    /**
     * @param string[]|null $methods
     * @return Loop|LoopInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    public function createLoop($methods = null)
    {
        return $this->getMock(Loop::class, $methods, [], '', false);
    }

    /**
     * @param string[]|null $methods
     * @param LoopInterface $loop
     * @return CacheInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    public function createCache($methods = null, LoopInterface $loop = null)
    {
        $loop = $loop === null ? $this->createLoop() : $loop;
        return $this->getMock(MemoryCache::class, $methods, [ $loop ]);
    }
}
