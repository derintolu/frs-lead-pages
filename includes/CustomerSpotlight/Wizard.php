<?php
/**
 * Customer Spotlight Wizard
 *
 * Multi-step wizard for creating Customer Spotlight landing pages.
 * Target specific buyer types with tailored messaging.
 *
 * @package FRSLeadPages
 */

namespace FRSLeadPages\CustomerSpotlight;

use FRSLeadPages\Core\LoanOfficers;
use FRSLeadPages\Integrations\InstantImages;

class Wizard {

    /**
     * Trigger class for opening modal
     */
    const TRIGGER_CLASS = 'cs-wizard-trigger';

    /**
     * Hash for URL triggering
     */
    const TRIGGER_HASH = 'customer-spotlight-wizard';

    /**
     * Initialize
     */
    public static function init() {
        add_shortcode( 'customer_spotlight_wizard', [ __CLASS__, 'render' ] );
        add_shortcode( 'customer_spotlight_wizard_button', [ __CLASS__, 'render_button' ] );
        add_action( 'wp_ajax_frs_create_spotlight', [ __CLASS__, 'ajax_create_spotlight' ] );

        // Add modal to footer on frontend
        add_action( 'wp_footer', [ __CLASS__, 'render_modal_container' ] );
    }

    /**
     * Render trigger button shortcode
     */
    public static function render_button( array $atts = [] ): string {
        $atts = shortcode_atts([
            'text'  => 'Create Customer Spotlight',
            'class' => '',
        ], $atts, 'customer_spotlight_wizard_button' );

        $classes = self::TRIGGER_CLASS;
        if ( ! empty( $atts['class'] ) ) {
            $classes .= ' ' . esc_attr( $atts['class'] );
        }

        return sprintf(
            '<button type="button" class="%s">%s</button>',
            esc_attr( $classes ),
            esc_html( $atts['text'] )
        );
    }

    /**
     * Render modal container in footer
     */
    public static function render_modal_container(): void {
        if ( ! is_user_logged_in() ) {
            return;
        }

        $user = wp_get_current_user();
        $allowed_roles = [ 'administrator', 'editor', 'author', 'contributor', 'loan_officer', 'realtor_partner' ];

        if ( ! array_intersect( $allowed_roles, $user->roles ) ) {
            return;
        }

        echo self::render_modal();
    }

    /**
     * Render the wizard (inline version)
     */
    public static function render( array $atts = [] ): string {
        if ( ! is_user_logged_in() ) {
            return self::render_login_required();
        }

        $user = wp_get_current_user();
        $allowed_roles = [ 'administrator', 'editor', 'author', 'contributor', 'loan_officer', 'realtor_partner' ];

        if ( ! array_intersect( $allowed_roles, $user->roles ) ) {
            return self::render_access_denied();
        }

        return self::render_wizard_content();
    }

    /**
     * Render modal version
     */
    private static function render_modal(): string {
        ob_start();
        ?>
        <div id="cs-wizard-modal" class="cs-modal">
            <div class="cs-modal__backdrop"></div>
            <div class="cs-modal__container">
                <button type="button" class="cs-modal__close" aria-label="Close">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
                <?php echo self::render_wizard_content( true ); ?>
            </div>
        </div>
        <?php echo self::render_modal_styles(); ?>
        <?php echo self::render_modal_scripts(); ?>
        <?php
        return ob_get_clean();
    }

