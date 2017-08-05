<?php

namespace Dazzle\Cache;

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

class Cache extends BaseEventEmitter implements CacheInterface
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
    protected $ending;

    /**
     * @var PromiseInterface[]
     */
    protected $endingPromises;

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
     * @param LoopInterface $loop
     * @param mixed[] $config
     */
    public function __construct(LoopInterface $loop, $config = [])
    {
        $this->loop = $loop;
        $this->loopTimer = null;
        $this->config = $this->createConfig($config);
        $this->open = false;
        $this->ending = false;
        $this->endingPromises = [];
        $this->paused = true;
        $this->storage = [];
        $this->timers = [];
        $this->timersCounter = 0;
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
        return $this->open || $this->ending;
    }

    /**
     * @override
     * @inheritDoc
     */
    public function start()
    {
        if ($this->open || $this->ending)
        {
            return Promise::doResolve($this);
        }

        $this->open = true;
        $this->ending = false;
        $this->handleStart();

        return Promise::doResolve($this);
    }

    /**
     * @override
     * @inheritDoc
     */
    public function stop()
    {
        if (!$this->open && !$this->ending)
        {
            return Promise::doResolve($this);
        }

        $this->open = false;
        $this->ending = false;
        $this->handleStop();

        return Promise::doResolve($this);
    }

    /**
     * @override
     * @inheritDoc
     */
    public function end()
    {
        if (!$this->open && !$this->ending)
        {
            return Promise::doResolve($this);
        }

        if ($this->timersCounter === 0)
        {
            return $this->stop();
        }

        $promise = new Promise();
        $this->open = false;
        $this->ending = true;
        $this->endingPromises[] = $promise;

        return $promise;
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
        if (!$this->open && !$this->ending)
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
        if (!$this->open && !$this->ending)
        {
            return Promise::doReject(new WriteException('Cache object is not open.'));
        }
        if (!isset($this->timers[$key]))
        {
            return Promise::doResolve(false);
        }

        unset($this->timers[$key]);
        $this->timersCounter--;

        if ($this->ending && $this->timersCounter === 0)
        {
            return $this->stop()->then(function() { return true; });
        }
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
        return Promise::doResolve(array_keys($this->storage));
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

        // TODO
        return Promise::doResolve([
            'keys'   => 0,
            'hits'   => 0,
            'misses' => 0,
            'ksize'  => 0,
            'vsize'  => 0,
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

        foreach ($this->endingPromises as $endingPromise)
        {
            $endingPromise->resolve($this);
        }
        $this->endingPromises = [];

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
