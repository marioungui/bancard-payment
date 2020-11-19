<?php
/*
 * Plugin Name: WooCommerce Bancard Gateway
 * Plugin URI: https://emedos.com.py/
 * Description: Acepte tarjetas de crédito o débido a través del vPOS 2.0 de Bancard
 * Author: Mario Moreno
 * Author URI: https://emedos.com.py/
 * Version: 1.3.1
 * Text Domain: bancard-payment
*/

// path of the log file where errors need to be logged 
$log_file = "./my-errors.log"; 
  
// setting error logging to be active 
ini_set("log_errors", TRUE);  
  
// setting the logging file in php.ini 
ini_set('error_log', $log_file); 

require 'vendor/autoload.php';
require 'src/bpay.php';