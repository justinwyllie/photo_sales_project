<?php

include('ca_pricing_calculator.php');
$tests = 0;
$errStr = '';
$successStr = '';

//given client_area_pricing_sample
$pricingCalculator = new ClientAreaPricingCalculator('/var/www/vhosts/mms-oxford.com/jwp_client_area_files/client_area_pricing_sample.xml');
$tests++;
$printAndMountPrice = $pricingCalculator->getPrintPriceAndMountPriceForRatioAndSize('1.50', '9x6');
if ($printAndMountPrice->printPrice  != '3.00')
{
    $errStr.= 'printPrice not got correctly on line ' . __LINE__ . "<br>";
}
else
{
    $successStr.= "printPrice got correctly" . "<br>";  
}
if ($printAndMountPrice->mountPrice  != '5.00')
{
    $errStr.= 'mountPrice not got correctly on line ' . __LINE__ . "<br>";
}
else
{
    $successStr.= "mountPrice got correctly" . "<br>";    
}

$tests++;
$printAndMountPrice = $pricingCalculator->getPrintPriceAndMountPriceForRatioAndSize('1.70', '16x9');
if ($printAndMountPrice->printPrice  != '4.50')
{
    $errStr.= 'printPrice not got correctly on line ' . __LINE__ . "<br>";
}
else
{
    $successStr.= "printPrice got correctly" . "<br>";  
}
if ($printAndMountPrice->mountPrice  != '8.00')
{
    $errStr.= 'mountPrice not got correctly on line ' . __LINE__ . "<br>";
}
else
{
    $successStr.= "mountPrice got correctly" . "<br>";    
}
$framePriceMatrix = $pricingCalculator->getFramePriceMatrixForGivenRatioAndSize('1.50', '9x6');
if ($framePriceMatrix['A'] != '20.00')
{
    $errStr.= 'framePriceMatrix not got correctly on line ' . __LINE__ . "<br>";
}
else
{
    $successStr.= "framePriceMatrix got correctly" . "<br>";  
}

$tests++;
$basketStr = <<<EOT
{"basket":[{"id":1,"image_ref":"image-007.jpg","image_ratio":"1.50","print_size":"9x6","mount_style":null,"frame_style":null,"frame_display_name":null,"print_price":"3.00","mount_price":0,"frame_price":0,"qty":1,"total_price":"3.00","confirmed_total_price": "3.00", "edit_mode":"edit","path":"\/client_area_image_provider.php?mode=prints&size=thumbs&file=image-007.jpg"},{"id":2,"image_ref":"image-007.jpg","image_ratio":"1.50","print_size":"9x6","mount_style":null,"frame_style":null,"frame_display_name":null,"print_price":"3.00","mount_price":0,"frame_price":0,"qty":"4","total_price":"12.00","confirmed_total_price": "12.00", "edit_mode":"edit","path":"\/client_area_image_provider.php?mode=prints&size=thumbs&file=image-007.jpg"}],"deliveryAndTotalShownToCustomer":{"clientName":"","address1":"","address2":"","city":"","zip":"","country":"","deliveryCharges":"2.50","totalItems":"15.00","grandTotal":"17.50","address_type":"address_on_file"},"calculatedDeliveryAndTotal":"TODO","ipnTrackId":"6fb03cb65ebd5","txnId":"353252166A558115V"}
EOT;

$basket = json_decode($basketStr);

$confirmedTotals = $pricingCalculator->calculateDeliveryAndTotals($basket->basket, true);
if (($confirmedTotals['totalItems'] !== '15.00') || ($confirmedTotals['deliveryCharges'] !== '2.50') || ($confirmedTotals['grandTotal'] !== '17.50'))
{
    $errStr.= 'confirmed totals on basket not got correctly on line ' . __LINE__ . "<br>";
    $errStr.= "&nbsp;&nbsp;Got: " . print_r($confirmedTotals, true) . "<br>";
}
else
{
    $successStr.= "confirmed totals on basket got correctly" . "<br>";  
}



?>

<html>
    <head>
        <style type="text/css">
                body
                {
                    font-family:arial,helvetica;
                }
                .error
                {
                    color: #ff0000;
                }
                .success
                {
                    color: #006400;
                }
        </style>
        <title>tests</title>
    </head>
<body>
    <h1>Tests</h1>

    
    <?php    
      echo "$tests run";
    ?>
    <hr>
    <div class="error">
        <?php 
          echo $errStr;
        ?>
    </div>
    <div class="success">
        <?php 
          echo $successStr;
        ?>
    </div>
</body>
</html>