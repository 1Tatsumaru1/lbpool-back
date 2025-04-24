<?php

namespace LBPool\Controller;

use LBPool\Model\MatchManager;
use LBPool\Model\UserManager;
use LBPool\Utils\Verificator;
use LBPool\Utils\HttpManager;
use LBPool\Utils\UserContext;
use LBPool\Utils\Utilities;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use stdClass;

class MatchController {

    private $logger;
    private $request_origin;

    public function __construct() {
        $this->logger = new Logger('match');
        $this->logger->pushHandler(new StreamHandler(__DIR__.'/logs/exploitation.log'));
        $this->request_origin = $_SERVER['REMOTE_ADDR'];
    }

    
    //****************************************
    // GET
    //****************************************


    public function getMatchesByPlayer($player_id) {
        if ($_SERVER['REQUEST_METHOD'] != 'GET') {
            $this->logger->warning($this->request_origin . ' | Match retrieval attempt with invalid HTTP method');
            HttpManager::handle_405();
        }
        $userContext = UserContext::getInstance();
        $mm = new MatchManager();
	  	if (!isset($player_id) || empty($player_id = Verificator::verifyInt($player_id, 1, 999))) {
            $player_id = $userContext->getUserId();
        }
        $matchList = $mm->getMatchesByPlayerId($player_id);
        if (!$matchList) {
            $this->logger->error($this->request_origin . ' | Unable to retrieve matchList in DB');
            HttpManager::handle_500('Match list retrieval error');
        }
        if (count($matchList) == 0) HttpManager::handle_204('No match record');
        HttpManager::handle_200('Match list', $matchList);
    }


    public function getEloHistoryByPlayer() {
        if ($_SERVER['REQUEST_METHOD'] != 'GET') {
            $this->logger->warning($this->request_origin . ' | Elo history retrieval attempt with invalid HTTP method');
            HttpManager::handle_405();
        }
        $userContext = UserContext::getInstance();
        $mm = new MatchManager();
        $eloHistory = $mm->getEloHistory($userContext->getUserId());
        if (!$eloHistory) {
            $this->logger->error($this->request_origin . ' | Unable to retrieve elo history in DB');
            HttpManager::handle_500('Elo history retreival error');
        }
        HttpManager::handle_200('Elo history', $eloHistory);
    }


    public function getStatsSinglePlayer($player_id) {
        if ($_SERVER['REQUEST_METHOD'] != 'GET') {
            $this->logger->warning($this->request_origin . ' | Player stats retrieval attempt with invalid HTTP method');
            HttpManager::handle_405();
        }
        $userContext = UserContext::getInstance();
        if (!isset($player_id) || empty($player_id = Verificator::verifyInt($player_id, 1, 999))) {
            $this->logger->warning($this->request_origin . ' | Stats retrieval attempt with invalid parameters by user ' . $userContext->getUserId());
            HttpManager::handle_400('Invalid parameters');
        }
        $mm = new MatchManager();
        $stats = new stdClass();
        $stats->personnal = $mm->getStatsByPlayerId($player_id);
	  	$stats->streak = $mm->getPersonalStreaks($player_id);
        $stats->elo = $mm->getPlayerElo($player_id);
        $stats->eloHistory = $mm->getEloHistory($player_id);
        $stats->rank = $mm->getPlayerRank($player_id);
        $stats->matches = $mm->getFutureMatchesByPlayerId($player_id);
	  //$stats->general = $mm->getGeneralStats();
        if (!$stats) {
            $this->logger->error($this->request_origin . ' | Unable to retrieve stats in DB');
            HttpManager::handle_500('Stats generation error');
        }
        HttpManager::handle_200('', $stats);
    }


