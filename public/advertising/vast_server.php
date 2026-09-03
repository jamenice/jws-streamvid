<?php
header('Content-Type: application/xml; charset=utf-8');

extract( $args );

if( wp_http_validate_url( $ads_vast_url ) ){
    $response = wp_remote_get( $ads_vast_url );

    if( ! is_wp_error( $response ) ){
        echo wp_remote_retrieve_body( $response );
    }
}else{
    echo esc_html( $ads_vast_url );
}