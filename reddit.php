<?php
  class Reddit {

    public function new() {
      $content=file_get_contents("https://ga.reddit.com/r/osugame/new.json?limit=100");
      $posts = json_decode($content);

      //go through all new posts and check unprocessed
      $db = Database::getConnection();
      for ($i = 0; $i < $posts->data->dist; ++$i) {
        $post = $posts->data->children[$i]->data;
        $existingPost = "SELECT id
                         FROM ppvr.posts
                         WHERE id='".$post->id."';";
        $result = $db->query($existingPost);

        if ($result->num_rows == 0) {
          //only check posts that are at least 24h old
          $age = time() - $post->created_utc;
          echo($age."\n");
          if ($age >= 10*60*60 && $age <= 28*60*60) {
            $GLOBALS['log'] .= "---------- parsing post ".$i." ----------\n";
            echo("bin hier \n");
            Reddit::parsePost($post, true);
          }
          else if ($age <= 24*60*60) {
            //parsePost($posts->data->children[$i], false);
          }
          //else $GLOBALS['log'] .= "-".$i.": ".$posts->data->children[$i]->data->url."\n"; //Debug
        }
      }
    }

    public function archive() {

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
        /*$tok = strtok($postTitle, "|");

        while (substr_count($tok, "osu!") > 1) {
          $tok = strtok("|");
        }
        $parsedPost["player"] = $tok;
        $tok = strtok("-");
        $parsedPost["artist"] = $tok;
        $tok = strtok("[");
        $parsedPost["title"] = $tok;
        $tok = strtok("]");
        $parsedPost["diff"] = $tok;*/
        $parseError = false;
        $matches = array();
        //Title
        $match = preg_match("/(.+) [\|丨].+-.+\[.+\]/", $postTitle, $matches);
        echo("Title: ".count($matches)."\n");
        var_dump($matches);
        if ($match != FALSE && count($matches) == 2) {
          $parsedPost["player"] = $matches[1];
        }
        else {
          $parseError = true;
        }

        //map Data
        $tmpMap = "";
        $match = preg_match("/.+[\|丨] (.+-.+\[.+\])/", $postTitle, $matches);
        echo("mapData: ".count($matches)."\n");
        echo("mapDataError: ".$match."\n");
        var_dump($matches);
        if ($match != FALSE && count($matches) == 2) {
          $tmpMap = $matches[1];
          echo("tmpMap: ".$tmpMap."\n");
        }
        else {
          $parseError = true;
        }

        //split map Data
        $match = preg_match("/(.+) - (.+?) \[(.+)\]/", $tmpMap, $matches);
        echo("mapDataSplit: ".count($matches)."\n");
        var_dump($matches);
        if ($match != FALSE && count($matches) == 4) {
          $parsedPost["artist"] = $matches[1];
          $parsedPost["title"] = $matches[2];
          $parsedPost["diff"] = $matches[3];
        }
        else {
          $parseError = true;
        }



        if ($final && $parseError == false) {
          /* if this gets triggered, there is probably some additional info next to
             the player, like "[osu!catch]" that wasn't recognized by the first
             check a few lines above */
          if (substr_count($parsedPost["player"], " ") > 1) {
            $GLOBALS['log'] .= "fehler 1\n";
            #postToDiscord($post, 1, $parsedPost);
          }
          else {
            //check some additional stuff before marking as final
            $GLOBALS['log'] .= "Original: ".$post->title."\n"; //Debug
            $GLOBALS['log'] .= "Parsed: ".$parsedPost["player"]." | ".
                            $parsedPost["artist"]. " - ".
                            $parsedPost["title"]."\n";
            Database::preparePost($post, $parsedPost);
          }
        }
        else if ($parseError == false){
          //Insert into DB without checking anything
        }
        else {

        }
      }
    }
  }
?>
