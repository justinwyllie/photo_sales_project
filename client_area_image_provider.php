<?php

session_start();

//check user is logged in
if (!empty($_SESSION["user"])) {
    $user = $_SESSION["user"];
} else {
    //TODO handle this better
    die();
}

$size = $_GET['size'];
$mode = $_GET['mode'];
$file = $_GET['file'];

$imageProvider = new imageProvider($size, $mode, $file, $user);
$imageProvider->printImage();

class imageProvider {

    //absolute path on your system to the client_area_files directory
    private $clientAreaDirectory = "/var/www/vhosts/justinwylliephotography.com/client_area_files";

    public function __construct($size, $mode, $file, $user) {
        $this->user = $user;
        $this->size = $size;
        $this->mode = $mode;
        $this->file = $file;
        $this->type = null;
        $this->imageType();
    }

    private function imageType() {
        if ((strpos(strtolower($this->file), 'jpg') !== false) or (strpos(strtolower($this->file), 'jpeg') !== false)) {
            $this->type = 'jpeg';
        } else if (strpos(strtolower($this->file), 'png') !== false) {
            $this->type = 'png';
        }

    }

    private function fail() {
        $im  = imagecreatetruecolor(150, 30);
        $bgc = imagecolorallocate($im, 255, 255, 255);
        $tc  = imagecolorallocate($im, 0, 0, 0);
        imagefilledrectangle($im, 0, 0, 150, 30, $bgc);
        imagestring($im, 1, 5, 5, 'Error loading ' . $this->file, $tc);
        header('Content-type: image/jpeg');
        imagejpeg($im);
        imagedestroy($im);
    }

    public function printImage() {

        if ($this->type === null) {
            $this->fail();
        }

        $fnc = 'imagecreatefrom' . $this->type;
        $filePath = $this->clientAreaDirectory . DIRECTORY_SEPARATOR . $this->user . DIRECTORY_SEPARATOR .
            $this->mode . DIRECTORY_SEPARATOR . $this->size . DIRECTORY_SEPARATOR . $this->file;
        $im = @$fnc($filePath);
        if (!$im) {
            $this->fail();
        }
        header('Content-Type: image/' . $this->type);
        $fnc2 = 'image' . $this->type;
        $fnc2($im);
        imagedestroy($im);
    }

}



?>