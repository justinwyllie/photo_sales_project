<?php


/**
 * Database fields: gateway, orderRef, sessionId, datePlaced(epoch), paymentStatus, ipnTrackId
 * paymentStatus is initially 'provisional' then the PayPal value. we are looking for 'Completed'
 *    TODO - if something goes wrong we will lose the database need a backup 
 *
 */

class ClientAreaDB
{


    public function __construct($path)
    {
    
        $file = $path . DIRECTORY_SEPARATOR . 'ca_orders_database.csv';
        $this->fileHandle = file($file, 'w') ;
        $this->db = [];

    }
    
    
    /**
     * return the sessionId of the updated order
     */

    public function markOrderStatus($orderRef, $paymentStatus, $ipnTrackId) {
        $sesionId = null;
        $this->readFile();
            foreach ($this->db as &$order) {
            if ($order[1] == $orderRef) {
                $order[4]   =  $paymentStatus;
                $order[5]   =  $ipnTrackId;
                $sesionId = $order[2];
                break;
            }
        
        }
        return $sessionId;
        fclose($this->fileHandle); 
    }
    
    public function getSessionId($orderRef) {
        $this->readFile();
        $sesionId = null;
        foreach ($this->db as $order) {
            if ($order[1] == $orderRef) {
                $sessionId = $order[2];
                break;
            }
        
        }
        fclose($this->fileHandle); 
        return $sessionId;

    }
    
    public function createEntry($gateway, $orderRef, $sessionId)   {
        $this->readFile();
        $epoch = time();
        $this->db[] = array($gateway, $orderRef, $sessionId, $epoch, 'provisional', '');
        $this->writeFile();
        fclose($this->fileHandle); 
    }
    
    
          
 
    private function readFile() {
        $epoch = time();
        $oneMonthSeconds = 60 * 60 * 24 * 30 * 3;
        while ( ($order = fgetcsv($this->fileHandle) ) !== FALSE ) {
            //lose records more than 3 months old to prevent database ever expanding
            if ($order[3] > ($epoch - $oneMonthSeconds)) {
                $this->db[] = $order;
            }
        }
    }
    
    private function  writeFile() {
    
        foreach ($this->db as $order) {
            fputcsv($this->fileHandle, $order);
        }   
            
    }
    
}    

?>