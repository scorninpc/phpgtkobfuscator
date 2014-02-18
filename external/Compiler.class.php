<?php

//+----------------------------------------------------------------------+
//| The (sort of) PHP Compiler v0.1                                      |
//+----------------------------------------------------------------------+
//| Copyright (c) 2006 Warren Smith ( smythinc 'at' gmail 'dot' com )    |
//+----------------------------------------------------------------------+
//| This library is free software; you can redistribute it and/or modify |
//| it under the terms of the GNU Lesser General Public License as       |
//| published by the Free Software Foundation; either version 2.1 of the |
//| License, or (at your option) any later version.                      |
//|                                                                      |
//| This library is distributed in the hope that it will be useful, but  |
//| WITHOUT ANY WARRANTY; without even the implied warranty of           |
//| MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU    |
//| Lesser General Public License for more details.                      |
//|                                                                      |
//| You should have received a copy of the GNU Lesser General Public     |
//| License along with this library; if not, write to the Free Software  |
//| Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307 |
//| USA                                                                  |
//+----------------------------------------------------------------------+
//| Simple is good.                                                      |
//+----------------------------------------------------------------------+
//

/*
  +----------------------------------------------------------------------+
  | Package: The (sort of) PHP Compiler v0.1                             |
  | Class  : Compiler                                                    |
  | Created: 06/07/2006                                                  |
  +----------------------------------------------------------------------+
*/

class Compiler {

    /*-------------------*/
    /* V A R I A B L E S */
    /*-------------------*/

    /**
    * array
    *
    * Holds the source code for each file in an array
    */
    var $SourceCode = array();

    /**
    * string
    *
    * Holds the compiled code
    */
    var $CompiledCode = '';

    /**
    * string
    *
    * The name of the variable that holds the obfuscated code
    */
    var $VariableName = 'x';

    /**
    * integer
    *
    * The number of characters to have per line in the obfuscated source (not including indent)
    */
    var $CharsPerLine = 82;

    /**
    * string
    *
    * This is a quick and dirty tag to use in PHP code strings so we know where to inject code
    */
    var $InsertCodeTag = '__C0D3__';

    /**
    * string
    *
    * This is the opening shebang line (if any) for the final code
    */
    var $ShebangLine = '';

    /**
    * string
    *
    * This is the opening PHP tag for the final code
    */
    var $OpenPHPTag = '<?';

    /**
    * string
    *
    * This is the comment block (if any) to put into the final code
    */
    var $HeaderComment = '';

    /**
    * string
    *
    * This is the closing PHP tag for the final code
    */
    var $ClosePHPTag = '?>';

    /*-------------------*/
    /* F U N C T I O N S */
    /*-------------------*/

    /*
      +------------------------------------------------------------------+
      | Constructor                                                      |
      |                                                                  |
      | @return void                                                     |
      +------------------------------------------------------------------+
    */

    function Compiler($sourceFiles = '', $outputFile = '', $filters = ''){

	    // Run forever
	    set_time_limit(0);

	    // If we got the source files argument
	    if ($sourceFiles){

	    	// Compile the source files supplied
	    	$this->Compile($sourceFiles, $outputFile, $filters);
    	}
    }

    /*
      +------------------------------------------------------------------+
      | This is where all the magic happens, "compile", compress, filter |
      |                                                                  |
      | @return void                                                     |
      +------------------------------------------------------------------+
    */

    function Compile($sourceFiles, $outputFile = '', $filters = ''){

	    // Load the source files
	    $this->Load($sourceFiles);

	    // If we have no source code to play with
	    if (!count($this->SourceCode)){

		    // Report the fatal error to the user
		    $this->FatalError('Could not load any PHP source code to compile');
	    }

	    // Rewrite all the available source fragments into one source fragment
	    $this->Rewrite();

	    // Compress the code
	    $this->Compress();

	    // Filter the code
	    $this->Filter($filters);

	    // Write the output to a file (if any) and return the output too
	    return $this->Output($outputFile);
    }

