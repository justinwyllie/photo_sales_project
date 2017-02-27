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
                    color: 'darkGreen';
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