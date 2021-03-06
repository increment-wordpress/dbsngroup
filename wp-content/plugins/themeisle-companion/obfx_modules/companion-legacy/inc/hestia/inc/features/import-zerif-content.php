<?php
/**
 * Import content from Zerif theme when theme is activated.
 *
 * @package Hestia
 * @since 1.1.16
 */

if ( ! function_exists( 'hestia_import_zerif_content' ) ) {
	/**
	 * Main import function
	 */
	function hestia_import_zerif_content() {

		$zerif_pro_content  = get_option( 'theme_mods_zerif-pro' );
		$zerif_lite_content = get_option( 'theme_mods_zerif-lite' );

		if ( ! empty( $zerif_pro_content ) ) {
			hestia_import_old_theme_content( $zerif_pro_content );
		} elseif ( ! empty( $zerif_lite_content ) ) {
			hestia_import_old_theme_content( $zerif_lite_content );
		}
	}
}
add_action( 'after_switch_theme', 'hestia_import_zerif_content', 0 );

/**
 * Import old content from Zerif
 *
 * @param array $content the content from previous zerif instance.
 */
function hestia_import_old_theme_content( $content ) {
	hestia_import_simple_theme_mods( $content );
	hestia_import_widgets_as_theme_mods( $content );
}

/**
 * Set fixed theme mods from Zerif.
 *
 * @param array $content the content from previous zerif instance.
 */
