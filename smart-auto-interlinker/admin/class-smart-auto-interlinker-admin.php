<?php
/**
 * Admin functionality.
 *
 * @package SmartAutoInterlinker
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Smart_Auto_Interlinker_Admin {
	/**
	 * Initialize admin hooks.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_post_sai_save_settings', array( $this, 'handle_form_submission' ) );
	}

	/**
	 * Register plugin menu.
	 *
	 * @return void
	 */
	public function register_menu() {
		add_options_page(
			esc_html__( 'Smart Auto Interlinker', 'smart-auto-interlinker' ),
			esc_html__( 'Smart Auto Interlinker', 'smart-auto-interlinker' ),
			'manage_options',
			'smart-auto-interlinker',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Handle settings form submission.
	 *
	 * @return void
	 */
	public function handle_form_submission() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'smart-auto-interlinker' ) );
		}

		check_admin_referer( 'sai_save_settings', 'sai_nonce' );

		$keyword = '';
		if ( isset( $_POST['sai_keyword'] ) ) {
			$keyword = sanitize_text_field( wp_unslash( $_POST['sai_keyword'] ) );
		}

		update_option( 'sai_keyword', $keyword );

		$redirect_url = add_query_arg( 'sai-updated', '1', wp_get_referer() );
		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Render settings page.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		$keyword = sanitize_text_field( get_option( 'sai_keyword', '' ) );
		$updated = isset( $_GET['sai-updated'] ) && '1' === $_GET['sai-updated'];
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Smart Auto Interlinker', 'smart-auto-interlinker' ); ?></h1>

			<?php if ( $updated ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php echo esc_html__( 'Settings saved.', 'smart-auto-interlinker' ); ?></p>
				</div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'sai_save_settings', 'sai_nonce' ); ?>
				<input type="hidden" name="action" value="sai_save_settings" />

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="sai_keyword"><?php echo esc_html__( 'Keyword to link', 'smart-auto-interlinker' ); ?></label>
						</th>
						<td>
							<input
								type="text"
								id="sai_keyword"
								name="sai_keyword"
								class="regular-text"
								value="<?php echo esc_attr( $keyword ); ?>"
							/>
							<p class="description">
								<?php echo esc_html__( 'First occurrence of this keyword will be linked to your homepage.', 'smart-auto-interlinker' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<?php submit_button( esc_html__( 'Save Changes', 'smart-auto-interlinker' ) ); ?>
			</form>
		</div>
		<?php
	}
}
