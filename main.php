<?php
#include("db.php");
include("database.php");
include("discord.php");
include("reddit.php");
date_default_timezone_set("UTC");

$logfile = 'logs\log-'.date("m-d-y").'.txt';
$GLOBALS['log'] = file_get_contents($logfile);
$datetime = new DateTime();
$GLOBALS['log'].= "\n**********".$datetime->format('Y-m-d\TH:i:s.u')."**********\n";

//Connect to DB
$GLOBALS['conn'] = Database::getConnection();

//process reddit posts
if ($argc > 1) {
    if ($argv[1] == '--archive') {
        Reddit::archive(0); //start with first post
    }
}
else {
    Reddit::new();
}

file_put_contents($logfile, $GLOBALS['log']);




?>
