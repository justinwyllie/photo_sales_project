<?php

session_start();

//check user is logged in
if (!empty($_SESSION["user"])) {
    $user = $_SESSION["user"];
} else {
    //TODO handle this better
    die();
}

$req = $_SERVER["PHP_SELF"];
$requestMethod = $_SERVER["REQUEST_METHOD"];

function getBasket() 
{
    $result = new stdClass();
    $basket = $_SESSION["basket"];
    //dummy data
    $basket[] = array("id"=>1, "image" => "image001.jpg", "print_price"=>"5.00", "size"=>"9x6", "mount_price"=>"15.00", "mount_style"=>"White", "frame_style"=>"A", "frame_price"=>"25.00", "qty"=>"1");
    $basket[] = array("id"=>3, "image" => "image001.jpg", "print_price"=>"5.00", "size"=>"9x6", "mount_price"=>"15.00", "mount_style"=>"White", "frame_style"=>"C", "frame_price"=>"50.00", "qty"=>"2");
    $basket[] = array("id"=>2, "image" => "image002.jpg", "size"=>"9x6", "print_price"=>"5.00");
        
    return $basket;

}

function outputJson($output) {
    header("Content-type: application/json");
    echo json_encode($output);
    exit();
}


$output = getBasket() ;
outputJson($output);

?>