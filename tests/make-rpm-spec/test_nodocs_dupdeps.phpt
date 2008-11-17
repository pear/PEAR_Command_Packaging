--TEST--
make-rpm-spec command, pear package
--SKIPIF--
<?php
if (!getenv('PHP_PEAR_RUNTESTS')) {
    echo 'skip';
}
?>
--FILE--
<?php
error_reporting(E_ALL);
require_once dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'setup.php.inc';
$savedir = getcwd();

$package = 'PEAR_Command_Packaging-0.1.0';

mkdir($temp_path . DIRECTORY_SEPARATOR . 'SOURCES');
mkdir($temp_path . DIRECTORY_SEPARATOR . 'SPECS');
chdir($temp_path . DIRECTORY_SEPARATOR . 'SPECS');
copy(dirname(__FILE__) . "/packagefiles/$package.tgz", $temp_path .
DIRECTORY_SEPARATOR .
    'SOURCES' . DIRECTORY_SEPARATOR . "$package.tgz");
// 1.0
$ret = $command->run('make-rpm-spec', array(),
	array("../SOURCES/$package.tgz"));
$phpunit->assertNoErrors('ret 1');
$phpunit->showall();

chdir($savedir);
$phpunit->assertFileExists($temp_path . DIRECTORY_SEPARATOR . 'SPECS' . 
    DIRECTORY_SEPARATOR . "PEAR::$package.spec", 'spec file');
$phpunit->assertEquals(file_get_contents(dirname(__FILE__) . '/packagefiles/test_nodocs_dupdeps.spec'), file_get_contents($temp_path .
    DIRECTORY_SEPARATOR . 'SPECS' .
    DIRECTORY_SEPARATOR . "PEAR::$package.spec"), 'spec file contents');

echo 'tests done';
?>
--CLEAN--
<?php
require_once dirname(dirname(__FILE__)) . '/teardown.php.inc';
?>
--EXPECT--
tests done


