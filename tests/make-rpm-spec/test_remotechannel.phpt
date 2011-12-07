--TEST--
make-rpm-spec command - make a specfile for a package from a non-default channel
--SKIPIF--
<?php
if (!getenv('PHP_PEAR_RUNTESTS')) {
    echo "skip\n";
}
?>
--FILE--
<?php
require_once dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'setup.php.inc';

// Install the pear.example.com channel file so we can build Test_Package
require_once 'PEAR/Command/Channels.php';
$command2 = new PEAR_Command_Channels($fakelog, $config);
$command2->run('channel-add',array(), array(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'packagefiles' . DIRECTORY_SEPARATOR . 'channel.xml'));

$tarball = 'Test_Package-1.1.0.tgz';
$test_spec = 'test_remotechannel.spec';
$output_spec = 'example::Test_Package-1.1.0.spec';

$savedir = getcwd();

mkdir($temp_path . DIRECTORY_SEPARATOR . 'SOURCES');
mkdir($temp_path . DIRECTORY_SEPARATOR . 'SPECS');
chdir($temp_path . DIRECTORY_SEPARATOR . 'SPECS');
copy(
    dirname(__FILE__) . DIRECTORY_SEPARATOR . 'packagefiles' . DIRECTORY_SEPARATOR . $tarball, 
    $temp_path . DIRECTORY_SEPARATOR . 'SOURCES' . DIRECTORY_SEPARATOR . $tarball
);

$ret = $command->run(
    'make-rpm-spec',
    array('rpm-depname'=>'php-pear(%N%s)'),
    array($temp_path . DIRECTORY_SEPARATOR . 'SOURCES' . DIRECTORY_SEPARATOR . $tarball)
);

$phpunit->assertNoErrors('Check there were no errors');
$phpunit->showall();

chdir($savedir);
$phpunit->assertFileExists(
    $temp_path . DIRECTORY_SEPARATOR . 'SPECS' . DIRECTORY_SEPARATOR . $output_spec,
    'Check spec file exists'
);

$phpunit->assertEquals(file_get_contents(
    dirname(__FILE__) . DIRECTORY_SEPARATOR . 'packagefiles' . DIRECTORY_SEPARATOR .  $test_spec),
    @file_get_contents($temp_path . DIRECTORY_SEPARATOR . 'SPECS' . DIRECTORY_SEPARATOR . $output_spec),
    'Check spec file contents are correct'
);
echo 'tests done';
?>
--CLEAN--
<?php
require_once dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'teardown.php.inc';
?>
--EXPECT--
tests done


