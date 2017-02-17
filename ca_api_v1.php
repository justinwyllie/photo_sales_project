<?php

session_start();
//see http://coreymaynard.com/blog/creating-a-restful-api-with-php/ if you want to do this properly

$API = new ClientAreaAPI($_REQUEST['request'] );



class ClientAreaAPI
{


    public function __construct($request)
    {
    
         //absolute path on your system to the client_area_files directory
        $this->clientAreaDirectory = "/var/www/vhosts/mms-oxford.com/jwp_client_area_files";

        $this->accountsPath = $this->clientAreaDirectory . DIRECTORY_SEPARATOR . "client_area_accounts.xml";
        $this->imageProvider = "/client_area_image_provider.php";
       
        $this->args = explode('/', rtrim($request, '/'));
        $this->endpoint = array_shift($this->args);       //TODO handle e.g. basket/1
        if (sizeof($this->args) >= 1) {
            $this->param = array_shift($this->args);
        }
        
        $this->setLangStrings();
        $this->setAccounts();
      
        
        $this->verb = strtolower($_SERVER["REQUEST_METHOD"]);
        $this->action = $this->verb . ucfirst($this->endpoint);
        
        //actions which don't require a session. TODO better
        if (($this->action == "getLangStrings") || ($this->action == "postLogin") ) {
            $content = call_user_func_array(array($this, $this->action), array());
            $this->outputJson($content);
        }
        
        if (!empty($_SESSION["user"])) {
            $this->user = $_SESSION["user"];
            $content = call_user_func_array(array($this, $this->action), array());
            $this->outputJson($content);
        } else {
            $obj = new stdClass();
            $obj->status = "error";
            $obj->message = $this->lang('loginError');
            $this->outputJson($obj);                        
        }
    
    }
    
    
    //APP methods
    
    public function getSessionStatus()
    {
        //if we got this far...
        $obj = new stdClass();
        $obj->status = "success";
        $obj->message = "";
        $obj->appData = $_SESSION['options'];
        $this->outputJson($obj);
    }
    
    public function getModeChoice()
    {
        $obj = new stdClass();
        $obj->status = "success";
        $obj->message = "";
        $this->outputJson($obj);

    }
    
    
    /**
     * called at the start of the order process
     * send the current state of the Basket and The Order to the site owner *before the payment transaction
     * we do this in case there is an error e.g the SESSION is lost - and we no longer have the Basket and Order when the PayPal notify is handled
     */
    public function postOrderStep1() {
        $mode = $_POST['mode'];
        $gateway = $_POST['gateway'];
        $date =  date('m-d-Y-H-i-s');
        $orderRef =  $_SESSION["user"] . '_' .  $date;
        $data = $this->packageOrder($mode, $gateway);
        $data = "ORDER REF: $orderRef \n\n" . $data;
        $this->mailAdmin($data, "Provisional Order on Website.");
        $obj = new stdClass();
        $obj->status = "success";
        $obj->message = "";
        $obj->orderRef = $orderRef;
        $this->outputJson($obj);  
    }
    
