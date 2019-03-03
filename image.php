<?php
namespace Mre\Unicorn\lib;

require 'vendor/autoload.php';
require_once ("config.php");

function listFolderFiles($dir, $ext = "jpg,jpeg"){
    $ffs = scandir($dir);

    unset($ffs[array_search('.', $ffs, true)]);
    unset($ffs[array_search('..', $ffs, true)]);

    // prevent empty ordered elements
    if (count($ffs) < 1)
        return;

	$retVal = array();
	$allFileExt = explode(",", $ext);
    foreach($ffs as $ff){
		
		foreach($allFileExt as $currentFileExt) {
			if(strpos(strtolower($ff), $currentFileExt) && strpos($dir, "thumbs") == 0 ) {
				$retVal[] = $dir."/".$ff;
			}
		
			if(is_dir($dir.'/'.$ff)) {
				$retVal = array_merge($retVal, listFolderFiles($dir.'/'.$ff, $currentFileExt));
			}
		}
    }
	return $retVal;
}

$current = isset($_GET['current']) && is_numeric($_GET['current']) ? $_GET['current'] : -1;
$files = listFolderFiles("cache/mails");
$next = $current;
if($current == -1) {
	$next = rand(0, count($files) -1);
} else if($next >= 0) {
	$next++; // next image
	if(count($files) <= $next) {
		$next = 0;
	}
} else {
	$next = 0;
}
$theImage = $files[$next];
setlocale(LC_TIME, Config::read("image_locale"));

// Handle Subject
if(file_exists(Config::read("image_subject_file"))) {
	$theMessage = "";
	$file = fopen(Config::read("image_subject_file"), "r");
	$knownSenders = Config::read("image_known_senders");
	
	while(!feof($file)) {
		$currentLine = fgets($file);
		$currentLine = explode(";", $currentLine);
		$messageCreateDate = strtotime($currentLine[0]);
		if(strtotime("-".Config::read("image_message_age")." Days") < $messageCreateDate) {
			$date = strftime("%A, %d.%m", $messageCreateDate);
			$from = $currentLine[1];
			if(isset($knownSenders[$from])) {
				$from = $knownSenders[$from];
			}
			$msg = $currentLine[2];
			if($msg != "") {
				$theMessage .= "Nachricht von ".$from." am ".$date.": ".$msg." - - - - -  ";
			}
		} else {
			// this is an old message that will not be displayed
		}
	}
	fclose($file);
}


?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta http-equiv="refresh" content="<?php echo Config::read("image_refresh")?>; url=<?php echo Config::read("site_url")?>?current=<?php echo $next;?>" />
<meta name="apple-mobile-web-app-capable" content="yes" />
<title><?php echo Config::read("gallery_name")?></title>
<script src="https://cdnjs.cloudflare.com/ajax/libs/prefixfree/1.0.7/prefixfree.min.js"></script>
<style>
body {background-color: black;}
#fixed-div {    position: fixed;    top: 1em;    right: 1em; }

@keyframes push {
    from {
        margin: 100%;
    }
    to {
        margin: 0;
    }
}
.marquee {
    white-space: nowrap;
    overflow: hidden;
	background-color: black;
	color: white;
	font-size: 1.4em;
}
.marquee > span:nth-child(1) {
    animation:  20s linear 0s infinite push;
}
</style>

</head>

<body>

<?php if($theMessage != "") { ?>
<div class="marquee">
	<span><?php echo $theMessage; ?></span>
</div>
<?php } ?>

<?php
echo "<img src=\"".$theImage."\" width=\"100%\"/>";
?>


</body>

</html>