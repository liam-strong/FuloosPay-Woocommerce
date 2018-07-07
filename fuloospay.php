<?php
/*

Plugin Name: Woocommerce FuloosPay
Version: 1.0
Description: The Official Fuloos Payment Gateway
Author: LiteSpeed

*/
if (!defined('ABSPATH'))
{
    exit; // Exit if accessed directly

}

// Include our Gateway Class and register Payment Gateway with WooCommerce
add_action('plugins_loaded', 'fuloospay_init', 0);
function fuloospay_init()
{
    /* If the class doesn't exist (== WooCommerce isn't installed), return NULL */
    if (!class_exists('WC_Payment_Gateway')) return;
    /* If we made it this far, then include our Gateway Class */
    include_once ('includes/woocommerce.class.php');
    include_once ('includes/fuloospay.class.php');
    // Lets add it too WooCommerce
    add_filter('woocommerce_payment_gateways', 'fuloospay_gateway');
    function fuloospay_gateway($methods)
    {
        $methods[] = 'fuloospay_Gateway';
        return $methods;
    }
}

/*
 * Add custom link
 * The url will be http://yourworpress/wp-admin/admin.php?=wc-settings&tab=checkout
*/

add_filter('plugin_action_links_' . plugin_basename(__FILE__) , 'fuloos_pay');
function fuloos_pay($links)
{
    $plugin_links = array(
        '<a href="' . $this->ipn_url . '">' . __('Settings', 'fuloos_pay') . '</a>'
    );
    return array_merge($plugin_links, $links);
}

/*

Add Custom Sidebar with the icon Bytecoin.

*/

add_action('admin_menu', 'bytecoin_create_menu');
function bytecoin_create_menu()
{
    add_menu_page(__('woocommerce', 'textdomain') , 'Fuloos Pay', 'manage_options', 'admin.php?page=wc-settings&tab=checkout&section=fuloospay_gateway', '', plugins_url('http://bytecoin.org/static/favicon.ico') , 56 // Position on menu, woocommerce has 55.5, products has 55.6
    );
}

// Adding as a currencies
add_filter('woocommerce_currencies', 'add_my_currency');
add_filter('woocommerce_currency_symbol', 'add_my_currency_symbol', 10, 2);

function add_my_currency($currencies)
{
    $currencies['FLS'] = __('fuloos', 'woocommerce');
    return $currencies;
}

function add_my_currency_symbol($currency_symbol, $currency)
{
    switch ($currency)
    {
        case 'FLS':
            $currency_symbol = 'FLS';
        break;
    }
    return $currency_symbol;
}
