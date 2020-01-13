<?php

namespace achertovsky\mutex;

use Yii;

class SemaphoreMutex extends \yii\mutex\Mutex
{
    /**
     * @inheritdoc
     */
    public function acquire($name, $timeout = null)
    {
        return parent::acquire($name, $timeout);
    }
    /**
     * @var array $semaphores
     */
    protected $semaphores = [];
    
    /**
     * Receives shared memory segment by provided key
     * Shared memory is used in exclusive mode, so its providing atomic access
     * @param string $name
     * @param int $timeout
     * @return boolean
     */
    public function acquireLock($name, $timeout = null)
    {
        //fix issue with reserved characters by hashing the name
        $origName = $name;
        $name = md5($name);
        try {
            if (!file_exists("/tmp/$name")) {
                touch("/tmp/$name");
            }
            $semKey = ftok("/tmp/$name", 'a');
            $semId = sem_get($semKey, 1);
            $start = time();
            Yii::beginProfile("Waiting for lock of $origName", 'AtomicLock::receive');
            while (1) {
                if (sem_acquire($semId, true)) {
                    Yii::trace("Lock was received");
                    Yii::endProfile("Waiting for lock of $origName", 'AtomicLock::receive');
                    Yii::beginProfile("Total time in $origName lock", 'AtomicLock::receive');
                    $this->semaphores[$name] = $semId;
                    return true;
                }
                if (!is_null($timeout) && ($res = time()-$start) >= $timeout) {
                    Yii::info("Lock wasnt received after $timeout seconds. Give up.", 'dev');
                    Yii::endProfile("Waiting for lock of $origName", 'AtomicLock::receive');
                    return false;
                }
                sleep(1);
            }
        } catch (\Exception $ex) {
            Yii::error($ex->getMessage().' '.$ex->getTraceAsString());
            if (file_exists("/tmp/$name")) {
                unlink("/tmp/$name");
            }
            return false;
        }
    }
    
    /**
     * Releases used shared memory
     * @return boolean
     */
    public function releaseLock($name)
    {
        $origName = $name;
        $name = md5($name);
        if (!isset($this->semaphores[$name])) {
            Yii::info("No semaphore was released by name $name ".var_export($this->semaphores, true));
            return true;
        }
        $semId = $this->semaphores[$name];
        sem_release($semId);
        if (file_exists("/tmp/$name")) {
            unlink("/tmp/$name");
        }
        Yii::endProfile("Total time in $origName lock", 'AtomicLock::receive');
        return true;
    }
}
