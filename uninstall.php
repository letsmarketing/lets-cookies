<?php
/**
 * Desinstalação.
 *
 * Por padrão o REGISTRO DE CONSENTIMENTO É MANTIDO: ele é prova para o cliente
 * e apagá-lo ao remover o plugin poderia deixar o controlador sem evidência.
 * Para apagar tudo, defina no wp-config.php:
 *   define( 'LETS_COOKIES_DELETE_DATA', true );
 *
 * @package LetsCookies
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

wp_clear_scheduled_hook( 'lets_cookies_cleanup' );

if ( defined( 'LETS_COOKIES_DELETE_DATA' ) && LETS_COOKIES_DELETE_DATA ) {
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}lets_cookies_log" );
	delete_option( 'lets_cookies_settings' );
	delete_option( 'lets_cookies_db_version' );
}
