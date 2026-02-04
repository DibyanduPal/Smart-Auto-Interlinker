<?php
/**
 * Plugin Name: Smart Auto Interlinker
 * Description: Create per-post keyword → URL mappings and automatically interlink occurrences site-wide.
 * Version: 1.0.0
 * Author: Smart Auto Interlinker Contributors
 * Text Domain: smart-auto-interlinker
 * Domain Path: /languages
 * License: GPLv2 or later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Smart_Auto_Interlinker {
	const OPTION_SETTINGS = 'sai_settings';
	const OPTION_INDEX    = 'sai_index';
	const META_MAPPINGS   = 'sai_mappings';
	const NONCE_ACTION    = 'sai_save_mappings';
	const NONCE_FIELD     = 'sai_mappings_nonce';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'load_textdomain' ) );
		add_action( 'admin_menu', array( __CLASS__, 'register_settings_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'register_meta_box' ) );
		add_action( 'save_post', array( __CLASS__, 'save_post_mappings' ) );
		add_filter( 'the_content', array( __CLASS__, 'interlink_content' ), 9 );
		register_activation_hook( __FILE__, array( __CLASS__, 'build_index' ) );
	}

	public static function load_textdomain() {
		load_plugin_textdomain( 'smart-auto-interlinker', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}

	public static function register_settings_page() {
		add_options_page(
			__( 'Smart Auto Interlinker', 'smart-auto-interlinker' ),
			__( 'Smart Auto Interlinker', 'smart-auto-interlinker' ),
			'manage_options',
			'smart-auto-interlinker',
			array( __CLASS__, 'render_settings_page' )
		);
	}

	public static function register_settings() {
		register_setting(
			'sai_settings',
			self::OPTION_SETTINGS,
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
				'show_in_rest'      => false,
			)
		);

		add_settings_section(
			'sai_general',
			__( 'General Settings', 'smart-auto-interlinker' ),
			'__return_false',
			'sai_settings'
		);

		add_settings_field(
			'delete_data_on_uninstall',
			__( 'Delete data on uninstall', 'smart-auto-interlinker' ),
			array( __CLASS__, 'render_delete_setting' ),
			'sai_settings',
			'sai_general'
		);
	}

	public static function sanitize_settings( $input ) {
		$output = array();
		$output['delete_data_on_uninstall'] = empty( $input['delete_data_on_uninstall'] ) ? 0 : 1;

		return $output;
	}

	public static function render_delete_setting() {
		$options = self::get_settings();
		$value   = ! empty( $options['delete_data_on_uninstall'] );
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( self::OPTION_SETTINGS ); ?>[delete_data_on_uninstall]" value="1" <?php checked( $value ); ?> />
			<?php esc_html_e( 'Remove all plugin data when uninstalling the plugin.', 'smart-auto-interlinker' ); ?>
		</label>
		<?php
	}

	public static function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( __( 'Smart Auto Interlinker Settings', 'smart-auto-interlinker' ) ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'sai_settings' );
				do_settings_sections( 'sai_settings' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	public static function register_meta_box() {
		$post_types = array( 'post', 'page' );
		foreach ( $post_types as $post_type ) {
			add_meta_box(
				'sai_mappings',
				__( 'Smart Auto Interlinker Mappings', 'smart-auto-interlinker' ),
				array( __CLASS__, 'render_meta_box' ),
				$post_type,
				'normal',
				'default'
			);
		}
	}

	public static function render_meta_box( $post ) {
		$stored = get_post_meta( $post->ID, self::META_MAPPINGS, true );
		$lines  = array();

		if ( is_array( $stored ) ) {
			foreach ( $stored as $mapping ) {
				$keyword = isset( $mapping['keyword'] ) ? $mapping['keyword'] : '';
				$url     = isset( $mapping['url'] ) ? $mapping['url'] : '';
				if ( '' !== $keyword && '' !== $url ) {
					$lines[] = $keyword . ' | ' . $url;
				}
			}
		}
		?>
		<p><?php esc_html_e( 'Enter one mapping per line in the format “keyword | URL”.', 'smart-auto-interlinker' ); ?></p>
		<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD ); ?>
		<textarea class="widefat" rows="8" name="sai_mappings_text" id="sai_mappings_text"><?php echo esc_textarea( implode( "\n", $lines ) ); ?></textarea>
		<?php
	}

	public static function save_post_mappings( $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ), self::NONCE_ACTION ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( ! isset( $_POST['sai_mappings_text'] ) ) {
			return;
		}

		$raw_text = wp_unslash( $_POST['sai_mappings_text'] );
		$parsed   = self::parse_mappings_text( $raw_text );

		if ( empty( $parsed ) ) {
			delete_post_meta( $post_id, self::META_MAPPINGS );
		} else {
			update_post_meta( $post_id, self::META_MAPPINGS, $parsed );
		}

		self::build_index();
	}

	private static function parse_mappings_text( $raw_text ) {
		$lines    = preg_split( '/\r\n|\r|\n/', $raw_text );
		$mappings = array();

		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}
			$parts = array_map( 'trim', explode( '|', $line, 2 ) );
			if ( 2 !== count( $parts ) ) {
				continue;
			}
			$keyword = sanitize_text_field( $parts[0] );
			$url     = esc_url_raw( $parts[1] );
			if ( '' === $keyword || '' === $url ) {
				continue;
			}
			$mappings[] = array(
				'keyword' => $keyword,
				'url'     => $url,
			);
		}

		return $mappings;
	}

	public static function build_index() {
		$posts = get_posts(
			array(
				'post_type'      => array( 'post', 'page' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_key'       => self::META_MAPPINGS,
				'no_found_rows'  => true,
			)
		);

		$index = array();
		foreach ( $posts as $post_id ) {
			$mappings = get_post_meta( $post_id, self::META_MAPPINGS, true );
			if ( ! is_array( $mappings ) ) {
				continue;
			}
			foreach ( $mappings as $mapping ) {
				$keyword = isset( $mapping['keyword'] ) ? sanitize_text_field( $mapping['keyword'] ) : '';
				$url     = isset( $mapping['url'] ) ? esc_url_raw( $mapping['url'] ) : '';
				if ( '' === $keyword || '' === $url ) {
					continue;
				}
				$index[ $keyword ] = $url;
			}
		}

		update_option( self::OPTION_INDEX, $index, false );
	}

	public static function interlink_content( $content ) {
		if ( is_admin() ) {
			return $content;
		}

		$index = get_option( self::OPTION_INDEX, array() );
		if ( empty( $index ) || ! is_array( $index ) ) {
			return $content;
		}

		return self::replace_keywords( $content, $index );
	}

	private static function replace_keywords( $content, $index ) {
		$tokens     = wp_html_split( $content );
		$in_anchor  = false;
		$processed  = array();
		$patterns   = array();
		$replacers  = array();

		foreach ( $index as $keyword => $url ) {
			$keyword = sanitize_text_field( $keyword );
			$url     = esc_url_raw( $url );
			if ( '' === $keyword || '' === $url ) {
				continue;
			}
			$patterns[]  = '/\b' . preg_quote( $keyword, '/' ) . '\b/i';
			$replacers[] = function( $matches ) use ( $url ) {
				return sprintf(
					'<a href="%1$s">%2$s</a>',
					esc_url( $url ),
					esc_html( $matches[0] )
				);
			};
		}

		if ( empty( $patterns ) ) {
			return $content;
		}

		foreach ( $tokens as $token ) {
			if ( '<' === $token[0] ) {
				if ( preg_match( '/^<\\s*a\\b/i', $token ) ) {
					$in_anchor = true;
				} elseif ( preg_match( '/^<\\s*\\/\\s*a\\b/i', $token ) ) {
					$in_anchor = false;
				}
				$processed[] = $token;
				continue;
			}

			if ( $in_anchor ) {
				$processed[] = $token;
				continue;
			}

			$updated = $token;
			foreach ( $patterns as $index_key => $pattern ) {
				$updated = preg_replace_callback( $pattern, $replacers[ $index_key ], $updated );
			}
			$processed[] = $updated;
		}

		return implode( '', $processed );
	}

	private static function get_settings() {
		$options = get_option( self::OPTION_SETTINGS, array() );
		if ( ! is_array( $options ) ) {
			return array();
		}
		return $options;
	}
}

Smart_Auto_Interlinker::init();
