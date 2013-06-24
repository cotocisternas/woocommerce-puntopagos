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

add_action('plugins_loaded', 'woocommerce_gateway_webpay_init', 0);
add_filter('single_add_to_cart_text', 'woo_custom_cart_button_text');

function woo_custom_cart_button_text() {

    return __('Agregar al Carrito', 'woocommerce');
}

function webpay_more_details($params) {
    //IF IT IS A WEBPAY PAYMENT
    global $webpay_table_name;
    global $wpdb;

    $order_id = $params['order_id'];
    $myOrderDetails = $wpdb->get_row("SELECT * FROM $webpay_table_name WHERE idOrder = $order_id", ARRAY_A);
    if ($myOrderDetails):
        ?>
        <h2 class="related_products_title order_confirmed"><?= "Información Extra de la Transacción"; ?></h2>
        <div class="clear"></div>
        <table class="shop_table order_details">
            <thead>
                <tr>
                    <th class="product-name"><?= "Dato" ?></th>
                    <th class="product-quantity"><?= "Valor"; ?></th>


                </tr>
            </thead>
            <tfoot>

                <tr>
                    <th>Tipo de Transacción</th>
                    <th>Venta</th>

                </tr>
                <tr>
                    <th>Nombre del Comercio</th>
                    <th>Cristian W. Tala Manriquez</th>

                </tr>
                <tr>
                    <th>URL Comercio</th>
                    <th>http://www.empresasctm.cl</th>

                </tr>

                <tr>
                    <th>Código de Autorización</th>
                    <th><?= $myOrderDetails['TBK_CODIGO_AUTORIZACION'] ?></th>


                </tr>

                <tr>
                    <th>Final de Tarjeta</th>
                    <th><?= $myOrderDetails['TBK_FINAL_NUMERO_TARJETA'] ?></th>


                </tr>

                <tr>
                    <th>Tipo de pago</th>
                    <th><?
        if ($myOrderDetails['TBK_TIPO_PAGO'] == "VD") {
            echo "Redcompra </th></tr>";
            echo "<tr><td>Tipo de Cuota</td><td>Débito</td></tr>";
        } else {
            echo "Crédito </th></tr>";
            echo '<tr><td>Tipo de Cuota</td><td>';
            switch ($myOrderDetails['TBK_TIPO_PAGO']) {
                case 'VN':
                    echo 'Sin Cuotas';
                    break;
                case 'VC':
                    echo 'Cuotas Normales';
                    break;
                case 'SI':
                    echo 'Sin interés';
                    break;
                case 'CI':
                    echo 'Cuotas Comercio';
                    break;

                default:
                    echo $myOrderDetails['TBK_TIPO_PAGO'];
                    break;
            }
        }
        ?>

                        </td>

                </tr>

                <?
                if (!($myOrderDetails['TBK_TIPO_PAGO'] == "VD") || true ):
                    ?>
                    <tr>
                        <th>Número de Cuotas</th>
                        <th><?
            if (!($myOrderDetails['TBK_NUMERO_CUOTAS'] == "0")) {
                echo $myOrderDetails['TBK_NUMERO_CUOTAS'];
            } else {
                echo "00";
            }
                    ?></th>

                    </tr>
                    <?
                endif;
                ?>
            </tfoot>
        </table>

        <?php
    endif;
}

add_shortcode('webpay-details', 'webpay_more_details');

function webpay_disclaimer() {
    $page = get_page_by_title('Condiciones de Compra y Devoluciones');
    $texto = "Recuerda que al proceder con la compra estás de acuerdo con las <a href=" . get_page_link($page->ID) . "> Condiciones de Compra y Devoluciones de la Emprea. </a>";

    return $texto;
}

add_shortcode('webpay-disclaimer', 'webpay_disclaimer');

function webpay_despacho() {
    $texto = "<b><i>Si elegiste una forma de despacho que no haya sido retiro en local, se recuerda que puede tardar hasta 5 días hábiles en llegar a la dirección de destino dentro del territorio nacional.
        </b></i>";
    return $texto;
}

add_shortcode('webpay-despacho', 'webpay_despacho');

