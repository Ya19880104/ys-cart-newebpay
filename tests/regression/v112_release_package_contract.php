<?php
/**
 * NewebPay release package must include runtime docs/SDK/skills and exclude dev files.
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$artifacts = glob($root . '/artifacts/ys-cart-newebpay-*.zip') ?: [];

if (!$artifacts) {
    echo "v112_release_package_contract skipped: no release zip built yet" . PHP_EOL;
    exit(0);
}

rsort($artifacts);
$zipPath = $artifacts[0];

if (!class_exists('ZipArchive')) {
    fwrite(STDERR, "ZipArchive extension is required to inspect {$zipPath}" . PHP_EOL);
    exit(1);
}

$zip = new ZipArchive();
if (true !== $zip->open($zipPath)) {
    fwrite(STDERR, "Unable to open release zip: {$zipPath}" . PHP_EOL);
    exit(1);
}

$names = [];
for ($i = 0; $i < $zip->numFiles; $i++) {
    $names[] = (string) $zip->getNameIndex($i);
}
$zip->close();

$mustHave = [
    'ys-cart-newebpay/ys-cart-newebpay.php',
    'ys-cart-newebpay/manifest.php',
    'ys-cart-newebpay/vendor/autoload.php',
    'ys-cart-newebpay/vendor/yangsheep/ys-plugin-hub-client/ys-plugin-hub-client.php',
    'ys-cart-newebpay/README.md',
    'ys-cart-newebpay/docs/headless.md',
    'ys-cart-newebpay/sdk/ys-cart-newebpay-headless.js',
    'ys-cart-newebpay/skills/ys-cart-newebpay-headless.md',
];

foreach ($mustHave as $entry) {
    if (!in_array($entry, $names, true)) {
        fwrite(STDERR, "Release zip missing required entry: {$entry}" . PHP_EOL);
        exit(1);
    }
}

$forbiddenPatterns = [
    '#^ys-cart-newebpay/\\.git/#',
    '#^ys-cart-newebpay/\\.github/#',
    '#^ys-cart-newebpay/artifacts/#',
    '#^ys-cart-newebpay/bin/#',
    '#^ys-cart-newebpay/tests/#',
    '#^ys-cart-newebpay/tmp/#',
    '#^ys-cart-newebpay/node_modules/#',
    '#^ys-cart-newebpay/\\.env(\\..*)?$#',
    '#\\.log$#',
    '#\\.tmp$#',
    '#^ys-cart-newebpay/composer\\.(json|lock)$#',
];

foreach ($names as $entry) {
    foreach ($forbiddenPatterns as $pattern) {
        if (preg_match($pattern, $entry)) {
            fwrite(STDERR, "Release zip includes forbidden entry: {$entry}" . PHP_EOL);
            exit(1);
        }
    }
}

echo "v112_release_package_contract passed" . PHP_EOL;
