<?php

/*************************************************************************
 *                                                                       *
 * class.html2text.inc                                                   *
 *                                                                       *
 *************************************************************************
 *                                                                       *
 * Converts HTML to formatted plain text                                 *
 *                                                                       *
 * Copyright (c) 2005-2007 Jon Abernathy <jon@chuggnutt.com>             *
 * All rights reserved.                                                  *
 *                                                                       *
 * This script is free software; you can redistribute it and/or modify   *
 * it under the terms of the GNU General Public License as published by  *
 * the Free Software Foundation; either version 2 of the License, or     *
 * (at your option) any later version.                                   *
 *                                                                       *
 * The GNU General Public License can be found at                        *
 * http://www.gnu.org/copyleft/gpl.html.                                 *
 *                                                                       *
 * This script is distributed in the hope that it will be useful,        *
 * but WITHOUT ANY WARRANTY; without even the implied warranty of        *
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the          *
 * GNU General Public License for more details.                          *
 *                                                                       *
 * Author(s): Jon Abernathy <jon@chuggnutt.com>                          *
 *                                                                       *
 * Last modified: 08/08/07                                               *
 *                                                                       *
 *************************************************************************/


/**
 *  Takes HTML and converts it to formatted, plain text.
 *
 *  Thanks to Alexander Krug (http://www.krugar.de/) to pointing out and
 *  correcting an error in the regexp search array. Fixed 7/30/03.
 *
 *  Updated set_html() function's file reading mechanism, 9/25/03.
 *
 *  Thanks to Joss Sanglier (http://www.dancingbear.co.uk/) for adding
 *  several more HTML entity codes to the $search and $replace arrays.
 *  Updated 11/7/03.
 *
 *  Thanks to Darius Kasperavicius (http://www.dar.dar.lt/) for
 *  suggesting the addition of $allowed_tags and its supporting function
 *  (which I slightly modified). Updated 3/12/04.
 *
 *  Thanks to Justin Dearing for pointing out that a replacement for the
 *  <TH> tag was missing, and suggesting an appropriate fix.
 *  Updated 8/25/04.
 *
 *  Thanks to Mathieu Collas (http://www.myefarm.com/) for finding a
 *  display/formatting bug in the _build_link_list() function: email
 *  readers would show the left bracket and number ("[1") as part of the
 *  rendered email address.
 *  Updated 12/16/04.
 *
 *  Thanks to Wojciech Bajon (http://histeria.pl/) for submitting code
 *  to handle relative links, which I hadn't considered. I modified his
 *  code a bit to handle normal HTTP links and MAILTO links. Also for
 *  suggesting three additional HTML entity codes to search for.
 *  Updated 03/02/05.
 *
 *  Thanks to Jacob Chandler for pointing out another link condition
 *  for the _build_link_list() function: "https".
 *  Updated 04/06/05.
 *
 *  Thanks to Marc Bertrand (http://www.dresdensky.com/) for
 *  suggesting a revision to the word wrapping functionality; if you
 *  specify a $width of 0 or less, word wrapping will be ignored.
 *  Updated 11/02/06.
 *
 *  *** Big housecleaning updates below:
 *
 *  Thanks to Colin Brown (http://www.sparkdriver.co.uk/) for
 *  suggesting the fix to handle </li> and blank lines (whitespace).
 *  Christian Basedau (http://www.movetheweb.de/) also suggested the
 *  blank lines fix.
 *
 *  Special thanks to Marcus Bointon (http://www.synchromedia.co.uk/),
 *  Christian Basedau, Norbert Laposa (http://ln5.co.uk/),
 *  Bas van de Weijer, and Marijn van Butselaar
 *  for pointing out my glaring error in the <th> handling. Marcus also
 *  supplied a host of fixes.
 *
 *  Thanks to Jeffrey Silverman (http://www.newtnotes.com/) for pointing
 *  out that extra spaces should be compressed--a problem addressed with
 *  Marcus Bointon's fixes but that I had not yet incorporated.
 *
 *	Thanks to Daniel Schledermann (http://www.typoconsult.dk/) for
 *  suggesting a valuable fix with <a> tag handling.
 *
 *  Thanks to Wojciech Bajon (again!) for suggesting fixes and additions,
 *  including the <a> tag handling that Daniel Schledermann pointed
 *  out but that I had not yet incorporated. I haven't (yet)
 *  incorporated all of Wojciech's changes, though I may at some
 *  future time.
 *
 *  *** End of the housecleaning updates. Updated 08/08/07.
 *
 *  @author Jon Abernathy <jon@chuggnutt.com>
 *  @version 1.0.0
 *  @since PHP 4.0.2
 */
