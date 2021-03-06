<?php
/**
 * Recent posts with featured image
 *
 * @package Elementor Addon Widgets
 */

class EAW_Recent_Posts extends WP_Widget {

	public function __construct() {
		$widget_ops = array(
			'classname'                   => 'widget_recent_posts',
			'description'                 => __( 'Recent posts with featured image - ideal for use with Elementor Page Builder plugin', 'themeisle-companion' ),
			'customize_selective_refresh' => true,
		);

		parent::__construct( 'eaw-recent-posts', __( 'EAW: Elementor Recent Posts', 'themeisle-companion' ), $widget_ops );
		$this->alt_option_name = 'widget_recent_entries';

		add_action( 'save_post', array( $this, 'flush_widget_cache' ) );
		add_action( 'deleted_post', array( $this, 'flush_widget_cache' ) );
		add_action( 'switch_theme', array( $this, 'flush_widget_cache' ) );
	}

	/**
	 * @param array $args
	 * @param array $instance
	 */
	public function widget( $args, $instance ) {
		$cache = array();
		if ( ! $this->is_preview() ) {
			$cache = wp_cache_get( 'widget_recent_posts', 'widget' );
		}

		if ( ! is_array( $cache ) ) {
			$cache = array();
		}

		if ( ! isset( $args['widget_id'] ) ) {
			$args['widget_id'] = $this->id;
		}

		if ( isset( $cache[ $args['widget_id'] ] ) ) {
			echo $cache[ $args['widget_id'] ];

			return;
		}

		ob_start();

		$title = ( ! empty( $instance['title'] ) ) ? $instance['title'] : '';
		$title = apply_filters( 'widget_title', $title, $instance, $this->id_base );

		$number = ( ! empty( $instance['number'] ) ) ? absint( $instance['number'] ) : 3;
		if ( ! $number ) {
			$number = 3;
		}
		$show_excerpt = isset( $instance['show_excerpt'] ) ? $instance['show_excerpt'] : false;
		$excerptcount = ( ! empty( $instance['excerptcount'] ) ) ? absint( $instance['excerptcount'] ) : 20;

		if ( '' == $excerptcount || '0' == $excerptcount ) {
			$excerptcount = 20;
		}

		$eawp = new WP_Query(
			apply_filters(
				'eaw_widget_posts_args', array(
					'posts_per_page'      => $number,
					'no_found_rows'       => true,
					'post_status'         => 'publish',
					'ignore_sticky_posts' => true,
				)
			)
		);

		if ( $eawp->have_posts() ) {
			echo $args['before_widget'];

			if ( $title ) {
				echo $args['before_title'] . $title . $args['after_title'];
			}

			while ( $eawp->have_posts() ) :
				$eawp->the_post(); ?>
				<div class="eaw-recent-posts">
					<?php
					if ( has_post_thumbnail() ) {
						the_post_thumbnail( 'medium' );
					} ?>
					<div class="eaw-content">
						<h3><a href="<?php the_permalink(); ?>"><?php get_the_title() ? the_title() : the_ID(); ?></a>
						</h3>
						<p>
							<?php
							if ( $show_excerpt ) {
								echo wp_trim_words( get_the_excerpt(), $excerptcount, ' &hellip;' );
							} ?>
						</p>
					</div>
				</div>
			<?php
			endwhile;

			echo $args['after_widget'];

			wp_reset_postdata();

		}

		if ( ! $this->is_preview() ) {
			$cache[ $args['widget_id'] ] = ob_get_flush();
			wp_cache_set( 'widget_recent_posts', $cache, 'widget' );
		} else {
			ob_end_flush();
		}
	}

	public function update( $new_instance, $old_instance ) {
		$instance                 = $old_instance;
		$instance['title']        = strip_tags( $new_instance['title'] );
		$instance['number']       = (int) $new_instance['number'];
		$instance['excerptcount'] = (int) ( $new_instance['excerptcount'] );
		$instance['show_excerpt'] = isset( $new_instance['show_excerpt'] ) ? (bool) $new_instance['show_excerpt'] : false;
		$this->flush_widget_cache();

		$alloptions = wp_cache_get( 'alloptions', 'options' );
		if ( isset( $alloptions['widget_recent_entries'] ) ) {
			delete_option( 'widget_recent_entries' );
		}

		return $instance;
	}

	/**
	 * @access public
	 */
	public function flush_widget_cache() {
		wp_cache_delete( 'widget_recent_posts', 'widget' );
	}

	/**
	 * @param array $instance
	 */
	public function form( $instance ) {
		$title        = isset( $instance['title'] ) ? esc_attr( $instance['title'] ) : '';
		$number       = isset( $instance['number'] ) ? absint( $instance['number'] ) : 3;
		$excerptcount = isset( $instance['excerp