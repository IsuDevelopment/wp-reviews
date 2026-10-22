<?php
/**
 * View: all reviews.
 *
 * @package IsuDev\Reviews
 *
 * @var \IsuDev\Reviews\Admin\Reviews_List_Table $table
 */

defined( 'ABSPATH' ) || exit;

$table->prepare_items();
?>
<div class="wrap isurev-admin">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Reviews', 'isudev-reviews' ); ?></h1>
	<a class="page-title-action" href="<?php echo esc_url( admin_url( 'admin.php?page=isudev-reviews-sources' ) ); ?>"><?php esc_html_e( 'Manage sources', 'isudev-reviews' ); ?></a>
	<hr class="wp-header-end">
	<form method="get">
		<input type="hidden" name="page" value="isudev-reviews">
		<?php $table->display(); ?>
	</form>
</div>