class html2text
{

	/**
	 *  Contains the HTML content to convert.
	 *
	 *  @var string $html
	 *  @access public
	 */
	var $html;

	/**
	 *  Contains the converted, formatted text.
	 *
	 *  @var string $text
	 *  @access public
	 */
	var $text;

	/**
	 *  Maximum width of the formatted text, in columns.
	 *
	 *  Set this value to 0 (or less) to ignore word wrapping
	 *  and not constrain text to a fixed-width column.
	 *
	 *  @var integer $width
	 *  @access public
	 */
	var $width = 70;

	/**
	 *  List of preg* regular expression patterns to search for,
	 *  used in conjunction with $replace.
	 *
	 *  @var array $search
	 *  @access public
	 *  @see $replace
	 */
	var $search = array(
		"/\r/",                                  // Non-legal carriage return
		"/[\n\t]+/",                             // Newlines and tabs
		'/[ ]{2,}/',                             // Runs of spaces, pre-handling
		'/<script[^>]*>.*?<\/script>/i',         // <script>s -- which strip_tags supposedly has problems with
		'/<style[^>]*>.*?<\/style>/i',           // <style>s -- which strip_tags supposedly has problems with
		'/<p[^>]*>/i',                           // <P>
		'/<br[^>]*>/i',                          // <br>
		'/<i[^>]*>(.*?)<\/i>/i',                 // <i>
		'/<em[^>]*>(.*?)<\/em>/i',               // <em>
		'/(<ul[^>]*>|<\/ul>)/i',                 // <ul> and </ul>
		'/(<ol[^>]*>|<\/ol>)/i',                 // <ol> and </ol>
		'/<li[^>]*>(.*?)<\/li>/i',               // <li> and </li>
		'/<li[^>]*>/i',                          // <li>
		'/<hr[^>]*>/i',                          // <hr>
		'/<div[^>]*>/i',                         // <div>
		'/(<table[^>]*>|<\/table>)/i',           // <table> and </table>
		'/(<tr[^>]*>|<\/tr>)/i',                 // <tr> and </tr>
		'/<td[^>]*>(.*?)<\/td>/i',               // <td> and </td>
		'/&(nbsp|#160);/i',                      // Non-breaking space
		'/&(quot|rdquo|ldquo|#8220|#8221|#147|#148);/i',
												 // Double quotes
		'/&(apos|rsquo|lsquo|#8216|#8217);/i',   // Single quotes
		'/&gt;/i',                               // Greater-than
		'/&lt;/i',                               // Less-than
		'/&(amp|#38);/i',                        // Ampersand
		'/&(copy|#169);/i',                      // Copyright
		'/&(trade|#8482|#153);/i',               // Trademark
		'/&(reg|#174);/i',                       // Registered
		'/&(mdash|#151|#8212);/i',               // mdash
		'/&(ndash|minus|#8211|#8722);/i',        // ndash
		'/&(bull|#149|#8226);/i',                // Bullet
		'/&(pound|#163);/i',                     // Pound sign
		'/&(euro|#8364);/i',                     // Euro sign
		'/[ ]{2,}/'                              // Runs of spaces, post-handling
	);

