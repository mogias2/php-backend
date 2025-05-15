<?php

declare(strict_types=1);

require_once __DIR__ . '/../Autoloader.php';

use ParseError as ParseError;
use Exception as Exception;
use Throwable as Throwable;

set_error_handler(function (int $errno, string $errstr, string $errfile = null, int $errline = null, array $errcontext = null) {
    App::__error($errno, $errstr, $errfile, $errline, $errcontext);
});

set_exception_handler(function (Exception|Throwable $e) {
    App::__exception($e);
});

register_shutdown_function(function() {
    App::__shutdown();
});

final class App
{
    private static function create(string $className): Service
    {
        return new $className();
    }

    final public static function main(string $className)
    {
        Log::set(SystemConf::LIB_PATH, LogConf::NAME, LogConf::FILE, LogConf::MAX_FILES, LogConf::LEVEL);
        Log::set(SystemConf::LIB_PATH, ServiceLogConf::NAME, ServiceLogConf::FILE, ServiceLogConf::MAX_FILES, ServiceLogConf::LEVEL);

        $input = json_decode(file_get_contents('php://input'), true);
        $service = static::create($className);

        Profiler::start();

        $output = $service->work($input);
        static::send($output);
        $service->finish();

        $elapsed = Profiler::end();
        if ($elapsed > 1) {
            Log::warn("Took too long: $elapsed s");
        }

        if (!$service->ignoreLog) {
            $message = $service->getMessage();
            if (empty($message)) {
                $message = $input;
            }
            static::log($service->id(), $service->name(), $message, $output, $elapsed);
        }
    }

    private static function log(int $id, string $service, ?array $message, array $output, float $elapsed)
    {
        $log = $elapsed . '(sec)';

        if (empty($message)) {
            $args = ['service' => $service, 'id' => $id];
        } else {
            $args = array_merge(['service' => $service, 'id' => $id], $message);
        }

        Log::info($log, $args);
    }

    private static function send(array $output)
    {
        $output['Err'] = EServerErr::NO_ERR;
        static::echo($output);
    }

    private static function sendErr(int $err)
    {
        static::echo(['Err' => $err]);
    }

    private static function echo(array $output)
    {
        if (SystemConf::SERVICE_TYPE !== CommonDef::LIVE) {
            $encoded = json_encode($output);
            $outSize = empty($encoded) ? 0 : mb_strlen($encoded, '8bit');

            if ($outSize > 4096) {
                Log::warn('Too large output', ['size' => $outSize . '(bytes)']);
            }

            echo $encoded;
        } else {
            echo json_encode($output);
        }

        static::flush();
    }

    private static function flush()
    {
        ob_flush();
        flush();
    }

    final public static function __error(int $errno, string $errstr, ?string $errfile, ?int $errline, ?array $errcontext)
    {
        $msg = $errstr . ' '. $errfile . '(' . $errline . ')';
        throw new Exception($msg, $errno);
    }

    final public static function __exception(Exception|Throwable $e)
    {
        $bt = debug_backtrace();
        $err = $bt[1]['args'][0];

        if (SystemConf::SERVICE_TYPE === CommonDef::LOCAL) {
            var_dump($e);
            var_dump($err->getFile());
            var_dump($err->getLine());
            var_dump($err->getTrace());
            var_dump($err->getTraceAsString());
            var_dump($err->getMessage());
            var_dump($err->__toString());
        }

        if (self::$instance === null) {
             self::sendError(EServerErr::SYSTEM);
             throw new Exception($err->__toString());
        }

        $self = self::$instance;
        $self->recover();

        $code = $err->getCode();

        if ($code < EServerErr::SYSTEM) {
            self::sendErr(EServerErr::SYSTEM);
        } else {
            self::sendErr($code);
        }

        if ($code === 0) {
            Log::error("Exception($code): ".$err->getMessage().' '.$err->getFile().'('.$err->getLine().')');
        } else if ($code < EServerErr::SYSTEM || $code > EServerErr::FAIL) {
            if ($code !== EServerErr::RETRY) {
                Log::error("Error($code): ".$err->getMessage());
            }
        }
    }

    final public static function __shutdown()
    {
        $err = error_get_last();

        // Always $err is null, because __error handled $err before.
        // Never call.
        if ($err !== null) {
            Log::error('__shutdown', ['err' => $err]);
        }
    }
}
