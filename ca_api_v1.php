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
        $this->outputJson($obj);
    }
    
    public function getModeChoice()
    {
        $obj = new stdClass();
        $obj->status = "success";
        $obj->message = "";
        $this->outputJson($obj);

    }
    
    public function getPrintThumbs()
    {
        $obj = new stdClass();
        
        $thumbsDir = $this->clientAreaDirectory . DIRECTORY_SEPARATOR . $_SESSION['user'] .
            DIRECTORY_SEPARATOR . 'prints' . DIRECTORY_SEPARATOR . "thumbs";
            
            
        $files = scandir($thumbsDir);

        $thumbs=array();
        foreach ($files as $file)
        {
        
            $fileObj = new stdClass();
            if ( ($file !== ".")  && ($file !== "..") && (is_file($thumbsDir . DIRECTORY_SEPARATOR . $file)) )
            {
                $fileObj->file = $file;
                //TODO - in a loop? or at least we should cache the results
                $imageDimensions = $this->getImageDimensions($thumbsDir . DIRECTORY_SEPARATOR . $file);
                $fileObj->width =  $imageDimensions["width"];
                $fileObj->height =  $imageDimensions["height"];
                $fileObj->path =  $this->imageProvider . '?mode=prints&size=thumbs&file=' . $file;   //TODO put imageProvider onto some init and then mode and size can be set f/e
                $thumbs[] = $fileObj;
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
                if (is_array($restoredPrintssArray)) {
                    $_SESSION["printsChosen"] = $restoredPrintsArray;
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
  
  
    //COLLECTION METHODS
    public function getBasket() 
    {
        $result = new stdClass();
        $basket = $_SESSION["basket"];
        $deIndexedBasket[] = array();
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
        $options = simplexml_load_file($this->clientAreaDirectory . DIRECTORY_SEPARATOR . $user .
            DIRECTORY_SEPARATOR . "options.xml" );

        if ($options === false) {
             $this->outputJson500("Missing or broken user options file for " . $user);
        } 
        
        
        if (!isset($this->options)) {
            $this->outputJson500("Incorrect call to setUserOptions before call to setOptions " . $user);
        }

        $this->options["proofs_on"] = (bool) $options->proofsOn;
        $this->options["prints_on"] = (bool) $options->printsOn;
        //TODO are we using these?
        $this->options["customProofsMessage"] = $options->customProofsMessage . "";;
        $this->options["customPrintsMessage"] = $options->customPrintsMessage . "";;

        //potentially overide global options
        if (!empty($options->thumbsPerPage)) {
           $this->options["thumbsPerPage"]   = (int) $options->thumbsPerPage;  
        }
           
        if (!empty($options->enablePaypal)) {
            $this->options["enablePaypal"]   = (bool) $options->enablePaypal;  
        }
            
        if (!empty($options->proofsShowLabels)) {
            $this->options["proofsShowLabels"]   = (bool) $options->proofsShowLabels;  
        }
           
        if (!empty($options->showNannyingMessageAboutMoreThanOnePage)) {
            $this->options["showNannyingMessageAboutMoreThanOnePage"]   = (bool) $options->showNannyingMessageAboutMoreThanOnePage;  
        }
            
        $this->options["human_name"] =  $this->accounts[$user]["human_name"]   ;
    }
    
    private function setOptions()
    {
        $client_area_options = simplexml_load_file($this->clientAreaDirectory . DIRECTORY_SEPARATOR . 'client_area_options.xml');

        if ($client_area_options === false) {
            $this->outputJson500("Error in options file or file does not exist");
        }

        $displayOptions = $client_area_options->options->display;

        $options = array();
        $options["thumbsPerPage"] = (int) $displayOptions->thumbsPerPage;
        $options["proofsShowLabels"] = (bool) $displayOptions->proofsShowLabels;
        $options["showNannyingMessageAboutMoreThanOnePage"] = (bool) (int)  $displayOptions->showNannyingMessageAboutMoreThanOnePage;
        $options["enablePaypal"] = (bool) $displayOptions->enablePaypal;

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