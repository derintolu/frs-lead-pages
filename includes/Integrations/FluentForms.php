<?php
/**
 * FluentForms Integration for FRS Lead Pages
 *
 * Programmatically create and submit to FluentForms
 *
 * @package FRSLeadPages
 */

namespace FRSLeadPages\Integrations;

use FluentForm\App\Models\Form;
use FluentForm\App\Models\Submission;
use FluentForm\App\Models\FormMeta;
use FluentForm\App\Helpers\Helper;
use FluentForm\App\Services\Submission\SubmissionService;

class FluentForms {

    /**
     * Form IDs cache by page type
     */
    private static array $form_ids = [];

    /**
     * Page type labels for form titles
     */
    const PAGE_TYPE_LABELS = [
        'open_house'          => 'Open House',
        'customer_spotlight'  => 'Customer Spotlight',
        'special_event'       => 'Special Event',
        'mortgage_calculator' => 'Mortgage Calculator',
    ];

    /**
     * Initialize the integration
     */
    public static function init() {
        // Ensure FluentForms is active
        if ( ! self::is_active() ) {
            return;
        }

        // Register hooks
        add_action( 'fluentform/submission_inserted', [ __CLASS__, 'on_submission' ], 10, 3 );
    }

    /**
     * Check if FluentForms is active
     */
    public static function is_active(): bool {
        return defined( 'FLUENTFORM' ) && function_exists( 'wpFluent' );
    }

    /**
     * Get form ID for a specific page type from saved mapping
     *
     * @param string $page_type The page type (open_house, customer_spotlight, etc.)
     * @return int|null Form ID or null if not configured
     */
    public static function get_form_id_for_type( string $page_type ): ?int {
        // Validate page type
        if ( ! isset( self::PAGE_TYPE_LABELS[ $page_type ] ) ) {
            $page_type = 'open_house'; // Default fallback
        }

        // Check cache
        if ( isset( self::$form_ids[ $page_type ] ) ) {
            return self::$form_ids[ $page_type ];
        }

        if ( ! self::is_active() ) {
            return null;
        }

        // Get form mapping from options
        $form_mapping = get_option( 'frs_lead_pages_form_mapping', [] );

        if ( ! empty( $form_mapping[ $page_type ] ) ) {
            self::$form_ids[ $page_type ] = (int) $form_mapping[ $page_type ];
            return self::$form_ids[ $page_type ];
        }

        // Fallback: Try to find form by title
        $form_title = 'Lead Page - ' . self::PAGE_TYPE_LABELS[ $page_type ];
        $form = Form::where( 'title', $form_title )->first();

        if ( $form ) {
            self::$form_ids[ $page_type ] = $form->id;
            return $form->id;
        }

        return null;
    }

    /**
     * Get or create the lead page form (legacy - defaults to open_house)
     */
    public static function get_form_id(): ?int {
        return self::get_form_id_for_type( 'open_house' );
    }

    /**
     * Get all lead page form IDs (for all page types)
     *
     * @return array Array of form IDs
     */
    public static function get_all_lead_page_form_ids(): array {
        if ( ! self::is_active() ) {
            return [];
        }

        $form_ids = [];

        // Get form IDs for each page type
        foreach ( array_keys( self::PAGE_TYPE_LABELS ) as $page_type ) {
            $form_id = self::get_form_id_for_type( $page_type );
            if ( $form_id ) {
                $form_ids[] = $form_id;
            }
        }

        // Also get any forms with "Lead Page" in title (catches custom forms)
        $additional_forms = Form::where( 'title', 'LIKE', '%Lead Page%' )
            ->orWhere( 'title', 'LIKE', '%lead_page%' )
            ->pluck( 'id' )
            ->toArray();

        $form_ids = array_unique( array_merge( $form_ids, $additional_forms ) );

        return $form_ids;
    }

    /**
     * Create a FluentForm for a specific page type
     *
     * @param string $page_type  The page type
     * @param string $form_title The form title
     * @return int|null Form ID or null on failure
     */
    private static function create_form_for_type( string $page_type, string $form_title ): ?int {
        if ( ! self::is_active() ) {
            return null;
        }

        $form_fields = self::get_form_fields_for_type( $page_type );

        $form_data = [
            'title'             => $form_title,
            'status'            => 'published',
            'appearance_settings' => null,
            'form_fields'       => json_encode( $form_fields ),
            'has_payment'       => 0,
            'type'              => 'form',
            'conditions'        => null,
            'created_by'        => get_current_user_id(),
            'created_at'        => current_time( 'mysql' ),
            'updated_at'        => current_time( 'mysql' ),
        ];

        $form_id = Form::insertGetId( $form_data );

        if ( $form_id ) {
            // Add form settings
            self::add_form_settings( $form_id, $page_type );

            // Register form for tracking
            self::register_form( $form_id, $page_type );
        }

        return $form_id;
    }

