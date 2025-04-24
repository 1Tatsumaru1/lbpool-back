<?php

namespace LBPool\Controller;

use LBPool\Utils\Connection;
use LBPool\Utils\Verificator;
use LBPool\Utils\HttpManager;
use LBPool\Utils\UserContext;
use LBPool\Model\UserManager;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;


class AuthController {
    private $secret_key;
    private $algo = 'HS256';  // JWT signing algorithm
    private $token_expiry = 86400;  // Token expiry time in seconds (1 day)
    private $refresh_token_expiry;
    private $user_id;
    private $email;
    private $user_token;
    private $access_token;
    private $refresh_token;
    private $logger;
    private $request_origin;

    public function __construct() {
        Connection::loadEnvironmentVariables();
        $this->secret_key = $_ENV['SECRET_KEY'];
        $this->request_origin = $_SERVER['REMOTE_ADDR'];
        $this->refresh_token_expiry = strtotime('tomorrow midnight');
        $this->logger = new Logger('authentication');
        $this->logger->pushHandler(new StreamHandler(__DIR__.'/logs/authentication.log'));
    }

    
    public function grantAccess() {
        $token = $this->getBearerToken();
        if (empty($token)) {
            $this->authenticate();
            if (empty($this->user_id)) {
                $this->logger->warning($this->request_origin . ' | Connection attempt failed');
                HttpManager::handle_400('Wrong credentials');
            }
            return;
        }
        $this->validateToken($token);
        if (empty($this->user_id)) {
            $this->logger->warning($this->request_origin . ' | Connection attempt with invalid token');
            HttpManager::handle_400('Invalid token');
        }
        $this->logger->info($this->request_origin . ' | Successful connexion for user ' . $this->user_id);
        UserContext::createInstance($this->user_id, $this->email, $this->user_token, $this->access_token, $this->refresh_token);
        return;
    }

    
    public function authenticate(bool $auth_only = false) {
        if ($_SERVER['REQUEST_METHOD'] != 'POST') {
            $this->logger->warning($this->request_origin . ' | Authentication attempt with invalid HTTP method');
            HttpManager::handle_405();
        }
        //$this->rateLimitCheck();
        if (!isset($_POST['identifier']) || empty($identifier = Verificator::verifyEmail($_POST['identifier']))
            || !isset($_POST['password']) || empty($password = Verificator::verifyPassword($_POST['password'], 1, 255))) {
            $this->logger->warning($this->request_origin . ' | Connection attempt with invalid credentials');
            HttpManager::handle_400('Invalid credentials');
        }
        $um = new UserManager();
        if (!$um->playerExistsByEmail($identifier)) {
            $this->logger->warning($this->request_origin . ' | Connection attempt with invalid identifier (' . $identifier . ')');
            HttpManager::handle_400('Invalid identifier');
        }
        if (!$player = $um->getPlayerByEmail($identifier)) {
            $this->logger->error($this->request_origin . ' | Practitioner retrieval error with identifier ' . $identifier);
            HttpManager::handle_500('Could not retrieve practitioner');
        }
        if (!password_verify($password, $player->mdp)) {
            $this->logger->warning($this->request_origin . ' | Connection attempt with wrong password for user ' . $identifier);
            $um->decreaseAttempts($player->id);
            HttpManager::handle_400(
                'Wrong password',
                ($player->remaining_attempts == 0) ? 0 : ($player->remaining_attempts - 1)
            );
        }
        if (!(int)$player->active == 1) {
            $this->logger->warning($this->request_origin . ' | Connection attempt for inactive user ' . $identifier);
            HttpManager::handle_401('Inactive profile');
        }
        if (!$um->resetAttempts($player->id)) {
            $this->logger->error($this->request_origin . ' | User attempts reset error for user ' . $player->id);
            HttpManager::handle_500('Could not reset user attempts');
        }
        $this->generateToken($player->id, true);
        $this->user_id = (int)$player->id;
        $this->email = $player->email;
        $this->user_token = $player->token;
        if ($auth_only) {
            $this->logger->info($this->request_origin . ' | Successful initial connexion for user ' . $this->user_id);
            UserContext::createInstance($this->user_id, $this->email, $this->user_token, $this->access_token, $this->refresh_token);
            HttpManager::handle_200('Authentication successful', $this->user_id);
        }
    }


