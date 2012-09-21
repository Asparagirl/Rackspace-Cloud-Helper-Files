<?php 

// File Name: 'generate_thumbnails_then_move_files_to_container.php'
//
// Description: PHP script to automatically make thumbnails of images in a particular web server directory and then copy everything into a Rackspace Cloud Files container
//
// Requirements: PHP, CURL, PHP5-CURL, PECL, GD, and of course the Rackspace Cloud Files PHP binding, which you can get from GitHub
//
// Version: 1.0 -- released September 20, 2012
//
// Credits: Written by Brooke Schreier Ganz (asparagirl -at- dca -dot- net / Twitter: @Asparagirl), 
// but some of the image generation section was adapted from freely downloadable code from this page: 'http://icant.co.uk/articles/phpthumbnails/'
//
// License: Freedom Isn't Free, but this is.  Use it in good health, as my grandma would say.


// display error messages, in case something goes wrong
error_reporting(E_ALL);
ini_set('display_errors','On');

// basic HTML stuff
echo '<html>'."\n";
echo '<head>'."\n";
echo '<title>Make thumbnail images and then move files off the server and into a Cloud Files container</title>'."\n";
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



/* START THE IMAGES SECTION */


// let's keep a count of how many files we're going to be resizing
$count = '1';

// this tells the script 'you are here'
$imagefolder = '.';
$thumbsfolder = '.';

// define the suffixes for your newly created images, i.e. foo_thumbnail.jpg and foo_medium.jpg
$suffixformatthumbnail = '_thumbnail';
$suffixformatmedium = '_medium';

// get all JPG's and PNG's in your directory
$pics = directory($imagefolder,"jpg,JPG,JPEG,jpeg,png,PNG");

// start thumbnail creation
if ($pics[0]!="") {
	foreach ($pics as $p) {
		
		// get the image filename without the extension
		$pathinfo = pathinfo($p);
		$filename = basename($p,'.'.$pathinfo['extension']);
		$fileextension = $pathinfo['extension'];
		
		// now assemble the full thumbnail filename...
		$newfilename = $filename.$suffixformatthumbnail.'.'.$fileextension;
		// ...and assemble the full mediumsize filename
		$newfilename2 = $filename.$suffixformatmedium.'.'.$fileextension;
		
		// make a thumbnail image with max width 280 pixels and/or max height 380 pixels
		echo 'Creating thumbnail image #'.$count.': <em>'.$newfilename.'</em>';
		createthumb($p,$newfilename,280,380);
		set_time_limit(0); // resets the time limit on PHP scripts so that this can keep running on very big jobs
		
		// make a medium size image with max width 920 pixels and/or max height 750 pixels
		echo 'Creating medium size image #'.$count.': <em>'.$newfilename2.'</em>';
		createthumb($p,$newfilename2,920,750);
		set_time_limit(0); // resets the time limit on PHP scripts so that this can keep running on very big jobs
		
		// increase the count
		$count++;
	}
}

// Part of this is adapted from freely downloadable code from this web page: 'http://icant.co.uk/articles/phpthumbnails/'
// ...except that I fixed the proportionality of the generated images
function createthumb($name,$newfilename,$new_w,$new_h) {
	$system=explode(".",$name);
	if (preg_match("/jpg|jpeg/",$system[1])){$src_img=imagecreatefromjpeg($name);}
	if (preg_match("/png/",$system[1])){$src_img=imagecreatefrompng($name);}
	
	// get original height and width
	$old_x=imageSX($src_img);
	$old_y=imageSY($src_img);
	
	// deal with the ratio correctly
	$ratio_orig = $old_x/$old_y;
	if ($new_w/$new_h > $ratio_orig) {
	   $thumb_w = $new_h*$ratio_orig;
	   $thumb_h = $new_h;
	} else {
	   $thumb_h = $new_w/$ratio_orig;
	   $thumb_w = $new_w;
	}
	$dst_img=ImageCreateTrueColor($thumb_w,$thumb_h);
	
	// make a pretty picture!
	imagecopyresampled($dst_img,$src_img,0,0,0,0,$thumb_w,$thumb_h,$old_x,$old_y); 
	if (preg_match("/png/",$system[1])) {
		imagepng($dst_img,$newfilename); 
	} else {
		imagejpeg($dst_img,$newfilename); 
	}
	
	// echo the new height and width to the screen
	echo ' ('.round($thumb_h,0).' pixels tall x '.round($thumb_w,0).' pixels wide)<br />'."\n";
	
	// clean up, clean up, everybody everywhere
	imagedestroy($dst_img); 
	imagedestroy($src_img); 
}

// this reads the directory and grabs the filenames
function directory($dir,$filters) {
	$handle=opendir($dir);
	$files=array();
	if ($filters == "all"){while(($file = readdir($handle))!==false){$files[] = $file;}}
	if ($filters != "all") {
		$filters=explode(",",$filters);
		while (($file = readdir($handle))!==false) {
			for ($f=0;$f<sizeof($filters);$f++):
				$system=explode(".",$file);
				if ($system[1] == $filters[$f]){$files[] = $file;}
			endfor;
		}
	}
	closedir($handle);
	return $files;
}

// fix the count
$count--;

// final HTML stuff
echo '<h3>All done!  '.$count.' image files had thumbnail and medium size versions created.</h3>'."\n"."\n";




/* START THE UPLOADING SECTION */


// let's keep a count of how many files we're copying over
$count = '1';

// here comes the fun stuff
$handle = opendir($dir_name);
if (is_dir($dir_name)) {
	echo '<h2>FROM Directory: <em>'.$dir_name."</em></h2>\n";
	echo '<h2>TO Rackspace Cloud Files container: <em>'.$container_name."</em></h2>\n";
	while (false !== ($filename = readdir($handle))) {
		if ( ($filename !== '.') && ($filename !== '..') && ($filename !== '.DS_Store') && ($filename !== 'Thumbs.db') && ($filename !== 'move_files_to_container.php') && ($filename !== 'generate_thumbnails.php') && ($filename !== 'generate_thumbnails_then_move_files_to_container.php') ) { // skips non-files (directories), un-needed files, and skips this file too
			echo 'Transferring file #'.$count.': '.$filename."<br />\n";
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
