<?php



 
 class ClientAreaDB
 {
    
    private static $path = "/var/www/vhosts/mms-oxford.com/jwp_client_area_files"; 

    public static function create()
    {
        return new ClientAreaTextDB(self::$path);
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
        
        $this->basketDir =  $this->path . DIRECTORY_SEPARATOR . 'basket';
        if (!file_exists($this->basketDir))
        {
            mkdir($this->basketDir, 0744);    
        }
        $this->pendingDir =  $this->path . DIRECTORY_SEPARATOR . 'pending';
        if (!file_exists($this->pendingDir))
        {
            mkdir($this->pendingDir, 0744);    
        }
        $this->completedDir =  $this->path . DIRECTORY_SEPARATOR . 'completed';
        if (!file_exists($this->completedDir))
        {
            mkdir($this->completedDir, 0744);    
        }
    }
    
    private function cleanUpFiles()
    {
        $threeMonthsInSeconds = 60 * 60 * 24 * 90;
        $this->cleanUp($this->basketDir, $threeMonthsInSeconds); 
        $this->cleanUp($this->pendingDir, $threeMonthsInSeconds);
        $this->cleanUp($this->completedDir, $threeMonthsInSeconds); 
    }
    
    private function cleanUp($dir, $age)
    {
        $now = time();
        foreach (new DirectoryIterator($dir) as $fileInfo) {
            if ($fileInfo->isDot())
            {
                continue;
            }     
            $path = $fileInfo->getPathname(); 
            if (($now - filemtime($path)) >= $age) 
            {
                unlink($path);
            }
        }   
    }
    
    public function createBasket($ref)
    {
    
        $this->cleanUpFiles();
        $basketFile = $this->basketDir . DIRECTORY_SEPARATOR . $ref ;
        if (file_exists($basketFile))
        {
            return true;
        }
        else
        {
            $basketFile = $this->basketDir . DIRECTORY_SEPARATOR . $ref;
            $result = file_put_contents($basketFile, json_encode(array())) ;
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
            $basketString = file_get_contents($basketFile);  
            $objBasket = json_decode($basketString);
            return $objBasket;
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
    
    /**
     * @return $orderId
     *
     */
    public function addToBasket($ref, $order)
    {
        $basketFile = $this->basketDir . DIRECTORY_SEPARATOR . $ref;
        $basket = $this->getBasket($ref);
        $newOrderId = $this->generateUniqueOrderId($basket);
        $order->id = $newOrderId;
        $basket[] = $order;
        
        $result = file_put_contents($basketFile, json_encode($basket)) ; //TODO we have no backup?
        if ($result === false)
        {
            return false;
        }   
        else
        {
            return $newOrderId;
        }  
    }
    
    public function updateFieldToBasket($ref, $fieldName, $fieldValue)
    {
        $basketFile = $this->basketDir . DIRECTORY_SEPARATOR . $ref;
        $basket = $this->getBasket($ref);
        
    
    
    }
    
    public function updateBasket($ref, $orderId, $order)
    {
        $basketFile = $this->basketDir . DIRECTORY_SEPARATOR . $ref;
        $basket = $this->getBasket($ref);
        $updatedBasket = array();
        
        foreach ($basket as $orderLine)
        {
            if ($orderLine->id == $orderId) {
                $updatedBasket[] = $order;       
            }
            else
            {
                $updatedBasket[] = $orderLine;    
            }
        }
        
        $result = file_put_contents($basketFile, json_encode($updatedBasket)) ; 
        if ($result === false)
        {
            return false;
        }   
        else
        {
            return true;
        }  
    }
    
    private function generateUniqueOrderId($basket)
    {
        $id = 0;
        foreach ($basket as $order) {
            if ($order->id > $id) {
                $id = $order->id;
            }
        }
        
        return $id + 1;

    } 
    

    public function createPendingOrder($ref, $basketWithBackendPricingDelAndTotals)
    {
        $orderId = time(); //TODO how close are we getting to 255 max length?
        $orderRef = $ref . '_' . $orderId;
        $basketFile = $this->basketDir . DIRECTORY_SEPARATOR . $ref ;
        $pendingFile =   $this->pendingDir . DIRECTORY_SEPARATOR . $orderRef;
        $result = file_put_contents($pendingFile, json_encode($basketWithBackendPricingDelAndTotals)) ;
        if ($result === false)
        {
            return false;
        }
        else
        {
            $this->clearBasket($ref);
            return $orderRef;
        }
       
    }
    
    public function movePendingOrderToCompleted($orderRef, $ipnTrackId = null, $txnId = null)
    {
        $pendingFile = $this->pendingDir . DIRECTORY_SEPARATOR . $orderRef;
        $completedFile = $this->completedDir . DIRECTORY_SEPARATOR . $orderRef;
        if (file_exists($pendingFile))
        {
            $moveResult = rename($pendingFile, $completedFile);
            if ($moveResult) {
                 $completedString = file_get_contents($completedFile);
                $completed = json_decode($completedString);
                $completed->ipnTrackId = $ipnTrackId;
                $completed->txnId = $txnId; 
                $addTransInfoResult = file_put_contents($completedFile, json_encode($completed));
                if ($addTransInfoResult === false)
                {
                    return false;
                }
                else
                {
                    return $completed;
                }
            }
            else
            {
                return false;
            }
        }
        else
        {
            return false;
        }
    }
    
    

}
 

















 

?>