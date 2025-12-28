<?php
/**
 * User Mode Detection
 *
 * Determines whether the current user is a Loan Officer or Realtor
 * and provides the appropriate partner selection options.
 *
 * Supports WordPress Multisite:
 * - 1 Lender Portal (main site) → LO mode
 * - Many Partner Portals (subsites) → Realtor mode
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
     * Site type constants for multisite
     */
    const SITE_TYPE_LENDER = 'lender';
    const SITE_TYPE_PARTNER = 'partner';

    /**
     * Cache for current site type
     */
    private static ?string $cached_site_type = null;

    /**
     * Get the current user's mode
     *
     * In multisite: Mode is determined by which portal the user is on.
     * - Lender Portal → LO mode
     * - Partner Portal → Realtor mode
     *
     * In single site: Mode is determined by user role.
     *
     * @return string One of the MODE_* constants
     */
    public static function get_mode(): string {
        if ( ! is_user_logged_in() ) {
            return '';
        }

        $user = wp_get_current_user();

        // In multisite, the SITE determines the mode (not the user role)
        if ( is_multisite() ) {
            $site_type = self::get_current_site_type();

            // Admin override via URL parameter (for testing)
            if ( in_array( 'administrator', $user->roles, true ) ) {
                $mode = sanitize_text_field( $_GET['mode'] ?? '' );
                if ( $mode === 'lo' || $mode === 'loan_officer' ) {
                    return self::MODE_LOAN_OFFICER;
                } elseif ( $mode === 'realtor' || $mode === 'partner' ) {
                    return self::MODE_REALTOR;
                }
            }

            // Site type determines mode
            if ( $site_type === self::SITE_TYPE_LENDER ) {
                return self::MODE_LOAN_OFFICER;
            } elseif ( $site_type === self::SITE_TYPE_PARTNER ) {
                return self::MODE_REALTOR;
            }

            // Fallback to role-based detection if site type not configured
        }

        // Single site or fallback: Use role-based detection
        return self::get_mode_from_role( $user );
    }

    /**
     * Get mode based on user role (single site behavior)
     *
     * @param \WP_User $user The user object
     * @return string Mode constant
     */
    private static function get_mode_from_role( \WP_User $user ): string {
        // Check for admin first (they can act as either)
        if ( in_array( 'administrator', $user->roles, true ) ) {
            // Check URL parameter to determine which mode admin wants
            $mode = sanitize_text_field( $_GET['mode'] ?? '' );
            if ( $mode === 'lo' || $mode === 'loan_officer' ) {
                return self::MODE_LOAN_OFFICER;
            } elseif ( $mode === 'realtor' || $mode === 'partner' ) {
                return self::MODE_REALTOR;
            }
            // Default admin to LO mode
            return self::MODE_LOAN_OFFICER;
        }

        // Check for loan officer role
        if ( array_intersect( [ 'loan_officer', 'editor' ], $user->roles ) ) {
            return self::MODE_LOAN_OFFICER;
        }

        // Check for realtor role
        if ( array_intersect( [ 'realtor', 'realtor_partner', 'agent', 'author', 'contributor' ], $user->roles ) ) {
            return self::MODE_REALTOR;
        }

        return '';
    }

    /**
     * Get the current site type in multisite
     *
     * Determines if current site is the Lender Portal or a Partner Portal.
     *
     * Detection priority:
     * 1. Site option 'frs_portal_type' (explicitly set per site)
     * 2. Main site (blog_id 1) = Lender, subsites = Partner
     * 3. URL pattern matching (configurable)
     *
     * @return string SITE_TYPE_LENDER or SITE_TYPE_PARTNER
     */
    public static function get_current_site_type(): string {
        if ( ! is_multisite() ) {
            return '';
        }

        // Return cached value if available
        if ( self::$cached_site_type !== null ) {
            return self::$cached_site_type;
        }

        $blog_id = get_current_blog_id();

        // 1. Check explicit site option
        $portal_type = get_option( 'frs_portal_type', '' );
        if ( $portal_type === 'lender' ) {
            self::$cached_site_type = self::SITE_TYPE_LENDER;
            return self::$cached_site_type;
        } elseif ( $portal_type === 'partner' ) {
            self::$cached_site_type = self::SITE_TYPE_PARTNER;
            return self::$cached_site_type;
        }

        // 2. Check network option for lender site ID
        $lender_site_id = get_site_option( 'frs_lender_portal_site_id', 1 );
        if ( $blog_id == $lender_site_id ) {
            self::$cached_site_type = self::SITE_TYPE_LENDER;
            return self::$cached_site_type;
        }

        // 3. Check URL pattern (configurable)
        $site_url = get_site_url();
        $lender_patterns = get_site_option( 'frs_lender_url_patterns', [ 'lending', 'lender', 'lo-portal' ] );

        foreach ( $lender_patterns as $pattern ) {
            if ( stripos( $site_url, $pattern ) !== false ) {
                self::$cached_site_type = self::SITE_TYPE_LENDER;
                return self::$cached_site_type;
            }
        }

        // Default: All other sites are Partner Portals
        self::$cached_site_type = self::SITE_TYPE_PARTNER;
        return self::$cached_site_type;
    }

    /**
     * Check if current site is the Lender Portal
     *
     * @return bool
     */
    public static function is_lender_portal(): bool {
        return self::get_current_site_type() === self::SITE_TYPE_LENDER;
    }

    /**
     * Check if current site is a Partner Portal
     *
     * @return bool
     */
    public static function is_partner_portal(): bool {
        return self::get_current_site_type() === self::SITE_TYPE_PARTNER;
    }

    /**
     * Clear cached site type (useful for testing)
     */
    public static function clear_cache(): void {
        self::$cached_site_type = null;
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
     * @return array Configuration for the partner selection step
     */
    public static function get_partner_step_config(): array {
        $mode = self::get_mode();

        if ( $mode === self::MODE_LOAN_OFFICER ) {
            return [
                'title'       => 'Add a Realtor Partner',
                'subtitle'    => 'Optionally co-brand this page with a realtor',
                'label'       => 'Realtor Partner',
                'placeholder' => 'Select a realtor (optional)...',
                'helper'      => 'Leave empty to create a page for yourself, or select a realtor to co-brand.',
                'required'    => false,
                'partners'    => Realtors::get_realtors(),
                'skip_text'   => 'Skip - Create for myself only',
            ];
        }

        // Default: Realtor mode
        return [
            'title'       => 'Partner Up',
            'subtitle'    => 'Select a loan officer to co-brand this page',
            'label'       => 'Loan Officer',
            'placeholder' => 'Select a loan officer...',
            'helper'      => 'Your loan officer\'s info and branding will appear on the page.',
            'required'    => true,
            'partners'    => LoanOfficers::get_loan_officers(),
            'skip_text'   => null,
        ];
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
