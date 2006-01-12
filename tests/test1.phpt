--TEST--
PEAR_Command_Packaging test sample 1
--SKIPIF--
<?php
if (!getenv('PHP_PEAR_RUNTESTS')) {
    echo 'skip';
}
?>
--FILE--
<?php
require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'setup.php.inc';

$phpunit->assertEquals(1, 1, 'success');
$phpunit->assertEquals(array(1,2), 1, 'failure assertEquals');
PEAR::raiseError('test message');
PEAR_ErrorStack::staticPush('mypackage', 1, 'error', array(), 'test message 2');
$phpunit->assertErrors(array(
    array('package' => 'PEAR_Error', 'message' => 'test message'),
    array('package' => 'mypackage', 'message' => 'test message 2'),
), 'success assertErrors');
$phpunit->assertErrors(array(
    array('package' => 'PEAR_Error', 'message' => 'test message'),
    array('package' => 'mypackage', 'message' => 'test message 2'),
), 'failure assertErrors');
$phpunit->assertNoErrors('success, assertNoErrors');
PEAR::raiseError('test message');
$phpunit->assertNoErrors('failure, assertNoErrors');
$phpunit->assertFileExists(dirname(__FILE__) . 'test1.phpt', 'success, assertFileExists');
$phpunit->assertFileExists(dirname(__FILE__) . 'poo', 'failure, assertFileExists');
$phpunit->assertFileNotExists(dirname(__FILE__) . 'poo', 'success, assertFileNotExists');
$phpunit->assertFileNotExists(dirname(__FILE__) . 'test1.phpt', 'failure, assertFileNotExists');
$phpunit->assertIsa('PEAR_PHPTest', $phpunit, 'success assertIsa');
$phpunit->assertIsa('poo', $phpunit, 'failure, assertIsa');
$phpunit->assertTrue(true, 'success');
$phpunit->assertTrue(1, 'failure assertTrue');
$phpunit->assertNotFalse(1, 'success');
$phpunit->assertFalse(false, 'success');
$phpunit->assertFalse(0, 'failure, assertFalse');
$phpunit->assertNotTrue(0, 'success, assertNotTrue');
$phpunit->assertNull(null, 'success');
$phpunit->assertNotNull($phpunit, 'success, assertNotNull');

// how you run the make-rpm-spec command
//$command->run('make-rpm-spec', array('spec-template' => $temp_path . '/blah.spec', 'fakeoptionwithnoargument' => true), array('package.xml'));
echo 'tests done';
?>
--EXPECT--
tests done