<?php

namespace Dazzle\Cache\Memory;

use Dazzle\Cache\CacheInterface;
use Dazzle\Event\BaseEventEmitter;
use Dazzle\Loop\LoopAwareTrait;
use Dazzle\Loop\LoopInterface;
use Dazzle\Loop\Timer\TimerInterface;
use Dazzle\Promise\Promise;
use Dazzle\Promise\PromiseInterface;
use Dazzle\Throwable\Exception\Runtime\ReadException;
use Dazzle\Throwable\Exception\Runtime\WriteException;
use Error;
use Exception;

class MemoryCache extends BaseEventEmitter implements CacheInterface
{
    use LoopAwareTrait;

    /**
     * @var TimerInterface
     */
    protected $loopTimer;

    /**
     * @var mixed[]
     */
    protected $config;

    /**
     * @var bool
     */
    protected $open;

    /**
     * @var bool
     */
    protected $paused;

    /**
     * @var mixed[]
     */
    protected $storage;

    /**
     * @var TimerInterface[]
     */
    protected $timers;

    /**
     * @var int
     */
    protected $timersCounter;

    /**
     * @var mixed[]
     */
    protected $stats;

    /**
     * @param LoopInterface $loop
     * @param mixed[] $config
     */
    public function __construct(LoopInterface $loop, $config = [])
    {
        $this->loop = $loop;
        $this->loopTimer = null;
        $this->config = $this->createConfig($config);
        $this->open = false;
        $this->paused = true;
        $this->storage = [];
        $this->timers = [];
        $this->timersCounter = 0;
        $this->stats = $this->createStats();
    }

    /**
     *
     */
    public function __destruct()
    {
        $this->stop();
        parent::__destruct();
    }

    /**
     * @override
     * @inheritDoc
     */
    public function isStarted()
    {
        return $this->open;
    }

    /**
     * @override
     * @inheritDoc
     */
    public function start()
    {
        if ($this->open)
        {
            return Promise::doResolve($this);
        }
        if ($this->loop->isRunning() === false)
        {
            $promise = new Promise();
            $this->loop->onStart(function() use($promise) {
                return $this->start()->then(function() use($promise) {
                    return $promise->resolve($this);
                });
            });
            return $promise;
        }

        $this->open = true;
        $this->handleStart();

        return Promise::doResolve($this);
    }

    /**
     * @override
     * @inheritDoc
     */
    public function stop()
    {
        if (!$this->open)
        {
            return Promise::doResolve($this);
        }

        $this->open = false;
        $this->handleStop();

        return Promise::doResolve($this);
    }

    /**
     * @override
     * @inheritDoc
     */
    public function end()
    {
        return $this->stop();
    }

    /**
     * @override
     * @inheritDoc
     */
    public function isPaused()
    {
        return $this->paused;
    }

    /**
     * @override
     * @inheritDoc
     */
    public function pause()
    {
        if (!$this->paused)
        {
            $this->paused = true;
            $this->loopTimer->cancel();
            $this->loopTimer = null;
        }
    }

    /**
     * @override
     * @inheritDoc
     */
    public function resume()
    {
        if ($this->paused)
        {
            $this->paused = false;
            $this->loopTimer = $this->getLoop()->addPeriodicTimer($this->config['interval'], [ $this, 'handleTick' ]);
        }
    }

    /**
     * @override
     * @inheritDoc
     */
    public function set($key, $val, $ttl = 0.0)
    {
        if (!$this->open)
        {
            return Promise::doReject(new WriteException('Cache object is not open.'));
        }
        $this->storage[$key] = $val;

        if (is_object($val))
        {
            return Promise::doReject(new WriteException('Objects are not supported.'));
        }

        if ($ttl > 0)
        {
            return $this->setTtl($key, $ttl)->then(function() use($val) { return $val; });
        }
        return Promise::doResolve($val);
    }

    /**
     * @override
     * @inheritDoc
     */
    public function get($key)
    {
        if (!$this->open)
        {
            return Promise::doReject(new ReadException('Cache object is not open.'));
        }
        return Promise::doResolve(array_key_exists($key, $this->storage) ? $this->storage[$key] : null);
    }

