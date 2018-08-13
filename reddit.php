<?php
  class Reddit {

    public function new() {
      $content=file_get_contents("https://ga.reddit.com/r/osugame/new.json?limit=100");
      $posts = json_decode($content);

      //go through all new posts and parse which are not in Database
      $db = Database::getConnection();
      for ($i = 0; $i < $posts->data->dist; ++$i) {
        $post = $posts->data->children[$i]->data;
        $existingPost = "SELECT id
                         FROM ppvr.posts
                         WHERE id='".$post->id."' AND final=1;";
        $result = $db->query($existingPost);

        if ($result->num_rows == 0) {
          //only check posts that are at least 24h old
          $age = time() - $post->created_utc;
          if ($age >= 24*60*60 /*&& $age <= 28*60*60*/) {
            $GLOBALS['log'] .= "---------- parsing final post ".$i." ----------\n";
            Reddit::parsePost($post, 1);
          }
          else if ($age <= 24*60*60) {
            $GLOBALS['log'] .= "---------- parsing new post ".$i." ----------\n";
            Reddit::parsePost($post, 0);
          }
        }
      }
    }

    public function archive() {
      $content=file_get_contents("https://ga.reddit.com/r/osugame/new.json?limit=100");
      $posts = json_decode($content);

      //go through all new posts and check unprocessed
      $db = Database::getConnection();
      for ($i = 0; $i < $posts->data->dist; ++$i) {
        $post = $posts->data->children[$i]->data;
        $existingPost = "SELECT f.id, t.id
                         FROM ppvr.posts f, ppvr.posts_tmp t
                         WHERE f.id='".$post->id."' OR t.id='".$post->id."';";
        $result = $db->query($existingPost);

        if ($result->num_rows == 0) {
          //only check posts that are at least 24h old
          $age = time() - $post->created_utc;
          $final = 0;
          if ($age >= 24*60*60 /*&& $age <= 28*60*60*/) {
            $GLOBALS['log'] .= "---------- parsing post ".$i." ----------\n";
            $final = 1;
            Reddit::parsePost($post, $final);
          }
          else if ($age <= 24*60*60) {
            Reddit::parsePost($post, $final);
          }
        }
      }
    }

    private function parsePost($post, $final) {
      /* check for characteristic characters from the already established format
         Player Name | Song Artist - Song Title [Diff Name] +Mods */
      $postTitle = $post->title;
      if (strpos($postTitle, "|") &&
          strpos($postTitle, "-") &&
          strpos($postTitle, "["))
      {
        //parse relevant information from post title
        $parsedPost = array(
          "player" => "**error**",
          "artist" => "**error**",
          "title" => "**error**",
          "diff" => "**error**",
        );

        $parseError = false;
        $matches = array();
        //Title
        $match = preg_match("/(.+) *[\|丨].+-.+\[.+\]/", $postTitle, $matches);
        if ($match != FALSE && count($matches) == 2) {
          $parsedPost["player"] = $matches[1];
        }
        else {
          $parseError = true;
        }

        //map Data
        $tmpMap = "";
        $match = preg_match("/.+[\|丨] *(.+-.+\[.+\])/", $postTitle, $matches);
        if ($match != FALSE && count($matches) == 2) {
          $tmpMap = $matches[1];
        }
        else {
          $parseError = true;
        }

        //split map Data
        $match = preg_match("/(.+) - (.+?) \[(.+)\]/", $tmpMap, $matches);
        if ($match != FALSE && count($matches) == 4) {
          $parsedPost["artist"] = $matches[1];
          $parsedPost["title"] = $matches[2];
          $parsedPost["diff"] = $matches[3];
        }
        else {
          $parseError = true;
        }



        if ($parseError == false) {
          $GLOBALS['log'] .= "Original: ".$post->title."\n"; //Debug
          //check some additional stuff before marking as final
          $GLOBALS['log'] .= "Parsed: ".$parsedPost["player"]." | ".
                          $parsedPost["artist"]. " - ".
                          $parsedPost["title"]."\n";
          Database::preparePost($post, $parsedPost, $final);
        }
        else {
          Database::insertNewPostTmp($post, $parsedPost);
        }
      }
    }
  }
?>
