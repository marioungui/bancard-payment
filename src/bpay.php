<?php

/*
    Si estas viendo esto... Probablemente no deberías verlo :)

    Si es para fines de investigación y/o desarrollo me parece genial. Contactame y vemos qué podemos hacer

    PERO...

    Si tratás de hacer ingeniería inversa a este código no lo puedo impedir, Pero sí te voy a agradecer que no lo uses con fines comerciales. Al fin y al cabo con la compra del plugin estas ayudando a un developer a seguir adelante y poder pagar sus tazas de café, internet, casa, etc.
*/

add_filter('plugins_api', 'bancard_plugin_info', 20, 3);
function bancard_plugin_info( $res, $action, $args ) {
     if( 'plugin_information' !== $action ) {
        return false;
    }
 
    $plugin_slug = 'bancard-payment';
    if( $plugin_slug !== $args->slug ) {
        return false;
    }
    if( false == $remote = get_transient( 'bancard_update' . $plugin_slug ) ) {
         $remote = wp_remote_get( 'https://emedos.com.py/bancard-update/info.json', array(
            'timeout' => 10,
            'headers' => array(
                'Accept' => 'application/json'
            ) )
        );
        if ( ! is_wp_error( $remote ) && isset( $remote['response']['code'] ) && $remote['response']['code'] == 200 && ! empty( $remote['body'] ) ) {
            set_transient( 'bancard_update' . $plugin_slug, $remote, 43200 ); // 12 hours cache
        }
    }
    if( ! is_wp_error( $remote ) && isset( $remote['response']['code'] ) && $remote['response']['code'] == 200 && ! empty( $remote['body'] ) ) {
        $remote = json_decode( $remote['body'] );
        $res = new stdClass();
        $res->name = $remote->name;
        $res->slug = $plugin_slug;
        $res->version = $remote->version;
        $res->tested = $remote->tested;
        $res->requires = $remote->requires;
        $res->author = '<a href="https://emedos.com.py">Mario Moreno</a>';
        $res->author_profile = 'https://fb.me/marioungui';
        $res->download_link = $remote->download_url;
        $res->trunk = $remote->download_url;
        $res->requires_php = '7.0';
        $res->last_updated = $remote->last_updated;
        $res->sections = array(
            'description' => $remote->sections->description,
            'installation' => $remote->sections->installation,
            'changelog' => $remote->sections->changelog
        );
        if( !empty( $remote->sections->screenshots ) ) {
            $res->sections['screenshots'] = $remote->sections->screenshots;
        }
        $res->banners = array(
            'low' => 'https://emedos.com.py/bancard-update/banner-772x250.jpg',
            'high' => 'https://emedos.com.py/bancard-update/banner-1544x500.jpg'
        );
        return $res;
    }
    return false;
}

add_filter('site_transient_update_plugins', 'bancard_push_update' );
function bancard_push_update( $transient ){
    if ( empty($transient->checked ) ) {
            return $transient;
        }
    if( false == $remote = get_transient( 'bancard_upgrade_bancard-payment' ) ) {
 
        $remote = wp_remote_get( 'https://emedos.com.py/bancard-update/info.json', array(
            'timeout' => 10,
            'headers' => array(
                'Accept' => 'application/json'
            ) )
        );
 
        if ( !is_wp_error( $remote ) && isset( $remote['response']['code'] ) && $remote['response']['code'] == 200 && !empty( $remote['body'] ) ) {
            set_transient( 'bancard_upgrade_bancard-payment', $remote, 43200 ); // 12 hours cache
        }
 
    }
 
    if( $remote ) {
 
        $remote = json_decode( $remote['body'] );
 
        if( $remote && version_compare( '1.3', $remote->version, '<' ) && version_compare($remote->requires, get_bloginfo('version'), '<' ) ) {
            $res = new stdClass();
            $res->slug = 'bancard-payment';
            $res->plugin = 'bancard-payment/bancard-payment.php';
            $res->new_version = $remote->version;
            $res->tested = $remote->tested;
            $res->package = $remote->download_url;
            $transient->response[$res->plugin] = $res;
            }
    }
    return $transient;
}

use \Firebase\JWT\JWT;

if( false == $token = get_transient( 'bancard-api-token_bancard-payment' ) ) {
    $token = wp_remote_get("https://emedos.com.py/bancard-update/gen.php?domain={$_SERVER['SERVER_NAME']}", array(
        'timeout' => 10
        )
    );
    if ( !is_wp_error( $token ) && isset( $token['response']['code'] ) && $token['response']['code'] == 200 && !empty( $token['body'] ) ) {
        set_transient( 'bancard-api-token_bancard-payment', $token, 86400 ); // Day Cache
    }
}
else {
    $token = get_transient('bancard-api-token_bancard-payment');
}
if( false == $publicKey = get_transient( 'bancard-api-publicKey_bancard-payment' ) ) {
    $publicKey = wp_remote_get("https://emedos.com.py/bancard-update/publico.pub", array(
        'timeout' => 10
        )
    );
    if ( !is_wp_error( $publicKey ) && isset( $publicKey['response']['code'] ) && $publicKey['response']['code'] == 200 && !empty( $publicKey['body'] ) ) {
        set_transient( 'bancard-api-publicKey_bancard-payment', $publicKey, 86400 ); // Day Cache
    }

}
else {
    $publicKey = get_transient('bancard-api-publicKey_bancard-payment');
}

