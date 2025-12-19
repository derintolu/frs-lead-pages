<?php
/**
 * Template for Lead Pages (frs_lead_page post type)
 *
 * Full-screen landing page with FluentForms integration.
 * 65/35 split layout: hero image left, form right.
 *
 * @package FRSLeadPages
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Prevent caching
header( 'Cache-Control: no-cache, no-store, must-revalidate' );
header( 'Pragma: no-cache' );
header( 'Expires: 0' );

// Get page data
global $post;
$page_id        = $post->ID;
$page_type      = get_post_meta( $page_id, '_frs_page_type', true );
$headline       = get_post_meta( $page_id, '_frs_headline', true );
$subheadline    = get_post_meta( $page_id, '_frs_subheadline', true );
$consent_text   = get_post_meta( $page_id, '_frs_consent_text', true );
$hero_image_url = get_post_meta( $page_id, '_frs_hero_image_url', true );
$hero_image_id  = get_post_meta( $page_id, '_frs_hero_image_id', true );

// Get hero image
if ( $hero_image_id ) {
    $hero_image_url = wp_get_attachment_image_url( $hero_image_id, 'full' );
}

// Get FluentForm ID - use page-specific form or get form for page type
$form_id = get_post_meta( $page_id, '_frs_fluent_form_id', true );
if ( ! $form_id && class_exists( 'FRSLeadPages\Integrations\FluentForms' ) ) {
    $form_id = \FRSLeadPages\Integrations\FluentForms::get_form_id_for_type( $page_type );
}

// Property details (for open house)
$property_address = get_post_meta( $page_id, '_frs_property_address', true );
$property_price   = get_post_meta( $page_id, '_frs_property_price', true );
$property_beds    = get_post_meta( $page_id, '_frs_property_beds', true );
$property_baths   = get_post_meta( $page_id, '_frs_property_baths', true );

// Event details
$event_name = get_post_meta( $page_id, '_frs_event_name', true );
$event_date = get_post_meta( $page_id, '_frs_event_date', true );
$event_time = get_post_meta( $page_id, '_frs_event_time', true );
$event_end_time = get_post_meta( $page_id, '_frs_event_end_time', true );
$event_venue = get_post_meta( $page_id, '_frs_event_venue', true );
$event_description = get_post_meta( $page_id, '_frs_event_description', true );

// Value propositions
$value_props = get_post_meta( $page_id, '_frs_value_props', true );

// LO and Realtor info
$lo_id = get_post_meta( $page_id, '_frs_loan_officer_id', true );
$realtor_id = get_post_meta( $page_id, '_frs_realtor_id', true );
$realtor_name = get_post_meta( $page_id, '_frs_realtor_name', true );
$realtor_photo = get_post_meta( $page_id, '_frs_realtor_photo', true );

// Helper function to get user photo from multiple sources
if ( ! function_exists( 'frs_get_user_photo' ) ) {
    function frs_get_user_photo( $user_id ) {
        if ( ! $user_id ) return '';

        // 1. Check FRS Profiles table (headshot_id)
        if ( class_exists( 'FRSUsers\Models\Profile' ) ) {
            $profile = \FRSUsers\Models\Profile::where( 'user_id', $user_id )->first();
            if ( $profile && ! empty( $profile->headshot_id ) ) {
                $url = wp_get_attachment_url( $profile->headshot_id );
                if ( $url ) return $url;
            }
        }

        // 2. Check user_profile_photo meta (SureDash)
        $suredash_photo = get_user_meta( $user_id, 'user_profile_photo', true );
        if ( ! empty( $suredash_photo ) ) return $suredash_photo;

        // 3. Check Simple Local Avatars
        $simple_avatar = get_user_meta( $user_id, 'simple_local_avatar', true );
        if ( ! empty( $simple_avatar ) && ! empty( $simple_avatar['full'] ) ) {
            return $simple_avatar['full'];
        }

        // 4. Check custom_avatar_url meta
        $custom_avatar = get_user_meta( $user_id, 'custom_avatar_url', true );
        if ( ! empty( $custom_avatar ) ) return $custom_avatar;

        // 5. Check profile_photo meta
        $profile_photo = get_user_meta( $user_id, 'profile_photo', true );
        if ( ! empty( $profile_photo ) ) return $profile_photo;

        // 6. Fallback to Gravatar
        return get_avatar_url( $user_id, [ 'size' => 200 ] );
    }
}

// Helper function to get user NMLS from multiple sources
if ( ! function_exists( 'frs_get_user_nmls' ) ) {
    function frs_get_user_nmls( $user_id ) {
        if ( ! $user_id ) return '';

        // 1. Check FRS Profiles table first (most accurate source)
        if ( class_exists( 'FRSUsers\Models\Profile' ) ) {
            $profile = \FRSUsers\Models\Profile::where( 'user_id', $user_id )->first();
            if ( $profile ) {
                $nmls = $profile->nmls ?: $profile->nmls_number;
                if ( ! empty( $nmls ) ) return $nmls;
            }
        }

        // 2. Check linked person post meta
        $profile_id = get_user_meta( $user_id, 'profile', true );
        if ( $profile_id ) {
            $nmls = get_post_meta( $profile_id, 'nmls', true ) ?: get_post_meta( $profile_id, 'nmls_number', true );
            if ( ! empty( $nmls ) ) return $nmls;
        }

        // 3. Fallback to user meta
        return get_user_meta( $user_id, 'nmls_id', true ) ?: get_user_meta( $user_id, 'nmls', true );
    }
}

// Get LO data
$lo_data = [];
if ( $lo_id ) {
    $lo_user = get_user_by( 'ID', $lo_id );
    if ( $lo_user ) {
        $lo_data = [
            'id'         => $lo_id,
            'name'       => $lo_user->display_name,
            'first_name' => $lo_user->first_name,
            'last_name'  => $lo_user->last_name,
            'email'      => $lo_user->user_email,
            'phone'      => get_user_meta( $lo_id, 'phone', true ) ?: get_user_meta( $lo_id, 'phone_number', true ) ?: get_user_meta( $lo_id, 'mobile_phone', true ),
            'nmls'       => \FRSLeadPages\frs_get_user_nmls( $lo_id ),
            'title'      => get_user_meta( $lo_id, 'job_title', true ) ?: 'Loan Officer',
            'company'    => '21st Century Lending',
            'photo'      => frs_get_user_photo( $lo_id ),
        ];
    }
}

// Get Realtor data from user if realtor_id exists
$realtor_data = [];
if ( $realtor_id ) {
    $realtor_user = get_user_by( 'ID', $realtor_id );
    if ( $realtor_user ) {
        $realtor_data = [
            'id'         => $realtor_id,
            'name'       => $realtor_user->display_name,
            'first_name' => $realtor_user->first_name,
            'last_name'  => $realtor_user->last_name,
            'email'      => $realtor_user->user_email,
            'phone'      => get_user_meta( $realtor_id, 'phone', true ) ?: get_user_meta( $realtor_id, 'phone_number', true ) ?: get_user_meta( $realtor_id, 'mobile_phone', true ),
            'title'      => get_user_meta( $realtor_id, 'job_title', true ) ?: 'Sales Associate',
            'license'    => get_user_meta( $realtor_id, 'license_number', true ) ?: get_user_meta( $realtor_id, 'dre_license', true ),
            'company'    => get_user_meta( $realtor_id, 'company', true ) ?: get_user_meta( $realtor_id, 'brokerage', true ) ?: '',
            'photo'      => frs_get_user_photo( $realtor_id ),
        ];
    }
} elseif ( $realtor_name ) {
    // Fallback to manual entry
    $realtor_data = [
        'id'         => 0,
        'name'       => $realtor_name,
        'first_name' => '',
        'last_name'  => '',
        'email'      => get_post_meta( $page_id, '_frs_realtor_email', true ),
        'phone'      => get_post_meta( $page_id, '_frs_realtor_phone', true ),
        'title'      => 'Sales Associate',
        'license'    => get_post_meta( $page_id, '_frs_realtor_license', true ),
        'company'    => get_post_meta( $page_id, '_frs_realtor_company', true ),
        'photo'      => $realtor_photo ?: '',
    ];
}

// Accent colors by page type
$accent_colors = [
    'open_house'          => '#0ea5e9',
    'customer_spotlight'  => '#10b981',
    'special_event'       => '#f59e0b',
    'mortgage_calculator' => '#8b5cf6',
];
$accent_color = $accent_colors[ $page_type ] ?? '#0ea5e9';

// Track page view
$views = (int) get_post_meta( $page_id, '_frs_page_views', true );
update_post_meta( $page_id, '_frs_page_views', $views + 1 );

$badge_labels = [
    'open_house'          => 'Open House',
    'customer_spotlight'  => 'Customer Spotlight',
    'special_event'       => 'Special Event',
    'mortgage_calculator' => 'Mortgage Calculator',
];
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html( $headline ?: get_the_title() ); ?> | <?php bloginfo( 'name' ); ?></title>

    <meta property="og:title" content="<?php echo esc_attr( $headline ?: get_the_title() ); ?>">
    <meta property="og:description" content="<?php echo esc_attr( $subheadline ); ?>">
    <meta property="og:url" content="<?php echo esc_url( get_permalink() ); ?>">
    <?php if ( $hero_image_url ) : ?>
    <meta property="og:image" content="<?php echo esc_url( $hero_image_url ); ?>">
    <?php endif; ?>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --accent: <?php echo esc_attr( $accent_color ); ?>;
            --accent-light: <?php echo esc_attr( $accent_color ); ?>15;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        html, body {
            height: 100%;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            -webkit-font-smoothing: antialiased;
            background: #f8fafc;
            margin: 0;
            padding: 0;
            overflow: hidden;
        }

        #wpadminbar { display: none !important; }
        html { margin-top: 0 !important; }
    </style>

    <?php if ( $page_type === 'mortgage_calculator' ) : ?>
    <!-- Calculator-specific CSS -->
    <style>
        /* Mortgage Calculator - Clean Full Page Layout */
        .lead-page--calculator {
            display: block;
            min-height: 100vh;
            background: linear-gradient(180deg, #0f172a 0%, #1e293b 100%);
            font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        /* Team Header */
        .calc-header {
            text-align: center;
            padding: 40px 20px 32px;
            height: 400px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .calc-header__headline {
            font-size: 42px;
            font-weight: 700;
            color: #ffffff;
            margin: 0 0 16px;
            font-family: 'Playfair Display', Georgia, serif;
        }
        .calc-header__title {
            font-size: 24px;
            font-weight: 600;
            color: #ffffff;
            margin: 0 0 32px;
            font-family: 'Playfair Display', Georgia, serif;
        }
        .calc-header__team {
            display: flex;
            justify-content: center;
            gap: 48px;
            flex-wrap: wrap;
            margin-bottom: 24px;
        }
        .calc-team-card {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .calc-team-card__photo {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid rgba(45, 212, 218, 0.5);
        }
        .calc-team-card__info {
            text-align: left;
        }
        .calc-team-card__name {
            display: block;
            font-size: 18px;
            font-weight: 600;
            color: #2dd4da;
            margin-bottom: 2px;
        }
        .calc-team-card__role {
            display: block;
            font-size: 14px;
            color: rgba(255, 255, 255, 0.8);
        }
        .calc-team-card__nmls,
        .calc-team-card__company {
            display: block;
            font-size: 12px;
            color: rgba(255, 255, 255, 0.5);
        }
        .calc-team-card__contact {
            display: flex;
            gap: 12px;
            margin-top: 6px;
        }
        .calc-team-card__contact a {
            font-size: 13px;
            color: #2dd4da;
            text-decoration: none;
        }
        .calc-team-card__contact a:hover {
            text-decoration: underline;
        }
        .calc-header__powered {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.4);
            margin: 0;
        }

        /* Calculator Main */
        .calc-main {
            background: #ffffff;
            padding-top: 40px;
            padding-bottom: 60px;
        }

        /* Form field border colors using gradient */
        .calc-main input,
        .calc-main select,
        .calc-main textarea,
        .calc-main [role="combobox"],
        .calc-main .border {
            border-color: var(--gradient-start, #252526) !important;
        }
        .calc-main input:focus,
        .calc-main select:focus,
        .calc-main textarea:focus,
        .calc-main [role="combobox"]:focus {
            border-color: var(--gradient-end, #1f1f1f) !important;
            box-shadow: 0 0 0 2px var(--gradient-end, #1f1f1f) !important;
        }

        /* Responsive */
        @media (max-width: 640px) {
            .calc-header__team {
                flex-direction: column;
                align-items: center;
                gap: 24px;
            }
            .calc-team-card {
                width: 100%;
                max-width: 280px;
            }
            .calc-header__title {
                font-size: 20px;
            }
        }
    </style>
    <?php else : ?>
    <!-- Standard Layout CSS (Open House, Customer Spotlight, Special Event) -->
    <style>
        .lead-page {
            display: grid;
            grid-template-columns: 55% 45%;
            min-height: 100vh;
        }

        .lead-page__hero {
            position: relative;
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            min-height: 100vh;
        }

        .lead-page__hero-image {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
        }

        .lead-page__hero-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(to right, rgba(0,0,0,0.35) 0%, rgba(0,0,0,0.15) 100%);
        }

        .lead-page__hero-content {
            position: relative;
            z-index: 10;
            padding: 48px;
            display: grid;
            grid-template-columns: 1fr auto;
            grid-template-rows: auto 1fr auto;
            gap: 24px;
            height: 100%;
        }

        /* Top Left: Headline + QR Code */
        .lead-page__top-left {
            grid-column: 1;
            grid-row: 1;
        }

        .lead-page__headline {
            color: white;
            font-size: 48px;
            font-weight: 700;
            line-height: 1.1;
            margin-bottom: 20px;
            max-width: 600px;
        }

        .lead-page__subheadline {
            color: rgba(255,255,255,0.9);
            font-size: 20px;
            font-weight: 400;
            line-height: 1.5;
            margin-bottom: 24px;
            max-width: 550px;
        }

        .lead-page__value-props {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-bottom: 32px;
            max-width: 500px;
        }

        .lead-page__value-prop {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            color: white;
            font-size: 16px;
            line-height: 1.4;
        }

        .lead-page__value-prop-icon {
            flex-shrink: 0;
            width: 28px;
            height: 28px;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .lead-page__value-prop-icon svg {
            width: 14px;
            height: 14px;
            color: white;
        }

        /* Top Left: Company Branding */
        .lead-page__branding {
            grid-column: 1;
            grid-row: 1;
            display: flex;
            align-items: flex-start;
            justify-content: flex-start;
        }

        /* Frosted Glass Mixin Variables */
        .frosted-glass {
            background: rgba(255, 255, 255, 0.75);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .frosted-glass-dark {
            background: rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.15);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
        }

        /* Agents Row (above form) - Frosted Glass */
        .lead-page__agents-row {
            display: flex;
            gap: 16px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }

        .lead-page__agent-card-light {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 16px 20px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            flex: 1;
            min-width: 220px;
            transition: all 0.3s ease;
        }

        .lead-page__agent-card-light:hover {
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
            transform: translateY(-2px);
        }

        .lead-page__agent-card-light .lead-page__agent-photo {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #e2e8f0;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .lead-page__agent-card-light .lead-page__agent-info h4 {
            font-size: 16px;
            font-weight: 600;
            color: #1e293b;
            margin: 0 0 4px;
            white-space: nowrap;
        }

        .lead-page__agent-card-light .lead-page__agent-info p {
            font-size: 13px;
            color: #64748b;
            margin: 0;
            white-space: nowrap;
        }

        .lead-page__logos {
            display: flex;
            flex-direction: row;
            gap: 12px;
            align-items: center;
        }

        .lead-page__company-logo {
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            padding: 14px 24px;
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            transition: all 0.3s ease;
        }

        .lead-page__company-logo:hover {
            background: rgba(0, 0, 0, 0.6);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.35);
        }

        .lead-page__company-logo--dark {
            background: rgba(0, 0, 0, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .lead-page__company-logo--dark:hover {
            background: rgba(0, 0, 0, 0.6);
        }

        .lead-page__company-logo img {
            height: 44px;
            width: auto;
            display: block;
        }

        .lead-page__company-logo span {
            font-size: 16px;
            font-weight: 700;
            color: #1e293b;
            white-space: nowrap;
        }

        /* Center Content: Headline, Value Props */
        .lead-page__content {
            grid-column: 1 / -1;
            grid-row: 2;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 20px 0;
        }


        /* Bottom Left: Property Info */
        .lead-page__bottom-left {
            grid-column: 1;
            grid-row: 3;
            align-self: end;
        }

        .lead-page__info-badge {
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 24px 28px;
            border-radius: 20px;
            color: white;
            max-width: 420px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            transition: all 0.3s ease;
        }

        .lead-page__info-badge:hover {
            background: rgba(0, 0, 0, 0.6);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.35);
        }

        .lead-page__info-badge h3 {
            font-size: 20px;
            font-weight: 600;
            margin: 0 0 8px;
            white-space: nowrap;
            color: #ffffff;
        }
        .lead-page__info-badge p {
            font-size: 14px;
            margin: 4px 0;
            white-space: nowrap;
            color: #ffffff;
        }
        .lead-page__property-details {
            display: flex;
            gap: 16px;
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid rgba(255,255,255,0.2);
        }
        .lead-page__property-detail {
            text-align: center;
        }
        .lead-page__property-detail strong {
            display: block;
            font-size: 24px;
            font-weight: 700;
            color: #ffffff;
        }
        .lead-page__property-detail span {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #ffffff;
        }
        .lead-page__price { font-size: 28px; font-weight: 700; color: var(--accent); margin-top: 12px; }

        /* Add to Calendar Button */
        .lead-page__calendar-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 16px;
            padding: 10px 18px;
            background: #1e3a5f;
            color: #ffffff;
            font-size: 14px;
            font-weight: 600;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
        }

        .lead-page__calendar-btn:hover {
            background: #2d4a6f;
            transform: translateY(-1px);
        }

        .lead-page__calendar-btn svg {
            width: 18px;
            height: 18px;
        }

        .lead-page__calendar-dropdown {
            position: relative;
            display: inline-block;
        }

        .lead-page__calendar-options {
            display: none;
            position: absolute;
            bottom: 100%;
            left: 0;
            margin-bottom: 8px;
            background: rgba(0, 0, 0, 0.85);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            padding: 8px;
            min-width: 180px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
            z-index: 100;
        }

        .lead-page__calendar-dropdown:hover .lead-page__calendar-options,
        .lead-page__calendar-dropdown:focus-within .lead-page__calendar-options {
            display: block;
        }

        .lead-page__calendar-option {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            color: #ffffff;
            text-decoration: none;
            font-size: 14px;
            border-radius: 8px;
            transition: background 0.2s ease;
        }

        .lead-page__calendar-option:hover {
            background: rgba(255, 255, 255, 0.15);
        }

        .lead-page__calendar-option svg {
            width: 18px;
            height: 18px;
            flex-shrink: 0;
        }

        /* Add Contact Button */
        .lead-page__contact-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            background: transparent;
            color: #64748b;
            font-size: 12px;
            font-weight: 500;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            margin-top: 8px;
        }

        .lead-page__contact-btn:hover {
            background: #f1f5f9;
            color: #1e3a5f;
            border-color: #cbd5e1;
        }

        .lead-page__contact-btn svg {
            width: 14px;
            height: 14px;
        }

        .lead-page__form {
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            padding: 48px 40px;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            border-left: 1px solid rgba(255, 255, 255, 0.3);
        }

        .lead-page__form-header { margin-bottom: 32px; flex-shrink: 0; }
        .lead-page__form-title { font-size: 24px; font-weight: 700; color: #1e293b; margin-bottom: 8px; }
        .lead-page__form-subtitle { font-size: 15px; color: #64748b; }

        .lead-page__form-container {
            flex: 1;
        }

        /* FluentForms styling overrides */
        .lead-page__form-container .fluentform {
            font-family: inherit !important;
        }
        .lead-page__form-container .fluentform form,
        .lead-page__form-container .ff-el-group,
        .lead-page__form-container .ff-t-container {
            overflow: visible !important;
        }
        .lead-page__form-container .ff-el-input--label { font-weight: 500 !important; color: #374151 !important; font-size: 14px !important; }
        .lead-page__form-container .ff-el-form-control {
            border: 1px solid #e2e8f0 !important;
            border-radius: 10px !important;
            padding: 12px 16px !important;
            font-size: 15px !important;
            transition: all 0.2s !important;
        }
        .lead-page__form-container .ff-el-form-control:focus {
            border-color: var(--accent) !important;
            box-shadow: 0 0 0 3px var(--accent-light) !important;
            outline: none !important;
        }
        .lead-page__form-container .ff-btn-submit {
            background: #1e3a5f !important;
            border: none !important;
            border-radius: 10px !important;
            padding: 14px 24px !important;
            font-size: 16px !important;
            font-weight: 600 !important;
            width: 100% !important;
            cursor: pointer !important;
        }
        .lead-page__form-container .ff-btn-submit:hover { background: #152a45 !important; }
        .lead-page__form-container .ff-el-group { margin-bottom: 20px !important; }
        .lead-page__form-container .ff-message-success {
            background: #ecfdf5 !important;
            border: 1px solid #10b981 !important;
            color: #065f46 !important;
            padding: 24px !important;
            border-radius: 12px !important;
            text-align: center !important;
        }

        .lead-page__consent { margin-top: 16px; padding-top: 16px; border-top: 1px solid #e2e8f0; font-size: 12px; color: #94a3b8; }
        .lead-page__powered { margin-top: 24px; text-align: center; font-size: 12px; color: #94a3b8; }

        /* FluentForms Multi-step styling */
        .lead-page__form-container .ff-step-container { margin-bottom: 24px; }
        .lead-page__form-container .ff-step-header { margin-bottom: 24px; }
        .lead-page__form-container .ff-step-titles {
            display: flex;
            gap: 8px;
            margin-bottom: 16px;
        }
        .lead-page__form-container .ff-step-titles li {
            flex: 1;
            height: 4px;
            background: #e2e8f0;
            border-radius: 2px;
            list-style: none;
        }
        .lead-page__form-container .ff-step-titles li.ff_active,
        .lead-page__form-container .ff-step-titles li.ff_completed {
            background: var(--accent);
        }
        .lead-page__form-container .ff-el-progress-status { display: none; }

        /* FluentForms step navigation */
        .lead-page__form-container .ff-step-nav {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }
        .lead-page__form-container .ff-btn-prev,
        .lead-page__form-container .ff-btn-next {
            padding: 14px 28px !important;
            font-size: 16px !important;
            font-weight: 600 !important;
            border-radius: 10px !important;
            cursor: pointer !important;
            transition: all 0.2s !important;
            border: none !important;
        }
        .lead-page__form-container .ff-btn-prev {
            background: transparent !important;
            color: #64748b !important;
        }
        .lead-page__form-container .ff-btn-prev:hover { color: #1e293b !important; }
        .lead-page__form-container .ff-btn-next {
            background: #1e3a5f !important;
            color: #fff !important;
            margin-left: auto !important;
        }
        .lead-page__form-container .ff-btn-next:hover { background: #152a45 !important; }

        /* FluentForms radio buttons as pressable buttons */
        .lead-page__form-container .ff-el-form-check {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .lead-page__form-container .ff-el-form-check label {
            display: flex;
            align-items: center;
            padding: 14px 16px;
            background: #fff;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.15s;
            font-size: 15px;
            font-weight: 500;
        }
        .lead-page__form-container .ff-el-form-check label:hover {
            border-color: #cbd5e1;
            background: #f8fafc;
        }
        .lead-page__form-container .ff-el-form-check input[type="radio"] {
            display: none;
        }
        .lead-page__form-container .ff-el-form-check input[type="radio"]:checked + span,
        .lead-page__form-container .ff-el-form-check label:has(input:checked) {
            background: var(--accent);
            border-color: var(--accent);
            color: #fff;
        }

        @media (max-width: 1024px) {
            html, body { height: auto; }
            .lead-page {
                display: flex;
                flex-direction: column;
                min-height: auto;
            }
            .lead-page__hero { min-height: auto; }
            .lead-page__hero-content {
                padding: 32px 24px;
                grid-template-columns: 1fr;
                grid-template-rows: auto auto auto;
                height: auto;
            }
            .lead-page__top-left { grid-column: 1; grid-row: 1; }
            .lead-page__top-right {
                grid-column: 1;
                grid-row: 2;
                flex-direction: row;
                flex-wrap: wrap;
                align-items: flex-start;
                justify-content: flex-start;
            }
            .lead-page__bottom-left { grid-column: 1; grid-row: 3; }
            .lead-page__headline { font-size: 28px; margin-bottom: 16px; }
            .lead-page__qr-wrapper { padding: 12px 16px; }
            .lead-page__qr-code { width: 60px; height: 60px; }
            .lead-page__agent-card { min-width: auto; }
            .lead-page__form { padding: 32px 24px; min-height: auto; }
        }
    </style>
    <?php endif; ?>

    <?php wp_head(); ?>
</head>
<body <?php body_class( 'frs-lead-page frs-lead-page--' . esc_attr( $page_type ) ); ?>>

<!-- DEBUG: page_type = "<?php echo esc_attr( $page_type ); ?>" length=<?php echo strlen( $page_type ); ?> -->
<?php if ( $page_type === 'mortgage_calculator' ) : ?>
<!-- Mortgage Calculator Layout - Single Column -->
<div class="lead-page lead-page--calculator">
    <!-- Team Header -->
    <header class="calc-header">
        <?php if ( $headline ) : ?>
            <h1 class="calc-header__headline"><?php echo esc_html( $headline ); ?></h1>
        <?php endif; ?>
        <h2 class="calc-header__title">Your Lending Team</h2>
        <div class="calc-header__team">
            <?php if ( ! empty( $lo_data ) ) : ?>
                <div class="calc-team-card">
                    <?php if ( ! empty( $lo_data['photo'] ) ) : ?>
                        <img src="<?php echo esc_url( $lo_data['photo'] ); ?>" alt="<?php echo esc_attr( $lo_data['name'] ); ?>" class="calc-team-card__photo">
                    <?php endif; ?>
                    <div class="calc-team-card__info">
                        <strong class="calc-team-card__name"><?php echo esc_html( $lo_data['name'] ); ?></strong>
                        <span class="calc-team-card__role"><?php echo esc_html( $lo_data['title'] ?: 'Loan Officer' ); ?></span>
                        <?php if ( ! empty( $lo_data['nmls'] ) ) : ?>
                            <span class="calc-team-card__nmls">NMLS# <?php echo esc_html( $lo_data['nmls'] ); ?></span>
                        <?php endif; ?>
                        <div class="calc-team-card__contact">
                            <?php if ( ! empty( $lo_data['phone'] ) ) : ?>
                                <a href="tel:<?php echo esc_attr( $lo_data['phone'] ); ?>"><?php echo esc_html( $lo_data['phone'] ); ?></a>
                            <?php endif; ?>
                            <?php if ( ! empty( $lo_data['email'] ) ) : ?>
                                <a href="mailto:<?php echo esc_attr( $lo_data['email'] ); ?>">Email</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ( ! empty( $realtor_data ) && ! empty( $realtor_data['name'] ) ) : ?>
                <div class="calc-team-card">
                    <?php if ( ! empty( $realtor_data['photo'] ) ) : ?>
                        <img src="<?php echo esc_url( $realtor_data['photo'] ); ?>" alt="<?php echo esc_attr( $realtor_data['name'] ); ?>" class="calc-team-card__photo">
                    <?php endif; ?>
                    <div class="calc-team-card__info">
                        <strong class="calc-team-card__name"><?php echo esc_html( $realtor_data['name'] ); ?></strong>
                        <span class="calc-team-card__role"><?php echo esc_html( $realtor_data['title'] ?: 'Sales Associate' ); ?></span>
                        <?php if ( ! empty( $realtor_data['company'] ) ) : ?>
                            <span class="calc-team-card__company"><?php echo esc_html( $realtor_data['company'] ); ?></span>
                        <?php endif; ?>
                        <div class="calc-team-card__contact">
                            <?php if ( ! empty( $realtor_data['phone'] ) ) : ?>
                                <a href="tel:<?php echo esc_attr( $realtor_data['phone'] ); ?>"><?php echo esc_html( $realtor_data['phone'] ); ?></a>
                            <?php endif; ?>
                            <?php if ( ! empty( $realtor_data['email'] ) ) : ?>
                                <a href="mailto:<?php echo esc_attr( $realtor_data['email'] ); ?>">Email</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <p class="calc-header__powered">Powered by 21st Century Lending</p>
    </header>

    <!-- Calculator Widget -->
    <?php
    // Get saved gradient colors or use defaults
    $gradient_start = get_post_meta( $page_id, '_frs_gradient_start', true ) ?: '#252526';
    $gradient_end = get_post_meta( $page_id, '_frs_gradient_end', true ) ?: '#1f1f1f';
    ?>
    <main class="calc-main" style="--gradient-start: <?php echo esc_attr( $gradient_start ); ?>; --gradient-end: <?php echo esc_attr( $gradient_end ); ?>;">

        <?php
        // Enqueue frs-lrg widget assets
        if ( class_exists( '\LendingResourceHub\Assets\Frontend' ) ) {
            \LendingResourceHub\Assets\Frontend::get_instance()->enqueue_widget_assets();
        }

        // Build data attributes for calculator
        $calc_attrs = [
            'data-loan-officer-id' => esc_attr( $lo_id ),
            'data-show-lead-form' => 'true',
            'data-gradient-start' => esc_attr( $gradient_start ),
            'data-gradient-end' => esc_attr( $gradient_end ),
        ];

        if ( ! empty( $lo_data['name'] ) ) {
            $calc_attrs['data-loan-officer-name'] = esc_attr( $lo_data['name'] );
        }
        if ( ! empty( $lo_data['email'] ) ) {
            $calc_attrs['data-loan-officer-email'] = esc_attr( $lo_data['email'] );
        }
        if ( ! empty( $lo_data['phone'] ) ) {
            $calc_attrs['data-loan-officer-phone'] = esc_attr( $lo_data['phone'] );
        }
        if ( ! empty( $lo_data['nmls'] ) ) {
            $calc_attrs['data-loan-officer-nmls'] = esc_attr( $lo_data['nmls'] );
        }

        $attr_string = '';
        foreach ( $calc_attrs as $key => $value ) {
            $attr_string .= sprintf( ' %s="%s"', $key, $value );
        }
        ?>
        <div id="mortgage-calculator" class="frs-mortgage-calculator-widget"<?php echo $attr_string; ?>></div>
    </main>
</div>

<?php wp_footer(); ?>
</body>
</html>

<?php else : ?>
<!-- Standard Layout (Open House, Customer Spotlight, Special Event) -->
<div class="lead-page">
    <div class="lead-page__hero">
        <?php if ( $hero_image_url ) : ?>
            <img src="<?php echo esc_url( $hero_image_url ); ?>" alt="" class="lead-page__hero-image">
        <?php endif; ?>
        <div class="lead-page__hero-overlay"></div>

        <div class="lead-page__hero-content">
            <!-- Top Left: Company Logos (Partner first, then 21C) -->
            <div class="lead-page__branding">
                <?php
                // Brokerage/Partner logo (for sales associate)
                $brokerage_logo = get_post_meta( $page_id, '_frs_brokerage_logo', true );
                ?>
                <div class="lead-page__logos">
                    <?php if ( $brokerage_logo ) : ?>
                        <div class="lead-page__company-logo">
                            <img src="<?php echo esc_url( $brokerage_logo ); ?>" alt="Partner">
                        </div>
                    <?php endif; ?>
                    <div class="lead-page__company-logo lead-page__company-logo--dark">
                        <img src="<?php echo esc_url( home_url( '/wp-content/uploads/2025/09/21C-Wordmark-White.svg' ) ); ?>" alt="21st Century Lending">
                    </div>
                </div>
            </div>

            <!-- Center/Middle: Headline, Value Props -->
            <div class="lead-page__content">
                <?php if ( $headline ) : ?>
                    <h1 class="lead-page__headline"><?php echo esc_html( $headline ); ?></h1>
                <?php endif; ?>

                <?php if ( $subheadline ) : ?>
                    <p class="lead-page__subheadline"><?php echo esc_html( $subheadline ); ?></p>
                <?php endif; ?>

                <?php if ( $value_props ) : ?>
                    <div class="lead-page__value-props">
                        <?php
                        $props = array_filter( array_map( 'trim', explode( "\n", $value_props ) ) );
                        foreach ( $props as $prop ) :
                        ?>
                            <div class="lead-page__value-prop">
                                <div class="lead-page__value-prop-icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                        <polyline points="20 6 9 17 4 12"></polyline>
                                    </svg>
                                </div>
                                <span><?php echo esc_html( $prop ); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Bottom Left: Property/Event Info -->
            <div class="lead-page__bottom-left">
                <?php if ( $page_type === 'open_house' && $property_address ) : ?>
                    <div class="lead-page__info-badge">
                        <h3><?php echo esc_html( $property_address ); ?></h3>
                        <?php if ( $property_beds || $property_baths || $property_price ) : ?>
                            <div class="lead-page__property-details">
                                <?php if ( $property_beds ) : ?>
                                    <div class="lead-page__property-detail">
                                        <strong><?php echo esc_html( $property_beds ); ?></strong>
                                        <span>Beds</span>
                                    </div>
                                <?php endif; ?>
                                <?php if ( $property_baths ) : ?>
                                    <div class="lead-page__property-detail">
                                        <strong><?php echo esc_html( $property_baths ); ?></strong>
                                        <span>Baths</span>
                                    </div>
                                <?php endif; ?>
                                <?php if ( $property_price ) : ?>
                                    <div class="lead-page__property-detail">
                                        <strong>$<?php echo esc_html( number_format( (int) $property_price / 1000 ) ); ?>k</strong>
                                        <span>Price</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php elseif ( $page_type === 'special_event' && $event_name ) : ?>
                    <?php
                    // Build calendar URLs
                    $event_start = $event_date;
                    $event_end = $event_date;

                    // Parse time if available
                    if ( $event_time ) {
                        $start_datetime = strtotime( $event_date . ' ' . $event_time );
                        $event_start = date( 'Ymd\THis', $start_datetime );

                        // End time: use provided end time or default to 2 hours after start
                        if ( $event_end_time ) {
                            $end_datetime = strtotime( $event_date . ' ' . $event_end_time );
                        } else {
                            $end_datetime = $start_datetime + ( 2 * 60 * 60 ); // 2 hours
                        }
                        $event_end = date( 'Ymd\THis', $end_datetime );
                    } else {
                        // All-day event
                        $event_start = date( 'Ymd', strtotime( $event_date ) );
                        $event_end = date( 'Ymd', strtotime( $event_date . ' +1 day' ) );
                    }

                    $cal_title = $event_name;
                    $cal_description = $event_description ?: $subheadline ?: '';
                    $cal_location = $event_venue ?: '';

                    // Google Calendar URL
                    $google_cal_url = add_query_arg( [
                        'action'   => 'TEMPLATE',
                        'text'     => $cal_title,
                        'dates'    => $event_start . '/' . $event_end,
                        'details'  => $cal_description,
                        'location' => $cal_location,
                    ], 'https://calendar.google.com/calendar/render' );

                    // Outlook Web URL
                    $outlook_url = add_query_arg( [
                        'path'      => '/calendar/action/compose',
                        'rru'       => 'addevent',
                        'subject'   => $cal_title,
                        'startdt'   => $event_start,
                        'enddt'     => $event_end,
                        'body'      => $cal_description,
                        'location'  => $cal_location,
                    ], 'https://outlook.live.com/calendar/0/deeplink/compose' );

                    // ICS file URL (we'll create an endpoint for this)
                    $ics_url = add_query_arg( [
                        'frs_calendar_event' => $page_id,
                        'format' => 'ics',
                    ], get_permalink() );
                    ?>
                    <div class="lead-page__info-badge">
                        <h3><?php echo esc_html( $event_name ); ?></h3>
                        <?php if ( $event_date ) : ?>
                            <p>
                                <?php echo esc_html( date_i18n( 'F j, Y', strtotime( $event_date ) ) ); ?>
                                <?php if ( $event_time ) : ?>
                                    at <?php echo esc_html( date_i18n( 'g:i A', strtotime( $event_time ) ) ); ?>
                                <?php endif; ?>
                            </p>
                        <?php endif; ?>
                        <?php if ( $event_venue ) : ?>
                            <p><?php echo esc_html( $event_venue ); ?></p>
                        <?php endif; ?>

                        <!-- Add to Calendar Dropdown -->
                        <div class="lead-page__calendar-dropdown">
                            <button class="lead-page__calendar-btn" type="button">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                    <line x1="16" y1="2" x2="16" y2="6"></line>
                                    <line x1="8" y1="2" x2="8" y2="6"></line>
                                    <line x1="3" y1="10" x2="21" y2="10"></line>
                                    <line x1="12" y1="14" x2="12" y2="18"></line>
                                    <line x1="10" y1="16" x2="14" y2="16"></line>
                                </svg>
                                Add to Calendar
                            </button>
                            <div class="lead-page__calendar-options">
                                <a href="<?php echo esc_url( $google_cal_url ); ?>" target="_blank" rel="noopener" class="lead-page__calendar-option">
                                    <svg viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M12 0C5.383 0 0 5.383 0 12s5.383 12 12 12 12-5.383 12-12S18.617 0 12 0zm-1.5 17.25L6 12.75l1.5-1.5L10.5 14.25l6-6L18 9.75l-7.5 7.5z"/>
                                    </svg>
                                    Google Calendar
                                </a>
                                <a href="<?php echo esc_url( $outlook_url ); ?>" target="_blank" rel="noopener" class="lead-page__calendar-option">
                                    <svg viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M7.88 12.04q0 .45-.11.87-.1.41-.33.74-.22.33-.58.52-.37.2-.87.2-.48 0-.85-.2-.36-.19-.58-.52-.22-.33-.33-.74-.1-.42-.1-.87 0-.45.1-.87.11-.42.33-.74.22-.33.58-.52.37-.2.85-.2.5 0 .87.2.36.19.58.52.23.32.33.74.11.42.11.87zm-1.44 0q0-.29-.06-.53-.06-.24-.18-.42-.11-.18-.28-.28-.17-.1-.4-.1-.22 0-.39.1-.17.1-.28.28-.11.18-.17.42-.06.24-.06.53 0 .29.06.53.06.24.17.41.11.18.28.28.17.1.39.1.23 0 .4-.1.17-.1.28-.28.12-.17.18-.41.06-.24.06-.53zm8.27-.92v3.76h-1.21v-3.76h-.96v-.93h3.13v.93h-.96zm-2.32 3.76h-1.21v-4.69h1.21v4.69zm-8.57 0h-1.2v-4.69h1.2v4.69zm15.89-3.54q-.26-.04-.48-.04-.38 0-.6.13-.21.13-.34.35-.12.21-.17.48-.05.27-.05.56 0 .62.23.95.23.33.72.33.22 0 .46-.06.24-.07.47-.17v.95q-.26.11-.53.16-.27.05-.54.05-.55 0-.92-.19-.37-.19-.6-.51-.22-.33-.33-.74-.1-.42-.1-.87 0-.5.12-.93.13-.43.38-.75.25-.32.62-.5.38-.18.88-.18.24 0 .49.05.26.06.5.17l-.3.91z"/>
                                    </svg>
                                    Outlook
                                </a>
                                <a href="<?php echo esc_url( $ics_url ); ?>" download="<?php echo esc_attr( sanitize_title( $event_name ) ); ?>.ics" class="lead-page__calendar-option">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                        <polyline points="7 10 12 15 17 10"></polyline>
                                        <line x1="12" y1="15" x2="12" y2="3"></line>
                                    </svg>
                                    Download .ics
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="lead-page__form">
        <!-- Agent Cards - Inline -->
        <div class="lead-page__agents-row">
            <?php if ( ! empty( $lo_data ) ) : ?>
                <?php
                $lo_vcard_url = add_query_arg( [
                    'frs_vcard' => $lo_data['id'],
                    'type' => 'lo',
                ], get_permalink() );
                ?>
                <div class="lead-page__agent-card-light">
                    <?php if ( ! empty( $lo_data['photo'] ) ) : ?>
                        <img src="<?php echo esc_url( $lo_data['photo'] ); ?>" alt="<?php echo esc_attr( $lo_data['name'] ); ?>" class="lead-page__agent-photo">
                    <?php endif; ?>
                    <div class="lead-page__agent-info">
                        <h4><?php echo esc_html( $lo_data['name'] ); ?></h4>
                        <p><?php echo esc_html( $lo_data['title'] ); ?><?php if ( ! empty( $lo_data['nmls'] ) ) : ?> | NMLS# <?php echo esc_html( $lo_data['nmls'] ); ?><?php endif; ?></p>
                        <a href="<?php echo esc_url( $lo_vcard_url ); ?>" download="<?php echo esc_attr( sanitize_title( $lo_data['name'] ) ); ?>.vcf" class="lead-page__contact-btn">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                                <circle cx="9" cy="7" r="4"></circle>
                                <line x1="19" y1="8" x2="19" y2="14"></line>
                                <line x1="16" y1="11" x2="22" y2="11"></line>
                            </svg>
                            + Contact
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ( ! empty( $realtor_data ) ) : ?>
                <?php
                $realtor_vcard_url = add_query_arg( [
                    'frs_vcard' => $realtor_data['id'] ?: 'manual',
                    'type' => 'realtor',
                    'page_id' => $page_id,
                ], get_permalink() );
                ?>
                <div class="lead-page__agent-card-light">
                    <?php if ( ! empty( $realtor_data['photo'] ) ) : ?>
                        <img src="<?php echo esc_url( $realtor_data['photo'] ); ?>" alt="<?php echo esc_attr( $realtor_data['name'] ); ?>" class="lead-page__agent-photo">
                    <?php endif; ?>
                    <div class="lead-page__agent-info">
                        <h4><?php echo esc_html( $realtor_data['name'] ); ?></h4>
                        <p><?php echo esc_html( $realtor_data['title'] ); ?><?php if ( ! empty( $realtor_data['license'] ) ) : ?> | DRE# <?php echo esc_html( $realtor_data['license'] ); ?><?php endif; ?></p>
                        <a href="<?php echo esc_url( $realtor_vcard_url ); ?>" download="<?php echo esc_attr( sanitize_title( $realtor_data['name'] ) ); ?>.vcf" class="lead-page__contact-btn">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                                <circle cx="9" cy="7" r="4"></circle>
                                <line x1="19" y1="8" x2="19" y2="14"></line>
                                <line x1="16" y1="11" x2="22" y2="11"></line>
                            </svg>
                            + Contact
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="lead-page__form-header">
            <?php
            // Dynamic form headers based on page type
            $form_headers = [
                'open_house' => [
                    'title' => 'Sign In to View This Property',
                    'subtitle' => 'Fill out the form below and we\'ll send you more details',
                ],
                'customer_spotlight' => [
                    'title' => 'Get Your Free Assessment',
                    'subtitle' => 'Answer a few quick questions and we\'ll reach out with your personalized evaluation',
                ],
                'special_event' => [
                    'title' => 'Register for This Event',
                    'subtitle' => 'Secure your spot and we\'ll send you all the details',
                ],
                'mortgage_calculator' => [
                    'title' => 'Get Your Personalized Results',
                    'subtitle' => 'Complete the form to receive your custom mortgage analysis',
                ],
            ];
            $header = $form_headers[ $page_type ] ?? $form_headers['customer_spotlight'];
            ?>
            <h2 class="lead-page__form-title"><?php echo esc_html( $header['title'] ); ?></h2>
            <p class="lead-page__form-subtitle"><?php echo esc_html( $header['subtitle'] ); ?></p>
        </div>

        <div class="lead-page__form-container">
            <?php
            if ( $form_id && defined( 'FLUENTFORM' ) ) {
                // Render FluentForms with hidden field values via JavaScript
                echo do_shortcode( '[fluentform id="' . absint( $form_id ) . '"]' );
                ?>
                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    // Populate hidden fields with page data
                    const form = document.querySelector('.fluentform');
                    if (form) {
                        const hiddenFields = {
                            'lead_page_id': '<?php echo esc_js( $page_id ); ?>',
                            'page_type': '<?php echo esc_js( $page_type ); ?>',
                            'loan_officer_id': '<?php echo esc_js( $lo_id ); ?>',
                            'realtor_id': '<?php echo esc_js( get_post_meta( $page_id, '_frs_realtor_id', true ) ); ?>'
                        };

                        Object.entries(hiddenFields).forEach(([name, value]) => {
                            const input = form.querySelector('input[name="' + name + '"]');
                            if (input) input.value = value;
                        });
                    }
                });
                </script>
                <?php
            } else {
                // Fallback message if FluentForms not available
                echo '<p style="color: #64748b; text-align: center; padding: 40px 20px;">Form not available. Please contact the site administrator.</p>';
            }
            ?>
        </div>

        <?php if ( $consent_text ) : ?>
            <div class="lead-page__consent"><?php echo esc_html( $consent_text ); ?></div>
        <?php endif; ?>

        <div class="lead-page__powered">Powered by 21st Century Lending</div>
    </div>
</div>

<?php wp_footer(); ?>
</body>
</html>
<?php endif; ?>
