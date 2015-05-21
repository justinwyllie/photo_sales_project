<?php



$action = $_GET['action'] ;

if ($action == "clear-cache")
{
    //TODO
    exit;
}

session_start();


if ( (! isset($_SESSION['userId'])) || (! isset($_SESSION['thumbLongestSide'])) 
    || (! isset($_SESSION['path'])) )  {
    exit();
}




$file = $_GET['file'];


$posImage = new posImage($file, $_SESSION['path'], $_SESSION['thumbLongestSide'] );
if ($posImage->errorCondition) {     
    exit();
}
else
{       
    $posImage->$action();

}




class posImage
{

    private $fileName;
    private $directory;
    private $thumbsDirectory;
    private $minisDirectory;
    private $mimeType;
    private $thumbLongestSide;
    private $pathToFile;
    private $pathToThumb;
    private $pathToMini;
    private $width;
    private $height;
    public  $errorCondition = false;

    public function __construct($fileName, $directory, $thumbLongestSide) {

        $this->fileName = $fileName;
        $this->directory = $directory;
        $this->pathToFile = $directory .  DIRECTORY_SEPARATOR   . $fileName;
        $this->thumbsDirectory = $directory . DIRECTORY_SEPARATOR . 'thumbs';
        $this->minisDirectory = $directory . DIRECTORY_SEPARATOR . 'minis';
        $this->pathToThumb = $this->thumbsDirectory . DIRECTORY_SEPARATOR . $fileName;
        $this->pathToMini = $this->minisDirectory . DIRECTORY_SEPARATOR . $fileName;
        $this->midDirectory = $directory . DIRECTORY_SEPARATOR . 'mid';
        $this->pathToMid = $this->midDirectory . DIRECTORY_SEPARATOR . $fileName;
        $this->thumbLongestSide = $thumbLongestSide;


        if (is_readable($this->pathToFile))
        {
            $details = getimagesize($this->pathToFile);
              

            if ($details)
            {
                $this->width = $details[0];
                $this->height = $details[1];
                $type = $details[2];
                $this->mimeType = image_type_to_mime_type($type)  ;         
                
                
            }
            else
            {           
                $this->errorCondition = true;
            }
         }
        else
        {                   
            $this->errorCondition = true;

        }

        if ( file_exists($this->minisDirectory)) 
        {
            $w = is_writable($this->minisDirectory); 
            $ex = is_executable($this->minisDirectory); 
            if (!($w && $ex))
            {
                $modeSet = chmod($this->minisDirectory, 0700 );  //u=rwx   
                if ($modeSet === false)
                {
                    error_log("PRINT ORDERING SYSTEM MESSAGE: Could not set correct permissions on '".$this->minisDirectory .
                         "' You may need to change the owner to the same as the web server is running as or try deleting the /thumbs directory and starting again   " )    ;
                }
            }

        }
        else
        {
            mkdir($this->minisDirectory, 0700)  ;    //u=rwx
        }

        if ( file_exists($this->thumbsDirectory)) 
        {
            $w = is_writable($this->thumbsDirectory); 
            $ex = is_executable($this->thumbsDirectory); 
            if (!($w && $ex))
            {
                $modeSet = chmod($this->thumbsDirectory, 0700 );  //u=rwx   
                if ($modeSet === false)
                {
                    error_log("PRINT ORDERING SYSTEM MESSAGE: Could not set correct permissions on '".$this->thumbsDirectory .
                         "' You may need to change the owner to the same as the web server is running as or try deleting the /thumbs directory and starting again   " )    ;
                }
            }

        }
        else
        {
            mkdir($this->thumbsDirectory, 0700)  ;    //u=rwx
        }

        if ( file_exists($this->midDirectory)) 
        {
            $w = is_writable($this->midDirectory); 
            $ex = is_executable($this->midDirectory); 
            if (!($w && $ex))
            {
                $modeSet = chmod($this->midDirectory, 0700 );  //u=rwx   
                if ($modeSet === false)
                {
                    error_log("PRINT ORDERING SYSTEM MESSAGE: Could not set correct permissions on '".$this->midDirectory .
                         "' You may need to change the owner to the same as the web server is running as or try deleting the /mid directory and starting again   " )    ;
                }
            }

        }
        else
        {
            mkdir($this->midDirectory, 0700)  ;    //u=rwx
        }


  




    }

