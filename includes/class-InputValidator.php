<?php
if ( !class_exists('InputValidator') ) :

if ( !class_exists('WP_Function_Wrapper'))
	require(dirname(__FILE__).'/class-WP_Function_Wrapper.php');

class InputValidator {
	const PASSWORD_MIN_LENGTH = 6;

	private $inputs = array();
	private $rules  = array();
	private $errors = array();

	private $rc;

	function __construct( $method = 'POST' ) {
		$method = is_string($method) ? strtoupper($method) : $method;
		switch ($method) {
		case 'POST':
			$this->inputs = $_POST;
			break;
		case 'GET':
			$this->inputs = $_GET;
			break;
		case 'COOKIE':
			$this->inputs = $_COOKIE;
			break;
		default:
			if ( is_array($method) ) {
				$this->inputs = $method;
			} else {
				$this->inputs = $_POST;
			}
		}
		$this->errors_init();
		$this->rc = new ReflectionClass("InputValidator");
	}

	/*
	 * validate
	 */
	private function validate( $field, $val, &$err ) {
		$err = '';
		if ( isset($this->rules[$field]) ) {
			$rules = (array)$this->rules[$field];
			if ( !is_array($val) ) {
				foreach ( $rules as $rule ) {
					if ( isset($rule['func']) && is_callable($rule['func']) ) {
						$args = (array)( isset($rule['args']) ? $rule['args'] : array($field) );
						$args = array_merge( array($val), $args );
						$val  = call_user_func_array( $rule['func'], $args );
						if ( WP_Function_Wrapper::is_wp_error($val) ) {
							$err = $val;
							return $val;
						}
					}
				}
			} else {
				$errors = array();
				foreach ( $val as $key => &$v ) {
					$e = '';
					$v = $this->validate( $field, $v, $e );
					if ( !empty($e) )
						$errors[$key] = $e;
				}
				if ( count($errors) > 0 )
					$err = $errors;
			}
		}
		return $val;
	}

	private function array_fetch( $array, $field = '', $validate = true ) {
		$val = isset($array[$field]) ? $array[$field] : null;
		if ( $validate ) {
			$err = '';
			$val = $this->validate( $field, $val, $err );
			if ( !empty($err) )
				$this->set_error( $field, $err );
		}
		return $val;
	}

	/*
	 * get input data
	 */
	public function input( $index = false, $validate = true ) {
		if ( !$index ) {
			$post = array();
			foreach (array_keys($this->inputs) as $key) {
				$post[$key] = $this->array_fetch($this->inputs, $key, $validate);
			}
			return $post;
		} else if ( is_array($index) ) {
			$post = array();
			foreach ($index as $key) {
				$post[$key] = $this->array_fetch($this->inputs, $key, $validate);
			}
			return $post;
		} else {
			return $this->array_fetch($this->inputs, $index, $validate);
		}
	}

	/*
	 * set validate rules
	 */
	public function set_rules( $field, $func ) {
		if ( !isset($this->rules[$field]) ) {
			$this->rules[$field] = array();
		}

		$arg_list = func_get_args();
		unset($arg_list[1]);

		if ( is_string($func) && $this->rc->hasMethod($func) ) {
			$this->rules[$field][] = array( 'func' => array(&$this, $func), 'args' => $arg_list );
		} else if ( is_callable($func) ) {
			$this->rules[$field][] = array( 'func' => $func, 'args' => $arg_list );
		} else if ( is_array($func) ) {
			foreach ( $func as $f ) {
				$this->set_rules( $field, $f );
			}
		}
	}

	/*
	 * get errors
	 */
	public function get_errors() {
		return $this->errors;
	}

	public function errors_init() {
		$this->errors = array();
	}

	private function set_error( $field, $message = '' ) {
		if ( !is_array($message) ) {
			$message = 
				WP_Function_Wrapper::is_wp_error($message)
				? WP_Function_Wrapper::get_error_message($field, $message)
				: '';
		} else {
			$err = array();
			foreach ( $message as $key => $val ) {
				if ( WP_Function_Wrapper::is_wp_error($val) ) {
					$err[$key] = WP_Function_Wrapper::get_error_message($field, $val);
				}
			}
			$message = count($err) > 0 ? $err : '';
		}

		if ( !empty($message) ) {
			if ( !isset($this->errors[$field]) )
				$this->errors[$field] = array();
			if ( !in_array($message, $this->errors[$field]) )
				$this->errors[$field] = $message;
		}
	}

	/*
	 * validate rules
	 */
	private function trim( $val ) {
		return trim($val);
	}

	private function esc_html( $val ) {
		return WP_Function_Wrapper::esc_html( $val );
	}

	private function required( $val, $field = '' ) {
		if ( empty($val) ) {
			return WP_Function_Wrapper::wp_error( $field, sprintf('The "%s" field is required.', $field), $val );
		}
		return $val;
	}

	private function min_length( $val, $field = '', $min_length = false ) {
		if ( !is_numeric($min_length) )
			return $val;
		if ( strlen($val) < $min_length ) {
			return WP_Function_Wrapper::wp_error( $field, sprintf('The "%s" field must be at least %s characters in length.', $field, $min_length), $val );
		}
		return $val;
	}

