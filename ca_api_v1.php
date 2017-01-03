<?php

session_start();
//see http://coreymaynard.com/blog/creating-a-restful-api-with-php/ if you want to do this properly

$API = new ClientAreaAPI($_REQUEST['request'] );



class ClientAreaAPI
{


    public function __construct($request)
    {
        //TOOD for now login is handled by the 'old' app. here we just check they've logged in with that
        if (!empty($_SESSION["user"])) {
            $this->user = $_SESSION["user"];
        } else {
            $obj = new stdClass();
            $obj->status = "error";
            $obj->message = "not_logged_in";
            $this->outputJson($obj);

        }

    
        $this->clientAreaDirectory = $_SESSION["client_area_directory_path"];
       
        $this->args = explode('/', rtrim($request, '/'));
        $this->endpoint = array_shift($this->args);       //TODO handle e.g. basket/1
        if (sizeof($this->args) >= 1) {
            $this->param = array_shift($this->args);
        }
      
        
        $this->verb = strtolower($_SERVER["REQUEST_METHOD"]);
        $this->action = $this->verb . ucfirst($this->endpoint);
        $content = call_user_func_array(array($this, $this->action), array());
        $this->outputJson($content);
    
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
    
    //TODO this is basically the same as the  setLang method in CLientArea - it would be nice to make them share a common helper class or something
    public function getLangStrings() 
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
  
  
    //COLLECTION METHODS
    public function getBasket() 
    {
        $result = new stdClass();
        $basket = $_SESSION["basket"];
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
  
  private function outputJson($output) {
      header("Content-type: application/json");
      echo json_encode($output);
      exit();
  }
  
  
 
}

?>