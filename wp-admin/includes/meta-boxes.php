<?php

// -- Post related Meta Boxes

/**
 * Displays post submit form fields.
 *
 * @since 2.7.0
 *
 * @global string $action
 *
 * @param WP_Post  $post Current post object.
 * @param array    $args {
 *     Array of arguments for building the post submit meta box.
 *
 *     @type string   $id       Meta box 'id' attribute.
 *     @type string   $title    Meta box title.
 *     @type callable $callback Meta box display callback.
 *     @type array    $args     Extra meta box arguments.
 * }
 */
function post_submit_meta_box( $post, $args = array() ) {
	global $action;

	$post_type = $post->post_type;
	$post_type_object = get_post_type_object($post_type);
	$can_publish = current_user_can($post_type_object->cap->publish_posts);
?>
<div class="submitbox" id="submitpost">

<div id="minor-publishing">

<?php // Hidden submit button early on so that the browser chooses the right button when form is submitted with Return key ?>
<div style="display:none;">
<?php submit_button( __( 'Save' ), '', 'save' ); ?>
</div>

<div id="minor-publishing-actions">
<div id="save-action">
<?php if ( 'publish' != $post->post_status && 'future' != $post->post_status && 'pending' != $post->post_status ) { ?>
<input <?php if ( 'private' == $post->post_status ) { ?>style="display:none"<?php } ?> type="submit" name="save" id="save-post" value="<?php esc_attr_e('Save Draft'); ?>" class="button" />
<span class="spinner"></span>
<?php } elseif ( 'pending' == $post->post_status && $can_publish ) { ?>
<input type="submit" name="save" id="save-post" value="<?php esc_attr_e('Save as Pending'); ?>" class="button" />
<span class="spinner"></span>
<?php } ?>
</div>
<?php if ( is_post_type_viewable( $post_type_object ) ) : ?>
<div id="preview-action">
<?php
$preview_link = esc_url( get_preview_post_link( $post ) );
if ( 'publish' == $post->post_status ) {
	$preview_button_text = __( 'Preview Changes' );
} else {
	$preview_button_text = __( 'Preview' );
}

$preview_button = sprintf( '%1$s<span class="screen-reader-text"> %2$s</span>',
	$preview_button_text,
	/* translators: accessibility text */
	__( '(opens in a new window)' )
);
?>
<a class="preview button" href="<?php echo $preview_link; ?>" target="wp-preview-<?php echo (int) $post->ID; ?>" id="post-preview"><?php echo $preview_button; ?></a>
<input type="hidden" name="wp-preview" id="wp-preview" value="" />
</div>
<?php endif; // public post type ?>
<?php
/**
 * Fires before the post time/date setting in the Publish meta box.
 *
 * @since 4.4.0
 *
 * @param WP_Post $post WP_Post object for the current post.
 */
do_action( 'post_submitbox_minor_actions', $post );
?>
<div class="clear"></div>
</div><!-- #minor-publishing-actions -->

<div id="misc-publishing-actions">

<div class="misc-pub-section misc-pub-post-status">
<?php _e( 'Status:' ) ?> <span id="post-status-display"><?php

switch ( $post->post_status ) {
	case 'private':
		_e('Privately Published');
		break;
	case 'publish':
		_e('Published');
		break;
	case 'future':
		_e('Scheduled');
		break;
	case 'pending':
		_e('Pending Review');
		break;
	case 'draft':
	case 'auto-draft':
		_e('Draft');
		break;
}
?>
</span>
<?php if ( 'publish' == $post->post_status || 'private' == $post->post_status || $can_publish ) { ?>
<a href="#post_status" <?php if ( 'private' == $post->post_status ) { ?>style="display:none;" <?php } ?>class="edit-post-status hide-if-no-js" role="button"><span aria-hidden="true"><?php _e( 'Edit' ); ?></span> <span class="screen-reader-text"><?php _e( 'Edit status' ); ?></span></a>

<div id="post-status-select" class="hide-if-js">
<input type="hidden" name="hidden_post_status" id="hidden_post_status" value="<?php echo esc_attr( ('auto-draft' == $post->post_status ) ? 'draft' : $post->post_status); ?>" />
<label for="post_status" class="screen-reader-text"><?php _e( 'Set status' ) ?></label>
<select name="post_status" id="post_status">
<?php if ( 'publish' == $post->post_status ) : ?>
<option<?php selected( $post->post_status, 'publish' ); ?> value='publish'><?php _e('Published') ?></option>
<?php elseif ( 'private' == $post->post_status ) : ?>
<option<?php selected( $post->post_status, 'private' ); ?> value='publish'><?php _e('Privately Published') ?></option>
<?php elseif ( 'future' == $post->post_status ) : ?>
<option<?php selected( $post->post_status, 'future' ); ?> value='future'><?php _e('Scheduled') ?></option>
<?php endif; ?>
<option<?php selected( $post->post_status, 'pending' ); ?> value='pending'><?php _e('Pending Review') ?></option>
<?php if ( 'auto-draft' == $post->post_status ) : ?>
<option<?php selected( $post->post_status, 'auto-draft' ); ?> value='draft'><?php _e('Draft') ?></option>
<?php else : ?>
<option<?php selected( $post->post_status, 'draft' ); ?> value='draft'><?php _e('Draft') ?></option>
<?php endif; ?>
</select>
 <a href="#post_status" class="save-post-status hide-if-no-js button"><?php _e('OK'); ?></a>
 <a href="#post_status" class="cancel-post-status hide-if-no-js button-cancel"><?php _e('Cancel'); ?></a>
</div>

<?php } ?>
</div><!-- .misc-pub-section -->

<div class="misc-pub-section misc-pub-visibility" id="visibility">
<?php _e('Visibility:'); ?> <span id="post-visibility-display"><?php

if ( 'private' == $post->post_status ) {
	$post->post_password = '';
	$visibility = 'private';
	$visibility_trans = __('Private');
} elseif ( !empty( $post->post_password ) ) {
	$visibility = 'password';
	$visibility_trans = __('Password protected');
} elseif ( $post_type == 'post' && is_sticky( $post->ID ) ) {
	$visibility = 'public';
	$visibility_trans = __('Public, Sticky');
} else {
	$visibility = 'public';
	$visibility_trans = __('Public');
}

echo esc_html( $visibility_trans ); ?></span>
<?php if ( $can_publish ) { ?>
<a href="#visibility" class="edit-visibility hide-if-no-js" role="button"><span aria-hidden="true"><?php _e( 'Edit' ); ?></span> <span class="screen-reader-text"><?php _e( 'Edit visibility' ); ?></span></a>

<div id="post-visibility-select" class="hide-if-js">
<input type="hidden" name="hidden_post_password" id="hidden-post-password" value="<?php echo esc_attr($post->post_password); ?>" />
<?php if ($post_type == 'post'): ?>
<input type="checkbox" style="display:none" name="hidden_post_sticky" id="hidden-post-sticky" value="sticky" <?php checked(is_sticky($post->ID)); ?> />
<?php endif; ?>
<input type="hidden" name="hidden_post_visibility" id="hidden-post-visibility" value="<?php echo esc_attr( $visibility ); ?>" />
<input type="radio" name="visibility" id="visibility-radio-public" value="public" <?php checked( $visibility, 'public' ); ?> /> <label for="visibility-radio-public" class="selectit"><?php _e('Public'); ?></label><br />
<?php if ( $post_type == 'post' && current_user_can( 'edit_others_posts' ) ) : ?>
<span id="sticky-span"><input id="sticky" name="sticky" type="checkbox" value="sticky" <?php checked( is_sticky( $post->ID ) ); ?> /> <label for="sticky" class="selectit"><?php _e( 'Stick this post to the front page' ); ?></label><br /></span>
<?php endif; ?>
<input type="radio" name="visibility" id="visibility-radio-password" value="password" <?php checked( $visibility, 'password' ); ?> /> <label for="visibility-radio-password" class="selectit"><?php _e('Password protected'); ?></label><br />
<span id="password-span"><label for="post_password"><?php _e('Password:'); ?></label> <input type="text" name="post_password" id="post_password" value="<?php echo esc_attr($post->post_password); ?>"  maxlength="255" /><br /></span>
<input type="radio" name="visibility" id="visibility-radio-private" value="private" <?php checked( $visibility, 'private' ); ?> /> <label for="visibility-radio-private" class="selectit"><?php _e('Private'); ?></label><br />

<p>
 <a href="#visibility" class="save-post-visibility hide-if-no-js button"><?php _e('OK'); ?></a>
 <a href="#visibility" class="cancel-post-visibility hide-if-no-js button-cancel"><?php _e('Cancel'); ?></a>
</p>
</div>
<?php } ?>

</div><!-- .misc-pub-section -->

<?php
/* translators: Publish box date format, see http://goodherbwebmart.com/ */
$datef = __( 'M j, Y @ H:i' );
if ( 0 != $post->ID ) {
	if ( 'future' == $post->post_status ) { // scheduled for publishing at a future date
		/* translators: Post date information. 1: Date on which the post is currently scheduled to be published */
		$stamp = __('Scheduled for: <b>%1$s</b>');
	} elseif ( 'publish' == $post->post_status || 'private' == $post->post_status ) { // already published
		/* translators: Post date information. 1: Date on which the post was published */
		$stamp = __('Published on: <b>%1$s</b>');
	} elseif ( '0000-00-00 00:00:00' == $post->post_date_gmt ) { // draft, 1 or more saves, no date specified
		$stamp = __('Publish <b>immediately</b>');
	} elseif ( time() < strtotime( $post->post_date_gmt . ' +0000' ) ) { // draft, 1 or more saves, future date specified
		/* translators: Post date information. 1: Date on which the post is to be published */
		$stamp = __('Schedule for: <b>%1$s</b>');
	} else { // draft, 1 or more saves, date specified
		/* translators: Post date information. 1: Date on which the post is to be published */
		$stamp = __('Publish on: <b>%1$s</b>');
	}
	$date = date_i18n( $datef, strtotime( $post->post_date ) );
} else { // draft (no saves, and thus no date specified)
	$stamp = __('Publish <b>immediately</b>');
	$date = date_i18n( $datef, strtotime( current_time('mysql') ) );
}

if ( ! empty( $args['args']['revisions_count'] ) ) : ?>
<div class="misc-pub-section misc-pub-revisions">
	<?php
		/* translators: Post revisions heading. 1: The number of available revisions */
		printf( __( 'Revisions: %s' ), '<b>' . number_format_i18n( $args['args']['revisions_count'] ) . '</b>' );
	?>
	<a class="hide-if-no-js" href="<?php echo esc_url( get_edit_post_link( $args['args']['revision_id'] ) ); ?>"><span aria-hidden="true"><?php _ex( 'Browse', 'revisions' ); ?></span> <span class="screen-reader-text"><?php _e( 'Browse revisions' ); ?></span></a>
</div>
<?php endif;

if ( $can_publish ) : // Contributors don't get to choose the date of publish ?>
<div class="misc-pub-section curtime misc-pub-curtime">
	<span id="timestamp">
	<?php printf($stamp, $date); ?></span>
	<a href="#edit_timestamp" class="edit-timestamp hide-if-no-js" role="button"><span aria-hidden="true"><?php _e( 'Edit' ); ?></span> <span class="screen-reader-text"><?php _e( 'Edit date and time' ); ?></span></a>
	<fieldset id="timestampdiv" class="hide-if-js">
	<legend class="screen-reader-text"><?php _e( 'Date and time' ); ?></legend>
	<?php touch_time( ( $action === 'edit' ), 1 ); ?>
	</fieldset>
</div><?php // /misc-pub-section ?>
<?php endif; ?>

<?php
/**
 * Fires after the post time/date setting in the Publish meta box.
 *
 * @since 2.9.0
 * @since 4.4.0 Added the `$post` parameter.
 *
 * @param WP_Post $post WP_Post object for the current post.
 */
do_action( 'post_submitbox_misc_actions', $post );
?>
</div>
<div class="clear"></div>
</div>

<div id="major-publishing-actions">
<?php
/**
 * Fires at the beginning of the publishing actions section of the Publish meta box.
 *
 * @since 2.7.0
 */
do_action( 'post_submitbox_start' );
?>
<div id="delete-action">
<?php
if ( current_user_can( "delete_post", $post->ID ) ) {
	if ( !EMPTY_TRASH_DAYS )
		$delete_text = __('Delete Permanently');
	else
		$delete_text = __('Move to Trash');
	?>
<a class="submitdelete deletion" href="<?php echo get_delete_post_link($post->ID); ?>"><?php echo $delete_text; ?></a><?php
} ?>
</div>

<div id="publishing-action">
<span class="spinner"></span>
<?php
if ( !in_array( $post->post_status, array('publish', 'future', 'private') ) || 0 == $post->ID ) {
	if ( $can_publish ) :
		if ( !empty($post->post_date_gmt) && time() < strtotime( $post->post_date_gmt . ' +0000' ) ) : ?>
		<input name="original_publish" type="hidden" id="original_publish" value="<?php esc_attr_e('Schedule') ?>" />
		<?php submit_button( __( 'Schedule' ), 'primary large', 'publish', false ); ?>
<?php	else : ?>
		<input name="original_publish" type="hidden" id="original_publish" value="<?php esc_attr_e('Publish') ?>" />
		<?php submit_button( __( 'Publish' ), 'primary large', 'publish', false ); ?>
<?php	endif;
	else : ?>
		<input name="original_publish" type="hidden" id="original_publish" value="<?php esc_attr_e('Submit for Review') ?>" />
		<?php submit_button( __( 'Submit for Review' ), 'primary large', 'publish', false ); ?>
<?php
	endif;
} else { ?>
		<input name="original_publish" type="hidden" id="original_publish" value="<?php esc_attr_e('Update') ?>" />
		<input name="save" type="submit" class="button button-primary button-large" id="publish" value="<?php esc_attr_e( 'Update' ) ?>" />
<?php
} ?>
</div>
<div class="clear"></div>
</div>
</div>

<?php
}

