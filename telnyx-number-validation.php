<?php
/**
 * Plugin Name: Telnyx Number Validation
 * Description: Validates phone numbers using Telnyx API on form submissions (WPForms, CF7, Elementor Forms)
 * Version: 1.0.0
 * Author: Ella’s Bubbles Dev Team
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Prevent direct access
}

// Load the main plugin class
require_once plugin_dir_path(__FILE__) . 'includes/class-telnyx-validator.php';
