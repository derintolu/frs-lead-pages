<?php
/**
 * FluentForms Wizard Forms for Generation Station
 *
 * Creates and manages multi-step wizard forms for creating lead pages.
 * Per SOP: Wizard should BE a FluentForm, not embed one.
 *
 * @package FRSLeadPages
 */

namespace FRSLeadPages\Integrations;

use FluentForm\App\Models\Form;
use FluentForm\App\Models\FormMeta;
use FRSLeadPages\Core\LoanOfficers;

class WizardForms {

    /**
     * Form type identifiers
     */
    const TYPE_OPEN_HOUSE = 'open_house';
    const TYPE_SPOTLIGHT  = 'customer_spotlight';
    const TYPE_EVENT      = 'special_event';

    /**
     * Option key for storing wizard form IDs
     */
    const OPTION_KEY = 'frs_lead_pages_wizard_form_ids';

    /**
     * Initialize the wizard forms
     */
    public static function init() {
        if ( ! FluentForms::is_active() ) {
            return;
        }

        // Hook into wizard form submissions
        add_action( 'fluentform/submission_inserted', [ __CLASS__, 'on_wizard_submission' ], 10, 3 );

        // Update LO dropdown options dynamically
        add_filter( 'fluentform/rendering_field_data_select', [ __CLASS__, 'populate_lo_dropdown' ], 10, 2 );
    }

    /**
     * Get wizard form IDs (create if needed)
     *
     * @return array Associative array of form type => form ID
     */
    public static function get_form_ids(): array {
        $form_ids = get_option( self::OPTION_KEY, [] );

        // Check if forms exist
        $types = [ self::TYPE_OPEN_HOUSE, self::TYPE_SPOTLIGHT, self::TYPE_EVENT ];
        $needs_update = false;

        foreach ( $types as $type ) {
            if ( empty( $form_ids[ $type ] ) ) {
                $form_id = self::create_wizard_form( $type );
                if ( $form_id ) {
                    $form_ids[ $type ] = $form_id;
                    $needs_update = true;
                }
            } else {
                // Verify form still exists
                $form = Form::find( $form_ids[ $type ] );
                if ( ! $form ) {
                    $form_id = self::create_wizard_form( $type );
                    if ( $form_id ) {
                        $form_ids[ $type ] = $form_id;
                        $needs_update = true;
                    }
                }
            }
        }

        if ( $needs_update ) {
            update_option( self::OPTION_KEY, $form_ids );
        }

        return $form_ids;
    }

    /**
     * Get form ID for a specific wizard type
     *
     * @param string $type Wizard type
     * @return int|null Form ID or null
     */
    public static function get_form_id( string $type ): ?int {
        $form_ids = self::get_form_ids();
        return $form_ids[ $type ] ?? null;
    }

    /**
     * Create a wizard form programmatically
     *
     * @param string $type Wizard type
     * @return int|null Form ID or null on failure
     */
    private static function create_wizard_form( string $type ): ?int {
        if ( ! FluentForms::is_active() ) {
            return null;
        }

        $title = self::get_form_title( $type );
        $fields = self::get_form_fields( $type );

        $form_data = [
            'title'               => $title,
            'status'              => 'published',
            'appearance_settings' => null,
            'form_fields'         => json_encode( $fields ),
            'has_payment'         => 0,
            'type'                => 'form',
            'conditions'          => null,
            'created_by'          => get_current_user_id(),
            'created_at'          => current_time( 'mysql' ),
            'updated_at'          => current_time( 'mysql' ),
        ];

        $form_id = Form::insertGetId( $form_data );

        if ( $form_id ) {
            self::add_form_settings( $form_id, $type );
            self::add_form_meta( $form_id, $type );
        }

        return $form_id;
    }

    /**
     * Get form title by type
     */
    private static function get_form_title( string $type ): string {
        $titles = [
            self::TYPE_OPEN_HOUSE => 'Generation Station - Open House Wizard',
            self::TYPE_SPOTLIGHT  => 'Generation Station - Customer Spotlight Wizard',
            self::TYPE_EVENT      => 'Generation Station - Special Event Wizard',
        ];
        return $titles[ $type ] ?? 'Generation Station Wizard';
    }