    /**
     * Render wizard content
     */
    private static function render_wizard_content( bool $is_modal = false ): string {
        $user = wp_get_current_user();
        $user_data = [
            'id'      => $user->ID,
            'name'    => $user->display_name,
            'email'   => $user->user_email,
            'phone'   => get_user_meta( $user->ID, 'phone', true ) ?: get_user_meta( $user->ID, 'billing_phone', true ),
            'license' => get_user_meta( $user->ID, 'license_number', true ),
            'photo'   => get_avatar_url( $user->ID, [ 'size' => 200 ] ),
        ];

        $loan_officers = LoanOfficers::get_loan_officers();

        $spotlight_types = [
            'first_time_buyer' => [
                'label' => 'First-Time Buyer',
                'desc'  => 'New to homeownership',
                'icon'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>',
            ],
            'move_up_buyer' => [
                'label' => 'Move-Up Buyer',
                'desc'  => 'Ready for more space',
                'icon'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 20V10M12 20V4M6 20v-6"/></svg>',
            ],
            'downsizer' => [
                'label' => 'Downsizer',
                'desc'  => 'Simplifying lifestyle',
                'icon'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M8 12h8M12 8v8"/></svg>',
            ],
            'investor' => [
                'label' => 'Investor',
                'desc'  => 'Building portfolio',
                'icon'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>',
            ],
            'relocating' => [
                'label' => 'Relocating',
                'desc'  => 'Moving to a new area',
                'icon'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="10" r="3"/><path d="M12 21.7C17.3 17 20 13 20 10a8 8 0 1 0-16 0c0 3 2.7 7 8 11.7z"/></svg>',
            ],
            'veteran' => [
                'label' => 'Veteran',
                'desc'  => 'Military & VA benefits',
                'icon'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>',
            ],
        ];

        ob_start();
        ?>
        <div id="cs-wizard" class="cs-wizard" data-user='<?php echo esc_attr( wp_json_encode( $user_data ) ); ?>'>
            <div class="cs-wizard__hero">
                <div class="cs-wizard__hero-content">
                    <h1>Create Your<br>Customer Spotlight</h1>
                    <p>Build a targeted landing page for specific buyer types with personalized messaging.</p>
                </div>
            </div>

            <div class="cs-wizard__form">
                <div class="cs-wizard__progress">
                    <div class="cs-wizard__progress-bar" style="width: 14.3%"></div>
                </div>

                <div class="cs-wizard__header">
                    <p class="cs-wizard__title">Customer Spotlight Wizard</p>
                    <p class="cs-wizard__subtitle">Step <span id="cs-step-num">1</span> of 8</p>
                </div>

                <div class="cs-wizard__nav-top">
                    <button type="button" id="cs-back-top" class="cs-btn cs-btn--ghost cs-btn--sm" style="display:none;">Back</button>
                    <button type="button" id="cs-next-top" class="cs-btn cs-btn--primary cs-btn--sm">Continue</button>
                </div>

                <div class="cs-wizard__content">
                <!-- Step 0: Choose Loan Officer -->
                <div class="cs-step" data-step="0">
                    <div class="cs-step__header">
                        <h2>Partner Up</h2>
                        <p>Select a loan officer to co-brand this page</p>
                    </div>
                    <div class="cs-step__body">
                        <label class="cs-label">Loan Officer</label>
                        <div class="cs-dropdown" id="cs-loan-officer-dropdown">
                            <input type="hidden" id="cs-loan-officer" name="loan_officer" value="">
                            <button type="button" class="cs-dropdown__trigger">
                                <span class="cs-dropdown__value">Select a loan officer...</span>
                                <svg class="cs-dropdown__arrow" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
                            </button>
                            <div class="cs-dropdown__menu">
                                <?php foreach ( $loan_officers as $lo ) : ?>
                                    <div class="cs-dropdown__item"
                                         data-value="<?php echo esc_attr( $lo['id'] ); ?>"
                                         data-name="<?php echo esc_attr( $lo['name'] ); ?>"
                                         data-nmls="<?php echo esc_attr( $lo['nmls'] ); ?>"
                                         data-photo="<?php echo esc_attr( $lo['photo_url'] ); ?>"
                                         data-email="<?php echo esc_attr( $lo['email'] ); ?>"
                                         data-phone="<?php echo esc_attr( $lo['phone'] ); ?>">
                                        <img src="<?php echo esc_url( $lo['photo_url'] ); ?>" alt="" class="cs-dropdown__photo">
                                        <div class="cs-dropdown__info">
                                            <span class="cs-dropdown__name"><?php echo esc_html( $lo['name'] ); ?></span>
                                            <span class="cs-dropdown__nmls">NMLS# <?php echo esc_html( $lo['nmls'] ); ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <p class="cs-helper">Your loan officer's info and branding will appear on the page.</p>
                    </div>
                </div>

                <!-- Step 1: Spotlight Type -->
                <div class="cs-step" data-step="1" style="display:none;">
                    <div class="cs-step__header">
                        <h2>Who's Your Target?</h2>
                        <p>Select the buyer type you want to reach</p>
                    </div>
                    <div class="cs-step__body">
                        <div class="cs-type-grid">
                            <?php foreach ( $spotlight_types as $key => $type ) : ?>
                                <label class="cs-type-card">
                                    <input type="radio" name="cs-spotlight-type" value="<?php echo esc_attr( $key ); ?>">
                                    <div class="cs-type-card__content">
                                        <div class="cs-type-card__icon"><?php echo $type['icon']; ?></div>
                                        <div class="cs-type-card__text">
                                            <strong><?php echo esc_html( $type['label'] ); ?></strong>
                                            <span><?php echo esc_html( $type['desc'] ); ?></span>
                                        </div>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Customize Page -->
                <div class="cs-step" data-step="2" style="display:none;">
                    <div class="cs-step__header">
                        <h2>Customize Your Page</h2>
                        <p>Create compelling messaging for your audience</p>
                    </div>
                    <div class="cs-step__body">
                        <div class="cs-field">
                            <label class="cs-label">Headline</label>
                            <div id="cs-headline-options" class="cs-radio-group">
                                <!-- Populated by JS based on spotlight type -->
                            </div>
                            <input type="text" id="cs-headline-custom" class="cs-input" placeholder="Enter custom headline" style="display:none; margin-top:12px;">
                        </div>
                        <div class="cs-field">
                            <label class="cs-label">Subheadline</label>
                            <input type="text" id="cs-subheadline" class="cs-input" placeholder="A short description of what you're offering">
                        </div>
                        <div class="cs-field">
                            <label class="cs-label">Value Propositions <span class="cs-label-hint">(one per line)</span></label>
                            <textarea id="cs-value-props" class="cs-textarea" rows="4" placeholder="Expert market knowledge
Personalized service
Smooth closing process"></textarea>
                            <p class="cs-helper">These will appear as bullet points on your page</p>
                        </div>
                    </div>
                </div>

                <!-- Step 3: Hero Image -->
                <div class="cs-step" data-step="3" style="display:none;">
                    <div class="cs-step__header">
                        <h2>Choose Your Photo</h2>
                        <p>Select a lifestyle image for your audience</p>
                    </div>
                    <div class="cs-step__body">
                        <div id="cs-images-grid" class="cs-images-grid">
                            <!-- Stock images populated by JS -->
                        </div>
                        <div class="cs-upload-section">
                            <p>Or find the perfect stock photo:</p>
                            <?php echo InstantImages::render_search_button( 'cs', '#10b981' ); ?>
                            <p style="margin-top: 16px;">Or upload your own image:</p>
                            <input type="file" id="cs-image-upload" accept="image/*" class="cs-file-input">
                            <label for="cs-image-upload" class="cs-btn cs-btn--secondary">Upload Image</label>
                        </div>
                        <input type="hidden" id="cs-hero-image" value="">
                        <?php echo InstantImages::render_search_modal( 'cs', 'cs-hero-image' ); ?>
                    </div>
                </div>

                <!-- Step 4: Contact Fields -->
                <div class="cs-step" data-step="4" style="display:none;">
                    <div class="cs-step__header">
                        <h2>Contact Fields</h2>
                        <p>Required info from visitors</p>
                    </div>
                    <div class="cs-step__body">
                        <div class="cs-toggle-list">
                            <label class="cs-toggle">
                                <input type="checkbox" checked disabled> Full Name <span class="cs-toggle__required">Required</span>
                            </label>
                            <label class="cs-toggle">
                                <input type="checkbox" checked disabled> Email <span class="cs-toggle__required">Required</span>
                            </label>
                            <label class="cs-toggle">
                                <input type="checkbox" checked disabled> Phone <span class="cs-toggle__required">Required</span>
                            </label>
                            <label class="cs-toggle">
                                <input type="checkbox" name="cs-q-comments" checked> Comments
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Step 5: Qualifying Questions -->
                <div class="cs-step" data-step="5" style="display:none;">
                    <div class="cs-step__header">
                        <h2>Qualifying Questions</h2>
                        <p>Optional questions to qualify leads</p>
                    </div>
                    <div class="cs-step__body">
                        <div class="cs-toggle-list">
                            <label class="cs-toggle">
                                <input type="checkbox" name="cs-q-agent" checked> Are you working with an agent?
                            </label>
                            <label class="cs-toggle">
                                <input type="checkbox" name="cs-q-preapproved" checked> Are you pre-approved?
                            </label>
                            <label class="cs-toggle">
                                <input type="checkbox" name="cs-q-interested" checked> Interested in pre-approval?
                            </label>
                            <label class="cs-toggle">
                                <input type="checkbox" name="cs-q-timeline" checked> Buying timeline
                            </label>
                            <label class="cs-toggle">
                                <input type="checkbox" name="cs-q-pricerange"> Ideal price range
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Step 6: Branding -->
                <div class="cs-step" data-step="6" style="display:none;">
                    <div class="cs-step__header">
                        <h2>Your Team Info</h2>
                        <p>Confirm your contact details</p>
                    </div>
                    <div class="cs-step__body">
                        <p class="cs-section-label">Your Information</p>
                        <div class="cs-row">
                            <div class="cs-field cs-field--half">
                                <label class="cs-label">Your Name</label>
                                <input type="text" id="cs-realtor-name" class="cs-input" value="<?php echo esc_attr( $user_data['name'] ); ?>">
                            </div>
                            <div class="cs-field cs-field--half">
                                <label class="cs-label">License #</label>
                                <input type="text" id="cs-realtor-license" class="cs-input" value="<?php echo esc_attr( $user_data['license'] ); ?>">
                            </div>
                        </div>
                        <div class="cs-row">
                            <div class="cs-field cs-field--half">
                                <label class="cs-label">Phone</label>
                                <input type="tel" id="cs-realtor-phone" class="cs-input" value="<?php echo esc_attr( $user_data['phone'] ); ?>">
                            </div>
                            <div class="cs-field cs-field--half">
                                <label class="cs-label">Email</label>
                                <input type="email" id="cs-realtor-email" class="cs-input" value="<?php echo esc_attr( $user_data['email'] ); ?>">
                            </div>
                        </div>

                        <p class="cs-section-label" style="margin-top:24px;">Loan Officer (from Step 1)</p>
                        <div id="cs-lo-preview" class="cs-lo-preview">
                            <!-- Populated by JS -->
                        </div>
                    </div>
                </div>

                <!-- Step 7: Preview & Publish -->
                <div class="cs-step" data-step="7" style="display:none;">
                    <div class="cs-step__header">
                        <h2>Review & Publish</h2>
                        <p>Everything looks good? Let's make it live.</p>
                    </div>
                    <div class="cs-step__body">
                        <div id="cs-summary" class="cs-summary">
                            <!-- Populated by JS -->
                        </div>
                    </div>
                </div>

                <!-- Success State -->
                <div class="cs-step cs-step--success" data-step="success" style="display:none;">
                    <div class="cs-success">
                        <div class="cs-success__icon">
                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                <polyline points="22 4 12 14.01 9 11.01"></polyline>
                            </svg>
                        </div>
                        <h2>Your Page is Live!</h2>
                        <p id="cs-success-type"></p>
                        <div class="cs-success__actions">
                            <a id="cs-success-link" href="#" class="cs-btn cs-btn--primary" target="_blank">View Page</a>
                            <button type="button" id="cs-copy-link" class="cs-btn cs-btn--secondary">Copy Link</button>
                        </div>
                        <a href="<?php echo esc_url( remove_query_arg( 'created' ) ); ?>" class="cs-link">Create Another</a>
                    </div>
                </div>

                </div><!-- .cs-wizard__content -->

                <div class="cs-wizard__footer">
                    <button type="button" id="cs-back" class="cs-btn cs-btn--ghost" style="display:none;">Back</button>
                    <button type="button" id="cs-next" class="cs-btn cs-btn--primary">Continue</button>
                    <button type="button" id="cs-publish" class="cs-btn cs-btn--primary" style="display:none;">
                        <span class="cs-btn__text">Publish Page</span>
                        <span class="cs-btn__loading" style="display:none;">Creating...</span>
                    </button>
                </div>
            </div><!-- .cs-wizard__form -->
        </div><!-- .cs-wizard -->

        <?php echo self::render_styles(); ?>
        <?php echo self::render_scripts(); ?>
        <?php
        return ob_get_clean();
    }

