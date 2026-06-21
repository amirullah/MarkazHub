<?php
require "inc/marketplace.php";
$CF=$argv[1];
function rentang($orders){
  $min=null;$max=null;
  foreach($orders as $o){ $d=mp_parse_date($o['orderDate']??null); if($min===null||$d<$min)$min=$d; if($max===null||$d>$max)$max=$d; }
  return substr($min,0,10)." s/d ".substr($max,0,10)." (".count($orders)." pesanan)";
}
$files=[
 "SHOPEE income"   => ["shopee/Income.sudah dilepas.id.20260101_20260621.xlsx","Income.xlsx"],
 "SHOPEE order"    => ["shopee/Order.all.20250801_20250831.xlsx","Order.all.xlsx"],
 "TIKTOK income"   => [null,"income.xlsx"],
 "TIKTOK pesanan"  => ["tiktok-tokopedia/Selesai pesanan-2026-06-21-18_40.csv","Selesai pesanan.csv"],
];
foreach($files as $label=>$f){
  $path = $f[0]===null ? glob("$CF/tiktok-tokopedia/income_*.xlsx")[0] : "$CF/".$f[0];
  $r=mp_read_file($path,$f[1]);
  echo str_pad($label,16).": ".rentang($r["orders"])."\n";
}
