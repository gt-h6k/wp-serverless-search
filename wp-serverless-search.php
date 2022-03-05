<?php

/**
 * Plugin Name: WP Serverless Search
 * Plugin URI: https://github.com/emaildano/wp-serverless-search
 * Description: A static search plugin for WordPress.
 * Version: 0.0.1
 * Author: DigitalCube, Daniel Olson
 * Author URI: https://digitalcube.jp
 * License: GPL2
 * Text Domain: wp-serverless-search
 */


/**
 * On Plugin Activation
 */

function wp_sls_search_install() {
	// trigger our function that registers the custom post type
	create_wp_sls_dir();
	create_search_feed();
}

add_action( 'init', 'create_wp_sls_dir' );
register_activation_hook( __FILE__, 'wp_sls_search_install' );

/**
 * Create WP SLS Dir
 */

function create_wp_sls_dir() {

	$upload_dir = wp_get_upload_dir();
	$save_path  = $upload_dir['basedir'] . '/wp-sls/.';
	$dirname    = dirname( $save_path );

	if ( ! is_dir( $dirname ) ) {
		mkdir( $dirname, 0755, true );
	}
}

/**
 * Create Search Feed
 */
add_action( 'publish_post', 'create_search_feed' );
function create_search_feed() {

	require_once( ABSPATH . 'wp-admin/includes/export.php' );

	ob_start();

	$wpExportOptions = array(
		'content' => 'post',
		'status'  => 'publish',
	);

	custom_export_wp( $wpExportOptions );

	$xml = ob_get_clean();

	$upload_dir = wp_get_upload_dir();
	$save_path  = $upload_dir['basedir'] . '/wp-sls/search-feed.xml';

	file_put_contents( $save_path, $xml );
}

/**
 * Set Plugin Defaults
 */

function wp_sls_search_default_options() {
	$options = array(
		'wp_sls_search_form'       => 'form[role=search]',
		'wp_sls_search_form_input' => 'input[type=search]',
	);

	foreach ( $options as $key => $value ) {
		update_option( $key, $value );
	}
}

if ( ! get_option( 'wp_sls_search_form' ) ) {
	register_activation_hook( __FILE__, 'wp_sls_search_default_options' );
}

/**
 * Admin Settings Menu
 */

add_action( 'admin_menu', 'wp_sls_search' );
function wp_sls_search() {
	add_options_page(
		'WP Serverless Search',
		'WP Serverless Search',
		'manage_options',
		'wp-sls-search',
		'wp_sls_search_options'
	);
}

require_once( 'lib/includes.php' );

/*
* Scripts
*/

add_action( 'wp_footer', 'wp_sls_search_assets' );
add_action( 'wp_footer', 'wp_sls_search_assets' );
function wp_sls_search_assets() {

	$shifter_js = plugins_url( 'main/main.js', __FILE__ );

	$upload_dir = wp_get_upload_dir();
	$feed_url   = $upload_dir['baseurl'] . '/wp-sls/search-feed.xml';

	$search_params = array(
		'searchFeed'      => $feed_url,
		'searchForm'      => get_option( 'wp_sls_search_form' ),
		'searchFormInput' => get_option( 'wp_sls_search_form_input' )
	);

	wp_register_script( 'wp-sls-search-js', $shifter_js, array( 'jquery', 'micromodal', 'fusejs' ), null, true );
	wp_localize_script( 'wp-sls-search-js', 'searchParams', $search_params );
	wp_enqueue_script( 'wp-sls-search-js' );

	wp_register_script( 'fusejs', 'https://cdnjs.cloudflare.com/ajax/libs/fuse.js/3.2.1/fuse.min.js', null, null, true );
	wp_enqueue_script( 'fusejs' );

	wp_register_script( 'micromodal', 'https://cdn.jsdelivr.net/npm/micromodal/dist/micromodal.min.js', null, null, true );
	wp_enqueue_script( 'micromodal' );

	wp_register_style( "wp-sls-search-css", plugins_url( '/main/main.css', __FILE__ ) );
	wp_enqueue_style( "wp-sls-search-css" );
}

