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
     * sem_release and sem_remove requires sem_id.
     * This is the place to store it
     *
     * @var array $semaphores
     */
    protected $semaphores = [];
    
    /**
     * Receives shared memory segment by provided key
     * Shared memory is used in exclusive mode, so its providing atomic access
     *
     * @param string $name
     * @param int $timeout
     * @return boolean
     */
    public function acquireLock($name, $timeout = null, $limit = 1)
    {
        //fix issue with reserved characters by hashing the name
        try {
            if (!file_exists("/tmp/$name")) {
                touch("/tmp/$name");
            }
            $semKey = ftok("/tmp/$name", 'a');
            $semId = sem_get($semKey, $limit);
            $start = time();
            Yii::beginProfile("Waiting for lock of $name", 'AtomicLock::receive');
            while (1) {
                if (sem_acquire($semId, is_null($timeout) ? false : true)) {
                    Yii::trace("Aquire: success: Name: $name", 'semaphore');
                    Yii::endProfile("Waiting for lock of $name", 'AtomicLock::receive');
                    Yii::beginProfile("Total time in $name lock", 'AtomicLock::receive');
                    $this->semaphores[$name] = $semId;
                    return true;
                }
                if (!is_null($timeout) && (time()-$start) >= $timeout) {
                    Yii::trace("Aquire: Failure. Name: $name. Reason: Lock wasnt received after $timeout seconds. Give up.", 'semaphore');
                    Yii::endProfile("Waiting for lock of $name", 'AtomicLock::receive');
                    return false;
                }
                sleep(1);
            }
        } catch (\Exception $ex) {
            Yii::error($ex->getMessage().' '.$ex->getTraceAsString(), 'semaphore');
            if (file_exists("/tmp/$name")) {
                @unlink("/tmp/$name");
            }
            return false;
        }
    }
    
    /**
     * Releases used shared memory
     *
     * @param string $name
     * @return boolean
     */
    public function releaseLock($name)
    {
        if (!isset($this->semaphores[$name])) {
            Yii::trace("Release: failure. Name: $name. No semaphore was found in array ".var_export($this->semaphores, true), 'semaphore');
            return true;
        }
        $semId = $this->semaphores[$name];
        $result = sem_release($semId);
        if ($result) {
            Yii::trace("Release: success. Name: $name", 'semaphore');
        } else {
            Yii::error("Release: failure. Name: $name", 'semaphore');
        }
        if (file_exists("/tmp/$name")) {
            @unlink("/tmp/$name");
        }
        Yii::endProfile("Total time in $name lock", 'AtomicLock::receive');
        return true;
    }

    /**
     * Removes semaphore. Use it only when unlikely someone will work with same semaphore in short period again
     * Wrong usage may create races.
     *
     * @param string $name
     * @return boolean
     */
    public function removeSemaphore($name)
    {
        if (!isset($this->semaphores[$name])) {
            return true;
        }
        $semId = $this->semaphores[$name];
        return sem_remove($semId);
    }
}