    /*
      +------------------------------------------------------------------+
      | Load the source files into memory so we can clean and join code  |
      |                                                                  |
      | @return boolean                                                  |
      +------------------------------------------------------------------+
    */

    function Load($sourceFiles){

	    // If we have an array as source files
	    if (is_array($sourceFiles)){

		    // Then loop through each one
		    foreach ($sourceFiles as $SourceFile){

			    // Load the file into memory
			    $this->Load($SourceFile);
		    }

		// If we have a string
	    } elseif (is_string($sourceFiles)){

		    // If the string contains a space character or a comma
		    if (strstr($sourceFiles, ' ') || strstr($sourceFiles, ',')){

			    // Replace all spaces with commas
			    $sourceFiles = str_replace(' ', ',', $sourceFiles);

			    // Lets blow this mother fucker up pinky!
			    $sourceFiles = explode(',', $sourceFiles);

			    // Loop through each item in the array
			    foreach ($sourceFiles as $SourceFile){

				    // If we aren't dealing with an empty string
				    if (strlen($SourceFile) > 0){

					    // Run this single string through the Load function again
					    $this->Load($SourceFile);
				    }
			    }

			// If we have a asterix in the string
		    } elseif (strstr($sourceFiles, '*')){

			    // Then we must be globbing
				foreach (glob($sourceFiles) as $SourceFile){

					// Load the source code for this file
					$this->LoadSourceCode($SourceFile);
				}

				// We were probably successfull
				return TRUE;

		    } else {

			    // Load the source code for this file
			    $this->LoadSourceCode($sourceFiles);

			    // If we got here we were probably successfull
			    return TRUE;
		    }
		}

		// If we got here something funny happened
		return FALSE;
    }

    /*
      +------------------------------------------------------------------+
      | Attempt to load the source file into memory (if it exists)       |
      |                                                                  |
      | @return boolean                                                  |
      +------------------------------------------------------------------+
    */

    function LoadSourceCode($sourceFile){

	    // If the source file exists
	    if (file_exists($sourceFile)){

		    // Read the file into the next element in the array
		    array_push($this->SourceCode, trim(file_get_contents($sourceFile)));

		    // Success
		    return TRUE;

	    } else {

		    // Failure
		    return FALSE;
	    }
    }

    /*
      +------------------------------------------------------------------+
      | Rewrite all the code fragments we have into one large file       |
      |                                                                  |
      | @return void                                                     |
      +------------------------------------------------------------------+
    */

