<?php

declare(strict_types=1);

use Exception as Exception;
use PDO as PDO;
use PDOException as PDOException;
use PDOStatement as PDOStatement;

final class MySQLConn extends Connector
{
    private string $host;
    private int $port;
    private string $database;
    private string $user;
    private string $password;
    private ?PDOStatement $stmt = null;
    private int $paramIdx;
    private bool $emul;
    private int $mode;
    private bool $mixed;

    final public function __construct(string $host, int $port, string $database, string $user, string $password, bool $emul = false)
    {
        $this->host = $host;
        $this->port = $port;
        $this->database = $database;
        $this->user = $user;
        $this->password = $password;
        $this->emul = $emul;
        $this->connect();

        //this->$mode = PDO::FETCH_ASSOC;
        $this->mode = PDO::FETCH_NUM;
        $this->mixed = false;
    }

    final public function connect()
    {
        try {
            $dsn = 'mysql:host=' . $this->host . ';port=' . $this->port . ';dbname=' . $this->database;
            $this->conn = new PDO($dsn, $this->user, $this->password,
                [
                    PDO::ATTR_EMULATE_PREPARES => $this->emul,
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_PERSISTENT => false,
                    PDO::ATTR_STRINGIFY_FETCHES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
                    PDO::ATTR_TIMEOUT => 5
                ]);
            // $this->conn->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);
            // $this->conn->setAttribute(\PDO::ATTR_STRINGIFY_FETCHES, false);
        } catch (PDOException $e) {
            $this->error($e->getMessage());
        }
    }

    final public function close()
    {
        $this->conn = null;
    }

    private function error(string $msg, array $ctx = [])
    {
        Log::error('DB failed', ['command' => $msg, 'ctx' => $ctx]);
    }

    private function clear()
    {
        $this->stmt = null;
        $this->paramIdx = 0;
    }

    private function setMode(bool $assoc = false)
    {
        $this->mode = $assoc ? PDO::FETCH_ASSOC : PDO::FETCH_NUM;
    }

    //-----------------------------------------

    final public function exec(string $statement): int
    {
        try {
            $row = $this->conn->exec($statement);
            if ($row === false) {
                return 0;
            }
            return $row;
        } catch (PDOException $e) {
            $this->error('execute', ['err' => $e->getMessage(), 'statement' => $statement]);
        }
        return 0;
    }

    final public function query(string $query, ?int $fetchMode = null): array
    {
        try {
            $statement = $this->conn->query($query);
            return $statement->fetchAll();
        } catch (PDOException $e) {
            $this->error('execute', ['err' => $e->getMessage(), 'query' => $query]);
        }
        return [];
    }

    //-----------------------------------------

    private function prepare(string $query)
    {
        $stmt = $this->conn->prepare($query);
        if ($stmt === false) {
            $this->error('prepare: ' . $query);
        }

        $this->stmt = $stmt;
        $this->paramIdx = 0;
    }

    final public function bindNull()
    {
        if (!$this->stmt->bindValue(++$this->paramIdx, null, PDO::PARAM_NULL)) {
            $this->error('bind null', ['index' => $this->paramIdx]);
        }
    }

    final public function bindInt(int $value)
    {
        if (!$this->stmt->bindValue(++$this->paramIdx, $value, PDO::PARAM_INT)) {
            $this->error('bind int', ['index' => $this->paramIdx, 'value' => $value]);
        }
    }

    final public function bindString(string $value)
    {
        if (!$this->stmt->bindValue(++$this->paramIdx, $value, PDO::PARAM_STR)) {
            $this->error('bind string', ['index' => $this->paramIdx, 'value' => $value]);
        }
    }

    final public function bindFloat(float $value)
    {
        if (!$this->stmt->bindValue(++$this->paramIdx, $value, PDO::PARAM_STR)) {
            $this->error('bind float', ['index' => $this->paramIdx, 'value' => $value]);
        }
    }

    final public function bindIntVals(int ...$args)
    {
        foreach($args as $arg) {
            $this->bindInt($arg);
        }
    }

    final public function bindVals(mixed ...$args)
    {
        foreach($args as $arg) {
            if ($arg === null) {
                $this->bindNull();
            } else if (is_string($arg)) {
                $this->bindString($arg);
            } else if (is_float($arg)) {
                $this->bindFloat($arg);
            } else {
                $this->bindInt($arg);
            }
        }
    }