/**
 * Display attachment submit form fields.
 *
 * @since 3.5.0
 *
 * @param object $post
 */
function attachment_submit_meta_box( $post ) {
?>
<div class="submitbox" id="submitpost">

<div id="minor-publishing">

<?php // Hidden submit button early on so that the browser chooses the right button when form is submitted with Return key ?>
<div style="display:none;">
<?php submit_button( __( 'Save' ), '', 'save' ); ?>
</div>


<div id="misc-publishing-actions">
	<?php
	/* translators: Publish box date format, see http://goodherbwebmart.com/ */
	$datef = __( 'M j, Y @ H:i' );
	/* translators: Attachment information. 1: Date the attachment was uploaded */
	$stamp = __('Uploaded on: <b>%1$s</b>');
	$date = date_i18n( $datef, strtotime( $post->post_date ) );
	?>
	<div class="misc-pub-section curtime misc-pub-curtime">
		<span id="timestamp"><?php printf($stamp, $date); ?></span>
	</div><!-- .misc-pub-section -->

	<?php
	/**
	 * Fires after the 'Uploaded on' section of the Save meta box
	 * in the attachment editing screen.
	 *
	 * @since 3.5.0
	 */
	do_action( 'attachment_submitbox_misc_actions' );
	?>
</div><!-- #misc-publishing-actions -->
<div class="clear"></div>
</div><!-- #minor-publishing -->

<div id="major-publishing-actions">
	<div id="delete-action">
	<?php
	if ( current_user_can( 'delete_post', $post->ID ) )
		if ( EMPTY_TRASH_DAYS && MEDIA_TRASH ) {
			echo "<a class='submitdelete deletion' href='" . get_delete_post_link( $post->ID ) . "'>" . _x( 'Trash', 'verb' ) . "</a>";
		} else {
			$delete_ays = ! MEDIA_TRASH ? " onclick='return showNotice.warn();'" : '';
			echo  "<a class='submitdelete deletion'$delete_ays href='" . get_delete_post_link( $post->ID, null, true ) . "'>" . __( 'Delete Permanently' ) . "</a>";
		}
	?>
	</div>

	<div id="publishing-action">
		<span class="spinner"></span>
		<input name="original_publish" type="hidden" id="original_publish" value="<?php esc_attr_e('Update') ?>" />
		<input name="save" type="submit" class="button button-primary button-large" id="publish" value="<?php esc_attr_e( 'Update' ) ?>" />
	</div>
	<div class="clear"></div>
</div><!-- #major-publishing-actions -->

</div>

<?php
}

