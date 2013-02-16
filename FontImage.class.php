<?php
	/**
	 * A class for generating images based on fonts and text
	 *
	 * @author Andreas Jalsøe <andreas@jalsoedesign.net>
	 * @copyright Copyright (c) 2013, Andreas Jalsøe
	 * @license http://opensource.org/licenses/Apache-2.0 Apache License 2.0
	 * @version 1.0
	 */
	
	/**
	 * The factory to make sure that we don't
	 * create a lot of instances as it might
	 * get heavy on resources
	 */
	class FontImageFactory {
		private static $instance;
		public static function Get($cacheEnabled = null, $cacheDirectory = null) {
			if (self::$instance === null) {
				self::$instance = new FontImage($cacheEnabled, $cacheDirectory);
			}
			return self::$instance;
		}
	}
	
	/**
	 * The main class for doing everything this class is supposed to do
	 */
	class FontImage {		
		/**
		 * The path to the font files
		 */
		private $fontDirectory = './fonts/';
		
		/**
		 * The name of the font to get from $fontDirectory
		 */
		private $font = "arial.ttf";
		
		/**
		 * The size of the font in points
		 */
		private $fontSize = 12;
		
		/**
		 * The angle of the font in degrees
		 */
		private $fontAngle = 0;
		
		/**
		 * The maximum width of the font
		 * null is "no maximum width"
		 * The text will wrap if it gets beyond $width
		 */
		private $width = null;
		
		/**
		 * The maximum height of the font
		 * null is "no maximum height"
		 *
		 * If the text wraps because of $width being too short,
		 * then $height will be ignored
		 */
		private $height = null;
		
		/**
		 * Whether to use wrapping - true is "yes" and false is "no"
		 */
		private $wrapping = false;
		
		/**
		 * Colour of the image text (RGB array)
		 */
		private $textColour = array(0, 0, 0); //Black
		
		/**
		 * Colour of the image background (RGB array/false)
		 *
		 * false = transparent
		 */
		private $backgroundColour = false; //Transparent
		
		/**
		 * Whether the cache is enabled or not
		 */
		private $cacheEnabled = true;
		
		/**
		 * Directory for all the cache files
		 */
		private $cacheDirectory = './cache/';
		
		/**
		 * The constructor for the class
		 *
		 * Checks whether GD is installed, and if not 
		 * it outputs a FontImageNotSupportedException
		 *
		 * @param bool $cacheEnabled Whether or not cache should be enabled | null is default ($cacheEnabled)
		 * @param string $cacheDirectory The directory to put the cache files in | null is default ($cacheDirectory)
		 */
		public function __construct($cacheEnabled = null, $cacheDirectory = null) {
			if (!extension_loaded('gd')) {
				throw new FontImageNotSupportedException('GD does not seem to be installed');
			}
			if (!function_exists('imagettfbbox')) {
				throw new FontImageNotSupportedException('Truetype font support does not seem to be installed');
			}
			$gdInfo = gd_info();
			if (!$gdInfo['FreeType Support']) {
				throw new FontImageNotSupportedException('Truetype font support does not seem to be supported');
			}
			if ($cacheEnabled === null || $cacheEnabled) {
				$this->CacheEnable();
				$this->SetCacheDirectory(($cacheDirectory !== null ? $cacheDirectory : $this->cacheDirectory));
			}
		}
		
		/**
		 * Sets whether or not the cache shuld be enabled
		 *
		 * @param bool $cacheEnabled Whether or not cache should be enabled | true is default
		 */
		public function CacheEnable($cacheEnable = true) {
			$this->cacheEnabled = (bool)$cacheEnable;
		}
		
		/**
		 * Sets whether or not the cache shuld be enabled
		 *
		 * @param string $cacheDirectory The directory to put the cache files in
		 */
		public function SetCacheDirectory($cacheDirectory) {
			if (empty($cacheDirectory)) {
				throw new FontImageWrongVariableTypeException('Cache directory cannot be empty. If you want to disable the cache, use CacheEnable(false).');
				return false;
			}
			$cacheDirectory = $cacheDirectory . (substr($cacheDirectory, -1) != '/' ? '/' : '');
			if (!file_exists($cacheDirectory)) {
				if (!mkdir($cacheDirectory, 0777, true)) {
					throw new FontImageUnaccessibleException('Could not create cache folder at "' . $cacheDirectory . '"');
					return false;
				}
			}
			$cacheExplanationFile = $cacheDirectory . 'what_is_this.txt';
			if (!file_exists($cacheExplanationFile)) {
				$cacheExplanationFileHandle = fopen($cacheExplanationFile, 'w+');
				if ($cacheExplanationFileHandle !== false) {
					fwrite($cacheExplanationFileHandle, 'All the .cache.png files in this folder are cache files for FontImage. If you want to get rid of them, feel free to to so.');
					fclose($cacheExplanationFileHandle);
				} else {
					throw new FontImageUnaccessibleException('Could not write to cache folder at "' . $cacheDirectory . '"');
					return false;
				}
			}
			$this->cacheDirectory = $cacheDirectory;
			return true;
		}
		
		/**
		 * Created the font cache folder for the selected font
		*/
		private function GetFontCacheFolder($cacheDirectory = null) {
			$fontWithoutExtension = pathinfo($this->font, PATHINFO_FILENAME);
			$cacheDirectoryFont = ($cacheDirectory !== null ? $cacheDirectory : $this->cacheDirectory) . $fontWithoutExtension  . '/';
			if (!file_exists($cacheDirectoryFont)) {
				if (!mkdir($cacheDirectoryFont)) {
					throw new FontImageUnaccessibleException('Could not create font folder in cache folder at "' . $cacheDirectoryFont . '"');
					return false;
				}
			}
			return $cacheDirectoryFont;
		}
		
		/**
		 * Sets the font directory to $fontDirectory
		 *
		 * On success: Returns true
		 * On failure: Returns false and throws a FontImageUnaccessibleException
		 *
		 * @param string $fontDirectory The directory where the font files are located
		 * @return bool
		 */
		public function SetFontDirectory($fontDirectory) {
			if (file_exists($fontDirectory)) {
				$this->fontDirectory = $fontDirectory . (substr($fontDirectory, -1) != '/' ? '/' : '');
				return true;
			} else {
				throw new FontImageUnaccessibleException('Could not find the font directory "' . $fontDirectory . '"');
				return false;
			}
		}
		
		/**
		 * Sets the font to $font
		 *
		 * @param string $font The name of the font either with or without extension
		 * The class automatically appends .ttf to the path if nothing else has been selected
		 */
		public function SetFont($font) {
			$this->font = $font . (strpos($font, '.') === false ? '.ttf' : '');
		}
		
		/**
		 * Sets the font size to $fontSize
		 *
		 * On success: Returns true
		 * On failure: Returns false and throws an exception
		 *
		 * @param int|mixed $fontSize The size of the font in pt
		 * @param bool $strict Whether $fontSize *must* be an integer or if it should just be casted as such
		 * @return bool
		 */
		public function SetFontSize($fontSize, $strict = false) {
			if (!is_int($fontSize)) {
				if ($strict) {
					throw new FontImageWrongVariableTypeException($fontSize . ' is not an integer - it is a ' . gettype($fontSize));
					return false;
				}
				$fontSize = (int)$fontSize;
			}
			$this->fontSize = $fontSize;
			return true;
		}
		
		/**
		 * Sets the font angle to $angle
		 *
		 * On success: Returns true
		 * On failure: Returns false and throws an exception
		 *
		 * @param int|mixed $angle The angle of the font in pt
		 * @param bool $strict Whether $angle *must* be an integer or if it should just be casted as such
		 * @return bool
		 */
		public function SetFontAngle($fontAngle, $strict = false) {
			if (!is_int($fontAngle)) {
				if ($strict) {
					throw new FontImageWrongVariableTypeException($fontAngle . ' is not an integer - it is a ' . gettype($fontAngle));
					return false;
				}
				$fontAngle = (int)$fontAngle;
			}
			$this->fontAngle = $fontAngle;
			return true;
		}
		
		/**
		 * Sets the maximum width and height
		 *
		 * @param int $maxWidth The maximum width of the final image - null means "any width". If this is on, wrapping can occur.
		 * @param int $maxHeight The maximum height of the final image - null means "any height".
		 */
		public function SetSize($width = null, $height = null) {
			if ($width !== null) {
				$this->width = (int)$width;
			}
			if ($height !== null) {
				$this->height = (int)$height;
			}
		}
		
		/**
		 * Sets the colour of the final text
		 *
		 * @param string|array|int $colour
		 *
		 * @return bool On success: true | On failure: 
		 */
		public function SetColour($textColour, $backgroundColour = false) {
			if ($textColour !== null) {
				if (!is_array($textColour)) {
					$textColour = $this->ConvertToRGBArray($textColour);
				}
				if (is_array($textColour)) {
					$this->textColour = $textColour;
				} else {
					throw new FontImageWrongVariableTypeException('Text colour was not an RGB array, it was "' . gettype($backgroundColour) . '"');
					return false;
				}
			}
			if ($backgroundColour !== null) {
				if ($backgroundColour === false) {
					//Transparent
					$this->backgroundColour = false;
				} else {
					if (!is_array($backgroundColour)) {
						$backgroundColour = $this->ConvertToRGBArray($backgroundColour);
					}
					if (is_array($backgroundColour)) {
						$this->backgroundColour = $backgroundColour;
					} else {
						throw new FontImageWrongVariableTypeException('Background colour was not an RGB array, it was "' . gettype($backgroundColour) . '"');
						return false;
					}
				}
			}
			return true;
		}
		
		/**
		 * Converts whatever colour format to an RGB array
		 *
		 * @param mixed @colour The colour to convert to an RGB array
		 */
		private function ConvertToRGBArray($colour) {
			if (!is_array($colour)) {
				if (is_int($colour)) {
					return array(
						0xFF & ($colour >> 0x10),
						0xFF & ($colour >> 0x8),
						0xFF & $colour,
					);
				} else if (is_string($colour)) {
					return $this->HexStringToRGBArray($colour);
				} else {
					throw new FontImageWrongVariableTypeException('Could not convert "" to an RGB array');
					return false;
				}
			} else {
				return $colour; //It's already an RGB array (we suppose)
			}
		}
		
		/**
		 * Converts a hex string to a RGB array
		 *
		 * @param $hexString The hex string with or without a prepended #
		 *
		 * @return mixed | On success: Returns RGB array | On failure: Returns false and throws an exception
		 */
		private function HexStringToRGBArray($hexString) {
			$hexString = ($hexString[0] == "#" ? substr($hexString, 1) : "");
			$colourLength = strlen($hexString);
			if ($colourLength == 6) {
				//Regular hex string
				$colourValue = hexdec($hexString);
				return array(
					0xFF & ($colourValue >> 0x10),
					0xFF & ($colourValue >> 0x8),
					0xFF & $colourValue,
				);
			} else if ($colourLength == 3) {
				//Shorthand hex code
				return array(
					hexdec(str_repeat($hexString[0], 2)),
					hexdec(str_repeat($hexString[1], 2)),
					hexdec(str_repeat($hexString[2], 2)),
				);
			}
			throw new FontImageException('Could not convert hex string "' . $hexString . '" to an RGB array');
			return false;
		}
		
		/**
		 * Enabled/disable wrapping
		 *
		 * @param bool $wrapping Whether to enable (true) or disable (false) wrapping
		 */
		public function UseWrapping($wrapping = false) {
			$this->wrapping = (bool)$wrapping;
		}
		
		/**
		 * Generates the image file
		 *
		 * On success: Returns true
		 * On failure: Returns false and throws an exception
		 *
		 * @param string $text The text to write to the image
		 * @param bool $savePath The path to save this image to instead of outputting image
		 * @param bool @noCache Whether to ignore cache completely (ignores $cacheEnable)
		 *
		 * @return bool|resource
		 */
		public function Generate($text, $savePath = null, $noCache = false) {
			if ($this->cacheEnabled) {
				$fontCacheDirectory = $this->GetFontCacheFolder();
				if ($fontCacheDirectory === false) {
					return false;
				}
				$fontHash = md5($text . $this->textColour . $this->backgroundColour . $this->fontSize . $this->fontAngle . $this->width . $this->height);
				$cachePath = $fontCacheDirectory . $fontHash . '.cache.png';
				if (file_exists($cachePath)) {
					$imageGDHandle = imagecreatefrompng($cachePath);
					if ($imageGDHandle !== false) {
						imagesavealpha($imageGDHandle, true);
						
						$return = true;
						header('Content-type: image/png');
						if (!imagepng($imageGDHandle)) {
							throw new FontImageException('Could not output the final image');
							$return = false;
						}
						imagedestroy($imageGDHandle);
						return $return;
					}
				}
			}
			$fontPath = $this->fontDirectory . $this->font;
			if ($fontPath === null || file_exists($fontPath)) {
				if ($savePath !== null) {
					if (!is_writable($savePath)) {
						throw new FontImageException('Cannot write to path "' . $savePath . '"');
						return false;
					}
				}
				
				//Do wrapping
				if ($this->wrapping) {
					if ($this->width !== null) {
						foreach ($lines as $i => $line) {
							$words = explode(' ', $line);
							$wrappedString = $words[0];
							if (count($words) > 1) {
								foreach ($words as $word) {
									$imageBoundingBox = imagettfbbox($this->fontSize, $this->fontAngle, $fontPath, $wrappedString . ' ' . $word);
									$lineIsTooBig = (abs($imageBoundingBox[4] - $imageBoundingBox[0]) > $this->width);
									$wrappedString .= ($lineIsTooBig ? PHP_EOL : ' ') . $word;
								}
								$lines[$i] = $wrappedString;
							}
						}
					}
					$text = implode(PHP_EOL, $lines);
				}
				
				$imageBoundingBox = imagettfbbox($this->fontSize, $this->fontAngle, $fontPath, $text);
				
				//Get image width and height
				if ($this->width === null || $this->height === null) {
					$imageWidth = ($this->width !== null ? $this->width : abs($imageBoundingBox[4] - $imageBoundingBox[0]));
					$imageHeight = ($this->height !== null ? $this->height : abs($imageBoundingBox[5] - $imageBoundingBox[1]));
				} else {
					$imageWidth = $this->width;
					$imageHeight = $this->height;
				}
				
				//Create image
				$imageGDHandle = imagecreatetruecolor($imageWidth, $imageHeight);
				
				if ($this->backgroundColour !== false) {
					$backgroundColour = imagecolorallocate($imageGDHandle, $this->backgroundColour[0], $this->backgroundColour[1], $this->backgroundColour[2]);
				} else {
					//Transparent
					$backgroundColour = imagecolorallocatealpha($imageGDHandle, 255,255,255, 127);
					imagealphablending($imageGDHandle, false);
					imagesavealpha($imageGDHandle, true);
				}
				$textColour = imagecolorallocate($imageGDHandle, $this->textColour[0], $this->textColour[1], $this->textColour[2]);
				
				imagefilledrectangle($imageGDHandle, 0, 0, $imageWidth, $imageHeight, $backgroundColour);
				
				if ($this->backgroundColour === false) {
					//Enable alpha blending again before output
					imagealphablending($imageGDHandle, true);
				}
				imagettftext($imageGDHandle, $this->fontSize, $this->fontAngle, 0, $this->fontSize + ($this->fontSize / 5), $textColour, $fontPath, $text);
				
				if ($this->cacheEnabled) {
					imagepng($imageGDHandle, $cachePath);
				}
				
				//Output image
				$return = true;
				if ($savePath !== null) {
					if (!imagepng($imageGDHandle, $savePath)) {
						throw new FontImageException('Could not create the final image');
						$return = false;
					}
				} else {					
					header('Content-type: image/png');
					if (!imagepng($imageGDHandle)) {
						throw new FontImageException('Could not output the final image');
						$return = false;
					}
				}
				imagedestroy($imageGDHandle);
				
				return $return;
			} else {
				throw new FontImageUnaccessibleException('Could not find the font "' . $fontPath . '"');
				return false;
			}
		}
		
		/**
		 * @source http://www.php.net/manual/en/function.imagettfbbox.php#76333
		 */
		public function ImageTTFBBoxImproved($size, $angle, $font, $text) {
			$dummy = imagecreate(1, 1);
			$black = imagecolorallocate($dummy, 0, 0, 0);
			$bbox = imagettftext($dummy, $size, $angle, 0, 0, $black, $font, $text);
			imagedestroy($dummy);
			return $bbox;
		}
	}
	
	/**
	 * The exception class to use if this class is not supported by the server
	 */
	class FontImageWrongVariableTypeException extends FontImageException { }
	
	/**
	 * The exception class to use if this class is not supported by the server
	 */
	class FontImageNotSupportedException extends FontImageException { }
	
	/**
	 * The exception class for missing files/folders
	 */
	class FontImageUnaccessibleException extends FontImageException { }
	
	/**
	 * The main exception class
	 */
	class FontImageException extends Exception { }
	
	/**
	 * Some basic colours
	 *
	 * Blatantly stolen from http://en.wikipedia.org/wiki/Web_colors#HTML_color_names
	 */
	class FontImageColour {
		const WHITE 	= 0XFFFFFF;
		const SILVER 	= 0XC0C0C0;
		const GRAY 		= 0X808080;
		const BLACK 	= 0X000000;
		const RED 		= 0XFF0000;
		const MAROON 	= 0X800000;
		const YELLOW 	= 0XFFFF00;
		const OLIVE 	= 0X808080;
		const LIME 		= 0X00FF00;
		const GREEN 	= 0X008000;
		const AQUA 		= 0X00FFFF;
		const TEAL 		= 0X008080;
		const BLUE 		= 0X0000FF;
		const NAVY 		= 0X000080;
		const FUCHSIA 	= 0XFF00FF;
		const PURPLE 	= 0X800080;
	}
?>