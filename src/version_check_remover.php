#!/usr/bin/env php
<?php
declare (strict_types = 1);

require_once (__DIR__ . '/../vendor/autoload.php'); // idk if i need to replace / with DIRECTORY_SEPARATOR or not, cba checking.
require_once(__DIR__ . '/php_constants.php');
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Node\Stmt\Function_;
use PhpParser\ParserFactory;

if (!function_exists("dd")) {
    function dd(...$args)
    {
        $file = debug_backtrace()[0]['file'];
        $line = debug_backtrace()[0]['line'];
        echo "dd at {$file}:{$line}\n";
        var_dump(...$args);
        die();
    }
}
function doesCodeLookLikeCompatibilityCode(string $code): bool
{    
    // first detect code like if(PHP_MAJOR_VERSION < 8)
    if(preg_match("/PHP_MAJOR_VERSION\s*\</", $code)) {
        return true;
    }
    // then detect code like if(version_compare(PHP_VERSION, '7.0.0')  < ) and if(version_compare(PHP_VERSION, '7.0.0' '<=') )
    if(preg_match("/version_compare\s*\(\s*PHP_VERSION[\s\S]*?\</", $code)) {
        return true;
    }
    // then detect code like if ( defined( 'E_DEPRECATED' ) ) { define( 'QM_E_DEPRECATED', E_DEPRECATED );}
    $consts = get_constants("99.99.99", $get_removed = true);
    foreach($consts as $const) {
        if(preg_match("/defined\s*\(\s*['\"]".preg_quote($const,'/')."['\"]/", $code)) {
            return true;
        }
    }
    // i guess it's not compat code?
    return false;
}
function comment_out_lines(string $raw, array $lines): string
{
    $raw = explode("\n", $raw);
    foreach ($lines as $line) {
        $raw[$line] = "// " . $raw[$line];
    }
    return implode("\n", $raw);
}
function get_line_range(string $raw, int $start_line, int $end_line): string
{
    if($start_line > $end_line) {
        throw new \RangeException("start_line > end_line");
    }
    if($start_line < 0) {
        throw new \RangeException("start_line < 0");
    }

    $lines= explode("\n", $raw);
    if($end_line >= count($lines)) {
        throw new \RangeException("end_line >= count(lines)");
    }
    $lines = array_slice($raw, $start_line, $end_line - $start_line + 1);
    return implode("\n", $lines);
}
function process_file(string $source, string $target, bool $verbose): void
{
    if ($verbose) {
        echo "Processing '{$source}'\n";
    }
    $raw = file_get_contents($source);
    if ($raw === false) {
        throw new RuntimeException("Failed to read file: $source");
    }
    $linesToCommentOut = [];
    $parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
    $ast = $parser->parse($raw);

    $traverser = new NodeTraverser();
    $traverser->addVisitor(new class($linesToCommentOut) extends NodeVisitorAbstract
    {
        private $linesToCommentOut = [];
        function __construct(array &$linesToCommentOut)
        {
            $this->linesToCommentOut = &$linesToCommentOut;
        }
        public function enterNode(Node $node)
        {
            if (false) {
                if ($node instanceof Function_) {
                    // Clean out the function body
                    foreach ($node->getStmts() as $stmt) {
                        for ($i = $stmt->getStartLine(); $i <= $stmt->getEndLine(); ++$i) {
                            $this->linesToCommentOut[$i - 1] = null; // TODO: why -1? does getStartLine count from 1?
                        }
                    }
                    //var_dump($node->stmts);
                }
            }
            if($node instanceof PhpParser\Node\Stmt\If_){
                $prettyPrinter = new PhpParser\PrettyPrinter\Standard;
                $ifCode = $prettyPrinter->prettyPrint([$node]);
                $firstLine = explode("\n", $ifCode, 2)[0];
                if(doesCodeLookLikeCompatibilityCode($firstLine)) {
                    for($i = $node->getStartLine(); $i <= $node->getEndLine(); $i++) {
                        $this->linesToCommentOut[$i - 1] = null; // TODO: why -1? does getStartLine count from 1?
                    }
                }
            }
        }
    });
    $astCleaned = $traverser->traverse($ast);
    $compatCount = count($linesToCommentOut);
    $rawCleaned = null;
    if($compatCount === 0) {
        if ($verbose) {
            echo "No compatibility code found in '{$source}'\n";
        }
        $rawCleaned = $raw;
    }else{
        if($verbose) {
            echo "Found {$compatCount} line(s) of compatibility code in '{$source}'\n";
        }
        $rawCleaned = comment_out_lines($raw, array_keys($linesToCommentOut));
    }
    if($verbose) {
        echo "Writing '{$target}'\n";
    }
    if(($toWrite = strlen($rawCleaned)) !== ($written = file_put_contents($target, $rawCleaned, LOCK_EX))) {
        throw new RuntimeException("Failed to write file: $target, could only write {$written}/{$toWrite} bytes");
    }
}

