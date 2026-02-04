<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$settings = get_option( 'sai_settings', array() );
$delete   = ! empty( $settings['delete_data_on_uninstall'] );

if ( ! $delete ) {
	return;
}

delete_option( 'sai_settings' );
delete_option( 'sai_index' );

delete_metadata( 'post', 0, 'sai_mappings', '', true );
