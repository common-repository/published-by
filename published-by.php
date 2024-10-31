<?php
/**
 * Plugin Name: Published By
 * Version:     1.3
 * Plugin URI:  http://coffee2code.com/wp-plugins/published-by/
 * Author:      Scott Reilly
 * Author URI:  http://coffee2code.com/
 * Text Domain: published-by
 * License:     GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Description: Track which user actually published a post, separate from who created the post. Display that info as a column in admin post listings.
 *
 * Compatible with WordPress 4.6 through 4.9+.
 *
 * =>> Read the accompanying readme.txt file for instructions and documentation.
 * =>> Also, visit the plugin's homepage for additional information and updates.
 * =>> Or visit: https://wordpress.org/plugins/published-by/
 *
 * @package Published_By
 * @author  Scott Reilly
 * @version 1.3
 */

/*
 * TODO:
 * - Add template tags that parallel author-related template tags, such as:
 *   get_the_publisher, the_publisher, get_publisher_id, get_publisher_link
 * - Provisions to disable/enable per post_type
 * - Hook to allow defining custom logic for guessing publishing user for older
 *   posts.
 * - Introduce 'c2c_published-by_user_url' filter to permit customizing where
 *   the user's name links to.
 * - ...and/or provide a way to customize the link destination from among
 *   choices: profile, user_url, admin listing of posts (of this post type) by
 *   the user, front-end listing of posts (of this post type) by the user.
 * - Hover text for guessed publisher name should say it's a guess and based on
 *   what (i.e. last user to edit post, last revision to post, post author)
 * - For published-by filter dropdown, either omit the dropdown (or switch to
 *   text field) if the number of published-by users is excessive.
 * - Document filters in readme
 */