    function Rewrite(){

	    // This is to ensure backwards compatibility between PHP 4 and PHP 5 comments
	    if (!defined('T_ML_COMMENT')){ define('T_ML_COMMENT', T_COMMENT); } else { define('T_DOC_COMMENT', T_ML_COMMENT); }

	    // Loop through each source code fragment
	    foreach ($this->SourceCode as $SourceCode){

			// Create tokens from the source code
			$Tokens = token_get_all($SourceCode);

			// Loop through each token
			foreach ($Tokens as $Token){

				// If the token is a string
				if (is_string($Token)){

					// Then add it
					$this->CompiledCode .= $Token;

				} else {

					// Grab the token name and value
					list($TokenType, $TokenValue) = $Token;

					// Handle each token type differently
					switch ($TokenType){

						// PHP open tag
						case T_OPEN_TAG:

							// Don't do anything
							break;

						// PHP close tag
						case T_CLOSE_TAG:

							// Don't do anything
							break;

						// Comments
			            case T_COMMENT:
			            case T_ML_COMMENT:
			            case T_DOC_COMMENT:

			            	// Don't do anything
			            	break;

			            // Whitespaces
			            case T_WHITESPACE:
			            case T_ENCAPSED_AND_WHITESPACE:

			            	// If we are in HEREDOC or INLINEHTML modes
			            	if ($HereDoc){

				            	// Just add the whitespace without messign with it
				            	$this->CompiledCode .= $TokenValue;

			            	} else {

				            	// If the last character wasn't a white space
				            	if ($LastToken != T_WHITESPACE){

				            		// Just use a space
				            		$this->CompiledCode .= ' ';
			            		}
		            		}
			            	break;

			            // Start of heredoc statement
			            case T_START_HEREDOC:

			            	// We're now in HEREDOC mode
			            	$HereDoc = TRUE;

			            	// Add the HEREDOC and a newline after
			            	$this->CompiledCode .= $TokenValue."\n";
			            	break;

			            // End of heredoc statement
			            case T_END_HEREDOC:

			            	// End of HEREDOC mode
			            	$HereDoc = FALSE;

			            	// Add the HEREDOC and a newline before and after
			            	$this->CompiledCode .= "\n".$TokenValue."\n";
			            	break;

			            // Inline HTML
			            case T_INLINE_HTML:

			            	// Increment the HEREDOC counter
			            	$HereCount++;

			            	// Embed the HTML chunk with HEREDOC syntax
			            	$this->CompiledCode .= "\n".'echo <<< H'.$HereCount."\n".$TokenValue."\n".'H'.$HereCount.';'."\n";
			            	break;

						// Everything else we need
						default:

							// Add this token value to the final code with a trailing space
							$this->CompiledCode .= $TokenValue;
							break;
					}

					// Set the last token type to the one we just did
					$LastToken = $TokenType;
				}
			}
	    }

	    // Add PHP opening and closing tags
	    $this->CompiledCode = '<?'.$this->CompiledCode.'?>';
    }

    /*
      +------------------------------------------------------------------+
      | This will compress the code with gzip, it requires zlib          |
      |                                                                  |
      | @return void                                                     |
      +------------------------------------------------------------------+
    */

    function Compress(){

	    // Compress the code with gzip
	    $Code = base64_encode(gzdeflate($this->GetCompiledCode()));

	    // This is the code to reverse obfuscation and run the code
	    $reverseCode  = 'gzinflate(base64_decode('.$this->InsertCodeTag.'))';

	    // Build the code all nice and formatted
	    $Code = $this->MakeCode($Code, $reverseCode);

	    // Save the changes made to the obfuscated code
	    $this->SetCompiledCode($Code);
    }

    /*
      +------------------------------------------------------------------+
      | This will run the code thus far through some obfuscation filters |
      |                                                                  |
      | @return void                                                     |
      +------------------------------------------------------------------+
    */

    function Filter($filters = ''){

	    // If we have filters
	    if ($filters){

		    // This is the code we will be filterin
		    $Code = $this->GetCompiledCode();

		    // If we have an array of filters
		    if (is_array($filters)){

			    // If the array looks like a lonely object + method
			    if (count($filters) == 2 && is_object($filters[0])){

				    // If the specified method exists
				    if (method_exists($filters[0], $filters[1])){

					    // Then filter the code through it
						$Code = call_user_func(array($filters[0], $filters[1]), $Code);
				    }

			    } else {

				    // Loop through each row in the array
				    foreach ($filters as $Filter){

					    // If we have an array
					    if (is_array($Filter)){

						    // If the specified method exists
						    if (method_exists($Filter[0], $Filter[1])){

							    // Then filter the code through it
								$Code = call_user_func(array($Filter[0], $Filter[1]), $Code);
						    }

						// If we have a string
					    } elseif (is_string($Filter)) {

						    // If we have a built in filter method by this name
						    if (method_exists($this, $Filter.'Filter')){

							    // Then filter the source through it
							    $Code = call_user_func(array($this, $Filter.'Filter'), $Code);

							// If we have a function that matches this filter name
						    } elseif (function_exists($Filter)){

							    // Then filter the source through it
							    $Code = call_user_func($Filter, $Code);
						    }
					    }
				    }
			    }

		    } else {

			    // If we have a built in filter method by this name
			    if (method_exists($this, $filters.'Filter')){

				    // Then filter the source through it
				    $Code = call_user_func(array($this, $filters.'Filter'), $Code);

				// If we have a function that matches this filter name
			    } elseif (function_exists($filters)){

				    // Then filter the source through it
				    $Code = call_user_func($filters, $Code);
			    }
		    }

		    // Save the changes made to the obfuscated code
		    $this->SetCompiledCode($Code);
	    }
    }

