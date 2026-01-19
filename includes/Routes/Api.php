<?php
/**
 * REST API Routes for FRS Lead Pages
 *
 * @package FRSLeadPages
 */

namespace FRSLeadPages\Routes;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class Api {

    const NAMESPACE = 'frs-lead-pages/v1';

    /**
     * Register all routes
     */
    public static function register_routes() {
        // Loan Officers
        register_rest_route( self::NAMESPACE, '/loan-officers', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'get_loan_officers' ],
            'permission_callback' => [ __CLASS__, 'can_create_pages' ],
        ]);

        register_rest_route( self::NAMESPACE, '/loan-officers/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'get_loan_officer' ],
            'permission_callback' => [ __CLASS__, 'can_create_pages' ],
            'args'                => [
                'id' => [
                    'validate_callback' => function( $param ) {
                        return is_numeric( $param );
                    },
                ],
            ],
        ]);

        // Property Lookup (for Open House)
        register_rest_route( self::NAMESPACE, '/property/lookup', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'lookup_property' ],
            'permission_callback' => [ __CLASS__, 'can_create_pages' ],
        ]);

        // Lead Pages CRUD
        register_rest_route( self::NAMESPACE, '/pages', [
            [
                'methods'             => 'GET',
                'callback'            => [ __CLASS__, 'get_pages' ],
                'permission_callback' => [ __CLASS__, 'can_create_pages' ],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ __CLASS__, 'create_page' ],
                'permission_callback' => [ __CLASS__, 'can_create_pages' ],
            ],
        ]);

        register_rest_route( self::NAMESPACE, '/pages/(?P<id>\d+)', [
            [
                'methods'             => 'GET',
                'callback'            => [ __CLASS__, 'get_page' ],
                'permission_callback' => '__return_true', // Public for viewing
            ],
            [
                'methods'             => 'PUT',
                'callback'            => [ __CLASS__, 'update_page' ],
                'permission_callback' => [ __CLASS__, 'can_edit_page' ],
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [ __CLASS__, 'delete_page' ],
                'permission_callback' => [ __CLASS__, 'can_edit_page' ],
            ],
        ]);

        // Lead Submissions (form submission)
        register_rest_route( self::NAMESPACE, '/pages/(?P<page_id>\d+)/submit', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'submit_lead' ],
            'permission_callback' => '__return_true', // Public
        ]);

        // Submissions List (for dashboard)
        register_rest_route( self::NAMESPACE, '/submissions', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'get_submissions' ],
            'permission_callback' => [ __CLASS__, 'can_view_submissions' ],
        ]);

        // Stats endpoint
        register_rest_route( self::NAMESPACE, '/stats', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'get_stats' ],
            'permission_callback' => [ __CLASS__, 'can_view_submissions' ],
        ]);

        // Image Upload
        register_rest_route( self::NAMESPACE, '/upload', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'upload_image' ],
            'permission_callback' => [ __CLASS__, 'can_create_pages' ],
        ]);

        // QR Code generation
        register_rest_route( self::NAMESPACE, '/pages/(?P<id>\d+)/qr', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'get_qr_code' ],
            'permission_callback' => '__return_true',
            'args'                => [
                'id' => [
                    'validate_callback' => function( $param ) {
                        return is_numeric( $param );
                    },
                ],
            ],
        ]);

        // Regenerate QR Code
        register_rest_route( self::NAMESPACE, '/pages/(?P<id>\d+)/qr/regenerate', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'regenerate_qr_code' ],
            'permission_callback' => [ __CLASS__, 'can_edit_page' ],
        ]);

        // ========================================
        // Cross-Site Sync Endpoints (Multisite)
        // ========================================

        // Ping - Test connection from partner portal to lender portal
        register_rest_route( self::NAMESPACE, '/sync/ping', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'sync_ping' ],
            'permission_callback' => [ __CLASS__, 'validate_sync_api_key' ],
        ]);

        // Push - Partner portal pushes page to lender portal
        register_rest_route( self::NAMESPACE, '/sync/push', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'sync_push' ],
            'permission_callback' => [ __CLASS__, 'validate_sync_api_key' ],
        ]);

        // Register - Partner portal registers with lender portal
        register_rest_route( self::NAMESPACE, '/sync/register', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'sync_register' ],
            'permission_callback' => [ __CLASS__, 'validate_sync_api_key' ],
        ]);

        // Status - Get sync status for a page
        register_rest_route( self::NAMESPACE, '/sync/status/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'sync_status' ],
            'permission_callback' => [ __CLASS__, 'can_edit_page' ],
        ]);

        // Loan Officers - Partner portal fetches available LOs from lender portal
        register_rest_route( self::NAMESPACE, '/sync/loan-officers', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'sync_get_loan_officers' ],
            'permission_callback' => [ __CLASS__, 'validate_sync_api_key' ],
        ]);
    }

    /**
     * GET /pages/{id}/qr - Get QR code for a page
     */
    public static function get_qr_code( WP_REST_Request $request ): WP_REST_Response {
        $page_id = (int) $request->get_param( 'id' );
        $post = get_post( $page_id );

        if ( ! $post || $post->post_type !== 'frs_lead_page' ) {
            return new WP_REST_Response( [ 'error' => 'Page not found' ], 404 );
        }

        $qr_data = \FRSLeadPages\Core\QRCode::get_or_generate( $page_id );

        return new WP_REST_Response( [
            'success' => true,
            'data'    => $qr_data,
        ], 200 );
    }

    /**
     * POST /pages/{id}/qr/regenerate - Regenerate QR code
     */
    public static function regenerate_qr_code( WP_REST_Request $request ): WP_REST_Response {
        $page_id = (int) $request->get_param( 'id' );
        $post = get_post( $page_id );

        if ( ! $post || $post->post_type !== 'frs_lead_page' ) {
            return new WP_REST_Response( [ 'error' => 'Page not found' ], 404 );
        }

        $qr_data = \FRSLeadPages\Core\QRCode::regenerate( $page_id );

        return new WP_REST_Response( [
            'success' => true,
            'data'    => $qr_data,
        ], 200 );
    }

    /**
     * Permission: Can create pages
     *
     * Allowed roles: loan_officer, realtor_partner, administrator, editor, author, contributor
     */
    public static function can_create_pages(): bool {
        if ( ! is_user_logged_in() ) {
            return false;
        }

        $user = wp_get_current_user();
        $allowed_roles = [
            'administrator',
            'editor',
            'author',
            'contributor',
            'loan_officer',
            'realtor_partner',
        ];

        return (bool) array_intersect( $allowed_roles, $user->roles );
    }

    /**
     * Permission: Can edit specific page
     *
     * Allowed roles: loan_officer, realtor_partner, administrator, editor, author, contributor
     */
    public static function can_edit_page( WP_REST_Request $request ): bool {
        if ( ! is_user_logged_in() ) {
            return false;
        }

        $user = wp_get_current_user();
        $allowed_roles = [
            'administrator',
            'editor',
            'author',
            'contributor',
            'loan_officer',
            'realtor_partner',
        ];

        return (bool) array_intersect( $allowed_roles, $user->roles );
    }

    /**
     * Permission: Can view submissions
     *
     * Allowed roles: loan_officer, realtor_partner, administrator, editor, author, contributor
     */
    public static function can_view_submissions(): bool {
        if ( ! is_user_logged_in() ) {
            return false;
        }

        $user = wp_get_current_user();
        $allowed_roles = [
            'administrator',
            'editor',
            'author',
            'contributor',
            'loan_officer',
            'realtor_partner',
        ];

        return (bool) array_intersect( $allowed_roles, $user->roles );
    }

    /**
     * GET /loan-officers - List all loan officers
     *
     * Uses cached data from frs-wp-users API or local WordPress users
     */
    public static function get_loan_officers( WP_REST_Request $request ): WP_REST_Response {
        $search = $request->get_param( 'search' );
        $format = $request->get_param( 'format' ); // 'dropdown' for form options

        // Get loan officers from centralized source (cached)
        $loan_officers = \FRSLeadPages\Core\LoanOfficers::get_loan_officers();

        // Filter by search if provided
        if ( $search ) {
            $search_lower = strtolower( $search );
            $loan_officers = array_filter( $loan_officers, function( $lo ) use ( $search_lower ) {
                return strpos( strtolower( $lo['name'] ), $search_lower ) !== false
                    || strpos( strtolower( $lo['email'] ), $search_lower ) !== false;
            } );
        }

        // Return dropdown format if requested
        if ( $format === 'dropdown' ) {
            return new WP_REST_Response(
                \FRSLeadPages\Core\LoanOfficers::get_dropdown_options(),
                200
            );
        }

        return new WP_REST_Response( array_values( $loan_officers ), 200 );
    }

    /**
     * GET /loan-officers/{id} - Get single loan officer
     */
    public static function get_loan_officer( WP_REST_Request $request ): WP_REST_Response {
        $user_id = (int) $request->get_param( 'id' );
        $user = get_user_by( 'ID', $user_id );

        if ( ! $user ) {
            return new WP_REST_Response( [ 'error' => 'Loan officer not found' ], 404 );
        }

        return new WP_REST_Response( self::format_loan_officer( $user ), 200 );
    }

    /**
     * Format loan officer data
     */
    private static function format_loan_officer( \WP_User $user ): array {
        return [
            'id'    => $user->ID,
            'name'  => $user->display_name,
            'title' => get_user_meta( $user->ID, 'job_title', true ) ?: 'Loan Officer',
            'nmls'  => \FRSLeadPages\frs_get_user_nmls( $user->ID ),
            'phone' => get_user_meta( $user->ID, 'phone', true ) ?: get_user_meta( $user->ID, 'billing_phone', true ),
            'email' => $user->user_email,
            'photo' => get_avatar_url( $user->ID, [ 'size' => 200 ] ),
        ];
    }

    /**
     * POST /property/lookup - Lookup property details
     */
    public static function lookup_property( WP_REST_Request $request ): WP_REST_Response {
        $address = sanitize_text_field( $request->get_param( 'address' ) );

        if ( empty( $address ) ) {
            return new WP_REST_Response( [ 'error' => 'Address is required' ], 400 );
        }

        // Try Rentcast API if available (optional integration)
        if ( function_exists( 'lrh_rentcast_property_lookup' ) ) {
            $result = lrh_rentcast_property_lookup( $address );
            if ( $result && ! is_wp_error( $result ) ) {
                return new WP_REST_Response( $result, 200 );
            }
        }

        // Fallback: Return empty data for manual entry
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Property not found. Please enter details manually.',
            'data'    => [
                'address' => $address,
                'price'   => '',
                'beds'    => '',
                'baths'   => '',
                'sqft'    => '',
                'photos'  => [],
            ],
        ], 200 );
    }

    /**
     * GET /pages - List pages
     *
     * Query params:
     * - loan_officer_id: Filter by loan officer ID
     * - realtor_id: Filter by realtor ID
     * - type: Filter by page type
     */
    public static function get_pages( WP_REST_Request $request ): WP_REST_Response {
        $loan_officer_id = $request->get_param( 'loan_officer_id' );
        $realtor_id = $request->get_param( 'realtor_id' );
        $page_type = $request->get_param( 'type' );

        $args = [
            'post_type'      => 'frs_lead_page',
            'posts_per_page' => 100,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'post_status'    => 'any',
            'meta_query'     => [ 'relation' => 'AND' ],
        ];

        // Filter by loan officer
        if ( $loan_officer_id ) {
            $args['meta_query'][] = [
                'key'   => '_frs_loan_officer_id',
                'value' => absint( $loan_officer_id ),
            ];
        }

        // Filter by realtor
        if ( $realtor_id ) {
            $args['meta_query'][] = [
                'key'   => '_frs_realtor_id',
                'value' => absint( $realtor_id ),
            ];
        }

        // Filter by page type
        if ( $page_type ) {
            $args['meta_query'][] = [
                'key'   => '_frs_page_type',
                'value' => sanitize_text_field( $page_type ),
            ];
        }

        // If no filters provided and not admin, use current user
        if ( ! $loan_officer_id && ! $realtor_id && ! current_user_can( 'manage_options' ) ) {
            $args['meta_query'][] = [
                'relation' => 'OR',
                [
                    'key'   => '_frs_realtor_id',
                    'value' => get_current_user_id(),
                ],
                [
                    'key'   => '_frs_loan_officer_id',
                    'value' => get_current_user_id(),
                ],
            ];
        }

        // Remove empty meta_query
        if ( count( $args['meta_query'] ) <= 1 ) {
            unset( $args['meta_query'] );
        }

        $query = new \WP_Query( $args );

        $pages = array_map( function( $post ) {
            return self::format_page( $post, true );
        }, $query->posts );

        return new WP_REST_Response([
            'success' => true,
            'data'    => $pages,
            'total'   => $query->found_posts,
        ], 200 );
    }

    /**
     * GET /pages/{id} - Get single page
     */
    public static function get_page( WP_REST_Request $request ): WP_REST_Response {
        $page_id = (int) $request->get_param( 'id' );
        $post = get_post( $page_id );

        if ( ! $post || $post->post_type !== 'frs_lead_page' ) {
            return new WP_REST_Response( [ 'error' => 'Page not found' ], 404 );
        }

        return new WP_REST_Response( self::format_page( $post, true ), 200 );
    }

    /**
     * POST /pages - Create new page
     */
    public static function create_page( WP_REST_Request $request ): WP_REST_Response {
        $data = $request->get_json_params();

        // Validate required fields
        $page_type = sanitize_text_field( $data['pageType'] ?? 'open_house' );
        $lo_id = absint( $data['loanOfficerId'] ?? 0 );

        if ( ! $lo_id ) {
            return new WP_REST_Response( [ 'error' => 'Loan officer is required' ], 400 );
        }

        // Generate title
        $title = self::generate_page_title( $data );

        // Create post
        $post_data = [
            'post_title'  => $title,
            'post_type'   => 'frs_lead_page',
            'post_status' => 'publish',
            'post_author' => get_current_user_id(),
        ];

        $page_id = wp_insert_post( $post_data );

        if ( is_wp_error( $page_id ) ) {
            return new WP_REST_Response( [ 'error' => $page_id->get_error_message() ], 500 );
        }

        // Save meta fields
        self::save_page_meta( $page_id, $data );

        $post = get_post( $page_id );

        return new WP_REST_Response([
            'success' => true,
            'page'    => self::format_page( $post, true ),
            'url'     => get_permalink( $page_id ),
            'editUrl' => get_edit_post_link( $page_id, 'raw' ),
        ], 201 );
    }

    /**
     * PUT /pages/{id} - Update page
     */
    public static function update_page( WP_REST_Request $request ): WP_REST_Response {
        $page_id = (int) $request->get_param( 'id' );
        $data = $request->get_json_params();

        $post = get_post( $page_id );
        if ( ! $post || $post->post_type !== 'frs_lead_page' ) {
            return new WP_REST_Response( [ 'error' => 'Page not found' ], 404 );
        }

        // Update title if needed
        if ( ! empty( $data['title'] ) ) {
            wp_update_post([
                'ID'         => $page_id,
                'post_title' => sanitize_text_field( $data['title'] ),
            ]);
        }

        // Update meta
        self::save_page_meta( $page_id, $data );

        $post = get_post( $page_id );

        return new WP_REST_Response([
            'success' => true,
            'page'    => self::format_page( $post, true ),
        ], 200 );
    }

    /**
     * DELETE /pages/{id} - Delete page
     */
    public static function delete_page( WP_REST_Request $request ): WP_REST_Response {
        $page_id = (int) $request->get_param( 'id' );

        $result = wp_delete_post( $page_id, true );

        if ( ! $result ) {
            return new WP_REST_Response( [ 'error' => 'Failed to delete page' ], 500 );
        }

        return new WP_REST_Response( [ 'success' => true ], 200 );
    }

    /**
     * POST /pages/{page_id}/submit - Submit lead form
     */
    public static function submit_lead( WP_REST_Request $request ): WP_REST_Response {
        $page_id = (int) $request->get_param( 'page_id' );
        $data = $request->get_json_params();

        $post = get_post( $page_id );
        if ( ! $post || $post->post_type !== 'frs_lead_page' ) {
            return new WP_REST_Response( [ 'error' => 'Page not found' ], 404 );
        }

        // Validate required fields
        $name = sanitize_text_field( $data['fullName'] ?? '' );
        $email = sanitize_email( $data['email'] ?? '' );
        $phone = sanitize_text_field( $data['phone'] ?? '' );

        if ( ! $name || ! $email || ! $phone ) {
            return new WP_REST_Response( [ 'error' => 'Name, email, and phone are required' ], 400 );
        }

        // Get page meta
        $page_type = get_post_meta( $page_id, '_frs_page_type', true );
        $lo_id = (int) get_post_meta( $page_id, '_frs_loan_officer_id', true );
        $realtor_id = (int) get_post_meta( $page_id, '_frs_realtor_id', true );

        // Submit to FluentForms (our integration handles everything)
        $result = \FRSLeadPages\Integrations\FluentForms::submit_lead( $data, $page_id );

        if ( ! $result['success'] ) {
            // Fallback: store locally if FluentForms fails
            $submissions = (int) get_post_meta( $page_id, '_frs_page_submissions', true );
            update_post_meta( $page_id, '_frs_page_submissions', $submissions + 1 );

            // Store in post meta as backup
            $leads = get_post_meta( $page_id, '_frs_leads', true ) ?: [];
            $leads[] = [
                'name'       => $name,
                'email'      => $email,
                'phone'      => $phone,
                'data'       => $data,
                'created_at' => current_time( 'mysql' ),
            ];
            update_post_meta( $page_id, '_frs_leads', $leads );
        }

        // Add to FluentCRM if available (backup - FluentForms integration handles this too)
        if ( function_exists( 'FluentCrmApi' ) && ! $result['success'] ) {
            self::add_to_fluent_crm( $data, $page_type, $lo_id, $realtor_id, $page_id );
        }

        // Fire action for other integrations
        do_action( 'frs_lead_pages_submission', $data, $page_id, $page_type, $lo_id, $realtor_id );

        return new WP_REST_Response([
            'success'       => true,
            'message'       => 'Thank you! We will be in touch shortly.',
            'submission_id' => $result['submission_id'] ?? null,
        ], 200 );
    }

    /**
     * POST /upload - Upload image
     */
    public static function upload_image( WP_REST_Request $request ): WP_REST_Response {
        if ( ! function_exists( 'wp_handle_upload' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
        }

        $file = $request->get_file_params();

        if ( empty( $file['file'] ) ) {
            return new WP_REST_Response( [ 'error' => 'No file uploaded' ], 400 );
        }

        $attachment_id = media_handle_upload( 'file', 0 );

        if ( is_wp_error( $attachment_id ) ) {
            return new WP_REST_Response( [ 'error' => $attachment_id->get_error_message() ], 500 );
        }

        return new WP_REST_Response([
            'success' => true,
            'id'      => $attachment_id,
            'url'     => wp_get_attachment_image_url( $attachment_id, 'full' ),
        ], 200 );
    }

    /**
     * Format page for API response
     */
    private static function format_page( \WP_Post $post, bool $full = false ): array {
        $meta = get_post_meta( $post->ID );

        $data = [
            'id'              => $post->ID,
            'title'           => $post->post_title,
            'slug'            => $post->post_name,
            'url'             => get_permalink( $post->ID ),
            'page_type'       => $meta['_frs_page_type'][0] ?? 'open_house',
            'status'          => $post->post_status,
            'views'           => (int) ( $meta['_frs_page_views'][0] ?? 0 ),
            'submissions'     => (int) ( $meta['_frs_page_submissions'][0] ?? 0 ),
            'loan_officer_id' => (int) ( $meta['_frs_loan_officer_id'][0] ?? 0 ),
            'realtor_id'      => (int) ( $meta['_frs_realtor_id'][0] ?? 0 ),
            'headline'        => $meta['_frs_headline'][0] ?? '',
            'hero_image_url'  => self::get_hero_image_url( $meta ),
            'created_at'      => $post->post_date,
            'updated_at'      => $post->post_modified,
        ];

        if ( $full ) {
            $data['property_address'] = $meta['_frs_property_address'][0] ?? '';
            $data['property_price']   = $meta['_frs_property_price'][0] ?? '';
            $data['property_beds']    = $meta['_frs_property_beds'][0] ?? '';
            $data['property_baths']   = $meta['_frs_property_baths'][0] ?? '';
            $data['property_sqft']    = $meta['_frs_property_sqft'][0] ?? '';
            $data['subheadline']      = $meta['_frs_subheadline'][0] ?? '';
            $data['button_text']      = $meta['_frs_button_text'][0] ?? 'Sign In';
            $data['consent_text']     = $meta['_frs_consent_text'][0] ?? '';
            $data['form_questions']   = json_decode( $meta['_frs_form_questions'][0] ?? '{}', true );
        }

        return $data;
    }

    /**
     * Get hero image URL from meta
     */
    private static function get_hero_image_url( array $meta ): string {
        $image_id = (int) ( $meta['_frs_hero_image_id'][0] ?? 0 );
        if ( $image_id ) {
            $url = wp_get_attachment_image_url( $image_id, 'full' );
            if ( $url ) {
                return $url;
            }
        }
        return $meta['_frs_hero_image_url'][0] ?? '';
    }

    /**
     * Generate page title
     */
    private static function generate_page_title( array $data ): string {
        $type = $data['pageType'] ?? 'open_house';

        switch ( $type ) {
            case 'open_house':
                $address = $data['propertyAddress'] ?? '';
                return $address ? 'Open House: ' . $address : 'Open House';

            case 'customer_spotlight':
                $customer = $data['customerName'] ?? '';
                return $customer ? 'Spotlight: ' . $customer : 'Customer Spotlight';

            case 'special_event':
                $event = $data['eventName'] ?? '';
                return $event ?: 'Special Event';

            default:
                return 'Lead Page';
        }
    }

    /**
     * Save page meta fields
     */
    private static function save_page_meta( int $page_id, array $data ) {
        $meta_mapping = [
            'pageType'        => '_frs_page_type',
            'loanOfficerId'   => '_frs_loan_officer_id',
            'propertyAddress' => '_frs_property_address',
            'propertyPrice'   => '_frs_property_price',
            'propertyBeds'    => '_frs_property_beds',
            'propertyBaths'   => '_frs_property_baths',
            'propertySqft'    => '_frs_property_sqft',
            'heroImageId'     => '_frs_hero_image_id',
            'heroImageUrl'    => '_frs_hero_image_url',
            'headline'        => '_frs_headline',
            'subheadline'     => '_frs_subheadline',
            'buttonText'      => '_frs_button_text',
            'consentText'     => '_frs_consent_text',
            'spotlightType'   => '_frs_spotlight_type',
            'eventType'       => '_frs_event_type',
            'eventName'       => '_frs_event_name',
            'eventDate'       => '_frs_event_date',
            'eventTimeStart'  => '_frs_event_time_start',
            'eventTimeEnd'    => '_frs_event_time_end',
            'eventVenue'      => '_frs_event_venue',
            'eventAddress'    => '_frs_event_address',
        ];

        foreach ( $meta_mapping as $data_key => $meta_key ) {
            if ( isset( $data[ $data_key ] ) ) {
                $value = $data[ $data_key ];
                if ( is_numeric( $value ) ) {
                    $value = absint( $value );
                } else {
                    $value = sanitize_text_field( $value );
                }
                update_post_meta( $page_id, $meta_key, $value );
            }
        }

        // Handle form questions (JSON)
        if ( isset( $data['formQuestions'] ) ) {
            update_post_meta( $page_id, '_frs_form_questions', wp_json_encode( $data['formQuestions'] ) );
        }

        // Always set realtor ID to current user
        update_post_meta( $page_id, '_frs_realtor_id', get_current_user_id() );
    }

    /**
     * Submit to FluentForm
     */
    private static function submit_to_fluent_form( int $form_id, array $data, int $page_id ) {
        // Map data to FluentForm fields
        $form_data = [
            'names' => [
                'first_name' => $data['firstName'] ?? '',
                'last_name'  => $data['lastName'] ?? '',
            ],
            'email'  => $data['email'] ?? '',
            'phone'  => $data['phone'] ?? '',
            'source' => 'Lead Page #' . $page_id,
        ];

        // Submit via FluentForm API
        try {
            $api = fluentFormApi( 'forms' )->entryInstance( $form_id );
            $api->createEntry( $form_data );
        } catch ( \Exception $e ) {
            error_log( 'FRS Lead Pages - FluentForm submission failed: ' . $e->getMessage() );
        }
    }

    /**
     * Add contact to FluentCRM
     */
    private static function add_to_fluent_crm( array $data, string $page_type, int $lo_id, int $realtor_id, int $page_id ) {
        try {
            $contact_api = FluentCrmApi( 'contacts' );

            // Split name
            $name_parts = explode( ' ', $data['fullName'] ?? '', 2 );
            $first_name = $name_parts[0] ?? '';
            $last_name  = $name_parts[1] ?? '';

            // Determine tags based on page type
            $tags = [ 'generation-station' ];
            switch ( $page_type ) {
                case 'open_house':
                    $tags[] = 'open-house-lead';
                    break;
                case 'customer_spotlight':
                    $tags[] = 'spotlight-lead';
                    break;
                case 'special_event':
                    $tags[] = 'event-lead';
                    break;
            }

            // Add qualifying question tags
            if ( ! empty( $data['preApproved'] ) && $data['preApproved'] === false ) {
                $tags[] = 'not-pre-approved';
            }
            if ( ! empty( $data['workingWithAgent'] ) && $data['workingWithAgent'] === false ) {
                $tags[] = 'no-agent';
            }

            $contact_data = [
                'first_name' => $first_name,
                'last_name'  => $last_name,
                'email'      => $data['email'] ?? '',
                'phone'      => $data['phone'] ?? '',
                'status'     => 'subscribed',
                'tags'       => $tags,
                'custom_fields' => [
                    'source_page_id'  => $page_id,
                    'source_page_url' => get_permalink( $page_id ),
                    'loan_officer_id' => $lo_id,
                    'realtor_id'      => $realtor_id,
                ],
            ];

            $contact_api->createOrUpdate( $contact_data );

        } catch ( \Exception $e ) {
            error_log( 'FRS Lead Pages - FluentCRM add failed: ' . $e->getMessage() );
        }
    }

    /**
     * GET /submissions - Get lead submissions from FluentForms
     *
     * Query params:
     * - loan_officer_id: Filter by loan officer ID
     * - realtor_id: Filter by realtor ID
     * - page_id: Filter by specific page ID
     * - status: Filter by status
     */
    public static function get_submissions( WP_REST_Request $request ): WP_REST_Response {
        $loan_officer_id = $request->get_param( 'loan_officer_id' );
        $realtor_id = $request->get_param( 'realtor_id' );
        $page_id = $request->get_param( 'page_id' );
        $status = $request->get_param( 'status' );

        // Get submissions from FluentForms integration
        $submissions = \FRSLeadPages\Integrations\FluentForms::get_submissions([
            'loan_officer_id' => $loan_officer_id ? absint( $loan_officer_id ) : null,
            'realtor_id'      => $realtor_id ? absint( $realtor_id ) : null,
            'page_id'         => $page_id ? absint( $page_id ) : null,
            'status'          => $status ? sanitize_text_field( $status ) : null,
        ]);

        return new WP_REST_Response([
            'success' => true,
            'data'    => $submissions,
            'total'   => count( $submissions ),
        ], 200 );
    }

    /**
     * GET /stats - Get lead pages statistics
     *
     * Query params:
     * - loan_officer_id: Stats for loan officer
     * - realtor_id: Stats for realtor
     */
    public static function get_stats( WP_REST_Request $request ): WP_REST_Response {
        $loan_officer_id = $request->get_param( 'loan_officer_id' );
        $realtor_id = $request->get_param( 'realtor_id' );

        // Build pages query
        $args = [
            'post_type'      => 'frs_lead_page',
            'posts_per_page' => -1,
            'post_status'    => 'any',
            'meta_query'     => [ 'relation' => 'AND' ],
        ];

        if ( $loan_officer_id ) {
            $args['meta_query'][] = [
                'key'   => '_frs_loan_officer_id',
                'value' => absint( $loan_officer_id ),
            ];
        }

        if ( $realtor_id ) {
            $args['meta_query'][] = [
                'key'   => '_frs_realtor_id',
                'value' => absint( $realtor_id ),
            ];
        }

        // If no filters and not admin, use current user
        if ( ! $loan_officer_id && ! $realtor_id && ! current_user_can( 'manage_options' ) ) {
            $args['meta_query'][] = [
                'relation' => 'OR',
                [
                    'key'   => '_frs_realtor_id',
                    'value' => get_current_user_id(),
                ],
                [
                    'key'   => '_frs_loan_officer_id',
                    'value' => get_current_user_id(),
                ],
            ];
        }

        if ( count( $args['meta_query'] ) <= 1 ) {
            unset( $args['meta_query'] );
        }

        $query = new \WP_Query( $args );

        $total_pages = $query->found_posts;
        $total_views = 0;
        $total_submissions = 0;
        $pages_by_type = [
            'open_house'         => 0,
            'customer_spotlight' => 0,
            'event'              => 0,
            'general'            => 0,
        ];

        foreach ( $query->posts as $post ) {
            $total_views += (int) get_post_meta( $post->ID, '_frs_page_views', true );
            $total_submissions += (int) get_post_meta( $post->ID, '_frs_page_submissions', true );

            $page_type = get_post_meta( $post->ID, '_frs_page_type', true ) ?: 'general';
            if ( isset( $pages_by_type[ $page_type ] ) ) {
                $pages_by_type[ $page_type ]++;
            } else {
                $pages_by_type['general']++;
            }
        }

        // Calculate conversion rate
        $conversion_rate = $total_views > 0 ? round( ( $total_submissions / $total_views ) * 100, 1 ) : 0;

        return new WP_REST_Response([
            'success' => true,
            'data'    => [
                'total_pages'       => $total_pages,
                'total_views'       => $total_views,
                'total_submissions' => $total_submissions,
                'conversion_rate'   => $conversion_rate,
                'pages_by_type'     => $pages_by_type,
            ],
        ], 200 );
    }

    // ========================================
    // Cross-Site Sync Methods
    // ========================================

    /**
     * Permission: Validate sync API key from request header
     */
    public static function validate_sync_api_key( WP_REST_Request $request ): bool {
        $api_key = $request->get_header( 'X-FRS-API-Key' );

        if ( empty( $api_key ) ) {
            return false;
        }

        $stored_key = get_option( 'frs_sync_api_key', '' );

        return ! empty( $stored_key ) && hash_equals( $stored_key, $api_key );
    }

    /**
     * GET /sync/ping - Test connection
     */
    public static function sync_ping( WP_REST_Request $request ): WP_REST_Response {
        $portal_type = get_option( 'frs_portal_type', '' );

        return new WP_REST_Response([
            'success'     => true,
            'message'     => 'Connection successful',
            'portal_type' => $portal_type,
            'site_name'   => get_bloginfo( 'name' ),
            'site_url'    => home_url(),
            'version'     => FRS_LEAD_PAGES_VERSION,
        ], 200 );
    }

    /**
     * POST /sync/register - Register partner portal with lender portal
     */
    public static function sync_register( WP_REST_Request $request ): WP_REST_Response {
        $portal_type = get_option( 'frs_portal_type', '' );

        // Only lender portals can accept registrations
        if ( $portal_type !== 'lender' ) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'This site is not configured as a lender portal',
            ], 400 );
        }

        $data = $request->get_json_params();
        $partner_url = esc_url_raw( $data['site_url'] ?? '' );
        $partner_name = sanitize_text_field( $data['site_name'] ?? '' );

        if ( empty( $partner_url ) ) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Partner site URL is required',
            ], 400 );
        }

        // Get existing partner sites
        $partner_sites = get_option( 'frs_registered_partner_sites', [] );

        // Update or add partner
        $found = false;
        foreach ( $partner_sites as &$site ) {
            if ( $site['url'] === $partner_url ) {
                $site['name'] = $partner_name;
                $site['last_sync'] = current_time( 'mysql' );
                $site['status'] = 'active';
                $found = true;
                break;
            }
        }

        if ( ! $found ) {
            $partner_sites[] = [
                'url'        => $partner_url,
                'name'       => $partner_name,
                'registered' => current_time( 'mysql' ),
                'last_sync'  => current_time( 'mysql' ),
                'status'     => 'active',
            ];
        }

        update_option( 'frs_registered_partner_sites', $partner_sites );

        // Log the registration
        \FRSLeadPages\Core\SyncService::log( 'register', null, 'success', "Partner registered: {$partner_name}" );

        return new WP_REST_Response([
            'success' => true,
            'message' => 'Partner portal registered successfully',
        ], 200 );
    }

    /**
     * POST /sync/push - Receive page data from partner portal
     */
    public static function sync_push( WP_REST_Request $request ): WP_REST_Response {
        $portal_type = get_option( 'frs_portal_type', '' );

        // Only lender portals can receive pushes
        if ( $portal_type !== 'lender' ) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'This site is not configured as a lender portal',
            ], 400 );
        }

        $data = $request->get_json_params();

        // Validate required fields
        if ( empty( $data['source_id'] ) || empty( $data['source_url'] ) ) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Source ID and URL are required',
            ], 400 );
        }

        // Create or update the synced page
        $result = \FRSLeadPages\Core\SyncService::receive_page( $data );

        if ( ! $result['success'] ) {
            return new WP_REST_Response( $result, 500 );
        }

        return new WP_REST_Response( $result, 200 );
    }

    /**
     * GET /sync/status/{id} - Get sync status for a page
     */
    public static function sync_status( WP_REST_Request $request ): WP_REST_Response {
        $page_id = (int) $request->get_param( 'id' );
        $post = get_post( $page_id );

        if ( ! $post || $post->post_type !== 'frs_lead_page' ) {
            return new WP_REST_Response( [ 'error' => 'Page not found' ], 404 );
        }

        $sync_status = get_post_meta( $page_id, '_frs_sync_status', true ) ?: 'not_synced';
        $synced_to = get_post_meta( $page_id, '_frs_synced_to_url', true );
        $synced_id = get_post_meta( $page_id, '_frs_synced_to_id', true );
        $last_sync = get_post_meta( $page_id, '_frs_last_sync', true );
        $sync_error = get_post_meta( $page_id, '_frs_sync_error', true );

        return new WP_REST_Response([
            'success'     => true,
            'status'      => $sync_status,
            'synced_to'   => $synced_to,
            'synced_id'   => $synced_id,
            'last_sync'   => $last_sync,
            'sync_error'  => $sync_error,
        ], 200 );
    }

    /**
     * GET /sync/loan-officers - Get available loan officers for partner portal
     */
    public static function sync_get_loan_officers( WP_REST_Request $request ): WP_REST_Response {
        $partner_url = $request->get_header( 'X-FRS-Partner-URL' );

        // Get all loan officers
        $loan_officers = \FRSLeadPages\Core\LoanOfficers::get_loan_officers();

        // If partner URL provided, could filter by assigned LOs (future enhancement)
        // For now, return all LOs from lender portal

        return new WP_REST_Response([
            'success' => true,
            'data'    => $loan_officers,
        ], 200 );
    }
}
