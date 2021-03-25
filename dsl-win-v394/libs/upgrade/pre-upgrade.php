<?php
/**
 * Exports all DesktopServer defined databases to SQL text files and changes
 * any MyISAM to InnoDB.
 */

/** Determine the platform and locate the current config json file, temp folders */
$config = "/Users/Shared/.com.serverpress.desktopserver.json";
$mysqldump = "/Applications/XAMPP/xamppfiles/bin/mysqldump";
$temp = "/Applications/XAMPP/xamppfiles/temp";
$upgrade = $temp . "/db-upgrade";
if ( PHP_OS !== 'Darwin' ) {
  $config = "c:\\ProgramData\\DesktopServer\\com.serverpress.desktopserver.json";
  $mysqldump = "c:\\xampplite\\mysql\\bin\\mysqldump.exe";
  $temp = "c:\\xampplite\\tmp";
  $upgrade = $temp . "\\db-upgrade";
}
system("rm -rf ".escapeshellarg($upgrade));
sleep(2);
if ( !is_dir( $upgrade ) ) {
  mkdir( $upgrade );
}

/** Start running services */
echo "Checking services...\n";
if (GetProcessID('mysqld') == 0) {
  echo "Got processID of " . GetProcessID('mysqld');
  if ( PHP_OS == 'Darwin' ) {
  	echo shell_exec('chmod -R 777 /Applications/XAMPP/xamppfiles/var;/Applications/XAMPP/xamppfiles/sbin/mysqld --defaults-file=/Applications/XAMPP/xamppfiles/etc/my.cnf --innodb_force_recovery=3 >/dev/null 2>/dev/null &');
    //echo shell_exec('/Applications/XAMPP/xamppfiles/xampp restart');
  }else{
    echo shell_exec('c:\\xampplite\\xampp_cli restart xampp');
  }
}
sleep(8);

/** Update Documents/Websites to UserHome/Sites */
$json = json_decode( file_get_contents( $config ) );
if (PHP_OS !== 'Darwin') {
  $json->documents = getenv('USERPROFILE') . "\\Sites";
}else{
  $json->documents = getenv('HOME') . '/Sites';
}

// Create Sites if not present
if (FALSE === file_exists($json->documents)) {
  mkdir( $json->documents );
}

// Write back default to sites folder
file_put_contents( $config, json_encode( $json, JSON_PRETTY_PRINT ) );

/** Build credentials SQL script */
$file_no = 1;
$cred = '';
foreach( $json->sites as $site ) {
  $db_user = $site->dbUser;
  $db_name = $site->dbName;
  $db_pass = $site->dbPass;
  if ( $db_user !== '' ) {
      $cred = "CREATE USER '$db_user'@'localhost' IDENTIFIED BY '$db_pass';\n";
      $cred .= "GRANT ALL PRIVILEGES ON $db_name.* TO '$db_user'@'localhost' IDENTIFIED BY '$db_pass';\n";


      /** Dump each defined MySQL database to iterative file */
      $output = $upgrade . "/dsdb-" . $file_no . ".sql";
      if ( file_exists( $output ) ) {
        unlink( $output );
      }
      $exec = $mysqldump . " --user root --add-drop-database --databases " . $db_name . " > " . $output;
      exec( $exec );

      /** Search and replace any ENGINE=MyISAM with ENGINE=InnoDB */
      if ( !is_file( $output) ) continue;
      $target = $output . '.new';
      $sh = fopen($output, 'r');
      $th = fopen($target, 'w');
      while ( !feof( $sh ) ) {
        $line = fgets( $sh );
        if ( $line === false ) break;
        if ( strpos($line, 'ENGINE=MyISAM') !==false ) {
            $line = str_replace( 'ENGINE=MyISAM', 'ENGINE=InnoDB', $line );
        }
        fwrite( $th, $line );
      }

      /** Append original database credentials */
      fwrite( $th, $cred );
      fclose( $sh );
      fclose( $th );
      unlink( $output ); // delete old source file
      rename( $target, $output ); // rename target file to source file

      $file_no++;
  }
}

/** Ensure shutdown of running services */
echo "Stopping services...\n";
if (GetProcessID('mysqld') != 0) {
  echo "Got processID of " . GetProcessID('mysqld');
  if ( PHP_OS == 'Darwin' ) {
    echo shell_exec("kill -9 $(ps aux | grep '/Applications/XAMPP/xamppfiles/bin/[h]ttpd' | awk '{print $2}');/Applications/XAMPP/xamppfiles/xampp stop");
    echo shell_exec('/Applications/XAMPP/xamppfiles/bin/mysqladmin --user=root --password= shutdown');
  }else{
    echo shell_exec('c:\\xampplite\\xampp_cli stop xampp');
    echo shell_exec('c:\\xampplite\\mysql\\bin\\mysqladmin --user=root --password= shutdown');
  }
}
sleep(2);
echo "Dump complete\n";


/**
 * Common string parsing functions
 */
function DelRightMost($sSource, $sSearch) {
  for ($i = strlen($sSource); $i >= 0; $i = $i - 1) {
    $f = strpos($sSource, $sSearch, $i);
    if ($f !== FALSE) {
       return substr($sSource,0, $f);
       break;
    }
  }
  return $sSource;
}
function DelLeftMost($sSource, $sSearch) {
  for ($i = 0; $i < strlen($sSource); $i = $i + 1) {
    $f = strpos($sSource, $sSearch, $i);
    if ($f !== FALSE) {
       return substr($sSource,$f + strlen($sSearch), strlen($sSource));
       break;
    }
  }
  return $sSource;
}
function GetLeftMost($sSource, $sSearch) {
  for ($i = 0; $i < strlen($sSource); $i = $i + 1) {
    $f = strpos($sSource, $sSearch, $i);
    if ($f !== FALSE) {
       return substr($sSource,0, $f);
       break;
    }
  }
  return $sSource;
}
function GetRightMost($sSrc, $sSrch) {
  for ($i = strlen($sSrc); $i >= 0; $i = $i - 1) {
    $f = strpos($sSrc, $sSrch, $i);
    if ($f !== FALSE) {
       return substr($sSrc,$f + strlen($sSrch), strlen($sSrc));
    }
  }
  return $sSrc;
}

/**
 * GetProcessID sName As String - returns the process id by the given name
 * or 0 if none found.
 */
function GetProcessID($sName) {
  $nPID = 0;
  if ( PHP_OS == 'Darwin' ) {
    $sResult = chr(10) . shell_exec('ps -axc');
    $sName = " " . $sName . chr(10);
    if (FALSE !== strpos($sResult, $sName)) {
      $nPID = intval( GetLeftMost(trim(GetRightMost(GetLeftMost($sResult, $sName), chr(10))), " ") );
    }
  } else {
    $sResult = chr(10) . shell_exec('tasklist');
    $sName = chr(10) . $sName . ".";
    if (FALSE !== strpos($sResult, $sName)) {
      $nPID = intval( GetLeftMost(trim(DelLeftMost(GetRightMost($sResult, $sName), " ")), " ") );
    }
  }
  return $nPID;
}


