<?php
/*
Plugin Name: PageLines Developer
Author: Evan Mattson
Description: A plugin for pagelines developers developers developers
Version: 1.0
PageLines: true
*/


class PageLinesDeveloper {

	const version = '1.0';

	private $toggles;

	function __construct() {

		$this->slug  = basename( dirname( __FILE__ ) );
		$this->_slug = str_replace( '-', '_', $this->slug );
		$this->name  = "PageLines Developer";
		$this->path  = sprintf( '%s/%s', WP_PLUGIN_DIR, $this->slug );
		$this->uri   = plugins_url( $this->slug );

		$this->toggles = array(
			'PL_DEV',
			'PL_LESS_DEV'
		);

		$this->supports['ui'] = version_compare( $GLOBALS['wp_version'], '3.5', '>=' );

		$this->persistent();

		if ( is_admin() )
			$this->admin_actions();
		else
			$this->front_actions();
	} // __construct

/*
	add_action( '',	array(&$this, '') );
	add_filter( '',	array(&$this, '') );
*/
	// actions that always run
	function persistent() {

		$this->init_constants();

		// core wp-admin-bar menus are added here
		add_action( 'init',	array(&$this, 'init') );

		// pagelines admin menus
		add_action( 'admin_bar_menu', array(&$this, 'modify_pagelines_menus'), 110 );

		if ( $this->supports['ui'] )
			add_action( 'wp_after_admin_bar_render', array(&$this, 'print_modal') );

	}
	// admin only
	function admin_actions() {
		add_action( 'pagelines_setup',			array(&$this, 'register_less') );
		add_action( 'admin_enqueue_scripts',	array(&$this, 'global_enqueue') );
	}
	// front only
	function front_actions() {
		add_action( 'wp_enqueue_scripts',		array(&$this, 'global_enqueue') );
	}

	##########################################################################################################
	
	function init_constants() {
		$const = array();
		foreach ( $this->toggles as $c ) {
			$defined = defined( $c );
			$const[ $c ] = array(
				'defined' => $defined,
			);
			if ( $defined )
				$const[ $c ]['value'] = constant( $c );
		}
		$this->const = $const;
		//ddprint( $const, 'initial' );

		$this->process_toggle();
		$this->dynamic_define();
	}

	function process_toggle() {

		$keys = array(
			'const'  => '',
			'action' => '',
			'nonce'  => '',
		);
		$keys = array_filter( shortcode_atts( $keys, $_GET ) );

		if ( count( $keys ) < 3 )
			return;
		else
			extract( $keys );

		// verify
		if ( ! wp_verify_nonce( $nonce, "$const|$action" ) || !in_array( $const, $this->toggles) )
			return;

		$this->set_toggle_setting( $const, $action );
	}

	function dynamic_define() {
		foreach ( $this->const as $const => $c ) {
			if ( $c['defined'] )
				continue;

			$saved = $this->get_toggle_setting( $const );
			if ( !is_null( $saved ) )
				define( $const, $saved );
		}
	}

	function init() {
		remove_action( 'admin_bar_menu', 'wp_admin_bar_wp_menu', 10 );
		add_action( 'admin_bar_menu', array(&$this, 'add_new_root_menu'), 10 );
	}
	function register_less() {
		if ( function_exists('register_lessdev_dir') )
			register_lessdev_dir( 'aaemnnosttv', $this->slug, $this->name, $this->path.'/styles' );
	}

	/**
	 * Enqueue Styles for both admin & FE
	 */
	function global_enqueue() {
		$suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';
		$suffix = '';
		wp_enqueue_style( $this->slug, "{$this->uri}/styles/pagelines-developer$suffix.css", null, self::version );

		if ( $this->supports['ui'] ) {
			wp_enqueue_script( 'jquery-ui-dialog' );
			wp_enqueue_script( $this->slug, "{$this->uri}/js/pagelines-developer.js", array('jquery-ui-dialog') );
		}
	}




	function get_pl_constants() {
		$known = array(
			'LESS_FILE_MODE',
			'DYNAMIC_FILE_URL',
		);

		$found = $this->discover_constants();

		return array_unique( array_merge( $known, $found ) );
	}

	function get_sorted_constants() {
		$constants = $this->get_pl_constants();
		asort( $constants );
		$all = array(
			'url'  => array(),
			'dir'  => array(),
			'file' => array(),
			'page' => array(),
			'misc' => array(),
		);

		foreach ( $constants as $c ) {
			$cat = 'misc';
			$value = constant( $c );

			if ( 0 === strpos($value, WP_CONTENT_DIR) ) {

				if ( is_file( $value ) )
					$cat = 'file';
				else
					$cat = 'dir';
			}
			elseif ( 0 === strpos($value, 'http') )
				$cat = 'url';
			elseif ( 0 === strpos($value, 'admin.php') )
				$cat = 'page';


			$all[ $cat ][ $c ] = $value;
		}
		//ddprint( WP_CONTENT_DIR );
		//ddprint( $all );
		return $all;
	}

