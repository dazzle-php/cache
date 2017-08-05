<?php

namespace Dazzle\Cache\Test\TModule;

use Dazzle\Cache\Cache;
use Dazzle\Cache\CacheInterface;
use Dazzle\Cache\Test\_Simulation\SimulationInterface;
use Dazzle\Cache\Test\TModule;
use Dazzle\Loop\LoopInterface;
use Dazzle\Promise\Promise;
use Dazzle\Promise\PromiseInterface;

class CacheTest extends TModule
{
    const TIMEOUT_TTL = 5e-1;

    const TIMEOUT_EPS = 1e-1;

    /**
     *
     */
    public function testCache_StartsAndStops_ResolvesPromisesAndEmitsEvents()
    {
        $sim = $this->simulate(function(SimulationInterface $sim) {
            $loop = $sim->getLoop();
            $cache = $this->createCache($loop);

            $cache->on('start', function($passedCache) use($cache, $sim) {
                $this->assertSame($cache, $passedCache);
                $sim->done();
            });
            $cache->on('stop', function($passedCache) use($cache) {
                $this->assertSame($cache, $passedCache);
            });

            $sim->onStart(function() use($cache) {
                $cache->start()->done(function($resolvedCache) use($cache) {
                    $this->assertSame($cache, $resolvedCache);
                });
            });
            $sim->onStop(function() use($cache) {
                $cache->stop()->done(function($resolvedCache) use($cache) {
                    $this->assertSame($cache, $resolvedCache);
                });
            });
        });
    }

    /**
     *
     */
    public function testCache_Ends_WhenThereAreNoTTLs()
    {
        $sim = $this->simulate(function(SimulationInterface $sim) {
            $loop = $sim->getLoop();
            $cache = $this->createCache($loop);

            $cache->on('start', function(CacheInterface $cache) {
                $cache->end();
            });
            $cache->on('stop', function(CacheInterface $cache) use($sim) {
                $sim->done();
            });

            $sim->onStart(function() use($cache) {
                $cache->start();
            });
        });
    }

    /**
     *
     */
    public function testCache_Ends_WhenLastTTLHasBeenFlushed()
    {
        $sim = $this->simulate(function(SimulationInterface $sim) {
            $loop = $sim->getLoop();
            $cache = $this->createCache($loop);
            $time = 0;

            $cache->on('start', function(CacheInterface $cache) use(&$time) {
                $time = microtime(true);
                /** @var PromiseInterface $promise */
                $promise = Promise::doResolve();
                $promise = $promise->then(function() use($cache) {
                    return $cache->set('TEST', 'VAL', static::TIMEOUT_TTL);
                });
                $promise = $promise->then(function() use($cache) {
                    return $cache->end();
                });
                return $promise;
            });
            $cache->on('stop', function(CacheInterface $cache) use(&$time, $sim) {
                $time = microtime(true) - $time;

                if ($time >= static::TIMEOUT_TTL && $time <= static::TIMEOUT_TTL + static::TIMEOUT_EPS)
                {
                    $sim->done();
                }
                else
                {
                    $sim->fail('End triggered stop with wrong timeout.');
                }
            });

            $sim->onStart(function() use($cache) {
                $cache->start();
            });
        });
    }

    /**
     *
     */
    public function testCache_StartsAndStops_Repeatedly()
    {
        $sim = $this->simulate(function(SimulationInterface $sim) {
            $loop = $sim->getLoop();
            $cache = $this->createCache($loop);

            $cache->once('start', function(CacheInterface $cache) use($sim) {
                /** @var PromiseInterface $promise */
                $promise = Promise::doResolve();
                $promise = $promise->then([ $cache, 'stop' ]);
                $promise = $promise->then([ $cache, 'start' ]);
                $promise = $promise->then(function() use($cache) {
                    return $cache->set('TEST_KEY', 'TEST_VAL');
                });
                $promise = $promise->then(function() use($cache) {
                    return $cache->get('TEST_KEY');
                });
                $promise = $promise->then(function() use($cache) {
                    return $cache->exists('TEST_KEY')->then(function($result) {
                        $this->assertSame(true, $result);
                    });
                });
                $promise = $promise->then([ $cache, 'stop' ]);
                $promise = $promise->then(function() use($sim) {
                    $sim->emit('pass');
                });
                $promise = $promise->then(null, function($ex) use($sim) {
                    $sim->fail($ex);
                });
                return $promise;
            });
            $cache->on('stop', function(CacheInterface $cache) use($sim) {
                $sim->emit('pass');
            });

            $sim->onStart(function() use($cache) {
                $cache->start();
            });
            $sim->delayOnce('pass', 3, function() use($sim) {
                $sim->done();
            });
        });
    }

