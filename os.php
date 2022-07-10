<?php
// if (PHP_OS == "Linux") {
//     echo "123";
// } else {
//     echo "321";
// }

$data = "\r\nContent-Length: 10";
preg_match("/\r\nContent-Length: ?(\d+)/i", $data, $matches);
print_r($matches);