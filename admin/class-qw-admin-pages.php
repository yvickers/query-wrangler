<?php

class QW_Admin_Pages {
	/**
	 * QW_Settings object
	 *
	 * @var QW_Settings
	 */
	protected $settings;

	/**
	 * WPDB object
	 *
	 * @var WPDB
	 */
	protected $wpdb;

	/**
	 * Admin page hooks created by this class
	 *
	 * @var array
	 */
	public $pages = array();

	public $base_uri = 'admin.php?page=query-wrangler';
	public $base_url;

	function __construct( $settings, $wpdb ){
		$this->settings = $settings;
		$this->wpdb = $wpdb;

		$this->db_table = $wpdb->prefix . 'query_wrangler';
		$this->base_url = admin_url( $this->base_uri );
	}

	/**
	 * Register class with WordPress
	 *
	 * @param $settings
	 */
	static public function register( $settings, $wpdb ){
		$plugin = new self( $settings, $wpdb );

		add_action( 'admin_menu', array( $plugin, 'admin_menu' ), 9999 );
		add_action( 'admin_enqueue_scripts', array( $plugin, 'admin_enqueue_scripts' ) );
	}

	/**
	 * Provide menu items and gather their hook names
	 */
	function admin_menu(){
		global $menu;
		// get the first available menu placement around 30, trivial, I know
		$menu_placement = 1000;
		for ( $i = 30; $i < 100; $i ++ ) {
			if ( ! isset( $menu[ $i ] ) ) {
				$menu_placement = $i;
				break;
			}
		}

		$this->pages['list'] = add_menu_page( __( 'Query Wrangler' ),
				__( 'Query Wrangler' ),
				'manage_options',
				'query-wrangler',
				array( $this, 'list_page' ),
				'',
				$menu_placement );

		// hidden with css
		$this->pages['actions'] = add_submenu_page( 'query-wrangler',
				__( 'Actions Router' ),
				__( 'Actions Router' ),
				'manage_options',
				'query-wrangler.actions',
				array( $this, 'actions_router' ) );

		// hidden with css
		$this->pages['edit'] = add_submenu_page( 'query-wrangler',
				__( 'Edit Query' ),
				__( 'Edit Query' ),
				'manage_options',
				'query-wrangler.edit',
				array( $this, 'edit_page' ) );

		// hidden with css
		$this->pages['export'] = add_submenu_page( 'query-wrangler',
				__( 'Export' ),
				__( 'Export' ),
				'manage_options',
				'query-wrangler.export',
				array( $this, 'export_page' ) );

		$this->pages['create'] = add_submenu_page( 'query-wrangler',
				__( 'Create New Query' ),
				__( 'Add New' ),
				'manage_options',
				'query-wrangler.create',
				array( $this, 'create_page' ) );

		$this->pages['import'] = add_submenu_page( 'query-wrangler',
				__( 'Import' ),
				__( 'Import' ),
				'manage_options',
				'query-wrangler.import',
				array( $this, 'import_page' ) );

		$this->pages['settings'] = add_submenu_page( 'query-wrangler',
				__( 'Settings' ),
				__( 'Settings' ),
				'manage_options',
				'query-wrangler.settings',
				array( $this, 'settings_page' ) );
	}

	/**
	 * Enqueue scripts and styles
	 *
	 * @param $hook
	 */
	function admin_enqueue_scripts( $hook ){
		// global styles
		wp_enqueue_style( 'qw-global',
				QW_PLUGIN_URL . '/admin/css/global.css',
				array(),
				QW_VERSION);

		if ( in_array( $hook, $this->pages ) ){
			$flip = array_flip( $this->pages );

			// qw generic styles
			wp_enqueue_style( 'qw-admin',
					QW_PLUGIN_URL . '/admin/css/query-wrangler.css',
					array(),
					QW_VERSION);

			// list page
			if ( $flip[ $hook ] == 'list' ){
				wp_enqueue_script( 'qw-admin-list-js',
						plugins_url( '/admin/js/query-wrangler-list.js', dirname( __FILE__ ) ),
						array(),
						QW_VERSION,
						TRUE );
			}

			// edit page
			if ( $flip[ $hook ] == 'edit' ) {
				$dir = QW_PLUGIN_URL . '/admin';

				// jquery ui rom cdn
				// @todo - non-cdn version? anything available in wp core?
				global $wp_scripts;
				$ui = $wp_scripts->query( 'jquery-ui-core' );
				wp_enqueue_style( 'jquery-ui-smoothness',
						"//ajax.googleapis.com/ajax/libs/jqueryui/{$ui->ver}/themes/smoothness/jquery-ui.min.css",
						FALSE,
						NULL );

				wp_enqueue_style( 'qw-admin-edit',
						"{$dir}/css/query-wrangler-views.css",
						array(),
						QW_VERSION );

				// js
				wp_enqueue_script( 'qw-unserialize-form',
						"{$dir}/js/jquery.unserialize-form.js",
						array(),
						QW_VERSION,
						TRUE );

				wp_enqueue_script( 'qw-admin-js',
						"$dir/js/query-wrangler.js",
						array(
							'jquery',
							'jquery-ui-core',
							'jquery-ui-accordion',
							'jquery-ui-autocomplete',
							'jquery-ui-dialog',
							'jquery-ui-sortable',
							'qw-unserialize-form',
						),
						QW_VERSION,
						TRUE );

				wp_enqueue_script( 'qw-edit-theme-views',
						"{$dir}/js/query-wrangler-views.js",
						array( 'qw-admin-js' ),
						QW_VERSION,
						TRUE );
			}
		}
	}