	/**
	 *  List of pattern replacements corresponding to patterns searched.
	 *
	 *  @var array $replace
	 *  @access public
	 *  @see $search
	 */
	var $replace = array(
		'',                                     // Non-legal carriage return
		' ',                                    // Newlines and tabs
		' ',                                    // Runs of spaces, pre-handling
		'',                                     // <script>s -- which strip_tags supposedly has problems with
		'',                                     // <style>s -- which strip_tags supposedly has problems with
		"\n\n",                                 // <P>
		"\n",                                   // <br>
		'_\\1_',                                // <i>
		'_\\1_',                                // <em>
		"\n\n",                                 // <ul> and </ul>
		"\n\n",                                 // <ol> and </ol>
		"\t* \\1\n",                            // <li> and </li>
		"\n\t* ",                               // <li>
		"\n-------------------------\n",        // <hr>
		"<div>\n",                                   // <div>
		"\n\n",                                 // <table> and </table>
		"\n",                                   // <tr> and </tr>
		"\t\t\\1\n",                            // <td> and </td>
		' ',                                    // Non-breaking space
		'"',                                    // Double quotes
		"'",                                    // Single quotes
		'>',
		'<',
		'&',
		'(c)',
		'(tm)',
		'(R)',
		'--',
		'-',
		'*',
		'£',
		'EUR',                                  // Euro sign. � ?
		' '                                     // Runs of spaces, post-handling
	);

	/**
	 *  List of preg* regular expression patterns to search for
	 *  and replace using callback function.
	 *
	 *  @var array $callback_search
	 *  @access public
	 */
	var $callback_search = array(
		'/<(h)[123456][^>]*>(.*?)<\/h[123456]>/i', // H1 - H3
		'/<(b)[^>]*>(.*?)<\/b>/i',                 // <b>
		'/<(strong)[^>]*>(.*?)<\/strong>/i',       // <strong>
		'/<(a) [^>]*href=("|\')([^"\']+)\2[^>]*>(.*?)<\/a>/i',
												   // <a href="">
		'/<(th)[^>]*>(.*?)<\/th>/i',               // <th> and </th>
	);

	/**
	*  List of preg* regular expression patterns to search for in PRE body,
	*  used in conjunction with $pre_replace.
	*
	*  @var array $pre_search
	*  @access public
	*  @see $pre_replace
	*/
	var $pre_search = array(
		"/\n/",
		"/\t/",
		'/ /',
		'/<pre[^>]*>/',
		'/<\/pre>/'
	);

	/**
	 *  List of pattern replacements corresponding to patterns searched for PRE body.
	 *
	 *  @var array $pre_replace
	 *  @access public
	 *  @see $pre_search
	 */
	var $pre_replace = array(
		'<br>',
		'&nbsp;&nbsp;&nbsp;&nbsp;',
		'&nbsp;',
		'',
		''
	);

	/**
	 *  Contains a list of HTML tags to allow in the resulting text.
	 *
	 *  @var string $allowed_tags
	 *  @access public
	 *  @see set_allowed_tags()
	 */
	var $allowed_tags = '';

	/**
	 *  Contains the base URL that relative links should resolve to.
	 *
	 *  @var string $url
	 *  @access public
	 */
	var $url;

	/**
	 *  Indicates whether content in the $html variable has been converted yet.
	 *
	 *  @var boolean $_converted
	 *  @access private
	 *  @see $html, $text
	 */
	var $_converted = false;

	/**
	 *  Contains URL addresses from links to be rendered in plain text.
	 *
	 *  @var string $_link_list
	 *  @access private
	 *  @see _build_link_list()
	 */
	var $_link_list = '';

	/**
	 *  Number of valid links detected in the text, used for plain text
	 *  display (rendered similar to footnotes).
	 *
	 *  @var integer $_link_count
	 *  @access private
	 *  @see _build_link_list()
	 */
	var $_link_count = 0;

