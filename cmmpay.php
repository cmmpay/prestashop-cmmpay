<?php
/**
 * CMMPay - A Sample Payment Module for PrestaShop 1.7
 *
 * This file is the declaration of the module.
 *
 * @author CMMPay Team
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class CmmPay extends PaymentModule
{
    private $_html = '';
    private $_postErrors = array();

    public $address;

    /**
     * PrestaPay constructor.
     *
     * Set the information about this module
     */
    public function __construct()
    {
        $this->name                   = 'cmmpay';
        $this->tab                    = 'payments_gateways';
        $this->version                = '1.0';
        $this->author                 = 'CMMPay Team';
        $this->controllers            = array('payment', 'validation');
        $this->currencies             = true;
        $this->currencies_mode        = 'checkbox';
        $this->bootstrap              = true;
        $this->displayName            = 'CMMPay';
        $this->description            = 'CMM Payment module.';
        $this->confirmUninstall       = 'Are you sure you want to uninstall this module?';
        $this->ps_versions_compliancy = array('min' => '1.7.0', 'max' => _PS_VERSION_);

        parent::__construct();
    }

    /**
     * Install this module and register the following Hooks:
     *
     * @return bool
     */
    public function install()
    {
        return parent::install()
            && $this->registerHook('paymentOptions')
            && $this->registerHook('paymentReturn');
    }

    /**
     * Uninstall this module and remove it from all hooks
     *
     * @return bool
     */
    public function uninstall()
    {
        return parent::uninstall();
    }

    /**
     *
     *   Returns a string containing the HTML necessary to
     *   generate a configuration screen on the admin
     *
     * @return string
     */
    public function getContent()
    {
        $output = null;
        $checkValid = true;
        if (Tools::isSubmit('submit'.$this->name)) {
            $loginID            = strval(Tools::getValue('LOGIN_ID'));
            $transactionKey     = strval(Tools::getValue('TRANSACTION_KEY'));
            $signatureKey       = strval(Tools::getValue('SIGNATURE_KEY'));
            $md5Hash            = strval(Tools::getValue('MD5_HASH'));
            $endPoint           = strval(Tools::getValue('GETAWAY_URL'));

//           Message valid login id
            if (
                !$loginID ||
                empty($loginID) ||
                !Validate::isGenericName($loginID)
            ) {
                $output .= $this->displayError($this->l('Invalid Login ID value'));
            } else {
                Configuration::updateValue('LOGIN_ID', $loginID);
                $checkValid = false;
            }
//            Message valid transaction key
            if (
                !$transactionKey ||
                empty($transactionKey) ||
                !Validate::isGenericName($transactionKey)
            ) {
                $output .= $this->displayError($this->l('Invalid Transaction Key value'));
            } else {
                Configuration::updateValue('TRANSACTION_KEY', $transactionKey);
                $checkValid = false;
            }
//               Message valid signature key
            if (
                !$signatureKey ||
                empty($signatureKey) ||
                !Validate::isGenericName($signatureKey)
            ) {
                $output .= $this->displayError($this->l('Invalid Login ID value'));
            } else {
                Configuration::updateValue('SIGNATURE_KEY', $signatureKey);
                $checkValid = false;
            }
//               Message valid MD5 Hash
            if (
                !$md5Hash ||
                empty($md5Hash) ||
                !Validate::isGenericName($md5Hash)
            ) {
                $output .= $this->displayError($this->l('Invalid MD5 Hash value'));
            } else {
                Configuration::updateValue('MD5_HASH', $md5Hash);
                $checkValid = false;
            }

//               Message valid END Point
            if (
                !$endPoint ||
                empty($endPoint) ||
                !Validate::isGenericName($endPoint)
            ) {
                $output .= $this->displayError($this->l('Invalid End Point value'));
            } else {
                Configuration::updateValue('GETAWAY_URL', $endPoint);
                $checkValid = false;
            }


            if($checkValid == false)
            {
                $output .= $this->displayConfirmation($this->l('Settings updated'));
            }
        }

        return $output.$this->displayForm();
    }

    /**
     * Display admin form
     *
     * @return string
     */
    public function displayForm()
    {
        // Get default language
        $defaultLang = (int)Configuration::get('PS_LANG_DEFAULT');

        // Init Fields form array
        $fieldsForm = $this->adminForm();

        $helper = new HelperForm();

        // Module, token and currentIndex
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;

        // Language
        $helper->default_form_language = $defaultLang;
        $helper->allow_employee_form_lang = $defaultLang;

        // Title and toolbar
        $helper->title          = $this->displayName;
        $helper->show_toolbar   = true;        // false -> remove toolbar
        $helper->toolbar_scroll = true;      // yes - > Toolbar is always visible on the top of the screen.
        $helper->submit_action  = 'submit'.$this->name;
        $helper->toolbar_btn    = [
            'save' => [
                'desc' => $this->l('Save'),
                'href' => AdminController::$currentIndex.'&configure='.$this->name.'&save'.$this->name.
                    '&token='.Tools::getAdminTokenLite('AdminModules'),
            ],
            'back' => [
                'href' => AdminController::$currentIndex.'&token='.Tools::getAdminTokenLite('AdminModules'),
                'desc' => $this->l('Back to list')
            ]
        ];

        // Load current value
        $helper->fields_value['LOGIN_ID']           = Configuration::get('LOGIN_ID');
        $helper->fields_value['TRANSACTION_KEY']    = Configuration::get('TRANSACTION_KEY');
        $helper->fields_value['SIGNATURE_KEY']      = Configuration::get('SIGNATURE_KEY');
        $helper->fields_value['MD5_HASH']           = Configuration::get('MD5_HASH');
        $helper->fields_value['GETAWAY_URL']        = Configuration::get('GETAWAY_URL')!='' ? Configuration::get('GETAWAY_URL') : 'https://app.cmmpay.net/pay';
        return $helper->generateForm($fieldsForm);
    }

    private function adminForm()
    {
        $fieldsForm[0]['form'] = [
            'legend' => [
                'title' => $this->l('Settings'),
            ],
            'input' => [
                [
                    'type' => 'text',
                    'label'=> $this->l('Login ID'),
                    'name' => 'LOGIN_ID',
                    'size' => 50,
                    'required' => true
                ],[
                    'type' => 'text',
                    'label'=> $this->l('Transaction Key'),
                    'name' => 'TRANSACTION_KEY',
                    'size' => 50,
                    'required' => true
                ],[
                    'type' => 'text',
                    'label'=> $this->l('Signature Key'),
                    'name' => 'SIGNATURE_KEY',
                    'size' => 50,
                    'required' => true
                ],[
                    'type' => 'text',
                    'label'=> $this->l('MD5 Hash Value'),
                    'name' => 'MD5_HASH',
                    'size' => 50,
                    'required' => true
                ],[
                    'type'  => 'text',
                    'label' => $this->l('End Point'),
                    'name'  => 'GETAWAY_URL',
                    'size' => 50,
                    'required' => true
                ]
            ],
            'submit' => [
                'title' => $this->l('Save'),
                'class' => 'btn btn-default pull-right'
            ]
        ];


        return $fieldsForm;
    }
    /**
     * Display this module as a payment option during the checkout
     *
     * @param array $params
     * @return array|void
     */
    public function hookPaymentOptions($params)
    {
        /*
         * Verify if this module is active
         */
        if (!$this->active) {
            return;
        }

        /**
         * Form action URL. The form data will be sent to the
         * validation controller when the user finishes
         * the order process.
         */
//        $formAction = $this->context->link->getModuleLink($this->name, 'validation', array(), true);
        $formAction = $this->context->link->getModuleLink($this->name, 'payment', array(), true);

        /**
         * Assign the url form action to the template var $action
         */
        $this->smarty->assign(['action' => $formAction]);

        /**
         *  Load form template to be displayed in the checkout step
         */
        $paymentForm = $this->fetch('module:cmmpay/views/templates/hooks/payment_option.tpl');

        /**
         * Create a PaymentOption object containing the necessary data
         * to display this module in the checkout
         */
        $newOption = new PrestaShop\PrestaShop\Core\Payment\PaymentOption;
        $newOption->setModuleName($this->displayName)
            ->setCallToActionText($this->displayName)
            ->setAction($formAction)
            ->setForm($paymentForm);

        $payment_options = array(
            $newOption
        );

        return $payment_options;
    }

    /**
     * Display a message in the paymentReturn hook
     *
     * @param array $params
     * @return string
     */
    public function hookPaymentReturn($params)
    {
        /**
         * Verify if this module is enabled
         */
        if (!$this->active) {
            return;
        }

        return $this->fetch('module:cmmpay/views/templates/hooks/payment_return.tpl');
    }
}