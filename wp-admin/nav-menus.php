<?php
/**
 * WordPress Administration for Navigation Menus
 * Interface functions
 *
 * @version 2.0.0
 *
 * @package WordPress
 * @subpackage Administration
 */

/** Load WordPress Administration Bootstrap */
require_once( dirname( __FILE__ ) . '/admin.php' );

// Load all the nav menu interface functions
require_once( ABSPATH . 'wp-admin/includes/nav-menu.php' );

if ( ! current_theme_supports( 'menus' ) && ! current_theme_supports( 'widgets' ) )
	wp_die( __( 'Your theme does not support navigation menus or widgets.' ) );

// Permissions Check
if ( ! current_user_can( 'edit_theme_options' ) ) {
	wp_die(
		'<h1>' . __( 'Cheatin&#8217; uh?' ) . '</h1>' .
		'<p>' . __( 'Sorry, you are not allowed to edit theme options on this site.' ) . '</p>',
		403
	);
}

wp_enqueue_script( 'nav-menu' );

if ( wp_is_mobile() )
	wp_enqueue_script( 'jquery-touch-punch' );

// Container for any messages displayed to the user
$messages = array();

// Container that stores the name of the active menu
$nav_menu_selected_title = '';

// The menu id of the current menu being edited
$nav_menu_selected_id = isset( $_REQUEST['menu'] ) ? (int) $_REQUEST['menu'] : 0;

// Get existing menu locations assignments
$locations = get_registered_nav_menus();
$menu_locations = get_nav_menu_locations();
$num_locations = count( array_keys( $locations ) );

// Allowed actions: add, update, delete
$action = isset( $_REQUEST['action'] ) ? $_REQUEST['action'] : 'edit';

/*
 * If a JSON blob of navigation menu data is found, expand it and inject it
 * into `$_POST` to avoid PHP `max_input_vars` limitations. See #14134.
 */
_wp_expand_nav_menu_post_data();

