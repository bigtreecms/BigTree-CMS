<?
	
	function colorPalette($imageFile, $numColors, $granularity = 5) 
	{ 
	   $granularity = max(1, abs((int)$granularity)); 
	   $colors = array(); 
	   $size = @getimagesize($imageFile); 
	   if($size === false) 
	   { 
		  user_error("Unable to get image size data"); 
		  return false; 
	   } 
	   $img = @imagecreatefromjpeg($imageFile);
	   // Andres mentioned in the comments the above line only loads jpegs, 
	   // and suggests that to load any file type you can use this:
	   // $img = @imagecreatefromstring(file_get_contents($imageFile)); 
	
	   if(!$img) 
	   { 
		  user_error("Unable to open image file"); 
		  return false; 
	   } 
	   for($x = 0; $x < $size[0]; $x += $granularity) 
	   { 
		  for($y = 0; $y < $size[1]; $y += $granularity) 
		  { 
			 $thisColor = imagecolorat($img, $x, $y); 
			 $rgb = imagecolorsforindex($img, $thisColor); 
			 $red = round(round(($rgb['red'] / 0x33)) * 0x33); 
			 $green = round(round(($rgb['green'] / 0x33)) * 0x33); 
			 $blue = round(round(($rgb['blue'] / 0x33)) * 0x33); 
			 $thisRGB = sprintf('%02X%02X%02X', $red, $green, $blue); 
			 if(array_key_exists($thisRGB, $colors)) 
			 { 
				$colors[$thisRGB]++; 
			 } 
			 else 
			 { 
				$colors[$thisRGB] = 1; 
			 } 
		  } 
	   } 
	   arsort($colors); 
	   return array_slice(array_keys($colors), 0, $numColors); 
	} 
	
?>