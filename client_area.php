<?php

/**
 * Class ClientArea
 *
 * Proof Dimensions :
 * thumbs width = max long edge = 120px
 * main long edge = max long edge = 720 px.
 *
 * TODO LIST
 * store the chosen proofs somewhere so that if user logs in again we can restore them
 * - cld we just seralise the SESSION data and put that in a file?
 *
 */

class ClientArea
{
    public $action;
    private $options;
    private $accounts;


    public function __construct()
    {


        $this->setOptions();
        $this->setAccounts();

        //the web page which includes this system
        $this->containerPageUrl = "account.php";
        //absolute path on your system to where the images are stored. Must be under web root. See Docs.
        $this->clientAreaDiretory = "/var/www/vhosts/justinwylliephotography.com/httpdocs/client_area";
        //url for the client area - url which matches above directory. Can the absolute or relative. See Docs.
        $this->clientAreaUrl = "/client_area";


    }

    //TODO XML
    private function setAccounts()
    {

        $accounts = array();
        $accounts['nascimento']["password"] = "test";
        $accounts['nascimento']["proofs_on"] = true;
        $accounts['nascimento']["prints_on"] = false;
        $accounts['nascimento']["human_name"] = "Decio";
        $accounts['nascimento']["customProofsMessage"] = "";

        $accounts['nascimento']["options"]["thumbsPerPage"] = 10;
        $this->accounts = $accounts;

    }

    private function personaliseOptions()
    {


    }

    //TODO XML
    private function setOptions()
    {
        $options = array();
        $options["thumbsPerPage"] = 20;
        $options["proofsShowLabels"] = true;
        $options["showNannyingMessageAboutMoreThanOnePage"] = false;

        $this->options = $options;
    }