function hestia_import_simple_theme_mods( $content ) {
	$slider_content_theme_mod = get_theme_mod( 'hestia_slider_content' );
	if ( empty( $slider_content_theme_mod ) ) {
		/* Import the big title section / slider */
		if ( ! empty( $content['zerif_bigtitle_title_2'] ) ) {
			$big_title_text = $content['zerif_bigtitle_title_2'];
		} elseif ( ! empty( $content['zerif_bigtitle_title'] ) ) {
			$big_title_text = $content['zerif_bigtitle_title'];
		}
		if ( ! empty( $content['zerif_bigtitle_redbutton_label_2'] ) ) {
			$big_title_button_text = $content['zerif_bigtitle_redbutton_label_2'];
		} elseif ( ! empty( $content['zerif_bigtitle_redbutton_label'] ) ) {
			$big_title_button_text = $content['zerif_bigtitle_redbutton_label'];
		} elseif ( ! empty( $content['zerif_bigtitle_greenbutton_label'] ) ) {
			$big_title_button_text = $content['zerif_bigtitle_greenbutton_label'];
		}
		if ( ! empty( $content['zerif_bigtitle_redbutton_url'] ) ) {
			$big_title_button_link = $content['zerif_bigtitle_redbutton_url'];
		} elseif ( ! empty( $content['zerif_bigtitle_greenbutton_url'] ) ) {
			$big_title_button_link = $content['zerif_bigtitle_greenbutton_url'];
		}
		if ( ! empty( $content['zerif_background_settings'] ) ) {
			$big_title_background_settings = $content['zerif_background_settings'];
		}

		if ( ! empty( $big_title_text ) || ( ! empty( $big_title_button_text ) && ! empty( $big_title_button_link ) ) || ! empty( $big_title_background_settings ) ) {

			$imported_slider_content = array();

			/* Check the background type from Zerif ( image or slider ) */
			if ( ! empty( $big_title_background_settings ) ) {
				if ( $big_title_background_settings == 'zerif-background-slider' ) {
					$slider_background = array();
					for ( $i = 1; $i <= 3; $i ++ ) {
						if ( ! empty( $content[ 'zerif_bgslider_' . $i ] ) ) {
							array_push( $slider_background, $content[ 'zerif_bgslider_' . $i ] );
						}
					}
				}
			} elseif ( ! empty( $content['background_image'] ) ) {
				$slider_background = $content['background_image'];
			} else {
				$slider_background = get_template_directory_uri() . '/assets/img/slider3.jpg';
			}

			if ( ! empty( $slider_background ) ) {
				if ( is_array( $slider_background ) ) {
					/* Set a slider for the multiple slides background. */
					foreach ( $slider_background as $background ) {
						$transient_imported_slider_content = array(
							'image_url' => esc_url( $background ),
							'title'     => ! empty( $big_title_text ) ? wp_kses_post( $big_title_text ) : '',
							'text'      => ! empty( $big_title_button_text ) ? esc_html( $big_title_button_text ) : '',
							'link'      => ! empty( $big_title_button_link ) ? esc_url( $big_title_button_link ) : '',
						);
						array_push( $imported_slider_content, $transient_imported_slider_content );
					}
				} else {
					/* Set a single slide for the single image background. */
					$imported_slider_content = array(
						array(
							'image_url' => esc_url( $slider_background ),
							'title'     => ! empty( $big_title_text ) ? wp_kses_post( $big_title_text ) : '',
							'text'      => ! empty( $big_title_button_text ) ? esc_html( $big_title_button_text ) : '',
							'link'      => ! empty( $big_title_button_link ) ? esc_url( $big_title_button_link ) : '',
						),
					);
				}
			}
		}// End if().

		/* Set the slider based on the imported content. */
		if ( ! empty( $imported_slider_content ) ) {
			set_theme_mod( 'hestia_slider_content', json_encode( $imported_slider_content ) );
		}
	}// End if().
	/* END OF SLIDER IMPORT */

	/* Import the "Big Title" section */
	if ( ! empty( $content['background_image'] ) ) {
		set_theme_mod( 'hestia_big_title_background', $content['background_image'] );
	}
	if ( ! empty( $big_title_text ) ) {
		set_theme_mod( 'hestia_big_title_title', $big_title_text );
	}
	if ( ! empty( $big_title_button_text ) ) {
		set_theme_mod( 'hestia_big_title_button_text', $big_title_button_text );
	}
	if ( ! empty( $big_title_button_link ) ) {
		set_theme_mod( 'hestia_big_title_button_link', $big_title_button_link );
	}
	set_theme_mod( 'hestia_big_title_text', '' );
	/* END OF BIG TITLE IMPORT */

	/* Import the texts from "Our Focus" */
	hestia_import_customizer_setting( $content, 'zerif_ourfocus_title', 'hestia_features_title' );
	hestia_import_customizer_setting( $content, 'zerif_ourfocus_subtitle', 'hestia_features_subtitle' );
	/* END OF OUR FOCUS TITLES IMPORT */

	/* Import the texts from "Our Team" */
	hestia_import_customizer_setting( $content, 'zerif_ourteam_title', 'hestia_team_title' );
	hestia_import_customizer_setting( $content, 'zerif_ourteam_subtitle', 'hestia_team_subtitle' );
	/* END OF TEAM TITLES IMPORT */

	/* Import the texts from "Testimonials" */
	hestia_import_customizer_setting( $content, 'zerif_testimonials_title', 'hestia_testimonials_title' );
	hestia_import_customizer_setting( $content, 'zerif_testimonials_subtitle', 'hestia_testimonials_subtitle' );
	/* END OF TESTIMONIALS TITLES IMPORT */

	/* Import the texts from "Contact" */
	hestia_import_customizer_setting( $content, 'zerif_contactus_title', 'hestia_contact_title' );
	hestia_import_customizer_setting( $content, 'zerif_contactus_subtitle', 'hestia_contact_subtitle' );
	/* END OF CONTACT TITLES IMPORT */

	/* Import the texts from "Packages" */
	hestia_import_customizer_setting( $content, 'zerif_packages_title', 'hestia_pricing_title' );
	hestia_import_customizer_setting( $content, 'zerif_packages_subtitle', 'hestia_pricing_subtitle' );
	/* END OF PACKAGES TITLES IMPORT */

	/* Import the texts from "Subscribe" */
	hestia_import_customizer_setting( $content, 'zerif_subscribe_title', 'hestia_subscribe_title' );
	hestia_import_customizer_setting( $content, 'zerif_subscribe_subtitle', 'hestia_subscribe_subtitle' );
	/* END OF SUBSCRIBE TITLES IMPORT */

	/* Import the custom logo */
	hestia_import_customizer_setting( $content, 'custom_logo', 'custom_logo' );
	/* END OF CUSTOM LOGO IMPORT */

	$contact_theme_mod = get_theme_mod( 'hestia_contact_content_new' );
	if ( empty( $contact_theme_mod ) ) {
		if ( ! empty( $content['zerif_email'] ) ) {
			$email = $content['zerif_email'];
		}
		if ( ! empty( $content['zerif_phone'] ) ) {
			$phone = $content['zerif_phone'];
		}
		if ( ! empty( $content['zerif_address'] ) ) {
			$address = $content['zerif_address'];
		}

		$contact_content = '';

		if ( ! empty( $email ) ) {
			$contact_content .= '<div class="info info-horizontal"><div class="icon icon-primary"><i class="fa fa-envelope"></i></div><div class="description"><h4 class="info-title">' . wp_kses_post( $email ) . '</h4></div>';
		}

		if ( ! empty( $phone ) ) {
			$contact_content .= '<div class="info info-horizontal"><div class="icon icon-primary"><i class="fa fa-phone"></i></div><div class="description"><h4 class="info-title">' . wp_kses_post( $phone ) . '</h4></div>';
		}

		if ( ! empty( $address ) ) {
			$contact_content .= '<div class="info info-horizontal"><div class="icon icon-primary"><i class="fa fa-map-marker"></i></div><div class="description"><h4 class="info-title">' . wp_kses_post( $address ) . '</h4></div>';
		}

		if ( ! empty( $contact_content ) ) {
			set_theme_mod( 'hestia_contact_content_new', $contact_content );
		}
	}
}

