<?php
class Zerif_Theme_Info extends WP_Customize_Control {
	public function render_content() {
		$docs_url = 'http://goodherbwebmart.com/';
		$demo_url = 'http://goodherbwebmart.com/';
		$fvp_url = 'http://goodherbwebmart.com/';
		$review_url = 'http://goodherbwebmart.com/'; ?>
		<div class="zerif-theme-info">
			<?php
			printf( '<a href="'.esc_url( $docs_url ).'" target="_blank">%s</a>', __( 'View Documentation', 'zerif-lite' ) ); ?>
			<hr/>
			<?php
			printf( '<a href="'.esc_url( $demo_url ).'" target="_blank">%s</a>', __( 'View Demo', 'zerif-lite' ) ); ?>
			<hr/>
			<?php
			printf( '<a href="'.esc_url( $fvp_url ).'" target="_blank">%s</a>', __( 'Free VS Pro', 'zerif-lite' ) ); ?>
			<hr/>
			<?php
			printf( '<a href="'.esc_url( $review_url ).'" target="_blank">%s</a>', __( 'Leave a review', 'zerif-lite' ) ); ?>
		</div>
		<?php
	}
}