    /**
     * Get form fields configuration by type
     */
    private static function get_form_fields( string $type ): array {
        $common_hidden_fields = self::get_hidden_fields( $type );
        $lo_dropdown = self::get_lo_dropdown_field();

        switch ( $type ) {
            case self::TYPE_OPEN_HOUSE:
                return self::get_open_house_fields( $common_hidden_fields, $lo_dropdown );
            case self::TYPE_SPOTLIGHT:
                return self::get_spotlight_fields( $common_hidden_fields, $lo_dropdown );
            case self::TYPE_EVENT:
                return self::get_event_fields( $common_hidden_fields, $lo_dropdown );
            default:
                return [];
        }
    }

    /**
     * Get hidden fields common to all wizard types
     */
    private static function get_hidden_fields( string $type ): array {
        return [
            [
                'index'      => 100,
                'element'    => 'input_hidden',
                'attributes' => [ 'name' => 'realtor_id' ],
                'settings'   => [
                    'admin_field_label' => 'Realtor ID',
                    'default_value'     => '{get.realtor_id}',
                ],
            ],
            [
                'index'      => 101,
                'element'    => 'input_hidden',
                'attributes' => [ 'name' => 'realtor_name' ],
                'settings'   => [
                    'admin_field_label' => 'Realtor Name',
                    'default_value'     => '{get.realtor_name}',
                ],
            ],
            [
                'index'      => 102,
                'element'    => 'input_hidden',
                'attributes' => [ 'name' => 'realtor_email' ],
                'settings'   => [
                    'admin_field_label' => 'Realtor Email',
                    'default_value'     => '{get.realtor_email}',
                ],
            ],
            [
                'index'      => 103,
                'element'    => 'input_hidden',
                'attributes' => [ 'name' => 'realtor_phone' ],
                'settings'   => [
                    'admin_field_label' => 'Realtor Phone',
                    'default_value'     => '{get.realtor_phone}',
                ],
            ],
            [
                'index'      => 104,
                'element'    => 'input_hidden',
                'attributes' => [ 'name' => 'page_type', 'value' => $type ],
                'settings'   => [
                    'admin_field_label' => 'Page Type',
                    'default_value'     => $type,
                ],
            ],
        ];
    }

    /**
     * Get Loan Officer dropdown field
     */
    private static function get_lo_dropdown_field(): array {
        return [
            'index'      => 1,
            'element'    => 'select',
            'attributes' => [
                'name'        => 'loan_officer',
                'class'       => 'frs-lo-dropdown',
                'placeholder' => 'Select a Loan Officer',
            ],
            'settings'   => [
                'admin_field_label' => 'Loan Officer',
                'label'             => 'Select Your Loan Officer Partner',
                'help_message'      => 'Choose the loan officer to feature on this page',
                'container_class'   => '',
                'label_placement'   => '',
                'validation_rules'  => [
                    'required' => [
                        'value'   => true,
                        'message' => 'Please select a loan officer',
                    ],
                ],
                'advanced_options'  => [], // Will be populated dynamically
            ],
        ];
    }

