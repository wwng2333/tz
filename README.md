基于workerman的雅黑探针

实用功能：
<table><thead><tr><th>系统</th><th align="center">服务器实时数据</th><th align="right">网络使用状况</th></tr></thead><tbody><tr><td>Linux</td><td align="center">√</td><td align="right">√</td></tr><tr><td>Windows</td><td align="center">√</td><td align="right">总流量</td></tr><tr><td>Freebsd</td><td align="center">√</td><td align="right">×</td></tr><tr><td>OpenWRT/LEDE</td><td align="center">√</td><td align="right">√</td></tr><tr><td>Android</td><td align="center">√</td><td align="right">√</td></tr></tbody></table>

Usage:
```bash
apt install php7.2-cli php7.2-json composer git -y #Ubuntu 18.04
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