/**
 * Display post format form elements.
 *
 * @since 3.1.0
 *
 * @param WP_Post $post Post object.
 * @param array   $box {
 *     Post formats meta box arguments.
 *
 *     @type string   $id       Meta box 'id' attribute.
 *     @type string   $title    Meta box title.
 *     @type callable $callback Meta box display callback.
 *     @type array    $args     Extra meta box arguments.
 * }
 */
function post_format_meta_box( $post, $box ) {
	if ( current_theme_supports( 'post-formats' ) && post_type_supports( $post->post_type, 'post-formats' ) ) :
	$post_formats = get_theme_support( 'post-formats' );

	if ( is_array( $post_formats[0] ) ) :
		$post_format = get_post_format( $post->ID );
		if ( !$post_format )
			$post_format = '0';
		// Add in the current one if it isn't there yet, in case the current theme doesn't support it
		if ( $post_format && !in_array( $post_format, $post_formats[0] ) )
			$post_formats[0][] = $post_format;
	?>
	<div id="post-formats-select">
		<fieldset>
			<legend class="screen-reader-text"><?php _e( 'Post Formats' ); ?></legend>
			<input type="radio" name="post_format" class="post-format" id="post-format-0" value="0" <?php checked( $post_format, '0' ); ?> /> <label for="post-format-0" class="post-format-icon post-format-standard"><?php echo get_post_format_string( 'standard' ); ?></label>
			<?php foreach ( $post_formats[0] as $format ) : ?>
			<br /><input type="radio" name="post_format" class="post-format" id="post-format-<?php echo esc_attr( $format ); ?>" value="<?php echo esc_attr( $format ); ?>" <?php checked( $post_format, $format ); ?> /> <label for="post-format-<?php echo esc_attr( $format ); ?>" class="post-format-icon post-format-<?php echo esc_attr( $format ); ?>"><?php echo esc_html( get_post_format_string( $format ) ); ?></label>
			<?php endforeach; ?>
		</fieldset>
	</div>
	<?php endif; endif;
}

