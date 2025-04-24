<?php

namespace LBPool\Utils;


class Connection {

    private static $instance = NULL;
    private $pdo;
    private static $ENV_PATH = __DIR__ . '/../.env';
    
    private function __construct(){
        try{
            self::loadEnvironmentVariables();
            $host = $_ENV['DB_HOST'];
            $db = $_ENV['DB_NAME'];
            $user = $_ENV['DB_USER'];
            $pass = $_ENV['DB_PASS'];
            $this->pdo = new \PDO('mysql:host=' . $host . ';dbname=' . $db . ';charset=utf8', $user, $pass, [\PDO::MYSQL_ATTR_INIT_COMMAND=>"SET NAMES utf8"]);
            $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        } catch (\PDOException $ex) {
            HttpManager::handle_500('Unable to establish a connection to the database');
        } catch (\Exception $e) {
            HttpManager::handle_500('File system exception');
        }
    }

    public static function loadEnvironmentVariables() {
        if (!file_exists(self::$ENV_PATH)) HttpManager::handle_500('file system exception : ENV file not found');
        $lines = file(self::$ENV_PATH, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                putenv(sprintf('%s=%s', $name, $value));
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }

    public static function getInstance() {
        if (self::$instance === NULL) {
            self::$instance = new Connection();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->pdo;
    }

}
