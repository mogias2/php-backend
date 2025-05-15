<?php declare(strict_types=1);

final class Autoloader
{
    public static $classes = [
        // config
        //'LogConf' => '/../conf/Conf.php',
        //'MySQLConf' => '/../conf/Conf.php',
        //'RedisConf' => '/../conf/Conf.php',
        //'SystemConf' => '/../conf/Conf.php',
    ];

    public static $dirs = [
        'base/',
        //'common/',
        //'data/',
        //'gen/',
        //'model/',
        //'service/',
        //'util/',
    ];
}

spl_autoload_register(function (string $className) {
    if (isset(Autoloader::$classes[$className])) {
        $path = __DIR__ . Autoloader::$classes[$className];

        if (defined($path)) {
            return;
        }

        require $path;
        define($path, 1);
        return;
    } else {
        foreach (Autoloader::$dirs as $dir) {
            $path = __DIR__.'/'.$dir.$className.'.php';
            if (defined($path)) {
                return;
            }

            if (file_exists($path)) {
                require $path;
                define($path, 1);
                return;
            }
        }
    }
});