    private function executeInternal(string $query, array &$params): int
    {
        $this->prepare($query);

        if ($this->mixed) {
            $this->bindVals(...$params);
        } else {
            $this->bindIntVals(...$params);
        }

        if (!$this->stmt->execute()) {
            $this->error('execute', ['query' => $query, 'param' => $params]);
        }

        return $this->stmt->rowCount();
    }

    final public function execute(string $query, int ...$params): int
    {
        return $this->execMixed($query, $params, false);
    }

    final public function execMixed(string $query, array $params, bool $mixed): int
    {
        $this->mixed = $mixed;
        $count = 0;

        try {
            $count = $this->executeInternal($query, $params);
        } catch (PDOException $e) {
            $this->error('execute', ['err' => $e->getMessage(), 'query' => $query, 'param' => $params]);
        }

        $this->clear();

        return $count;
    }

    final public function fetch(string $query, int ...$params): array|false
    {
        return $this->fetchMixed($query, $params, false);
    }

    final public function fetchMixed(string $query, array $params, bool $mixed, bool $ignoreErr = false): array|false
    {
        $result = null;

        $this->mixed = $mixed;

        try {
            $this->executeInternal($query, $params);

            if ($this->stmt->rowCount() === 0) {
                return [];
            }

            $result = $this->stmt->fetch($this->mode);
            if ($result === false) {
                $this->error('fetch', ['query' => $query, 'param' => $params]);
            }
        } catch (PDOException $e) {
            if ($ignoreErr) {
                Service::warn('fetch', ['err' => $e->getMessage(), 'query' => $query, 'param' => $params]);
            } else {
                $this->error('fetch', ['err' => $e->getMessage(), 'query' => $query, 'param' => $params]);
            }
        }

        $this->clear();

        return ($result === false || empty($result)) ? false : $result;
    }

    final public function fetchAll(string $query, int ...$params): array|false
    {
        return $this->fetchAllMixed($query, $params, false);
    }

    final public function fetchAllMixed(string $query, array $params, bool $mixed): array|false
    {
        $results = null;

        $this->mixed = $mixed;

        try {
            $this->executeInternal($query, $params);

            $results = $this->stmt->fetchAll($this->mode);
            if ($results === false) {
                $this->error('fetch all', ['query' => $query, 'param' => $params]);
            }
        } catch (PDOException $e) {
            $this->error('fetch all', ['err' => $e->getMessage(), 'query' => $query, 'param' => $params]);
        }

        $this->clear();

        return ($results === false) ? false : $results;
    }

    final public function fetchAllSet(): array
    {
        $ok = false;
        $resultSet = null;
        do {
            try {
                if ($this->stmt === null) {
                    $this->error('fetch all set: not prepared');
                    break;
                }

                if (!$this->stmt->execute()) {
                    $this->error('fetch all set: execute');
                    break;
                }

                $resultSet = [];
                do {
                    $rowset = $this->stmt->fetchAll(PDO::FETCH_ASSOC);
                    $resultSet[] = $rowset;
                } while ($this->stmt->nextRowset());

                $ok = true;
            } catch (PDOException $e) {
                $this->error('fetch all set', ['err' => $e->getMessage()]);
                break;
            }
        } while (false);

        $this->clear();
        return [$ok, $resultSet];
    }

    final public function fetchObjects($class): array
    {
        $ok = false;
        $resultSet = null;
        do {
            try {
                if ($this->stmt === null) {
                    $this->error('fetch objects: not prepared');
                    break;
                }

                if (!$this->stmt->execute()) {
                    $this->error('fetch objects: execute');
                    break;
                }

                do {
                    $resultSet[] = $this->stmt->fetchAll(PDO::FETCH_CLASS, $class);
                } while ($this->stmt->nextRowset());

                $ok = true;
            } catch (PDOException $e) {
                $this->error('fetch objects', ['err' => $e->getMessage()]);
                break;
            }
        } while (false);

        $this->clear();
        return [$ok, $resultSet];
    }

    final public function execTransaction(callable $func)
    {
        try {
            if (!$this->conn->beginTransaction()) {
                $this->error('begin transaction');
            }

            $func($this);

            if (!$this->conn->commit()) {
                $this->error('commit');
            }
        } catch (PDOException $e) {
            $this->error($e->getMessage());

            if (!$this->conn->rollback()) {
                $this->error('rollback');
            }
        }

        $this->clear();
    }

    final public function lastInsertId(): ?string
    {
        $ret = $this->conn->lastInsertId();
        return ($ret === false) ? null : $ret;
    }
}
