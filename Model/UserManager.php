<?php

namespace LBPool\Model;

use LBPool\Utils\Connection;


class UserManager {

    private $cnx;
    
    public function __construct() {
        $this->cnx = Connection::getInstance()->getConnection();
    }


    //****************************************
    // EXISTENCE
    //****************************************

    public function playerExistsById(int $id) {
        try {
            $sentence = 'SELECT COUNT(id) FROM players WHERE id=:id AND deleted_at IS NULL;';
            $q = $this->cnx->prepare($sentence);
            $q->bindValue(':id', $id);
            $q->execute();
            return ((int)$q->fetch(\PDO::FETCH_COLUMN) > 0);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function playerExistsByEmail(string $email) {
        try {
            $sentence = 'SELECT COUNT(id) FROM players WHERE email=:e AND deleted_at IS NULL;';
            $q = $this->cnx->prepare($sentence);
            $q->bindValue(':e', $email);
            $q->execute();
            return ((int)$q->fetch(\PDO::FETCH_COLUMN) > 0);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function checkPlayerToken(string $email, string $token) {
        try {
            $sentence = 'SELECT token FROM players WHERE email=:e AND deleted_at IS NULL;';
            $q = $this->cnx->prepare($sentence);
            $q->bindValue(':e', $email);
            $q->execute();
            return ($q->fetch(\PDO::FETCH_COLUMN) == $token);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function checkPlayerActive(string $email) {
        try {
            $sentence = 'SELECT active FROM players WHERE email=:e AND deleted_at IS NULL;';
            $q = $this->cnx->prepare($sentence);
            $q->bindValue(':e', $email);
            $q->execute();
            return ((int)$q->fetch(\PDO::FETCH_COLUMN) == 1);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function isPlayerDeleted(string $email) {
        try {
            $sentence = 'SELECT deleted_at FROM players WHERE email=:e;';
            $q = $this->cnx->prepare($sentence);
            $q->bindValue(':e', $email);
            $q->execute();
            return ($q->fetch(\PDO::FETCH_COLUMN) != NULL);
        } catch (\Exception $e) {
            return false;
        }
    }


    //****************************************
    // SELECTIONS
    //****************************************

    public function getPlayerById(int $id) {
        try {
            $sentence = 'SELECT id, name, email, mdp, token, token_validity, remaining_attempts, active, elo 
                FROM players WHERE id=:id AND deleted_at IS NULL;';
            $q = $this->cnx->prepare($sentence);
            $q->bindValue(':id', $id, \PDO::PARAM_INT);
            $q->execute();
            return $q->fetch(\PDO::FETCH_OBJ);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getPlayerByEmail(string $email) {
        try {
            $sentence = 'SELECT id, name, email, mdp, token, token_validity, remaining_attempts, active, elo 
                FROM players WHERE email=:e AND deleted_at IS NULL;';
            $q = $this->cnx->prepare($sentence);
            $q->bindValue(':e', $email);
            $q->execute();
            return $q->fetch(\PDO::FETCH_OBJ);
        } catch (\Exception $e) {
            return false;
        }
    }

    // public function getPlayers() {
    //     try {
    //         $sentence = 'SELECT id, name, total_matches, wins, losses, forfeits, current_streak '
    //             . 'FROM players '
    //             . 'WHERE active=1 AND deleted_at IS NULL;';
    //         $q = $this->cnx->prepare($sentence);
    //         $q->execute();
    //         return $q->fetchAll(\PDO::FETCH_OBJ);
    //     } catch (\Exception $e) {
    //         return false;
    //     }
    // }

    // public function getStatsByPlayerId(int $id) {
    //     try {
    //         $sentence = 'SELECT AVG(total_matches), wins, losses, forfeits, current_streak '
    //             . 'FROM players '
    //             . 'WHERE active=1 AND deleted_at IS NULL;';
    //         $q = $this->cnx->prepare($sentence);
    //         $q->execute();
    //         return $q->fetchAll(\PDO::FETCH_OBJ);
    //     } catch (\Exception $e) {
    //         return false;
    //     }
    // }


    //****************************************
    // CREATE
    //****************************************

    // public function addPractitioner(string $firstName, string $familyName, string $identifier, string $activationKey, int $appLevel) {
    //     try {
    //         $sentence = "INSERT INTO practitioner (first_name, family_name, identifier, activation_key, app_level) "
    //             . "VALUES (:fir, :nam, :ide, :act, :lev);";
    //         $q = $this->cnx->prepare($sentence);
    //         $q->bindValue('fir', $firstName);
    //         $q->bindValue('nam', $familyName);
    //         $q->bindValue('ide', $identifier);
    //         $q->bindValue('act', $activationKey);
    //         $q->bindValue('lev', $appLevel, \PDO::PARAM_INT);
    //         return $q->execute();
    //     } catch (\Exception $e) {
    //         return false;
    //     }
    // }


    //****************************************
    // UPDATE
    //****************************************


    public function setToken(int $id, string $token) {
        try {
            $sentence = "UPDATE players SET token=:tok, token_validity=CURRENT_TIMESTAMP WHERE id=:id AND deleted_at IS NULL;";
            $q = $this->cnx->prepare($sentence);
            $q->bindValue(':tok', $token);
            $q->bindValue(':id', $id, \PDO::PARAM_INT);
            return $q->execute();
        } catch (\Exception $e) {
            return false;
        }
    }

    public function unsetToken(int $id) {
        try {
            $sentence = "UPDATE players SET token=null, token_validity=null WHERE id=:id AND deleted_at IS NULL;";
            $q = $this->cnx->prepare($sentence);
            $q->bindValue(':id', $id, \PDO::PARAM_INT);
            return $q->execute();
        } catch (\Exception $e) {
            return false;
        }
    }

    public function decreaseAttempts(int $id) {
        try {
            $sentence = "UPDATE players "
                . "SET remaining_attempts = CASE "
                . "WHEN remaining_attempts > 1 THEN remaining_attempts - 1 "
                . "ELSE 0 "
                . "END, "
                . "active = CASE "
                . "WHEN remaining_attempts <= 1 THEN 0 "
                . "ELSE active "
                . "END "
                . "WHERE id=:id AND deleted_at IS NULL;";
            $q = $this->cnx->prepare($sentence);
            $q->bindValue(':id', $id, \PDO::PARAM_INT);
            return $q->execute();
        } catch (\Exception $e) {
            return false;
        }
    }

    public function resetAttempts(int $id) {
        try {
            $sentence = "UPDATE players SET remaining_attempts = 5 WHERE id=:id AND deleted_at IS NULL;";
            $q = $this->cnx->prepare($sentence);
            $q->bindValue(':id', $id, \PDO::PARAM_INT);
            return $q->execute();
        } catch (\Exception $e) {
            return false;
        }
    }

    public function updatePassword(int $id, string $pass) {
        try {
            $sentence = "UPDATE players SET mdp=:mdp WHERE id=:id AND deleted_at IS NULL;";
            $q = $this->cnx->prepare($sentence);
            $q->bindValue(':mdp', $pass);
            $q->bindValue('id', $id, \PDO::PARAM_INT);
            return $q->execute();
        } catch (\Exception $e) {
            return false;
        }
    }

    public function profileActivation(int $id, int $newState) {
        try {
            $sentence = "UPDATE players SET active=:act WHERE id=:id AND deleted_at IS NULL;";
            $q = $this->cnx->prepare($sentence);
            $q->bindValue(':id', $id, \PDO::PARAM_INT);
            $q->bindValue(':act', $newState, \PDO::PARAM_INT);
            return $q->execute();
        } catch (\Exception $e) {
            return false;
        }
    }

    public function profileReactivation(string $email, string $token) {
        try {
            $sentence = "UPDATE players SET token=:tok, mdp=NULL, active=0, deleted_at=NULL WHERE email=:e;";
            $q = $this->cnx->prepare($sentence);
            $q->bindValue(':e', $email);
            $q->bindValue(':tok', $token);
            return $q->execute();
        } catch (\Exception $e) {
            return false;
        }
    }

    public function playerProfileValidation(string $email, string $password) {
        try {
            $sentence = "UPDATE players SET active=1, mdp=:pas WHERE email=:e AND deleted_at IS NULL;";
            $q = $this->cnx->prepare($sentence);
            $q->bindValue(':pas', $password);
            $q->bindValue(':e', $email);
            return $q->execute();
        } catch (\Exception $e) {
            return false;
        }
    }

    public function updatePlayer(int $id, string $name, string $email) {
        try {
            $sentence = 'UPDATE players SET name=:n, email=:e WHERE id=:id AND deleted_at IS NULL;';
            $q = $this->cnx->prepare($sentence);
            $q->bindValue(':n', $name);
            $q->bindValue(':e', $email);
            $q->bindValue(':id', $id, \PDO::PARAM_INT);
            return $q->execute();
        } catch (\Exception $e) {
            return false;
        }
    }

}