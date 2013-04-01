<?php
/** This script will rewrite the .htaccess file support static publishing */
$file = '.htaccess';
$insertTextFile = 'staticpublishqueue/scripts/htaccess-insert.txt';

if (file_exists($file)) {
	//make a backup of htaccess file
	copy($file, $file . '-backup');

	if (file_exists($insertTextFile)) {
		if (stripos(file_get_contents($file),'CONFIG FOR STATIC PUBLISHING') === false) {
			//insert the static publishing code into the htaccess (rewriting the file)
			file_put_contents($file,str_replace('RewriteEngine On',file_get_contents($insertTextFile),file_get_contents($file)));
			echo "Success: updated '$file' to support static publishing\n";
		} else {
			echo "Error: '$file' is already configured for static publishing\n";
		}
	} else {
		echo "Error: '$insertTextFile' does not exist\n";
	}
} else {
	echo "Error: '$file' does not exist\n";
}