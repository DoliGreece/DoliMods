<?php
/**
 * Numbers_Words
 *
 * PHP version 4
 *
 * Copyright (c) 1997-2006 The PHP Group
 *
 * This source file is subject to version 3.0 of the PHP license,
 * that is bundled with this package in the file LICENSE, and is
 * available at through the world-wide-web at
 * http://www.php.net/license/3_0.txt.
 * If you did not receive a copy of the PHP license and are unable to
 * obtain it through the world-wide-web, please send a note to
 * license@php.net so we can mail you a copy immediately.
 *
 * Authors: Piotr Klaban <makler@man.torun.pl>
 *
 * @category Numbers
 * @package  Numbers_Words
 * @author   Piotr Klaban <makler@man.torun.pl>
 * @license  PHP 3.0 http://www.php.net/license/3_0.txt
 * @version  SVN: $Id: Words.php 302815 2010-08-26 15:54:33Z ifeghali $
 * @link     http://pear.php.net/package/Numbers_Words
 */

// {{{ Numbers_Words

/**
 * The Numbers_Words class provides method to convert arabic numerals to words.
 *
 * @category Numbers
 * @package  Numbers_Words
 * @author   Piotr Klaban <makler@man.torun.pl>
 * @license  PHP 3.0 http://www.php.net/license/3_0.txt
 * @link     http://pear.php.net/package/Numbers_Words
 * @since    PHP 4.2.3
 * @access   public
 */
class Numbers_Words
{
	// {{{ properties

	/**
	 * Default Locale name
	 * @var string
	 * @access public
	 */
	var $locale = 'en_US';

	var $labelcurrency;
	var $labelcurrencysing;

	var $labelcents;
	var $labelcentsing;

	// }}}
	// {{{ toWords()

	/**
	 * Converts a number to its word representation
	 *
	 * @param integer $num     An integer between -infinity and infinity inclusive :)
	 *                         that should be converted to a words representation
	 * @param string  $locale  Language name abbreviation. Optional. Defaults to
	 *                         current loaded driver or en_US if any.
	 * @param array   $options Specific driver options
	 *
	 * @access public
	 * @author Piotr Klaban <makler@man.torun.pl>
	 * @since  PHP 4.2.3
	 * @return string  The corresponding word representation
	 */
	function toWords($num, $locale = '', $options = array())
	{
		if (empty($locale)) {
			$locale = $this->locale;
		}

		if (empty($locale)) {
			$locale = 'en_US';
		}

		// @CHANGE
		if (! empty($this->dir)) set_include_path($this->dir.PATH_SEPARATOR.get_include_path());
		include_once "Numbers/Words/lang.${locale}.php";

		$classname = "Numbers_Words_${locale}";

		if (!class_exists($classname)) {
			return Numbers_Words::raiseError("Unable to include the Numbers/Words/lang.${locale}.php file, even in dir ".$this->dir.PATH_SEPARATOR.get_include_path());
		}

		$methods = get_class_methods($classname);

		if (!in_array('_toWords', $methods) && !in_array('_towords', $methods)) {
			return Numbers_Words::raiseError("Unable to find _toWords method in '$classname' class");
		}

		if (!is_int($num)) {
			// cast (sanitize) to int without losing precision
			$num = preg_replace('/^[^\d]*?(-?)[ \t\n]*?(\d+)([^\d].*?)?$/', '$1$2', $num);
		}

		$truth_table  = ($classname == get_class($this)) ? 'T' : 'F';
		$truth_table .= (empty($options)) ? 'T' : 'F';

		switch ($truth_table) {

			/**
			 * We are a language driver
			 */
			case 'TT':
			return trim($this->_toWords($num));
			break;

			/**
			 * We are a language driver with custom options
			 */
			case 'TF':
			return trim($this->_toWords($num, $options));
			break;

			/**
			 * We are the parent class
			 */
			case 'FT':
				@$obj = new $classname;
			return trim($obj->_toWords($num));
			break;

			/**
			 * We are the parent class and should pass driver options
			 */
			case 'FF':
				@$obj = new $classname;
			return trim($obj->_toWords($num, $options));
			break;
		}
	}
	// }}}

