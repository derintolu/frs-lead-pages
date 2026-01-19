<?php
/**
 * Custom Post Types for FRS Lead Pages
 *
 * @package FRSLeadPages
 */

namespace FRSLeadPages\Core;

class PostTypes {

    /**
     * Initialize hooks
     */
    public static function init() {
        add_action( 'init', [ __CLASS__, 'register' ] );

        // Add custom fields to REST response
        add_action( 'rest_api_init', [ __CLASS__, 'register_rest_fields' ] );
    }

    /**
     * Register custom post types
     */
    public static function register() {
        // Lead Page post type
        register_post_type( 'frs_lead_page', [
            'labels' => [
                'name'               => __( 'Lead Pages', 'frs-lead-pages' ),
                'singular_name'      => __( 'Lead Page', 'frs-lead-pages' ),
                'add_new'            => __( 'Add New', 'frs-lead-pages' ),
                'add_new_item'       => __( 'Add New Lead Page', 'frs-lead-pages' ),
                'edit_item'          => __( 'Edit Lead Page', 'frs-lead-pages' ),
                'new_item'           => __( 'New Lead Page', 'frs-lead-pages' ),
                'view_item'          => __( 'View Lead Page', 'frs-lead-pages' ),
                'search_items'       => __( 'Search Lead Pages', 'frs-lead-pages' ),
                'not_found'          => __( 'No lead pages found', 'frs-lead-pages' ),
                'not_found_in_trash' => __( 'No lead pages found in trash', 'frs-lead-pages' ),
                'menu_name'          => __( 'Lead Pages', 'frs-lead-pages' ),
            ],
            'public'              => true,
            'publicly_queryable'  => true,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'show_in_rest'        => true,
            'rest_base'           => 'lead-pages',
            'rest_controller_class' => 'WP_REST_Posts_Controller',
            'menu_position'       => 25,
            'menu_icon'           => 'dashicons-megaphone',
            'supports'            => [ 'title', 'custom-fields', 'author' ],
            'has_archive'         => false,
            'rewrite'             => [
                'slug'       => 'p', // Short URLs: /p/open-house-123-main-st
                'with_front' => false,
            ],
            // Use standard post capabilities so anyone who can edit_posts can create lead pages
            'capability_type'     => 'post',
            'map_meta_cap'        => true,
        ]);

        // Register meta fields
        self::register_meta_fields();
    }

