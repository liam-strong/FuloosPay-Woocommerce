<?php
/*

Fuloos Pay
Version: 1.0

*/
class fuloospay_Gateway extends WC_Payment_Gateway
{
    private $reloadTime = 300000;
    private $discount;
    private $confirmed = false;
    private $FuloosPay;

    function __construct()
    {
        $this->id = "fuloospay_gateway";
        $this->method_title = __("Fuloos Pay", 'fuloospay_gateway');
        $this->method_description = __("BytePay Payment Gateway Plug-in for WooCommerce. You can find more information about this payment gateway in our website. You'll need a daemon online for your address.", 'fuloospay_gateway');
        $this->title = __("FuloosPay", 'fuloospay_gateway');
        $this->version = "0.2"; //
        $this->icon = apply_filters('woocommerce_offline_icon', '');
        $this->has_fields = false;
        $this->log = new WC_Logger();
        $this->init_form_fields();
        $this->token = $this->get_option('user_token');
        $this->ipn_url = $this->get_option('ipn_url');
        $this->init_settings();
        foreach ($this->settings as $setting_key => $value)
        {
            $this->$setting_key = $value;
        }
        add_action('admin_notices', array(
            $this,
            'do_ssl_check'
        ));
        add_action('admin_notices', array(
            $this,
            'validate_fields'
        ));
        add_action('woocommerce_thankyou_' . $this->id, array(
            $this,
            'instruction'
        ));
        if (is_admin())
        {
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(
                $this,
                'process_admin_options'
            ));
            add_filter('woocommerce_currencies', array(
                $this,
                'add_my_currency'
            ));
            add_filter('woocommerce_currency_symbol', 'add_my_currency_symbol', 10, 2);
            add_action('woocommerce_email_before_order_table', array(
                $this,
                'email_instructions'
            ) , 10, 2);
        }
        $this->FuloosPay = new FuloosPay($this->token);
    }

    public function add_my_currency($currencies)
    {
        $currencies['FLS'] = __('fuloos', 'woocommerce');
        return $currencies;
    }

    public function add_my_currency_symbol($currency_symbol, $currency)
    {
        switch ($currency)
        {
            case 'FLS':
                $currency_symbol = 'FLS';
            break;
        }
        return $currency_symbol;
    }

    public function admin_options()
    {
        $this
            ->log
            ->add('fuloospay_gateway', '[SUCCESS] Bytecoin Settings Correct');
        echo "<hr>";
        echo "<h1>Fuloos Pay - Payment Gateway</h1>";
        echo "<p>This is the official WooCommerce Plugin for FuloosPay!";
        echo "<div style='border:1px solid #DDD;padding:5px 10px;font-weight:bold;color:#223079;background-color:#9ddff3;'>";
        try
        {
            $this->getamountinfo();
        }
        catch(Exception $e)
        {
            echo $e;
        }
        echo "</div>";
        echo "<table class='form-table'>";
        $this->generate_settings_html();
        echo "</table>";
        echo "<h4>Create an account at Fuloos Pay.net or find out more about accepting Fuloos Coin. <a href=\"https://wiki.bytecoin.org/wiki/Bytecoin_RPC_Wallet\">here</a></h4>";
    }

    function getCurrentURL()
    {
        $currentURL = (@$_SERVER["HTTPS"] == "on") ? "https://" : "http://";
        $currentURL .= $_SERVER["SERVER_NAME"];

        if ($_SERVER["SERVER_PORT"] != "80" && $_SERVER["SERVER_PORT"] != "443")
        {
            $currentURL .= ":" . $_SERVER["SERVER_PORT"];
        }

        $currentURL .= $_SERVER["REQUEST_URI"];
        return $currentURL;
    }

    public function init_form_fields()
    {
        $protocol = isset($_SERVER["HTTPS"]) ? 'https' : 'http';
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable / Disable', 'fuloospay_gateway') ,
                'label' => __('Enable this payment gateway', 'fuloospay_gateway') ,
                'type' => 'checkbox',
                'default' => 'no'
            ) ,
            'title' => array(
                'title' => __('Title', 'fuloospay_gateway') ,
                'type' => 'text',
                'description' => __('Payment title the customer will see during the checkout process.', 'fuloospay_gateway') ,
                'default' => __('Fuloos Coin', 'fuloospay_gateway')
            ) ,
            'description' => array(
                'title' => __('Description', 'fuloospay_gateway') ,
                'type' => 'textarea',
                'description' => __('Payment description the customer will see during the checkout process.', 'fuloospay_gateway') ,
                'default' => __('Pay via FuloosPay; you can purchase Fuloos Coin via FuloosPay.net', 'fuloospay_gateway')
            ) ,
            'ipn_url' => array(
                'title' => __('IPN URL', 'fuloospay_gateway') ,
                'type' => 'text',
                'description' => __('Please make sure this is correct to make the payments fully automated.', 'fuloospay_gateway') ,
                'default' => __($protocol . '//' . $_SERVER['HTTP_HOST'] . '/wp-content/plugins/woocommerce-fuloospay/callback_ipn.php', 'fuloospay_gateway')
            ) ,
            'user_token' => array(
                'title' => __('Token', 'fuloospay_gateway') ,
                'type' => 'text',
                'description' => __('This is unique token by each user account! Can be found in account page!', 'fuloospay_gateway') ,
                'default' => __('setup', 'fuloospay_gateway')
            ) ,
            'environment' => array(
                'title' => __(' Test Mode', 'fuloospay_gateway') ,
                'label' => __('Enable Test Mode', 'fuloospay_gateway') ,
                'type' => 'checkbox',
                'description' => __('Check this box if you are using testnet', 'fuloospay_gateway') ,
                'default' => 'no'
            )
        );
    }

    public function retriveprice($currency, $amount)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://panel.fuloospay.net/api/?key=' . $this->token . '&method=fuloos_price');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_ENCODING, 'Content-Type: application/json');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $server_output = curl_exec($ch);
        $result = json_decode($server_output, true);
        if (!isset($result))
        {
            $this
                ->log
                ->add('fuloospay_Gateway', '[ERROR] Unable to get the price of Fuloos');
        }
        if ($currency == 'USD')
        {
            return $amount / $result['result']['fuloos_usd'];
        }
        if ($currency == 'EUR')
        {
            return $amount / $result['result']['fuloos_eur'];
        }
        if ($currency == 'GBP')
        {
            return $amount / $result['result']['fuloos_gbp'];
        }
        if ($currency == 'INR')
        {
            return $amount / $result['INR'];
        }
        if ($currency == 'FLS')
        {
            $price = '1';
            return $result;
        }
    }

    public function payment_complete($order_id)
    {
        $order = wc_get_order($order_id);
        $order->update_status('completed', __('Payment has been received', 'bytecoin_gateway'));
        return array(
            'result' => 'success',
            'redirect' => $this->get_return_url($order)
        );
        // Create invoice here

    }

    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);
        $order->update_status('on-hold', __('Awaiting offline payment', 'fuloospay_gateway'));
        $order->reduce_order_stock();
        WC()
            ->cart
            ->empty_cart();
        return array(
            'result' => 'success',
            'redirect' => $this->get_return_url($order)
        );
        // Create invoice here

    }

    public function instruction($order_id)
    {

        $order = wc_get_order($order_id);
        $amount = floatval(preg_replace('#[^\d.]#', '', $order->get_total()));
        $currency = $order->get_currency();
        $amountinfls = $this->retriveprice($currency, $amount);

        // Set Variables
        $invoicecreate = $this
            ->FuloosPay
            ->_createinvoice($amountinfls, 'wp_' . $order_id, $this->ipn_url, $this->getCurrentURL());

        if ($invoicecreate['status'] != 500)
        {
            $invoiceInformation = $this
                ->FuloosPay
                ->get_invoice("wp_" . $order_id);

            $invoiceid = $invoiceInformation['result']['invoiceid'];

            $this
                ->FuloosPay
                ->Redirect("https://www.fuloospay.net/invoice/$invoiceid", false);
        }
        else
        {

            $invoiceInformation = $this
                ->FuloosPay
                ->get_invoice("wp_" . $order_id);

            $invoiceid = $invoiceInformation['result']['invoiceid'];

            if ($invoiceInformation['result']['status'] == 1)
            {
                echo "<div id='streamTitle'></div>";
                echo "<link rel='stylesheet' href='https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css'>";
                echo "<div class='row'>
																		<div class='col-sm-12 col-md-12 col-lg-12'>
																		<h4> Fuloos Pay (Pay Instantly via Fuloos Coin)</h4>
																			<div class='alert alert-danger'>We are still waiting for your invoice to be paid...</div>
																			<a href='https://www.fuloospay.net/invoice/$invoiceid' class='btn btn-primary btn-block'> Pay Now </a>
																		</div>
																	</div>";
            }
            else if ($invoiceInformation['result']['status'] == 2)
            {

                echo "<div id='streamTitle'></div>";
                echo "<link rel='stylesheet' href='https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css'>";
                echo "<div class='row'>
																		<div class='col-sm-12 col-md-12 col-lg-12'>
																		<h4> Fuloos Pay (Pay Instantly via Fuloos Coin)</h4>
																			<div class='alert alert-success'>Invoice has been paid, and your order is now being proccesed..</div>

																		</div>
																	</div>";
            }

        }

    }

    // Check if we are forcing SSL on checkout pages
    // Custom function not required by the Gateway
    public function do_ssl_check()
    {
        if ($this->enabled == "yes")
        {
            if (get_option('woocommerce_force_ssl_checkout') == "no")
            {
                echo "<div class=\"error\"><p>" . sprintf(__("<strong>%s</strong> is enabled and WooCommerce is not forcing the SSL certificate on your checkout page. Please ensure that you have a valid SSL certificate and that you are <a href=\"%s\">forcing the checkout pages to be secured.</a>") , $this->method_title, admin_url('admin.php?page=wc-settings&tab=checkout')) . "</p></div>";
            }
        }
    }

    public function getamountinfo()
    {
        if ($this
            ->FuloosPay
            ->_checkaccess())
        {
            $response = $this
                ->FuloosPay
                ->_getaccount();
            echo "Full Name:  " . $response['result']['full_name'] . " </br>";
            echo "Connection Status: Success </br>";
            echo "Your balance is: " . $response['result']['balance'] . " FLS </br>";
            echo "USD Balance Value:  " . round($response['result']['usd_balance'], 2) . "  USD </br>";
            $this
                ->log
                ->add('fuloospay_gateway', '[SUCCESS] Access Key is correct.');
        }
        else
        {
            echo "Access Key is incorrect and does not exist in our database..";
        }
    }
}
