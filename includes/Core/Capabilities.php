<?php
/**
 * Custom Capabilities for FRS Lead Pages
 *
 * Registers custom capabilities for the frs_lead_page post type
 * and assigns them to appropriate roles.
 *
 * @package FRSLeadPages
 */

namespace FRSLeadPages\Core;

class Capabilities {

	/**
	 * Custom capability type for lead pages
	 */
	const CAPABILITY_TYPE = 'frs_lead_page';

	/**
	 * All capabilities for this post type
	 */
	const CAPABILITIES = [
		// Primitive capabilities
		'edit_frs_lead_page',
		'read_frs_lead_page',
		'delete_frs_lead_page',
		// Meta capabilities
		'edit_frs_lead_pages',
		'edit_others_frs_lead_pages',
		'edit_private_frs_lead_pages',
		'edit_published_frs_lead_pages',
		'publish_frs_lead_pages',
		'read_private_frs_lead_pages',
		'delete_frs_lead_pages',
		'delete_others_frs_lead_pages',
		'delete_private_frs_lead_pages',
		'delete_published_frs_lead_pages',
	];

	/**
	 * Role capability mappings
	 *
	 * Defines which capabilities each role should have
	 */
	const ROLE_CAPABILITIES = [
		'administrator' => [
			'edit_frs_lead_page'            => true,
			'read_frs_lead_page'            => true,
			'delete_frs_lead_page'          => true,
			'edit_frs_lead_pages'           => true,
			'edit_others_frs_lead_pages'    => true,
			'edit_private_frs_lead_pages'   => true,
			'edit_published_frs_lead_pages' => true,
			'publish_frs_lead_pages'        => true,
			'read_private_frs_lead_pages'   => true,
			'delete_frs_lead_pages'         => true,
			'delete_others_frs_lead_pages'  => true,
			'delete_private_frs_lead_pages' => true,
			'delete_published_frs_lead_pages' => true,
		],
		'editor' => [
			'edit_frs_lead_page'            => true,
			'read_frs_lead_page'            => true,
			'delete_frs_lead_page'          => true,
			'edit_frs_lead_pages'           => true,
			'edit_others_frs_lead_pages'    => true,
			'edit_private_frs_lead_pages'   => true,
			'edit_published_frs_lead_pages' => true,
			'publish_frs_lead_pages'        => true,
			'read_private_frs_lead_pages'   => true,
			'delete_frs_lead_pages'         => true,
			'delete_others_frs_lead_pages'  => true,
			'delete_private_frs_lead_pages' => true,
			'delete_published_frs_lead_pages' => true,
		],
		'realtor_partner' => [
			'edit_frs_lead_page'            => true,
			'read_frs_lead_page'            => true,
			'delete_frs_lead_page'          => true,
			'edit_frs_lead_pages'           => true,
			'edit_others_frs_lead_pages'    => false, // Can only edit own
			'edit_private_frs_lead_pages'   => true,
			'edit_published_frs_lead_pages' => true,
			'publish_frs_lead_pages'        => true,
			'read_private_frs_lead_pages'   => true,
			'delete_frs_lead_pages'         => true,
			'delete_others_frs_lead_pages'  => false, // Can only delete own
			'delete_private_frs_lead_pages' => true,
			'delete_published_frs_lead_pages' => true,
		],
		'loan_officer' => [
			'edit_frs_lead_page'            => true,
			'read_frs_lead_page'            => true,
			'delete_frs_lead_page'          => false, // Cannot delete
			'edit_frs_lead_pages'           => true,
			'edit_others_frs_lead_pages'    => false, // Can only view/edit pages they're assigned to
			'edit_private_frs_lead_pages'   => true,
			'edit_published_frs_lead_pages' => true,
			'publish_frs_lead_pages'        => false, // Cannot publish
			'read_private_frs_lead_pages'   => true,
			'delete_frs_lead_pages'         => false,
			'delete_others_frs_lead_pages'  => false,
			'delete_private_frs_lead_pages' => false,
			'delete_published_frs_lead_pages' => false,
		],
	];

	/**
	 * Initialize hooks
	 */
	public static function init() {
		// Map meta capabilities to primitive capabilities
		add_filter( 'map_meta_cap', [ __CLASS__, 'map_meta_cap' ], 10, 4 );
	}

	/**
	 * Register capabilities on plugin activation
	 *
	 * This should be called during plugin activation to add
	 * capabilities to the database.
	 */
	public static function register() {
		foreach ( self::ROLE_CAPABILITIES as $role_name => $caps ) {
			$role = get_role( $role_name );

			if ( ! $role ) {
				continue;
			}

			foreach ( $caps as $cap => $grant ) {
				if ( $grant ) {
					$role->add_cap( $cap );
				} else {
					// Explicitly remove if set to false
					$role->remove_cap( $cap );
				}
			}
		}

		// Mark capabilities as registered
		update_option( 'frs_lead_pages_caps_version', FRS_LEAD_PAGES_VERSION );
	}

	/**
	 * Unregister capabilities on plugin deactivation
	 *
	 * Removes all custom capabilities from roles.
	 */
	public static function unregister() {
		foreach ( self::ROLE_CAPABILITIES as $role_name => $caps ) {
			$role = get_role( $role_name );

			if ( ! $role ) {
				continue;
			}

			foreach ( array_keys( $caps ) as $cap ) {
				$role->remove_cap( $cap );
			}
		}

		delete_option( 'frs_lead_pages_caps_version' );
	}

