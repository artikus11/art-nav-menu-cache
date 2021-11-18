<?php
/*
Plugin Name: Art Nav Menu Cache
Plugin URI: http://URI_Of_Page_Describing_Plugin_and_Updates
Description: Плагин кеширования навигационного меню через транзиенты
Version: 1.0
Author: Artem Abramovich
Author URI: https://wpruse.ru
License: A "Slug" license name e.g. GPL2
*/

/**
 * @source https://generatewp.com/how-to-use-transients-to-speed-up-wordpress-menus/
 */
class Art_Nav_Menu_Cache {

	/**
	 * $cache_time
	 * transient expiration time
	 *
	 * @var int
	 */
	public $cache_time = WEEK_IN_SECONDS;


	public function init() {

		add_filter( 'pre_wp_nav_menu', [ $this, 'pre_wp_nav_menu' ], 10, 2 );

		add_filter( 'wp_nav_menu', [ $this, 'wp_nav_menu' ], 10, 2 );

		add_action( 'wp_update_nav_menu', [ $this, 'wp_update_nav_menu' ], 10, 1 );

	}


	protected function get_menu_object( $args ) {

		$locations = get_nav_menu_locations();
		$menu_obj  = false;

		if ( $args->theme_location ) {
			$menu_obj = wp_get_nav_menu_object( $locations[ $args->theme_location ] );
		}

		return $menu_obj;
	}


	protected function get_menu_parent( $args ) {

		$items   = wp_get_nav_menu_items( $this->get_menu_object( $args ) );
		$parents = [];

		if ( ! empty( $items ) ) {
			foreach ( $items as $item ) {
				if ( (int) $item->menu_item_parent === 0 ) {
					$parents[] = $item->object_id;
				}
			}
		}

		return array_map( 'intval', $parents );
	}


	/**
	 * get_menu_key
	 * Simple function to generate a unique id for the menu transient
	 * based on the menu arguments and currently requested page.
	 *
	 * @param  object $args An object containing wp_nav_menu() arguments.
	 *
	 * @return string
	 */
	protected function get_menu_key( $args ) {

		global $wp_query;

		$_queried_object_id = 0;
		$queried_object_id  = empty( $wp_query->queried_object_id ) ? 0 : (int) $wp_query->queried_object_id;

		if ( in_array( (int) $queried_object_id, $this->get_menu_parent( $args ), true ) ) {
			$_queried_object_id = $queried_object_id;

		}

		return sprintf( 'nav_menu_%s_%s_%d', $args->theme_location, md5( serialize( $this->get_menu_object( $args ) ) ), $_queried_object_id );
	}


	/**
	 * get_menu_transient
	 * Simple function to get the menu transient based on menu arguments
	 *
	 * @param  object $args An object containing wp_nav_menu() arguments.
	 *
	 * @return mixed            menu output if exists and valid else false.
	 */
	public function get_menu_transient( $args ) {

		$key = $this->get_menu_key( $args );

		return get_transient( $key );
	}


	/**
	 * pre_wp_nav_menu
	 *
	 * This is the magic filter that lets us short-circit the menu generation
	 * if we find it in the cache so anything other then null returend will skip the menu generation.
	 *
	 * @param  string|null $nav_menu Nav menu output to short-circuit with.
	 * @param  object      $args     An object containing wp_nav_menu() arguments
	 *
	 * @return string|null
	 */
	public function pre_wp_nav_menu( $nav_menu, $args ) {

		$in_cache = $this->get_menu_transient( $args );

		$last_updated = get_transient( 'MC-' . $args->theme_location . '-updated' );

		if ( isset( $in_cache['data'], $last_updated ) && $last_updated < $in_cache['time'] ) {
			return $in_cache['data'];
		}

		return $nav_menu;
	}


	/**
	 * wp_nav_menu
	 * store menu in cache
	 *
	 * @param  string $nav  The HTML content for the navigation menu.
	 * @param  object $args An object containing wp_nav_menu() arguments
	 *
	 * @return string           The HTML content for the navigation menu.
	 */
	public function wp_nav_menu( $nav, $args ) {

		$last_updated = get_transient( 'MC-' . $args->theme_location . '-updated' );

		if ( false === $last_updated ) {
			set_transient( 'MC-' . $args->theme_location . '-updated', time() );
		}

		$key  = $this->get_menu_key( $args );
		$data = [ 'time' => time(), 'data' => $nav ];

		set_transient( $key, $data, $this->cache_time );

		return $nav;
	}


	/**
	 * wp_update_nav_menu
	 * refresh time on update to force refresh of cache
	 *
	 * @param  int $menu_id
	 *
	 * @return void
	 */
	public function wp_update_nav_menu( $menu_id ) {

		$locations = array_flip( get_nav_menu_locations() );

		if ( isset( $locations[ $menu_id ] ) ) {
			set_transient( 'MC-' . $locations[ $menu_id ] . '-updated', time() );
		}
	}

}

( new Art_Nav_Menu_Cache() )->init();