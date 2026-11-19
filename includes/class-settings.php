<?php
/**
 * Configurações do plugin: valores padrão, leitura e sanitização.
 *
 * @package LetsCookies
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Lets_Cookies_Settings {

	const OPTION = 'lets_cookies_settings';

	/** Categorias que dependem de consentimento (Necessários é sempre ativa). */
	const CATEGORIES = array( 'analytics', 'embedded', 'marketing' );

	/**
	 * Valores padrão, já com textos-modelo em português para a LGPD.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'enabled_categories' => array( 'analytics', 'embedded' ),
			'policy_url'         => '',
			'policy_version'     => 1,
			'rights_url'         => '',
			'retention_days'     => 365,
			'show_floating'      => 1,
			'position'           => 'bottom-right',
			'color_primary'      => '#55693c',
			'color_text'         => '#1f1f1f',
			'color_background'   => '#ffffff',
			'integrations'       => array(
				'ga4'      => '',
				'clarity'  => '',
				'rdstation' => '',
				'meta'     => '',
				'linkedin' => '',
			),
			'texts'              => array(
				'title'          => 'Sua privacidade',
				'message'        => 'Usamos cookies necessários para o funcionamento do site. Com o seu consentimento, também usamos cookies para entender como o site é utilizado e para exibir conteúdos de terceiros, como vídeos. Você pode aceitar todos, recusar os não necessários ou escolher por categoria.',
				'policy_link'    => 'Política de Cookies',
				'accept_all'     => 'Aceitar todos',
				'reject_all'     => 'Rejeitar não necessários',
				'customize'      => 'Personalizar',
				'save'           => 'Salvar preferências',
				'prefs_title'    => 'Preferências de cookies',
				'prefs_intro'    => 'Escolha quais categorias de cookies você autoriza. Os cookies necessários ficam sempre ativos, pois sem eles o site não funciona. Você pode mudar sua escolha a qualquer momento pelo link "Preferências de cookies".',
				'always_active'  => 'Sempre ativos',
				'browser_info'   => 'Você também pode bloquear ou apagar cookies nas configurações do seu navegador. Isso pode afetar o funcionamento do site, e alguns recursos de terceiros podem não ser bloqueáveis por esse caminho.',
				'floating_label' => 'Preferências de cookies',
				'cat_necessary'  => 'Essenciais ao funcionamento e à segurança do site. Permitem a navegação entre páginas e registram a sua própria escolha quanto a cookies. Não dependem de consentimento.',
				'cat_analytics'  => 'Permitem entender como os visitantes usam o site, como as páginas mais acessadas e as dificuldades de navegação, para melhorar o conteúdo. Dependem do seu consentimento.',
				'cat_embedded'   => 'Instalados por conteúdos de terceiros exibidos nas páginas, como vídeos, mapas e podcasts, somente quando você os carrega. Dependem do seu consentimento.',
				'cat_marketing'  => 'Usados por redes sociais e plataformas de anúncios para medir campanhas e exibir publicidade relevante. Dependem do seu consentimento.',
			),
		);
	}

	/** Nomes das categorias exibidos ao visitante. */
	public static function category_labels() {
		return array(
			'necessary' => 'Necessários',
			'analytics' => 'Analíticos',
			'embedded'  => 'Conteúdo incorporado',
			'marketing' => 'Marketing',
		);
	}

	/**
	 * Configurações atuais mescladas com os padrões.
	 *
	 * @return array
	 */
	public static function get() {
		$saved    = get_option( self::OPTION, array() );
		$defaults = self::defaults();
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		$merged                 = array_merge( $defaults, $saved );
		$merged['integrations'] = array_merge( $defaults['integrations'], isset( $saved['integrations'] ) && is_array( $saved['integrations'] ) ? $saved['integrations'] : array() );
		$merged['texts']        = array_merge( $defaults['texts'], isset( $saved['texts'] ) && is_array( $saved['texts'] ) ? $saved['texts'] : array() );
		return $merged;
	}

	/**
	 * Categorias ativas no site, na ordem de exibição.
	 *
	 * @return string[]
	 */
	public static function enabled_categories() {
		$settings = self::get();
		$enabled  = array_values( array_intersect( self::CATEGORIES, (array) $settings['enabled_categories'] ) );
		return $enabled;
	}

	/**
	 * Sanitiza o que vem do formulário do painel.
	 *
	 * @param array $input Dados enviados.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$defaults = self::defaults();
		$current  = self::get();
		$input    = is_array( $input ) ? $input : array();
		$out      = $current;

		$cats                      = isset( $input['enabled_categories'] ) ? (array) $input['enabled_categories'] : array();
		$out['enabled_categories'] = array_values( array_intersect( self::CATEGORIES, array_map( 'sanitize_key', $cats ) ) );

		$out['policy_url']     = isset( $input['policy_url'] ) ? esc_url_raw( trim( $input['policy_url'] ) ) : '';
		$out['rights_url']     = isset( $input['rights_url'] ) ? esc_url_raw( trim( $input['rights_url'] ) ) : '';
		$out['policy_version'] = isset( $input['policy_version'] ) ? max( 1, absint( $input['policy_version'] ) ) : $current['policy_version'];
		$out['retention_days'] = isset( $input['retention_days'] ) ? min( 3650, max( 30, absint( $input['retention_days'] ) ) ) : $current['retention_days'];
		$out['show_floating']  = empty( $input['show_floating'] ) ? 0 : 1;
		$out['position']       = ( isset( $input['position'] ) && in_array( $input['position'], array( 'bottom-right', 'bottom-left', 'bottom-bar' ), true ) ) ? $input['position'] : 'bottom-right';

		foreach ( array( 'color_primary', 'color_text', 'color_background' ) as $key ) {
			$color       = isset( $input[ $key ] ) ? sanitize_hex_color( $input[ $key ] ) : '';
			$out[ $key ] = $color ? $color : $defaults[ $key ];
		}

		$patterns = array(
			'ga4'       => '/^G-[A-Z0-9]{4,20}$/',
			'clarity'   => '/^[a-z0-9]{6,20}$/',
			'rdstation' => '/^[a-f0-9-]{8,64}$/i',
			'meta'      => '/^[0-9]{6,20}$/',
			'linkedin'  => '/^[0-9]{3,20}$/',
		);
		foreach ( $patterns as $key => $pattern ) {
			$value = isset( $input['integrations'][ $key ] ) ? trim( (string) $input['integrations'][ $key ] ) : '';
			if ( 'ga4' === $key ) {
				$value = strtoupper( $value );
			}
			if ( 'clarity' === $key ) {
				$value = strtolower( $value );
			}
			$out['integrations'][ $key ] = ( '' !== $value && preg_match( $pattern, $value ) ) ? $value : '';
		}

		foreach ( array_keys( $defaults['texts'] ) as $key ) {
			if ( isset( $input['texts'][ $key ] ) ) {
				// options.php já remove as barras antes de chamar este callback.
				$text                 = sanitize_textarea_field( $input['texts'][ $key ] );
				$out['texts'][ $key ] = '' !== trim( $text ) ? $text : $defaults['texts'][ $key ];
			}
		}

		return $out;
	}
}
