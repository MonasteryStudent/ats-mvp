<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Dieses Skript darf nur ueber die Kommandozeile ausgeführt werden.');
}

if (!extension_loaded('pdo_sqlite')) {
    fwrite(STDERR, "Fehler: Die PHP-Erweiterung pdo_sqlite ist nicht aktiviert.\n");
    exit(1);
}

$databaseDirectory = dirname(DATABASE_PATH);

if (!is_dir($databaseDirectory) && !mkdir($databaseDirectory, 0775, true) && !is_dir($databaseDirectory)) {
    fwrite(STDERR, "Fehler: Das Datenbankverzeichnis konnte nicht angelegt werden.\n");
    exit(1);
}

$schema = file_get_contents(PROJECT_ROOT . '/database/schema.sql');
$seed = file_get_contents(PROJECT_ROOT . '/database/seed.sql');

if ($schema === false || $seed === false) {
    fwrite(STDERR, "Fehler: Schema- oder Seed-Datei konnte nicht gelesen werden.\n");
    exit(1);
}

try {
    $database = new PDO('sqlite:' . DATABASE_PATH);
    $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $database->exec('PRAGMA foreign_keys = ON');
    $database->beginTransaction();
    $database->exec($schema);
    $database->exec($seed);
    $database->commit();

    $jobCount = (int) $database->query('SELECT COUNT(*) FROM stellen')->fetchColumn();
    echo "Datenbank erfolgreich initialisiert. Stellen: {$jobCount}\n";
    echo 'Pfad: ' . DATABASE_PATH . "\n";
} catch (Throwable $exception) {
    if (isset($database) && $database instanceof PDO && $database->inTransaction()) {
        $database->rollBack();
    }

    fwrite(STDERR, 'Fehler: ' . $exception->getMessage() . "\n");
    exit(1);
}

