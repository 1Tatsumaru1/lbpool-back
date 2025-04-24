<?php

namespace LBPool\Controller;

use LBPool\Model\UserManager;
use LBPool\Utils\HttpManager;
use LBPool\Utils\Verificator;
use LBPool\Utils\UserContext;
use LBPool\Utils\Utilities;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;


class UserController {

    private $logger;
    private $request_origin;

    public function __construct() {
        $this->logger = new Logger('exploitation');
        $this->logger->pushHandler(new StreamHandler(__DIR__.'/logs/exploitation.log'));
        $this->request_origin = $_SERVER['REMOTE_ADDR'];
    }


    public function register() {
        if ($_SERVER['REQUEST_METHOD'] != 'POST') {
            $this->logger->warning($this->request_origin . ' | Account registration attempt with invalid HTTP method');
            HttpManager::handle_405();
        }
        if (!isset($_POST['email']) || empty($email = Verificator::verifyEmail($_POST['email']))
            || !isset($_POST['token']) || empty($token = Verificator::verifyText($_POST['token'], 50, 50))
            || !isset($_POST['password']) || empty($password = Verificator::verifyPassword($_POST['password'], 10, 255))) {
                $this->logger->warning($this->request_origin . ' | Account registration attempt with invalid parameters');
                HttpManager::handle_400('Invalid parameters');
        }
        $um = new UserManager();
        if (!$um->playerExistsByEmail($email)) {
            $this->logger->warning($this->request_origin . ' | Account registration attempt for non existing user ' . $email);
            HttpManager::handle_400('Non existing user');
        }
        if (!$um->checkPlayerToken($email, $token)) {
            $this->logger->error($this->request_origin . ' | Account registration attempt with wrong activation key for user ' . $email);
            HttpManager::handle_400('Wrong activation key');
        }
        if ($um->checkPlayerActive($email, $token)) {
            $this->logger->error($this->request_origin . ' | Account registration attempt for already active account on user ' . $email);
            HttpManager::handle_401('Account already active');
        }
        if (!$um->playerProfileValidation($email, password_hash($password, PASSWORD_DEFAULT))) {
            $this->logger->error($this->request_origin . ' | Account registration failed DB-side ' . $email);
            HttpManager::handle_500('Registration failure');
        }
        HttpManager::handle_200('Registration completed successfully', NULL, true);
    }


    /**
     * Password reset process 1/3
     */
	  public function forgottenPassword() {
        if ($_SERVER['REQUEST_METHOD'] != 'POST') {
            $this->logger->warning($this->request_origin . ' | Forgotten password attempt with invalid HTTP method');
            HttpManager::handle_405();
        }
        if (!isset($_POST['resetEmail']) || empty($email = Verificator::verifyEmail($_POST['resetEmail']))) {
            $this->logger->warning($this->request_origin . ' | Forgotten password attempt with invalid credential');
            HttpManager::handle_400('Invalid credentials');
        }
        $um = new UserManager();
        if (!$um->playerExistsByEmail($email)) {
            $this->logger->warning($this->request_origin . ' | Forgotten password attempt with inexistant identifer');
            HttpManager::handle_400('Invalid identifier');
        }
        if (!$user = $um->getPlayerByEmail($email)) {
            $this->logger->error($this->request_origin . ' | User retrieval error for user ' . $email);
            HttpManager::handle_500('Could not retrieve user');
        }
        if ($user->active == 0) {
            $this->logger->warning($this->request_origin . ' | Forgotten password attempt for inactive user ' . $email);
            HttpManager::handle_401('Inactive user');
        }
        $token = str_pad(random_int(1000, 999999), 6, '0', STR_PAD_LEFT);
        if (!$um->setToken($user->id, $token)) {
            $this->logger->error($this->request_origin . ' | User token setting error for user ' . $email);
            HttpManager::handle_500('Could not set user token');
        }
        $s = DIRECTORY_SEPARATOR;
        $rootPath = strstr(__DIR__, 'Controller', true);
        $path =  $rootPath . 'view' . $s . 'passwordReset.html';
        $sending = Utilities::sendEmail(
            $email,
            'Réinitialisation de votre mot de passe',
            str_replace('%token%', $token, file_get_contents($path))
        );
        if ($sending == 0) {
            $this->logger->error($this->request_origin . ' | Email sending error for user ' . $email);
            HttpManager::handle_500('Email sending failed');
        }
        HttpManager::handle_200('Forgotten password: retrieval code sent via email', NULL, true);
    }


