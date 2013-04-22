<?php
class WP_Function_Wrapper {
	static public function wp_error($field, $message, $data = '') {
		if ( class_exists('WP_Error') ) {
			return new WP_Error($field, $message, $data);
		} else {
			$error = new stdClass();
			$error->validate = false;
			$error->field = $field;
			$error->message = $message;
			$error->data = $data;
			return $error;
		}
	}

	static public function is_wp_error($thing) {
		if ( function_exists('is_wp_error') ) {
			return is_wp_error( $thing );
		} else {
			return ( is_object($thing) && isset($thing->validate) && $thing->validate === false);
		}
	}

	static public function get_error_message($field, $thing) {
		if ( self::is_wp_error($thing) ) {
			return class_exists('WP_Error')
				? $thing->get_error_message()
				: (isset($thing->message) ? $thing->message : sprintf('The "%s" field is invalid.', $field));
		} else {
			return null;
		}
	}

	static public function is_email( $email, $deprecated = false ) {
		if ( function_exists('is_email') ) {
			return is_email( $email, $deprecated );
		} else {
			// Test for the minimum length the email can be
			if ( strlen( $email ) < 3 )
				return false;

			// Test for an @ character after the first position
			if ( strpos( $email, '@', 1 ) === false )
				return false;

			// Split out the local and domain parts
			list( $local, $domain ) = explode( '@', $email, 2 );

			// LOCAL PART
			// Test for invalid characters
			if ( !preg_match( '/^[a-zA-Z0-9!#$%&\'*+\/=?^_`{|}~\.-]+$/', $local ) )
				return false;

			// DOMAIN PART
			// Test for sequences of periods
			if ( preg_match( '/\.{2,}/', $domain ) )
				return false;

			// Test for leading and trailing periods and whitespace
			if ( trim( $domain, " \t\n\r\0\x0B." ) !== $domain )
				return false;

			// Split the domain into subs
			$subs = explode( '.', $domain );

			// Assume the domain will have at least two subs
			if ( 2 > count( $subs ) )
				return false;

			// Loop through each sub
			foreach ( $subs as $sub ) {
				// Test for leading and trailing hyphens and whitespace
				if ( trim( $sub, " \t\n\r\0\x0B-" ) !== $sub )
					return false;

				// Test for invalid characters
				if ( !preg_match('/^[a-z0-9-]+$/i', $sub ) )
					return false;
			}

			// Congratulations your email made it!
			return $email;
		}
	}

	static public function esc_html( $text ) {
		if ( function_exists('esc_html') ) {
			return esc_html($text);
		} else {
			$safe_text = self::wp_check_invalid_utf8( $text );
			$safe_text = self::wp_specialchars( $safe_text, ENT_QUOTES );
			return $safe_text;
		}
	}

	static public function wp_check_invalid_utf8( $string, $strip = false ) {
		if ( function_exists('wp_check_invalid_utf8') ) {
			return wp_check_invalid_utf8( $string, $strip );
		} else {
			$string = (string) $string;
			if ( 0 === strlen( $string ) ) {
				return '';
			}

			// Check for support for utf8 in the installed PCRE library once and store the result in a static
			static $utf8_pcre;
			if ( !isset( $utf8_pcre ) ) {
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
	}

	static public function wp_specialchars( $string, $quote_style = ENT_NOQUOTES, $charset = false, $double_encode = false ) {
		if ( function_exists('_wp_specialchars') ) {
			return _wp_specialchars( $string, $quote_style, $charset, $double_encode );
		} else {
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
			if ( ! $charset )
				$charset = 'UTF-8';

			if ( in_array( $charset, array( 'utf8', 'utf-8', 'UTF8' ) ) )
				$charset = 'UTF-8';

			$_quote_style = $quote_style;
			if ( $quote_style === 'double' ) {
				$quote_style = ENT_COMPAT;
				$_quote_style = ENT_COMPAT;
			} elseif ( $quote_style === 'single' ) {
				$quote_style = ENT_NOQUOTES;
			}

			$string = @htmlspecialchars( $string, $quote_style, $charset );

			// Backwards compatibility
			if ( 'single' === $_quote_style )
				$string = str_replace( "'", '&#039;', $string );

			return $string;
		}
	}

} // end of class
// EOF
