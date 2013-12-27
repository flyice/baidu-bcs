<?php

/**
 Plugin Name: 百度云存储
Plugin URI:
Description: 上传附件到百度云存储。
Version: 0.1
Author: Coda
Author URI:
License: GPLv2
*/
if ( ! defined( 'WPINC' ) )
	die();

if ( ! defined( 'BAIDU_BCS_INC_DIR' ) )
	define( 'BAIDU_BCS_INC_DIR', plugin_dir_path( __FILE__ ) . 'includes' );

if ( ! defined( 'BAIDU_BCS_SDK_DIR' ) )
	define( 'BAIDU_BCS_SDK_DIR', BAIDU_BCS_INC_DIR . '/Baidu-BCS-SDK' );

require_once BAIDU_BCS_INC_DIR . '/class-baidu-bcs-plugin.php';

new Baidu_BCS_Plugin();