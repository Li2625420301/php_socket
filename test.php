<?php
var_dump(strlen(11));die;
$data = 'hello';
$len = strlen($data)+6;

$bin = pack("Nn", $len, "1");
$aa = unpack("Nn", $bin);
print_r($aa);
die;
//0110 1000 0110 0101 0110 1100 0110 1100
$a1 = 65539; //用三个字节存储 0000 0001 0000 0000 0000 0011

// 0xff表示的数二进制1111 1111
// 0x1234表示16进制
$a11 = $a1>>16&0xFF; //a1的高位往右移动16位 0000 0000 0000 0000 0000 0001 &
                                    //   0000 0000 0000 0000 1111 1111
                                    //   0000 0000 0000 0000 0000 0001  ->1(10进制)

$a12 = $a1>>8&0xFF;  //a1的高位往右移动8位  0000 0000 0000 0001 0000 0000 &
                                    //   0000 0000 0000 0000 1111 1111
                                    //   0000 0000 0000 0000 0000 0000  ->0(10进制)

$a13 = $a1>>0&0xFF; //a1的高位往右移动0位  0000 0000 0000 0000 0000 0011 &
                                    //  0000 0000 0000 0000 1111 1111
                                    //  0000 0000 0000 0000 0000 0011  ->3(10进制)

                                    // 1>>1
                                    //  0000 0001   &
                                    //  0000 0000
                                    //  0000 0000

                                    // 1<<1
                                    //  0000 0001   &
                                    //  0000 0010
                                    



// print_r($a11."\n");
// print_r($a12."\n");
// print_r($a13."\n");
$a = pack("CCC", $a11, $a12, $a13);
$aa = unpack("Clen1/Clen2/Clen3", $a);

$ret = 0;
$ret|=$aa['len1']<<16; // 0000 0001 0000 0000 0000 0000 |或
                       // 0000 0000 0000 0000 0000 0000
                       // 0000 0001 0000 0000 0000 0000


$ret|=$aa['len2']<<8; // 0000 0000 0000 0000 0000 0000 |或
                      // 0000 0001 0000 0000 0000 0000
                      // 0000 0001 0000 0000 0000 0000

$ret|=$aa['len3']<<0; // 0000 0000 0000 0000 0000 0011 |或
                      // 0000 0001 0000 0000 0000 0000
                      // 0000 0001 0000 0000 0000 0011

print_r($ret);

// 在使用的时候一定要注意：1字节序 2要知道要用多少字节来储存[打包]数据
// 取出来的时候就很灵活，unpack, ord