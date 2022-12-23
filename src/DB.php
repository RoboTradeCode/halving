<?php

namespace Src;

use PDO;
use PDOException;

class DB
{

    private static PDO $connect;

    public static function connect(): void
    {
        $db = require_once CONFIG . '/db.config.php';
        try {
            $dbh = new PDO(
                'mysql:host=' . $db['host'] . ';port=' . $db['port'] . ';dbname=' . $db['db'],
                $db['user'],
                $db['password'],
                [PDO::ATTR_PERSISTENT => true, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
            );
            $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            Log::error($e);
            echo '[' . date('Y-m-d H:i:s') . '] [ERROR] Can not connect to db. Message: ' . $e->getMessage() . PHP_EOL;
            die();
        }
        self::$connect = $dbh;
    }

    public static function getConfig(mixed $user_id = 1)
    {
        $query = sprintf(
        /** @lang sql */ 'SELECT %s FROM `config` WHERE user_id = :user_id',
            implode('`, `', ['api_public', 'api_secret', 'exchange', 'symbol', 'low', 'high', 'count_of_orders'])
        );
        $sth = self::$connect->prepare($query);
        $sth->execute(['user_id' => $user_id]);
        return $sth->fetch();
    }

    private static function insert(string $table, array $columns_and_values): void
    {
        $columns = array_keys($columns_and_values);

        $sth = self::$connect->prepare(
            sprintf(
            /** @lang sql */ 'INSERT INTO `%s` (`%s`) VALUES (:%s)',
                $table,
                implode('`, `', $columns),
                implode(', :', $columns)
            )
        );

        $sth->execute($columns_and_values);
    }
}