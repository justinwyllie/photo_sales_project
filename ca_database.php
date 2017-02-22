<?php



 
 class ClientAreaDB
 {
    
    private $path = "/var/www/vhosts/mms-oxford.com/jwp_client_area_files"; 

    public static function create()
    {
        return new ClientAreaTextDB(self::path);
    }
 
}
 
 
class ClientAreaTextDB
{

    public function __construct($path)
    {
        $this->path = $path;
        $this->createDirectories();
 
    }
    
    //requires safe_mode_include_dir.
    private function createDirectories()
    {
        
        $this->basketDir =  $this->path . DIRECTORY_SEPARATOR . 'basket'
        if (!file_exists($this->basketDir))
        {
            mkdir($this->basketDir, 0644);    
        }
        $thi->pendingDir =  $this->path . DIRECTORY_SEPARATOR . 'pending'
        if (!file_exists($this->pendingDir))
        {
            mkdir($this->pendingDir, 0644);    
        }
        $this->completedDir =  $this->path . DIRECTORY_SEPARATOR . 'completed'
        if (!file_exists($this->completedDir))
        {
            mkdir($this->completedDir, 0644);    
        }
    }
    
    public function createBasket($ref)
    {
        $basketFile = $this->basketDir . DIRECTORY_SEPARATOR . $ref ;
        if (file_exists($basketFile))
        {
            return true;
        }
        else
        {
            $result = file_put_contents($this->basketDir . DIRECTORY_SEPARATOR . $ref, json_encode(array())) ;
            if ($result === false)
            {
                return false;
            }   
            else
            {
                return true;
            }   
        }
    }
    
    
    public function getBasket($ref)
    {
        $basketFile = $this->basketDir . DIRECTORY_SEPARATOR . $ref ;
        
        if (file_exists($basketFile))
        {
            $basketString = file_get_contents($this->basketDir);  
            return json_decode($basketString);
        }
        else
        {
            return false;
        }
    }
    
    public function clearBasket($ref)
    {
        $basketFile = $this->basketDir . DIRECTORY_SEPARATOR . $ref ;
        return unlink($basketFile);       

    }
    

    public function createPendingOrder($ref, $basketWithBackendPricing)
    {
        
        $orderId = time(); //TODO how close are we getting to 255 max length?
        $orderRef = $ref . '_' . $orderId;
        $basketFile = $this->basketDir . DIRECTORY_SEPARATOR . $ref ;
        $pendingFile =   $this->pendingDir . DIRECTORY_SEPARATOR . $orderRef;
        $result = file_put_contents($pendingFile, json_encode($basketWithBackendPricing)) ;
        if ($result === false)
        {
            return false;
        }
        else
        {
            return $this->clearBasket($ref);
        }
       
    }
    
    public function movePendingOrderToCompleted($ref)
    {
        $basket = $this->getBasket($ref);
        if  ($basket === false)
        {
            return false;
        }
        $orderId = time(); //TODO how close are we getting to 255 max length?
        $orderRef = $ref . '_' . $orderId;
        $basketFile = $this->basketDir . DIRECTORY_SEPARATOR . $ref ;
        $pendingFile =   $this->pendingDir . DIRECTORY_SEPARATOR . $orderRef;
        $result = rename($basketFile, $pendingFile);
        if ($result) {
            return array('orderRef'=>$orderRef, 'basket'=>$basket);
        }
        else
        {
            return false;
        }
    }
    
    

}
 

















 

?>