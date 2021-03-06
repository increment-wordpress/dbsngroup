<?php
/**
 * WordPress Customize Control classes
 *
 * @package WordPress
 * @subpackage Customize
 * @since 3.4.0
 */

/**
 * Customize Control class.
 *
 * @since 3.4.0
 */
class WP_Customize_Control {

	/**
	 * Incremented with each new class instantiation, then stored in $instance_number.
	 *
	 * Used when sorting two instances whose priorities are equal.
	 *
	 * @since 4.1.0
	 *
	 * @static
	 * @access protected
	 * @var int
	 */
	protected static $instance_count = 0;

	/**
	 * Order in which this instance was created in relation to other instances.
	 *
	 * @since 4.1.0
	 * @access public
	 * @var int
	 */
	public $instance_number;

	/**
	 * Customizer manager.
	 *
	 * @since 3.4.0
	 * @access public
	 * @var WP_Customize_Manager
	 */
	public $manager;

	/**
	 * Control ID.
	 *
	 * @since 3.4.0
	 * @access public
	 * @var string
	 */
	public $id;

	/**
	 * All settings tied to the control.
	 *
	 * @since 3.4.0
	 * @access public
	 * @var array
	 */
	public $settings;

	/**
	 * The primary setting for the control (if there is one).
	 *
	 * @since 3.4.0
	 * @access public
	 * @var string
	 */
	public $setting = 'default';

	/**
	 * Capability required to use this control.
	 *
	 * Normally this is empty and the capability is derived from the capabilities
	 * of the associated `$settings`.
	 *
	 * @since 4.5.0
	 * @access public
	 * @var string
	 */
	public $capability;

	/**
	 * Order priority to load the control in Customizer.
	 *
	 * @since 3.4.0
	 * @access public
	 * @var int
	 */
	public $priority = 10;

	/**
	 * Section the control belongs to.
	 *
	 * @since 3.4.0
	 * @access public
	 * @var string
	 */
	public $section = '';

	/**
	 * Label for the control.
	 *
	 * @since 3.4.0
	 * @access public
	 * @var string
	 */
	public $label = '';

	/**
	 * Description for the control.
	 *
	 * @since 4.0.0
	 * @access public
	 * @var string
	 */
	public $description = '';

	/**
	 * List of choices for 'radio' or 'select' type controls, where values are the keys, and labels are the values.
	 *
	 * @since 3.4.0
	 * @access public
	 * @var array
	 */
	public $choices = array();

	/**
	 * List of custom input attributes for control output, where attribute names are the keys and values are the values.
	 *
	 * Not used for 'checkbox', 'radio', 'select', 'textarea', or 'dropdown-pages' control types.
	 *
	 * @since 4.0.0
	 * @access public
	 * @var array
	 */
	public $input_attrs = array();

	/**
	 * Show UI for adding new content, currently only used for the dropdown-pages control.
	 *
	 * @since 4.7.0
	 * @access public
	 * @var bool
	 */
	public $allow_addition = false;

	/**
	 * @deprecated It is better to just call the json() method
	 * @since 3.4.0
	 * @access public
	 * @var array
	 */
	public $json = array();

	/**
	 * Control's Type.
	 *
	 * @since 3.4.0
	 * @access public
	 * @var string
	 */
	public $type = 'text';

	/**
	 * Callback.
	 *
	 * @since 4.0.0
	 * @access public
	 *
	 * @see WP_Customize_Control::active()
	 *
	 * @var callable Callback is called with one argument, the instance of
	 *               WP_Customize_Control, and returns bool to indicate whether
	 *               the control is active (such as it relates to the URL
	 *               currently being previewed).
	 */
	public $active_callback = '';