    private function generateToken(int $uid, bool $generate_refresh = false) {
        $issued_at = time();
        $expiration_time = $issued_at + $this->token_expiry;
        $payload = [
            'iat' => $issued_at,
            'exp' => $expiration_time,
            'user_id' => $uid,
            'type' => 'access'
        ];
        $this->access_token = JWT::encode($payload, $this->secret_key, $this->algo);
        if ($generate_refresh) {
            $refresh_payload = [
                'iat' => $issued_at,
                'exp' => $this->refresh_token_expiry,
                'user_id' => $uid,
                'type' => 'refresh'
            ];
            $this->refresh_token = JWT::encode($refresh_payload, $this->secret_key, $this->algo);
        } else {
            $this->refresh_token = NULL;
        }
    }


    private function validateToken(string $token) {
        try {
            $payload = JWT::decode($token, new Key($this->secret_key, $this->algo));
            $this->user_id = (int)$payload->user_id;
            $um = new UserManager();
            if (!$um->playerExistsById($this->user_id)) {
                $this->logger->warning($this->request_origin . ' | Token contains invalid user_id (' . $this->user_id . ')');
                HttpManager::handle_400('Invalid user id');
            }
            if (!$player = $um->getPlayerById($this->user_id)) {
                $this->logger->error($this->request_origin . ' | Practitioner retrieval error for user ' . $this->user_id);
                HttpManager::handle_500('Could not retrieve user');
            }
            $this->email = $player->email;
            $this->user_token = $player->token;
            if ($payload->type == 'refresh') {
                $this->generateToken($this->user_id);
                return;
            }
            $this->access_token = $token;
            $this->refresh_token = NULL;
            return;  // Token is valid
        } catch (\Exception $e) {
            $this->user_id = NULL;
            return;  // Invalid token
        }
    }

    
    private function getBearerToken() {
        $headers = NULL;
        if (isset($_SERVER['Authorization'])) {
            $headers = trim($_SERVER["Authorization"]);
        } else if (isset($_SERVER['HTTP_AUTHORIZATION'])) { // Nginx or fast CGI
            $headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
        } elseif (function_exists('apache_request_headers')) { // Server-side fix for the Authorization header being dropped
            $requestHeaders = apache_request_headers();
            $requestHeaders = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));
            if (isset($requestHeaders['Authorization'])) $headers = trim($requestHeaders['Authorization']);
        }
        if (!empty($headers) && preg_match('/Bearer\s(\S+)/', $headers, $matches)) return $matches[1]; // token
        return; // Token not found
    }
    
    
    // private function rateLimitCheck() {
    //     $ip_address = $_SERVER['REMOTE_ADDR'];
    //     $attempts_key = "attempts_{$ip_address}";
    //     $timestamp_key = "timestamp_{$ip_address}";

    //     // Fetch current attempts count and timestamp of last attempt
    //     $attempts = CacheManager::get($attempts_key) ?: 0;
    //     $last_attempt = CacheManager::get($timestamp_key) ?: 0;
    //     $current_time = time();

    //     // Reset attempts if time since last attempt is greater than a set threshold (e.g., 15 minutes)
    //     if ($current_time - $last_attempt > 900) {
    //         $attempts = 0;
    //     }

    //     if ($attempts >= 5) {
    //         HttpManager::handle_429('Too Many Requests: Please try again later.');
    //     }

    //     // Record attempt
    //     $attempts++;
    //     CacheManager::set($attempts_key, $attempts, 900); // Store attempts count with a 15 minute expiry
    //     CacheManager::set($timestamp_key, $current_time, 900); // Update timestamp with a 15 minute expiry
    // }
}
