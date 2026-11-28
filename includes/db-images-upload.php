<?php

/**
 * @param array $file
 * @param int   $maxDim
 * @param int   $quality
 * @param int   $maxBytes
 * @return string
 * @throws RuntimeException
 */
function sw_uploaded_image_to_data_uri(
    array $file,
    int $maxDim = 900,
    int $quality = 75,
    int $maxBytes = 5242880
): string {
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('File upload failed or no file was selected.');
    }

    if (!is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Invalid uploaded file source.');
    }

    if (($file['size'] ?? 0) > $maxBytes) {
        $maxMb = (int) round($maxBytes / (1024 * 1024));
        throw new RuntimeException("Image size must be less than {$maxMb}MB.");
    }

    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
    } else {
        $imgInfo = @getimagesize($file['tmp_name']);
        $mime    = $imgInfo['mime'] ?? '';
    }

    if (!in_array($mime, $allowedTypes, true)) {
        throw new RuntimeException('Only JPG, PNG, or WEBP images are allowed.');
    }

    $binaryData = file_get_contents($file['tmp_name']);
    if ($binaryData === false || $binaryData === '') {
        throw new RuntimeException('Unable to read uploaded image.');
    }

    if (function_exists('imagecreatefromstring')) {
        $srcImg = @imagecreatefromstring($binaryData);
        if ($srcImg !== false) {
            if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
                $exif = @exif_read_data($file['tmp_name']);
                $orientation = (int) ($exif['Orientation'] ?? 1);
                if ($orientation === 3) {
                    $srcImg = imagerotate($srcImg, 180, 0);
                } elseif ($orientation === 6) {
                    $srcImg = imagerotate($srcImg, -90, 0);
                } elseif ($orientation === 8) {
                    $srcImg = imagerotate($srcImg, 90, 0);
                }
            }

            $origW = imagesx($srcImg);
            $origH = imagesy($srcImg);

            if ($origW > $maxDim || $origH > $maxDim) {
                $ratio = min($maxDim / $origW, $maxDim / $origH);
                $newW  = max(1, (int) round($origW * $ratio));
                $newH  = max(1, (int) round($origH * $ratio));
            } else {
                $newW = $origW;
                $newH = $origH;
            }

            $dstImg = imagecreatetruecolor($newW, $newH);
            $white = imagecolorallocate($dstImg, 255, 255, 255);
            imagefilledrectangle($dstImg, 0, 0, $newW, $newH, $white);
            imagecopyresampled($dstImg, $srcImg, 0, 0, 0, 0, $newW, $newH, $origW, $origH);

            ob_start();
            imagejpeg($dstImg, null, $quality);
            $compressed = ob_get_clean();

            imagedestroy($srcImg);
            imagedestroy($dstImg);

            if ($compressed !== false && $compressed !== '') {
                $binaryData = $compressed;
                $mime       = 'image/jpeg';
            }
        }
    }

    return 'data:' . $mime . ';base64,' . base64_encode($binaryData);
}

function sw_resolve_db_image(?string $storedValue, string $relativePrefix = '../'): string
{
    $val = trim((string) $storedValue);
    if ($val === '') {
        return '';
    }

    if (str_starts_with($val, 'data:image/') || preg_match('#^https?://#i', $val)) {
        return $val;
    }

    return rtrim($relativePrefix, '/') . '/' . ltrim($val, '/');
}