<?php
/**
 * User Mode Detection
 *
 * Determines whether the current user is a Loan Officer or Realtor
 * and provides the appropriate partner selection options.
 *
 * @package FRSLeadPages
 */

namespace FRSLeadPages\Core;

class UserMode {

    /**
     * User mode constants
     */
    const MODE_LOAN_OFFICER = 'loan_officer';
    const MODE_REALTOR = 'realtor';
    const MODE_ADMIN = 'admin';

    /**
     * Get the current user's mode
     *
     * @return string One of the MODE_* constants
     */
    public static function get_mode(): string {
        if ( ! is_user_logged_in() ) {
            return '';
        }

        $user = wp_get_current_user();

        // Check for admin first (they can act as either)
        if ( in_array( 'administrator', $user->roles, true ) ) {
            // Check URL parameter to determine which mode admin wants
            $mode = sanitize_text_field( $_GET['mode'] ?? '' );
            if ( $mode === 'lo' || $mode === 'loan_officer' ) {
                return self::MODE_LOAN_OFFICER;
            } elseif ( $mode === 'partner' ) {
                return self::MODE_REALTOR;
            }
            // Default admin to LO mode
            return self::MODE_LOAN_OFFICER;
        }

        // Check for partner/agent roles
        if ( array_intersect( [ 'realtor_partner', 'agent' ], $user->roles ) ) {
            return self::MODE_REALTOR;
        }

        // Everyone else is loan officer mode (including loan_officer, editor, author, contributor)
        return self::MODE_LOAN_OFFICER;
    }

    /**
     * Check if current user is a loan officer
     *
     * @return bool
     */
    public static function is_loan_officer(): bool {
        return self::get_mode() === self::MODE_LOAN_OFFICER;
    }

    /**
     * Check if current user is a realtor
     *
     * @return bool
     */
    public static function is_realtor(): bool {
        return self::get_mode() === self::MODE_REALTOR;
    }

    /**
     * Get the current user's profile data
     *
     * @return array User data formatted for wizard use
     */
    public static function get_current_user_data(): array {
        if ( ! is_user_logged_in() ) {
            return [];
        }

        $user = wp_get_current_user();
        $mode = self::get_mode();

        $data = [
            'id'    => $user->ID,
            'name'  => $user->display_name,
            'email' => $user->user_email,
            'phone' => get_user_meta( $user->ID, 'phone', true ) ?: get_user_meta( $user->ID, 'mobile_phone', true ) ?: get_user_meta( $user->ID, 'billing_phone', true ),
            'photo' => \FRSLeadPages\get_user_photo( $user->ID ) ?: get_avatar_url( $user->ID, [ 'size' => 200 ] ),
            'mode'  => $mode,
        ];

        if ( $mode === self::MODE_LOAN_OFFICER ) {
            $data['nmls'] = \FRSLeadPages\frs_get_user_nmls( $user->ID );
            $data['title'] = get_user_meta( $user->ID, 'title', true ) ?: get_user_meta( $user->ID, 'job_title', true ) ?: 'Loan Officer';
            $data['company'] = '21st Century Lending';
            $data['arrive'] = LoanOfficers::get_user_arrive_link( $user->ID, $data['nmls'] );
        } else {
            $data['license'] = get_user_meta( $user->ID, 'license_number', true ) ?: get_user_meta( $user->ID, 'dre_license', true );
            $data['title'] = get_user_meta( $user->ID, 'title', true ) ?: get_user_meta( $user->ID, 'job_title', true ) ?: 'Sales Associate';
            $data['company'] = get_user_meta( $user->ID, 'company', true ) ?: get_user_meta( $user->ID, 'brokerage', true );
        }

        return $data;
    }

    /**
     * Get partner label based on user mode
     *
     * @return string Label for the partner field
     */
    public static function get_partner_label(): string {
        if ( self::is_loan_officer() ) {
            return 'Realtor Partner';
        }
        return 'Loan Officer';
    }

    /**
     * Get partners list based on user mode
     *
     * @return array List of potential partners
     */
    public static function get_partners(): array {
        if ( self::is_loan_officer() ) {
            return Realtors::get_realtors();
        }
        return LoanOfficers::get_loan_officers();
    }

    /**
     * Check if partner selection is optional for current mode
     *
     * For LOs, partnering with a realtor is optional.
     * For Realtors, partnering with an LO is required.
     *
     * @return bool
     */
    public static function is_partner_optional(): bool {
        return self::is_loan_officer();
    }

