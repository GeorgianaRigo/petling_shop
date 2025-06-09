<?php

defined( 'ABSPATH' ) or exit;

if ( get_option( 'pwgc_database_version' ) != PWGC_VERSION ) {
    global $wpdb;

    // Switch to the local database in case we're multisite and have switched sites.
    $wpdb->pimwick_gift_card = $wpdb->prefix . 'pimwick_gift_card';
    $wpdb->pimwick_gift_card_activity = $wpdb->prefix . 'pimwick_gift_card_activity';

    $wpdb->query( "
        CREATE TABLE IF NOT EXISTS `{$wpdb->pimwick_gift_card}` (
            `pimwick_gift_card_id` INT NOT NULL AUTO_INCREMENT,
            `number` TEXT NOT NULL,
            `active` TINYINT(1) NOT NULL DEFAULT 1,
            `create_date` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `expiration_date` DATE NULL,
            PRIMARY KEY (`pimwick_gift_card_id`),
            UNIQUE `{$wpdb->prefix}ix_pimwick_gift_card_number` ( `number` (128) )
        );
    " );

    if ( $wpdb->last_error != '' ) {
        wp_die( $wpdb->last_error );
    }

    $wpdb->query( "
        CREATE TABLE IF NOT EXISTS `{$wpdb->pimwick_gift_card_activity}` (
            `pimwick_gift_card_activity_id` INT NOT NULL AUTO_INCREMENT,
            `pimwick_gift_card_id` INT NOT NULL,
            `user_id` BIGINT(20) UNSIGNED NULL DEFAULT NULL,
            `activity_date` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `action` VARCHAR(60) NOT NULL,
            `amount` DECIMAL(15,6) NULL DEFAULT NULL,
            `note` TEXT NULL DEFAULT NULL,
            `reference_activity_id` INT NULL DEFAULT NULL,
            PRIMARY KEY (`pimwick_gift_card_activity_id`),
            INDEX `{$wpdb->prefix}ix_pimwick_gift_card_id` (`pimwick_gift_card_id`)
        );
    " );
    if ( $wpdb->last_error != '' ) {
        wp_die( $wpdb->last_error );
    }

    update_option( 'pwgc_database_version', PWGC_VERSION );

    pwgc_set_table_names();
}
