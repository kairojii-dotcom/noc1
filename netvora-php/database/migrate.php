<?php

declare(strict_types=1);

/**
 * Migration runner — applies every *.sql in database/migrations in order.
 * Tracks applied files in a `schema_migrations` table.
 *
 *   php database/migrate.php
 */

require __DIR__ . '/../app/Core/Env.php';
\App\Core\Env::load(__DIR__ . '/../.env');
require __DIR__ . '/../app/Core/Database.php';

use App\Core\Database;

$pdo = Database::connection();
$pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
    filename TEXT PRIMARY KEY,
    applied_at TIMESTAMPTZ NOT NULL DEFAULT now()
)");

$applied = array_column(
    $pdo->query("SELECT filename FROM schema_migrations")->fetchAll(),
    'filename'
);

$files = glob(__DIR__ . '/migrations/*.sql') ?: [];
sort($files);

$count = 0;
foreach ($files as $file) {
    $name = basename($file);
    if (in_array($name, $applied, true)) {
        echo "  - skip   $name\n";
        continue;
    }
    echo "  + apply  $name ... ";
    $sql = file_get_contents($file);
    try {
        $pdo->exec($sql);
        $stmt = $pdo->prepare("INSERT INTO schema_migrations (filename) VALUES (?)");
        $stmt->execute([$name]);
        echo "done\n";
        $count++;
    } catch (\Throwable $e) {
        echo "FAILED\n    " . $e->getMessage() . "\n";
        exit(1);
    }
}

echo "\nMigration complete. $count file(s) applied.\n";
