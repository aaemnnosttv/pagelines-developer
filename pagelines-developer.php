<?php
/*
	Plugin Name: PageLines Developer
	Author: Evan Mattson
	Author URI: http://aaemnnost.tv
	Description: A plugin for pagelines developers developers developers
	Version: 1.0.5
*/


class PageLinesDeveloper
{
	const version = '1.0.4';
	private $toggles;

	function __construct()
	{
		$this->slug    = basename( dirname( __FILE__ ) );
		$this->_slug   = str_replace( '-', '_', $this->slug );
		$this->name    = "PageLines Developer";
		$this->path    = sprintf( '%s/%s', WP_PLUGIN_DIR, $this->slug );
		$this->uri     = plugins_url( $this->slug );
		$this->toggles = array(
			'PL_DEV',
			'PL_LESS_DEV'
		);

		$this->supports['ui'] = version_compare( $GLOBALS['wp_version'], '3.5', '>=' );

		if ( defined('DEMO_MODE') && DEMO_MODE )
			return;

		$this->init();
	} // __construct

/*
	add_action( '',	array(&$this, '') );
	add_filter( '',	array(&$this, '') );
*/
	function init()
	{
		// core wp-admin-bar menus are added here
		add_action( 'init',	array(&$this, 'init_display') );

		if ( $this->supports['ui'] )
		{
			$this->init_constants();
			add_action( 'wp_after_admin_bar_render', array(&$this, 'print_modal') );
		}
		
		add_action( 'pagelines_setup',			array(&$this, 'register_less') );
		add_action( 'admin_enqueue_scripts',	array(&$this, 'global_enqueue') );
		add_action( 'wp_enqueue_scripts',		array(&$this, 'global_enqueue') );
	}

	##########################################################################################################
	
	function init_constants()
	{
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

		$this->process_toggle();
		$this->dynamic_define();
	}

