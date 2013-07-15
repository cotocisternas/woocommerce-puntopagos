<?php
/*
  Plugin Name: Woocommerce Webpay ( Chilean Payment Gateway )
  Description: Sistema de pagos de WooCommerce con WebPay
  Author: Cristian Tala Sánchez
  Version: 2.1
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

/*
 * Este activation Hook crea una tabla en la base de datos para mantener el registro
 * de las transacciones.
 */
register_activation_hook(__FILE__, 'webpay_install');
add_action('plugins_loaded', 'init_woocommerce_webpay');

/*
 * Se agrega nuestro Gateway de pago al array que posee WooCommerce
 */

function add_webpay_gateway_class($methods) {
    $methods[] = 'WC_WebPay';
    return $methods;
}

add_filter('woocommerce_payment_gateways', 'add_webpay_gateway_class');

/*
 * Se crea la clase de conexión
 */

function init_woocommerce_webpay() {

    /*
     * Si la clase de la cual quiero heredar no existe no hago nada.
     */
    if (!class_exists('WC_Payment_Gateway'))
        return;

    class WC_Webpay extends WC_Payment_Gateway {

        /**
         * Constructor for the gateway.
         *
         * @access public
         * @return void
         */
        public function __construct() {

            if (isset($_REQUEST['page_id'])):
                if ($_REQUEST['page_id'] == 'xt_compra') 
				{		
					add_action( 'woocommerce_api_' . strtolower( get_class( $this ) ), array( $this, 'xt_compra' ) );				
                } else {
                    //add_action('init', array(&$this, 'check_webpay_response'));
					$this->check_webpay_response();
                }
            endif;			
            $this->id = 'webpay';
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


            $this->redirect_page_id = $this->settings['redirect_page_id'];

// Actions
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(&$this, 'process_admin_options'));
            //add_action('woocommerce_thankyou_webpay', array(&$this, 'thankyou_page'));
			add_action('woocommerce_thankyou_webpay', array(&$this, 'thankyousuccess_page'));
            add_action('woocommerce_receipt_webpay', array(&$this, 'receipt_page'));
// Customer Emails
// add_action('woocommerce_email_before_order_table', array($this, 'email_instructions'), 10, 2);
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
                    'label' => __('Habilita Woocommerce Webpay Plus', 'woocommerce'),
                    'default' => 'yes'
                ),
                'title' => array(
                    'title' => __('Title', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('', 'woocommerce'),
                    'default' => __('Web Pay Plus', 'woocommerce')
                ),
                'description' => array(
                    'title' => __('Customer Message', 'woocommerce'),
                    'type' => 'textarea',
                    'description' => __('Give the customer instructions for paying via BACS, and let them know that their order won\'t be shipping until the money is received.', 'woocommerce'),
                //'default' => __('Make your payment directly into our bank account. Please use your Order ID as the payment reference. Your order wont be shipped until the funds have cleared in our account.', 'woocommerce')
                ),
                'account_details' => array(
                    'title' => __('Detalles de WebPay', 'woocommerce'),
                    'type' => 'title',
                    'description' => __('Optionally enter your bank details below for customers to pay into.', 'woocommerce'),
                    'default' => ''
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
                    'description' => __('url like : /usr/lib/cgi-bin/', 'woocommerce'),
                    'default' => __('/usr/lib/cgi-bin/', 'woocommerce')
                ),
                'redirect_page_id' => array(
                    'title' => __('Return Page'),
                    'type' => 'select',
                    'options' => $this->get_pages('Select Page'),
                    'description' => "URL of success page"
                ),
				'trade_name' => array(
                    'title' => __('Nombre del Comercio', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('Trade Name like : EmpresasCTM', 'woocommerce')
                ),
				'url_commerce' => array(
                    'title' => __('URL Comercio', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('Url Commerce like : http://www.empresasctm.cl', 'woocommerce')
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

            if (isset($_REQUEST['status'])):

                if ($_REQUEST['status'] == "failure"):
                    echo '<h2>' . __('Un error ha ocurrido', 'webpay') . '</h2>';

                    $TBK_ID_SESION
                            = $_POST["TBK_ID_SESION"];
                    $TBK_ORDEN_COMPRA
                            = $_POST["TBK_ORDEN_COMPRA"];
                    ?>

                    <CENTER>
                        <B>TRANSACCIÓN FRACASADA !!!</B>
                        <TABLE>
                            <TR><TH>FRACASO</TH></TR>
                            <TR><TD>
                                    TBK_ID_SESION=<?php ECHO $TBK_ID_SESION; ?><BR>
                                    TBK_ORDEN_COMPRA=<?php ECHO $TBK_ORDEN_COMPRA; ?><BR>
                                </TD></TR>
                        </TABLE>
                    </CENTER>

                    <?
                else:
                    ?>

                    <CENTER>
                        <B>TRANSACCIÓN Exitosa !!!</B>
                        <TABLE>
                            <TR><TH>Éxito</TH></TR>
                            <TR><TD>
                                    TBK_ID_SESION=<?php ECHO $TBK_ID_SESION; ?><BR>
                                    TBK_ORDEN_COMPRA=<?php ECHO $TBK_ORDEN_COMPRA; ?><BR>
                                </TD></TR>
                        </TABLE>
                    </CENTER>

                    <?
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
                endif;
            endif;
        }
		
		function thankyousuccess_page() {
		  if (isset($_REQUEST['status'])):
		  if ($_REQUEST['status'] == "failure"):
			echo '<h2>' . __('Un error ha ocurrido', 'webpay') . '</h2>';

			$TBK_ID_SESION
					= $_REQUEST["TBK_ID_SESION"];
			$TBK_ORDEN_COMPRA
					= $_REQUEST["TBK_ORDEN_COMPRA"];
			?>
                    <CENTER>
                        <B>TRANSACCIÓN FRACASADA !!!</B>
                        <TABLE>
                            <TR><TH>FRACASO</TH></TR>
                            <TR><TD>
                                    TBK_ID_SESION=<?php echo $TBK_ID_SESION; ?><BR>
                                    TBK_ORDEN_COMPRA=<?php echo $TBK_ORDEN_COMPRA; ?><BR>
                                </TD></TR>
                        </TABLE>
                    </CENTER>
		  <?php
		  else:
		  //IF IT IS A WEBPAY PAYMENT
		  global $webpay_table_name;
		  global $wpdb; 
		  $order_id = explode('_', $_REQUEST['order']);
          $order_id = (int) $order_id[0];
		  $paramArr = array();
		  $myOrderDetails = $wpdb->get_row("SELECT * FROM $webpay_table_name WHERE idOrder = $order_id", ARRAY_A);
		  if ($myOrderDetails):
        ?>
			<h2 class="related_products_title order_confirmed"><?= "Información Extra de la Transacción"; ?></h2>
			<div class="clear"></div>
			<table class="shop_table order_details">
				<thead>
					<tr>
						<th class="product-name"><?php echo "Dato" ?></th>
						<th class="product-quantity"><?php echo "Valor"; ?></th>
	
	
					</tr>
				</thead>
				<tfoot>
	
					<tr>
						<th>Tipo de Transacción</th>
						<th>Venta</th>
	
					</tr>
					<tr>
						<th>Nombre del Comercio</th>
						<th><?php echo $this->settings['trade_name']; ?></th>
	
					</tr>
					<tr>
						<th>URL Comercio</th>
						<th><?php echo $this->settings['url_commerce']; ?></th>
	
					</tr>
	
					<tr>
						<th>Código de Autorización</th>
						<th><?php echo $myOrderDetails['TBK_CODIGO_AUTORIZACION'] ?></th>
	
	
					</tr>
	
					<tr>
						<th>Final de Tarjeta</th>
						<th><?php echo $myOrderDetails['TBK_FINAL_NUMERO_TARJETA'] ?></th>
	
	
					</tr>
	
					<tr>
						<th>Tipo de pago</th>
						<th><?php 
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
	
					<?php 
					if (!($myOrderDetails['TBK_TIPO_PAGO'] == "VD") || true):
						?>
						<tr>
							<th>Número de Cuotas</th>
							<th><?php 
								if (!($myOrderDetails['TBK_NUMERO_CUOTAS'] == "0")) {
									echo $myOrderDetails['TBK_NUMERO_CUOTAS'];
								} else {
									echo "00";
								}
								?></th>
	
						</tr>
						<?php 
					endif;
					?>
				</tfoot>
			</table>
			<?php
			endif;
			endif;
			endif;
		}

        function receipt_page($order) {
            echo '<p>' . __('Gracias por tu pedido, por favor haz click a continuación para pagar con webpay', 'woocommerce') . '</p>';
            echo $this->generate_webpay_form($order);
        }

        //        function thankyou_page() {
        //            if ($description = $this->get_description())
        //                echo wpautop(wptexturize($description));
        //        }
        //
    //        function receipt_page($order) {
        //            echo '<p>' . __('Gracias por tu pedido, por favor haz click a continuación para pagar con webpay', 'woocommerce') . '</p>';
        //            echo $this->generate_webpay_form($order);
        //        }

        function process_payment($order_id) {
            $sufijo = "[WEBPAY - PROCESS - PAYMENT]";
            log_me("Iniciando el proceso de pago para $order_id",$sufijo);
            $order = &new WC_Order($order_id);
            return array('result' => 'success', 'redirect' => add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(get_option('woocommerce_pay_page_id'))))
            );
        }

        public function generate_webpay_form($order_id) {
            global $woocommerce;
            $SUFIJO = "[WEBPAY - FORM]";

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

            $filename = __FILE__;
//            if(file_exists($filename)):
//                if(!is_dir($filename))
//                {
//                    mkdir(dirname($filename), 0777);
//                    chmod(dirname($filename), 0777);
//                }
//            endif;
            //Archivos de datos para uso de pagina de cierre

            log_me("Entrando a la verificación de carpetas", $SUFIJO);
            if (!is_dir(dirname($filename) . "/comun")) {
                mkdir(dirname($filename) . "/comun", 0777);
                chmod(dirname($filename) . "/comun", 0777);
            }

            $myPath = dirname(__FILE__) . "/comun/dato$TBK_ID_SESION.log";

            log_me("Se utilizará $myPath para guardar los datos", $SUFIJO);
            /*             * **************** FIN CONFIGURACION **************** */
            //formato Moneda
            $partesMonto = split(",", $TBK_MONTO);
            $TBK_MONTO = $partesMonto[0] . "00";
            //Grabado de datos en archivo de transaccion
            $fic = fopen($myPath, "w+");
            $linea = "$TBK_MONTO;$TBK_ORDEN_COMPRA";

            log_me("Preparando para escribir $linea en $myPath", $SUFIJO);
            fwrite($fic, $linea);
            fclose($fic);
            log_me("ARCHIVO CERRADO", $SUFIJO);

            log_me("Argumentos",$SUFIJO);
            $ccavenue_args = array(
                'TBK_TIPO_TRANSACCION' => "TR_NORMAL",
                'TBK_MONTO' => $TBK_MONTO,
                'TBK_ORDEN_COMPRA' => $TBK_ORDEN_COMPRA,
                'TBK_ID_SESION' => $TBK_ID_SESION,
                'TBK_URL_EXITO' => $redirect_url . $queryStr . "status=success&order=$order_id&key=$order_key",
                'TBK_URL_FRACASO' => $redirect_url . $queryStr . "status=failure&order=$order_id&key=$order_key",
            );
            log_me($ccavenue_args);
            
            
            $woopayment = array();
            foreach ($ccavenue_args as $key => $value) {
                $woopayment[] = "<input type='hidden' name='$key' value='$value'/>";
            }

            return '<form action="' . $this->liveurl . '" method="post" id="webpayplus">
        ' . implode('', $woopayment) . '
        <input type="submit" class="button" id="submit_webpayplus_payment_form" value="Pagar" /> <a class="button cancel" href="' . $order->get_cancel_order_url() . '">Cancel</a>
        <script type="text/javascript">
            jQuery(function() {
                jQuery("body").block(
                        {
                            message: "<img src=\"' . $woocommerce->plugin_url() . '/assets/images/ajax-loader.gif\" alt=\"Redirecting�\" style=\"float:left; margin-right: 10px;\" />' . __('Thank you for your order. We are now redirecting you to Webpay to make payment.', 'mrova') . '",
                            overlayCSS:
                                    {
                                        background: "#fff",
                                        opacity: 0.6
                                    },
                            css: {
                                padding: 20,
                                textAlign: "center",
                                color: "#555",
                                border: "3px solid #aaa",
                                backgroundColor: "#fff",
                                cursor: "wait",
                                lineHeight: "32px"
                            }
                        });
                jQuery("#submit_webpayplus_payment_form").click();

            });
        </script>
    </form>';
        }

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
            $SUFIJO = "[WEBPAY - RESPONSE]";

            log_me("Entrando al Webpay Response", $SUFIJO);

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

                                log_me('START WEBPAY RESPONSE ARRAY REQUEST', $SUFIJO);
                                log_me($_REQUEST);
                                log_me('END WEBPAY RESPONSE ARRAY REQUEST', $SUFIJO);
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
            } else {
                log_me("FALTAN PARAMETROS", $SUFIJO);
            }
            log_me("SALIENDO DEL RESPONSE", $SUFIJO);
        }

        /**
         * Process the payment and return the result
         *
         * @access public
         * @param int $order_id
         * @return array
         */
        //            function process_payment($order_id) {
        //                global $woocommerce;
        //
    //                $order = new WC_Order($order_id);
        //
    //                // Mark as on-hold (we're awaiting the payment)
        //                $order->update_status('on-hold', __('Awaiting BACS payment', 'woocommerce'));
        //
    //                // Reduce stock levels
        //                $order->reduce_order_stock();
        //
    //                // Remove cart
        //                $woocommerce->cart->empty_cart();
        //
    //                // Return thankyou redirect
        //                return array(
        //                    'result' => 'success',
        //                    'redirect' => add_query_arg('key', $order->order_key, add_query_arg('order', $order->id, get_permalink(woocommerce_get_page_id('thanks'))))
        //                );
        //            }


        function thankyouContent($content) {
            //echo $this->msg;
        }

        public function xt_compra() {
            global $webpay_table_name;
            global $wpdb;
            global $woocommerce;
            $sufijo = "[XT_COMPRA]";
            log_me("Iniciando xt_compra",$sufijo);

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

            if ($TBK_RESPUESTA == -1)
                die("ACEPTADO");

            //Validar que la orden exista         
            $order = new WC_Order($order_id);
            log_me($order->status, $sufijo);

            //Si la orden de compra no tiene status es debido a que no existe

            if ($order->status == '') 
			{
                log_me("ORDEN NO EXISTENTE " . $order_id, $sufijo);
                die('RECHAZADO');
            } 
			else 
			{
                log_me("ORDEN EXISTENTE " . $order_id, $sufijo);
                //CUANDO UNA ORDEN ES PAGADA SE VA A ON HOLD.

                if ($order->status == 'completed') 
				{
                    log_me("ORDEN YA PAGADA (COMPLETED) EXISTENTE " . $order_id, "\t" . $sufijo);
                    die('RECHAZADO');
                } 
				else 
				{

                    if ($order->status == 'pending' || $order->status == 'failed') 
					{
                        log_me("ORDEN DE COMPRA NO PAGADA (PENDING). Se procede con el pago de la orden " . $order_id, $sufijo);
                    } else 
					{
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
                    $acepta = true;
                else
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
            log_me("FINALIZANDO XT_COMPRA",$sufijo);
        }

    }

    //End of the GateWay Class

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
}
?>
