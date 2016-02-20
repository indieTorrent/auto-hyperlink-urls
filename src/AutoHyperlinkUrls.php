<?php namespace indietorrent\AutoHyperlinkUrls;

/**
 * @package Auto_Hyperlink_URLs
 * @author Scott Reilly
 * @version 4.0
 */
/*
Plugin Name: Auto-hyperlink URLs
Version: 4.0
Plugin URI: http://coffee2code.com/wp-plugins/auto-hyperlink-urls/
Author: Scott Reilly
Author URI: http://coffee2code.com
Text Domain: auto-hyperlink-urls
Description: Automatically hyperlink text URLs and email addresses originally written only as plaintext.

Compatible with WordPress 3.0+, 3.1+, 3.2+.

=>> Read the accompanying readme.txt file for instructions and documentation.
=>> Also, visit the plugin's homepage for additional information and updates.
=>> Or visit: http://wordpress.org/extend/plugins/auto-hyperlink-urls/

TODO:
	* More tests (incl. testing filters)
	* Update screenshot for WP 3.2

*/

/*
Copyright (c) 2004-2011 by Scott Reilly (aka coffee2code)

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation
files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy,
modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the
Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR
IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
*/

require('required-wordpress-functions.php');

class c2c_AutoHyperlinkUrls {