/**
 * Import widgets as theme mods.
 *
 * @param array $content the content from previous zerif instance.
 */
function hestia_import_widgets_as_theme_mods( $content ) {

	/* Define the sidebars to be checked for widgets. */
	$sidebars = array(
		'sidebar-ourfocus',
		'sidebar-testimonials',
		'sidebar-aboutus',
		'sidebar-ourteam',
		'sidebar-packages',
		'sidebar-subscribe',
	);

	// Declare arrays to store the widgets id's.
	$focus_widgets_ids       = array();
	$team_widgets_ids        = array();
	$testimonial_widgets_ids = array();
	$package_widgets_ids     = array();
	$clients_widgets_ids     = array();

	$sidebars_widgets = wp_get_sidebars_widgets();

	foreach ( $sidebars as $sidebar_id ) {

		// A nested array in the format $sidebar_id => array( 'widget_id-1', 'widget_id-2' ... );
		// Get the widget ID's per sidebar
		if ( ! empty( $sidebars_widgets[ $sidebar_id ] ) ) {
			$widgets_long_ids = $sidebars_widgets[ $sidebar_id ];

			if ( is_array( $widgets_long_ids ) && ! empty( $widgets_long_ids ) ) {
				foreach ( $widgets_long_ids as $id ) {

					$short_id_transient = explode( '-', $id );
					$short_id           = end( $short_id_transient );

					if ( strpos( $id, 'ctup-ads' ) !== false ) {
						array_push( $focus_widgets_ids, $short_id );
					} elseif ( strpos( $id, 'zerif_testim' ) !== false ) {
						array_push( $testimonial_widgets_ids, $short_id );
					} elseif ( strpos( $id, 'zerif_team' ) !== false ) {
						array_push( $team_widgets_ids, $short_id );
					} elseif ( strpos( $id, 'color-picker' ) !== false ) {
						array_push( $package_widgets_ids, $short_id );
					} elseif ( strpos( $id, 'zerif_clients' ) !== false ) {
						array_push( $clients_widgets_ids, $short_id );
					}
				}
			}
		}
	}

	hestia_import_focus_widgets( $focus_widgets_ids );
	hestia_import_testimonial_widgets( $testimonial_widgets_ids );
	hestia_import_team_widgets( $team_widgets_ids );
	hestia_import_packages_widgets( $package_widgets_ids );
	hestia_import_about_us_content( $content, $clients_widgets_ids );
}

/**
 * Import Focus Widgets to Features Section.
 *
 * @param array $widget_ids the ids of focus widgets active inside sidebars.
 */
function hestia_import_focus_widgets( $widget_ids ) {
	$features_content = get_theme_mod( 'hestia_features_content' );
	if ( empty( $features_content ) ) {
		if ( ! empty( $widget_ids ) ) {
			$widgets_content          = array();
			$widgets_exported_content = array();
			$widgets                  = get_option( 'widget_ctup-ads-widget' );
			foreach ( $widget_ids as $widget_id ) {
				array_push( $widgets_content, $widgets[ $widget_id ] );
			}
			foreach ( $widgets_content as $widget_content ) {
				$transient_content = array();

				if ( isset( $widget_content['title'] ) ) {
					$transient_content['title'] = $widget_content['title'];
				}
				if ( isset( $widget_content['text'] ) ) {
					$transient_content['text'] = $widget_content['text'];
				}
				if ( isset( $widget_content['link'] ) ) {
					$transient_content['link'] = $widget_content['link'];
				}
				$transient_content['icon_value'] = 'fa-circle-thin';
				$transient_content['color']      = '#9c27b0';
				array_push( $widgets_exported_content, $transient_content );
			}
			$widgets_exported_content = json_encode( $widgets_exported_content );
			set_theme_mod( 'hestia_features_content', $widgets_exported_content );
		}
	}
}

/**
 * Import Testimonial Widgets to Testimonial Section.
 *
 * @param array $widget_ids the ids of testimonial widgets active inside sidebars.
 */
