<?php
/*
    Si estas viendo esto... Probablemente no deberías verlo :)

    Si es para fines de investigación y/o desarrollo me parece genial. Contactame y vemos qué podemos hacer

    PERO...

    Si tratás de hacer ingeniería inversa a este código no lo puedo impedir, Pero sí te voy a agradecer que no lo uses con fines comerciales. Al fin y al cabo con la compra del plugin estas ayudando a un developer a seguir adelante y poder pagar sus tazas de café, internet, casa, etc.
*/

add_filter('woocommerce_payment_gateways', 'catastro_add_gateway_class');
function catastro_add_gateway_class($gateways) {
    $gateways[] = 'WC_Catastro_Gateway';
    return $gateways;
}
add_action('plugins_loaded', 'catastro_init_gateway_class');
function catastro_init_gateway_class() {

    class WC_Catastro_Gateway extends WC_Payment_Gateway {
        public function __construct() {

            $this->id = 'catastro'; 
            $this->icon = ''; 
            $this->has_fields = true;
            $this->method_title = 'Bancard 1-Click';
            $this->method_description = 'Acepte pagos vía tarjeta de crédito o débito en 1 sólo click';
            $this->supports = array(
                'products',
                'refunds',
                'subscriptions',
                'subscription_cancellation', 
                'subscription_suspension', 
                'subscription_reactivation',
                'subscription_amount_changes',
                'subscription_date_changes',
                'subscription_payment_method_change',
                'subscription_payment_method_change_customer',
                'subscription_payment_method_change_admin',
                'multiple_subscriptions'
            );
            $this->init_form_fields();
            $this->init_settings();
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->enabled = $this->get_option('enabled');
            $this->testmode = 'yes' === $this->get_option('testmode');
            $this->private_key = $this->testmode ? $this->get_option('test_private_key') : $this->get_option('private_key');
            $this->publishable_key = $this->testmode ? $this->get_option('test_publishable_key') : $this->get_option('publishable_key');
            $this->catastro_endpoint = $this->testmode ? "https://vpos.infonet.com.py:8888" : "https://vpos.infonet.com.py";

            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(
                $this,
                'process_admin_options'
            ));

        }

        public static function btn_pago_catastro($button_text) {
            $button_text = 'Pagar';
            return $button_text;   
        }

        public function init_form_fields() {

            $this->form_fields = array(
                'enabled' => array(
                    'title' => 'Activar/Desactivar',
                    'label' => 'Activar pagos en 1 click - Bancard',
                    'type' => 'checkbox',
                    'description' => '',
                    'default' => 'no'
                ) ,
                'title' => array(
                    'title' => 'Título',
                    'type' => 'text',
                    'description' => 'Esto es el título de el usuario ve en la página de pagos.',
                    'default' => 'Pagos en 1 click - Bancard',
                    'desc_tip' => true
                ) ,
                'description' => array(
                    'title' => 'Descripción',
                    'type' => 'textarea',
                    'description' => 'Descripción del portal de pagos, el usuario ve esto en la página de pagos.',
                    'default' => 'Pague cómodamente a través de su billetera Catastro'
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
        public function validate_fields() {
            if( isset($_POST['payment_method']) && $_POST['payment_method'] == "catastro" ) {
                if (!isset($_POST['bancard-ntoken']) || empty($_POST['bancard-ntoken'])) {
                    wc_add_notice(  'Favor seleccione una tarjeta para continuar con el pago', 'error' );
                    return false;
                }
                else {
                    define("CATASTRO_PAGOTOKEN",$_POST['bancard-ntoken']);
                    return true;
                }
            }
            return true;
        }

        public function payment_fields() {

            do_action('woocommerce_credit_card_form_start', $this->id);
            ?>
            <div id="info-catastro-field"><p>Pague de forma rápida, segura y sencilla registrando su tarjeta en la sección <i>"Mi Cuenta"</i></p></div>

            <?php

            $unhash = $this->private_key.get_current_user_id()."request_user_cards";
            $hash = hash("sha256",$unhash);

            $args = [
            "public_key" => $this->publishable_key,
            "operation" => ["token" => $hash]
            ];

            $url = $this->catastro_endpoint . "/vpos/api/0.3/users/".get_current_user_id()."/cards";
            $ch = curl_init($url);
            $payload = json_encode($args,JSON_PRETTY_PRINT);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type:application/json'
            ));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $result = curl_exec($ch);
            $resultado = json_decode($result,true);

            if ($resultado['status'] == "success") {
                if (empty($resultado['cards'])) {
                    echo "<p>No se registró ninguna tarjeta en su cuenta.</p>";
                }
                else {
                    foreach ($resultado['cards'] as $tarjeta => $valor) {
                        if ($valor['card_type'] == "credit") {
                            $valor['card_type'] = "Tarj. Credito";
                        }
                        if ($valor['card_type'] == "debit") {
                            $valor['card_type'] = "Tarj. Debito";
                        }
                        if (stripos($valor['card_brand'],"bancard") !== FALSE) {
                            $card_brand = "bancard";
                        }
                        if (stripos($valor['card_brand'],"visa") !== FALSE) {
                            $card_brand = "visa";
                        }
                        if (stripos($valor['card_brand'],"mastercard") !== FALSE) {
                            $card_brand = "mastercard";
                        }
                        $imgcard = plugin_dir_url( __FILE__ )."../assets/credit-cards/".$card_brand.".png";
                        echo '<div class="bancard-card-box form-row"><input type="radio" id="card-'.$valor["card_id"].'" class="bancard-nmasked" name="bancard-ntoken" id="bancard-nmasked" value="'.$valor['alias_token'].'"><img class="bancard-cardbrand" src="'.$imgcard.'"><label for="card-'.$valor["card_id"].'" class="bancard-nmaskednumber">'.chunk_split($valor['card_masked_number'],4,' ').'</label>
                        <span class="bancard-cardvenc">Venc.</span>
                        <span class="bancard-cardvencinfo">08/21</span></div>';
                    }
                }
            }

            do_action('woocommerce_credit_card_form_end', $this->id);

        }

        public function process_payment($order_id) {

            global $woocommerce;
            $order = wc_get_order($order_id);
            $order_data = $order->get_data();
            $cart_currency = $order->get_currency();
            $cart_amount = ($cart_currency == "PYG") ? $order->get_total() . ".00" : $order->get_total();
            $unhash = $this->private_key . $order->get_order_number(). "charge" . $cart_amount . $cart_currency . CATASTRO_PAGOTOKEN;
            $hash = hash("sha256",$unhash);

            $args = [
            "public_key" => $this->publishable_key,
            "operation" => [
                "token" => $hash,
                "shop_process_id" => $order->get_order_number(),
                "amount" => $cart_amount,
                "currency" => $cart_currency,
                "description" => "Pago de productos de " . get_bloginfo('name'),
                "number_of_payments" => 1,
                "additional_data" => "",
                "alias_token" => CATASTRO_PAGOTOKEN
                ]
            ];

            $url = $this->catastro_endpoint . "/vpos/api/0.3/charge";
            $ch = curl_init($url);
            $payload = json_encode($args,JSON_PRETTY_PRINT);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type:application/json'
            ));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $result = curl_exec($ch);
            $resultado = json_decode($result,true);

            if ($resultado['confirmation']['response'] == "S") {
                if ($resultado['confirmation']['response_code'] == "00") {
                    $order->payment_complete();
                    $order->reduce_order_stock();         
                    $woocommerce->cart->empty_cart();
         
                    return array(
                        'result' => 'success',
                        'redirect' => $this->get_return_url( $order )
                    );
                }
                else {
                    return array(
                        'result' => 'success',
                        'redirect' => $this->get_return_url( $order )
                    );
                    wc_add_notice("Error al procesar pago. {$resultado['confirmation']['response_description']}. {$resultado['confirmation']['extended_response_description']}", 'error');
                    wp_delete_post($order_id,true);
                }
            }
            else if ($resultado['status'] == "error") {
                if (!empty($resultado['messages']['dsc'])) {
                    return array(
                        'result' => 'success',
                        'redirect' => $this->get_return_url( $order )
                    );
                    wc_add_notice("Error al procesar el pago. {$resultado['messages']['dsc']}", 'error');
                    $order->update_status('failed', 'Error al procesar el pago.');
                    wp_delete_post($order_id,true);
                }
            }
            else {
                wc_add_notice("Error al procesar pago. No se efectuó ninguna transacción en tu tarjeta de crédito/débito.", 'error');
                wp_delete_post($order_id,true);
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
            $url = $this->catastro_endpoint . "/vpos/api/0.3/single_buy/rollback";
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


        public function get_card_ID_for_current_user($user_id) {
            $results = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}bcards WHERE user_id = {$user_id}", OBJECT );
            return $results;
        }

        public function delete_used_card($card_id) {
            global $wpdb;
            $wpdb->get_results( "DELETE FROM {$wpdb->prefix}bcards WHERE id = {$card_id}", OBJECT );
        }

        public function generate_card_ID($user_id) {
            global $wpdb;
            $results = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}bcards WHERE user_id = {$user_id} AND is_used = 0 ORDER BY id DESC LIMIT 1", OBJECT );

            if (empty($results)) {
                $wpdb->get_results( "INSERT INTO {$wpdb->prefix}bcards (`user_id`, `is_used`) VALUES ('{$user_id}', '0')", OBJECT );
                $results = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}bcards WHERE user_id = {$user_id} AND is_used = 0 ORDER BY id DESC LIMIT 1", OBJECT );
                return $results[0]->id;
            }

            else if ($results[0]->is_used == 0) {
                return $results[0]->id;
            }
        }

        function set_card_ID_for_current_user($card_id) {
            global $wpdb;
            $wpdb->get_results( "UPDATE {$wpdb->prefix}bcards SET `is_used` = '1' WHERE id = {$card_id};", OBJECT );
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
    $Bcatastro = new WC_Catastro_Gateway;
    if ($Bcatastro->enabled == 'yes') {
        add_filter( 'woocommerce_account_menu_items','bancard_add_mis_tarjetas_link_my_account');
        add_action( 'init','bancard_add_mis_tarjetas_endpoint');
        add_filter( 'query_vars','bancard_mis_tarjetas_query_vars', 0);
        add_action( 'woocommerce_account_mis-tarjetas_endpoint','bancard_mis_tarjetas_content');
        add_filter('woocommerce_order_button_text',array('WC_Catastro_Gateway','btn_pago_catastro'));
    }
    function bancard_add_mis_tarjetas_endpoint() {
        add_rewrite_endpoint( 'mis-tarjetas', EP_ROOT | EP_PAGES );
    }
      
    function bancard_mis_tarjetas_query_vars( $vars ) {
        $vars[] = 'mis-tarjetas';
        return $vars;
    }
      
    function bancard_add_mis_tarjetas_link_my_account( $items ) {
        $items['mis-tarjetas'] = __('Mis tarjetas','bancard-payment');
        return $items;
    }

    function bancard_mis_tarjetas_content() {
        echo '<h3 style="text-align:center;">'.__('Administrar mis tarjetas','bancard-payment').'</h3>';
        ?>
        <style>
            .bancard-boxes {
                display: table;
                width: auto;
                margin-left: auto;
                margin-right: auto;
                padding: 20px 0;
            }
            .bancard-box {
                width: 170px;
                display: inline;
                margin: 10px;
                float: left;
                padding: 10px;
                border: 1px #b0b0b0 solid;
                border-radius: 10px;
            }
            .card-icon {
                width: 30px;
                display: inline;
            }
            .card-icon img, .card-icon svg {
                display: table;
                max-width:100%;
                margin-left:auto;
                margin-right:auto;
            }
            .bancard-box span.text{
                text-align: center;
                display:inherit;
            }
        </style>
        <div class="bancard-boxes">
            <a href="?action=get_cards">
                <div class="bancard-box bboxrojo">
                    <div class="card-icon"><img src="https://img.icons8.com/fluent/96/000000/bank-card-back-side.png"/></div>
                    <span class="text">Listar mis tarjetas</span>
                </div>
            </a>
           <a href="?action=add_card">
                <div class="bancard-box bboxrojo">
                    <div class="card-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" x="0px" y="0px" width="96" height="96" viewBox="0 0 171 171" style=" fill:#000000;"><g fill="none" fill-rule="nonzero" stroke="none" stroke-width="1" stroke-linecap="butt" stroke-linejoin="miter" stroke-miterlimit="10" stroke-dasharray="" stroke-dashoffset="0" font-family="none" font-weight="none" font-size="none" text-anchor="none" style="mix-blend-mode: normal"><path d="M0,171.99654v-171.99654h171.99654v171.99654z" fill="none" stroke="none"></path><g id="original-icon" fill="#2ecc71" stroke="none" opacity="0" visibility="hidden"><path d="M23.94,30.78c-9.405,0 -17.1,7.695 -17.1,17.1v75.24c0,9.405 7.695,17.1 17.1,17.1h123.12c9.405,0 17.1,-7.695 17.1,-17.1v-75.24c0,-9.405 -7.695,-17.1 -17.1,-17.1zM23.94,37.62h123.12c5.70445,0 10.26,4.55555 10.26,10.26v6.84h-143.64v-6.84c0,-5.70445 4.55555,-10.26 10.26,-10.26zM13.68,71.82h143.64v51.3c0,5.70445 -4.55555,10.26 -10.26,10.26h-123.12c-5.70445,0 -10.26,-4.55555 -10.26,-10.26zM30.78,82.08v6.84h54.72v-6.84z"></path></g><g id="subtracted-icon" fill="#2ecc71" stroke="none"><path d="M147.06,30.78c9.405,0 17.1,7.695 17.1,17.1l0,58.39614c-2.09806,-1.88359 -4.38958,-3.55544 -6.84,-4.98101v-29.47514h-143.64v51.3c0,5.70445 4.55555,10.26 10.26,10.26l71.96223,0c-0.09419,1.12805 -0.14223,2.26869 -0.14223,3.42c0,1.15131 0.04805,2.29195 0.14223,3.42l-71.96223,0c-9.405,0 -17.1,-7.695 -17.1,-17.1v-75.24c0,-9.405 7.695,-17.1 17.1,-17.1zM13.68,47.88v6.84h143.64v-6.84c0,-5.70445 -4.55555,-10.26 -10.26,-10.26h-123.12c-5.70445,0 -10.26,4.55555 -10.26,10.26zM85.5,82.08v6.84h-54.72v-6.84z"></path></g><g stroke="none"><g id="Layer_9" fill="#2ecc71"><path d="M136.8,102.6c-18.81,0 -34.2,15.39 -34.2,34.2c0,18.81 15.39,34.2 34.2,34.2c18.81,0 34.2,-15.39 34.2,-34.2c0,-18.81 -15.39,-34.2 -34.2,-34.2zM153.558,140.22h-13.338v13.338c0,2.052 -1.368,3.42 -3.42,3.42c-2.052,0 -3.42,-1.368 -3.42,-3.42v-13.338h-13.338c-2.052,0 -3.42,-1.368 -3.42,-3.42c0,-2.052 1.368,-3.42 3.42,-3.42h13.338v-13.68c0,-2.052 1.368,-3.42 3.42,-3.42c2.052,0 3.42,1.368 3.42,3.42v13.68h13.338c2.052,0 3.42,1.368 3.42,3.42c0,2.052 -1.368,3.42 -3.42,3.42z"></path></g><g fill="#000000" opacity="0"><g id="IOS" font-family="Inter, sans-serif" font-weight="400" font-size="16" text-anchor="start" visibility="hidden"></g><g id="IOS_copy"><path d="M136.8,95.76c-22.572,0 -41.04,18.468 -41.04,41.04c0,22.572 18.468,41.04 41.04,41.04c22.572,0 41.04,-18.468 41.04,-41.04c0,-22.572 -18.468,-41.04 -41.04,-41.04z"></path></g></g></g><path d="M102.6,171v-68.4h68.4v68.4z" id="overlay-drag" fill="#ff0000" stroke="none" opacity="0"></path></g></svg>
                    </div>
                    <span class="text">Agregar nueva tarjeta</span>
                </div>
            </a>
            <a href="?action=delete_card">
                <div class="bancard-box bboxrojo">
                    <div class="card-icon"><svg xmlns="http://www.w3.org/2000/svg" x="0px" y="0px" width="96" height="96" viewBox="0 0 171 171" style=" fill:#000000;"><g fill="none" fill-rule="nonzero" stroke="none" stroke-width="1" stroke-linecap="butt" stroke-linejoin="miter" stroke-miterlimit="10" stroke-dasharray="" stroke-dashoffset="0" font-family="none" font-weight="none" font-size="none" text-anchor="none" style="mix-blend-mode: normal"><path d="M0,171.99654v-171.99654h171.99654v171.99654z" fill="none" stroke="none"></path><g id="original-icon" fill="#e74c3c" stroke="none" opacity="0" visibility="hidden"><path d="M23.94,30.78c-9.405,0 -17.1,7.695 -17.1,17.1v75.24c0,9.405 7.695,17.1 17.1,17.1h123.12c9.405,0 17.1,-7.695 17.1,-17.1v-75.24c0,-9.405 -7.695,-17.1 -17.1,-17.1zM23.94,37.62h123.12c5.70445,0 10.26,4.55555 10.26,10.26v6.84h-143.64v-6.84c0,-5.70445 4.55555,-10.26 10.26,-10.26zM13.68,71.82h143.64v51.3c0,5.70445 -4.55555,10.26 -10.26,10.26h-123.12c-5.70445,0 -10.26,-4.55555 -10.26,-10.26zM30.78,82.08v6.84h54.72v-6.84z"></path></g><g id="subtracted-icon" fill="#e74c3c" stroke="none"><path d="M147.06,30.78c9.405,0 17.1,7.695 17.1,17.1l0,58.39614c-2.09806,-1.88359 -4.38958,-3.55544 -6.84,-4.98101v-29.47514h-143.64v51.3c0,5.70445 4.55555,10.26 10.26,10.26l71.96223,0c-0.09419,1.12805 -0.14223,2.26869 -0.14223,3.42c0,1.15131 0.04805,2.29195 0.14223,3.42l-71.96223,0c-9.405,0 -17.1,-7.695 -17.1,-17.1v-75.24c0,-9.405 7.695,-17.1 17.1,-17.1zM13.68,47.88v6.84h143.64v-6.84c0,-5.70445 -4.55555,-10.26 -10.26,-10.26h-123.12c-5.70445,0 -10.26,4.55555 -10.26,10.26zM85.5,82.08v6.84h-54.72v-6.84z"></path></g><g stroke="none"><g id="Layer_9" fill="#e41c1c"><path d="M136.8,102.6c-18.81,0 -34.2,15.39 -34.2,34.2c0,18.81 15.39,34.2 34.2,34.2c18.81,0 34.2,-15.39 34.2,-34.2c0,-18.81 -15.39,-34.2 -34.2,-34.2zM151.164,146.376c1.368,1.368 1.368,3.42 0,4.788c-0.684,0.684 -1.71,1.026 -2.394,1.026c-0.684,0 -1.71,-0.342 -2.394,-1.026l-9.576,-9.576l-9.576,9.576c-0.684,0.684 -1.71,1.026 -2.394,1.026c-0.684,0 -1.71,-0.342 -2.394,-1.026c-1.368,-1.368 -1.368,-3.42 0,-4.788l9.576,-9.576l-9.576,-9.576c-1.368,-1.368 -1.368,-3.42 0,-4.788c1.368,-1.368 3.42,-1.368 4.788,0l9.576,9.576l9.576,-9.576c1.368,-1.368 3.42,-1.368 4.788,0c1.368,1.368 1.368,3.42 0,4.788l-9.576,9.576z"></path></g><g fill="#000000" opacity="0"><g id="IOS" font-family="Inter, sans-serif" font-weight="400" font-size="16" text-anchor="start" visibility="hidden"></g><g id="IOS_copy"><path d="M136.8,95.76c-22.572,0 -41.04,18.468 -41.04,41.04c0,22.572 18.468,41.04 41.04,41.04c22.572,0 41.04,-18.468 41.04,-41.04c0,-22.572 -18.468,-41.04 -41.04,-41.04z"></path></g></g></g><path d="M102.6,171v-68.4h68.4v68.4z" id="overlay-drag" fill="#ff0000" stroke="none" opacity="0"></path></g></svg></div>
                    <span class="text">Eliminar tarjeta</span>
                </div>
            </a>
        </div>
        <?php
        if (isset($_GET['action']) && $_GET['action'] == "get_cards") {
            $Bcatastro = new WC_Catastro_Gateway;
            /*
            <p>User ID: '.get_current_user_id().'</p>
            <p>Public Key: '.$this->publishable_key.'</p>
            <p>Private Key: '.$this->private_key.'</p>
            */
            $unhash = $Bcatastro->private_key.get_current_user_id()."request_user_cards";
            $hash = hash("sha256",$unhash);

            $args = [
            "public_key" => $Bcatastro->publishable_key,
            "operation" => ["token" => $hash]
            ];

            $url = $Bcatastro->catastro_endpoint . "/vpos/api/0.3/users/".get_current_user_id()."/cards";
            $ch = curl_init($url);
            $payload = json_encode($args,JSON_PRETTY_PRINT);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type:application/json'
            ));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $result = curl_exec($ch);
            $resultado = json_decode($result,true);

            if ($resultado['status'] == "success") {
                if (empty($resultado['cards'])) {
                    echo "<p>No se registró ninguna tarjeta en su cuenta. Asegúrese de cargarlo correctamente en su perfil ubicada en la sección \"Mi Cuenta\"</p>";
                }
                else {
                    ?>
                    <table>
                        <tr>
                            <th>Marca:</th>
                            <th>Número:</th>
                            <th>Vencimiento:</th>
                            <th>Tipo:</th>
                            <th colspan="1">Acción</th>
                        </tr>
                        <?php
                        foreach ($resultado['cards'] as $tarjeta => $valor) {
                            $url = $_SERVER['REQUEST_SCHEME'] .'://'. $_SERVER['HTTP_HOST'] . explode('?', $_SERVER['REQUEST_URI'], 2)[0]."?action=delete_card&cardid={$valor['card_id']}&ctoken={$valor['alias_token']}";
                            if ($valor['card_type'] == "credit") {
                                $valor['card_type'] = "Tarj. Crédito";
                            }
                            if ($valor['card_type'] == "debit") {
                                $valor['card_type'] = "Tarj. Débito";
                            }
                            echo "<tr>";
                            echo "<td>{$valor['card_brand']}</td>";
                            echo "<td>{$valor['card_masked_number']}</td>";
                            echo "<td>{$valor['expiration_date']}</td>";
                            echo "<td>{$valor['card_type']}</td>";
                            echo "<td><a href=\"{$url}\"><button>Eliminar Tarjeta</button></a></td>";
                            echo "</tr>";
                        }
                        ?>
                    </table>
                    <?php
                    //print_r($resultado['cards']);
                }
            }

        }
        else if (isset($_GET['action']) && $_GET['action'] == "add_card") {
            $Bcatastro = new WC_Catastro_Gateway;

            $cardid = $Bcatastro->generate_card_ID(get_current_user_id());
            $user_phone = get_user_meta( get_current_user_id(), 'billing_phone', true );
            $user_email = get_userdata( get_current_user_id(), 'user_email', true )->user_email;
            /*
            <p>User ID: '.get_current_user_id().'</p>
            <p>Public Key: '.$Bcatastro->publishable_key.'</p>
            <p>Private Key: '.$Bcatastro->private_key.'</p>
            */
            $unhash = $Bcatastro->private_key.$cardid.get_current_user_id()."request_new_card";
            $hash = hash("sha256",$unhash);
            $url = $Bcatastro->catastro_endpoint . "/vpos/api/0.3/cards/new";


            $args = [
            "public_key" => $Bcatastro->publishable_key,
            "operation" => [
                "token" => $hash,
                "card_id" => $cardid,
                "user_id" => get_current_user_id(),
                "user_cell_phone" => $user_phone,
                "user_mail" => $user_email,
                "return_url" => $_SERVER['REQUEST_SCHEME'] .'://'. $_SERVER['HTTP_HOST'] . explode('?', $_SERVER['REQUEST_URI'], 2)[0]."?action=added_card&bcid={$cardid}"
            ]];


            $ch = curl_init($url);
            $payload = json_encode($args,JSON_PRETTY_PRINT);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type:application/json'
            ));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $result = curl_exec($ch);
            $resultado = json_decode($result,true);


            if ($resultado['status'] == "success") {

                $script = $Bcatastro->testmode ? plugin_dir_url( __FILE__ )."../bancard-checkout-dev.js" : plugin_dir_url( __FILE__ )."../bancard-checkout.js";

                ?>
                <script src="<?= $script ?>"></script>
                <script>
                    window.onload = function () {
                        Bancard.Cards.createForm('bancard-catastro', "<?=$resultado['process_id']?>")};
                </script>
                <div id="bancard-catastro"></div>
                <?php
            }

        }
        else if (isset($_GET['action']) && $_GET['action'] == "added_card") {
            $Bcatastro = new WC_Catastro_Gateway;
            if (isset($_GET['status'])) {
                if ($_GET['status'] == "add_new_card_fail") {
                    echo '<h4>No se ha añadido la tarjeta</h4>';
                    echo "<p>{$_GET["description"]}</p>";             
                }
                else if ($_GET['status'] == "add_new_card_success") {
                    if (isset($_GET['bcid'])) {
                        echo '<h4>Registro realizado exitosamente</h4>';
                        echo "<p>Se ha registrado una nueva tarjeta en su cuenta de ".get_bloginfo('name').". A partir de ahora puede comprar con seguridad en un sólo click sin tener que cargar sus datos nuevamente.</p>";
                        $Bcatastro->set_card_ID_for_current_user($_GET['bcid']);
                    }   
                }
            }
        }
        else if (isset($_GET['action']) && $_GET['action'] == "delete_card" && isset($_GET['cardid']) && is_numeric($_GET['cardid']) && isset($_GET['ctoken'])) {

            $Bcatastro = new WC_Catastro_Gateway;


            $unhash = $Bcatastro->private_key."delete_card".get_current_user_id().$_GET['ctoken'];
            $hash = hash("sha256",$unhash);
            $url = $Bcatastro->catastro_endpoint . "/vpos/api/0.3/users/".get_current_user_id()."/cards";


            $args = [
            "public_key" => $Bcatastro->publishable_key,
            "operation" => [
                "token" => $hash,
                "alias_token" => $_GET['ctoken']
            ]];


            $ch = curl_init($url);
            $payload = json_encode($args,JSON_PRETTY_PRINT);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type:application/json'
            ));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $result = curl_exec($ch);
            $resultado = json_decode($result,true);

            $Bcatastro->delete_used_card($_GET['cardid']);

            if ($resultado['status'] == "success") {
                echo "Tarjeta eliminada exitosamente";
            }
        }
        else {
            echo "Para ver sus tarjetas registradas haga click en \"Listar Tarjetas\"";
        }
    }
}