	/**
	 * Routing for admin page actions
	 */
	function actions_router(){
		$query_id = $this->get_current_query_id();
		$action = !empty( $_GET['action'] ) ? $_GET['action'] : null;

		if ( $action ) {
			switch ( $action ) {
				case 'update':
					if ( $query_id && !empty( $_POST[ QW_FORM_PREFIX ] ) ){
						$this->update_query( $query_id, $_POST[ QW_FORM_PREFIX ] );
						$this->redirect( 'query-wrangler.edit', $query_id );
					}
					break;

				case 'delete':
					if ( $query_id ) {
						$this->delete_query( $query_id );
						$this->redirect();
					}
					break;

				case 'create':
					if ( !empty( $_POST[ QW_FORM_PREFIX ] ) ) {
						$new_query_id = $this->create_query( $_POST[ QW_FORM_PREFIX ] );
						$this->redirect( 'query-wrangler.edit', $new_query_id );
					}
					break;

				case 'import':
					if ( !empty( $_POST[ QW_FORM_PREFIX ] ) ) {
						$new_query_id = $this->import_query( $_POST[ QW_FORM_PREFIX ] );
						$this->redirect( 'query-wrangler.edit', $new_query_id );
					}
					break;

				case 'save_settings':
					if ( !empty( $_POST[ QW_FORM_PREFIX ] ) ) {
						$this->settings_save( $_POST[ QW_FORM_PREFIX ] );
						$this->redirect( 'query-wrangler.settings' );
					}
					break;
			}
		}

		exit;
	}

	/**
	 * List - Page
	 */
	function list_page() {
		include_once QW_PLUGIN_DIR . '/admin/templates/page-query-list.php';

		$list_table = new Query_Wrangler_List_Table( $this );
		$list_table->do_the_deal();
	}

	/**
	 * Delete - delete a query
	 *
	 * @param $query_id
	 */
	function delete_query( $query_id ){
		if ( !is_numeric( $query_id ) ) return;

		$this->wpdb->delete( $this->db_table, array( 'id' => absint( $query_id ) ) );

		do_action( 'qw_delete_query', $query_id );

		// @todo - move this somewhere that subscribes to the action
		$table = $this->wpdb->prefix . "query_override_terms";
		$this->wpdb->delete( $table, array( 'query_id' => $query_id ) );
	}

	/**
	 * Settings - Page
	 */
	function settings_page() {
		$form = new QW_Form_Fields( array(
			'action' => $this->base_url . '.actions&noheader=true&action=save_settings',
			'form_field_prefix' => QW_FORM_PREFIX,
			'id' => 'qw-edit-settings',
			'form_style' => 'settings_table',
		) );

		print $this->template( 'page-settings', array(
			'form' => $form,
			'settings' => $this->settings,
		) );
	}

	/**
	 * Settings - Save
	 *
	 * @param $new
	 */
	function settings_save( $new ){
		$settings = $this->settings;
		$settings->set( 'widget_theme_compat', (int) !empty( $new['widget_theme_compat'] ) );
		$settings->set( 'live_preview',        (int) !empty( $new['live_preview'] ) );
		$settings->set( 'show_silent_meta',    (int) !empty( $new['show_silent_meta'] ) );
		$settings->set( 'shortcode_compat',    (int) !empty( $new['shortcode_compat'] ) );
		$settings->set( 'meta_value_field_handler', absint( $new['meta_value_field_handler'] ) );
		$settings->save();
	}

