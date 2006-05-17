<?php
/**
 * PEAR_Command_Packaging (make-rpm-spec commands)
 *
 * PHP versions 4 and 5
 *
 * LICENSE: This source file is subject to version 3.01 of the PHP license
 * that is available through the world-wide-web at the following URI:
 * http://www.php.net/license/3_01.txt.  If you did not receive a copy of
 * the PHP License and are unable to obtain it through the web, please
 * send a note to license@php.net so we can mail you a copy immediately.
 *
 * @category   pear
 * @package    PEAR
 * @author     Tim Jackson <timj@php.net>
 * @author     Greg Beaver <cellog@php.net>
 * @copyright  2006 The PHP Group
 * @license    http://www.php.net/license/3_01.txt  PHP License 3.01
 * @version    CVS: $Id$
 * @link       http://pear.php.net/package/PEAR_Command_Packaging
 * @since      File available since Release 0.1.0
 */

/**
 * base class
 */
require_once 'PEAR/Command/Common.php';

/**
 * PEAR commands for RPM management
 *
 * @category   pear
 * @package    PEAR
 * @author     Tim Jackson <timj@php.net>
 * @author     Greg Beaver <cellog@php.net>
 * @copyright  2006 The PHP Group
 * @license    http://www.php.net/license/3_01.txt  PHP License 3.01
 * @version    Release: @package_version@
 * @link       http://pear.php.net/package/PEAR_Command_Packaging
 * @since      Class available since Release 0.1.0
 */

class PEAR_Command_Packaging extends PEAR_Command_Common
{
    var $commands = array(

        'make-rpm-spec' => array(
            'summary' => 'Builds an RPM spec file from a PEAR package',
            'function' => 'doMakeRPM',
            'shortcut' => 'rpm',
            'options' => array(
                'spec-template' => array(
                    'shortopt' => 't',
                    'arg' => 'FILE',
                    'doc' => 'Use FILE as RPM spec file template'
                    ),
                'rpm-release' => array(
                    'shortopt' => 'r',
                    'arg' => 'RELEASE',
                    'doc' => 'RPM release version. Defaults to "1".'
                    ),
                'rpm-pkgname' => array(
                    'shortopt' => 'p',
                    'arg' => 'FORMAT',
                    'doc' => 'Use FORMAT as format string for RPM package name. Substitutions
are as follows:
%s = PEAR package name
%l = PEAR package name (lowercased)
%S = PEAR package name (with underscores replaced with hyphens)
%C = Channel alias
%c = Channel alias, lowercased
Defaults to "%C::%s".',
                    ),
                'rpm-depname' => array(
                    'shortopt' => 'd',
                    'arg' => 'FORMAT',
                    'doc' => 'Use FORMAT as format string for RPM package name. Substitutions
are as for the --rpm-pkgname option. Defaults to be the same as
the format defined by the --rpm-pkgname option.',
                   ),
                ),
            'doc' => '<package-file>

Creates an RPM .spec file for wrapping a PEAR package inside an RPM
package.  Intended to be used from the SPECS directory, with the PEAR
package tarball in the SOURCES directory:

$ cd /path/to/rpm-build-tree/SPECS
$ pear make-rpm-spec ../SOURCES/Net_Socket-1.0.tgz
Wrote RPM spec file PEAR::Net_Socket-1.0.spec
$ rpm -bb PEAR::Net_Socket-1.0.spec
...
Wrote: /path/to/rpm-build-tree/RPMS/noarch/PEAR::Net_Socket-1.0-1.noarch.rpm
',
            ),
        );

    var $output;
    
    // The default format of the RPM package name
    var $_rpm_pkgname_format = '%C::%s';
    
    // The default format of various dependencies that might be generated in the
    // spec file.
    // NULL = "don't generate a dep".
    // %P   = use the same as whatever rpm_pkgname_format is set to be
    var $_rpm_depname_format = array(
        'pkg' => '%P',
        'ext' => 'php-%l',
        'php' => 'php');
    
    // Format of the filename for the output spec file. Substitutions are as per 
    // the rpm-pkgname format string, with the addition of:
    // %v = package version
    // %P = use the same as whatever rpm_pkgname_format is set to be
    var $_rpm_specname_format = '%P-%v.spec';

