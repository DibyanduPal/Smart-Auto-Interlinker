<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SAI_Metabox {
	const META_KEY = 'sai_mappings';

	public function register() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'save_post', array( $this, 'save_mappings' ) );
	}

	public function add_meta_box() {
		$post_types = array( 'post', 'page' );
		foreach ( $post_types as $post_type ) {
			add_meta_box(
				'sai_mappings',
				__( 'Smart Auto Interlinker', 'sai' ),
				array( $this, 'render_meta_box' ),
				$post_type,
				'normal',
				'default'
			);
		}
	}

	public function render_meta_box( $post ) {
		$stored   = get_post_meta( $post->ID, self::META_KEY, true );
		$mappings = is_array( $stored ) ? $stored : array();

		wp_nonce_field( 'sai_save_mappings', 'sai_mappings_nonce' );
		?>
		<p><?php esc_html_e( 'Add keyword or phrase mappings to target URLs. Separate multiple keywords with commas.', 'sai' ); ?></p>
		<table class="widefat" id="sai-mappings-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Keywords / Phrases', 'sai' ); ?></th>
					<th><?php esc_html_e( 'Target URL', 'sai' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'sai' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $mappings ) ) : ?>
					<tr class="sai-mapping-row">
						<td>
							<input type="text" name="sai_mapping_keywords[]" class="widefat" placeholder="keyword one, keyword two" />
						</td>
						<td>
							<input type="url" name="sai_mapping_url[]" class="widefat" placeholder="https://example.com" />
						</td>
						<td>
							<button type="button" class="button sai-remove-row"><?php esc_html_e( 'Remove', 'sai' ); ?></button>
						</td>
					</tr>
				<?php else : ?>
					<?php foreach ( $mappings as $mapping ) : ?>
						<?php
							$keywords = '';
							if ( ! empty( $mapping['keywords'] ) && is_array( $mapping['keywords'] ) ) {
								$keywords = implode( ', ', array_map( 'sanitize_text_field', $mapping['keywords'] ) );
							}
							$url = isset( $mapping['url'] ) ? esc_url( $mapping['url'] ) : '';
						?>
						<tr class="sai-mapping-row">
							<td>
								<input type="text" name="sai_mapping_keywords[]" class="widefat" value="<?php echo esc_attr( $keywords ); ?>" />
							</td>
							<td>
								<input type="url" name="sai_mapping_url[]" class="widefat" value="<?php echo esc_url( $url ); ?>" />
							</td>
							<td>
								<button type="button" class="button sai-remove-row"><?php esc_html_e( 'Remove', 'sai' ); ?></button>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
		<p>
			<button type="button" class="button" id="sai-add-row"><?php esc_html_e( 'Add Mapping', 'sai' ); ?></button>
		</p>
		<script>
			(function() {
				var tableBody = document.querySelector('#sai-mappings-table tbody');
				var addRowButton = document.getElementById('sai-add-row');
				if (!tableBody || !addRowButton) {
					return;
				}

				function addRow() {
					var row = document.createElement('tr');
					row.className = 'sai-mapping-row';
					row.innerHTML = '<td><input type="text" name="sai_mapping_keywords[]" class="widefat" placeholder="keyword one, keyword two" /></td>' +
						'<td><input type="url" name="sai_mapping_url[]" class="widefat" placeholder="https://example.com" /></td>' +
						'<td><button type="button" class="button sai-remove-row">' + <?php echo wp_json_encode( __( 'Remove', 'sai' ) ); ?> + '</button></td>';
					tableBody.appendChild(row);
				}

				function removeRow(event) {
					if (!event.target.classList.contains('sai-remove-row')) {
						return;
					}
					var rows = tableBody.querySelectorAll('.sai-mapping-row');
					if (rows.length <= 1) {
						rows[0].querySelectorAll('input').forEach(function(input) {
							input.value = '';
						});
						return;
					}
					event.target.closest('tr').remove();
				}

				addRowButton.addEventListener('click', addRow);
				tableBody.addEventListener('click', removeRow);
			})();
		</script>
		<?php
	}

	public function save_mappings( $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! isset( $_POST['sai_mappings_nonce'] ) || ! wp_verify_nonce( $_POST['sai_mappings_nonce'], 'sai_save_mappings' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$keywords_list = isset( $_POST['sai_mapping_keywords'] ) ? (array) $_POST['sai_mapping_keywords'] : array();
		$urls          = isset( $_POST['sai_mapping_url'] ) ? (array) $_POST['sai_mapping_url'] : array();

		$mappings = array();
		$max      = max( count( $keywords_list ), count( $urls ) );

		for ( $i = 0; $i < $max; $i++ ) {
			$raw_keywords = isset( $keywords_list[ $i ] ) ? wp_unslash( $keywords_list[ $i ] ) : '';
			$raw_url      = isset( $urls[ $i ] ) ? wp_unslash( $urls[ $i ] ) : '';

			$url = esc_url_raw( trim( $raw_url ) );
			if ( empty( $url ) ) {
				continue;
			}

			$keywords = array();
			foreach ( explode( ',', $raw_keywords ) as $keyword ) {
				$keyword = sanitize_text_field( $keyword );
				$keyword = trim( $keyword );
				if ( '' !== $keyword ) {
					$keywords[] = $keyword;
				}
			}

			$keywords = array_values( array_unique( $keywords ) );
			if ( empty( $keywords ) ) {
				continue;
			}

			$mappings[] = array(
				'keywords' => $keywords,
				'url'      => $url,
			);
		}

		if ( empty( $mappings ) ) {
			delete_post_meta( $post_id, self::META_KEY );
			return;
		}

		update_post_meta( $post_id, self::META_KEY, $mappings );
	}
}
