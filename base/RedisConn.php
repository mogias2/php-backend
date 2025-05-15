<?php

declare(strict_types=1);

use Redis as Redis;

final class RedisConn extends Connector
{
    private string $host;
    private int $port;
    private int $db;
    private string $password;
    private int $curDb;

    final public function __construct(string $host, int $port, int $db, string $password)
    {
        $this->host = $host;
        $this->port = $port;
        $this->db = $db;
        $this->password = $password;
    }

    final public function connect(): bool
    {
        $this->conn = new Redis();

        if (!$this->conn->connect($this->host, $this->port)) {
            $this->conn = null;
            return false;
        }

        $this->conn->auth($this->password);
        $this->conn->setOption(Redis::OPT_SCAN, Redis::SCAN_NORETRY);
        //$this->conn->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_PHP);
        $this->select($this->db);
        return true;
    }

    final public function close()
    {
        $this->conn?->close();
        $this->conn = null;
    }

    final public function select(int $db)
    {
        $this->curDb = $db;
        $this->conn->select($db);
    }

    final public function getCurDb(): int
    {
        return $this->curDb;
    }
}