switch ( $action ) {
	case 'add-menu-item':
		check_admin_referer( 'add-menu_item', 'menu-settings-column-nonce' );
		if ( isset( $_REQUEST['nav-menu-locations'] ) )
			set_theme_mod( 'nav_menu_locations', array_map( 'absint', $_REQUEST['menu-locations'] ) );
		elseif ( isset( $_REQUEST['menu-item'] ) )
			wp_save_nav_menu_items( $nav_menu_selected_id, $_REQUEST['menu-item'] );
		break;
	case 'move-down-menu-item' :

		// Moving down a menu item is the same as moving up the next in order.
		check_admin_referer( 'move-menu_item' );
		$menu_item_id = isset( $_REQUEST['menu-item'] ) ? (int) $_REQUEST['menu-item'] : 0;
		if ( is_nav_menu_item( $menu_item_id ) ) {
			$menus = isset( $_REQUEST['menu'] ) ? array( (int) $_REQUEST['menu'] ) : wp_get_object_terms( $menu_item_id, 'nav_menu', array( 'fields' => 'ids' ) );
			if ( ! is_wp_error( $menus ) && ! empty( $menus[0] ) ) {
				$menu_id = (int) $menus[0];
				$ordered_menu_items = wp_get_nav_menu_items( $menu_id );
				$menu_item_data = (array) wp_setup_nav_menu_item( get_post( $menu_item_id ) );

				// Set up the data we need in one pass through the array of menu items.
				$dbids_to_orders = array();
				$orders_to_dbids = array();
				foreach ( (array) $ordered_menu_items as $ordered_menu_item_object ) {
					if ( isset( $ordered_menu_item_object->ID ) ) {
						if ( isset( $ordered_menu_item_object->menu_order ) ) {
							$dbids_to_orders[$ordered_menu_item_object->ID] = $ordered_menu_item_object->menu_order;
							$orders_to_dbids[$ordered_menu_item_object->menu_order] = $ordered_menu_item_object->ID;
						}
					}
				}

				// Get next in order.
				if (
					isset( $orders_to_dbids[$dbids_to_orders[$menu_item_id] + 1] )
				) {
					$next_item_id = $orders_to_dbids[$dbids_to_orders[$menu_item_id] + 1];
					$next_item_data = (array) wp_setup_nav_menu_item( get_post( $next_item_id ) );

					// If not siblings of same parent, bubble menu item up but keep order.
					if (
						! empty( $menu_item_data['menu_item_parent'] ) &&
						(
							empty( $next_item_data['menu_item_parent'] ) ||
							$next_item_data['menu_item_parent'] != $menu_item_data['menu_item_parent']
						)
					) {

						$parent_db_id = in_array( $menu_item_data['menu_item_parent'], $orders_to_dbids ) ? (int) $menu_item_data['menu_item_parent'] : 0;

						$parent_object = wp_setup_nav_menu_item( get_post( $parent_db_id ) );

						if ( ! is_wp_error( $parent_object ) ) {
							$parent_data = (array) $parent_object;
							$menu_item_data['menu_item_parent'] = $parent_data['menu_item_parent'];
							update_post_meta( $menu_item_data['ID'], '_menu_item_menu_item_parent', (int) $menu_item_data['menu_item_parent'] );

						}

					// Make menu item a child of its next sibling.
					} else {
						$next_item_data['menu_order'] = $next_item_data['menu_order'] - 1;
						$menu_item_data['menu_order'] = $menu_item_data['menu_order'] + 1;

						$menu_item_data['menu_item_parent'] = $next_item_data['ID'];
						update_post_meta( $menu_item_data['ID'], '_menu_item_menu_item_parent', (int) $menu_item_data['menu_item_parent'] );

						wp_update_post($menu_item_data);
						wp_update_post($next_item_data);
					}

				// The item is last but still has a parent, so bubble up.
				} elseif (
					! empty( $menu_item_data['menu_item_parent'] ) &&
					in_array( $menu_item_data['menu_item_parent'], $orders_to_dbids )
				) {
					$menu_item_data['menu_item_parent'] = (int) get_post_meta( $menu_item_data['menu_item_parent'], '_menu_item_menu_item_parent', true);
					update_post_meta( $menu_item_data['ID'], '_menu_item_menu_item_parent', (int) $menu_item_data['menu_item_parent'] );
				}
			}
		}

		break;
	case 'move-up-menu-item' :
		check_admin_referer( 'move-menu_item' );
		$menu_item_id = isset( $_REQUEST['menu-item'] ) ? (int) $_REQUEST['menu-item'] : 0;
		if ( is_nav_menu_item( $menu_item_id ) ) {
			$menus = isset( $_REQUEST['menu'] ) ? array( (int) $_REQUEST['menu'] ) : wp_get_object_terms( $menu_item_id, 'nav_menu', array( 'fields' => 'ids' ) );
			if ( ! is_wp_error( $menus ) && ! empty( $menus[0] ) ) {
				$menu_id = (int) $menus[0];
				$ordered_menu_items = wp_get_nav_menu_items( $menu_id );
				$menu_item_data = (array) wp_setup_nav_menu_item( get_post( $menu_item_id ) );

				// Set up the data we need in one pass through the array of menu items.
				$dbids_to_orders = array();
				$orders_to_dbids = array();
				foreach ( (array) $ordered_menu_items as $ordered_menu_item_object ) {
					if ( isset( $ordered_menu_item_object->ID ) ) {
						if ( isset( $ordered_menu_item_object->menu_order ) ) {
							$dbids_to_orders[$ordered_menu_item_object->ID] = $ordered_menu_item_object->menu_order;
							$orders_to_dbids[$ordered_menu_item_object->menu_order] = $ordered_menu_item_object->ID;
						}
					}
				}

				// If this menu item is not first.
				if ( ! empty( $dbids_to_orders[$menu_item_id] ) && ! empty( $orders_to_dbids[$dbids_to_orders[$menu_item_id] - 1] ) ) {

					// If this menu item is a child of the previous.
					if (
						! empty( $menu_item_data['menu_item_parent'] ) &&
						in_array( $menu_item_data['menu_item_parent'], array_keys( $dbids_to_orders ) ) &&
						isset( $orders_to_dbids[$dbids_to_orders[$menu_item_id] - 1] ) &&
						( $menu_item_data['menu_item_parent'] == $orders_to_dbids[$dbids_to_orders[$menu_item_id] - 1] )
					) {
						$parent_db_id = in_array( $menu_item_data['menu_item_parent'], $orders_to_dbids ) ? (int) $menu_item_data['menu_item_parent'] : 0;
						$parent_object = wp_setup_nav_menu_item( get_post( $parent_db_id ) );

						if ( ! is_wp_error( $parent_object ) ) {
							$parent_data = (array) $parent_object;

							/*
							 * If there is something before the parent and parent a child of it,
							 * make menu item a child also of it.
							 */
							if (
								! empty( $dbids_to_orders[$parent_db_id] ) &&
								! empty( $orders_to_dbids[$dbids_to_orders[$parent_db_id] - 1] ) &&
								! empty( $parent_data['menu_item_parent'] )
							) {
								$menu_item_data['menu_item_parent'] = $parent_data['menu_item_parent'];

							/*
							 * Else if there is something before parent and parent not a child of it,
							 * make menu item a child of that something's parent
							 */
							} elseif (
								! empty( $dbids_to_orders[$parent_db_id] ) &&
								! empty( $orders_to_dbids[$dbids_to_orders[$parent_db_id] - 1] )
							) {
								$_possible_parent_id = (int) get_post_meta( $orders_to_dbids[$dbids_to_orders[$parent_db_id] - 1], '_menu_item_menu_item_parent', true);
								if ( in_array( $_possible_parent_id, array_keys( $dbids_to_orders ) ) )
									$menu_item_data['menu_item_parent'] = $_possible_parent_id;
								else
									$menu_item_data['menu_item_parent'] = 0;

							// Else there isn't something before the parent.
							} else {
								$menu_item_data['menu_item_parent'] = 0;
							}

							// Set former parent's [menu_order] to that of menu-item's.
							$parent_data['menu_order'] = $parent_data['menu_order'] + 1;

							// Set menu-item's [menu_order] to that of former parent.
							$menu_item_data['menu_order'] = $menu_item_data['menu_order'] - 1;

							// Save changes.
							update_post_meta( $menu_item_data['ID'], '_menu_item_menu_item_parent', (int) $menu_item_data['menu_item_parent'] );
							wp_update_post($menu_item_data);
							wp_update_post($parent_data);
						}

					// Else this menu item is not a child of the previous.
					} elseif (
						empty( $menu_item_data['menu_order'] ) ||
						empty( $menu_item_data['menu_item_parent'] ) ||
						! in_array( $menu_item_data['menu_item_parent'], array_keys( $dbids_to_orders ) ) ||
						empty( $orders_to_dbids[$dbids_to_orders[$menu_item_id] - 1] ) ||
						$orders_to_dbids[$dbids_to_orders[$menu_item_id] - 1] != $menu_item_data['menu_item_parent']
					) {
						// Just make it a child of the previous; keep the order.
						$menu_item_data['menu_item_parent'] = (int) $orders_to_dbids[$dbids_to_orders[$menu_item_id] - 1];
						update_post_meta( $menu_item_data['ID'], '_menu_item_menu_item_parent', (int) $menu_item_data['menu_item_parent'] );
						wp_update_post($menu_item_data);
					}
				}
			}
		}
		break;

	case 'delete-menu-item':
		$menu_item_id = (int) $_REQUEST['menu-item'];

		check_admin_referer( 'delete-menu_item_' . $menu_item_id );

		if ( is_nav_menu_item( $menu_item_id ) && wp_delete_post( $menu_item_id, true ) )
			$messages[] = '<div id="message" class="updated notice is-dismissible"><p>' . __('The menu item has been successfully deleted.') . '</p></div>';
		break;

	case 'delete':
		check_admin_referer( 'delete-nav_menu-' . $nav_menu_selected_id );
		if ( is_nav_menu( $nav_menu_selected_id ) ) {
			$deletion = wp_delete_nav_menu( $nav_menu_selected_id );
		} else {
			// Reset the selected menu.
			$nav_menu_selected_id = 0;
			unset( $_REQUEST['menu'] );
		}

		if ( ! isset( $deletion ) )
			break;

		if ( is_wp_error( $deletion ) )
			$messages[] = '<div id="message" class="error notice is-dismissible"><p>' . $deletion->get_error_message() . '</p></div>';
		else
			$messages[] = '<div id="message" class="updated notice is-dismissible"><p>' . __( 'The menu has been successfully deleted.' ) . '</p></div>';
		break;

	case 'delete_menus':
		check_admin_referer( 'nav_menus_bulk_actions' );
		foreach ( $_REQUEST['delete_menus'] as $menu_id_to_delete ) {
			if ( ! is_nav_menu( $menu_id_to_delete ) )
				continue;

			$deletion = wp_delete_nav_menu( $menu_id_to_delete );
			if ( is_wp_error( $deletion ) ) {
				$messages[] = '<div id="message" class="error notice is-dismissible"><p>' . $deletion->get_error_message() . '</p></div>';
				$deletion_error = true;
			}
		}

		if ( empty( $deletion_error ) )
			$messages[] = '<div id="message" class="updated notice is-dismissible"><p>' . __( 'Selected menus have been successfully deleted.' ) . '</p></div>';
		break;

	case 'update':
		check_admin_referer( 'update-nav_menu', 'update-nav-menu-nonce' );

		// Remove menu locations that have been unchecked.
		foreach ( $locations as $location => $description ) {
			if ( ( empty( $_POST['menu-locations'] ) || empty( $_POST['menu-locations'][ $location ] ) ) && isset( $menu_locations[ $location ] ) && $menu_locations[ $location ] == $nav_menu_selected_id )
				unset( $menu_locations[ $location ] );
		}

		// Merge new and existing menu locations if any new ones are set.
		if ( isset( $_POST['menu-locations'] ) ) {
			$new_menu_locations = array_map( 'absint', $_POST['menu-locations'] );
			$menu_locations = array_merge( $menu_locations, $new_menu_locations );
		}

		// Set menu locations.
		set_theme_mod( 'nav_menu_locations', $menu_locations );

		// Add Menu.
		if ( 0 == $nav_menu_selected_id ) {
			$new_menu_title = trim( esc_html( $_POST['menu-name'] ) );

			if ( $new_menu_title ) {
				$_nav_menu_selected_id = wp_update_nav_menu_object( 0, array('menu-name' => $new_menu_title) );

				if ( is_wp_error( $_nav_menu_selected_id ) ) {
					$messages[] = '<div id="message" class="error notice is-dismissible"><p>' . $_nav_menu_selected_id->get_error_message() . '</p></div>';
				} else {
					$_menu_object = wp_get_nav_menu_object( $_nav_menu_selected_id );
					$nav_menu_selected_id = $_nav_menu_selected_id;
					$nav_menu_selected_title = $_menu_object->name;
					if ( isset( $_REQUEST['menu-item'] ) )
						wp_save_nav_menu_items( $nav_menu_selected_id, absint( $_REQUEST['menu-item'] ) );
					if ( isset( $_REQUEST['zero-menu-state'] ) ) {
						// If there are menu items, add them
						wp_nav_menu_update_menu_items( $nav_menu_selected_id, $nav_menu_selected_title );
						// Auto-save nav_menu_locations
						$locations = get_nav_menu_locations();
						foreach ( $locations as $location => $menu_id ) {
								$locations[ $location ] = $nav_menu_selected_id;
								break; // There should only be 1
						}
						set_theme_mod( 'nav_menu_locations', $locations );
					}
					if ( isset( $_REQUEST['use-location'] ) ) {
						$locations = get_registered_nav_menus();
						$menu_locations = get_nav_menu_locations();
						if ( isset( $locations[ $_REQUEST['use-location'] ] ) )
							$menu_locations[ $_REQUEST['use-location'] ] = $nav_menu_selected_id;
						set_theme_mod( 'nav_menu_locations', $menu_locations );
					}

					// $messages[] = '<div id="message" class="updated"><p>' . sprintf( __( '<strong>%s</strong> has been created.' ), $nav_menu_selected_title ) . '</p></div>';
					wp_redirect( admin_url( 'nav-menus.php?menu=' . $_nav_menu_selected_id ) );
					exit();
				}
			} else {
				$messages[] = '<div id="message" class="error notice is-dismissible"><p>' . __( 'Please enter a valid menu name.' ) . '</p></div>';
			}

		// Update existing menu.
		} else {

			$_menu_object = wp_get_nav_menu_object( $nav_menu_selected_id );

			$menu_title = trim( esc_html( $_POST['menu-name'] ) );
			if ( ! $menu_title ) {
				$messages[] = '<div id="message" class="error notice is-dismissible"><p>' . __( 'Please enter a valid menu name.' ) . '</p></div>';
				$menu_title = $_menu_object->name;
			}

			if ( ! is_wp_error( $_menu_object ) ) {
				$_nav_menu_selected_id = wp_update_nav_menu_object( $nav_menu_selected_id, array( 'menu-name' => $menu_title ) );
				if ( is_wp_error( $_nav_menu_selected_id ) ) {
					$_menu_object = $_nav_menu_selected_id;
					$messages[] = '<div id="message" class="error notice is-dismissible"><p>' . $_nav_menu_selected_id->get_error_message() . '</p></div>';
				} else {
					$_menu_object = wp_get_nav_menu_object( $_nav_menu_selected_id );
					$nav_menu_selected_title = $_menu_object->name;
				}
			}

			// Update menu items.
			if ( ! is_wp_error( $_menu_object ) ) {
				$messages = array_merge( $messages, wp_nav_menu_update_menu_items( $_nav_menu_selected_id, $nav_menu_selected_title ) );

				// If the menu ID changed, redirect to the new URL.
				if ( $nav_menu_selected_id != $_nav_menu_selected_id ) {
					wp_redirect( admin_url( 'nav-menus.php?menu=' . intval( $_nav_menu_selected_id ) ) );
					exit();
				}
			}
		}
		break;
	case 'locations':
		if ( ! $num_locations ) {
			wp_redirect( admin_url( 'nav-menus.php' ) );
			exit();
		}

		add_filter( 'screen_options_show_screen', '__return_false' );

		if ( isset( $_POST['menu-locations'] ) ) {
			check_admin_referer( 'save-menu-locations' );

			$new_menu_locations = array_map( 'absint', $_POST['menu-locations'] );
			$menu_locations = array_merge( $menu_locations, $new_menu_locations );
			// Set menu locations
			set_theme_mod( 'nav_menu_locations', $menu_locations );

			$messages[] = '<div id="message" class="updated notice is-dismissible"><p>' . __( 'Menu locations updated.' ) . '</p></div