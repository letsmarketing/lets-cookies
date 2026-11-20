<?php
/**
 * Registro de consentimento no banco do próprio WordPress.
 *
 * Guarda o mínimo necessário para comprovar o consentimento (LGPD, art. 8º):
 * ID aleatório do visitante, data e hora, versão da política, escolha por
 * categoria e o IP já truncado e transformado em hash. Nunca o IP completo.
 *
 * @package LetsCookies
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Lets_Cookies_Consent_Log {

	const CRON_HOOK  = 'lets_cookies_cleanup';
	const RATE_VISITOR = 10;   // Registros por visitante (ID + rede)...
	const RATE_NETWORK = 300;  // ...e por rede (/24), que pode ser uma empresa inteira ou um proxy...
	const RATE_WINDOW  = 600;  // ...a cada 10 minutos.
	const ACTIONS    = array( 'accept_all', 'reject_all', 'custom', 'revoke' );

	/** Nome da tabela. */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'lets_cookies_log';
	}

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		add_action( self::CRON_HOOK, array( __CLASS__, 'cleanup' ) );
		add_action( 'admin_post_lets_cookies_export', array( __CLASS__, 'export_csv' ) );
	}

	/** Cria a tabela e agenda a limpeza. */
	public static function activate() {
		self::create_table();
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/** Recria a tabela quando a estrutura muda numa atualização. */
	public static function maybe_upgrade() {
		if ( get_option( 'lets_cookies_db_version' ) !== LETS_COOKIES_DB_VERSION ) {
			self::create_table();
		}
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	private static function create_table() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table   = self::table();
		$charset = $wpdb->get_charset_collate();
		$sql     = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			consent_id char(36) NOT NULL,
			created_at datetime NOT NULL,
			policy_version int(10) unsigned NOT NULL DEFAULT 1,
			action varchar(20) NOT NULL,
			analytics tinyint(1) NOT NULL DEFAULT 0,
			embedded tinyint(1) NOT NULL DEFAULT 0,
			marketing tinyint(1) NOT NULL DEFAULT 0,
			ip_hash char(64) NOT NULL DEFAULT '',
			page varchar(255) NOT NULL DEFAULT '',
			row_hash char(64) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY consent_id (consent_id),
			KEY created_at (created_at)
		) {$charset};";
		dbDelta( $sql );
		update_option( 'lets_cookies_db_version', LETS_COOKIES_DB_VERSION, false );
	}

	public static function register_routes() {
		register_rest_route(
			'lets-cookies/v1',
			'/consent',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_request' ),
				// Rota pública de propósito: o visitante não tem login e as páginas
				// ficam em cache (um nonce expiraria). A proteção está na validação
				// estrita e no limite por visitante abaixo.
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Recebe a escolha do visitante.
	 *
	 * @param WP_REST_Request $request Requisição.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_request( $request ) {
		$data = self::validate( (array) $request->get_json_params() );
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$ip_hash = self::ip_hash( isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '' );
		if ( ! self::within_rate_limit( $ip_hash, $data['consent_id'] ) ) {
			return new WP_Error( 'lets_cookies_rate_limited', 'Muitas requisições.', array( 'status' => 429 ) );
		}

		self::insert( $data, $ip_hash );
		return new WP_REST_Response( array( 'ok' => true ), 201 );
	}

	/**
	 * Validação estrita do corpo da requisição. Público para os testes.
	 *
	 * @param array $body Corpo JSON.
	 * @return array|WP_Error
	 */
	public static function validate( $body ) {
		$consent_id = isset( $body['consent_id'] ) ? (string) $body['consent_id'] : '';
		if ( ! preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $consent_id ) ) {
			return new WP_Error( 'lets_cookies_invalid', 'ID inválido.', array( 'status' => 400 ) );
		}

		$action = isset( $body['action'] ) ? (string) $body['action'] : '';
		if ( ! in_array( $action, self::ACTIONS, true ) ) {
			return new WP_Error( 'lets_cookies_invalid', 'Ação inválida.', array( 'status' => 400 ) );
		}

		$settings = Lets_Cookies_Settings::get();
		$version  = isset( $body['version'] ) ? (int) $body['version'] : 0;
		if ( $version < 1 || $version > (int) $settings['policy_version'] ) {
			return new WP_Error( 'lets_cookies_invalid', 'Versão inválida.', array( 'status' => 400 ) );
		}

		$choices = isset( $body['choices'] ) && is_array( $body['choices'] ) ? $body['choices'] : array();
		if ( count( $choices ) > count( Lets_Cookies_Settings::CATEGORIES ) ) {
			return new WP_Error( 'lets_cookies_invalid', 'Categorias inválidas.', array( 'status' => 400 ) );
		}
		$enabled = Lets_Cookies_Settings::enabled_categories();
		$clean   = array();
		foreach ( Lets_Cookies_Settings::CATEGORIES as $cat ) {
			$value = isset( $choices[ $cat ] ) ? $choices[ $cat ] : false;
			if ( ! is_bool( $value ) ) {
				return new WP_Error( 'lets_cookies_invalid', 'Escolha inválida.', array( 'status' => 400 ) );
			}
			// Categoria desligada no site nunca é registrada como aceita.
			$clean[ $cat ] = $value && in_array( $cat, $enabled, true );
		}

		// Coerência entre a ação e as escolhas.
		if ( in_array( $action, array( 'reject_all', 'revoke' ), true ) && in_array( true, $clean, true ) ) {
			return new WP_Error( 'lets_cookies_invalid', 'Escolhas incoerentes.', array( 'status' => 400 ) );
		}

		// Só um caminho do próprio site: começa com "/" e usa caracteres de URL.
		// Bloqueia textos que virariam fórmula no Excel (CSV injection).
		$page = isset( $body['page'] ) ? (string) $body['page'] : '';
		$path = (string) wp_parse_url( $page, PHP_URL_PATH );
		$page = ( '' !== $path && '/' === $path[0] && preg_match( '#^/[A-Za-z0-9/_\-.~%]*$#', $path ) ) ? substr( $path, 0, 255 ) : '/';

		return array(
			'consent_id' => $consent_id,
			'action'     => $action,
			'version'    => $version,
			'choices'    => $clean,
			'page'       => $page,
		);
	}

	/**
	 * Trunca o IP (IPv4 /24, IPv6 /48) e aplica HMAC com o salt do site.
	 *
	 * @param string $ip IP do visitante.
	 * @return string
	 */
	public static function ip_hash( $ip ) {
		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			$parts    = explode( '.', $ip );
			$parts[3] = '0';
			$ip       = implode( '.', $parts );
		} elseif ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
			$packed = inet_pton( $ip );
			$ip     = inet_ntop( substr( $packed, 0, 6 ) . str_repeat( "\0", 10 ) );
		} else {
			$ip = '';
		}
		return hash_hmac( 'sha256', $ip, wp_salt( 'auth' ) );
	}

	/**
	 * Dois limites: um por visitante, para barrar repetição, e um mais alto
	 * por rede, para barrar quem gera IDs aleatórios. Assim muitos visitantes
	 * reais atrás do mesmo IP (empresa, proxy) não perdem o registro.
	 */
	public static function within_rate_limit( $ip_hash, $consent_id ) {
		$visitor_key = 'lets_ck_rv_' . substr( hash( 'sha256', $ip_hash . $consent_id ), 0, 32 );
		$network_key = 'lets_ck_rn_' . substr( $ip_hash, 0, 32 );
		$visitor     = (int) get_transient( $visitor_key );
		$network     = (int) get_transient( $network_key );
		if ( $visitor >= self::RATE_VISITOR || $network >= self::RATE_NETWORK ) {
			return false;
		}
		set_transient( $visitor_key, $visitor + 1, self::RATE_WINDOW );
		set_transient( $network_key, $network + 1, self::RATE_WINDOW );
		return true;
	}

	/**
	 * Grava o registro, encadeado ao anterior por hash (ajuda a mostrar que
	 * registros não foram alterados depois).
	 */
	private static function insert( $data, $ip_hash ) {
		global $wpdb;
		$table      = self::table();
		$created_at = gmdate( 'Y-m-d H:i:s' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$prev = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT row_hash FROM %i ORDER BY id DESC LIMIT 1', $table ) );

		$row = array(
			'consent_id'     => $data['consent_id'],
			'created_at'     => $created_at,
			'policy_version' => $data['version'],
			'action'         => $data['action'],
			'analytics'      => (int) $data['choices']['analytics'],
			'embedded'       => (int) $data['choices']['embedded'],
			'marketing'      => (int) $data['choices']['marketing'],
			'ip_hash'        => $ip_hash,
			'page'           => $data['page'],
		);

		$row['row_hash'] = hash( 'sha256', $prev . '|' . implode( '|', $row ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->insert( $table, $row, array( '%s', '%s', '%d', '%s', '%d', '%d', '%d', '%s', '%s', '%s' ) );
	}

	/** Apaga registros mais antigos que o prazo de guarda. */
	public static function cleanup() {
		global $wpdb;
		$settings = Lets_Cookies_Settings::get();
		$cutoff   = gmdate( 'Y-m-d H:i:s', time() - ( DAY_IN_SECONDS * (int) $settings['retention_days'] ) );
		$table    = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE created_at < %s', $table, $cutoff ) );
	}

	/**
	 * Registros mais recentes, para o painel.
	 *
	 * @param int $limit Quantidade.
	 * @return array
	 */
	public static function recent( $limit = 50 ) {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY id DESC LIMIT %d', $table, $limit ), ARRAY_A );
	}

	/** Total de registros. */
	public static function count() {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table ) );
	}

	/**
	 * Segunda barreira contra CSV injection: célula que começa com = + - @
	 * ou tabulação ganha um apóstrofo, para o Excel tratar como texto.
	 *
	 * @param mixed $value Valor da célula.
	 * @return string
	 */
	public static function csv_safe( $value ) {
		$value = (string) $value;
		return ( '' !== $value && false !== strpos( "=+-@\t\r", $value[0] ) ) ? "'" . $value : $value;
	}

	/** Exporta todos os registros em CSV (só administradores). */
	public static function export_csv() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Sem permissão.', 403 );
		}
		check_admin_referer( 'lets_cookies_export' );

		global $wpdb;
		$table = self::table();
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="registro-consentimento-' . gmdate( 'Y-m-d' ) . '.csv"' );

		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" ); // BOM para o Excel abrir acentos corretamente.
		fputcsv( $out, array( 'id', 'consent_id', 'data_utc', 'versao_politica', 'acao', 'analiticos', 'conteudo_incorporado', 'marketing', 'ip_hash', 'pagina', 'row_hash' ), ';' );

		$offset = 0;
		do {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY id ASC LIMIT 1000 OFFSET %d', $table, $offset ), ARRAY_A );
			foreach ( $rows as $row ) {
				fputcsv( $out, array_map( array( __CLASS__, 'csv_safe' ), array_values( $row ) ), ';' );
			}
			$offset += 1000;
		} while ( count( $rows ) === 1000 );

		fclose( $out );
		exit;
	}
}
