<?php
// $time1 = microtime(true);
// $i =0;
// while (1) {
//     $i++;
//     if(microtime(true)- $time1 >1){echo $i;exit();}
// }

// function a($fname, $content){
//     echo "i am ". $content;
// }

// function b($fname, $content, $country){
//     echo $content . $country . PHP_EOL;
// }

// function say(){
//     $args = func_get_args();
//     call_user_func_array($args[0], $args);
// }

// say("b", "我是", "中国人");
// say("a", "chinese");

// function sum(...$numbers)
// {
//     var_dump(...$numbers);
//     // $acc = 0;
//     // foreach( $numbers as $number )
//     // {
//     //     $acc += $number;
//     // }
//     // return $acc;
// }

// sum(1,2,3,4);

// function sumNumber($a,$b ,$c)
// {
//     echo $a + $b+ $c;
//     echo "\n";
// }
// sumNumber(...[1,2, 3, 3]);
 
// $a = [1,2];
// sumNumber(...$a);

// $fi = new \finfo(FILEINFO_MIME_TYPE);
// $name = $fi->file("logo.txt");
// print_r($name."\r\n");

//websocket异或数据加密算法
$a = "test";
$secret = "china";
$datalen = strlen($a);
$bin = "";
for ($i=0; $i<$datalen; $i++) {
    $bin .= $a[$i]^$secret[$i%$datalen];
}
echo $bin;
echo "\r\n";

$unbin = "";
$datalen = strlen($bin);
for ($i=0; $i<$datalen; $i++) {
    $unbin .= $a[$i]^$secret[$i%$datalen];
}
print_r($unbin);
