<?php

namespace LBPool\Utils;


class Router{
    
    public static function route(string $endpoint, string $action, $id = NULL): void {
        $controllerName = 'LBPool\\Controller\\' . ucfirst($endpoint) . 'Controller';
        if (class_exists($controllerName)) {
            $controller = new $controllerName;
            if (is_callable(array($controller, $action))) {
                $controller->$action($id);
            } else {
                HttpManager::handle_400();
            }
        } else {
            HttpManager::handle_404();
        }
    }
    
    public static function url($endpoint, $action = NULL, $id = NULL): string {
        $parts = array_filter([$endpoint, $action, $id]);
        return '/' . implode('/', $parts);
    }

}