<?php
if( ! defined( 'ABSPATH' ) ){
    exit;
}

do_action( 'streamvid/playlist/form', array('type'=>'edit','term_id' => $_GET['playlist']) );