    private function getOption($option)
    {
        return $this->options[$option];
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
        } elseif (isset($_SESSION["user"]) && ($_SERVER["REQUEST_METHOD"] === "GET") ) {
            $this->action = "confirmLogoutScreen";
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
        $fields["confirmLogoutMessage"] = "Proceeding will log you out. Your order will not be saved.";
        $fields["confirmLogoutMessageNo"] = "No. Don't log me out. I want to continue with my order";
        $fields["confirmLogoutMessageYes"] = "Yes. Please log me out.";
        $fields["chooseOption"] = "What do you want to do?";
        $fields["go"] = "Go";
        $fields["hello"] = "Hello";
        $fields["proofsMessage"] = "Your proofs are displayed here. Please select which ones you would like, and then press 'Done'.";
        $fields["done"] = "Done";
        $fields["chosen"] = "Chosen: ";
        $fields["checkAllMessage"] = "Please make sure you have reviewed all the pages before submitting your order";
        $fields["moreThanOnePageMessage"] = "Please be sure to check all the pages";
        $fields["logout"] = "Log Out";
        $fields["confirmLogoutMessage"] = "Are you sure? Your current selection will not be saved.";
        $fields["okText"] = "OK";
        $fields["cancelText"] = "Cancel";



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
            $_SESSION["proofsPagesVisited"] = array();
            return true;
        } else {
            return false;
        }

    }

    private function logout()
    {
        $this->logoutAndShowLoginScreen();


    }

    private function clearSession()
    {
        unset($_SESSION['user']);
        unset( $_SESSION["proofsPagesVisited"]);
        //TODO make sure we clear all fields

    }

    private function processProofs()
    {
        echo "hi";
        exut;
    }

    private function showProofsScreen()
    {

        if (isset($_POST['index']))
        {
            $startIndex = $_POST['index'];
        }
        else
        {
            $startIndex = 0;
        }

        $user = $_SESSION["user"];
        $account = $this->accounts[$user];
        $customProofsMessage = $account["customProofsMessage"];

        $proofsMessage = $this->lang("proofsMessage");
        $chosen = $this->lang("chosen");
        $done = $this->lang("done");
        $logout = $this->lang("logout");
        $checkAllMessage = $this->lang("checkAllMessage");
        $confirmLogoutMessage = $this->lang("confirmLogoutMessage");
        $okText = $this->lang("okText");
        $cancelText = $this->lang("cancelText");



        $thumbsDir = $this->clientAreaDiretory . DIRECTORY_SEPARATOR . $_SESSION['user'] .
            DIRECTORY_SEPARATOR . "proofs" . DIRECTORY_SEPARATOR . "thumbs";
        $mainDir = $this->clientAreaDiretory . DIRECTORY_SEPARATOR . $_SESSION['user'] .
            DIRECTORY_SEPARATOR . "proofs" . DIRECTORY_SEPARATOR . "main" . DIRECTORY_SEPARATOR;
        $files = scandir($thumbsDir);
        $thumbs=array();
        foreach ($files as $file)
        {
            if ( ($file !== ".")  && ($file !== "..") && (is_file($thumbsDir . DIRECTORY_SEPARATOR . $file)) )
            {
                $thumbs[] = $file;
            }
        }


        $thumbsForThisPage = array_slice($thumbs, $startIndex, $this->options["thumbsPerPage"]);

        if (($thumbsForThisPage < $thumbs) && $this->getOption("showNannyingMessageAboutMoreThanOnePage")) {
            $extraMessage = '<br><span>' . $this->lang("moreThanOnePageMessage") . '</span>';
        } else {
            $extraMessage = "";
        }

        $numberOfThumbs = $this->getImageCount($thumbsDir);
        $pageHtml = $this->pageHtml($startIndex, $this->options["thumbsPerPage"], $numberOfThumbs);

        //this little block is about finding out if the user has visited all pages
        //so we can warn them if they try to click 'Done' but have not visited all pages
        if (!in_array($startIndex, $_SESSION["proofsPagesVisited"])) {
            $_SESSION["proofsPagesVisited"][] = $startIndex;
        }

        $pageIndexes = $this->getPageIndexes($this->options["thumbsPerPage"], $numberOfThumbs);
        $notVisited = array_diff($pageIndexes, $_SESSION["proofsPagesVisited"] );
        if (empty($notVisited)) {
            $allPagesVisited = "yes";
        } else {
            $allPagesVisited = "no";
        }

        $pageThumbsHtml = "";

        foreach ($thumbsForThisPage as $file) {

            if ($this->options["proofsShowLabels"]) {
                $label = '<span class="ca_label">' . $file . '</span>';
            } else {
                $label = "";
            }

            //TODO - in a loop?
            $imageDimensions = $this->getImageDimensions($mainDir . $file);

            $file = str_replace(".JPG", ".jpg", $file); //TODO hack to fix Nascimento
            $filePath = $this->clientAreaUrl . '/' . $user . "/proofs/thumbs/" . $file;
            $fileHtml = '<div class="ca_thumb_pic"><img' .
                ' data-image-width="' . $imageDimensions["width"] . '" ' .
                'src="' . $filePath . '"><br><input type="checkbox" value="' .
                  $file . '">' . $label . '</div>';
            $pageThumbsHtml.=  $fileHtml;
        }

        $urlForMains = $this->clientAreaUrl . '/' . $user . "/proofs/main/";

        if ($this->getOption("proofsShowLabels")) {
            $labelsOption = "on";
        } else {
        $labelsOption = "off";
        }

        $html = <<<EOF

             <form action="$this->containerPageUrl" method="post" id="ca_action_form">
                <input type="hidden" name="action" id="ca_action_field" value="showProofsScreen">
                <input type="hidden" name="index" id="ca_index_field" value="0">
             </form>

            <div class="ca_proofs_bar" data-url-for-mains="$urlForMains"
                data-labels-option="$labelsOption"
                data-check-all-message="$checkAllMessage"
                data-all-pages-visited="$allPagesVisited"
                data-confirm-logout-message="$confirmLogoutMessage"
                data-ok-text="$okText"
                data-cancel-text="$cancelText"
                >
                <span class="ca_message_area">$proofsMessage $customProofsMessage $extraMessage</span>

            </div>

            <div class="ca_sub_bar">
                <span class="ca_pagination_box">
                  $pageHtml
                </span>
                <button class="ca_logout_button ca_logout_confirm_event">$logout</button>
                <button class="ca_proof_button ca_proof_event">$done</button>
                <span class="ca_counter_box">
                    <span class="ca_counter_label">$chosen</span>
                    <span class="ca_counter">0</span>
                </span>
            </div>


            <hr class="ca_clear">
            <div class="ca_proofs_thumbs">
                $pageThumbsHtml
            </div>

EOF;
        echo $html;
        exit;

    }

    private function getImageDimensions($file)
    {
        $dimensions = getimagesize($file);

        $result = array();
        if ($dimensions !== false) {
            $result["width"] = $dimensions[0];
            $result["height"] = $dimensions[1];
        }

        return $result;

    }
    private function showPrintsScreen()
    {
        echo "Prints";
    }

    private function showChoiceScreen()
    {

        $chooseOption = $this->lang("chooseOption");
        $viewProofs = $this->lang("viewProofs");
        $orderPrints = $this->lang("orderPrints");
        $go = $this->lang("go");
        $hello = $this->lang("hello");

        $user = $_SESSION["user"];
        $account = $this->accounts[$user];
        $name = $account["human_name"];

        //TODO check the dirs exist as well
        if ($account["proofs_on"]) {
            $proofs_on ="";
        } else {
            $proofs_on = "disabled";
        }

        if ($account["prints_on"]) {
            $prints_on ="";
        } else {
            $prints_on = "disabled";
        }

        $html = <<<EOF

            <div class="print_login">
                $hello <span class="ca_human_name">$name</span>. $chooseOption
            <form action="$this->containerPageUrl" method="post" id="ca_action_form">
                <input type="hidden" name="action" id="ca_action_field" value="">
                <select id="ca_activity_choice" class="ca_select_box">
                    <option value="0">Select..</option>
                    <option value="showProofsScreen" $proofs_on>$viewProofs</option>
                    <option value="showPrintsScreen" $prints_on>$orderPrints</option>
                </select><br>
                <button class="ca_large_button ca_choose_activity_event"
                    type="button">$go</button>
            </form>
        </div>
EOF;

        echo $html;
        exit;

    }

    private function confirmLogoutScreen()
    {

        $confirmLogoutMessage = $this->lang("confirmLogoutMessage");
        $confirmLogoutMessageYes = $this->lang("confirmLogoutMessageYes");
        $confirmLogoutMessageNo = $this->lang("confirmLogoutMessageNo");


        $html = <<<EOF
            <div class="print_login">
                $confirmLogoutMessage
            <form action="$this->containerPageUrl" method="post" id="ca_action_form">
                <input type="hidden" name="action" id="ca_action_field" value="">
                <button class="ca_large_button ca_confirm_switch_event" data-call="logoutAndShowLoginScreen"
                    type="button">$confirmLogoutMessageYes</button>
                <button class="ca_large_button ca_confirm_switch_event" data-call="showChoiceScreen"
                    type="button">$confirmLogoutMessageNo</button>
            </form>
        </div>


EOF;

        echo $html;
        exit;
    }

    private function logoutAndShowLoginScreen()
    {
        $this->clearSession();
        $this->showLoginScreen();
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
            <form action="$this->containerPageUrl" method="post">
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

                <button class="ca_standard_button ca_login_button" id="login" !type="button">$login</button>
            </form>
        </div>


EOF;
        echo $html;
        exit;


    }


    //TODO move these old pos functions to some utility library
    private function getImageCount($dir)
    {
        $haveIterator = class_exists("FilesystemIteratorx");
        if ($haveIterator)
        {
            $fi = new FilesystemIterator($dir, FilesystemIterator::SKIP_DOTS);
            $i = 0;
            foreach  ($fi as $fileOrFolder)
            {
                if ($fileOrFolder->isFile())
                {
                    $i++;
                }
            }
            return $i;

        }
        else
        {
            $i = 0;
            $files = scandir($dir);
            foreach ($files as $file)
            {
                if ( ($file != ".")  && ($file != "..") && (is_file($dir . DIRECTORY_SEPARATOR . $file))  )
                {
                    $i++;
                }
            }
            return $i;
        }

    }

    private function getPageIndexes($thumbsPerPage, $numberOfThumbs)
    {
        $numberOfPages = ceil($numberOfThumbs / $thumbsPerPage);

        $index = 0;
        $pages = array();

        for ($i= 1; $i <= $numberOfPages; $i++)
        {
            $pages[$i] =  $index;
            $index = $index + $thumbsPerPage;
        }

        return $pages;
    }

    private function pageHtml($startIndex, $thumbsPerPage, $numberOfThumbs)
    {
        $output = '<div class="ca_page_info">';

        $pages = $this->getPageIndexes($thumbsPerPage, $numberOfThumbs);

        $currentPage = array_search($startIndex, $pages);

        $length = 11;
        $offset = $currentPage - 6;
        if ($offset < 0) {
            $offset = 0;
        }

        $pagesToShow = array_slice($pages, $offset, $length, $preserve_keys = true);

        foreach($pagesToShow as $pageNumber => $index)
        {
            if ($index == $startIndex) {
                $class = " ca_highlighted_pagination ";
            }
            else
            {
                $class = "";
            }
            $output.= '<span data-index="' . $index . '" class="ca_page_number ' . $class .
                '">' . $pageNumber . '</span>';

        }


        $output.= '</div>';
        return $output;

    }

}




?>