	/**
	 * Boolean flag, true if a table of link URLs should be listed after the text.
	 *
	 * @var boolean $_do_links
	 * @access private
	 * @see html2text()
	 */
	var $_do_links = true;

	/**
	 *  Constructor.
	 *
	 *  If the HTML source string (or file) is supplied, the class
	 *  will instantiate with that source propagated, all that has
	 *  to be done it to call get_text().
	 *
	 *  @param string $source HTML content
	 *  @param boolean $from_file Indicates $source is a file to pull content from
	 *  @param boolean $do_links Indicate whether a table of link URLs is desired
	 *  @param integer $width Maximum width of the formatted text, 0 for no limit
	 *  @access public
	 *  @return void
	 */
	function html2text($source = '', $from_file = false, $do_links = true, $width = 75)
	{
		if ( !empty($source) ) {
			$this->set_html($source, $from_file);
		}

		$this->set_base_url();
		$this->_do_links = $do_links;
		$this->width = $width;
	}

	/**
	 *  Loads source HTML into memory, either from $source string or a file.
	 *
	 *  @param string $source HTML content
	 *  @param boolean $from_file Indicates $source is a file to pull content from
	 *  @access public
	 *  @return void
	 */
	function set_html($source, $from_file = false)
	{
		if ( $from_file && file_exists($source) ) {
			$this->html = file_get_contents($source);
		} else $this->html = $source;

		$this->_converted = false;
	}

	/**
	 *  Returns the text, converted from HTML.
	 *
	 *  @access public
	 *  @return string
	 */
	function get_text()
	{
		if ( !$this->_converted ) {
			$this->_convert();
		}

		return $this->text;
	}

	/**
	 *  Prints the text, converted from HTML.
	 *
	 *  @access public
	 *  @return void
	 */
	function print_text()
	{
		print $this->get_text();
	}

	/**
	 *  Alias to print_text(), operates identically.
	 *
	 *  @access public
	 *  @return void
	 *  @see print_text()
	 */
	function p()
	{
		print $this->get_text();
	}

	/**
	 *  Sets the allowed HTML tags to pass through to the resulting text.
	 *
	 *  Tags should be in the form "<p>", with no corresponding closing tag.
	 *
	 *  @access public
	 *  @return void
	 */
	function set_allowed_tags($allowed_tags = '')
	{
		if ( !empty($allowed_tags) ) {
			$this->allowed_tags = $allowed_tags;
		}
	}

	/**
	 *  Sets a base URL to handle relative links.
	 *
	 *  @access public
	 *  @return void
	 */
	function set_base_url($url = '')
	{
		if ( empty($url) ) {
			if ( !empty($_SERVER['HTTP_HOST']) ) {
				$this->url = 'http://' . $_SERVER['HTTP_HOST'];
			} else {
				$this->url = '';
			}
		} else {
			// Strip any trailing slashes for consistency (relative
			// URLs may already start with a slash like "/file.html")
			if ( substr($url, -1) == '/' ) {
				$url = substr($url, 0, -1);
			}
			$this->url = $url;
		}
	}

