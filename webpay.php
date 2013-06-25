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
include_once 'admin/webpay_install.php';
include_once 'admin/webpay_debug.php';

register_activation_hook(__FILE__, 'webpay_install');
add_action('plugins_loaded', 'init_woocommerce_webpay');

function add_webpay_gateway_class($methods) {
    $methods[] = 'WC_WebPay';
    return $methods;
}

add_filter('woocommerce_payment_gateways', 'add_webpay_gateway_class');

function init_woocommerce_webpay() {

    class WC_Webpay extends WC_Payment_Gateway {

        /**
         * Constructor for the gateway.
         *
         * @access public
         * @return void
         */
        public function __construct() {

            if (isset($_REQUEST['page_id'])) {
                if ($_REQUEST['page_id'] == 'xt_compra') {
                    add_action('init', array(&$this, 'xt_compra'));
                } else {
                    add_action('init', array(&$this, 'check_webpay_response'));
                }
            }


            $this->id = 'webpay';
            //$this->icon = apply_filters('woocommerce_bacs_icon', '');
            $this->has_fields = false;
            $this->icon = WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)) . '/images/logo.png';
            $this->method_title = __('WebPay GateWay', 'woocommerce');

            // Load the settings.
            $this->init_form_fields();
            $this->init_settings();

            // Define user set variables
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->liveurl = $this->settings['cgiurl'];
            $this->macpath = $this->settings['macpath'];

            // Actions
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('woocommerce_thankyou_webpay', array($this, 'thankyou_page'));

            // Customer Emails
            add_action('woocommerce_email_before_order_table', array($this, 'email_instructions'), 10, 2);
        }

        /**
         * Initialise Gateway Settings Form Fields
         *
         * @access public
         * @return void
         */
        function init_form_fields() {

            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Enable/Disable', 'woocommerce'),
                    'type' => 'checkbox',
                    'label' => __('Enable Woocommerce Webpay Plus', 'woocommerce'),
                    'default' => 'yes'
                ),
                'title' => array(
                    'title' => __('Title', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('', 'woocommerce'),
                    'default' => __('Web Pay Plus', 'woocommerce')
                ),
                'cgiurl' => array(
                    'title' => __('CGI URL', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('url like : http://empresasctm.cl/cgi-bin/tbk_bp_pago.cgi', 'woocommerce'),
                    'default' => __('http://empresasctm.cl/cgi-bin/tbk_bp_pago.cgi', 'woocommerce')
                ),
                'macpath' => array(
                    'title' => __('Check Mac Path', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('url like : /var/www/webpayconector/cgi-bin', 'woocommerce'),
                    'default' => __('/var/www/webpayconector/cgi-bin', 'woocommerce')
                ),
                'description' => array(
                    'title' => __('Customer Message', 'woocommerce'),
                    'type' => 'textarea',
                    'default' => __('Sistema de Pagos a través de tarjetas de crédito y redcompra usando WebPayPlus.', 'woocommerce')
                ),
                'redirect_page_id' => array(
                    'title' => __('Return Page'),
                    'type' => 'select',
                    'options' => $this->get_pages('Select Page'),
                    'description' => "URL of success page"
                ),
                'idcomercio' => array(
                    'title' => __('IDCOMERCIO', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('IDCOMERCIO of tbk_config.dat', 'woocommerce'),
                    'default' => __('597026007976', 'woocommerce')
                ),
                'medcom' => array(
                    'title' => __('MEDCOM', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('MEDCOM of tbk_config.dat', 'woocommerce'),
                    'default' => __('2', 'woocommerce')
                ),
                'tbk_key_id' => array(
                    'title' => __('TBK_KEY_ID', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('TBK_KEY_ID of tbk_config.dat', 'woocommerce'),
                    'default' => __('101', 'woocommerce')
                ),
                'vericom' => array(
                    'title' => __('PARAMVERIFCOM', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('PARAMVERIFCOM of tbk_config.dat', 'woocommerce'),
                    'default' => __('1', 'woocommerce')
                ),
                'urlcgicom' => array(
                    'title' => __('URLCGICOM', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('URLCGICOM of tbk_config.dat', 'woocommerce'),
                    'default' => __('http://54.243.133.13/cgi-bin/tbk_bp_resultado.cgi', 'woocommerce')
                ),
                'servercom' => array(
                    'title' => __('SERVERCOM', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('SERVERCOM of tbk_config.dat', 'woocommerce'),
                    'default' => __('54.243.133.13', 'woocommerce')
                ),
                'portcom' => array(
                    'title' => __('PORTCOM', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('PORTCOM of tbk_config.dat', 'woocommerce'),
                    'default' => __('80', 'woocommerce')
                ),
                'whitelist' => array(
                    'title' => __('WHITELISTCOM', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('WHITELISTCOM of tbk_config.dat', 'woocommerce'),
                    'default' => __('ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz 0123456789./:=&?_', 'woocommerce')
                ),
                'host' => array(
                    'title' => __('HOST', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('HOST of tbk_config.dat', 'woocommerce'),
                    'default' => __('54.243.133.13', 'woocommerce')
                ),
                'wport' => array(
                    'title' => __('WPORT', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('WPORT of tbk_config.dat', 'woocommerce'),
                    'default' => __('80', 'woocommerce')
                ),
                'cgitra' => array(
                    'title' => __('URLCGITRA', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('URLCGITRA of tbk_config.dat', 'woocommerce'),
                    'default' => __('/filtroUnificado/bp_revision.cgi', 'woocommerce')
                ),
                'cgimedtra' => array(
                    'title' => __('URLCGIMEDTRA', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('URLCGIMEDTRA of tbk_config.dat', 'woocommerce'),
                    'default' => __('/filtroUnificado/bp_validacion.cgi', 'woocommerce')
                ),
                'servertra' => array(
                    'title' => __('SERVERTRA', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('SERVERTRA of tbk_config.dat', 'woocommerce'),
                    'default' => __('https://certificacion.webpay.cl', 'woocommerce')
                ),
                'porttra' => array(
                    'title' => __('PORTTRA', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('PORTTRA of tbk_config.dat', 'woocommerce'),
                    'default' => __('6443', 'woocommerce')
                ),
                'conf_tra' => array(
                    'title' => __('PREFIJO_CONF_TR', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('PREFIJO_CONF_TR of tbk_config.dat', 'woocommerce'),
                    'default' => __('HTML_', 'woocommerce')
                ),
                'html_tr_normal' => array(
                    'title' => __('HTML_TR_NORMAL', 'woocommerce'),
                    'type' => 'hidden',
                    'description' => __('HTML_TR_NORMAL of tbk_config.dat', 'woocommerce'),
                    'default' => __('http://empresasctm.cl/webpayconector/wordpress/?page_id=xt_compra&pay=webpay', 'woocommerce')
                ),
            );
        }

        /**
         * Admin Panel Options
         * - Options for bits like 'title' and availability on a country-by-country basis
         *
         * @access public
         * @return void
         */
        public function admin_options() {
            ?>
            <h3><?php _e('WebPay Plus', 'woocommerce'); ?></h3>
            <p><?php _e('Permite el pago con Tarjetas Bancarias en Chile.', 'woocommerce'); ?></p>
            <table class="form-table">
                <?php
                // Generate the HTML For the settings form.
                $this->generate_settings_html();
                ?>
            </table><!--/.form-table-->
            <?php
        }

        /**
         * Output for the order received page.
         *
         * @access public
         * @return void
         */
        function thankyou_page() {
            if ($description = $this->get_description())
                echo wpautop(wptexturize(wp_kses_post($description)));

            echo '<h2>' . __('Our Details', 'woocommerce') . '</h2>';

            echo '<ul class="order_details bacs_details">';

            $fields = apply_filters('woocommerce_bacs_fields', array(
                'account_name' => __('Account Name', 'woocommerce'),
                'account_number' => __('Account Number', 'woocommerce'),
                'sort_code' => __('Sort Code', 'woocommerce'),
                'bank_name' => __('Bank Name', 'woocommerce'),
                'iban' => __('IBAN', 'woocommerce'),
                'bic' => __('BIC', 'woocommerce')
            ));

            foreach ($fields as $key => $value) {
                if (!empty($this->$key)) {
                    echo '<li class="' . esc_attr($key) . '">' . esc_attr($value) . ': <strong>' . wptexturize($this->$key) . '</strong></li>';
                }
            }

            echo '</ul>';
        }

        /**
         * Add content to the WC emails.
         *
         * @access public
         * @param WC_Order $order
         * @param bool $sent_to_admin
         * @return void
         */
        function email_instructions($order, $sent_to_admin) {

            if ($sent_to_admin)
                return;

            if ($order->status !== 'on-hold')
                return;

            if ($order->payment_method !== 'bacs')
                return;

            if ($description = $this->get_description())
                echo wpautop(wptexturize($description));
            ?><h2><?php _e('Our Details', 'woocommerce') ?></h2><ul class="order_details bacs_details"><?php
                $fields = apply_filters('woocommerce_bacs_fields', array(
                    'account_name' => __('Account Name', 'woocommerce'),
                    'account_number' => __('Account Number', 'woocommerce'),
                    'sort_code' => __('Sort Code', 'woocommerce'),
                    'bank_name' => __('Bank Name', 'woocommerce'),
                    'iban' => __('IBAN', 'woocommerce'),
                    'bic' => __('BIC', 'woocommerce')
                ));

                foreach ($fields as $key => $value) :
                    if (!empty($this->$key)) :
                        echo '<li class="' . $key . '">' . $value . ': <strong>' . wptexturize($this->$key) . '</strong></li>';
                    endif;
                endforeach;
                ?></ul><?php
        }

        /**
         * Process the payment and return the result
         *
         * @access public
         * @param int $order_id
         * @return array
         */
        function process_payment($order_id) {
            global $woocommerce;

            $order = new WC_Order($order_id);

            // Mark as on-hold (we're awaiting the payment)
            $order->update_status('on-hold', __('Awaiting BACS payment', 'woocommerce'));

            // Reduce stock levels
            $order->reduce_order_stock();

            // Remove cart
            $woocommerce->cart->empty_cart();

            // Return thankyou redirect
            return array(
                'result' => 'success',
                'redirect' => add_query_arg('key', $order->order_key, add_query_arg('order', $order->id, get_permalink(woocommerce_get_page_id('thanks'))))
            );
        }
        
                public function xt_compra() {
            global $webpay_table_name;
            global $wpdb;
            global $woocommerce;
            $sufijo = "[XT_COMPRA]";

            //rescate de datos de POST.
            $TBK_RESPUESTA = $_POST["TBK_RESPUESTA"];
            $TBK_ORDEN_COMPRA = $_POST["TBK_ORDEN_COMPRA"];
            $TBK_MONTO = $_POST["TBK_MONTO"];
            $TBK_ID_SESION = $_POST["TBK_ID_SESION"];
            $TBK_TIPO_TRANSACCION = $_POST['TBK_TIPO_TRANSACCION'];
            $TBK_CODIGO_AUTORIZACION = $_POST['TBK_CODIGO_AUTORIZACION'];
            $TBK_FINAL_NUMERO_TARJETA = $_POST['TBK_FINAL_NUMERO_TARJETA'];
            $TBK_FECHA_CONTABLE = $_POST['TBK_FECHA_CONTABLE'];
            $TBK_FECHA_TRANSACCION = $_POST['TBK_FECHA_TRANSACCION'];
            $TBK_HORA_TRANSACCION = $_POST['TBK_HORA_TRANSACCION'];
            $TBK_ID_TRANSACCION = $_POST['TBK_ID_TRANSACCION'];
            $TBK_TIPO_PAGO = $_POST['TBK_TIPO_PAGO'];
            $TBK_NUMERO_CUOTAS = $_POST['TBK_NUMERO_CUOTAS'];


            //Validación de los datos del post.
            if (!isset($TBK_RESPUESTA) || !is_numeric($TBK_RESPUESTA))
                die('RECHAZADO');
            if (!isset($TBK_ORDEN_COMPRA))
                die('RECHAZADO');
            if (!isset($TBK_MONTO) || !is_numeric($TBK_MONTO))
                die('RECHAZADO');
            if (!isset($TBK_ID_SESION) || !is_numeric($TBK_ID_SESION))
                die('RECHAZADO');
            if (!isset($TBK_TIPO_TRANSACCION))
                die('RECHAZADO');
            if (!isset($TBK_CODIGO_AUTORIZACION) || !is_numeric($TBK_CODIGO_AUTORIZACION))
                die('RECHAZADO');
            if (!isset($TBK_FINAL_NUMERO_TARJETA) || !is_numeric($TBK_FINAL_NUMERO_TARJETA))
                die('RECHAZADO');
            if (!isset($TBK_FECHA_CONTABLE) || !is_numeric($TBK_FECHA_CONTABLE))
                die('RECHAZADO');
            if (!isset($TBK_FECHA_TRANSACCION) || !is_numeric($TBK_FECHA_TRANSACCION))
                die('RECHAZADO');
            if (!isset($TBK_HORA_TRANSACCION) || !is_numeric($TBK_HORA_TRANSACCION))
                die('RECHAZADO');
            if (!isset($TBK_ID_TRANSACCION) || !is_numeric($TBK_ID_TRANSACCION))
                die('RECHAZADO');
            if (!isset($TBK_TIPO_PAGO))
                die('RECHAZADO');
            if (!isset($TBK_NUMERO_CUOTAS) || !is_numeric($TBK_NUMERO_CUOTAS))
                die('RECHAZADO');

            $order_id = explode('_', $TBK_ORDEN_COMPRA);
            $order_id = (int) $order_id[0];

            if (!is_numeric($order_id))
                die('RECHAZADO');

            if($TBK_RESPUESTA==-1)
                die("ACEPTADO");
            
            //Validar que la orden exista         
            $order = new WC_Order($order_id);
            log_me($order->status, $sufijo);

            //Si la orden de compra no tiene status es debido a que no existe

            if ($order->status == '') {
                log_me("ORDEN NO EXISTENTE " . $order_id, $sufijo);
                die('RECHAZADO');
            } else {
                log_me("ORDEN EXISTENTE " . $order_id, $sufijo);
                //CUANDO UNA ORDEN ES PAGADA SE VA A ON HOLD.

                if ($order->status == 'completed') {
                    log_me("ORDEN YA PAGADA (COMPLETED) EXISTENTE " . $order_id, "\t" . $sufijo);
                    die('RECHAZADO');
                } else {

                    if ($order->status == 'pending') {
                        log_me("ORDEN DE COMPRA NO PAGADA (PENDING). Se procede con el pago de la orden " . $order_id, $sufijo);
                    } else {
                        log_me("ORDEN YA PAGADA (" . $order->status . ") EXISTENTE " . $order_id, "\t" . $sufijo);
                        die('RECHAZADO');
                    }
                }
            }


            /*             * **************** CONFIGURAR AQUI ****************** */
            $myPath = dirname(__FILE__) . "/comun/dato$TBK_ID_SESION.log";
            //GENERA ARCHIVO PARA MAC
            $filename_txt = dirname(__FILE__) . "/comun/MAC01Normal$TBK_ID_SESION.txt";
            // Ruta Checkmac
            $cmdline = $this->macpath . "/tbk_check_mac.cgi $filename_txt";
            /*             * **************** FIN CONFIGURACION **************** */
            $acepta = false;
            //lectura archivo que guardo pago.php
            if ($fic = fopen($myPath, "r")) {
                $linea = fgets($fic);
                fclose($fic);
            }
            $detalle = split(";", $linea);
            if (count($detalle) >= 1) {
                $monto = $detalle[0];
                $ordenCompra = $detalle[1];
            }
            //guarda los datos del post uno a uno en archivo para la ejecuci�n del MAC
            $fp = fopen($filename_txt, "wt");
            while (list($key, $val) = each($_POST)) {
                fwrite($fp, "$key=$val&");
            }
            fclose($fp);
            //Validaci�n de respuesta de Transbank, solo si es 0 continua con la pagina de cierre
            if ($TBK_RESPUESTA == "0") {
                $acepta = true;
            } else {
                $acepta = false;
            }
            //validaci�n de monto y Orden de compra
            if ($TBK_MONTO == $monto && $TBK_ORDEN_COMPRA == $ordenCompra && $acepta == true) {
                $acepta = true;
            } else {
                $acepta = false;
            }

            //Validaci�n MAC
            if ($acepta == true) {
                exec($cmdline, $result, $retint);
                if ($result [0] == "CORRECTO")
                    $acepta = true; else
                    $acepta = false;
            }
            ?>
            <html>
                <?php
                if ($acepta == true) {
                    ?>
                    ACEPTADO
                <?php } else { ?>
                    RECHAZADO
                <?php } exit; ?>
            </html>

            <?php
        }

    }

    //End of the GateWay Class
}
?>
