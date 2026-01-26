<?php
/**
 * Partner Portal Configuration
 *
 * Manages Partner Portal settings including assigned LOs and Realtor preferences.
 * Each Partner Portal (subsite) has LOs assigned by region/area.
 * Realtors can set a preferred LO that's auto-selected in wizards.
 *
 * @package FRSLeadPages
 */

namespace FRSLeadPages\Core;

class PartnerPortal {

    /**
     * Option keys
     */
    const OPTION_ASSIGNED_LOS = 'frs_assigned_loan_officers';
    const OPTION_PORTAL_TYPE = 'frs_portal_type'; // 'lender' or 'partner'
    const OPTION_LENDER_SITE_ID = 'frs_lender_site_id'; // Which site is the lender portal

    /**
     * User meta keys
     */
    const META_PREFERRED_LO = 'frs_preferred_lo_id';

    /**
     * Initialize hooks
     */
    public static function init(): void {
        // Save preferred LO when Realtor selects one in wizard
        add_action( 'wp_ajax_frs_set_preferred_lo', [ __CLASS__, 'ajax_set_preferred_lo' ] );
    }

    /**
     * Check if current site is a Partner Portal
     *
     * @return bool
     */
    public static function is_partner_portal(): bool {
        if ( ! is_multisite() ) {
            return false;
        }

        $portal_type = get_option( self::OPTION_PORTAL_TYPE, '' );

        if ( $portal_type === 'partner' ) {
            return true;
        }

        if ( $portal_type === 'lender' ) {
            return false;
        }

        // Default: Main site (1) is lender, others are partner portals
        return get_current_blog_id() !== 1;
    }

    /**
     * Check if current site is the Lender Portal
     *
     * @return bool
     */
    public static function is_lender_portal(): bool {
        if ( ! is_multisite() ) {
            return true; // Single site acts as lender
        }

        $portal_type = get_option( self::OPTION_PORTAL_TYPE, '' );

        if ( $portal_type === 'lender' ) {
            return true;
        }

        if ( $portal_type === 'partner' ) {
            return false;
        }

        // Default: Main site (1) is lender
        return get_current_blog_id() === 1;
    }

    /**
     * Get the Lender Portal site ID
     *
     * @return int
     */
    public static function get_lender_site_id(): int {
        if ( ! is_multisite() ) {
            return get_current_blog_id();
        }

        // Check network option first
        $lender_id = get_site_option( self::OPTION_LENDER_SITE_ID, 0 );
        if ( $lender_id ) {
            return (int) $lender_id;
        }

        // Default to site 1
        return 1;
    }

    /**
     * Get assigned Loan Officers for current Partner Portal
     *
     * @return array Array of LO user IDs
     */
    public static function get_assigned_loan_officers(): array {
        $assigned = get_option( self::OPTION_ASSIGNED_LOS, [] );

        if ( ! is_array( $assigned ) ) {
            $assigned = [];
        }

        return array_map( 'absint', $assigned );
    }

    /**
     * Set assigned Loan Officers for current Partner Portal
     *
     * @param array $lo_ids Array of LO user IDs
     * @return bool
     */
    public static function set_assigned_loan_officers( array $lo_ids ): bool {
        $lo_ids = array_map( 'absint', $lo_ids );
        $lo_ids = array_filter( $lo_ids ); // Remove zeros
        $lo_ids = array_unique( $lo_ids );

        return update_option( self::OPTION_ASSIGNED_LOS, $lo_ids );
    }

    /**
     * Add a Loan Officer to the assigned list
     *
     * @param int $lo_id LO user ID
     * @return bool
     */
    public static function add_assigned_loan_officer( int $lo_id ): bool {
        $assigned = self::get_assigned_loan_officers();

        if ( ! in_array( $lo_id, $assigned, true ) ) {
            $assigned[] = $lo_id;
            return self::set_assigned_loan_officers( $assigned );
        }

        return true;
    }

    /**
     * Remove a Loan Officer from the assigned list
     *
     * @param int $lo_id LO user ID
     * @return bool
     */
    public static function remove_assigned_loan_officer( int $lo_id ): bool {
        $assigned = self::get_assigned_loan_officers();
        $assigned = array_diff( $assigned, [ $lo_id ] );

        return self::set_assigned_loan_officers( $assigned );
    }