    public function getStatsAll() {
        if ($_SERVER['REQUEST_METHOD'] != 'GET') {
            $this->logger->warning($this->request_origin . ' | General stats retrieval attempt with invalid HTTP method');
            HttpManager::handle_405();
        }
        $mm = new MatchManager();
        $stats = $mm->getGeneralStats();
        if (!$stats) {
            $this->logger->error($this->request_origin . ' | Unable to retrieve stats in DB');
            HttpManager::handle_500('Stats generation error');
        }
        HttpManager::handle_200('', $stats);
    }

    public function getPlayers() {
        if ($_SERVER['REQUEST_METHOD'] != 'GET') {
            $this->logger->warning($this->request_origin . ' | General stats retrieval attempt with invalid HTTP method');
            HttpManager::handle_405();
        }
        $mm = new MatchManager();
        $players = $mm->getPlayers();
        if (!$players) {
            $this->logger->error($this->request_origin . ' | Unable to retrieve stats in DB');
            HttpManager::handle_500('Stats generation error');
        }
        HttpManager::handle_200('', $players);
    }


    //****************************************
    // CREATE / UPDATE
    //****************************************


    public function createMatch() {
        if ($_SERVER['REQUEST_METHOD'] != 'POST') {
            $this->logger->warning($this->request_origin . ' | Match creation attempt with invalid HTTP method');
            HttpManager::handle_405();
        }
        $userContext = UserContext::getInstance();
	  	$userId = $userContext->getUserId();
        if (!isset($_POST['p1']) || empty($p1 = Verificator::verifyInt($_POST['p1'], 1, 999))
            || !isset($_POST['p2']) || empty($p2 = Verificator::verifyInt($_POST['p2'], 1, 999))
            || !isset($_POST['start']) || empty($start = Verificator::verifyDate($_POST['start']))
            || ($p1 != $userId && $p2 != $userId)) {
                $this->logger->warning($this->request_origin . ' | Match creation attempt with invalid parameters by user ' . $userId);
                HttpManager::handle_400('Invalid parameters');
        }
        $mm = new MatchManager();
        $match = $mm->addMatch($p1, $p2, $start->format('Y-m-d H:i:s'));
        if (!$match) {
            $this->logger->error($this->request_origin . ' | Unable to create match in DB');
            HttpManager::handle_500('Match creation error');
        }
	  	$match = $mm->getLastCreatedMatchByPlayerId($userId);
        HttpManager::handle_200('', $match);
    }


