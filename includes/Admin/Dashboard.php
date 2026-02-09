<?php
/**
 * Lead Pages Dashboard Admin Page
 *
 * @package FRSLeadPages
 */

namespace FRSLeadPages\Admin;

use FRSLeadPages\Core\Submissions;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Dashboard {

	/**
	 * Initialize admin dashboard
	 */
	public static function init() {
		add_action( 'admin_menu', [ __CLASS__, 'add_menu_page' ] );
		add_action( 'admin_init', [ __CLASS__, 'handle_delete_action' ] );
	}

	/**
	 * Handle delete action
	 */
	public static function handle_delete_action() {
		if ( ! isset( $_GET['action'] ) || $_GET['action'] !== 'delete_lead_page' ) {
			return;
		}

		if ( ! isset( $_GET['post_id'] ) || ! isset( $_GET['_wpnonce'] ) ) {
			return;
		}

		$post_id = absint( $_GET['post_id'] );

		if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'delete_lead_page_' . $post_id ) ) {
			wp_die( __( 'Security check failed', 'frs-lead-pages' ) );
		}

		if ( ! current_user_can( 'delete_post', $post_id ) ) {
			wp_die( __( 'Insufficient permissions', 'frs-lead-pages' ) );
		}

		wp_delete_post( $post_id, true );

		wp_redirect( admin_url( 'edit.php?post_type=frs_lead_page&page=frs-lead-pages-dashboard&deleted=1' ) );
		exit;
	}

	/**
	 * Add admin menu page
	 */
	public static function add_menu_page() {
		add_submenu_page(
			'edit.php?post_type=frs_lead_page',
			__( 'Lead Pages Dashboard', 'frs-lead-pages' ),
			__( 'Dashboard', 'frs-lead-pages' ),
			'edit_posts',
			'frs-lead-pages-dashboard',
			[ __CLASS__, 'render_dashboard_page' ]
		);
	}

	/**
	 * Render dashboard page
	 */
	public static function render_dashboard_page() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		$list_table = new Dashboard_List_Table();
		$list_table->prepare_items();

		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=frs_lead_page' ) ); ?>" class="page-title-action">
				<?php _e( 'Add New', 'frs-lead-pages' ); ?>
			</a>

			<hr class="wp-header-end">

			<form method="get">
				<input type="hidden" name="post_type" value="frs_lead_page" />
				<input type="hidden" name="page" value="frs-lead-pages-dashboard" />
				<?php $list_table->display(); ?>
			</form>
		</div>
		<?php
	}
}

/**
 * Custom WP_List_Table for Lead Pages Dashboard
 */
