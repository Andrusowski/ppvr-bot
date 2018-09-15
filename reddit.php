<?php
class Reddit {

    private static $lastParse;

    //consts taken from christopher-dG's osu-bot
    private static $title_ignores = [
        'UNNOTICED',
        'UNNOTICED?',
        'RIPPLE',
        'GATARI',
        'UNSUBMITTED',
        'OFFLINE',
        'RESTRICTED',
        'BANNED',
        'UNRANKED',
        'LOVED',
        'STANDARD',
        'STD',
        'OSU!',
        'O!STD',
        'OSU!STD',
        'OSU!STANDARD',
        'TAIKO',
        'OSU!TAIKO',
        'O!TAIKO',
        'CTB',
        'O!CATCH',
        'OSU!CATCH',
        'CATCH',
        'OSU!CTB',
        'O!CTB',
        'MANIA',
        'O!MANIA',
        'OSU!MANIA',
        'OSU!M',
        'O!M',
    ];

    public function new() {
        //$content=file_get_contents("https://ga.reddit.com/r/osugame/new.json?limit=100");
        $content=file_get_contents('https://ga.reddit.com/r/osugame/search.json?q=flair%3AGameplay&sort=new&restrict_sr=on&t=all');
        $posts = json_decode($content);

        //go through all new posts and parse, if not in the Database
        $db = Database::getConnection();
        for ($i = 0; $i < $posts->data->dist; ++$i) {
            $post = $posts->data->children[$i]->data;
            $existingPost = "SELECT id, final "
            . "FROM ppvr.posts "
            . "WHERE id='".$post->id."' "
            . "UNION "
            . "SELECT id, author "
            . "FROM ppvr.tmpposts "
            . "WHERE id='".$post->id."';";
            $result = $db->query($existingPost);

            $age = time() - $post->created_utc;


            if ($result->num_rows == 0) {
                //determine if post is final (>48h old)
                if ($age >= 48*60*60) {
                    Reddit::parsePost($post, 1, 0);
                }
                else if ($age < 48*60*60) {
                    Reddit::parsePost($post, 0, 0);
                }
            }
            //update non-final post, if it already exists in the database
            else {
                $row = $result->fetch_assoc();
                if ($row['final'] == 0) {
                    if ($age >= 24*60*60) {
                        Database::updatePost($post, 1);
                    }
                    else {
                        Database::updatePost($post, 0);
                    }
                }
            }
        }
    }


    public function archive() {
        //go through all new posts and parse which are not in Database
        $db = Database::getConnection();
        $after = 1426668291;  //time of first scorepost posted
        self::$lastParse = time();

        while ($after < time() - 60*60) { //stop archiving, when posts are younger than an hour
            $content = file_get_contents('https://api.pushshift.io/reddit/submission/search?subreddit=osugame&sort=asc&limit=100&after='.$after);
            $posts = json_decode($content);
            echo date('d/m/Y', $after)."\n";

            for ($i = 0; $i < sizeof($posts->data); ++$i) {
                $post = $posts->data[$i];
                $existingPost = "SELECT id, final "
                . "FROM ppvr.posts "
                . "WHERE id='".$post->id."' "
                . "UNION "
                . "SELECT id, author "
                . "FROM ppvr.tmpposts "
                . "WHERE id='".$post->id."';";
                $result = $db->query($existingPost);

                $age = time() - $post->created_utc;

                if ($result->num_rows == 0) {
                    //determine if post is final (>48h old)
                    if ($age >= 48*60*60) {
                        Reddit::parsePost($post, 1, 1);
                    }
                    else if ($age < 48*60*60) {
                        Reddit::parsePost($post, 0, 1);
                    }
                }

                $after = $post->created_utc;
            }
        }
    }


    private function parsePost($post, $final, $archive) {
        /* check for characteristic characters from the already established format
        Player Name | Song Artist - Song Title [Diff Name] +Mods */
        $postTitle = $post->title;
        if (strpos($postTitle, '|') &&
        strpos($postTitle, '-') &&
        strpos($postTitle, '['))
        {
            //clean up posttitle from various annotations
            $postTitle = preg_replace('/([\[\(]\#.*[\]\)])/U', '', $postTitle);

            foreach(self::$title_ignores as $ignore) {
                $postTitle = preg_replace("/([\[\(]".$ignore."[\]\)])/i", '', $postTitle);
            }

            ////parse relevant information from post title
            $parsedPost = array(
                'player' => '**error**',
                'artist' => '**error**',
                'title' => '**error**',
                'diff' => '**error**',
            );

            $parseError = false;
            $matches = array();

            //Player
            $match = preg_match("/(.+)\s*[\|丨].+-.+?\[.+?\]/", $postTitle, $matches);
            if ($match != FALSE && count($matches) == 2) {
                $parsedPost['player'] = trim($matches[1]);
            }
            else {
                $parseError = true;
            }

            //map Data
            $tmpMap = '';
            $match = preg_match("/.+[\|丨]\s+(.+-.+\[.+\])/", $postTitle, $matches);
            if ($match != FALSE && count($matches) == 2) {
                $tmpMap = $matches[1];
            }
            else {
                $match = preg_match("/.+[\|丨]\s*(.+-.+\[.+\])/", $postTitle, $matches);
                if ($match != FALSE && count($matches) == 2) {
                    $tmpMap = $matches[1];
                }
                else {
                    $parseError = true;
                }
            }

            //split map Data
            $match = preg_match("/(.+)\s-\s(.+?)\s\[(.+?)\]/", $tmpMap, $matches);
            if ($match != FALSE && count($matches) == 4) {
                $parsedPost['artist'] = trim($matches[1]);
                $parsedPost['title'] = trim($matches[2]);
                $parsedPost['diff'] = trim($matches[3]);
            }
            else {
                $parseError = true;
            }


            if ($parseError == false) {
                echo("\nOriginal: ".$post->title."\n"); //Debug
                //check some additional stuff before marking as final
                echo('Parsed: '.$parsedPost['player'].' | '.
                $parsedPost['artist']. ' - '.
                $parsedPost['title']. ' [' .
                $parsedPost['diff']."]\n");

                //take a break to prevent osu!api spam
                while (self::$lastParse == time()) {
                    //wait
                }
                self::$lastParse = time();

                $content = file_get_contents('https://www.reddit.com/r/osugame/comments/'.$post->id.'.json');
                $post = json_decode($content)[0]->data->children[0]->data;

                Database::preparePost($post, $parsedPost, $final);

                return true;
            }
            else {
                Database::insertNewPostTmp($post, $parsedPost);
            }
        }

        return false;
    }
}
?>
