=== WooCommerce Webpay Gateway Chilean  ===

Contributors: Cristian Tala S.



Tags: 

Requires at least: 3, 3.3+ and WooCommerce 1.6+

Tested up to: 3.6

Stable tag: 2.3

== Description ==

This plugin enables to pay with webpay plus ( Chile ) in Woocommerce 

Wiki Home : https://bitbucket.org/ctala/woocommerce-webpay/wiki/Home

Ya ha sido bastante tiempo en el que me han preguntado por esto en los comentarios y al fin decidí liberar el código de manera OpenSource.

Algunos de ustedes se preguntarán el por qué no cobro por este plugin, tomando en consideración que mucha gente ofrece una buena cantidad por el servicio. Bueno, la respuesta es simple; Creo que junto a la comunidad de desarrolladores podemos mejorar mucho más este código y así todos tener un plugin de una calidad mucho mayor que por la que podríamos pagar ( Suena bien no ? ).


A considerar :

*El Código se distribuye bajo GPLV3.
*Este código YA es compatible con la última versión de WooCommerce (Version > 2.0.12)
*El código no presenta garantía de ningún tipo.
*Se puso a disposición un Wiki para la instalación
*Se puso a disposición un BugTracker para que podamos ver los problemas que vayan saliendo en conjunto.
*Se asume que ya se hizo la configuración de los CGI para Transbank
*Si necesitan los CGI de Transbank los pueden descargar de : https://bitbucket.org/ctala/webpayconector. Ya deberían estar listos para usarlos. Con estos parto para generar un eCommerce.
*Para los que quieren mejorar el código, y nunca han ocupado una herramienta de control de versiones, les recomiendo que lean sobre GIT, además de lo que es un FORK.

Recuerden cambiar el shortcode de [woocommerce-thankyou] por [webpay-thankyou] cuando corresponda.

== Changelog ==



= 2.3.2 =

Se valida que la página de éxito venga de un POST.
Se arregla problema de validación del KEY


= 2.3.1 =

Merge con el código de Felipe Egas

= 2.3 =

Se agregan los templates para los pagos con webpay.
- Template para la página de Agradecimientos
- Template para la página de detalles de la orden

Esto se hizo debido a que Transbank pide información que antes no pedía en la página de éxito.


= 2.2 =

Se establece el short-code [webpay-thankyou] para realizar las validaciones necesarias por parte de transbank. Es necesario cambiar en la página de recepción del pedido por [woocommerce-thankyou]. 


= 2.1.9 =
Se cambian y estandarizan las recepciones de los status según el manual de woocommerce.
*Pending – Order received (unpaid)
*Failed – Payment failed or was declined (unpaid)
*Processing – Payment received and stock has been reduced- the order is awaiting fulfilment
*Completed – Order fulfilled and complete – requires no further action
*On-Hold – Awaiting payment – stock is reduced, but you need to confirm payment
*Cancelled – Cancelled by an admin or the customer – no further action required
*Refunded – Refunded by an admin - no further action required

= 2.1.8 =
Arreglada posible duplicidad cuando la orden pasa a on-hold.

= 2.1.7 = 
Arreglado el error de callback con las direcciones web largas. Se usan los shortlinks para no tener problemas.

= 2.1.6 =
Modificado para que funcionara con versiones más antiguas de php.

= 2.1.3 =
Agregado Mensaje en Notas del pedido.

= 2.1.2 =
Arreglada la creación automática del directorio común.
Ahora la carpeta común estará bajo uploadas para que no se borre al actualizar el plugin.

= 2.1 =
Added the eCommerce Variables.

