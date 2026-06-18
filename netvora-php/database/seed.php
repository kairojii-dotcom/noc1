<?php

declare(strict_types=1);

/**
 * Seeder — creates the bootstrap Super Admin account.
 * Run AFTER migrate.php.
 *
 *   php database/seed.php
 *
 * Default credentials (change after first login):
 *   email    : superadmin@netvora.com
 *   password : Netvora#2026
 */

require __DIR__ . '/../app/Core/Env.php';
\App\Core\Env::load(__DIR__ . '/../.env');
require __DIR__ . '/../app/Core/Database.php';

use App\Core\Database;

$email    = 'superadmin@netvora.com';
$password = 'Netvora#2026';
$hash     = password_hash($password, PASSWORD_BCRYPT);

$existing = Database::selectOne("SELECT id FROM users WHERE email=:e AND tenant_id IS NULL", [':e' => $email]);

if ($existing) {
    echo "Super admin already exists ($email). Skipping.\n";
    exit(0);
}

Database::execute(
    "INSERT INTO users (tenant_id, role_code, name, email, password_hash, is_active)
     VALUES (NULL, 'super_admin', 'Super Admin', :email, :hash, true)",
    [':email' => $email, ':hash' => $hash]
);

echo "Super admin created.\n  email:    $email\n  password: $password\n";
echo "IMPORTANT: change this password after first login.\n";