	/**
	 * Create - Page
	 */
	function create_page() {

		$form = new QW_Form_Fields( array(
			'action' => $this->base_url . '.actions&noheader=true&action=create',
			'form_field_prefix' => QW_FORM_PREFIX,
		) );

		print $this->template( 'page-query-create', array(
			'form' => $form,
		) );
	}

	/**
	 * Create - Insert new query
	 *
	 * @param $new
	 *
	 * @return int New Query ID
	 * @internal param $post - $_POST data
	 */
	function create_query( $new ) {
		$values = array(
			'name' => sanitize_text_field( $new['name'] ),
			'slug' => sanitize_title( $new['name'] ),
			'type' => sanitize_text_field( $new['type'] ),
			'path' => NULL,
			'data' => qw_serialize( qw_default_query_data() ),
		);

		$this->wpdb->insert( $this->db_table, $values );

		return $this->wpdb->insert_id;
	}

	/**
	 * Export - Page
	 */
	function export_page() {
		$query_id = $this->get_current_query_id();

		if ( $query_id ) {
			$qw_query = qw_get_query( $query_id );

			print $this->template( 'page-query-export', array(
				'form' => new QW_Form_Fields(),
				'query_name' => $qw_query->name,
				'exported_query' => $this->export_query( $qw_query->id )
			) );
		}
	}

	/**
	 * Export - Get query data as php code
	 *
	 * @param $query_id - the query's id number
	 * @return string
	 */
	function export_query( $query_id ) {
		$query = qw_get_query( $query_id );
		$row = ( array ) $query->row;
		unset( $row['id'] );

		return "\$query = " . var_export( $row, 1 ) . ";";
	}

	/**
	 * Import - Page
	 */
	function import_page() {
		$form = new QW_Form_Fields( array(
			'action' => $this->base_url . '.actions&noheader=true&action=import',
			'form_field_prefix' => QW_FORM_PREFIX,
		) );

		print $this->template( 'page-query-import', array(
			'form' => $form,
		) );
	}

	/**
	 * Import - query into the database
	 *
	 * @param $import
	 *
	 * @return int
	 * @internal param $post
	 */
	function import_query( $import ) {
		$query = null;

		if ( !empty( $import['query'] ) ){
			eval( stripslashes( $import['query'] ) );
		}

		if ( !empty( $query ) ) {
			$query['name'] = ! empty( $import['name'] ) ? sanitize_text_field( $import['name'] ) : 'No Name';
			$query['slug'] = sanitize_title( $query['name'] );
			$query['data'] = qw_serialize( $query['data'] );

			$this->wpdb->insert( $this->db_table, $query );

			return $this->wpdb->insert_id;
		}
	}

	/**
	 * Edit - Page
	 */
	function edit_page(){
		$query_id = $this->get_current_query_id();
		if ( !$query_id ) return;

		$qw_query = qw_get_query( $query_id );
		if ( !$qw_query ) return;

		$args = $this->edit_page_args( $qw_query );
		$args['editor']       = $this->template( 'form-editor', $args );
		$args['live_preview'] = $this->settings->get('live_preview');

		print $this->template( 'page-query-edit', $args );
	}

	/**
	 * Edit - Arguments
	 *
	 * @param $qw_query
	 *
	 * @return array
	 */
	function edit_page_args( $qw_query ) {
		$options = $qw_query->row->data;
		$display = isset( $options['display'] ) ? array_map( 'stripslashes_deep', $options['display'] ) : array();

		// preprocess existing handlers
		$handlers = qw_get_query_handlers( $options );
		$handlers = $this->make_handler_wrapper_forms( $handlers, $options );

		// start building edit page data
		$editor_args = array(
			'form_action'         => admin_url( "admin.php?page=query-wrangler.actions&action=update&query_id={$qw_query->id}&noheader=true" ),

			'query_id'            => $qw_query->id,
			'query_slug'          => $qw_query->slug,
			'query_name'          => $qw_query->name,
			'query_type'          => $qw_query->type,
			'shortcode'           => '[query slug="' . $qw_query->slug . '"]',
			'options'             => $options,
			'args'                => $options['args'],
			'display'             => $display,
			'basics'              => qw_all_basic_settings(),
			'filters'             => $handlers['filter']['items'],
			'fields'              => $handlers['field']['items'],
			'sorts'               => $handlers['sort']['items'],
			'overrides'           => $handlers['override']['items'],

			'post_statuses'       => qw_all_post_statuses(),
			'styles'              => qw_all_styles(),
			'row_styles'          => qw_all_row_styles(),
			'row_complete_styles' => qw_all_row_complete_styles(),
			'page_templates'      => get_page_templates(),
			'post_types'          => qw_all_post_types(),
			'pager_types'         => qw_all_pager_types(),
			'all_overrides'       => qw_all_overrides(),
			'all_filters'         => qw_all_filters(),
			'all_fields'          => qw_all_fields(),
			'all_sorts'           => qw_all_sort_options(),
			'image_sizes'         => get_intermediate_image_sizes(),
			'file_styles'         => qw_all_file_styles(),
		);

		// shortcode compatibility
		if ( $this->settings->get('shortcode_compat') ){
			$editor_args['shortcode'] = '[qw_query slug="' . $qw_query->slug . '"]';
		}

		return $editor_args;
	}

