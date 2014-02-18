<?php 

/**
* This file contains the phptrasher class that allows to obfuscate the PHP source code.
*
* @package phptrasher
*/

/**
* phptrasher class
*
* This class allows to obfuscate the PHP source code.
*
* @package phptrasher
* @author Setec Astronomy
* @version 1.0
* @abstract Obfuscate PHP source code.
* @copyright 2004
* @example sample.php A sample code.
* @link http://setecastronomy.stufftoread.com
*/

class phptrasher {
	
	/**
	* Specify if the class has to strip the comments.
	* @var boolean         
	*/
	var $removecomments = false;
	/**
	* Specify if the class has to strip the line breaks.
	* @var boolean         
	*/
	var $removelinebreaks = false;
	/**
	* Specify if the class has to obfuscate the classes names.
	* @var boolean         
	*/
	var $obfuscateclass = false;
	/**
	* Specify if the class has to obfuscate the functions names.
	* @var boolean         
	*/
	var $obfuscatefunction = false;
	/**
	* Specify if the class has to obfuscate the variables names.
	* @var boolean         
	*/
	var $obfuscatevariable = false;

	/**
 	* @access private
	*/
	var $variables = array ();
	/**
 	* @access private
	*/
	var $functions = array ();
	/**
 	* @access private
	*/
	var $classes = array ();
	
	/**
	* Default constructor
	*
	* This is the default constructor of phptrasher class.
	*/
	// public function __construct ()
	function phptrasher () {
	
	}
	
	/**
	* The initialize method
	*
	* This method initialize the internal variables, call it before starting to obfuscating the PHP source code.<br>
	* Call it only once when you are obfuscating multiple files.
	*/
	function initialize () {
		$this->variables = array ();
		$this->functions = array ();
		$this->classes = array ();
	}
	
	/**
	* The trash method
	*
	* This method returns an obfuscated version of the given file.
	* @param string $file the file name to be obfuscated.
	* @return mixed it returns the obfuscated code or false if it fails.
	*/
	function trash ($file = '') {
		$in_function = false;
		$in_class = false;
		$in_class_graph = false;
		$in_new_class = false;
		$in_var = false;
		
		$in_class_count = 0;
		
		$result = '';
		$code = @file_get_contents ($file); 
		if ($code !== false) {
		 	$tokens = token_get_all ($code); 
			foreach ($tokens as $token) { 
				if (is_string ($token)) { 
					$in_function = false;
					$in_class = false;		 	
					$in_new_class = false;		 	
					$in_var = false;		 	
					$result .= $token;		 	
					
					if ((trim ($token) == '{') && ($in_class_graph)) {
						$in_class_count++; 
					} elseif ((trim ($token) == '}') && ($in_class_graph)) {
						$in_class_count--; 
						if ($in_class_count == 0) {
							$in_class_graph = false;
						}
					}
					
				} else { 
					list ($id, $text) = $token; 
					switch ($id) { 
					case T_VARIABLE: 
						if (!$in_var) {
							if ($text == '$this') {
								$result .= $text;		 	
							} elseif ($this->obfuscatevariable) {
								if ((substr ($text, 0, 2) == '$_') || (substr ($text, 0, 8) == '$GLOBALS')) {
									$result .= $text;
								} else {
									if (!isset ($this->variables[$text])) {
										$this->variables[$text] = '$_' . md5 ($text . microtime ());
									}
									$result .= $this->variables[$text];		 	
								}
							} else {
								$result .= $text;
							}
						} else {
							$result .= $text;
							$in_var = false;
						}
						break; 
					case T_FUNCTION: 
						$in_function = true;		 	
						$result .= $text;		 	
						break; 
					case T_NEW: 
						$in_new_class = true;
						$result .= $text;		 	
						break; 
					case T_CLASS: 
						$in_class = true;
						$in_class_graph = true;		 	
						$result .= $text;		 	
						break; 
					case T_VAR:
						$in_var = true;
						$result .= $text;		 	
						break; 
					case T_STRING:
						if ($in_function) {
							if (($in_class_count != 0) && ($this->obfuscatefunction)) {
								if (isset ($this->classes[$text])) {
									$result .= $this->classes[$text];
								} else {
									$result .= $text;								
								}								
							} elseif ($this->obfuscatefunction) {
								if (!isset ($this->functions[$text])) {
									$this->functions[$text] = '_' . md5 ($text . microtime ());
								}
								$result .= $this->functions[$text];		 	
							} else {
								$result .= $text;								
							}
							$in_function = false;
						} elseif (($in_class) || ($in_new_class)) {
							if ($this->obfuscateclass) {
								if ((substr ($text, 1, 2) == '$_') && (substr ($text, 1, 8) == '$GLOBALS')) {
									$result .= $text;
								} else {
									if (!isset ($this->classes[$text])) {
										$this->classes[$text] = '_' . md5 ($text . microtime ());
									}
									$result .= $this->classes[$text];		 	
								}
							} else {
								$result .= $text;
							}
							$in_class = false;
							$in_new_class = false;
						} else {
							$result .= $text;		 	
						}
						break; 
					case T_COMMENT:
					case T_ML_COMMENT:
					case T_DOC_COMMENT:
						if (!$this->removecomments) {
							$result .= $text;		 
						}	
						break; 
					case T_WHITESPACE:
						if ($this->removelinebreaks) {
							$result = trim ($result) . ' ' . trim ($text);
						} else {
							$result .= $text;		 	
						}
						break; 
					default: 
						$result .= $text;		 	
						break; 
					} // switch($id) { 
				} // if (is_string ($token)) { 
			} // foreach ($tokens as $token) { 			
			return $result;		
		} // if ($code !== false) {
		return false;
	} // function trash ($file = '') {
}

?>
