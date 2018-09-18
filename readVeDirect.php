#! /usr/bin/php
<?php
// :7F6ED00C80A99
// :7EFED00185A

// swaps two values for littleEndian
// expects 2 bytes (int16) like F6ED or 4 bytes (int32) like AABBCCDD, returns anything else unmodified, f.x.int8
function swapEndian($value) {
   if (strlen($value)==8) {
     return substr($value,6,2).substr($value,4,2).substr($value,2,2).substr($value,0,2);
   } elseif (strlen($value)==4) {
     return substr($value,2,2).substr($value,0,2);
   } else {
     return $value;
  }   
}


// decodes HexValues from reading like :7F6ED00C80A99 or :7EFED00185A
function getDecode($value) {
  $value=trim($value);
  
  if (substr($value,0,2)<>":7")  {return false;}
  
  $registerName=swapEndian(substr($value,2,4));
  if (strlen($value)==18) {  // four Byte value
    $registerValue=swapEndian(substr($value,8,8));
  } elseif (strlen($value)==14) {  // two Byte value
    $registerValue=swapEndian(substr($value,8,4));
  } else {  // one byte value
    $registerValue=substr($value,8,2);
  }  // if  
  $registerValue=hexdec($registerValue);
  
  return array($registerName => $registerValue);  
}// function getDecode

function getReadString($register) {
   $checksum=hexdec(substr($register,0,2));
//   echo "$checksum\n";
   $checksum= $checksum + hexdec(substr($register,2,2));
//   echo "$checksum\n";
   $checksum=$checksum+7;
   if ($checksum<=hexdec(55)) {
      $checksum=hexdec(55)-$checksum;
   } else {
      $checksum=hexdec(255)-$checksum;
   }
   
//   echo "$checksum\n";
   $checksum=strtoupper(dechex($checksum));
//   echo "$checksum\n";
   return ":7".swapEndian($register)."00".$checksum."\n";
}  // function getReadString

function getWriteString($register,$value,$intLength) {
   $valueHex=dechex($value);
   $valueHex=str_pad($valueHex,$intLength/4,"0",STR_PAD_LEFT);
//   echo "valueHex: $valueHex\n";
   $result = "8".swapEndian($register)."00".swapEndian($valueHex);
   return strtoupper(":".$result.getCheckSum($result));
}  // function getWriteString

// expects one char command and all values like 7EFED0018
function getCheckSum($value) {
   $checksum=substr($value,0,1);
   $value=substr($value,1);
   while ($value<>"") {
       $checksum=$checksum+hexdec(substr($value,0,2));
       $value=substr($value,2);
//       echo "$value $checksum\n";
    } // while
//   while ($checksum >hexdec(255)) { $checksum=$checksum-hexdec(255); } 
   if ($checksum<=hexdec(55)) {
      $checksum=hexdec(55)-$checksum;
   } else {
      $checksum=hexdec(255)-$checksum;
   }
   
  // echo "checksum: $checksum " .dechex($checksum)."\n";
   
   $checksum=substr(strtoupper("0".dechex($checksum)),-2);
    return $checksum;
} // getCheckSum

function readRegister($fp,$register) {
/*
   while (!feof($fp)) {
  $result=fgets($fp);
  echo "result(wait): $result \n";
   }
*/
    $void=stream_get_contents($fp); // clean stream before sending command
  fwrite($fp,getReadString($register));
    while (($result = fgets($fp, 4096)) === false) {} ;
  
  //echo "result($register): $result \n";
  return getDecode($result);  
} 

$v0201=array(
   0 => "NOT CHARGING",
   2 => "FAULT",
   3 => "BULK - Full current charge with charge current set-point",
   4 => "Absorption - Voltage controlled with absorption voltage set-point ",
   5 => "Float - Voltage controlled with float voltage set-point ",
   7 => "Equalize - Voltage controlled with equalization voltage set-point",
   252 => "ESS Voltage controlled with remote voltage set-point",
   255 => "UNAVALABLE");

/*
echo getChecksum("7BBED00190D")."\n";
echo hexdec("255")." ".hexdec("55");
*/

