<?php
/**
 * CMMPay - CMM Payment Module for PrestaShop 1.7
 *
 * Order Validation Controller
 *
 * @author CMMPay Team
 * @license https://opensource.org/licenses/afl-3.0.php
 */

class CmmpayPaymentModuleFrontController extends ModuleFrontController
{
    /**
     * Request CMM and get Response
     */

    public function postProcess()
    {
        $cart = $this->context->cart;
        $authorized = false;

        /**
         * Verify if this module is enabled and if the cart has
         * a valid customer, delivery address and invoice address
         */
        if (!$this->module->active || $cart->id_customer == 0 || $cart->id_address_delivery == 0
            || $cart->id_address_invoice == 0) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        /**
         * Verify if this payment module is authorized
         */
        foreach (Module::getPaymentModules() as $module) {
            if ($module['name'] == 'cmmpay') {
                $authorized = true;
                break;
            }
        }

        if (!$authorized) {
            die($this->l('This payment method is not available.'));
        }

        /** @var CustomerCore $customer */
        $customer = new Customer($cart->id_customer);
        $invoice = new Address((int) $cart->id_address_invoice);
        $delivery = new Address((int) $cart->id_address_delivery);
        /**
         *
         * Check if this is a valid customer account
         */
        if (!Validate::isLoadedObject($customer)) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        /**
         * Create post params for CMMPay
         */
        $timeStamp          = time();
        $login_id           = Configuration::get('LOGIN_ID');
        $order_total        = $cart->getOrderTotal(true, Cart::BOTH);
        $order_id           = $cart->id;
        $currency_code      = $this->context->currency->iso_code;
        $transactionKey     = Configuration::get('TRANSACTION_KEY');
        $delivery_state     = $delivery->id_state ? new State((int) $delivery->id_state) : false;
        $invoice_state      = $invoice->id_state ? new State((int) $invoice->id_state) : false;

        if (function_exists('hash_hmac')) {
            $hash_d        = hash_hmac('md5', sprintf('%s^%s^%s^%s^%s',
                $login_id,
                $order_id,
                $timeStamp,
                $order_total,
                $currency_code
            ), $transactionKey);
        } else {
            $hash_d    = bin2hex(mhash(MHASH_MD5, sprintf('%s^%s^%s^%s^%s',
                $login_id,
                $order_id,
                $timeStamp,
                $order_total,
                $currency_code
            ), $transactionKey));
        }

        $relay_url = $this->context->link->getModuleLink('cmmpay', 'validation', array(), true);;
        $authorize_args = array(
            'x_login'                  => $login_id,
            'x_amount'                 => $order_total,
            'x_invoice_num'            => $order_id,
            'x_relay_response'         => "TRUE",
            'x_relay_url'              => $relay_url,
            'x_fp_sequence'            => $order_id,
            'x_fp_hash'                => $hash_d,
            'x_show_form'              => 'PAYMENT_FORM',
            'x_version'                => '3.1',
            'x_fp_timestamp'           => $timeStamp,
            'x_first_name'             => $customer->firstname,
            'x_last_name'              => $customer->lastname,
            'x_company'                => $customer->company ,
            'x_email'                  => $customer->email,
            'x_address'                => $invoice->address1,
            'x_country'                => $invoice->country,
            'x_state'                  => $invoice->id_state ? $invoice_state->name : '',
            'x_city'                   => $invoice->city,
            'x_zip'                    => $invoice->postcode,
            'x_phone'                  => $invoice->phone,
            'x_ship_to_first_name'     => $delivery->firstname,
            'x_ship_to_last_name'      => $delivery->lastname,
            'x_ship_to_company'        => $delivery->company,
            'x_ship_to_address'        => $delivery->address1,
            'x_ship_to_country'        => $delivery->country,
            'x_ship_to_state'          => $delivery->id_state ? $delivery_state->name : '',
            'x_ship_to_city'           => $delivery->city,
            'x_ship_to_zip'            => $delivery->postcode,
            'x_freight'                => $cart->getTotalShippingCost(true, Cart::BOTH),
            'x_cancel_url_text'        => 'Cancel Payment',
            'x_cancel_url'             => $this->context->link->getPageLink('order&step=1',true),
            'x_currency_code'          => $currency_code,
            'x_type'                   => 'AUTH_CAPTURE',
            'x_test_request'           => 'FALSE'
        );


        $authorize_args_array = array();

        foreach($authorize_args as $key => $value){
            $authorize_args_array[] = "<input type='hidden' name='$key' value='$value'/>";
        }

        $endpoint = Configuration::get('GETAWAY_URL');

        $loading = ' <div style="width: 100%; height: 100%;top: 50%; padding-top: 10px;padding-left: 10px;  left: 50%; transform: translate(40%, 40%)"><div style="width: 150px;height: 150px;border-top: #CC0000 solid 5px; border-radius: 50%;animation: a1 2s linear infinite;position: absolute"></div> </div> <style>*{overflow: hidden;}@keyframes a1 {to{transform: rotate(360deg)}}</style>';
        $html_form  = '<form action="'.$endpoint.'" method="post" id="authorize_payment_form">' .implode('', $authorize_args_array).'<input type="submit" id="submit_authorize_payment_form" style="display: none"/>'.$loading.'</form><script>document.getElementById("submit_authorize_payment_form").click();</script>';

        echo ($html_form);
        die();
    }
}