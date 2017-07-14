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
        $this->proofsBasketDir =  $this->path . DIRECTORY_SEPARATOR . 'proofs_basket';
        if (!file_exists($this->proofsBasketDir))
        {
            mkdir($this->proofsBasketDir, 0744);    
        }
        $this->proofsBasketCompletedDir =  $this->path . DIRECTORY_SEPARATOR . 'proofs_basket_completed';
        if (!file_exists($this->proofsBasketCompletedDir))
        {
            mkdir($this->proofsBasketCompletedDir, 0744);    
        }
        $this->backupsDir =  $this->path . DIRECTORY_SEPARATOR . 'backups';
        if (!file_exists($this->backupsDir))
        {
            mkdir($this->backupsDir, 0744);    
        }
        $this->proofsBackupsDir =  $this->path . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR . 'proofs';
        if (!file_exists($this->proofsBackupsDir))
        {
            mkdir($this->proofsBackupsDir, 0744);    
        }
        $this->basketBackupsDir =  $this->path . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR . 'basket';
        if (!file_exists($this->basketBackupsDir))
        {
            mkdir($this->basketBackupsDir, 0744);    
        }
    }
    
    private function cleanUpFiles($mode)
    {
        $threeMonthsInSeconds = 60 * 60 * 24 * 90;
        if ($mode == 'prints')
        {
            $this->cleanUp($this->basketDir, $threeMonthsInSeconds); 
            $this->cleanUp($this->pendingDir, $threeMonthsInSeconds);
            $this->cleanUp($this->completedDir, $threeMonthsInSeconds); 
        }
        else
        {
            $this->cleanUp($this->proofsBasketDir, $threeMonthsInSeconds);
        }
  
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
    
    public function makeBackups($ref)
    {
    
        $possibleProofsBasketFile = $this->proofsBasketDir . DIRECTORY_SEPARATOR . $ref;
        if (file_exists($possibleProofsBasketFile))
        {
            $targetFile =  $this->generateUniqueFile($this->proofsBackupsDir, $ref); 
            copy($possibleProofsBasketFile, $this->proofsBackupsDir . DIRECTORY_SEPARATOR . $targetFile);   
        } 
        
        $possibleBasketFile = $this->basketDir . DIRECTORY_SEPARATOR . $ref;
        if (file_exists($possibleBasketFile))
        {
            $targetFile =  $this->generateUniqueFile($this->basketBackupsDir, $ref); 
            copy($possibleBasketFile, $this->basketBackupsDir . DIRECTORY_SEPARATOR . $targetFile);   
        } 
    
    }
    
    public function createBasket($ref)
    {
    
        $this->cleanUpFiles('prints');
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
    
    public function removeFromBasket($ref, $orderId)
    {
        $basketFile = $this->basketDir . DIRECTORY_SEPARATOR . $ref;
        $basket = $this->getBasket($ref);
        $updatedBasket = array();

        foreach ($basket as $orderLine)
        {   
            if ($orderLine->id != $orderId) {
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
    
    public function updateFieldToBasket($ref, $fieldName, $fieldValue)                 //TODO???
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
    
    
    public function createProofsBasket($ref)   
    {
    
        $this->cleanUpFiles('proofs');
        $proofsBasketFile = $this->proofsBasketDir . DIRECTORY_SEPARATOR . $ref ;
        if (file_exists($proofsBasketFile))
        {
            return true;
        }
        else
        {
            $proofsBasketFile = $this->proofsBasketDir . DIRECTORY_SEPARATOR . $ref;
            $result = file_put_contents($proofsBasketFile, json_encode(array())) ;
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
    
    public function getProofsBasket($ref)
    {
        $proofsBasketFile = $this->proofsBasketDir . DIRECTORY_SEPARATOR . $ref ;
        
        if (file_exists($proofsBasketFile))
        {
            $proofsBasketString = file_get_contents($proofsBasketFile);  
            $objProofsBasket = json_decode($proofsBasketString);
            return $objProofsBasket;
        }
        else
        {
            return false;
        }
    }
    
    public function addToProofsBasket($ref, $fileRef)
    {
        $proofsBasketFile = $this->proofsBasketDir . DIRECTORY_SEPARATOR . $ref;
        $proofsBasket = $this->getProofsBasket($ref);

        $inBasket = false;
        foreach ($proofsBasket as $proof)
        {
            if ($proof->file_ref == $fileRef)
            {   
                $inBasket = true;
                break;
            }
        }

        if (!$inBasket)
        {
            $newProof = new stdClass();
            $newProof->id = $fileRef;
            $newProof->file_ref = $fileRef; 
            $proofsBasket[] = $newProof;
        }
        $result = file_put_contents($proofsBasketFile, json_encode($proofsBasket)) ; //TODO we have no backup?
        return $result;  
    }
    
    public function removeFromProofsBasket($ref, $fileRef)
    {
        $proofsBasketFile = $this->proofsBasketDir . DIRECTORY_SEPARATOR . $ref;
        $proofsBasket = $this->getProofsBasket($ref);
        $newProofsBasket = array();
        foreach ($proofsBasket as $proof)
        {
            if ($proof->file_ref != $fileRef)
            {   
                $newProofsBasket[] = $proof;
            }
        }
        
        $result = file_put_contents($proofsBasketFile, json_encode($newProofsBasket)) ; //TODO we have no backup?
        return $result;  
    }
    
    public function clearProofsBasket($ref)
    {
        $proofsBasketFile = $this->proofsBasketDir . DIRECTORY_SEPARATOR . $ref ;
        return unlink($proofsBasketFile);       
    }
    
    
    public function getAndClearProofs($ref)
    {
        $proofsBasketFile = $this->proofsBasketDir . DIRECTORY_SEPARATOR . $ref;
        $targetFile =  $this->generateUniqueFile($this->proofsBasketCompletedDir, $ref);
        $proofsBasketFileCompleted = $this->proofsBasketCompletedDir . DIRECTORY_SEPARATOR . $targetFile;
        $proofsBasket = $this->getProofsBasket($ref);
        
        $result = rename($proofsBasketFile, $proofsBasketFileCompleted);
        if ($result)
        {
            return $proofsBasket; 
        }
        else
        {
            return false;
        }
        
              
    
    }
    
    private function generateUniqueFile($dir, $file)
    {
           
       $finalName = $file; 
       $i = 1; 
 
       while(file_exists($dir . DIRECTORY_SEPARATOR . $finalName))
       {        
            $finalName = $file . "-" . $i;
            $i++;
        }
        return $finalName;
    
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
        $orderRef = $this->generateUniqueFile($this->pendingDir, $ref);
        $basketFile = $this->basketDir . DIRECTORY_SEPARATOR . $ref ;
        $pendingFile =   $this->pendingDir . DIRECTORY_SEPARATOR . $orderRef;
        $result = file_put_contents($pendingFile, json_encode($basketWithBackendPricingDelAndTotals)) ;
        if ($result === false)
        {
            return false;
        }
        else
        {
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