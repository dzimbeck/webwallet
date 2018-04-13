<?php
  session_start();
  include "_variables.php";

  function logg($log_msg) {
    date_default_timezone_set("UTC");
    $datetime = date("Y/m/d H:i:s") . " ";
    file_put_contents("callback.log", $datetime . $log_msg . "\n", FILE_APPEND);
  }

  $redirect = "";
  if(isset($_REQUEST["r"])){
    $redirect = $_REQUEST["r"];
    if($redirect == "success"){
      $messages .= "<div class='alert alert-success'>The transaction was completed succesfully.<br>Your Bay will be sent to your wallet shortly.</div>";      
    }
    elseif($redirect == "error"){
      $messages .= "<div class='alert'>There was an error with the transaction. Please try again.</div>";      
    }
    elseif($redirect == "callback"){
      logg(file_get_contents('php://input'));  
      exit(true);
    }
  }

  $page_title = "Buy BAY with your credit card";
  $meta_title = "Buy BAY with your credit card";
  $meta_description = "Buy BAY with your credit card.";
  $meta_url = "/buy";
  $meta_robots = "noindex";

  $data_wf_page = "";

  //indacoin variables
  $testing = false;
  $debug = false;
  
  //https://indacoin.com/gw/partneradmin
  $partner = 'bitbaywallet';
  $secret='28729E9ae628935';

  $action = "";
  $result = "";
  $error = "";
  $result_decoded = "";
  $options = "";
  $data_print = "";
  $messsages = "";
  
  $currency = "USD";
  $amount = 50;
  $min_amount = 50;
  $max_amount = 500;
  $rate = 0;
  $pay_url = "";
  $txid = "";
  $refresh = false;
  if(isset($_SESSION['txid']) && isset($_REQUEST["action"])){
    $txid = $_SESSION['txid'];
    $refresh = true;
  }
  
  $user_id = $_POST["email"];
  //$wallet = $_POST["wallet"];
  $wallet = $_REQUEST["wallet"];
  
  $bodyclass = "loggedout";
  if(isset($_REQUEST["bc"])){
    $bodyclass = $_REQUEST["bc"];
  }
  
  $args = "?action=check-buy";
  if(isset($_REQUEST["action"])){
    $action = $_REQUEST["action"];
  }
  if(isset($_REQUEST["testing"])){
    $testing = $_REQUEST["testing"];
    $args .= "&testing=".$testing;
  }
  if(isset($_REQUEST["currency"])){
    $currency = $_REQUEST["currency"];
  }
  if(isset($_REQUEST["debug"])){
    $debug = $_REQUEST["debug"];
    $args .= "&debug=".$debug;
  }
  if(isset($_REQUEST["amount"])){
    $amount = intval($_REQUEST["amount"]);
  }
 
  
  function getbody($filename) {
    $file = file_get_contents($filename);

    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML($file);
    libxml_use_internal_errors(false);
    $bodies = $dom->getElementsByTagName('body');
    assert($bodies->length === 1);
    $body = $bodies->item(0);
    for ($i = 0; $i < $body->children->length; $i++) {
        $body->remove($body->children->item($i));
    }
    $stringbody = $dom->saveHTML($body);
    return $stringbody;
  }

  // NotFound, Chargeback, Declined, Cancelled, Failed, Draft, Payed, Verification, FundsSent, Finished
  
  //https://stackoverflow.com/questions/2445276/how-to-post-data-in-php-using-file-get-contents
  //https://stackoverflow.com/questions/5647461/how-do-i-send-a-post-request-with-php
  function postJSON($type){
    global $result, $error, 
      $result_decoded, $options, $currency,
      $data_print, $testing, $wallet,
      $partner, $secret, $user_id;

    $url = [
      "check" => "exgw_getUserlimits",
      "create" => "exgw_createTransaction",
    ];
    $method = 'POST';
    if($testing){
      $target_url = 'https://bitbay.market/indacoin-integration/posttest.php?testing=true';      
    }
    else{
      $target_url = 'https://indacoin.com/api/' . $url[$type];      
    }
      
    //$cur_in = 'usd';
    $cur_in = $currency;
    $cur_out = 'bay';
    $amount_in = $_POST["amount"];

    $nonce = 1000000;  
    $string= $partner ."_". $nonce;
    $sig = base64_encode(hash_hmac('sha256', $string, $secret,true));
     
    $arr = array(
      'user_id' => $user_id != "" ? $user_id : "user@emailprovider.com",
      'cur_in' => $cur_in,
      'cur_out' => $cur_out,
      'target_address' => $wallet,
      'amount_in' => $amount_in
    );
    
    $data = json_encode($arr);
    $data_print = json_encode($arr, JSON_PRETTY_PRINT);      
        
    if($type == "check"){
      $options = array(
        'http' => array(
           'header' => "Content-Type: application/json\r\n"
           ."gw-partner: ".$partner."\r\n",
           'method' => $method,
           'content' => $data
        )
      );
    }
    else{
      $options = array(
        'http' => array(
           'header' => "Content-Type: application/json\r\n"
           ."gw-partner: ".$partner."\r\n"
           ."gw-nonce: ".$nonce."\r\n"
           ."gw-sign: ".$sig."\r\n",
           'method' => $method,
           'content' => $data
        )
      );      
    }
    $context = stream_context_create($options);
    
    //post data with custom error handling
    set_error_handler(
      create_function(
        '$severity, $message, $file, $line',
        'throw new ErrorException($message, $severity, $severity, $file, $line);'
      )
    );
    try {
      $result = file_get_contents($target_url, false, $context);
      $result_decoded = json_decode($result);    
    }
    catch (Exception $e) {
      $error = $e->getMessage();
    }
    restore_error_handler();    
    return $result;
  }//postJSON
  
  function createRequestURL($txid){
    global $partner, $secret;
    $string=$partner."_".$txid;
    $sig = base64_encode(base64_encode(hash_hmac('sha256', $string, $secret,true)));    
    return "https://indacoin.com/gw/payment_form?transaction_id=$txid&partner=$partner&cnfhash=$sig";
  }
  
  if($refresh){
    //we already have txid in session
    $pay_url = createRequestURL($_SESSION['txid']);  

  }else{

    //get price //should also be done in js 
    //https://indacoin.com/api/GetCoinConvertAmount/USD/BAY/100/bitbay/
    //for testing
    $bay = "1124.989898989";
    if(!$testing){
      //https://indacoin.com/api/GetCoinConvertAmount/USD/BAY/50/bitbay/      
      //https://indacoin.com/api/GetCoinConvertAmount/EURO/BAY/50/bitbay/   
      $bay_url = "https://indacoin.com/api/GetCoinConvertAmount/$currency/BAY/$amount/bitbay/";
      $bay = getbody($bay_url); 
      $bay = (float) filter_var( $bay, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION );
      $rate = $bay / $amount;
      $bay = number_format($bay,8);
    }
    //check USER LIMIT
    $max_amount = postJSON("check");
    $max_amount = intval($max_amount);  

    if( $action == "check-buy"){

      if( $max_amount >= $amount && $amount >= $min_amount){
        $txid = postJSON("create");
        $_SESSION['txid'] = $txid;
        if($txid == "too small amount"){
          $messsages .= "Minimum amount to buy is " . $min_amount . "<br/>";
        }
        $pay_url = createRequestURL($txid);
      }   
      else{
        $action = "";
        $messages .= "<div class='alert'>You can buy from " . $min_amount . " " .  $currency . " up to your daily allowance of " . $max_amount . " " . $currency . "</div>";
      }     
    }  
  }//if refresh else

  if (in_array($action,["buy","check","check-buy"])){
    //can only be done before any headers are send
    header("Location: " . $pay_url); 
    exit();
  }

  include "_section-header.php";