    /**
     * Password reset process 2/3
     */
    public function checkUserToken() {
        if ($_SERVER['REQUEST_METHOD'] != 'POST') {
            $this->logger->warning($this->request_origin . ' | User-Token check attempt with invalid HTTP method');
            HttpManager::handle_405();
        }
        if (!isset($_POST['resetEmail']) || empty($email = Verificator::verifyEmail($_POST['resetEmail']))
            || !isset($_POST['resetToken']) || empty($token = Verificator::verifyText($_POST['resetToken'], 6, 6))) {
            $this->logger->warning($this->request_origin . ' | User-Token check attempt with invalid credentials');
            HttpManager::handle_400('Invalid credentials');
        }
        $um = new UserManager();
        if (!$um->playerExistsByEmail($email)) {
            $this->logger->warning($this->request_origin . ' | User-Token check attempt with inexistant identifer');
            HttpManager::handle_400('Invalid identifier');
        }
        if (!$user = $um->getPlayerByEmail($email)) {
            $this->logger->error($this->request_origin . ' | User retrieval error for user ' . $email);
            HttpManager::handle_500('Could not retrieve user');
        }
        if ($user->active == 0) {
            $this->logger->warning($this->request_origin . ' | User-Token check attempt for inactive user ' . $email);
            HttpManager::handle_401('Inactive user');
        }
        if ($user->token != $token || !Verificator::isWithinMinutes(new \DateTime($user->token_validity), new \DateTime(), 30)) {
            $this->logger->error($this->request_origin . ' | User-Token check attempt with invalid or expired token for user ' . $email);
            $um->decreaseAttempts($user->id);
            HttpManager::handle_400(
                'Invalid token',
                ($user->remaining_attempts == 0) ? 0 : ($user->remaining_attempts - 1)
            );
        }
        $um->resetAttempts($user->practitioner_id);
        HttpManager::handle_200('Forgotten password: token verified', NULL, true);
    }


    /**
     * Password reset process 3/3
     */
    public function modifyPassword() {
        if ($_SERVER['REQUEST_METHOD'] != 'POST') {
            $this->logger->warning($this->request_origin . ' | Password reset attempt with invalid HTTP method');
            HttpManager::handle_405();
        }
        if (!isset($_POST['resetEmail']) || empty($email = Verificator::verifyEmail($_POST['resetEmail']))
            || !isset($_POST['resetToken']) || empty($token = Verificator::verifyText($_POST['resetToken'], 6, 6))
            || !isset($_POST['newPassword']) || empty($newPassword = Verificator::verifyPassword($_POST['newPassword'], 10, 255))) {
            $this->logger->warning($this->request_origin . ' | Password reset attempt with invalid parameters');
            HttpManager::handle_400('Invalid parameters');
        }
        $um = new UserManager();
        if (!$um->playerExistsByEmail($email)) {
            $this->logger->warning($this->request_origin . ' | Password modification attempt with inexistant identifer');
            HttpManager::handle_400('Invalid identifier');
        }
        if (!$user = $um->getPlayerByEmail($email)) {
            $this->logger->error($this->request_origin . ' | User retrieval error for user ' . $email);
            HttpManager::handle_500('Could not retrieve user');
        }
        if ($user->active == 0) {
            $this->logger->warning($this->request_origin . ' | Password modification attempt for inactive user ' . $email);
            HttpManager::handle_401('Inactive user');
        }
        if ($user->token != $token || !Verificator::isWithinMinutes(new \DateTime($user->token_validity), new \DateTime(), 30)) {
            $this->logger->error($this->request_origin . ' | Password modification attempt with invalid or expired token for user ' . $email);
            $um->decreaseAttempts($user->id);
            HttpManager::handle_400(
                'Invalid token',
                ($user->remaining_attempts == 0) ? 0 : ($user->remaining_attempts - 1)
            );
        }
        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        if (!$um->updatePassword($user->id, $newHash)) {
            $this->logger->warning($this->request_origin . ' | Error while changing password for user ' . $email);
            HttpManager::handle_500('Password change error');
        }
        if (!$um->unsetToken($user->id)) {
            $this->logger->error($this->request_origin . ' | User token unset error for user ' . $email);
            HttpManager::handle_500('Could not unset user token');
        }
        if (!$um->resetAttempts($user->id)) {
            $this->logger->error($this->request_origin . ' | User attempts reset error for user ' . $email);
            HttpManager::handle_500('Could not reset user attempts');
        }
        HttpManager::handle_200('Forgotten password: password modified successfuly', NULL, true);
    }


