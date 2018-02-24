<?php

function logg($log_msg) {
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
$lookupTimeout = 60;
$timeout = 10;
$opts = array(
  'http'=>array(
    'timeout' => $timeout,
  )
);
$context = stream_context_create($opts);
$file = file_get_contents('http://www.example.com/', false, $context);
                        
$domains = [
  "http://195.181.242.206:9998/api?",
  "http://79.137.38.49:9998/api?",
];

$index = rand(0, count($domains) - 1);
//for testing
$index = 1;

$start_time = time();    
while (true) {
  if ((time() - $start_time) > $lookupTimeout) {
    logg("Could not find working server");
  }
  $url = $domains[$index] . ($_SERVER['QUERY_STRING']);
  $page = file_get_contents($url,false,$context);
  if($page === false){
    logg($domains[$index] . " did no respond within " . $timeout . " secs");
    $index++;
    $index = $index % count($domains);
    continue;
  }else{
    logg($domains[$index] . " succesfully responded: " . $page);
    header("HTTP/1.1 200 OK");
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');  
    header("Access-Control-Allow-Methods: PUT, PATCH, GET, POST, DELETE, OPTIONS"); 
    header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");
    echo $page;  
    break;    
  }
}
