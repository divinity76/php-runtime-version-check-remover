# php-runtime-version-check-remover
remove runtime version check codepaths from php codes... 
inteded to be used as a preprocessor for PHPCompatibility/PHPCompatibility to workaround issues like https://github.com/PHPCompatibility/PHPCompatibility/issues/569 and https://github.com/PHPCompatibility/PHPCompatibility/issues/1339

sample usage:
```sh
$ time nice -n -19 php src/version_check_remover.php --source-dir='/home/hans/projects/CoolProject' --target-dir='/home/hans/projects/CoolProject_for_phpcompat_scan' --verbose 2>&1 | tee log.log

Processing directory: '/home/hans/projects/CoolProject/'
Processing directory: '/home/hans/projects/CoolProject/modules/'
Processing directory: '/home/hans/projects/CoolProject/modules/createad/'
Processing directory: '/home/hans/projects/CoolProject/modules/createad/views/'
Processing '/home/hans/projects/CoolProject/modules/createad/views/generatorHeading.php'
No compatibility code found in '/home/hans/projects/CoolProject/modules/createad/views/generatorHeading.php'
Writing '/home/hans/projects/CoolProject_for_phpcompat_scan/modules/createad/views/generatorHeading.php'
(...)
Processing '/home/hans/projects/CoolProject/vendor/guzzlehttp/guzzle/src/Utils.php'
Found 3 line(s) of compatibility code in '/home/hans/projects/CoolProject/vendor/guzzlehttp/guzzle/src/Utils.php'
Writing '/home/hans/projects/CoolProject_for_phpcompat_scan/vendor/guzzlehttp/guzzle/src/Utils.php'
(...)
real	4m18.389s
user	3m59.256s
sys	0m20.191s

$ rm -f fullreport.log; time nice -n -19 ./vendor/bin/phpcs -p /home/hans/projects/CoolProject_for_phpcompat_scan --standard=PHPCompatibility --extensions=php --runtime-set testVersion 8.1 --report-full=fullreport.log -v
Registering sniffs in the PHPCompatibility standard... DONE (144 sniffs registered)
Creating file list... DONE (14539 files in queue)
Changing into directory /home/hans/projects/CoolProject_for_phpcompat_scan/modules/createad/views
Processing generatorHeading.php [PHP => 316 tokens in 40 lines]... DONE in 10ms (0 errors, 0 warnings)
(...)
Changing into directory /home/hans/projects/CoolProject_for_phpcompat_scan/vendor/guzzlehttp/guzzle/src
Processing Utils.php [PHP => 644 tokens in 92 lines]... DONE in 17ms (0 errors, 0 warnings)
(...)
Time: 22 mins, 30.52 secs; Memory: 868.01MB


real	22m30.598s
user	22m9.777s
sys	0m18.479s
```