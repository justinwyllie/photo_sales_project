<?php


/**
 * Database fields: gateway, orderRef, sessionId, datePlaced(epoch), paymentStatus, ipnTrackId
 * paymentStatus is initially 'provisional' then the PayPal value. we are looking for 'Completed'
 *    TODO - if something goes wrong we will lose the database need a backup 
 *
 */

class ClientAreaDB
{
    //absolute path on your system to the client_area_files directory
    private $path = "/var/www/vhosts/mms-oxford.com/jwp_client_area_files"; 

    public function __construct()
    {

        $this->file = $this->path . DIRECTORY_SEPARATOR . 'ca_orders_database.csv';
        $this->db = [];
        $this->createDatabase();

    }
    
    
    /**
     * return the sessionId of the updated order
     */

    public function markOrderStatus($orderRef, $paymentStatus, $ipnTrackId) {
        //TODO if we support gateways other than PayPal standard we will have to equalise the paymentStatus field as we check for 'Completed' in postLogin
        $sessionId = null;
        foreach ($this->db as &$order) {
            error_log('gothere'.$order[1]);
            if ($order[1] == $orderRef) {
                $order[4]   =  $paymentStatus;
                $order[5]   =  $ipnTrackId;
                $sessionId = $order[2];
                break;
            }
        
        }
        $this->saveDatabase();
        return $sessionId;
     }
    
    public function getOrderCompletedStatus($orderRef) {
        $orderStatus = false;
        foreach ($this->db as $order) {
            if ($order[1] == $orderRef) {
                $orderStatus = $order[4];
                break;
            }
        }
        if ($orderStatus === 'Completed') {
            return true;
        } 
        else  
        {
            return false;
        }
       

    }
    
    public function createEntry($gateway, $orderRef, $sessionId)   {
        $epoch = time();
        $this->db[] = array($gateway, $orderRef, $sessionId, $epoch, 'provisional', '');
        $this->saveDatabase();
    }
    

     
 
    private function createDatabase() {
        $fileHandle = fopen($this->file, 'r') ;
        $epoch = time();
        $oneMonthSeconds = 60 * 60 * 24 * 30 * 3;
        if (!empty($fileHandle)) {
            while ( ($order = fgetcsv($fileHandle) ) !== FALSE ) {
                //lose records more than 3 months old to prevent database ever expanding   TODO test
                if ($order[3] > ($epoch - $oneMonthSeconds)) {
                     $this->db[] = $order;
                }
            }
        error_log('aaa'.sizeof($this->db));    
        $this->closeFileHandle($fileHandle);    
        }
    }
    
    private function saveDatabase() {
        $fileHandle = fopen($this->file, 'w') ;
        if (!empty($fileHandle)) {
            foreach ($this->db as $order) {
                fputcsv($fileHandle, $order);
            }
            $this->closeFileHandle($fileHandle);   
        }    
    }
    
    private function closeFileHandle($fileHandle) {
        fclose($fileHandle); 
    }
    
}    

?>