<?php

declare(strict_types=1);

namespace CS2\Database;

use PDO;
use PDOException;

class Database
{
    private PDO $connection;

    public function __construct()
    {
        $host = getenv('DB_HOST') ?: '127.0.0.1';

        $port = getenv('DB_PORT') ?: '3306';

        $database = getenv('DB_NAME')
            ?: 'cs2_inventory';

        $username = getenv('DB_USER')
            ?: 'root';

        $password = getenv('DB_PASSWORD')
            ?: '';

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $host,
            $port,
            $database
        );

        try {

            $this->connection = new PDO(
                $dsn,
                $username,
                $password,
                [
                    PDO::ATTR_ERRMODE =>
                        PDO::ERRMODE_EXCEPTION,

                    PDO::ATTR_DEFAULT_FETCH_MODE =>
                        PDO::FETCH_ASSOC,

                    PDO::ATTR_EMULATE_PREPARES =>
                        false
                ]
            );

        } catch (PDOException $e) {

            throw new \RuntimeException(
                'Error conectando con la base de datos.'
            );
        }
    }

    public function getConnection(): PDO
    {
        return $this->connection;
    }
}