<?php
/**
 * ImageOptimizer — dependency-free image optimisation using the GD extension.
 *
 * - Resize to max dimensions (keeps aspect ratio, never upscales)
 * - Convert to WebP for smaller files
 * - Strip EXIF / metadata and flatten transparency onto a background colour
 *
 * Requires the PHP GD extension (php-gd).
 */
class ImageOptimizer
{
    /** @var array Supported input types mapped to GD loader/saver hints. */
    private static $types = [
        IMAGETYPE_JPEG => ['load' => 'imagecreatefromjpeg', 'save' => 'imagejpeg',  'ext' => 'jpg'],
        IMAGETYPE_PNG  => ['load' => 'imagecreatefrompng',  'save' => 'imagepng',   'ext' => 'png'],
        IMAGETYPE_GIF  => ['load' => 'imagecreatefromgif',  'save' => 'imagegif',   'ext' => 'gif'],
        IMAGETYPE_WEBP => ['load' => 'imagecreatefromwebp', 'save' => 'imagewebp',  'ext' => 'webp'],
    ];

    /**
     * Optimise an image and write the result to $destination.
     *
     * @param string $source       Path to the source image.
     * @param string $destination  Path where the optimised image is written.
     * @param array  $options      Supported keys:
     *   - max_width   (int)   default 1600 — longest side cap, no upscale
     *   - quality     (int)   default 80   — JPEG/WebP quality 1–100
     *   - convert_webp(bool)  default true — convert JPEG/PNG/GIF to WebP
     *   - bg          (array) default [255,255,255] — background for flattened transparency
     * @return array  ['ok'=>bool,'before'=>int,'after'=>int,'saved_pct'=>float,'format'=>string,'error'=>?string]
     */
    public static function optimise($source, $destination, array $options = [])
    {
        $options = array_merge([
            'max_width'    => 1600,
            'quality'      => 80,
            'convert_webp' => true,
            'bg'           => [255, 255, 255],
        ], $options);

        if (!extension_loaded('gd')) {
            return ['ok' => false, 'error' => 'GD extension not loaded (install php-gd)'];
        }
        if (!is_file($source) || !is_readable($source)) {
            return ['ok' => false, 'error' => 'Source file not readable: ' . $source];
        }

        $info = @getimagesize($source);
        if ($info === false || !isset(self::$types[$info[2]])) {
            return ['ok' => false, 'error' => 'Unsupported image type: ' . $source];
        }

        $loader = self::$types[$info[2]]['load'];
        $img = @$loader($source);
        if ($img === false) {
            return ['ok' => false, 'error' => 'Could not decode image: ' . $source];
        }

        [$w, $h] = [$info[0], $info[1]];

        // Resize down only — never upscale small images.
        $max = max(1, (int) $options['max_width']);
        if (max($w, $h) > $max) {
            $ratio = $max / max($w, $h);
            $newW = (int) round($w * $ratio);
            $newH = (int) round($h * $ratio);
            $resized = imagecreatetruecolor($newW, $newH);

            // Preserve transparency for PNG/GIF when not converting.
            if (!$options['convert_webp'] && in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_GIF], true)) {
                imagealphablending($resized, false);
                imagesavealpha($resized, true);
                $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
                imagefill($resized, 0, 0, $transparent);
            } else {
                // Flatten onto the background colour (WebP has no need for alpha here).
                [$r, $g, $b] = array_map('intval', (array) $options['bg']);
                $bg = imagecolorallocate($resized, $r, $g, $b);
                imagefill($resized, 0, 0, $bg);
            }

            imagecopyresampled($resized, $img, 0, 0, 0, 0, $newW, $newH, $w, $h);
            imagedestroy($img);
            $img = $resized;
        }

        // Decide the output format. WebP conversion also strips EXIF metadata.
        $format = $info[2];
        $ext = self::$types[$info[2]]['ext'];
        if ($options['convert_webp'] && function_exists('imagewebp')) {
            $format = IMAGETYPE_WEBP;
            $ext = 'webp';
        }

        $dir = dirname($destination);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            imagedestroy($img);
            return ['ok' => false, 'error' => 'Cannot create destination dir: ' . $dir];
        }

        $quality = max(1, min(100, (int) $options['quality']));
        $saved = false;
        if ($format === IMAGETYPE_WEBP) {
            $saved = imagewebp($img, $destination, $quality);
        } elseif ($format === IMAGETYPE_JPEG) {
            $saved = imagejpeg($img, $destination, $quality);
        } elseif ($format === IMAGETYPE_PNG) {
            // Map 1–100 quality to PNG compression level 0–9 (inverted).
            $saved = imagepng($img, $destination, (int) round(9 - ($quality / 100) * 9));
        } else {
            $saved = imagegif($img, $destination);
        }
        imagedestroy($img);

        if (!$saved) {
            return ['ok' => false, 'error' => 'Failed writing: ' . $destination];
        }

        $before = filesize($source);
        $after = filesize($destination);
        return [
            'ok'        => true,
            'before'    => $before,
            'after'     => $after,
            'saved_pct' => $before > 0 ? round((1 - $after / $before) * 100, 1) : 0,
            'format'    => $ext,
        ];
    }
}
