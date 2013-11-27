# Bienvenido

Bienvenidos al wiki de este plugin. Espero que me ayuden a hacer que este plugin sea el mejor que se pueda encontrar como gateway de pago para WooCommerce con Webpay.

Siempre puedes encontrar la última versión estable por el buscador de plugins de wordpress.
* http://wordpress.org/plugins/webpay-woocommerce-plugin/
* 

##Estado del Arte

Este plugin funciona con las últimas versiones de woocommerce. (Version 2.0.12)

## Descarga


Si quieres editarlo recomiendo descargarlo con git:

```
$ git clone https://ctala@bitbucket.org/ctala/woocommerce-webpay.git
```


Siempre se puede obtener y descargar la última versión de esta página o del siguiente link:

```
https://bitbucket.org/ctala/woocommerce-webpay/get/master.zip
```
 
## DEBUG MODE
Si tienes habilitado en tu instalación de Wordpress el modo debug, el plugin creará mensaje dependiendo de donde se encuentre en el código de manera bien detallada.
Si habilitas el login en wordpress puedes ver todo lo que está ocurriendo dentro del archivo debug.log dentro de la carpeta wp-content.

###Ejemplo del debug.log

```
#!bash

ubuntu@ip-10-147-227-221:/var/www/wp-content$ tail -f debug.log -n 1000 | grep WEBPAY
[25-Jun-2013 16:24:20 UTC] [WEBPAY - FORM]      -> Entrando a la verificación de carpetas
[25-Jun-2013 16:24:20 UTC] [WEBPAY - FORM]      -> Se utilizará /var/www/wp-content/plugins/woocommerce-webpay-2.0/comun/dato20130625042420.log para guardar los datos
[25-Jun-2013 16:24:20 UTC] [WEBPAY - FORM]      -> Preparando para escribir 1000;35 en /var/www/wp-content/plugins/woocommerce-webpay-2.0/comun/dato20130625042420.log
[25-Jun-2013 16:24:20 UTC] [WEBPAY - FORM]      -> ARCHIVO CERRADO
[25-Jun-2013 16:28:26 UTC] [WEBPAY - FORM]      -> Entrando a la verificación de carpetas
[25-Jun-2013 16:28:26 UTC] [WEBPAY - FORM]      -> Se utilizará /var/www/wp-content/plugins/woocommerce-webpay-2.0/comun/dato20130625042826.log para guardar los datos
[25-Jun-2013 16:28:26 UTC] [WEBPAY - FORM]      -> Preparando para escribir 1000;36 en /var/www/wp-content/plugins/woocommerce-webpay-2.0/comun/dato20130625042826.log
[25-Jun-2013 16:28:26 UTC] [WEBPAY - FORM]      -> ARCHIVO CERRADO
[25-Jun-2013 16:35:25 UTC] [WEBPAY - PROCESS - PAYMENT] -> Iniciando el proceso de pago para 37
[25-Jun-2013 16:35:25 UTC] [WEBPAY - FORM]      -> Entrando a la verificación de carpetas
[25-Jun-2013 16:35:25 UTC] [WEBPAY - FORM]      -> Se utilizará /var/www/wp-content/plugins/woocommerce-webpay-2.0/comun/dato20130625043525.log para guardar los datos
[25-Jun-2013 16:35:25 UTC] [WEBPAY - FORM]      -> Preparando para escribir 1000;37 en /var/www/wp-content/plugins/woocommerce-webpay-2.0/comun/dato20130625043525.log

```

Puedes además revisar el código de manera directa en esta página.


# Instalación. #

Una ves descargado el plugin hay dos cosas que tienes que tener en consideración.


1. El plugin creará una carpeta dentro del directorio de uploads llamada webpay-data que contendrá la carpeta  **comun**. Si esta carpeta no se ha creado por favor crearla con permisos de lectura y escritura.

2. Es my importante que se entiendan los conceptos de escritura y lectura de archivos. El script de transbank es ejecutado por el usuario de apache. Si este usuario no tiene permiso de acceso (Lectura y escritura ) a los archivos creados en la carpeta común, estos tendrán un problema de validación en las últimas etapas.   

3. Debes de tener configurado los archivos CGI de WebPay. El plugin te preguntará por esta información dentro de la configuración. ( Woocommerce -> Settings -> Paymente Gateways -> Webpay Gateway).

4. La página usada por HTML_TR_NORMAL = http://DIRECCIONDETUPAGINA/?page_id=xt_compra&pay=webpay&wc-api=WC_Webpay

5. Es necesario cambiar en la página de recepción del pedido [woocommerce-thankyou] por [webpay-thankyou]. 

# Ejemplo **tbk_config.dat**. #

OJO,PESTAÑA y CEJA. La configuración siguiente es para un ambiente de certificación.

```
#!bash

IDCOMERCIO = TUIDCOMERCIO
MEDCOM = 2
TBK_KEY_ID = 101
PARAMVERIFCOM = 1
URLCGICOM = http://DIRECCIONDETUPAGINA/cgi-bin/tbk_bp_resultado.cgi
SERVERCOM = TUIP
PORTCOM = 80
WHITELISTCOM = ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz 0123456789./:=&?_
HOST = TUIP
WPORT = 80
URLCGITRA = /filtroUnificado/bp_revision.cgi
URLCGIMEDTRA = /filtroUnificado/bp_validacion.cgi
SERVERTRA = https://certificacion.webpay.cl
PORTTRA = 6443
PREFIJO_CONF_TR = HTML_
HTML_TR_NORMAL =  http://DIRECCIONDETUPAGINA/?page_id=xt_compra&pay=webpay&wc-api=WC_Webpay

```
#CHANGELOG
* V2.3 : Se agregan los templates para los pagos con webpay.
* V2.2 : Se establece el short-code [webpay-thankyou] para realizar las validaciones necesarias por parte de transbank. Es necesario cambiar en la página de recepción del pedido por [woocommerce-thankyou]. 
* V2.1.9 : Se usa el estandar definido por woocommerce para los códigos de estado.
* V2.1.8 : Arreglada posible duplicidad cuando la orden pasa a on-hold.
* V2.1.7 : 
* V2.1.6 : Modificado para que funcionara con versiones más antiguas de php.
* V2.1.5 : Se cambian los permisos por defecto de la carpeta común. Con esto se deben asegurar que el usuario que ejecuta los CGI sea el mismo que crea los archivos.
* V2.1.2 : Se externaliza la carpeta común al directorio de uploads. De esta manera no se borra la información al actualizar el plugin.
* V2.1 : Se agregan las variables de la tienda en la configuración del plugin.

Y eso debería ser todo por ahora.


Have fun!