add_action( 'wp_footer', 'wp_sls_search_modal' );
function wp_sls_search_modal() { ?>
    <div class="wp-sls-search-modal" id="wp-sls-search-modal" aria-hidden="true">
        <div class="wp-sls-search-modal__overlay" tabindex="-1" data-micromodal-overlay>
            <div class="wp-sls-search-modal__container" role="dialog" aria-labelledby="modal__title"
                 aria-describedby="modal__content">
                <header class="wp-sls-search-modal__header">
                    <a href="#" aria-label="Close modal" data-micromodal-close></a>
                </header>
                <form class="search-form" role="search" method="get">
                    <input id="wp-sls-earch-field" class="wp-sls-search-field" type="search" autocomplete="off"
                           class="search-field" placeholder="Search …" value="" name="s">
                </form>
                <div role="document"></div>
            </div>
        </div>
    </div>
<?php
}

/**
 * xmlファイルを出力する関数
 *
 * @param $args
 *
 * @return void
 */
function custom_export_wp( $args = array() ) {
	global $wpdb, $post;

	$defaults = array(
		'content'    => 'all',
		'author'     => false,
		'category'   => false,
		'start_date' => false,
		'end_date'   => false,
		'status'     => false,
	);
	$args     = wp_parse_args( $args, $defaults );


	$sitename = sanitize_key( get_bloginfo( 'name' ) );
	if ( ! empty( $sitename ) ) {
		$sitename .= '.';
	}
	$date        = gmdate( 'Y-m-d' );
	$wp_filename = $sitename . 'WordPress.' . $date . '.xml';

	/**
	 * Filters the export filename.
	 *
	 * @param string $wp_filename The name of the file for download.
	 * @param string $sitename The site name.
	 * @param string $date Today's date, formatted.
	 *
	 * @since 4.4.0
	 *
	 */
	$filename = apply_filters( 'export_wp_filename', $wp_filename, $sitename, $date );

	header( 'Content-Description: File Transfer' );
	header( 'Content-Disposition: attachment; filename=' . $filename );
	header( 'Content-Type: text/xml; charset=' . get_option( 'blog_charset' ), true );

	if ( 'all' !== $args['content'] && post_type_exists( $args['content'] ) ) {
		$ptype = get_post_type_object( $args['content'] );
		if ( ! $ptype->can_export ) {
			$args['content'] = 'post';
		}

		$where = $wpdb->prepare( "{$wpdb->posts}.post_type = %s", $args['content'] );
	} else {
		$post_types = get_post_types( array( 'can_export' => true ) );
		$esses      = array_fill( 0, count( $post_types ), '%s' );

		// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$where = $wpdb->prepare( "{$wpdb->posts}.post_type IN (" . implode( ',', $esses ) . ')', $post_types );
	}

	if ( $args['status'] && ( 'post' === $args['content'] || 'page' === $args['content'] ) ) {
		$where .= $wpdb->prepare( " AND {$wpdb->posts}.post_status = %s", $args['status'] );
	} else {
		$where .= " AND {$wpdb->posts}.post_status != 'auto-draft'";
	}

	$join = '';
	if ( $args['category'] && 'post' === $args['content'] ) {
		$term = term_exists( $args['category'], 'category' );
		if ( $term ) {
			$join  = "INNER JOIN {$wpdb->term_relationships} ON ({$wpdb->posts}.ID = {$wpdb->term_relationships}.object_id)";
			$where .= $wpdb->prepare( " AND {$wpdb->term_relationships}.term_taxonomy_id = %d", $term['term_taxonomy_id'] );
		}
	}

	if ( in_array( $args['content'], array( 'post', 'page', 'attachment' ), true ) ) {
		if ( $args['author'] ) {
			$where .= $wpdb->prepare( " AND {$wpdb->posts}.post_author = %d", $args['author'] );
		}

		if ( $args['start_date'] ) {
			$where .= $wpdb->prepare( " AND {$wpdb->posts}.post_date >= %s", gmdate( 'Y-m-d', strtotime( $args['start_date'] ) ) );
		}

		if ( $args['end_date'] ) {
			$where .= $wpdb->prepare( " AND {$wpdb->posts}.post_date < %s", gmdate( 'Y-m-d', strtotime( '+1 month', strtotime( $args['end_date'] ) ) ) );
		}
	}

	// Grab a snapshot of post IDs, just in case it changes during the export.
	$post_ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} $join WHERE $where" );

	/*
	 * Get the requested terms ready, empty unless posts filtered by category
	 * or all content.
	 */
	$cats  = array();
	$tags  = array();
	$terms = array();
	if ( isset( $term ) && $term ) {
		$cat  = get_term( $term['term_id'], 'category' );
		$cats = array( $cat->term_id => $cat );
		unset( $term, $cat );
	} elseif ( 'all' === $args['content'] ) {
		$categories = (array) get_categories( array( 'get' => 'all' ) );
		$tags       = (array) get_tags( array( 'get' => 'all' ) );

		$custom_taxonomies = get_taxonomies( array( '_builtin' => false ) );
		$custom_terms      = (array) get_terms(
			array(
				'taxonomy' => $custom_taxonomies,
				'get'      => 'all',
			)
		);

		// Put categories in order with no child going before its parent.
		while ( $cat = array_shift( $categories ) ) {
			if ( ! $cat->parent || isset( $cats[ $cat->parent ] ) ) {
				$cats[ $cat->term_id ] = $cat;
			} else {
				$categories[] = $cat;
			}
		}

		// Put terms in order with no child going before its parent.
		while ( $t = array_shift( $custom_terms ) ) {
			if ( ! $t->parent || isset( $terms[ $t->parent ] ) ) {
				$terms[ $t->term_id ] = $t;
			} else {
				$custom_terms[] = $t;
			}
		}

		unset( $categories, $custom_taxonomies, $custom_terms );
	}


	/**
	 * Wrap given string in XML CDATA tag.
	 *
	 * @param string $str String to wrap in XML CDATA tag.
	 *
	 * @return string
	 * @since 2.1.0
	 *
	 */
	function wxr_cdata( $str ) {
		if ( ! seems_utf8( $str ) ) {
			$str = utf8_encode( $str );
		}
		$str = '<![CDATA[' . str_replace( ']]>', ']]]]><![CDATA[>', $str ) . ']]>';

		return $str;
	}

    /*
     * excerpt
     */
	function get_the_custom_excerpt( $content ) {
		$content = preg_replace( '/<!--more-->.+/is', "", $content );
		$content = strip_shortcodes( $content );
		$content = strip_tags( $content );
		$content = str_replace( "&nbsp;", '', $content );
		$content = str_replace( "\n", '', $content );

		return $content;
	}

	echo '<?xml version="1.0" encoding="' . get_bloginfo( 'charset' ) . "\" ?>\n";
	?>
    <channel>
        <title><?php bloginfo_rss( 'name' ); ?></title>
        <link><?php bloginfo_rss( 'url' ); ?></link>
        <description><?php bloginfo_rss( 'description' ); ?></description>
        <language><?php bloginfo_rss( 'language' ); ?></language>
        <base_site_url><?php bloginfo_rss( 'url' ); ?></base_site_url>

		<?php
		if ( $post_ids ) {
			global $wp_query;

			$wp_query->in_the_loop = true;

			while ( $next_posts = array_splice( $post_ids, 0, 20 ) ) {
				$where = 'WHERE ID IN (' . implode( ',', $next_posts ) . ')';
				$posts = $wpdb->get_results( "SELECT * FROM {$wpdb->posts} $where" );

				foreach ( $posts as $post ) {
					setup_postdata( $post );

					$title   = wxr_cdata( apply_filters( 'the_title_export', $post->post_title ) );
					$content = wxr_cdata( apply_filters( 'the_content_export', get_the_custom_excerpt( $post->post_content ) ) );
					$excerpt = wxr_cdata( apply_filters( 'the_excerpt_export', $post->post_excerpt ) );
					?>
                    <item>
                        <title><?php echo $title; ?></title>
                        <link><?php echo get_permalink(); ?></link>
                        <thumbnail><?php echo get_the_post_thumbnail_url(); ?></thumbnail>
                        <content><?php echo $content; ?></content>
                        <excerpt><?php echo $excerpt; ?></excerpt>
                        <post_date><?php echo wxr_cdata( $post->post_date_gmt ); ?></post_date>
                        <post_modified><?php echo wxr_cdata( $post->post_modified ); ?></post_modified>
                    </item>
					<?php
				}
			}
		}
		?>
    </channel>
	<?php
}