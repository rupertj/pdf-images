<?php

namespace rupertj\pdf_images;

class PdfImage {

  /**
   * Create an image from PDF XObject binary data.
   *
   * Based on ISO 32000-1 standard for PDF image objects.
   *
   * @param string $binary_data
   *   Binary image data extracted from a PDF XObject.
   * @param int $width
   *   The width of the image in pixels.
   * @param int $height
   *   The height of the image in pixels.
   * @param int $bits_per_component
   *   The number of bits per color component (typically 1, 2, 4, 8, or 16).
   * @param string $color_space
   *   The color space (e.g., 'DeviceRGB', 'DeviceGray', 'DeviceCMYK', 'Indexed').
   *
   * @return \GdImage
   *   A GD image.
   */
  public static function createImageFromXObjectData(string $binary_data, int $width, int $height, int $bits_per_component, string $color_space): \GdImage {

    // Validate input parameters:
    if ($width <= 0 || $height <= 0) {
      throw new \Exception("Width and height must be greater than 0. {$width}x{$height} passed.");
    }

    if (!in_array($bits_per_component, [1, 2, 4, 8, 16])) {
      throw new \Exception("Bits per component must be 1, 2, 4, 8, or 16. {$bits_per_component} passed.");
    }

    // Create a new image
    $image = imagecreatetruecolor($width, $height);
    if (!$image) {
      throw new \Exception("Couldn't create new image");
    }

    // @todo: We need to figure out how to handle:
    // * ICCBased. See https://blog.idrsolutions.com/what-are-iccbased-colorspaces-in-pdf-files/.
    // * Indexed. See PDF spec part 8.6.6.3 Indexed Color Spaces.
    switch ($color_space) {
      case 'DeviceRGB':
      case 'RGB':
        return self::rgbImage($image, $binary_data, $width, $height, $bits_per_component);

      case 'DeviceGray':
      case 'G':
        return self::grayscaleImage($image, $binary_data, $width, $height, $bits_per_component);

      case 'DeviceCMYK':
      case 'CMYK':
        return self::cmykImage($image, $binary_data, $width, $height, $bits_per_component);

      default:
        throw new \Exception("Unknown colour space " . $color_space);
    }
  }

  /**
   * Process RGB image data.
   *
   * @param \GdImage $image
   *   The image to work on.
   * @param string $binary_data
   *   The raw binary image data.
   * @param int $width
   *   The width of the image.
   * @param int $height
   *   The height of the image.
   * @param int $bits_per_component
   *   The number of bits per color component.
   *
   * @return ?\GdImage
   *   The processed image or NULL on failure.
   */
  private static function rgbImage(\GdImage $image, string $binary_data, int $width, int $height, int $bits_per_component): ?\GdImage {
    $bytes_per_component = $bits_per_component / 8;
    $components_per_pixel = 3; // RGB has 3 components
    $bytes_per_pixel = $bytes_per_component * $components_per_pixel;

    $data_length = strlen($binary_data);
    $expected_length = $width * $height * $bytes_per_pixel;

    if ($data_length < $expected_length) {
      return NULL;
    }

    $offset = 0;
    for ($y = 0; $y < $height; $y++) {
      for ($x = 0; $x < $width; $x++) {
        if ($offset + $bytes_per_pixel > $data_length) {
          return $image;
        }

        // Extract RGB values based on bits per component
        if ($bits_per_component == 8) {
          $r = ord($binary_data[$offset]);
          $g = ord($binary_data[$offset + 1]);
          $b = ord($binary_data[$offset + 2]);
        } else {
          // Scale values to 8-bit range
          $max_value = (1 << $bits_per_component) - 1;
          $r = self::componentValue($binary_data, $offset, $bits_per_component) * 255 / $max_value;
          $g = self::componentValue($binary_data, $offset + $bytes_per_component, $bits_per_component) * 255 / $max_value;
          $b = self::componentValue($binary_data, $offset + 2 * $bytes_per_component, $bits_per_component) * 255 / $max_value;
        }

        $color = imagecolorallocate($image, (int) $r, (int) $g, (int) $b);
        imagesetpixel($image, $x, $y, $color);

        $offset += $bytes_per_pixel;
      }
    }

    return $image;
  }

