<?php
/*
  Plugin Name: Woocommerce Webpay ( Chilean Payment Gateway )
  Description: Sistema de pagos de WooCommerce con WebPay
  Author: Cristian Tala Sánchez
  Version: 1.3
  Author URI: www.cristiantala.cl
 
This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License or any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

add_action( 'plugins_loaded', 'init_woocommerce_webpay' );

function add_webpay_gateway_class( $methods ) {
	$methods[] = 'WC_WebPay'; 
	return $methods;
}

add_filter( 'woocommerce_payment_gateways', 'add_webpay_gateway_class' );

function init_woocommerce_webpay() {
	class WC_WebPay extends WC_Payment_Gateway {
            
            public function __construct() {
                $this->id = "WebPay GateWay ";
                $this->method_title = "";
                $this->method_description = "";
            }
        }
}

?>
