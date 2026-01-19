<?php
/**
 * QR Code Generation for Open House Pages
 *
 * @package FRSLeadPages
 */

namespace FRSLeadPages\Core;

class QRCode {

    /**
     * Generate QR code for a lead page
     *
     * @param int    $page_id Lead page post ID
     * @param string $mode    URL mode: 'scan' adds ?scan=1 param
     * @return array          QR code data with URL and image
     */
    public static function generate( int $page_id, string $mode = 'scan' ): array {
        $page_url = get_permalink( $page_id );

        if ( $mode === 'scan' ) {
            $page_url = add_query_arg( 'scan', '1', $page_url );
        }

        // Generate QR code URL using Google Charts API (free, no API key needed)
        $qr_api_url = 'https://chart.googleapis.com/chart?' . http_build_query( [
            'chs'  => '300x300',
            'cht'  => 'qr',
            'chl'  => $page_url,
            'choe' => 'UTF-8',
            'chld' => 'M|2', // Medium error correction, 2px margin
        ] );

        // Try to download and store locally
        $local_url = self::store_locally( $page_id, $qr_api_url );

        return [
            'page_url'   => $page_url,
            'qr_url'     => $local_url ?: $qr_api_url,
            'qr_api_url' => $qr_api_url,
            'is_local'   => ! empty( $local_url ),
        ];
    }

    /**
     * Download QR code and store locally
     *
     * @param int    $page_id  Lead page post ID
     * @param string $qr_url   QR code API URL
     * @return string|null     Local URL or null on failure
     */
    private static function store_locally( int $page_id, string $qr_url ): ?string {
        // Download the image
        $response = wp_remote_get( $qr_url, [ 'timeout' => 10 ] );

        if ( is_wp_error( $response ) ) {
            return null;
        }

        $image_data = wp_remote_retrieve_body( $response );
        if ( empty( $image_data ) ) {
            return null;
        }

        // Generate filename
        $filename = 'qr-lead-page-' . $page_id . '.png';

        // Store in uploads
        $upload = wp_upload_bits( $filename, null, $image_data );

        if ( $upload['error'] ) {
            error_log( 'FRS Lead Pages - QR code upload failed: ' . $upload['error'] );
            return null;
        }

        // Store URL in post meta
        update_post_meta( $page_id, '_frs_qr_code_url', $upload['url'] );
        update_post_meta( $page_id, '_frs_qr_code_file', $upload['file'] );

        return $upload['url'];
    }

    /**
     * Get existing QR code for a page
     *
     * @param int $page_id Lead page post ID
     * @return string|null QR code URL or null
     */
    public static function get( int $page_id ): ?string {
        $url = get_post_meta( $page_id, '_frs_qr_code_url', true );

        if ( ! empty( $url ) ) {
            return $url;
        }

        return null;
    }

    /**
     * Generate or get existing QR code
     *
     * @param int $page_id Lead page post ID
     * @return array QR code data
     */
    public static function get_or_generate( int $page_id ): array {
        $existing = self::get( $page_id );

        if ( $existing ) {
            return [
                'page_url' => add_query_arg( 'scan', '1', get_permalink( $page_id ) ),
                'qr_url'   => $existing,
                'is_local' => true,
            ];
        }

        return self::generate( $page_id );
    }

    /**
     * Delete QR code for a page
     *
     * @param int $page_id Lead page post ID
     */
    public static function delete( int $page_id ): void {
        $file = get_post_meta( $page_id, '_frs_qr_code_file', true );

        if ( $file && file_exists( $file ) ) {
            unlink( $file );
        }

        delete_post_meta( $page_id, '_frs_qr_code_url' );
        delete_post_meta( $page_id, '_frs_qr_code_file' );
    }

    /**
     * Regenerate QR code for a page
     *
     * @param int $page_id Lead page post ID
     * @return array QR code data
     */
    public static function regenerate( int $page_id ): array {
        self::delete( $page_id );
        return self::generate( $page_id );
    }
}