function hestia_import_testimonial_widgets( $widget_ids ) {
	$testimonials_content = get_theme_mod( 'hestia_testimonials_content' );
	if ( empty( $testimonials_content ) ) {
		if ( ! empty( $widget_ids ) ) {
			$widgets_content          = array();
			$widgets_exported_content = array();
			$widgets                  = get_option( 'widget_zerif_testim-widget' );
			foreach ( $widget_ids as $widget_id ) {
				array_push( $widgets_content, $widgets[ $widget_id ] );
			}
			foreach ( $widgets_content as $widget_content ) {
				$transient_content = array();

				if ( isset( $widget_content['text'] ) ) {
					$transient_content['text'] = $widget_content['text'];
				}
				if ( isset( $widget_content['title'] ) ) {
					$transient_content['title'] = $widget_content['title'];
				}
				if ( isset( $widget_content['details'] ) ) {
					$transient_content['subtitle'] = $widget_content['details'];
				}
				if ( isset( $widget_content['image_uri'] ) ) {
					$transient_content['image_url'] = $widget_content['image_uri'];
				}
				array_push( $widgets_exported_content, $transient_content );
			}
			$widgets_exported_content = json_encode( $widgets_exported_content );
			set_theme_mod( 'hestia_testimonials_content', $widgets_exported_content );
		}
	}
}

/**
 * Import Team Widgets to Team Section.
 *
 * @param array $widget_ids the ids of team member widgets active inside sidebars.
 */
function hestia_import_team_widgets( $widget_ids ) {
	$team_content = get_theme_mod( 'hestia_team_content' );
	if ( empty( $team_content ) ) {
		if ( ! empty( $widget_ids ) ) {
			$widgets_content          = array();
			$widgets_exported_content = array();
			$widgets                  = get_option( 'widget_zerif_team-widget' );
			foreach ( $widget_ids as $widget_id ) {
				array_push( $widgets_content, $widgets[ $widget_id ] );
			}
			foreach ( $widgets_content as $widget_content ) {
				$transient_content = array();
				$transient_socials = array();

				if ( isset( $widget_content['image_uri'] ) ) {
					$transient_content['image_url'] = $widget_content['image_uri'];
				}
				if ( isset( $widget_content['name'] ) ) {
					$transient_content['title'] = $widget_content['name'];
				}
				if ( isset( $widget_content['position'] ) ) {
					$transient_content['subtitle'] = $widget_content['position'];
				}
				if ( isset( $widget_content['description'] ) ) {
					$transient_content['text'] = $widget_content['description'];
				}

				if ( ! empty( $widget_content['fb_link'] ) ) {
					$social_item = array(
						'link' => $widget_content['fb_link'],
						'icon' => 'fa-facebook',
					);
					array_push( $social_item, $transient_socials );
				}

				if ( ! empty( $widget_content['tw_link'] ) ) {
					$social_item = array(
						'link' => $widget_content['tw_link'],
						'icon' => 'fa-twitter',
					);
					array_push( $social_item, $transient_socials );
				}

				if ( ! empty( $widget_content['bh_link'] ) ) {
					$social_item = array(
						'link' => $widget_content['bh_link'],
						'icon' => 'fa-behance',
					);
					array_push( $social_item, $transient_socials );
				}

				if ( ! empty( $widget_content['db_link'] ) ) {
					$social_item = array(
						'link' => $widget_content['db_link'],
						'icon' => 'fa-dribbble',
					);
					array_push( $social_item, $transient_socials );
				}

				if ( ! empty( $widget_content['ln_link'] ) ) {
					$social_item = array(
						'link' => $widget_content['ln_link'],
						'icon' => 'fa-linkedin',
					);
					array_push( $social_item, $transient_socials );
				}

				if ( ! empty( $widget_content['gp_link'] ) ) {
					$social_item = array(
						'link' => $widget_content['gp_link'],
						'icon' => 'fa-google-plus',
					);
					array_push( $social_item, $transient_socials );
				}

				if ( ! empty( $widget_content['pinterest_link'] ) ) {
					$social_item = array(
						'link' => $widget_content['pinterest_link'],
						'icon' => 'fa-pinterest',
					);
					array_push( $social_item, $transient_socials );
				}

				if ( ! empty( $widget_content['tumblr_link'] ) ) {
					$social_item = array(
						'link' => $widget_content['tumblr_link'],
						'icon' => 'fa-tumblr',
					);
					array_push( $social_item, $transient_socials );
				}

				if ( ! empty( $widget_content['reddit_link'] ) ) {
					$social_item = array(
						'link' => $widget_content['reddit_link'],
						'icon' => 'fa-reddit',
					);
					array_push( $social_item, $transient_socials );
				}

				if ( ! empty( $widget_content['youtube_link'] ) ) {
					$social_item = array(
						'link' 