	/**
	 * Constructor.
	 *
	 * Supplied `$args` override class property defaults.
	 *
	 * If `$args['settings']` is not defined, use the $id as the setting ID.
	 *
	 * @since 3.4.0
	 *
	 * @param WP_Customize_Manager $manager Customizer bootstrap instance.
	 * @param string               $id      Control ID.
	 * @param array                $args    {
	 *     Optional. Arguments to override class property defaults.
	 *
	 *     @type int                  $instance_number Order in which this instance was created in relation
	 *                                                 to other instances.
	 *     @type WP_Customize_Manager $manager         Customizer bootstrap instance.
	 *     @type string               $id              Control ID.
	 *     @type array                $settings        All settings tied to the control. If undefined, `$id` will
	 *                                                 be used.
	 *     @type string               $setting         The primary setting for the control (if there is one).
	 *                                                 Default 'default'.
	 *     @type int                  $priority        Order priority to load the control. Default 10.
	 *     @type string               $section         Section the control belongs to. Default empty.
	 *     @type string               $label           Label for the control. Default empty.
	 *     @type string               $description     Description for the control. Default empty.
	 *     @type array                $choices         List of choices for 'radio' or 'select' type controls, where
	 *                                                 values are the keys, and labels are the values.
	 *                                                 Default empty array.
	 *     @type array                $input_attrs     List of custom input attributes for control output, where
	 *                                                 attribute names are the keys and values are the values. Not
	 *                                                 used for 'checkbox', 'radio', 'select', 'textarea', or
	 *                                                 'dropdown-pages' control types. Default empty array.
	 *     @type array                $json            Deprecated. Use WP_Customize_Control::json() instead.
	 *     @type string               $type            Control type. Core controls include 'text', 'checkbox',
	 *                                                 'textarea', 'radio', 'select', and 'dropdown-pages'. Additional
	 *                                                 input types such as 'email', 'url', 'number', 'hidden', and
	 *                                                 'date' are supported implicitly. Default 'text'.
	 * }
	 */
	public function __construct( $manager, $id, $args = array() ) {
		$keys = array_keys( get_object_vars( $this ) );
		foreach ( $keys as $key ) {
			if ( isset( $args[ $key ] ) ) {
				$this->$key = $args[ $key ];
			}
		}

		$this->manager = $manager;
		$this->id = $id;
		if ( empty( $this->active_callback ) ) {
			$this->active_callback = array( $this, 'active_callback' );
		}
		self::$instance_count += 1;
		$this->instance_number = self::$instance_count;

		// Process settings.
		if ( ! isset( $this->settings ) ) {
			$this->settings = $id;
		}

		$settings = array();
		if ( is_array( $this->settings ) ) {
			foreach ( $this->settings as $key => $setting ) {
				$settings[ $key ] = $this->manager->get_setting( $setting );
			}
		} else if ( is_string( $this->settings ) ) {
			$this->setting = $this->manager->get_setting( $this->settings );
			$settings['default'] = $this->setting;
		}
		$this->settings = $settings;
	}

	/**
	 * Enqueue control related scripts/styles.
	 *
	 * @since 3.4.0
	 */
	public function enqueue() {}

	/**
	 * Check whether control is active to current Customizer preview.
	 *
	 * @since 4.0.0
	 *
	 * @return bool Whether the control is active to the current preview.
	 */
	final public function active() {
		$control = $this;
		$active = call_user_func( $this->active_callback, $this );

		/**
		 * Filters response of WP_Customize_Control::active().
		 *
		 * @since 4.0.0
		 *
		 * @param bool                 $active  Whether the Customizer control is active.
		 * @param WP_Customize_Control $control WP_Customize_Control instance.
		 */
		$active = apply_filters( 'customize_control_active', $active, $control );

		return $active;
	}

	/**
	 * Default callback used when invoking WP_Customize_Control::active().
	 *
	 * Subclasses can override this with their specific logic, or they may
	 * provide an 'active_callback' argument to the constructor.
	 *
	 * @since 4.0.0
	 *
	 * @return true Always true.
	 */
	public function active_callback() {
		return true;
	}

	/**
	 * Fetch a setting's value.
	 * Grabs the main setting by default.
	 *
	 * @since 3.4.0
	 *
	 * @param string $setting_key
	 * @return mixed The requested setting's value, if the setting exists.
	 */
	final public function value( $setting_key = 'default' ) {
		if ( isset( $this->settings[ $setting_key ] ) ) {
			return $this->settings[ $setting_key ]->value();
		}
	}

