<?php
/**
 * Apply Now Wizard
 *
 * Multi-step wizard for creating Apply Now landing pages.
 * Integrates FluentForms for application and Fluent Booking for scheduling.
 *
 * @package FRSLeadPages
 */

namespace FRSLeadPages\ApplyNow;

use FRSLeadPages\Core\LoanOfficers;
use FRSLeadPages\Core\Realtors;
use FRSLeadPages\Core\UserMode;

class Wizard {

    /**
     * Trigger class for opening modal
     */
    const TRIGGER_CLASS = 'an-wizard-trigger';

    /**
     * Hash for URL triggering
     */
    const TRIGGER_HASH = 'apply-now-wizard';

    /**
     * Initialize
     */
    public static function init() {
        add_shortcode( 'apply_now_wizard', [ __CLASS__, 'render' ] );
        add_shortcode( 'apply_now_wizard_button', [ __CLASS__, 'render_button' ] );
        add_action( 'wp_ajax_frs_create_apply_now', [ __CLASS__, 'ajax_create_apply_now' ] );

        // Add modal to footer on frontend
        add_action( 'wp_footer', [ __CLASS__, 'render_modal_container' ] );
    }

    /**
     * Render trigger button shortcode
     */
    public static function render_button( array $atts = [] ): string {
        $atts = shortcode_atts([
            'text'  => 'Create Apply Now Page',
            'class' => '',
        ], $atts, 'apply_now_wizard_button' );

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
        <div id="an-wizard-modal" class="an-modal">
            <div class="an-modal__backdrop"></div>
            <div class="an-modal__container">
                <button type="button" class="an-modal__close" aria-label="Close">
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
        // Determine user mode (Loan Officer or Realtor)
        $user_mode = UserMode::get_mode();
        $is_loan_officer = UserMode::is_loan_officer();
        $partner_config = UserMode::get_partner_step_config();

        // Get current user data for pre-fill
        $user = wp_get_current_user();
        $user_data = UserMode::get_current_user_data();
        $user_data['mode'] = $user_mode;
        $user_data['photo'] = get_avatar_url( $user->ID, [ 'size' => 200 ] );

        // Get partners based on user mode
        $partners = $partner_config['partners'];

        // Get available forms
        $forms = self::get_fluent_forms();

        // Get Fluent Booking calendars for current user
        $calendars = self::get_user_calendars();

        ob_start();
        ?>
        <div id="an-wizard" class="an-wizard" data-user='<?php echo esc_attr( wp_json_encode( $user_data ) ); ?>'>
            <div class="an-wizard__hero">
                <div class="an-wizard__hero-content">
                    <h1>Create Your<br>Apply Now Page</h1>
                    <p>Build a loan application landing page with scheduling integration.</p>
                </div>
            </div>

            <div class="an-wizard__form">
                <div class="an-wizard__progress">
                    <div class="an-wizard__progress-bar" style="width: 25%"></div>
                </div>

                <div class="an-wizard__header">
                    <p class="an-wizard__title">Apply Now Wizard</p>
                    <p class="an-wizard__subtitle">Step <span id="an-step-num">1</span> of 4</p>
                </div>

                <div class="an-wizard__nav-top">
                    <button type="button" id="an-back-top" class="an-btn an-btn--ghost an-btn--sm" style="display:none;">Back</button>
                    <button type="button" id="an-next-top" class="an-btn an-btn--primary an-btn--sm">Continue</button>
                </div>

                <div class="an-wizard__content">
                <!-- Step 0: Page Type Selection -->
                <div class="an-step" data-step="0">
                    <div class="an-step__header">
                        <h2><?php echo $is_loan_officer ? 'What type of page?' : esc_html( $partner_config['title'] ); ?></h2>
                        <p><?php echo $is_loan_officer ? 'Choose how you want to brand this page' : esc_html( $partner_config['subtitle'] ); ?></p>
                    </div>
                    <div class="an-step__body">
                        <?php if ( $is_loan_officer ) : ?>
                            <input type="hidden" id="an-page-type" name="page_type" value="">
                            <input type="hidden" id="an-partner" name="partner" value="">

                            <div class="an-page-type-cards">
                                <div class="an-page-type-card" data-type="solo">
                                    <div class="an-page-type-card__icon">
                                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                            <circle cx="12" cy="8" r="4"/>
                                            <path d="M6 21v-2a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v2"/>
                                        </svg>
                                    </div>
                                    <h3>Solo Page</h3>
                                    <p>Just your branding</p>
                                </div>
                                <div class="an-page-type-card" data-type="cobranded">
                                    <div class="an-page-type-card__icon">
                                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                            <circle cx="9" cy="8" r="3.5"/>
                                            <circle cx="15" cy="8" r="3.5"/>
                                            <path d="M3 21v-2a4 4 0 0 1 4-4h2"/>
                                            <path d="M15 15h2a4 4 0 0 1 4 4v2"/>
                                        </svg>
                                    </div>
                                    <h3>Co-branded</h3>
                                    <p>With a partner</p>
                                </div>
                            </div>

                            <div id="an-partner-selection" class="an-partner-selection" style="display: none;">
                                <label class="an-label" style="margin-top: 24px;">Select Partner</label>
                                <div class="an-dropdown" id="an-partner-dropdown" data-mode="<?php echo esc_attr( $user_mode ); ?>" data-required="false">
                                    <button type="button" class="an-dropdown__trigger">
                                        <span class="an-dropdown__value">Choose a partner...</span>
                                        <svg class="an-dropdown__arrow" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
                                    </button>
                                    <div class="an-dropdown__menu">
                                        <?php foreach ( $partners as $partner ) : ?>
                                            <?php
                                            $partner_id = $partner['user_id'] ?? $partner['id'];
                                            $partner_name = $partner['name'];
                                            $partner_photo = $partner['photo_url'] ?? '';
                                            $partner_license = $partner['license'] ?? '';
                                            $partner_company = $partner['company'] ?? '';
                                            ?>
                                            <div class="an-dropdown__item"
                                                 data-value="<?php echo esc_attr( $partner_id ); ?>"
                                                 data-name="<?php echo esc_attr( $partner_name ); ?>"
                                                 data-license="<?php echo esc_attr( $partner_license ); ?>"
                                                 data-company="<?php echo esc_attr( $partner_company ); ?>"
                                                 data-photo="<?php echo esc_attr( $partner_photo ); ?>">
                                                <img src="<?php echo esc_url( $partner_photo ); ?>" alt="" class="an-dropdown__photo">
                                                <div class="an-dropdown__info">
                                                    <span class="an-dropdown__name"><?php echo esc_html( $partner_name ); ?></span>
                                                    <?php if ( $partner_company ) : ?>
                                                        <span class="an-dropdown__nmls"><?php echo esc_html( $partner_company ); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <p class="an-helper">Select a real estate partner for co-branding</p>
                            </div>
                        <?php else : ?>
                            <label class="an-label"><?php echo esc_html( $partner_config['label'] ); ?></label>
                            <div class="an-dropdown" id="an-partner-dropdown" data-mode="<?php echo esc_attr( $user_mode ); ?>" data-required="true" data-preferred="<?php echo esc_attr( $partner_config['preferred_id'] ?? 0 ); ?>">
                                <input type="hidden" id="an-partner" name="partner" value="">
                                <button type="button" class="an-dropdown__trigger">
                                    <span class="an-dropdown__value"><?php echo esc_html( $partner_config['placeholder'] ); ?></span>
                                    <svg class="an-dropdown__arrow" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
                                </button>
                                <div class="an-dropdown__menu">
                                    <?php foreach ( $partners as $partner ) : ?>
                                        <?php
                                        $partner_id = $partner['user_id'] ?? $partner['id'];
                                        $partner_name = $partner['name'];
                                        $partner_photo = $partner['photo_url'] ?? '';
                                        $partner_nmls = $partner['nmls'] ?? '';
                                        $is_preferred = ( (int) $partner_id === (int) ( $partner_config['preferred_id'] ?? 0 ) );
                                        ?>
                                        <div class="an-dropdown__item<?php echo $is_preferred ? ' an-dropdown__item--preferred' : ''; ?>"
                                             data-value="<?php echo esc_attr( $partner_id ); ?>"
                                             data-name="<?php echo esc_attr( $partner_name ); ?>"
                                             data-nmls="<?php echo esc_attr( $partner_nmls ); ?>"
                                             data-photo="<?php echo esc_attr( $partner_photo ); ?>">
                                            <img src="<?php echo esc_url( $partner_photo ); ?>" alt="" class="an-dropdown__photo">
                                            <div class="an-dropdown__info">
                                                <span class="an-dropdown__name"><?php echo esc_html( $partner_name ); ?></span>
                                                <?php if ( $partner_nmls ) : ?>
                                                    <span class="an-dropdown__nmls">NMLS# <?php echo esc_html( $partner_nmls ); ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if ( $is_preferred ) : ?>
                                                <span class="an-dropdown__preferred-badge">★ Preferred</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <p class="an-helper"><?php echo esc_html( $partner_config['helper'] ); ?></p>

                            <?php if ( $partner_config['show_remember'] ?? false ) : ?>
                                <label class="an-checkbox" style="margin-top: 12px;">
                                    <input type="checkbox" id="an-remember-partner" name="remember_partner" value="1">
                                    <span class="an-checkbox__label">Remember my choice for next time</span>
                                </label>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Step 1: Page Content -->
                <div class="an-step" data-step="1" style="display:none;">
                    <div class="an-step__header">
                        <h2>Page Content</h2>
                        <p>Set your headline and description</p>
                    </div>
                    <div class="an-step__body">
                        <div class="an-field">
                            <label class="an-label">Headline</label>
                            <input type="text" id="an-headline" class="an-input" value="Apply Now - Start Your Home Loan Journey" placeholder="Enter headline">
                        </div>
                        <div class="an-field">
                            <label class="an-label">Subheadline <span class="an-label-hint">(optional)</span></label>
                            <input type="text" id="an-subheadline" class="an-input" value="Quick and easy application process" placeholder="Enter subheadline">
                        </div>
                    </div>
                </div>

                <!-- Step 2: Scheduling Options -->
                <div class="an-step" data-step="2" style="display:none;">
                    <div class="an-step__header">
                        <h2>Scheduling Integration</h2>
                        <p>Choose how visitors can connect with you</p>
                    </div>
                    <div class="an-step__body">
                        <input type="hidden" id="an-schedule-type" name="schedule_type" value="form">

                        <div class="an-schedule-options">
                            <div class="an-schedule-card" data-type="form">
                                <div class="an-schedule-card__icon">
                                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                        <polyline points="14 2 14 8 20 8"/>
                                        <line x1="16" y1="13" x2="8" y2="13"/>
                                        <line x1="16" y1="17" x2="8" y2="17"/>
                                        <polyline points="10 9 9 9 8 9"/>
                                    </svg>
                                </div>
                                <h3>Application Form</h3>
                                <p>Collect info via Fluent Form</p>
                            </div>
                            <?php if ( ! empty( $calendars ) ) : ?>
                            <div class="an-schedule-card" data-type="booking">
                                <div class="an-schedule-card__icon">
                                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                        <rect x="3" y="4" width="18" height="18" rx="2"/>
                                        <line x1="16" y1="2" x2="16" y2="6"/>
                                        <line x1="8" y1="2" x2="8" y2="6"/>
                                        <line x1="3" y1="10" x2="21" y2="10"/>
                                        <circle cx="12" cy="15" r="2"/>
                                    </svg>
                                </div>
                                <h3>Booking Calendar</h3>
                                <p>Let visitors book a consultation</p>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Form Selection (shown when form is selected) -->
                        <div id="an-form-selection" class="an-form-selection">
                            <label class="an-label" style="margin-top: 24px;">Select Form</label>
                            <select id="an-form-id" class="an-select">
                                <option value="">-- Select a form --</option>
                                <?php foreach ( $forms as $form ) : ?>
                                    <option value="<?php echo esc_attr( $form['id'] ); ?>"><?php echo esc_html( $form['title'] ); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="an-helper">Choose a Fluent Form for your application</p>
                        </div>

                        <!-- Calendar Selection (shown when booking is selected) -->
                        <div id="an-calendar-selection" class="an-calendar-selection" style="display: none;">
                            <label class="an-label" style="margin-top: 24px;">Select Calendar</label>
                            <select id="an-calendar-id" class="an-select">
                                <option value="">-- Select a calendar --</option>
                                <?php foreach ( $calendars as $calendar ) : ?>
                                    <option value="<?php echo esc_attr( $calendar['id'] ); ?>"><?php echo esc_html( $calendar['title'] ); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="an-helper">Choose your Fluent Booking calendar</p>
                        </div>
                    </div>
                </div>

                <!-- Step 3: Your Branding -->
                <div class="an-step" data-step="3" style="display:none;">
                    <div class="an-step__header">
                        <h2>Your Branding</h2>
                        <p>Review and customize your profile info</p>
                    </div>
                    <div class="an-step__body">
                        <div class="an-branding-preview">
                            <div class="an-branding-photo">
                                <img id="an-preview-photo" src="<?php echo esc_url( $user_data['photo'] ); ?>" alt="">
                            </div>
                            <div class="an-branding-info">
                                <p class="an-branding-name" id="an-preview-name"><?php echo esc_html( $user_data['name'] ); ?></p>
                                <p class="an-branding-detail" id="an-preview-license"><?php echo esc_html( $is_loan_officer ? ( $user_data['nmls'] ?? '' ) : ( $user_data['license'] ?? '' ) ); ?></p>
                            </div>
                        </div>

                        <?php if ( $is_loan_officer ) : ?>
                            <!-- LO Mode: Show LO fields -->
                            <div class="an-field">
                                <label class="an-label">Display Name</label>
                                <input type="text" id="an-lo-name" class="an-input" value="<?php echo esc_attr( $user_data['name'] ); ?>">
                            </div>
                            <div class="an-field">
                                <label class="an-label">NMLS #</label>
                                <input type="text" id="an-lo-nmls" class="an-input" value="<?php echo esc_attr( $user_data['nmls'] ?? '' ); ?>">
                            </div>
                            <div class="an-field">
                                <label class="an-label">Contact Phone</label>
                                <input type="tel" id="an-lo-phone" class="an-input" value="<?php echo esc_attr( $user_data['phone'] ); ?>">
                            </div>
                            <div class="an-field">
                                <label class="an-label">Contact Email</label>
                                <input type="email" id="an-lo-email" class="an-input" value="<?php echo esc_attr( $user_data['email'] ); ?>">
                            </div>
                        <?php else : ?>
                            <!-- Realtor Mode: Show Realtor fields -->
                            <div class="an-field">
                                <label class="an-label">Display Name</label>
                                <input type="text" id="an-realtor-name" class="an-input" value="<?php echo esc_attr( $user_data['name'] ); ?>">
                            </div>
                            <div class="an-field">
                                <label class="an-label">License Number</label>
                                <input type="text" id="an-realtor-license" class="an-input" value="<?php echo esc_attr( $user_data['license'] ?? '' ); ?>">
                            </div>
                            <div class="an-field">
                                <label class="an-label">Contact Phone</label>
                                <input type="tel" id="an-realtor-phone" class="an-input" value="<?php echo esc_attr( $user_data['phone'] ); ?>">
                            </div>
                            <div class="an-field">
                                <label class="an-label">Contact Email</label>
                                <input type="email" id="an-realtor-email" class="an-input" value="<?php echo esc_attr( $user_data['email'] ); ?>">
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Success State -->
                <div class="an-step an-step--success" data-step="success" style="display:none;">
                    <div class="an-success">
                        <div class="an-success__icon">
                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                                <polyline points="22 4 12 14.01 9 11.01"/>
                            </svg>
                        </div>
                        <h2 id="an-success-title">Your Apply Now Page is Live!</h2>
                        <p id="an-success-subtitle">Share this link with your clients</p>
                        <div class="an-success__url-box">
                            <input type="text" id="an-success-url" readonly>
                            <button type="button" id="an-copy-url" class="an-btn an-btn--primary">Copy Link</button>
                        </div>
                        <div class="an-success__actions">
                            <a id="an-view-page" href="#" class="an-btn an-btn--secondary" target="_blank">View Page</a>
                            <button type="button" id="an-create-another" class="an-btn an-btn--ghost">Create Another</button>
                        </div>
                    </div>
                </div>

                </div>

                <div class="an-wizard__footer">
                    <button type="button" class="an-btn an-btn--secondary" id="an-prev-btn" style="display:none;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                        Back
                    </button>
                    <button type="button" class="an-btn an-btn--primary" id="an-next-btn">
                        Continue
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                    </button>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get user's Fluent Booking calendars
     */
    private static function get_user_calendars(): array {
        $calendars = [];

        if ( ! defined( 'FLUENT_BOOKING_VERSION' ) || ! class_exists( '\FluentBooking\App\Models\Calendar' ) ) {
            return $calendars;
        }

        $user_id = get_current_user_id();
        $user_calendars = \FluentBooking\App\Models\Calendar::where( 'user_id', $user_id )->get();

        foreach ( $user_calendars as $calendar ) {
            $calendars[] = [
                'id'    => $calendar->id,
                'title' => $calendar->title ?? 'Calendar #' . $calendar->id,
            ];
        }

        return $calendars;
    }

    /**
     * Get available Fluent Forms
     */
    private static function get_fluent_forms(): array {
        $forms = [];

        if ( ! defined( 'FLUENTFORM_VERSION' ) || ! class_exists( '\FluentForm\App\Models\Form' ) ) {
            return $forms;
        }

        $all_forms = \FluentForm\App\Models\Form::select( ['id', 'title'] )
            ->where( 'status', 'published' )
            ->orderBy( 'title', 'asc' )
            ->get();

        foreach ( $all_forms as $form ) {
            $forms[] = [
                'id'    => $form->id,
                'title' => $form->title,
            ];
        }

        return $forms;
    }

    /**
     * Render login required message
     */
    private static function render_login_required(): string {
        return '<div class="an-message an-message--warning">Please log in to create an apply now page.</div>';
    }

    /**
     * Render access denied message
     */
    private static function render_access_denied(): string {
        return '<div class="an-message an-message--error">You do not have permission to create apply now pages.</div>';
    }

    /**
     * Render modal styles
     */
    private static function render_modal_styles(): string {
        ob_start();
        ?>
        <style>
        /* Apply Now Wizard - Indigo Theme */
        :root {
            --an-primary: #6366f1;
            --an-primary-dark: #4f46e5;
            --an-primary-light: #818cf8;
            --an-primary-bg: #eef2ff;
            --an-text: #1e293b;
            --an-text-light: #64748b;
            --an-border: #e5e7eb;
            --an-white: #ffffff;
            --an-success: #10b981;
            --an-error: #ef4444;
        }

        /* Modal Overlay */
        .an-modal {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 99999;
            overflow-y: auto;
        }
        .an-modal.is-open {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .an-modal__backdrop {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.6);
            z-index: 1;
        }
        .an-modal__container {
            position: relative;
            z-index: 2;
            width: 100vw;
            height: 100vh;
            overflow-y: auto;
        }
        .an-modal__close {
            position: fixed;
            top: 24px;
            right: 24px;
            z-index: 10;
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255,255,255,0.95);
            border: none;
            border-radius: 50%;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transition: all 0.2s;
        }
        .an-modal__close:hover {
            background: var(--an-white);
            transform: scale(1.1);
        }
        .an-modal__close svg {
            color: var(--an-text);
        }

        /* Wizard Layout */
        .an-wizard {
            display: flex;
            min-height: 100vh;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }

        /* Hero Section */
        .an-wizard__hero {
            width: 50%;
            height: 100vh;
            background: linear-gradient(135deg, var(--an-primary) 0%, var(--an-primary-dark) 100%);
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 64px;
            position: fixed;
            left: 0;
            top: 0;
            overflow: hidden;
            color: var(--an-white);
        }
        .an-wizard__hero::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
        }
        .an-wizard__hero h1 {
            font-size: 42px;
            font-weight: 700;
            line-height: 1.1;
            margin: 0 0 16px 0;
            color: var(--an-white);
            position: relative;
        }
        .an-wizard__hero p {
            font-size: 18px;
            opacity: 0.9;
            margin: 0;
            line-height: 1.6;
            max-width: 400px;
            position: relative;
        }