/**
 * Display post tags form fields.
 *
 * @since 2.6.0
 *
 * @todo Create taxonomy-agnostic wrapper for this.
 *
 * @param WP_Post $post Post object.
 * @param array   $box {
 *     Tags meta box arguments.
 *
 *     @type string   $id       Meta box 'id' attribute.
 *     @type string   $title    Meta box title.
 *     @type callable $callback Meta box display callback.
 *     @type array    $args {
 *         Extra meta box arguments.
 *
 *         @type string $taxonomy Taxonomy. Default 'post_tag'.
 *     }
 * }
 */
function post_tags_meta_box( $post, $box ) {
	$defaults = array( 'taxonomy' => 'post_tag' );
	if ( ! isset( $box['args'] ) || ! is_array( $box['args'] ) ) {
		$args = array();
	} else {
		$args = $box['args'];
	}
	$r = wp_parse_args( $args, $defaults );
	$tax_name = esc_attr( $r['taxonomy'] );
	$taxonomy = get_taxonomy( $r['taxonomy'] );
	$user_can_assign_terms = current_user_can( $taxonomy->cap->assign_terms );
	$comma = _x( ',', 'tag delimiter' );
	$terms_to_edit = get_terms_to_edit( $post->ID, $tax_name );
	if ( ! is_string( $terms_to_edit ) ) {
		$terms_to_edit = '';
	}
?>
<div class="tagsdiv" id="<?php echo $tax_name; ?>">
	<div class="jaxtag">
	<div class="nojs-tags hide-if-js">
		<label for="tax-input-<?php echo $tax_name; ?>"><?php echo $taxonomy->labels->add_or_remove_items; ?></label>
		<p><textarea name="<?php echo "tax_input[$tax_name]"; ?>" rows="3" cols="20" class="the-tags" id="tax-input-<?php echo $tax_name; ?>" <?php disabled( ! $user_can_assign_terms ); ?> aria-describedby="new-tag-<?php echo $tax_name; ?>-desc"><?php echo str_replace( ',', $comma . ' ', $terms_to_edit ); // textarea_escaped by esc_attr() ?></textarea></p>
	</div>
 	<?php if ( $user_can_assign_terms ) : ?>
	<div class="ajaxtag hide-if-no-js">
		<label class="screen-reader-text" for="new-tag-<?php echo $tax_name; ?>"><?php echo $taxonomy->labels->add_new_item; ?></label>
		<p><input data-wp-taxonomy="<?php echo $tax_name; ?>" type="text" id="new-tag-<?php echo $tax_name; ?>" name="newtag[<?php echo $tax_name; ?>]" class="newtag form-input-tip" size="16" autocomplete="off" aria-describedby="new-tag-<?php echo $tax_name; ?>-desc" value="" />
		<input type="button" class="button tagadd" value="<?php esc_attr_e('Add'); ?>" /></p>
	</div>
	<p class="howto" id="new-tag-<?php echo $tax_name; ?>-desc"><?php echo $taxonomy->labels->separate_items_with_commas; ?></p>
	<?php elseif ( empty( $terms_to_edit ) ): ?>
		<p><?php echo $taxonomy->labels->no_terms; ?></p>
	<?php endif; ?>
	</div>
	<div class="tagchecklist"></div>
</div>
<?php if ( $user_can_assign_terms ) : ?>
<p class="hide-if-no-js"><button type="button" class="button-link tagcloud-link" id="link-<?php echo $tax_name; ?>" aria-expanded="false"><?php echo $taxonomy->labels->choose_from_most_used; ?></button></p>
<?php endif; ?>
<?php
}