	public static $instance;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->c2c_AutoHyperlinkUrls();
	}

	public function c2c_AutoHyperlinkUrls() {
		// Be a singleton
		if ( ! is_null( self::$instance ) )
			return;

		#$this->C2C_Plugin_025( '4.0', 'autohyperlink-urls', 'c2c', __FILE__, array() );
		#register_activation_hook( __FILE__, array( __CLASS__, 'activation' ) );
		self::$instance = $this;
	}

	/**
	 * Handles activation tasks, such as registering the uninstall hook.
	 *
	 * @since 4.0
	 *
	 * @return void
	 */
	public function activation() {
		register_uninstall_hook( __FILE__, array( __CLASS__, 'uninstall' ) );
	}

	/**
	 * Handles uninstallation tasks, such as deleting plugin options.
	 *
	 * This can be overridden.
	 *
	 * @since 4.0
	 *
	 * @return void
	 */
	public function uninstall() {
		delete_option( 'c2c_autohyperlink_urls' );
	}


	/**
	 * Initializes the plugin's config data array.
	 *
	 * @return void
	 */
	public function load_config() {
		$this->config = array(
			'hyperlink_comments' => array( 'input' => 'checkbox', 'default' => true,
					'label' => __( 'Auto-hyperlink comments?', $this->textdomain ) ),
			'hyperlink_emails' => array( 'input' => 'checkbox', 'default' => true,
					'label' => __( 'Hyperlink email addresses?', $this->textdomain ) ),
			'strip_protocol' => array( 'input' => 'checkbox', 'default' => true,
					'label' => __( 'Strip protocol?', $this->textdomain ),
					'help' => __( 'Remove the protocol (i.e. \'http://\') from the displayed auto-hyperlinked link?', $this->textdomain ) ),
			'open_in_new_window' => array( 'input' => 'checkbox', 'default' => true,
					'label' => __( 'Open auto-hyperlinked links in new window?', $this->textdomain ) ),
			'nofollow' => array( 'input' => 'checkbox', 'default' => false,
					'label' => __( 'Enable <a href="http://en.wikipedia.org/wiki/Nofollow">nofollow</a>?', $this->textdomain ) ),
			'hyperlink_mode' => array( 'input' => 'shorttext', 'default' => 0,
					'label' => __( 'Hyperlink Mode/Truncation', $this->textdomain ),
					'help' => __( 'This determines what text should appear as the link.  Use <code>0</code> to show the full URL, use <code>1</code> to show just the hostname, or use a value greater than <code>10</code> to indicate how many characters of the URL you want shown before it gets truncated.  <em>If</em> text gets truncated, the truncation before/after text values above will be used.', $this->textdomain ) ),
			'truncation_before_text' => array( 'input' => 'text', 'default' => '',
					'label' => __( 'Text to show before link truncation', $this->textdomain ) ),
			'truncation_after_text' => array( 'input' => 'text', 'default' => '...',
					'label' => __( 'Text to show after link truncation', $this->textdomain ) ),
			'more_extensions' => array( 'input' => 'text', 'default' => '',
					'label' => __( 'Extra domain extensions.', $this->textdomain ),
					'help' => __( 'Space and/or comma-separated list of extensions/<acronym title="Top-Level Domains">TLDs</acronym>.<br />These are already built-in: com, org, net, gov, edu, mil, us, info, biz, ws, name, mobi, cc, tv', $this->textdomain ) )
		);
	}

	/**
	 * Override the plugin framework's register_filters() to actually actions
	 * against filters.
	 *
	 * @return void
	 */
	public function register_filters() {
		$options = $this->get_options();

		$filters = (array) apply_filters( 'c2c_autohyperlink_urls_filters', array( 'the_content', 'the_excerpt', 'widget_text' ) );
		foreach( $filters as $filter )
			add_filter( $filter, array( &$this, 'hyperlink_urls' ), 9 );

		if ( $options['hyperlink_comments'] ) {
			remove_filter( 'comment_text', array( &$this, 'make_clickable' ) );
			add_filter( 'comment_text', array( &$this, 'hyperlink_urls' ), 9 );
		}
	}

	/**
	 * Outputs the text above the setting form
	 *
	 * @return void (Text will be echoed.)
	 */
	public function options_page_description() {
		$options = $this->get_options();
		parent::options_page_description( __( 'Auto-hyperlink URLs', $this->textdomain ) );
		echo '<p>' . __( 'Automatically hyperlink text URLs and email addresses originally written only as plaintext.', $this->textdomain ) . '</p>';
	}

	/**
	 * Returns the class name(s) to be used for links created by Autohyperlinks.
	 *
	 * Default value is 'autohyperlink'. Can be filtered via the
	 * 'autohyperlink_urls_class' filter.
	 *
	 * @return string Class to assign to link
	 */
	public function get_class() {
		return esc_attr( apply_filters( 'autohyperlink_urls_class', 'autohyperlink' ) );
		##return 'autohyperlink';
	}

	/**
	 * Returns the link attributes to be used for links created by Autohyperlinks.
	 *
	 * Utilizes plugin options to determine if attributes such as 'target' and
	 * 'nofollow' should be used. Calls get_class() to determine the
	 * appropriate class name(s).
	 * Can be filtered via 'autohyperlink_urls_link_attributes' filter.
	 *
	 * @param string $title (optional) The text for the link's title attribute
	 * @return string The entire HTML attributes string to be used for link
	 */
	public function get_link_attributes( $title = '' ) {
		$options = $this->get_options();
		$link_attributes = 'class="' . $this->get_class() . '"';
		if ( $title )
			$link_attributes .= ' title="' . esc_attr( $title ) . '"';
		if ( $options['open_in_new_window'] )
			$link_attributes .= ' target="_blank"';
		if ( $options['nofollow'] )
			$link_attributes .= ' rel="nofollow"';
		return apply_filters( 'autohyperlink_urls_link_attributes', $link_attributes );
		##return $link_attributes;
	}

	/**
	 * Returns the TLDs recognized by the plugin.
	 *
	 * Returns a '|'-separated string of TLDs recognized by the plugin to be
	 * used in searches for non-protocoled text links.
	 *
	 * By default this is:
	 * 'com|org|net|gov|edu|mil|us|info|biz|ws|name|mobi|cc|tv'.  More
	 * extensions can be added via the plugin's settings page.
	 *
	 * @return string The '|'-separated string of TLDs.
	 */
	public function get_tlds() {
		static $tlds;
		if ( ! $tlds ) {
			$options = $this->get_options();
			$tlds = 'com|org|net|gov|edu|mil|us|info|biz|ws|name|mobi|cc|tv';
			if ( $options['more_extensions'] )
				$tlds .= '|' . implode('|', array_map( 'trim', explode( '|', str_replace( array( ', ', ' ', ',' ), '|', $options['more_extensions'] ) ) ) );
		}
		return apply_filters( 'autohyperlink_urls_tlds', $tlds );
		##return $tlds;
	}

	/**
	 * Truncates a URL according to plugin settings.
	 *
	 * Based on various plugin settings, this function will potentially
	 * truncate the supplied URL, optionally adding text before and/or
	 * after the URL if truncated.
	 *
	 * @param string $url The URL to potentially truncate
	 * @return string the potentially truncated version of the URL
	 */
	public function truncate_link( $url ) {
		$options         = $this->get_options();
		$mode            = intval( $options['hyperlink_mode'] );
		$more_extensions = $options['more_extensions'];
		$trunc_before    = $options['truncation_before_text'];
		$trunc_after     = $options['truncation_after_text'];
		$original_url    = $url;

		if ( 1 === $mode ) {
			$url = preg_replace( "/(([a-z]+?):\\/\\/[a-z0-9\-\:@]+).*/i", "$1", $url );
			$extensions = $this->get_tlds();
			$url = $trunc_before . preg_replace( "/([a-z0-9\-\:@]+\.($extensions)).*/i", "$1", $url ) . $trunc_after;
		} elseif ( ( $mode > 10 ) && ( strlen( $url ) > $mode ) ) {
			$url = $trunc_before . substr( $url, 0, $mode ) . $trunc_after;
		}

		return apply_filters( 'autohyperlink_urls_truncate_link', $url, $original_url );
		##return $original_url;
	}

	/**
	 * Hyperlinks plaintext links within text.
	 *
	 * @param string $text The text to have its plaintext links hyperlinked.
	 * @param array $args An array of configuration options, each element of which will override the plugin's corresponding default setting.
	 * @return The hyperlinked version of the text
	 */
	public function hyperlink_urls( $text, $args = array() ) {
		$options = $this->get_options();

		if ( ! empty( $args ) )
			$options = $this->options = wp_parse_args( $args, $options );

		$text = ' ' . $text . ' ';
		$extensions = $this->get_tlds();

		$text = preg_replace_callback( "#(?!<.*?)([\s{}\(\)\[\]>])([a-z0-9\-\.]+[a-z0-9\-])\.($extensions)((?:[/\#?][^\s<{}\(\)\[\]]*[^\.,\s<{}\(\)\[\]]?)?)(?![^<>]*?>)#is",
				array( &$this, 'do_hyperlink_url_no_proto' ), $text );
		$text = preg_replace_callback( '#(?!<.*?)(?<=[\s>])(\()?(([\w]+?)://((?:[\w\\x80-\\xff\#$%&~/\-=?@\[\](+]|[.,;:](?![\s<])|(?(1)\)(?![\s<])|\)))+))(?![^<>]*?>)#is',
				array( &$this, 'do_hyperlink_url' ), $text );

		if ( $options['hyperlink_emails'] )
			$text = preg_replace_callback( '#(?!<.*?)([.0-9a-z_+-]+)@(([0-9a-z-]+\.)+[0-9a-z]{2,})(?![^<>]*?>)#i',
				array( &$this, 'do_hyperlink_email' ), $text );

		// Remove links within links
		$text = preg_replace( "#(<a [^>]+>)(.*)<a [^>]+>([^<]*)</a>([^>]*)</a>#iU", "$1$2$3$4</a>" , $text );

		return trim( $text );
	}

	/**
	 * preg_replace_callback to create the replacement text for hyperlinks
	 *
	 * @param array $matches Matches as generated by a preg_replace_callback()
	 * @return string Replacement string
	 */
	public function do_hyperlink_url( $matches ) {
		$options = $this->get_options();
		$link_text = $options['strip_protocol'] ? $matches[4] : $matches[2];
		return $matches[1] . '<a href="' . esc_attr( $matches[2] ) . '" ' . $this->get_link_attributes( $matches[2] ) .'>' . $this->truncate_link( $link_text ) . '</a>';
	}

	/**
	 * preg_replace_callback to create the replacement text for
	 * non-protocol hyperlinks
	 *
	 * @param array $matches Matches as generated by a preg_replace_callback()
	 * @return string Replacement string
	 */
	public function do_hyperlink_url_no_proto( $matches ) {
		$dest = $matches[2] . '.' . $matches[3] . $matches[4];
		return $matches[1] . '<a href="http://' . esc_attr( $dest ) . '" ' . $this->get_link_attributes( "http://$dest" ) .'>' . $this->truncate_link( $dest ) . '</a>';
	}

	/**
	 * preg_replace_callback to create the replacement text for emails
	 *
	 * @param array $matches Matches as generated by a preg_replace_callback()
	 * @return string Replacement string
	 */
	public function do_hyperlink_email( $matches ) {
		$email = $matches[1] . '@' . $matches[2];
		return "<a class=\"" . $this->get_class() . "\" href=\"mailto:$email\" title=\"mailto:$email\">" . $this->truncate_link( $email ) . '</a>';
	}

	public function get_defaults()
	{
		return [
			'hyperlink_comments' => true,
			'hyperlink_emails' => true,
			'strip_protocol' => true,
			'open_in_new_window' => true,
			'nofollow' => false,
			'hyperlink_mode' => 0,
			'truncation_before_text' => '',
			'truncation_after_text' => '...',
			'more_extensions' => '',
		];
	}
	
	public function get_options()
	{
		return $this->get_defaults();
	}

} // end c2c_AutoHyperlinkUrls