function process_dir(string $source_dir, string $target_dir, bool $verbose): void
{
    $source_dir = rtrim($source_dir, '/\\') . DIRECTORY_SEPARATOR;
    $target_dir = rtrim($target_dir, '/\\') . DIRECTORY_SEPARATOR;
    if ($verbose) {
        echo "Processing directory: '{$source_dir}'\n";
    }
    if (!is_dir($source_dir)) {
        throw new Exception("Source directory '$source_dir' is not a directory");
    }
    $dir = opendir($source_dir);
    if ($dir === false) {
        error_log("Failed to open directory '{$source_dir}'\n");
        return;
    }
    if (!is_dir($target_dir)) {
        if (!mkdir($target_dir, 0755, true)) {
            throw new Exception("Could not create target directory '$target_dir'");
        }
    }
    while (false !== ($file = readdir($dir))) {
        if ($file === '.' || $file === '..') {
            continue;
        }
        $source_file = $source_dir . $file;
        $target_file = $target_dir . $file;
        if (is_dir($source_file)) {
            process_dir($source_file, $target_file, $verbose);
        } else {
            if (pathinfo($source_file, PATHINFO_EXTENSION) === 'php') {
                try{
                    process_file($source_file, $target_file, $verbose);
                } catch(\PhpParser\Error $syntaxError) {
                    // todo should i throw or keep on going? idk
                    error_log("Failed to process file '{$source_file}': {$syntaxError->getMessage()}\n");
                    if(!copy($source_file, $target_file)) {
                        throw new Exception("Failed to copy file '{$source_file}' to '{$target_file}'");
                    }
                }
            }
        }
    }
    closedir($dir);
}
function printUsage(): void
{
    global $argv;
    echo "Usage: {$argv[0]} --source-dir='<source_dir>' --target-dir='<target_dir>' [--verbose]\n";
}

global $argc, $argv;
$verbose = false;
$source_dir = $target_dir = null;
if (false && $argc < 3 || $argc > 4) {
    printUsage();
    exit(1);
}
foreach ($argv as $key => $arg) {
    if ($key === 0) {
        continue;
    }
    if (strpos($arg, '--verbose') === 0) {
        $verbose = true;
    } elseif (strpos($arg, '--source-dir=') === 0) {
        $source_dir = substr($arg, strlen('--source-dir='));
    } elseif (strpos($arg, '--target-dir') === 0) {
        $target_dir = substr($arg, strlen('--target-dir='));
    } else {
        printUsage();
        throw new RuntimeException("Unknown argument: $arg");
    }
}
if ($source_dir === null) {
    printUsage();
    throw new RuntimeException("Missing argument: --source-dir");
}
if ($target_dir === null) {
    printUsage();
    throw new RuntimeException("Missing argument: --target-dir");
}
process_dir($source_dir, $target_dir, $verbose);