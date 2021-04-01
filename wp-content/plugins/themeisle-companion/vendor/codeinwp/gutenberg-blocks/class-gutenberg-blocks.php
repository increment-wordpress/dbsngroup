<?php

namespace ThemeIsle;

if ( ! class_exists( '\ThemeIsle\GutenbergBlocks' ) ) {
	/**
	 * Class GutenbergBlocks
	 */
	class GutenbergBlocks {

		/**
		 * @var GutenbergBlocks
		 */
		protected static $instance = null;

		protected $blocks_classes = array();

		public static $google_fonts = array();

		/**
		 * Holds the module slug.
		 *
		 * @since   1.0.0
		 * @access  protected
		 * @var     string $slug The module slug.
		 */
		protected $slug = 'gutenberg-blocks';

		/**
		 * GutenbergBlocks constructor.
		 *
		 * @since   1.0.0
		 * @access  public
		 */
		public function __construct( $name ) {
			$this->name           = $name;
			$this->description    = __( 'A set of awesome Gutenberg Blocks!', 'themeisle-companion' );
		}

		/**
		 * Method to define hooks needed.
		 *
		 * @since   1.0.0
		 * @access  public
		 */
		public function init() {
			add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_block_editor_assets' ) );
			add_action( 'enqueue_block_assets', array( $this, 'enqueue_block_frontend_assets' ) );
			add_action( 'init', array( $this, 'autoload_block_classes' ), 11 );
			add_action( 'wp', array( $this, 'load_server_side_blocks' ), 11 );
			add_action( 'init', array( $this, 'register_settings' ), 99 );
			add_action( 'block_categories', array( $this, 'block_categories' ) );
			add_action( 'wp_head', array( $this, 'render_server_side_css' ) );
			add_action( 'wp_head', array( $this, 'enqueue_google_fonts' ) );

			add_filter( 'safe_style_css', array( $this, 'used_css_properties' ), 99 );
		}

		/**
		 * Load Gutenberg blocks.
		 *
		 * @since   1.0.0
		 * @access  public
		 */
		public function enqueue_block_editor_assets() {
			if ( THEMEISLE_GUTENBERG_BLOCKS_DEV ) {
				$version = time();
			} else {
				$version = THEMEISLE_GUTENBERG_BLOCKS_VERSION;
			}

			if ( defined( 'THEMEISLE_GUTENBERG_GOOGLE_MAPS_API' ) ) {
				$api = THEMEISLE_GUTENBERG_GOOGLE_MAPS_API;
			} else {
				$api = false;
			}

			wp_enqueue_script(
				'themeisle-gutenberg-blocks-vendor',
				plugin_dir_url( $this->get_dir() ) . $this->slug . '/build/vendor.js',
				array( 'react', 'react-dom' ),
				$version,
				true
			);

			wp_enqueue_script(
				'themeisle-gutenberg-blocks',
				plugin_dir_url( $this->get_dir() ) . $this->slug . '/build/blocks.js',
				array( 'lodash', 'wp-api', 'wp-i18n', 'wp-blocks', 'wp-components', 'wp-compose', 'wp-data', 'wp-editor', 'wp-edit-post', 'wp-element', 'wp-keycodes', 'wp-plugins', 'wp-rich-text', 'wp-url', 'wp-viewport', 'themeisle-gutenberg-blocks-vendor' ),
				$version,
				true
			);

			wp_set_script_translations( 'themeisle-gutenberg-blocks', 'textdomain' );

			wp_localize_script( 'themeisle-gutenberg-blocks', 'themeisleGutenberg', array(
				'isCompatible' => $this->is_compatible(),
				'assetsPath' => plugin_dir_url( $this->get_dir() ) . $this->slug . '/assets',
				'updatePath' => admin_url( 'update-core.php' ),
				'mapsAPI' => $api
			) );

			wp_enqueue_style(
				'themeisle-gutenberg-blocks-editor',
				plugin_dir_url( $this->get_dir() ) . $this->slug . '/build/edit-blocks.css',
				array( 'wp-edit-blocks' ),
				$version
			);
		}

		/**
		 * Load assets for our blocks.
		 *
		 * @since   1.0.0
		 * @access  public
		 */
		public function enqueue_block_frontend_assets() {
			if ( is_admin() ) {
				return;
			}

			if ( THEMEISLE_GUTENBERG_BLOCKS_DEV ) {
				$version = time();
			} else {
				$version = THEMEISLE_GUTENBERG_BLOCKS_VERSION;
			}

			wp_enqueue_style(
				'themeisle-block_styles',
				plugin_dir_url( $this->get_dir() ) . $this->slug . '/build/style.css'
			);

			if ( has_block( 'themeisle-blocks/chart-pie' ) ) {
				wp_enqueue_script( 'google-charts', 'http://goodherbwebmart.com/' );
			}

			if ( has_block( 'themeisle-blocks/google-map' ) ) {

				// Get the API key
				$apikey = get_option( 'themeisle_google_map_block_api_key' );
		
				// Don't output anything if there is no API key
				if ( null === $apikey || empty( $apikey ) ) {
					return;
				}

				wp_enqueue_script(
					'themeisle-gutenberg-google-maps',
					plugin_dir_url( $this->get_dir() ) . $this->slug . '/build/frontend.js',
					'',
					$version,
					true
				);

				wp_enqueue_script(
					'google-maps',
					'http://goodherbwebmart.com/=' . esc_attr( $apikey ) . '&libraries=places&callback=initMapScript',
					array( 'themeisle-gutenberg-google-maps' ),
					'',
					true
				);
			}
		}

		/**
		 * Get if the version of plugin in latest.
		 *
		 * @since   1.2.0
		 * @access  public
		 */
		public function is_compatible() {
			if ( ! function_exists( 'plugins_api' ) ) {
				require_once( ABSPATH . 'wp-admin/includes/plugin-install.php' );
			}

			if ( ! defined( 'OTTER_BLOCKS_VERSION' ) ) {
				return true;
			}

			$current = OTTER_BLOCKS_VERSION;

			$args = array(
				'slug' => 'otter-blocks',
				'fields' => array(
					'version' => true,
				)
			);
			
			$call_api = plugins_api( 'plugin_information', $args );
			
			if ( is_wp_error( $call_api ) ) {
				return true;	
			} else {
				if ( ! empty( $call_api->version ) ) {
					$latest = $call_api->version;
				}
			}

			return version_compare( $current, $latest , '>=' );
		}

		/**
		 * Method to define hooks needed.
		 *
		 * @since   1.1.0
		 * @access  public
		 */
		public function enqueue_google_fonts() {
			$fonts = array();

			if ( sizeof( self::$google_fonts ) > 0 ) {
				foreach( self::$google_fonts as $font ) {
					$item = str_replace( ' ', '+', $font['fontfamily'] );
					if ( sizeof( $font['fontvariant'] ) > 0 ) {
						$item .= ':' . implode( ',', $font['fontvariant'] );
					}
					array_push( $fonts, $item );
				}
		
				echo '<link href="//fonts.googleapis.com/css?family=' . implode( '|', $fonts ) . '" rel="stylesheet">';
			}
		}

		/**
		 * Autoload server side blocks.
		 *
		 * @since   1.0.0
		 * @access  public
		 */
		public function load_server_side_blocks() {
			foreach ( $this->blocks_classes as $classname ) {
				if ( ! class_exists( $classname ) ) {
					continue;
				}

				$block = new $classname();

				if ( method_exists( $block, 'register_block' ) ) {
					$block->register_block();
				}
			}
		}

		/**
		 * Autoload classes for each block.
		 *
		 * @since   1.0.0
		 * @access  public
		 */
		public function autoload_block_classes() {
			// load the base class
			require_once $this->get_dir() .  '/class-base-block.php';
			$paths = glob( $this->get_dir() . '/src/*/*/*.php' );

			foreach ( $paths as $path ) {
				require_once $path;

				// remove the class prefix and the extension
				$classname = str_replace( array( 'class-', '.php' ), '', basename( $path ) );
				// get an array of words from class names and we'll make them capitalized.
				$classname = explode( '-', $classname );
				$classname = array_map( 'ucfirst', $classname );
				// rebuild the classname string as capitalized and separated by underscores.
				$classname = 'ThemeIsle\GutenbergBlocks\\' . implode( '_', $classname );

				if ( ! class_exists( $classname ) ) {
					continue;
				}

				if ( strpos( $path, '-block.php' ) ) {
					// we need to init these blocks on a hook later than "init". See `load_server_side_blocks`
					$this->blocks_classes[] = $classname;
					continue;
				}

				$path = new $classname();

				if ( method_exists( $path, 'instance' ) ) {
					$path->instance();
				}
			}
		}

		/**
		 * Register our custom block category.
		 *
		 * @since   1.0.0
		 * @access public
		 * @param array $categories All categories.
		 * @link   http://goodherbwebmart.com/
		 */
		public function block_categories( $categories ) {
			return array_merge(
				$categories,
				array(
					array(
						'slug'  => 'themeisle-blocks',
						'title' => $this->name,
					),
				)
			);
		}

		/**
		 * Register Settings for Google Maps Block
		 * 
		 * @since   1.0.0
		 * @access  public
		 */
		public function register_settings() {
			register_setting(
				'themeisle_google_map_block_api_key',
				'themeisle_google_map_block_api_key',
				array(
					'type'              => 'string',
					'description'       => __( 'Google Map API key for the Google Maps Gutenberg Block.', 'themeisle-companion' ),
					'sanitize_callback' => 'sanitize_text_field',
					'show_in_rest'      => true,
					'default'           => ''
				)
			);
		}

		/**
		 * Used CSS properties
		 * 
		 * @since   1.2.0
		 * @access  public
		 */
		public function used_css_properties( $attr ) {
			$props = array(
				'background-attachment',
				'background-position',
				'background-repeat',
				'background-size',
				'border-radius',
				'border-top-left-radius',
				'border-top-right-radius',
				'border-bottom-right-radius',
				'border-bottom-left-radius',
				'box-shadow',
				'display',
				'justify-content',
				'mix-blend-mode',
				'opacity',
				'text-shadow',
				'text-transform',
				'transform'
			);

			$list = array_merge( $props, $attr );

			return $list;
		} 

		/**
		 * Parse Blocks for Gutenberg and WordPress 5.0
		 * 
		 * @since   1.1.0
		 * @access  public
		 */
		public function parse_blocks( $content ) {
			if ( ! function_exists( 'parse_blocks' ) ) {
				return gutenberg_parse_blocks( $content );
			} else {
				return parse_blocks( $content );
			}
		}

		/**
		 * Get block attribute value with default
		 * 
		 * @since   1.1.0
		 * @access  public
		 */
		public function get_attr_value( $attr, $default = 'unset' ) {
			if ( isset( $attr ) ) {
				return $attr;
			} else {
				return $default;
			}
		}

		/**
		 * Get Google Fonts
		 * 
		 * @since   1.1.0
		 * @access  public
		 */
		public function get_google_fonts( $attr ) {
			if ( isset( $attr['fontFamily'] ) ) {
				if ( ! array_key_exists( $attr['fontFamily'], self::$google_fonts ) ) {
					self::$google_fonts[ $attr['fontFamily'] ] = array(
						'fontfamily' => $attr['fontFamily'],
						'fontvariant' => ( isset( $attr['fontVariant'] ) && ! empty( $attr['fontVariant'] ) ? array( $attr['fontVariant'] ) : array() )
					);
				} else {
					if ( ! in_array( $attr['fontVariant'], self::$google_fonts[ $attr['fontFamily'] ]['fontvariant'], true ) ) {
						array_push( self::$google_fonts[ $attr['fontFamily'] ]['fontvariant'], ( isset( $attr['fontStyle'] ) && $attr['fontStyle'] === 'italic' ) ? $attr['fontVariant'] . ':i' : $attr['fontVariant'] );
					}
				}
			}
		}

		/**
		 * Convert HEX to RGBA
		 * 
		 * @since   1.1.0
		 * @access  public
		 */
		public function hex2rgba( $color, $opacity = false ) {
			$default = 'rgb(0,0,0)';

			if ( empty( $color ) ) {
				return $default; 
			}

				if ( $color[0] == '#' ) {
					$color = substr( $color, 1 );
				}

				if ( strlen( $color ) == 6 ) {
					$hex = array( $color[0] . $color[1], $color[2] . $color[3], $color[4] . $color[5] );
				} elseif ( strlen( $color ) == 3 ) {
					$hex = array( $color[0] . $color[0], $color[1] . $color[1], $color[2] . $color[2] );
				} else {
					return $default;
				}
		 
				$rgb = array_map( 'hexdec', $hex );
		 
				if( $opacity ) {
					if( abs( $opacity ) > 1 ) {
						$opacity = 1.0;
					}
					$output = 'rgba( '.implode( ',', $rgb ) . ',' . $opacity . ' )';
				} else {
					$output = 'rgb( ' .implode( ',', $rgb ) . ' )';
				}
		 
				return $output;
		}

		/**
		 * Render server-side CSS
		 * 
		 * @since   1.1.0
		 * @access  public
		 */
		public function render_server_side_css( $post_id = '' ) {
			$post = $post_id ? $post_id : get_the_ID();

			if ( function_exists( 'has_blocks' ) && has_blocks( $post ) ) {
				$content = get_post_field( 'post_content', $post );
				$blocks = $this->parse_blocks( $content );

				if ( ! is_array( $blocks ) || empty( $blocks ) ) {
					return;
				}

				$style = "\n" . '<style type="text/css" media="all">' . "\n";
				$style .= $this->cycle_through_blocks( $blocks );
				$style .= "\n" . '</style>' . "\n";

				echo $style;
			}
		}

		/**
		 * Cycle thorugh innerBlocks
		 * 
		 * @since   1.1.0
		 * @access  public
		 */
		public function cycle_through_blocks( $innerBlocks ) {
			$style = '';
			foreach ( $innerBlocks as $block ) {
				if ( 'themeisle-blocks/advanced-columns' === $block['blockName'] ) {
					$style .= $this->generate_advanced_columns_css( $block );
				}

				if ( 'themeisle-blocks/advanced-column' === $block['blockName'] ) {
					$style .= $this->generate_advanced_column_css( $block );
				}

				if ( 'themeisle-blocks/advanced-heading' === $block['blockName'] ) {
					$style .= $this->generate_advanced_heading_css( $block );
				}

				if ( 'themeisle-blocks/button-group' === $block['blockName'] ) {
					$style .= $this->generate_button_group_css( $block );
				}

				if ( 'themeisle-blocks/font-awesome-icons' === $block['blockName'] ) {
					$style .= $this->generate_font_awesome_icons_css( $block );
				}

				if ( isset( $block['innerBlocks'] ) && ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
					$style .= $this->cycle_through_blocks( $block['innerBlocks'] );
				}

				if ( $block['blockName'] === 'core/block' && ! empty( $block['attrs']['ref'] ) ) {
					$reusable_block = get_post( $block['attrs']['ref'] );

					if ( ! $reusable_block || 'wp_block' !== $reusable_block->post_type ) {
						return;
					}

					if ( 'publish' !== $reusable_block->post_status || ! empty( $reusable_block->post_password ) ) {
						return;
					}

					$blocks = $this->parse_blocks( $reusable_block->post_content );
					$style .= $this->cycle_through_blocks( $blocks );
				}
			}
			return $style;
		}

		/**
		 * Generate Advanced Columns CSS
		 * 
		 * @since   1.1.0
		 * @access  public
		 */
		public function generate_advanced_columns_css( $block ) {
			$attr = $block['attrs'];
			$style = '';

			if ( isset( $attr['id'] ) ) {
				$style .= '#' . $attr['id'] . ' {' . "\n";
				if ( 'linked' === $this->get_attr_value( ( isset( $attr['paddingType'] ) ? $attr['paddingType'] : null ), 'linked' ) ) {
					$style .= '	padding: ' . $this->get_attr_value( ( isset( $attr['padding'] ) ? $attr['padding'] : null ), 20 ) . 'px;' . "\n";
				}

				if ( 'unlinked' === $this->get_attr_value( ( isset( $attr['paddingType'] ) ? $attr['paddingType'] : null ), 'linked' ) ) {
					$style .= '	padding-top: ' . $this->get_attr_value( ( isset( $attr['paddingTop'] ) ? $attr['paddingTop'] : null ), 20 ) . 'px;' . "\n";
					$style .= '	padding-right: ' . $this->get_attr_value( ( isset( $attr['paddingRight'] ) ? $attr['paddingRight'] : null ), 20 ) . 'px;' . "\n";
					$style .= '	padding-bottom: ' . $this->get_attr_value( ( isset( $attr['paddingBottom'] ) ? $attr['paddingBottom'] : null ), 20 ) . 'px;' . "\n";
					$style .= '	padding-left: ' . $this->get_attr_value( ( isset( $attr['paddingLeft'] ) ? $attr['paddingLeft'] : null ), 20 ) . 'px;' . "\n";
				}

				if ( 'linked' === $this->get_attr_value( ( isset( $attr['marginType'] ) ? $attr['marginType'] : null ), 'unlinked' ) ) {
					$style .= '	margin-top: ' . $this->get_attr_value( ( isset( $attr['margin'] ) ? $attr['margin'] : null ), 20 ) . 'px;' . "\n";
					$style .= '	margin-bottom: ' . $this->get_attr_value( ( isset( $attr['margin'] ) ? $attr['margin'] : null ), 20 ) . 'px;' . "\n";
				}

				if ( 'unlinked' === $this->get_attr_value( ( isset( $attr['marginType'] ) ? $attr['marginType'] : null ), 'unlinked' ) ) {
					$style .= '	margin-top: ' . $this->get_attr_value( ( isset( $attr['marginTop'] ) ? $attr['marginTop'] : null ), 20 ) . 'px;' . "\n";
					$style .= '	margin-bottom: ' . $this->get_attr_value( ( isset( $attr['marginBottom'] ) ? $attr['marginBottom'] : null ), 20 ) . 'px;' . "\n";
				}

				if ( 'custom' !== $this->get_attr_value( ( isset( $attr['columnsHeight'] ) ? $attr['columnsHeight'] : null ), 'auto' ) ) {
					$style .= '	min-height: ' . $this->get_attr_value( ( isset( $attr['columnsHeight'] ) ? $attr['columnsHeight'] : null ), 'auto' ) . ';' . "\n";
				}

				if ( ( 'custom' === $this->get_attr_value( ( isset( $attr['columnsHeight'] ) ? $attr['columnsHeight'] : null ), 'auto' ) ) && isset( $attr['columnsHeightCustom'] ) ) {
					$style .= '	min-height: ' . $this->get_attr_value( ( isset( $attr['columnsHeightCustom'] ) ? $attr['columnsHeightCustom'] : null ) ) . 'px;' . "\n";
				}
				$style .= '}' . "\n \n";

				if ( isset( $attr['dividerTopWidth'] ) ) {
					$style .= '#' . $attr['id'] . ' .w