        /* Form Section */
        .an-wizard__form {
            width: 50%;
            margin-left: 50%;
            min-height: 100vh;
            background: var(--an-white);
            padding: 48px 56px;
            box-sizing: border-box;
        }

        /* Progress Bar */
        .an-wizard__progress {
            height: 4px;
            margin-bottom: 32px;
            background: var(--an-border);
        }
        .an-wizard__progress-bar {
            height: 100%;
            background: var(--an-primary);
            transition: width 0.3s ease;
        }

        /* Header */
        .an-wizard__header {
            padding: 20px 0;
            border-bottom: 1px solid var(--an-border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }
        .an-wizard__title {
            font-size: 14px;
            font-weight: 600;
            color: var(--an-primary);
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .an-wizard__nav-top {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-bottom: 16px;
        }
        .an-btn--sm {
            padding: 8px 16px;
            font-size: 13px;
        }
        .an-wizard__subtitle {
            font-size: 14px;
            color: var(--an-text-light);
            margin: 0;
        }

        /* Step Sections */
        .an-step__header {
            margin-bottom: 24px;
        }
        .an-step__header h2 {
            font-size: 22px;
            font-weight: 700;
            color: var(--an-text);
            margin: 0 0 8px 0;
        }
        .an-step__header p {
            font-size: 15px;
            color: var(--an-text-light);
            margin: 0;
        }

        /* Form Elements */
        .an-label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: var(--an-text);
            margin-bottom: 8px;
        }
        .an-label-hint {
            font-weight: 400;
            color: var(--an-text-light);
        }
        .an-input,
        .an-select,
        .an-textarea {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--an-border);
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.2s;
            box-sizing: border-box;
        }
        .an-input:focus,
        .an-select:focus,
        .an-textarea:focus {
            outline: none;
            border-color: var(--an-primary);
            box-shadow: 0 0 0 3px var(--an-primary-bg);
        }
        .an-helper {
            font-size: 13px;
            color: var(--an-text-light);
            margin-top: 8px;
        }
        .an-field {
            margin-bottom: 20px;
        }