/*
	Copyright (c) 2014-2018 by Scott Reilly (aka coffee2code)

	This program is free software; you can redistribute it and/or
	modify it under the terms of the GNU General Public License
	as published by the Free Software Foundation; either version 2
	of the License, or (at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

defined( 'ABSPATH' ) or die();

if ( ! class_exists( 'c2c_PublishedBy' ) ) :

class c2c_PublishedBy {

	/**
	 * Name for meta key used to store id of publishing user.
	 *
	 * @access private
	 * @var string
	 */
	private static $meta_key = 'c2c-published-by';

	/**
	 * Field name for the post listing column.
	 *
	 * @access private
	 * @var string
	 */
	private static $field = 'published_by';

	/**
	 * List of post IDs for whom the previously invoked get_publisher_id()
	 * returned a guessed publisher value.
	 *
	 * @since 1.2
	 * @access private
	 * @var array
	 */
	private static $guessed_publisher = array();

	/**
	 * Returns version of the plugin.
	 *
	 * @since 1.0
	 */
	public static function version() {
		return '1.3';
	}

	/**
	 * Hooks actions and filters.
	 *
	 * @since 1.0
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'do_init' ) );
	}

	/**
	 * Performs initializations on the 'init' action.
	 *
	 * @since 1.0
	 */
	public static function do_init() {
		// Load textdomain
		load_plugin_textdomain( 'published-by' );

		// Register hooks
		add_filter( 'manage_posts_columns',        array( __CLASS__, 'add_post_column' )               );
		add_action( 'manage_posts_custom_column',  array( __CLASS__, 'handle_column_data' ),     10, 2 );
		add_filter( 'manage_pages_columns',        array( __CLASS__, 'add_post_column' )               );
		add_action( 'manage_pages_custom_column',  array( __CLASS__, 'handle_column_data' ),     10, 2 );

		add_action( 'load-edit.php',               array( __CLASS__, 'add_admin_css' )                 );
		add_action( 'load-post.php',               array( __CLASS__, 'add_admin_css' )                 );
		add_action( 'transition_post_status',      array( __CLASS__, 'transition_post_status' ), 10, 3 );
		add_action( 'post_submitbox_misc_actions', array( __CLASS__, 'show_publisher' )                );

		add_filter( 'parse_query',                 array( __CLASS__, 'filter_by_query' )               );
		add_action( 'restrict_manage_posts',       array( __CLASS__, 'filter_by_dropdown' )            );

		add_action( 'deleted_user',                array( __CLASS__, 'deleted_user' ), 10, 2           );

		self::register_meta();
	}

	/**
	 * Registers the post meta field.
	 *
	 * @since 1.2
	 */
	public static function register_meta() {
		register_meta( 'post', self::$meta_key, array(
			'type'              => 'integer',
			'description'       => __( 'The user who published the post', 'published-by' ),
			'single'            => true,
			'sanitize_callback' => 'absint',
			'auth_callback'     => '__return_false',
			'show_in_rest'      => true,
		) );
	}

	/**
	 * Removes record of publishing user when user is deleted, unless their
	 * ownership of things is being reassigned, in which case change the
	 * publisheding user to the reassigned user.
	 *
	 * @since 1.3
	 *
	 * @param int      $id       ID of the deleted user.
	 * @param int|null $reassign ID of the user to reassign posts and links to.
	 *                           Default null, for no reassignment.
	 */
	public static function deleted_user( $user_id, $reassign ) {
		if ( $reassign ) {
			global $wpdb;

			// Get IDs of posts that have the deleted user assigned as publishing user.
			$post_ids = $wpdb->get_col( $wpdb->prepare(
				"SELECT post_id FROM $wpdb->postmeta WHERE meta_key = %s AND meta_value = %s",
				self::$meta_key,
				$user_id
			) );

			// Reassign publishing user.
			$wpdb->update(
				$wpdb->postmeta,
				array( 'meta_value' => $reassign ),
				array( 'meta_key'   => self::$meta_key ),
				array( '%d' ),
				array( '%s' )
			);

			// Clear post_meta cache for affected posts.
			foreach ( $post_ids as $p_id ) {
				wp_cache_delete( $p_id, 'post_meta' );
			}
		} else {
			delete_metadata( 'post', null, self::$meta_key, $user_id, true );
		}
	}

	/**
	 * Returns the post statuses that should show the "Published By" column.
	 *
	 * @since 1.2
	 */
	public static function get_post_statuses() {
		return (array) apply_filters( 'c2c_published_by_post_status', array( 'private', 'publish' ) );
	}

	/**
	 * Determines if the Published By column should be shown.
	 *
	 * @since 1.0
	 *
	 * @return bool
	 */
	private static function include_column() {
		return ( ! isset( $_GET['post_status'] ) || 'all' === $_GET['post_status'] || in_array( $_GET['post_status'], self::get_post_statuses() ) );
	}

	/**
	 * Adds hook to outputs CSS for the display of the Published By column if
	 * on the appropriate admin page.
	 *
	 * @since 1.0
	 */
	public static function add_admin_css() {
		if ( ! self::include_column() ) {
			return;
		}

		add_action( 'admin_head', array( __CLASS__, 'admin_css' ) );
	}

	/**
	 * Outputs CSS for the display of the Published By column.
	 *
	 * @since 1.0
	 */
	public static function admin_css() {
		echo "<style type='text/css'>.fixed .column-" . self::$field . " {width:10%;}
			#c2c-published-by {font-weight:600;}
			#c2c-published-by a {color:#444;}
			.c2c-published-by-guess {font-style:italic;}
			.c2c-published-by-guess:after {content:'?';}
			</style>\n";
	}

	/**
	 * Displays the publisher of the post in the publish metabox.
	 *
	 * @since 1.0
	 */
	public static function show_publisher() {
		global $post;

		if ( ! in_array( $post->post_status, self::get_post_statuses() ) ) {
			return;
		}

		$publisher_id = self::get_publisher_id( $post->ID );

		$class = self::is_publisher_id_guessed( $post->ID ) ? 'c2c-published-by-guess' : '';

		if ( ! $publisher_id ) {
			return;
		}

		$publisher = get_userdata( $publisher_id );

		if ( get_current_user_id() === $publisher_id ) {
			$user_link = '<b class="' . $class . '">you</b>';
		} else {
			$user_link = sprintf(
				'<span id="c2c-published-by"><a href="%s" class="%s">%s</a></span>',
				esc_url( self::get_user_url( $publisher_id ) ),
				$class,
				sanitize_text_field( $publisher->display_name )
			);
		}

		echo '<div class="misc-pub-section curtime misc-pub-curtime">';
		printf( __( 'Published by: %s', 'published-by' ), $user_link );
		echo '</div>';
	}

	/**
	 * Returns the URL for the user.
	 *
	 * @since 1.2
	 *
	 * @param  int $user_id The user ID.
	 * @return string
	 */
	public static function get_user_url( $user_id ) {
		if ( (int) $user_id ) {
			return self_admin_url( 'user-edit.php?user_id=' . (int) $user_id );
		}
	}

	/**
	 * Adds a column to show who published the post/page.
	 *
	 * @since 1.0
	 *
	 * @param  array $posts_columns Array of post column titles.
	 *
	 * @return array The $posts_columns array with the 'published by' column's title added.
	 */
	public static function add_post_column( $posts_columns ) {
		if ( self::include_column() ) {
			$posts_columns[ self::$field ] = __( 'Published By', 'published-by' );
		}

		return $posts_columns;
	}

	/**
	 * Outputs the user who published the post for each post listed in the post
	 * listing table in the admin.
	 *
	 * @since 1.0
	 *
	 * @param string $column_name The name of the column.
	 * @param int    $post_id     The id of the post being displayed.
	 */
	public static function handle_column_data( $column_name, $post_id ) {
		if ( ! self::include_column() ) {
			return;
		}

		if ( self::$field === $column_name ) {
			$publisher_id = self::get_publisher_id( $post_id );
			$class = self::is_publisher_id_guessed( $post_id ) ? 'c2c-published-by-guess' : '';

			if ( $publisher_id ) {
				if ( get_current_user_id() === $publisher_id ) {
					$user_link = '<span class="' . $class . '">you</span>';
				} else {
					$publisher = get_userdata( $publisher_id );
					$user_link = sprintf(
						'<a href="%s" class="%s">%s</a>',
						esc_url( self::get_user_url( $publisher_id ) ),
						$class,
						sanitize_text_field( $publisher->display_name )
					);
				}
				echo $user_link;
			}
		}
	}

	/**
	 * Records the publisher of the post.
	 *
	 * @since 1.0
	 *
	 * @param string $new_status New post status.
	 * @param string $old_status Old post status.
	 */
	public static function transition_post_status( $new_status, $old_status, $post ) {
		// Only concerned with posts changing post status
		if ( $new_status == $old_status ) {
			return;
		}

		// Only concerned with posts being published
		if ( 'publish' !== $new_status ) {
			return;
		}

		// Can only save publishing user ID if one can be obtained
		if ( $current_user_id = get_current_user_id() ) {
			update_post_meta( $post->ID, self::$meta_key, $current_user_id );
		}
	}

	/**
	 * Determines if the publisher_id returned by get_pubsliher_id() was guessed
	 * or not.
	 *
	 * Note: presumes that `get_publisher_id()` was called for the given post_id
	 * before using this function.
	 *
	 * @since 1.2
	 *
	 * @param  int  $post_id The id of the post being displayed.
	 * @return bool True if the pubhlisher_id was guessed, false otherwise.
	 */
	public static function is_publisher_id_guessed( $post_id ) {
		return in_array( $post_id, self::$guessed_publisher );
	}

	/**
	 * Returns the ID of the user who published the post.
	 *
	 * @since 1.0
	 *
	 * @param  int $post_id The id of the post being displayed.
	 * @return int The ID of the user who published the post. Will be 0 if user is unknown.
	 */
	public static function get_publisher_id( $post_id ) {
		$publisher_id = 0;
		$post         = get_post( $post_id );

		if ( $post && in_array( get_post_status( $post_id ), self::get_post_statuses() ) ) {

			$post_id = $post->ID;

			do {

				// Use publisher id saved in custom field by the plugin.
				if ( $publisher_id = get_post_meta( $post_id, self::$meta_key, true ) ) {
					break;
				}

				// At this point, we're guessing who published the post.

				// Allow disabling of the various checks to guess who published the post.
				if ( apply_filters( 'c2c_published_by_skip_guessing', false, $post_id ) ) {
					break;
				}

				// Make note that the publisher of this post was a guess.
				if ( ! self::is_publisher_id_guessed( $post_id ) ) {
					self::$guessed_publisher[] = $post_id;
				}

				// Use the user WP saved as the latest editor
				// NOTE: get_the_modified_author() does not currently accept a
				// $post argument and therefore cannot be used directly as hoped.
				if ( $publisher_id = get_post_meta( $post_id, '_edit_last', true ) ) {
					break;
				}

				// Guess who it is based on latest revision.
				$post_revisions  = wp_get_post_revisions( $post_id );
				$latest_revision = array_shift( $post_revisions );
				if ( $latest_revision ) {
					$rev = wp_get_post_revision( $latest_revision );
					$publisher_id = $rev->post_author;
					break;
				}

				// If no publisher at this point, then assume post author
				$publisher_id = $post->post_author;
				break;

			} while ( 0 );

		}

		return (int) $publisher_id;
	}

	/**
	 * Displays a categories dropdown for filtering on the Posts list table.
	 *
	 * @since 1.3.0
	 *
	 * @param string $post_type Post type slug.
	 */
	public static function filter_by_dropdown( $post_type ) {
		global $wpdb;

		if ( ! self::include_column() ) {
			return;
		}

		/**
		 * Filters whether to remove the 'Published By' dropdown from the post list table.
		 *
		 * @since 1.3.0
		 *
		 * @param bool   $disable   Whether to disable the 'published by' dropdown. Default false.
		 * @param string $post_type Post type slug.
		 */
		if ( false !== apply_filters( 'c2c_published_by_disable_filter_dropdown', false, $post_type ) ) {
			return;
		}

		$published_by_sql = "SELECT DISTINCT pm.meta_value FROM {$wpdb->postmeta} pm
			LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			WHERE pm.meta_key = '%s'";
		$published_by_fields = array( self::$meta_key );

		if ( ! empty( $_GET['post_status'] ) && 'all' !== $_GET['post_status'] ) {
			$published_by_sql .= " AND p.post_status = '%s'";
			$published_by_fields[] = $_GET['post_status'];
		}

		if ( ! empty( $_GET['post_type'] ) && 'all' !== $_GET['post_type'] ) {
			$published_by_sql .= " AND p.post_type = '%s'";
			$published_by_fields[] = $_GET['post_type'];
		}

		$published_by_sql .= " ORDER BY pm.meta_value ASC";
		$published_by = $wpdb->get_col( $wpdb->prepare( $published_by_sql, $published_by_fields ) );

		// Return if no published-by users were found to filter by.
		if ( ! $published_by ) {
			return;
		}
?>
		<label class="screen-reader-text" for="filter-by-published-by"><?php _e( 'Filter by published by', 'published-by' ); ?></label>
		<select name="published-by" id="filter-by-published-by">
			<option value=""><?php _e( 'All Published By', 'published-by' ); ?></option>
<?php
			$users = [];
			foreach ( $published_by as $user_id ) {
				$users[ $user_id ] = new WP_User( $user_id );
			}
			uasort( $users, function ( $a, $b ) { return strnatcmp( $a->display_name, $b->display_name ); } );
			$current = empty( $_GET['published-by'] ) ? '' : $_GET['published-by'];
			foreach ( $users as $user_id => $user ) {
				printf( "\t\t\t<option value=\"%s\"%s>%s</option>\n", esc_attr( $user_id ), selected( $user_id, $current, true ), $user->display_name );
			}
?>
		</select>
<?php
	}

	/**
	 * Filters post query for admin post listings to filter by published-by.
	 *
	 * @since 1.3.0
	 *
	 * @param string $query Query object.
	 */
	public static function filter_by_query( $query ) {
		global $pagenow;

		if ( ! is_admin() || ! self::include_column() ) {
			return;
		}

		if ( isset( $_GET['post_type'] ) ) {
			$type = empty( $_GET['post_type'] ) ? 'post' : $_GET['post_type'];
		}

		if ( is_admin() && $pagenow === 'edit.php' && ! empty( $_GET['published-by'] ) ) {
			$query->query_vars['meta_key'] = self::$meta_key;
			$query->query_vars['meta_value'] = $_GET['published-by'];
		}
	}

} // end c2c_PublishedBy

c2c_PublishedBy::init();

endif; // end if !class_exists()
