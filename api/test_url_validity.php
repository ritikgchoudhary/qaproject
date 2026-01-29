<?php
$url = "https://upload.wikimedia.org/wikipedia/commons/thumb/t/t6/India_Gate_600x400.jpg/640px-India_Gate_600x400.jpg";
$headers = @get_headers($url);
if($headers) {
    echo "Status: " . $headers[0] . "\n";
} else {
    echo "Failed to get headers.\n";
}
?>
