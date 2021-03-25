<?php
/**
 *
 * The Sendmail script allows DesktopServer to route low-level email to a file in
 * a self purging folder that cleans up itself after 48 hours.
 *
 * @package DesktopServer
 * @since 3.8.0
 */

// Write mail to temp/mail folder
if ( PHP_OS === 'Darwin' ){
	$mail_folder = "/Applications/XAMPP/xamppfiles/temp/mail";
}else{
	$mail_folder = "c:/xampplite/tmp/mail";
}
if (!file_exists($mail_folder)) { mkdir($mail_folder, 0777, true); }
# create a filename for the eml file
$i = 0;
do {
	$filename = $mail_folder . '/'. date('Y-m-d_H.m.s-') . str_pad( strval( $i ), 4, '0', STR_PAD_LEFT) . '.eml';
	$i++;
}while( is_file($filename) === true );

# write the email contents to the file
$email_contents = fopen('php://stdin', 'r');
$fstat = fstat($email_contents);
file_put_contents($filename, $email_contents);

// Clean up files older than 2 days
if (file_exists($mail_folder)) {
	foreach (new DirectoryIterator($mail_folder) as $fileInfo) {
		if ($fileInfo->isDot()) {
			continue;
		}
		if (time() - $fileInfo->getCTime() >= 2*24*60*60) {
			unlink($fileInfo->getRealPath());
		}
	}
}
