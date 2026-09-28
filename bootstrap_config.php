<?php
/**
 * Charge la config DB : variables d'environnement (Render / Docker) en priorite,
 * sinon config.php local (Wamp). Ne pas committer config.php.
 */
declare(strict_types=1);

function rouahimo_load_config(): array
{
    $envHost = getenv('DB_HOST');
    if ($envHost !== false && $envHost !== '') {
        return [
            'host' => $envHost,
            'port' => (int) (getenv('DB_PORT') !== false && getenv('DB_PORT') !== '' ? getenv('DB_PORT') : 3306),
            'name' => getenv('DB_NAME') !== false && getenv('DB_NAME') !== '' ? getenv('DB_NAME') : 'rouahimo',
            'user' => getenv('DB_USER') !== false && getenv('DB_USER') !== '' ? getenv('DB_USER') : 'root',
            'pass' => getenv('DB_PASS') !== false ? (string) getenv('DB_PASS') : '',
        ];
    }

    $local = __DIR__ . '/config.php';
    if (is_file($local)) {
        /** @var array $cfg */
        $cfg = require $local;
        return $cfg;
    }

    // Defaut Docker (MariaDB local dans le meme conteneur)
    return [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'rouahimo',
        'user' => 'rouahimo',
        'pass' => 'rouahimo',
    ];
}

return rouahimo_load_config();