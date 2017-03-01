<?php

class ClientAreaUtilities
{
    public function __construct()
    {
    
    }
    
    /**
     * @return string
     *
     */
    public static function formatBasketJsonObjectToHumanReadable($jsonBasketBlob)
    {
    
        return json_encode($jsonBasketBlob) . "FORMATED!";
   
    }
    
}  


?>