    /**
     * Render styles
     */
    private static function render_styles(): string {
        return '
        <style>
            .cs-wizard {
                display: flex;
                min-height: 100vh;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            }
            .cs-wizard__hero {
                width: 50%;
                height: 100vh;
                background: linear-gradient(135deg, #059669 0%, #047857 100%);
                display: flex;
                flex-direction: column;
                justify-content: center;
                padding: 64px;
                position: fixed;
                left: 0;
                top: 0;
                overflow: hidden;
            }
            .cs-wizard__hero::before {
                content: "";
                position: absolute;
                top: -50%;
                right: -50%;
                width: 100%;
                height: 100%;
                background: radial-gradient(circle, rgba(16,185,129,0.2) 0%, transparent 70%);
                pointer-events: none;
            }
            .cs-wizard__hero-content {
                position: relative;
                z-index: 1;
            }
            .cs-wizard__hero h1 {
                font-size: 48px;
                font-weight: 700;
                color: #fff;
                margin: 0 0 16px;
                line-height: 1.1;
            }
            .cs-wizard__hero p {
                font-size: 18px;
                color: rgba(255,255,255,0.8);
                margin: 0;
                max-width: 400px;
            }
            .cs-wizard__form {
                width: 50%;
                margin-left: 50%;
                min-height: 100vh;
                background: #fff;
                padding: 48px 56px;
                box-sizing: border-box;
            }
            .cs-wizard__progress {
                height: 3px;
                background: #e5e7eb;
                margin-bottom: 40px;
            }
            .cs-wizard__progress-bar {
                height: 100%;
                background: #10b981;
                transition: width 0.3s ease;
            }
            .cs-wizard__header {
                margin-bottom: 8px;
            }
            .cs-wizard__title {
                font-size: 12px;
                font-weight: 600;
                color: #10b981;
                margin: 0 0 4px;
                text-transform: uppercase;
                letter-spacing: 0.1em;
            }
            .cs-wizard__subtitle {
                font-size: 13px;
                color: #94a3b8;
                margin: 0;
            }
            .cs-wizard__nav-top {
                display: flex;
                gap: 12px;
                justify-content: flex-end;
                margin-bottom: 16px;
            }
            .cs-btn--sm {
                padding: 8px 16px;
                font-size: 13px;
            }
            .cs-wizard__content {
            }
            .cs-step {
                display: flex;
                flex-direction: column;
            }
            .cs-step__body {
                padding-right: 8px;
            }
            .cs-step__header h2 {
                font-size: 32px;
                font-weight: 700;
                color: #0f172a;
                margin: 0 0 8px;
            }
            .cs-step__header p {
                font-size: 15px;
                color: #64748b;
                margin: 0 0 32px;
            }
            .cs-label {
                display: block !important;
                font-size: 15px !important;
                font-weight: 600 !important;
                color: #374151 !important;
                margin-bottom: 12px !important;
            }
            .cs-label-hint {
                font-weight: 400;
                color: #94a3b8;
            }
            #cs-wizard .cs-input,
            #cs-wizard input[type="text"],
            #cs-wizard input[type="email"],
            #cs-wizard input[type="tel"],
            #cs-wizard textarea {
                width: 100%;
                padding: 18px 20px;
                font-size: 16px;
                border: 2px solid #e5e7eb;
                border-radius: 10px;
                background-color: #fff;
                box-sizing: border-box;
                min-height: 56px;
            }
            .cs-textarea {
                min-height: 600px;
                resize: vertical;
            }
            .cs-input:focus, .cs-textarea:focus {
                outline: none;
                border-color: #10b981;
            }
            .cs-dropdown {
                position: relative;
                width: 100%;
            }
            .cs-dropdown__trigger {
                width: 100%;
                height: 60px;
                padding: 0 20px;
                font-size: 16px;
                border: 2px solid #e5e7eb;
                border-radius: 10px;
                background-color: #fff;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: space-between;
                text-align: left;
                color: #374151;
            }
            .cs-dropdown.open .cs-dropdown__trigger {
                border-color: #10b981;
                box-shadow: 0 0 0 4px rgba(16,185,129,0.1);
            }
            .cs-dropdown__menu {
                position: fixed;
                background: #fff;
                border: 2px solid #e5e7eb;
                border-radius: 8px;
                box-shadow: 0 10px 40px rgba(0,0,0,0.15);
                max-height: 300px;
                overflow-y: auto;
                z-index: 99999;
                display: none;
            }
            .cs-dropdown.open .cs-dropdown__menu {
                display: block;
            }
            .cs-dropdown__item {
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 12px 16px;
                cursor: pointer;
                transition: background 0.15s;
            }
            .cs-dropdown__item:hover {
                background: #f3f4f6;
            }
            .cs-dropdown__item.selected {
                background: #ecfdf5;
            }
            .cs-dropdown__photo {
                width: 40px;
                height: 40px;
                border-radius: 50%;
                object-fit: cover;
            }
            .cs-dropdown__name {
                font-size: 15px;
                font-weight: 600;
                color: #1f2937;
                display: block;
            }
            .cs-dropdown__nmls {
                font-size: 13px;
                color: #6b7280;
            }
            .cs-type-grid {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 16px;
            }
            .cs-type-card {
                cursor: pointer;
            }
            .cs-type-card input {
                position: absolute;
                opacity: 0;
            }
            .cs-type-card__content {
                display: flex;
                align-items: center;
                gap: 16px;
                padding: 20px;
                background: #fff;
                border: 2px solid #e5e7eb;
                border-radius: 12px;
                transition: all 0.2s;
            }
            .cs-type-card:hover .cs-type-card__content {
                border-color: #10b981;
                background: #f0fdf4;
            }
            .cs-type-card input:checked + .cs-type-card__content {
                border-color: #10b981;
                background: #ecfdf5;
                box-shadow: 0 0 0 4px rgba(16,185,129,0.15);
            }
            .cs-type-card__icon {
                width: 48px;
                height: 48px;
                display: flex;
                align-items: center;
                justify-content: center;
                background: #f0fdf4;
                border-radius: 12px;
                flex-shrink: 0;
            }
            .cs-type-card__icon svg {
                width: 24px;
                height: 24px;
                stroke: #059669;
            }
            .cs-type-card input:checked + .cs-type-card__content .cs-type-card__icon {
                background: #10b981;
            }
            .cs-type-card input:checked + .cs-type-card__content .cs-type-card__icon svg {
                stroke: #fff;
            }
            .cs-type-card__text {
                display: flex;
                flex-direction: column;
                gap: 2px;
            }
            .cs-type-card__text strong {
                font-size: 15px;
                font-weight: 600;
                color: #1f2937;
            }
            .cs-type-card__text span {
                font-size: 13px;
                color: #6b7280;
            }
            .cs-radio-group {
                display: flex;
                flex-wrap: wrap;
                gap: 10px;
            }
            .cs-radio-btn {
                position: relative;
                cursor: pointer;
            }
            .cs-radio-btn input {
                position: absolute;
                opacity: 0;
            }
            .cs-radio-btn__label {
                display: inline-block;
                padding: 14px 20px;
                font-size: 15px;
                font-weight: 500;
                color: #374151;
                background: #fff;
                border: 2px solid #e5e7eb;
                border-radius: 10px;
                transition: all 0.15s ease;
                cursor: pointer;
            }
            .cs-radio-btn input:checked + .cs-radio-btn__label {
                background: #10b981;
                border-color: #10b981;
                color: #fff;
            }
            .cs-images-grid {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 16px;
                margin-bottom: 24px;
            }
            .cs-image-option {
                aspect-ratio: 4/3;
                border-radius: 12px;
                overflow: hidden;
                cursor: pointer;
                border: 3px solid transparent;
                transition: all 0.2s;
            }
            .cs-image-option img {
                width: 100%;
                height: 100%;
                object-fit: cover;
            }
            .cs-image-option--selected {
                border-color: #10b981;
                box-shadow: 0 0 0 4px rgba(16,185,129,0.2);
            }
            .cs-upload-section {
                text-align: center;
                padding: 24px;
                background: #f8fafc;
                border-radius: 12px;
                border: 2px dashed #cbd5e1;
            }
            .cs-upload-section p {
                margin: 0 0 12px;
                color: #64748b;
            }
            .cs-file-input {
                display: none;
            }
            .cs-toggle-list {
                display: flex;
                flex-direction: column;
                gap: 12px;
            }
            .cs-toggle {
                display: flex;
                align-items: center;
                gap: 14px;
                font-size: 15px;
                color: #374151;
                cursor: pointer;
                padding: 12px 16px;
                background: #f8fafc;
                border-radius: 10px;
            }
            .cs-toggle input {
                width: 20px;
                height: 20px;
                accent-color: #10b981;
            }
            .cs-toggle__required {
                font-size: 11px;
                font-weight: 600;
                color: #94a3b8;
                margin-left: auto;
                text-transform: uppercase;
            }
            .cs-section-label {
                font-size: 11px;
                font-weight: 700;
                color: #64748b;
                text-transform: uppercase;
                letter-spacing: 0.1em;
                margin: 0 0 16px;
            }
            .cs-field {
                margin-bottom: 24px !important;
            }
            .cs-row {
                display: flex;
                gap: 20px;
            }
            .cs-field--half {
                flex: 1;
            }
            .cs-helper {
                font-size: 13px;
                color: #94a3b8;
                margin: 10px 0 0;
            }
            .cs-lo-preview {
                display: flex;
                align-items: center;
                gap: 16px;
                padding: 20px;
                background: #f8fafc;
                border-radius: 12px;
            }
            .cs-lo-preview img {
                width: 64px;
                height: 64px;
                border-radius: 50%;
                object-fit: cover;
            }
            .cs-lo-preview__info h4 {
                font-size: 17px;
                font-weight: 600;
                color: #0f172a;
                margin: 0 0 4px;
            }
            .cs-lo-preview__info p {
                font-size: 14px;
                color: #64748b;
                margin: 0;
            }
            .cs-summary {
                background: #f8fafc;
                border-radius: 16px;
                padding: 28px;
            }
            .cs-summary__row {
                display: flex;
                justify-content: space-between;
                padding: 14px 0;
                border-bottom: 1px solid #e5e7eb;
            }
            .cs-summary__row:last-child {
                border-bottom: none;
            }
            .cs-summary__label {
                font-size: 14px;
                color: #64748b;
            }
            .cs-summary__value {
                font-size: 14px;
                font-weight: 600;
                color: #0f172a;
            }
            .cs-btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                padding: 18px 36px;
                font-size: 17px;
                font-weight: 600;
                border-radius: 12px;
                border: none;
                cursor: pointer;
                transition: all 0.2s;
            }
            .cs-btn--primary {
                background: #10b981;
                color: #fff;
            }
            .cs-btn--primary:hover {
                background: #059669;
            }
            .cs-btn--secondary {
                background: #f1f5f9;
                color: #0f172a;
            }
            .cs-btn--ghost {
                background: transparent;
                color: #64748b;
            }
            .cs-wizard__footer {
                display: flex;
                justify-content: space-between;
                padding: 24px 0;
                margin-top: auto;
                border-top: 1px solid #e5e7eb;
                flex-shrink: 0;
                background: #fff;
            }
            .cs-success {
                text-align: center;
                padding: 48px 24px;
            }
            .cs-success__icon {
                width: 88px;
                height: 88px;
                background: linear-gradient(135deg, #10b981 0%, #059669 100%);
                color: #fff;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 28px;
                box-shadow: 0 8px 24px rgba(16,185,129,0.3);
            }
            .cs-success h2 {
                font-size: 28px;
                font-weight: 700;
                color: #0f172a;
                margin: 0 0 8px;
            }
            .cs-success p {
                font-size: 16px;
                color: #64748b;
                margin: 0 0 28px;
            }
            .cs-success__actions {
                display: flex;
                gap: 12px;
                justify-content: center;
                margin-bottom: 24px;
            }
            .cs-link {
                font-size: 14px;
                color: #64748b;
                text-decoration: none;
            }
            @media (max-width: 1024px) {
                .cs-wizard {
                    flex-direction: column;
                    height: auto;
                    min-height: 100vh;
                }
                .cs-wizard__hero {
                    width: 100%;
                    padding: 48px 32px;
                }
                .cs-wizard__hero h1 {
                    font-size: 32px;
                }
                .cs-wizard__form {
                    width: 100%;
                    height: auto;
                    flex: 1;
                }
                .cs-type-grid {
                    grid-template-columns: 1fr;
                }
            }
            @media (max-width: 640px) {
                .cs-row {
                    flex-direction: column;
                    gap: 0;
                }
                .cs-images-grid {
                    grid-template-columns: repeat(2, 1fr);
                }
            }
        </style>' . InstantImages::render_search_styles( 'cs', '#10b981' );
    }

