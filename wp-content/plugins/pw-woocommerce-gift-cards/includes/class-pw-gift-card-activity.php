<?php

/*
Copyright (C) Pimwick, LLC

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

defined( 'ABSPATH' ) or exit;

if ( ! class_exists( 'PW_Gift_Card_Activity' ) ) :

class PW_Gift_Card_Activity {

    /*
     *
     * Properties
     *
     */
    public function get_id() { return $this->pimwick_gift_card_activity_id; }
    protected function set_id( $id ) { $this->pimwick_gift_card_activity_id = $id; }
    private $pimwick_gift_card_activity_id;

    public function get_gift_card_id() { return $this->pimwick_gift_card_id; }
    protected function set_gift_card_id( $gift_card_id ) { $this->pimwick_gift_card_id = $gift_card_id; }
    private $pimwick_gift_card_id;

    public function get_activity_date() { return $this->activity_date; }
    protected function set_activity_date( $activity_date ) { $this->activity_date = $activity_date; }
    private $activity_date;

    public function get_action() { return $this->action; }
    protected function set_action( $action ) { $this->action = $action; }
    private $action;

    public function get_amount() { return $this->amount; }
    protected function set_amount( $amount ) { $this->amount = $amount; }
    private $amount;

    public function get_note() { return $this->note; }
    protected function set_note( $note ) { $this->note = $note; }
    private $note;

    public function get_reference_activity_id() { return $this->reference_activity_id; }
    protected function set_reference_activity_id( $reference_activity_id ) { $this->reference_activity_id = $reference_activity_id; }
    private $reference_activity_id;



    /*
     *
     * Static Methods
     *
     */
    public static function get_card_activity( $gift_card, $limit = 0 ) {
        global $wpdb;

        $activity_records = array();

        $results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$wpdb->pimwick_gift_card_activity}` WHERE pimwick_gift_card_id = %d ORDER BY `activity_date` LIMIT %d", $gift_card->get_id(), absint( $limit ) ) );
        if ( null !== $results ) {
            foreach ( $results as $row ) {

                $activity = new PW_Gift_Card_Activity();

                $activity->set_id( $result->pimwick_gift_card_activity_id );
                $activity->set_gift_card_id( $result->pimwick_gift_card_id );
                $activity->set_activity_date( $result->activity_date );
                $activity->set_action( $result->action );
                $activity->set_amount( $result->amount );
                $activity->set_note( $result->note );
                $activity->set_reference_activity_id( $result->reference_activity_id );

                $activity_records[] = $activity;
            }

        } else {
            wp_die( __( 'Could not find activity record', 'pw-woocommerce-gift-cards' ) . ': ' . $id );
        }

        $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$wpdb->pimwick_gift_card_activity}` WHERE pimwick_gift_card_activity_id = %d", $id ) );

        return $activity_records;
    }

    public static function record( $gift_card_id, $action, $amount = null, $note = null, $reference_activity_id = null ) {
        global $wpdb;

        if ( !in_array( $action, array( 'create', 'transaction', 'deactivate', 'reactivate', 'note' ) ) ) {
            wp_die( __( 'Invalid action value: ', 'pw-woocommerce-gift-cards' ) . $action );
        }

        $result = $wpdb->insert( $wpdb->pimwick_gift_card_activity, array(
            'pimwick_gift_card_id'  => $gift_card_id,
            'action'                => $action,
            'amount'                => $amount,
            'note'                  => wc_clean( $note ),
            'user_id'               => get_current_user_id(),
            'reference_activity_id' => $reference_activity_id,
        ) );

        if ( $result ) {
            return $result;

        } else {
            wp_die( $wpdb->last_error );
        }
    }
}

endif;

?>