<?php
/**
 * Saída no site: Consent Mode padrão, scripts das integrações (bloqueados),
 * arquivos do banner e configuração para o JavaScript.
 *
 * @package LetsCookies
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Lets_Cookies_Frontend {

	const COOKIE = 'lets_cookies_consent';

	public static function init() {
		if ( is_admin() ) {
			return;
		}
		add_action( 'wp_head', array( __CLASS__, 'consent_mode_default' ), 1 );
		add_action( 'wp_head', array( __CLASS__, 'integration_scripts' ), 20 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	/**
	 * Google Consent Mode v2: tudo negado por padrão, antes de qualquer tag.
	 * Se o visitante já escolheu, a escolha é aplicada aqui mesmo, sem esperar
	 * o carregamento do banner.
	 */
	public static function consent_mode_default() {
		$settings = Lets_Cookies_Settings::get();
		$version  = (int) $settings['policy_version'];
		?>
<script id="lets-cookies-consent-mode">
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('consent', 'default', {ad_storage:'denied', ad_user_data:'denied', ad_personalization:'denied', analytics_storage:'denied', functionality_storage:'denied', personalization_storage:'denied', security_storage:'granted', wait_for_update:500});
(function(){try{var m=document.cookie.match(/(?:^|; )<?php echo esc_js( self::COOKIE ); ?>=([^;]*)/);if(!m)return;var s=JSON.parse(decodeURIComponent(m[1]));if(!s||s.v!==<?php echo (int) $version; ?>||!s.c)return;var g=function(b){return b?'granted':'denied';};gtag('consent','update',{analytics_storage:g(s.c.analytics),ad_storage:g(s.c.marketing),ad_user_data:g(s.c.marketing),ad_personalization:g(s.c.marketing),functionality_storage:g(s.c.embedded)});}catch(e){}})();
</script>
		<?php
	}

	/**
	 * Scripts das integrações configuradas, todos bloqueados como text/plain.
	 * IDs já validados por regex na gravação das configurações.
	 */
	public static function integration_scripts() {
		$settings = Lets_Cookies_Settings::get();
		$enabled  = Lets_Cookies_Settings::enabled_categories();
		$ids      = $settings['integrations'];
		$out      = '';

		if ( '' !== $ids['ga4'] && in_array( 'analytics', $enabled, true ) ) {
			$id   = $ids['ga4'];
			$out .= Lets_Cookies_Blocker::script( 'analytics', '', 'https://www.googletagmanager.com/gtag/js?id=' . rawurlencode( $id ), 'ga4' );
			$out .= Lets_Cookies_Blocker::script( 'analytics', "gtag('js', new Date());gtag('config', " . wp_json_encode( $id ) . ');', '', 'ga4' );
		}

		if ( '' !== $ids['clarity'] && in_array( 'analytics', $enabled, true ) ) {
			$code = '(function(c,l,a,r,i,t,y){c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);})(window,document,"clarity","script",' . wp_json_encode( $ids['clarity'] ) . ');';
			$out .= Lets_Cookies_Blocker::script( 'analytics', $code, '', 'clarity' );
		}

		if ( '' !== $ids['rdstation'] && in_array( 'marketing', $enabled, true ) ) {
			$out .= Lets_Cookies_Blocker::script( 'marketing', '', 'https://d335luupugsy2.cloudfront.net/js/loader-scripts/' . rawurlencode( $ids['rdstation'] ) . '-loader.js', 'rdstation' );
		}

		if ( '' !== $ids['meta'] && in_array( 'marketing', $enabled, true ) ) {
			$code = "!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init'," . wp_json_encode( $ids['meta'] ) . ");fbq('track','PageView');";
			$out .= Lets_Cookies_Blocker::script( 'marketing', $code, '', 'meta' );
		}

		if ( '' !== $ids['linkedin'] && in_array( 'marketing', $enabled, true ) ) {
			$code = 'window._linkedin_partner_id=' . wp_json_encode( $ids['linkedin'] ) . ';window._linkedin_data_partner_ids=window._linkedin_data_partner_ids||[];window._linkedin_data_partner_ids.push(window._linkedin_partner_id);(function(l){if(!l){window.lintrk=function(a,b){window.lintrk.q.push([a,b])};window.lintrk.q=[]}var s=document.getElementsByTagName("script")[0];var b=document.createElement("script");b.type="text/javascript";b.async=true;b.src="https://snap.licdn.com/li.lms-analytics/insight.min.js";s.parentNode.insertBefore(b,s);})(window.lintrk);';
			$out .= Lets_Cookies_Blocker::script( 'marketing', $code, '', 'linkedin' );
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- montado acima com valores escapados/validados.
		echo $out;
	}

	public static function enqueue() {
		wp_enqueue_style( 'lets-cookies', LETS_COOKIES_URL . 'assets/css/lets-cookies.css', array(), LETS_COOKIES_VERSION );
		wp_enqueue_script( 'lets-cookies', LETS_COOKIES_URL . 'assets/js/lets-cookies.js', array(), LETS_COOKIES_VERSION, true );

		$settings = Lets_Cookies_Settings::get();
		$css      = sprintf(
			':root{--lets-ck-primary:%1$s;--lets-ck-text:%2$s;--lets-ck-bg:%3$s;}',
			$settings['color_primary'],
			$settings['color_text'],
			$settings['color_background']
		);
		wp_add_inline_style( 'lets-cookies', $css );

		wp_add_inline_script( 'lets-cookies', 'window.LetsCookiesConfig = ' . wp_json_encode( self::config() ) . ';', 'before' );
	}

	/**
	 * Configuração entregue ao JavaScript.
	 *
	 * @return array
	 */
	public static function config() {
		$settings   = Lets_Cookies_Settings::get();
		$labels     = Lets_Cookies_Settings::category_labels();
		$enabled    = Lets_Cookies_Settings::enabled_categories();
		$cookies    = Lets_Cookies_Catalog::cookies_by_category();
		$categories = array();

		foreach ( array_merge( array( 'necessary' ), $enabled ) as $cat ) {
			$categories[] = array(
				'key'         => $cat,
				'label'       => $labels[ $cat ],
				'description' => $settings['texts'][ 'cat_' . $cat ],
				'required'    => 'necessary' === $cat,
				'cookies'     => isset( $cookies[ $cat ] ) ? $cookies[ $cat ] : array(),
			);
		}

		return array(
			'version'      => (int) $settings['policy_version'],
			'cookieName'   => self::COOKIE,
			'cookieDays'   => 180,
			'categories'   => $categories,
			'texts'        => $settings['texts'],
			'policyUrl'    => $settings['policy_url'],
			'rightsUrl'    => $settings['rights_url'],
			'restUrl'      => esc_url_raw( rest_url( 'lets-cookies/v1/consent' ) ),
			'position'     => $settings['position'],
			'showFloating' => (bool) $settings['show_floating'],
			'clickToLoad'  => (bool) apply_filters( 'lets_cookies_click_to_load', ! empty( $settings['click_to_load'] ) ),
			'browserLinks' => self::browser_links(),
		);
	}

	/**
	 * Páginas oficiais de ajuda para bloquear cookies no navegador
	 * (Guia ANPD, pág. 32). Conferir os endereços a cada versão.
	 *
	 * @return array
	 */
	public static function browser_links() {
		return apply_filters(
			'lets_cookies_browser_links',
			array(
				array( 'name' => 'Google Chrome', 'url' => 'https://support.google.com/chrome/answer/95647?hl=pt-BR' ),
				array( 'name' => 'Mozilla Firefox', 'url' => 'https://support.mozilla.org/kb/clear-cookies-and-site-data-firefox' ),
				array( 'name' => 'Safari (Mac)', 'url' => 'https://support.apple.com/pt-br/guide/safari/gerenciar-cookies-sfri11471/mac' ),
				array( 'name' => 'Microsoft Edge', 'url' => 'https://support.microsoft.com/pt-br/edge/temporarily-allow-cookies-and-site-data-in-microsoft-edge' ),
			)
		);
	}
}