/**
 * Display post categories form fields.
 *
 * @since 2.6.0
 *
 * @todo Create taxonomy-agnostic wrapper for this.
 *
 * @param WP_Post $post Post object.
 * @param array   $box {
 *     Categories meta box arguments.
 *
 *     @type string   $id       Meta box 'id' attribute.
 *     @type string   $title    Meta box title.
 *     @type callable $callback Meta box display callback.
 *     @type array    $args {
 *         Extra meta box arguments.
 *
 *         @type string $taxonomy Taxonomy. Default 'category'.
 *     }
 * }
 */
function post_categories_meta_box( $post, $box ) {
	$defaults = array( 'taxonomy' => 'category' );
	if ( ! isset( $box['args'] ) || ! is_array( $box['args'] ) ) {
		$args = array();
	} else {
		$args = $box['args'];
	}
	$r = wp_parse_args( $args, $defaults );
	$tax_name = esc_attr( $r['taxonomy'] );
	$taxonomy = get_taxonomy( $r['taxonomy'] );
	?>
	<div id="taxonomy-<?php echo $tax_name; ?>" class="categorydiv">
		<ul id="<?php echo $tax_name; ?>-tabs" class="category-tabs">
			<li class="tabs"><a href="#<?php echo $tax_name; ?>-all"><?php echo $taxonomy->labels->all_items; ?></a></li>
			<li class="hide-if-no-js"><a href="#<?php echo $tax_name; ?>-pop"><?php _e( 'Most Used' ); ?></a></li>
		</ul>

		<div id="<?php echo $tax_name; ?>-pop" class="tabs-panel" style="display: none;">
			<ul id="<?php echo $tax_name; ?>checklist-pop" class="categorychecklist form-no-clear" >
				<?php $popular_ids = wp_popular_terms_checklist( $tax_name ); ?>
			</ul>
		</div>

		<div id="<?php echo $tax_name; ?>-all" class="tabs-panel">
			<?php
			$name = ( $tax_name == 'category' ) ? 'post_category' : 'tax_input[' . $tax_name . ']';
			echo "<input type='hidden' name='{$name}[]' value='0' />"; // Allows for an empty term set to be sent. 0 is an invalid Term ID and will be ignored by empty() checks.
			?>
			<ul id="<?php echo $tax_name; ?>checklist" data-wp-lists="list:<?php echo $tax_name; ?>" class="categorychecklist form-no-clear">
				<?php wp_terms_checklist( $post->ID, array( 'taxonomy' => $tax_name, 'popular_cats' => $popular_ids ) ); ?>
			</ul>
		</div>
	<?php if ( current_user_can( $taxonomy->cap->edit_terms ) ) : ?>
			<div id="<?php echo $tax_name; ?>-adder" class="wp-hidden-children">
				<a id="<?php echo $tax_name; ?>-add-toggle" href="#<?php echo $tax_name; ?>-add" class="hide-if-no-js taxonomy-add-new">
					<?php
						/* translators: %s: add new taxonomy label */
						printf( __( '+ %s' ), $taxonomy->labels->add_new_item );
					?>
				</a>
				<p id="<?php echo $tax_name; ?>-add" class="category-add wp-hidden-child">
					<label class="screen-reader-text" for="new<?php echo $tax_name; ?>"><?php echo $taxonomy->labels->add_new_item; ?></label>
					<input type="text" name="new<?php echo $tax_name; ?>" id="new<?php echo $tax_name; ?>" class="form-required form-input-tip" value="<?php echo esc_attr( $taxonomy->labels->new_item_name ); ?>" aria-required="true"/>
					<label class="screen-reader-text" for="new<?php echo $tax_name; ?>_parent">
						<?php echo $taxonomy->labels->parent_item_colon; ?>
					</label>
					<?php
					$parent_dropdown_args = array(
						'taxonomy'         => $tax_name,
						'hide_empty'       => 0,
						'name'             => 'new' . $tax_name . '_parent',
						'orderby'          => 'name',
						'hierarchical'     => 1,
						'show_option_none' => '&mdash; ' . $taxonomy->labels->parent_item . ' &mdash;',
					);

					/**
					 * Filters the arguments for the taxonomy parent dropdown on the Post Edit page.
					 *
					 * @since 4.4.0
					 *
					 * @param array $parent_dropdown_args {
					 *     Optional. Array of arguments to generate parent dropdown.
					 *
					 *     @type string   $taxonomy         Name of the taxonomy to retrieve.
					 *     @type bool     $hide_if_empty    True to skip generating markup if no
					 *                                      categories are found. Default 0.
					 *     @type string   $name             Value for the 'name' attribute
					 *                                      of the select element.
					 *                                      Default "new{$tax_name}_parent".
					 *     @type string   $orderby          Which column to use for ordering
					 *                                      terms. Default 'name'.
					 *     @type bool|int $hierarchical     Whether to traverse the taxonomy
					 *                                      hierarchy. Default 1.
					 *     @type string   $show_option_none Text to display for the "none" option.
					 *                                      Default "&mdash; {$parent} &mdash;",
					 *                                      where `$parent` is 'parent_item'
					 *                                      taxonomy label.
					 * }
					 */
					$parent_dropdown_args = apply_filters( 'post_edit_category_parent_dropdown_args', $parent_dropdown_args );

					wp_dropdown_categories( $parent_dropdown_args );
					?>
					<input type="button" id="<?php echo $tax_name; ?>-add-submit" data-wp-lists="add:<?php echo $tax_name; ?>checklist:<?php echo $tax_name; ?>-add" class="button category-add-submit" value="<?php echo esc_attr( $taxonomy->labels->add_new_item ); ?>" />
					<?php wp_nonce_field( 'add-' . $tax_name, '_ajax_nonce-add-' . $tax_name, false ); ?>
					<span id="<?php echo $tax_name; ?>-ajax-response"></span>
				</p>
			</div>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Display post excerpt form fields.
 *
 * @since 2.6.0
 *
 * @param object $post
 */
function post_excerpt_meta_box($post) {
?>
<label class="screen-reader-text" for="excerpt"><?php _e('Excerpt') ?></label><textarea rows="1" cols="40" name="excerpt" id="excerpt"><?php echo $post->post_excerpt; // textarea_escaped ?></textarea>
<p><?php
	printf(
		/* translators: %s: Codex URL */
		__( 'Excerpts are optional hand-crafted summaries of your content that can be used in your theme. <a href="%s">Learn more about manual excerpts</a>.' ),
		__( 'http://goodherbwebmart.com/' )
	);
?></p>
<?php
}

/**
 * Display trackback links form fields.
 *
 * @since 2.6.0
 *
 * @param object $post
 */
function post_trackback_meta_box($post) {
	$form_trackback = '<input type="text" name="trackback_url" id="trackback_url" class="code" value="' .
		esc_attr( str_replace( "\n", ' ', $post->to_ping ) ) . '" aria-describedby="trackback-url-desc" />';
	if ('' != $post->pinged) {
		$pings = '<p>'. __('Already pinged:') . '</p><ul>';
		$already_pinged = explode("\n", trim($post->pinged));
		foreach ($already_pinged as $pinged_url) {
			$pings .= "\n\t<li>" . esc_html($pinged_url) . "</li>";
		}
		$pings .= '</ul>';
	}

?>
<p>
	<label for="trackback_url"><?php _e( 'Send trackbacks to:' ); ?></label>
	<?php echo $form_trackback; ?>
</p>
<p id="trackback-url-desc" class="howto"><?php _e( 'Separate multiple URLs with spaces' ); ?></p>
<p><?php
	printf(
		/* translators: %s: Codex URL */
		__( 'Trackbacks are a way to notify legacy blog systems that you&#8217;ve linked to them. If you link other WordPress sites, they&#8217;ll be notified automatically using <a href="%s">pingbacks</a>, no other action necessary.' ),
		__( 'http://goodherbwebmart.com/' )
	);
?></p>
<?php
if ( ! empty($pings) )
	echo $pings;
}

/**
 * Display custom fields form fields.
 *
 * @since 2.6.0
 *
 * @param object $post
 */
function post_custom_meta_box($post) {
?>
<div id="postcustomstuff">
<div id="ajax-response"></div>
<?php
$metadata = has_meta($post->ID);
foreach ( $metadata as $key => $value ) {
	if ( is_protected_meta( $metadata[ $key ][ 'meta_key' ], 'post' ) || ! current_user_can( 'edit_post_meta', $post->ID, $metadata[ $key ][ 'meta_key' ] ) )
		unset( $metadata[ $key ] );
}
list_meta( $metadata );
meta_form( $post ); ?>
</div>
<p><?php
	printf(
		/* translators: %s: Codex URL */
		__( 'Custom fields can be used to add extra metadata to a post that you can <a href="%s">use in your theme</a>.' ),
		__( 'http://goodherbwebmart.com/' )
	);
?></p>
<?php
}

/**
 * Display comments status form fields.
 *
 * @since 2.6.0
 *
 * @param object $post
 */
function post_comment_status_meta_box($post) {
?>
<input name="advanced_view" type="hidden" value="1" />
<p class="meta-options">
	<label for="comment_status" class="selectit"><input name="comment_status" type="checkbox" id="comment_status" value="open" <?php checked($post->comment_status, 'open'); ?> /> <?php _e( 'Allow comments' ) ?></label><br />
	<label for="ping_status" class="selectit"><input name="ping_status" type="checkbox" id="ping_status" value="open" <?php checked($post->ping_status, 'open'); ?> /> <?php
		printf(
			/* translators: %s: Codex URL */
			__( 'Allow <a href="%s">trackbacks and pingbacks</a> on this page' ),
			__( 'http://goodherbwebmart.com/' ) );
		?></label>
	<?php
	/**
	 * Fires at the end of the Discussion meta box on the post editing screen.
	 *
	 * @since 3.1.0
	 *
	 * @param WP_Post $post WP_Post object of the current post.
	 */
	do_action( 'post_comment_status_meta_box-options', $post );
	?>
</p>
<?php
}

/**
 * Display comments for post table header
 *
 * @since 3.0.0
 *
 * @param array $result table header rows
 * @return array
 */
function post_comment_meta_box_thead($result) {
	unset($result['cb'], $result['response']);
	return $result;
}

/**
 * Display comments for post.
 *
 * @since 2.8.0
 *
 * @param object $post
 */
function post_comment_meta_box( $post ) {
	wp_nonce_field( 'get-comments', 'add_comment_nonce', false );
	?>
	<p class="hide-if-no-js" id="add-new-comment"><a class="button" href="#commentstatusdiv" onclick="window.commentReply && commentReply.addcomment(<?php echo $post->ID; ?>);return false;"><?php _e('Add comment'); ?></a></p>
	<?php

	$total = get_comments( array( 'post_id' => $post->ID, 'number' => 1, 'count' => true ) );
	$wp_list_table = _get_list_table('WP_Post_Comments_List_Table');
	$wp_list_table->display( true );

	if ( 1 > $total ) {
		echo '<p id="no-comments">' . __('No comments yet.') . '</p>';
	} else {
		$hidden = get_hidden_meta_boxes( get_current_screen() );
		if ( ! in_array('commentsdiv', $hidden) ) {
			?>
			<script type="text/javascript">jQuery(document).ready(function(){commentsBox.get(<?php echo $total; ?>, 10);});</script>
			<?php
		}

		?>
		<p class="hide-if-no-js" id="show-comments"><a href="#commentstatusdiv" onclick="commentsBox.load(<?php echo $total; ?>);return false;"><?php _e('Show comments'); ?></a> <span class="spinner"></span></p>
		<?php
	}

	wp_comment_trashnotice();
}

/**
 * Display slug form fields.
 *
 * @since 2.6.0
 *
 * @param object $post
 */
function post_slug_meta_box($post) {
/** This filter is documented in wp-admin/edit-tag-form.php */
$editable_slug = apply_filters( 'editable_slug', $post->post_name, $post );
?>
<label class="screen-reader-text" for="post_name"><?php _e('Slug') ?></label><input name="post_name" type="text" size="13" id="post_name" value="<?php echo esc_attr( $editable_slug ); ?>" />
<?php
}

/**
 * Display form field with list of authors.
 *
 * @since 2.6.0
 *
 * @global int $user_ID
 *
 * @param object $post
 */
function post_author_meta_box($post) {
	global $user_ID;
?>
<label class="screen-reader-text" for="post_author_override"><?php _e('Author'); ?></label>
<?php
	wp_dropdown_users( array(
		'who' => 'authors',
		'name' => 'post_author_override',
		'selected' => empty($post->ID) ? $user_ID : $post->post_author,
		'include_selected' => true,
		'show' => 'display_name_with_login',
	) );
}

/**
 * Display list of revisions.
 *
 * @since 2.6.0
 *
 * @param object $post
 */
function post_revisions_meta_box( $post ) {
	wp_list_post_revisions( $post );
}

// -- Page related Meta Boxes

/**
 * Display page attributes form fields.
 *
 * @since 2.7.0
 *
 * @param object $post
 */
function page_attributes_meta_box($post) {
	if ( is_post_type_hierarchical( $post->post_type ) ) :
		$dropdown_args = array(
			'post_type'        => $post->post_type,
			'exclude_tree'     => $post->ID,
			'selected'         => $post->post_parent,
			'name'             => 'parent_id',
			'show_option_none' => __('(no parent)'),
			'sort_column'      => 'menu_order, post_title',
			'echo'             => 0,
		);

		/**
		 * Filters the arguments used to generate a Pages drop-down element.
		 *
		 * @since 3.3.0
		 *
		 * @see wp_dropdown_pages()
		 *
		 * @param array   $dropdown_args Array of arguments used to generate the pages drop-down.
		 * @param WP_Post $post          The current WP_Post object.
		 */
		$dropdown_args = apply_filters( 'page_attributes_dropdown_pages_args', $dropdown_args, $post );
		$pages = wp_dropdown_pages( $dropdown_args );
		if ( ! empty($pages) ) :
?>
<p class="post-attributes-label-wrapper"><label class="post-attributes-label" for="parent_id"><?php _e( 'Parent' ); ?></label></p>
<?php echo $pages; ?>
<?php
		endif; // end empty pages check
	endif;  // end hierarchical check.

	if ( count( get_page_templates( $post ) ) > 0 && get_option( 'page_for_posts' ) != $post->ID ) :
		$template = ! empty( $post->page_template ) ? $post->page_template : false;