    /**
     * Get Open House wizard fields (multi-step)
     */
    private static function get_open_house_fields( array $hidden_fields, array $lo_dropdown ): array {
        return [
            'fields' => array_merge(
                [
                    // Step 1: Property Details
                    [
                        'index'      => 0,
                        'element'    => 'form_step',
                        'attributes' => [ 'class' => '' ],
                        'settings'   => [
                            'step_title' => 'Property Details',
                            'container_class' => '',
                        ],
                    ],
                    [
                        'index'      => 2,
                        'element'    => 'input_text',
                        'attributes' => [
                            'name'        => 'property_address',
                            'type'        => 'text',
                            'placeholder' => '123 Main Street, City, State ZIP',
                        ],
                        'settings'   => [
                            'admin_field_label' => 'Property Address',
                            'label'             => 'Property Address',
                            'validation_rules'  => [
                                'required' => [
                                    'value'   => true,
                                    'message' => 'Property address is required',
                                ],
                            ],
                        ],
                    ],
                    [
                        'index'      => 3,
                        'element'    => 'input_number',
                        'attributes' => [
                            'name'        => 'listing_price',
                            'type'        => 'number',
                            'placeholder' => '500000',
                        ],
                        'settings'   => [
                            'admin_field_label' => 'Listing Price',
                            'label'             => 'Listing Price ($)',
                            'number_format'     => 'comma_dot',
                            'prefix_label'      => '$',
                            'validation_rules'  => [
                                'required' => [
                                    'value'   => true,
                                    'message' => 'Listing price is required',
                                ],
                            ],
                        ],
                    ],
                    [
                        'index'      => 4,
                        'element'    => 'input_number',
                        'attributes' => [
                            'name'        => 'bedrooms',
                            'type'        => 'number',
                            'placeholder' => '3',
                        ],
                        'settings'   => [
                            'admin_field_label' => 'Bedrooms',
                            'label'             => 'Bedrooms',
                        ],
                    ],
                    [
                        'index'      => 5,
                        'element'    => 'input_number',
                        'attributes' => [
                            'name'        => 'bathrooms',
                            'type'        => 'number',
                            'placeholder' => '2',
                            'step'        => '0.5',
                        ],
                        'settings'   => [
                            'admin_field_label' => 'Bathrooms',
                            'label'             => 'Bathrooms',
                        ],
                    ],
                    [
                        'index'      => 6,
                        'element'    => 'input_number',
                        'attributes' => [
                            'name'        => 'sqft',
                            'type'        => 'number',
                            'placeholder' => '2000',
                        ],
                        'settings'   => [
                            'admin_field_label' => 'Square Feet',
                            'label'             => 'Square Feet',
                        ],
                    ],

                    // Step 2: Customize Page
                    [
                        'index'      => 10,
                        'element'    => 'form_step',
                        'attributes' => [ 'class' => '' ],
                        'settings'   => [
                            'step_title' => 'Customize Your Page',
                            'container_class' => '',
                        ],
                    ],
                    [
                        'index'      => 11,
                        'element'    => 'input_text',
                        'attributes' => [
                            'name'        => 'headline',
                            'type'        => 'text',
                            'placeholder' => 'Welcome to Your Dream Home!',
                        ],
                        'settings'   => [
                            'admin_field_label' => 'Headline',
                            'label'             => 'Page Headline',
                            'help_message'      => 'The main headline visitors will see',
                            'validation_rules'  => [
                                'required' => [
                                    'value'   => true,
                                    'message' => 'Headline is required',
                                ],
                            ],
                        ],
                    ],
                    [
                        'index'      => 12,
                        'element'    => 'input_text',
                        'attributes' => [
                            'name'        => 'subheadline',
                            'type'        => 'text',
                            'placeholder' => 'Schedule a private tour today',
                        ],
                        'settings'   => [
                            'admin_field_label' => 'Subheadline',
                            'label'             => 'Subheadline (Optional)',
                        ],
                    ],
                    [
                        'index'      => 13,
                        'element'    => 'input_image',
                        'attributes' => [
                            'name' => 'hero_image',
                        ],
                        'settings'   => [
                            'admin_field_label' => 'Hero Image',
                            'label'             => 'Hero Image',
                            'help_message'      => 'Upload a beautiful photo of the property',
                            'btn_text'          => 'Upload Property Image',
                            'max_file_size'     => [
                                'value' => 5,
                                'unit'  => 'MB',
                            ],
                            'max_file_count'    => 1,
                            'allowed_image_types' => [ 'jpg', 'jpeg', 'png', 'webp' ],
                        ],
                    ],

                    // Step 3: Loan Officer & Finish
                    [
                        'index'      => 20,
                        'element'    => 'form_step',
                        'attributes' => [ 'class' => '' ],
                        'settings'   => [
                            'step_title' => 'Loan Officer Partner',
                            'container_class' => '',
                        ],
                    ],
                    $lo_dropdown,
                    [
                        'index'      => 22,
                        'element'    => 'input_checkbox',
                        'attributes' => [
                            'name' => 'enabled_questions',
                        ],
                        'settings'   => [
                            'admin_field_label' => 'Lead Form Questions',
                            'label'             => 'Select questions for your lead form',
                            'advanced_options'  => [
                                [ 'label' => 'Working with an agent?', 'value' => 'working_with_agent' ],
                                [ 'label' => 'Pre-approved?', 'value' => 'pre_approved' ],
                                [ 'label' => 'Interested in pre-approval?', 'value' => 'interested_in_preapproval' ],
                                [ 'label' => 'Buying timeline', 'value' => 'timeframe' ],
                            ],
                        ],
                    ],
                ],
                $hidden_fields
            ),
            'submitButton' => [
                'element'    => 'button',
                'attributes' => [
                    'type'  => 'submit',
                    'class' => '',
                ],
                'settings'   => [
                    'button_style'    => 'default',
                    'button_size'     => 'md',
                    'align'           => 'center',
                    'container_class' => '',
                    'background_color' => '#0ea5e9',
                    'color'           => '#ffffff',
                    'button_ui'       => [
                        'type' => 'default',
                        'text' => 'Create My Landing Page',
                    ],
                ],
            ],
            'stepsWrapper' => [
                'stepTitles' => [
                    'Property Details',
                    'Customize Your Page',
                    'Loan Officer Partner',
                ],
                'stepStart'    => 0,
                'stepEnd'      => 2,
                'progressbar'  => true,
                'step_animation' => 'slide',
            ],
        ];
    }

