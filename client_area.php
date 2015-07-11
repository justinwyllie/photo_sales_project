<?php


class ClientArea
{
    public $action;


    public function __construct()
    {

        //TODO XML
        $accounts = array();
        $accounts['nascimento']["password"] = "test";
        $accounts['nascimento']["proofs"] = true;
        $accounts['nascimento']["prints"] = false;

        $this->accounts = $accounts;

        $this->clientDir = dirname(__FILE__) . DIRECTORY_SEPARATOR . "client_area";

    }

    public function run()
    {
        call_user_func_array(array($this, $this->action), array());
    }

    /**
     * Check that there is a session and an action
     * Act appropriately.
     *
     */
    public function controller()
    {

        if ((isset($_SESSION["user"])) && (!empty($_POST["action"])) && ($_POST["action"] !== "login")) {
            $this->action = $_POST["action"];
        } elseif ((isset($_SESSION["user"])) && (!empty($_POST["action"])) && ($_POST["action"] === "login")) {
            $this->setLogin();
        } elseif (isset($_SESSION["user"]) && (empty($_POST["action"])) ) {
            $this->action = "showLoginScreen";
        } elseif (!isset($_SESSION["user"])  && ($_SERVER["REQUEST_METHOD"] === "GET")  ) {
            $this->action = "showLoginScreen";
        } elseif (!isset($_SESSION["user"])  && ($_SERVER["REQUEST_METHOD"] === "POST")
                && (!empty($_POST["action"])) && ($_POST["action"] === "login") ) {
            $this->setLogin();
        } elseif  (!isset($_SESSION["user"])  && ($_SERVER["REQUEST_METHOD"] === "POST")
            && (!empty($_POST["action"])) && ($_POST["action"] !== "login") ) {
            $this->loginMessage = $this->lang("sessionExpired");
            $this->action = "showLoginScreen";
        } else {
            $this->action = "terminateScript";
        }
    }


    private function terminateScript()
    {
        //todo log this/alert me
        die();
    }



    //TODO
    private function lang($field)
    {

        $fields = array();
        $fields["loginError"] = "Error logging in. Possibly wrong username/password";
        $fields["sessionExpired"] = "Your session has expired. Please login again.";
        $fields["userName"] = "Your username:";

        $fields["password"] = "Password:";
        $fields["likeToDo"] = "what would you like to do?";
        $fields["select"] = "Select..";
        $fields["viewProofs"] = "View Proofs";
        $fields["orderPrints"] = "Order Prints";
        $fields["login"] = "Login";


        if (isset($fields[$field])) {
            return $fields[$field];
        } else {
            return "";
        }
    }


    private function setLogin()
    {
        $loginResult = $this->doLogin();
        if ($loginResult) {
            $this->action = "showChoiceScreen";
        } else {
            $this->loginMessage = $this->lang("loginError");
            $this->action = "showLoginScreen";
        }
    }

    private function doLogin()
    {
        $user = $_POST['login'];
        $password = $_POST['password'];

        //TODO if they relogin in durring a session what happens to the state of their order?
        //should we wipe it if they relog in? - probably yes to handle the unlikely event
        //that a second user is using the same browser instance.

        $this->clearSession();

        if (!empty($this->accounts[$user]) && !empty($password) && ($this->accounts[$user]["password"] === $password)) {
            $_SESSION["user"] = $user;
            return true;
        } else {
            return false;
        }

     }

    private function clearSession()
    {
        unset($_SESSION['user']);
        //TODO make sure we clear all fields

    }

    private function showProofsScreen()
    {


        if (isset($_GET['page'])) {
            $page = (int)$_GET['page'];
        } else {
            $page = 0;
        }


        $dir = dirname(__FILE__);
        $files = scandir($dir . DIRECTORY_SEPARATOR . 'proofs' . DIRECTORY_SEPARATOR . $_SESSION['user']);

        var_dump($files);
        exit;

    }

    private function showChoiceScreen()
    {



        echo "choice";
        exit;

    }


    private function showLoginScreen()
    {
        if (isset($this->loginMessage)) {
            $loginMessage = $this->loginMessage;
        } else {
            $loginMessage = "";
        }

        $userName = $this->lang("userName");

        $password = $this->lang("password");
        $likeToDo = $this->lang("likeToDo");
        $select = $this->lang("select");
        $viewProofs = $this->lang("viewProofs");
        $orderPrints = $this->lang("orderPrints");
        $login = $this->lang("login");


        $html = <<<EOF
            <div class="print_login">
            <form action="/account.php" method="post">
                <input type="hidden" name="action" value="login">
                <span class="login_error">$loginMessage</span><br>
                <span class="your_print_label">$userName</span>
                <input class="your_print_field" type="text" name="login">
                <br class="clear">
                <span class="your_print_label" >$password</span>
                <input class="your_print_field" type="password" name="password">
                <br class="clear">
               <!-- <span class="your_print_label" >$likeToDo</span>
                <select name="mode" class="your_print_field">
                        <option value="0">$select</option>
                        <option value="proofs">$viewProofs</option>
                        <option value="proofs">$orderPrints</option>
                </select>-->
                <br class="clear">

                <button class="login_button" id="login" !type="button">$login</button>
            </form>
        </div>


EOF;

        echo $html;
        exit;

    }



}




?>