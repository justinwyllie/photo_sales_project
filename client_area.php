<?php

/**
 * Class ClientArea
 *
 * Proof Dimensions :
 * thumbs width = max long edge = 120px
 * main long edge = max long edge = 720 px.
 *
 *
 * TODO LIST
 * store the chosen proofs somewhere so that if user logs in again we can restore them
 * - cld we just seralise the SESSION data and put that in a file?
 *
 */


session_start();
$clientArea = new ClientArea;
$clientArea->controller();
$clientArea->run();


class ClientArea
{
    public $action;
    private $options;
    private $accounts;
    protected $template;
    private $adminEmail;


    public function __construct()
    {


        //path to the options config file. SHOULD be outside of your web root
        $this->optionsPath = "/var/www/vhosts/justinwylliephotography.com/client_area_options.xml";
        //path to the accounts config file. MUST be outside of your web root
        $this->accountsPath = "/var/www/vhosts/justinwylliephotography.com/client_area_accounts.xml";
        //path to the language strings file.
        $this->langPath = "/var/www/vhosts/justinwylliephotography.com/client_area_lang.xml";

        //path, absolute or relative to this script, to where the page template is stored on your system
         $this->template = "client_area_template.php";


        $this->containerPageUrl = $_SERVER['PHP_SELF'];

        $this->setOptions();
        $this->setAccounts();
        $this->setLang();
    }

    private function setUserOptions($user)
    {
        $options = simplexml_load_file($this->clientAreaDirectory . DIRECTORY_SEPARATOR . $user .
            DIRECTORY_SEPARATOR . "options.xml" );

        if ($options === false) {
            $this->destroySession();
            $this->terminateScript("Missing or broken user options file for " . $user);
        } else {

            $this->accounts[$user]["proofs_on"] = (bool) (int) $options->proofsOn;
            $this->accounts[$user]["prints_on"] = (bool) (int) $options->printsOn;
            $this->accounts[$user]["customProofsMessage"] = $options->customProofsMessage . "";
            $this->accounts[$user]["thumbsPerPage"] = (int) $options->thumbsPerPage ;
        }
    }

    private function setAccounts()
    {
        $client_area_accounts = simplexml_load_file($this->accountsPath);

        if ($client_area_accounts === false) {
            $this->criticalError("Error in accounts file or file does not exist");
        }

        $accounts = array();
        foreach($client_area_accounts->account as $account) {
            $username = $account["username"] . "";
            $accounts[$username]["password"] = $account->password . "";
            $accounts[$username]["human_name"] = $account->human_name . "";

        }

        $this->accounts = $accounts;

    }

    private function personaliseOptions()
    {


    }

    private function setLang()
    {
        $strings = simplexml_load_file($this->langPath);

        if ($strings === false) {
            $this->criticalError("Error in language file or file does not exist");
        }

        $langStrings = array();
        foreach ($strings->field as $field) {
            $fieldName = $field["name"] . "";
            $fieldValue = trim($field . "");
            $langStrings[$fieldName] = $fieldValue;
        }

        $this->langStrings = $langStrings;

    }



    private function setOptions()
    {
        $client_area = simplexml_load_file($this->optionsPath);

        if ($client_area === false) {
            $this->criticalError("Error in options file or file does not exist");
        }

        $systemOptions = $client_area->options->system;
        $displayOptions = $client_area->options->display;

        $this->clientAreaDirectory = $systemOptions->clientAreaDirectory . "";
        $this->clientAreaUrl = $systemOptions->clientAreaUrl . "";
        $this->jsUrl = $systemOptions->jsUrl . "";
        $this->cssUrl = $systemOptions->cssUrl . "";
        $this->adminEmail = $systemOptions->adminEmail . "";
        $this->appName = $systemOptions->appName . "";

        $options = array();
        $options["thumbsPerPage"] = (int) $displayOptions->thumbsPerPage;
        $options["proofsShowLabels"] = (bool) (int) $displayOptions->proofsShowLabels;
        $options["showNannyingMessageAboutMoreThanOnePage"] = (bool) (int)  $displayOptions->showNannyingMessageAboutMoreThanOnePage;

        $this->options = $options;
    }


    private function getOption($option)
    {
        return $this->options[$option];
    }

    private function criticalError($message)
    {
        echo "Sorry. A critical error has occurred. Please contact the site owner." .
            " Additional information may be available: " . $message;
        exit;
    }

