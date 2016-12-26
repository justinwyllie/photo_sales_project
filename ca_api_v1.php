<?php

session_start();

//check user is logged in
if (!empty($_SESSION["user"])) {
    $user = $_SESSION["user"];
} else {
    //TODO handle this better
    die();
}

//see http://coreymaynard.com/blog/creating-a-restful-api-with-php/ if you want to do this properly

$API = new ClientAreaAPI($_REQUEST['request'] );



class ClientAreaAPI
{


    public function __construct($request)
    {
        $this->clientAreaDirectory = $_SESSION["client_area_directory_path"];
       
        $this->args = explode('/', rtrim($request, '/'));
        $this->endpoint = array_shift($this->args);       //TODO handle e.g. basket/1
      
        
        $this->verb = strtolower($_SERVER["REQUEST_METHOD"]);
        $this->action = $this->verb . ucfirst($this->endpoint);
        $content = call_user_func_array(array($this, $this->action), array());
        $this->outputJson($content);
    
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
        //dummy data
        $basket[] = array("id"=>1, "image" => "image-001.jpg", "print_price"=>"5.00", "size"=>"9x6", "mount_price"=>"15.00", "mount_style"=>"White", "frame_style"=>"A", "frame_price"=>"25.00", "qty"=>"1");
        $basket[] = array("id"=>3, "image" => "image-001.jpg", "print_price"=>"5.00", "size"=>"9x6", "mount_price"=>"15.00", "mount_style"=>"White", "frame_style"=>"C", "frame_price"=>"50.00", "qty"=>"2");
        $basket[] = array("id"=>2, "image" => "image-002.jpg", "size"=>"9x6", "print_price"=>"5.00");
          
        return $basket;
  
  }
  

  
  
  private function outputJson($output) {
      header("Content-type: application/json");
      echo json_encode($output);
      exit();
  }
  
  
 
}

?>