?>


<div id="buy" class="main section buy bb-form form-signin">
  <div class="w-container">

<? if ($action == ""): ?>

<div class="w-container">
  <h2 class="align-center heading-purple">Purchase BitBay</h2>
  <p class="align-center">You can buy BAY with your credit card using the form below.</p>
</div> 
<div class="w-row">
<div class="w-col w-col-9 w-col-small-small-stack middle">
  <form action="<?php echo $_SERVER['PHP_SELF'] . $args ?>" method="post">

  <div class="messages"><?php echo $messages ?></div>

  <div class="form-group">
      <label for="email">Email <span class="required_field">*</span></label>
      <input id="email" name="email" type="email" class="form-control" required="required" value="<?php echo $user_id ?>"
          data-error="Valid email is required.">
      <div class="help-block with-errors"></div>
  </div>
  <div class="form-group">
	  <label for="amount">With: <span class="required_field">*</span></label>
    <div class="rightsmall">
      <select name="currency" id="currency">
        <option value="USD" <?php echo ($currency == "USD" ? "selected" : ""); ?>>USD</option>
        <option value="EURO" <?php echo ($currency == "EURO" ? "selected" : ""); ?>>EURO</option>
      </select>
    </div>
    <div class="leftfloat">
      <input type="text" name="amount" id="amount" value="<?php echo $amount ?>" class="leftfloat" data-min-max="50-<?php echo $max_amount ?>" required="required">
      <div class="help-block with-errors"></div>
    </div>
  </div>
  <div class="form-group">    
    <label for="amount">You get:</label>
    <div class="amount likeinput"><span id="totalbay" rate="<?php echo $rate ?>"><?php echo $bay ?></span> <strong>$BAY</strong></div>
	  <div class="help-block with-errors"></div>
	  </div>
  <div class="form-group">    
    <label for="wallet">Send to your BitBay Wallet: <span class="required_field">*</span></label>
    <input type="text" name="wallet" value="<?php echo $wallet ?>" required="required"><br/>
    <div class="help-block with-errors"></div>
  </div>    
  <div class="form-group">      
    NOTE: The actual amount received may be different due to changes in the exchange rate.<br><br>
  </div>
  <div class="form-group center">      
    <input type="submit" class="button" value="Get $BAY">
  </div>
	  <div class="form-group center">      
		  <h6><i>*You will be redirected to our partners website to complete your purchase</i></h6>
  </div>
  </form>
