#!/usr/bin/env php
<?php
/**
 * optimize-images.php — batch-optimise every image in a directory.
 *
 * Usage:
 *   php optimize-images.php /path/to/images [--max-width=1600] [--quality=80] [--no-webp]
 *
 * Writes optimised copies next to the originals (originals are never modified)
 * and prints a before/after report.
 */
require __DIR__ . '/../src/ImageOptimizer.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Run this from the command line.\n");
    exit(1);
}

$opts = getopt('', ['max-width::', 'quality::', 'no-webp']);
$dir = $argv[1] ?? null;

if (!$dir || !is_dir($dir)) {
    fwrite(STDERR, "Usage: php optimize-images.php <directory> [--max-width=1600] [--quality=80] [--no-webp]\n");
    exit(1);
}

$options = [
    'max_width'    => (int) ($opts['max-width'] ?? 1600),
    'quality'      => (int) ($opts['quality'] ?? 80),
    'convert_webp' => !isset($opts['no-webp']),
];

$pattern = rtrim($dir, '/') . '/*.{jpg,jpeg,png,gif,webp}';
$files = glob($pattern, GLOB_BRACE) ?: [];

$totalBefore = 0;
$totalAfter = 0;
$done = 0;
$failed = 0;

foreach ($files as $file) {
    $info = pathinfo($file);
    $dest = $info['dirname'] . '/' . $info['filename'] . '-opt';
    $dest .= ($options['convert_webp'] && $info['extension'] !== 'webp') ? '.webp' : '.' . $info['extension'];

    $res = ImageOptimizer::optimise($file, $dest, $options);
    if (!$res['ok']) {
        $failed++;
        fwrite(STDERR, "FAIL {$info['basename']}: {$res['error']}\n");
        continue;
    }
    $done++;
    $totalBefore += $res['before'];
    $totalAfter += $res['after'];
    printf(
        "OK   %-40s %8.1f KB -> %8.1f KB  (saved %.1f%%, %s)\n",
        $info['basename'],
        $res['before'] / 1024,
        $res['after'] / 1024,
        $res['saved_pct'],
        $res['format']
    );
}

printf(
    "\nDone: %d optimised, %d failed. Total %.2f MB -> %.2f MB.\n",
    $done,
    $failed,
    $totalBefore / 1048576,
    $totalAfter / 1048576
);
