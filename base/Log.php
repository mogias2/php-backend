<?php

declare(strict_types=1);

use Monolog\Handler\RotatingFileHandler;
use Bramus\Monolog\Formatter\ColoredLineFormatter;
use Bramus\Monolog\Formatter\ColorSchemes\TrafficLight2;

final class Log
{
    private static array $loggers = [];

    final public static function set(string $libPath, string $name, string $logPath, int $files, string $level)
    {
        require_once $libPath . '/ansi-php-3.1/src/AnsiAutoloader.php';
        require_once $libPath . '/monolog-3.2.0/src/MonologAutoloader.php';
        require_once $libPath . '/monolog-colored-line-formatter-3.1.1/src/ColoredLineFormatterAutoloader.php';
        require_once $libPath . '/Psr/PsrAutoloader.php';

        $logger = new Monolog\Logger($name);

        $formatter = new ColoredLineFormatter(new TrafficLight2(), null, null, false, true);

        $handler = new RotatingFileHandler($logPath, $files, $level, true, null, true);
        $handler->setFormatter($formatter);

        $logger->pushHandler($handler);

        self::$loggers[] = $logger;
    }

    final public static function default(): Monolog\Logger
    {
        if (count(self::$loggers) === 0) {
            self::set(SystemConf::LIB_PATH, LogConf::NAME, LogConf::FILE, LogConf::MAX_FILES, LogConf::LEVEL);
        }
        return self::$loggers[0];
    }

    final public static function logger(int $idx): Monolog\Logger
    {
        $count = count(self::$loggers);
        if ($count === 0) {
            self::set(SystemConf::LIB_PATH, LogConf::NAME, LogConf::FILE, LogConf::MAX_FILES, LogConf::LEVEL);
            return self::$loggers[0];
        }

        if ($idx < 0 || $idx >= $count) {
            return self::default();
        }

        return self::$loggers[$idx];
    }

    final public static function system(string $msg, array $ctx = [])
    {
        self::notice($msg, $ctx);
    }

    final public static function debug(string $msg, array $ctx = [])
    {
        self::default()->debug($msg, $ctx);
    }

    final public static function notice(string $msg, array $ctx = [])
    {
        self::default()->notice($msg, $ctx);
    }

    final public static function info(string $msg, array $ctx = [])
    {
        self::default()->info($msg, $ctx);
    }

    final public static function warn(string $msg, array $ctx = [])
    {
        self::default()->warning($msg, $ctx);
    }

    final public static function error(string $msg, array $ctx = [])
    {
        self::default()->error($msg, $ctx);
    }

    final public static function critical()
    {
        $msg = '';
        $args = func_get_args();
        foreach ($args as $arg) {
            $msg .= $arg . ' ';
        }

        self::default()->critical($msg);
    }
}
