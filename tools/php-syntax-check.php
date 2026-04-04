<?php
/**
 * Esegue `php -l` su tutti i file .php del plugin, escludendo vendor / .git / node_modules.
 * Uso dalla root del plugin: `php tools/php-syntax-check.php` oppure `composer lint:syntax`.
 * Intercetta parse error prima del deploy (es. merge che duplica la chiusura delle classi).
 *
 * @package FP\DiscountGift\Tools
 */

declare(strict_types=1);

$root            = dirname(__DIR__);
$excludeDirNames = array( 'vendor', '.git', 'node_modules' );

$iterator = new RecursiveIteratorIterator(
    new RecursiveCallbackFilterIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        static function (SplFileInfo $current) use ($excludeDirNames): bool {
            $name = $current->getFilename();
            if ($current->isDir()) {
                return ! in_array($name, $excludeDirNames, true);
            }

            return strtolower($current->getExtension()) === 'php';
        }
    ),
    RecursiveIteratorIterator::LEAVES_ONLY
);

$ok = true;

/** @var SplFileInfo $file */
foreach ($iterator as $file) {
    if (! $file->isFile()) {
        continue;
    }
    $path = $file->getPathname();
    passthru('php -l ' . escapeshellarg($path), $code);
    if (0 !== $code) {
        $ok = false;
    }
}

exit($ok ? 0 : 1);