    public function getPrintThumbs()
    {
        $obj = new stdClass();
        
        $thumbsDir = $this->clientAreaDirectory . DIRECTORY_SEPARATOR . $_SESSION['user'] .
            DIRECTORY_SEPARATOR . 'prints' . DIRECTORY_SEPARATOR . "thumbs";
            
        $mainDir = $this->clientAreaDirectory . DIRECTORY_SEPARATOR . $_SESSION['user'] .
            DIRECTORY_SEPARATOR . 'prints' . DIRECTORY_SEPARATOR . "main";     
            
            
        $files = scandir($thumbsDir);

        $thumbs=array();
        foreach ($files as $file)
        {
        
            $fileObj = new stdClass();
            if ( ($file !== ".")  && ($file !== "..") && (is_file($thumbsDir . DIRECTORY_SEPARATOR . $file)) )
            {
                $fileObj->file = $file;
                //TODO - in a loop? or at least we should cache the results
                $mainFile = $mainDir . DIRECTORY_SEPARATOR . $file;
                if (file_exists($mainFile)) { 
                    $imageDimensionsOfMainPic = $this->getImageDimensions($mainFile);
                    if (empty($imageDimensionsOfMainPic)) 
                    {
                        continue;    
                    }
                    else
                    {
                        $fileObj->mainWidth =  $imageDimensionsOfMainPic["width"];
                        $fileObj->mainHheight =  $imageDimensionsOfMainPic["height"]; 
                    }
                    $imageDimensions = $this->getImageDimensions($thumbsDir . DIRECTORY_SEPARATOR . $file);
                    if (empty($imageDimensionsOfMainPic)) 
                    {
                        continue;    
                    }
                    else
                    {
                        $fileObj->width =  $imageDimensions["width"];
                        $fileObj->height =  $imageDimensions["height"];    
                    }
                    $fileObj->path =  $this->imageProvider . '?mode=prints&size=thumbs&file=' . $file;   //TODO put imageProvider onto some init and then mode and size can be set f/e
                    $thumbs[] = $fileObj;
                }
                else
                {
                    continue;
                }
            }
        }
        $this->outputJson($thumbs);
    }
    
    
    public function postLogin()
    {       
        $obj = new stdClass();
                                                             
        $user = $_POST['name'];
        $password = $_POST['password'];
        $restoredProofs = $_POST['restoredProofs'];                  
        $restoredProofsPagesVisited = $_POST['restoredProofsPagesVisited'];
        $restoredPrints = $_POST['restoredPrints'];                  
        $restoredPrintsPagesVisited = $_POST['restoredPrintsPagesVisited'];

        if (!empty($this->accounts[$user]) && !empty($password) && ($this->accounts[$user]["password"] === $password)) {
            session_unset();
            
            $this->setOptions();
            $this->setUserOptions($user);
            

            $_SESSION["user"] = $user;
            $_SESSION["proofsPagesVisited"] = array();
            $_SESSION["proofsChosen"] = array();
            $_SESSION["printsPagesVisited"] = array();
            $_SESSION["printsChosen"] = array();
            $_SESSION["basket"] = array();
            $_SESSION["order"] = array();
            $_SESSION["options"] = $this->options;

            //If the user is logging in try to restore the proofs chosen based on what was stored in html data if it is available
            if (!empty($restoredProofs)) {
                $restoredProofsArray = json_decode($restoredProofs);
                if (is_array($restoredProofsArray)) {
                    $_SESSION["proofsChosen"] = $restoredProofsArray;
                }
            }
                
            //If the user is logging in try to restore the proof pages visited chosen based on what was stored in html data if it is available    
            if (!empty($restoredProofsPagesVisited)) {
                $restoredProofsPagesVisitedArray = json_decode($restoredProofsPagesVisited);
                if (is_array($restoredProofsPagesVisitedArray)) {

                    $_SESSION["proofsPagesVisited"] = $restoredProofsPagesVisitedArray;
                }
            }
            
            //If the user is logging in try to restore the prints chosen based on what was stored in html data if it is available
            if (!empty($restoredPrints)) {
                $restoredPrintsArray = json_decode($restoredPrints);
                if (is_array($restoredPrintsArray)) {
                    $_SESSION["basket"] = $restoredPrintsArray;
                }
            }
            
            //If the user is logging in try to restore the prints pages visited chosen based on what was stored in html data if it is available  
            if (!empty($restoredPrintsPagesVisited)) {
                $restoredPrintsPagesVisitedArray = json_decode($restoredPrintsPagesVisited);
                if (is_array($restoredPrintsPagesVisitedArray)) {

                    $_SESSION["printsPagesVisited"] = $restoredPrintsPagesVisitedArray;
                }
            }
                                     
            $obj->status = "success";
            $obj->message = "";
            $obj->appData = $this->options;
        } else {
            $obj->status = "error";
            $obj->message = $this->lang('loginError');
        }
        
        $this->outputJson($obj);

    }
    
    
    
    //MODEL METHODS
    
   
    public function getPricing() 
    {                                          
        $pricingModel = simplexml_load_file($this->clientAreaDirectory . DIRECTORY_SEPARATOR . "client_area_pricing.xml" );
        //$obj = new stdClass();
        // $obj->pricingModel = $pricingModel;
        if ($pricingModel === false) 
        {
          //TODO implement this
          //$this->destroySession();
          //$this->terminateScript("Missing or broken user options file for " . $user);
        }   
         
        return $pricingModel;
    }
    
    public function getLangStrings() 
    {                                          
        return  $this->langStrings();
    }
    
    public function postOrder()
    {
        $order = file_get_contents('php://input');
        $order = json_decode($order);
        $_SESSION["order"] = $order;
        $order->id = 1; 
        return $order;    
    }
    
    public function putOrder()
    {    
        $order = file_get_contents('php://input');
        $order = json_decode($order);
        $_SESSION["order"] = $order;
        return $order;    
    }
  
  
    //COLLECTION METHODS
    public function getBasket() 
    {
        $result = new stdClass();
        $basket = $_SESSION["basket"];
        $deIndexedBasket = array();
        foreach ($basket as $id => $order) {
            $deIndexedBasket[] = $order;
        }
        return $deIndexedBasket;
    }
    