function woocommerce_gateway_webpay_init() {

    if (!class_exists('WC_Payment_Gateway'))
        return;

    /**
     * Localisation
     */
    load_plugin_textdomain('wc-gateway-name', false, dirname(plugin_basename(__FILE__)) . '/languages');

    /**
     * Gateway class
     */
    class Wc_Webpay extends WC_Payment_Gateway {

        public function __construct() {

            if ($_REQUEST['page_id'] == 'xt_compra') {
                add_action('init', array(&$this, 'xt_compra'));
            } else {
                add_action('init', array(&$this, 'check_webpay_response'));
            }

            $this->id = 'webpay';
            $this->has_fields = true;
            // Load the form fields.
            $this->init_form_fields();
            $this->icon = WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)) . '/images/logo.png';
            // Load the settings.
            $this->init_settings();
            // Define user set variables
            $this->title = $this->settings['title'];
            $this->description = $this->settings['description'];
            $this->liveurl = $this->settings['cgiurl'];
            $this->macpath = $this->settings['macpath'];

            //$this -> liveurl = WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)).'/cgi-bin/tbk_bp_pago.cgi';
            $this->redirect_page_id = $this->settings['redirect_page_id'];
            // Actions
            add_action('woocommerce_update_options_payment_gateways', array(&$this, 'process_admin_options'));
            add_action('woocommerce_settings_saved', array(&$this, 'save_tbk_config'));
            add_action('woocommerce_receipt_webpay', array(&$this, 'receipt_page'));
            add_action('woocommerce_thankyou_webpay', array(&$this, 'thankyou_page'));
        }

        function save_tbk_config() {

            if ($_REQUEST['subtab'] == "gateway-webpay") {
                 $content     = file_get_contents(WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)) ."/tbk_config.dat");

                  $content      = str_replace("{idcomercio}",$this->settings['idcomercio'],$content);
                  $content      = str_replace("{medcom}",$this->settings['medcom'],$content);
                  $content      = str_replace("{tbk_key_id}",$this->settings['tbk_key_id'],$content);
                  $content      = str_replace("{vericom}",$this->settings['vericom'],$content);
                  $content      = str_replace("{urlcgicom}",$this->settings['urlcgicom'],$content);
                  $content      = str_replace("{servercom}",$this->settings['servercom'],$content);
                  $content      = str_replace("{portcom}",$this->settings['portcom'],$content);
                  $content      = str_replace("{whitelist}",$this->settings['whitelist'],$content);
                  $content      = str_replace("{host}",$this->settings['host'],$content);
                  $content      = str_replace("{wport}",$this->settings['wport'],$content);
                  $content      = str_replace("{cgitra}",$this->settings['cgitra'],$content);
                  $content      = str_replace("{cgimedtra}",$this->settings['cgimedtra'],$content);
                  $content      = str_replace("{servertra}",$this->settings['servertra'],$content);
                  $content      = str_replace("{porttra}",$this->settings['porttra'],$content);
                  $content      = str_replace("{conf_tra}",$this->settings['conf_tra'],$content);
                  $content      = str_replace("{html_tr_normal}",$this->settings['html_tr_normal'],$content); 

                $content = "IDCOMERCIO = " . $this->settings['idcomercio'] . "\n";
                $content .= "MEDCOM = " . $this->settings['medcom'] . "\n";
                $content .= "TBK_KEY_ID = " . $this->settings['tbk_key_id'] . "\n";
                $content .= "PARAMVERIFCOM = " . $this->settings['vericom'] . "\n";
                $content .= "URLCGICOM = " . $this->settings['urlcgicom'] . "\n";
                $content .= "SERVERCOM = " . $this->settings['servercom'] . "\n";
                $content .= "PORTCOM = " . $this->settings['portcom'] . "\n";
                $content .= "WHITELISTCOM = " . $this->settings['whitelist'] . "\n";
                $content .= "HOST = " . $this->settings['host'] . "\n";
                $content .= "WPORT = " . $this->settings['wport'] . "\n";
                $content .= "URLCGITRA = " . $this->settings['cgitra'] . "\n";
                $content .= "URLCGIMEDTRA = " . $this->settings['cgimedtra'] . "\n";
                $content .= "SERVERTRA = " . $this->settings['servertra'] . "\n";
                $content .= "PORTTRA = " . $this->settings['porttra'] . "\n";
                $content .= "PREFIJO_CONF_TR = " . $this->settings['conf_tra'] . "\n";
                $content .= "HTML_TR_NORMAL = " . get_option('siteurl') . "/?page_id=xt_compra&pay=webpay" . "\n";

