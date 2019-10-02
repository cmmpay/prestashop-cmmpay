<?php
/**
 * CMMPay - CMM Payment Module for PrestaShop 1.7
 *
 * Order Validation Controller
 *
 * @author CMMPay Team
 * @license https://opensource.org/licenses/afl-3.0.php
 */

class CmmpayValidationModuleFrontController extends ModuleFrontController
{
    /**
     * Validate Payment
     *
     * @throws Exception
     */
    public function postProcess()
    {

        $fallback_url = 'index.php?controller=order&step=1';

        /**
         * Get current cart object from session
         */
        if($_POST['x_invoice_num']){
            $cart = new Cart($_POST['x_invoice_num']);
            $customer = new Customer($cart->id_customer);
        } else {
            $this->web_redirect($fallback_url);
        }

        /**
         * Verify if this module is enabled and if the cart has
         * a valid customer, delivery address and invoice address
         */
        if (!$this->module->active || $cart->id_customer == 0 || $cart->id_address_delivery == 0
            || $cart->id_address_invoice == 0) {
        	$this->web_redirect($fallback_url);
        }
        
        /**
         * Check if this is a valid customer account
         */
        if (!Validate::isLoadedObject($customer)) {
            $this->web_redirect($fallback_url);
        }

        if($this->check_authorize_response()){
            /**
             * Place the order
             */
            if(!$cart->orderExists()){
                $this->module->validateOrder(
                    (int) $cart->id,
                    Configuration::get('PS_OS_PAYMENT'),
                    (float) $cart->getOrderTotal(true, Cart::BOTH),
                    $this->module->displayName,
                    null,
                    null,
                    (int) $cart->id_currency,
                    false,
                    $customer->secure_key
                );
            }
        /**
         * Redirect the customer to the order confirmation page
         */
           $url = _PS_BASE_URL_.'/index.php?controller=order-confirmation&id_cart='.(int)$cart->id.'&id_module='.(int)$this->module->id.'&id_order='.$this->module->currentOrder.'&key='.$customer->secure_key;
        } else {
           $url = $fallback_url;
        }
        $this->web_redirect($url);
        die;
}


    public function web_redirect($url){
        echo "<html><head><script language=\"javascript\">
                <!--
                window.location=\"{$url}\";
                //-->
                </script>
                </head><body><noscript><meta http-equiv=\"refresh\" content=\"0;url={$url}\"></noscript></body></html>";

    }
    /**
     * Check for valid cmmpay server callback to validate the transaction response.
     **/
    function check_authorize_response()
    {
        if ( count($_POST) ){
            $this->msg['class']     = 'error';
            $this->msg['message']   = 'Validation Fail';
            $signatureKey           = Configuration::get('SIGNATURE_KEY');

            $hashData = implode('^', [
                $_POST['x_trans_id'],
                $_POST['x_test_request'],
                $_POST['x_response_code'],
                $_POST['x_auth_code'],
                $_POST['x_cvv2_resp_code'],
                $_POST['x_cavv_response'],
                $_POST['x_avs_code'],
                $_POST['x_method'],
                $_POST['x_account_number'],
                $_POST['x_amount'],
                $_POST['x_company'],
                $_POST['x_first_name'],
                $_POST['x_last_name'],
                $_POST['x_address'],
                $_POST['x_city'],
                $_POST['x_state'],
                $_POST['x_zip'],
                $_POST['x_country'],
                $_POST['x_phone'],
                $_POST['x_fax'],
                $_POST['x_email'],
                $_POST['x_ship_to_company'],
                $_POST['x_ship_to_first_name'],
                $_POST['x_ship_to_last_name'],
                $_POST['x_ship_to_address'],
                $_POST['x_ship_to_city'],
                $_POST['x_ship_to_state'],
                $_POST['x_ship_to_zip'],
                $_POST['x_ship_to_country'],
                $_POST['x_invoice_num'],
            ]);

            $digest = strtoupper(HASH_HMAC('sha512',"^".$hashData."^",hex2bin($signatureKey)));

            if ( $_POST['x_response_code'] != '' &&  ( strtoupper($_POST['x_SHA2_Hash']) ==  $digest ) ){
                return TRUE;
            }else{
                return FALSE;
            }
        }
        else{
            return FALSE;
        }
    }
}