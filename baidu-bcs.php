<?php

/**
Plugin Name: 百度云存储
Plugin URI:
Description: WordPress百度云存储插件。
Version: 0.1
Author: flyice
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

new Baidu_BCS_Plugin( __FILE__ );