    public function postBasket()
    {
        $newOrderLine = file_get_contents('php://input');
        $newId = $this->generateUniqueOrderId();
        $order = json_decode($newOrderLine);
        $order->id = $newId;
        $_SESSION["basket"][$newId] = $order;
        
        return $order;

    }
    
    public function putBasket()
    {
        $updatedOrderLine = file_get_contents('php://input');
        $orderId = $this->param;
        $order = json_decode($updatedOrderLine);
        $_SESSION["basket"][$orderId] = $order;
        return $order;

    }
    
    public function deleteBasket()
    {
        $orderId = $this->param;
        $basket = $_SESSION["basket"];
        unset($basket[$orderId]);
        $_SESSION["basket"] = $basket;
        return  ""; 
    
    }
    

  
    //HELPERS
    /**
     *   TODO - text in lang file
     *   make this look nicer
     *
     */
    private function packageOrder($mode, $gateway) {
    
    
        $package = "Hi\n\n";    
    
        if ($mode == 'online_payment') {
            $package.= "This is NOT the actual order. It indicates the user MAY be attempting a purchase. Wait for the 'Order Confirmed' email and/or check your account ($gateway) to check that you have received the payment";
        }   else {
            $package.= 'An order has been placed on the web site. You need to take payment manually.';    
        }

        if (isset($_SESSION["basket"])) {
            $package = "\n\n" . $package . "\n\nBasket:\n\n";
            $basket = $_SESSION["basket"];
            foreach ($basket as $orderLine) {
                $package = $package . json_encode($orderLine) . "\n\n";
            }
        } 
        
        $package.= "\n\n";
        
        if (!empty($_SESSION["order"] )) {
            $package = $package .  "Order Info:\n\n";
            $package = $package . json_encode($_SESSION["order"]);
        } 
        
        $package = $package . "\n\n" . "Remember to check the pricing is correct!" . "\n\n" . "The Web Team";
        return $package;   
    }
    
    private function mailAdmin($body, $subject = 'Message from POS') {
        if (!isset($_SESSION['options'])) {
            //TODO - we've been called before the SESSION options has been set in postLogin - we have to manually open the options file to get the admin email
        } else {
            $adminEmail = $_SESSION['options']['adminEmail'];
            mail($adminEmail, $subject, $body);
        }
        
        
    }
    
    
    private function generateUniqueOrderId()
    {
        $id = 0;
        $basket = $_SESSION["basket"];
        foreach ($basket as $order) {
            if ($order->id > $id) {
                $id = $order->id;
            }
        }
        
        return $id + 1;
 
    } 
    
    private function setLangStrings()
    {
        $this->langStrings = $this->langStrings();
    }

    
    private function setAccounts()
    {
        $client_area_accounts = simplexml_load_file($this->accountsPath);

        if ($client_area_accounts === false) {
            $this->outputJson500("Error in accounts file or file does not exist");
        }

        $accounts = array();
        foreach($client_area_accounts->account as $account) {
            $username = $account["username"] . "";
            $accounts[$username]["password"] = $account->password . "";
            $accounts[$username]["human_name"] = $account->human_name . "";

        }

        $this->accounts = $accounts;
    }
    
    private function setUserOptions($user)
    {
        $userOptions = simplexml_load_file($this->clientAreaDirectory . DIRECTORY_SEPARATOR . $user .
            DIRECTORY_SEPARATOR . "options.xml" );

        if ($userOptions === false) {
             $this->outputJson500("Missing or broken user options file for " . $user);
        } 
        
        //TODO test this
        if (!isset($this->options)) {
            $this->outputJson500("Incorrect call to setUserOptions before call to setOptions " . $user);
        }

        $this->options["proofs_on"] = $userOptions->proofsOn == 'true' ? true: false;
        $this->options["prints_on"] = $userOptions->printsOn == 'true' ? true: false;
        
        $this->options["client_address"] = json_encode($userOptions->clientAddress);
        $this->options["eventName"]   = $userOptions->eventName . "";


        //potentially overide global options
        if (!empty($userOptions->thumbsPerPage)) {
           $this->options["thumbsPerPage"]   = (int) $userOptions->thumbsPerPage;  
        }

        if (!empty($userOptions->enablePaypal)) {
            $this->options["enablePaypal"]   = $userOptions->enablePaypal == 'true' ? true: false; 
        }
            
        if (!empty($userOptions->proofsShowLabels)) {
            $this->options["proofsShowLabels"]   = $userOptions->proofsShowLabels == 'true' ? true: false;   
        }
           
        if (!empty($userOptions->showNannyingMessageAboutMoreThanOnePage)) {
            $this->options["showNannyingMessageAboutMoreThanOnePage"]   = $userOptions->showNannyingMessageAboutMoreThanOnePage == 'true' ? true: false;
        }

        if (!empty($userOptions->deliveryChargesEnabled)) {
            $this->options["deliveryChargesEnabled"]   = $userOptions->deliveryChargesEnabled == 'true' ? true: false;
        }
      
        if (!empty($userOptions->proofsModeMessage)) {
            $this->options["proofsModeMessage"]   = $userOptions->proofsModeMessage . "";
        }
       
        if (!empty($userOptions->printsModeMessagePayPalEnabled)) {
            $this->options["printsModeMessagePayPalEnabled"]   = $userOptions->printsModeMessagePayPalEnabled . "";
        }
        
        if (!empty($userOptions->printsModeMessagePayPalNotEnabled)) {
            $this->options["printsModeMessagePayPalNotEnabled"]   = $userOptions->printsModeMessagePayPalNotEnabled . "";
        }
            
        $this->options["human_name"] =  $this->accounts[$user]["human_name"]   ;
        $this->options["username"] =  $user;
    }
    