class Dashboard_List_Table extends \WP_List_Table {

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct( [
			'singular' => 'lead_page',
			'plural'   => 'lead_pages',
			'ajax'     => false,
		] );
	}

	/**
	 * Get columns
	 */
	public function get_columns() {
		return [
			'title'      => __( 'Title', 'frs-lead-pages' ),
			'page_type'  => __( 'Page Type', 'frs-lead-pages' ),
			'creator'    => __( 'Creator', 'frs-lead-pages' ),
			'views'      => __( 'Views', 'frs-lead-pages' ),
			'leads'      => __( 'Leads', 'frs-lead-pages' ),
			'date'       => __( 'Date Created', 'frs-lead-pages' ),
		];
	}

	/**
	 * Get sortable columns
	 */
	public function get_sortable_columns() {
		return [
			'title' => [ 'title', false ],
			'date'  => [ 'date', true ],
			'views' => [ 'views', false ],
			'leads' => [ 'leads', false ],
		];
	}

	/**
	 * Prepare items for display
	 */
	public function prepare_items() {
		$columns  = $this->get_columns();
		$hidden   = [];
		$sortable = $this->get_sortable_columns();

		$this->_column_headers = [ $columns, $hidden, $sortable ];

		$per_page     = 20;
		$current_page = $this->get_pagenum();

		// Get orderby and order
		$orderby = isset( $_GET['orderby'] ) ? sanitize_text_field( $_GET['orderby'] ) : 'date';
		$order   = isset( $_GET['order'] ) ? sanitize_text_field( $_GET['order'] ) : 'DESC';

		// Build query args
		$args = [
			'post_type'      => 'frs_lead_page',
			'post_status'    => [ 'publish', 'draft', 'pending' ],
			'posts_per_page' => $per_page,
			'paged'          => $current_page,
		];

		// Handle sorting
		if ( $orderby === 'views' ) {
			$args['meta_key'] = '_frs_page_views';
			$args['orderby']  = 'meta_value_num';
			$args['order']    = $order;
		} elseif ( $orderby === 'leads' ) {
			$args['meta_key'] = '_frs_page_submissions';
			$args['orderby']  = 'meta_value_num';
			$args['order']    = $order;
		} else {
			$args['orderby'] = $orderby;
			$args['order']   = $order;
		}

		$query = new \WP_Query( $args );

		$this->items = $query->posts;

		$this->set_pagination_args( [
			'total_items' => $query->found_posts,
			'per_page'    => $per_page,
			'total_pages' => $query->max_num_pages,
		] );
	}

	/**
	 * Column: Title
	 */
	public function column_title( $item ) {
		$edit_url = get_edit_post_link( $item->ID );
		$view_url = get_permalink( $item->ID );
		$delete_url = wp_nonce_url(
			admin_url( 'edit.php?post_type=frs_lead_page&page=frs-lead-pages-dashboard&action=delete_lead_page&post_id=' . $item->ID ),
			'delete_lead_page_' . $item->ID
		);

		$actions = [
			'edit' => sprintf(
				'<a href="%s">%s</a>',
				esc_url( $edit_url ),
				__( 'Edit', 'frs-lead-pages' )
			),
			'view' => sprintf(
				'<a href="%s" target="_blank">%s</a>',
				esc_url( $view_url ),
				__( 'View', 'frs-lead-pages' )
			),
			'submissions' => sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'edit.php?post_type=frs_lead_page&page=frs-lead-pages-submissions&lead_page_id=' . $item->ID ) ),
				__( 'View Submissions', 'frs-lead-pages' )
			),
			'delete' => sprintf(
				'<a href="%s" class="submitdelete" onclick="return confirm(\'%s\');">%s</a>',
				esc_url( $delete_url ),
				esc_js( __( 'Are you sure you want to delete this lead page?', 'frs-lead-pages' ) ),
				__( 'Delete', 'frs-lead-pages' )
			),
		];

		return sprintf(
			'<strong><a href="%s">%s</a></strong>%s',
			esc_url( $edit_url ),
			esc_html( $item->post_title ),
			$this->row_actions( $actions )
		);
	}

	/**
	 * Column: Page Type
	 */
	public function column_page_type( $item ) {
		$page_type = get_post_meta( $item->ID, '_frs_page_type', true );

		$type_labels = [
			'open_house'        => __( 'Open House', 'frs-lead-pages' ),
			'customer_spotlight' => __( 'Customer Spotlight', 'frs-lead-pages' ),
			'special_event'     => __( 'Special Event', 'frs-lead-pages' ),
		];

		return isset( $type_labels[ $page_type ] ) ? $type_labels[ $page_type ] : ucwords( str_replace( '_', ' ', $page_type ) );
	}

	/**
	 * Column: Creator
	 */
	public function column_creator( $item ) {
		$realtor_id = get_post_meta( $item->ID, '_frs_realtor_id', true );
		$lo_id      = get_post_meta( $item->ID, '_frs_loan_officer_id', true );

		$creators = [];

		if ( $realtor_id ) {
			$realtor = get_user_by( 'ID', $realtor_id );
			if ( $realtor ) {
				$creators[] = sprintf(
					'<div><strong>%s:</strong> %s</div>',
					__( 'Realtor', 'frs-lead-pages' ),
					esc_html( $realtor->display_name )
				);
			}
		}

		if ( $lo_id ) {
			$lo = get_user_by( 'ID', $lo_id );
			if ( $lo ) {
				$creators[] = sprintf(
					'<div><strong>%s:</strong> %s</div>',
					__( 'LO', 'frs-lead-pages' ),
					esc_html( $lo->display_name )
				);
			}
		}

		if ( empty( $creators ) ) {
			$author = get_user_by( 'ID', $item->post_author );
			return $author ? esc_html( $author->display_name ) : '—';
		}

		return implode( '', $creators );
	}

	/**
	 * Column: Views
	 */
	public function column_views( $item ) {
		$views = (int) get_post_meta( $item->ID, '_frs_page_views', true );
		return number_format_i18n( $views );
	}

	/**
	 * Column: Leads
	 */
	public function column_leads( $item ) {
		$leads = Submissions::count_for_page( $item->ID );

		return number_format_i18n( $leads );
	}

	/**
	 * Column: Date
	 */
	public function column_date( $item ) {
		$time_diff = human_time_diff( strtotime( $item->post_date ), current_time( 'timestamp' ) );

		return sprintf(
			'<abbr title="%s">%s ago</abbr><br><span class="post-status">%s</span>',
			esc_attr( get_the_date( 'c', $item ) ),
			esc_html( $time_diff ),
			esc_html( ucfirst( $item->post_status ) )
		);
	}

	/**
	 * Default column
	 */
	public function column_default( $item, $column_name ) {
		return '—';
	}

	/**
	 * Message to display when no items
	 */
	public function no_items() {
		_e( 'No lead pages found.', 'frs-lead-pages' );
	}
}
