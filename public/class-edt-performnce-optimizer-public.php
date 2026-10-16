<?php
/**
 * The public-facing functionality of the plugin.
 * This plugin does NOT load any additional CSS or JS on the frontend.
 * All optimization is done via dequeuing other plugins' assets.
 *
 * @package    EDT_Performance_Optimizer
 * @subpackage EDT_Performance_Optimizer/public
 */

class Edt_Performnce_Optimizer_Public {

	private string $plugin_name;
	private string $version;

	public function __construct( string $plugin_name, string $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
	}

	/**
	 * This plugin does not enqueue any frontend CSS.
	 * Keeping method signature for boilerplate compatibility.
	 */
	public function enqueue_styles(): void {
		// Intentionally empty — this plugin does not add frontend CSS.
		// All optimization is achieved by removing unnecessary assets,
		// not by adding new ones.
	}

	/**
	 * This plugin does not enqueue any frontend JavaScript.
	 * Keeping method signature for boilerplate compatibility.
	 */
	public function enqueue_scripts(): void {
		// Intentionally empty — this plugin does not add frontend JS.
	}
}
