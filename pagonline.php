<?php

/*$nzshpcrt_gateways[$num] = array(
    'name' => 'Unicredit PagOnline',
    'display_name' => 'Unicredit PagOnline',
    'internalname' => 'pagonline',
    'form' => 'form_pagonline',
    'submit_function' => 'submit_pagonline',
    'class_name' => 'wpsc_merchant_pagonline',
    'api_version' => 2.0
);*/

class wpsc_merchant_pagonline extends wpsc_merchant
{
    function __construct($purchase_id = null, $is_receiving = false)
    {
        parent::__construct($purchase_id, $is_receiving);
    }

    function submit()
    {
        global $wpdb;

        $this->set_purchase_processed_by_purchid(2);
        $log = $wpdb->get_row('SELECT * FROM `' . WPSC_TABLE_PURCHASE_LOGS . '` WHERE `sessionid` = ' . $this->cart_data['session_id'] . ' LIMIT 1');

        $totale_price = $log->totalprice;
        $totale_price = str_replace(get_option('wpsc_decimal_separator'), '', $totale_price);
        $totale_price = str_replace(get_option('wpsc_thousands_separator'), '', $totale_price);

        $query = 'numeroCommerciante=' . get_option('pagonline_numeroCommerciante') .
            '&userID=' . get_option('pagonline_userID') .
            '&password=' . get_option('pagonline_password') .
            '&numeroOrdine=' . $log->id .
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
        $query .= '&mac=' . urlencode($mac_encoded);

        wp_redirect('https://pagamenti.unicredito.it/initInsert.do?' . $query);

        exit();
    }
}
