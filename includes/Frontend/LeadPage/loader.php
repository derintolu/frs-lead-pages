<?php
/**
 * Lead Page Template Loader
 *
 * This file is loaded by WordPress's template_include filter.
 * It delegates to the Template class which handles routing to the correct template.
 *
 * @package FRSLeadPages
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Render the lead page using the Template class
global $post;
\FRSLeadPages\Frontend\LeadPage\Template::render( $post->ID );
