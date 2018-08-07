<?php
include("db.php");

class Database {

    private static $db;
    private $connection;

    private function __construct() {
        $this->connection = new MySQLi(HOST, USERNAME, PASSWORD, DATABASE, PORT);

        if ($this->connection->connect_errno) {
            $GLOBALS['log'] .= "Failed to connect to MySQL: ("
                               .$mysqli->connect_errno.") "
                               .$mysqli->connect_error;
        }

        $GLOBALS['log'] .= "Connection successfull: ";
        $GLOBALS['log'] .= $this->connection->host_info."\n";
    }

    function __destruct() {
        $this->connection->close();
    }

    public static function getConnection() {
        if (self::$db == null) {
            self::$db = new Database();
        }
        return self::$db->connection;
    }


    public function preparePost($post, $parsedPost) {
      $apiUser = file_get_contents (
                   "https://osu.ppy.sh/api/get_user?k=".API_KEY.
                   "&u=".$parsedPost["player"]."&type=string"
                 );
      $user = json_decode($apiUser);

      //if the api can find the username, check if its in the DB and insert
      if ($user != null) {
        $dbUser = Database::checkPlayerDB($user[0]->user_id);

        if ($dbUser->num_rows == 0) {
          Database::insertNewPlayer($user);
          $dbUser = Database::checkPlayerDB($user[0]->user_id);
        }
        if ($dbUser->num_rows <= 1) {
          Database::insertNewPost($post, $parsedPost, $user, $dbUser);
        }
        else {
          $GLOBALS['log'] .= "Somehow the user \"".$user[0]->user_id."\" exists multiple times?!";
        }
      }
      /* else check if ther username exists in the DB as an alias and retry
         the osu!api call using the alias */
      else {
        $dbUserAlias = "SELECT id, name, alias
                   FROM ppvr.players
                   WHERE alias='".$parsedPost["player"]."';";
        $resultAlias = $GLOBALS['conn']->query($dbUserAlias);

        if ($resultAlias->num_rows == 1) {
          $resultUserAlias = $result->fetch_assoc();
          $apiUserAlias = file_get_contents (
                            "https://osu.ppy.sh/api/get_user?k=".API_KEY.
                            "&u=".$resultUserAlias["alias"]."&type=string"
                          );
          $userAlias = json_decode($apiUserAlias);
          if ($userAlias != null)
          {
            Database::insertNewPost($post, $parsedPost, $userAlias, $result);
          }
        }
        else {
          #postToDiscord($post, 2, $parsedPost);
        }
      }
    }

    private function checkPlayerDB($user_id) {
      $db = Database::getConnection();
      $dbUser = "SELECT id, name, alias
                 FROM ppvr.players
                 WHERE id='".$user_id."';";

      return $db->query($dbUser);
    }

    private function insertNewPlayer($user) {
      $db = Database::getConnection();
      $newPlayer =
      "INSERT INTO ppvr.players (id, name, alias, pp)
       VALUES ('".(int)$user[0]->user_id."', '".$user[0]->username."', NULL, 0);";

      if ($db->query($newPlayer) === TRUE) {
        $GLOBALS['log'] .= "New player \"".$user[0]->username."\" added successfully\n";
      } else {
        $GLOBALS['log'] .= "Error: " . $newPlayer . "\n" . $db->error."\n";
      }
    }

    public function insertNewPost($post, $parsedPost, $user, $result) {
      $db = Database::getConnection();
      $resultUser = $result->fetch_assoc();

      //update username in case of namechange, save old username in alias
      if ($resultUser["name"] != $user[0]->username) {
        $updatePlayer = "UPDATE ppvr.players
                         SET name='".$user[0]->username."', alias='".$resultUser["name"]."'
                         WHERE  id=".(int)$user[0]->user_id.";";
        if ($db->query($updatePlayer) === TRUE) {
         $GLOBALS['log'] .= "Player \"".$user[0]->username."\" updated successfully! Old name:".
         $resultUser["name"]."\n";
        }
        else {
         $GLOBALS['log'] .= "Error: " . $updatePlayer . "\n" . $db->error;
        }
      }

      //insert Post in DB
      $newPost =
      "INSERT INTO ppvr.posts (id, player_id, map_artist, map_title, map_diff, author,
                         score, ups, downs, created_utc)
       VALUES ('".$post->id."', '"
                 .$user[0]->user_id."', '"
                 .htmlspecialchars_decode($db->real_escape_string($parsedPost["artist"]))."', '"
                 .htmlspecialchars_decode($db->real_escape_string($parsedPost["title"]))."', '"
                 .htmlspecialchars_decode($db->real_escape_string($parsedPost["diff"]))."', '"
                 .$post->author."', '"
                 .$post->score."', '"
                 .$post->ups."', '"
                 .$post->downs."', "
                 .$post->created_utc.");";

      if ($db->query($newPost) === TRUE) {
        $GLOBALS['log'] .= "Post added successfully\n";
        #postToDiscord($post, 0, $parsedPost);
      }
      else {
        $GLOBALS['log'] .= "Error: " . $newPost . "\n" . $db->error."\n";
      }

      //add player's php
      $updatePP = "UPDATE ppvr.players
                   SET pp = pp + ".$post->score."
                   WHERE  id=".(int)$user[0]->user_id.";";
      if ($db->query($updatePP) === TRUE) {
       $GLOBALS['log'] .= "added ".$post->score."pp to player \"".$user[0]->username."\" successfully\n";
      }
      else {
       $GLOBALS['log'] .= "Error: " . $updatePP . "\n" . $db->error;
      }
    }
}

?>