	/**
	 * Refresh the parameters passed to the JavaScript via JSON.
	 *
	 * @since 3.4.0
	 */
	public function to_json() {
		$this->json['settings'] = array();
		foreach ( $this->settings as $key => $setting ) {
			$this->json['settings'][ $key ] = $setting->id;
		}

		$this->json['type'] = $this->type;
		$this->json['priority'] = $this->priority;
		$this->json['active'] = $this->active();
		$this->json['section'] = $this->section;
		$this->json['content'] = $this->get_content();
		$this->json['label'] = $this->label;
		$this->json['description'] = $this->description;
		$this->json['instanceNumber'] = $this->instance_number;

		if ( 'dropdown-pages' === $this->type ) {
			$this->json['allow_addition'] = $this->allow_addition;
		}
	}

	/**
	 * Get the data to export to the client via JSON.
	 *
	 * @since 4.1.0
	 *
	 * @return array Array of parameters passed to the JavaScript.
	 */
	public function json() {
		$this->to_json();
		return $this->json;
	}

	/**
	 * Checks if the user can use this control.
	 *
	 * Returns false if the user cannot manipulate one of the associated settings,
	 * or if one of the associated settings does not exist. Also returns false if
	 * the associated section does not exist or if its capability check returns
	 * false.
	 *
	 * @since 3.4.0
	 *
	 * @return bool False if theme doesn't support the control or user doesn't have the required permissions, otherwise true.
	 */
	final public function check_capabilities() {
		if ( ! empty( $this->capability ) && ! current_user_can( $this->capability ) ) {
			return false;
		}

		foreach ( $this->settings as $setting ) {
			if ( ! $setting || ! $setting->check_capabilities() ) {
				return false;
			}
		}

		$section = $this->manager->get_section( $this->section );
		if ( isset( $section ) && ! $section->check_capabilities() ) {
			return false;
		}

		return true;
	}

	/**
	 * Get the control's content for insertion into the Customizer pane.
	 *
	 * @since 4.1.0
	 *
	 * @return string Contents of the control.
	 */
	final public function get_content() {
		ob_start();
		$this->maybe_render();
		return trim( ob_get_clean() );
	}

	/**
	 * Check capabilities and render the control.
	 *
	 * @since 3.4.0
	 * @uses WP_Customize_Control::render()
	 */
	final public function maybe_render() {
		if ( ! $this->check_capabilities() )
			return;

		/**
		 * Fires just before the current Customizer control is rendered.
		 *
		 * @since 3.4.0
		 *
		 * @param WP_Customize_Control $this WP_Customize_Control instance.
		 */
		do_action( 'customize_render_control', $this );

		/**
		 * Fires just before a specific Customizer control is rendered.
		 *
		 * The dynamic portion of the hook name, `$this->id`, refers to
		 * the control ID.
		 *
		 * @since 3.4.0
		 *
		 * @param WP_Customize_Control $this WP_Customize_Control instance.
		 */
		do_action( "customize_render_control_{$this->id}", $this );

		$this->render();
	}

	/**
	 * Renders the control wrapper and calls $this->render_content() for the internals.
	 *
	 * @since 3.4.0
	 */
	protected function render() {
		$id    = 'customize-control-' . str_replace( array( '[', ']' ), array( '-', '' ), $this->id );
		$class = 'customize-control customize-control-' . $this->type;

		?><li id="<?php echo esc_attr( $id ); ?>" class="<?php echo esc_attr( $class ); ?>">
			<?php $this->render_content(); ?>
		</li><?php
	}

	/**
	 * Get the data link attribute for a setting.
	 *
	 * @since 3.4.0
	 *
	 * @param string $setting_key
	 * @return string Data link parameter, if $setting_key is a valid setting, empty string otherwise.
	 */
	public function get_link( $setting_key = 'default' ) {
		if ( ! isset( $this->settings[ $setting_key ] ) )
			return '';

		return 'data-customize-setting-link="' . esc_attr( $this->settings[ $setting_key ]->id ) . '"';
	}

	/**
	 * Render the data link attribute for the control's input element.
	 *
	 * @since 3.4.0
	 * @uses WP_Customize_Control::get_link()
	 *
	 * @param string $setting_key
	 */
	public function link( $setting_key = 'default' ) {
		echo $this->get_link( $setting_key );
	}

