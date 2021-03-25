<?php
/*

Author: Stephen Carroll, Dave Jesch
Version: 2.4
License: GPLv2
Author URI: http://serverpress.com

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.

*/

// Create our main QuickDeploy object
$qd = new QuickDeploy();
if (isset($_GET['deploying'])){
	$qd->deploy();
	exit();
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<title>Quick Deploy</title>
<style type="text/css">
<!--
body {
	font: 100% Verdana, Arial, Helvetica, sans-serif;
	background: #b0b5b5;
	margin: 25px 0;
	padding: 0;
	text-align: center;
	color: #000000;
}
h1 {
	text-align: center;
	font-size: 25px;
}
.oneColElsCtr #container {
	width: 46em;
	background: #FFFFFF;
	margin: 0 auto;
	border: 1px solid #000000;
	text-align: left;
	border: 8px solid #000000;
}
.oneColElsCtr #mainContent {
	padding: 10px 20px 50px;
	font-size: 10pt;
	border: 8px dashed orange;
	margin:-8px;
}
.shadow {
	-moz-box-shadow: 0px 5px 8px #333;
	-webkit-box-shadow: 0px 5px 8px #333;
	box-shadow: 0px 5px 8px #333;
}
.warn {
	text-decoration: blink;
	color: orange;
}
-->
</style></head>
<body class="oneColElsCtr">
<div id="container" class="shadow">
  <div id="mainContent">
    <h1><span class="warn">!</span>!<span class="warn">!</span>!<span class="warn">!</span> WARNING - WEBSITE WILL BE <u>ERASED</u> <span class="warn">!</span>!<span class="warn">!</span>!<span class="warn">!</span></h1>
