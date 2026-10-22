<?php
/**
 * Reviews list table.
 *
 * @package IsuDev\Reviews
 */

declare( strict_types = 1 );

namespace IsuDev\Reviews\Admin;

use IsuDev\Reviews\Renderer;
use IsuDev\Reviews\Review_Repository;
use IsuDev\Reviews\Source_Repository;

defined( 'ABSPATH' ) || exit;

if ( ! \class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * All stored reviews with source filter and hide/show.
 */
final class Reviews_List_Table extends \WP_List_Table {

	const PER_PAGE = 20;

	/**
	 * Source names by ID.
	 *
	 * @var array<int, string>
	 */
	private array $source_names = array();

	/**
	 * Constructor.
	 *
	 * @param Review_Repository $reviews Reviews.
	 * @param Source_Repository $sources Sources.
	 */
	public function __construct( private Review_Repository $reviews, Source_Repository $sources ) {
		parent::__construct(
			array(
				'singular' => 'review',
				'plural'   => 'reviews',
				'ajax'     => false,
			)
		);

		foreach ( $sources->all() as $source ) {
			$this->source_names[ (int) $source['id'] ] = '' !== (string) $source['name'] ? (string) $source['name'] : (string) $source['external_id'];
		}
	}

	/**
	 * Columns.
	 *
	 * @return array<string, string>
	 */
	public function get_columns(): array {
		return array(
			'cb'           => '<input type="checkbox" />',
			'author_name'  => \__( 'Author', 'isudev-reviews' ),
			'rating'       => \__( 'Rating', 'isudev-reviews' ),
			'content'      => \__( 'Review', 'isudev-reviews' ),
			'source_id'    => \__( 'Source', 'isudev-reviews' ),
			'published_at' => \__( 'Date', 'isudev-reviews' ),
		);
	}

	/**
	 * Bulk actions.
	 *
	 * @return array<string, string>
	 */
	protected function get_bulk_actions(): array {
		return array(
			'hide' => \__( 'Hide', 'isudev-reviews' ),
			'show' => \__( 'Show', 'isudev-reviews' ),
		);
	}

	/**
	 * Current source filter.
	 */
	private function current_source(): int {
		return \absint( $_GET['source'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter.
	}

	/**
	 * Loads the page of items.
	 */
	public function prepare_items(): void {
		$args = array(
			'source_ids'     => $this->current_source() ? array( $this->current_source() ) : array(),
			'include_hidden' => true,
		);

		$total = $this->reviews->count( $args );

		$this->items = $this->reviews->query(
			$args + array(
				'limit'  => self::PER_PAGE,
				'offset' => ( $this->get_pagenum() - 1 ) * self::PER_PAGE,
			)
		);

		$this->set_pagination_args(
			array(
				'total_items' => $total,
				'per_page'    => self::PER_PAGE,
			)
		);
		$this->_column_headers = array( $this->get_columns(), array(), array(), 'author_name' );
	}

	/**
	 * Source filter dropdown.
	 *
	 * @param string $which top|bottom.
	 */
	protected function extra_tablenav( $which ): void {
		if ( 'top' !== $which || \count( $this->source_names ) < 2 ) {
			return;
		}
		?>
		<div class="alignleft actions">
			<label class="screen-reader-text" for="isurev-source-filter"><?php \esc_html_e( 'Filter by source', 'isudev-reviews' ); ?></label>
			<select name="source" id="isurev-source-filter">
				<option value="0"><?php \esc_html_e( 'All sources', 'isudev-reviews' ); ?></option>
				<?php foreach ( $this->source_names as $id => $name ) : ?>
					<option value="<?php echo \esc_attr( (string) $id ); ?>" <?php \selected( $this->current_source(), $id ); ?>><?php echo \esc_html( $name ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php \submit_button( \__( 'Filter', 'isudev-reviews' ), '', 'filter_action', false ); ?>
		</div>
		<?php
	}

	/**
	 * Hidden rows get a class.
	 *
	 * @param array<string, mixed> $item Row.
	 */
	public function single_row( $item ): void {
		echo '<tr class="' . ( (int) $item['is_hidden'] ? 'isurev-row--hidden' : '' ) . '">';
		$this->single_row_columns( $item );
		echo '</tr>';
	}

	/**
	 * Checkbox.
	 *
	 * @param array<string, mixed> $item Row.
	 */
	protected function column_cb( $item ): string {
		return \sprintf( '<input type="checkbox" name="review[]" value="%d" />', (int) $item['id'] );
	}

	/**
	 * Author with avatar and hide/show row action.
	 *
	 * @param array<string, mixed> $item Row.
	 */
	protected function column_author_name( array $item ): string {
		$hidden = (bool) (int) $item['is_hidden'];
		$url    = \wp_nonce_url(
			\admin_url( 'admin-post.php?action=isurev_review_visibility&review[]=' . (int) $item['id'] . '&visibility=' . ( $hidden ? 'show' : 'hide' ) ),
			'isurev_review_visibility'
		);

		$actions = array(
			'visibility' => \sprintf( '<a href="%s">%s</a>', \esc_url( $url ), $hidden ? \esc_html__( 'Show', 'isudev-reviews' ) : \esc_html__( 'Hide', 'isudev-reviews' ) ),
		);
		if ( '' !== (string) $item['review_url'] ) {
			$actions['view'] = \sprintf( '<a href="%s" target="_blank" rel="noopener">%s</a>', \esc_url( (string) $item['review_url'] ), \esc_html__( 'View original', 'isudev-reviews' ) );
		}

		return \sprintf(
			'<div class="isurev-admin-author">%1$s<strong>%2$s</strong>%3$s</div>%4$s',
			Renderer::avatar( $item ),
			\esc_html( (string) $item['author_name'] ),
			$hidden ? ' <span class="isurev-badge">' . \esc_html__( 'Hidden', 'isudev-reviews' ) . '</span>' : '',
			$this->row_actions( $actions )
		);
	}

	/**
	 * Stars.
	 *
	 * @param array<string, mixed> $item Row.
	 */
	protected function column_rating( array $item ): string {
		return Renderer::stars( $item['rating'], (string) $item['recommendation'] );
	}

	/**
	 * Text excerpt.
	 *
	 * @param array<string, mixed> $item Row.
	 */
	protected function column_content( array $item ): string {
		$content = (string) $item['content'];

		return '' === $content
			? '<em>' . \esc_html__( '(rating only)', 'isudev-reviews' ) . '</em>'
			: \esc_html( \wp_trim_words( $content, 40 ) );
	}

	/**
	 * Platform icon + source name.
	 *
	 * @param array<string, mixed> $item Row.
	 */
	protected function column_source_id( array $item ): string {
		return Renderer::platform_icon( (string) $item['platform'] ) . ' ' . \esc_html( $this->source_names[ (int) $item['source_id'] ] ?? '' );
	}

	/**
	 * Date.
	 *
	 * @param array<string, mixed> $item Row.
	 */
	protected function column_published_at( array $item ): string {
		return \esc_html( \wp_date( (string) \get_option( 'date_format' ), (int) \strtotime( $item['published_at'] . ' UTC' ) ) );
	}

	/**
	 * Empty state.
	 */
	public function no_items(): void {
		\printf(
			/* translators: %s: link to the Sources screen. */
			\esc_html__( 'No reviews yet. %s', 'isudev-reviews' ),
			'<a href="' . \esc_url( \admin_url( 'admin.php?page=isudev-reviews-sources' ) ) . '">' . \esc_html__( 'Add a source', 'isudev-reviews' ) . '</a>'
		);
	}
}
