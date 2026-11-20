<?php
/**
 * Plugin Name:       Lets Cookies
 * Plugin URI:        https://letsite.com.br/
 * Description:       Banner de cookies e registro de consentimento (LGPD) da Lets. Roda inteiro no próprio site: nenhuma requisição externa antes do consentimento e registro guardado no banco do WordPress.
 * Version:           0.1.1
 * Requires at least: 5.9
 * Requires PHP:      7.4
 * Author:            Lets Marketing
 * Author URI:        https://letsite.com.br/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       lets-cookies
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LETS_COOKIES_VERSION', '0.1.1' );
define( 'LETS_COOKIES_FILE', __FILE__ );
define( 'LETS_COOKIES_DIR', plugin_dir_path( __FILE__ ) );
define( 'LETS_COOKIES_URL', plugin_dir_url( __FILE__ ) );
define( 'LETS_COOKIES_DB_VERSION', '1' );

/*
 * Endereço de atualização. Fica numa constante para que a troca do GitHub
 * pelo servidor próprio da Lets seja só uma atualização comum.
 * Pode ser sobrescrita no wp-config.php.
 */
if ( ! defined( 'LETS_COOKIES_UPDATE_URL' ) ) {
	define( 'LETS_COOKIES_UPDATE_URL', '' );
}

require_once LETS_COOKIES_DIR . 'includes/class-settings.php';
require_once LETS_COOKIES_DIR . 'includes/class-catalog.php';
require_once LETS_COOKIES_DIR . 'includes/class-consent-log.php';
require_once LETS_COOKIES_DIR . 'includes/class-blocker.php';
require_once LETS_COOKIES_DIR . 'includes/class-frontend.php';
require_once LETS_COOKIES_DIR . 'includes/class-shortcodes.php';
require_once LETS_COOKIES_DIR . 'includes/class-admin.php';
require_once LETS_COOKIES_DIR . 'includes/functions.php';

register_activation_hook( __FILE__, array( 'Lets_Cookies_Consent_Log', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Lets_Cookies_Consent_Log', 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () {
		Lets_Cookies_Consent_Log::maybe_upgrade();
		Lets_Cookies_Consent_Log::init();
		Lets_Cookies_Blocker::init();
		Lets_Cookies_Frontend::init();
		Lets_Cookies_Shortcodes::init();
		if ( is_admin() ) {
			Lets_Cookies_Admin::init();
		}
	}
);
