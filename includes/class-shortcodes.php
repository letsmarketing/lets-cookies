<?php
/**
 * Shortcodes para a página da Política de Cookies.
 *
 * [lets_cookies_table]        Tabela dos cookies em uso, por categoria.
 * [lets_cookies_preferences]  Link que reabre as preferências.
 *
 * @package LetsCookies
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Lets_Cookies_Shortcodes {

	public static function init() {
		add_shortcode( 'lets_cookies_table', array( __CLASS__, 'table' ) );
		add_shortcode( 'lets_cookies_preferences', array( __CLASS__, 'preferences' ) );
	}

	public static function table() {
		$labels  = Lets_Cookies_Settings::category_labels();
		$grouped = Lets_Cookies_Catalog::cookies_by_category();
		$order   = array_merge( array( 'necessary' ), Lets_Cookies_Settings::enabled_categories() );
		$html    = '<div class="lets-ck-policy-table">';

		foreach ( $order as $cat ) {
			if ( empty( $grouped[ $cat ] ) ) {
				continue;
			}
			$html .= '<h3>' . esc_html( $labels[ $cat ] ) . '</h3>';
			$html .= '<table><thead><tr><th scope="col">Cookie</th><th scope="col">Provedor</th><th scope="col">Finalidade</th><th scope="col">Prazo</th></tr></thead><tbody>';
			foreach ( $grouped[ $cat ] as $cookie ) {
				$html .= '<tr><td>' . esc_html( $cookie['name'] ) . '</td><td>' . esc_html( $cookie['provider'] ) . '</td><td>' . esc_html( $cookie['purpose'] ) . '</td><td>' . esc_html( $cookie['duration'] ) . '</td></tr>';
			}
			$html .= '</tbody></table>';
		}

		return $html . '</div>';
	}

	public static function preferences( $atts ) {
		$atts = shortcode_atts( array( 'text' => 'Preferências de cookies' ), $atts, 'lets_cookies_preferences' );
		return '<a href="#lets-cookies" class="lets-cookies-open">' . esc_html( $atts['text'] ) . '</a>';
	}
}
