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


    public function insertNewPostTmp($post, $parsedPost) {
        $db = Database::getConnection();

        $newPost =
        "INSERT INTO ppvr.tmpposts (id, title, author, score)
        VALUES ('".$post->id."', '"
            .htmlspecialchars_decode($db->real_escape_string($post->title))."', '"
            .$post->author."', '"
            .$post->score.");";

            $error ="\nTitle: ".$post->title.
            "\nParsed Data:".
            "\n  -Player: ".$parsedPost["player"].
            "\n  -Artist: ".$parsedPost["artist"].
            "\n  -Title: ".$parsedPost["title"].
            "\n  -Diff: ".$parsedPost["diff"]."\n";
            $GLOBALS['log'] .= $error;

            if ($db->query($newPost) === TRUE) {
                $GLOBALS['log'] .= "Temporary Post added successfully";
                #postToDiscord($post, 0, $parsedPost);
            }
            else {
                $GLOBALS['log'] .= "Error: " . $newPost . "\n" . $db->error."\n";
            }
        }


        public function preparePost($post, $parsedPost, $final) {
            $db = Database::getConnection();

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
                    Database::insertNewPost($post, $parsedPost, $user, $dbUser, $final);
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
                $resultAlias = $db->query($dbUserAlias);

                if ($resultAlias->num_rows == 1) {
                    $resultUserAlias = $resultAlias->fetch_assoc();
                    $apiUserAlias = file_get_contents (
                        "https://osu.ppy.sh/api/get_user?k=".API_KEY.
                        "&u=".$resultUserAlias["name"]."&type=string"
                    );
                    $userAlias = json_decode($apiUserAlias);
                    if ($userAlias != null)
                    {
                        Database::insertNewPost($post, $parsedPost, $userAlias, $resultAlias, $final);
                    }
                }
                else {
                    Database::insertNewPostTmp($post, $parsedPost);
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
            "INSERT INTO ppvr.players (id, name, alias)
            VALUES ('".(int)$user[0]->user_id."', '".$user[0]->username."', NULL);";

            if ($db->query($newPlayer) === TRUE) {
                $GLOBALS['log'] .= "New player \"".$user[0]->username."\" added successfully\n";
            } else {
                $GLOBALS['log'] .= "Error: " . $newPlayer . "\n" . $db->error."\n";
            }
        }


        public function insertNewPost($post, $parsedPost, $user, $result, $final) {
            echo($final."\n");
            $db = Database::getConnection();
            $resultUser = $result->fetch_assoc();

            //update username in case of namechange, save old username in alias
            if ($resultUser["name"] != $user[0]->username
            && $resultUser["name"] != ""
            && $resultUser["name"] != null) {
                $updatePlayer = "UPDATE ppvr.players
                SET name='".$user[0]->username."', alias='".$resultUser["name"]."'
                WHERE  id=".(int)$user[0]->user_id.";";
                if ($db->query($updatePlayer) === TRUE) {
                    $GLOBALS['log'] .= "Player \"".$user[0]->username."\" updated successfully! Old name:".
                    $resultUser["name"]."\n";
                }
                else {
                    echo("Error: " . $updatePlayer . "\n" . $db->error);
                }
            }

            $newPost =
            "INSERT INTO ppvr.posts (id, player_id, map_artist, map_title, map_diff, author,
                score, ups, downs, gilded, created_utc, final)
                VALUES ('".$post->id."', '"
                    .$user[0]->user_id."', '"
                    .htmlspecialchars_decode($db->real_escape_string($parsedPost["artist"]))."', '"
                    .htmlspecialchars_decode($db->real_escape_string($parsedPost["title"]))."', '"
                    .htmlspecialchars_decode($db->real_escape_string($parsedPost["diff"]))."', '"
                    .$post->author."', "
                    .$post->score.", "
                    .round($post->score * $post->upvote_ratio).", "
                    .round($post->score * (1 - $post->upvote_ratio)).", "
                    .$post->gilded.", "
                    .$post->created_utc.", "
                    .$final.");";

                    if ($db->query($newPost) === TRUE) {
                        $GLOBALS['log'] .= "Post added successfully\n";

                        //add update to update-table
                        $update = "INSERT INTO ppvr.updates (id, score)
                        VALUES ('".$post->id."', ".$post->score.");";
                        if ($db->query($update) !== TRUE) {
                            echo("Error: " . $update . "\n" . $db->error."\n");
                        }
                    }
                    else {
                        echo("Error: " . $newPost . "\n" . $db->error."\n");
                    }
                }

                public function updatePost($post, $final) {
                    $db = Database::getConnection();
                    $postUpdate = "UPDATE ppvr.posts
                    SET score=".$post->score.", ups=".round($post->score * $post->upvote_ratio).", downs=".round($post->score * (1 - $post->upvote_ratio)).", gilded=".$post->gilded.", final=".$final."
                    WHERE id='".$post->id."' AND final=0;";
                    if ($db->query($postUpdate) === TRUE) {
                        $GLOBALS['log'] .= "Non-final post updated successfully\n";

                        //add update to update-table
                        $update = "INSERT INTO ppvr.updates (id, score)
                        VALUES ('".$post->id."', ".$post->score.");";
                        if ($db->query($update) !== TRUE) {
                            echo("Error: " . $update . "\n" . $db->error."\n");
                        }
                    }
                    else {
                        $GLOBALS['log'] .= "Error: " . $postUpdate . "\n" . $db->error."\n";
                    }
                }
            }

            ?>