    /*
      +------------------------------------------------------------------+
      | Gets the code so far in the obfuscation proccess                 |
      |                                                                  |
      | @return string                                                   |
      +------------------------------------------------------------------+
    */

    function GetCompiledCode(){

	    // Grab the stuff between the PHP tags in the compiled code
	    ereg('<\?(.*)\?>', $this->CompiledCode, $Code);

	    // If we have a header comment block
	    if (strlen($this->HeaderComment) > 0){

		    // Then remove it from the code
		    $Code[1] = str_replace("\n".$this->HeaderComment."\n", '', $Code[1]);
	    }

	    // Return the first (and hopefully only) PHP chunk we have
	    return $Code[1];
    }

    /*
      +------------------------------------------------------------------+
      | Sets the code thus far in the compilation process                |
      |                                                                  |
      | @return string                                                   |
      +------------------------------------------------------------------+
    */

    function SetCompiledCode($code){

	    // If we have a shebang line
	    if (strlen($this->ShebangLine) > 0){

		    // Then set the shebang string
		    $Shebang = $this->ShebangLine."\n";
	    }

	    // If we have a header comment for the final file
	    if ($this->HeaderComment){

		    // Then set the header string
		    $Header = "\n".$this->HeaderComment."\n";
	    }

	    // Set the compiled code to the new code with PHP tags and stuff
	    $this->CompiledCode = $Shebang.$this->OpenPHPTag.$Header.$code.$this->ClosePHPTag;
    }

    /*
      +------------------------------------------------------------------+
      | This will format a string or array of values into pretty code    |
      |                                                                  |
      | @return string                                                   |
      +------------------------------------------------------------------+
    */

    function MakeCode($codeVar, $reverseCode = '', $escapeWith = ''){

	    // This is the left side of the assign statement to put obfuscated code in a variable
	    $AssignVar = "\$".$this->VariableName.'=';

	    // If we have a header comment
	    if (strlen($this->HeaderComment) > 0){

		    // Then build the indentation without the opening PHP tag
		    $Indent = str_pad('', strlen($AssignVar), ' ');

	    } else {

		    // Build the indentation with the opening PHP tag
		    $Indent = str_pad('', (strlen($AssignVar) + strlen($this->OpenPHPTag)), ' ');
	    }

	    // If we don't have an array of values
	    if (!is_array($codeVar)){

		    // Then copy the (assumed) string to a new variable
		    $TmpString = $codeVar;

		    // Clean the mixed var variable
		    unset($codeVar);

		    // Loop through each character in the string
		    for ($i = 0; $i < strlen($TmpString); $i++){

			    // Add the character to the array we have to build
			    $codeVar[] = $TmpString[$i];
		    }

		    // We're done with the temp string
		    unset($TmpString);
	    }

	    // Initiate the character counter (per line)
	    $i = 0;

	    // Initiate the return string
	    $return = $AssignVar;

	    // Loop through each value of the array
	    foreach ($codeVar as $Value){

		    // If this is the first character on this line
		    if ($i == 0){

			    // If this is not the first line in the code
			    if (strlen($return) > strlen($AssignVar)){

				    // Then indent this line
				    $return .= $Indent;
			    }

			    // Add the opening quote for this line
			    $return .= '"';
		    }

		    // Add the escape string (if any) to the value and add it to the current line
		    $return .= $escapeWith.$Value;

		    // Increment the character counter
		    $i += strlen($escapeWith.$Value);

		    // If this is the last character for this line
		    if ($i >= $this->CharsPerLine){

			    // Reset the character counter
			    $i = 0;

			    // End the line
			    $return .= '".'."\n";
		    }
	    }

	    // If we weren't on the last character for that line when we ended the loop
	    if ($i > 0){

		    // Then add the closing quote and line deliminator
		    $return .= '";';

	    } else {

		    // Get rid of the last 2 characters of the string and add the line deliminator
		    $return = substr($return, 0, -2).';';
	    }

	    // This is the minimal reverse string
	    $Reverse = 'eval('.$this->InsertCodeTag.');';

	    // If we have user specified reverse code
	    if ($reverseCode){

		    // Replace the insert code tag in the minimum reverse code with our code
		    $Reverse = str_replace($this->InsertCodeTag, $reverseCode, $Reverse);
	    }

	    // Replace the insert code tag in the reverse code string
	    $Reverse = str_replace($this->InsertCodeTag, "\$".$this->VariableName, $Reverse);

	    // If we can add the reverse code to this line comfortably
	    if ($this->CharsPerLine >= ($i + strlen($Reverse))){

		    // Then add it
		    $return .= ' '.$Reverse;

	    } else {

		    // Add a newline and the indentation first
		    $return .= "\n".$Indent.$Reverse;
	    }

	    // And we're done here
	    return $return;
    }

