<?php if (!defined("WP_UNINSTALL_PLUGIN")) { exit; } delete_option("hmsc_options"); global $wpdb; $wpdb->query("DROP TABLE IF EXISTS " . $wpdb->prefix . "hmsc_chat_logs");
