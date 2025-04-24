<?php

namespace LBPool;

use LBPool\Utils\Router;
use LBPool\Utils\HttpManager;
use LBPool\Controller\AuthController;

// Load 3rd-party classes
require 'vendor/autoload.php';

// Initialize variables
$endpoint = '';
$action = '';
$id = '';

// URL parsing
$separator = '/';
$trim_characters = " \n\r\t\v\x00" . $separator;
$uri = trim($_SERVER['REQUEST_URI'], $trim_characters);
$parameters = explode($separator, $uri);
foreach($parameters as &$p) { $p = htmlspecialchars($p); }
$parameters_count = count($parameters);

// In case it's a password reset action
if ($parameters[0] == 'user') {
    if ($parameters[1] == 'forgottenPassword' || $parameters[1] == 'checkUserToken' || $parameters[1] == 'modifyPassword' || $parameters[1] == 'register') {
        Router::route($parameters[0], $parameters[1]);
    }
}
// In case it's an authentication request
if ($parameters[0] == 'auth' && $parameters[1] == 'authenticate') {
    Router::route($parameters[0], $parameters[1], true);
}
// In case it's a garbage collection job
if ($parameters[0] == 'sync' && $parameters[1] == 'garbageCollector') {
    Router::route($parameters[0], $parameters[1]);
}

// Authentication
$authController = new AuthController();
$authController->grantAccess();

// Parameters assigment & Routing
switch ($parameters_count) {
    case 2:
        Router::route($parameters[0], $parameters[1]);
        break;
    case 3:
        Router::route($parameters[0], $parameters[1], $parameters[2]);
        break;
    default:
        HttpManager::handle_400();
        break;
}
