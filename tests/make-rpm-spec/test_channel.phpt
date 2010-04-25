--TEST--
make-rpm-spec command - make a specfile for a channel
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

mkdir($temp_path . DIRECTORY_SEPARATOR . 'SOURCES');
mkdir($temp_path . DIRECTORY_SEPARATOR . 'SPECS');
chdir($temp_path . DIRECTORY_SEPARATOR . 'SPECS');
copy(
    dirname(__FILE__) . DIRECTORY_SEPARATOR . 'packagefiles' . DIRECTORY_SEPARATOR . 'channel.xml', 
    $temp_path . DIRECTORY_SEPARATOR . 'SOURCES' . DIRECTORY_SEPARATOR . 'channel.xml'
);

$ret = $command->run(
    'make-rpm-spec',
    array(),
    array($temp_path . DIRECTORY_SEPARATOR . 'SOURCES' . DIRECTORY_SEPARATOR . 'channel.xml')
);

$phpunit->assertNoErrors('ret 1');
$phpunit->showall();

chdir($savedir);
$phpunit->assertFileExists(
    $temp_path . DIRECTORY_SEPARATOR . 'SPECS' . DIRECTORY_SEPARATOR . 'php-channel-example.spec',
    'Check spec file exists'
);

$phpunit->assertEquals(
	file_get_contents(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'packagefiles' . DIRECTORY_SEPARATOR . 'test_channel.spec'),
   file_get_contents($temp_path . DIRECTORY_SEPARATOR . 'SPECS' . DIRECTORY_SEPARATOR . 'php-channel-example.spec'),
    'Check spec file contents are correct'
);

echo "tests done\n";
?>
--CLEAN--
<?php
require_once dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'teardown.php.inc';
?>
--EXPECT--
tests done


