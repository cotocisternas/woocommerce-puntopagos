<?php
if (isset($_REQUEST['status'])):
    if ($_REQUEST['status'] == "failure"):
        //echo '<h2>' . __('Un error ha ocurrido', 'webpay') . '</h2>';

        $TBK_ID_SESION
                = $_REQUEST["TBK_ID_SESION"];
        $TBK_ORDEN_COMPRA
                = $_REQUEST["TBK_ORDEN_COMPRA"];
        ?>
        <h2>Transacción Fracasada</h2>

        </h3>Las posibles causas de este rechazo son:</h3>

        <ul style="margin-left: 50px">
            <li>      Error en el ingreso de los datos de su tarjeta de crédito o Debito (fecha y/o código de seguridad).</li>
            <li>      Su tarjeta de crédito o debito no cuenta con el cupo necesario para cancelar la compra.</li>
            <li>      Tarjeta aún no habilitada en el sistema financiero. </li>
            <li>      Si el problema persiste favor comunicarse con su Banco emisor. </li>
        </ul>

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
?>
