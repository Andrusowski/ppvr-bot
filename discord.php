<?php

class Discord {

    /*
    Reasons:
    0: no issue
    1: couldn't parse title
    2: player not found
    */
    public function post($post, $reason, $parsedPost) {
        $datatmp = [
            //"content" => "Hello, world!",
            "embeds" => [
                [
                    "title" => $post->title,
                    "description" => null,
                    "url" => "https://reddit.com".$post->permalink,
                    "color" => 0xFFFFFF,
                    "timestamp" => (new DateTime())->format("c"),
                    "author" => [
                        "name" => null,
                        //"url" => "https://example.com",
                        //"icon_url" => $image
                    ],
                    "thumbnail" => [
                        "url" => null
                    ]
                ]
            ]
        ];

        $webhook = "https://discordapp.com/api/webhooks/";
        if ($reason == 0) {
            $webhook .=  DISCORD_WEBHOOK_OK;
            $datatmp["embeds"][0]["description"] = "final score: "
            .$post->score." ("
            .$post->ups." ⇧/"
            .$post->upvote_ratio." ⇩)";
            $datatmp["embeds"][0]["author"]["name"] = "New post added:";
        }
        else {
            $webhook .=  DISCORD_WEBHOOK_ERROR;
            $datatmp["embeds"][0]["description"] = "##### Parsed info: \n".
            "player : ".$parsedPost["player"]."\n".
            "artist : ".$parsedPost["artist"]."\n".
            "title  : ".$parsedPost["title"]."\n".
            "diff   : ".$parsedPost["diff"]."\n".
            "score  : ".$post->score."\n".
            "ups    : ".$post->ups."\n".
            "upvote_ratio  : ".$post->upvote_ratio."\n".
            "created: ".$post->created_utc."\n".
            "author : ".$post->author."\n";

            if ($reason == 1) {
                $datatmp["embeds"][0]["author"]["name"] = "Couldn't parse post:";
            }
            else if ($reason == 2) {
                $datatmp["embeds"][0]["author"]["name"] = "Couldn't find player:";
            }
        }

        //add thumbnail if posts links to a picture
        $url = $post->url;
        if (strpos($url, "osu.ppy.sh/ss/") !== false || strpos($url, ".jpg") !== false || strpos($url, ".png") !== false) {
            $datatmp["embeds"][0]["thumbnail"]["url"] = $url;
        }

        //encode array and post
        $data = json_encode($datatmp);
        $ch = curl_init($webhook);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "Content-Length: " . strlen($data)
        ]);
        return curl_exec($ch);
    }
}

?>