    /**
     * PEAR_Command_Packaging constructor.
     *
     * @access public
     */
    function PEAR_Command_Packaging(&$ui, &$config)
    {
        parent::PEAR_Command_Common($ui, $config);
    }

    function &getPackageFile($config, $debug = false, $tmpdir = null)
    {
        if (!class_exists('PEAR_Common')) {
            require_once 'PEAR/Common.php';
        }
        if (!class_exists('PEAR/PackageFile.php')) {
            require_once 'PEAR/PackageFile.php';
        }
        $a = &new PEAR_PackageFile($config, $debug, $tmpdir);
        $common = new PEAR_Common;
        $common->ui = $this->ui;
        $a->setLogger($common);
        return $a;
    }

    /**
     * For unit testing purposes
     */
    function &getInstaller(&$ui)
    {
        if (!class_exists('PEAR_Installer')) {
            require_once 'PEAR/Installer.php';
        }
        $a = &new PEAR_Installer($ui);
        return $a;
    }
	
    /**
     * For unit testing purposes
     */
    function makeTempDir()
    {
        return System::mktemp(array('-d', 'pear2rpm'));
    }

    function doMakeRPM($command, $options, $params)
    {
        require_once 'System.php';
        require_once 'Archive/Tar.php';
        if (sizeof($params) != 1) {
            return $this->raiseError("bad parameter(s), try \"help $command\"");
        }
        if (!file_exists($params[0])) {
            return $this->raiseError("file does not exist: $params[0]");
        }
        $reg = &$this->config->getRegistry();
        $pkg = &$this->getPackageFile($this->config, $this->_debug);
        $pf = &$pkg->fromAnyFile($params[0], PEAR_VALIDATE_NORMAL);
        if (PEAR::isError($pf)) {
            $u = $pf->getUserinfo();
            if (is_array($u)) {
                foreach ($u as $err) {
                    if (is_array($err)) {
                        $err = $err['message'];
                    }
                    $this->ui->outputData($err);
                }
            }
            return $this->raiseError("$params[0] is not a valid package");
        }
        $tmpdir = $this->makeTempDir();
        $instroot = $this->makeTempDir();
        $tmp = $this->config->get('verbose');
        $this->config->set('verbose', 0);
        $installer = $this->getInstaller($this->ui);
        require_once 'PEAR/Downloader/Package.php';
        $pack = new PEAR_Downloader_Package($installer);
        $pack->setPackageFile($pf);
        $params[0] = &$pack;
        $installer->setOptions(array('installroot' => $instroot,
                                          'nodeps' => true, 'soft' => true));
        $installer->setDownloadedPackages($params);
        $info = $installer->install($params[0],
                                    array('installroot' => $instroot,
                                          'nodeps' => true, 'soft' => true));
        $pkgdir = $pf->getPackage() . '-' . $pf->getVersion();
        $this->config->set('verbose', $tmp);
        
        // Work out where we are loading the specfile template from
        if (isset($options['spec-template'])) {
            $spec_template = $options['spec-template'];
        } else {
            $spec_template = '@DATA-DIR@/PEAR_Command_Packaging/template.spec';
        }

        // Initialise the RPM package/dep naming format options
        $this->_initialiseNamingOptions($options);        
        
        // Set the RPM release version
        if (isset($options['rpm-release'])) {
            $info['release'] = $options['rpm-release'];
        } else {
            $info['release'] = '1';
        }
        
        // Work out the alias for the channel that this package is in
        $info['possible_channel'] = '';
        $alias = $this->_getChannelAlias($pf->getChannel(), $pf->getPackage());
        if ($alias != 'PEAR' && $alias != 'PECL') {
            $info['possible_channel'] = $pf->getChannel() . '/';
        }

        // Set up some of the basic macros
        $info['rpm_xml_dir'] = '/var/lib/pear';
        $info['extra_config'] = '';
        $info['extra_headers'] = '';
        $info['doc_files'] = array();
        $info['doc_files_relocation_script'] = '';
        $info['doc_files_statement'] = '';
        $info['files'] = '';
        $info['package2xml'] = '';
        $info['rpm_package'] = $this->_getRPMName($pf->getPackage(), $pf->getChannel());
        $info['pear_rpm_name'] = $this->_getRPMName('PEAR', 'pear.php.net', 'pkgdep');
        $info['description'] = wordwrap($info['description']);
        
        // Hook to support virtual provides, where the dependency name differs
        // from the package name
        $rpmdep = $this->_getRPMName($pf->getPackage(), $pf->getChannel(), 'pkgdep');
        if (!empty($rpmdep) && $rpmdep != $info['rpm_package']) {
            $info['extra_headers'] .= "Provides: $rpmdep = " . $pf->getVersion(). "\n";
        }
        
        $srcfiles = 0;
        foreach ($info['filelist'] as $name => $attr) {
            if (!isset($attr['role'])) {
                continue;
            }
            $name = preg_replace('![/:\\\\]!', '/', $name);
            if ($attr['role'] == 'doc') {
                $info['doc_files'][] .= $name;
            // Map role to the rpm vars
            } else {
                $c_prefix = '%{_libdir}/php/pear';
                switch ($attr['role']) {
                    case 'php':
                        $prefix = $c_prefix;
                    break;
                    case 'ext':
                        $prefix = '%{_libdir}/php';
                    break; // XXX good place?
                    case 'src':
                        $srcfiles++;
                        $prefix = '%{_includedir}/php';
                    break; // XXX good place?
                    case 'test':
                        $prefix = "$c_prefix/tests/" . $pf->getPackage();
                    break;
                    case 'data':
                        $prefix = "$c_prefix/data/" . $pf->getPackage();
                    break;
                    case 'script':
                        $prefix = '%{_bindir}';
                    break;
                    default: // non-standard roles
                        $prefix = "$c_prefix/$attr[role]/" . $pf->getPackage();
                        $info['extra_config'] .=
                        "\n        -d {$attr[role]}_dir=$c_prefix/{$attr[role]} \\";
                        $this->ui->outputData('WARNING: role "' . $attr['role'] . '" used, ' .
                            'and will be installed in "' . $c_prefix . '/' . $attr['role'] .
                            '/' . $pf->getPackage() .
                            ' - hand-edit the final .spec if this is wrong', $command);
                    break;
                }
                $name = str_replace('\\', '/', $name);
                $info['files'] .= "$prefix/$name\n";
            }
        }
        
        $ndocs = count($info['doc_files']);
        if ($ndocs > 1) {
            $info['doc_files'] = 'docs/' . $pf->getPackage() . '/{' . implode(',', $info['doc_files']) . '}';
        } elseif ($ndocs > 0) {
            $info['doc_files'] = 'docs/' . $pf->getPackage() . '/' . $info['doc_files'][0];
        } else {
            $info['doc_files'] = '';
        }
        if (!empty($info['doc_files'])) {
            $info['doc_files_statement'] = '%doc ' . $info['doc_files'];
            $info['doc_files_relocation_script'] = "mv %{buildroot}/docs .\n";
        }
        
        if ($srcfiles > 0) {
            require_once 'OS/Guess.php';
            $os = new OS_Guess;
            $arch = $os->getCpu();
        } else {
            $arch = 'noarch';
        }
        $cfg = array('master_server', 'php_dir', 'ext_dir', 'doc_dir',
                     'bin_dir', 'data_dir', 'test_dir');
        foreach ($cfg as $k) {
            if ($k == 'master_server') {
                $chan = $reg->getChannel($pf->getChannel());
                $info[$k] = $chan->getServer();
                continue;
            }
            $info[$k] = $this->config->get($k);
        }
        $info['arch'] = $arch;
        $fp = @fopen($spec_template, "r");
        if (!$fp) {
            return $this->raiseError("could not open RPM spec file template $spec_template: $php_errormsg");
        }
        $info['package'] = $pf->getPackage();
        $info['version'] = $pf->getVersion();
        $info['release_license'] = $pf->getLicense();
        $info['release_state'] = $pf->getState();
        if ($pf->getDeps()) {
            if ($pf->getPackagexmlVersion() == '1.0') {
                $requires = $conflicts = array();
                foreach ($pf->getDeps() as $dep) {
                    if (isset($dep['optional']) && $dep['optional'] == 'yes') {
                        continue;
                    }
                    
                    if (!isset($dep['type']) || $dep['type'] == 'pkg') {
                        $type = 'pkgdep';
                    } else {
                        $type = $dep['type'];
                    }
                    
                    if (!isset($dep['channel'])) $dep['channel'] = null;
                    // $package contains the *dependency name* here, which may or may
                    // not be the same as the package name
                    $package = $this->_getRPMName($dep['name'], $dep['channel'], $type);

                    // If we could not find an RPM namespace equivalent, don't add the dependency
                    if (empty($package)) {
                        continue;
                    }

                    $trans = array(
                        '>' => '>',
                        '<' => '<',
                        '>=' => '>=',
                        '<=' => '<=',
                        '=' => '=',
                        'gt' => '>',
                        'lt' => '<',
                        'ge' => '>=',
                        'le' => '<=',
                        'eq' => '=',
                    );
                    if ($dep['rel'] == 'has') {
                        // We use $package as the index to the $requires array to de-duplicate deps.
                        // Note that in the case of duplicate deps, versioned deps will "win" - see several lines down.
                        $requires[$package] = $package;
                    } elseif ($dep['rel'] == 'not') {
                        $conflicts[] = $package;
                    } elseif ($dep['rel'] == 'ne') {
                        $conflicts[] = $package . ' = ' . $dep['version'];
                    } elseif (isset($trans[$dep['rel']])) {
                        $requires[$package] = $package . ' ' . $trans[$dep['rel']] . ' ' . $dep['version'];
                    }
                }
                if (count($requires)) {
                    $info['extra_headers'] .= 'Requires: ' . implode(', ', $requires) . "\n";
                }
                if (count($conflicts)) {
                    $info['extra_headers'] .= 'Conflicts: ' . implode(', ', $conflicts) . "\n";
                }
            } else {
                $info['package2xml'] = '2'; // tell the spec to use package2.xml
                $requires = $conflicts = array();
                $deps = $pf->getDeps(true);
                if (isset($deps['required']['package'])) {
                    if (!isset($deps['required']['package'][0])) {
                        $deps['required']['package'] = array($deps['required']['package']);
                    }
                    foreach ($deps['required']['package'] as $dep) {

                        if (!isset($dep['type']) || $dep['type'] == 'pkg') {
                            $type = 'pkgdep';
                        } else {
                            $type = $dep['type'];
                        }

                        if (!isset($dep['channel'])) $dep['channel'] = null;
                        // $package contains the *dependency name* here, which may or may
                        // not be the same as the package name
                        $package = $this->_getRPMName($dep['name'], $dep['channel'], $type);
                        
                        if (empty($package)) {
                            continue;
                        }
                        
                        if (isset($dep['conflicts']) && (isset($dep['min']) ||
                              isset($dep['max']))) {
                            $deprange = array();
                            if (isset($dep['min'])) {
                                $deprange[] = array($dep['min'],'>=');
                            }
                            if (isset($dep['max'])) {
                                $deprange[] = array($dep['max'], '<=');
                            }
                            if (isset($dep['exclude'])) {
                                if (!is_array($dep['exclude']) ||
                                      !isset($dep['exclude'][0])) {
                                    $dep['exclude'] = array($dep['exclude']);
                                }
                                if (count($deprange)) {
                                    $excl = $dep['exclude'];
                                    // change >= to > if excluding the min version
                                    // change <= to < if excluding the max version
                                    for($i = 0; $i < count($excl); $i++) {
                                        if (isset($deprange[0]) &&
                                              $excl[$i] == $deprange[0][0]) {
                                            $deprange[0][1] = '<';
                                            unset($dep['exclude'][$i]);
                                        }
                                        if (isset($deprange[1]) &&
                                              $excl[$i] == $deprange[1][0]) {
                                            $deprange[1][1] = '>';
                                            unset($dep['exclude'][$i]);
                                        }
                                    }
                                }
                                if (count($dep['exclude'])) {
                                    $dep['exclude'] = array_values($dep['exclude']);
                                    $newdeprange = array();
                                    // remove excludes that are outside the existing range
                                    for ($i = 0; $i < count($dep['exclude']); $i++) {
                                        if ($dep['exclude'][$i] < $dep['min'] ||
                                              $dep['exclude'][$i] > $dep['max']) {
                                            unset($dep['exclude'][$i]);
                                        }
                                    }
                                    $dep['exclude'] = array_values($dep['exclude']);
                                    usort($dep['exclude'], 'version_compare');
                                    // take the remaining excludes and
                                    // split the dependency into sub-ranges
                                    $lastmin = $deprange[0];
                                    for ($i = 0; $i < count($dep['exclude']) - 1; $i++) {
                                        $newdeprange[] = '(' .
                                            $package . " {$lastmin[1]} {$lastmin[0]} and " .
                                            $package . ' < ' . $dep['exclude'][$i] . ')';
                                        $lastmin = array($dep['exclude'][$i], '>');
                                    }
                                    if (isset($dep['max'])) {
                                        $newdeprange[] = '(' . $package .
                                            " {$lastmin[1]} {$lastmin[0]} and " .
                                            $package . ' < ' . $dep['max'] . ')';
                                    }
                                    $conflicts[] = implode(' or ', $deprange);
                                } else {
                                    $conflicts[] = $package .
                                        " {$deprange[0][1]} {$deprange[0][0]}" .
                                        (isset($deprange[1]) ? 
                                        " and $package {$deprange[1][1]} {$deprange[1][0]}"
                                        : '');
                                }
                            }
                            continue;
                        }
                        if (!isset($dep['min']) && !isset($dep['max']) &&
                              !isset($dep['exclude'])) {
                            if (isset($dep['conflicts'])) {
                                $conflicts[] = $package;
                            } else {
                                $requires[$package] = $package;
                            }
                        } else {
                            if (isset($dep['min'])) {
                                $requires[$package] = $package . ' >= ' . $dep['min'];
                            }
                            if (isset($dep['max'])) {
                                $requires[$package] = $package . ' <= ' . $dep['max'];
                            }
                            if (isset($dep['exclude'])) {
                                $ex = $dep['exclude'];
                                if (!is_array($ex)) {
                                    $ex = array($ex);
                                }
                                foreach ($ex as $ver) {
                                    $conflicts[] = $package . ' = ' . $ver;
                                }
                            }
                        }
                    }
                    require_once 'Archive/Tar.php';
                    $tar = new Archive_Tar($pf->getArchiveFile());
                    $tar->pushErrorHandling(PEAR_ERROR_RETURN);
                    $a = $tar->extractInString('package2.xml');
                    $tar->popErrorHandling();
                    if ($a === null || PEAR::isError($a)) {
                        $info['package2xml'] = '';
                        // this doesn't have a package.xml version 1.0
                        $requires[$info['pear_rpm_name']] = $info['pear_rpm_name'] . ' >= ' .
                            $deps['required']['pearinstaller']['min'];
                    }
                    if (count($requires)) {
                        $info['extra_headers'] .= 'Requires: ' . implode(', ', $requires) . "\n";
                    }
                    if (count($conflicts)) {
                        $info['extra_headers'] .= 'Conflicts: ' . implode(', ', $conflicts) . "\n";
                    }
                }
            }
        }

        // remove the trailing newline
        $info['extra_headers'] = trim($info['extra_headers']);
        if (function_exists('file_get_contents')) {
            fclose($fp);
            $spec_contents = preg_replace('/@([a-z0-9_-]+)@/e', '$info["\1"]',
                file_get_contents($spec_template));
        } else {
            $spec_contents = preg_replace('/@([a-z0-9_-]+)@/e', '$info["\1"]',
                fread($fp, filesize($spec_template)));
            fclose($fp);
        }
        $spec_file = $this->_getRPMNameFromFormat($this->_rpm_specname_format, $pf->getPackage(), $alias, $info['version']);
        $wp = fopen($spec_file, "wb");
        if (!$wp) {
            return $this->raiseError("could not write RPM spec file $spec_file: $php_errormsg");
        }
        fwrite($wp, $spec_contents);
        fclose($wp);
        $this->ui->outputData("Wrote RPM spec file $spec_file", $command);

        return true;
    }
    