<?php	if (! isset($_GET['filename'])) { ?>
		<p>Warning - Quick Deploy will overwrite your website with the contents of your given ZIP archive.</p>
		<p>To use Quick Deploy, ensure that this file (quick-deploy.php) and your valid zip package reside within
			the same folder. Both files should be located in the same folder where you want your WordPress website to be located.
			You should have a working copy of WordPress with a valid wp-config.php file present in the same folder as quick-deploy.php OR
			your ZIP archive must be pre-configured with your host's database credentials if no existing wp-config.php is present. To proceed,
			enter the filename of the zip archive you wish to deploy below and click 'Deploy'. Quick Deploy will do the following:</p>
		<ul>
			<li>Uncompress your ZIP package to a temporary folder.</li>
			<li>ERASE and REPLACE your website files with the contents of the ZIP package.</li>
			<li>ERASE and REPLACE your database with the contents of the ZIP package.</li>
			<li>ERASE your ZIP package, temporary folder, and this file.</li>
		</ul>
		<p>This script is experimental and may not work on all servers - USE AT YOUR OWN RISK.</p>
		<br />
		<div style="text-align:center;">
			<form method="GET" action="<?php echo $_SERVER['SCRIPT_NAME'] ?>"
				  enctype="multipart/form-data">
			<input type="text" name="filename" id="filename" style="border:1px solid #000"/>
			<input type="submit" value="Deploy" onClick="return confirm('Your website will be erased!!! Are you sure you want to continue?');"/>
			</form>
		</div>
<?php	} else {
			if ($qd->fileExists()) { ?>
				<p>Deploying... please wait...</p>
				<iframe src="<?php echo $_SERVER['SCRIPT_NAME'] ?>?filename=<?php echo $_GET['filename'] ?>&deploying=true" width="100%" height="200">
				</iframe>
<?php		} else {
				echo '<p>Unable to locate the ZIP archive "', $_GET['filename'], '". <a href="', $_SERVER['SCRIPT_NAME'], '">Click here to go back.</a>';
			}
		}



	/**
	 * Declare our quick-deploy class
	 **/
	class QuickDeploy {
		const VERSION = '2.4';

		public $zipfile = '';
		public $dbuser = '';
		public $dbname = '';
		public $dbpass = '';
		public $dbhost = '';
		public $dbport = 3306;

		private $_mysqli = NULL;								/// mysqli connnection resource

		function __construct(){
			if ( !empty( $_GET['filename'] ) ) {
				$this->zipfile = $this->delRightMost(__FILE__, '/') . '/' . $_GET['filename'];
			}
		}

		/**
		 * Performs the deployment
		 */
		function deploy(){
			$this->init();
			$this->getCredentials();
			$this->unzip();
			$this->setCredentials();
			$this->getCredentials();
			$this->copy($this->delRightMost($this->zipfile, '.'),
					$this->delRightMost(__FILE__, '/') . '/');
			$this->getCredentials();
			if ( function_exists( 'mysqli_connect' ) ) {
				$this->importDB();
			} else {
				// We're on a *very* old server
				$this->legacyImportDB();
			}
			$this->deleteAll($this->delRightMost($this->zipfile, "."), true);
			$this->delete($this->delRightMost(__FILE__, '/') . '/database.sql');
			// delete other database#.sql files
			for ($idx = 1; ; ++$idx) {
				$filename = $this->delRightMost(__FILE__, '/') . '/database' . $idx . '.sql';
				if (file_exists($filename))
					$this->delete($filename);
				else
					break;
			}
			$this->delete($this->delRightMost(__FILE__, '/') . '/quick-deploy.sql');
			$this->delete($this->delRightMost(__FILE__, '/') . '/quick-deploy.php');
			$this->delete($this->zipfile);
			echo "Done!<script>window.parent.location='/';</script>";
		}

		/**
		 * Initialize anything and everything needed by this class
		 */
		private function init()
		{
			date_default_timezone_set(date_default_timezone_get());
		}

		/**
		 * imports the database file(s) into the configured database. Processes all database*.sql files
		 */
		function importDB()
		{
$this->_log(__METHOD__.'() starting database import');
			$mysqli = mysqli_connect($this->dbhost, $this->dbuser, $this->dbpass, $this->dbname, $this->dbport);
			if (mysqli_connect_errno()){
$this->_log(__METHOD__.'() could not connect ' . mysqli_connect_error());
				echo "Error - Could not connect: " . mysqli_connect_error();
				exit();
			}

			//
			// *** Set up prerequisite to use phpMyAdmin's SQL Import plugin
			//

			if ( FALSE === mysqli_query( $mysqli, 'SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";' ) ){
$this->_log(__METHOD__.'() mysqli error: ' . mysqli_error($mysqli));
				return 'Error - ' . mysqli_error($mysqli);
			}
			$this->_mysqli = $mysqli;

			$idx = '';
$this->_log(__METHOD__.'() start processing files');
			for (;;) {
				$file_name = $this->delRightMost($this->zipfile, '.') . '/database' . $idx . '.sql';
$this->_log(__METHOD__.'() checking file ' . $file_name);
				if (file_exists($file_name)) {
					$this->importDBFile($file_name);
				} else {
					if ('' === $idx)
						$idx = 0;
					else {
$this->_log(__METHOD__.'() file not found: ' . $file_name);
						break;
					}
				}
				++$idx;
			}
$this->_log(__METHOD__.'() done processing files');
		}

		/**
		 * Imports a single database file into the database
		 * @param type $filename
		 */
		function importDBFile($filename)
		{
			$mysqli = $this->_mysqli;
			$buffer = file_get_contents($filename);
			$GLOBALS['finished'] = true;

			// Defaults for parser
			$sql = '';
			$start_pos = 0;
			$i = 0;
			$len= 0;
			$big_value = 2147483647;
			$delimiter_keyword = 'DELIMITER '; // include the space because it's mandatory
			$length_of_delimiter_keyword = strlen($delimiter_keyword);
			$sql_delimiter = ';';

			// Current length of our buffer
			$len = strlen($buffer);

			// Grab some SQL queries out of it
			while ($i < $len) {
				$found_delimiter = false;

				// Find first interesting character
				$old_i = $i;
				// this is about 7 times faster that looking for each sequence i
				// one by one with strpos()
				if (preg_match('/(\'|"|#|-- |\/\*|`|(?i)(?<![A-Z0-9_])' . $delimiter_keyword . ')/', $buffer, $matches, PREG_OFFSET_CAPTURE, $i)) {
					// in $matches, index 0 contains the match for the complete
					// expression but we don't use it
					$first_position = $matches[1][1];
				} else {
					$first_position = $big_value;
				}
				/**
				 * @todo we should not look for a delimiter that might be
				 *		inside quotes (or even double-quotes)
				 */
				// the cost of doing this one with preg_match() would be too high
				$first_sql_delimiter = strpos($buffer, $sql_delimiter, $i);
				if ($first_sql_delimiter === false) {
					$first_sql_delimiter = $big_value;
				} else {
					$found_delimiter = true;
				}

				// set $i to the position of the first quote, comment.start or delimiter found
				$i = min($first_position, $first_sql_delimiter);

				if ($i == $big_value) {
					// none of the above was found in the string

					$i = $old_i;
					if (!$GLOBALS['finished']) {
						break;
					}
					// at the end there might be some whitespace...
					if (trim($buffer) == '') {
						$buffer = '';
						$len = 0;
						break;
					}
					// We hit end of query, go there!
					$i = strlen($buffer) - 1;
				}

				// Grab current character
				$ch = $buffer[$i];

				// Quotes
				if (strpos('\'"`', $ch) !== false) {
					$quote = $ch;
					$endq = false;
					while (!$endq) {
						// Find next quote
						$pos = strpos($buffer, $quote, $i + 1);
						/*
						 * Behave same as MySQL and accept end of query as end of backtick.
						 * I know this is sick, but MySQL behaves like this:
						 *
						 * SELECT * FROM `table
						 *
						 * is treated like
						 *
						 * SELECT * FROM `table`
						 */
						if ($pos === false && $quote == '`' && $found_delimiter) {
							$pos = $first_sql_delimiter - 1;
						// No quote? Too short string
						} elseif ($pos === false) {
							// We hit end of string => unclosed quote, but we handle it as end of query
							if ($GLOBALS['finished']) {
								$endq = true;
								$i = $len - 1;
							}
							$found_delimiter = false;
							break;
						}
						// Was not the quote escaped?
						$j = $pos - 1;
						while ($buffer[$j] == '\\') $j--;
						// Even count means it was not escaped
						$endq = (((($pos - 1) - $j) % 2) == 0);
						// Skip the string
						$i = $pos;

						if ($first_sql_delimiter < $pos) {
							$found_delimiter = false;
						}
					}
					if (!$endq) {
						break;
					}
					$i++;
					// Aren't we at the end?
					if ($GLOBALS['finished'] && $i == $len) {
						$i--;
					} else {
						continue;
					}
				}

				// Not enough data to decide
				if ((($i == ($len - 1) && ($ch == '-' || $ch == '/'))
				  || ($i == ($len - 2) && (($ch == '-' && $buffer[$i + 1] == '-')
					|| ($ch == '/' && $buffer[$i + 1] == '*')))) && !$GLOBALS['finished']) {
					break;
				}

				// Comments
				if ($ch == '#'
				 || ($i < ($len - 1) && $ch == '-' && $buffer[$i + 1] == '-'
				  && (($i < ($len - 2) && $buffer[$i + 2] <= ' ')
				   || ($i == ($len - 1)  && $GLOBALS['finished'])))
				 || ($i < ($len - 1) && $ch == '/' && $buffer[$i + 1] == '*')
						) {
					// Copy current string to SQL
					if ($start_pos != $i) {
						$sql .= substr($buffer, $start_pos, $i - $start_pos);
					}
					// Skip the rest
					$start_of_comment = $i;
					// do not use PHP_EOL here instead of "\n", because the export
					// file might have been produced on a different system
					$i = strpos($buffer, $ch == '/' ? '*/' : "\n", $i);
					// didn't we hit end of string?
					if ($i === false) {
						if ($GLOBALS['finished']) {
							$i = $len - 1;
						} else {
							break;
						}
					}
					// Skip *
					if ($ch == '/') {
						$i++;
					}
					// Skip last char
					$i++;
					// We need to send the comment part in case we are defining
					// a procedure or function and comments in it are valuable
					$sql .= substr($buffer, $start_of_comment, $i - $start_of_comment);
					// Next query part will start here
					$start_pos = $i;
					// Aren't we at the end?
					if ($i == $len) {
						$i--;
					} else {
						continue;
					}
				}
				// Change delimiter, if redefined, and skip it (don't send to server!)
				if (strtoupper(substr($buffer, $i, $length_of_delimiter_keyword)) == $delimiter_keyword
				 && ($i + $length_of_delimiter_keyword < $len)) {
					 // look for EOL on the character immediately after 'DELIMITER '
					 // (see previous comment about PHP_EOL)
				   $new_line_pos = strpos($buffer, "\n", $i + $length_of_delimiter_keyword);
				   // it might happen that there is no EOL
				   if (false === $new_line_pos) {
					   $new_line_pos = $len;
				   }
				   $sql_delimiter = substr($buffer, $i + $length_of_delimiter_keyword, $new_line_pos - $i - $length_of_delimiter_keyword);
				   $i = $new_line_pos + 1;
				   // Next query part will start here
				   $start_pos = $i;
				   continue;
				}

				// End of SQL
				if ($found_delimiter || ($GLOBALS['finished'] && ($i == $len - 1))) {
					$tmp_sql = $sql;
					if ($start_pos < $len) {
						$length_to_grab = $i - $start_pos;

						if (! $found_delimiter) {
							$length_to_grab++;
						}
						$tmp_sql .= substr($buffer, $start_pos, $length_to_grab);
						unset($length_to_grab);
					}
					// Do not try to execute empty SQL
					if (! preg_match('/^([\s]*;)*$/', trim($tmp_sql))) {
						$sql = $tmp_sql;

						//
						// Execute on our connection
						//
						if ( mysqli_query( $mysqli, $sql ) === FALSE ){
							echo 'Error - ' . mysqli_error( $mysqli );
							exit();
						}
						$buffer = substr($buffer, $i + strlen($sql_delimiter));

						// Reset parser:
						$len = strlen($buffer);
						$sql = '';
						$i = 0;
						$start_pos = 0;
						// Any chance we will get a complete query?
						//if ((strpos($buffer, ';') === false) && !$GLOBALS['finished']) {
						if ((strpos($buffer, $sql_delimiter) === false) && !$GLOBALS['finished']) {
							break;
						}
					} else {
						$i++;
						$start_pos = $i;
					}
				}
			} // End of parser loop

//			mysqli_close( $mysqli );
		}

		function legacyImportDB(){
			$buffer = file_get_contents($this->delRightMost($this->zipfile, ".") . '/database.sql');
			$connect = mysql_connect($this->dbhost . ( 3306 !== $this->dbport ? ':' . $this->dbport : ''), $this->dbuser, $this->dbpass);
			if (!$connect){
				echo "Error - Could not connect: " . mysql_error();
				exit();
			}
			mysql_select_db($this->dbname);

			//
			// *** Set up prerequisite to use phpMyAdmin's SQL Import plugin
			//

			if ( mysql_query( 'SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";' ) === FALSE ){
				return 'Error - ' . mysql_error();
			}
			$GLOBALS['finished'] = true;

			// Defaults for parser
			$sql = '';
			$start_pos = 0;
			$i = 0;
			$len= 0;
			$big_value = 2147483647;
			$delimiter_keyword = 'DELIMITER '; // include the space because it's mandatory
			$length_of_delimiter_keyword = strlen($delimiter_keyword);
			$sql_delimiter = ';';

			// Current length of our buffer
			$len = strlen($buffer);

			// Grab some SQL queries out of it
			while ($i < $len) {
				$found_delimiter = false;

				// Find first interesting character
				$old_i = $i;
				// this is about 7 times faster that looking for each sequence i
				// one by one with strpos()
				if (preg_match('/(\'|"|#|-- |\/\*|`|(?i)(?<![A-Z0-9_])' . $delimiter_keyword . ')/', $buffer, $matches, PREG_OFFSET_CAPTURE, $i)) {
					// in $matches, index 0 contains the match for the complete
					// expression but we don't use it
					$first_position = $matches[1][1];
				} else {
					$first_position = $big_value;
				}
				/**
				 * @todo we should not look for a delimiter that might be
				 *		inside quotes (or even double-quotes)
				 */
				// the cost of doing this one with preg_match() would be too high
				$first_sql_delimiter = strpos($buffer, $sql_delimiter, $i);
				if ($first_sql_delimiter === false) {
					$first_sql_delimiter = $big_value;
				} else {
					$found_delimiter = true;
				}

				// set $i to the position of the first quote, comment.start or delimiter found
				$i = min($first_position, $first_sql_delimiter);

				if ($i == $big_value) {
					// none of the above was found in the string

					$i = $old_i;
					if (!$GLOBALS['finished']) {
						break;
					}
					// at the end there might be some whitespace...
					if (trim($buffer) == '') {
						$buffer = '';
						$len = 0;
						break;
					}
					// We hit end of query, go there!
					$i = strlen($buffer) - 1;
				}

				// Grab current character
				$ch = $buffer[$i];

				// Quotes
				if (strpos('\'"`', $ch) !== false) {
					$quote = $ch;
					$endq = false;
					while (!$endq) {
						// Find next quote
						$pos = strpos($buffer, $quote, $i + 1);
						/*
						 * Behave same as MySQL and accept end of query as end of backtick.
						 * I know this is sick, but MySQL behaves like this:
						 *
						 * SELECT * FROM `table
						 *
						 * is treated like
						 *
						 * SELECT * FROM `table`
						 */
						if ($pos === false && $quote == '`' && $found_delimiter) {
							$pos = $first_sql_delimiter - 1;
						// No quote? Too short string
						} elseif ($pos === false) {
							// We hit end of string => unclosed quote, but we handle it as end of query
							if ($GLOBALS['finished']) {
								$endq = true;
								$i = $len - 1;
							}
							$found_delimiter = false;
							break;
						}
						// Was not the quote escaped?
						$j = $pos - 1;
						while ($buffer[$j] == '\\') $j--;
						// Even count means it was not escaped
						$endq = (((($pos - 1) - $j) % 2) == 0);
						// Skip the string
						$i = $pos;

						if ($first_sql_delimiter < $pos) {
							$found_delimiter = false;
						}
					}
					if (!$endq) {
						break;
					}
					$i++;
					// Aren't we at the end?
					if ($GLOBALS['finished'] && $i == $len) {
						$i--;
					} else {
						continue;
					}
				}

				// Not enough data to decide
				if ((($i == ($len - 1) && ($ch == '-' || $ch == '/'))
				  || ($i == ($len - 2) && (($ch == '-' && $buffer[$i + 1] == '-')
					|| ($ch == '/' && $buffer[$i + 1] == '*')))) && !$GLOBALS['finished']) {
					break;
				}

				// Comments
				if ($ch == '#'
				 || ($i < ($len - 1) && $ch == '-' && $buffer[$i + 1] == '-'
				  && (($i < ($len - 2) && $buffer[$i + 2] <= ' ')
				   || ($i == ($len - 1)  && $GLOBALS['finished'])))
				 || ($i < ($len - 1) && $ch == '/' && $buffer[$i + 1] == '*')
						) {
					// Copy current string to SQL
					if ($start_pos != $i) {
						$sql .= substr($buffer, $start_pos, $i - $start_pos);
					}
					// Skip the rest
					$start_of_comment = $i;
					// do not use PHP_EOL here instead of "\n", because the export
					// file might have been produced on a different system
					$i = strpos($buffer, $ch == '/' ? '*/' : "\n", $i);
					// didn't we hit end of string?
					if ($i === false) {
						if ($GLOBALS['finished']) {
							$i = $len - 1;
						} else {
							break;
						}
					}
					// Skip *
					if ($ch == '/') {
						$i++;
					}
					// Skip last char
					$i++;
					// We need to send the comment part in case we are defining
					// a procedure or function and comments in it are valuable
					$sql .= substr($buffer, $start_of_comment, $i - $start_of_comment);
					// Next query part will start here
					$start_pos = $i;
					// Aren't we at the end?
					if ($i == $len) {
						$i--;
					} else {
						continue;
					}
				}
				// Change delimiter, if redefined, and skip it (don't send to server!)
				if (strtoupper(substr($buffer, $i, $length_of_delimiter_keyword)) == $delimiter_keyword
				 && ($i + $length_of_delimiter_keyword < $len)) {
					 // look for EOL on the character immediately after 'DELIMITER '
					 // (see previous comment about PHP_EOL)
				   $new_line_pos = strpos($buffer, "\n", $i + $length_of_delimiter_keyword);
				   // it might happen that there is no EOL
				   if (false === $new_line_pos) {
					   $new_line_pos = $len;
				   }
				   $sql_delimiter = substr($buffer, $i + $length_of_delimiter_keyword, $new_line_pos - $i - $length_of_delimiter_keyword);
				   $i = $new_line_pos + 1;
				   // Next query part will start here
				   $start_pos = $i;
				   continue;
				}

				// End of SQL
				if ($found_delimiter || ($GLOBALS['finished'] && ($i == $len - 1))) {
					$tmp_sql = $sql;
					if ($start_pos < $len) {
						$length_to_grab = $i - $start_pos;

						if (! $found_delimiter) {
							$length_to_grab++;
						}
						$tmp_sql .= substr($buffer, $start_pos, $length_to_grab);
						unset($length_to_grab);
					}
					// Do not try to execute empty SQL
					if (! preg_match('/^([\s]*;)*$/', trim($tmp_sql))) {
						$sql = $tmp_sql;

						//
						// Execute on our connection
						//
						if ( mysql_query( $sql ) === FALSE ){
							echo 'Error - ' . mysql_error();
							exit();
						}
						$buffer = substr($buffer, $i + strlen($sql_delimiter));

						// Reset parser:
						$len = strlen($buffer);
						$sql = '';
						$i = 0;
						$start_pos = 0;
						// Any chance we will get a complete query?
						//if ((strpos($buffer, ';') === false) && !$GLOBALS['finished']) {
						if ((strpos($buffer, $sql_delimiter) === false) && !$GLOBALS['finished']) {
							break;
						}
					} else {
						$i++;
						$start_pos = $i;
					}
				}
			} // End of parser loop

			mysql_close( $dbc );

		}

		/**
		 * Checks to see if the zip file to be deployed exists
		 * @return boolea TRUE if the file exists
		 */
		function fileExists(){
			// TODO: rename to zipFileExists()
			return file_exists($this->zipfile);
		}

		/**
		 * Reads the database credentials from the wp-config file and stores them into class properties
		 */
		function getCredentials()
		{
$this->_log(__METHOD__.'() looking for config file ' . $this->delRightMost(__FILE__, '/') . '/wp-config.php');
			// Read our wp-config.php for database credentials
			if (! file_exists($this->delRightMost(__FILE__, '/') . '/wp-config.php'))
				return;
			$h = fopen($this->delRightMost(__FILE__, '/') . '/wp-config.php', 'r');
			$dbc = fread($h, filesize($this->delRightMost(__FILE__, '/') . '/wp-config.php'));
			fclose($h);

			// Extract the credentials
			$this->dbname = $this->getLeftMost(
								$this->delLeftMost(
									$this->delLeftMost($dbc, "'DB_NAME'"), "'"),"'");
			$this->dbuser = $this->getLeftMost(
								$this->delLeftMost(
									$this->delLeftMost($dbc, "'DB_USER'"), "'"),"'");
			$this->dbpass = $this->getLeftMost(
								$this->delLeftMost(
									$this->delLeftMost($dbc, "'DB_PASSWORD'"), "'"),"'");
			$this->dbhost = $this->getLeftMost(
								$this->delLeftMost(
									$this->delLeftMost($dbc, "'DB_HOST'"), "'"),"'");
			// check for port reference within the host name
			if ( FALSE !== ($pos = strpos( $this->dbhost, ':' ) ) ) {
				$this->dbport = abs( substr( $this->dbhost, $pos + 1 ) );
				$this->dbhost = substr( $this->dbhost, 0, $pos );
			}
		}

		/**
		 * Updates the wp-config file with database credentials from class properties
			*/
		   function setCredentials()
		   {
			   // Set the credentials in the extract wp-config.php file (if we had one prior)
			   if (! file_exists($this->delRightMost(__FILE__, '/') . '/wp-config.php')) return;
			   $h = fopen($this->delRightMost($this->zipfile, ".") . '/wp-config.php', 'r');
			   $dbc = fread($h, filesize($this->delRightMost($this->zipfile, ".") . '/wp-config.php'));
			   fclose($h);
			   $newfile = "";
			   $newfile = $this->getLeftMost($dbc, "'DB_NAME'") . "'DB_NAME', '" . $this->dbname . "');\n";
			   $dbc = $this->delLeftMost($dbc, "'DB_NAME'");
			   $dbc = $this->delLeftMost($dbc, ");");
			   $newfile .= $this->getLeftMost($dbc, "'DB_USER'") . "'DB_USER', '" . $this->dbuser . "');\n";
			   $dbc = $this->delLeftMost($dbc, "'DB_USER'");
			   $dbc = $this->delLeftMost($dbc, ");");
			   $newfile .= $this->getLeftMost($dbc, "'DB_PASSWORD'") . "'DB_PASSWORD', '" . $this->dbpass . "');\n";
			   $dbc = $this->delLeftMost($dbc, "'DB_PASSWORD'");
			   $dbc = $this->delLeftMost($dbc, ");");

			$host = $this->dbhost;
			if ( 3306 !== $this->dbport )
				$host .= ':' . $this->dbport;
$this->_log(__METHOD__.'() setting host to "' . $host . '"');
			$newfile .= $this->getLeftMost($dbc, "'DB_HOST'") . "'DB_HOST', '" . $host . "');\n";
			$dbc = $this->delLeftMost($dbc, "'DB_HOST'");
			$dbc = $this->delLeftMost($dbc, ");");
			$newfile .= $dbc;

			// Write the file with updates
			$h = fopen($this->delRightMost($this->zipfile, ".") . '/wp-config.php', 'w');
			if (fwrite($h, $newfile)===false){
				echo "Error - Unable to write updated wp-config file.";
				exit();
			}
			fclose($h);
		}

		/**
		 * Unzips the deployment package into a folder who name matches the .zip file
		 */
		function unzip()
		{
			$folder = $this->delRightMost($this->zipfile,".");
$this->_log(__METHOD__.'() folder=' . $folder);
			if ( file_exists($folder)) {
$this->_log(__METHOD__.'() target folder already exists...skipping unzip step');
				return;
			}
			$zip = new ZipArchive;
			if ( TRUE === $zip->open( $this->zipfile ) ) {
				$zip->extractTo(dirname(__FILE__));
				if ( ! file_exists($folder)) {
$this->_log(__METHOD__.'() error- zip failed to unzip');
					echo "Error - ZIP file failed to unzip into folder.";
					exit();
				  }
				  $zip->close();
			   } else {
$this->_log(__METHOD__.'() error- failed to open');
				echo "Error - ZIP library failed to open the archive.";
				exit();
			}
$this->_log(__METHOD__.'() unzip complete');
		}

		/**
		 * Recursive file copy
		 * @param string $source The source directory name
		 * @param string $target The target directory name
		 */
		function copy( $source, $target ) {
			if ( is_dir( $source ) ) {
				@mkdir( $target, 0755, true );
				$d = dir( $source );
				while ( FALSE !== ( $entry = $d->read() ) ) {
						if ( $entry == '.' || $entry == '..' ) {
								continue;
						}
						$Entry = $source . '/' . $entry;
						if ( is_dir( $Entry ) ) {
								$this->copy( $Entry, $target . '/' . $entry );
								continue;
						}
						@copy( $Entry, $target . '/' . $entry );
						@chmod("{$target}/{$entry}", 0644);
				}
				$d->close();
			} else {
				copy( $source, $target );
			}
		}

		/**
		 * Deletes the specified file
		 * @param type $file
		 */
		function delete($file){
			if (file_exists($file)){
				unlink($file);
			}
		}

		/**
		 * Performs a recursive directory delete
		 * @param string $directory The directory name to remove files from; the contents will be deleted
		 * @param boolean $empty If TRUE will also remove the specified directory
		 * @return boolean Always returns TRUE
		 */
		function deleteAll($directory, $empty = false) {
			$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory), RecursiveIteratorIterator::CHILD_FIRST);
			foreach ($iterator as $path) {
				if ($path->isDir()) {
						@rmdir($path->__toString());
				} else {
						unlink($path->__toString());
				}
			}
			if ($empty) {
					if (!@rmdir($directory)) {
							return false;
					}
			}
			return true;
		}

		/**
		 * String manipulation tool. Removes all characters before and including the search value
		 * @param string $sSource The string to modify
		 * @param string $sSearch The content within $sSource to search for
		 * @return string Eveything after, but not including the $sSearch string. If the $sSearch string is not found, the original string is returned
		 */
		function delLeftMost($sSource, $sSearch) {
		  for ($i = 0; $i < strlen($sSource); $i = $i + 1) {
			$f = strpos($sSource, $sSearch, $i);
			if ($f !== FALSE) {
			   return substr($sSource,$f + strlen($sSearch), strlen($sSource));
			   break;
			}
		  }
		  return $sSource;
		}

		/**
		 * String manipulation tool. Gets the contents of the string up to, and icluding the search string
		 * @param string $sSource The string to search within
		 * @param string $sSearch The string to search for
		 * @return string The left most portion of the string that contains the $sSearch string. If the $sSearch string is not found, the entire string is returned.
		 */
		function getLeftMost($sSource, $sSearch) {
			// TODO: refactor to remove the loop as strpos() returns the first occurance anyway
			for ($i = 0; $i < strlen($sSource); $i++) {
				$f = strpos($sSource, $sSearch, $i);
				if (FALSE !== $f) {
					return substr($sSource,0, $f);
					break;
				}
			}
		  return $sSource;
		}

		/**
		 * String manipulation too. Gets the 
		 * @param type $sSource
		 * @param type $sSearch
		 * @return type
		 */
		function delRightMost($sSource, $sSearch) {
			// TODO: refactor to use strrchr()
			for ($i = strlen($sSource); $i >= 0; $i--) {
				$f = strpos($sSource, $sSearch, $i);
				if (FALSE !== $f) {
					return substr($sSource,0, $f);
					break;
				}
			}
			return $sSource;
		}

		/**
		 * Perform logging
		 * @param string $msg The message to be logged
		 * @param boolean $backtrace If set to TRUE will log a backtrace as well
		 */
		private function _log($msg, $backtrace = FALSE)
		{
//return;
			$file = dirname(__FILE__) . '/~log.txt';
			$fh = @fopen($file, 'a+');
			if (FALSE !== $fh) {
				if (NULL === $msg)
					fwrite($fh, date('Y-m-d H:i:s'));
				else
					fwrite($fh, date('Y-m-d H:i:s - ') . $msg . "\r\n");

				if ($backtrace) {
					$callers = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
					array_shift($callers);
					$path = dirname(dirname(dirname(plugin_dir_path(__FILE__)))) . DIRECTORY_SEPARATOR;

					$n = 1;
					foreach ($callers as $caller) {
						$func = $caller['function'] . '()';
						if (isset($caller['class']) && !empty($caller['class'])) {
							$type = '->';
							if (isset($caller['type']) && !empty($caller['type']))
								$type = $caller['type'];
							$func = $caller['class'] . $type . $func;
						}
						$file = isset($caller['file']) ? $caller['file'] : '';
						$file = str_replace('\\', '/', str_replace($path, '', $file));
						if (isset($caller['line']) && !empty($caller['line']))
							$file .= ':' . $caller['line'];
						$frame = $func . ' - ' . $file;
						$out = '    #' . ($n++) . ': ' . $frame . PHP_EOL;
						fwrite($fh, $out);
						if (self::$_debug_output)
							echo $out;
					}
				}

				fclose($fh);
			}
		}
	}

	if ( !class_exists('ZipArchive', FALSE ) ) {
		echo '<div style="margin:10px; border: 2px solid red; padding: 5px">Class ZipArchive is not defined.</div>';
	} ?>
	<div style="float:right; margin-right: 10px">v<?php echo QuickDeploy::VERSION; ?></div>
  </div>
</div>
</body>
</html>
