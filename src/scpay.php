<?php

class scpay extends WC_Payment_Gateway
{
    public function __construct()
    {
        $this->id = "scPay";

        $this->method_title = __("SCPay", 'scPay');

        $this->method_description = __("Share Commerce Payment Gateway Plug-in for WooCommerce", 'scPay');

        $this->title = __("scPay", 'scPay');

        $this->hash_type = 'sha256';

        $this->environment_mode = 'test';

        $this->icon = 'https://sharecommerce-pg.oss-ap-southeast-3.aliyuncs.com/share-commerce-logo.png';

        $this->has_fields = true;

        $this->init_form_fields();

        $this->init_settings();

        foreach ($this->settings as $setting_key => $value) {
            $this->$setting_key = $value;
        }

        if (is_admin()) {
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(
                $this,
                'process_admin_options',
            ));
        }
    }

    # Build the administration fields for this specific Gateway
    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable / Disable', 'scPay'),
                'label' => __('Enable this payment gateway', 'scPay'),
                'type' => 'checkbox',
                'default' => 'no',
            ),
            'title' => array(
                'title' => __('Title', 'scPay'),
                'type' => 'text',
                'desc_tip' => __('Payment title the customer will see during the checkout process.', 'scPay'),
                'default' => __('SCPay', 'scPay'),
            ),
            'description' => array(
                'title' => __('Description', 'scPay'),
                'type' => 'textarea',
                'desc_tip' => __('Payment description the customer will see during the checkout process.', 'scPay'),
                'default' => __('Share Commerce Payment Gateway Solutions & Management', 'scPay'),
                'css' => 'max-width:350px;',
            ),
            'merchantid' => array(
                'title' => __('Merchant ID', 'scPay'),
                'type' => 'text',
                'desc_tip' => __('Merchant ID can obtain from Share Commerce', 'scPay'),
            ),
            'secretkey' => array(
                'title' => __('Secret Key', 'scPay'),
                'type' => 'text',
                'desc_tip' => __('Merchant Key can obtain from Share Commerce', 'scPay'),
            ),
            'hash_type' => array(
                'title' => __('Hash Type', 'scPay'),
                'type' => 'select',
                'class' => 'wc-enhanced-select',
                'description' => __('Signing method, currently only support sha256.', 'scPay'),
                'default' => 'sha256',
                'desc_tip' => true,
                'options' => array(
                    'sha256' => __('sha256', 'scPay'),
                ),
            ),
            'redirecturl' => array(
                'title' => __('Redirect URL', 'scPay'),
                'type' => 'text',
                'desc_tip' => __('This is the payment return url', 'scPay'),
                'default' => __('https://domain.com/wc-api/scpay_redirect', 'scPay'),
            ),
            'environment_mode' => array(
                'title' => __('Environment Mode', 'scPay'),
                'type' => 'select',
                'class' => 'wc-enhanced-select',
                'description' => __('Choose environment mode, testing or production mode.', 'scPay'),
                'default' => 'test',
                'desc_tip' => true,
                'options' => array(
                    'live' => __('Live', 'scPay'),
                    'test' => __('Test', 'scPay'),
                ),
            ),
        );
    }

    public function process_payment($order_id)
    {
        # Get Customer Order Detail
        $customer_order = wc_get_order($order_id);

        $old_wc = version_compare(WC_VERSION, '3.0', '<');

        if ($old_wc) {
            $order_id = $customer_order->id;
            $amount = $customer_order->order_total;
            $name = $customer_order->billing_first_name . ' ' . $customer_order->billing_last_name;
            $email = $customer_order->billing_email;
            $phone = $customer_order->billing_phone;
            $billingaddress1 = $customer_order->billing_address_1;
            $billingaddress2 = $customer_order->billing_address_2;
            $billingcity = $customer_order->billing_city;
            $billingstate = $customer_order->billing_state;
            $billingcountry = $customer_order->billing_country;
        } else {
            $order_id = $customer_order->get_id();
            $amount = $customer_order->get_total();
            $name = $customer_order->get_billing_first_name() . ' ' . $customer_order->get_billing_last_name();
            $email = $customer_order->get_billing_email();
            $phone = $customer_order->get_billing_phone();
            $billingaddress1 = $customer_order->get_billing_address_1();
            $billingaddress2 = $customer_order->get_billing_address_2();
            $billingcity = $customer_order->get_billing_city();
            $billingstate = $customer_order->get_billing_state();
            $billingcountry = $customer_order->get_billing_country();
        }

        $post_args = array(
            'MerchantID' => $this->merchantid,
            'CurrencyCode' => 'MYR',
            'TxnAmount' => $amount,
            'MerchantOrderNo' => $order_id,
            'MerchantOrderDesc' => "Payment for Order No. : " . $order_id,
            'MerchantRef1' => '',
            'MerchantRef2' => '',
            'MerchantRef3' => '',
            'CustReference' => '',
            'CustName' => $name,
            'CustEmail' => $email,
            'CustPhoneNo' => $phone,
            'CustAddress1' => $billingaddress1,
            'CustAddress2' => $billingaddress2,
            'CustCountryCode' => $billingcountry,
            'CustAddressState' => $billingstate,
            'CustAddressCity' => $billingcity,
            'RedirectUrl' => '',
        );

        # make sign
        $signstr = "";
        foreach ($post_args as $key => $value) {
            $signstr .= $value;
        }

        if ($this->hash_type == 'sha256') {
            $post_args['SCSign'] = hash_hmac('sha256', $signstr, $this->secretkey);
        }

        # make query string
        $query_string = '';
        foreach ($post_args as $key => $value) {
            $query_string .= $key . "=" . $value . '&';
        }

        # Remove Last &
        $query_string = substr($query_string, 0, -1);

        if ($this->environment_mode == 'test') {
            $environment_url = 'https://staging.payment.share-commerce.com/Payment';
        } else {
            $environment_url = 'https://payment.share-commerce.com/Payment';
        }

        // echo "<PRE>";
        // print_r(array(
        //     'result' => 'success',
        //     'redirect' => $environment_url . '?' . $query_string,
        // ));
        // exit();

        return array(
            'result' => 'success',
            'redirect' => $environment_url . '?' . $query_string,
        );
    }

    public function scpay_callback()
    {
        $json = file_get_contents('php://input');
        $var = json_decode($json);

        $logger = wc_get_logger();
        $logger->info( wc_print_r( $var, true ), array( 'source' => 'scpay_callback' ));

        if (isset($var['RespCode']) && isset($var['RespDesc']) && isset($var['MerchantOrderNo']) && isset($var['TxnRefNo']) && isset($var['SCSign'])) {
            global $woocommerce;

            $order = wc_get_order($var['MerchantOrderNo']);

            $old_wc = version_compare(WC_VERSION, '3.0', '<');

            $order_id = $old_wc ? $order->id : $order->get_id();

            if ($order && $order_id != 0) {
                # Check Sign
                $signstr = "";
                foreach ($var as $key => $value) {
                    if ($key == 'SCSign') {
                        continue;
                    }

                    $signstr .= $value;
                }
                $sign = "";
                if ($this->hash_type == 'sha256') {
                    $sign = hash_hmac('sha256', $signstr, $this->secretkey);
                }

                if ($sign == $var['SCSign']) {
                    if ($var['RespCode'] == '00' || $var['RespDesc'] == 'Success') {
                        if (strtolower($order->get_status()) == 'pending' || strtolower($order->get_status()) == 'processing') {
                            # only update if order is pending
                            if (strtolower($order->get_status()) == 'pending') {
                                $order->payment_complete();

                                $order->add_order_note('Payment successfully made through SCPay with Transaction Reference ' . $var['TxnRefNo']);
                            }
                        }

                        die('OK');

                    } else {
                        if (strtolower($order->get_status()) == 'pending') {
                            if (!$is_callback) {
                                $order->add_order_note('Payment was unsuccessful');
                                add_filter('the_content', 'scpay_payment_declined_msg');
                            }
                        }
                        
                        die('OK');
                    }
                } else {
                    add_filter('the_content', 'scpay_hash_error_msg');
                }
            }

            exit();
        }
    }

    public function scpay_redirect()
    {
        $var = $_GET;

        $logger = wc_get_logger();
        $logger->info( wc_print_r( $var, true ), array( 'source' => 'scpay_redirect' ));

        if (isset($var['RespCode']) && isset($var['RespDesc']) && isset($var['MerchantOrderNo']) && isset($var['TxnRefNo']) && isset($var['SCSign'])) {
            global $woocommerce;

            $order = wc_get_order($var['MerchantOrderNo']);

            $old_wc = version_compare(WC_VERSION, '3.0', '<');

            $order_id = $old_wc ? $order->id : $order->get_id();

            if ($order && $order_id != 0) {
                # Check Sign
                $signstr = "";
                foreach ($var as $key => $value) {
                    if ($key == 'SCSign') {
                        continue;
                    }

                    $signstr .= $value;
                }
                $sign = "";
                if ($this->hash_type == 'sha256') {
                    $sign = hash_hmac('sha256', $signstr, $this->secretkey);
                }

                if ($sign == $var['SCSign']) {
                    if ($var['RespCode'] == '00' || $var['RespDesc'] == 'Success') {
                        if (strtolower($order->get_status()) == 'pending' || strtolower($order->get_status()) == 'processing') {
                            # only update if order is pending
                            if (strtolower($order->get_status()) == 'pending') {
                                $order->payment_complete();

                                $order->add_order_note('Payment successfully made through SCPay with Transaction Reference ' . $var['TxnRefNo']);
                            }

                            wp_redirect($order->get_checkout_order_received_url());
                            exit();
                        }

                        wp_redirect($order->get_checkout_order_received_url());
                        exit();
                    } else {
                        if (strtolower($order->get_status()) == 'pending') {
                            $order->add_order_note('Payment was unsuccessful');
                            add_filter('the_content', 'scpay_payment_declined_msg');
                        }

                        wp_redirect($woocommerce->cart->get_checkout_url());
                        exit();
                    }
                } else {
                    add_filter('the_content', 'scpay_hash_error_msg');
                }
            }
        }
    }

    # Validate fields, do nothing for the moment
    public function validate_fields()
    {
        return true;
    }

    /**
     * Check if this gateway is enabled and available in the user's country.
     * Note: Not used for the time being
     * @return bool
     */
    public function is_valid_for_use()
    {
        return in_array(get_woocommerce_currency(), array('MYR'));
    }
}