    /**
     *
     */
    public function testCache_IsAbleToSetAndGetValues()
    {
        $sim = $this->simulate(function(SimulationInterface $sim) {
            $loop = $sim->getLoop();
            $cache = $this->createCache($loop);

            $cache->on('start', function(CacheInterface $cache) use($sim) {
                return Promise::doResolve()
                    ->then(function() use($cache) {
                        return $cache->get('TEST_KEY');
                    })
                    ->then(function($val) use($cache) {
                        $this->assertSame(null, $val);
                        return $cache->set('TEST_KEY', [ 'data' => 'A' ]);
                    })
                    ->then(function($val) use($cache) {
                        $this->assertSame([ 'data' => 'A' ], $val);
                        return $cache->get('TEST_KEY');
                    })
                    ->then(function($val) use($cache) {
                        $this->assertSame([ 'data' => 'A' ], $val);
                    })
                    ->done([ $sim, 'done' ], [ $sim, 'fail' ]);
            });

            $sim->onStart(function() use($cache) {
                $cache->start();
            });
            $sim->onStop(function() use($cache) {
                $cache->stop();
            });
        });
    }

    /**
     *
     */
    public function testCache_IsAbleToRemoveAndCheckExistanceOfValues()
    {
        $sim = $this->simulate(function(SimulationInterface $sim) {
            $loop = $sim->getLoop();
            $cache = $this->createCache($loop);

            $cache->on('start', function(CacheInterface $cache) use($sim) {
                return Promise::doResolve()
                    ->then(function() use($cache) {
                        return $cache->exists('TEST_KEY')->then(function($result) {
                            $this->assertSame(false, $result);
                        });
                    })
                    ->then(function() use($cache) {
                        return $cache->set('TEST_KEY', [ 'data' => 'A' ]);
                    })
                    ->then(function() use($cache) {
                        return $cache->exists('TEST_KEY')->then(function($result) {
                            $this->assertSame(true, $result);
                        });
                    })
                    ->then(function() use($cache) {
                        return $cache->remove('TEST_KEY');
                    })
                    ->then(function() use($cache) {
                        return $cache->exists('TEST_KEY')->then(function($result) {
                            $this->assertSame(false, $result);
                        });
                    })
                    ->done([ $sim, 'done' ], [ $sim, 'fail' ]);
            });

            $sim->onStart(function() use($cache) {
                $cache->start();
            });
            $sim->onStop(function() use($cache) {
                $cache->stop();
            });
        });
    }

    /**
     *
     */
    public function testCache_IsAbleToSetTTL_UsingSetTTLMethod()
    {
        $sim = $this->simulate(function(SimulationInterface $sim) {
            $loop = $sim->getLoop();
            $cache = $this->createCache($loop);

            $cache->on('start', function(CacheInterface $cache) use($sim) {
                return Promise::doResolve()
                    ->then(function() use($cache) {
                        return $cache->set('TEST_KEY', [ 'data' => 'A' ]);
                    })
                    ->then(function() use($cache) {
                        return $cache->exists('TEST_KEY')->then(function($result) {
                            $this->assertSame(true, $result);
                        });
                    })
                    ->then(function($val) use($cache) {
                        return $cache->setTtl('TEST_KEY', static::TIMEOUT_TTL);
                    })
                    ->then(function() use($cache) {
                        return $cache->exists('TEST_KEY')->then(function($result) {
                            $this->assertSame(true, $result);
                        });
                    })
                    ->then(function() use($cache, $sim) {
                        return new Promise(function($resolve, $reject) use($sim) {
                            $ttl = static::TIMEOUT_TTL + static::TIMEOUT_EPS;
                            $sim->getLoop()->addTimer($ttl, function() use($resolve, $reject) {
                                return $resolve();
                            });
                        });
                    })
                    ->then(function() use($cache) {
                        return $cache->exists('TEST_KEY')->then(function($result) {
                            $this->assertSame(false, $result);
                        });
                    })
                    ->done([ $sim, 'done' ], [ $sim, 'fail' ]);
            });

            $sim->onStart(function() use($cache) {
                $cache->start();
            });
            $sim->onStop(function() use($cache) {
                $cache->stop();
            });
        });
    }

    /**
     *
     */
    public function testCache_IsAbleToSetTTL_UsingSetMethod()
    {
        $sim = $this->simulate(function(SimulationInterface $sim) {
            $loop = $sim->getLoop();
            $cache = $this->createCache($loop);

            $cache->on('start', function(CacheInterface $cache) use($sim) {
                return Promise::doResolve()
                    ->then(function() use($cache) {
                        return $cache->set('TEST_KEY', [ 'data' => 'A' ], static::TIMEOUT_TTL);
                    })
                    ->then(function() use($cache) {
                        return $cache->exists('TEST_KEY')->then(function($result) {
                            $this->assertSame(true, $result);
                        });
                    })
                    ->then(function() use($cache, $sim) {
                        return new Promise(function($resolve, $reject) use($sim) {
                            $ttl = static::TIMEOUT_TTL + static::TIMEOUT_EPS;
                            $sim->getLoop()->addTimer($ttl, function() use($resolve, $reject) {
                                return $resolve();
                            });
                        });
                    })
                    ->then(function() use($cache) {
                        return $cache->exists('TEST_KEY')->then(function($result) {
                            $this->assertSame(false, $result);
                        });
                    })
                    ->done([ $sim, 'done' ], [ $sim, 'fail' ]);
            });

            $sim->onStart(function() use($cache) {
                $cache->start();
            });
            $sim->onStop(function() use($cache) {
                $cache->stop();
            });
        });
    }

