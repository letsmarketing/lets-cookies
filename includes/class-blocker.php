<?php
/**
 * Bloqueio de conteúdos e scripts de terceiros até o consentimento.
 *
 * - Iframes de provedores conhecidos no conteúdo viram um aviso com botão
 *   "Carregar conteúdo"; o iframe fica num <template> inerte.
 * - Scripts saem como type="text/plain" com data-lets-category e só são
 *   ativados pelo JS quando a categoria é autorizada.
 *
 * @package LetsCookies
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Lets_Cookies_Blocker {

	public static function init() {
		add_filter( 'the_content', array( __CLASS__, 'filter_content' ), 99 );
		add_filter( 'widget_text_content', array( __CLASS__, 'filter_content' ), 99 );
		add_filter( 'widget_custom_html_content', array( __CLASS__, 'filter_content' ), 99 );
	}

	/**
	 * Provedores reconhecidos: domínio => [serviço, nome exibido].
	 *
	 * @return array
	 */
	public static function providers() {
		return apply_filters(
			'lets_cookies_embed_providers',
			array(
				'youtube.com'          => array( 'youtube', 'YouTube' ),
				'youtube-nocookie.com' => array( 'youtube', 'YouTube' ),
				'youtu.be'             => array( 'youtube', 'YouTube' ),
				'vimeo.com'            => array( 'vimeo', 'Vimeo' ),
				'spotify.com'          => array( 'spotify', 'Spotify' ),
				'google.com'           => array( 'googlemaps', 'Google Maps' ),
				'google.com.br'        => array( 'googlemaps', 'Google Maps' ),
			)
		);
	}

	/**
	 * Descobre o provedor de uma URL.
	 *
	 * @param string $url URL do iframe.
	 * @return array|null [serviço, nome]
	 */
	public static function provider_for( $url ) {
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		if ( '' === $host ) {
			return null;
		}
		foreach ( self::providers() as $domain => $info ) {
			if ( $host === $domain || substr( $host, -strlen( '.' . $domain ) ) === '.' . $domain ) {
				// Em google.com só o que for mapa conta como Google Maps.
				if ( 'googlemaps' === $info[0] && false === strpos( (string) wp_parse_url( $url, PHP_URL_PATH ), '/maps' ) && 0 !== strpos( $host, 'maps.' ) ) {
					continue;
				}
				return $info;
			}
		}
		return null;
	}

	/**
	 * Troca iframes de provedores conhecidos por avisos bloqueados.
	 *
	 * @param string $html Conteúdo.
	 * @return string
	 */
	public static function filter_content( $html ) {
		if ( is_admin() || is_feed() || ! is_string( $html ) || false === stripos( $html, '<iframe' ) ) {
			return $html;
		}
		return preg_replace_callback( '#<iframe\b[^>]*>.*?</iframe>#is', array( __CLASS__, 'replace_iframe' ), $html );
	}

	/**
	 * @param array $m Casamento do regex.
	 * @return string
	 */
	public static function replace_iframe( $m ) {
		$tag = $m[0];
		if ( false !== strpos( $tag, 'data-lets-src' ) ) {
			return $tag; // Já tratado.
		}
		if ( ! preg_match( '#\ssrc=(["\'])(.*?)\1#i', $tag, $s ) ) {
			return $tag;
		}
		$src      = html_entity_decode( $s[2], ENT_QUOTES );
		$provider = self::provider_for( $src );
		if ( null === $provider ) {
			return $tag;
		}
		$width  = preg_match( '#\swidth=(["\']?)([\d.]+%?)#i', $tag, $a ) ? $a[2] : '16';
		$height = preg_match( '#\sheight=(["\']?)([\d.]+)#i', $tag, $b ) ? $b[2] : '9';
		$title  = preg_match( '#\stitle=(["\'])(.*?)\1#i', $tag, $t ) ? html_entity_decode( $t[2], ENT_QUOTES ) : '';
		return self::placeholder( $provider[0], $provider[1], $src, $width, $height, $title );
	}

	/**
	 * Monta o aviso bloqueado de um conteúdo incorporado.
	 *
	 * @param string $service Chave do serviço (youtube, vimeo, ...).
	 * @param string $name    Nome exibido.
	 * @param string $src     URL do iframe.
	 * @param string $width   Largura (número ou %).
	 * @param string $height  Altura em px.
	 * @param string $title   Título acessível do iframe.
	 * @return string
	 */
	public static function placeholder( $service, $name, $src, $width = '16', $height = '9', $title = '' ) {
		if ( 'youtube' === $service ) {
			$src = preg_replace( '#^https?://(www\.)?youtube\.com/#i', 'https://www.youtube-nocookie.com/', $src );
		}
		if ( false !== strpos( (string) $width, '%' ) ) {
			$style = 'height:' . (int) $height . 'px';
		} else {
			$ratio = ( (float) $width > 0 ) ? round( (float) $height / (float) $width * 100, 2 ) : 56.25;
			$style = 'padding-top:' . $ratio . '%';
		}
		$title = '' !== $title ? $title : sprintf( 'Conteúdo de %s', $name );
		$open  = self::public_url( $service, $src );

		$iframe = sprintf(
			'<iframe data-lets-src="%s" title="%s" loading="lazy" allow="autoplay; encrypted-media; fullscreen; picture-in-picture" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>',
			esc_attr( $src ),
			esc_attr( $title )
		);

		$html  = '<div class="lets-ck-embed" data-lets-category="embedded" data-lets-service="' . esc_attr( $service ) . '" style="' . esc_attr( $style ) . '">';
		$html .= '<template>' . $iframe . '</template>';
		$html .= '<div class="lets-ck-embed__box">';
		$html .= '<p>' . sprintf(
			/* translators: %s: provider name */
			esc_html__( 'Este conteúdo é fornecido por %s, que pode instalar cookies e transferir dados de navegação para fora do Brasil.', 'lets-cookies' ),
			'<strong>' . esc_html( $name ) . '</strong>'
		) . '</p>';
		$html .= '<button type="button" class="lets-ck-embed__btn">' . esc_html__( 'Carregar conteúdo', 'lets-cookies' ) . '</button>';
		$html .= '<p class="lets-ck-embed__note">' . esc_html__( 'Requer autorização dos cookies de “Conteúdo incorporado”.', 'lets-cookies' );
		if ( $open ) {
			$html .= ' <a href="' . esc_url( $open ) . '" target="_blank" rel="noopener noreferrer">' . sprintf( esc_html__( 'Abrir no %s', 'lets-cookies' ), esc_html( $name ) ) . '</a>';
		}
		$html .= '</p></div></div>';
		return $html;
	}

	/**
	 * Endereço para abrir o conteúdo no site do provedor (sem carregar nada aqui).
	 */
	private static function public_url( $service, $src ) {
		if ( 'youtube' === $service && preg_match( '#/embed/([A-Za-z0-9_-]{6,})#', $src, $id ) ) {
			return 'https://www.youtube.com/watch?v=' . $id[1];
		}
		if ( 'vimeo' === $service && preg_match( '#/video/(\d+)#', $src, $id ) ) {
			return 'https://vimeo.com/' . $id[1];
		}
		if ( 'spotify' === $service ) {
			return str_replace( '/embed/', '/', strtok( $src, '?' ) );
		}
		if ( 'googlemaps' === $service ) {
			return 'https://www.google.com/maps';
		}
		return '';
	}

	/**
	 * Script bloqueado até o consentimento da categoria.
	 *
	 * @param string $category analytics | embedded | marketing.
	 * @param string $code     JavaScript inline (opcional).
	 * @param string $src      URL do script externo (opcional).
	 * @param string $service  Chave do serviço, para depuração.
	 * @return string
	 */
	public static function script( $category, $code = '', $src = '', $service = '' ) {
		$attrs = 'type="text/plain" data-lets-category="' . esc_attr( $category ) . '"';
		if ( '' !== $service ) {
			$attrs .= ' data-lets-service="' . esc_attr( $service ) . '"';
		}
		if ( '' !== $src ) {
			$attrs .= ' data-lets-src="' . esc_url( $src ) . '"';
		}
		// O código é gerado pelo próprio plugin a partir de IDs já validados.
		return '<script ' . $attrs . '>' . $code . '</script>' . "\n";
	}
}