	// {{{ toCurrency()
	/**
	 * Converts a currency value to word representation (1.02 => one dollar two cents)
	 * If the number has not any fraction part, the "cents" number is omitted.
	 *
	 * @param float  $num      A float/integer/string number representing currency value
	 *
	 * @param string $locale   Language name abbreviation. Optional. Defaults to en_US.
	 *
	 * @param string $int_curr International currency symbol
	 *                         as defined by the ISO 4217 standard (three characters).
	 *                         E.g. 'EUR', 'USD', 'PLN'. Optional.
	 *                         Defaults to $def_currency defined in the language class.
	 *
	 * @return string  The corresponding word representation
	 *
	 * @access public
	 * @author Piotr Klaban <makler@man.torun.pl>
	 * @since  PHP 4.2.3
	 * @return string
	 */
	function toCurrency($num, $locale = 'en_US', $int_curr = '')
	{
		$ret = $num;

		// @CHANGE
		if (! empty($this->dir)) set_include_path($this->dir.PATH_SEPARATOR.get_include_path());
		@include_once "Numbers/Words/lang.${locale}.php";

		$classname = "Numbers_Words_${locale}";

		if (!class_exists($classname)) {
			return Numbers_Words::raiseError("Unable to include the Numbers/Words/lang.${locale}.php file");
		}

		$methods = get_class_methods($classname);

		if (!in_array('toCurrencyWords', $methods) && !in_array('tocurrencywords', $methods)) {
			return Numbers_Words::raiseError("Unable to find toCurrencyWords method in '$classname' class");
		}

		@$obj = new $classname;

		// @CHANGE. Set the _currency_names that will be used by toCurrencyWords to use the one set in function_numberwords.lib.php
		// $this->labelcurrency
		// $this->labelcurrencysing
		// $this->labelcents
		// $this->labelcentsing
		$obj->_currency_names[$int_curr] = array(array($this->labelcurrencysing, $this->labelcurrency), array($this->labelcentsing, $this->labelcents));
		$rounding = getDolGlobalInt('MAIN_MAX_DECIMALS_TOT');
		//var_dump($obj->_currency_names[$int_curr]);

		// round if a float is passed, use Math_BigInteger otherwise
		// @CHANGE
		//if (is_float($num)) {
			//$num = round($num, 2);
			$num = round((float) $num, $rounding);
		//}

		if (strpos((string) $num, '.') === false) {	// If no decimal part
			return trim($obj->toCurrencyWords($int_curr, $num));
		}

		$currency = explode('.', $num, 2);

		// @CHANGE
		$currency[1] = substr($currency[1].'00000000', 0, $rounding);
		/*
		$len = strlen($currency[1]);

		if ($len == 1) {
			// add leading zero
			$currency[1] .= '0';
		} elseif ($len > 2) {
			// get the 3rd digit after the comma
			$round_digit = substr($currency[1], 2, 1);

			// cut everything after the 2nd digit
			$currency[1] = substr($currency[1], 0, 2);

			if ($round_digit >= 5) {
				// round up without losing precision
				include_once "Math/BigInteger.php";

				$int = new Math_BigInteger(join($currency));
				$int = $int->add(new Math_BigInteger(1));
				$int_str = $int->toString();

				$currency[0] = substr($int_str, 0, -2);
				$currency[1] = substr($int_str, -2);

				// check if the rounded decimal part became zero
				if ($currency[1] == '00') {
					$currency[1] = false;
				}
			}
		}
		*/

		return trim($obj->toCurrencyWords($int_curr, $currency[0], $currency[1]));
	}
	// }}}

	// {{{ getLocales()
	/**
	 * Lists available locales for Numbers_Words
	 *
	 * @param mixed $locale string/array of strings $locale
	 *                      Optional searched language name abbreviation.
	 *                      Default: all available locales.
	 *
	 * @return array   The available locales (optionaly only the requested ones)
	 * @author Piotr Klaban <makler@man.torun.pl>
	 * @author Bertrand Gugger, bertrand at toggg dot com
	 *
	 * @access public
	 * @static
	 * @return mixed[]
	 */
	function getLocales($locale = null)
	{
		$ret = array();
		if (isset($locale) && is_string($locale)) {
			$locale = array($locale);
		}

		$dname = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'Words' . DIRECTORY_SEPARATOR;

		$dh = opendir($dname);

		if ($dh) {
			while ($fname = readdir($dh)) {
				if (preg_match('#^lang\.([a-z_]+)\.php$#i', $fname, $matches)) {
					if (is_file($dname . $fname) && is_readable($dname . $fname) &&
						(!isset($locale) || in_array($matches[1], $locale))) {
						$ret[] = $matches[1];
					}
				}
			}
			closedir($dh);
			sort($ret);
		}

		return $ret;
	}
	// }}}

	// {{{ raiseError()
	/**
	 * Trigger a PEAR error
	 *
	 * To improve performances, the PEAR.php file is included dynamically.
	 *
	 * @param string $msg error message
	 *
	 * @return PEAR_Error
	 */
	function raiseError($msg)
	{
		// @CHANGE
		//include_once 'PEAR.php';
		//return PEAR::raiseError($msg);
		$this->error=$msg;
		//dol_print_error('',$msg);
		return -1;
	}
	// }}}
}

// }}}
