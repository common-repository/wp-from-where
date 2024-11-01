<?php

/*
Plugin Name: WordPress From/Where
Plugin URI: http://elliottback.com/wp/
Description: Logs requests from search engines and determines what keywords are being used to find your posts!
Author: Elliott Back
Author URI: http://elliottback.com
Version: 1.0
*/

require_once (ABSPATH.'wp-includes/wp-db.php');
define('WP_FROM_WHERE_APP_NAME', 'wp_from_where');

// create our little tables, once
function wp_from_where_install(){
	global $wpdb, $table_prefix;

	if (!get_option('wp_from_where_db')) {
		$sql = "CREATE TABLE $table_prefix".WP_FROM_WHERE_APP_NAME." (post_id INT UNSIGNED NOT NULL, keyword VARCHAR(255) NOT NULL, count INT UNSIGNED NOT NULL, PRIMARY KEY (post_id, keyword))";

		if ($wpdb->query($sql) === false)
			$wpdb->print_error();

		update_option('wp_from_where_db', true);
	}
}

// try really hard to find the post ID
function wp_from_where_id() {
	global $post, $posts, $meta, $meta_obj, $meta_object, $comment_post_ID;

	if(count($posts) > 1)
		return null;

	$id = $meta->post;
	if (empty ($id))
		$id = $post->post_id;
	if (empty ($id))
		$id = $post->ID;
	if (empty ($id))
		$id = $comment_post_ID;
	if (empty ($id))
		$id = $posts[0]->post_id;
	if (empty ($id))
		$id = $posts[0]->ID;
	if (empty ($id))
		$id = $_GET['p'];
	if (empty ($id))
		$id = $meta_obj->post;
	if (empty ($id))
		$id = $meta_object->post;

	return $id;
}

// return search engine hits!
function wp_from_where($per_post = true, $sort = 'DESC', $limit = 10, $before = '', $after = '', $sep = ', ', $dolinks = true) {
	global $wpdb, $table_prefix;

	$href = 'http://www.google.com/search?q=';
	$titl = ' search engine hits.';

	$id = wp_from_where_id();
	if (empty ($id) && $per_post)
		return;

	$sql = "SELECT DISTINCT * FROM $table_prefix".WP_FROM_WHERE_APP_NAME. ($per_post ? " WHERE post_id = $id " : "")." ORDER BY count $sort LIMIT $limit";
	$res = $wpdb->get_results($sql, ARRAY_A);

	if (empty ($res)) {
		echo $before."No results yet!".$after;
	} else {
		foreach ($res as $row) {
			echo $before;
			if($dolinks) echo '<a href="'.$href.htmlentities(urlencode($row['keyword'])).'" title="'.$row['count'].$titl.'">';
			echo htmlentities(urldecode($row['keyword']));
			if($dolinks) echo '</a>';
			echo (++ $i < count($res)) ? $sep : ' ';
			echo $after;
		}

		// attribution
		if($dolinks){
			echo '<!-- Results by WP From / Where (http://elliottback.com) -->';
			echo $before.'<small>Results by <a href="http://elliottback.com" title="Elliott C. Back">WP From / Where</a></small>'.$after;
		}
	}
}

function wp_from_where_log_to_db() {
	global $wpdb, $table_prefix;

	// only per-post
	// Post id
	$id = wp_from_where_id();
	
	if (empty ($id))
		return;

	// snippet from Dave Child @
	// http://www.ilovejackdaniels.com/php/google-style-keyword-highlighting/
	$search = true;
	if ((isset ($_SERVER['HTTP_REFERER'])) && (strlen(trim($_SERVER['HTTP_REFERER'])) > 0)) {
		$keywords = array ();
		$url = urldecode($_SERVER['HTTP_REFERER']);

		/* All the search engines that are nice enough to use q= */

		if (
				eregi("www\.google", $url) || 
				eregi("blogsearch\.google", $url) || 
				eregi("blogs\.icerocket\.com", $url) ||
				eregi("www\.alltheweb", $url) ||
				eregi("search\.msn", $url)
			)
			
			preg_match("`(\?|&|&amp;)q=(.*?)(&|&amp;|$)`si", " $url ", $keywords);

		// Technorati
		// http://technorati.com/search/elliott+back
		else if(eregi("technorati\.com/search/", $url))
			preg_match("`(/search/)(.*?)`si", " $url ", $keywords);
		
		else if(eregi("technorati\.com/tags/", $url))
			preg_match("`(/tags/)([^\?]*)`si", " $url ", $keywords);
		
		// Yahoo 
		// http://search.yahoo.com/search?p=%22Antioch+University+Los+Angeles%22+elliott
		else if ((eregi("yahoo\.com", $url)) or (eregi("search\.yahoo", $url)))
			preg_match("`(\?|&|&amp;)p=(.*?)(&|&amp;|$)`si", " $url ", $keywords);

		// Looksmart 
		else if (eregi("looksmart\.com", $url))
			preg_match("`(\?|&|&amp;)qt=(.*?)(&|&amp;|$)`si", " $url ", $keywords);

		// Netscape
		// http://search.netscape.com/ns/boomframe.jsp?query=american+inter+university
		else if (eregi("search.netscape.com", $url))
			preg_match("`(\?|&|&amp;)query=(.*?)(&|&amp;|$)`si", " $url ", $keywords);

		// None
		else
			$search = false;
	}
	
	// Get keywords
	$kw = $wpdb->escape(trim($keywords[2]));

	// Actually have keywords
	if (strlen($kw) < 1)
		return;

	// only from a search engine
	if (!$search)
		return;

	// See if the record exists
	$sql = "SELECT wfw.count FROM $table_prefix".WP_FROM_WHERE_APP_NAME." wfw WHERE post_id = $id AND keyword = '$kw' LIMIT 1";
	$res = $wpdb->get_var($sql);

	// good for insert
	if ($res == 0) {
		$sql = "INSERT INTO $table_prefix".WP_FROM_WHERE_APP_NAME."(post_id, keyword, count) VALUES ($id, '$kw', 1)";
		if ($wpdb->query($sql) === false)
			$wpdb->print_error();
	}

	// need to update from res
	else {
		$res ++;
		$sql = "UPDATE $table_prefix".WP_FROM_WHERE_APP_NAME." SET count = $res WHERE post_id = $id AND keyword = '$kw'";
		if ($wpdb->query($sql) === false)
			$wpdb->print_error();
	}
}

if(function_exists('register_activation_hook'))
	register_activation_hook(__FILE__, 'wp_from_where_install');

if(function_exists('add_action'))
	add_action('wp_head', 'wp_from_where_log_to_db');
?>