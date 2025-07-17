<?php

/**
 * @file example.php
 *
 * This file is not intended to be used by your project. It's an example of how
 * this library could be used.
 *
 * It also serves as a command line utility to extract the images from a PDF,
 * where we know how to do so.
 */

use Smalot\PdfParser\Element\ElementName;
use Smalot\PdfParser\XObject\Image;
use rupertj\pdf_images\PdfImage;

include './vendor/autoload.php';

if (empty($argv[1])) {
  die("Please supply the path to a PDF file.\n");
}

$file = $argv[1];

if (!file_exists($file)) {
  die($file . " does not exist.\n");
}

$parser = new Smalot\PdfParser\Parser();
$pdf = $parser->parseFile($file);
foreach ($pdf->getPages() as $i => $page) {

  foreach ($page->getXObjects() as $j => $xObject) {

    if (!$xObject instanceof Image) {
      continue;
    }

    $image = $xObject;

    $filter = $image->get('Filter')->getContent();
    $width = (int) $image->get('Width')->getContent();
    $height = (int) $image->get('Height')->getContent();
    $bitsPerComponent = (int) $image->get('BitsPerComponent')->getContent();

    // We need to get the image color space like this for some reason.
    $elements = $image->getHeader()->getElements();
    $colorSpace = '';

    if (isset($elements['ColorSpace'])) {
      if ($elements['ColorSpace'] instanceof ElementName) {
        $colorSpace = $elements['ColorSpace']->getContent();
      }
      else {
        // This is when it's a pdfObject.
        $colorSpace = $elements['ColorSpace']->getHeader()
          ->get(0)
          ->getContent();
      }
    }

    if ($filter === 'DCTDecode') {
      // DCTDecode objects are just JPEGs. We can write them to a file and use them.

      $imageFileName = "{$i}_{$j}.jpg";
      file_put_contents($imageFileName, $image->getContent());
      echo "Wrote $imageFileName\n";
    }
    else if ($filter === 'FlateDecode') {

      $imageFileName = "{$i}_{$j}.png";

      // FlateDecode objects have already been decompressed for us by
      // smalot/pdfparser, but aren't images yet. We need to rebuild the pixel
      // data in the source file into an image and save it.
      try {
        $imageFile = PdfImage::createImageFromXObjectData($image->getContent(), $width, $height, $bitsPerComponent, $colorSpace);
        if (!imagepng($imageFile, $imageFileName)) {
          throw new \Exception("Failed to write $imageFileName");
        }
        echo "Wrote $imageFileName\n";
      }
      catch (\Exception $e) {
        echo "Failed to create $imageFileName. \"" . $e->getMessage() . "\" {$width}x{$height} $bitsPerComponent bits $colorSpace.\n";
      }
    }
    else if ($filter === 'JPXDecode') {
      echo "Unimplemented filter $filter\n";
    }
    else {
      echo "Unknown filter $filter\n";
    }
  }
}