    /*
      +------------------------------------------------------------------+
      | Output the compiled code to a file or the output stream          |
      |                                                                  |
      | @return string                                                   |
      +------------------------------------------------------------------+
    */

    function Output($outputFile = ''){

	    // If we have code to write
	    if ($this->CompiledCode){

		    // If we have an output file
		    if ($outputFile){

			    // Then attempt to open a pointer to the output file
			    $pointer = fopen($outputFile, 'w+');

			    // If we have a file pointer
			    if ($pointer){

				    // Then write the obfiscated code to the file
				    fwrite($pointer, $this->CompiledCode);

				    // Close the file pointer
				    fclose($pointer);

			    } else {

				    // Fatal Error
				    $this->FatalError('Could not open "'.$outputFile.'" for writing');
			    }
		    }

	    } else {

		    // Fatal Error
		    $this->FatalError('There is no compiled code to output to "'.$outputFile.'"');
	    }

	    // Just return the output
	    return $this->CompiledCode;
    }

    /*
      +------------------------------------------------------------------+
      | This will print an error                                         |
      |                                                                  |
      | @return void                                                     |
      +------------------------------------------------------------------+
    */

    function Error($msg){

	    // Show the error
	    echo('[-] '.trim($msg)."\n");
    }

    /*
      +------------------------------------------------------------------+
      | This will print an error and stop code execution                 |
      |                                                                  |
      | @return void                                                     |
      +------------------------------------------------------------------+
    */

    function FatalError($msg){

	    // Show the error
	    $this->Error('Fatal Error: '.$msg);

	    // Exit
	    exit();
    }

    /*---------------*/
    /* F I L T E R S */
    /*---------------*/

    /*
      +------------------------------------------------------------------+
      | OMG yo, that shit is like sooooo leet, turns source into hex     |
      |                                                                  |
      | @return string                                                   |
      +------------------------------------------------------------------+
    */

    function LeetFilter($code){

		// Loop through each character in the code
	    for ($i = 0; $i < strlen($code); $i++){

		    // Put the hex value of this character in the array
		    $HexValues[] = dechex(ord($code[$i]));
	    }

	    // This will turn the HexValues array into code
	    return $this->MakeCode($HexValues, '', '\\x');
    }

    /*
      +------------------------------------------------------------------+
      | Turns the source code into an octal escaped string               |
      |                                                                  |
      | @return string                                                   |
      +------------------------------------------------------------------+
    */

    function OctalFilter($code){

		// Loop through each character in the code
	    for ($i = 0; $i < strlen($code); $i++){

		    // Put the octal value of this character in the array
		    $OctValues[] = str_pad(decoct(ord($code[$i])), 3, '0', STR_PAD_LEFT);
	    }

	    // This will turn the hexValues array into code
	    return $this->MakeCode($OctValues, '', '\\');
    }
}

?>