	private function max_length( $val, $field = '', $max_length = false ) {
		if ( !is_numeric($max_length) )
			return $val;
		if ( strlen($val) > $min_length ) {
			return WP_Function_Wrapper::wp_error( $field, sprintf('The "%s" field must be at most %s characters in length.', $field, $max_length), $val );
		}
		return $val;
	}

	private function password_min_length( $val, $field = '', $min_length = false ) {
		if ( !is_numeric($min_length) )
			$min_length = self::PASSWORD_MIN_LENGTH;
		return $this->min_length( $val, $field, $min_length );
	}

	private function bool( $val ) {
		if ( is_bool($val) ) {
			return $val;
		} else if ( is_numeric($val) ) {
			return intval($val) > 0;
		} else if (empty($val) || !isset($val) || preg_match('/^(false|off|no)$/i', $val)) {
			return false;
		} else {
			return true;
		}
	}

	private function url( $val, $field = '' ) {
		$val_org = $val;
		$val = str_replace(
			array('／','：','＃','＆', '？'),
			array('/',':','#','&', '?'),
			function_exists('mb_convert_kana') ? mb_convert_kana($val, 'as') : $val
			);
		$regex = '/^\b(?:https?|shttp):\/\/(?:(?:[-_.!~*\'()a-zA-Z0-9;:&=+$,]|%[0-9A-Fa-f][0-9A-Fa-f])*@)?(?:(?:[a-zA-Z0-9](?:[-a-zA-Z0-9]*[a-zA-Z0-9])?\.)*[a-zA-Z](?:[-a-zA-Z0-9]*[a-zA-Z0-9])?\.?|[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+)(?::[0-9]*)?(?:\/(?:[-_.!~*\'()a-zA-Z0-9:@&=+$,]|%[0-9A-Fa-f][0-9A-Fa-f])*(?:;(?:[-_.!~*\'()a-zA-Z0-9:@&=+$,]|%[0-9A-Fa-f][0-9A-Fa-f])*)*(?:\/(?:[-_.!~*\'()a-zA-Z0-9:@&=+$,]|%[0-9A-Fa-f][0-9A-Fa-f])*(?:;(?:[-_.!~*\'()a-zA-Z0-9:@&=+$,]|%[0-9A-Fa-f][0-9A-Fa-f])*)*)*)?(?:\?(?:[-_.!~*\'()a-zA-Z0-9;\/?:@&=+$,]|%[0-9A-Fa-f][0-9A-Fa-f])*)?(?:#(?:[-_.!~*\'()a-zA-Z0-9;\/?:@&=+$,]|%[0-9A-Fa-f][0-9A-Fa-f])*)?$/i';
		if ( !preg_match($regex, $val) ) {
			return WP_Function_Wrapper::wp_error( $field, sprintf('The "%s" field is invalid.', $field), $val_org );
		}
		return $val;
	}

	private function email( $val, $field = '' ) {
		$val_org = $val;
		$val = str_replace(
			array('＠','。','．','＋'),
			array('@','.','.','+'),
			function_exists('mb_convert_kana') ? mb_convert_kana($val, 'as') : $val
			);
		if ( !($val = WP_Function_Wrapper::is_email($val)) ) {
			return WP_Function_Wrapper::wp_error( $field, sprintf('The "%s" field is invalid.', $field), $val_org );
		}
		return $val;
	}

	private function tel( $val, $field = '' ) {
		$val_org = $val;
		$val = str_replace(
			array('ー','－','（','）'),
			array('-','-','(',')'),
			function_exists('mb_convert_kana') ? mb_convert_kana($val, 'ns') : $val
			);
		if ( !preg_match('/^[0-9\-\(\)]+$/', $val) ) {
			return WP_Function_Wrapper::wp_error( $field, sprintf('The "%s" field is invalid.', $field), $val_org );
		}
		return $val;
	}

	private function postcode( $val, $field = '' ) {
		$val_org = $val;
		$val = str_replace(
			array('ー','－'),
			array('-','-'),
			function_exists('mb_convert_kana') ? mb_convert_kana($val, 'ns') : $val
			);
		if ( !preg_match('/^[0-9\-]+$/', $val) ) {
			return WP_Function_Wrapper::wp_error( $field, sprintf('The "%s" field is invalid.', $field), $val_org );
		}
		return $val;
	}

	private function numeric( $val, $field = '' ) {
		$val_org = $val;
		$val = function_exists('mb_convert_kana') ? mb_convert_kana($val, 'ns') : $val;
		if ( !is_numeric($val) ) {
			return WP_Function_Wrapper::wp_error( $field, sprintf('The "%s" field is invalid.', $field), $val_org );
		}
		return $val;
	}

	private function match( $val, $field, $regex ) {
		if ( !preg_match($regex, $val) ) {
			return WP_Function_Wrapper::wp_error( $field, sprintf('The "%s" field is invalid.', $field), $val );
		}
		return $val;
	}

	private function kana( $val ) {
		$val = function_exists('mb_convert_kana') ? mb_convert_kana($val, 'ASKVC') : $val;
		return $val;
	}
} // end of class

endif;
// EOF