    /**
     * Register post meta fields
     */
    private static function register_meta_fields() {
        $meta_fields = [
            // Page type
            '_frs_page_type' => [
                'type'        => 'string',
                'description' => 'Page type: open_house, customer_spotlight, special_event',
                'single'      => true,
                'default'     => 'open_house',
            ],
            // Loan officer
            '_frs_loan_officer_id' => [
                'type'        => 'integer',
                'description' => 'Loan officer user ID',
                'single'      => true,
                'default'     => 0,
            ],
            // Realtor
            '_frs_realtor_id' => [
                'type'        => 'integer',
                'description' => 'Realtor user ID (page owner)',
                'single'      => true,
                'default'     => 0,
            ],
            // Property details (Open House)
            '_frs_property_address' => [
                'type'        => 'string',
                'description' => 'Property address',
                'single'      => true,
                'default'     => '',
            ],
            '_frs_property_price' => [
                'type'        => 'string',
                'description' => 'Property price',
                'single'      => true,
                'default'     => '',
            ],
            '_frs_property_beds' => [
                'type'        => 'string',
                'description' => 'Number of bedrooms',
                'single'      => true,
                'default'     => '',
            ],
            '_frs_property_baths' => [
                'type'        => 'string',
                'description' => 'Number of bathrooms',
                'single'      => true,
                'default'     => '',
            ],
            '_frs_property_sqft' => [
                'type'        => 'string',
                'description' => 'Square footage',
                'single'      => true,
                'default'     => '',
            ],
            // Hero image
            '_frs_hero_image_id' => [
                'type'        => 'integer',
                'description' => 'Hero image attachment ID',
                'single'      => true,
                'default'     => 0,
            ],
            '_frs_hero_image_url' => [
                'type'        => 'string',
                'description' => 'Hero image URL (if external)',
                'single'      => true,
                'default'     => '',
            ],
            // Content
            '_frs_headline' => [
                'type'        => 'string',
                'description' => 'Page headline',
                'single'      => true,
                'default'     => 'Welcome!',
            ],
            '_frs_subheadline' => [
                'type'        => 'string',
                'description' => 'Page subheadline',
                'single'      => true,
                'default'     => 'Sign in to tour this beautiful home',
            ],
            '_frs_button_text' => [
                'type'        => 'string',
                'description' => 'Submit button text',
                'single'      => true,
                'default'     => 'Sign In',
            ],
            '_frs_consent_text' => [
                'type'        => 'string',
                'description' => 'Consent/fine print text',
                'single'      => true,
                'default'     => 'By signing in, you agree to receive communications about this property and financing options.',
            ],
            // Form questions (JSON)
            '_frs_form_questions' => [
                'type'        => 'string',
                'description' => 'Form questions configuration (JSON)',
                'single'      => true,
                'default'     => '',
            ],
            // Spotlight type
            '_frs_spotlight_type' => [
                'type'        => 'string',
                'description' => 'Spotlight type: first_time, veteran, investor, refinance, move_up, downsizer',
                'single'      => true,
                'default'     => '',
            ],
            // Event details
            '_frs_event_type' => [
                'type'        => 'string',
                'description' => 'Event type',
                'single'      => true,
                'default'     => '',
            ],
            '_frs_event_name' => [
                'type'        => 'string',
                'description' => 'Event name',
                'single'      => true,
                'default'     => '',
            ],
            '_frs_event_date' => [
                'type'        => 'string',
                'description' => 'Event date',
                'single'      => true,
                'default'     => '',
            ],
            '_frs_event_time_start' => [
                'type'        => 'string',
                'description' => 'Event start time',
                'single'      => true,
                'default'     => '',
            ],
            '_frs_event_time_end' => [
                'type'        => 'string',
                'description' => 'Event end time',
                'single'      => true,
                'default'     => '',
            ],
            '_frs_event_venue' => [
                'type'        => 'string',
                'description' => 'Event venue name',
                'single'      => true,
                'default'     => '',
            ],
            '_frs_event_address' => [
                'type'        => 'string',
                'description' => 'Event address',
                'single'      => true,
                'default'     => '',
            ],
            // Analytics
            '_frs_page_views' => [
                'type'        => 'integer',
                'description' => 'Page view count',
                'single'      => true,
                'default'     => 0,
            ],
            '_frs_page_submissions' => [
                'type'        => 'integer',
                'description' => 'Form submission count',
                'single'      => true,
                'default'     => 0,
            ],
            // FluentForm integration
            '_frs_fluent_form_id' => [
                'type'        => 'integer',
                'description' => 'Associated FluentForm ID',
                'single'      => true,
                'default'     => 0,
            ],
        ];

        foreach ( $meta_fields as $key => $args ) {
            register_post_meta( 'frs_lead_page', $key, [
                'type'              => $args['type'],
                'description'       => $args['description'],
                'single'            => $args['single'],
                'default'           => $args['default'],
                'show_in_rest'      => true,
                'sanitize_callback' => $args['type'] === 'integer' ? 'absint' : 'sanitize_text_field',
                'auth_callback'     => function( $allowed, $meta_key, $post_id ) {
                    return current_user_can( 'edit_post', $post_id );
                },
            ]);
        }
    }

