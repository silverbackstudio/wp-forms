<?php

/**
 * @package Silverback Form Classes
 * @version 1.1
 */

/**
Plugin Name: Silverback Form Classes
Plugin URI: https://github.com/silverbackstudio/wp-email
Description: SilverbackStudio Mailing Classes
Author: Silverback Studio
Version: 1.1
Author URI: http://www.silverbackstudio.it/
Text Domain: svbk-forms
 */


function svbk_forms_init() {
	load_plugin_textdomain( 'svbk-forms', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}

add_action( 'plugins_loaded', 'svbk_forms_init' );