	function do_pl_constant_rows() {
		foreach ( $this->get_sorted_constants() as $cat => $consts ) {
			
			printf('<tr class="const-cat-title">
					<td colspan="2">%s</td>
			</tr>', $cat );

			foreach ( $consts as $const => $value )
				printf('<tr class="const-data"><td class="const-name">%s</td><td class="const-value">%s</td></tr>', $const, $value );
			?>
			<?php
		}
	}

	function discover_constants() {
		$all = get_defined_constants(true);

		$pl_const = array();
		foreach ( $all['user'] as $name => $value )
			if ( $this->maybe_pl_const( $name ) )
				$pl_const[] = $name;

		return $pl_const;
	}

	function maybe_pl_const( $c ) {
		return ( 0 === strpos($c, 'PL_') || 0 === strpos($c, 'PAGELINES_') );
	}

	function add_new_root_menu() {
		global $wp_admin_bar, $wp_version;


		/**
		 * TOP LEVEL
		 */
		$wp_admin_bar->add_menu( array(
			'id'    => $this->slug,
			'title' => '<i class="pldicon-pagelines icon-large"></i> '. PL_CORE_VERSION,
			'href'  => '#'
		)	);

		/**
		 * Kids
		 * ####################################
		 */
		

		// WP Logo / Version
		$this->child_menu( array(
			'id'    => 'wp-logo',
			'title' => '<span class="ab-icon"></span> &nbsp;&nbsp;'. $wp_version,
		)	);

			// Funciton Reference
			$this->child_menu( array(
				'id'    => 'wp-codex',
				'title' => $this->get_external_link_text('Function Reference'),
				'href'  => 'http://codex.wordpress.org/Function_Reference/',
				'meta'  => array( 'target' => '_blank', ),
			), 'wp-logo' );

			// Action Reference
			$this->child_menu( array(
				'id'    => 'wp-plugin-api',
				'title' => $this->get_external_link_text('Action Reference'),
				'href'  => 'http://codex.wordpress.org/Plugin_API/Action_Reference',
				'meta'  => array( 'target' => '_blank', ),
			), 'wp-logo' );
			#########################
	
		// CHEAT SHEET
		$this->child_menu(
			array(
				'id'    => 'cheat_sheet_link',
				'title' => $this->get_external_link_text('Cheat Sheet'),
				'href'  => 'http://demo.pagelines.me/cheat-sheet/',
				'meta'  => array( 'target' => '_blank', ),
		)	);


		// Icon Reference
		$this->child_menu(
			array(
				'id'    => 'font_awesome_link',
				'title' => $this->get_external_link_text('Icon Reference'),
				'href'  => 'http://fortawesome.github.io/Font-Awesome/icons/',
				'meta'  => array( 'target' => '_blank', ),
		) 	);

		// CONSTANTS
		if ( $this->supports['ui'] ) {
			$this->child_menu(
				array(
					'id'    => 'pl_constants',
					'title' => $this->get_icon_text( 'CONSTANTS', 'globe' ),
					'href'  => '#',
					//'meta' => array('class' => 'button insert-media add_media')
			) 	);
		}

		// Flush LESS
		$this->child_menu(
			array(
				'id'    => 'flush_less',
				'title' => $this->get_icon_text( __('Flush LESS', 'pagelines'), 'refresh' ),
				'href'  => add_query_arg( array( 'pl_reset_less' => 1 ) )
		) 	);


		// Constant Toggles
		foreach ( $this->const as $const => $c ) {
			if ( $c['defined'] )
				continue;

			$saved = $this->get_toggle_setting( $const );

			$action = $saved ? 'toggle-off' : 'toggle-on';
			$nonce = wp_create_nonce( "$const|$action" );

			$this->child_menu(
				array(
					'id'    => 'toggle_'.strtolower( $const ),
					'title' => $this->get_icon_text( $const, 'off' ),
					'href'  => add_query_arg( array(
						'const'  => $const,
						'action' => $action,
						'nonce'  => $nonce,
						) ),
					'meta' => array(
						'class' => $action,
						)
			) 	);
		}


	}

	function get_toggle_setting( $const ) {
		$d = array(
			'toggle' => array()
		);
		$setting = get_option( $this->slug );
		$setting = wp_parse_args( $setting, $d );
		return isset( $setting['toggle'][ $const ] ) ? $setting['toggle'][ $const ] : null;
	}
	private function set_toggle_setting( $const, $val ) {
		$d = array(
			'toggle' => array()
		);
		$setting = get_option( $this->slug );
		$setting = wp_parse_args( $setting, $d );

		$val = ('toggle-on' == $val) ? true : false;

		$setting['toggle'][ $const ] = intval( $val );
		update_option( $this->slug, $setting );
	}


	function get_icon_text( $text, $icon ) {
		return sprintf('<i class="pldicon-%s"></i>&nbsp;&nbsp;%s', $icon, $text );
	}

	function get_external_link_text( $text ) {
		return $this->get_icon_text( $text, 'external-link');
	}

	function child_menu( $args, $parent = false ) {
		global $wp_admin_bar;

		$parent = $parent ? $parent : $this->slug;

		// backwards parse
		$args = wp_parse_args( array('parent' => $parent), $args );

		$wp_admin_bar->add_menu( $args );
	}

	function modify_pagelines_menus() {
		global $wp_admin_bar;
		$wp_admin_bar->remove_menu('pl_flush');
	}

	function print_modal() {
		include('modal.php');
	}


} // PageLinesDeveloper

###########################################################
add_action( 'pagelines_hook_pre', 'init_PageLinesDeveloper' );
function init_PageLinesDeveloper() {
	if ( current_user_can('edit_theme_options') )
		new PageLinesDeveloper;
}
###########################################################