    /**
     * @override
     * @inheritDoc
     */
    public function remove($key)
    {
        if (!$this->open)
        {
            return Promise::doReject(new WriteException('Cache object is not open.'));
        }
        if (!array_key_exists($key, $this->storage))
        {
            return Promise::doResolve(false);
        }
        unset($this->storage[$key]);

        if (isset($this->timers[$key]))
        {
            return $this->removeTtl($key)->then(function() { return true; });
        }
        return Promise::doResolve(true);
    }

    /**
     * @override
     * @inheritDoc
     */
    public function exists($key)
    {
        if (!$this->open)
        {
            return Promise::doReject(new ReadException('Cache object is not open.'));
        }
        return Promise::doResolve(array_key_exists($key, $this->storage));
    }

    /**
     * @override
     * @inheritDoc
     */
    public function setTtl($key, $ttl)
    {
        if (!$this->open)
        {
            return Promise::doReject(new WriteException('Cache object is not open.'));
        }
        if ($ttl <= 0) {
            return Promise::doReject(new WriteException('TTL needs to be higher than 0.'));
        }
        if (!array_key_exists($key, $this->storage))
        {
            return Promise::doReject(new WriteException('Timeout cannot be set on undefined key.'));
        }

        $timer = round($ttl / $this->config['interval']);
        $this->timers[$key] = [ 'timeout' => $timer, 'ttl' => $ttl ];
        $this->timersCounter++;
        return Promise::doResolve($ttl);
    }

    /**
     * @override
     * @inheritDoc
     */
    public function getTtl($key)
    {
        if (!$this->open)
        {
            return Promise::doReject(new ReadException('Cache object is not open.'));
        }
        if (!isset($this->timers[$key]))
        {
            return Promise::doResolve(0);
        }
        return Promise::doResolve($this->timers[$key]['ttl']);
    }

    /**
     * @override
     * @inheritDoc
     */
    public function removeTtl($key)
    {
        if (!$this->open)
        {
            return Promise::doReject(new WriteException('Cache object is not open.'));
        }
        if (!isset($this->timers[$key]))
        {
            return Promise::doResolve(false);
        }

        unset($this->timers[$key]);
        $this->timersCounter--;

        return Promise::doResolve(true);
    }

    /**
     * @override
     * @inheritDoc
     */
    public function existsTtl($key)
    {
        if (!$this->open)
        {
            return Promise::doReject(new ReadException('Cache object is not open.'));
        }
        return Promise::doResolve(isset($this->timers[$key]));
    }

    /**
     * @override
     * @inheritDoc
     */
    public function getKeys()
    {
        if (!$this->open)
        {
            return Promise::doReject(new ReadException('Cache object is not open.'));
        }
        return Promise::doResolve(array_keys($this->storage))->then(function($result) {
            sort($result);
            return $result;
        });
    }

    /**
     * @override
     * @inheritDoc
     */
    public function getStats()
    {
        if (!$this->open)
        {
            return Promise::doReject(new ReadException('Cache object is not open.'));
        }
        return Promise::doResolve([
            'keys'   => count($this->storage),
        ]);
    }

    /**
     * @override
     * @inheritDoc
     */
    public function flush()
    {
        if (!$this->open)
        {
            return Promise::doReject(new WriteException('Cache object is not open.'));
        }

        $this->storage = [];
        $this->timers = [];
        $this->timersCounter = 0;

        return Promise::doResolve();
    }

    /**
     * Create configuration.
     *
     * @return mixed[]
     */
    protected function createConfig($config = [])
    {
        return array_merge([ 'interval' => 1e-1 ], $config);
    }

    /**
     * Create stats.
     *
     * @return mixed[]
     */
    protected function createStats()
    {
        return [];
    }

    /**
     * Handle start.
     */
    protected function handleStart()
    {
        $this->resume();
        $this->emit('start', [ $this ]);
    }

    /**
     * Handle stop.
     */
    protected function handleStop()
    {
        $this->storage = [];
        $this->timers = [];
        $this->timersCounter = 0;

        $this->pause();
        $this->emit('stop', [ $this ]);
    }

    /**
     * Handle loop tick.
     *
     * @internal
     */
    public function handleTick()
    {
        $timers = [];

        foreach ($this->timers as $key=>$timer)
        {
            if ($this->timers[$key]['timeout'] <= 1)
            {
                $timers[] = $key;
            }
            else
            {
                $this->timers[$key]['timeout']--;
            }
        }

        foreach ($timers as $key)
        {
            $this->remove($key);
        }
    }
}