	/**
	 * Render the custom attributes for the control's input element.
	 *
	 * @since 4.0.0
	 */
	public function input_attrs() {
		foreach ( $this->input_attrs as $attr => $value ) {
			echo $attr . '="' . esc_attr( $value ) . '" ';
		}
	}

	/**
	 * Render the control's content.
	 *
	 * Allows the content to be overridden without having to rewrite the wrapper in `$this::render()`.
	 *
	 * Supports basic input types `text`, `checkbox`, `textarea`, `radio`, `select` and `dropdown-pages`.
	 * Additional input types such as `email`, `url`, `number`, `hidden` and `date` are supported implicitly.
	 *
	 * Control content can alternately be rendered in JS. See WP_Customize_Control::print_template().
	 *
	 * @since 3.4.0
	 */
	protected function render_content() {
		switch( $this->type ) {
			case 'checkbox':
				?>
				<label>
					<input type="checkbox" value="<?php echo esc_attr( $this->value() ); ?>" <?php $this->link(); checked( $this->value() ); ?> />
					<?php echo esc_html( $this->label ); ?>
					<?php if ( ! empty( $this->description ) ) : ?>
						<span class="description customize-control-description"><?php echo $this->description; ?></span>
					<?php endif; ?>
				</label>
				<?php
				break;
			case 'radio':
				if ( empty( $this->choices ) )
					return;

				$name = '_customize-radio-' . $this->id;

				if ( ! empty( $this->label ) ) : ?>
					<span class="customize-control-title"><?php echo esc_html( $this->label ); ?></span>
				<?php endif;
				if ( ! empty( $this->description ) ) : ?>
					<span class="description customize-control-description"><?php echo $this->description ; ?></span>
				<?php endif;

				foreach ( $this->choices as $value => $label ) :
					?>
					<label>
						<input type="radio" value="<?php echo esc_attr( $value ); ?>" name="<?php echo esc_attr( $name ); ?>" <?php $this->link(); checked( $this->value(), $value ); ?> />
						<?php echo esc_html( $label ); ?><br/>
					</label>
					<?php
				endforeach;
				break;
			case 'select':
				if ( empty( $this->choices ) )
					return;

				?>
				<label>
					<?php if ( ! empty( $this->label ) ) : ?>
						<span class="customize-control-title"><?php echo esc_html( $this->label ); ?></span>
					<?php endif;
					if ( ! empty( $this->description ) ) : ?>
						<span class="description customize-control-description"><?php echo $this->description; ?></span>
					<?php endif; ?>

					<select <?php $this->link(); ?>>
						<?php
						foreach ( $this->choices as $value => $label )
							echo '<option value="' . esc_attr( $value ) . '"' . selected( $this->value(), $value, false ) . '>' . $label . '</option>';
						?>
					</select>
				</label>
				<?php
				break;
			case 'textarea':
				?>
				<label>
					<?php if ( ! empty( $this->label ) ) : ?>
						<span class="customize-control-title"><?php echo esc_html( $this->label ); ?></span>
					<?php endif;
					if ( ! empty( $this->description ) ) : ?>
						<span class="description customize-control-description"><?php echo $this->description; ?></span>
					<?php endif; ?>
					<textarea rows="5" <?php $this->input_attrs(); ?> <?php $this->link(); ?>><?php echo esc_textarea( $this->value() ); ?></textarea>
				</label>
				<?php
				break;
			case 'dropdown-pages':
				?>
				<label>
				<?php if ( ! empty( $this->label ) ) : ?>
					<span class="customize-control-title"><?php echo esc_html( $this->label ); ?></span>
				<?php endif;
				if ( ! empty( $this->description ) ) : ?>
					<span class="description customize-control-description"><?php echo $this->description; ?></span>
				<?php endif; ?>

				<?php
				$dropdown_name = '_customize-dropdown-pages-' . $this->id;
				$show_option_none = __( '&mdash; Select &mdash;' );
				$option_none_value = '0';
				$dropdown = wp_dropdown_pages(
					array(
						'name'              => $dropdown_name,
						'echo'              