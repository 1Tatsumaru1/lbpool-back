<?php

namespace LBPool\Utils;


class Autoloader{

    public static function register() {
        spl_autoload_register([__CLASS__, 'autoload']);
    }

    static function autoload($class) {
        if (strpos($class, 'LBPool\\') === 0) {
            $class = str_replace('LBPool\\', '', $class);
            $class = str_replace('\\', '/', $class);
            $file = __DIR__ . '/../' . $class . '.php';
            if (file_exists($file)) {
                require_once $file;
            }
        }
    }
    
    // public static function register(){
    //     spl_autoload_register(array(__CLASS__, 'loadModel'));
    //     spl_autoload_register(array(__CLASS__, 'loadView'));
    //     spl_autoload_register(array(__CLASS__, 'loadController'));
    //     spl_autoload_register(array(__CLASS__, 'loadUtils'));
    // }
    
    // static function loadModel($class){
    //     $file = __DIR__ . '/../model/' . $class . '.php';
    //     if (file_exists($file)){
    //         require_once $file;
    //     }
    // }
    
    // static function loadView($class){
    //     $file = __DIR__ . '/../view/' . $class . '.php';
    //     if (file_exists($file)){
    //         require_once $file;
    //     }
    // }
    
    // static function loadController($class){
    //     $file = __DIR__ . '/../controller/' . $class . '.php';
    //     if (file_exists($file)){
    //         require_once $file;
    //     }
    // }
    
    // static function loadUtils($class){
    //     $file = __DIR__ . '/' . $class . '.php';
    //     if (file_exists($file)){
    //         require_once $file;
    //     }
    // }
}