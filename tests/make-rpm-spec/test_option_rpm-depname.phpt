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
error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'setup.php.inc';
$savedir = getcwd();

$package = 'Net_SMTP-1.2.8';

mkdir($temp_path . DIRECTORY_SEPARATOR . 'SOURCES');
mkdir($temp_path . DIRECTORY_SEPARATOR . 'SPECS');
chdir($temp_path . DIRECTORY_SEPARATOR . 'SPECS');
copy(
    dirname(__FILE__) . DIRECTORY_SEPARATOR . 'packagefiles' . DIRECTORY_SEPARATOR . "$package.tgz",
    $temp_path . DIRECTORY_SEPARATOR . 'SOURCES' . DIRECTORY_SEPARATOR . "$package.tgz"
);
$ret = $command->run('make-rpm-spec', array('rpm-depname'=>'php-pear(%s)'), array($temp_path . DIRECTORY_SEPARATOR . 'SOURCES' . DIRECTORY_SEPARATOR . "$package.tgz"));
$phpunit->assertNoErrors('ret 1');
$phpunit->showall();

chdir($savedir);
$phpunit->assertFileExists($temp_path . DIRECTORY_SEPARATOR . 'SPECS' . 
    DIRECTORY_SEPARATOR . "PEAR::$package.spec", 'spec file');
$phpunit->assertEquals(
    file_get_contents(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'packagefiles' . DIRECTORY_SEPARATOR . 'test_option_rpm-depname.spec'),
    file_get_contents($temp_path . DIRECTORY_SEPARATOR . 'SPECS' . DIRECTORY_SEPARATOR . "PEAR::$package.spec"),
    'spec file contents'
);

echo 'tests done';
?>
--CLEAN--
<?php
require_once dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'teardown.php.inc';
?>
--EXPECT--
tests done