	function process_toggle()
	{
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

	function dynamic_define()
	{
		foreach ( $this->const as $const => $c )
		{
			if ( $c['defined'] )
				continue;

			$saved = $this->get_toggle_setting( $const );
			if ( !is_null( $saved ) )
				define( $const, $saved );
		}
	}

	function init_display()
	{
		$this->is_dms = class_exists( 'PageLinesTemplateHandler' );

		if ( ! $this->dev_user() )
			return;

		add_action( 'admin_bar_menu', array(&$this, 'admin_bar_menu'), 11 );
		// pagelines admin menus
		add_action( 'admin_bar_menu', array(&$this, 'modify_pagelines_menus'), 110 );
	}

	function dev_user()
	{
		// defined (single user and multi user)
		if ( $this->const_check('PAGELINES_DEVELOPER_LOCK') )
		{	
			// get current users info
			$user_data = wp_get_current_user();
			$user = $user_data->user_login;

			// explode the alowed users, if its a single name explode still returns an array.
			$users = explode( ',', PAGELINES_DEVELOPER_LOCK );
			
			// if current user is not in the array of allowed users return false.
			if( ! in_array( $user, $users ) )
				return false;
		}
		elseif ( !current_user_can('edit_theme_options') )
			return false;

		//	If we get this far either PAGELINES_DEVELOPER_LOCK is not defined or is not a string or the user is allowed so we just return true.
		return true;
	}

	function const_check( $c )
	{
		return (
			defined( $c )
			&& is_string( constant( $c ) )
			&& constant( $c )
		);
	}

	function register_less()
	{
		if ( function_exists('register_lessdev_dir') )
			register_lessdev_dir( 'aaemnnosttv', $this->slug, $this->name, $this->path.'/styles' );
	}

	/**
	 * Enqueue Styles for both admin & FE
	 */
	function global_enqueue()
	{
		//$suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';
		$suffix = '';
		wp_enqueue_style( $this->slug, "{$this->uri}/styles/pagelines-developer$suffix.css", null, self::version );

		if ( $this->supports['ui'] )
		{
			wp_enqueue_style ( 'media-views' );
			wp_enqueue_script( 'jquery-ui-dialog' );
			wp_enqueue_script( $this->slug, "{$this->uri}/js/pagelines-developer.js", array('jquery-ui-dialog') );
		}
	}

	function get_pl_constants()
	{
		$known = array(
			'LESS_FILE_MODE',
			'DYNAMIC_FILE_URL',
		);

		$found = $this->discover_constants();

		return array_unique( array_merge( $known, $found ) );
	}

	function get_sorted_constants()
	{
		$constants = $this->get_pl_constants();
		asort( $constants );
		$all = array(
			'url'  => array(),
			'dir'  => array(),
			'file' => array(),
			'page' => array(),
			'misc' => array(),
		);

		foreach ( $constants as $c )
		{
			$cat = 'misc';
			$value = defined( $c ) ? constant( $c ) : null;

			// (file|dir)
			if ( 0 === strpos($value, WP_CONTENT_DIR) )
			{
				if ( is_file( $value ) )
					$cat = 'file';
				else
					$cat = 'dir';
			}
			// url
			elseif ( preg_match('#^(http|//)#', $value) )
				$cat = 'url';
			// admin page
			elseif ( 0 === strpos($value, 'admin.php') )
				$cat = 'page';

			$all[ $cat ][ $c ] = $value;
		}

		return $all;
	}

	function do_pl_constant_rows()
	{
		foreach ( $this->get_sorted_constants() as $cat => $consts )
		{	
			printf('<tr class="const-cat-title">
						<td colspan="2">%s</td>
					</tr>', $cat );

			foreach ( $consts as $const => $value )
				printf('<tr class="const-data">
							<td class="const-name">%s</td>
							<td class="const-value">%s</td>
						</tr>', $const, $value );
		}
	}

	function discover_constants()
	{
		$all = get_defined_constants(true);
		$pl_const = array();

		foreach ( $all['user'] as $name => $value )
			if ( $this->maybe_pl_const( $name ) )
				$pl_const[] = $name;

		return $pl_const;
	}

	function maybe_pl_const( $c )
	{
		return ( preg_match('/^(PL_|PAGELINES_)/', $c) );
	}

	function admin_bar_menu( $wpab )
	{
		$this->modify_root_menu( $wpab );
		$this->add_child_menus( $wpab );
	}

	function modify_root_menu( $wpab )
	{
		global $wp_version;
		$wp_node = $wpab->get_node('wp-logo');

		$sp = '&nbsp;';
		$pl_version = ($this->is_dms ? "DMS$sp"  : '') . PL_CORE_VERSION;

		$title = $wp_node->title;
		// add wp version
		$title .= "$sp$sp<span class='version'><i class='leftarrow'></i>$wp_version</span>";
		$title = '<div class="wp ver-group">' . $title . '</div>';
		// pagelines
		$title .= "<div class='pl ver-group'><i class='pldicon-pagelines'></i>$sp<span class='version'><i class='leftarrow'></i>$pl_version</span></div>";

		// update wp-node
		$wp_node->title = $title;
		$wp_node->href = is_admin() ? admin_url() : home_url();
		$wpab->add_menu( $wp_node );

		// Changelog
		$this->child_menu(
			array(
				'id'    => 'pl-changelog',
				'title' => $this->get_external_link_text('Changelog'),
				'href'  => 'https://github.com/pagelines/DMS/blob/Dev/changelog.txt',
				'meta'  => array( 'target' => '_blank', ),
		), 'wp-logo' );
		// PL Group
		$wpab->add_group( array(
			'parent' => 'wp-logo',
			'id'     => 'pl-group',
			'meta'   => array(
				'class' => 'ab-sub-secondary pl',
			),
		) );

		// relocate about
		$about_node = $wpab->get_node('about');
		$about_node->parent = 'wp-logo-external';
		$wpab->remove_node('about');
		$wpab->add_node( $about_node );
		
		// add WP Reference
		// Funciton Reference
		$this->child_menu( array(
			'id'    => 'wp-codex',
			'title' => $this->get_external_link_text('Function Reference'),
			'href'  => 'http://codex.wordpress.org/Function_Reference/',
			'meta'  => array( 'target' => '_blank', ),
		), 'documentation' );

		// Action Reference
		$this->child_menu( array(
			'id'    => 'wp-plugin-api',
			'title' => $this->get_external_link_text('Action Reference'),
			'href'  => 'http://codex.wordpress.org/Plugin_API/Action_Reference',
			'meta'  => array( 'target' => '_blank', ),
		), 'documentation' );
	}

	function add_child_menus( $wpab )
	{
		// Launchpad
		$this->child_menu(
			array(
				'id'    => 'launchpad',
				'title' => $this->get_icon_text( 'Launchpad', 'pagelines' ),
				'href'  => 'https://www.pagelines.com/launchpad/member.php',
				'meta'  => array( 'target' => '_blank', ),
		)	);

		// PageLines Products (wp-admin)
		$this->child_menu(
			array(
				'id'    => 'pl-admin',
				'title' => $this->get_icon_text( 'Products Admin', 'pagelines' ),
				'href'  => 'http://www.pagelines.com/wp-admin/edit.php?post_type=product',
				'meta'  => array( 'target' => '_blank', ),
		)	);

		// PL Reference
		$this->child_menu(
			array(
				'id'    => 'pl-reference',
				'title' => $this->get_icon_text( 'Docs', 'pagelines' ),
				'href'  => 'http://docs.pagelines.com/developer/dms-option-engine',
				'meta'  => array( 'target' => '_blank', ),
		)	);

		// Github Docs
		$this->child_menu(
			array(
				'id'    => 'dms.io',
				'title' => $this->get_external_link_text('Docs'),
				'href'  => 'http://docs.pagelines.com/',
				'meta'  => array( 'target' => '_blank', ),
		), 'pl-reference' );

		// CHEAT SHEET
		$this->child_menu(
			array(
				'id'    => 'cheat_sheet_link',
				'title' => $this->get_external_link_text('Cheat Sheet'),
				'href'  => 'http://demo.pagelines.me/cheat-sheet/',
				'meta'  => array( 'target' => '_blank', ),
		), 'pl-reference' );

		// Icon Reference
		$this->child_menu(
			array(
				'id'    => 'font_awesome_link',
				'title' => $this->get_external_link_text('Icon Reference'),
				'href'  => 'http://fortawesome.github.io/Font-Awesome/icons/',
				'meta'  => array( 'target' => '_blank', ),
		), 'pl-reference' );

		// LESS Reference
		$this->child_menu(
			array(
				'id'    => 'less_docs_link',
				'title' => $this->get_external_link_text('LESS Docs'),
				'href'  => 'http://lesscss.org',
				'meta'  => array( 'target' => '_blank', ),
		), 'pl-reference' );

		// CONSTANTS
		if ( $this->supports['ui'] )
		{
			$this->child_menu(
				array(
					'id'    => 'pl_constants',
					'title' => $this->get_icon_text( 'CONSTANTS', 'globe' ),
					'href'  => '#',
			), 'pl-reference' );
		}

		// Flush LESS
		$this->child_menu(
			array(
				'id'    => 'flush_less',
				'title' => $this->get_icon_text( __('Flush LESS', 'pagelines'), 'refresh' ),
				'href'  => add_query_arg( array( 'pl_reset_less' => 1 ) )
		) 	);


		// Constant Toggles
		foreach ( $this->const as $const => $c ) :

			if ( $c['defined'] )
				continue;

			$saved  = $this->get_toggle_setting( $const );
			$action = $saved ? 'toggle-off' : 'toggle-on';
			$nonce  = wp_create_nonce( "$const|$action" );

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
						'title' => str_replace('-', ' ', $action)
					)
			) 	);

		endforeach;
	}

