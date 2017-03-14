# tz
基于workerman的雅黑探针

实用功能：
| 系统   | 服务器实时数据 | 网络使用状况 |
|--------|:-------------:| -----:|
| Linux | √ | √ |
|Windows| √ | 总流量 |
|Freebsd| √ | × |
|OpenWRT/LEDE| √ | √ |
|Android| √ | √ |

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

#Worker::$stdoutFile = 'tz.log'; //日志，默认禁用
$http_worker = new Worker("http://0.0.0.0:2345"); //监听地址
$http_worker->name = 'Proberv'; //实例名称
$http_worker->user = 'root'; //以哪个用户运行
$http_worker->count = 3; //子进程数量
```