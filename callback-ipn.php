<?php
/* IPN Call Back */
/* This file is sent the order to be upadeted in Woocommerce Order Table */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
if (isset($_POST['orderid']) || isset($_GET['orderid']))
{
    require ('../../../wp-load.php');

    if (!isset($_GET))
    {
        $order_id = explode("_", $_POST['orderid']);
    }
    else
    {
        $order_id = explode("_", $_GET['orderid']);
    }

    $order = wc_get_order($order_id[1]);
    $order->update_status('completed', __('Payment has been received', 'fuloospay_gatway'));
    die("Order updated...");
}
else
{
    die("No order id was provided, callback error...");
}

?>
