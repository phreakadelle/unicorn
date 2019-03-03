<?
namespace Mre\Unicorn\lib;

require 'vendor/autoload.php';
require_once ("config.php");

use Kunnu\Dropbox\DropboxApp;
use Kunnu\Dropbox\Dropbox as BaseDropbox;
use Kunnu\Dropbox\DropboxFile as DropboxFile;


function isVideo($pFilename) {
    if(strlen($pFilename) == 0) {
        return false;
    }
    
    $formats = array("avi", "mpg", "mpeg", "mov");
    
    $retVal = false;
    foreach($formats as $currentFormat) {
        if($strpos($pFilenmae, ".".$currentFormat)) {
            $retVal = true;
        }
    }
    return $retVal;
}

header('Content-Type: application/json');

/* return message*/
$retVal = array();
$retVal['debug'] = array();
$retVal['messages'] = array();

/* try to connect */
$inbox = imap_open(Config::read('mail_host'), Config::read('mail_user'), Config::read('mail_password')) or die('Cannot connect to Email: ' . imap_last_error());

/* grab emails */
$emails = imap_search($inbox, '');

/* if emails are returned, cycle through each... */
if ($emails) {
    
    /* begin output var */
    $output = '';
    
    /* put the newest emails on top */
    rsort($emails);
    
    foreach ($emails as $email_number) {
        
        /* get information specific to this email */
        $overview = imap_fetch_overview($inbox, $email_number, 0);
        //$message = imap_fetchbody($inbox, $email_number, 2);
        $structure = imap_fetchstructure($inbox, $email_number);
        
        $attachments = array();
        if (isset($structure->parts) && count($structure->parts)) {
            for ($i = 0; $i < count($structure->parts); $i ++) {
                $attachments[$i] = array(
                    'is_attachment' => false,
                    'filename' => '',
                    'name' => '',
                    'attachment' => ''
                );
                
                if ($structure->parts[$i]->ifdparameters) {
                    foreach ($structure->parts[$i]->dparameters as $object) {
                        if (strtolower($object->attribute) == 'filename') {
                            $attachments[$i]['is_attachment'] = true;
                            $attachments[$i]['filename'] = $object->value;
                        }
                    }
                }
                
                if ($structure->parts[$i]->ifparameters) {
                    foreach ($structure->parts[$i]->parameters as $object) {
                        if (strtolower($object->attribute) == 'name') {
                            $attachments[$i]['is_attachment'] = true;
                            $attachments[$i]['name'] = $object->value;
                        }
                    }
                }
                
                if ($attachments[$i]['is_attachment']) {
                    $attachments[$i]['attachment'] = imap_fetchbody($inbox, $email_number, $i + 1);
                    if ($structure->parts[$i]->encoding == 3) { // 3 = BASE64
                        $attachments[$i]['attachment'] = base64_decode($attachments[$i]['attachment']);
                    } elseif ($structure->parts[$i]->encoding == 4) { // 4 = QUOTED-PRINTABLE
                        $attachments[$i]['attachment'] = quoted_printable_decode($attachments[$i]['attachment']);
                    }
                }
            } // for($i = 0; $i < count($structure->parts); $i++)
        } // if(isset($structure->parts) && count($structure->parts))
        
        if (count($attachments) != 0) {
            foreach ($attachments as $at) {
                if (isVideo($at['attachment'])) {
                    $retVal['debug'][] = "Ignore Video Attachment ".$at['filename'];
                } else ($at['is_attachment'] == 1) {
                    file_put_contents($at['filename'], $at['attachment']);
                    
                    // Fix Image Rotation Start
                    $exif = exif_read_data($at['filename']);
                    if(array_key_exists('Orientation', $exif)) {
                        $ort = $exif['Orientation']; /*STORES ORIENTATION FROM IMAGE */
                        $ort1 = $ort;
                        $exif = exif_read_data($at['filename'], 0, true);
                        if (!empty($ort1)) {
                            $image = imagecreatefromjpeg($at['filename']);
                            $ort = $ort1;
                            switch ($ort) {
                                case 3:
                                    $image = imagerotate($image, 180, 0);
                                    break;
                            
                                case 6:
                                    $image = imagerotate($image, -90, 0);
                                    break;
                            
                                case 8:
                                    $image = imagerotate($image, 90, 0);
                                    break;
                            }
                        }
                        imagejpeg($image,$at['filename'], 90); /*IF FOUND ORIENTATION THEN ROTATE IMAGE IN PERFECT DIMENSION*/
                    } else {
                        $retVal['debug'][] = "Could not change picture orientation ".$at['filename'];
                    }
                    // Fix Image Rotation End
                    
                    
                    $api = new BaseDropbox(new DropboxApp("1", Config::read('secret'), Config::read('access_token')));
                    
                    $dropboxFile = new DropboxFile($at['filename']);
                    $file = $api->upload($dropboxFile, Config::read('mail_image_directory_current') . $at['filename'], [
                        'autorename' => true
                    ]);
                    
                    // Uploaded File
                    $retVal['items'][] = $file->getName();
                    
                    unlink($at['filename']);
                    
                    // Move old files Start
                    
                    $api = new BaseDropbox(new DropboxApp("1", Config::read('secret'), Config::read('access_token')));
                    $listFolderContents = $api->listFolder(Config::read('mail_image_directory_current'));
                    
                    //Fetch Items
                    $items = $listFolderContents->getItems();
                    $cache = array();
                    foreach($items as $cu) {
                        $cache[strtotime($cu->getClientModified())] = $cu->getName();
                    }
                    
                    for($i = 0; $i < (count($cache) - Config::read('image_cache')); $i++) {
                        
                        $item = array_values($cache)[$i];
                        $destination = Config::read('mail_image_directory_archive').$item;
                        
                        try {
                            $file = $api->move("/mails/".$item, $destination);
                            $retVal['cleanup'][] = $item;
                        } catch(\Exception $e) {
                            try {
                                $deletedFolder = $api->delete($destination);
                                $retVal['cleanup'][] = $e->getMessage().". Deleted item manually";
                            } catch(\Exception $e) {
                                $retVal['cleanup'][] = "Failed to Deleted item manually ".$item;
                            }
                        }
                    }
                    
                    // Move old files End
                    
                }
            }
        }
        
        // Write Subject to file
        if($overview[0]->subject != "") {
            $subject = iconv_mime_decode(($overview[0]->subject),0, "UTF-8");
            $from = iconv_mime_decode(($overview[0]->from),0, "UTF-8");
            
            $fp = fopen(Config::read('image_subject_file'), 'a');
            fwrite($fp, date("Y-m-d").";".$from.";".$subject."\n");
            fclose($fp);
            
            $retVal['messages'][] = $subject;
            //$retVal['debug'][] = $overview[0];
        } else {
            print_r($overview);
        }
        
        imap_delete($inbox, $email_number);
        imap_expunge($inbox);
    }
}

/* close the connection */
imap_close($inbox);

// skipupdate only for testing
if ($emails && !isset($_GET['skipupdate'])) {
    // Trigger the Import in the Gallery
    $retVal['message'] = "Trigger Gallery Refresh.";
    $options = array(
        'http' => array(
            'header' => "Content-type: application/x-www-form-urlencoded\r\n",
            'method' => 'POST'
        )
    );
    $context = stream_context_create($options);
    try {
        $result = @file_get_contents(Config::read('admin_url'), false, $context);
    } catch(Exception $e) {
        $retVal['debug'][] = "failed to update gallery";
    }
    
} else {
    $retVal['message'] = "No Update Needed";
}


//print_r($cache);
echo json_encode($retVal);
?>