    /**
     * Register additional REST API fields for full page data
     */
    public static function register_rest_fields() {
        // Add loan officer data to REST response
        register_rest_field( 'frs_lead_page', 'loan_officer', [
            'get_callback' => [ __CLASS__, 'get_loan_officer_field' ],
            'schema'       => [
                'description' => 'Loan officer details',
                'type'        => 'object',
                'context'     => [ 'view', 'edit' ],
                'properties'  => [
                    'id'    => [ 'type' => 'integer' ],
                    'name'  => [ 'type' => 'string' ],
                    'email' => [ 'type' => 'string' ],
                    'phone' => [ 'type' => 'string' ],
                    'nmls'  => [ 'type' => 'string' ],
                    'title' => [ 'type' => 'string' ],
                    'photo' => [ 'type' => 'string' ],
                ],
            ],
        ]);

        // Add realtor data to REST response
        register_rest_field( 'frs_lead_page', 'realtor', [
            'get_callback' => [ __CLASS__, 'get_realtor_field' ],
            'schema'       => [
                'description' => 'Realtor details',
                'type'        => 'object',
                'context'     => [ 'view', 'edit' ],
                'properties'  => [
                    'id'      => [ 'type' => 'integer' ],
                    'name'    => [ 'type' => 'string' ],
                    'email'   => [ 'type' => 'string' ],
                    'phone'   => [ 'type' => 'string' ],
                    'company' => [ 'type' => 'string' ],
                    'license' => [ 'type' => 'string' ],
                    'photo'   => [ 'type' => 'string' ],
                ],
            ],
        ]);

        // Add page URL to REST response
        register_rest_field( 'frs_lead_page', 'page_url', [
            'get_callback' => function( $post ) {
                return get_permalink( $post['id'] );
            },
            'schema'       => [
                'description' => 'Public URL of the lead page',
                'type'        => 'string',
                'format'      => 'uri',
                'context'     => [ 'view', 'edit' ],
            ],
        ]);

        // Add QR code data to REST response
        register_rest_field( 'frs_lead_page', 'qr_code', [
            'get_callback' => function( $post ) {
                return QRCode::get( $post['id'] );
            },
            'schema'       => [
                'description' => 'QR code data for the page',
                'type'        => 'object',
                'context'     => [ 'view', 'edit' ],
            ],
        ]);

        // Add analytics summary
        register_rest_field( 'frs_lead_page', 'analytics', [
            'get_callback' => function( $post ) {
                $views = (int) get_post_meta( $post['id'], '_frs_page_views', true );
                $submissions = (int) get_post_meta( $post['id'], '_frs_page_submissions', true );
                return [
                    'views'           => $views,
                    'submissions'     => $submissions,
                    'conversion_rate' => $views > 0 ? round( ( $submissions / $views ) * 100, 1 ) : 0,
                ];
            },
            'schema'       => [
                'description' => 'Page analytics summary',
                'type'        => 'object',
                'context'     => [ 'view', 'edit' ],
                'properties'  => [
                    'views'           => [ 'type' => 'integer' ],
                    'submissions'     => [ 'type' => 'integer' ],
                    'conversion_rate' => [ 'type' => 'number' ],
                ],
            ],
        ]);

        // Add hero image URL (resolved from ID or direct URL)
        register_rest_field( 'frs_lead_page', 'hero_image', [
            'get_callback' => function( $post ) {
                $image_id = (int) get_post_meta( $post['id'], '_frs_hero_image_id', true );
                if ( $image_id ) {
                    $url = wp_get_attachment_image_url( $image_id, 'full' );
                    if ( $url ) {
                        return [
                            'id'  => $image_id,
                            'url' => $url,
                        ];
                    }
                }
                $url = get_post_meta( $post['id'], '_frs_hero_image_url', true );
                return [
                    'id'  => 0,
                    'url' => $url ?: '',
                ];
            },
            'schema'       => [
                'description' => 'Hero image details',
                'type'        => 'object',
                'context'     => [ 'view', 'edit' ],
                'properties'  => [
                    'id'  => [ 'type' => 'integer' ],
                    'url' => [ 'type' => 'string', 'format' => 'uri' ],
                ],
            ],
        ]);

        // Add parsed form questions (JSON decoded)
        register_rest_field( 'frs_lead_page', 'form_questions_parsed', [
            'get_callback' => function( $post ) {
                $json = get_post_meta( $post['id'], '_frs_form_questions', true );
                if ( $json ) {
                    $decoded = json_decode( $json, true );
                    return is_array( $decoded ) ? $decoded : [];
                }
                return [];
            },
            'schema'       => [
                'description' => 'Parsed form questions configuration',
                'type'        => 'array',
                'context'     => [ 'view', 'edit' ],
            ],
        ]);

        // Add leads/submissions for the page (requires permission)
        register_rest_field( 'frs_lead_page', 'leads', [
            'get_callback' => [ __CLASS__, 'get_leads_field' ],
            'schema'       => [
                'description' => 'Lead submissions for this page (requires view permission)',
                'type'        => 'object',
                'context'     => [ 'view', 'edit' ],
                'properties'  => [
                    'total'       => [ 'type' => 'integer' ],
                    'submissions' => [
                        'type'  => 'array',
                        'items' => [
                            'type'       => 'object',
                            'properties' => [
                                'id'         => [ 'type' => 'integer' ],
                                'first_name' => [ 'type' => 'string' ],
                                'last_name'  => [ 'type' => 'string' ],
                                'email'      => [ 'type' => 'string' ],
                                'phone'      => [ 'type' => 'string' ],
                                'status'     => [ 'type' => 'string' ],
                                'created_at' => [ 'type' => 'string' ],
                                'response'   => [ 'type' => 'object' ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * Get leads/submissions field for REST response
     *
     * Only returns data if user has permission to view submissions.
     *
     * @param array $post Post data.
     * @return array|null Leads data or null if no permission.
     */
    public static function get_leads_field( $post ): ?array {
        $post_id = $post['id'];

        // Check permission - user must be able to view submissions
        if ( ! self::can_view_page_leads( $post_id ) ) {
            return null;
        }

        // Get submissions from FluentForms integration
        if ( class_exists( '\FRSLeadPages\Integrations\FluentForms' ) ) {
            $result = \FRSLeadPages\Integrations\FluentForms::get_submissions_for_page( $post_id, [
                'per_page' => 100,
                'page'     => 1,
                'status'   => 'all',
            ]);

            if ( ! empty( $result['submissions'] ) ) {
                return $result;
            }
        }

        // Fallback: Check post meta for backup leads
        $backup_leads = get_post_meta( $post_id, '_frs_leads', true );
        if ( is_array( $backup_leads ) && ! empty( $backup_leads ) ) {
            return [
                'total'       => count( $backup_leads ),
                'submissions' => array_map( function( $lead, $index ) {
                    return [
                        'id'         => $index + 1,
                        'first_name' => $lead['name'] ?? '',
                        'last_name'  => '',
                        'email'      => $lead['email'] ?? '',
                        'phone'      => $lead['phone'] ?? '',
                        'status'     => 'unread',
                        'created_at' => $lead['created_at'] ?? '',
                        'response'   => $lead['data'] ?? [],
                    ];
                }, $backup_leads, array_keys( $backup_leads ) ),
            ];
        }

        return [
            'total'       => 0,
            'submissions' => [],
        ];
    }

    /**
     * Check if current user can view leads for a specific page
     *
     * Rules:
     * - Admins/editors can view all leads
     * - Loan officers can view leads for pages where they are assigned as the LO
     * - Realtor partners can only view leads for their own pages
     *
     * @param int $post_id Post ID.
     * @return bool True if user can view leads.
     */
    private static function can_view_page_leads( int $post_id ): bool {
        if ( ! is_user_logged_in() ) {
            return false;
        }

        $user_id = get_current_user_id();

        // Admins and editors can view all
        if ( current_user_can( 'manage_options' ) || current_user_can( 'edit_others_posts' ) ) {
            return true;
        }

        // Loan officers can view leads for pages where they are assigned
        $lo_id = (int) get_post_meta( $post_id, '_frs_loan_officer_id', true );
        if ( $lo_id === $user_id ) {
            return true;
        }

        // Realtor partners can only view leads for their own pages
        $realtor_id = (int) get_post_meta( $post_id, '_frs_realtor_id', true );
        if ( $realtor_id === $user_id ) {
            return true;
        }

        // Post author can view their own page's leads
        $post = get_post( $post_id );
        if ( $post && (int) $post->post_author === $user_id ) {
            return true;
        }

        return false;
    }

    /**
     * Get loan officer field for REST response
     *
     * @param array $post Post data.
     * @return array|null Loan officer data.
     */
    public static function get_loan_officer_field( $post ): ?array {
        $lo_id = (int) get_post_meta( $post['id'], '_frs_loan_officer_id', true );

        if ( ! $lo_id ) {
            return null;
        }

        $user = get_user_by( 'ID', $lo_id );

        if ( ! $user ) {
            return null;
        }

        return [
            'id'    => $user->ID,
            'name'  => $user->display_name,
            'email' => $user->user_email,
            'phone' => get_user_meta( $lo_id, 'phone', true ) ?: get_user_meta( $lo_id, 'phone_number', true ),
            'nmls'  => \FRSLeadPages\frs_get_user_nmls( $lo_id ),
            'title' => get_user_meta( $lo_id, 'job_title', true ) ?: 'Loan Officer',
            'photo' => \FRSLeadPages\get_user_photo( $lo_id ),
        ];
    }

    /**
     * Get realtor field for REST response
     *
     * @param array $post Post data.
     * @return array|null Realtor data.
     */
    public static function get_realtor_field( $post ): ?array {
        $realtor_id = (int) get_post_meta( $post['id'], '_frs_realtor_id', true );

        if ( ! $realtor_id ) {
            return null;
        }

        $user = get_user_by( 'ID', $realtor_id );

        if ( ! $user ) {
            return null;
        }

        return [
            'id'      => $user->ID,
            'name'    => $user->display_name,
            'email'   => $user->user_email,
            'phone'   => get_user_meta( $realtor_id, 'phone', true ) ?: get_user_meta( $realtor_id, 'phone_number', true ),
            'company' => get_user_meta( $realtor_id, 'company', true ) ?: get_user_meta( $realtor_id, 'brokerage', true ),
            'license' => get_user_meta( $realtor_id, 'license_number', true ) ?: get_user_meta( $realtor_id, 'dre_license', true ),
            'photo'   => \FRSLeadPages\get_user_photo( $realtor_id ),
        ];
    }
}
