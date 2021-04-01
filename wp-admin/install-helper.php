<?php
/**
 * Plugins may load this file to gain access to special helper functions for
 * plugin installation. This file is not included by WordPress and it is
 * recommended, to prevent fatal errors, that this file is included using
 * require_once().
 *
 * These functions are not optimized for speed, but they should only be used
 * once in a while, so speed shouldn't be a concern. If it is and you are
 * needing to use these functions a lot, you might experience time outs. If you
 * do, then it is advised to just write the SQL code yourself.
 *
 *     check_column( 'wp_links', 'link_description', 'mediumtext' );
 *     if ( check_column( $wpdb->comments, 'comment_author', 'tinytext' ) ) {
 *         echo "ok\n";
 *     }
 *
 *     $error_count = 0;
 *     $tablename = $wpdb->links;
 *     // Check the column.
 *     if ( ! check_column($wpdb->links, 'link_description', 'varchar( 255 )' ) ) {
 *         $ddl = "ALTER TABLE $wpdb->links MODIFY COLUMN link_description varchar(255) NOT NULL DEFAULT '' ";
 *         $q = $wpdb->query( $ddl );
 *     }
 *
 *     if ( check_column( $wpdb->links, 'link_description', 'varchar( 255 )' ) ) {
 *         $res .= $tablename . ' - ok <br />';
 *     } else {
 *         $res .= 'There was a problem with ' . $tablename . '<br />';
 *         ++$error_count;
 *     }
 *
 * @package WordPress
 * @subpackage Plugin
 */

/** Load WordPress Bootstrap */
require_once(dirname(dirname(__FILE__)).'/wp-load.php');

if ( ! function_exists('maybe_create_table') ) :
/**
 * Create database table, if it doesn't already exist.
 *
 * @since 1.0.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param string $table_name Database table name.
 * @param string $create_ddl Create database table SQL.
 * @return bool False on error, true if already exists or success.
 */
function maybe_create_table($table_name, $create_ddl) {
	global $wpdb;
	foreach ($wpdb->get_col("SHOW TABLES",0) as $table ) {
		if ($table == $table_name) {
			return true;
		}
	}
	// Didn't find it, so try to create it.
	$wpdb->query($create_ddl);

	// We cannot directly tell that whether this succeeded!
	foreach ($wpdb->get_col("SHOW TABLES",0) as $table ) {
		if ($table == $table_name) {
			return true;
		}
	}
	return false;
}
endif;

if ( ! function_exists('maybe_add_column') ) :
/**
 * Add column to database table, if column doesn't already exist in table.
 *
 * @since 1.0.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param string $table_name Database table name
 * @param string $column_name Table column name
 * @param string $create_ddl SQL to add column to table.
 * @return bool False on failure. True, if already exists or was successful.
 */
function maybe_add_column($table_name, $column_name, $create_ddl) {
	global $wpdb;
	foreach ($wpdb->get_col("DESC $table_name",0) as $column ) {

		if ($column == $column_name) {
			return true;
		}
	}

	// Didn't find it, so try to create it.
	$wpdb->query($create_ddl);

	// We cannot directly tell that whether this succeeded!
	foreach ($wpdb->get_col("DESC $table_name",0) as $column ) {
		if ($column == $column_name) {
			return true;
		}
	}
	return false;
}
endif;

/**
 * Drop column from database table, if it exists.
 *
 * @since 1.0.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param string $table_name Table name
 * @param string $column_name Column name
 * @param string $drop_ddl SQL statement to drop column.
 * @return bool False on failure, true on success or doesn't exist.
 */
function maybe_drop_column($table_name, $column_name, $drop_ddl) {
	global $wpdb;
	foreach ($wpdb->get_col("DESC $table_name",0) as $column ) {
		if ($column == $column_name) {

			// Found it, so try to drop it.
			$wpdb->query($drop_ddl);

			// We cannot directly tell that whether this succeeded!
			foreach ($wpdb->get_col("DESC $table_name",0) as $column ) {
				if ($column == $column_name) {
					return false;
				}
			}
		}
	}
	// Else didn't find it.
	return true;
}

/**
 * Check column matches criteria.
 *
 * Uses the SQL DESC for r