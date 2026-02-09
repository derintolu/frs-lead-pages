<?php
/**
 * Lead Submissions Admin Page
 *
 * @package FRSLeadPages
 */

namespace FRSLeadPages\Admin;

use FRSLeadPages\Core\Submissions as SubmissionsService;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Submissions {

	/**
	 * Initialize submissions page
	 */
	public static function init() {
		add_action( 'admin_menu', [ __CLASS__, 'add_menu_page' ] );
		add_action( 'admin_init', [ __CLASS__, 'handle_csv_export' ] );
		add_action( 'admin_init', [ __CLASS__, 'handle_delete_submission' ] );
	}

	/**
	 * Handle delete submission
	 */
	public static function handle_delete_submission() {
		if ( ! isset( $_GET['action'] ) || $_GET['action'] !== 'delete_submission' ) {
			return;
		}

		if ( ! isset( $_GET['submission_id'] ) || ! isset( $_GET['_wpnonce'] ) ) {
			return;
		}

		$submission_id = absint( $_GET['submission_id'] );

		if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'delete_submission_' . $submission_id ) ) {
			wp_die( __( 'Security check failed', 'frs-lead-pages' ) );
		}

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( __( 'Insufficient permissions', 'frs-lead-pages' ) );
		}

		// Delete submission
		SubmissionsService::delete( $submission_id );

		$redirect_url = admin_url( 'edit.php?post_type=frs_lead_page&page=frs-lead-pages-submissions&deleted=1' );
		if ( isset( $_GET['lead_page_id'] ) ) {
			$redirect_url .= '&lead_page_id=' . absint( $_GET['lead_page_id'] );
		}

		wp_redirect( $redirect_url );
		exit;
	}

	/**
	 * Add admin menu page
	 */
	public static function add_menu_page() {
		add_submenu_page(
			'edit.php?post_type=frs_lead_page',
			__( 'All Submissions', 'frs-lead-pages' ),
			__( 'All Submissions', 'frs-lead-pages' ),
			'edit_posts',
			'frs-lead-pages-submissions',
			[ __CLASS__, 'render_submissions_page' ]
		);
	}

	/**
	 * Render submissions page
	 */
	public static function render_submissions_page() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		$list_table = new Submissions_List_Table();
		$list_table->prepare_items();

		// Get filter options
		$selected_page = isset( $_GET['lead_page_id'] ) ? absint( $_GET['lead_page_id'] ) : 0;
		$lead_pages = get_posts( [
			'post_type'      => 'frs_lead_page',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		] );

		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<hr class="wp-header-end">

			<div class="tablenav top" style="margin-bottom: 15px;">
				<div class="alignleft actions">
					<label for="filter-lead-page" class="screen-reader-text"><?php _e( 'Filter by Lead Page', 'frs-lead-pages' ); ?></label>
					<select name="lead_page_id" id="filter-lead-page">
						<option value=""><?php _e( 'All Lead Pages', 'frs-lead-pages' ); ?></option>
						<?php foreach ( $lead_pages as $page ) : ?>
							<option value="<?php echo esc_attr( $page->ID ); ?>" <?php selected( $selected_page, $page->ID ); ?>>
								<?php echo esc_html( $page->post_title ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<button type="button" id="filter-submit" class="button"><?php _e( 'Filter', 'frs-lead-pages' ); ?></button>
				</div>

				<div class="alignleft actions">
					<form method="post" style="display: inline;">
						<?php wp_nonce_field( 'frs_export_submissions', 'frs_export_nonce' ); ?>
						<input type="hidden" name="action" value="export_submissions" />
						<input type="hidden" name="lead_page_id" value="<?php echo esc_attr( $selected_page ); ?>" />
						<button type="submit" class="button button-primary">
							<?php _e( 'Export to CSV', 'frs-lead-pages' ); ?>
						</button>
					</form>
				</div>
			</div>

			<form method="get">
				<input type="hidden" name="post_type" value="frs_lead_page" />
				<input type="hidden" name="page" value="frs-lead-pages-submissions" />
				<input type="hidden" name="lead_page_id" value="<?php echo esc_attr( $selected_page ); ?>" />
				<?php $list_table->display(); ?>
			</form>
		</div>

		<script>
		jQuery(document).ready(function($) {
			$('#filter-submit').on('click', function() {
				var leadPageId = $('#filter-lead-page').val();
				var url = new URL(window.location.href);
				if (leadPageId) {
					url.searchParams.set('lead_page_id', leadPageId);
				} else {
					url.searchParams.delete('lead_page_id');
				}
				url.searchParams.set('paged', '1'); // Reset to page 1
				window.location.href = url.toString();
			});
		});
		</script>
		<?php
	}

	/**
	 * Handle CSV export
	 */
	public static function handle_csv_export() {
		if ( ! isset( $_POST['action'] ) || $_POST['action'] !== 'export_submissions' ) {
			return;
		}

		if ( ! check_admin_referer( 'frs_export_submissions', 'frs_export_nonce' ) ) {
			wp_die( __( 'Security check failed', 'frs-lead-pages' ) );
		}

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( __( 'Insufficient permissions', 'frs-lead-pages' ) );
		}

		$lead_page_id = isset( $_POST['lead_page_id'] ) ? absint( $_POST['lead_page_id'] ) : 0;

		// Get submissions
		$filters = [];
		if ( $lead_page_id > 0 ) {
			$filters['page_id'] = $lead_page_id;
		}

		$submissions = SubmissionsService::get_submissions( $filters );

		// Generate CSV
		$filename = 'lead-submissions-' . date( 'Y-m-d-H-i-s' ) . '.csv';

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );

		$output = fopen( 'php://output', 'w' );

		// CSV headers
		fputcsv( $output, [
			'ID',
			'First Name',
			'Last Name',
			'Email',
			'Phone',
			'Lead Page',
			'Page Type',
			'Working With Agent',
			'Pre-Approved',
			'Interested in Pre-Approval',
			'Timeframe',
			'Comments',
			'Date',
			'Status',
		] );

		// CSV rows
		foreach ( $submissions as $submission ) {
			// Get full row from custom table for additional fields
			$full = SubmissionsService::get( (int) $submission['submission_id'] );

			fputcsv( $output, [
				$submission['id'],
				$submission['first_name'],
				$submission['last_name'],
				$submission['email'],
				$submission['phone'],
				$submission['lead_page_title'],
				ucwords( str_replace( '_', ' ', $submission['page_type'] ) ),
				$full->working_with_agent ?? '',
				$full->pre_approved ?? '',
				$full->interested_in_preapproval ?? '',
				$full->timeframe ?? '',
				$full->comments ?? '',
				$submission['created_at'],
				$submission['status'],
			] );
		}

		fclose( $output );
		exit;
	}
}

