<?php
session_start();



class Client
{
    public $action;

    public function __construct()
    {

        //TODO XML
        $accounts = array();
        $accounts['nascimento']["password"] = "tw93b";
        $accounts['nascimento']["proofs"] = true;
        $accounts['nascimento']["prints"] = false;

        $this->accounts = $accounts;

        $this->dir = dir(__FILE__) . DIRECTORY_SEPARATOR . "client_area";

    }

    public function controller()
    {
        if (!isset($_SESSION["user"])) {
            $this->action = "showLoginScreen";
        } else {
            if (!isset($_GET["action"])) {
                $this->action = "showChoiceScreen";
            } else {
                $this->action = $_GET["action"];
            }

        }
    }

    public function run()
    {
        $this->action();
    }




    private function hasSession()
    {
        if ( empty($_SESSION["user"])){
            return false;
        } else {
            return true;
        }

    }

    private function doLogin()
    {
        $user = $_POST['login'];
        $password = $_POST['password'];

        if (empty($accounts[$user]) || empty($password) || ($accounts[$user]["password"] !== $password)) {
            if (isset($_SESSION['user'])) {
                unset($_SESSION['user']);
            }
            header('Location: http://justinwylliephotography.com/yourprints?login_failed');
            exit;
        } else {
            $_SESSION["user"] = $user;
        }

    }

    private  function showProofsScreen()
    {

        if (!$this->hasSession()) {
            $this->showLoginScreen();
            exit;
        }

        if (isset($_GET['page'])) {
            $page = (int)$_GET['page'];
        } else {
            $page = 0;
        }


        $dir = dirname(__FILE__);
        $files = scandir($dir . DIRECTORY_SEPARATOR . 'proofs' . DIRECTORY_SEPARATOR . $_SESSION['user']);

        var_dump($files);

    }

    private function showChoiceScreen()
    {

        if (!$this->hasSession()) {
            $this->showLoginScreen();
            exit;
        }

        //output a form with option to view proofs or order prints
        //bases offer on config above and directories
        echo "x";
    }


    private function showLoginScreen()
    {

    }

}

$client = new Client;
$client->controller();

?>
<!doctype html>
<html lang="en-gb" >
<head>
    <?php
    include('header.php');
    ?>

    <script src="/lightbox2/js/lightbox-2.6.min.js"></script>
    <link rel="stylesheet" href="pos.css" type="text/css">
    <link href="/lightbox2/css/lightbox.css" rel="stylesheet" />

    <script>

        jQuery(function() {

            jQuery("#login").click(function () {

                //jQuery(".login_error").show();

            })

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
            $client->run();
    ?>


</div>

</body>

</html>


<?php






?>