<?php

declare(strict_types=1);

final class Profiler
{
    private static array $times = [];

    final public static function start()
    {
        array_push(self::$times, microtime(true));
    }

    final public static function end(): float
    {
        if (empty(self::$times)) {
            return 0;
        }

        $latest = array_pop(self::$times);
        $latest = microtime(true) - $latest;

        if ($latest > 1) {
            $latest = round($latest, 2);
        } else if ($latest > 0.001){
            $latest = round($latest, 3);
        } else {
            $latest = round($latest, 4);
        }
        return $latest;
    }
}
