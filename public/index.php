<?php
declare(strict_types=1);

// Front controller: every request enters here.
define('BASE_PATH', dirname(__DIR__));

// Loads App\Folder\ClassName from app/Folder/ClassName.php
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $file = BASE_PATH . '/app/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require BASE_PATH . '/app/Core/helpers.php';
load_env(BASE_PATH . '/.env');

use App\Core\ErrorHandler;
use App\Core\Router;
use App\Core\Session;

$config = require BASE_PATH . '/config/app.php';
date_default_timezone_set($config['timezone']);

ErrorHandler::register((bool) $config['debug']);
Session::start();

$router = new Router(require BASE_PATH . '/config/routes.php');
$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