    /**
     *
     */
    public function testCache_IsAbleToSetAndGetTTL()
    {
        $sim = $this->simulate(function(SimulationInterface $sim) {
            $loop = $sim->getLoop();
            $cache = $this->createCache($loop);

            $cache->on('start', function(CacheInterface $cache) use($sim) {
                return Promise::doResolve()
                    ->then(function() use($cache) {
                        return $cache->set('TEST_KEY', 'SOME_VAL');
                    })
                    ->then(function() use($cache) {
                        return $cache->getTtl('TEST_KEY')->then(function($result) {
                            $this->assertEquals(0, $result);
                        });
                    })
                    ->then(function($val) use($cache) {
                        return $cache->setTtl('TEST_KEY', static::TIMEOUT_TTL);
                    })
                    ->then(function() use($cache) {
                        return $cache->getTtl('TEST_KEY')->then(function($result) {
                            $this->assertEquals(static::TIMEOUT_TTL, $result);
                        });
                    })
                    ->then(function() use($cache, $sim) {
                        return new Promise(function($resolve, $reject) use($sim) {
                            $ttl = static::TIMEOUT_TTL + static::TIMEOUT_EPS;
                            $sim->getLoop()->addTimer($ttl, function() use($resolve, $reject) {
                                return $resolve();
                            });
                        });
                    })
                    ->then(function() use($cache) {
                        return $cache->getTtl('TEST_KEY')->then(function($result) {
                            $this->assertEquals(0, $result);
                        });
                    })
                    ->done([ $sim, 'done' ], [ $sim, 'fail' ]);
            });

            $sim->onStart(function() use($cache) {
                $cache->start();
            });
            $sim->onStop(function() use($cache) {
                $cache->stop();
            });
        });
    }

    /**
     *
     */
    public function testCache_IsAbleToRemoveAndCheckExistanceOfTTLs()
    {
        $sim = $this->simulate(function(SimulationInterface $sim) {
            $loop = $sim->getLoop();
            $cache = $this->createCache($loop);

            $cache->on('start', function(CacheInterface $cache) use($sim) {
                return Promise::doResolve()
                    ->then(function() use($cache) {
                        return $cache->set('TEST_KEY', 'SOME_VAL');
                    })
                    ->then(function() use($cache) {
                        return $cache->existsTtl('TEST_KEY')->then(function($result) {
                            $this->assertSame(false, $result);
                        });
                    })
                    ->then(function() use($cache) {
                        return $cache->setTtl('TEST_KEY', static::TIMEOUT_TTL);
                    })
                    ->then(function() use($cache) {
                        return $cache->existsTtl('TEST_KEY')->then(function($result) {
                            $this->assertSame(true, $result);
                        });
                    })
                    ->then(function() use($cache) {
                        return $cache->removeTtl('TEST_KEY');
                    })
                    ->then(function() use($cache) {
                        return $cache->existsTtl('TEST_KEY')->then(function($result) {
                            $this->assertSame(false, $result);
                        });
                    })
                    ->done([ $sim, 'done' ], [ $sim, 'fail' ]);
            });

            $sim->onStart(function() use($cache) {
                $cache->start();
            });
            $sim->onStop(function() use($cache) {
                $cache->stop();
            });
        });
    }

    /**
     *
     */
    public function testCache_IsAbleToRetrieveExistingKeys()
    {
        $sim = $this->simulate(function(SimulationInterface $sim) {
            $loop = $sim->getLoop();
            $cache = $this->createCache($loop);

            $cache->on('start', function(CacheInterface $cache) use($sim) {
                return Promise::doResolve()
                    ->then(function() use($cache) {
                        return Promise::all([
                            $cache->set('TEST_KEY_A', 'SOME_VAL'),
                            $cache->set('TEST_KEY_B', 'SOME_VAL'),
                            $cache->set('TEST_KEY_C', 'SOME_VAL'),
                        ]);
                    })
                    ->then(function() use($cache) {
                        return $cache->getKeys()->then(function($result) {
                            $this->assertSame([ 'TEST_KEY_A', 'TEST_KEY_B', 'TEST_KEY_C' ], $result);
                        });
                    })
                    ->done([ $sim, 'done' ], [ $sim, 'fail' ]);
            });

            $sim->onStart(function() use($cache) {
                $cache->start();
            });
            $sim->onStop(function() use($cache) {
                $cache->stop();
            });
        });
    }

    /**
     * @param LoopInterface $loop
     * @return Cache|CacheInterface
     */
    public function createCache(LoopInterface $loop)
    {
        return new Cache($loop);
    }
}