    /**
     * Get Customer Spotlight wizard fields (multi-step)
     */
    private static function get_spotlight_fields( array $hidden_fields, array $lo_dropdown ): array {
        return [
            'fields' => array_merge(
                [
                    // Step 1: Spotlight Type
                    [
                        'index'      => 0,
                        'element'    => 'form_step',
                        'attributes' => [ 'class' => '' ],
                        'settings'   => [
                            'step_title' => 'Spotlight Type',
                            'container_class' => '',
                        ],
                    ],
                    [
                        'index'      => 2,
                        'element'    => 'select',
                        'attributes' => [
                            'name' => 'spotlight_type',
                        ],
                        'settings'   => [
                            'admin_field_label' => 'Spotlight Type',
                            'label'             => 'Who is this spotlight for?',
                            'validation_rules'  => [
                                'required' => [
                                    'value'   => true,
                                    'message' => 'Please select a spotlight type',
                                ],
                            ],
                            'advanced_options'  => [
                                [ 'label' => 'First-Time Home Buyer', 'value' => 'first_time_buyer' ],
                                [ 'label' => 'Move-Up Buyer', 'value' => 'move_up_buyer' ],
                                [ 'label' => 'Downsizer', 'value' => 'downsizer' ],
                                [ 'label' => 'Investor', 'value' => 'investor' ],
                                [ 'label' => 'Relocating', 'value' => 'relocating' ],
                            ],
                        ],
                    ],
                    [
                        'index'      => 3,
                        'element'    => 'input_text',
                        'attributes' => [
                            'name'        => 'customer_name',
                            'type'        => 'text',
                            'placeholder' => 'John & Jane Smith',
                        ],
                        'settings'   => [
                            'admin_field_label' => 'Customer Name',
                            'label'             => 'Customer Name (Optional)',
                            'help_message'      => 'Leave blank to use generic template',
                        ],
                    ],

                    // Step 2: Customize
                    [
                        'index'      => 10,
                        'element'    => 'form_step',
                        'attributes' => [ 'class' => '' ],
                        'settings'   => [
                            'step_title' => 'Customize Your Page',
                            'container_class' => '',
                        ],
                    ],
                    [
                        'index'      => 11,
                        'element'    => 'input_text',
                        'attributes' => [
                            'name'        => 'headline',
                            'type'        => 'text',
                            'placeholder' => 'Ready to Find Your Perfect Home?',
                        ],
                        'settings'   => [
                            'admin_field_label' => 'Headline',
                            'label'             => 'Page Headline',
                            'validation_rules'  => [
                                'required' => [
                                    'value'   => true,
                                    'message' => 'Headline is required',
                                ],
                            ],
                        ],
                    ],
                    [
                        'index'      => 12,
                        'element'    => 'textarea',
                        'attributes' => [
                            'name'        => 'value_props',
                            'rows'        => 4,
                            'placeholder' => 'Enter each value proposition on a new line',
                        ],
                        'settings'   => [
                            'admin_field_label' => 'Value Propositions',
                            'label'             => 'Value Propositions',
                            'help_message'      => 'One per line (e.g., "Expert market knowledge", "Personalized service")',
                        ],
                    ],
                    [
                        'index'      => 13,
                        'element'    => 'input_image',
                        'attributes' => [
                            'name' => 'hero_image',
                        ],
                        'settings'   => [
                            'admin_field_label' => 'Hero Image',
                            'label'             => 'Hero Image',
                            'help_message'      => 'A lifestyle image that represents your target audience',
                            'btn_text'          => 'Upload Image',
                            'max_file_size'     => [ 'value' => 5, 'unit' => 'MB' ],
                            'max_file_count'    => 1,
                            'allowed_image_types' => [ 'jpg', 'jpeg', 'png', 'webp' ],
                        ],
                    ],

                    // Step 3: Loan Officer
                    [
                        'index'      => 20,
                        'element'    => 'form_step',
                        'attributes' => [ 'class' => '' ],
                        'settings'   => [
                            'step_title' => 'Loan Officer Partner',
                            'container_class' => '',
                        ],
                    ],
                    $lo_dropdown,
                    [
                        'index'      => 22,
                        'element'    => 'input_checkbox',
                        'attributes' => [
                            'name' => 'enabled_questions',
                        ],
                        'settings'   => [
                            'admin_field_label' => 'Lead Form Questions',
                            'label'             => 'Select questions for your lead form',
                            'advanced_options'  => [
                                [ 'label' => 'Working with an agent?', 'value' => 'working_with_agent' ],
                                [ 'label' => 'Pre-approved?', 'value' => 'pre_approved' ],
                                [ 'label' => 'Interested in pre-approval?', 'value' => 'interested_in_preapproval' ],
                                [ 'label' => 'Buying timeline', 'value' => 'timeframe' ],
                            ],
                        ],
                    ],
                ],
                $hidden_fields
            ),
            'submitButton' => [
                'element'    => 'button',
                'attributes' => [
                    'type'  => 'submit',
                    'class' => '',
                ],
                'settings'   => [
                    'button_style'    => 'default',
                    'button_size'     => 'md',
                    'align'           => 'center',
                    'background_color' => '#0ea5e9',
                    'color'           => '#ffffff',
                    'button_ui'       => [
                        'type' => 'default',
                        'text' => 'Create My Landing Page',
                    ],
                ],
            ],
            'stepsWrapper' => [
                'stepTitles' => [
                    'Spotlight Type',
                    'Customize Your Page',
                    'Loan Officer Partner',
                ],
                'stepStart'    => 0,
                'stepEnd'      => 2,
                'progressbar'  => true,
                'step_animation' => 'slide',
            ],
        ];
    }