    /**
     * Render scripts
     */
    private static function render_scripts(): string {
        $headline_options = [
            'first_time_buyer' => [
                'Ready to Buy Your First Home?',
                'First Home? We Can Help!',
                'Your Dream Home Awaits',
                'Start Your Homeownership Journey',
            ],
            'move_up_buyer' => [
                'Ready for Your Next Chapter?',
                'Time to Upgrade Your Space?',
                'Find Your Forever Home',
                'More Space for Your Growing Family',
            ],
            'downsizer' => [
                'Ready to Simplify?',
                'Right-Size Your Life',
                'Less Space, More Freedom',
                'Your Perfect-Fit Home Awaits',
            ],
            'investor' => [
                'Build Your Real Estate Portfolio',
                'Smart Investing Starts Here',
                'Grow Your Wealth Through Property',
                'Your Next Investment Property',
            ],
            'relocating' => [
                'Welcome to Your New City!',
                'Making Your Move Easy',
                'Find Home in a New Place',
                'Your Relocation Experts',
            ],
            'veteran' => [
                'Thank You for Your Service',
                'VA Home Loan Benefits Await',
                'Serving Those Who Served',
                'Your Military Home Benefits',
            ],
        ];

        $stock_images = [
            'first_time_buyer' => [
                'https://images.unsplash.com/photo-1560518883-ce09059eeffa?w=600&h=400&fit=crop',
                'https://images.unsplash.com/photo-1582268611958-ebfd161ef9cf?w=600&h=400&fit=crop',
                'https://images.unsplash.com/photo-1570129477492-45c003edd2be?w=600&h=400&fit=crop',
            ],
            'move_up_buyer' => [
                'https://images.unsplash.com/photo-1600596542815-ffad4c1539a9?w=600&h=400&fit=crop',
                'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?w=600&h=400&fit=crop',
                'https://images.unsplash.com/photo-1613490493576-7fde63acd811?w=600&h=400&fit=crop',
            ],
            'downsizer' => [
                'https://images.unsplash.com/photo-1502672260266-1c1ef2d93688?w=600&h=400&fit=crop',
                'https://images.unsplash.com/photo-1484154218962-a197022b5858?w=600&h=400&fit=crop',
                'https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?w=600&h=400&fit=crop',
            ],
            'investor' => [
                'https://images.unsplash.com/photo-1560518883-ce09059eeffa?w=600&h=400&fit=crop',
                'https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?w=600&h=400&fit=crop',
                'https://images.unsplash.com/photo-1560520653-9e0e4c89eb11?w=600&h=400&fit=crop',
            ],
            'relocating' => [
                'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=600&h=400&fit=crop',
                'https://images.unsplash.com/photo-1600047509807-ba8f99d2cdde?w=600&h=400&fit=crop',
                'https://images.unsplash.com/photo-1600573472591-ee6981cf35b6?w=600&h=400&fit=crop',
            ],
            'veteran' => [
                'https://images.unsplash.com/photo-1560518883-ce09059eeffa?w=600&h=400&fit=crop',
                'https://images.unsplash.com/photo-1600596542815-ffad4c1539a9?w=600&h=400&fit=crop',
                'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?w=600&h=400&fit=crop',
            ],
        ];

        $type_labels = [
            'first_time_buyer' => 'First-Time Buyer',
            'move_up_buyer' => 'Move-Up Buyer',
            'downsizer' => 'Downsizer',
            'investor' => 'Investor',
            'relocating' => 'Relocating',
            'veteran' => 'Veteran',
        ];

        return '
        <script>
        document.addEventListener("DOMContentLoaded", function() {
            const wizard = document.getElementById("cs-wizard");
            if (!wizard) return;

            const headlineOptions = ' . wp_json_encode( $headline_options ) . ';
            const stockImages = ' . wp_json_encode( $stock_images ) . ';
            const typeLabels = ' . wp_json_encode( $type_labels ) . ';

            const steps = wizard.querySelectorAll(".cs-step[data-step]");
            const progressBar = wizard.querySelector(".cs-wizard__progress-bar");
            const stepNum = document.getElementById("cs-step-num");
            const backBtn = document.getElementById("cs-back");
            const nextBtn = document.getElementById("cs-next");
            const publishBtn = document.getElementById("cs-publish");
            const backBtnTop = document.getElementById("cs-back-top");
            const nextBtnTop = document.getElementById("cs-next-top");

            let currentStep = 0;
            let data = {
                loanOfficer: {},
                spotlightType: "",
                customize: {},
                questions: {},
                branding: {}
            };

            // Dropdown handling
            document.querySelectorAll(".cs-dropdown").forEach(dropdown => {
                const trigger = dropdown.querySelector(".cs-dropdown__trigger");
                const menu = dropdown.querySelector(".cs-dropdown__menu");
                const items = dropdown.querySelectorAll(".cs-dropdown__item");
                const hiddenInput = dropdown.querySelector("input[type=hidden]");
                const valueDisplay = dropdown.querySelector(".cs-dropdown__value");

                function positionMenu() {
                    const rect = trigger.getBoundingClientRect();
                    menu.style.top = (rect.bottom + 4) + "px";
                    menu.style.left = rect.left + "px";
                    menu.style.width = rect.width + "px";
                }

                trigger.addEventListener("click", (e) => {
                    e.stopPropagation();
                    dropdown.classList.toggle("open");
                    if (dropdown.classList.contains("open")) positionMenu();
                });

                items.forEach(item => {
                    item.addEventListener("click", () => {
                        items.forEach(i => i.classList.remove("selected"));
                        item.classList.add("selected");
                        hiddenInput.value = item.dataset.value;
                        valueDisplay.textContent = item.querySelector(".cs-dropdown__name").textContent;
                        dropdown.classList.remove("open");

                        if (item.dataset.name) hiddenInput.dataset.name = item.dataset.name;
                        if (item.dataset.nmls) hiddenInput.dataset.nmls = item.dataset.nmls;
                        if (item.dataset.photo) hiddenInput.dataset.photo = item.dataset.photo;
                        if (item.dataset.email) hiddenInput.dataset.email = item.dataset.email;
                        if (item.dataset.phone) hiddenInput.dataset.phone = item.dataset.phone;
                    });
                });
            });

            document.addEventListener("click", () => {
                document.querySelectorAll(".cs-dropdown.open").forEach(d => d.classList.remove("open"));
            });

            // Update headline options when spotlight type changes
            wizard.querySelectorAll("input[name=\"cs-spotlight-type\"]").forEach(radio => {
                radio.addEventListener("change", function() {
                    data.spotlightType = this.value;
                    updateHeadlineOptions(this.value);
                    updateStockImages(this.value);
                });
            });

            function updateHeadlineOptions(type) {
                const container = document.getElementById("cs-headline-options");
                const options = headlineOptions[type] || [];
                container.innerHTML = options.map((opt, i) => `
                    <label class="cs-radio-btn">
                        <input type="radio" name="cs-headline" value="${opt}" ${i === 0 ? "checked" : ""}>
                        <span class="cs-radio-btn__label">${opt}</span>
                    </label>
                `).join("") + `
                    <label class="cs-radio-btn">
                        <input type="radio" name="cs-headline" value="custom">
                        <span class="cs-radio-btn__label">Custom...</span>
                    </label>
                `;

                container.querySelectorAll("input").forEach(radio => {
                    radio.addEventListener("change", function() {
                        const customInput = document.getElementById("cs-headline-custom");
                        customInput.style.display = this.value === "custom" ? "block" : "none";
                    });
                });
            }

            function updateStockImages(type) {
                const grid = document.getElementById("cs-images-grid");
                const images = stockImages[type] || stockImages.first_time_buyer;
                grid.innerHTML = images.map((img, i) => `
                    <div class="cs-image-option ${i === 0 ? "cs-image-option--selected" : ""}" data-url="${img}">
                        <img src="${img}" alt="Stock image">
                    </div>
                `).join("");

                document.getElementById("cs-hero-image").value = images[0];

                grid.querySelectorAll(".cs-image-option").forEach(opt => {
                    opt.addEventListener("click", () => {
                        grid.querySelectorAll(".cs-image-option").forEach(o => o.classList.remove("cs-image-option--selected"));
                        opt.classList.add("cs-image-option--selected");
                        document.getElementById("cs-hero-image").value = opt.dataset.url;
                    });
                });
            }

            function showStep(step) {
                steps.forEach(s => s.style.display = "none");
                const target = wizard.querySelector(`[data-step="${step}"]`);
                if (target) target.style.display = "flex";

                const progress = ((step + 1) / 8) * 100;
                progressBar.style.width = progress + "%";
                stepNum.textContent = step + 1;

                backBtn.style.display = step > 0 ? "inline-flex" : "none";
                nextBtn.style.display = step < 7 ? "inline-flex" : "none";
                publishBtn.style.display = step === 7 ? "inline-flex" : "none";

                // Update top buttons too
                if (backBtnTop) backBtnTop.style.display = step > 0 ? "inline-flex" : "none";
                if (nextBtnTop) nextBtnTop.style.display = step < 7 ? "inline-flex" : "none";

                if (step === 6) updateLoPreview();
                if (step === 7) updateSummary();
            }

            function validateStep(step) {
                if (step === 0) {
                    const lo = document.getElementById("cs-loan-officer");
                    if (!lo.value) {
                        alert("Please select a loan officer");
                        return false;
                    }
                    data.loanOfficer = {
                        id: lo.value,
                        name: lo.dataset.name,
                        nmls: lo.dataset.nmls,
                        photo: lo.dataset.photo,
                        email: lo.dataset.email,
                        phone: lo.dataset.phone
                    };
                }
                if (step === 1) {
                    const typeRadio = wizard.querySelector("input[name=\"cs-spotlight-type\"]:checked");
                    if (!typeRadio) {
                        alert("Please select a spotlight type");
                        return false;
                    }
                    data.spotlightType = typeRadio.value;
                }
                if (step === 2) {
                    const headlineRadio = wizard.querySelector("input[name=\"cs-headline\"]:checked");
                    const headline = headlineRadio ? (headlineRadio.value === "custom" ? document.getElementById("cs-headline-custom").value : headlineRadio.value) : "";
                    if (!headline) {
                        alert("Please select or enter a headline");
                        return false;
                    }
                    data.customize = {
                        headline: headline,
                        subheadline: document.getElementById("cs-subheadline").value,
                        valueProps: document.getElementById("cs-value-props").value
                    };
                }
                if (step === 3) {
                    const heroImg = document.getElementById("cs-hero-image").value;
                    if (!heroImg) {
                        alert("Please select a hero image");
                        return false;
                    }
                    data.heroImage = heroImg;
                }
                if (step === 4) {
                    data.questions = {
                        comments: wizard.querySelector("[name=cs-q-comments]")?.checked || false,
                        agent: wizard.querySelector("[name=cs-q-agent]")?.checked || false,
                        preapproved: wizard.querySelector("[name=cs-q-preapproved]")?.checked || false,
                        interested: wizard.querySelector("[name=cs-q-interested]")?.checked || false,
                        timeline: wizard.querySelector("[name=cs-q-timeline]")?.checked || false,
                        pricerange: wizard.querySelector("[name=cs-q-pricerange]")?.checked || false
                    };
                }
                if (step === 5) {
                    data.branding = {
                        realtorName: document.getElementById("cs-realtor-name").value,
                        realtorLicense: document.getElementById("cs-realtor-license").value,
                        realtorPhone: document.getElementById("cs-realtor-phone").value,
                        realtorEmail: document.getElementById("cs-realtor-email").value
                    };
                }
                return true;
            }

            function updateLoPreview() {
                const preview = document.getElementById("cs-lo-preview");
                if (data.loanOfficer.name) {
                    preview.innerHTML = `
                        <img src="${data.loanOfficer.photo || ""}" alt="">
                        <div class="cs-lo-preview__info">
                            <h4>${data.loanOfficer.name}</h4>
                            <p>NMLS# ${data.loanOfficer.nmls}</p>
                        </div>
                    `;
                }
            }

            function updateSummary() {
                const summary = document.getElementById("cs-summary");
                summary.innerHTML = `
                    <div class="cs-summary__row">
                        <span class="cs-summary__label">Spotlight Type</span>
                        <span class="cs-summary__value">${typeLabels[data.spotlightType] || data.spotlightType}</span>
                    </div>
                    <div class="cs-summary__row">
                        <span class="cs-summary__label">Headline</span>
                        <span class="cs-summary__value">${data.customize.headline}</span>
                    </div>
                    <div class="cs-summary__row">
                        <span class="cs-summary__label">Loan Officer</span>
                        <span class="cs-summary__value">${data.loanOfficer.name}</span>
                    </div>
                `;
            }

            nextBtn.addEventListener("click", function() {
                if (validateStep(currentStep)) {
                    currentStep++;
                    showStep(currentStep);
                }
            });

            backBtn.addEventListener("click", function() {
                currentStep--;
                showStep(currentStep);
            });

            // Top button listeners
            if (nextBtnTop) {
                nextBtnTop.addEventListener("click", function() {
                    if (validateStep(currentStep)) {
                        currentStep++;
                        showStep(currentStep);
                    }
                });
            }
            if (backBtnTop) {
                backBtnTop.addEventListener("click", function() {
                    currentStep--;
                    showStep(currentStep);
                });
            }

            // Publish
            publishBtn.addEventListener("click", async () => {
                publishBtn.querySelector(".cs-btn__text").style.display = "none";
                publishBtn.querySelector(".cs-btn__loading").style.display = "inline";

                try {
                    const response = await fetch("' . admin_url( 'admin-ajax.php' ) . '", {
                        method: "POST",
                        headers: { "Content-Type": "application/x-www-form-urlencoded" },
                        body: new URLSearchParams({
                            action: "frs_create_spotlight",
                            nonce: "' . wp_create_nonce( 'frs_create_spotlight' ) . '",
                            data: JSON.stringify(data)
                        })
                    });
                    const result = await response.json();

                    if (result.success) {
                        document.getElementById("cs-success-type").textContent = typeLabels[data.spotlightType] + " Spotlight";
                        document.getElementById("cs-success-link").href = result.data.url;
                        document.getElementById("cs-copy-link").onclick = () => {
                            navigator.clipboard.writeText(result.data.url);
                            alert("Link copied!");
                        };
                        showStep("success");
                        wizard.querySelector(".cs-wizard__footer").style.display = "none";
                    } else {
                        alert(result.data || "Failed to create page");
                    }
                } catch (e) {
                    alert("An error occurred");
                }

                publishBtn.querySelector(".cs-btn__text").style.display = "inline";
                publishBtn.querySelector(".cs-btn__loading").style.display = "none";
            });

            // Image upload
            const imageUpload = document.getElementById("cs-image-upload");
            if (imageUpload) imageUpload.addEventListener("change", (e) => {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = (ev) => {
                        document.getElementById("cs-hero-image").value = ev.target.result;
                        const grid = document.getElementById("cs-images-grid");
                        grid.querySelectorAll(".cs-image-option").forEach(o => o.classList.remove("cs-image-option--selected"));
                    };
                    reader.readAsDataURL(file);
                }
            });

            showStep(0);
        });
        </script>' . InstantImages::render_search_scripts( 'cs', 'cs-hero-image', 'cs-images-grid' );
    }

    /**
     * AJAX: Create spotlight page
     */
    public static function ajax_create_spotlight() {
        check_ajax_referer( 'frs_create_spotlight', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( 'Not logged in' );
        }

        $data = json_decode( stripslashes( $_POST['data'] ?? '{}' ), true );

        if ( empty( $data['spotlightType'] ) ) {
            wp_send_json_error( 'Missing spotlight type' );
        }

        $type_labels = [
            'first_time_buyer' => 'First-Time Buyer',
            'move_up_buyer' => 'Move-Up Buyer',
            'downsizer' => 'Downsizer',
            'investor' => 'Investor',
            'relocating' => 'Relocating',
            'veteran' => 'Veteran',
        ];

        $type_label = $type_labels[ $data['spotlightType'] ] ?? ucwords( str_replace( '_', ' ', $data['spotlightType'] ) );

        // Create the landing page
        $page_id = wp_insert_post([
            'post_type'   => 'frs_lead_page',
            'post_title'  => 'Spotlight: ' . $type_label,
            'post_status' => 'publish',
            'post_author' => get_current_user_id(),
        ]);

        if ( is_wp_error( $page_id ) ) {
            wp_send_json_error( $page_id->get_error_message() );
        }

        // Save meta
        update_post_meta( $page_id, '_frs_page_type', 'customer_spotlight' );
        update_post_meta( $page_id, '_frs_spotlight_type', $data['spotlightType'] );
        update_post_meta( $page_id, '_frs_headline', $data['customize']['headline'] ?? '' );
        update_post_meta( $page_id, '_frs_subheadline', $data['customize']['subheadline'] ?? '' );
        update_post_meta( $page_id, '_frs_value_props', $data['customize']['valueProps'] ?? '' );
        update_post_meta( $page_id, '_frs_hero_image_url', $data['heroImage'] ?? '' );
        update_post_meta( $page_id, '_frs_loan_officer_id', $data['loanOfficer']['id'] ?? '' );
        update_post_meta( $page_id, '_frs_realtor_id', get_current_user_id() );
        update_post_meta( $page_id, '_frs_realtor_name', $data['branding']['realtorName'] ?? '' );
        update_post_meta( $page_id, '_frs_realtor_phone', $data['branding']['realtorPhone'] ?? '' );
        update_post_meta( $page_id, '_frs_realtor_email', $data['branding']['realtorEmail'] ?? '' );
        update_post_meta( $page_id, '_frs_realtor_license', $data['branding']['realtorLicense'] ?? '' );
        update_post_meta( $page_id, '_frs_enabled_questions', $data['questions'] ?? [] );
        update_post_meta( $page_id, '_frs_page_views', 0 );

        wp_send_json_success([
            'id'  => $page_id,
            'url' => get_permalink( $page_id ),
        ]);
    }

    /**
     * Render login required
     */
    private static function render_login_required(): string {
        return '<div class="cs-wizard" style="text-align:center;padding:48px;">
            <h2>Login Required</h2>
            <p>Please log in to create a Customer Spotlight page.</p>
            <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '" class="cs-btn cs-btn--primary">Log In</a>
        </div>';
    }

    /**
     * Render access denied
     */
    private static function render_access_denied(): string {
        return '<div class="cs-wizard" style="text-align:center;padding:48px;">
            <h2>Access Denied</h2>
            <p>You do not have permission to create Customer Spotlight pages.</p>
        </div>';
    }

    /**
     * Render modal-specific styles
     */
    private static function render_modal_styles(): string {
        return '
        <style>
            .cs-modal {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                z-index: 99999;
                display: none;
            }
            .cs-modal.cs-modal--open {
                display: flex;
            }
            .cs-modal__backdrop {
                display: none;
            }
            .cs-modal__container {
                width: 100vw;
                height: 100vh;
                overflow-y: auto;
            }
            .cs-modal__close {
                position: fixed;
                top: 24px;
                right: 24px;
                width: 48px;
                height: 48px;
                display: flex;
                align-items: center;
                justify-content: center;
                background: rgba(255,255,255,0.95);
                border: none;
                border-radius: 50%;
                cursor: pointer;
                color: #64748b;
                transition: all 0.2s;
                z-index: 100;
                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            }
            .cs-modal__close:hover {
                background: #fff;
                color: #0f172a;
                transform: scale(1.05);
            }
            body.cs-modal-open {
                overflow: hidden;
            }
        </style>';
    }

    /**
     * Render modal-specific scripts
     */
    private static function render_modal_scripts(): string {
        $trigger_class = self::TRIGGER_CLASS;
        $trigger_hash = self::TRIGGER_HASH;

        return '
        <script>
        (function() {
            const modal = document.getElementById("cs-wizard-modal");
            if (!modal) return;

            const backdrop = modal.querySelector(".cs-modal__backdrop");
            const closeBtn = modal.querySelector(".cs-modal__close");
            const triggerClass = "' . $trigger_class . '";
            const triggerHash = "' . $trigger_hash . '";

            function openModal() {
                modal.classList.add("cs-modal--open");
                document.body.classList.add("cs-modal-open");
            }

            function closeModal() {
                modal.classList.remove("cs-modal--open");
                document.body.classList.remove("cs-modal-open");
                if (window.location.hash === "#" + triggerHash) {
                    history.replaceState(null, null, window.location.pathname + window.location.search);
                }
            }

            closeBtn.addEventListener("click", closeModal);
            backdrop.addEventListener("click", closeModal);

            document.addEventListener("keydown", (e) => {
                if (e.key === "Escape" && modal.classList.contains("cs-modal--open")) {
                    closeModal();
                }
            });

            document.addEventListener("click", (e) => {
                if (e.target.classList.contains(triggerClass) || e.target.closest("." + triggerClass)) {
                    e.preventDefault();
                    openModal();
                }
            });

            function checkHash() {
                if (window.location.hash === "#" + triggerHash) {
                    openModal();
                }
            }

            checkHash();
            window.addEventListener("hashchange", checkHash);
        })();
        </script>';
    }
}
