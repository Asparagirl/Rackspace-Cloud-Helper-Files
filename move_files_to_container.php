<?php

// File Name: 'move_files_to_container.php'
//
// Description: PHP script to automatically copy files out of a particular web server directory and into a Rackspace Cloud Files container
//
// Requirements: PHP, CURL, PHP5-CURL, PECL, and of course the Rackspace Cloud Files PHP binding, which you can get from GitHub
//
// Version: 1.0 -- released September 20, 2012
//
// Credits: Written by Brooke Schreier Ganz (asparagirl -at- dca -dot- net / Twitter: @Asparagirl)
//
// License: Freedom Isn't Free, but this is.  Use it in good health, as my grandma would say.


// display error messages, in case something goes wrong
error_reporting(E_ALL);
ini_set('display_errors','On');

// basic HTML stuff
echo '<html>'."\n";
echo '<head>'."\n";
echo '<title>Move files off the server and into a Cloud Files container</title>'."\n";
echo '</head>'."\n"."\n";
echo '<body>'."\n"."\n";
echo '<h1>Let\'s go!</h1>'."\n";

// you need to have already installed the Cloud Files PHP binding on your server correctly so that this include works
require('cloudfiles.php');

// the following items need to be changed to your own particular information
$username = "YOUR-USERNAME-GOES-HERE"; // your Rackspace Cloud username goes here
$api_key = "ABCDEFGHIJKLMNOPQRSTUVWXYZ"; // your Rackspace Cloud API Key goes here
$container_name = "NAME OF MY CONTAINER"; // name of the Rackspace Cloud Files container goes here
$dir_name = "/var/www/foo/bar"; // path of directory where the files are currently stored, no trailing slash, probably starts with '/var/www/something'

// 5,6,7,8, everyone authenticate!
$auth = new CF_Authentication($username, $api_key);
$auth->authenticate();
$conn = new CF_Connection($auth);

// check to see if your Cloud Files container already exists; if it doesn't, it will be created and made public (to the Akamai CDN)
try { $container = $conn->get_container($container_name); }
catch (NoSuchContainerException $e) {
    $container = $conn->create_container($container_name);
    $container->make_public();
	echo '<p>(Hey there, just FYI: the Cloud Files container name <em>'.$container_name.'</em> didn\'t exist, so it was just created now.)</p>'."\n";
}

// let's keep a count of how many files we're copying over
$count = '1';

// here comes the fun stuff
$handle = opendir($dir_name);
if (is_dir($dir_name)) {
	echo '<h2>FROM Directory: <em>'.$dir_name."</em></h2>\n";
	echo '<h2>TO Rackspace Cloud Files container: <em>'.$container_name."</em></h2>\n";
	while (false !== ($filename = readdir($handle))) {
		if ( ($filename !== '.') && ($filename !== '..') && ($filename !== '.DS_Store') && ($filename !== 'Thumbs.db') && ($filename !== 'move_files_to_container.php') ) { // skips non-files (directories), un-needed files, and skips this file too
			echo 'Processing file #'.$count.': '.$filename."<br />\n";
			$obj = $container->create_object($filename);
			$obj->load_from_filename($dir_name . "/" . $filename);
			set_time_limit(0); // resets the time limit on PHP scripts so that this can keep running on very big jobs
			$count++;
		}
	}
}
closedir($handle);

// fix the count
$count--;

// final HTML stuff
echo '<h3>All done!  '.$count.' files moved to the <em>'.$container_name.'</em> container</h3>'."\n"."\n";
echo '</body>'."\n";
echo '</html>';

// jiggle the handle
flush();

// all done!  Yay!  See, that wasn't so bad.

?>