if (empty($token['body'])) {
    exit('Token no valido');
}
if ($token['body'] == "Sitio no existente") {
    function error_activacion_bancard_plugin() {
        $class = 'notice notice-error';
        $message = 'Lo sentimos, este sitio web no está validado para usar el plugin "WooCommerce Bancard Gateway y por tanto no se puede realizar la activación del plugin. Favor contacte al desarrollador para más información".';
        printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) ); 
    }
    add_action( 'admin_notices', 'error_activacion_bancard_plugin' );
    $plugins = array(
        'bancard-payment/bancard-payment.php'
    );
    require_once(ABSPATH . 'wp-admin/includes/plugin.php');
    deactivate_plugins($plugins);
}
if ($token['response']['code'] == 200) {
    if( $decoded = JWT::decode($token['body'], $publicKey['body'], ['RS256']) ) {
        if ($decoded->end_time > time()) {

            // Declares a new tab in My Account to enable "My Cards" section.
            // 1. Register new endpoint to use for My Account page
            // Note: Resave Permalinks or it will give 404 error
              

              

              
            

            require_once "zimple-payment.php";
            require_once "cat.php";


            // Note: add_action must follow 'woocommerce_account_{your-endpoint-slug}_endpoint' format


            add_filter('woocommerce_payment_gateways', 'bancard_add_gateway_class');
            function bancard_add_gateway_class($gateways) {
                $gateways[] = 'WC_bancard_Gateway';
                return $gateways;
            }
            add_action('plugins_loaded', 'bancard_init_gateway_class');
            function bancard_init_gateway_class() {

                class WC_Bancard_Gateway extends WC_Payment_Gateway {
                    public function __construct() {

                        $this->id = 'bancard'; 
                        $this->icon = ''; 
                        $this->has_fields = true;
                        $this->method_title = 'Portal de Pagos BANCARD';
                        $this->method_description = 'Acepte pagos a través de Crédito y Débido a través del vPOS de Bancard';
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
                        $this->bancard_endpoint = $this->testmode ? "https://vpos.infonet.com.py:8888" : "https://vpos.infonet.com.py";

                        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(
                            $this,
                            'process_admin_options'
                        ));

                        add_action('wp_enqueue_scripts', array(
                            $this,
                            'payment_scripts'
                        ));

                        add_action('woocommerce_api_bancard', array(
                            $this,
                            'webhook'
                        ));

                        if (!defined('BANCARD_VAULT')) {
                            add_filter('woocommerce_order_button_text', array($this,'btn_pago_bancard'));
                        }
                        if ($this->check_if_card_table_exists() == false) {
                            $this->create_card_table();
                        }

                    }

                    private function check_if_card_table_exists() {
                        global $wpdb;

                        $table_name = $wpdb->prefix."bcards";

                        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
                            return true;
                        }
                        else {
                            return false;
                        }
                    }
                    private function create_card_table() {
                        global $wpdb;

                        $table_name = $wpdb->prefix."bcards";

                        $query = "CREATE TABLE {$table_name} ( `id` INT NOT NULL AUTO_INCREMENT , `user_id` INT NOT NULL , `is_used` BOOLEAN NOT NULL , PRIMARY KEY (`id`));";
                        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
                        dbDelta($query);
                    }


                    public function init_form_fields() {

                        $this->form_fields = array(
                            'enabled' => array(
                                'title' => 'Activar/Desactivar',
                                'label' => 'Activar portal de pagos de Bancard',
                                'type' => 'checkbox',
                                'description' => '',
                                'default' => 'no'
                            ) ,
                            'title' => array(
                                'title' => 'Título',
                                'type' => 'text',
                                'description' => 'Esto es el título de el usuario ve en la página de pagos.',
                                'default' => 'Tarjeta de Crédito/Débito',
                                'desc_tip' => true
                            ) ,
                            'description' => array(
                                'title' => 'Descripción',
                                'type' => 'textarea',
                                'description' => 'Descripción del portal de pagos, el usuario ve esto en la página de pagos.',
                                'default' => 'Pague con su tarjeta de Crédito o Débito a través del vPOS de Bancard'
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
                        <div id="info-bancard-field">Pague con su tarjeta de crédito o débito de forma rápida y segura con el vPOS de Bancard.</div>
                        <div id="bancard-checkout"></div>
                        <?php
                        do_action('woocommerce_credit_card_form_end', $this->id);

                    }

                    public function payment_scripts() {

                        if (!is_cart() && !is_account_page() && !is_checkout() && !isset($_GET['pay_for_order'])) {
                            return;
                        }

                        if ('no' === $this->enabled) {
                            return;
                        }

                        if (empty($this->private_key) || empty($this->publishable_key)) {
                            return;
                        }

                        $checkout_script = $this->testmode ? "../bancard-checkout-dev.js" : "../bancard-checkout.js";

                        wp_enqueue_script('bancard_js', plugins_url($checkout_script, __FILE__));

                        wp_register_script('woocommerce_bancard', plugins_url('../woocommerce-bancard.js', __FILE__) , array(
                            'jquery',
                            'bancard_js'
                        ));

                        wp_localize_script('woocommerce_bancard', 'bancard_params', array(
                            'publishableKey' => $this->publishable_key
                        ));

                        wp_enqueue_script('woocommerce_bancard');

                        wp_register_style( 'woo-bancard-cards', plugins_url('../css/cards.css', __FILE__) );
                        wp_enqueue_style('woo-bancard-cards');

                    }

                    public function process_payment($order_id) {

                        global $woocommerce;
                        $order = wc_get_order($order_id);
                        $order_data = $order->get_data();
                        $cart_currency = $order->get_currency(); 
                        $cart_amount = ($cart_currency == "PYG") ? $order->get_total() . ".00" : $order->get_total();
                        $unhash = $this->private_key . $order->get_order_number() . $cart_amount . $cart_currency; 
                        $hash = hash("sha256",$unhash);
                        $args = [
                            "public_key" => $this->publishable_key,
                            "operation" => [
                                "token" => $hash,
                                "shop_process_id" => $order->get_order_number(),
                                "currency" => $cart_currency,
                                "amount" => $cart_amount,
                                "description" => "Pagá tus productos de " . get_bloginfo('name') . " de forma rápida y segura con BANCARD",
                                "return_url" => get_site_url(null, '/wc-api/bancard/?order_id=' . $order_id, "https") ,
                                "cancel_url" => get_site_url(null, '/wc-api/bancard/?order_id=' . $order_id, "https")
                            ]];
                        $url = $this->bancard_endpoint . "/vpos/api/0.3/single_buy";
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

                            $order->update_status('on-hold', 'Esperando confirmación de pago de Bancard.');

                            wc_add_notice("<span data-order=\"{$process}\" data-method=\"bancard\"></span>Favor ingrese los datos de su tarjeta de crédito o débito para finalizar la compra.", 'notice');
                            return;
                        }
                        else {
                            wc_add_notice("Hubo un error al procesar el pago y no fue realizada ninguna transacción. Favor intente hacer una nueva compra, en caso de persistencia póngase en contacto con nosotros. {$order->get_order_number()}", 'error');
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
                        $args = ["public_key" => $this->publishable_key, "operation" => ["token" => $hash, "shop_process_id" => $order->get_order_number()]];
                        $url = $this->bancard_endpoint . "/vpos/api/0.3/single_buy/rollback";
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

                    function btn_pago_bancard($button_text) {
                        define('BANCARD_VAULT', true);
                        $button_text = 'Proceder al pago';
                        return $button_text;   
                    }

                    public function webhook() {
                        ob_start();
                        if ($_SERVER['REQUEST_METHOD'] == "POST") {


                            $b_response = json_decode(file_get_contents("php://input") , true);
                            error_log(print_r($b_response,true));

                            $order_number = $b_response['operation']['shop_process_id'];

                            global $wpdb;
                            $results = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}postmeta WHERE meta_value = {$order_number}", OBJECT );

                            $order_id = ($results[0]->meta_value == $b_response['operation']['shop_process_id']) ? $results[0]->post_id : $b_response['operation']['shop_process_id'];

                            $order = wc_get_order($order_id);

                            
                            if ($b_response['operation']['response'] == "S") {
                                if ($b_response['operation']['response_code'] == "00") {
                                    $order->payment_complete();
                                    wc_reduce_stock_levels($order_id);
                                    $order->add_order_note("Se procesó exitosamente el pago por parte de BANCARD. Código de Autorización: {$b_response['operation']['authorization_number']}. Ticket: {$b_response['operation']['ticket_number']}");
                                    $order->add_order_note("Gracias por comprar en " . get_bloginfo('name') . ". Recibimos tu pago y estaremos en contacto contigo para confirmar la entrega.", true);
                                    echo json_encode(array(
                                        "status" => "success"
                                    ));
                                    exit();
                                }
                                else {
                                    $order->update_status('failed', "Hubo un error al procesar su pago: {$b_response['operation']['response_description']}. {$b_response['operation']['extended_response_description']}.");
                                    http_response_code(400);
                                    echo json_encode(array(
                                        "status" => "fail"
                                    ));
                                    exit();
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
        }
        else {
            function bancard_plugin_load_novalgina() {
                $class = 'notice notice-error';
                $message = 'Lo sentimos, la licencia del plugin "WooCommerce Bancard Gateway" ha expirado y por tanto se ha desactivado. Para activar la licencia contacte al desarrollador del plugin.';
                printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) ); 
                $plugins = array(
                'bancard-payment/bancard-payment.php'
                );
                require_once(ABSPATH . 'wp-admin/includes/plugin.php');
                deactivate_plugins($plugins);
            }
            add_action( 'admin_notices', 'bancard_plugin_load_novalgina' );
        }
    }
    else {
        exit("Token NOT OK");
    }
}
