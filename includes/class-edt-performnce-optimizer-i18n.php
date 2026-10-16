<?php

/**
 * Define the internationalization functionality
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @link       https://sujay-kumar-kotal.vercel.app
 * @since      1.0.0
 *
 * @package    Edt_Performnce_Optimizer
 * @subpackage Edt_Performnce_Optimizer/includes
 */

/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @since      1.0.0
 * @package    Edt_Performnce_Optimizer
 * @subpackage Edt_Performnce_Optimizer/includes
 * @author     Sujay Kumar Kotal <sujaykumarkotal8520@gmail.com>
 */
class Edt_Performnce_Optimizer_i18n {


	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since    1.0.0
	 */
	public function load_plugin_textdomain() {

		load_plugin_textdomain(
			'edt-performnce-optimizer',
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
		);

	}



}
