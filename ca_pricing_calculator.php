<?php



//basically copied from PricingModel and will eventually provide all the calculations currently being done in that model as services to the model   plus those currently being 
//done in basketColletion and orderLineModel
//so that there is a single source of truth for price calculations    see inline comments for   fixBackendPricingOnBasket and road map
class ClientAreaPricingCalculator
{

    public function __construct($pricingFilePath)
    {
        $this->pricingModel = simplexml_load_file($pricingFilePath );

        $this->cache = new stdClass();
        $this->cache->sizesForRatio = array();
        $this->cache->sizeGroupForRatioAndSize = array();
        $this->cache->printPriceAndMountPriceForRatioAndSize = array();
        $this->cache->framePriceMatrixForGivenRatioAndSize = array();
    }
    
    /**
     * @return array of sizeBlocks
     *
     */
    
    private function  getSizesForRatio($imageRatio)
    {
     
            
        if ((array_key_exists($imageRatio, $this->cache->sizesForRatio))) 
        {
            return $this->cache->sizesForRatio[$imageRatio];
        }
        else
        { 
            $printSizes = $this->pricingModel->printSizes;
            $sizeGroupsForRatio = null;
            
            //TODO. This is painful.
            $availableRatios = array();
            $sizeGroups = $printSizes->xpath('./sizeGroups/sizeGroup') ;
    
            foreach ($sizeGroups as $sizeGroup)
            {
                $availableRatios[] = $sizeGroup->ratio . '';    
            }
            
            //work out nearest match ratio
            $lastDiff = null;
            $bestMatchRatio;
            foreach($availableRatios as $availableRatio)
            {
                $newDiff =  abs($imageRatio - $availableRatio);
                if (($lastDiff === null) || ($newDiff < $lastDiff ))
                {
                    $bestMatchRatio = $availableRatio;
                    $lastDiff = $newDiff;    
                }
            }
            //get the size group using the bestMatchRatio
            foreach ($sizeGroups as $sizeGroup)
            {
                if (($sizeGroup->ratio . '' ) ==  $bestMatchRatio)
                {
                    $sizeGroupsForRatio = $sizeGroup->xpath('./sizes/size');
                    break;    
                }
            }
            //TODO is this an array even if only one ?

            $this->cache->sizesForRatio[$imageRatio] = $sizeGroupsForRatio;
            return $sizeGroupsForRatio;
        }
             
    }
    
    private function  getSizeGroupForRatioAndSize($imageRatio, $printSize)
    {
        
        if ((array_key_exists($imageRatio, $this->cache->sizeGroupForRatioAndSize)) && (array_key_exists($printSize, $this->cache->sizeGroupForRatioAndSize[$imageRatio]))) 
        {
            return $this->cache->sizeGroupForRatioAndSize[$imageRatio][$printSize];
        }
        else
        {
            $sizeBlock = null;
            $sizeGroups = $this->getSizesForRatio($imageRatio);

            foreach ($sizeGroups as $size)
            {
                if ($size->value == $printSize)
                {
                    $sizeBlock = $size;
                    break;    
                }
            }
            if (!array_key_exists($imageRatio, $this->cache->sizeGroupForRatioAndSize)) 
            {
               $this->cache->sizeGroupForRatioAndSize[$imageRatio] = array();    
            }
            $this->cache->sizeGroupForRatioAndSize[$imageRatio][$printSize] = $sizeBlock;
            return $sizeBlock; 
 
        }
           
    }
    
    public function getPrintPriceAndMountPriceForRatioAndSize($imageRatio, $printSize)
    {
        if ((array_key_exists($imageRatio, $this->cache->printPriceAndMountPriceForRatioAndSize)) && (array_key_exists($printSize, $this->cache->printPriceAndMountPriceForRatioAndSize[$imageRatio]))) 
        {
            return $this->cache->printPriceAndMountPriceForRatioAndSize[$imageRatio][$printSize];
        }
        else
        {
            $mountPrice = null;
            $printPrice = null;
            $sizeGroup = $this->getSizeGroupForRatioAndSize($imageRatio, $printSize);  
            $ret = new stdClass();
            $ret->mountPrice = $sizeGroup->mountPrice . '';
            $ret->printPrice = $sizeGroup->printPrice . '';;                
            if (!array_key_exists($imageRatio, $this->cache->printPriceAndMountPriceForRatioAndSize)) 
            {
               $this->cache->printPriceAndMountPriceForRatioAndSize[$imageRatio] = array();    
            }
            $this->cache->printPriceAndMountPriceForRatioAndSize[$imageRatio][$printSize] = $ret;
            return $ret ;    
       } 
    }
    
    public function getFramePriceMatrixForGivenRatioAndSize($imageRatio, $printSize)
    {
            
        if ((array_key_exists($imageRatio, $this->cache->framePriceMatrixForGivenRatioAndSize)) && (array_key_exists($printSize, $this->cache->framePriceMatrixForGivenRatioAndSize[$imageRatio]))) 
        {
            return $this->cache->framePriceMatrixForGivenRatioAndSize[$imageRatio][$printSize];
        }
        else
        {
            $sizeGroup = $this->getSizeGroupForRatioAndSize($imageRatio, $printSize);
            $framePricesObj = array();    
            foreach($sizeGroup->xpath('./framePrices/framePrice') as $framePrice)
            {
                $style = $framePrice->style . '';
                $framePricesObj[$style] = $framePrice->price . '';   
            }
            if (!array_key_exists($imageRatio, $this->cache->framePriceMatrixForGivenRatioAndSize )) 
            {
               $this->cache->framePriceMatrixForGivenRatioAndSize[$imageRatio] = array();    
            }
            $this->cache->framePriceMatrixForGivenRatioAndSize[$imageRatio][$printSize] = $framePricesObj;
            return $framePricesObj;
        
        }        
    }
    
    
    /**
     * @param $basket is object based on passed json of basket
     * @return assoc array:    
     * totalItems = 20.00
     * deliveryCharges = 5.00
     * grandTotal = 25.00
     *
     */
    public function calculateDeliveryAndTotals($basket, $calculateDelivery)
    {
    
        $basket = $basket->basket;
        $totalItems = 0;
        $deliveryCharges = 0;
        $grandTotal = 0; 
        if ($calculateDelivery)
        {
            
            $deliveryChargesNodeList = $this->pricingModel->xpath('./deliveryCharges');
            $deliveryCharges =  $deliveryChargesNodeList[0];
        }
        
        $perPrintItem = $deliveryCharges->perPrintItem . '';
        $perMountItem = $deliveryCharges->perMountItem . '';
        $perFrameItem = $deliveryCharges->perFrameItem . '';
        
        
        foreach($basket as $orderLine)
        {
            
            $totalItems = $totalItems + $orderLine->confirmed_total_price;
            $qty = $orderLine->qty;
            if ($calculateDelivery)
            {   
                $lineDelCost = $qty * $perPrintItem;
                if ($orderLine->mount_style !== null)
                {
                    $lineDelCost = $lineDelCost + ($qty * $perMountItem);
                } 
                if ($orderLine->frame_style !== null)
                {
                    $lineDelCost = $lineDelCost + ($qty * $perFrameItem);
                } 
                $deliveryCharges = $deliveryCharges + $lineDelCost;
            }
        }
        
        return array(
            'totalItems' => number_format((float) $totalItems, 2),
            'deliveryCharges' =>   number_format((float) $deliveryCharges, 2),
            'grandTotal' =>  number_format((float) ($deliveryCharges + $totalItems) , 2)
        );

    }

}  

?>