    // }}}
    // {{{ _initialiseNamingOptions()
    /*
     * Initialise the RPM naming options
     *
     * @param  array &$options    Standard options array
     * @return void
     */    
    function _initialiseNamingOptions(&$options)
    {
        if (isset($options['rpm-pkgname'])) {
            $this->_rpm_pkgname_format = $options['rpm-pkgname'];
        }
        
        if (isset($options['rpm-depname'])) {
            $this->_rpm_depname_format['pkg'] = $options['rpm-depname'];
        } else {
            $this->_rpm_depname_format['pkg'] = $this->_rpm_pkgname_format;
        }
    }
    
    
    // }}}
    // {{{ _getRPMName()
    /*
     * Return an RPM name
     *
     * @param  string $package_name Package name
     * @param  string $chan_name    Optional channel name
     * @param  string $type         Optional type (e.g. 'pkg', 'ext'). Defaults to 'pkg'.
     *                              'pkgdep' is a special case that means 'pkg', but it's a 
     *                              dependency rather than the package itself
     *
     * @return string RPM name. If empty, assume there is no equivalent in RPM namespace.
     */
    function _getRPMName($package_name, $chan_name=null, $type='pkg')
    {
        $chan_alias = $this->_getChannelAlias($chan_name, $package_name);
        switch ($type) {
            case 'pkg':
                return $this->_getRPMNameFromFormat($this->_rpm_pkgname_format, $package_name, $chan_alias);
            case 'pkgdep':
                $type = 'pkg';
                // let it drop through...
            default:
                if (isset($this->_rpm_depname_format[$type]) && !empty($this->_rpm_depname_format[$type])) {
                    return $this->_getRPMNameFromFormat($this->_rpm_depname_format[$type], $package_name, $chan_alias);
                }
                return '';
        }
    }
	