    private function setOptions()
    {                                                                                                                                                    
        $client_area_options = simplexml_load_file($this->clientAreaDirectory . DIRECTORY_SEPARATOR . 'client_area_options.xml');

        if ($client_area_options === false) {
             //TODO make sure all requests from the f/e have an error handler which goes
             // to an error page which has a link to login again and also in  outputJson500 notify site owner if we have an email 
            $this->outputJson500("Error in options file or file does not exist");  
        }

        $userOptions = $client_area_options->options->userOverrideable;

        $options = array();
        $options["thumbsPerPage"] = (int) $userOptions->thumbsPerPage;
        $options["proofsShowLabels"] = $userOptions->proofsShowLabels == 'true' ? true: false;
        $options["showNannyingMessageAboutMoreThanOnePage"] =  $userOptions->showNannyingMessageAboutMoreThanOnePage == 'true' ? true: false;
        $options["enableOnlinePayments"] = $userOptions->enableOnlinePayments == 'true' ? true: false;
        $options["paymentGateway"] = false;
          foreach ($client_area_options->options->system->paymentGateway as $gateway) {
            if (gateway == 'true') {
                $options["paymentGateway"] = $gateway->name;
                break;    
            }
        }
       
        
        $options["deliveryChargesEnabled"] = $userOptions->deliveryChargesEnabled == 'true' ? true: false;
        $options["proofsModeMessage"] = $userOptions->proofsModeMessage . "";
        $options["printsModeMessage"] = $userOptions->printsModeMessage . "";
        $options["paypalAccountEmail"]  =  $client_area_options->options->system->paypalAccountEmail . "";
        $options["paypalSandboxAccountEmail"]  =  $client_area_options->options->system->paypalSandboxAccountEmail . "";
        $options["paypalIPNHandler"]  =  $client_area_options->options->system->paypalIPNHandler . "";
        $options["paypalIPNSSL"] = $userOptions->paypalIPNSSL == 'true' ? true: false;
        $options["adminEmail"]  =  $client_area_options->options->system->adminEmail . "";
        $options["mode"]  =  $client_area_options->options->system->mode . "";
        $options["domain"]  =  $client_area_options->options->system->domain . "";
        $options["paypalPaymentDescription"]  =  $client_area_options->options->system->paypalPaymentDescription . "";
        $options["sessId"]  =  session_id();

        $this->options = $options;
    }

                          
    private function destroySession()
    {
       session_unset();
       session_destroy();
    }
    
        
    private function langStrings()
    {
        $strings = simplexml_load_file($this->clientAreaDirectory . DIRECTORY_SEPARATOR . "client_area_lang.xml" );
        if ($strings === false) 
        {
          //TODO implement this
          //$this->destroySession();
          //$this->terminateScript("Missing or broken user options file for " . $user);
        } 
        $langStrings = array();
        foreach ($strings->field as $field) {
            $fieldName = $field["name"] . "";
            $fieldValue = trim($field . "");
            $langStrings[$fieldName] = $fieldValue;
        }
        return  $langStrings;
    
    }
    
    private function lang($field)
    {
        if (isset($this->langStrings[$field])) {
            return $this->langStrings[$field];
        } else {
            return "";
        }
    }
    
    private function getImageDimensions($file)
    {
        $dimensions = getimagesize($file);

        $result = array();
        if ($dimensions !== false) {
            $result["width"] = $dimensions[0];
            $result["height"] = $dimensions[1];
        }

        return $result;
     }
    
    private function outputJson($output) {
        header("Content-type: application/json");
        echo json_encode($output);
        exit();
    }
    
    private function outputJson500($errMessage) {
        //TODO email $errMessage to admin
        header("Content-type: application/json");
        header("HTTP/1.1 500 Internal Server Error");
        exit();
    }
  
  
 
}

?>