    /**
     * Get wizard step 0 config based on user mode
     *
     * For Realtors: Uses PartnerPortal to get available LOs and preferred selection
     * For LOs: Returns all Realtors (optional selection)
     *
     * @return array Configuration for the partner selection step
     */
    public static function get_partner_step_config(): array {
        $mode = self::get_mode();

        if ( $mode === self::MODE_LOAN_OFFICER ) {
            return [
                'title'         => 'Add a Realtor Partner',
                'subtitle'      => 'Optionally co-brand this page with a realtor',
                'label'         => 'Realtor Partner',
                'placeholder'   => 'Select a realtor (optional)...',
                'helper'        => 'Leave empty to create a page for yourself, or select a realtor to co-brand.',
                'required'      => false,
                'partners'      => Realtors::get_realtors(),
                'skip_text'     => 'Skip - Create for myself only',
                'preferred_id'  => 0,
                'show_remember' => false,
                'auto_selected' => false,
            ];
        }

        // Realtor mode: Use PartnerPortal for available LOs and preferences
        $available_los = PartnerPortal::get_available_loan_officers();
        $preferred_lo = PartnerPortal::get_preferred_loan_officer();

        $config = [
            'title'         => 'Partner Up',
            'subtitle'      => 'Select a loan officer to co-brand this page',
            'label'         => 'Loan Officer',
            'placeholder'   => 'Select a loan officer...',
            'helper'        => 'Your loan officer\'s info and branding will appear on the page.',
            'required'      => true,
            'partners'      => $available_los,
            'skip_text'     => null,
            'preferred_id'  => $preferred_lo,
            'show_remember' => true, // Show "Remember my choice" checkbox
            'auto_selected' => false,
        ];

        // If only one LO available, auto-select them
        if ( count( $available_los ) === 1 ) {
            $config['preferred_id'] = (int) $available_los[0]['id'];
            $config['auto_selected'] = true;
            $config['helper'] = 'This loan officer will be featured on your page.';
        }

        // If preferred LO is set, update helper text
        if ( $preferred_lo > 0 && ! $config['auto_selected'] ) {
            $config['helper'] = 'Your preferred loan officer is pre-selected. You can change this anytime.';
        }

        return $config;
    }

    /**
     * Build meta data for lead page creation based on user mode
     *
     * @param int   $partner_id The selected partner ID (or 0 for none)
     * @param array $branding   Branding/contact info from wizard
     * @return array Meta key-value pairs for the lead page
     */
    public static function build_page_meta( int $partner_id, array $branding = [] ): array {
        $mode = self::get_mode();
        $user_id = get_current_user_id();
        $meta = [];

        if ( $mode === self::MODE_LOAN_OFFICER ) {
            // LO is primary
            $meta['_frs_loan_officer_id'] = $user_id;
            $meta['_frs_lo_name'] = $branding['loName'] ?? '';
            $meta['_frs_lo_phone'] = $branding['loPhone'] ?? '';
            $meta['_frs_lo_email'] = $branding['loEmail'] ?? '';
            $meta['_frs_lo_nmls'] = $branding['loNmls'] ?? '';

            // Realtor is optional partner
            if ( $partner_id ) {
                $meta['_frs_realtor_id'] = $partner_id;
                $realtor = Realtors::get_realtor( $partner_id );
                if ( $realtor ) {
                    $meta['_frs_realtor_name'] = $realtor['name'];
                    $meta['_frs_realtor_phone'] = $realtor['phone'];
                    $meta['_frs_realtor_email'] = $realtor['email'];
                    $meta['_frs_realtor_license'] = $realtor['license'];
                    $meta['_frs_realtor_company'] = $realtor['company'];
                }
            }
        } else {
            // Realtor is primary
            $meta['_frs_realtor_id'] = $user_id;
            $meta['_frs_realtor_name'] = $branding['realtorName'] ?? '';
            $meta['_frs_realtor_phone'] = $branding['realtorPhone'] ?? '';
            $meta['_frs_realtor_email'] = $branding['realtorEmail'] ?? '';
            $meta['_frs_realtor_license'] = $branding['realtorLicense'] ?? '';
            $meta['_frs_realtor_company'] = $branding['realtorCompany'] ?? '';

            // LO is required partner
            if ( $partner_id ) {
                $meta['_frs_loan_officer_id'] = $partner_id;
                $lo = LoanOfficers::get_loan_officer( $partner_id );
                if ( $lo ) {
                    $meta['_frs_lo_name'] = $lo['name'];
                    $meta['_frs_lo_phone'] = $lo['phone'];
                    $meta['_frs_lo_email'] = $lo['email'];
                    $meta['_frs_lo_nmls'] = $lo['nmls'];
                }
            }
        }

        return $meta;
    }
}