        /* Dropdown */
        .an-dropdown {
            position: relative;
        }
        .an-dropdown__trigger {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--an-border);
            border-radius: 10px;
            background: var(--an-white);
            font-size: 15px;
            text-align: left;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all 0.2s;
        }
        .an-dropdown__trigger:hover {
            border-color: var(--an-primary-light);
        }
        .an-dropdown.is-open .an-dropdown__trigger {
            border-color: var(--an-primary);
            box-shadow: 0 0 0 3px var(--an-primary-bg);
        }
        .an-dropdown__arrow {
            transition: transform 0.2s;
        }
        .an-dropdown.is-open .an-dropdown__arrow {
            transform: rotate(180deg);
        }
        .an-dropdown__menu {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            margin-top: 4px;
            background: var(--an-white);
            border: 2px solid var(--an-border);
            border-radius: 8px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            z-index: 100;
            max-height: 280px;
            overflow-y: auto;
        }
        .an-dropdown.is-open .an-dropdown__menu {
            display: block;
        }
        .an-dropdown__item {
            padding: 12px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            transition: background 0.15s;
        }
        .an-dropdown__item:hover {
            background: var(--an-primary-bg);
        }
        .an-dropdown__item.is-selected {
            background: var(--an-primary-bg);
        }
        .an-dropdown__photo {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }
        .an-dropdown__info {
            display: flex;
            flex-direction: column;
        }
        .an-dropdown__name {
            font-weight: 600;
            color: var(--an-text);
        }
        .an-dropdown__nmls {
            font-size: 13px;
            color: var(--an-text-light);
        }
        .an-dropdown__item--preferred {
            background: #fef3c7;
            border-left: 3px solid #f59e0b;
        }
        .an-dropdown__item--preferred:hover {
            background: #fde68a;
        }
        .an-dropdown__preferred-badge {
            margin-left: auto;
            font-size: 11px;
            font-weight: 600;
            color: #b45309;
            background: #fef3c7;
            padding: 2px 8px;
            border-radius: 10px;
        }
        .an-checkbox {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }
        .an-checkbox input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: var(--an-primary);
            cursor: pointer;
        }
        .an-checkbox__label {
            font-size: 14px;
            color: var(--an-text-light);
        }

        /* Page Type Cards (LO mode) */
        .an-page-type-cards { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 8px; }
        .an-page-type-card { border: 2px solid #e2e8f0; border-radius: 12px; padding: 24px 16px; text-align: center; cursor: pointer; transition: all 0.2s ease; background: #fff; }
        .an-page-type-card:hover { border-color: var(--an-primary-light); background: var(--an-primary-bg); }
        .an-page-type-card.selected { border-color: var(--an-primary); background: var(--an-primary-bg); box-shadow: 0 0 0 4px rgba(99,102,241,0.15); }
        .an-page-type-card__icon { width: 64px; height: 64px; margin: 0 auto 12px; display: flex; align-items: center; justify-content: center; background: var(--an-primary-bg); border-radius: 50%; }
        .an-page-type-card__icon svg { stroke: var(--an-primary); }
        .an-page-type-card.selected .an-page-type-card__icon { background: var(--an-primary); }
        .an-page-type-card.selected .an-page-type-card__icon svg { stroke: #fff; }
        .an-page-type-card h3 { font-size: 16px; font-weight: 600; color: var(--an-text); margin: 0 0 4px; }
        .an-page-type-card p { font-size: 13px; color: var(--an-text-light); margin: 0; }
        .an-partner-selection { margin-top: 16px; }

        /* Schedule Options */
        .an-schedule-options { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 8px; }
        .an-schedule-card { border: 2px solid #e2e8f0; border-radius: 12px; padding: 24px 16px; text-align: center; cursor: pointer; transition: all 0.2s ease; background: #fff; }
        .an-schedule-card:hover { border-color: var(--an-primary-light); background: var(--an-primary-bg); }
        .an-schedule-card.selected { border-color: var(--an-primary); background: var(--an-primary-bg); box-shadow: 0 0 0 4px rgba(99,102,241,0.15); }
        .an-schedule-card__icon { width: 64px; height: 64px; margin: 0 auto 12px; display: flex; align-items: center; justify-content: center; background: var(--an-primary-bg); border-radius: 50%; }
        .an-schedule-card__icon svg { stroke: var(--an-primary); }
        .an-schedule-card.selected .an-schedule-card__icon { background: var(--an-primary); }
        .an-schedule-card.selected .an-schedule-card__icon svg { stroke: #fff; }
        .an-schedule-card h3 { font-size: 16px; font-weight: 600; color: var(--an-text); margin: 0 0 4px; }
        .an-schedule-card p { font-size: 13px; color: var(--an-text-light); margin: 0; }

        /* Branding Preview */
        .an-branding-preview {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 20px;
            background: var(--an-primary-bg);
            border-radius: 12px;
            margin-bottom: 24px;
        }
        .an-branding-photo img {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--an-white);
        }
        .an-branding-name {
            font-size: 18px;
            font-weight: 700;
            color: var(--an-text);
            margin: 0 0 4px;
        }
        .an-branding-detail {
            font-size: 14px;
            color: var(--an-text-light);
            margin: 0;
        }

        /* Success State */
        .an-success {
            text-align: center;
            padding: 40px 20px;
        }
        .an-success__icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--an-success) 0%, #059669 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
        }
        .an-success__icon svg {
            stroke: var(--an-white);
        }
        .an-success h2 {
            font-size: 24px;
            font-weight: 700;
            color: var(--an-text);
            margin: 0 0 8px;
        }
        .an-success p {
            font-size: 15px;
            color: var(--an-text-light);
            margin: 0 0 24px;
        }
        .an-success__url-box {
            display: flex;
            gap: 8px;
            max-width: 500px;
            margin: 0 auto 24px;
        }
        .an-success__url-box input {
            flex: 1;
            padding: 12px 16px;
            border: 2px solid var(--an-border);
            border-radius: 8px;
            font-size: 14px;
            background: #f8fafc;
        }
        .an-success__actions {
            display: flex;
            gap: 12px;
            justify-content: center;
        }

        /* Footer */
        .an-wizard__footer {
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid var(--an-border);
            display: flex;
            justify-content: space-between;
            gap: 12px;
        }

        /* Buttons */
        .an-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
        }
        .an-btn--primary {
            background: var(--an-primary);
            color: var(--an-white);
        }
        .an-btn--primary:hover {
            background: var(--an-primary-dark);
        }
        .an-btn--primary:disabled {
            background: var(--an-border);
            cursor: not-allowed;
        }
        .an-btn--secondary {
            background: var(--an-white);
            color: var(--an-text);
            border: 2px solid var(--an-border);
        }
        .an-btn--secondary:hover {
            border-color: var(--an-primary);
            color: var(--an-primary);
        }
        .an-btn--ghost {
            background: transparent;
            color: var(--an-text-light);
            border: none;
        }
        .an-btn--ghost:hover {
            color: var(--an-primary);
        }

        /* Loading State */
        .an-btn.is-loading {
            pointer-events: none;
            opacity: 0.7;
        }
        .an-btn.is-loading::after {
            content: '';
            width: 16px;
            height: 16px;
            border: 2px solid transparent;
            border-top-color: currentColor;
            border-radius: 50%;
            animation: an-spin 0.8s linear infinite;
            margin-left: 8px;
        }
        @keyframes an-spin {
            to { transform: rotate(360deg); }
        }

        /* Messages */
        .an-message {
            padding: 16px 20px;
            border-radius: 8px;
            font-size: 14px;
        }
        .an-message--warning {
            background: #fef3c7;
            color: #92400e;
        }
        .an-message--error {
            background: #fee2e2;
            color: #991b1b;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .an-wizard {
                flex-direction: column;
                height: auto;
                min-height: 100vh;
            }
            .an-wizard__hero {
                width: 100%;
                height: auto;
                position: relative;
                padding: 48px 32px;
            }
            .an-wizard__hero h1 {
                font-size: 32px;
            }
            .an-wizard__form {
                width: 100%;
                margin-left: 0;
                padding: 32px;
            }
        }
        @media (max-width: 640px) {
            .an-wizard__hero {
                padding: 32px 24px;
            }
            .an-wizard__hero h1 {
                font-size: 28px;
            }
            .an-wizard__form {
                padding: 24px;
            }
            .an-page-type-cards,
            .an-schedule-options {
                grid-template-columns: 1fr;
            }
        }
        </style>
        <?php
        return ob_get_clean();
    }

    /**
     * Render modal scripts
     */
    private static function render_modal_scripts(): string {
        ob_start();
        ?>
        <script>
        (function() {
            const modal = document.getElementById('an-wizard-modal');
            const wizard = document.getElementById('an-wizard');
            if (!modal || !wizard) return;

            let currentStep = 0;
            const totalSteps = 4;
            let selectedPartner = null;
            let selectedScheduleType = 'form';

            // Get user data and mode
            const userData = JSON.parse(wizard.dataset.user || "{}");
            const userMode = userData.mode || "realtor";
            const isLoanOfficer = userMode === "loan_officer";

            // Page type card selection (LO mode)
            const pageTypeCards = wizard.querySelectorAll('.an-page-type-card');
            const pageTypeInput = document.getElementById('an-page-type');
            const partnerSelectionDiv = document.getElementById('an-partner-selection');
            const partnerInput = document.getElementById('an-partner');

            if (pageTypeCards.length > 0 && isLoanOfficer) {
                pageTypeCards.forEach(card => {
                    card.addEventListener('click', () => {
                        pageTypeCards.forEach(c => c.classList.remove('selected'));
                        card.classList.add('selected');
                        const pageType = card.dataset.type;
                        if (pageTypeInput) pageTypeInput.value = pageType;

                        if (partnerSelectionDiv) {
                            if (pageType === 'cobranded') {
                                partnerSelectionDiv.style.display = 'block';
                            } else {
                                partnerSelectionDiv.style.display = 'none';
                                if (partnerInput) partnerInput.value = '';
                                selectedPartner = null;
                                const dropdownValue = wizard.querySelector('#an-partner-dropdown .an-dropdown__value');
                                if (dropdownValue) dropdownValue.textContent = 'Choose a partner...';
                                wizard.querySelectorAll('#an-partner-dropdown .an-dropdown__item').forEach(i => i.classList.remove('is-selected'));
                            }
                        }
                    });
                });
            }

            // Schedule type card selection
            const scheduleCards = wizard.querySelectorAll('.an-schedule-card');
            const scheduleTypeInput = document.getElementById('an-schedule-type');
            const formSelection = document.getElementById('an-form-selection');
            const calendarSelection = document.getElementById('an-calendar-selection');

            if (scheduleCards.length > 0) {
                // Default select first card
                scheduleCards[0].classList.add('selected');

                scheduleCards.forEach(card => {
                    card.addEventListener('click', () => {
                        scheduleCards.forEach(c => c.classList.remove('selected'));
                        card.classList.add('selected');
                        selectedScheduleType = card.dataset.type;
                        if (scheduleTypeInput) scheduleTypeInput.value = selectedScheduleType;

                        // Toggle form/calendar selection
                        if (selectedScheduleType === 'form') {
                            formSelection.style.display = 'block';
                            calendarSelection.style.display = 'none';
                        } else {
                            formSelection.style.display = 'none';
                            calendarSelection.style.display = 'block';
                        }
                    });
                });
            }

            // Open modal
            document.querySelectorAll('.<?php echo self::TRIGGER_CLASS; ?>').forEach(btn => {
                btn.addEventListener('click', () => {
                    modal.classList.add('is-open');
                    document.body.style.overflow = 'hidden';
                });
            });

            // Check URL hash
            if (window.location.hash === '#<?php echo self::TRIGGER_HASH; ?>') {
                modal.classList.add('is-open');
                document.body.style.overflow = 'hidden';
            }

            // Close modal
            modal.querySelector('.an-modal__backdrop').addEventListener('click', closeModal);
            modal.querySelector('.an-modal__close').addEventListener('click', closeModal);

            function closeModal() {
                modal.classList.remove('is-open');
                document.body.style.overflow = '';
            }

            // Dropdown functionality
            const dropdown = document.getElementById('an-partner-dropdown');
            if (dropdown) {
                const trigger = dropdown.querySelector('.an-dropdown__trigger');
                const menu = dropdown.querySelector('.an-dropdown__menu');
                const items = dropdown.querySelectorAll('.an-dropdown__item');
                const input = document.getElementById('an-partner');
                const valueDisplay = dropdown.querySelector('.an-dropdown__value');

                trigger.addEventListener('click', () => {
                    dropdown.classList.toggle('is-open');
                });

                items.forEach(item => {
                    item.addEventListener('click', () => {
                        items.forEach(i => i.classList.remove('is-selected'));
                        item.classList.add('is-selected');
                        input.value = item.dataset.value;
                        selectedPartner = {
                            id: item.dataset.value,
                            name: item.dataset.name,
                            nmls: item.dataset.nmls || '',
                            license: item.dataset.license || '',
                            company: item.dataset.company || '',
                            photo: item.dataset.photo || '',
                            email: item.dataset.email || '',
                            phone: item.dataset.phone || ''
                        };
                        valueDisplay.innerHTML = `
                            <img src="${item.dataset.photo || ''}" style="width:24px;height:24px;border-radius:50%;margin-right:8px;">
                            ${item.dataset.name}
                        `;
                        dropdown.classList.remove('is-open');
                    });
                });

                document.addEventListener('click', (e) => {
                    if (!dropdown.contains(e.target)) {
                        dropdown.classList.remove('is-open');
                    }
                });

                // Auto-select preferred partner if set
                const preferredId = dropdown.dataset.preferred;
                if (preferredId && preferredId !== '0') {
                    const preferredItem = dropdown.querySelector(`.an-dropdown__item[data-value="${preferredId}"]`);
                    if (preferredItem) {
                        preferredItem.click();
                    }
                }
            }

            // Copy URL button
            const copyUrlBtn = document.getElementById('an-copy-url');
            if (copyUrlBtn) {
                copyUrlBtn.addEventListener('click', () => {
                    const urlInput = document.getElementById('an-success-url');
                    if (urlInput) {
                        navigator.clipboard.writeText(urlInput.value).then(() => {
                            const originalText = copyUrlBtn.textContent;
                            copyUrlBtn.textContent = 'Copied!';
                            setTimeout(() => {
                                copyUrlBtn.textContent = originalText;
                            }, 2000);
                        });
                    }
                });
            }

            // Create another button
            const createAnotherBtn = document.getElementById('an-create-another');
            if (createAnotherBtn) {
                createAnotherBtn.addEventListener('click', () => {
                    currentStep = 0;
                    selectedPartner = null;
                    selectedScheduleType = 'form';
                    goToStep(0);
                    document.querySelector('.an-wizard__footer').style.display = 'flex';
                });
            }

            // Navigation
            const prevBtn = document.getElementById('an-prev-btn');
            const nextBtn = document.getElementById('an-next-btn');
            const backBtnTop = document.getElementById('an-back-top');
            const nextBtnTop = document.getElementById('an-next-top');

            prevBtn.addEventListener('click', () => {
                if (currentStep > 0) {
                    goToStep(currentStep - 1);
                }
            });

            nextBtn.addEventListener('click', () => {
                if (validateStep()) {
                    if (currentStep < totalSteps - 1) {
                        goToStep(currentStep + 1);
                    } else {
                        submitWizard();
                    }
                }
            });

            if (nextBtnTop) {
                nextBtnTop.addEventListener('click', () => {
                    if (validateStep()) {
                        if (currentStep < totalSteps - 1) {
                            goToStep(currentStep + 1);
                        } else {
                            submitWizard();
                        }
                    }
                });
            }
            if (backBtnTop) {
                backBtnTop.addEventListener('click', () => {
                    if (currentStep > 0) {
                        goToStep(currentStep - 1);
                    }
                });
            }

            function goToStep(step) {
                document.querySelectorAll('.an-step').forEach(el => el.style.display = 'none');
                const stepEl = document.querySelector(`.an-step[data-step="${step}"]`);
                if (stepEl) stepEl.style.display = 'block';
                currentStep = step;

                // Update progress
                const progress = ((step + 1) / totalSteps) * 100;
                document.querySelector('.an-wizard__progress-bar').style.width = progress + '%';
                document.getElementById('an-step-num').textContent = step + 1;

                // Update buttons
                prevBtn.style.display = step === 0 ? 'none' : 'flex';
                if (backBtnTop) backBtnTop.style.display = step === 0 ? 'none' : 'inline-flex';
                if (nextBtnTop) nextBtnTop.style.display = step < totalSteps - 1 ? 'inline-flex' : 'none';

                if (step === totalSteps - 1) {
                    nextBtn.innerHTML = 'Create Page <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>';
                } else {
                    nextBtn.innerHTML = 'Continue <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>';
                }
            }

            function validateStep() {
                if (currentStep === 0) {
                    if (isLoanOfficer) {
                        const pageType = document.getElementById('an-page-type')?.value;
                        if (!pageType) {
                            alert('Please select Solo Page or Co-branded');
                            return false;
                        }
                        if (pageType === 'cobranded' && !selectedPartner) {
                            alert('Please select a partner for co-branding');
                            return false;
                        }
                    } else {
                        const partnerDropdown = document.getElementById('an-partner-dropdown');
                        const isRequired = partnerDropdown?.dataset.required === 'true';
                        if (isRequired && !selectedPartner) {
                            alert('Please select a loan officer');
                            return false;
                        }

                        // Save preference if checked
                        if (selectedPartner) {
                            const rememberCheckbox = document.getElementById('an-remember-partner');
                            if (rememberCheckbox && rememberCheckbox.checked) {
                                fetch(ajaxurl, {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                    body: new URLSearchParams({
                                        action: 'frs_set_preferred_lo',
                                        nonce: '<?php echo wp_create_nonce( 'frs_lead_pages' ); ?>',
                                        lo_id: selectedPartner.id,
                                        remember: 'true'
                                    })
                                });
                            }
                        }
                    }
                } else if (currentStep === 1) {
                    const headline = document.getElementById('an-headline')?.value?.trim();
                    if (!headline) {
                        alert('Please enter a headline');
                        return false;
                    }
                } else if (currentStep === 2) {
                    if (selectedScheduleType === 'form') {
                        const formId = document.getElementById('an-form-id')?.value;
                        if (!formId) {
                            alert('Please select a form');
                            return false;
                        }
                    } else if (selectedScheduleType === 'booking') {
                        const calendarId = document.getElementById('an-calendar-id')?.value;
                        if (!calendarId) {
                            alert('Please select a calendar');
                            return false;
                        }
                    }
                }
                return true;
            }

            function submitWizard() {
                nextBtn.classList.add('is-loading');
                nextBtn.disabled = true;

                const data = {
                    action: 'frs_create_apply_now',
                    nonce: '<?php echo wp_create_nonce( 'frs_lead_pages' ); ?>',
                    user_mode: userMode,
                    headline: document.getElementById('an-headline')?.value || '',
                    subheadline: document.getElementById('an-subheadline')?.value || '',
                    schedule_type: selectedScheduleType,
                    form_id: document.getElementById('an-form-id')?.value || '',
                    calendar_id: document.getElementById('an-calendar-id')?.value || ''
                };

                if (isLoanOfficer) {
                    data.lo_name = document.getElementById('an-lo-name')?.value || userData.name;
                    data.lo_nmls = document.getElementById('an-lo-nmls')?.value || '';
                    data.lo_phone = document.getElementById('an-lo-phone')?.value || '';
                    data.lo_email = document.getElementById('an-lo-email')?.value || '';

                    if (selectedPartner) {
                        data.partner_id = selectedPartner.id;
                        data.partner_name = selectedPartner.name;
                        data.partner_license = selectedPartner.license;
                        data.partner_company = selectedPartner.company;
                        data.partner_phone = selectedPartner.phone;
                        data.partner_email = selectedPartner.email;
                    }
                } else {
                    data.realtor_name = document.getElementById('an-realtor-name')?.value || userData.name;
                    data.realtor_license = document.getElementById('an-realtor-license')?.value || '';
                    data.realtor_phone = document.getElementById('an-realtor-phone')?.value || '';
                    data.realtor_email = document.getElementById('an-realtor-email')?.value || '';
                    data.loan_officer_id = selectedPartner?.id || '';
                }

                fetch('<?php echo admin_url( 'admin-ajax.php' ); ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams(data)
                })
                .then(res => res.json())
                .then(response => {
                    if (response.success) {
                        showSuccessState(response.data.url);
                    } else {
                        alert(response.data.message || 'Error creating page');
                        nextBtn.classList.remove('is-loading');
                        nextBtn.disabled = false;
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('Error creating page');
                    nextBtn.classList.remove('is-loading');
                    nextBtn.disabled = false;
                });
            }

            function showSuccessState(pageUrl) {
                document.querySelectorAll('.an-step').forEach(el => el.style.display = 'none');
                const successStep = document.querySelector('.an-step[data-step="success"]');
                if (successStep) successStep.style.display = 'block';

                document.getElementById('an-success-url').value = pageUrl;
                document.getElementById('an-view-page').href = pageUrl;
                document.querySelector('.an-wizard__footer').style.display = 'none';

                nextBtn.classList.remove('is-loading');
                nextBtn.disabled = false;
            }
        })();
        </script>
        <?php
        return ob_get_clean();
    }

    /**
     * AJAX handler for creating apply now page
     */
    public static function ajax_create_apply_now(): void {
        check_ajax_referer( 'frs_lead_pages', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => 'Not authorized' ] );
        }

        $user = wp_get_current_user();
        $allowed_roles = [ 'administrator', 'editor', 'author', 'contributor', 'loan_officer', 'realtor_partner' ];

        if ( ! array_intersect( $allowed_roles, $user->roles ) ) {
            wp_send_json_error( [ 'message' => 'Not authorized' ] );
        }

        $headline = sanitize_text_field( $_POST['headline'] ?? 'Apply Now - Start Your Home Loan Journey' );
        $subheadline = sanitize_text_field( $_POST['subheadline'] ?? '' );
        $schedule_type = sanitize_text_field( $_POST['schedule_type'] ?? 'form' );
        $form_id = absint( $_POST['form_id'] ?? 0 );
        $calendar_id = absint( $_POST['calendar_id'] ?? 0 );

        // Create post
        $post_data = [
            'post_title'   => $headline,
            'post_status'  => 'publish',
            'post_type'    => 'frs_lead_page',
            'post_author'  => $user->ID,
        ];

        $post_id = wp_insert_post( $post_data );

        if ( is_wp_error( $post_id ) ) {
            wp_send_json_error( [ 'message' => $post_id->get_error_message() ] );
        }

        // Save common meta
        update_post_meta( $post_id, '_frs_page_type', 'apply_now' );
        update_post_meta( $post_id, '_frs_headline', $headline );
        update_post_meta( $post_id, '_frs_subheadline', $subheadline );
        update_post_meta( $post_id, '_frs_schedule_type', $schedule_type );
        update_post_meta( $post_id, '_frs_form_id', $form_id );
        update_post_meta( $post_id, '_frs_calendar_id', $calendar_id );

        // Save creator info and partner info based on user mode
        $user_mode = sanitize_text_field( $_POST['user_mode'] ?? 'realtor' );
        update_post_meta( $post_id, '_frs_creator_mode', $user_mode );

        if ( $user_mode === 'loan_officer' ) {
            update_post_meta( $post_id, '_frs_loan_officer_id', $user->ID );
            update_post_meta( $post_id, '_frs_lo_name', sanitize_text_field( $_POST['lo_name'] ?? '' ) );
            update_post_meta( $post_id, '_frs_lo_phone', sanitize_text_field( $_POST['lo_phone'] ?? '' ) );
            update_post_meta( $post_id, '_frs_lo_email', sanitize_email( $_POST['lo_email'] ?? '' ) );
            update_post_meta( $post_id, '_frs_lo_nmls', sanitize_text_field( $_POST['lo_nmls'] ?? '' ) );

            $partner_id = absint( $_POST['partner_id'] ?? 0 );
            if ( $partner_id ) {
                update_post_meta( $post_id, '_frs_realtor_id', $partner_id );
                update_post_meta( $post_id, '_frs_realtor_name', sanitize_text_field( $_POST['partner_name'] ?? '' ) );
                update_post_meta( $post_id, '_frs_realtor_phone', sanitize_text_field( $_POST['partner_phone'] ?? '' ) );
                update_post_meta( $post_id, '_frs_realtor_email', sanitize_email( $_POST['partner_email'] ?? '' ) );
                update_post_meta( $post_id, '_frs_realtor_license', sanitize_text_field( $_POST['partner_license'] ?? '' ) );
                update_post_meta( $post_id, '_frs_realtor_company', sanitize_text_field( $_POST['partner_company'] ?? '' ) );
            }
        } else {
            update_post_meta( $post_id, '_frs_realtor_id', $user->ID );
            update_post_meta( $post_id, '_frs_realtor_name', sanitize_text_field( $_POST['realtor_name'] ?? '' ) );
            update_post_meta( $post_id, '_frs_realtor_phone', sanitize_text_field( $_POST['realtor_phone'] ?? '' ) );
            update_post_meta( $post_id, '_frs_realtor_email', sanitize_email( $_POST['realtor_email'] ?? '' ) );
            update_post_meta( $post_id, '_frs_realtor_license', sanitize_text_field( $_POST['realtor_license'] ?? '' ) );
            update_post_meta( $post_id, '_frs_loan_officer_id', absint( $_POST['loan_officer_id'] ?? 0 ) );
        }

        wp_send_json_success([
            'post_id' => $post_id,
            'url'     => get_permalink( $post_id ),
        ]);
    }
}
