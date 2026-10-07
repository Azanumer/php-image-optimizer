# PHP Image Optimizer

Dependency-free image optimisation for PHP (GD extension only). Resize, convert to WebP, and strip EXIF metadata — perfect for shrinking WordPress uploads before they reach the browser.

## What's inside

| File | What it does |
|---|---|
| `src/ImageOptimizer.php` | Static `optimise()` class: max-width resize (no upscaling), JPEG/PNG/GIF → WebP conversion, quality control, transparency flattening |
| `optimize-images.php` | CLI batch runner — optimises a whole directory, originals untouched, prints a before/after report |

## Requirements

- PHP 7.4+ with the GD extension (`php-gd`)

## Usage

```php
require 'src/ImageOptimizer.php';

$result = ImageOptimizer::optimise('uploads/photo.jpg', 'uploads/photo-opt.webp', [
    'max_width'    => 1600,   // cap longest side; smaller images are left alone
    'quality'      => 80,     // 1–100
    'convert_webp' => true,   // WebP conversion (strips EXIF)
    'bg'           => [255, 255, 255], // background when flattening transparency
]);

if ($result['ok']) {
    echo "Saved {$result['saved_pct']}% ({$result['before']} → {$result['after']} bytes)";
}
```

Batch a whole uploads folder:

```bash
php optimize-images.php /var/www/mysite/wp-content/uploads --max-width=1600 --quality=80
```

MIT licensed. Contributions welcome.
