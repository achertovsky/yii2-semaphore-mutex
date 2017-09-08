<?php

namespace achertovsky\mutex;

use Yii;

class SemaphoreMutex extends \yii\mutex\Mutex
{
    /**
     * Receives shared memory segment by provided key
     * Shared memory is used in exclusive mode, so its providing atomic access
     * @param string $name
     * @param int $timeout
     * @return boolean
     */
    public function acquireLock($name, $timeout = null)
    {
        try {
            if (!file_exists("/tmp/$name")) {
                touch("/tmp/$name");
            }
            $semKey = ftok("/tmp/$name", 'a');
            $semId = sem_get($semKey, 1);
            $start = 0;
            Yii::beginProfile("Waiting for lock of $name", 'AtomicLock::receive');
            while (1) {
                if (sem_acquire($semId, true)) {
                    Yii::endProfile("Waiting for lock of $name", 'AtomicLock::receive');
                    Yii::beginProfile("Total time in $name lock", 'AtomicLock::receive');
                    return true;
                }
                sleep(1);
                if (!is_null($timeout) && ($res = time()-$start) >= $timeout) {
                    Yii::trace("Lock wasnt received after $timeout seconds. Give up.", 'dev');
                    Yii::endProfile("Waiting for lock of $name", 'AtomicLock::receive');
                    return false;
                }
            }
        } catch (\Exception $ex) {
            Yii::error($ex->getMessage().' '.$ex->getTraceAsString());
            return false;
        }
    }
    
    /**
     * Releases used shared memory
     * @return boolean
     */
    public function releaseLock($name)
    {
        if (!file_exists("/tmp/$name")) {
            return false;
        }
        $semKey = ftok("/tmp/$name", 'a');
        $semId = sem_get($semKey, 1);
        sem_release($semId);
        Yii::endProfile("Total time in {$this->key} lock", 'AtomicLock::receive');
        return true;
    }
}