    /**
     * Get Special Event wizard fields (multi-step)
     */
    private static function get_event_fields( array $hidden_fields, array $lo_dropdown ): array {
        return [
            'fields' => array_merge(
                [
                    // Step 1: Event Type
                    [
                        'index'      => 0,
                        'element'    => 'form_step',
                        'attributes' => [ 'class' => '' ],
                        'settings'   => [
                            'step_title' => 'Event Details',
                            'container_class' => '',
                        ],
                    ],
                    [
                        'index'      => 2,
                        'element'    => 'select',
                        'attributes' => [
                            'name' => 'event_type',
                        ],
                        'settings'   => [
                            'admin_field_label' => 'Event Type',
                            'label'             => 'What type of event?',
                            'validation_rules'  => [
                                'required' => [
                                    'value'   => true,
                                    'message' => 'Please select an event type',
                                ],
                            ],
                            'advanced_options'  => [
                                [ 'label' => 'First-Time Buyer Seminar', 'value' => 'first_time_seminar' ],
                                [ 'label' => 'Homebuyer Workshop', 'value' => 'homebuyer_workshop' ],
                                [ 'label' => 'Investment Property Seminar', 'value' => 'investment_seminar' ],
                                [ 'label' => 'Community Event', 'value' => 'community_event' ],
                                [ 'label' => 'Other', 'value' => 'other' ],
                            ],
                        ],
                    ],
                    [
                        'index'      => 3,
                        'element'    => 'input_text',
                        'attributes' => [
                            'name'        => 'event_title',
                            'type'        => 'text',
                            'placeholder' => 'First-Time Buyer Success Workshop',
                        ],
                        'settings'   => [
                            'admin_field_label' => 'Event Title',
                            'label'             => 'Event Title',
                            'validation_rules'  => [
                                'required' => [
                                    'value'   => true,
                                    'message' => 'Event title is required',
                                ],
                            ],
                        ],
                    ],
                    [
                        'index'      => 4,
                        'element'    => 'input_date',
                        'attributes' => [
                            'name' => 'event_date',
                        ],
                        'settings'   => [
                            'admin_field_label' => 'Event Date',
                            'label'             => 'Event Date',
                            'date_format'       => 'm/d/Y',
                            'validation_rules'  => [
                                'required' => [
                                    'value'   => true,
                                    'message' => 'Event date is required',
                                ],
                            ],
                        ],
                    ],
                    [
                        'index'      => 5,
                        'element'    => 'input_text',
                        'attributes' => [
                            'name'        => 'event_time',
                            'type'        => 'text',
                            'placeholder' => '10:00 AM - 12:00 PM',
                        ],
                        'settings'   => [
                            'admin_field_label' => 'Event Time',
                            'label'             => 'Event Time',
                        ],
                    ],
                    [
                        'index'      => 6,
                        'element'    => 'input_text',
                        'attributes' => [
                            'name'        => 'event_location',
                            'type'        => 'text',
                            'placeholder' => 'Community Center, 123 Main St',
                        ],
                        'settings'   => [
                            'admin_field_label' => 'Event Location',
                            'label'             => 'Event Location',
                        ],
                    ],

                    // Step 2: Customize
                    [
                        'index'      => 10,
                        'element'    => 'form_step',
                        'attributes' => [ 'class' => '' ],
                        'settings'   => [
                            'step_title' => 'Customize Your Page',
                            'container_class' => '',
                        ],
                    ],
                    [
                        'index'      => 11,
                        'element'    => 'input_text',
                        'attributes' => [
                            'name'        => 'headline',
                            'type'        => 'text',
                            'placeholder' => 'Join Us for a Free Homebuyer Seminar!',
                        ],
                        'settings'   => [
                            'admin_field_label' => 'Headline',
                            'label'             => 'Page Headline',
                            'validation_rules'  => [
                                'required' => [
                                    'value'   => true,
                                    'message' => 'Headline is required',
                                ],
                            ],
                        ],
                    ],
                    [
                        'index'      => 12,
                        'element'    => 'textarea',
                        'attributes' => [
                            'name'        => 'event_description',
                            'rows'        => 4,
                            'placeholder' => 'Describe what attendees will learn...',
                        ],
                        'settings'   => [
                            'admin_field_label' => 'Event Description',
                            'label'             => 'Event Description',
                        ],
                    ],
                    [
                        'index'      => 13,
                        'element'    => 'input_image',
                        'attributes' => [
                            'name' => 'hero_image',
                        ],
                        'settings'   => [
                            'admin_field_label' => 'Hero Image',
                            'label'             => 'Event Banner Image',
                            'btn_text'          => 'Upload Image',
                            'max_file_size'     => [ 'value' => 5, 'unit' => 'MB' ],
                            'max_file_count'    => 1,
                            'allowed_image_types' => [ 'jpg', 'jpeg', 'png', 'webp' ],
                        ],
                    ],

                    // Step 3: Loan Officer
                    [
                        'index'      => 20,
                        'element'    => 'form_step',
                        'attributes' => [ 'class' => '' ],
                        'settings'   => [
                            'step_title' => 'Loan Officer Partner',
                            'container_class' => '',
                        ],
                    ],
                    $lo_dropdown,
                ],
                $hidden_fields
            ),
            'submitButton' => [
                'element'    => 'button',
                'attributes' => [
                    'type'  => 'submit',
                    'class' => '',
                ],
                'settings'   => [
                    'button_style'    => 'default',
                    'button_size'     => 'md',
                    'align'           => 'center',
                    'background_color' => '#0ea5e9',
                    'color'           => '#ffffff',
                    'button_ui'       => [
                        'type' => 'default',
                        'text' => 'Create My Event Page',
                    ],
                ],
            ],
            'stepsWrapper' => [
                'stepTitles' => [
                    'Event Details',
                    'Customize Your Page',
                    'Loan Officer Partner',
                ],
                'stepStart'    => 0,
                'stepEnd'      => 2,
                'progressbar'  => true,
                'step_animation' => 'slide',
            ],
        ];
    }