	function get_toggle_setting( $const )
	{
		$d = array(
			'toggle' => array()
		);
		$setting = get_option( $this->slug );
		$setting = wp_parse_args( $setting, $d );
		return isset( $setting['toggle'][ $const ] ) ? $setting['toggle'][ $const ] : null;
	}

	private function set_toggle_setting( $const, $val )
	{
		$d = array(
			'toggle' => array()
		);
		$setting = get_option( $this->slug );
		$setting = wp_parse_args( $setting, $d );

		$val = ('toggle-on' == $val) ? true : false;

		$setting['toggle'][ $const ] = intval( $val );
		update_option( $this->slug, $setting );
	}


	function get_icon_text( $text, $icon )
	{
		return sprintf('<i class="pldicon-%s"></i>&nbsp;&nbsp;%s', $icon, $text );
	}

	function get_external_link_text( $text )
	{
		return $this->get_icon_text( $text, 'external-link');
	}

	function child_menu( $args, $parent = false )
	{
		global $wp_admin_bar;

		$parent = $parent ? $parent : 'pl-group';

		// backwards parse
		$args = wp_parse_args( array('parent' => $parent), $args );

		$wp_admin_bar->add_menu( $args );
	}

	function modify_pagelines_menus( $wpab )
	{
		$wpab->remove_menu('pl_flush'); // relocated
	}

	function print_modal()
	{
		include('modal.php');
	}


} // PageLinesDeveloper

###########################################################
add_action( 'pagelines_hook_pre', 'init_PageLinesDeveloper' );
function init_PageLinesDeveloper()
{
	new PageLinesDeveloper;
}
###########################################################
