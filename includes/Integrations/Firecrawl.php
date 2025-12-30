<?php
/**
 * Firecrawl API Integration
 *
 * Fetches property data and images from real estate listings.
 *
 * @package FRSLeadPages
 */

namespace FRSLeadPages\Integrations;

class Firecrawl {

    /**
     * API endpoint
     */
    const API_URL = 'https://api.firecrawl.dev/v1';

    /**
     * Get API key from settings
     */
    public static function get_api_key(): string {
        // Try frs-lead-pages setting first, fallback to psb setting
        $key = get_option( 'frs_lead_pages_firecrawl_api_key', '' );
        if ( empty( $key ) ) {
            $key = get_option( 'psb_firecrawl_api_key', '' );
        }
        return $key;
    }

    /**
     * Check if API is configured
     */
    public static function is_configured(): bool {
        return ! empty( self::get_api_key() );
    }

    /**
     * Scrape a listing URL for property data
     *
     * @param string $url Listing URL (Zillow, Redfin, Realtor.com, etc.)
     * @return array|WP_Error Property data or error
     */
    public static function scrape_listing( string $url ): array|\WP_Error {
        $api_key = self::get_api_key();

        if ( empty( $api_key ) ) {
            return new \WP_Error( 'no_api_key', 'Firecrawl API key not configured' );
        }

        $response = wp_remote_post( self::API_URL . '/scrape', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode([
                'url'     => $url,
                'formats' => [ 'markdown', 'extract' ],
                'extract' => [
                    'schema' => [
                        'type'       => 'object',
                        'properties' => [
                            'address'    => [ 'type' => 'string' ],
                            'price'      => [ 'type' => 'number' ],
                            'bedrooms'   => [ 'type' => 'number' ],
                            'bathrooms'  => [ 'type' => 'number' ],
                            'sqft'       => [ 'type' => 'number' ],
                            'year_built' => [ 'type' => 'number' ],
                            'lot_size'   => [ 'type' => 'string' ],
                            'mls_number' => [ 'type' => 'string' ],
                            'images'     => [
                                'type'  => 'array',
                                'items' => [ 'type' => 'string' ],
                            ],
                        ],
                    ],
                ],
            ]),
            'timeout' => 30,
        ]);

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code !== 200 ) {
            return new \WP_Error(
                'api_error',
                $body['error'] ?? 'Failed to fetch property data',
                [ 'status' => $code ]
            );
        }

        // Extract property data
        $extract = $body['data']['extract'] ?? [];
        $images  = $body['data']['images'] ?? [];

        // Merge extracted images with page images
        $all_images = array_merge(
            $extract['images'] ?? [],
            $images
        );

        // Filter to only property images (exclude icons, logos, etc.)
        $property_images = array_filter( $all_images, function( $img ) {
            // Skip small images and common non-property patterns
            if ( stripos( $img, 'logo' ) !== false ) return false;
            if ( stripos( $img, 'icon' ) !== false ) return false;
            if ( stripos( $img, 'avatar' ) !== false ) return false;
            if ( stripos( $img, 'profile' ) !== false ) return false;
            return true;
        });

        return [
            'address'    => $extract['address'] ?? '',
            'price'      => (int) ( $extract['price'] ?? 0 ),
            'bedrooms'   => (int) ( $extract['bedrooms'] ?? 0 ),
            'bathrooms'  => (float) ( $extract['bathrooms'] ?? 0 ),
            'sqft'       => (int) ( $extract['sqft'] ?? 0 ),
            'year_built' => (int) ( $extract['year_built'] ?? 0 ),
            'lot_size'   => $extract['lot_size'] ?? '',
            'mls_number' => $extract['mls_number'] ?? '',
            'images'     => array_values( array_slice( $property_images, 0, 3 ) ),
            'source_url' => $url,
        ];
    }

    /**
     * Search for property by address
     *
     * @param string $address Property address
     * @return array|WP_Error Property data or error
     */
    public static function search_property( string $address ): array|\WP_Error {
        $api_key = self::get_api_key();

        if ( empty( $api_key ) ) {
            return new \WP_Error( 'no_api_key', 'Firecrawl API key not configured' );
        }

        // Search for the property listing
        $response = wp_remote_post( self::API_URL . '/search', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode([
                'query'  => $address . ' real estate listing',
                'limit'  => 5,
                'scrapeOptions' => [
                    'formats' => [ 'markdown' ],
                ],
            ]),
            'timeout' => 30,
        ]);

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code !== 200 ) {
            return new \WP_Error(
                'api_error',
                $body['error'] ?? 'Failed to search for property',
                [ 'status' => $code ]
            );
        }

        $results = $body['data'] ?? [];

        if ( empty( $results ) ) {
            return new \WP_Error( 'not_found', 'No listing found for this address' );
        }

        // Find the best match (prefer Zillow, Redfin, Realtor.com)
        $preferred_domains = [ 'zillow.com', 'redfin.com', 'realtor.com' ];
        $best_result = $results[0];

        foreach ( $results as $result ) {
            foreach ( $preferred_domains as $domain ) {
                if ( isset( $result['url'] ) && stripos( $result['url'], $domain ) !== false ) {
                    $best_result = $result;
                    break 2;
                }
            }
        }

        // If we found a URL, scrape it for full details
        if ( ! empty( $best_result['url'] ) ) {
            return self::scrape_listing( $best_result['url'] );
        }

        return new \WP_Error( 'not_found', 'Could not find property listing' );
    }
}