    /**
     * Get form field configuration - Multi-step form
     */
    private static function get_form_fields(): array {
        return [
            'fields' => [
                // Step 1: Contact Info
                [
                    'index'      => 0,
                    'element'    => 'input_name',
                    'attributes' => [
                        'name'        => 'names',
                        'data-type'   => 'name-element',
                    ],
                    'settings'   => [
                        'container_class' => '',
                        'admin_field_label' => 'Full Name',
                        'label' => 'Full Name',
                        'label_placement' => '',
                        'validation_rules' => [
                            'required' => [
                                'value'   => true,
                                'message' => 'This field is required',
                            ],
                        ],
                        'conditional_logics' => [],
                    ],
                    'fields'     => [
                        'first_name' => [
                            'element'    => 'input_text',
                            'attributes' => [
                                'type'        => 'text',
                                'name'        => 'first_name',
                                'placeholder' => 'First Name',
                            ],
                            'settings'   => [
                                'label' => 'First Name',
                                'visible' => true,
                                'validation_rules' => [
                                    'required' => [
                                        'value' => true,
                                        'message' => 'This field is required',
                                    ],
                                ],
                            ],
                            'editor_options' => [ 'template' => 'inputText' ],
                        ],
                        'last_name'  => [
                            'element'    => 'input_text',
                            'attributes' => [
                                'type'        => 'text',
                                'name'        => 'last_name',
                                'placeholder' => 'Last Name',
                            ],
                            'settings'   => [
                                'label' => 'Last Name',
                                'visible' => true,
                                'validation_rules' => [
                                    'required' => [
                                        'value' => true,
                                        'message' => 'This field is required',
                                    ],
                                ],
                            ],
                            'editor_options' => [ 'template' => 'inputText' ],
                        ],
                    ],
                    'editor_options' => [
                        'title' => 'Name Fields',
                        'element' => 'name-fields',
                        'icon_class' => 'icon-user',
                        'template' => 'nameFields',
                    ],
                ],
                [
                    'index'      => 1,
                    'element'    => 'input_email',
                    'attributes' => [
                        'name'        => 'email',
                        'type'        => 'email',
                        'placeholder' => 'Email Address',
                    ],
                    'settings'   => [
                        'admin_field_label' => 'Email',
                        'label' => 'Email',
                        'validation_rules' => [
                            'required' => [
                                'value'   => true,
                                'message' => 'This field is required',
                            ],
                            'email' => [
                                'value'   => true,
                                'message' => 'Please enter a valid email address',
                            ],
                        ],
                        'conditional_logics' => [],
                    ],
                    'editor_options' => [
                        'title' => 'Email Address',
                        'icon_class' => 'icon-envelope-o',
                        'template' => 'inputText',
                    ],
                ],
                [
                    'index'      => 2,
                    'element'    => 'phone',
                    'attributes' => [
                        'name'        => 'phone',
                        'type'        => 'tel',
                        'placeholder' => 'Phone Number',
                    ],
                    'settings'   => [
                        'admin_field_label' => 'Phone',
                        'label' => 'Phone',
                        'validation_rules' => [
                            'required' => [
                                'value'   => true,
                                'message' => 'This field is required',
                            ],
                        ],
                        'conditional_logics' => [],
                    ],
                    'editor_options' => [
                        'title' => 'Phone',
                        'icon_class' => 'icon-phone',
                        'template' => 'inputText',
                    ],
                ],
                // Form Step Break (Step 1 -> Step 2)
                [
                    'index'      => 3,
                    'element'    => 'form_step',
                    'attributes' => [
                        'id'    => '',
                        'class' => '',
                    ],
                    'settings'   => [
                        'prev_btn' => [
                            'type' => 'default',
                            'text' => 'Previous',
                            'img_url' => '',
                        ],
                        'next_btn' => [
                            'type' => 'default',
                            'text' => 'Continue',
                            'img_url' => '',
                        ],
                    ],
                    'editor_options' => [
                        'title' => 'Form Step',
                        'icon_class' => 'icon-step-forward',
                        'template' => 'formStep',
                    ],
                ],
                // Step 2: Qualifying Questions
                [
                    'index'      => 4,
                    'element'    => 'input_radio',
                    'attributes' => [
                        'type' => 'radio',
                        'name' => 'working_with_agent',
                        'value' => '',
                    ],
                    'settings'   => [
                        'admin_field_label' => 'Working With Agent',
                        'label' => 'Are you working with an agent?',
                        'container_class' => '',
                        'label_placement' => '',
                        'display_type' => '',
                        'validation_rules' => [
                            'required' => [
                                'value' => false,
                                'message' => 'This field is required',
                            ],
                        ],
                        'conditional_logics' => [],
                    ],
                    'options' => [
                        'Yes' => 'Yes',
                        'No' => 'No',
                    ],
                    'editor_options' => [
                        'title' => 'Radio Button',
                        'icon_class' => 'icon-dot-circle-o',
                        'element' => 'input-radio',
                        'template' => 'inputRadio',
                    ],
                ],
                [
                    'index'      => 5,
                    'element'    => 'input_radio',
                    'attributes' => [
                        'type' => 'radio',
                        'name' => 'pre_approved',
                        'value' => '',
                    ],
                    'settings'   => [
                        'admin_field_label' => 'Pre-Approved',
                        'label' => 'Are you pre-approved for financing?',
                        'container_class' => '',
                        'label_placement' => '',
                        'display_type' => '',
                        'validation_rules' => [
                            'required' => [
                                'value' => false,
                                'message' => 'This field is required',
                            ],
                        ],
                        'conditional_logics' => [],
                    ],
                    'options' => [
                        'Yes' => 'Yes',
                        'No' => 'No',
                    ],
                    'editor_options' => [
                        'title' => 'Radio Button',
                        'icon_class' => 'icon-dot-circle-o',
                        'element' => 'input-radio',
                        'template' => 'inputRadio',
                    ],
                ],
                [
                    'index'      => 6,
                    'element'    => 'input_radio',
                    'attributes' => [
                        'type' => 'radio',
                        'name' => 'interested_in_preapproval',
                        'value' => '',
                    ],
                    'settings'   => [
                        'admin_field_label' => 'Interested in Pre-Approval',
                        'label' => 'Interested in getting pre-approved?',
                        'container_class' => '',
                        'label_placement' => '',
                        'display_type' => '',
                        'validation_rules' => [
                            'required' => [
                                'value' => false,
                                'message' => 'This field is required',
                            ],
                        ],
                        'conditional_logics' => [],
                    ],
                    'options' => [
                        'Yes' => 'Yes',
                        'No' => 'No',
                        'Already approved' => 'Already approved',
                    ],
                    'editor_options' => [
                        'title' => 'Radio Button',
                        'icon_class' => 'icon-dot-circle-o',
                        'element' => 'input-radio',
                        'template' => 'inputRadio',
                    ],
                ],
                // Form Step Break (Step 2 -> Step 3)
                [
                    'index'      => 7,
                    'element'    => 'form_step',
                    'attributes' => [
                        'id'    => '',
                        'class' => '',
                    ],
                    'settings'   => [
                        'prev_btn' => [
                            'type' => 'default',
                            'text' => 'Previous',
                            'img_url' => '',
                        ],
                        'next_btn' => [
                            'type' => 'default',
                            'text' => 'Continue',
                            'img_url' => '',
                        ],
                    ],
                    'editor_options' => [
                        'title' => 'Form Step',
                        'icon_class' => 'icon-step-forward',
                        'template' => 'formStep',
                    ],
                ],
                // Step 3: Additional Info
                [
                    'index'      => 8,
                    'element'    => 'input_radio',
                    'attributes' => [
                        'type' => 'radio',
                        'name' => 'timeframe',
                        'value' => '',
                    ],
                    'settings'   => [
                        'admin_field_label' => 'Timeframe',
                        'label' => 'When are you looking to buy?',
                        'container_class' => '',
                        'label_placement' => '',
                        'display_type' => '',
                        'validation_rules' => [
                            'required' => [
                                'value' => false,
                                'message' => 'This field is required',
                            ],
                        ],
                        'conditional_logics' => [],
                    ],
                    'options' => [
                        'As soon as possible' => 'As soon as possible',
                        '1-3 months' => '1-3 months',
                        '3-6 months' => '3-6 months',
                        'Just browsing' => 'Just browsing',
                    ],
                    'editor_options' => [
                        'title' => 'Radio Button',
                        'icon_class' => 'icon-dot-circle-o',
                        'element' => 'input-radio',
                        'template' => 'inputRadio',
                    ],
                ],
                [
                    'index'      => 9,
                    'element'    => 'textarea',
                    'attributes' => [
                        'name'        => 'comments',
                        'rows'        => 3,
                        'placeholder' => 'Any questions or comments?',
                    ],
                    'settings'   => [
                        'admin_field_label' => 'Comments',
                        'label' => 'Comments or Questions',
                        'validation_rules' => [
                            'required' => [
                                'value' => false,
                                'message' => 'This field is required',
                            ],
                        ],
                        'conditional_logics' => [],
                    ],
                    'editor_options' => [
                        'title' => 'Text Area',
                        'icon_class' => 'icon-paragraph',
                        'template' => 'inputTextarea',
                    ],
                ],
                // Hidden fields for tracking
                [
                    'index'      => 10,
                    'element'    => 'input_hidden',
                    'attributes' => [
                        'name'  => 'lead_page_id',
                        'value' => '',
                    ],
                    'settings'   => [
                        'admin_field_label' => 'Lead Page ID',
                    ],
                ],
                [
                    'index'      => 11,
                    'element'    => 'input_hidden',
                    'attributes' => [
                        'name'  => 'page_type',
                        'value' => '',
                    ],
                    'settings'   => [
                        'admin_field_label' => 'Page Type',
                    ],
                ],
                [
                    'index'      => 12,
                    'element'    => 'input_hidden',
                    'attributes' => [
                        'name'  => 'loan_officer_id',
                        'value' => '',
                    ],
                    'settings'   => [
                        'admin_field_label' => 'Loan Officer ID',
                    ],
                ],
                [
                    'index'      => 13,
                    'element'    => 'input_hidden',
                    'attributes' => [
                        'name'  => 'realtor_id',
                        'value' => '',
                    ],
                    'settings'   => [
                        'admin_field_label' => 'Realtor ID',
                    ],
                ],
            ],
            'submitButton' => [
                'element'       => 'button',
                'attributes'    => [
                    'type'  => 'submit',
                    'class' => '',
                ],
                'settings'      => [
                    'button_style'     => 'default',
                    'button_size'      => 'md',
                    'align'            => 'left',
                    'container_class'  => '',
                    'current_state'    => 'normal_styles',
                    'background_color' => '#0ea5e9',
                    'color'            => '#ffffff',
                    'button_ui'        => [
                        'type' => 'default',
                        'text' => 'Submit',
                    ],
                ],
            ],
            'stepsWrapper' => [
                'stepStart' => [
                    'element' => 'step_start',
                    'attributes' => [
                        'id' => '',
                        'class' => '',
                    ],
                    'settings' => [
                        'progress_indicator' => 'progress_bar',
                        'step_titles' => [ 'Contact Info', 'Quick Questions', 'Almost Done' ],
                    ],
                    'editor_options' => [
                        'title' => 'Start Paging',
                    ],
                ],
                'stepEnd' => [
                    'element' => 'step_end',
                    'attributes' => [
                        'id' => '',
                        'class' => '',
                    ],
                    'settings' => [
                        'prev_btn' => [
                            'type' => 'default',
                            'text' => 'Previous',
                            'img_url' => '',
                        ],
                    ],
                    'editor_options' => [
                        'title' => 'End Paging',
                    ],
                ],
            ],
        ];
    }

