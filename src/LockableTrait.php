<?php
namespace App\BxConsole;

use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Lock\Lock;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;

trait LockableTrait {

    /** @var Lock */
    private $_lock;

    protected function lock($blocking = false) {

        if (null !== $this->_lock) {
            throw new LogicException('A lock is already in place.');
        }

        $lockStore = new FlockStore(pathinfo(EnvHelper::getCrontabFile(), PATHINFO_DIRNAME));
        $lockFactory = new LockFactory($lockStore);

        $this->_lock = $lockFactory->createLock($this->getLockName($this->getName()), EnvHelper::getCrontabTimeout());

        if (!$this->_lock->acquire($blocking)) {
            $this->_lock = null;

            return false;
        }

        return true;
    }

    /**
     * Releases the command lock if there is one.
     */
    protected function release()
    {
        if ($this->_lock) {
            $this->_lock->release();
            $this->_lock = null;
        }
    }

    protected function getLockName($cmd) {

        return preg_replace('/[^a-z\d ]/i', '_', $cmd);
    }
}