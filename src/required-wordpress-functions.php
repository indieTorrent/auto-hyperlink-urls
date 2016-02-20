<?php

/**
 * Merge user defined arguments into defaults array.
 *
 * This function is used throughout WordPress to allow for both string or array
 * to be merged into another array.
 *
 * @since 2.2.0
 *
 * @param string|array $args     Value to merge with $defaults
 * @param array        $defaults Optional. Array that serves as the defaults. Default empty.
 * @return array Merged user defined values with defaults.
 */
function wp_parse_args( $args, $defaults = '' ) {
	if ( is_object( $args ) )
		$r = get_object_vars( $args );
	elseif ( is_array( $args ) )
		$r =& $args;
	else
		wp_parse_str( $args, $r );

	if ( is_array( $defaults ) )
		return array_merge( $defaults, $r );
	return $r;
}

/**
 * Parses a string into variables to be stored in an array.
 *
 * Uses {@link http://www.php.net/parse_str parse_str()} and stripslashes if
 * {@link http://www.php.net/magic_quotes magic_quotes_gpc} is on.
 *
 * @since 2.2.1
 *
 * @param string $string The string to be parsed.
 * @param array  $array  Variables will be stored in this array.
 */
function wp_parse_str( $string, &$array ) {
	parse_str( $string, $array );
	if ( get_magic_quotes_gpc() )
		$array = stripslashes_deep( $array );
	/**
	 * Filter the array of variables derived from a parsed string.
	 *
	 * @since 2.3.0
	 *
	 * @param array $array The array populated with variables.
	 */
	$array = apply_filters( 'wp_parse_str', $array );
}

/**
 * Checks for invalid UTF8 in a string.
 *
 * @since 2.8.0
 *
 * @staticvar bool $is_utf8
 * @staticvar bool $utf8_pcre
 *
 * @param string  $string The text which is to be checked.
 * @param bool    $strip Optional. Whether to attempt to strip out invalid UTF8. Default is false.
 * @return string The checked text.
 */
function wp_check_invalid_utf8( $string, $strip = false ) {
	$string = (string) $string;

	if ( 0 === strlen( $string ) ) {
		return '';
	}

	// Store the site charset as a static to avoid multiple calls to get_option()
	static $is_utf8 = null;
	if ( ! isset( $is_utf8 ) ) {
		$is_utf8 = in_array( get_option( 'blog_charset' ), array( 'utf8', 'utf-8', 'UTF8', 'UTF-8' ) );
	}
	if ( ! $is_utf8 ) {
		return $string;
	}

	// Check for support for utf8 in the installed PCRE library once and store the result in a static
	static $utf8_pcre = null;
	if ( ! isset( $utf8_pcre ) ) {
		$utf8_pcre = @preg_match( '/^./u', 'a' );
	}
	// We can't demand utf8 in the PCRE installation, so just return the string in those cases
	if ( !$utf8_pcre ) {
		return $string;
	}

	// preg_match fails when it encounters invalid UTF8 in $string
	if ( 1 === @preg_match( '/^./us', $string ) ) {
		return $string;
	}

	// Attempt to strip the bad chars if requested (not recommended)
	if ( $strip && function_exists( 'iconv' ) ) {
		return iconv( 'utf-8', 'utf-8', $string );
	}

	return '';
}

/**
 * Escaping for HTML attributes.
 *
 * @since 2.8.0
 *
 * @param string $text
 * @return string
 */
function esc_attr( $text ) {
	$safe_text = wp_check_invalid_utf8( $text );
	$safe_text = _wp_specialchars( $safe_text, ENT_QUOTES );
	/**
	 * Filter a string cleaned and escaped for output in an HTML attribute.
	 *
	 * Text passed to esc_attr() is stripped of invalid or special characters
	 * before output.
	 *
	 * @since 2.0.6
	 *
	 * @param string $safe_text The text after it has been escaped.
 	 * @param string $text      The text prior to being escaped.
	 */
	return apply_filters( 'attribute_escape', $safe_text, $text );
}

/**
 * Call the functions added to a filter hook.
 *
 * The callback functions attached to filter hook $tag are invoked by calling
 * this function. This function can be used to create a new filter hook by
 * simply calling this function with the name of the new hook specified using
 * the $tag parameter.
 *
 * The function allows for additional arguments to be added and passed to hooks.
 *
 *     // Our filter callback function
 *     function example_callback( $string, $arg1, $arg2 ) {
 *         // (maybe) modify $string
 *         return $string;
 *     }
 *     add_filter( 'example_filter', 'example_callback', 10, 3 );
 *
 *     /*
 *      * Apply the filters by calling the 'example_callback' function we
 *      * "hooked" to 'example_filter' using the add_filter() function above.
 *      * - 'example_filter' is the filter hook $tag
 *      * - 'filter me' is the value being filtered
 *      * - $arg1 and $arg2 are the additional arguments passed to the callback.
 *     $value = apply_filters( 'example_filter', 'filter me', $arg1, $arg2 );
 *
 * @since 0.71
 *
 * @global array $wp_filter         Stores all of the filters.
 * @global array $merged_filters    Merges the filter hooks using this function.
 * @global array $wp_current_filter Stores the list of current filters with the current one last.
 *
 * @param string $tag   The name of the filter hook.
 * @param mixed  $value The value on which the filters hooked to `$tag` are applied on.
 * @param mixed  $var   Additional variables passed to the functions hooked to `$tag`.
 * @return mixed The filtered value after all hooked functions are applied to it.
 */