    /**
     * Add form settings/meta
     */
    private static function add_form_settings( int $form_id ): void {
        $settings = [
            'confirmation' => [
                'redirectTo'            => 'samePage',
                'messageToShow'         => 'Thank you for your submission!',
                'customPage'            => null,
                'samePageFormBehavior'  => 'hide_form',
                'customUrl'             => null,
            ],
            'restrictions' => [
                'limitNumberOfEntries' => [
                    'enabled' => false,
                ],
                'scheduleForm' => [
                    'enabled' => false,
                ],
                'requireLogin' => [
                    'enabled' => false,
                ],
                'denyEmptySubmission' => [
                    'enabled' => false,
                ],
            ],
            'layout' => [
                'labelPlacement'   => 'top',
                'helpMessagePlacement' => 'with_label',
                'asteriskPlacement' => 'asterisk-right',
            ],
        ];

        FormMeta::insert([
            'form_id'  => $form_id,
            'meta_key' => 'formSettings',
            'value'    => json_encode( $settings ),
        ]);
    }

    /**
     * Register form with frs-lead-pages tracking system
     *
     * Keeps track of forms created by this plugin for internal use.
     */
    private static function register_form( int $form_id ): void {
        // Get current tracked forms
        $tracked_forms = get_option( 'frs_lead_pages_form_ids', [] );

        if ( ! is_array( $tracked_forms ) ) {
            $tracked_forms = [];
        }

        // Add our form if not already tracked
        if ( ! in_array( $form_id, $tracked_forms, true ) ) {
            $tracked_forms[] = $form_id;
            update_option( 'frs_lead_pages_form_ids', $tracked_forms );
        }
    }

