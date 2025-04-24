<?php

namespace LBPool\Model;

use DateTime;
use LBPool\Utils\Connection;


class MatchManager {

    private $cnx;
    
    public function __construct() {
        $this->cnx = Connection::getInstance()->getConnection();
    }


    //****************************************
    // SELECTIONS
    //****************************************


    public function getStatsByPlayerId(int $id) {
        try {
            $sentence = 'SELECT
			  (SELECT COUNT(*) 
			   FROM matches 
			   WHERE (player1_id = :pid OR player2_id = :pid) AND deleted_at IS NULL) AS nb_matches,
		  
			  (SELECT COUNT(*) 
			   FROM matches 
			   WHERE winner_id = :pid AND deleted_at IS NULL) AS nb_wins,
		  
			  COALESCE(
				  (SELECT COUNT(*) 
				   FROM matches 
				   WHERE winner_id = :pid AND deleted_at IS NULL) * 1.0 
				  / NULLIF((SELECT COUNT(*) 
							FROM matches 
							WHERE (player1_id = :pid OR player2_id = :pid) AND deleted_at IS NULL), 0),
				  0
			  ) AS win_rate,
		  
			  (SELECT COUNT(*) 
			   FROM matches 
			   WHERE (player1_id = :pid OR player2_id = :pid) AND winner_id != :pid AND is_forfeit = 1 AND deleted_at IS NULL) AS nb_forfeits,
		  
			  (SELECT COUNT(*) 
			   FROM matches 
			   WHERE (player1_id = :pid OR player2_id = :pid) AND winner_id != :pid AND deleted_at IS NULL) AS nb_losses,
		  
			  (SELECT p.name FROM (
				  SELECT 
					  CASE 
						  WHEN player1_id = :pid THEN player2_id 
						  ELSE player1_id 
					  END AS opponent_id,
					  COUNT(*) AS wins_against_opponent
				  FROM matches
				  WHERE winner_id = :pid 
					AND (player1_id = :pid OR player2_id = :pid) 
					AND deleted_at IS NULL
				  GROUP BY opponent_id
				  ORDER BY wins_against_opponent DESC
				  LIMIT 1
			  ) AS punching_bag
			  JOIN players p ON p.id = punching_bag.opponent_id) AS punching_bag_name,
		  
			  (SELECT wins_against_opponent FROM (
				  SELECT 
					  CASE WHEN player1_id = :pid THEN player2_id ELSE player1_id END AS opponent_id,
					  COUNT(*) AS wins_against_opponent
				  FROM matches
				  WHERE winner_id = :pid AND (player1_id = :pid OR player2_id = :pid) AND deleted_at IS NULL
				  GROUP BY opponent_id
				  ORDER BY wins_against_opponent DESC
				  LIMIT 1
			  ) AS punching_bag) AS punching_bag_wins,
		  
			  (SELECT p.name FROM (
				  SELECT 
					  CASE 
						  WHEN player1_id = :pid THEN player2_id 
						  ELSE player1_id 
					  END AS opponent_id,
					  COUNT(*) AS losses_against_opponent
				  FROM matches
				  WHERE winner_id != :pid 
					AND (player1_id = :pid OR player2_id = :pid) 
					AND deleted_at IS NULL
				  GROUP BY opponent_id
				  ORDER BY losses_against_opponent DESC
				  LIMIT 1
			  ) AS slayer
			  JOIN players p ON p.id = slayer.opponent_id) AS slayer_name,
		  
			  (SELECT losses_against_opponent FROM (
				  SELECT 
					  CASE WHEN player1_id = :pid THEN player2_id ELSE player1_id END AS opponent_id,
					  COUNT(*) AS losses_against_opponent
				  FROM matches
				  WHERE winner_id != :pid AND (player1_id = :pid OR player2_id = :pid) AND deleted_at IS NULL
				  GROUP BY opponent_id
				  ORDER BY losses_against_opponent DESC
				  LIMIT 1
			  ) AS slayer) AS slayer_losses
		  
		  	FROM (SELECT 1) AS dummy;';
            $q = $this->cnx->prepare($sentence);
            $q->bindValue(':pid', $id, \PDO::PARAM_INT);
            $q->execute();
		  	return $q->fetch(\PDO::FETCH_OBJ);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getGeneralStats() {
        try {
            $sentence = 'SELECT
				(SELECT COUNT(*) FROM players WHERE deleted_at IS NULL) AS nb_players,
				(SELECT COUNT(*) * 1.0 / NULLIF(COUNT(DISTINCT p.id), 0)
					FROM players p
				   	JOIN matches m ON p.id = m.player1_id OR p.id = m.player2_id
				   	WHERE p.deleted_at IS NULL AND m.deleted_at IS NULL) AS avg_matches_per_player,
				(SELECT MAX(wins * 1.0 / NULLIF(total_matches, 0)) 
				   	FROM (
					  	SELECT 
						p.id AS player_id,
						COUNT(m.id) AS total_matches,
						SUM(CASE WHEN m.winner_id = p.id THEN 1 ELSE 0 END) AS wins
					  	FROM players p
					  	LEFT JOIN matches m ON p.id = m.player1_id OR p.id = m.player2_id
					  	WHERE p.deleted_at IS NULL AND m.deleted_at IS NULL
					  	GROUP BY p.id
				 	) AS stats
				) AS max_win_rate
                FROM (SELECT 1) AS dummy;';
            $q = $this->cnx->prepare($sentence);
            $q->execute();
            return $q->fetch(\PDO::FETCH_OBJ);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getPlayers() {
        try {
            $sentence = 'SELECT 
				  p.id,
				  p.name,
				  p.elo,
				  COUNT(m.id) AS total_matches,
				  SUM(CASE WHEN m.winner_id = p.id THEN 1 ELSE 0 END) AS wins,
				  SUM(CASE WHEN m.winner_id = p.id THEN 1 ELSE 0 END) * 1.0 
					  / NULLIF(COUNT(m.id), 0) AS win_rate
			  FROM 
				  players p
			  LEFT JOIN 
				  matches m ON p.id = m.player1_id OR p.id = m.player2_id
			  WHERE 
				  p.deleted_at IS NULL AND m.deleted_at IS NULL
			  GROUP BY 
				  p.id, p.name, p.elo
			  ORDER BY 
				  p.elo DESC;';
            $q = $this->cnx->prepare($sentence);
            $q->execute();
            return $q->fetchAll(\PDO::FETCH_OBJ);
        } catch (\Exception $e) {
            return false;
        }
    }
  
  	public function getFutureMatchesByPlayerId(int $id) {
        try {
		  $sentence = 'SELECT 
		  		m.id,
				m.start_time,
				opp.name AS opponent_name
			FROM matches m
			JOIN players opp ON opp.id = 
				CASE 
					WHEN m.player1_id = :pid THEN m.player2_id
					ELSE m.player1_id
				END
			WHERE 
				(m.player1_id = :pid OR m.player2_id = :pid)
				AND m.start_time > NOW()
				AND m.deleted_at IS NULL
				AND opp.deleted_at IS NULL
			ORDER BY m.start_time ASC;';
            $q = $this->cnx->prepare($sentence);
            $q->bindValue(':pid', $id, \PDO::PARAM_INT);
            $q->execute();
            return $q->fetchAll(\PDO::FETCH_OBJ);
        } catch (\Exception $e) {
            return false;
        }
    }
  
  	public function getLastCreatedMatchByPlayerId(int $id) {
        try {
		  $sentence = 'SELECT m.*, p1.name as p1_name, p2.name as p2_name
			FROM matches m
			JOIN players p1 ON p1.id = m.player1_id
			JOIN players p2 ON p2.id = m.player2_id
			WHERE 
				(m.player1_id = :pid OR m.player2_id = :pid)
				AND m.deleted_at IS NULL
				AND p1.deleted_at IS NULL
				AND p2.deleted_at IS NULL
			ORDER BY m.id DESC LIMIT 1;';
            $q = $this->cnx->prepare($sentence);
            $q->bindValue(':pid', $id, \PDO::PARAM_INT);
            $q->execute();
            return $q->fetch(\PDO::FETCH_OBJ);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getMatchById(int $id) {
        try {
            $sentence = 'select m.*, p1.name as p1_name, p2.name as p2_name
				from matches m
				join players p1 on p1.id = m.player1_id
				join players p2 on p2.id = m.player2_id
				where m.id=:id
				and m.deleted_at is null
				and p1.deleted_at is null
				and p2.deleted_at is null';
            $q = $this->cnx->prepare($sentence);
            $q->bindValue(':id', $id, \PDO::PARAM_INT);
            $q->execute();
            return $q->fetch(\PDO::FETCH_OBJ);
        } catch (\Exception $e) {
            return false;
        }
    }
  
  
    
    public function getMatchesByPlayerId(int $id) {
        try {
            $sentence = 'select m.id, m.player1_id, m.player2_id, m.winner_id, m.is_forfeit, m.start_time, m.reward, m.remaining,
				p1.name as p1_name, p2.name as p2_name,
                c.name as championship, t.name as tournament 
                from matches m 
				join players p1 on p1.id=m.player1_id
				join players p2 on p2.id=m.player2_id
                left join championship_match cm on m.id=cm.match_id 
                left join championship c on cm.championship_id=c.id 
                left join tournament_match tm on m.id=tm.match_id 
                left join tournament t on tm.tournament_id=t.id 
                where (m.player1_id=:id or m.player2_id=:id)
                and m.deleted_at is null
                order by m.start_time desc;';
            $q = $this->cnx->prepare($sentence);
            $q->bindValue(':id', $id, \PDO::PARAM_INT);
            $q->execute();
            return $q->fetchAll(\PDO::FETCH_OBJ);
        } catch (\Exception $e) {
            return false;
        }
    }
  
	public function getPersonalStreaks(int $id) {
	  	$winners = $this->getOrderedWinnerListByPlayerId($id);
	  	$streak = new \stdClass();
	  	if (is_bool($winners)) {
			$streak->current = 0;
		  	$streak->best = 0;
		  	return $streak;
		}
		$bestStreak = 0;
		$cursor = 0;
		foreach ($winners as $w) {
			if ($w == $id) {
				$cursor++;
				if ($cursor > $bestStreak) {
					$bestStreak = $cursor;
				}
			} else {
				$cursor = 0;
			}
		}
		$winnersReversed = array_reverse($winners);
		$currentStreak = 0;
		foreach ($winnersReversed as $w) {
			if ($w == $id) {
				$currentStreak++;
			} else {
				break;
			}
		}
	  	$streak->best = $bestStreak;
	  	$streak->current = $currentStreak;
	  	return $streak;
	}
  
    public function getOrderedWinnerListByPlayerId(int $id) {
        try {
            $sentence = 'SELECT winner_id
			  FROM matches
			  WHERE (player1_id = :pid OR player2_id = :pid) AND deleted_at IS NULL
			  ORDER BY start_time ASC;';
            $q = $this->cnx->prepare($sentence);
            $q->bindValue(':id', $id, \PDO::PARAM_INT);
            $q->execute();
            return $q->fetchAll(\PDO::FETCH_COLUMN);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getMatchCountByPlayerId(int $id) {
        try {
            $sentence = 'select count(*) from matches where (player1_id=:id or player2_id=:id) and deleted_at is null;';
            $q = $this->cnx->prepare($sentence);
            $q->bindValue(':id', $id, \PDO::PARAM_INT);
            $q->execute();
            return $q->fetch(\PDO::FETCH_COLUMN);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getMatchCountByPairOfPlayers(int $p1, int $p2) {
        try {
            $sentence = 'select count(*) from matches 
                where ((player1_id=:p1 and player2_id=:p2) or (player1_id=:p2 and player2_id=:p1))
                and deleted_at is null
                and start_time > DATE_SUB(NOW(), INTERVAL 30 DAY);';
            $q = $this->cnx->prepare($sentence);
            $q->bindValue(':id', $p1, \PDO::PARAM_INT);
            $q->bindValue(':id', $p2, \PDO::PARAM_INT);
            $q->execute();
            return $q->fetch(\PDO::FETCH_COLUMN);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getMatchesByChampionshipId(int $id) {
        try {
            $sentence = 'select m.id, m.player1_id, m.player2_id, m.winner_id, m.is_forfeit, m.start_time, m.reward, m.remaining
                from matches m 
                left join championship_match cm on m.id=cm.match_id 
                left join championship c on cm.championship_id=c.id 
                where c.id=1 and m.deleted_at is null
                order by m.start_time desc;';
            $q = $this->cnx->prepare($sentence);
            $q->bindValue(':id', $id, \PDO::PARAM_INT);
            $q->execute();
            return $q->fetchAll(\PDO::FETCH_OBJ);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getMatchesByTournamentId(int $id) {
        try {
            $sentence = 'select m.id, m.player1_id, m.player2_id, m.winner_id, m.is_forfeit, m.start_time, m.reward, m.remaining
                from matches m 
                left join tournament_match tm on m.id=tm.match_id 
                left join tournament t on tm.tournament_id=t.id 
                where t.id=1 and m.deleted_at is null
                order by m.start_time desc;';
            $q = $this->cnx->prepare($sentence);
            $q->bindValue(':id', $id, \PDO::PARAM_INT);
            $q->execute();
            return $q->fetchAll(\PDO::FETCH_OBJ);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getPlayerElo(int $player_id) {
        try {
            $sentence = 'select elo from players where id=:pid and deleted_at is null';
            $q = $this->cnx->prepare($sentence);
            $q->bindValue(':pid', $player_id, \PDO::PARAM_INT);
            $q->execute();
            return $q->fetch(\PDO::FETCH_COLUMN);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getPlayerRank(int $player_id) {
        try {
            $sentence = 'SELECT COUNT(*) + 1 AS player_rank
			  FROM players
			  WHERE deleted_at IS NULL
				AND elo > (
				  SELECT elo FROM players WHERE id = :pid AND deleted_at IS NULL
				);';
            $q = $this->cnx->prepare($sentence);
            $q->bindValue(':pid', $player_id, \PDO::PARAM_INT);
            $q->execute();
            return $q->fetch(\PDO::FETCH_COLUMN);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getEloHistory(int $player_id) {
        try {
            $sentence = 'select eh.elo, eh.recorded_at
                from elo_history eh
                join players p on p.id=eh.player_id
                where eh.recorded_at > (now() - interval 3 month)
                and eh.player_id=:pid
                and p.deleted_at is null
                order by recorded_at asc;';
            $q = $this->cnx->prepare($sentence);
            $q->bindValue(':pid', $player_id, \PDO::PARAM_INT);
            $q->execute();
            return $q->fetchAll(\PDO::FETCH_OBJ);
        } catch (\Exception $e) {
            return false;
        }
    }


    //****************************************
    // INSERT / UPDATE
    //****************************************


    public function addMatch(int $player1_id, int $player2_id, string $start_time) {
        try {
            $end = DateTime::createFromFormat('Y-m-d H:i:s', $start_time);
            $end->modify('+15 minutes');
            $end_time = $end->format('Y-m-d H:i:s');
            $sentence = 'INSERT INTO matches (player1_id, player2_id, start_time, end_time)
                VALUES (:p1, :p2, :st, :et);';
            $q = $this->cnx->prepare($sentence);
            $q->bindValue(':p1', $player1_id, \PDO::PARAM_INT);
            $q->bindValue(':p2', $player2_id, \PDO::PARAM_INT);
            $q->bindValue(':st', $start_time);
            $q->bindValue(':et', $end_time);
            return $q->execute();
        } catch (\Exception $e) {
            return false;
        }
    }

    public function updateMatch(int $id, int $player1_id, int $player2_id, string $start_time) {
        try {
            $end = DateTime::createFromFormat('Y-m-d H:i:s', $start_time);
            $end->modify('+15 minutes');
            $end_time = $end->format('Y-m-d H:i:s');
            $sentence = 'UPDATE matches 
                SET player1_id=:p1, player2_id=:p2, start_time=:st, end_time=:et 
                WHERE id=:id;';
            $q = $this->cnx->prepare($sentence);
            $q->bindValue(':id', $id, \PDO::PARAM_INT);
            $q->bindValue(':p1', $player1_id, \PDO::PARAM_INT);
            $q->bindValue(':p2', $player2_id, \PDO::PARAM_INT);
            $q->bindValue(':st', $start_time);
            $q->bindValue(':et', $end_time);
            return $q->execute();
        } catch (\Exception $e) {
            return false;
        }
    }

    public function scoreMatch(int $id, int $winner_id, bool $is_forfeit, int $remaining) {
        try {
            $sentence = 'UPDATE matches 
                SET winner_id=:win, is_forfeit=:f, remaining=:r
                WHERE id=:id';
            $q = $this->cnx->prepare($sentence);
            $q->bindValue(':id', $id, \PDO::PARAM_INT);
            $q->bindValue(':win', $winner_id, \PDO::PARAM_INT);
            $q->bindValue(':f', (int)$is_forfeit, \PDO::PARAM_INT);
		  	$q->bindValue(':r', $remaining, \PDO::PARAM_INT);
            return $q->execute();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * contest_level :
     * 0 = none
     * 1 = prelim
     * 2 = finals
     */
    public function adjustRanks(int $player1_id, int $player2_id, int $winner_id, int $contest_level, string $end, int $match_id) {
        try {
            $p1_elo = $this->getPlayerElo($player1_id);
            $p2_elo = $this->getPlayerElo($player2_id);
            $k = ($contest_level == 0) ? 32 : (($contest_level == 1) ? 40 : 50);
            $s = ($winner_id == $player1_id) ? 1 : 0;
            $e = 1 / (1 + pow(10, ($p2_elo - $p1_elo) / 400));
            $p1_elo += (int) round($k * ($s - $e));
            $p2_elo += (int) round($k * ((1 - $s) - (1 - $e)));
            $this->updatePlayerElo($player1_id, $p1_elo);
            $this->updatePlayerElo($player2_id, $p2_elo);
            $this->updatePlayerHistory($player1_id, $p1_elo, $end);
            $this->updatePlayerHistory($player2_id, $p2_elo, $end);
		  	$this->updateMatchReward($match_id, abs((int) round($k * ($s - $e))));
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function updatePlayerElo(int $player_id, int $elo) {
        try {
            $sentence = 'update players set elo=:e where id=:pid and deleted_at is null;';
            $q = $this->cnx->prepare($sentence);
            $q->bindValue(':e', $elo, \PDO::PARAM_INT);
            $q->bindValue(':pid', $player_id, \PDO::PARAM_INT);
            $q->execute();
            return $q->fetch(\PDO::FETCH_COLUMN);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function updatePlayerHistory(int $player_id, int $elo, string $end) {
        try {
            $sentence = 'insert into elo_history (player_id, elo, recorded_at) values (:pid, :elo, :end);';
            $q = $this->cnx->prepare($sentence);
            $q->bindValue(':pid', $player_id, \PDO::PARAM_INT);
            $q->bindValue(':elo', $elo, \PDO::PARAM_INT);
            $q->bindValue(':end', $end);
            $q->execute();
            return $q->fetch(\PDO::FETCH_COLUMN);
        } catch (\Exception $e) {
            return false;
        }
    }
  
  	public function updateMatchReward(int $match_id, int $elo) {
        try {
            $sentence = 'update matches set reward=:e where id=:id and deleted_at is null;';
            $q = $this->cnx->prepare($sentence);
            $q->bindValue(':e', $elo, \PDO::PARAM_INT);
            $q->bindValue(':id', $match_id, \PDO::PARAM_INT);
            $q->execute();
            return $q->fetch(\PDO::FETCH_COLUMN);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function deleteMatch(int $id) {
        try {
            $sentence = 'update matches set deleted_at=CURRENT_TIMESTAMP where id=:id';
            $q = $this->cnx->prepare($sentence);
            $q->bindValue(':id', $id, \PDO::PARAM_INT);
            $q->execute();
            return $q->fetch(\PDO::FETCH_COLUMN);
        } catch (\Exception $e) {
            return false;
        }
    }

}