	/**
	 * Map meta capabilities to primitive capabilities
	 *
	 * This allows WordPress to properly check ownership and
	 * determine if a user can edit/delete a specific post.
	 *
	 * @param array  $caps    Required primitive capabilities.
	 * @param string $cap     Capability being checked.
	 * @param int    $user_id User ID.
	 * @param array  $args    Additional arguments (post ID, etc.).
	 * @return array Modified capabilities.
	 */
	public static function map_meta_cap( $caps, $cap, $user_id, $args ) {
		// Only handle our post type capabilities
		$meta_caps = [
			'edit_frs_lead_page',
			'read_frs_lead_page',
			'delete_frs_lead_page',
		];

		if ( ! in_array( $cap, $meta_caps, true ) ) {
			return $caps;
		}

		// Get the post
		$post = isset( $args[0] ) ? get_post( $args[0] ) : null;

		if ( ! $post || $post->post_type !== 'frs_lead_page' ) {
			return $caps;
		}

		$post_type = get_post_type_object( $post->post_type );

		if ( ! $post_type ) {
			return $caps;
		}

		// Get the user
		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return [ 'do_not_allow' ];
		}

		// Check if user is the post author
		$is_author = ( (int) $user_id === (int) $post->post_author );

		// Check if user is the assigned realtor
		$realtor_id = (int) get_post_meta( $post->ID, '_frs_realtor_id', true );
		$is_realtor = ( (int) $user_id === $realtor_id );

		// Check if user is the assigned loan officer
		$lo_id = (int) get_post_meta( $post->ID, '_frs_loan_officer_id', true );
		$is_loan_officer = ( (int) $user_id === $lo_id );

		// Owner means: author OR assigned realtor
		$is_owner = $is_author || $is_realtor;

		switch ( $cap ) {
			case 'edit_frs_lead_page':
				if ( $post->post_status === 'publish' ) {
					// Published post
					if ( $is_owner || $is_loan_officer ) {
						$caps = [ 'edit_published_frs_lead_pages' ];
					} else {
						$caps = [ 'edit_others_frs_lead_pages' ];
					}
				} elseif ( $post->post_status === 'private' ) {
					// Private post
					if ( $is_owner || $is_loan_officer ) {
						$caps = [ 'edit_private_frs_lead_pages' ];
					} else {
						$caps = [ 'edit_others_frs_lead_pages' ];
					}
				} else {
					// Draft, pending, etc.
					if ( $is_owner || $is_loan_officer ) {
						$caps = [ 'edit_frs_lead_pages' ];
					} else {
						$caps = [ 'edit_others_frs_lead_pages' ];
					}
				}
				break;

			case 'read_frs_lead_page':
				if ( $post->post_status === 'private' ) {
					if ( $is_owner || $is_loan_officer ) {
						$caps = [ 'read' ];
					} else {
						$caps = [ 'read_private_frs_lead_pages' ];
					}
				} else {
					// Public posts are readable by all
					$caps = [ 'read' ];
				}
				break;

			case 'delete_frs_lead_page':
				if ( $post->post_status === 'publish' ) {
					if ( $is_owner ) {
						$caps = [ 'delete_published_frs_lead_pages' ];
					} else {
						$caps = [ 'delete_others_frs_lead_pages' ];
					}
				} elseif ( $post->post_status === 'private' ) {
					if ( $is_owner ) {
						$caps = [ 'delete_private_frs_lead_pages' ];
					} else {
						$caps = [ 'delete_others_frs_lead_pages' ];
					}
				} else {
					if ( $is_owner ) {
						$caps = [ 'delete_frs_lead_pages' ];
					} else {
						$caps = [ 'delete_others_frs_lead_pages' ];
					}
				}
				break;
		}

		return $caps;
	}

	/**
	 * Check if capabilities need to be updated
	 *
	 * Useful for plugin updates that add new capabilities.
	 *
	 * @return bool True if capabilities need updating.
	 */
	public static function needs_update(): bool {
		$current_version = get_option( 'frs_lead_pages_caps_version', '0' );
		return version_compare( $current_version, FRS_LEAD_PAGES_VERSION, '<' );
	}

	/**
	 * Get capability type array for register_post_type
	 *
	 * @return array Capability type configuration.
	 */
	public static function get_capability_type(): array {
		return [ 'frs_lead_page', 'frs_lead_pages' ];
	}

	/**
	 * Get full capabilities array for register_post_type
	 *
	 * @return array All capabilities mapped for the post type.
	 */
	public static function get_capabilities(): array {
		return [
			// Meta capabilities (mapped via map_meta_cap)
			'edit_post'              => 'edit_frs_lead_page',
			'read_post'              => 'read_frs_lead_page',
			'delete_post'            => 'delete_frs_lead_page',
			// Primitive capabilities
			'edit_posts'             => 'edit_frs_lead_pages',
			'edit_others_posts'      => 'edit_others_frs_lead_pages',
			'edit_private_posts'     => 'edit_private_frs_lead_pages',
			'edit_published_posts'   => 'edit_published_frs_lead_pages',
			'publish_posts'          => 'publish_frs_lead_pages',
			'read_private_posts'     => 'read_private_frs_lead_pages',
			'delete_posts'           => 'delete_frs_lead_pages',
			'delete_others_posts'    => 'delete_others_frs_lead_pages',
			'delete_private_posts'   => 'delete_private_frs_lead_pages',
			'delete_published_posts' => 'delete_published_frs_lead_pages',
		];
	}
}
