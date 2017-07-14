<?php

session_start();
include('ca_database.php');

//see http://coreymaynard.com/blog/creating-a-restful-api-with-php/ if you want to do this properly


$API = new ClientAreaAPI($_REQUEST['request'] );



class ClientAreaAPI
{


    public function __construct($request)
    {
    
         //absolute path on your system to the client_area_files directory
        $this->clientAreaDirectory = "/var/www/vhosts/mms-oxford.com/jwp_client_area_files";
        try 
        {
            $this->db = ClientAreaDB::create();
        }
        catch(Exception $e)
        {
            $this->outputJson500($e->getMessage());         
        }

        $this->accountsPath = $this->clientAreaDirectory . DIRECTORY_SEPARATOR . "client_area_accounts.xml";
        $this->pricingPath = $this->clientAreaDirectory . DIRECTORY_SEPARATOR . "client_area_pricing.xml";
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
    
    public function getCreateBasket()
    {
    
        $mode = $this->param;
    
        if (isset($_COOKIE['client_area_tracker'])) {

            $result =  $this->createBasket($_SESSION['user'] . '_' . $_COOKIE['client_area_tracker'], $mode);

            
            if ($result === false) {
                  $this->outputJson500("Error calling createBasket in getCreateBasket");           
            }
            else
            {
                  $obj = new stdClass();
                  $obj->status = "success";
                  $obj->message = "";
                  $this->outputJson($obj);
            }
        }
        else
        {
            $this->outputJson500("Tracking cookie not set in getCreateBasket called with '" . $param . "'");     
        }
         
    
    }
    
    public function postProcessProofs()
    {
      
      if (isset($_COOKIE['client_area_tracker'])) {

            $result =  $this->db->getAndClearProofs($_SESSION['user'] . '_' . $_COOKIE['client_area_tracker']);
            
            if ($result === false) {
                  $this->outputJson500("Error calling getAndClearProofs in getProcessProofs");           
            }
            else
            {
                  $data = "PROOFS ORDER for: " .  $_SESSION['user']  . "\n\n" . json_encode($result) . "\n\nThe user additionally said\n\n" . $_POST["message"];
                  $this->mailAdmin($data, "Proofs Order on Website.");
                  $obj = new stdClass();
                  $obj->status = "success";
                  $obj->message = "";
                  $this->outputJson($obj);
            }
        }
        else
        {
            $this->outputJson500("Tracking cookie not set in getProcessProofs called with");     
        }

    }
    
    
    public function getClearCompleteBasket() 
    {
        if (isset($_COOKIE['client_area_tracker'])) {
            $result =  $this->clearBasket($_COOKIE['client_area_tracker']);
            if ($result === false) {
                  $this->outputJson500("Error calling clearBasket in getClearCompleteBasket");           
            }
            else
            {
                  $obj = new stdClass();
                  $obj->status = "success";
                  $obj->message = "";
                  $this->outputJson($obj);
            }
        }
        else
        {
            $this->outputJson500("Tracking cookie not set in getClearCompleteBasket");     
        }
         
        
    
    }
    
    public function getSessionStatus()
    {
        //if we got this far...
        $obj = new stdClass();
        $obj->status = "success";
        $obj->message = "";
        $this->setOptions();
        $this->setUserOptions($_SESSION['user']);
        $obj->appData = $this->options;
        $this->outputJson($obj);
    }
    
    public function getModeChoice()
    {
        $obj = new stdClass();
        $obj->status = "success";
        $obj->message = "";
        $this->outputJson($obj);

    }
    

    public function postPaypalStandard() {
   
        $mode = $_POST['mode'];
        $order =  $_POST['order'];
        $clientAreaTracker =   '';
        
        
        if (isset($_COOKIE['client_area_tracker'])) {
            $ref = $_SESSION['user'] . '_' .  $_COOKIE['client_area_tracker'];
            $basket = $this->db->getBasket($ref);
            
            if ($basket === false)
            {
                $this->outputJson500("Unable to get basket in postPaypalStandard");        
            }
            $pendingOrder = array('basket'=>$basket, 'order'=>$order);
            $pendingOrder = $this->fixBackendPricingOnBasket($pendingOrder);
            $orderRef = $this->db->createPendingOrder($ref, $pendingOrder);
            if ($orderRef === false)
            {
                $this->outputJson500("error calling createPendingOrder in postPaypalStandard");
            }
            else
            {
                include_once('ca_utilities.php');
                $formattedPendingOrder =  ClientAreaUtilities::formatBasketJsonObjectToHumanReadable($pendingOrder);
                $data = $this->packageOrderForEmail($orderRef, $formattedPendingOrder, $mode, 'paypalStandard');
                $data = "ORDER REF: $orderRef \n\n" . $data;
                $this->mailAdmin($data, "Provisional Order on Website.");
                $obj = new stdClass();
                $obj->status = "success";
                $obj->message = "";
                $obj->orderRef = $orderRef;
                $this->outputJson($obj); 
            }
        }
        else
        {
            $this->outputJson500("Tracking cookie not set in postPaypalStandard");    
        }
    }
    
    /**
     * reads the basket order which contains pricing calculated on the front-end and replaces the pricing with
     * pricing calculated on the back-end using the pricing config file. client side pricing is open to manipulation.
     * TODO the question is - do we replicate the same calculation logic as in the f/e? here we replicate the JS code into PHP and hope
     * we've got the logic the same. ideally these calculations should be done in a single place. there is an item in the RoadMap to do this.
     * for now we replicate the logic: from 
     * BasketCollection.getTotalCost() and BasketCollection.getDeliveryCost()
     * PricingModel.getSizesForRatio()  (gets the correct pricing block for the image based on the best available match between image ratio size blocks and the image ratio itself)
     * OrderLineModel.setPrices() (sets pricing totals per order line row)
     * 
     * as an interim step we add into the order the correctly calculated totals while leaving the pricing and totals which was shown to the client.
     * the site onwer can then verify that the totals shown to the user and the totals correctly calculated are the same. 
     * obviously this is not ideal and when a single source of pricing is implemented we we will drop the client side pricing from this file. 
     * 
     * the added in block has the following structure:
     *      also on each order line we also lose path field and edit_mode field
     * and add a new field to the root:
     * DeliveryAndTotal.totalItems = 20.00
     * DeliveryAndTotal.deliveryCharges = 5.00
     * DeliveryAndTotal.grandTotal = 25.00
     * 
     * when there is a single source of calculation then the next step is on each order line to replace the fields: image_ratio, print_price, mount_price, frame_price and total_price we ones calculated here.
     *      - they won't be different unless client had manipulated the data
     * and lose the field deliveryAndTotalShownToCustomer 
     * 
     * the basic idea ia the ClientAreaPricingCalculator will do all calculations and the calculations
     * in the f/e as above will instead call services provided by this class. 
     *
     */
    private function fixBackendPricingOnBasket($pendingOrder)
    {
    
        try
        {
            include('ca_pricing_calculator.php');
            $pricingCalculator = new ClientAreaPricingCalculator($this->pricingPath);
        }
        catch(Exception $e)
        {
            $this->outputJson500($e->getMessage());        
        }

        $thumbsDir = $this->clientAreaDirectory . DIRECTORY_SEPARATOR . $_SESSION['user'] .
            DIRECTORY_SEPARATOR . 'prints' . DIRECTORY_SEPARATOR . "thumbs";
         
        $DeliveryAndTotal = new stdClass(); 
        $DeliveryAndTotal->totalItems = 0;
    
        
        foreach ($pendingOrder['basket'] as &$orderLine)
        {
            $imageDimensions = $this->imageDimensions($thumbsDir . DIRECTORY_SEPARATOR . $orderLine->image_ref);
            
            $width = $imageDimensions['width'];
            $height = $imageDimensions['height'];
            
            $actualImageRatio =   max($width, $height) /  min($width, $height);
            $actualImageRatio = number_format((float) $actualImageRatio, 2);

            $rowTotal = 0;
            //logic here from OrderLineModel.setPrices and PricingModel
            if ($orderLine->print_size !== null) 
            {
                $printAndMountPrice = $pricingCalculator->getPrintPriceAndMountPriceForRatioAndSize($actualImageRatio, $orderLine->print_size);
                $rowTotal = $printAndMountPrice->printPrice * $orderLine->qty;    
            }
            if (($orderLine->mount_style !== null) && ($orderLine->mount_style !== 'no_mount')) 
            {
                $printAndMountPrice = $pricingCalculator->getPrintPriceAndMountPriceForRatioAndSize($actualImageRatio, $orderLine->print_size);
                $rowTotal = $rowTotal + ($printAndMountPrice->mountPrice * $orderLine->qty);    
            }
            if (($orderLine->frame_style !== null) && ($orderLine->frame_style !== 'no_frame')) 
            {
                $framePrices = $pricingCalculator->getFramePriceMatrixForGivenRatioAndSize($actualImageRatio, $orderLine->print_size);   
                $applicableFramePrice = $framePrices[$orderLine->frame_style];
                $rowTotal = $rowTotal + ($applicableFramePrice * $orderLine->qty);    
            }
            $orderLine->total_price = number_format((float) $rowTotal, 2);
            unset($orderLine->path);
            unset($orderLine->edit_mode); 
        }
        
        //logic in calculateDeliveryAndTotals is from BasketCollection
        $calculateDelivery = $_SESSION['options']['deliveryChargesEnabled'] ;
        $pendingOrder['DeliveryAndTotal'] = $pricingCalculator->calculateDeliveryAndTotals($pendingOrder['basket'], $calculateDelivery);
        return $pendingOrder;
    
    }
   
    
    public function getThumbs()
    {
        $obj = new stdClass();
        $mode = $this->param;
     
        
        $thumbsDir = $this->clientAreaDirectory . DIRECTORY_SEPARATOR . $_SESSION['user'] .
            DIRECTORY_SEPARATOR . $mode . DIRECTORY_SEPARATOR . "thumbs";
            
        $mainDir = $this->clientAreaDirectory . DIRECTORY_SEPARATOR . $_SESSION['user'] .
            DIRECTORY_SEPARATOR . $mode . DIRECTORY_SEPARATOR . "main";     
            
            
        $files = scandir($thumbsDir);

        $thumbs=array();
        foreach ($files as $file)
        {
        
            $fileObj = new stdClass();
            if ( ($file !== ".")  && ($file !== "..") && (is_file($thumbsDir . DIRECTORY_SEPARATOR . $file)) )
            {
                $fileObj->file = $file;
                //check that the main file exists and don't send the thumb if it is missing
                $mainFile = $mainDir . DIRECTORY_SEPARATOR . $file;
                if (file_exists($mainFile)) { 
                   
                    $fileObj->path =  $this->imageProvider . '?mode=' . $mode . '&size=thumbs&file=' . $file;  
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
    
    public function getImageDimensions()
    {
                            
        $obj = new stdClass();
                                                                 
        $file = $this->param;
        $size = array_shift($this->args);
        $mode = array_shift($this->args);
        
        $filePath = $this->clientAreaDirectory . DIRECTORY_SEPARATOR . $_SESSION['user'] .
            DIRECTORY_SEPARATOR . $mode . DIRECTORY_SEPARATOR . $size . DIRECTORY_SEPARATOR . $file;   

        
        $dimensions = $this->imageDimensions($filePath)   ;
        if (empty($dimensions)) 
        {
            $this->outputJson500("Something has gpne wrong. Please contact support.")   ;
        }
        else
        {
            $actualImageRatio =  max($dimensions['width'], $dimensions['height']) /  min($dimensions['width'], $dimensions['height']);
            $obj->ratio = number_format((float) $actualImageRatio, 2);
            $obj->dimensions =  $dimensions;
            $this->outputJson($obj);
        }
        
    }
    
    
    public function postLogin()
    {       
        $obj = new stdClass();
                                                         
        $user = $_POST['name'];
        $password = $_POST['password'];
        //TODO are we using any of these?                  NO LOSE
        $restoredProofs = $_POST['restoredProofs'];                  
        $restoredProofsPagesVisited = $_POST['restoredProofsPagesVisited'];
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
            
          
            $cookie = null;
           //if this user (identified by browser cookie) has an order in /baskets regenerate from that
            if (!isset($_COOKIE['client_area_tracker'])) {           //TDOO set this onto a class property in the constructor
                $expires =  time() + (10 * 365 * 24 * 60 * 60);
                $rand = time() . rand(0, 1000000);
                $cookie = array('name'=>'client_area_tracker', 'value'=>$rand, 'expires'=>$expires);
             } 
             else
             {
                $ref = $user . '_' . $_COOKIE['client_area_tracker'];
                $this->db->makeBackups($ref) ;
             }
          
            
            //If the user is logging in try to restore the prints pages visited chosen based on what was stored in html data if it is available  
            //TODO not sure if we are using this?
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
        
  
        $this->outputJson($obj, $cookie);

    }
    
    
    
    //MODEL METHODS
    
   
    public function getPricing() 
    {                                          
        $pricingModel = simplexml_load_file($this->pricingPath );
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
    
    public function postPricingCalculationData()
    {
        $call = $_POST['call'];
        try
        {
            include('ca_pricing_calculator.php');
            $pricingCalculator = new ClientAreaPricingCalculator($this->pricingPath);
            switch ($call)
            {
                case 'getSizesForRatio':
                    $ratio = $_POST['imageRatio'];
                    $result = $pricingCalculator->getSizesForRatio($ratio);  
                    break;
                    
                case 'getPrintPriceAndMountPriceForRatioAndSize':
                    $ratio = $_POST['imageRatio'];
                    $printSize = $_POST['printSize'];
                    $result = $pricingCalculator->getPrintPriceAndMountPriceForRatioAndSize($ratio, $printSize);  
                    break;   
                    
                case 'getFramePriceMatrixForGivenRatioAndSize':
                    $ratio = $_POST['imageRatio'];
                    $printSize = $_POST['printSize'];
                    $result = $pricingCalculator->getFramePriceMatrixForGivenRatioAndSize($ratio, $printSize);  
                    break;  
                    
                case 'getCalculateApplicableDevliveryChargesAndTotals':
                    $basket = $this->getBasket();
                    $calculateDelivery = $_SESSION['options']['deliveryChargesEnabled'] ;
                    $result = $pricingCalculator->calculateDeliveryAndTotals($basket, $calculateDelivery);
                    break;                
            }
  
            
        }
        catch(Exception $e)
        {
             $this->outputJson500("Effor getting price calculations in getPricingCalculationData");
        }
 
        $obj = new stdClass();
        $obj->status = "success";
        $obj->data = $result;
        $this->outputJson($obj); 
 
    
    }
    
    
    
    
    
    public function getLangStrings() 
    {                                          
        return  $this->langStrings();
    }
    
   
  
  
    //COLLECTION METHODS
    public function getProofsBasket()
    {
        $result = new stdClass();
        if (isset($_COOKIE['client_area_tracker'])) {
            $ref = $_SESSION['user'] . '_' . $_COOKIE['client_area_tracker'];
            $proofsBasket = $this->db->getProofsBasket($ref);
            if ($proofsBasket === false) 
            {
                $this->outputJson500("Error getting basket in getProofsBasket");
            }
            else
            {
                return $proofsBasket;
            }
        }
        else
        {
            $this->outputJson500("Tracking cookie not sent in call to getProofsBasket");
        }
    
    }
    
    public function postProofsBasket()
    {
        $data = file_get_contents('php://input');
        $dataObj = json_decode($data);
        $fileRef = $dataObj->file_ref;
           
        if (isset($_COOKIE['client_area_tracker'])) {
            $ref = $_SESSION['user'] . '_' . $_COOKIE['client_area_tracker'];
            $result = $this->db->addToProofsBasket($ref, $fileRef);
            if ($result === false) 
            {
                $this->outputJson500("Error calling db->addToProofsBasket in postProofsBasket");
            }
            else
            {
                $dataObj->id = $fileRef;
                return $dataObj;
            }
        }
        else
        {
            $this->outputJson500("Tracking cookie not sent in call to postProofsBasket");
        }
    }
    
    public function deleteProofsBasket()
    {
        $fileRef = $this->param;      //probably not.... 
        if (isset($_COOKIE['client_area_tracker'])) {
            $ref = $_SESSION['user'] . '_' . $_COOKIE['client_area_tracker'];
            $result = $this->db->removeFromProofsBasket($ref, $fileRef);
            if ($result === false) 
            {
                $this->outputJson500("Error calling db->removeFromProofsBasket in deleteProofsBasket");
            }
            else
            {
                return "";   
            }
        }
        else
        {
            $this->outputJson500("Tracking cookie not sent in call to deleteProofsBasket");
        }
    
    }

    public function getBasket() 
    {
         
        $result = new stdClass();
        if (isset($_COOKIE['client_area_tracker'])) {
            $ref = $_SESSION['user'] . '_' . $_COOKIE['client_area_tracker'];
            $basket = $this->db->getBasket($ref);
            if ($basket === false) 
            {
                $this->outputJson500("Error getting basket in getBasket");
            }
            else
            {
                return $basket;
            }
        }
        else
        {
            $this->outputJson500("Tracking cookie not sent in call to getBasket");
        }
        
    }
    
    public function postBasket()
    {
        $newOrderLine = file_get_contents('php://input');
        $order = json_decode($newOrderLine);
        $order->edit_mode = 'save';
        
        if (isset($_COOKIE['client_area_tracker'])) {
            $ref = $_SESSION['user'] . '_' . $_COOKIE['client_area_tracker'];
            $result = $this->db->addToBasket($ref, $order);
            if ($result === false) 
            {
                $this->outputJson500("Error calling db->addToBasket in postBasket");
            }
            else
            {
                $newId = $result;
                $order->id = $newId;
                return $order;
                    
            }
        }
        else
        {
            $this->outputJson500("Tracking cookie not sent in call to postBasket");
        }

    }
    
    public function putBasket()
    {
        $updatedOrderLine = file_get_contents('php://input');
        $orderId = $this->param;
        $order = json_decode($updatedOrderLine);
        $order->edit_mode = 'save';
        if (isset($_COOKIE['client_area_tracker'])) {
            $ref = $_SESSION['user'] . '_' . $_COOKIE['client_area_tracker'];
            $result = $this->db->updateBasket($ref, $orderId, $order);
            if ($result === false) 
            {
                $this->outputJson500("Error calling db->updateBasket in putBasket");
            }
            else
            {
                return $order;    
            }
        }
        else
        {
            $this->outputJson500("Tracking cookie not sent in call to putBasket");
        }
      
      

    }
    //remove an order line from the basket
    public function deleteBasket()
    {
        $orderId = $this->param;
        if (isset($_COOKIE['client_area_tracker'])) {
            $ref = $_SESSION['user'] . '_' . $_COOKIE['client_area_tracker'];
            $result = $this->db->removeFromBasket($ref, $orderId);
            if ($result === false) 
            {
                $this->outputJson500("Error calling db->removeFromBasket in deleteBasket");
            }
            else
            {
                return true;   
            }
        }
        else
        {
            $this->outputJson500("Tracking cookie not sent in call to deleteBasket");
        }
    
    }
    

  
    //HELPERS

   
    private function clearBasket($trackerId)
    {
          return $this->db->clearBasket($trackerId);    
    }
    
   
    private function createBasket($trackerId, $mode)
    {
        if ($mode == 'prints')
        {
            $ret =  $this->db->createBasket($trackerId);
        }
        else
        {
            $ret =  $this->db->createProofsBasket($trackerId);
        }
        
        return $ret;
    }
    
 
    
    
    /**
     *   TODO - text in lang file
     *   make this look nicer
     *
     */
    private function packageOrderForEmail($orderRef, $formattedOrder, $mode, $gateway) {
    
        $package = "Hi\n\n";    
    
        if ($mode == 'online_payment') {
            $package.= "This is NOT the actual order. It indicates the user MAY be attempting a purchase. Wait for the 'Order Confirmed' email and/or check your account ($gateway) to check that you have received the payment";
        }   else {
            $package.= 'An order has been placed on the web site. You need to take payment manually.';    
        }


        $package = "\n\n" . $package . "\n\nBasket:\n\n";
        $package = $package . $formattedOrder . "\n\n";
        
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
        
        if (!empty($userOptions->thumbMaxHeight)) {
           $this->options["thumbMaxHeight"]   = (int) $userOptions->thumbMaxHeight;  
        }

        if (!empty($userOptions->enableOnlinePayments)) {
            $this->options["enableOnlinePayments"]   = $userOptions->enableOnlinePayments == 'true' ? true: false; 
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
        
       if (!empty($userOptions->printsModeMessage)) {
            $this->options["printsModeMessage"]   = $userOptions->printsModeMessage . "";
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
        $options["thumbMaxHeight"] = (int) $userOptions->thumbMaxHeight;
        $options["proofsShowLabels"] = $userOptions->proofsShowLabels == 'true' ? true: false;
        $options["showNannyingMessageAboutMoreThanOnePage"] =  $userOptions->showNannyingMessageAboutMoreThanOnePage == 'true' ? true: false;
        $options["enableOnlinePayments"] = $userOptions->enableOnlinePayments == 'true' ? true: false;
        $options["paymentGateway"] = false;
        $gateways = $client_area_options->options->system->paymentGateway->children();
          foreach ($gateways as $gateway) {
             if ($gateway  == 'true') {
                $options["paymentGateway"] = $gateway->getName();
                break;    
            }
        }
       
        $options["deliveryChargesEnabled"] = $userOptions->deliveryChargesEnabled == 'true' ? true: false;
        $options["proofsModeMessage"] = $userOptions->proofsModeMessage . "";
        $options["printsModeMessage"] = $userOptions->printsModeMessage . "";
        $options["paypalAccountEmail"]  =  $client_area_options->options->system->paypalStandard->paypalAccountEmail . "";
        $options["paypalSandboxAccountEmail"]  =  $client_area_options->options->system->paypalStandard->paypalSandboxAccountEmail . "";
        $options["paypalIPNHandler"]  =  $client_area_options->options->system->paypalStandard->paypalIPNHandler . "";
        $options["paypalIPNSSL"] = $client_area_options->options->system->paypalStandard->paypalIPNSSL == 'true' ? true: false;
        $options["adminEmail"]  =  $client_area_options->options->system->adminEmail . "";
        $options["mode"]  =  $client_area_options->options->system->mode . "";
        $options["domain"]  =  $client_area_options->options->system->domain . "";
        $options["paypalPaymentDescription"]  =  $client_area_options->options->system->paypalStandard->paypalPaymentDescription . "";
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
    
    private function imageDimensions($file)
    {
        $dimensions = getimagesize($file);

        $result = array();
        if ($dimensions !== false) {
            $result["width"] = $dimensions[0];
            $result["height"] = $dimensions[1];
        }

        return $result;
     }
    
    private function outputJson($output, $cookie = null) {
        header("Content-type: application/json");
        if (!is_null($cookie))
        {
            setcookie($cookie['name'], $cookie['value'], $cookie['expires']);
        }
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