    public function run()
    {

        if (isset($_SESSION["user"])) {
            $this->setUserOptions($_SESSION["user"]);
        }

        $content = call_user_func_array(array($this, $this->action), array());

        if (is_object($content) ) {
            $this->outputJson($content);
        } else {
            $this->outputHtmlPage($content);
        }


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
        } elseif (!isset($_SESSION["user"]) && (!empty($_POST["action"])) && (strpos($_POST["action"], "ajax") !== false)) {
            $this->redirectToLoginScreen();
        } elseif  (!isset($_SESSION["user"])  && ($_SERVER["REQUEST_METHOD"] === "POST")
            && (!empty($_POST["action"])) && ($_POST["action"] !== "login") ) {
            $this->loginMessage = $this->lang("sessionExpired");
            $this->action = "showLoginScreen";
        } else {
            $this->action = "terminateScript";
        }

    }



    private function redirectToLoginScreen()
    {
        $obj = new stdClass();
        $obj->redirect = true;
        $this->outputJson($obj);
    }

    //TODO this still can be called repeatedly?
    private function terminateScript($info = "")
    {
        if (!empty($_SESSION["user"])) {
            $msg = "User: " . $_SESSION["user"];
        } else {
            $msg = "No valid logged in user";
        }
        $this->caMail("Error on site", "The user received a critical error. " . $msg . " " . $info);

        if (isset($_POST["action"]) && (strpos($_POST["action"], "ajax" ) !== false) ){
            $this->outputJson500();
        } else {
            $this->outputHtmlPage('<span class="ca_error">' . $this->lang("criticalErrorMessage") . '</span>');
            exit;
        }


    }

    private function caMail($subject, $content)
    {
        return mail($this->adminEmail, $this->appName . ' ' . $subject, $content);
    }



    //TODO
    private function lang($field)
    {
        if (isset($this->langStrings[$field])) {
            return $this->langStrings[$field];
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
        $restoredProofs = $_POST['restoredProofs'];
        $restoredPagesVisited = $_POST['restoredPagesVisited'];

        if (!empty($this->accounts[$user]) && !empty($password) && ($this->accounts[$user]["password"] === $password)) {
            session_unset();

            $_SESSION["user"] = $user;
            $_SESSION["proofsPagesVisited"] = array();
            $_SESSION["proofsChosen"] = array();

            if (!empty($restoredProofs)) {
                $restoredProofsArray = json_decode($restoredProofs);
                if (is_array($restoredProofsArray)) {
                    $_SESSION["proofsChosen"] = $restoredProofsArray;
                }
            }

            if (!empty($restoredPagesVisited)) {
                $restoredPagesVisitedArray = json_decode($restoredPagesVisited);
                if (is_array($restoredPagesVisitedArray)) {

                    $_SESSION["proofsPagesVisited"] = $restoredPagesVisitedArray;
                }
            }

            return true;
        } else {
            return false;
        }

    }

    private function logout()
    {
        return $this->logoutAndShowLoginScreen();
    }

    private function destroySession()
    {
       session_unset();
       session_destroy();

    }

    private function sendProofs($additionalMessage)
    {
        $subject = $this->lang("adminSendProofsMessage");
        $message = $this->lang("user");
        $message = $message . " : " . $_SESSION["user"] . "\n\n";

        $proofString = implode("\n", $_SESSION["proofsChosen"]);
        $message.= $proofString;
        $message.= "\n\n";
        $message.= $this->lang("adminAdditionalMessage");
        $message.= $additionalMessage;

        return $this->caMail($subject, $message);

    }


    private function processProofs()
    {
        $additionalMessage = $_POST["processProofsMessage"];
        $ret = $this->sendProofs($additionalMessage);

        if ($ret) {
            $message = '<span>' . $this->lang("proofsSuccess") . '</span>';
        } else {
            $message = '<span class="ca_error">' . $this->lang("proofsFailure")  . '</span>';
        }

        $dataAttributes = array();
        $dataAttributes["confirm-logout-message"] = $this->lang("confirmLogoutMessage");
        $dataAttributes["ok-text"] = $this->lang("okText");
        $dataAttributes["cancel-text"] = $this->lang("cancelText");
        $dataAttributes["username"] = $_SESSION["user"];
        $dataAttributes["critical-error-message"] = $this->lang("criticalErrorMessage");
        $mainBar = $this->caProofsBar($dataAttributes, $this->lang("proofsTitle"));
        $subBar = $this->caSubBar("", false, false, null);

        $html = <<<EOF
            $mainBar
            $subBar
            <hr class="ca_clear">
            $message
             <form action="$this->containerPageUrl" method="post" id="ca_action_form">
                <input type="hidden" name="action" id="ca_action_field" value="processProofs">
             </form>

EOF;

        return $html;


    }

    private function processProofsConfirm()
    {

        $dataAttributes = array();
        $dataAttributes["confirm-logout-message"] = $this->lang("confirmLogoutMessage");
        $dataAttributes["ok-text"] = $this->lang("okText");
        $dataAttributes["cancel-text"] = $this->lang("cancelText");
        $dataAttributes["username"] = $_SESSION["user"];
        $dataAttributes["critical-error-message"] = $this->lang("criticalErrorMessage");
        $dataAttributes["last-page-visited-index"] = $this->noScriptInjection($_POST["index"]);
        $message = $this->lang("finaliseProofChoices") . " " . $this->lang("submit");
        $yourInstructions = $this->lang("yourInstructions");
        $submit = $this->lang("submit");
        $mainBar = $this->caProofsBar($dataAttributes, $message);
        $subBar = $this->caSubBar("", false, true, null);

        $html = <<<EOF
            $mainBar
             $subBar
            <hr class="ca_clear">
            <div class="ca_print_login ca_additional_instructions">
                 <form action="$this->containerPageUrl" method="post" id="ca_action_form">
                    <input type="hidden" name="action" id="ca_action_field" value="processProofs">
                    <input type="hidden" name="index" id="ca_index_field" value="">
                    <div class="ca_label">$yourInstructions</div>
                    <textarea class="ca_proofs_box" name="processProofsMessage" id="processProofsMessage"></textarea>
                    <button>$submit</button>
                 </form>
            </div>

EOF;

        return $html;

    }

    private function showProofsScreen()
    {

        if (isset($_POST['index']))
        {
            $startIndex = $this->noScriptInjection($_POST['index']);
        }
        else
        {
            $startIndex = 0;
        }


        $user = $_SESSION["user"];
        $account = $this->accounts[$user];

        $customProofsMessage = $account["customProofsMessage"];
        $proofsMessage = $this->lang("proofsMessage");
        $thumb = $this->lang("thumb");

        $thumbsDir = $this->clientAreaDirectory . DIRECTORY_SEPARATOR . $_SESSION['user'] .
            DIRECTORY_SEPARATOR . "proofs" . DIRECTORY_SEPARATOR . "thumbs";
        $mainDir = $this->clientAreaDirectory . DIRECTORY_SEPARATOR . $_SESSION['user'] .
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
        $maxImageHeightInThisPageSet = $this->getMaxHeight($thumbsDir, $thumbsForThisPage);
        if ($maxImageHeightInThisPageSet === 0) {
            $picStyle = "";
        } else {
            $picHolderHeight = $maxImageHeightInThisPageSet + 20;
            $picStyle = ' style="height:' . $picHolderHeight . 'px;"';
        }

        foreach ($thumbsForThisPage as $file) {

            if ((isset($_SESSION["proofsChosen"])) && in_array($file, $_SESSION["proofsChosen"])) {
                $checked = ' checked="checked" ';
            } else {
                $checked = '';
            }

            if ($this->options["proofsShowLabels"]) {
                $label = '<span class="ca_label">' . $file . '</span>';
            } else {
                $label = "";
            }

            //TODO - in a loop? or at least we should cache the results
            $imageDimensions = $this->getImageDimensions($mainDir . $file);


            $file = str_replace(".JPG", ".jpg", $file); //TODO hack to fix Nascimento
            $filePath = $this->clientAreaUrl . '/' . $user . "/proofs/thumbs/" . $file;
            $fileHtml = '<div class="ca_thumb_pic"'. $picStyle . '><img' .
                ' data-image-width="' . $imageDimensions["width"] . '" ' .
                'src="' . $filePath . '" alt="' . $thumb  . '"><input class="ca_proof_checkbox_event" type="checkbox" value="' .
                  $file . '"' . $checked . ' >' . $label . '</div>';
            $pageThumbsHtml.=  $fileHtml;
        }

        $urlForMains = $this->clientAreaUrl . '/' . $user . "/proofs/main/";

        if ($this->getOption("proofsShowLabels")) {
            $labelsOption = "on";
        } else {
        $labelsOption = "off";
        }

        $proofsChosenCount = count($_SESSION["proofsChosen"]);

        $message = "$proofsMessage $customProofsMessage $extraMessage";
        $dataAttributes = array();

        $dataAttributes["url-for-mains"] = $urlForMains;
        $dataAttributes["labels-option"] = $labelsOption;
        $dataAttributes["check-all-message"] = $this->lang("checkAllMessage");
        $dataAttributes["all-pages-visited"] = $allPagesVisited;
        $dataAttributes["confirm-logout-message"] = $this->lang("confirmLogoutMessage");
        $dataAttributes["ok-text"] = $this->lang("okText");
        $dataAttributes["cancel-text"] = $this->lang("cancelText");
        $dataAttributes["username"] = $_SESSION["user"];
        $dataAttributes["critical-error-message"] = $this->lang("criticalErrorMessage");
        $mainBar = $this->caProofsBar($dataAttributes, $message);
        $subBar = $this->caSubBar($pageHtml, true, false, $proofsChosenCount);

        $html = <<<EOF

             <form action="$this->containerPageUrl" method="post" id="ca_action_form">
                <input type="hidden" name="action" id="ca_action_field" value="showProofsScreen">
                <input type="hidden" name="index" id="ca_index_field" value="0">
             </form>

             $mainBar
             $subBar

            <hr class="ca_clear">
            <div class="ca_proofs_thumbs">
                $pageThumbsHtml
            </div>

EOF;
        return $html;


    }

    private function caProofsBar($dataAttributes, $message)
    {
        $attributes = "";
        foreach ($dataAttributes as $key => $value) {
            $attributes = $attributes . ' data-' . $key . '="' . $value . '"';
        }
        $html = <<<EOT
                <div class="ca_proofs_bar"
                $attributes
                >
                <span class="ca_message_area">$message</span>
            </div>
EOT;

        return $html;
    }

    private function caSubBar($pageHtml, $needsDoneButton, $needsCancelButton, $proofsChosenCount)
    {


        $done = $this->lang("done");
        $logout = $this->lang("logout");
        $cancelText = $this->lang("cancelText");

        if ($needsDoneButton) {
            $doneButton = '<button class="ca_proof_button ca_proof_event">' . $done . '</button>';
        } else {
            $doneButton = '';
        }

        if ($needsCancelButton) {
            $cancelButton = '<button class="ca_proof_button ca_proof_cancel_event">' . $cancelText . '</button>';
        } else {
            $cancelButton = '';
        }

        if (!is_null($proofsChosenCount)) {
            $chosen = $this->lang("chosen");
            $proofsChosenText = '<span class="ca_counter_label">' . $chosen . '</span>' .
                '<span class="ca_counter">' . $proofsChosenCount . '</span>';
        } else {
            $proofsChosenText = "";
        }

            $html = <<<EOT

              <div class="ca_sub_bar">
                <div class="ca_pagination_box">
                  $pageHtml
                </div>
                <button class="ca_logout_button ca_logout_confirm_event">$logout</button>
                     $doneButton $cancelButton
                <span class="ca_counter_box">

                    $proofsChosenText
                </span>
            </div>

EOT;
        return $html;
    }


    private function getMaxHeight($thumbsDir, $thumbsForThisPage)
    {

        $maxHeight = 0;

         foreach ($thumbsForThisPage as $thumb) {
            $dimensions = $this->getImageDimensions($thumbsDir . DIRECTORY_SEPARATOR . $thumb);
            if ((!empty($dimensions)) && ($dimensions["height"] > $maxHeight)) {
                $maxHeight = $dimensions["height"];
            }
        }

        return $maxHeight;


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
        return "Prints";
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

            <div class="ca_print_login">
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

        return $html;

    }

    private function confirmLogoutScreen()
    {

        $confirmLogoutMessage = $this->lang("confirmLogoutMessage");
        $confirmLogoutMessageYes = $this->lang("confirmLogoutMessageYes");
        $confirmLogoutMessageNo = $this->lang("confirmLogoutMessageNo");


        $html = <<<EOF
            <div class="ca_print_login">
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

        return $html;

    }

    private function logoutAndShowLoginScreen()
    {
        $this->destroySession();
        $ret = $this->showLoginScreen();
        return $ret;

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
            <div class="ca_print_login">
                <form action="$this->containerPageUrl" method="post" id="ca_action_form">
                    <input type="hidden" name="action" value="login">
                    <input type="hidden" name="restoredProofs" id="restoredProofs" value="">
                    <input type="hidden" name="restoredPagesVisited" id="restoredPagesVisited" value="">
                    <span class="ca_login_error">$loginMessage</span><br>
                    <span class="ca_your_print_label">$userName</span>
                    <input class="ca_your_print_field" type="text" name="login" id="ca_login_name">
                    <br class="clear ">
                    <span class="ca_your_print_label" >$password</span>
                    <input class="ca_your_print_field" type="password" name="password">
                    <br class="clear">
                   <!-- <span class="ca_your_print_label" >$likeToDo</span>
                    <select name="mode" class="ca_your_print_field">
                            <option value="0">$select</option>
                            <option value="proofs">$viewProofs</option>
                            <option value="proofs">$orderPrints</option>
                    </select>-->
                    <br class="clear">

                    <button class="ca_standard_button ca_login_button ca_login_button_event" id="login" type="button">$login</button>
                </form>
            </div>


EOF;
        return $html;



    }

    private function ajaxAddRemoveProofImage()
    {
        $fileRef = $_POST["fileRef"];
        $fileAction = $_POST["fileAction"];

        $result = new stdClass();
        $result->fileRef = $fileRef;

        if (!isset($_SESSION["proofsChosen"])) {
            $_SESSION["proofsChosen"] = array(); //here
        }

        if ($fileAction === "add") {
            if (!in_array($fileRef, $_SESSION["proofsChosen"])) {
                $_SESSION["proofsChosen"][] =   $fileRef;
            }
            $result->checkboxOn = true;
        } elseif ($fileAction === "remove") {
            $keyToRemove = array_search($fileRef, $_SESSION["proofsChosen"]);
            if ($keyToRemove !== false) {
                unset($_SESSION["proofsChosen"][$keyToRemove]);
            }
            $result->checkboxOn = false;
        }

        $result->numberOfProofs = count($_SESSION["proofsChosen"]);

        return $result;
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
                $class = " ca_highlighted_pagination  ";
            }
            else
            {
                $class = "";
            }
            $output.= '<span data-index="' . $index . '" class="ca_page_number_event  ca_proofs_page' . $class .
                '">' . $pageNumber . '</span>';

        }

        $output.= '</div>';
        return $output;

    }

    private function outputJson500() {
        header("Content-type: application/json");
        header("HTTP/1.1 500 Internal Server Error");
        exit();
    }

    private function outputJson($output) {
        header("Content-type: application/json");
        echo json_encode($output, JSON_FORCE_OBJECT);
        exit();
    }

    private function outputHtmlPage($content) {

        //build header
        $headerContent = <<<EOT
        <script src="$this->jsUrl"></script>
        <link rel="stylesheet" href="$this->cssUrl" type="text/css">

EOT;

        $wrappedContent = <<<EOT
            <div id="ca_content_area">
           $content
            </div>
            <br class="ca_clear">
EOT;


        $template = new TemplateEngine;
        $template->setText();
        $template->setVars(
            array(
                "CLIENTAREAHEAD" => $headerContent,
                "CLIENTAREABODY" => $wrappedContent,

            )
        );

        echo $template->getText();
        exit;


    }

    private function microtime_float()
    {
        list($usec, $sec) = explode(" ", microtime());
        return ((float)$usec + (float)$sec);
    }


    private function noScriptInjection($data)
    {
        return htmlentities($data);
    }

}


class clientAreaPrints
{



}

class TemplateEngine extends ClientArea
{


    public function __construct()
    {
        parent::__construct();
    }

    public function setText()
    {
        ob_start();
        include($this->template);
        $this->text = ob_get_contents();
        ob_end_clean();

    }

    //TODO improve this is temporary
    public function setVars($vars)
    {
        foreach ($vars as $key => $replacement) {

            $this->text = str_replace("##" . $key . "##", $replacement, $this->text);
        }
    }

    public function getText()
    {
        return $this->text;
    }

}





?>