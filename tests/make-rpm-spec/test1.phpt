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

mkdir($temp_path . DIRECTORY_SEPARATOR . 'SOURCES');
mkdir($temp_path . DIRECTORY_SEPARATOR . 'SPECS');
chdir($temp_path . DIRECTORY_SEPARATOR . 'SPECS');
copy(dirname(__FILE__) . '/packagefiles/Net_SMTP-1.2.8.tgz', $temp_path .
DIRECTORY_SEPARATOR .
    'SOURCES' . DIRECTORY_SEPARATOR . 'Net_SMTP-1.2.8.tgz');
// 1.0
$ret = $command->run('make-rpm-spec', array(),
	array('../SOURCES/Net_SMTP-1.2.8.tgz'));
$phpunit->assertNoErrors('ret 1');
$phpunit->showall();

chdir($savedir);
$phpunit->assertFileExists($temp_path . DIRECTORY_SEPARATOR . 'SPECS' . 
    DIRECTORY_SEPARATOR . 'PEAR::Net_SMTP-1.2.8.spec', 'spec file');
$phpunit->assertEquals(file_get_contents(dirname(__FILE__) . '/packagefiles/test1.spec'), file_get_contents($temp_path .
    DIRECTORY_SEPARATOR . 'SPECS' .
    DIRECTORY_SEPARATOR . 'PEAR::Net_SMTP-1.2.8.spec'), 'spec file contents');

echo 'tests done';
?>
--CLEAN--
<?php
require_once dirname(dirname(__FILE__)) . '/teardown.php.inc';
?>
--EXPECT--
tests done