    /**
     * Submit lead data to FluentForms
     *
     * @param array $lead_data Lead data from form submission
     * @param int   $page_id   Lead page post ID
     * @return array Result with submission ID or error
     */
    public static function submit_lead( array $lead_data, int $page_id ): array {
        if ( ! self::is_active() ) {
            return [
                'success' => false,
                'message' => 'FluentForms is not active',
            ];
        }

        $form_id = self::get_form_id();

        if ( ! $form_id ) {
            return [
                'success' => false,
                'message' => 'Could not get or create form',
            ];
        }

        // Get page meta for tracking
        $page_type = get_post_meta( $page_id, '_frs_page_type', true );
        $lo_id     = get_post_meta( $page_id, '_frs_loan_officer_id', true );
        $realtor_id = get_post_meta( $page_id, '_frs_realtor_id', true );

        // Parse full name into first/last
        $name_parts = explode( ' ', $lead_data['fullName'] ?? '', 2 );
        $first_name = $name_parts[0] ?? '';
        $last_name  = $name_parts[1] ?? '';

        // Prepare form data
        $form_data = [
            'names' => [
                'first_name' => sanitize_text_field( $first_name ),
                'last_name'  => sanitize_text_field( $last_name ),
            ],
            'email'                     => sanitize_email( $lead_data['email'] ?? '' ),
            'phone'                     => sanitize_text_field( $lead_data['phone'] ?? '' ),
            'working_with_agent'        => $lead_data['workingWithAgent'] === true ? 'yes' : ( $lead_data['workingWithAgent'] === false ? 'no' : '' ),
            'pre_approved'              => $lead_data['preApproved'] === true ? 'yes' : ( $lead_data['preApproved'] === false ? 'no' : '' ),
            'interested_in_preapproval' => $lead_data['interestedInPreApproval'] === true ? 'yes' : ( $lead_data['interestedInPreApproval'] === false ? 'no' : '' ),
            'timeframe'                 => sanitize_text_field( $lead_data['timeframe'] ?? '' ),
            'comments'                  => sanitize_textarea_field( $lead_data['comments'] ?? '' ),
            // Hidden tracking fields
            'lead_page_id'              => $page_id,
            'page_type'                 => $page_type,
            'loan_officer_id'           => $lo_id,
            'realtor_id'                => $realtor_id,
        ];

        // Get previous submission for serial number
        $previous_item = Submission::where( 'form_id', $form_id )
            ->orderBy( 'id', 'DESC' )
            ->first();

        $serial_number = $previous_item ? $previous_item->serial_number + 1 : 1;

        // Prepare submission data
        $submission_data = [
            'form_id'       => $form_id,
            'serial_number' => $serial_number,
            'response'      => json_encode( $form_data, JSON_UNESCAPED_UNICODE ),
            'source_url'    => get_permalink( $page_id ),
            'user_id'       => get_current_user_id(),
            'browser'       => self::get_browser(),
            'device'        => self::get_device(),
            'ip'            => self::get_ip(),
            'status'        => 'unread',
            'created_at'    => current_time( 'mysql' ),
            'updated_at'    => current_time( 'mysql' ),
        ];

        // Insert submission
        $submission_id = Submission::insertGetId( $submission_data );

        if ( ! $submission_id ) {
            return [
                'success' => false,
                'message' => 'Failed to create submission',
            ];
        }

        // Set submission meta
        Helper::setSubmissionMeta( $submission_id, '_entry_uid_hash', md5( wp_generate_uuid4() . $submission_id ), $form_id );

        // Record entry details for search
        $submission_service = new SubmissionService();
        $submission_service->recordEntryDetails( $submission_id, $form_id, $form_data );

        // Get the form object for hooks
        $form = Form::find( $form_id );

        // Fire FluentForms hooks so integrations (FluentCRM, notifications, etc.) work
        do_action( 'fluentform/submission_inserted', $submission_id, $form_data, $form );

        // Store reference in post meta
        add_post_meta( $page_id, '_frs_submission_ids', $submission_id );

        // Update lead count
        $lead_count = (int) get_post_meta( $page_id, '_frs_lead_count', true );
        update_post_meta( $page_id, '_frs_lead_count', $lead_count + 1 );

        return [
            'success'       => true,
            'submission_id' => $submission_id,
            'form_id'       => $form_id,
        ];
    }

