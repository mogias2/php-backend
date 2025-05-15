<?php

declare(strict_types=1);

interface IConnector
{
    public function connect();
    public function close();
}

abstract class Connector implements IConnector
{
    protected ?object $conn = null;

    public function __destruct()
    {
        $this->close();
    }

    final public function conn(): object
    {
        return $this->conn;
    }
}
