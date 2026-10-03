<?php
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'wec_settings' );
delete_option( 'mecw_version' );
delete_transient( 'mecw_legacy_notice' );
