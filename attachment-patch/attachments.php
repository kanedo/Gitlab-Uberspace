<?php
define('ROOT_PATH', '/home/<uberspace-user>/gitlab/public/uploads/');
$file	= $_GET['f'];

if(substr($file, -1) == '/') {
	$file = substr($file, 0, -1);
}

$abspath	= ROOT_PATH . $file;

if(substr(realpath($abspath), 0, strlen(ROOT_PATH)) != ROOT_PATH) {
    header('"Status: 404 Not Found');
}

if(file_exists($abspath)) {
	header('Content-Description: File Transfer');
	header('Content-Type: application/octet-stream');
	header('Content-Disposition: attachment; filename='.basename($abspath));
	header('Content-Transfer-Encoding: binary');
	header('Expires: 0');
	header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
	header('Pragma: public');
	header('Content-Length: ' . filesize($abspath));
	
	readfile($abspath);
	exit;
} else {
	header('"Status: 404 Not Found');
}
?>