  /**
   * Process grayscale image data.
   *
   * @param \GdImage $image
   *   The GD image.
   * @param string $binary_data
   *   The raw binary image data.
   * @param int $width
   *   The width of the image.
   * @param int $height
   *   The height of the image.
   * @param int $bits_per_component
   *   The number of bits per color component.
   *
   * @return ?\GdImage
   *   The processed image or NULL on failure.
   */
  private static function grayscaleImage(\GdImage $image, string $binary_data, int $width, int $height, int $bits_per_component): ?\GdImage {
    $bytes_per_component = $bits_per_component / 8;
    $data_length = strlen($binary_data);
    $expected_length = $width * $height * $bytes_per_component;

    if ($data_length < $expected_length) {
      return NULL;
    }

    $offset = 0;
    for ($y = 0; $y < $height; $y++) {
      for ($x = 0; $x < $width; $x++) {
        if ($offset + $bytes_per_component > $data_length) {
          return $image;
        }

        // Extract grayscale value
        if ($bits_per_component == 8) {
          $gray = ord($binary_data[$offset]);
        } else {
          // Scale value to 8-bit range
          $max_value = (1 << $bits_per_component) - 1;
          $gray = self::componentValue($binary_data, $offset, $bits_per_component) * 255 / $max_value;
        }

        $color = imagecolorallocate($image, (int) $gray, (int) $gray, (int) $gray);
        imagesetpixel($image, $x, $y, $color);

        $offset += $bytes_per_component;
      }
    }

    return $image;
  }

  /**
   * Process CMYK image data.
   *
   * @param \GdImage $image
   *   The GD image.
   * @param string $binary_data
   *   The raw binary image data.
   * @param int $width
   *   The width of the image.
   * @param int $height
   *   The height of the image.
   * @param int $bits_per_component
   *   The number of bits per color component.
   *
   * @return ?\GdImage
   *   The processed image or NULL on failure.
   */
  private static function cmykImage(\GdImage $image, string $binary_data, int $width, int $height, int $bits_per_component): ?\GdImage {
    $bytes_per_component = $bits_per_component / 8;
    // CMYK has 4 components
    $components_per_pixel = 4;
    $bytes_per_pixel = $bytes_per_component * $components_per_pixel;

    $data_length = strlen($binary_data);
    $expected_length = $width * $height * $bytes_per_pixel;

    if ($data_length < $expected_length) {
      return NULL;
    }

    $offset = 0;
    for ($y = 0; $y < $height; $y++) {
      for ($x = 0; $x < $width; $x++) {
        if ($offset + $bytes_per_pixel > $data_length) {
          return $image;
        }

        // Extract CMYK values and convert to RGB
        if ($bits_per_component == 8) {
          $c = ord($binary_data[$offset]) / 255;
          $m = ord($binary_data[$offset + 1]) / 255;
          $y_cmyk = ord($binary_data[$offset + 2]) / 255;
          $k = ord($binary_data[$offset + 3]) / 255;
        } else {
          // Scale values to 0-1 range
          $max_value = (1 << $bits_per_component) - 1;
          $c = self::componentValue($binary_data, $offset, $bits_per_component) / $max_value;
          $m = self::componentValue($binary_data, $offset + $bytes_per_component, $bits_per_component) / $max_value;
          $y_cmyk = self::componentValue($binary_data, $offset + 2 * $bytes_per_component, $bits_per_component) / $max_value;
          $k = self::componentValue($binary_data, $offset + 3 * $bytes_per_component, $bits_per_component) / $max_value;
        }

        // Convert CMYK to RGB
        $r = (1 - $c) * (1 - $k) * 255;
        $g = (1 - $m) * (1 - $k) * 255;
        $b = (1 - $y_cmyk) * (1 - $k) * 255;

        $color = imagecolorallocate($image, (int) $r, (int) $g, (int) $b);
        imagesetpixel($image, $x, $y, $color);

        $offset += $bytes_per_pixel;
      }
    }

    return $image;
  }

  /**
   * Extract a component value from binary data.
   *
   * @param string $data
   *   The binary data.
   * @param int $offset
   *   The byte offset.
   * @param int $bits_per_component
   *   The number of bits per component.
   *
   * @return int
   *   The extracted component value.
   */
  private static function componentValue(string $data, int $offset, int $bits_per_component): int {
    if ($bits_per_component == 8) {
      return ord($data[$offset] ?? 0);
    }
    elseif ($bits_per_component == 16) {
      $byte1 = ord($data[$offset] ?? 0);
      $byte2 = ord($data[$offset + 1] ?? 0);
      return ($byte1 << 8) | $byte2;
    } else {
      // For 1, 2, 4 bits per component, we need bit-level extraction
      $byte_offset = intval($offset / 8);
      $bit_offset = $offset % 8;

      if (!isset($data[$byte_offset])) {
        return 0;
      }

      $byte = ord($data[$byte_offset]);
      $mask = (1 << $bits_per_component) - 1;
      return ($byte >> (8 - $bit_offset - $bits_per_component)) & $mask;
    }
  }

}
