<?php
#include("db.php");
include("database.php");
include("discord.php");
include("reddit.php");
date_default_timezone_set("UTC");

$logfile = 'log.txt';
$GLOBALS['log'] = file_get_contents($logfile);
$datetime = new DateTime();
$GLOBALS['log'].= "\n**********".$datetime->format('Y-m-d\TH:i:s.u')."**********\n";

//check internet connection
$response = null;
system("ping -c 1 reddit.com", $response);
if($response == 0)
{
  //Connect to DB
  $GLOBALS['conn'] = Database::getConnection();

  //process reddit posts
  if ($argc > 1) {
    if ($argv[1] == '--archive') {
      Reddit::archive();
    }
  }
  else {
    Reddit::new();
  }
}
else {
  $GLOBALS['log'].= "Kann Keine Verbindung zu Reddit herstellen. Abbrechen.";
}


file_put_contents($logfile, $GLOBALS['log']);




?>