    /**
     * Hook for when submission is inserted (for FluentCRM tagging, etc.)
     */
    public static function on_submission( int $submission_id, array $form_data, $form ): void {
        // Check if this is our lead page form
        if ( empty( $form_data['lead_page_id'] ) ) {
            return;
        }

        $page_id = (int) $form_data['lead_page_id'];
        $page_type = $form_data['page_type'] ?? '';
        $pre_approved = $form_data['pre_approved'] ?? '';
        $interested = $form_data['interested_in_preapproval'] ?? '';

        // Send to n8n webhook
        self::send_to_webhook( $submission_id, $form_data, $page_id );

        // Add FluentCRM tags based on responses
        if ( function_exists( 'FluentCrmApi' ) ) {
            $tags = [];

            // Page type tag
            $tags[] = 'lead-page-' . $page_type;

            // Pre-approval status
            if ( $pre_approved === 'yes' ) {
                $tags[] = 'pre-approved';
            } elseif ( $pre_approved === 'no' && $interested === 'yes' ) {
                $tags[] = 'needs-preapproval';
                $tags[] = 'hot-lead';
            }

            // Apply tags if we have an email
            $email = $form_data['email'] ?? '';
            if ( $email && ! empty( $tags ) ) {
                $contact_api = FluentCrmApi( 'contacts' );
                $contact = $contact_api->getContact( $email );

                if ( $contact ) {
                    $contact->attachTags( $tags );
                }
            }
        }
    }

