<?php
/**
 * Catálogo de serviços conhecidos e dos cookies que eles instalam.
 *
 * Os prazos marcados com 'confirmar' => true vieram de listagens públicas e
 * devem ser conferidos pelo verificador antes de cada versão.
 *
 * @package LetsCookies
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Lets_Cookies_Catalog {

	/**
	 * Todos os serviços do catálogo.
	 *
	 * @return array<string,array>
	 */
	public static function services() {
		$services = array(
			'lets_cookies' => array(
				'name'     => 'Lets Cookies (este site)',
				'category' => 'necessary',
				'cookies'  => array(
					array( 'lets_cookies_consent', 'Este site', 'Guarda a sua escolha sobre cookies, para o aviso não reaparecer a cada página.', '6 meses' ),
				),
			),
			'ga4'          => array(
				'name'     => 'Google Analytics',
				'category' => 'analytics',
				'cookies'  => array(
					array( '_ga', 'Google', 'Distingue visitantes para medir a audiência.', '2 anos' ),
					array( '_ga_<ID>', 'Google', 'Mantém o estado da sessão.', '2 anos' ),
				),
			),
			'clarity'      => array(
				'name'     => 'Microsoft Clarity',
				'category' => 'analytics',
				'cookies'  => array(
					array( '_clck', 'Microsoft', 'Guarda o identificador do Clarity e preferências deste site.', '1 ano', true ),
					array( '_clsk', 'Microsoft', 'Une as páginas vistas numa mesma sessão.', '1 dia', true ),
					array( 'CLID', 'Microsoft', 'Identifica a primeira visita a sites com Clarity.', '1 ano', true ),
					array( 'MUID', 'Microsoft', 'Identifica o navegador em serviços da Microsoft.', '1 ano', true ),
				),
			),
			'rdstation'    => array(
				'name'     => 'RD Station Marketing',
				'category' => 'marketing',
				'cookies'  => array(
					array( '__trf.src', 'RD Station', 'Registra a origem da visita para a automação de marketing.', '1 ano', true ),
					array( 'rdtrk', 'RD Station', 'Associa as visitas a um contato da base de marketing.', '1 ano', true ),
				),
			),
			'meta'         => array(
				'name'     => 'Meta Pixel',
				'category' => 'marketing',
				'cookies'  => array(
					array( '_fbp', 'Meta', 'Mede campanhas e permite anúncios no Facebook e Instagram.', '3 meses', true ),
				),
			),
			'linkedin'     => array(
				'name'     => 'LinkedIn Insight',
				'category' => 'marketing',
				'cookies'  => array(
					array( 'li_sugr', 'LinkedIn', 'Identifica o navegador para medir campanhas.', '3 meses', true ),
					array( 'bcookie', 'LinkedIn', 'Identifica o navegador em serviços do LinkedIn.', '1 ano', true ),
				),
			),
			'youtube'      => array(
				'name'     => 'YouTube',
				'category' => 'embedded',
				'cookies'  => array(
					array( 'YSC', 'YouTube (Google)', 'Registra as visualizações dos vídeos incorporados.', 'Sessão' ),
					array( 'VISITOR_INFO1_LIVE', 'YouTube (Google)', 'Guarda preferências do player e mede a conexão.', '6 meses', true ),
					array( 'VISITOR_PRIVACY_METADATA', 'YouTube (Google)', 'Guarda o estado de privacidade do visitante no YouTube.', '6 meses', true ),
				),
			),
			'vimeo'        => array(
				'name'     => 'Vimeo',
				'category' => 'embedded',
				'cookies'  => array(
					array( 'vuid', 'Vimeo', 'Registra as visualizações dos vídeos incorporados.', '2 anos', true ),
				),
			),
			'spotify'      => array(
				'name'     => 'Spotify',
				'category' => 'embedded',
				'cookies'  => array(
					array( 'sp_t', 'Spotify', 'Identificador anônimo do player.', '1 ano', true ),
					array( 'sp_landing', 'Spotify', 'Registra a página de entrada no player.', '1 dia', true ),
				),
			),
			'soundcloud'   => array(
				'name'     => 'SoundCloud',
				'category' => 'embedded',
				'cookies'  => array(
					array( 'sc_anonymous_id', 'SoundCloud', 'Identificador anônimo do player de áudio.', '1 ano', true ),
				),
			),
			'googlemaps'   => array(
				'name'     => 'Google Maps',
				'category' => 'embedded',
				'cookies'  => array(
					array( 'NID', 'Google', 'Guarda preferências e informações do mapa incorporado.', '6 meses', true ),
				),
			),
		);

		/**
		 * Permite acrescentar serviços próprios de um site.
		 *
		 * @param array $services Catálogo.
		 */
		return apply_filters( 'lets_cookies_catalog', $services );
	}

	/**
	 * Serviços em uso neste site: o próprio plugin, integrações com ID
	 * preenchido e os conteúdos incorporados, se a categoria estiver ativa.
	 *
	 * @return array<string,array>
	 */
	public static function active_services() {
		$settings = Lets_Cookies_Settings::get();
		$enabled  = Lets_Cookies_Settings::enabled_categories();
		$all      = self::services();
		$active   = array( 'lets_cookies' => $all['lets_cookies'] );

		foreach ( $settings['integrations'] as $key => $id ) {
			if ( '' !== $id && isset( $all[ $key ] ) && in_array( $all[ $key ]['category'], $enabled, true ) ) {
				$active[ $key ] = $all[ $key ];
			}
		}

		if ( in_array( 'embedded', $enabled, true ) ) {
			foreach ( array( 'youtube', 'vimeo', 'spotify', 'soundcloud', 'googlemaps' ) as $key ) {
				$active[ $key ] = $all[ $key ];
			}
		}

		/**
		 * Ajusta a lista de serviços ativos (por exemplo, para tirar provedores
		 * de vídeo que o site não usa).
		 */
		return apply_filters( 'lets_cookies_active_services', $active );
	}

	/**
	 * Cookies ativos agrupados por categoria.
	 *
	 * @return array<string,array> categoria => lista de [nome, provedor, finalidade, prazo, serviço]
	 */
	public static function cookies_by_category() {
		$grouped = array();
		foreach ( self::active_services() as $service ) {
			foreach ( $service['cookies'] as $cookie ) {
				$grouped[ $service['category'] ][] = array(
					'name'     => $cookie[0],
					'provider' => $cookie[1],
					'purpose'  => $cookie[2],
					'duration' => $cookie[3],
					'service'  => $service['name'],
				);
			}
		}
		return $grouped;
	}
}