    public function alterMatch() {
        if ($_SERVER['REQUEST_METHOD'] != 'PUT') {
            $this->logger->warning($this->request_origin . ' | Match alteration attempt with invalid HTTP method');
            HttpManager::handle_405();
        }
        $userContext = UserContext::getInstance();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!isset($data['id']) || empty($id = Verificator::verifyInt($data['id'], 1))
            || !isset($data['p1']) || empty($p1 = Verificator::verifyInt($data['p1'], 1, 999))
            || !isset($data['p2']) || empty($p2 = Verificator::verifyInt($data['p2'], 1, 999))
            || !isset($data['start']) || empty($start = Verificator::verifyDate($data['start']))
            || ($p1 != $userContext->getUserId() && $p2 != $userContext->getUserId())) {
                $this->logger->warning($this->request_origin . ' | Match alteration attempt with invalid parameters by user ' . $userContext->getUserId());
                HttpManager::handle_400('Invalid parameters');
        }
        $mm = new MatchManager();
        $match = $mm->updateMatch($id, $p1, $p2, $start->format('Y-m-d H:i:s'));
        if (!$match) {
            $this->logger->error($this->request_origin . ' | Unable to alter match in DB');
            HttpManager::handle_500('Match alteration error');
        }
	  	$match = $mm->getMatchById($id);
        HttpManager::handle_200('', $match);
    }


    public function recordMatch() {
        if ($_SERVER['REQUEST_METHOD'] != 'POST') {
            $this->logger->warning($this->request_origin . ' | Match recording attempt with invalid HTTP method');
            HttpManager::handle_405();
        }
        $userContext = UserContext::getInstance();
        if (!isset($_POST['id']) || empty($id = Verificator::verifyInt($_POST['id'], 1))
            || !isset($_POST['winner_id']) || empty($winner = Verificator::verifyInt($_POST['winner_id'], 1, 999))
            || !isset($_POST['is_forfeit']) || !in_array($forfeit = Verificator::verifyInt($_POST['is_forfeit']), [0, 1])
            || !isset($_POST['contest_level']) || !in_array($contest = Verificator::verifyInt($_POST['contest_level']), [0, 1, 2])
		   	|| !isset($_POST['remaining']) || !in_array($remaining = Verificator::verifyInt($_POST['remaining']), [0, 1, 2, 3, 4, 5, 6, 7])) {
                $this->logger->warning($this->request_origin . ' | Match recording attempt with invalid parameters by user ' . $userContext->getUserId());
                HttpManager::handle_400('Invalid parameters');
        }
        $mm = new MatchManager();
        $match = $mm->getMatchById($id);
	  	$userId = $userContext->getUserId();
        if ($match->player1_id != $userId && $match->player2_id != $userId) {
            $this->logger->warning($this->request_origin . ' | NOT YOUR MATCH by user ' . $userId);
            HttpManager::handle_400('NOT YOUR MATCH');
        }
	  	$currentElo = $mm->getPlayerElo($userId);
		$currentRank = $mm->getPlayerRank($userId);
        $ok = $mm->scoreMatch($id, $winner, (bool)$forfeit, $remaining);
        if (!$ok) {
            $this->logger->error($this->request_origin . ' | Unable to record match in DB');
            HttpManager::handle_500('Match recording error');
        }
        $ok = $mm->adjustRanks($match->player1_id, $match->player2_id, $winner, $contest, date("Y-m-d H:i:s"), $match->id);
        if (!$ok) {
            $this->logger->error($this->request_origin . ' | Unable to update scores in DB');
            HttpManager::handle_500('Score update error');
        }
	  	$newElo = $mm->getPlayerElo($userId);
		$newRank = $mm->getPlayerRank($userId);
	  	$elo = new \stdClass();
	  	$elo->won = ($winner == $userId);
	  	$elo->added = $newElo - $currentElo;
	  	$elo->total = $newElo;
	  	$elo->rank_changed = ($currentRank != $newRank);
	  	$elo->new_rank = $newRank;
        HttpManager::handle_200('', $elo);
    }


    // public function deleteMatch() {
    //     if ($_SERVER['REQUEST_METHOD'] != 'DELETE') {
    //         $this->logger->warning($this->request_origin . ' | Match recording attempt with invalid HTTP method');
    //         HttpManager::handle_405();
    //     }
    //     $userContext = UserContext::getInstance();
    //     if (!isset($_POST['id']) || empty($id = Verificator::verifyInt($_POST['id'], 1))
    //         || !isset($_POST['winner_id']) || empty($winner = Verificator::verifyInt($_POST['winner_id'], 1, 999))
    //         || !isset($_POST['is_forfeit']) || empty($forfeit = Verificator::verifyInt($_POST['is_forfeit'], 0, 1))
    //         || !isset($_POST['contest_level']) || empty($contest = Verificator::verifyInt($_POST['contest_level'], 0, 2))) {
    //             $this->logger->warning($this->request_origin . ' | Match recording attempt with invalid parameters by user ' . $userContext->getUserId());
    //             HttpManager::handle_400('Invalid parameters');
    //     }
    //     $mm = new MatchManager();
    //     $match = $mm->getMatchById($id);
    //     if ($match->player1_id != $userContext->getUserId() && $match->player2_id != $userContext->getUserId()) {
    //         $this->logger->warning($this->request_origin . ' | Match recording attempt with invalid parameters by user ' . $userContext->getUserId());
    //         HttpManager::handle_400('Invalid parameters');
    //     }
    //     $ok = $mm->scoreMatch($id, $winner, (bool)$forfeit);
    //     if (!$ok) {
    //         $this->logger->error($this->request_origin . ' | Unable to record match in DB');
    //         HttpManager::handle_500('Match recording error');
    //     }
    //     $ok = $mm->adjustRanks($match->player1_id, $match->player2_id, $winner, $contest);
    //     if (!$ok) {
    //         $this->logger->error($this->request_origin . ' | Unable to update scores in DB');
    //         HttpManager::handle_500('Score update error');
    //     }
    //     HttpManager::handle_200('', $ok);
    // }

    
    // public function createAccount() {
    //     if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    //         $this->logger->warning($this->request_origin . ' | Account creation attempt with invalid HTTP method');
    //         HttpManager::handle_405();
    //     }
    //     $userContext = UserContext::getInstance();
    //     if ($userContext->getAppLevel() < 4) {
    //         $this->logger->warning($this->request_origin . ' | Account creation attempt without admin rights by user ' . $userContext->getUserId());
    //         HttpManager::handle_403();
    //     }
    //     if (!isset($_POST['userEmail']) || empty($userEmail = Verificator::verifyEmail($_POST['userEmail']))
    //         || !isset($_POST['userLevel']) || empty($userLevel = Verificator::verifyInt($_POST['userLevel'], 1, 3))
    //         || !isset($_POST['userFamilyName']) || empty($userFamilyName = Verificator::verifyText($_POST['userFamilyName'], 1, 100))
    //         || !isset($_POST['userFirstName']) || empty($userFirstName = Verificator::verifyText($_POST['userFirstName'], 1, 100))) {
    //             $this->logger->warning($this->request_origin . ' | Account creation attempt with invalid parameters by user ' . $userContext->getUserId());
    //             HttpManager::handle_400('Invalid parameters');
    //     }
    //     $um = new UserManager();
    //     if ($um->practitionerExistsByIdentifier($userEmail)) {
    //         $this->logger->warning($this->request_origin . ' | Account creation attempt for already existing practitioner by user ' . $userContext->getUserId());
    //         HttpManager::handle_400('User already exists');
    //     } else if ($um->isUserDeleted($userEmail)) {
    //         $this->logger->warning($this->request_origin . ' | Account creation attempt for already deleted practitioner by user ' . $userContext->getUserId());
    //         HttpManager::handle_204('Deleted user account');
    //     }
    //     $key = bin2hex(random_bytes(25));
    //     $insertion = $um->addPractitioner($userFirstName, $userFamilyName, $userEmail, $key, $userLevel);
    //     if (!$insertion) {
    //         $this->logger->error($this->request_origin . ' | Account creation attempt: DB failure for email ' . $userEmail);
    //         HttpManager::handle_500('User creation error');
    //     }
    //     HttpManager::handle_200('Account created successfully');
    // }
  
  
    // public function sendAccountDetails() {
    //     if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    //         $this->logger->warning($this->request_origin . ' | Account details sending attempt with invalid HTTP method');
    //         HttpManager::handle_405();
    //     }
    //     $userContext = UserContext::getInstance();
    //     if ($userContext->getAppLevel() < 4) {
    //         $this->logger->warning($this->request_origin . ' | Account details sending attempt without admin rights by user ' . $userContext->getUserId());
    //         HttpManager::handle_403();
    //     }
    //     if (!isset($_POST['userId']) || empty($userId = Verificator::verifyInt($_POST['userId'], 1))) {
    //             $this->logger->warning($this->request_origin . ' | Account details sending attempt with invalid parameters by user ' . $userContext->getUserId());
    //             HttpManager::handle_400('Invalid parameters');
    //     }
    //     $um = new UserManager();
    //     if (!$user = $um->getPractitionerById($userId)) {
    //         $this->logger->warning($this->request_origin . ' | Account details sending attempt for non existing practitioner by user ' . $userContext->getUserId());
    //         HttpManager::handle_400('User does not exists');
    //     } else if ($um->isUserDeleted($user->identifier)) {
    //         $this->logger->warning($this->request_origin . ' | Account details sending attempt for deleted practitioner by user ' . $userContext->getUserId());
    //         HttpManager::handle_204('Deleted user account');
    //     }
    //     $s = DIRECTORY_SEPARATOR;
    //     $rootPath = strstr(__DIR__, 'Controller', true);
    //     $path =  $rootPath . 'view' . $s . 'register.html';
    //     $sending = Utilities::sendEmail(
    //         $user->identifier,
    //         'Votre profil praticien AST-BAZI',
    //         str_replace('%activationKey%', $user->activation_key, file_get_contents($path))
    //     );
    //     if ($sending == 0) {
    //         $this->logger->error($this->request_origin . ' | Email sending error for user ' . $user->identifier);
    //         HttpManager::handle_500('Email sending failed');
    //     }
    //     HttpManager::handle_200('Email successfully sent');
    // }


    // public function reactivateAccount() {
    //     if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    //         $this->logger->warning($this->request_origin . ' | Account reactivation attempt with invalid HTTP method');
    //         HttpManager::handle_405();
    //     }
    //     $userContext = UserContext::getInstance();
    //     if ($userContext->getAppLevel() < 4) {
    //         $this->logger->warning($this->request_origin . ' | Account reactivation attempt without admin rights by user ' . $userContext->getUserId());
    //         HttpManager::handle_403();
    //     }
    //     if (!isset($_POST['userEmail']) || empty($userEmail = Verificator::verifyEmail($_POST['userEmail']))) {
    //             $this->logger->warning($this->request_origin . ' | Account reactivation attempt with invalid parameters by user ' . $userContext->getUserId());
    //             HttpManager::handle_400('Invalid parameters');
    //     }
    //     $um = new UserManager();
    //     if ($um->practitionerExistsByIdentifier($userEmail)) {
    //         $this->logger->warning($this->request_origin . ' | Account reactivation attempt for already existing practitioner by user ' . $userContext->getUserId());
    //         HttpManager::handle_400('User already exists');
    //     }
    //     $key = bin2hex(random_bytes(25));
    //     $reactivation = $um->profileReactivation($userEmail, $key);
    //     if (!$reactivation) {
    //         $this->logger->error($this->request_origin . ' | Account reactivation attempt: DB failure for email ' . $userEmail);
    //         HttpManager::handle_500('User reactivation error');
    //     }
    //     $s = DIRECTORY_SEPARATOR;
    //     $rootPath = strstr(__DIR__, 'Controller', true);
    //     $path =  $rootPath . 'view' . $s . 'register.html';
    //     $sending = Utilities::sendEmail(
    //         $userEmail,
    //         'Réactivation de votre profil praticien AST-BAZI',
    //         str_replace('%activationKey%', $key, file_get_contents($path))
    //     );
    //     if ($sending == 0) {
    //         $this->logger->error($this->request_origin . ' | Email sending error for user ' . $userEmail);
    //         HttpManager::handle_500('Email sending failed');
    //     }
    //     HttpManager::handle_200('Account reactivated successfully');
    // }


    // public function modifyPractitioner() {
    //     if ($_SERVER['REQUEST_METHOD'] != 'PUT') {
    //         $this->logger->warning($this->request_origin . ' | Account modification attempt with invalid HTTP method');
    //         HttpManager::handle_405();
    //     }
    //     $userContext = UserContext::getInstance();
    //     if ($userContext->getAppLevel() < 4) {
    //         $this->logger->warning($this->request_origin . ' | Account modification attempt without admin rights by user ' . $userContext->getUserId());
    //         HttpManager::handle_403();
    //     }
    //     $data = json_decode(file_get_contents('php://input'), true);
    //     if (!isset($data['userId']) || empty($userId = Verificator::verifyInt($data['userId'], 1, 99999))
    //         || !isset($data['userEmail']) || empty($userEmail = Verificator::verifyEmail($data['userEmail']))
    //         || !isset($data['userLevel']) || empty($userLevel = Verificator::verifyInt($data['userLevel'], 1, 3))
    //         || !isset($data['userFamilyName']) || empty($userFamilyName = Verificator::verifyText($data['userFamilyName'], 1, 100))
    //         || !isset($data['userFirstName']) || empty($userFirstName = Verificator::verifyText($data['userFirstName'], 1, 100))) {
    //             $this->logger->warning($this->request_origin . ' | Account modification attempt with invalid parameters by admin user ' . $userContext->getUserId());
    //             HttpManager::handle_400('Invalid parameters');
    //     }
    //     $um = new UserManager();
    //     if (!$um->updateUser($userId, $userFirstName, $userFamilyName, $userEmail, $userLevel)) {
    //         $this->logger->error($this->request_origin . ' | Unable to perform user update in DB');
    //         HttpManager::handle_500('Practitioner account modification error');
    //     }
    //     HttpManager::handle_200('Practitioner account successfully modified');
    // }


    // public function modifyPractitionerActivityStatus() {
    //     if ($_SERVER['REQUEST_METHOD'] != 'PUT') {
    //         $this->logger->warning($this->request_origin . ' | Account status alteration attempt with invalid HTTP method');
    //         HttpManager::handle_405();
    //     }
    //     $userContext = UserContext::getInstance();
    //     if ($userContext->getAppLevel() < 4) {
    //         $this->logger->warning($this->request_origin . ' | Account status alteration attempt without admin rights by user ' . $userContext->getUserId());
    //         HttpManager::handle_403();
    //     }
    //     $data = json_decode(file_get_contents('php://input'), true);
    //     if (!isset($data['userId']) || ($userId = Verificator::verifyInt($data['userId'], 1, 99999)) === NULL
    //         || !isset($data['userActive']) || ($userActive = Verificator::verifyInt($data['userActive'], 0, 1)) === NULL) {
    //             $this->logger->warning($this->request_origin . ' | Account status alteration attempt with invalid parameters by user ' . $userContext->getUserId());
    //             HttpManager::handle_400('Invalid parameters');
    //     }
    //     $um = new UserManager();
    //     if (!$um->profileActivation($userId, $userActive)) {
    //         $this->logger->error($this->request_origin . ' | Unable to perform user status alteration for user ' . $userId);
    //         HttpManager::handle_500('User status alteration error');
    //     }
    //     if ($userActive == 1) {
    //         if (!$um->resetAttempts($userId)) {
    //             $this->logger->error($this->request_origin . ' | Unable to reset authentication attempts for user ' . $userId);
    //             HttpManager::handle_500('User status alteration error');
    //         }
    //     }
    //     HttpManager::handle_200('Practitioner active status successfully modified');
    // }


    // public function deletePractitioner() {
    //     if ($_SERVER['REQUEST_METHOD'] != 'DELETE') {
    //         $this->logger->warning($this->request_origin . ' | Account deletion attempt with invalid HTTP method');
    //         HttpManager::handle_405();
    //     }
    //     $userContext = UserContext::getInstance();
    //     if ($userContext->getAppLevel() < 4) {
    //         $this->logger->warning($this->request_origin . ' | Account deletion attempt without admin rights by user ' . $userContext->getUserId());
    //         HttpManager::handle_403();
    //     }
    //     $data = json_decode(file_get_contents('php://input'), true);
    //     if (!isset($data['userId']) || empty($userId = Verificator::verifyInt($data['userId'], 1, 99999))) {
    //             $this->logger->warning($this->request_origin . ' | Account deletion attempt with invalid parameters by user ' . $userContext->getUserId());
    //             HttpManager::handle_400('Invalid parameters');
    //     }
    //     $um = new UserManager();
    //     if (!$um->deletePractitionerById($userId)) {
    //         $this->logger->error($this->request_origin . ' | Unable to perform user deletion in DB');
    //         HttpManager::handle_500('User deletion error');
    //     }
    //     HttpManager::handle_200('Practitioner successfully deleted');
    // }

}