/**
 * Custom WP_List_Table for Submissions
 */
class Submissions_List_Table extends \WP_List_Table {

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct( [
			'singular' => 'submission',
			'plural'   => 'submissions',
			'ajax'     => false,
		] );
	}

	/**
	 * Get columns
	 */
	public function get_columns() {
		return [
			'name'      => __( 'Name', 'frs-lead-pages' ),
			'email'     => __( 'Email', 'frs-lead-pages' ),
			'phone'     => __( 'Phone', 'frs-lead-pages' ),
			'lead_page' => __( 'Lead Page', 'frs-lead-pages' ),
			'date'      => __( 'Date', 'frs-lead-pages' ),
			'status'    => __( 'Status', 'frs-lead-pages' ),
		];
	}

	/**
	 * Get sortable columns
	 */
	public function get_sortable_columns() {
		return [
			'name' => [ 'name', false ],
			'date' => [ 'date', true ],
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

		// Get filter
		$lead_page_id = isset( $_GET['lead_page_id'] ) ? absint( $_GET['lead_page_id'] ) : 0;

		$filters = [];
		if ( $lead_page_id > 0 ) {
			$filters['page_id'] = $lead_page_id;
		}

		$submissions = SubmissionsService::get_submissions( $filters );

		// Handle sorting
		$orderby = isset( $_GET['orderby'] ) ? sanitize_text_field( $_GET['orderby'] ) : 'date';
		$order   = isset( $_GET['order'] ) ? sanitize_text_field( $_GET['order'] ) : 'DESC';

		if ( $orderby === 'name' ) {
			usort( $submissions, function( $a, $b ) use ( $order ) {
				$name_a = $a['first_name'] . ' ' . $a['last_name'];
				$name_b = $b['first_name'] . ' ' . $b['last_name'];
				$result = strcmp( $name_a, $name_b );
				return $order === 'DESC' ? -$result : $result;
			} );
		} elseif ( $orderby === 'date' ) {
			usort( $submissions, function( $a, $b ) use ( $order ) {
				$result = strcmp( $a['created_at'], $b['created_at'] );
				return $order === 'DESC' ? -$result : $result;
			} );
		}

		// Pagination
		$per_page     = 20;
		$current_page = $this->get_pagenum();
		$total_items  = count( $submissions );

		$this->items = array_slice( $submissions, ( $current_page - 1 ) * $per_page, $per_page );

		$this->set_pagination_args( [
			'total_items' => $total_items,
			'per_page'    => $per_page,
			'total_pages' => ceil( $total_items / $per_page ),
		] );
	}

	/**
	 * Column: Name
	 */
	public function column_name( $item ) {
		$name = trim( $item['first_name'] . ' ' . $item['last_name'] );

		$actions = [];

		// Link to lead page
		if ( ! empty( $item['lead_page_id'] ) ) {
			$page_url = get_permalink( $item['lead_page_id'] );
			$actions['view_page'] = sprintf(
				'<a href="%s" target="_blank">%s</a>',
				esc_url( $page_url ),
				__( 'View Page', 'frs-lead-pages' )
			);
		}

		// Delete link
		if ( ! empty( $item['submission_id'] ) ) {
			$lead_page_param = isset( $_GET['lead_page_id'] ) ? '&lead_page_id=' . absint( $_GET['lead_page_id'] ) : '';
			$delete_url = wp_nonce_url(
				admin_url( 'edit.php?post_type=frs_lead_page&page=frs-lead-pages-submissions&action=delete_submission&submission_id=' . $item['submission_id'] . $lead_page_param ),
				'delete_submission_' . $item['submission_id']
			);
			$actions['delete'] = sprintf(
				'<a href="%s" class="submitdelete" onclick="return confirm(\'%s\');">%s</a>',
				esc_url( $delete_url ),
				esc_js( __( 'Are you sure you want to delete this submission?', 'frs-lead-pages' ) ),
				__( 'Delete', 'frs-lead-pages' )
			);
		}

		return sprintf(
			'<strong>%s</strong>%s',
			esc_html( $name ),
			$this->row_actions( $actions )
		);
	}

	/**
	 * Column: Email
	 */
	public function column_email( $item ) {
		return sprintf(
			'<a href="mailto:%s">%s</a>',
			esc_attr( $item['email'] ),
			esc_html( $item['email'] )
		);
	}

	/**
	 * Column: Phone
	 */
	public function column_phone( $item ) {
		if ( empty( $item['phone'] ) ) {
			return '—';
		}

		return sprintf(
			'<a href="tel:%s">%s</a>',
			esc_attr( preg_replace( '/[^0-9]/', '', $item['phone'] ) ),
			esc_html( $item['phone'] )
		);
	}

	/**
	 * Column: Lead Page
	 */
	public function column_lead_page( $item ) {
		$page_type = ucwords( str_replace( '_', ' ', $item['page_type'] ) );

		return sprintf(
			'<a href="%s">%s</a><br><span class="description">%s</span>',
			esc_url( get_edit_post_link( $item['lead_page_id'] ) ),
			esc_html( $item['lead_page_title'] ),
			esc_html( $page_type )
		);
	}

	/**
	 * Column: Date
	 */
	public function column_date( $item ) {
		$time_diff = human_time_diff( strtotime( $item['created_at'] ), current_time( 'timestamp' ) );

		return sprintf(
			'<abbr title="%s">%s ago</abbr>',
			esc_attr( $item['created_at'] ),
			esc_html( $time_diff )
		);
	}

	/**
	 * Column: Status
	 */
	public function column_status( $item ) {
		$status = $item['status'];

		$status_labels = [
			'new'       => __( 'New', 'frs-lead-pages' ),
			'unread'    => __( 'Unread', 'frs-lead-pages' ),
			'read'      => __( 'Read', 'frs-lead-pages' ),
			'contacted' => __( 'Contacted', 'frs-lead-pages' ),
			'converted' => __( 'Converted', 'frs-lead-pages' ),
		];

		$label = isset( $status_labels[ $status ] ) ? $status_labels[ $status ] : ucfirst( $status );

		$class = 'new' === $status || 'unread' === $status ? 'status-new' : 'status-normal';

		return sprintf(
			'<span class="%s">%s</span>',
			esc_attr( $class ),
			esc_html( $label )
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
		_e( 'No submissions found.', 'frs-lead-pages' );
	}
}