	/**
	 * Update - existing query
	 *
	 * @param $query_id
	 * @param $options
	 */
	function update_query( $query_id, $options ) {
		$qw_query = qw_get_query( $query_id );
		if ( ! $qw_query ){
			return;
		}

		/*
		 * Hook to allow alterations before saving
		 */
		$options = apply_filters( 'qw_pre_save', $options, $query_id );
		$options = array_map( 'stripslashes_deep', $options );

		$data = array(
			'data' => qw_serialize( $options )
		);

		// update for pages
		if ( $qw_query->type == 'page' ) {
			$data['path'] = !empty( $options['display']['page']['path'] ) ? ltrim( $options['display']['page']['path'], '/' ) : '';
		}

		$this->wpdb->update( $this->db_table, $data, array( 'id' => $query_id ) );
	}

	/**
	 *
	 * @param $handlers
	 * @param $options
	 *
	 * @return mixed
	 */
	function make_handler_wrapper_forms( $handlers, $options ){
		$display     = array_map( 'stripslashes_deep', $options['display'] );
		$image_sizes = get_intermediate_image_sizes();
		$file_styles = qw_all_file_styles();

		// filters
		if ( !empty( $handlers['filter']['items'] ) ) {
			foreach ( $handlers['filter']['items'] as $k => &$filter ) {
				$args = array(
						'filter' => $filter,
						'weight' => $filter['weight'],
				);
				$filter['wrapper_form'] = $this->template( 'handler-filter', $args );
			}
		}

		// sorts
		if ( !empty( $handlers['sort']['items'] ) ) {
			foreach ( $handlers['sort']['items'] as $k => &$sort ) {
				$args = array(
						'sort'   => $sort,
						'weight' => $sort['weight'],
				);
				$sort['wrapper_form'] = $this->template( 'handler-sort', $args );
			}
		}

		// fields
		if ( !empty( $handlers['field']['items'] ) ) {
			$tokens = array();
			foreach ( $handlers['field']['items'] as $k => &$field ) {
				$tokens[ $field['name'] ] = '{{' . $field['name'] . '}}';
				$args = array(
						'image_sizes' => $image_sizes,
						'file_styles' => $file_styles,
						'field'       => $field,
						'weight'      => $field['weight'],
						'options'     => $options,
						'display'     => $display,
						'tokens'      => $tokens,
				);
				$field['wrapper_form'] = $this->template( 'handler-field', $args );
			}
		}

		// overrides
		if ( !empty( $handlers['override']['items'] ) ) {
			foreach ( $handlers['override']['items'] as $k => &$override ) {
				$args = array(
						'override' => $override,
						'weight'   => $override['weight'],
				);
				$override['wrapper_form'] = $this->template( 'handler-override', $args );
			}
		}

		return $handlers;
	}

	/**
	 * Get the current query being edited
	 *
	 * @return int|false
	 */
	function get_current_query_id() {
		$screen = get_current_screen();
		$flip = array_flip( $this->pages );

		// ensure we're on one of our pages
		if ( !empty( $flip[ $screen->base ] ) && !empty( $_GET['query_id'] ) ) {
			return absint( $_GET['query_id'] );
		}

		return FALSE;
	}

	/**
	 * Redirect helper
	 *
	 * @param string $page
	 * @param null $query_id
	 */
	function redirect( $page = 'query-wrangler', $query_id = NULL ) {
		$url = admin_url( "admin.php?page={$page}" );

		if ( $query_id ) {
			$url .= "&query_id=" . $query_id;
		}

		wp_redirect( $url );
		exit();
	}

	/**
	 * Simple template function for admin stuff
	 *
	 * @param $__template_name
	 * @param array $__args
	 *
	 * @return string
	 */
	function template( $__template_name, $__args = array() ){
		$__template_file = QW_PLUGIN_DIR . "/admin/templates/{$__template_name}.php";

		if ( file_exists( $__template_file ) ){
			ob_start();
			extract( $__args );
			include $__template_file;
			return ob_get_clean();
		}

		return '';
	}
}