    /**
     * Send lead data to n8n webhook
     *
     * @param int   $submission_id FluentForms submission ID
     * @param array $form_data     Form submission data
     * @param int   $page_id       Lead page post ID
     */
    private static function send_to_webhook( int $submission_id, array $form_data, int $page_id ): void {
        $webhook_url = get_option( 'frs_lead_pages_webhook_url', '' );

        if ( empty( $webhook_url ) ) {
            return;
        }

        $webhook_secret = get_option( 'frs_lead_pages_webhook_secret', '' );

        // Get page data
        $page_type = get_post_meta( $page_id, '_frs_page_type', true );
        $lo_id = (int) get_post_meta( $page_id, '_frs_loan_officer_id', true );
        $realtor_id = (int) get_post_meta( $page_id, '_frs_realtor_id', true );

        // Get LO data
        $lo_data = self::get_loan_officer_data( $lo_id );

        // Get realtor data
        $realtor_data = self::get_realtor_data( $realtor_id );

        // Get property data (for open house)
        $property_data = [
            'address'  => get_post_meta( $page_id, '_frs_property_address', true ),
            'price'    => get_post_meta( $page_id, '_frs_property_price', true ),
            'bedrooms' => get_post_meta( $page_id, '_frs_property_beds', true ),
            'bathrooms'=> get_post_meta( $page_id, '_frs_property_baths', true ),
            'sqft'     => get_post_meta( $page_id, '_frs_property_sqft', true ),
        ];

        // Build webhook payload (matches SOP structure)
        $payload = [
            'event'     => 'new_lead',
            'timestamp' => current_time( 'c' ),
            'source'    => 'generation_station',

            'lead' => [
                'first_name' => $form_data['names']['first_name'] ?? '',
                'last_name'  => $form_data['names']['last_name'] ?? '',
                'email'      => $form_data['email'] ?? '',
                'phone'      => $form_data['phone'] ?? '',
                'responses'  => [
                    'working_with_agent'        => $form_data['working_with_agent'] ?? '',
                    'pre_approved'              => $form_data['pre_approved'] ?? '',
                    'interested_in_preapproval' => $form_data['interested_in_preapproval'] ?? '',
                    'timeframe'                 => $form_data['timeframe'] ?? '',
                    'comments'                  => $form_data['comments'] ?? '',
                ],
            ],

            'page' => [
                'id'    => $page_id,
                'type'  => $page_type,
                'url'   => get_permalink( $page_id ),
                'title' => get_the_title( $page_id ),
            ],

            'property' => $property_data,

            'realtor' => $realtor_data,

            'loan_officer' => $lo_data,

            'submission' => [
                'id'      => $submission_id,
                'form_id' => self::get_form_id(),
            ],
        ];

        // Send webhook
        $headers = [
            'Content-Type' => 'application/json',
        ];

        if ( ! empty( $webhook_secret ) ) {
            $headers['X-Webhook-Secret'] = $webhook_secret;
        }

        $response = wp_remote_post( $webhook_url, [
            'headers' => $headers,
            'body'    => wp_json_encode( $payload ),
            'timeout' => 15,
        ] );

        // Log result
        $log_data = [
            'success'     => ! is_wp_error( $response ),
            'status_code' => is_wp_error( $response ) ? 0 : wp_remote_retrieve_response_code( $response ),
            'response'    => is_wp_error( $response ) ? $response->get_error_message() : wp_remote_retrieve_body( $response ),
            'timestamp'   => current_time( 'mysql' ),
        ];

        // Store webhook result in post meta
        add_post_meta( $page_id, '_frs_webhook_log', $log_data );

        // If webhook failed, store for retry
        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) >= 400 ) {
            self::store_failed_webhook( $payload, $submission_id, $log_data );
        }
    }

    /**
     * Get loan officer data for webhook
     */
    private static function get_loan_officer_data( int $lo_id ): array {
        if ( ! $lo_id ) {
            return [];
        }

        $user = get_user_by( 'ID', $lo_id );
        if ( ! $user ) {
            return [];
        }

        return [
            'id'    => $lo_id,
            'name'  => $user->display_name,
            'email' => $user->user_email,
            'phone' => get_user_meta( $lo_id, 'phone', true ) ?: get_user_meta( $lo_id, 'billing_phone', true ),
            'nmls'  => get_user_meta( $lo_id, 'nmls_id', true ) ?: get_user_meta( $lo_id, 'nmls', true ),
            'photo' => get_avatar_url( $lo_id, [ 'size' => 200 ] ),
        ];
    }

    /**
     * Get realtor data for webhook
     */
    private static function get_realtor_data( int $realtor_id ): array {
        if ( ! $realtor_id ) {
            return [];
        }

        $user = get_user_by( 'ID', $realtor_id );
        if ( ! $user ) {
            return [];
        }

        return [
            'id'    => $realtor_id,
            'name'  => $user->display_name,
            'email' => $user->user_email,
            'phone' => get_user_meta( $realtor_id, 'phone', true ) ?: get_user_meta( $realtor_id, 'billing_phone', true ),
        ];
    }

    /**
     * Store failed webhook for retry
     */
    private static function store_failed_webhook( array $payload, int $submission_id, array $log_data ): void {
        $failed = get_option( 'frs_lead_pages_failed_webhooks', [] );

        $failed[] = [
            'payload'       => $payload,
            'submission_id' => $submission_id,
            'error'         => $log_data,
            'failed_at'     => current_time( 'mysql' ),
            'attempts'      => 1,
        ];

        update_option( 'frs_lead_pages_failed_webhooks', $failed );
    }

    /**
     * Retry failed webhooks (call from cron or admin)
     */
    public static function retry_failed_webhooks(): array {
        $failed = get_option( 'frs_lead_pages_failed_webhooks', [] );
        $webhook_url = get_option( 'frs_lead_pages_webhook_url', '' );
        $webhook_secret = get_option( 'frs_lead_pages_webhook_secret', '' );

        if ( empty( $failed ) || empty( $webhook_url ) ) {
            return [ 'retried' => 0, 'success' => 0 ];
        }

        $results = [ 'retried' => 0, 'success' => 0 ];
        $still_failed = [];

        foreach ( $failed as $item ) {
            if ( $item['attempts'] >= 5 ) {
                // Max retries reached, keep in failed list
                $still_failed[] = $item;
                continue;
            }

            $results['retried']++;

            $headers = [ 'Content-Type' => 'application/json' ];
            if ( ! empty( $webhook_secret ) ) {
                $headers['X-Webhook-Secret'] = $webhook_secret;
            }

            $response = wp_remote_post( $webhook_url, [
                'headers' => $headers,
                'body'    => wp_json_encode( $item['payload'] ),
                'timeout' => 15,
            ] );

            if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) < 400 ) {
                $results['success']++;
            } else {
                $item['attempts']++;
                $item['last_attempt'] = current_time( 'mysql' );
                $still_failed[] = $item;
            }
        }

        update_option( 'frs_lead_pages_failed_webhooks', $still_failed );

        return $results;
    }

    /**
     * Get all submissions for a lead page
     *
     * Searches across ALL lead page forms (open_house, customer_spotlight, special_event, etc.)
     * and matches by lead_page_id in the submission response.
     *
     * @param int   $page_id The lead page post ID.
     * @param array $args    Query arguments (per_page, page, status).
     * @return array Array with total count and submissions.
     */
    public static function get_submissions_for_page( int $page_id, array $args = [] ): array {
        if ( ! self::is_active() ) {
            return [];
        }

        // Get ALL lead page form IDs (not just one type)
        $form_ids = self::get_all_lead_page_form_ids();
        if ( empty( $form_ids ) ) {
            return [ 'total' => 0, 'submissions' => [] ];
        }

        $defaults = [
            'per_page' => 20,
            'page'     => 1,
            'status'   => 'all',
        ];

        $args = wp_parse_args( $args, $defaults );

        // Search across all lead page forms
        // Use string comparison since lead_page_id is stored as string in JSON
        $query = Submission::whereIn( 'form_id', $form_ids )
            ->where( function( $q ) use ( $page_id ) {
                // Match both string and integer versions of page_id
                $q->whereRaw( "JSON_UNQUOTE(JSON_EXTRACT(response, '$.lead_page_id')) = ?", [ (string) $page_id ] )
                  ->orWhereRaw( "JSON_UNQUOTE(JSON_EXTRACT(response, '$.__fluent_form_embded_post_id')) = ?", [ (string) $page_id ] );
            })
            ->orderBy( 'id', 'DESC' );

        if ( $args['status'] !== 'all' ) {
            $query->where( 'status', $args['status'] );
        }

        $total = $query->count();
        $submissions = $query
            ->limit( $args['per_page'] )
            ->offset( ( $args['page'] - 1 ) * $args['per_page'] )
            ->get();

        return [
            'total'       => $total,
            'submissions' => $submissions->map( function( $submission ) {
                $response = json_decode( $submission->response, true );

                // Handle both nested (names.first_name) and flat (first_name) structures
                $first_name = $response['names']['first_name'] ?? $response['first_name'] ?? '';
                $last_name = $response['names']['last_name'] ?? $response['last_name'] ?? '';

                return [
                    'id'         => $submission->id,
                    'first_name' => $first_name,
                    'last_name'  => $last_name,
                    'name'       => trim( $first_name . ' ' . $last_name ),
                    'email'      => $response['email'] ?? '',
                    'phone'      => $response['phone'] ?? '',
                    'status'     => $submission->status,
                    'created_at' => $submission->created_at,
                    'form_id'    => $submission->form_id,
                    'response'   => $response,
                ];
            })->toArray(),
        ];
    }

    /**
     * Get submissions for a specific user (all pages owned by user)
     *
     * @param int $user_id User ID (loan officer or realtor)
     * @return array Array of formatted submission data
     */
    public static function get_submissions_for_user( int $user_id ): array {
        if ( ! self::is_active() ) {
            return [];
        }

        // Get all pages owned by user
        $pages = get_posts([
            'post_type'      => 'frs_lead_page',
            'posts_per_page' => -1,
            'post_status'    => 'any',
            'meta_query'     => [
                'relation' => 'OR',
                [
                    'key'   => '_frs_loan_officer_id',
                    'value' => $user_id,
                ],
                [
                    'key'   => '_frs_realtor_id',
                    'value' => $user_id,
                ],
            ],
        ]);

        if ( empty( $pages ) ) {
            return [];
        }

        $page_ids = wp_list_pluck( $pages, 'ID' );

        // Get all lead page form IDs (not just one)
        $form_ids = self::get_all_lead_page_form_ids();
        if ( empty( $form_ids ) ) {
            return [];
        }

        // Query submissions from ALL lead page forms
        $query = Submission::whereIn( 'form_id', $form_ids )
            ->orderBy( 'id', 'DESC' );

        // Build OR condition for: page_id matches OR loan_officer_id/realtor_id matches
        $query->where(function($q) use ($page_ids, $user_id) {
            // Match by page ID
            foreach ($page_ids as $page_id) {
                $q->orWhereRaw("JSON_EXTRACT(response, '$.lead_page_id') = ?", [(int) $page_id]);
            }
            // Also match by loan_officer_id or realtor_id directly in submission
            $q->orWhereRaw("JSON_EXTRACT(response, '$.loan_officer_id') = ?", [(int) $user_id]);
            $q->orWhereRaw("JSON_EXTRACT(response, '$.realtor_id') = ?", [(int) $user_id]);
        });

        $submissions = $query->limit( 200 )->get();

        return $submissions->map( function( $submission ) {
            $response = json_decode( $submission->response, true );

            // Get lead page info
            $lead_page_id = $response['lead_page_id'] ?? 0;
            $lead_page_title = $lead_page_id ? get_the_title( $lead_page_id ) : 'Unknown';

            // Handle both nested (names.first_name) and flat (first_name) structures
            $first_name = $response['names']['first_name'] ?? $response['first_name'] ?? '';
            $last_name = $response['names']['last_name'] ?? $response['last_name'] ?? '';

            return [
                'id'              => $submission->id,
                'lead_page_id'    => $lead_page_id,
                'lead_page_title' => $lead_page_title,
                'page_type'       => $response['page_type'] ?? 'general',
                'first_name'      => $first_name,
                'last_name'       => $last_name,
                'email'           => $response['email'] ?? '',
                'phone'           => $response['phone'] ?? '',
                'message'         => $response['comments'] ?? $response['message'] ?? '',
                'status'          => $submission->status === 'unread' ? 'new' : $submission->status,
                'loan_officer_id' => $response['loan_officer_id'] ?? null,
                'realtor_id'      => $response['realtor_id'] ?? null,
                'created_at'      => $submission->created_at,
                'form_id'         => $submission->form_id,
                'submission_id'   => $submission->id,
            ];
        })->toArray();
    }

    /**
     * Get submissions filtered by loan officer or realtor
     *
     * @param array $filters {
     *     @type int|null $loan_officer_id Filter by loan officer
     *     @type int|null $realtor_id      Filter by realtor
     *     @type int|null $page_id         Filter by specific page
     *     @type string|null $status       Filter by status
     * }
     * @return array Array of formatted submission data
     */
    public static function get_submissions( array $filters = [] ): array {
        if ( ! self::is_active() ) {
            return [];
        }

        $form_id = self::get_form_id();
        if ( ! $form_id ) {
            return [];
        }

        $query = Submission::where( 'form_id', $form_id )
            ->orderBy( 'id', 'DESC' );

        // Build JSON path filters
        $conditions = [];

        if ( ! empty( $filters['loan_officer_id'] ) ) {
            $conditions[] = [
                "JSON_EXTRACT(response, '$.loan_officer_id') = ?",
                [ (string) $filters['loan_officer_id'] ]
            ];
        }

        if ( ! empty( $filters['realtor_id'] ) ) {
            $conditions[] = [
                "JSON_EXTRACT(response, '$.realtor_id') = ?",
                [ (string) $filters['realtor_id'] ]
            ];
        }

        if ( ! empty( $filters['page_id'] ) ) {
            $conditions[] = [
                "JSON_EXTRACT(response, '$.lead_page_id') = ?",
                [ (int) $filters['page_id'] ]
            ];
        }

        // Apply conditions
        foreach ( $conditions as $condition ) {
            $query->whereRaw( $condition[0], $condition[1] );
        }

        if ( ! empty( $filters['status'] ) ) {
            $query->where( 'status', $filters['status'] );
        }

        $submissions = $query->limit( 100 )->get();

        return $submissions->map( function( $submission ) {
            $response = json_decode( $submission->response, true );

            // Get lead page info
            $lead_page_id = $response['lead_page_id'] ?? 0;
            $lead_page_title = $lead_page_id ? get_the_title( $lead_page_id ) : 'Unknown';

            return [
                'id'              => $submission->id,
                'lead_page_id'    => $lead_page_id,
                'lead_page_title' => $lead_page_title,
                'page_type'       => $response['page_type'] ?? 'general',
                'first_name'      => $response['names']['first_name'] ?? '',
                'last_name'       => $response['names']['last_name'] ?? '',
                'email'           => $response['email'] ?? '',
                'phone'           => $response['phone'] ?? '',
                'message'         => $response['comments'] ?? '',
                'status'          => $submission->status === 'unread' ? 'new' : $submission->status,
                'loan_officer_id' => $response['loan_officer_id'] ?? null,
                'realtor_id'      => $response['realtor_id'] ?? null,
                'created_at'      => $submission->created_at,
                'form_id'         => $submission->form_id,
                'submission_id'   => $submission->id,
            ];
        })->toArray();
    }

    /**
     * Get browser info
     */
    private static function get_browser(): string {
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        if ( strpos( $user_agent, 'Chrome' ) !== false ) {
            return 'Chrome';
        } elseif ( strpos( $user_agent, 'Firefox' ) !== false ) {
            return 'Firefox';
        } elseif ( strpos( $user_agent, 'Safari' ) !== false ) {
            return 'Safari';
        } elseif ( strpos( $user_agent, 'Edge' ) !== false ) {
            return 'Edge';
        }

        return 'Other';
    }

    /**
     * Get device info
     */
    private static function get_device(): string {
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        if ( preg_match( '/Mobile|Android|iPhone|iPad/i', $user_agent ) ) {
            return 'Mobile';
        }

        return 'Desktop';
    }

    /**
     * Get client IP
     */
    private static function get_ip(): string {
        $ip = '';

        if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        return sanitize_text_field( $ip );
    }
}