    /**
     * Get available Loan Officers for selection
     *
     * On Partner Portal: Returns only assigned LOs
     * On Lender Portal: Returns all LOs
     *
     * @return array Array of LO data
     */
    public static function get_available_loan_officers(): array {
        // On lender portal or single site, return all LOs
        if ( self::is_lender_portal() || ! is_multisite() ) {
            return LoanOfficers::get_loan_officers();
        }

        // On partner portal, filter to assigned LOs only
        $assigned_ids = self::get_assigned_loan_officers();

        if ( empty( $assigned_ids ) ) {
            // No LOs assigned yet - return all (fallback for unconfigured portals)
            return LoanOfficers::get_loan_officers();
        }

        $all_los = LoanOfficers::get_loan_officers();

        return array_filter( $all_los, function( $lo ) use ( $assigned_ids ) {
            return in_array( (int) $lo['id'], $assigned_ids, true );
        });
    }

    /**
     * Get Realtor's preferred LO ID
     *
     * @param int|null $user_id User ID (defaults to current user)
     * @return int LO user ID or 0 if not set
     */
    public static function get_preferred_loan_officer( ?int $user_id = null ): int {
        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }

        if ( ! $user_id ) {
            return 0;
        }

        $preferred = get_user_meta( $user_id, self::META_PREFERRED_LO, true );

        if ( ! $preferred ) {
            return 0;
        }

        $preferred = absint( $preferred );

        // Validate that preferred LO is still available/assigned
        if ( self::is_partner_portal() ) {
            $assigned = self::get_assigned_loan_officers();
            if ( ! empty( $assigned ) && ! in_array( $preferred, $assigned, true ) ) {
                // Preferred LO no longer assigned to this portal
                return 0;
            }
        }

        return $preferred;
    }

    /**
     * Set Realtor's preferred LO
     *
     * @param int      $lo_id   LO user ID
     * @param int|null $user_id User ID (defaults to current user)
     * @return bool
     */
    public static function set_preferred_loan_officer( int $lo_id, ?int $user_id = null ): bool {
        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }

        if ( ! $user_id ) {
            return false;
        }

        // Validate LO exists and is available
        if ( $lo_id > 0 ) {
            $available = self::get_available_loan_officers();
            $available_ids = array_column( $available, 'id' );

            if ( ! in_array( $lo_id, $available_ids, true ) ) {
                return false;
            }
        }

        return (bool) update_user_meta( $user_id, self::META_PREFERRED_LO, $lo_id );
    }

    /**
     * Clear Realtor's preferred LO
     *
     * @param int|null $user_id User ID (defaults to current user)
     * @return bool
     */
    public static function clear_preferred_loan_officer( ?int $user_id = null ): bool {
        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }

        if ( ! $user_id ) {
            return false;
        }

        return delete_user_meta( $user_id, self::META_PREFERRED_LO );
    }

    /**
     * AJAX handler to set preferred LO
     */
    public static function ajax_set_preferred_lo(): void {
        check_ajax_referer( 'frs_lead_pages', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( 'Not logged in' );
        }

        $lo_id = isset( $_POST['lo_id'] ) ? absint( $_POST['lo_id'] ) : 0;
        $remember = isset( $_POST['remember'] ) && $_POST['remember'] === 'true';

        if ( $remember && $lo_id > 0 ) {
            $result = self::set_preferred_loan_officer( $lo_id );
            wp_send_json_success( [ 'saved' => $result ] );
        } else {
            wp_send_json_success( [ 'saved' => false ] );
        }
    }

    /**
     * Get partner step config with preferred LO pre-selected
     *
     * @return array Configuration for wizard partner selection
     */
    public static function get_partner_step_config(): array {
        $config = UserMode::get_partner_step_config();

        // If in Partner mode, update with available LOs and preferred selection
        if ( UserMode::is_partner() ) {
            $available_los = self::get_available_loan_officers();
            $preferred_lo = self::get_preferred_loan_officer();

            $config['partners'] = $available_los;
            $config['preferred_id'] = $preferred_lo;
            $config['show_remember'] = true; // Show "Remember my choice" checkbox

            // If only one LO available, auto-select and potentially skip
            if ( count( $available_los ) === 1 ) {
                $config['preferred_id'] = $available_los[0]['id'];
                $config['auto_selected'] = true;
            }
        }

        return $config;
    }

    /**
     * Get data for wizard JavaScript
     *
     * @return array
     */
    public static function get_wizard_data(): array {
        return [
            'isPartnerPortal'   => self::is_partner_portal(),
            'isLenderPortal'    => self::is_lender_portal(),
            'availableLOs'      => self::get_available_loan_officers(),
            'preferredLOId'     => self::get_preferred_loan_officer(),
            'assignedLOCount'   => count( self::get_assigned_loan_officers() ),
        ];
    }
}
