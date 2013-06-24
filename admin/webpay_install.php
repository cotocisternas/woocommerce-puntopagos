<?php

include_once 'webpay_debug.php';

global $wpdb;

global $webpay_db_version;
$webpay_db_version = "1.0";

global $webpay_table_name;
$webpay_table_name = $wpdb->prefix . "webpay";

function webpay_install() {
    global $wpdb;
    global $webpay_db_version;
    global $webpay_table_name;



    $sql = "CREATE TABLE $webpay_table_name (
  id mediumint(9) NOT NULL AUTO_INCREMENT,
  idOrder INT NOT NULL,
  TBK_ORDEN_COMPRA INT NOT NULL,
  TBK_TIPO_TRANSACCION text NOT NULL,
  TBK_RESPUESTA  INT(2) NOT NULL,
  TBK_MONTO INT NOT NULL,
  TBK_CODIGO_AUTORIZACION INT NOT NULL,
  TBK_FINAL_NUMERO_TARJETA INT(4) NOT NULL,
  TBK_FECHA_CONTABLE INT(8) NOT NULL,
  TBK_FECHA_TRANSACCION INT(8) NOT NULL,
  TBK_HORA_TRANSACCION INT(6) NOT NULL,
  TBK_ID_TRANSACCION INT(20) NOT NULL,
  TBK_TIPO_PAGO VARCHAR(10) NOT NULL,
  TBK_NUMERO_CUOTAS INT(2) NOT NULL,
  UNIQUE KEY id (id)
    );";

    log_me($sql);
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    add_option("webpay_db_version", $webpay_db_version);
}

?>