function apply_filters( $tag, $value ) {
	global $wp_filter, $merged_filters, $wp_current_filter;

	$args = array();

	// Do 'all' actions first.
	if ( isset($wp_filter['all']) ) {
		$wp_current_filter[] = $tag;
		$args = func_get_args();
		_wp_call_all_hook($args);
	}

	if ( !isset($wp_filter[$tag]) ) {
		if ( isset($wp_filter['all']) )
			array_pop($wp_current_filter);
		return $value;
	}

	if ( !isset($wp_filter['all']) )
		$wp_current_filter[] = $tag;

	// Sort.
	if ( !isset( $merged_filters[ $tag ] ) ) {
		ksort($wp_filter[$tag]);
		$merged_filters[ $tag ] = true;
	}

	reset( $wp_filter[ $tag ] );

	if ( empty($args) )
		$args = func_get_args();

	do {
		foreach ( (array) current($wp_filter[$tag]) as $the_ )
			if ( !is_null($the_['function']) ){
				$args[1] = $value;
				$value = call_user_func_array($the_['function'], array_slice($args, 1, (int) $the_['accepted_args']));
			}

	} while ( next($wp_filter[$tag]) !== false );

	array_pop( $wp_current_filter );

	return $value;
}

function get_option($option)
{
	$options = [
		'blog_charset' => ['utf8', 'utf-8', 'UTF8', 'UTF-8'],
	];
	
	return $options[$option];
}

/**
 * Converts a number of special characters into their HTML entities.
 *
 * Specifically deals with: &, <, >, ", and '.
 *
 * $quote_style can be set to ENT_COMPAT to encode " to
 * &quot;, or ENT_QUOTES to do both. Default is ENT_NOQUOTES where no quotes are encoded.
 *
 * @since 1.2.2
 * @access private
 *
 * @staticvar string $_charset
 *
 * @param string     $string         The text which is to be encoded.
 * @param int|string $quote_style    Optional. Converts double quotes if set to ENT_COMPAT,
 *                                   both single and double if set to ENT_QUOTES or none if set to ENT_NOQUOTES.
 *                                   Also compatible with old values; converting single quotes if set to 'single',
 *                                   double if set to 'double' or both if otherwise set.
 *                                   Default is ENT_NOQUOTES.
 * @param string     $charset        Optional. The character encoding of the string. Default is false.
 * @param bool       $double_encode  Optional. Whether to encode existing html entities. Default is false.
 * @return string The encoded text with HTML entities.
 */
function _wp_specialchars( $string, $quote_style = ENT_NOQUOTES, $charset = false, $double_encode = false ) {
	$string = (string) $string;

	if ( 0 === strlen( $string ) )
		return '';

	// Don't bother if there are no specialchars - saves some processing
	if ( ! preg_match( '/[&<>"\']/', $string ) )
		return $string;

	// Account for the previous behaviour of the function when the $quote_style is not an accepted value
	if ( empty( $quote_style ) )
		$quote_style = ENT_NOQUOTES;
	elseif ( ! in_array( $quote_style, array( 0, 2, 3, 'single', 'double' ), true ) )
		$quote_style = ENT_QUOTES;

	// Store the site charset as a static to avoid multiple calls to wp_load_alloptions()
	if ( ! $charset ) {
		static $_charset = null;
		if ( ! isset( $_charset ) ) {
			$alloptions = wp_load_alloptions();
			$_charset = isset( $alloptions['blog_charset'] ) ? $alloptions['blog_charset'] : '';
		}
		$charset = $_charset;
	}

	if ( in_array( $charset, array( 'utf8', 'utf-8', 'UTF8' ) ) )
		$charset = 'UTF-8';

	$_quote_style = $quote_style;

	if ( $quote_style === 'double' ) {
		$quote_style = ENT_COMPAT;
		$_quote_style = ENT_COMPAT;
	} elseif ( $quote_style === 'single' ) {
		$quote_style = ENT_NOQUOTES;
	}

	if ( ! $double_encode ) {
		// Guarantee every &entity; is valid, convert &garbage; into &amp;garbage;
		// This is required for PHP < 5.4.0 because ENT_HTML401 flag is unavailable.
		$string = wp_kses_normalize_entities( $string );
	}

	$string = @htmlspecialchars( $string, $quote_style, $charset, $double_encode );

	// Backwards compatibility
	if ( 'single' === $_quote_style )
		$string = str_replace( "'", '&#039;', $string );

	return $string;
}
