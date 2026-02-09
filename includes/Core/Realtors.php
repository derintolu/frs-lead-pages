<?php
/**
 * Realtors API Integration
 *
 * Fetches realtor partners for loan officers to co-brand with.
 * Mirror of LoanOfficers.php but for the reverse direction.
 *
 * @package FRSLeadPages
 */

namespace FRSLeadPages\Core;

class Realtors {

    /**
     * Transient key for cached realtors
     */
    const CACHE_KEY = 'frs_lead_pages_realtors';

    /**
     * Get realtors (cached)
     *
     * @param bool $force_refresh Force refresh from API
     * @return array Array of realtor data
     */
    public static function get_realtors( bool $force_refresh = false ): array {
        if ( ! $force_refresh ) {
            $cached = get_transient( self::CACHE_KEY );
            if ( $cached !== false ) {
                return $cached;
            }
        }

        // Try external API first (frs-wp-users on hub21)
        $api_url = get_option( 'frs_lead_pages_users_api_url', '' );

        if ( ! empty( $api_url ) ) {
            $realtors = self::fetch_from_api( $api_url );
            if ( ! empty( $realtors ) ) {
                self::cache_realtors( $realtors );
                return $realtors;
            }
        }

        // Fallback to local WordPress users
        $realtors = self::fetch_local_users();
        self::cache_realtors( $realtors );

        return $realtors;
    }

    /**
     * Fetch realtors from frs-wp-users API
     *
     * @param string $api_url Base API URL
     * @return array
     */
    private static function fetch_from_api( string $api_url ): array {
        $api_key = get_option( 'frs_lead_pages_users_api_key', '' );

        $headers = [
            'Accept' => 'application/json',
        ];

        if ( ! empty( $api_key ) ) {
            $headers['Authorization'] = 'Bearer ' . $api_key;
        }

        // Call the profiles endpoint with type=agent filter
        $response = wp_remote_get( trailingslashit( $api_url ) . 'wp-json/frs-users/v1/profiles?type=agent', [
            'headers' => $headers,
            'timeout' => 10,
        ] );

        if ( is_wp_error( $response ) ) {
            error_log( 'FRS Lead Pages - Realtors API fetch failed: ' . $response->get_error_message() );
            return [];
        }

        $status = wp_remote_retrieve_response_code( $response );
        if ( $status !== 200 ) {
            error_log( 'FRS Lead Pages - Realtors API returned status ' . $status );
            return [];
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! is_array( $body ) ) {
            return [];
        }

        // Normalize data structure
        return array_map( function( $realtor ) {
            return [
                'id'        => $realtor['id'] ?? 0,
                'user_id'   => $realtor['user_id'] ?? 0,
                'name'      => trim( ( $realtor['first_name'] ?? '' ) . ' ' . ( $realtor['last_name'] ?? '' ) ) ?: ( $realtor['name'] ?? $realtor['display_name'] ?? '' ),
                'email'     => $realtor['email'] ?? '',
                'phone'     => $realtor['mobile_phone'] ?? $realtor['phone'] ?? '',
                'license'   => $realtor['dre_license'] ?? $realtor['license_number'] ?? '',
                'company'   => $realtor['brokerage'] ?? $realtor['company'] ?? '',
                'title'     => $realtor['title'] ?? 'Sales Associate',
                'photo_url' => $realtor['headshot_url'] ?? $realtor['photo_url'] ?? $realtor['avatar'] ?? '',
                'active'    => $realtor['active'] ?? true,
            ];
        }, $body );
    }

    /**
     * Fetch realtors from local WordPress users
     *
     * @return array
     */
    private static function fetch_local_users(): array {
        $users = get_users( [
            'role__in' => [ 'realtor', 'realtor_partner', 'agent' ],
            'orderby'  => 'display_name',
            'order'    => 'ASC',
            'number'   => 100,
        ] );

        return array_map( function( $user ) {
            return [
                'id'        => $user->ID,
                'user_id'   => $user->ID,
                'name'      => $user->display_name,
                'email'     => $user->user_email,
                'phone'     => get_user_meta( $user->ID, 'phone', true ) ?: get_user_meta( $user->ID, 'mobile_phone', true ) ?: get_user_meta( $user->ID, 'billing_phone', true ),
                'license'   => get_user_meta( $user->ID, 'license_number', true ) ?: get_user_meta( $user->ID, 'dre_license', true ),
                'company'   => get_user_meta( $user->ID, 'company', true ) ?: get_user_meta( $user->ID, 'brokerage', true ),
                'title'     => get_user_meta( $user->ID, 'title', true ) ?: get_user_meta( $user->ID, 'job_title', true ) ?: 'Sales Associate',
                'photo_url' => \FRSLeadPages\get_user_photo( $user->ID ) ?: \FRSLeadPages\frs_normalize_upload_url( get_avatar_url( $user->ID, [ 'size' => 200 ] ) ),
                'active'    => true,
            ];
        }, $users );
    }

    /**
     * Cache realtors
     *
     * @param array $realtors Realtors data
     */
    private static function cache_realtors( array $realtors ): void {
        $duration = get_option( 'frs_lead_pages_realtor_cache_duration', 3600 );
        set_transient( self::CACHE_KEY, $realtors, $duration );

        // Also store as fallback
        update_option( 'frs_lead_pages_realtor_fallback', $realtors );
    }

    /**
     * Get realtors formatted for dropdown
     *
     * @return array Options array for dropdown field
     */
    public static function get_dropdown_options(): array {
        $realtors = self::get_realtors();

        return array_map( function( $realtor ) {
            $label = $realtor['name'];
            if ( ! empty( $realtor['company'] ) ) {
                $label .= ' | ' . $realtor['company'];
            }

            return [
                'label' => $label,
                'value' => (string) ( $realtor['user_id'] ?: $realtor['id'] ),
                'image' => $realtor['photo_url'] ?? '',
            ];
        }, array_filter( $realtors, fn( $r ) => $r['active'] ?? true ) );
    }

    /**
     * Get single realtor by ID
     *
     * @param int $id Realtor user ID
     * @return array|null
     */
    public static function get_realtor( int $id ): ?array {
        $realtors = self::get_realtors();

        foreach ( $realtors as $realtor ) {
            if ( (int) ( $realtor['user_id'] ?: $realtor['id'] ) === $id ) {
                return $realtor;
            }
        }

        // Not in cache, try direct lookup
        $user = get_user_by( 'ID', $id );
        if ( ! $user ) {
            return null;
        }

        return [
            'id'        => $user->ID,
            'user_id'   => $user->ID,
            'name'      => $user->display_name,
            'email'     => $user->user_email,
            'phone'     => get_user_meta( $id, 'phone', true ) ?: get_user_meta( $id, 'mobile_phone', true ) ?: get_user_meta( $id, 'billing_phone', true ),
            'license'   => get_user_meta( $id, 'license_number', true ) ?: get_user_meta( $id, 'dre_license', true ),
            'company'   => get_user_meta( $id, 'company', true ) ?: get_user_meta( $id, 'brokerage', true ),
            'title'     => get_user_meta( $id, 'title', true ) ?: get_user_meta( $id, 'job_title', true ) ?: 'Sales Associate',
            'photo_url' => \FRSLeadPages\get_user_photo( $id ) ?: \FRSLeadPages\frs_normalize_upload_url( get_avatar_url( $id, [ 'size' => 200 ] ) ),
            'active'    => true,
        ];
    }

    /**
     * Force refresh realtors cache
     *
     * @return array Fresh realtors data
     */
    public static function refresh(): array {
        delete_transient( self::CACHE_KEY );
        return self::get_realtors( true );
    }
}
