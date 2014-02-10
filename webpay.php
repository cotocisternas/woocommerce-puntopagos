<?php
/*
  Plugin Name: Woocommerce Webpay ( Chilean Payment Gateway )
  Description: Sistema de pagos de WooCommerce con WebPay
  Author: Cristian Tala Sánchez
  Contribuidores: Felipe Egas
  Version: 2.4.1
  Author URI: www.cristiantala.cl
  Plugin URI: https://bitbucket.org/ctala/woocommerce-webpay/wiki/Home
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
add_shortcode('webpay_thankyou', 'webpayThankYou');




/*
 * La siguiente función  hace posible filtrar el acceso a la página de éxito de woocommerce.
 */

function webpayThankYou() {
global $woocommerce;
 $SUFIJO = "[PuntoPagos-THANKYOU]";
    log_me("Entrando al ThankYouPage", $SUFIJO);
	/*
		Reviso si al menos tengo los parametros post pasados por la respuesta de transbank.
		Los parametros retornados por post son los siguientes:
		[TBK_ID_SESION]
		[TBK_ORDEN_COMPRA]
	*/

    if (!(isset($_POST['TBK_ID_SESION']) && isset($_POST['TBK_ORDEN_COMPRA']) )) {
		//Esto significa que no viene del método POST
		 log_me("Método POST invalido", $SUFIJO);
		echo "<p>No puedes acceder a esta página de manera directa.</p>";		
		return;	
	}
	
    /*
     * Revisamos si los parametros necesarios para ver la página existen.
     * El plugin en general pasa los parametros de id de la orden, la llave y el status.
     */

    if (!(isset($_GET['order']) && isset($_GET['status']) && isset($_GET['key']))) {
      ?>
      <p><?= "No puedes acceder a esta página de manera directa"; ?></p>

      <?
      break;
    } else {
        /*
         * Si los parametros existen muestro la información si es que el status no es fallido.
         * Además de la recepción, se que es fallido cuando NO es pagado.
         * Para los siguientes status NO puedo mostrar esta página si es que el pago es con webpay.
         * 
          - Pending – Order received (unpaid)
          - Failed – Payment failed or was declined (unpaid)
          - On-Hold – Awaiting payment – stock is reduced, but you need to confirm payment
          - Cancelled – Cancelled by an admin or the customer – no further action required
          - Refunded – Refunded by an admin - no further action required
         * 
         * Por lo que solo se puede mostrar si la orden está completada o en proceso Si es que la orden es exitosa.
         * 
         */
          $order_id = explode('_', $_GET['order']);
          $order_id = (int) $order_id[0];

          if (!is_numeric($order_id)) {
            echo "<p>Acaba de ocurrir un error tratando de recuperar la información de la orden</p>";
          }
          $order = new WC_Order($order_id);

          if ($order) {
            log_me("ORDEN EXISTENTE", $SUFIJO);
		//Reviso el key de la orden.
		$esValida = $order->key_is_valid($_GET['key']);
		if(!$esValida)
			{	
				log_me("KEY INGRESADA NO VALIDA", $SUFIJO);
				echo "<p>Acaba de ocurrir un error tratando de recuperar la información de la orden</p>";
				return;
			}
            if (in_array($order->status, array('failed'))) {
              log_me("La orden está fallida, se carga la página de manera normal", $SUFIJO);
              echo do_shortcode('[woocommerce_thankyou]');
            } else {
                /*
                 * Debo corroborar que la orden este en proceso o completada si el pago es con webpay.
                 */
                log_me("La transacción no debería ser fallida, se verifica", $SUFIJO);
                $paymentMethod = $order->order_custom_fields[_payment_method][0];
                if ($paymentMethod == "webpay") {
                  log_me("\t -> El pago de la orden fue con WebPay", $SUFIJO);
                  

                  if ($order->status == "completed" || $order->status == "processing") {
                        /*
                         * Se carga el template del plugin para la página de éxito solo si es una compra con webpay
                         */
//                        echo "WEBPAY PAYMENT";
                        include_once plugin_dir_path(__FILE__) . '/php/templates/thankyou.php';
                        /*
                         * Se incluyen los detalles de la orden incluyendo el precio unitario según es requerido por transbank
                         */
                        include_once plugin_dir_path(__FILE__) . '/php/templates/order-details.php';
                        echo "<br><p>Recuerda que tus productos serán enviados por correo a tu domicilio si es que seleccionaste este medio de despacho y deberían estar entre 2 y 3 días hábiles a tu hogar.</p>";
                      } else {
                        /*
                         * Si este es el caso, se está intentando acceder a la página sin pasar por el ciclo regular.
                         */
                        echo "<h2>No te encuentras autorizado para acceder a esta página</h2>";
                        return;
                      }
                    } else {
                      log_me("\t -> El pago de la orden NO fue con WebPay o no se a realizado: $paymentMethod ", $SUFIJO);

                      if ($paymentMethod !== null) {
                        echo do_shortcode('[woocommerce_thankyou]');
                      } else {
                        echo "Esta orden aún no ha sido pagada o procesada. Por favor vuleve cuando lo hayas hecho.";
                        return;
                      }
                    }
                  }
                } else {
            //La orden no existía.
                  log_me("ORDEN NO EXISTENTE", $SUFIJO);
                  echo "<p>Lamentablemente la orden buscada no existe o no coincide con la información ingresada</p>";
                  return;
                }
              }
              log_me("Saliendo al ThankYouPage", $SUFIJO);
            }

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
		


            if ($_REQUEST['page_id'] == 'xt_compra') {
              add_action('woocommerce_api_' . strtolower(get_class($this)), array($this, 'xt_compra'));
            } else {

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
		$permalink_actual = get_permalink( );
		$permalink_por_id = get_permalink($this->redirect_page_id);
		
	if(isset($_REQUEST['page_id'])):		
	   if ($_REQUEST['page_id'] == $this->redirect_page_id || $permalink_actual==$permalink_por_id) {
			//Si la página es la de éxito, verifico si la orden está pagada o procesando. Si no termino
		 $order_id = explode('_', $_GET['order']);
          	 $order_id = (int) $order_id[0];

                 if (!is_numeric($order_id)) {
            		wp_redirect( home_url() ); exit;
          		}
          	 $order = new WC_Order($order_id);

		if($order)
			{
				//die("ACEPTADO");
				//Si la orden no está pagada, y trato de ver la página de éxito, rechazo.
				if(!($_REQUEST['status']=="failure"))
					{
						if(!($order->status=="processing"||$order->status=="complete"))
						{
							wp_redirect( home_url() ); exit;
						}
					}
		
			}
			else
			{
				wp_redirect( home_url() ); exit;
			}
	}
	endif;
		//echo $this->redirect_page_id ;
// Actions
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(&$this, 'process_admin_options'));

            add_action('woocommerce_thankyou_webpay', array(&$this, 'thankyousuccess_page'));
            add_action('woocommerce_receipt_webpay', array(&$this, 'receipt_page'));
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
              'label' => __('Habilita Woocommerce Webpay Plus + PuntoPagos', 'woocommerce'),
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
            'url_puntopago' => array(
              'title' => __('URL Comercio', 'woocommerce'),
              'type' => 'text',
              'description' => __('Pagina punto pago', 'woocommerce')
              'default' => __('https://sandbox.puntopagos.com')
              ),
            'key_puntopago' => array(
              'title' => __('URL Comercio', 'woocommerce'),
              'type' => 'text',
              'description' => __('Key punto pago, Ej: kLaQleQUIuwfeXXkxyTC', 'woocommerce')
              'default' => ''
              ),
            'secret_puntopago' => array(
              'title' => __('URL Comercio', 'woocommerce'),
              'type' => 'text',
              'description' => __('Secret token punto pago, Ej: o0PQThCnmmGoWD8KxLARy2MNb577k2IpqFWTMxVC', 'woocommerce')
              'default' => ''
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
          <h3><?php _e('WebPay Plus + PuntoPagos', 'woocommerce'); ?></h3>
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
        function thankyousuccess_page() {
          include_once plugin_dir_path(__FILE__) . '/php/thankYouPage.php';
        }

        function receipt_page($order) {
          echo '<p>' . __('Gracias por tu pedido, por favor haz click a continuación para pagar con webpay y PuntoPagos', 'woocommerce') . '</p>';
          echo $this->generate_webpay_form($order);
        }

        function process_payment($order_id) {
          $sufijo = "[WEBPAY - PROCESS - PAYMENT]";
          log_me("Iniciando el proceso de pago para $order_id", $sufijo);
          $order = &new WC_Order($order_id);
          return array('result' => 'success', 'redirect' => add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(get_option('woocommerce_pay_page_id'))))
            );
        }

        public function generate_webpay_form($order_id) {
          global $woocommerce;
          global $webpay_comun_folder;
          $SUFIJO = "[WEBPAY - FORM]";

          $order = &new WC_Order($order_id);
          $redirect_url = get_site_url() . "/?page_id=" . ($this->redirect_page_id);
          $order_id = $order_id;

          log_me("REDIRECT_URL " . $redirect_url, $SUFIJO);
          $order_key = $order->order_key;


          $TBK_MONTO = round($order->order_total);
          $TBK_ORDEN_COMPRA = $order_id;
          $TBK_ID_SESION = date("Ymdhis");

          $filename = __FILE__;

          $myPath = $webpay_comun_folder . DIRECTORY_SEPARATOR . "dato$TBK_ID_SESION.log";

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

          log_me("Argumentos", $SUFIJO);
          $ccavenue_args = array(
            'TBK_TIPO_TRANSACCION' => "TR_NORMAL",
            'TBK_MONTO' => $TBK_MONTO,
            'TBK_ORDEN_COMPRA' => $TBK_ORDEN_COMPRA,
            'TBK_ID_SESION' => $TBK_ID_SESION,
            'TBK_URL_EXITO' => $redirect_url . "&status=success&order=$order_id&key=$order_key",
            'TBK_URL_FRACASO' => $redirect_url . "&status=failure&order=$order_id&key=$order_key",
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
          global $webpay_comun_folder;
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
                if ($order->status !== 'completed'){


                  /**
                   * aquí es donde se hace la validación para la inyección.
                   * 
                   */
                  //RESCATO EL ARCHIVO
                  $TBK_ID_SESION = $_POST["TBK_ID_SESION"];
                  $TBK_ORDEN_COMPRA = $_POST["TBK_ORDEN_COMPRA"];

                  
                  //Archivo previamente generado para rescatar la información.
                  $myPath = $webpay_comun_folder . DIRECTORY_SEPARATOR . "MAC01Normal$TBK_ID_SESION.txt";
                                                 
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

                  /**
                   * si es una inyección, o sea que no pasa primero por el xt_compra, no se genera archivo
                   * "MAC" entonces siempre los valores darán cero, ademas de ver si el estado es "success"
                   * preguntamos si el en el archivo rescatado existe la orden de compra si es asi pasamos a la pagina de exito
                   * 
                   */
                  if ($status == 'success' &&  $TBK['TBK_ORDEN_COMPRA'][1]==$TBK_ORDEN_COMPRA) {
                    $woocommerce->cart->empty_cart();
                                // Mark as Processing, we already received the money.
                    $order->update_status('processing');

                                // Reduce stock levels
                    $order->reduce_order_stock();

                                // Remove cart
                    $woocommerce->cart->empty_cart();

                                // Empty awaiting payment session
                    unset($_SESSION['order_awaiting_payment']);

                    log_me('START WEBPAY RESPONSE ARRAY REQUEST', $SUFIJO);
                    log_me($_REQUEST);
                    log_me('END WEBPAY RESPONSE ARRAY REQUEST', $SUFIJO);




                    log_me("INSERTANDO EN LA BDD");
                    woocommerce_payment_complete_add_data_webpay($order_id, $TBK);
                    log_me("TERMINANDO INSERSIÓN");

                    /**
                     * en cambio si el status es "failure" o en el archivo MAC no existe la orden de compra, redirigimos a la pagina
                     * de fracaso
                     * 
                     */
                  } elseif ($status == 'failure' || $TBK['TBK_ORDEN_COMPRA'][1]!=$TBK_ORDEN_COMPRA) {
                    $order->update_status('failed');
                    $order->add_order_note('Failed');

                                //Si falla no limpio el carrito para poder pagar nuevamente
                                //$woocommerce->cart->empty_cart();
                  }
                } else {
                  $this->msg = 'Esta orden ya ha sido completada.';
                  add_action('the_content', array(&$this, 'thankyouContent'));
                }
              } catch (Exception $e) {

                $this->msg = "Ha ocurrido un error procesando el pago.";
                add_action('the_content', array(&$this, 'thankyouContent'));
              }
            }
          } else {
            log_me("FALTAN PARAMETROS", $SUFIJO);
          }
          log_me("SALIENDO DEL RESPONSE", $SUFIJO);
        }

        function thankyouContent($content) {
          echo $this->msg;
        }

        public function xt_compra() {
          global $webpay_table_name;
          global $wpdb;
          global $woocommerce;
          global $webpay_comun_folder;
          $sufijo = "[XT_COMPRA]";
          log_me("Iniciando xt_compra", $sufijo);

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

          /**
           * transbank pide que se de "ACEPTADO" si la respuesta está entre -8 y -1
           */
          if ($TBK_RESPUESTA >=-8 && $TBK_RESPUESTA <=-1)
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
                //CUANDO UNA ORDEN ES PAGADA SE VA A PROCESSING.

            if ($order->status == 'completed' || $order->status == 'processing' || $order->status == 'refunded' || $order->status == 'canceled') {
              log_me("ORDEN YA PAGADA (" . $order->status . ") EXISTENTE " . $order_id, "\t" . $sufijo);
              die('RECHAZADO');
            } else {

              if ($order->status == 'pending' || $order->status == 'failed') {
                log_me("ORDEN DE COMPRA NO PAGADA (" . $order->status . "). Se procede con el pago de la orden " . $order_id, $sufijo);
              } else {
                log_me("ORDEN YA PAGADA (" . $order->status . ") EXISTENTE " . $order_id, "\t" . $sufijo);
                die('RECHAZADO');
              }
            }
          }


          /*             * **************** CONFIGURAR AQUI ****************** */
          $myPath = $webpay_comun_folder . DIRECTORY_SEPARATOR . "dato$TBK_ID_SESION.log";
            //GENERA ARCHIVO PARA MAC
          $filename_txt = $webpay_comun_folder . DIRECTORY_SEPARATOR . "MAC01Normal$TBK_ID_SESION.txt";
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
            //Validación de respuesta de Transbank, solo si es 0 continua con la pagina de cierre
          if ($TBK_RESPUESTA == "0") {
            $acepta = true;
          } else {
            $acepta = false;
          }
            //validación de monto y Orden de compra
            //
            //
          if ($TBK_MONTO == $monto && $TBK_ORDEN_COMPRA == $ordenCompra && $acepta == true) {

            /**
             * validamos que la orden de compra no esté repetida preguntando a la base de datos.
             * 
             */
            $res=$wpdb->get_row("SELECT count(*) as total FROM ".$webpay_table_name." WHERE idOrder = ".$TBK_ORDEN_COMPRA, ARRAY_A);


            if($res['total']>0){

              $acepta=false;
            }else{

              $acepta=true;
            }
          } else {
            $acepta = false;
          }

            //Validación MAC
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
            log_me("FINALIZANDO XT_COMPRA", $sufijo);
          }

        }

    //End of the GateWay Class

        function woocommerce_payment_complete_add_data_webpay($order_id, $TBK) {
          global $webpay_table_name;
          global $wpdb;

          $order = new WC_Order($order_id);
          $order->add_order_note("Pago Completado. Transacción : " . $TBK['TBK_CODIGO_AUTORIZACION'][1]);

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
