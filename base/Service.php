<?php

declare(strict_types=1);

abstract class Service
{
    private static ?Service $instance = null;
    private ?array $input = null;
    private ?array $output = null;
    private array $message = [];
    protected array $serviceLogs = [];
    private int $id = CommonDef::INVALID_ID;
    private int $serviceID = CommonDef::INVALID_ID;
    private string $name;
    private bool $vervose = false;
    protected bool $debug = false;
    public bool $ignoreLog = false;

    abstract protected function run();
    abstract protected function loadSession();
    abstract protected function lock();
    abstract protected function unlock();

    protected function input() {}
    protected function init() {}
    protected function postRun() {}
    protected function output() {}
    protected function recover() {}
    public function finish() {}

    public function __construct()
    {
        self::$instance = $this;
        $class = static::class;
        $this->name = $class;

        if (SystemConf::SERVICE_TYPE === CommonDef::LOCAL) {
            $this->debug = true;
        }

        $this->vervose = SystemConf::SERVICE_TYPE !== CommonDef::LIVE;
    }

    final public function work(?array $input): array
    {
        $this->input = $input;

        if ($this->vervose) {
            $this->loadSession();
            $this->input();
        } else {
            $this->input();
            $this->loadSession();
        }

        $this->lock();

        try {
            $this->run();
        } catch (Exception|Throwable $e) {
            $this->output = [];
            $this->recover();
            throw $e;
        }

        $this->unlock();
        $this->postRun();
        $this->output();

        return $this->output;
    }

    final public function name(): string
    {
        return $this->name;
    }

    final public function id(): int
    {
        return $this->id;
    }

    final protected function setID(int $id)
    {
        $this->id = $id;
        $this->serviceID = CommonUtil::mergeLowHigh64(TimeUtil::now(), $this->id);
    }

    final protected function getInput(string $key, mixed &$rValue, int|string $not = null)
    {
        if ($this->input === null || !isset($this->input[$key])) {
            self::error(EServerErr::INVALID_INPUT, 'Not found input', ['key' => $key]);
        }

        $rValue = $this->input[$key];

        if ($not !== null) {
            if ($rValue === $not) {
                self::error(EServerErr::INVALID_INPUT, 'Invalid input', ['key' => $key, 'value' => $rValue]);
            }
        }
    }

    final protected function getInputWithChecker(string $key, mixed &$rValue, callable $checker)
    {
        if ($this->input === null || !isset($this->input[$key])) {
            self::error(EServerErr::INVALID_INPUT, 'Not found input', ['key' => $key]);
        }

        $rValue = $this->input[$key];

        if (!$checker()) {
            self::error(EServerErr::INVALID_INPUT, 'Invalid input', ['key' => $key, 'value' => $rValue]);
        }
    }

    final protected function setOutput(string $key, mixed $value)
    {
        $this->output[$key] = $value;
    }

    protected function setMessage(array $message)
    {
        $this->message = array_merge($this->message, $message);
    }

    public function getMessage(): array
    {
        return $this->message;
    }

    final public static function addServiceLog(array $data)
    {
        self::$instance->serviceLogs = $data;
    }

    final public static function addSubServiceLog(string $key, array $data)
    {
        self::$instance->serviceLogs['data'][$key] = $data;
    }

    final public static function debug(string $msg, array $args = [])
    {
        $self = self::$instance;

        if (!$self->debug) {
            return;
        }

        $msg = static::class . ' (' . $self->id . "): $msg";
        Log::debug($msg, $args);
    }

    final public static function warn(string $msg, array $args = [])
    {
        $self = self::$instance;
        $args = array_merge(['id' => $self->id, 'service' => $self->name], $args);
        Log::warn($msg, $args);
    }

    private static function getLine(int $stack = 1): string
    {
        $bt = debug_backtrace();

        $before = $bt[$stack];
        $file = $before['file'];

        $pos = strrpos($file, '/', -1);

        if ($pos === false) {
            $pos = strrpos($file, '\\', -1);
        }

        if ($pos !== false) {
            $file = substr($file, $pos + 1);
        }

        $line = $before['line'];

        return "$file($line)";
    }

    final public static function error(int $err, string $msg, array $args = [], int $stack = 1)
    {
        $line = self::getLine($stack);

        if ($err === EServerErr::NO_ERR) {
            $err = EServerErr::SYSTEM;
        }

        $msg = "$msg: $line";
        $args = array_merge(['service' => self::$instance->name, 'id' => self::$instance->id, 'err' => $err], $args);

        Log::error($msg, $args);

        throw new ServiceException('', $err);
    }

    final public static function fail(string $msg, array $args = [], int $stack = 1)
    {
        self::error(EServerErr::FAIL, $msg, $args, $stack);
    }
}