//                              echo $content;

                $myFile = $this->settings['macpath'] . "/datos/tbk_config.dat";
                $fh = fopen($myFile, 'w') or die("can't open file");
                fwrite($fh, $content);
                fclose($fh);

                //exit;
            }
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
            <h3><?php _e('WebPay', 'woocommerce'); ?></h3>
            <p><?php _e('Permite pagar en Chile con WebPay', 'woocommerce'); ?></p>
            <table class="form-table">
                <?php $this->generate_settings_html(); ?>
            </table>
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
                echo wpautop(wptexturize($description));
        }

        function receipt_page($order) {
            echo '<p>' . __('Gracias por tu pedido, por favor haz click a continuación para pagar con webpay', 'woocommerce') . '</p>';
            echo $this->generate_webpay_form($order);
        }

        function process_payment($order_id) {
            $order = &new WC_Order($order_id);
            return array('result' => 'success', 'redirect' => add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(get_option('woocommerce_pay_page_id'))))
            );
        }

        /**
         * Generate Webpay plus button link
         * */
        public function generate_webpay_form($order_id) {
            global $woocommerce;
            $order = &new WC_Order($order_id);
            $redirect_url = ($this->redirect_page_id == "" || $this->redirect_page_id == 0) ? get_site_url() . "/" : get_permalink($this->redirect_page_id);
            $order_id = $order_id;
            $order_key = $order->order_key;

            $permalinkStructure = get_option('permalink_structure');

            if (!empty($permalinkStructure))
                $queryStr = '?';
            else
                $queryStr = '&';


            $TBK_MONTO = round($order->order_total);
            $TBK_ORDEN_COMPRA = $order_id;
            $TBK_ID_SESION = date("Ymdhis");

            mkdir(dirname(__FILE__), 0777);
            chmod(dirname(__FILE__), 0777);

            //Archivos de datos para uso de pagina de cierre                    
            if (!is_dir(dirname(__FILE__) . "/comun")) {
                mkdir(dirname(__FILE__) . "/comun", 0777);
                chmod(dirname(__FILE__) . "/comun", 0777);
            }

            $myPath = dirname(__FILE__) . "/comun/dato$TBK_ID_SESION.log";
            /*             * **************** FIN CONFIGURACION **************** */
            //formato Moneda
            $partesMonto = split(",", $TBK_MONTO);
            $TBK_MONTO = $partesMonto[0] . "00";
            //Grabado de datos en archivo de transaccion
            $fic = fopen($myPath, "w+");
            $linea = "$TBK_MONTO;$TBK_ORDEN_COMPRA";
            fwrite($fic, $linea);
            fclose($fic);


            $ccavenue_args = array(
                'TBK_TIPO_TRANSACCION' => "TR_NORMAL",
                'TBK_MONTO' => $TBK_MONTO,
                'TBK_ORDEN_COMPRA' => $TBK_ORDEN_COMPRA,
                'TBK_ID_SESION' => $TBK_ID_SESION,
                'TBK_URL_EXITO' => $redirect_url . $queryStr . "status=success&order=$order_id&key=$order_key",
                'TBK_URL_FRACASO' => $redirect_url . $queryStr . "status=failure&order=$order_id&key=$order_key",
            );

            $woopayment = array();
            foreach ($ccavenue_args as $key => $value) {
                $woopayment[] = "<input type='hidden' name='$key' value='$value'/>";
            }

            return '<form action="' . $this->liveurl . '" method="post" id="webpayplus">
                ' . implode('', $woopayment) . '
                <input type="submit" class="button" id="submit_webpayplus_payment_form" value="Pagar" /> <a class="button cancel" href="' . $order->get_cancel_order_url() . '">Cancel</a>
                <script type="text/javascript">
jQuery(function(){
    jQuery("body").block(
            {
                message: "<img src=\"' . $woocommerce->plugin_url() . '/assets/images/ajax-loader.gif\" alt=\"Redirecting�\" style=\"float:left; margin-right: 10px;\" />' . __('Thank you for your order. We are now redirecting you to Webpay to make payment.', 'mrova') . '",
                    overlayCSS:
            {
                background: "#fff",
                    opacity: 0.6
        },
        css: {
            padding:        20,
                textAlign:      "center",
                color:          "#555",
                border:         "3px solid #aaa",
                backgroundColor:"#fff",
                cursor:         "wait",
                lineHeight:"32px"
        }
        });
        jQuery("#submit_webpayplus_payment_form").click();

        });
                    </script>
                </form>';
        }

        /*
         * End CCAvenue Essential Functions
         * */

        // get all pages
        function get_pages($title = false, $indent = true) {
            $wp_pages = get_pages('sort_column=menu_order');
            $page_list = array();
            if ($title)
                $page_list[] = $title;
            foreach ($wp_pages as $page) {
                $prefix = '';
                // show indented child pages?
                if ($indent) {
                    $has_parent = $page->post_parent;
                    while ($has_parent) {
                        $prefix .= ' - ';
                        $next_page = get_page($has_parent);
                        $has_parent = $next_page->post_parent;
                    }
                }
                // add to page list array array
                $page_list[$page->ID] = $prefix . $page->post_title;
            }
            return $page_list;
        }

        /**
         *      Check payment response from web pay plus
         * */
        function check_webpay_response() {
            global $woocommerce;


            if ($_REQUEST['TBK_ORDEN_COMPRA'] and $_REQUEST['TBK_ID_SESION']) {
                $order_id_time = $_REQUEST['order'];
                $order_id = explode('_', $_REQUEST['order']);
                $order_id = (int) $order_id[0];

                if ($order_id != '') {
                    try {
                        $order = new WC_Order($order_id);

                        $status = $_REQUEST['status'];
                        if ($order->status !== 'completed') {
                            if ($status == 'success') {
                                /* $order -> payment_complete();
                                  $woocommerce -> cart -> empty_cart();
                                  $order -> update_status('completed'); */

                                // Mark as on-hold (we're awaiting the cheque)
                                $order->update_status('on-hold');

                                // Reduce stock levels
                                $order->reduce_order_stock();

                                // Remove cart
                                $woocommerce->cart->empty_cart();

                                // Empty awaiting payment session
                                unset($_SESSION['order_awaiting_payment']);
//
//                                log_me('START WEBPAY RESPONSE ARRAY REQUEST');
//                                log_me($_REQUEST);
//                                log_me('END WEBPAY RESPONSE ARRAY REQUEST');
                                //RESCATO EL ARCHIVO
                                $TBK_ID_SESION
                                        = $_POST["TBK_ID_SESION"];
                                $TBK_ORDEN_COMPRA
                                        = $_POST["TBK_ORDEN_COMPRA"];
                                /*                                 * **************** CONFIGURAR AQUI ****************** */


                                //Archivo previamente generado para rescatar la información.
                                $myPath = dirname(__FILE__) . "/comun/MAC01Normal$TBK_ID_SESION.txt";
                                /*                                 * **************** FIN CONFIGURACION **************** */
                                //Rescate de los valores informados por transbank
                                $fic = fopen($myPath, "r");
                                $linea = fgets($fic);
                                fclose($fic);
                                $detalle = explode("&", $linea);

                                $TBK = array(
                                    'TBK_ORDEN_COMPRA' => explode("=", $detalle[0]),
                                    'TBK_TIPO_TRANSACCION' => explode("=", $detalle[1]),
                                    'TBK_RESPUESTA' => explode("=", $detalle[2]),
                                    'TBK_MONTO' => explode("=", $detalle[3]),
                                    'TBK_CODIGO_AUTORIZACION' => explode("=", $detalle[4]),
                                    'TBK_FINAL_NUMERO_TARJETA' => explode("=", $detalle[5]),
                                    'TBK_FECHA_CONTABLE' => explode("=", $detalle[6]),
                                    'TBK_FECHA_TRANSACCION' => explode("=", $detalle[7]),
                                    'TBK_HORA_TRANSACCION' => explode("=", $detalle[8]),
                                    'TBK_ID_TRANSACCION' => explode("=", $detalle[10]),
                                    'TBK_TIPO_PAGO' => explode("=", $detalle[11]),
                                    'TBK_NUMERO_CUOTAS' => explode("=", $detalle[12]),
                                        //'TBK_MAC' => explode("=", $detalle[13]),
                                );

//                                log_me("INICIO INFO PARA AGREGAR A LA DB EN CHECK RESPONSE");
//                                log_me($TBK);  
//                                log_me("FIN INFO PARA AGREGAR A LA DB EN CHECK RESPONSE");
//                                
                                log_me("INSERTANDO EN LA BDD");
                                woocommerce_payment_complete_add_data_webpay($order_id, $TBK);
                                log_me("TERMINANDO INSERSIÓN");
                            } elseif ($status == 'failure') {
                                $order->update_status('failed');
                                $order->add_order_note('Failed');

                                //Si falla no limpio el carrito para poder pagar nuevamente
                                //$woocommerce->cart->empty_cart();
                            }
                        } else {
                            $this->msg = 'order already completed.';
                            add_action('the_content', array(&$this, 'thankyouContent'));
                        }
                    } catch (Exception $e) {
                        // $errorOccurred = true;
                        $this->msg = "Error occured while processing your request";
                    }
                    //add_action('the_content', array(&$this, 'thankyouContent'));
                }
            }
        }

        /**
         * code to display success message on success page
         * */
        function thankyouContent($content) {
            echo $this->msg;
        }

        // Go wild in here
    }

    /**
     * Add the Gateway to WooCommerce
     * */
    function woocommerce_add_gateway_webpay_gateway($methods) {
        $methods[] = 'Wc_Webpay';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'woocommerce_add_gateway_webpay_gateway');

    function woocommerce_payment_complete_add_data_webpay($order_id, $TBK) {
        global $webpay_table_name;
        global $wpdb;

        log_me("idOrden : ");
        log_me($order_id);
        log_me('TBK:');
        log_me($TBK);
        $rows_affected = $wpdb->insert($webpay_table_name, array(
            'idOrder' => $order_id,
            'TBK_ORDEN_COMPRA' => $TBK['TBK_ORDEN_COMPRA'][1],
            'TBK_TIPO_TRANSACCION' => $TBK['TBK_TIPO_TRANSACCION'][1],
            'TBK_RESPUESTA' => $TBK['TBK_RESPUESTA'][1],
            'TBK_MONTO' => $TBK['TBK_MONTO'][1],
            'TBK_CODIGO_AUTORIZACION' => $TBK['TBK_CODIGO_AUTORIZACION'][1],
            'TBK_FINAL_NUMERO_TARJETA' => $TBK['TBK_FINAL_NUMERO_TARJETA'][1],
            'TBK_FECHA_CONTABLE' => $TBK['TBK_FECHA_CONTABLE'][1],
            'TBK_FECHA_TRANSACCION' => $TBK['TBK_FECHA_TRANSACCION'][1],
            'TBK_HORA_TRANSACCION' => $TBK['TBK_HORA_TRANSACCION'][1],
            'TBK_ID_TRANSACCION' => $TBK['TBK_ID_TRANSACCION'][1],
            'TBK_TIPO_PAGO' => $TBK['TBK_TIPO_PAGO'][1],
            'TBK_NUMERO_CUOTAS' => $TBK['TBK_NUMERO_CUOTAS'][1],
                )
        );
    }

    add_action('woocommerce_payment_complete', 'woocommerce_payment_complete_add_data_webpay', 10, 1);

    #CON RESPECTO A ACEPTAR LOS COMPROMISOS DE COMPRA
    // ADMIN
    add_action('woocommerce_settings_page_options', 'service_agreement');
    add_action('woocommerce_update_options_pages', 'save_service_agreement');

    function service_agreement() {

        woocommerce_admin_fields(array(
            array(
                'name' => __('Service Agreement Page ID', 'woocommerce'),
                'desc' => __('If you define a \'Service Agreement\' page the customer will be asked if they agree to it when checking out.', 'woocommerce'),
                'tip' => '',
                'id' => 'woocommerce_service_agreement_page_id',
                'std' => '',
                'class' => 'chosen_select_nostd',
                'css' => 'min-width:300px;',
                'type' => 'single_select_page',
                'desc_tip' => true,
            ),
        ));
    }

    function save_service_agreement() {

        if (isset($_POST['woocommerce_service_agreement_page_id'])) :
            update_option('woocommerce_service_agreement_page_id', woocommerce_clean($_POST['woocommerce_service_agreement_page_id']));
        else :
            delete_option('woocommerce_service_agreement_page_id');
        endif;
    }

    //FRONTEND


    add_action('woocommerce_review_order_after_submit', 'add_service_agreement_checkbox');
    add_action('woocommerce_checkout_process', 'check_service_agreement');

    function add_service_agreement_checkbox() {

        global $woocommerce;
        if (woocommerce_get_page_id('service_agreement') > 0) :
            ?>
            <br class="clear" /> 
            <p class="form-row service_agreement">
                <label for="service_agreement" class="checkbox"><?php _e('Acepto las', 'woocommerce'); ?> <a href="<?php echo esc_url(get_permalink(woocommerce_get_page_id('service_agreement'))); ?>" target="_blank"><?php _e('Condiciones de Compra y Devoluciones de la Empresa.', 'woocommerce'); ?></a></label>
                <input type="checkbox" class="input-checkbox" name="service_agreement" <?php if (isset($_POST['service_agreement'])) echo 'checked="checked"'; ?> id="service_agreement" />
            </p>
            
            <?php
        endif;
    }

    function check_service_agreement() {

        global $woocommerce;
        if (!isset($_POST['woocommerce_checkout_update_totals']) && empty($_POST['service_agreement']) && woocommerce_get_page_id('service_agreement') > 0) :

            $woocommerce->add_error(__('Debes de Aceptar los terminos de Compra de la empresa.', 'woocommerce'));

        endif;
    }

}
?>
