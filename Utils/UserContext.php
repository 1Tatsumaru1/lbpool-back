<?php

namespace LBPool\Utils;


class UserContext {

    private static $instance = NULL;
    private $user_id;
    private $email;
    private $user_token;
    private $access_token;
    private $refresh_token;

    private function __construct() {}

    public function getUserId() {
        return $this->user_id;
    }

    public function getUserToken() {
        return $this->user_token;
    }

    public function getAccessToken() {
        return $this->access_token;
    }

    public function getRefreshToken() {
        return $this->refresh_token;
    }

    public function getEmail() {
        return $this->email;
    }

    public static function getInstance() {
        if (self::$instance === NULL) {
            return HttpManager::handle_500('Auth instance missing');
        }
        return self::$instance;
    }

    public static function createInstance(int $id, string $email, $user_token, $access_token, $refresh_token) {
        if (self::$instance !== NULL) {
            return self::$instance;
        }
        self::$instance = new UserContext();
        self::$instance->user_id = $id;
        self::$instance->email = $email;
        self::$instance->user_token = $user_token;
        self::$instance->access_token = $access_token;
        self::$instance->refresh_token = $refresh_token;
        return self::$instance;
    }
}