</div>
</div>

<? elseif (in_array($action,["buy","check","check-buy"])): ?>

<iframe src="<?php echo $pay_url ?>" width="100%" height="600" frameborder="0" allowfullscreen></iframe>

<?php if ($debug){ 
  echo "<label for='debug'>debug info</label>";
  echo "<input type='checkbox' id='debug'>";
  echo "<pre>";
  echo "<h2>Variables</h2>"; 
  echo "max_amount: " . $max_amount . "<br/>";   
  echo "amount: " . $amount . "<br/>";   
  echo "min_amount: " . $min_amount . "<br/>";   
  echo "currency: " . $currency . "<br/>";   
  echo "txid: " . $txid . "<br/>";
  echo "url: <a href='$pay_url' target='_blank'>$pay_url</a><br/>";
  echo "<hr/>";

  echo "<h1>LAST Data and options Send</h1>";
  echo "<h2>Data send</h2>";
  print_r($data_print);
  echo "<hr/>";
  echo "<h2>Options send</h2>";
  print_r($options);
  echo "<hr/>";
  echo "<h1>Data and options Received</h1>";
  print_r($result);
  echo "<hr/>";
  print_r($result_decoded);
  echo "<hr/>";
  echo "<h2>Errors</h2>";    
  echo $error;
  echo "<pre>";
} ?>



<? elseif (in_array($action,["error"])): ?>

  <p>something went wrong</p>

<? endif; ?>

  </div>
</div>

<?php
  include "_section-footer.php";
?>