    /**
     * Add form settings
     */
    private static function add_form_settings( int $form_id, string $type ): void {
        $settings = [
            'confirmation' => [
                'redirectTo'           => 'customUrl',
                'messageToShow'        => 'Your landing page is being created...',
                'customPage'           => null,
                'samePageFormBehavior' => 'hide_form',
                'customUrl'            => '', // Will be set dynamically after page creation
            ],
            'restrictions' => [
                'limitNumberOfEntries' => [ 'enabled' => false ],
                'scheduleForm'         => [ 'enabled' => false ],
                'requireLogin'         => [
                    'enabled'          => true,
                    'requireLoginMsg'  => 'Please log in to create a landing page.',
                    'allowedRoles'     => [ 'realtor_partner', 'administrator', 'editor' ],
                ],
                'denyEmptySubmission'  => [ 'enabled' => false ],
            ],
            'layout' => [
                'labelPlacement'       => 'top',
                'helpMessagePlacement' => 'with_label',
                'asteriskPlacement'    => 'asterisk-right',
            ],
        ];

        FormMeta::insert([
            'form_id'  => $form_id,
            'meta_key' => 'formSettings',
            'value'    => json_encode( $settings ),
        ]);
    }

    /**
     * Add form meta (custom identifier)
     */
    private static function add_form_meta( int $form_id, string $type ): void {
        FormMeta::insert([
            'form_id'  => $form_id,
            'meta_key' => '_frs_wizard_type',
            'value'    => $type,
        ]);

        FormMeta::insert([
            'form_id'  => $form_id,
            'meta_key' => '_frs_is_wizard',
            'value'    => '1',
        ]);
    }