echo getWriteString("EDF1",hexdec("FF"),16)."\n";
echo getWriteString("EDF1",8,16)."\n";      // battery type Lio
echo "\n";
echo getWriteString("EDF1",255,16)."\n";      // battery type user
echo getWriteString("EDF6",27.6*100,16)."\n"; // Float
echo getWriteString("EDF7",28*100,16)."\n";  // Absorption
echo getWriteString("EDF4",0*100,16)."\n";  // Equalizing
echo getWriteString("EDF0",10*10,16)."\n";  // Max Current
echo getWriteString("EDF0",4*10,16)."\n";  // Max Current

//  echo getReadString("EDBC")."\n";
/*

  echo getReadString("EDF0")."\n";
  die;
*/
//  $if = "/dev/serial/by-id/usb-Silicon_Labs_CP2102_USB_to_UART_Bridge_Controller_0001-if00-port0";
  $if = "/dev/ttyUSB0";
  
  exec ("stty -F $if 19200 raw -echo");
  
  if (($fp=fopen($if,"a+"))===false) { die("unable to open connection\n"); }
  $i=1;
  stream_set_blocking($fp,false);  
//  $result=stream_get_contents($fp);
    
  $result=readRegister($fp,"EDF1");
  echo "Battery type: (EDF1): ".$result["EDF1"]." \n";

  $result=readRegister($fp,"EDEF");
  echo "Battery Voltage: (EDEF): ".$result["EDEF"] ." \n";

  $result=readRegister($fp,"EDF7");
  echo "Absorption Voltage: (EDF7): ".$result["EDF7"]/100 ." V\n";

  $result=readRegister($fp,"EDF6");
  echo "Float Voltage: (EDF6): ".$result["EDF6"]/100 ." V\n";

  $result=readRegister($fp,"EDF4");
  echo "Equalize Voltage: (EDF4): ".$result["EDF4"]/100 ." V\n";

  $result=readRegister($fp,$register="EDFD");
  echo "Automatic equalization mode: ($register): ".$result[$register] ." \n";

  $result=readRegister($fp,"EDF0");
  echo "Battery max current: (EDF0): ".$result["EDF0"]/10 ." A\n";
// K:8F0ED00F4017B
  $result=readRegister($fp,"EDCE");
  echo "Volatage Range: (EDCE): ".$result["EDCE"] ." \n";

  $result=readRegister($fp,"0200");
  echo "charger Device Mode: (0200): ".$result["0200"] ." \n";

  $result=readRegister($fp,"0201");
  echo "charger Device Status: (0201): ".$result["0201"] .":" .$v0201[$result["0201"]]." \n";

  $result=readRegister($fp,"0205");
  echo "Device Off reason: (0205): ".$result["0205"] ." \n";

  $result=readRegister($fp,$register="EDDF");
  echo "charger max current (setting): ($register): ".$result[$register]/100 ." A\n";

  $result=readRegister($fp,"EDDB");
  echo "  charger temperature: (EDDB): ".$result["EDDB"]/100 ." C\n";

  $result=readRegister($fp,"EDD5");
  echo "  charger Voltage: (EDD5): ".$result["EDD5"]/100 ." V\n";

  $result=readRegister($fp,"EDD7");
  echo "  charger Current: (EDD7): ".$result["EDD7"]/10 ." A\n";

  $result=readRegister($fp,"EDD2");
  echo "charger max power today: (EDD2): ".$result["EDD2"] ." W\n";

  $result=readRegister($fp,"EDD3");
  echo "yield today: (EDD3): ".$result["EDD3"]/100 ." kWh [".($result["EDD3"]*10) / 26.25 ." Ah @26.25V = 3.75V*7]\n";

  $result=readRegister($fp,"EDBC");
  echo "  panel power: (EDBC): ".$result["EDBC"]/100 ." W\n";

  $result=readRegister($fp,"EDBB");
  echo "  panel voltage: (EDBB): ".$result["EDBB"]/100 ." V\n";

  $result=readRegister($fp,"EDBD");
  echo "  panel current: (EDBD): ".$result["EDBD"]/10 ." A\n";





?>
