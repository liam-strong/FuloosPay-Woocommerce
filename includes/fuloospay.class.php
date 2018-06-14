<?php
class FuloosPay
{

    /*

        Fuloos Pay
        WooCommerce Payment Gateway

    */

    public $token_id;

    public function __construct($token)
    {
        $this->token_id = $token;
    }

    /*
        Check Users Access Key
    */

    public function _checkaccess()
    {
        //Send POST Request
        $result = $this->run(array(
            'key' => $this->token_id
        ));

        //Parse Request
        if ($result['status'] == "404")
        {
            return true;
        }
        else
        {
            return false;
        }
    }

    /*
        Get Account Information
    */

    public function _getaccount()
    {
        $data = $this->run(array(
            'key' => $this->token_id,
            'method' => "check_account"
        ));
        return $data;
    }

    /*
        Send Create Invoice to API
    */

    public function _createinvoice($amount_fls, $custom_ipn, $ipn_callback, $success_url)
    {
        $data = $this->run(array(
            'key' => $this->token_id,
            'method' => "add_invoice",
            'amount' => (float)$amount_fls,
            'custom_ipn' => $custom_ipn,
            'ipn_url' => $ipn_callback,
            'success_url' => $success_url
        ));
        return $data;
    }

    public function get_invoice($custom_ipn)
    {
        $data = $this->run(array(
            'key' => $this->token_id,
            'method' => "get_invoice",
            'orderid' => $custom_ipn
        ));
        return $data;
    }

    public function Redirect($url, $permanent = false)
    {
        header('Location: ' . $url, true, $permanent ? 301 : 302);

        exit();
    }

    // Proccess the request to the bytepay API
    public function run($data)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://panel.fuloospay.net/api/');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_ENCODING, 'Content-Type: application/json');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        $server_output = curl_exec($ch);
        return json_decode($server_output, true);
    }
}
?>
