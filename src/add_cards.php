<?php
$Bcatastro = new WC_Catastro_Gateway;

$cardid = $Bcatastro->generate_card_ID(get_current_user_id());
$user_phone = get_user_meta( get_current_user_id(), 'billing_phone', true );
$user_email = get_userdata( get_current_user_id(), 'user_email', true )->user_email;
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
error_log(print_r($payload,true));
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Content-Type:application/json'
));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$result = curl_exec($ch);
$resultado = json_decode($result,true);

error_log(print_r($resultado,true));
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