	/**
	 *  Workhorse function that does actual conversion.
	 *
	 *  First performs custom tag replacement specified by $search and
	 *  $replace arrays. Then strips any remaining HTML tags, reduces whitespace
	 *  and newlines to a readable format, and word wraps the text to
	 *  $width characters.
	 *
	 *  @access private
	 *  @return void
	 */
	function _convert()
	{
		// Variables used for building the link list
		$this->_link_count = 0;
		$this->_link_list = '';

		$text = trim(stripslashes($this->html));

		// Convert <PRE>
		$this->_convert_pre($text);

		// Run our defined search-and-replace
		$text = preg_replace($this->search, $this->replace, $text);

		// Replace known html entities
		$text = html_entity_decode($text, ENT_COMPAT, 'UTF-8');

		// Run our defined search-and-replace with callback
		$text = preg_replace_callback($this->callback_search, array('html2text', '_preg_callback'), $text);

		// Remove unknown/unhandled entities (this cannot be done in search-and-replace block)
		$text = preg_replace('/&#?[a-z0-9]{2,7};/i', '', $text);

		// Strip any other HTML tags
		$text = strip_tags($text, $this->allowed_tags);

		// Bring down number of empty lines to 2 max
		$text = preg_replace("/\n\s+\n/", "\n\n", $text);
		$text = preg_replace("/[\n]{3,}/", "\n\n", $text);

		// Add link list
		if ( !empty($this->_link_list) ) {
			$text .= "\n\nLinks:\n------\n" . $this->_link_list;
		}

		// Wrap the text to a readable format
		// for PHP versions >= 4.0.2. Default width is 75
		// If width is 0 or less, don't wrap the text.
		if ( $this->width > 0 ) {
			$text = wordwrap($text, $this->width);
		}

		$this->text = $text;

		$this->_converted = true;
	}

	/**
	 *  Helper function called by preg_replace() on link replacement.
	 *
	 *  Maintains an internal list of links to be displayed at the end of the
	 *  text, with numeric indices to the original point in the text they
	 *  appeared. Also makes an effort at identifying and handling absolute
	 *  and relative links.
	 *
	 *  @param string $link URL of the link
	 *  @param string $display Part of the text to associate number with
	 *  @access private
	 *  @return string
	 */
	function _build_link_list($link, $display)
	{
		if ( !$this->_do_links ) return $display;

		if ( substr($link, 0, 7) == 'http://' || substr($link, 0, 8) == 'https://' ||
			 substr($link, 0, 7) == 'mailto:' ) {
			$this->_link_count++;
			$this->_link_list .= "[" . $this->_link_count . "] $link\n";
			$additional = ' [' . $this->_link_count . ']';
		} elseif ( substr($link, 0, 11) == 'javascript:' ) {
			// Don't count the link; ignore it
			$additional = '';
			// what about href="#anchor" ?
		} else {
			$this->_link_count++;
			$this->_link_list .= "[" . $this->_link_count . "] " . $this->url;
			if ( substr($link, 0, 1) != '/' ) {
				$this->_link_list .= '/';
			}
			$this->_link_list .= "$link\n";
			$additional = ' [' . $this->_link_count . ']';
		}

		return $display . $additional;
	}

	/**
	 *  Helper function for PRE body conversion.
	 *
	 *  @param string HTML content
	 *  @access private
	 */
	function _convert_pre(&$text)
	{
		while (preg_match('/<pre[^>]*>(.*)<\/pre>/ismU', $text, $matches)) {
			$result = preg_replace($this->pre_search, $this->pre_replace, $matches[1]);
			$text = preg_replace('/<pre[^>]*>.*<\/pre>/ismU', '<div><br>' . $result . '<br></div>', $text, 1);
		}
	}

	/**
	 *  Callback function for preg_replace_callback use.
	 *
	 *  @param  array PREG matches
	 *  @return string
	 *  @access private
	 */
	function _preg_callback($matches)
	{
		switch ($matches[1]) {
			case 'b':
			case 'strong':
			return $this->_strtoupper($matches[2]);
			case 'th':
			return $this->_strtoupper("\t\t". $matches[2] ."\n");
			case 'h':
			return $this->_strtoupper("\n\n". $matches[2] ."\n\n");
			case 'a':
			return $this->_build_link_list($matches[3], $matches[4]);
		}
	}

	/**
	 *  Strtoupper multibyte wrapper function
	 *
	 *  @param  string
	 *  @return string
	 *  @access private
	 */
	function _strtoupper($str)
	{
		if (function_exists('mb_strtoupper'))
			return mb_strtoupper($str);
		else return strtoupper($str);
	}
}
