<?php

class Bancard {
	protected string $public_key;
	protected string $private_key;
	public function __construct($public_key, $private_key,$mode = "PROD") {
		if (!$public_key || !$private_key) {
			throw new Exception("Se requieren las llaves públicas y privadas");
		}
		$this->bancard_endpoint = ($mode == "TEST") ? "https://vpos.infonet.com.py:8888/" : "https://vpos.infonet.com.py/";
	}
	public function single_buy($order_number, $cart_amount, $cart_currency) {
		if (!$order_number || !$cart_amount || !$cart_currency) {
			throw new Exception("Es requerido los 3 parámetros para hacer una operación single buy");
		}
		else {
			$unhash = $this->private_key . $order_number . $cart_amount . $cart_currency;
			$hash = hash("sha256",$unhash);
			$args = [
				"public_key" => $this->private_key,
				"operation" => [
					"token" => $hash,
					"shop_process_id" => $order_number,
					"currency" => $cart_currency,
					"amount" => $cart_amount,
					"description" => "Pagá tus productos de " . get_bloginfo('name') . " de forma rápida y segura con BANCARD",
					"return_url" => get_site_url(null, '/wc-api/bancard/?order_id=' . $order_id, "https") ,
					"cancel_url" => get_site_url(null, '/wc-api/bancard/?order_id=' . $order_id, "https")
				]
			];
			$url = $this->bancard_endpoint . "/vpos/api/0.3/single_buy";

		}
			
	}
}