    public function updatePassword() {
        if ($_SERVER['REQUEST_METHOD'] != 'PUT') {
            $this->logger->warning($this->request_origin . ' | Password update attempt with invalid HTTP method');
            HttpManager::handle_405();
        }
        $data = json_decode(file_get_contents('php://input'), true);
        if (!isset($data['oldPassword']) || empty($oldPassword = Verificator::verifyText($data['oldPassword'], 0, 255))
            || !isset($data['newPassword']) || empty($newPassword = Verificator::verifyPassword($data['newPassword'], 10, 255))) {
            $this->logger->warning($this->request_origin . ' | Password update attempt with invalid parameters');
            HttpManager::handle_400('Invalid parameters');
        }
        $userContext = UserContext::getInstance();
        $um = new UserManager();
        if (!$user = $um->getPlayerById($userContext->getUserId())) {
            $this->logger->error($this->request_origin . ' | User retrieval error for user ' . $userContext->getUserId());
            HttpManager::handle_500('Could not retrieve user');
        }
        if (!password_verify($oldPassword, $user->mdp)) {
            $this->logger->warning($this->request_origin . ' | Password update attempt with wrong current password for user ' . $user->email);
            HttpManager::handle_400('Invalid password');
        }
        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        if (!$um->updatePassword($user->id, $newHash)) {
            $this->logger->warning($this->request_origin . ' | DB error while changing password for user ' . $user->email);
            HttpManager::handle_500('Password change error');
        }
        HttpManager::handle_200('Password changed successfuly');
    }


    public function editSelf() {
        if ($_SERVER['REQUEST_METHOD'] != 'PUT') {
            $this->logger->warning($this->request_origin . ' | User profile update attempt with invalid HTTP method');
            HttpManager::handle_405();
        }
        $data = json_decode(file_get_contents('php://input'), true);
        if (!isset($data['email']) || empty($email = Verificator::verifyEmail($data['email']))
            || !isset($data['name']) || empty($name = Verificator::verifyText($data['name'], 3))) {
            $this->logger->warning($this->request_origin . ' | User profile update attempt with invalid parameters');
            HttpManager::handle_400('Invalid parameters');
        }
        $userContext = UserContext::getInstance();
        $user_id = $userContext->getUserId();
        $um = new UserManager();
        if (!$um->updatePlayer($user_id, $name, $email)) {
            $this->logger->warning($this->request_origin . ' | DB error while changing identity for user ' . $user_id);
            HttpManager::handle_500('Identity change error');
        }
        HttpManager::handle_200('Identity modified successfuly');
    }

}
