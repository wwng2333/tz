# tz
基于workerman的雅黑探针

Usage:
```bash
git clone https://github.com/wwng2333/tz.git
cd tz
composer install
php tz.php start
```

```php
<?php
use Workerman\Worker;
require_once __DIR__ . '/vendor/autoload.php';

Worker::$stdoutFile = 'tz.log'; //日志
$http_worker = new Worker("http://0.0.0.0:80"); //监听地址
$http_worker->count = 5; //子进程数量
```