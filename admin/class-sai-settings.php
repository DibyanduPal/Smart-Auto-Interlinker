<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SAI_Settings {
	const OPTION_NAME = 'sai_settings';

	public function register() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	public static function defaults() {
		return array(
			'enabled'            => 1,
			'case_sensitive'     => 0,
			'max_links_per_post' => 3,
			'exclude_post_types' => array(),
			'link_target'        => '_self',
			'nofollow'           => 0,
		);
	}

	public static function get_settings() {
		$defaults = self::defaults();
		$options  = get_option( self::OPTION_NAME, array() );
		if ( ! is_array( $options ) ) {
			$options = array();
		}

		return wp_parse_args( $options, $defaults );
	}

	public function add_settings_page() {
		add_options_page(
			__( 'Smart Auto Interlinker', 'sai' ),
			__( 'Smart Auto Interlinker', 'sai' ),
			'manage_options',
			'smart-auto-interlinker',
			array( $this, 'render_page' )
		);
	}

	public function register_settings() {
		register_setting(
			'sai_settings_group',
			self::OPTION_NAME,
			array( $this, 'sanitize_settings' )
		);

		add_settings_section(
			'sai_settings_main',
			__( 'Global Options', 'sai' ),
			'__return_false',
			'smart-auto-interlinker'
		);

		add_settings_field(
			'sai_enabled',
			__( 'Enable auto interlinking', 'sai' ),
			array( $this, 'render_enabled_field' ),
			'smart-auto-interlinker',
			'sai_settings_main'
		);

		add_settings_field(
			'sai_case_sensitive',
			__( 'Case sensitive matching', 'sai' ),
			array( $this, 'render_case_sensitive_field' ),
			'smart-auto-interlinker',
			'sai_settings_main'
		);

		add_settings_field(
			'sai_max_links',
			__( 'Max links per post', 'sai' ),
			array( $this, 'render_max_links_field' ),
			'smart-auto-interlinker',
			'sai_settings_main'
		);

		add_settings_field(
			'sai_exclude_post_types',
			__( 'Exclude post types', 'sai' ),
			array( $this, 'render_exclude_post_types_field' ),
			'smart-auto-interlinker',
			'sai_settings_main'
		);

		add_settings_field(
			'sai_link_target',
			__( 'Link target', 'sai' ),
			array( $this, 'render_link_target_field' ),
			'smart-auto-interlinker',
			'sai_settings_main'
		);

		add_settings_field(
			'sai_nofollow',
			__( 'Add rel="nofollow"', 'sai' ),
			array( $this, 'render_nofollow_field' ),
			'smart-auto-interlinker',
			'sai_settings_main'
		);
	}

	public function sanitize_settings( $input ) {
		$defaults = self::defaults();
		$input    = is_array( $input ) ? $input : array();

		$sanitized = array();
		$sanitized['enabled']        = empty( $input['enabled'] ) ? 0 : 1;
		$sanitized['case_sensitive'] = empty( $input['case_sensitive'] ) ? 0 : 1;
		$sanitized['nofollow']       = empty( $input['nofollow'] ) ? 0 : 1;

		$max_links = isset( $input['max_links_per_post'] ) ? absint( $input['max_links_per_post'] ) : $defaults['max_links_per_post'];
		$sanitized['max_links_per_post'] = max( 0, $max_links );

		$valid_targets = array( '_self', '_blank' );
		$link_target   = isset( $input['link_target'] ) ? sanitize_text_field( $input['link_target'] ) : $defaults['link_target'];
		$sanitized['link_target'] = in_array( $link_target, $valid_targets, true ) ? $link_target : $defaults['link_target'];

		$post_types = get_post_types( array( 'public' => true ), 'names' );
		$exclude    = array();
		if ( ! empty( $input['exclude_post_types'] ) && is_array( $input['exclude_post_types'] ) ) {
			foreach ( $input['exclude_post_types'] as $post_type ) {
				$post_type = sanitize_text_field( $post_type );
				if ( in_array( $post_type, $post_types, true ) ) {
					$exclude[] = $post_type;
				}
			}
		}
		$sanitized['exclude_post_types'] = $exclude;

		return wp_parse_args( $sanitized, $defaults );
	}

	public function render_page() {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Smart Auto Interlinker Settings', 'sai' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'sai_settings_group' );
				do_settings_sections( 'smart-auto-interlinker' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	public function render_enabled_field() {
		$settings = self::get_settings();
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[enabled]" value="1" <?php checked( 1, $settings['enabled'] ); ?> />
			<?php esc_html_e( 'Turn on automatic interlinking', 'sai' ); ?>
		</label>
		<?php
	}

	public function render_case_sensitive_field() {
		$settings = self::get_settings();
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[case_sensitive]" value="1" <?php checked( 1, $settings['case_sensitive'] ); ?> />
			<?php esc_html_e( 'Match keywords with case sensitivity', 'sai' ); ?>
		</label>
		<?php
	}

	public function render_max_links_field() {
		$settings = self::get_settings();
		?>
		<input type="number" min="0" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[max_links_per_post]" value="<?php echo esc_attr( $settings['max_links_per_post'] ); ?>" class="small-text" />
		<p class="description"><?php esc_html_e( 'Limit the number of auto links added to each post.', 'sai' ); ?></p>
		<?php
	}

	public function render_exclude_post_types_field() {
		$settings   = self::get_settings();
		$post_types = get_post_types( array( 'public' => true ), 'objects' );
		?>
		<fieldset>
			<?php foreach ( $post_types as $post_type ) : ?>
				<label style="display:block; margin-bottom:4px;">
					<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[exclude_post_types][]" value="<?php echo esc_attr( $post_type->name ); ?>" <?php checked( in_array( $post_type->name, $settings['exclude_post_types'], true ) ); ?> />
					<?php echo esc_html( $post_type->labels->singular_name ); ?>
				</label>
			<?php endforeach; ?>
		</fieldset>
		<?php
	}

	public function render_link_target_field() {
		$settings = self::get_settings();
		?>
		<select name="<?php echo esc_attr( self::OPTION_NAME ); ?>[link_target]">
			<option value="_self" <?php selected( '_self', $settings['link_target'] ); ?>><?php esc_html_e( 'Same tab', 'sai' ); ?></option>
			<option value="_blank" <?php selected( '_blank', $settings['link_target'] ); ?>><?php esc_html_e( 'New tab', 'sai' ); ?></option>
		</select>
		<?php
	}

	public function render_nofollow_field() {
		$settings = self::get_settings();
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[nofollow]" value="1" <?php checked( 1, $settings['nofollow'] ); ?> />
			<?php esc_html_e( 'Add rel="nofollow" to generated links', 'sai' ); ?>
		</label>
		<?php
	}
}