    /**
     * Populate LO dropdown dynamically
     *
     * @param array $data    Field data
     * @param \stdClass $form Form object
     * @return array Modified field data
     */
    public static function populate_lo_dropdown( array $data, $form ): array {
        // Check if this is our LO dropdown
        if ( ! isset( $data['attributes']['class'] ) || strpos( $data['attributes']['class'], 'frs-lo-dropdown' ) === false ) {
            return $data;
        }

        // Get LO options
        $lo_options = LoanOfficers::get_dropdown_options();

        // Format for FluentForms
        $data['settings']['advanced_options'] = array_values( $lo_options );

        return $data;
    }

    /**
     * Handle wizard form submission - create landing page
     *
     * @param int   $submission_id Submission ID
     * @param array $form_data     Form data
     * @param \stdClass $form      Form object
     */
    public static function on_wizard_submission( int $submission_id, array $form_data, $form ): void {
        // Check if this is a wizard form
        $wizard_form_ids = self::get_form_ids();

        if ( ! in_array( (int) $form->id, array_values( $wizard_form_ids ), true ) ) {
            return;
        }

        // Get page type from form data
        $page_type = $form_data['page_type'] ?? '';

        if ( empty( $page_type ) ) {
            // Try to determine from form meta
            $form_meta = FormMeta::where( 'form_id', $form->id )
                ->where( 'meta_key', '_frs_wizard_type' )
                ->first();

            $page_type = $form_meta ? $form_meta->value : self::TYPE_OPEN_HOUSE;
        }

        // Create landing page
        $result = self::create_landing_page( $form_data, $page_type, $submission_id );

        // Store result in submission meta for confirmation
        if ( function_exists( 'wpFluent' ) ) {
            wpFluent()->table( 'fluentform_submission_meta' )->insert([
                'response_id' => $submission_id,
                'form_id'     => $form->id,
                'meta_key'    => '_frs_landing_page_result',
                'value'       => json_encode( $result ),
                'created_at'  => current_time( 'mysql' ),
                'updated_at'  => current_time( 'mysql' ),
            ]);
        }

        // Redirect to success page with page URL
        if ( $result['success'] && ! empty( $result['page_url'] ) ) {
            // Add success message and redirect URL to form response
            add_filter( 'fluentform/submission_confirmation', function( $confirmation ) use ( $result ) {
                $confirmation['redirectUrl'] = add_query_arg([
                    'created'  => '1',
                    'page_url' => urlencode( $result['page_url'] ),
                ], home_url( '/my-lead-pages/' ) );
                $confirmation['redirectTo'] = 'customUrl';
                return $confirmation;
            }, 10 );
        }
    }

