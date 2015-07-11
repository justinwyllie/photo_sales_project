<?php
session_start();
include("client_area.php");
$clientArea = new ClientArea;
$clientArea->controller();
?>
<!doctype html>
<html lang="en-gb" >

    <head>
        <?php
        include('header.php');
        ?>

        <script src="/lightbox2/js/lightbox-2.6.min.js"></script>
        <link rel="stylesheet" href="client_area.css" type="text/css">
        <link href="/lightbox2/css/lightbox.css" rel="stylesheet" />

        <script>

            jQuery(function() {


            });

        </script>

    </head>


    <body>

        <div id="contentHolder">

            <h1>Justin Wyllie Photography</h1>

            <div class="topbar">
                <div class="sectionHead pagename">
                    Wedding Prices
                </div>

                <?php
                include("menu.php");
                ?>

            </div><!-- end top bar -->



            <?php
                    $clientArea->run();
            ?>


        </div>

    </body>

</html>

