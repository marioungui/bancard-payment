<?php
/*
    Si estas viendo esto... Probablemente no deberías verlo :)

    Si es para fines de investigación y/o desarrollo me parece genial. Contactame y vemos qué podemos hacer

    PERO...

    Si tratás de hacer ingeniería inversa a este código no lo puedo impedir, Pero sí te voy a agradecer que no lo uses con fines comerciales. Al fin y al cabo con la compra del plugin estas ayudando a un developer a seguir adelante y poder pagar sus tazas de café, internet, casa, etc.
*/

add_filter('woocommerce_payment_gateways', 'zimple_add_gateway_class');
function zimple_add_gateway_class($gateways) {
    $gateways[] = 'WC_zimple_Gateway';
    return $gateways;
}
add_action('plugins_loaded', 'zimple_init_gateway_class');
function zimple_init_gateway_class() {

    class WC_Zimple_Gateway extends WC_Payment_Gateway {
        public function __construct() {

            $this->id = 'zimple'; 
            $this->icon = ''; 
            $this->has_fields = true;
            $this->method_title = 'Billetera Zimple';
            $this->method_description = 'Acepte pagos de Billetera Zimple';
            $this->supports = array(
                'products',
                'refunds'
            );
            $this->init_form_fields();
            $this->init_settings();
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->enabled = $this->get_option('enabled');
            $this->testmode = 'yes' === $this->get_option('testmode');
            $this->private_key = $this->testmode ? $this->get_option('test_private_key') : $this->get_option('private_key');
            $this->publishable_key = $this->testmode ? $this->get_option('test_publishable_key') : $this->get_option('publishable_key');
            $this->zimple_endpoint = $this->testmode ? "https://vpos.infonet.com.py:8888" : "https://vpos.infonet.com.py";

            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(
                $this,
                'process_admin_options'
            ));


            add_action('woocommerce_api_zimple', array(
                $this,
                'webhook'
            ));

        }

        public function init_form_fields() {

            $this->form_fields = array(
                'enabled' => array(
                    'title' => 'Activar/Desactivar',
                    'label' => 'Activar portal de pagos de Zimple',
                    'type' => 'checkbox',
                    'description' => '',
                    'default' => 'no'
                ) ,
                'title' => array(
                    'title' => 'Título',
                    'type' => 'text',
                    'description' => 'Esto es el título de el usuario ve en la página de pagos.',
                    'default' => 'Billetera Zimple',
                    'desc_tip' => true
                ) ,
                'description' => array(
                    'title' => 'Descripción',
                    'type' => 'textarea',
                    'description' => 'Descripción del portal de pagos, el usuario ve esto en la página de pagos.',
                    'default' => 'Pague cómodamente a través de su billetera Zimple'
                ) ,
                'testmode' => array(
                    'title' => 'Modo de Prueba',
                    'label' => 'Habilitar el modo de prueba',
                    'type' => 'checkbox',
                    'description' => 'Utilice esta opción si quiere poner el sistema en modo de pruebas utilizando las claves del servidor de pruebas',
                    'default' => 'yes',
                    'desc_tip' => true
                ) ,
                'test_publishable_key' => array(
                    'title' => 'Llave pública de pruebas',
                    'type' => 'text'
                ) ,
                'test_private_key' => array(
                    'title' => 'Llave privada de pruebas',
                    'type' => 'password'
                ) ,
                'publishable_key' => array(
                    'title' => 'Llave pública del entorno Live',
                    'type' => 'text'
                ) ,
                'private_key' => array(
                    'title' => 'Llave privada del entorno Live',
                    'type' => 'password'
                )
            );
        }

        public function payment_fields() {

            do_action('woocommerce_credit_card_form_start', $this->id);
            ?>
            <div id="info-zimple-field">Pague a través de su Billetera Zimple <strong>Asegúrese que el número listado anteriormente corresponda al número con el que tiene registrado el servicio</strong>.</div>
            <div id="zimple-checkout"></div>
            <?php
            do_action('woocommerce_credit_card_form_end', $this->id);

        }

        public function process_payment($order_id) {

            global $woocommerce;
            $order = wc_get_order($order_id);
            $order_data = $order->get_data();
            $cart_currency = $order->get_currency();
            $telefono = $order_data['billing']['phone'];
            $cart_amount = ($cart_currency == "PYG") ? $order->get_total() . ".00" : $order->get_total();
            $unhash = $this->private_key . $order->get_order_number() . $cart_amount . $cart_currency;
            $hash = hash("sha256",$unhash);
            $args = [
                "public_key" => $this->publishable_key,
                "operation" => [
                    "token" => $hash,
                    "shop_process_id" => intval($order->get_order_number()),
                    "currency" => $cart_currency,
                    "amount" => $cart_amount,
                    "additional_data" => $telefono,
                    "description" => "Pagá tus productos de " . get_bloginfo('name') . " de forma rápida y segura con ZIMPLE",
                    "return_url" => get_site_url(null,'/wc-api/bancard/?order_id=' . $order_id,"https"),
                    "cancel_url" => get_site_url(null,'/wc-api/bancard/?status=payment_fail&order_id=' . $order_id, "https"),
                    "zimple" => "S"
                ]];
            $url = $this->zimple_endpoint . "/vpos/api/0.3/single_buy";
            $ch = curl_init($url);
            $payload = json_encode($args);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type:application/json'
            ));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $result = curl_exec($ch);
            $resultado = json_decode($result, true);

            if ($resultado['status'] == "success") {

                $process = $resultado['process_id'];

                $order->update_status('on-hold', 'Esperando confirmación de pago de Zimple.');

                wc_add_notice("<span data-order=\"{$process}\" data-method=\"zimple\"></span>Favor ingrese los datos de su tarjeta de crédito o débito para finalizar la compra.", 'notice');
                return;
            }
            else {
                wp_delete_post($order_id,true);
                wc_add_notice("Hubo un error alw procesar el pago y no fue realizada ninguna transacción. Favor intente hacer una nueva compra, en caso de persistencia póngase en contacto con nosotros. {$order->get_order_number()}", 'error');
                if (curl_exec($ch) === false) {
                    error_log('Curl error: ' . curl_error($ch));
                }
                curl_close($ch);
                return;
            }

        }

        public function process_refund($order_id, $amount = null, $reason = '') {
            $order = wc_get_order($order_id);

            $unhash = $this->private_key . $order->get_order_number() . "rollback" . "0.00";
            $hash = hash("sha256",$unhash);
            $args = [
            "public_key" => $this->publishable_key,
            "operation" => [
                "token" => $hash,
                "shop_process_id" => $order->get_order_number()
                ]];
            $url = $this->zimple_endpoint . "/vpos/api/0.3/single_buy/rollback";
            $ch = curl_init($url);
            $payload = json_encode($args);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type:application/json'
            ));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $result = curl_exec($ch);
            $resultado = json_decode($result, true);
            curl_close($ch);
            if ($resultado['status'] == "success") {
                $order = wc_get_order($order_id);
                $order->update_meta_data('refund_amount', $order->get_total_refunded());
                $order->save();
                return true;
            }
            else {
                return false;
            }
        }

        public function webhook() {
            ob_start();
            if ($_SERVER['REQUEST_METHOD'] == "POST") {


                $b_response = json_decode(file_get_contents("php://input") , true);
                error_log(print_r($b_response,true));
                $order = wc_get_order($b_response['operation']['shop_process_id']);


                if ($b_response['operation']['response'] == "S") {
                    if ($b_response['operation']['response_code'] == "00") {
                        $order->payment_complete();
                        $order->reduce_order_stock();
                        $order->add_order_note("Se procesó exitosamente el pago por parte de ZIMPLE. Código de Autorización: {$b_response['operation']['authorization_number']}. Ticket: {$b_response['operation']['ticket_number']}");
                        $order->add_order_note("Gracias por comprar en " . get_bloginfo('name') . ". Recibimos tu pago y estaremos en contacto contigo para confirmar la entrega.", true);
                        echo json_encode(array(
                            "status" => "success"
                        ));
                    }
                    else {
                        $order->update_status('failed', "Hubo un error al procesar su pago: {$b_response['operation']['response_description']}. {$b_response['operation']['extended_response_description']}.");
                        http_response_code(400);
                        echo json_encode(array(
                            "status" => "fail"
                        ));
                    }
                }
            }
            else if (isset($_GET['order_id']) && !empty($_GET['order_id'])) {

                $order = wc_get_order($_GET['order_id']);

                if ($order->get_status() == "processing" || $order->get_status() == "completed") {
                    $url_r = $order->get_checkout_order_received_url();
                    header("Location: {$url_r}");
                }
                else if (isset($_GET['status']) && $_GET['status'] == "payment_fail") {
                    $order->update_status('failed', 'El pago no ha sido procesado.');
                    $url_r = $order->get_checkout_order_received_url();
                    header("Location: {$url_r}");
                }
            }
            ob_end_flush();
        }
    }
}