    /**
     * Create landing page from wizard submission
     *
     * @param array  $form_data     Form submission data
     * @param string $page_type     Page type
     * @param int    $submission_id FluentForms submission ID
     * @return array Result with success status and page info
     */
    private static function create_landing_page( array $form_data, string $page_type, int $submission_id ): array {
        // Generate title based on page type
        $title = self::generate_page_title( $form_data, $page_type );

        // Create the landing page CPT
        $page_id = wp_insert_post([
            'post_type'   => 'frs_lead_page',
            'post_title'  => $title,
            'post_status' => 'publish',
            'post_author' => get_current_user_id(),
        ]);

        if ( is_wp_error( $page_id ) ) {
            return [
                'success' => false,
                'error'   => $page_id->get_error_message(),
            ];
        }

        // Store page type
        update_post_meta( $page_id, '_frs_page_type', $page_type );

        // Store all form data as serialized meta
        update_post_meta( $page_id, '_frs_wizard_data', $form_data );
        update_post_meta( $page_id, '_frs_wizard_submission_id', $submission_id );

        // Store individual fields for querying
        $meta_mapping = [
            // Common fields
            'realtor_id'      => '_frs_realtor_id',
            'realtor_name'    => '_frs_realtor_name',
            'realtor_email'   => '_frs_realtor_email',
            'realtor_phone'   => '_frs_realtor_phone',
            'loan_officer'    => '_frs_loan_officer_id',
            'headline'        => '_frs_headline',
            'subheadline'     => '_frs_subheadline',
            'enabled_questions' => '_frs_enabled_questions',

            // Open House fields
            'property_address' => '_frs_property_address',
            'listing_price'    => '_frs_property_price',
            'bedrooms'         => '_frs_property_beds',
            'bathrooms'        => '_frs_property_baths',
            'sqft'             => '_frs_property_sqft',

            // Spotlight fields
            'spotlight_type'   => '_frs_spotlight_type',
            'customer_name'    => '_frs_customer_name',
            'value_props'      => '_frs_value_props',

            // Event fields
            'event_type'       => '_frs_event_type',
            'event_title'      => '_frs_event_title',
            'event_date'       => '_frs_event_date',
            'event_time'       => '_frs_event_time',
            'event_location'   => '_frs_event_location',
            'event_description'=> '_frs_event_description',
        ];

        foreach ( $meta_mapping as $form_field => $meta_key ) {
            if ( isset( $form_data[ $form_field ] ) && ! empty( $form_data[ $form_field ] ) ) {
                update_post_meta( $page_id, $meta_key, $form_data[ $form_field ] );
            }
        }

        // Handle hero image upload
        if ( ! empty( $form_data['hero_image'] ) ) {
            // FluentForms stores uploaded files as URLs or attachment IDs
            $hero_image = $form_data['hero_image'];

            if ( is_array( $hero_image ) && ! empty( $hero_image[0] ) ) {
                $hero_image = $hero_image[0];
            }

            if ( is_numeric( $hero_image ) ) {
                update_post_meta( $page_id, '_frs_hero_image_id', absint( $hero_image ) );
            } else {
                update_post_meta( $page_id, '_frs_hero_image_url', esc_url_raw( $hero_image ) );
            }
        }

        // Initialize counters
        update_post_meta( $page_id, '_frs_page_views', 0 );
        update_post_meta( $page_id, '_frs_page_submissions', 0 );

        // Generate QR code for Open House
        $qr_code = null;
        if ( $page_type === self::TYPE_OPEN_HOUSE ) {
            $qr_code = self::generate_qr_code( $page_id );
            if ( $qr_code ) {
                update_post_meta( $page_id, '_frs_qr_code', $qr_code );
            }
        }

        return [
            'success'  => true,
            'page_id'  => $page_id,
            'page_url' => get_permalink( $page_id ),
            'qr_code'  => $qr_code,
        ];
    }

    /**
     * Generate page title based on type and data
     */
    private static function generate_page_title( array $form_data, string $page_type ): string {
        switch ( $page_type ) {
            case self::TYPE_OPEN_HOUSE:
                $address = $form_data['property_address'] ?? 'Property';
                // Extract just street address
                $address_parts = explode( ',', $address );
                return 'Open House: ' . trim( $address_parts[0] );

            case self::TYPE_SPOTLIGHT:
                $spotlight_type = $form_data['spotlight_type'] ?? 'buyer';
                $type_labels = [
                    'first_time_buyer' => 'First-Time Buyer',
                    'move_up_buyer'    => 'Move-Up Buyer',
                    'downsizer'        => 'Downsizer',
                    'investor'         => 'Investor',
                    'relocating'       => 'Relocating',
                ];
                $type_label = $type_labels[ $spotlight_type ] ?? ucwords( str_replace( '_', ' ', $spotlight_type ) );

                if ( ! empty( $form_data['customer_name'] ) ) {
                    return $form_data['customer_name'] . ' - ' . $type_label . ' Spotlight';
                }
                return $type_label . ' Spotlight';

            case self::TYPE_EVENT:
                return $form_data['event_title'] ?? 'Special Event';

            default:
                return 'Landing Page';
        }
    }

    /**
     * Generate QR code for landing page
     */
    private static function generate_qr_code( int $page_id ): ?string {
        $url = add_query_arg( 'scan', '1', get_permalink( $page_id ) );

        // Use QRCode class if available
        if ( class_exists( '\FRSLeadPages\Core\QRCode' ) ) {
            return \FRSLeadPages\Core\QRCode::generate( $url, $page_id );
        }

        // Fallback to Google Charts API
        $qr_api_url = 'https://chart.googleapis.com/chart?' . http_build_query([
            'chs' => '300x300',
            'cht' => 'qr',
            'chl' => $url,
            'choe' => 'UTF-8',
        ]);

        return $qr_api_url;
    }

    /**
     * Delete wizard forms (for cleanup/reset)
     */
    public static function delete_forms(): void {
        $form_ids = get_option( self::OPTION_KEY, [] );

        foreach ( $form_ids as $form_id ) {
            if ( $form_id && function_exists( 'wpFluent' ) ) {
                Form::where( 'id', $form_id )->delete();
                FormMeta::where( 'form_id', $form_id )->delete();
            }
        }

        delete_option( self::OPTION_KEY );
    }
}
