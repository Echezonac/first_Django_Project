<?php
/**
 * Reloads all DesktopServer SQL text files into a clean database and analyzes each site
 * for conversion from .dev to .dev.cc
 */
 
/** Determine the platform and locate the current config json file, temp folders */
$oldDocs = getenv('HOME') . "/Documents/Websites";
$newDocs = getenv('HOME') . "/Sites";
$config = "/Users/Shared/.com.serverpress.desktopserver.json";
$mysql = "/Applications/XAMPP/xamppfiles/bin/mysql";
$temp = "/Applications/XAMPP/xamppfiles/temp";
$upgrade = $temp . "/db-upgrade";
if ( PHP_OS !== 'Darwin' ) {
  $oldDocs = getenv('USERPROFILE') . "\\Documents\\Websites";
  $newDocs = getenv('USERPROFILE') . "\\Sites";
  $config = "c:\\ProgramData\\DesktopServer\\com.serverpress.desktopserver.json";
  $mysql = "c:\\xampplite\\mysql\\bin\\mysql.exe";
  $temp = "c:\\xampplite\\tmp";
  $upgrade = $temp . "\\db-upgrade";
}

/** Start running services */
echo "Checking services...\n";
if (GetProcessID('mysqld') == 0) {
  if ( PHP_OS == 'Darwin' ) {
    shell_exec('/Applications/XAMPP/xamppfiles/xampp restart');
  }else{
    shell_exec('c:\\xampplite\\xampp_cli restart xampp');
  }
}
sleep(2);

/** Reload the databases from SQL files */
$dir = new DirectoryIterator( $upgrade );
foreach ( $dir as $fileinfo ) {
  if (!$fileinfo->isDot()) {
    if ( substr( $fileinfo->getFilename(), -4) === ".sql" ) {
      echo exec( $mysql . " --user root < " . $fileinfo->getRealPath() );
    }
  }
}

/** Read the preferences file */
if ( !file_exists( $config ) ) return;
$pref = json_decode( file_get_contents( $config ) );

/** Create Sites folder if it does not exist */
if (FALSE === file_exists($newDocs)) {
  mkdir($newDocs);
}

/** Cycle through sites */
foreach ($pref->sites as $site) {
  if (file_exists($site->sitePath)) {

    // Process only .dev sites
    if (GetRightMost($site->siteName, ".") == "dev") {

      // Run wp search-replace on database appending .cc
      if ( PHP_OS == 'Darwin' ) {
        $cmd = "cd \"" . $site->sitePath . "\";";
      }else{
        $cmd = "cd /D \"" . $site->sitePath . "\"&";
      }

      $cmd .= "wp search-replace " . $site->siteName . " " . $site->siteName . ".cc --allow-root --all-tables --quiet";
      echo "Updating database for " . $site->siteName . "\n";
      echo shell_exec($cmd);

      // Perform search and replace in interested files
      echo "Updating files for " . $site->siteName . "\n";
      $files = GetDirContents($site->sitePath);
      foreach ($files as $file) {

        // Omit wp-includes and wp-admin folders
        if (FALSE == strrpos($file, "wp-includes")
        && FALSE == strrpos($file, "wp-admin")
        && FALSE == strrpos($file, "upgrade")) {
          $ext = "|" . GetRightMost($file, ".") . "|" ;
          if (FALSE !== strrpos("|php|js|json|htm|html|shtml|xml|htaccess|css|", $ext)) {
            $data = file_get_contents($file);
            $data = str_replace($site->siteName, $site->siteName . ".cc", $data);

            // Restore file ownership after write if need be
            file_put_contents($file, $data);
          }
        }
      }

      // Rename site path
      echo "Renaming website folder for " . $site->siteName . " to " . $site->siteName . ".cc\n";
      rename($site->sitePath, $site->sitePath . ".cc");
    }

    // Migrate sites that were in Documents/Websites to UserHome/Sites
    if ((FALSE !== strpos($site->sitePath, $oldDocs))) {

      // Move folder to new home
      $oldPath = $site->sitePath;
      $newPath = $newDocs . DelLeftMost($oldPath, $oldDocs) . DIRECTORY_SEPARATOR . $siteName;
  
      // Check existing, iterate unique
      $u = 1;
      while(file_exists($newPath)) {
        $newPath = DelRightMost($newPath, $pref->tld) . $u . $pref->tld;
        $u++;
      }
      
      // To do: Search & replace in wp-cli prior to move operation
      // Run wp search-replace on database appending .cc
      if ( PHP_OS == 'Darwin' ) {
        $cmd = "cd \"" . $site->sitePath . "\";";
      }else{
        $cmd = "cd /D \"" . $site->sitePath . "\"&";
      }

      $cmd .= "wp search-replace \"" . $oldPath . "\" \"" . $newPath . "\" --allow-root --all-tables --quiet";
      echo "Migrating document root for " . $site->sitePath . "\n";
      echo shell_exec($cmd);
      rename($oldPath, $newPath);
      if(substr($newPath, -1) == '\\') {
        $newPath = substr($newPath, 0, -1);
      }
      $pref->sites->{$site->siteName}->sitePath = $newPath;
    }
  }
}

/**  Update the preferences file TLD and site definitions */
if ($pref->tld == '.dev') {
  $pref->tld == '.dev.cc';
}
$data = json_encode($pref, JSON_PRETTY_PRINT);
file_put_contents($config, $data);

/** Shutdown services  */
echo "Stopping services...\n";
if (GetProcessID('mysqld') != 0) {
  if ( PHP_OS == 'Darwin' ) {
    shell_exec('/Applications/XAMPP/xamppfiles/xampp stop');
  }else{
    shell_exec('c:\\xampplite\\xampp_cli stop xampp');
  }
}
sleep(2);

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

/**
 * GetDirContents $path As String - returns all files from a given dir recursively
 */
 function GetDirContents($path) {
     $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));

     $files = array();
     foreach ($rii as $file)
         if (!$file->isDir())
             $files[] = $file->getPathname();

     return $files;
 }
