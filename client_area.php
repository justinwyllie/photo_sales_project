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
$clientArea->controller();  //sets everything up  : sets the action to run
$clientArea->run();         //runs the determined action


class ClientArea
{
    public $action;
    private $options;
    private $accounts;
    protected $template;
    private $adminEmail;


    public function __construct()
    {   
        //absolute path on your system to the client_area_files directory
        $this->clientAreaDirectory = "/var/www/vhosts/mms-oxford.com/jwp_client_area_files";

        $this->optionsPath = $this->clientAreaDirectory . DIRECTORY_SEPARATOR . "client_area_options.xml";
        $this->accountsPath = $this->clientAreaDirectory . DIRECTORY_SEPARATOR . "client_area_accounts.xml";
        $this->langPath = $this->clientAreaDirectory . DIRECTORY_SEPARATOR . "client_area_lang.xml";
   
        $this->template = "client_area_template.php";
        #url path from the web root to this file. normally it will be placed in the web root
        $this->imageProvider = "/client_area_image_provider.php";

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
            if ($this->accounts[$user]["proofs_on"])
            {
                $this->accounts[$user]["customProofsMessage"] = $options->customProofsMessage . "";
            }
            if ($this->accounts[$user]["prints_on"])
            {
                $this->accounts[$user]["customPrintsMessage"] = $options->customPrintsessage . "";
            }
            
            $this->accounts[$user]["customPrintsMessage"] = $options->customPrintsMessage . "";
            $this->accounts[$user]["thumbsPerPage"] = (int) $options->thumbsPerPage ;
            
            $_SESSION["client_area_directory_path"]   = $this->clientAreaDirectory;
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
        $client_area_options = simplexml_load_file($this->optionsPath);

        if ($client_area_options === false) {
            $this->criticalError("Error in options file or file does not exist");
        }

        $systemOptions = $client_area_options->options->system;
        $displayOptions = $client_area_options->options->display;

        $this->jsUrl = $systemOptions->jsUrl . "";
        $this->appUrl = $systemOptions->appUrl . "";
        $this->jQueryUrl = $systemOptions->jQueryUrl . "";
        $this->underscoreUrl = $systemOptions->underscoreUrl . "";
        $this->backboneUrl = $systemOptions->backboneUrl . "";
        $this->fontAwesomeCSSJSUrl = $systemOptions->fontAwesomeCSSJSUrl . "";
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
     * Set the action which will be run (see method 'run')
     *
     */
    public function controller()
    {
        $reqAddress = $_SERVER["PHP_SELF"];
        
        //var_dump(isset($_SESSION["user"]), $_SERVER["REQUEST_METHOD"]);exit;

        if ((isset($_SESSION["user"])) && (!empty($_POST["action"])) && ($_POST["action"] !== "login")) {
            $this->action = $_POST["action"];
        } elseif ((isset($_SESSION["user"])) && (!empty($_POST["action"])) && ($_POST["action"] === "login")) {
            $this->setLogin();
        } elseif (isset($_SESSION["user"]) && ($_SERVER["REQUEST_METHOD"] === "GET") ) {
            $this->action = "confirmLogoutScreen";
        } elseif (!isset($_SESSION["user"])  && ($_SERVER["REQUEST_METHOD"] === "GET")  ) {
            //var_dump('here');exit;
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
        $restoredProofsPagesVisited = $_POST['restoredProofsPagesVisited'];
        $restoredPrints = $_POST['restoredPrints'];                  
        $restoredPrintsPagesVisited = $_POST['restoredPrintsPagesVisited'];

        if (!empty($this->accounts[$user]) && !empty($password) && ($this->accounts[$user]["password"] === $password)) {
            session_unset();

            $_SESSION["user"] = $user;
            $_SESSION["proofsPagesVisited"] = array();
            $_SESSION["proofsChosen"] = array();
            $_SESSION["printsPagesVisited"] = array();
            $_SESSION["printsChosen"] = array();
            $_SESSION["basket"] = array();

            //If the user is logging in try to restore the proofs chosen based on what was stored in html data if it is available
            if (!empty($restoredProofs)) {
                $restoredProofsArray = json_decode($restoredProofs);
                if (is_array($restoredProofsArray)) {
                    $_SESSION["proofsChosen"] = $restoredProofsArray;
                }
            }
                
            //If the user is logging in try to restore the proof pages visited chosen based on what was stored in html data if it is available    
            if (!empty($restoredProofsPagesVisited)) {
                $restoredProofsPagesVisitedArray = json_decode($restoredProofsPagesVisited);
                if (is_array($restoredProofsPagesVisitedArray)) {

                    $_SESSION["proofsPagesVisited"] = $restoredProofsPagesVisitedArray;
                }
            }
            
            //If the user is logging in try to restore the prints chosen based on what was stored in html data if it is available
            if (!empty($restoredPrints)) {
                $restoredPrintsArray = json_decode($restoredPrints);
                if (is_array($restoredPrintssArray)) {
                    $_SESSION["printsChosen"] = $restoredPrintsArray;
                }
            }
            
            //If the user is logging in try to restore the prints pages visited chosen based on what was stored in html data if it is available  
            if (!empty($restoredPrintsPagesVisited)) {
                $restoredPrintsPagesVisitedArray = json_decode($restoredPrintsPagesVisited);
                if (is_array($restoredPrintsPagesVisitedArray)) {

                    $_SESSION["printsPagesVisited"] = $restoredPrintsPagesVisitedArray;
                }
            }
            
            //try to restore the basket based on html5 data
            //TODO
            
            
                                                               
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

    private function showThumbsScreen($mode)
    {
        
        if (isset($_POST['index']))
        {
            $pageIndex = $this->noScriptInjection($_POST['index']);
        }
        else
        {
            $pageIndex = 0;
        }

        $user = $_SESSION["user"];
        $account = $this->accounts[$user];

        if ($mode == 'proofs') {
            $userMessage = $this->lang("proofsMessage");
            $customMessage = $account["customProofsMessage"];
        } 
        else
        {
            $userMessage = $this->lang("printsMessage");
            $customMessage = $account["customPrintsMessage"];
        }
                                                        
        $thumb = $this->lang("thumb");

        $thumbsDir = $this->clientAreaDirectory . DIRECTORY_SEPARATOR . $_SESSION['user'] .
            DIRECTORY_SEPARATOR . $mode . DIRECTORY_SEPARATOR . "thumbs";
        $mainDir = $this->clientAreaDirectory . DIRECTORY_SEPARATOR . $_SESSION['user'] .
            DIRECTORY_SEPARATOR . $mode . DIRECTORY_SEPARATOR . "main" . DIRECTORY_SEPARATOR;
        $files = scandir($thumbsDir);
        $thumbs=array();
        foreach ($files as $file)
        {
            if ( ($file !== ".")  && ($file !== "..") && (is_file($thumbsDir . DIRECTORY_SEPARATOR . $file)) )
            {
                $thumbs[] = $file;
            }
        }

        $thumbsForThisPage = array_slice($thumbs, $pageIndex, $this->options["thumbsPerPage"]);

        if (($thumbsForThisPage < $thumbs) && $this->getOption("showNannyingMessageAboutMoreThanOnePage")) {
            $extraMessage = '<br><span>' . $this->lang("moreThanOnePageMessage") . '</span>';
        } else {
            $extraMessage = "";
        }

        $numberOfThumbs = $this->getImageCount($thumbsDir);
        $pageHtml = $this->pageHtml($pageIndex, $this->options["thumbsPerPage"], $numberOfThumbs);

        //these little blocks are about finding out if the user has visited all pages
        //so we can warn them if they try to click 'Done' or 'Checkout'' but have not visited all pages
        $pageIndexes = $this->getPageIndexes($this->options["thumbsPerPage"], $numberOfThumbs);
        $allPrintsPagesVisited = '';
        $allProofsPagesVisited = '';
                                          
        if ($mode == 'proofs') {
          if (!in_array($pageIndex, $_SESSION["proofsPagesVisited"])) {
              $_SESSION["proofsPagesVisited"][] = $pageIndex;
          }
          $notVisitedProofs = array_diff($pageIndexes, $_SESSION["proofsPagesVisited"] );
          if (empty($notVisitedProofs)) {
              $allProofsPagesVisited = "yes";
          } else {
              $allProofsPagesVisited = "no";
          }
        
        }
        else
        {
          if (!in_array($pageIndex, $_SESSION["printsPagesVisited"])) {
              $_SESSION["printsPagesVisited"][] = $pageIndex;
          }
          $notVisitedPrints = array_diff($pageIndexes, $_SESSION["printsPagesVisited"] );
          if (empty($notVisitedPrints)) {
              $allPrintsPagesVisited = "yes";
          } else {
              $allPrintsPagesVisited = "no";
          }
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

         
            //TODO - make this generic?
            if ($this->options["proofsShowLabels"]) {
                $label = '<span class="ca_label">' . $file . '</span>';
            } else {
                $label = "";
            }

            //TODO - in a loop? or at least we should cache the results
            $imageDimensions = $this->getImageDimensions($mainDir . $file);


            $file = str_replace(".JPG", ".jpg", $file); //TODO hack to fix Nascimento
            $thumbPath = $this->imageProvider . '?mode=proofs&size=thumbs&file=' . $file; 

            if ($mode == 'proofs')
            {     
                if ((isset($_SESSION["proofsChosen"])) && in_array($file, $_SESSION["proofsChosen"])) {
                    $checked = ' checked="checked" ';
                } else {
                    $checked = '';
                }
                $controls =  '<input class="ca_proof_checkbox_event" type="checkbox" value="' .
                  $file . '"' . $checked . ' > ' ;
                $thumbClass = '';
            }    
            else
            {
                $controls = '';
                //TODO - is it in the basket?
                $thumbClass = 'has_order';
            }

            $fileHtml = '<div class="ca_thumb_pic ' . $thumbClass . ' "'. $picStyle . '><img' .
                ' data-file-ref="' . $file . '" data-image-width="' . $imageDimensions["width"] . '" ' .
                'data-image-height="' . $imageDimensions["height"] . '" ' .
                'src="' . $thumbPath . '" alt="' . $thumb  . '">' . $controls . $label . '</div>';
            $pageThumbsHtml.=  $fileHtml;
        }

        $urlForMains = $this->imageProvider . '?mode=proofs&size=main';

        if ($this->getOption("proofsShowLabels")) {
            $labelsOption = "on";
        } else {
        $labelsOption = "off";
        }

        $proofsChosenCount = count($_SESSION["proofsChosen"]);

        $message = "$userMessage $customMessage $extraMessage";
        $dataAttributes = array();
        $dataAttributes["url-for-mains"] = $urlForMains;
        $dataAttributes["labels-option"] = $labelsOption;
        $dataAttributes["check-all-message"] = $this->lang("checkAllMessage");
        $dataAttributes["all-proofs-pages-visited"] = $allProofsPagesVisited;
        $dataAttributes["all-prints-pages-visited"] = $allPrintsPagesVisited;
        $dataAttributes["confirm-logout-message"] = $this->lang("confirmLogoutMessage");
        $dataAttributes["ok-text"] = $this->lang("okText");
        $dataAttributes["cancel-text"] = $this->lang("cancelText");
        $dataAttributes["username"] = $_SESSION["user"];
        $dataAttributes["critical-error-message"] = $this->lang("criticalErrorMessage");
        $dataAttributes["mode"] = $mode;
        $mainBar = $this->caProofsBar($dataAttributes, $message);
        $subBar = $this->caSubBar($pageHtml, true, false, $proofsChosenCount, $mode);

        $html = <<<EOF

             <form action="$this->containerPageUrl" method="post" id="ca_action_form">
                <input type="hidden" name="action" id="ca_action_field" value="">
                <input type="hidden" name="index" id="ca_index_field" value="0">
             </form>

             $mainBar
             $subBar

            <div class="ca_proofs_thumbs">
                $pageThumbsHtml
            </div>

EOF;
        return $html;


    }

    private function showProofsScreen()
    {                                
        return $this->showThumbsScreen('proofs');
    }

    private function showPrintsScreen()
    {                                    
        return $this->showThumbsScreen('prints');
    }

    private function caProofsBar($dataAttributes, $message)
    {
        $attributes = "";
        foreach ($dataAttributes as $key => $value) {
            $attributes = $attributes . ' data-' . $key . "='" . $value . "'";
        }
        $html = <<<EOT
                <div class="ca_menu_bar"
                $attributes
                >
                <span class="ca_message_area">$message</span>
            </div>
EOT;

        return $html;
    }

    private function caSubBar($pageHtml, $needsDoneButton, $needsCancelButton, $proofsChosenCount, $mode)
    {

        
        $logout = $this->lang("logout");
        $cancelText = $this->lang("cancelText");

        if ($needsDoneButton && $mode === 'proofs') {
            $doneButtonText  = $this->lang("done");
            $doneButton = '<button class="ca_proof_button ca_proof_event">' . $doneButtonText . '</button>';
        }
        
        if ($mode === 'prints') {
          $basketButtonText =     $this->lang("basketButtonText");
          $checkoutButtonText =     $this->lang("checkoutButtonText");
          $basketButton = '<button class="ca_menu_button ca_prints_basket_event">' . $basketButtonText . '</button>';
          $checkoutButton = '<button class="ca_menu_button ca_prints_checkout_event">' . $checkoutButtonText . '</button>';
        }
                           
        if (isset($doneButton)) {
          $orderingButtons = $doneButton;
        }
        else
        {
          $orderingButtons = $basketButton . $checkoutButton;
        }

        if ($needsCancelButton) {
            $cancelButton = '<button class="ca_proof_button ca_proof_cancel_event">' . $cancelText . '</button>';
        } else {
            $cancelButton = '';
        }

        if (!is_null($proofsChosenCount) && ($mode == 'proofs')) {
            $chosen = $this->lang("chosen");
            $proofsChosenText = '<span>' . $chosen . '</span>' .
                '<span class="ca_counter">' . $proofsChosenCount . '</span>';
        } else {
            $proofsChosenText = "";
        }

            $html = <<<EOT

              <div class="ca_sub_bar">
                <div class="ca_pagination_box">
                  $pageHtml
                </div>
                <div class="ca_menu_buttons">
                    <span class="ca_counter_box ca_counter_label ca_label">
                         $proofsChosenText
                    </span>
                    <span>$orderingButtons</span><span>$cancelButton</span>
                   <span><button class="ca_logout_button ca_logout_confirm_event">$logout</button></span>
                </div>
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


    private function showChoiceScreenXXX()
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
        
        return ""; //TODO not required - all html comes from outputHtmlPage - ultimately this file loses its controller and only has one purpose: server the starting html structure and the templates   
    }

    //TODO this can go when fully ported
    private function showLoginScreenX()
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
                    <input type="hidden" name="restoredProofsPagesVisited" id="restoredProofsPagesVisited" value="">
                    <input type="hidden" name="restoredPrints" id="restoredPrints" value="">
                    <input type="hidden" name="restoredPrintsPagesVisited" id="restoredPrintsPagesVisited" value="">
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

        for ($i= 0; $i < $numberOfPages; $i++)
        {
            $pages[$i] =  $index;
            $index = $index + $thumbsPerPage;
        }

        return $pages;
    }
    
    //TODO - put these into a file except the login one? in fact this is about all that will be in this file + app html
    private function appTemplates() 
    {
    
        $html=<<<EOF
            <script type="text/html" id="ca_order_line_tmpl">
                <div class="col-xs-2">
                    <div class="form-group ca_print_size_group ">
                        <select class="ca_form_control form-control ca_print_size_group ca_print_size_event">
                            <option value="--"><%= langStrings.select %></option>
                            <% _.each(applicableSizesGroup, function(size){ %>
                                <option value="<%= size.value %>" <% if (order.print_size == size.value) { %> selected <% } %>><%= size.display %> [<%= currency.symbol %><%= size.printPrice %>]</option>
                            <% }); %>    
                        </select>
                    </div>    
                </div>
                <div class="col-xs-2">
                    <div class="form-group ca_mount_group">
                        <select class="ca_form_control form-control ca_mount ca_mount_event" >
                            <option value="--"><%= langStrings.select %></option>
                            <% if (mountPrice !== null) { %>
                                <% _.each(mounts.mount, function(mount){ %>
                                  <option value="<%= mount.value %>" <% if (order.mount_style == mount.value) { %> selected <% } %>><%= mount.display %> [+ <%= currency.symbol %><%= mountPrice %>]</option>
                                <% }); %> 
                                <option value="no_mount" <% if (order.mount_style == "no_mount") { %> selected <% } %>><%= langStrings.noMount %></option>
                            <% } %>
                        </select>    
                    </div>
                </div>
                <div class="col-xs-2">
                    <div class="form-group ca_frame_group">      
                        <select class="ca_form_control form-control ca_frame ca_frame_event" >
                            <option value="--"><%= langStrings.select %></option>
                            <% if (framePrices !== null) { %>
                                <% _.each(framePrices, function(framePrice, frameStyle){ %>
                                    <option value="<%= frameStyle %>" <% if (order.frame_style == frameStyle) { %> selected <% } %>><%= frameStylesToDisplay[frameStyle] %> [+ <%= currency.symbol %><%= framePrice %>]</option>
                                <% }); %> 
                                <option value="no_frame" <% if (order.frame_style == "no_frame") { %> selected <% } %>><%= langStrings.noFrame %></option>
                            <% } %>  
                        </select>
                    </div>
                </div>
                <div class="col-xs-2">
                    <div class="form-group ca_qty_group">
                        <input type="text" id="ca_qty_field" class="ca_qty_input form-control ca_qty_event" value="<%= order.qty %>">
                    </div>  
                </div>
                 <div class="col-xs-1">
                    <div class="form-group ca_price_group">
                        <span><%= currency.symbol %><%= order.total_price %></span>
                    </div>  
                </div>
                <div class="col-xs-3">
                      <% if (mode == 'new') { %>
                          <button type="button" class="ca_add_event"><%= langStrings.add %></button>
                      <% } else { %>
                          <span class="ca_edit_icon fa <%= editStateIcon %>"></span>  
                          <button type="button" class="ca_update_event"><%= langStrings.update %></button>
                          <button type="button" class="ca_remove_event"><%= langStrings.remove %></button>
                      <% } %>
                      <div class="ca_order_info"></div>
                </div>
                
        </script>
        <script type="text/html" id="ca_order_line_row_head_tmpl">
                <div class="row ca_row_header">
                      <div class="col-xs-2"><%= langStrings.print_size %></div>
                      <div class="col-xs-2"><%= langStrings.mount %></div>
                      <div class="col-xs-2"><%= langStrings.frame %></div>
                      <div class="col-xs-2"><%= langStrings.quantity %></div>
                      <div class="col-xs-1"><%= langStrings.price %></div>
                      <div class="col-xs-3"></div>
                </div>
        </script>
        <script type="text/html" id="ca_login_tmpl">
            <div class="ca_print_login">
                <form action="" method="post" id="ca_action_form">
                    <span class="ca_login_error"></span><br>
                    <span class="ca_your_print_label"><%= langStrings.userName %></span>
                    <input class="ca_your_print_field" type="text" name="login" id="ca_login_name">
                    <br class="clear ">
                    <span class="ca_your_print_label" ><%= langStrings.password %></span>
                    <input class="ca_your_print_field" type="password" name="password" id="ca_login_password">
                    <br class="clear">
                    <button class="ca_standard_button ca_login_button ca_login_button_event" id="login" type="button"><%= langStrings.login %></button>
                </form>
            </div>
        </script>
        <script type="text/html" id="ca_mode_choice_tmpl">
            <div class="ca_print_login">
                <%= langStrings.hello %> <span class="ca_human_name"><%= userData["human_name"] %></span>. <%= langStrings.chooseOption %>
                <form action="" method="post" id="ca_action_form">
                    <input type="hidden" name="action" id="ca_action_field" value="">
                    <select id="ca_activity_choice" class="ca_select_box">
                        <option value="--">Select..</option>
                        <option value="proofs" <%= proofsOn %>><%= langStrings.viewProofs %></option>
                        <option value="prints" <%= printsOn %>><%= langStrings.orderPrints %></option>
                    </select><br>
                    <button class="ca_large_button ca_choose_activity_event"
                        type="button"><%= langStrings.go %></button>
                </form>
            </div>
        </script>
        <script type="text/html" id="ca_print_thumb_tmpl">           
            <div class="ca_thumb_pic ca_print_thumb_pic <%= in_basket_class %>" style="<%= style %>">
                <img src="<%= path %>" alt="<%= alt_text %>" >
                <span><%= label %></span>
            </div>
        </script>
 
EOF;
        return $html;    
    
    }
    
   


    private function pageHtml($pageIndex, $thumbsPerPage, $numberOfThumbs)
    {
        $output = '<div class="ca_page_info">';

        $pages = $this->getPageIndexes($thumbsPerPage, $numberOfThumbs);

        $currentPage = array_search($pageIndex, $pages);

        $length = 11;
        $offset = $currentPage - 6;
        if ($offset < 0) {
            $offset = 0;
        }

        $pagesToShow = array_slice($pages, $offset, $length, $preserve_keys = true);

        foreach($pagesToShow as $pageNumber => $index)
        {
            if ($index == $pageIndex) {
                $class = " ca_highlighted_pagination  ";
            }
            else
            {
                $class = "";
            }
            $output.= '<button data-index="' . $index . '" class="ca_page_number_event ca_thumbs_page ' . $class .
                '"><span>'  . $pageNumber . '</span></button>';

        }

        $output.= '</div>';
        $output.= $this->appTemplates(); 
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
        //TODO dont need content
        if (!empty($this->jQueryUrl)) 
        {
            $jQueryLine = "<script src='$this->jQueryUrl'></script>";
        }
        else
        {
            $jQueryLine = "";
        }

        //build header
        $headerContent = <<<EOT
        $jQueryLine
        <script src="$this->underscoreUrl"></script>
        <script src="$this->backboneUrl"></script>
        <script src="$this->appUrl"></script>
        <script src="$this->jsUrl"></script>
        <script src="$this->fontAwesomeCSSJSUrl"></script>
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
        <link rel="stylesheet" href="$this->cssUrl" type="text/css">
EOT;

        $wrappedContent = <<<EOT
            <div id="ca_content_area">
                <div id="ca_menu">
                </div>
                <div id="ca_main">
                </div>
                <div id="ca_lightbox">
                 </div>
                <div id="ca_popup">
                </div>
            </div>
            <br class="ca_clear">
EOT;
        $wrappedContent.= $this->appTemplates();

        $template = new TemplateEngine($this->template);
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




class TemplateEngine
{
    private $template;

    public function __construct($template)
    {
        $this->template = $template;
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