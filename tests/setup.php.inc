<?php

$server = 'test.fake';
$temp_path = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'testinstallertemp';

require_once 'PEAR.php';
require_once 'PEAR/Frontend.php';
class fake_log extends PEAR_Frontend
{
    var $_log = array();
    var $_download = array();
    function fake_log()
    {
        $GLOBALS['_PEAR_FRONTEND_SINGLETON'] = &$GLOBALS['fakelog'];
    }

    function log($level, $message)
    {
        $GLOBALS['fakelog']->_log[] = array($level, $message);
    }

    function outputData($info, $cmd = 'no command')
    {
        $GLOBALS['fakelog']->_log[] = array('info' => $info, 'cmd' => $cmd);
    }

    function _downloadCallback($msg, $params)
    {
        if ($msg == 'setup') {
            $params = 'self';
        }
        $GLOBALS['fakelog']->_download[] = array($msg, $params);
    }

    function getLog()
    {
        $log = $GLOBALS['fakelog']->_log;
        $GLOBALS['fakelog']->_log = array();
        return $log;
    }

    function getDownload()
    {
        $log = $GLOBALS['fakelog']->_download;
        $GLOBALS['fakelog']->_download = array();
        return $log;
    }

    function setDialogOutput($input, $output)
    {
        $GLOBALS['fakelog']->_dialogInput[var_export($input, true)] = $output;
    }

    function userDialog()
    {
        $input = func_get_args();
        if (isset($GLOBALS['fakelog']->_dialogInput[var_export($input, true)])) {
            return $GLOBALS['fakelog']->_dialogInput[var_export($input, true)];
        }
    }
}

function cleanall($dir = null)
{
    if ($dir !== null) {
        $statedir = $dir;
    } else {
        $statedir = $GLOBALS['statedir'];
    }
    clearstatcache();
    if (file_exists($statedir)) {
        $dp = opendir($statedir);
        while ($ent = readdir($dp)) {
            if (in_array($ent, array('.', '..'))) {
                continue;
            }
            if (is_dir($statedir . DIRECTORY_SEPARATOR . $ent)) {
                cleanall($statedir . DIRECTORY_SEPARATOR . $ent);
                continue;
            }
            unlink($statedir . DIRECTORY_SEPARATOR . $ent);
        }
        closedir($dp);
        rmdir($statedir);
    }
}

cleanall($temp_path);
$statedir = $temp_path;
if (!is_dir($temp_path)) {
    mkdir($temp_path);
}
if (!is_dir($temp_path . DIRECTORY_SEPARATOR . 'php')) {
    mkdir($temp_path . DIRECTORY_SEPARATOR . 'php');
}
if (!is_dir($temp_path . DIRECTORY_SEPARATOR . 'data')) {
    mkdir($temp_path . DIRECTORY_SEPARATOR . 'data');
}
if (!is_dir($temp_path . DIRECTORY_SEPARATOR . 'doc')) {
    mkdir($temp_path . DIRECTORY_SEPARATOR . 'doc');
}
if (!is_dir($temp_path . DIRECTORY_SEPARATOR . 'test')) {
    mkdir($temp_path . DIRECTORY_SEPARATOR . 'test');
}
if (!is_dir($temp_path . DIRECTORY_SEPARATOR . 'ext')) {
    mkdir($temp_path . DIRECTORY_SEPARATOR . 'ext');
}
if (!is_dir($temp_path . DIRECTORY_SEPARATOR . 'script')) {
    mkdir($temp_path . DIRECTORY_SEPARATOR . 'script');
}
if (!is_dir($temp_path . DIRECTORY_SEPARATOR . 'tmp')) {
    mkdir($temp_path . DIRECTORY_SEPARATOR . 'tmp');
}
if (!is_dir($temp_path . DIRECTORY_SEPARATOR . 'bin')) {
    mkdir($temp_path . DIRECTORY_SEPARATOR . 'bin');
}
if (!is_dir($temp_path . DIRECTORY_SEPARATOR . 'cache')) {
    mkdir($temp_path . DIRECTORY_SEPARATOR . 'cache');
}
if (!is_dir($temp_path . DIRECTORY_SEPARATOR . 'temp')) {
    mkdir($temp_path . DIRECTORY_SEPARATOR . 'temp');
}
// make the fake configuration - we'll use one of these and it should work
$config = serialize(
    array(
        'master_server' => $server,
        'preferred_state' => 'stable',
        'cache_dir' => $temp_path . DIRECTORY_SEPARATOR . 'cache',
        'php_dir' => $temp_path . DIRECTORY_SEPARATOR . 'php',
        'ext_dir' => $temp_path . DIRECTORY_SEPARATOR . 'ext',
        'data_dir' => $temp_path . DIRECTORY_SEPARATOR . 'data',
        'doc_dir' => $temp_path . DIRECTORY_SEPARATOR . 'doc',
        'test_dir' => $temp_path . DIRECTORY_SEPARATOR . 'test',
        'bin_dir' => $temp_path . DIRECTORY_SEPARATOR . 'bin',
        'temp_dir' => $temp_path . DIRECTORY_SEPARATOR . 'temp',
    )
);
touch($temp_path . DIRECTORY_SEPARATOR . 'pear.conf');
$fp = fopen($temp_path . DIRECTORY_SEPARATOR . 'pear.conf', 'w');
fwrite($fp, $config);
fclose($fp);

touch($temp_path . DIRECTORY_SEPARATOR . 'pear.ini');
$fp = fopen($temp_path . DIRECTORY_SEPARATOR . 'pear.ini', 'w');
fwrite($fp, $config);
fclose($fp);

putenv('PHP_PEAR_SYSCONF_DIR='.$temp_path);
$home = getenv('HOME');
if (!empty($home)) {
    // for PEAR_Config initialization
    putenv('HOME="'.$temp_path);
}
//require_once "PEAR/ChannelFile.php";
//require_once dirname(dirname(__FILE__)) . '/download_test_classes.php.inc';
require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'phpt_test.php.inc';
$phpunit = new PEAR_PHPTest(true);
$fakelog = new fake_log;
require_once 'PEAR/Config.php';
$config = new PEAR_Config($temp_path . '/pear.ini', $temp_path . '/pear.conf');

require_once 'PEAR/Command/Packaging.php';
require_once 'PEAR/PackageFile.php';
require_once 'PEAR/Installer.php';
class test_PEAR_Command_Packaging extends PEAR_Command_Packaging
{
    function getPackageFile($c, $d = false, $t = null)
    {
        $a = new PEAR_PackageFile($c, $d, $t);
        $a->setLogger($GLOBALS['fakelog']);
        return $a;
    }

    /*function getInstaller()
    {
        $a = new PEAR_Installer($this->ui);
        $a->setConfig($this->config);
        return $a;
    }*/
	
	// this is not used at the moment as the output from the installer (featuring paths)
	// is not examined, but may be useful in future as it provides a "known" temp
	// directory name rather than the random one from System::mktemp
	function makeTempDir() 
	{
		static $a = 0;
		$dir = $GLOBALS['temp_path'] . DIRECTORY_SEPARATOR . 'pear2rpm-temp' . $a++;
		mkdir($dir);
		return $dir;
	}
}

$command = new test_PEAR_Command_Packaging($fakelog, $config);
