<?php

function logg($log_msg) {
  date_default_timezone_set("UTC");
  $folder = false;
  if($folder){
    $log_folder = "log";
    if (!file_exists($log_folder)) {
        // create directory/folder uploads.
        mkdir($log_folder, 0777, true);
    }
    $log_file_data = $log_folder.'/log_' . date('Y-m-d') . '.log';    
  }else{
    $log_file_data = 'log_' . date('Y-m-d') . '.log';        
  }
  $datetime = date("Y/m/d H:i:s") . " ";
  file_put_contents($log_file_data, $datetime . $log_msg . "\n", FILE_APPEND);
}
$lookupTimeout = 40;
$timeout = 20;
$opts = array(
  'http'=>array(
    'timeout' => $timeout,
  )
);
$context = stream_context_create($opts);
$file = file_get_contents('http://www.example.com/', false, $context);
                        
$domains = [
  "http://195.181.242.206:9998/api?", /* anoxy */
  //"http://79.137.38.49:9998/api?", /* giorgosk */
  "http://104.251.218.154:9998/api" /* anoxy */
];

//get random server
$index = rand(0, count($domains) - 1);

//for testing or try to avoid 1st server
$index = 0;

$start_time = time();    
while (true) {
  if ((time() - $start_time) > $lookupTimeout) {
    logg("Could not find working server");
  }
  $url = $domains[$index] . ($_SERVER['QUERY_STRING']);
  $page = file_get_contents($url,false,$context);
  if($page === false){
    logg($domains[$index] . " no response within " . $timeout . " secs");
  }else if (strpos($page, 'api_status: \"error\"') !== false) {
    logg($domains[$index] . " unsuccessful response: " . $page);
  }else{
    logg($domains[$index] . " succesful response: " . $page);
    header("HTTP/1.1 200 OK");
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');  
    header("Access-Control-Allow-Methods: PUT, PATCH, GET, POST, DELETE, OPTIONS"); 
    header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");
    echo $page;  
    break;    
  }
  $index++;
  $index = $index % count($domains);
}
