<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

function database(): PDO
{
    static $connection = null;

    if ($connection instanceof PDO) {
        return $connection;
    }

    if (!extension_loaded('pdo_sqlite')) {
        throw new RuntimeException('Die PHP-Erweiterung pdo_sqlite ist nicht aktiviert.');
    }

    if (!is_file(DATABASE_PATH)) {
        throw new RuntimeException(
            'Die Datenbank wurde noch nicht initialisiert. Bitte zuerst scripts/init_database.php ausführen.'
        );
    }

    $connection = new PDO('sqlite:' . DATABASE_PATH);
    $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $connection->exec('PRAGMA foreign_keys = ON');

    return $connection;
}