<?php
/**
 * Funções para usar nos temas da Lets.
 *
 * @package LetsCookies
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'lets_cookies_embed' ) ) {
	/**
	 * Vídeo, mapa ou podcast já bloqueado até o consentimento.
	 *
	 * Uso no tema:  echo lets_cookies_embed( 'https://www.youtube.com/embed/ID' );
	 *
	 * @param string $src    URL do iframe do provedor.
	 * @param array  $args   width, height, title.
	 * @return string HTML (iframe original se o provedor não for reconhecido).
	 */
	function lets_cookies_embed( $src, $args = array() ) {
		$args     = wp_parse_args( $args, array( 'width' => '16', 'height' => '9', 'title' => '' ) );
		$provider = Lets_Cookies_Blocker::provider_for( $src );
		if ( null === $provider ) {
			return sprintf( '<iframe src="%s" title="%s" loading="lazy"></iframe>', esc_url( $src ), esc_attr( $args['title'] ) );
		}
		return Lets_Cookies_Blocker::placeholder( $provider[0], $provider[1], $src, (string) $args['width'], (string) $args['height'], $args['title'] );
	}
}

if ( ! function_exists( 'lets_cookies_script' ) ) {
	/**
	 * Script próprio bloqueado até o consentimento da categoria.
	 *
	 * @param string $category analytics | embedded | marketing.
	 * @param string $code     JavaScript inline.
	 * @param string $src      URL de script externo.
	 * @return string
	 */
	function lets_cookies_script( $category, $code = '', $src = '' ) {
		if ( ! in_array( $category, Lets_Cookies_Settings::CATEGORIES, true ) ) {
			return '';
		}
		return Lets_Cookies_Blocker::script( $category, $code, $src );
	}
}

if ( ! function_exists( 'lets_cookies_granted' ) ) {
	/**
	 * Diz se o visitante autorizou a categoria (útil em PHP, sem cache de página).
	 *
	 * @param string $category Categoria.
	 * @return bool
	 */
	function lets_cookies_granted( $category ) {
		if ( empty( $_COOKIE[ Lets_Cookies_Frontend::COOKIE ] ) ) {
			return false;
		}
		$state    = json_decode( wp_unslash( $_COOKIE[ Lets_Cookies_Frontend::COOKIE ] ), true ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$settings = Lets_Cookies_Settings::get();
		return is_array( $state )
			&& isset( $state['v'], $state['c'][ $category ] )
			&& (int) $state['v'] === (int) $settings['policy_version']
			&& true === $state['c'][ $category ];
	}
}
