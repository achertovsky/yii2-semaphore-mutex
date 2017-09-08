# yii2-semaphore-mutex

Description
======

Implementation of mutex through semaphore. Good for providing atomic access


Installing
======
The preferred way to install this extension is through composer.

```
{
	"require": {
	    "achertovsky/yii2-semaphore-mutex": "@dev"
    }
}
```

or

```
	composer require achertovsky/yii2-semaphore-mutex "@dev"
```
Usage
======

Add to components

```
[
    'components' => [
        'mutex' => [
            'class' => 'yii\mutex\FileMutex'
        ],
    ],
]
```

Use by 

```
$name = 'test mutex';
//use null to wait infinite or amount of seconds to wait in second param
Yii::$app->mutex->acquire($name, $infiniteWait ? null : 15);
Yii::$app->mutex->release($name);
```