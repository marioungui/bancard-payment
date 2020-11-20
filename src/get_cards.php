<?php
if (isset($_GET['action']) && $_GET['action'] == "get_cards")
{
    $Bcatastro = new WC_Catastro_Gateway;
    $unhash = $Bcatastro->private_key . get_current_user_id() . "request_user_cards";
    $hash = hash("sha256", $unhash);

    $args = ["public_key" => $Bcatastro->publishable_key, "operation" => ["token" => $hash]];

    $url = $Bcatastro->catastro_endpoint . "/vpos/api/0.3/users/" . get_current_user_id() . "/cards";
    $ch = curl_init($url);
    $payload = json_encode($args, JSON_PRETTY_PRINT);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type:application/json'
    ));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    $resultado = json_decode($result, true);

    if ($resultado['status'] == "success")
    {
        if (empty($resultado['cards']))
        {
            echo "<p>No se registró ninguna tarjeta en su cuenta. Asegúrese de cargarlo correctamente en su perfil ubicada en la sección \"Mi Cuenta\"</p>";
        }
        else
        {
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
            foreach ($resultado['cards'] as $tarjeta => $valor)
            {
                $url = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . explode('?', $_SERVER['REQUEST_URI'], 2) [0] . "?action=delete_card&cardid={$valor['card_id']}&ctoken={$valor['alias_token']}";
                if ($valor['card_type'] == "credit")
                {
                    $valor['card_type'] = "Tarj. Crédito";
                }
                if ($valor['card_type'] == "debit")
                {
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
        }
    }
}