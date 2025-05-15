<?php

declare(strict_types=1);

use MongoDB\Driver\ServerApi;

require_once(SystemConf::LIB_PATH . '/mongo-php-library-1.16.1/src/MongoAutoloader.php');
require_once(SystemConf::LIB_PATH . '/mongo-php-library-1.16.1/src/functions.php');

final class MongoConn extends Connector
{
    private string $uri;

    final public function __construct(string $uri)
    {
        $this->uri = $uri;
    }

    final public function connect()
    {
        $apiVersion = new ServerApi(ServerApi::V1);
        // Create a new client and connect to the server
        $this->conn = new MongoDB\Client($this->uri, [], ['serverApi' => $apiVersion]);
    }

    final public function close()
    {
        $this->conn = null;
    }

    private function error(string $msg, array $ctx = [])
    {
        Log::error('MongoDB failed', ['command' => $msg, 'ctx' => $ctx]);
    }
}
