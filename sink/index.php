<html>
<?php

/********************************************************************
 *  Check POST
 *    There is no need to display anything back for a POST request
 *    Respond with HTTP 503 - Service Unavialable
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function check_post() {
  if (count($_POST) > 0) {                                 //Anything POSTed
    http_response_code(503);                               //Resond Service Unavailable
    exit;
  }
}


/********************************************************************
 *  File Extension
 *    Returns the file extension of the document part of a URI
 *
 *  Params:
 *    document
 *  Return:
 *    Success - File Extension
 *    Failure - Blank string
 */
function file_extension($document) {
  if (preg_match('/\.(\w{2,4})$/', $document, $matches)) {
    return $matches[1];
  }
  else {
    return '';
  }
}


/********************************************************************
 *  Get Extension
 *    Extracts the folder and document from URI
 *    Then returns the file extension
 *
 *  Params:
 *    folder
 *  Return:
 *    Success - File Extension
 *    Failure - Blank string
 */
function get_extension($folder) {
  //Seperate the document and parameters
  $document = strstr($folder, '?', true);                  //Get portion of string before ? params

  if ($document === false) {                               //No params, use $folder
    return file_extension($folder);
  }
  else {                                                   //Params supplied, use $document
    return file_extension($document);
  }
}


check_post();

$host = $_SERVER['HTTP_HOST'];
$folder = $_GET['ntrkorigin'] ?? 'Unknown';
$extension = get_extension($folder);

echo "Folder: $folder<br>";
echo "Extension: $extension<br>";
echo $_SERVER['HTTP_HOST'];
echo '<br>';
print_r($_SERVER);
echo '<br>';
print_r($_GET);
?>
</html>