    public function main()
    {

        $width = $_GET['width'];
        $height = $_GET['height'];

        $mainMaxWidth = (int) ($width * 0.95);
        $mainMaxHeight   = (int) ($height * 0.7);

        

        //TODO test this
        if (($this->width <= $mainMaxWidth) && ($this->height <=$mainMaxHeight)) {
            $this->sendFile($this->pathToFile)  ;
            exit();  
        }
                        
        if (  (is_readable($this->pathToMid)) && (time() < (filemtime($this->pathToMid) + (60*60))   )   )
        {                                  
            $this->sendFile($this->pathToMid) ;
            exit();             
              
        }

        $heightRatio =  $mainMaxHeight /  $this->height;
        $widthRatio =   $mainMaxWidth / $this->width;

        if ($heightRatio <= $widthRatio)
        {
              $ratio =  $heightRatio;
        }
        else
        {
            $ratio = $widthRatio;
        }


        $new_width = floor($this->width * $ratio);
        $new_height = floor($this->height * $ratio);

  
        $imageOutput = imagecreatetruecolor($new_width, $new_height);

        $imageSource = null;
    
        if ($this->mimeType == "image/jpeg" )
        {
            $imageSource = imagecreatefromjpeg($this->pathToFile);
            imagecopyresampled($imageOutput, $imageSource, 0, 0, 0, 0, $new_width, $new_height, $this->width, $this->height);
            header('Content-Type: image/jpeg');
            imagejpeg($imageOutput, null, 100);
         
        }
        else if ($this->mimeType == "image/png" )
        {
              $imageSource = imagecreatefrompng($this->pathToFile);
              imagecopyresampled($imageOutput, $imageSource, 0, 0, 0, 0, $new_width, $new_height, $this->width, $this->height);   
              header('Content-Type: image/png');  
              imagepng($imageOutput, null, 0);
        }  
    
        
        if (is_writeable($this->midDirectory) && is_executable($this->midDirectory) && (! is_null($imageSource)) )
        {

            if ($this->mimeType == "image/jpeg" )
            {                       
                imagejpeg($imageOutput, $this->pathToMid, 100);
             
            }
            else if ($this->mimeType == "image/png" )
            {
                imagepng($imageOutput, $this->pathToMid, 0);
            } 

        }


        imagedestroy($imageOutput);

    }
 


    public function thumb()
    {

        //TODO test this
        if (($this->width <= 100) && ($this->height <=100)) {
            $this->sendFile($this->pathToFile)  ;
            exit();  
        }
                        
        if (  (is_readable($this->pathToThumb)) && (time() < (filemtime($this->pathToThumb) + (60*60))   )   )
        {                                  
            $this->sendFile($this->pathToThumb) ;
            exit();    
              
        }

        if ($this->width > $this->height) {
          $new_width = 100;

          $new_height = (int) ((100  / $this->width) * $this->height);
        }
        else
        {
              $new_height = 100;
              $new_width = (int) ((100  / $this->height) * $this->width);
        }
  
        $imageOutput = imagecreatetruecolor($new_width, $new_height);

        $imageSource = null;
    
        if ($this->mimeType == "image/jpeg" )
        {
            $imageSource = imagecreatefromjpeg($this->pathToFile);
            imagecopyresampled($imageOutput, $imageSource, 0, 0, 0, 0, $new_width, $new_height, $this->width, $this->height);
            header('Content-Type: image/jpeg');
            imagejpeg($imageOutput, null, 100);
         
        }
        else if ($this->mimeType == "image/png" )
        {
              $imageSource = imagecreatefrompng($this->pathToFile);
              imagecopyresampled($imageOutput, $imageSource, 0, 0, 0, 0, $new_width, $new_height, $this->width, $this->height);   
              header('Content-Type: image/png');  
              imagepng($imageOutput, null, 0);
        }  
    
        
        if (is_writeable($this->thumbsDirectory) && is_executable($this->thumbsDirectory) && (! is_null($imageSource)) )
        {

            if ($this->mimeType == "image/jpeg" )
            {                       
                imagejpeg($imageOutput, $this->pathToThumb, 100);
             
            }
            else if ($this->mimeType == "image/png" )
            {
                imagepng($imageOutput, $this->pathToThumb, 0);
            } 

        }


        imagedestroy($imageOutput);

    }

    public function mini()
    {

        //TODO test this
        if (($this->width <= 50) && ($this->height <=50)) {
            $this->sendFile($this->pathToFile)  ;
            exit();  
        }
                        
        if (  (is_readable($this->pathToMini)) && (time() < (filemtime($this->pathToMini) + (60*60))   )   )
        {                                  
            $this->sendFile($this->pathToMini) ;
            exit();    
              
        }

        if ($this->width > $this->height) {
          $new_width = 50;

          $new_height = (int) ((50  / $this->width) * $this->height);
        }
        else
        {
              $new_height = 50;
              $new_width = (int) ((50  / $this->height) * $this->width);
        }
  
        $imageOutput = imagecreatetruecolor($new_width, $new_height);

        $imageSource = null;
    
        if ($this->mimeType == "image/jpeg" )
        {
            $imageSource = imagecreatefromjpeg($this->pathToFile);
            imagecopyresampled($imageOutput, $imageSource, 0, 0, 0, 0, $new_width, $new_height, $this->width, $this->height);
            header('Content-Type: image/jpeg');
            imagejpeg($imageOutput, null, 100);
         
        }
        else if ($this->mimeType == "image/png" )
        {
              $imageSource = imagecreatefrompng($this->pathToFile);
              imagecopyresampled($imageOutput, $imageSource, 0, 0, 0, 0, $new_width, $new_height, $this->width, $this->height);   
              header('Content-Type: image/png');  
              imagepng($imageOutput, null, 0);
        }  
    
        
        if (is_writeable($this->minisDirectory) && is_executable($this->minisDirectory) && (! is_null($imageSource)) )
        {

            if ($this->mimeType == "image/jpeg" )
            {                       
                imagejpeg($imageOutput, $this->pathToMini, 100);
             
            }
            else if ($this->mimeType == "image/png" )
            {
                imagepng($imageOutput, $this->pathToMini, 0);
            } 

        }


        imagedestroy($imageOutput);

    }









    private function sendFile($file)
    {
        header("Content-type: ".$this->mimeType);      
        readfile($file);
        
    }




}


  

?>