    // }}}
    // {{{ _getChannelAlias()
    /*
     * Return a channel alias from a channel name
     *
     * @param  string $chan_name    Channel name (e.g. 'pecl.php.net')
     * @param  string $package_name Optional name of the PEAR package to which $chan_name relates.
     *                              Assists when "guessing" channel aliases for PEAR/PECL
     * @return string Channel alias (e.g. 'PECL')
     */

    function _getChannelAlias($chan_name, $package_name = null)
    {
        switch($chan_name) {
            case null:
            case '':
                // If channel name not supplied, it is presumably
                // either PEAR or PECL. There's no sure-fire way of
                // telling between the two, but we try to make an
                // intelligent guess: if the package name is supplied
                // and starts with a lowercase letter, it's PECL.
                if (ereg('^[a-z]', $package_name)) {
                    $alias = 'PECL';
                } else {
                    $alias = 'PEAR';
                }
                break;
            case 'pear.php.net':
                $alias = 'PEAR';
                break;
            case 'pecl.php.net':
                $alias = 'PECL';
                break;
            default:
                $reg = &$this->config->getRegistry();
                $chan = &$reg->getChannel($pf->getChannel());
                $alias = $chan->getAlias();
                $alias = strtoupper($alias);
                break;
         }
         return $alias;
    }

	
    // }}}
    // {{{ _getRPMNameFromFormat()
    /*
     * Get an RPM package or dependency name from a format string
     *
     * This method generates an RPM package or dependency name based on
     * a format string containing substitution variables, rather like
     * sprintf(). It supports the following substitution variables:
     * %s = package name
     * %l = package name, lowercased
     * %S = package name, with underscores replaced with hyphens
     * %v = package version
     * %C = channel alias
     * %c = channel alias, lowercased
     * %P = whatever the rpm pkgname format is set to be
     *
     * @param  string $format          Format string
     * @param  string $package_name    Package name (e.g. 'Example_Package')
     * @param  string $channel_alias   Channel alias (e.g. 'PEAR', 'PECL')
     * @param  string $version         Package version (e.g. '1.2.3')
     * @return string RPM package/dependency name
     */

    function _getRPMNameFromFormat($format, $package_name, $channel_alias, $version=null)
    {
        $name = $format;
    
        // pkgname_format
        $name = str_replace('%P', $this->_rpm_pkgname_format, $name);
    
        // Package name
        $name = str_replace('%s', $package_name, $name);
        
        // Package name, lowercased
        $name = str_replace('%l', strtolower($package_name), $name);
        
        // Package name, with underscores replaced with hyphens
        $name = str_replace('%S', str_replace('_', '-', $package_name), $name);

        // Channel alias
        $name = str_replace('%C', $channel_alias, $name);
        
        // Channel alias, lowercased
        $name = str_replace('%c', strtolower($channel_alias), $name);

        // Version
        $name = str_replace('%v', $version, $name);
        
        return $name;
    }

}

?>
