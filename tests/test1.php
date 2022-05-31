#!/usr/bin/env php
<?php
declare (strict_types = 1);
chdir(__DIR__ . '/../');
require_once 'vendor/autoload.php'; // idk if i need to replace / with DIRECTORY_SEPARATOR or not, cba checking.

$cmd = implode(" ", array(
    "php",
    escapeshellarg("src/version_check_remover.php"),
    "--source-dir=" . escapeshellarg("tests/test1_source"),
    "--target-dir=" . escapeshellarg("tests/test1_target"),
    "--verbose",
));
echo $cmd . "\n";
$ret = null;
passthru($cmd, $ret);
if ($ret !==  0) {
    echo "\nwarning! expected ret 0, but got {$ret}";
}
exit($ret);