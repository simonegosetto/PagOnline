<?php
/*
Plugin Name: WPEC Unicredit PagOnline
Plugin URI: http://github.com/MicheleBertoli/PagOnline
Description: Italian Unicredit PagOnline Payment Gateway for WP e-Commerce (http://getshopped.org)
Version: 1.0
Author: Michele Bertoli @ Gummy Industries
Author URI: http://gummyindustries.com
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

/*
 * This action hook registers our PHP class as a WooCommerce payment gateway
 */
add_filter('woocommerce_payment_gateways', 'pagonline_add_gateway_class');
function pagonline_add_gateway_class($gateways)
{
    $gateways[] = 'WC_PagOnline_Gateway'; // your class name is here
    return $gateways;
}

add_action('plugins_loaded', 'pagonline_init_gateway_class');
function pagonline_init_gateway_class()
{
    class WC_PagOnline_Gateway extends WC_Payment_Gateway
    {

        public function __construct()
        {

            $this->id = 'pagonline'; // payment gateway plugin ID
            $this->icon = ''; // URL of the icon that will be displayed on checkout page near your gateway name
            $this->has_fields = true; // in case you need a custom credit card form
            $this->method_title = 'PagOnline Gateway';
            $this->method_description = 'Description of payment gateway'; // will be displayed on the options page

            // gateways can support subscriptions, refunds, saved payment methods,
            // but in this tutorial we begin with simple payments
            $this->supports = array(
                'products'
            );

            // Method with all the options fields
            $this->init_form_fields();

            // Load the settings.
            $this->init_settings();
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->enabled = $this->get_option('enabled');


            $this->pagonline_numeroCommerciante = $this->get_option('pagonline_numeroCommerciante');
            $this->pagonline_stabilimento = $this->get_option('pagonline_stabilimento');
            $this->pagonline_userID = $this->get_option('pagonline_userID');
            $this->pagonline_password = $this->get_option('pagonline_password');
            $this->pagonline_flagDeposito = $this->get_option('pagonline_flagDeposito');
            $this->pagonline_urlOk = $this->get_option('pagonline_urlOk');
            $this->pagonline_urlKo = $this->get_option('pagonline_urlKo');
            $this->pagonline_tipoRispostaApv = 'click'; // $this->get_option('pagonline_tipoRispostaApv');
            $this->pagonline_flagRiciclaOrdine = 'N'; // $this->get_option('pagonline_flagRiciclaOrdine');
            $this->pagonline_stringaSegreta = $this->get_option('pagonline_stringaSegreta');

            // This action hook saves the settings
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

            // We need custom JavaScript to obtain a token
            add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));

            // You can also register a webhook here
            // add_action( 'woocommerce_api_{webhook name}', array( $this, 'webhook' ) );
        }

        public function init_form_fields()
        {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => 'Enable/Disable',
                    'label' => 'Enable PagOnline Gateway',
                    'type' => 'checkbox',
                    'description' => '',
                    'default' => 'no'
                ),
                'title' => array(
                    'title' => 'Title',
                    'type' => 'text',
                    'description' => 'This controls the title which the user sees during checkout.',
                    'default' => 'UniCredit',
                    'desc_tip' => true,
                ),
                'description' => array(
                    'title' => 'Description',
                    'type' => 'textarea',
                    'description' => 'This controls the description which the user sees during checkout.',
                    'default' => 'Pay with your credit card via our super-cool payment gateway.',
                ),

                'pagonline_numeroCommerciante' => array(
                    'title' => 'Numero Commerciante',
                    'label' => 'Numero Commerciante',
                    'type' => 'text',
                    'description' => 'Codice identificativo del merchant',
                ),
                'pagonline_stabilimento' => array(
                    'title' => 'Stabilimento',
                    'label' => 'Stabilimento',
                    'type' => 'text',
                    'description' => 'Codice identificativo del punto vendita',
                ),
                'pagonline_userID' => array(
                    'title' => 'User ID',
                    'label' => 'User ID',
                    'type' => 'text',
                    'description' => 'Nome utente per l\'accesso al sistema di pagamento',
                ),
                'pagonline_password' => array(
                    'title' => 'Password',
                    'label' => 'Password',
                    'type' => 'password',
                    'description' => 'Password per l\'accesso al sistema di pagamento',
                ),
                'pagonline_flagDeposito' => array(
                    'title' => 'Deposito',
                    'label' => 'Deposito',
                    'type' => 'checkbox',
                    'description' => 'Modalità di deposito automatico o manuale',
                ),
                'pagonline_urlOk' => array(
                    'title' => 'Url Ok',
                    'label' => 'Url Ok',
                    'type' => 'text',
                    'description' => 'Indirizzo dell\'esercente a cui verrà indirizzato il compratore in caso di transazione con esito positivo',
                ),
                'pagonline_urlKo' => array(
                    'title' => 'Url Ko',
                    'label' => 'Url Ko',
                    'type' => 'text',
                    'description' => 'Indirizzo dell\'esercente a cui verrà indirizzato il compratore in caso di transazione con esito negativo',
                ),
                'pagonline_stringaSegreta' => array(
                    'title' => 'Stringa segreta',
                    'label' => 'Stringa segreta',
                    'type' => 'text',
                    'description' => '',
                ),

            );
        }

        public function payment_fields()
        {
        }

        public function payment_scripts()
        {
        }

        public function validate_fields()
        {
            return true;
        }

        public function process_payment($order_id)
        {
            try {
                global $woocommerce;
                $order = wc_get_order($order_id);
                $totale_price = $order->get_total();

                /*$query = 'numeroCommerciante=' . get_option('pagonline_numeroCommerciante') .
                    '&userID=' . get_option('pagonline_userID') .
                    '&password=' . get_option('pagonline_password') .
                    '&numeroOrdine=' . $order_id .
                    '&totaleOrdine=' . $totale_price .
                    '&valuta=978' .
                    '&flagDeposito=' . get_option('pagonline_flagDeposito') .
                    '&urlOk=' . get_option('pagonline_urlOk') .
                    '&urlKo=' . get_option('pagonline_urlKo') .
                    '&tipoRispostaApv=' . get_option('pagonline_tipoRispostaApv') .
                    '&flagRiciclaOrdine=' . get_option('pagonline_flagRiciclaOrdine') .
                    '&stabilimento=' . get_option('pagonline_stabilimento');

                $string_to_digest = $query . '&' . get_option('pagonline_stringaSegreta');
                $mac = hash('md5', $string_to_digest, true);
                $mac_encoded = base64_encode($mac);
                $mac_encoded = substr($mac_encoded, 0, 24);

                $query = str_replace(get_option('pagonline_urlOk'), urlencode(get_option('pagonline_urlOk')), $query);
                $query = str_replace(get_option('pagonline_urlKo'), urlencode(get_option('pagonline_urlKo')), $query);
                $query .= '&mac=' . urlencode($mac_encoded);*/

                // wp_redirect('https://pagamenti.unicredito.it/initInsert.do?' . $query);

                exit();
            } catch (Exception $e) {
                error_log($e->getMessage(), 0);
            }
        }
    }

}
/*
if (!class_exists('pagonline_loader'))
{
	class pagonline_loader
	{
		private $source;
		private $destination;

		public function __construct()
		{
			$this->source = WP_PLUGIN_DIR . '/pagonline/pagonline.php';
			$this->destination = WP_PLUGIN_DIR . '/wp-e-commerce/wpsc-merchants/pagonline.php';	
		}

		public function load()
		{
			register_activation_hook(__file__, array(&$this, 'activate'));
			register_deactivation_hook(__file__, array(&$this, 'deactivate'));
		}

		public function activate()
		{
			if (!file_exists($this->destination)) 
			{
				copy($this->source, $this->destination);
			}
		}

		public function deactivate()
		{
			if (file_exists($this->destination)) 
			{
				unlink($this->destination);
			}
		}
	}

	$loader = new pagonline_loader();
	$loader->load();
}*/
