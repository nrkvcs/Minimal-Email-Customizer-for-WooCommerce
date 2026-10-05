<?php
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'ezmails_settings' );
delete_option( 'ezmails_version' );
