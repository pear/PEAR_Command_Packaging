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
 * @author     Tim Jackson <tim@timj.co.uk>
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
 * @author     Tim Jackson <tim@timj.co.uk>
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
            'summary' => 'Builds an RPM spec file from a PEAR package or channel XML file',
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
%n = Channel name (full) e.g. pear.example.com

Defaults to "%C::%s" for library/application packages and "php-channel-%c" for 
channel packages.',
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

Creates an RPM .spec file for wrapping a PEAR package or channel definition 
inside an RPM package.  Intended to be used from the SPECS directory, with the 
PEAR package tarball in the SOURCES directory:

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
    
    // ------------------------------------------------------------------------
    // BEGIN DISTRIBUTION CONFIG
    // This is the start of configuration options that might need to be patched
    // by downstream distributors
    // TODO: all this stuff should be settable via $options really
    // ------------------------------------------------------------------------
    
    /**
     * The default format of the RPM package name. See above for possible
     * substitution variables.
     *
     * There are two elements in the array:
     *
     * pkg - used when generating a spec file for an actual library/application
     * chan - used when generating a spec file for a channel
     *
     * If you change these, you will want to patch the documentation in the
     * $commands array above and in Packaging.xml so that it is consistent.
     */
    var $_rpm_pkgname_format = array(
        'pkg'  => '%C::%s',
        'chan' => 'php-channel-%c',
    );
    
    /**
     * The default format of various dependencies that might be generated in the
     * spec file. The currently-handled dependency types are:
     *
     * pkg  = another PEAR package
     * ext  = a PHP extension
     * php  = PHP itself
     * chan = a PEAR installer-based channel
     *
     * In each one:
     *
     * NULL = don't generate a dependency
     * %P   = use the same as whatever rpm_pkgname_format is set to be
     */
    var $_rpm_depname_format = array(
        'pkg'  => '%P',
        'ext'  => 'php-%l',
        'php'  => 'php',
        'chan' => 'php-channel(%n)',
    );
    
    /**
     * Format of the filename for the output spec file. Substitutions are as per 
     * the rpm-pkgname format string, with the addition of:
     *
     * %v = package version
     * %P = use the same as whatever rpm_pkgname_format is set to be
     *
     * There are two elements in the array:
     *
     * pkg  - used when generating a spec file for an actual library/application
     * chan - used when generating a spec file for a channel
     */
    var $_rpm_specname_format = array(
        'pkg'  => '%P-%v.spec',
        'chan' => 'php-channel-%c.spec'
    );
    
    /**
     * File prefixes to use for the standard file roles. Used when generating
     * specs for packages only. It's OK to use RPM macros here; these file
     * paths are not used internally, only for putting in the output spec file.
     *
     * Don't include trailing slashes.
     *
     * The %s (PEAR package name) substitution is understood, and if this is
     * used *and* it is at the end of the string, then make-rpm-spec will be
     * intelligent and include just the top level directory in the corresponding 
     * "[role]_files_statement" macro instead of listing *all* the files in that
     * subdirectory. That makes for cleaner specs and means that the output
     * package will own that directory.
     *
     * NB that 'doc' is handled specially and should normally be empty
     * Files with role='src' should not appear in final package so do not
     * need to be listed here
     */
    var $_file_prefixes = array(
        'php' => '%{_libdir}/php/pear',
        'doc' => '',
        'ext' => '%{_libdir}/php',
        'test' => '%{_libdir}/php/tests/%s',
        'data' => '%{_libdir}/php/data/%s',
        'script' => '%{_bindir}'
    );
    
    /**
     * The format to use when adding new RPM header lines to the spec file, in
     * printf format. The first '%s' is the RPM header name, the second is the
     * value.
     */
    var $_spec_line_format = '%s: %s';
    
    // ------------------------------------------------------------------------
    // --- END DISTRIBUTION CONFIG
    // ------------------------------------------------------------------------
    
    /**
     * The final output substitutions which will be put into the RPM spec 
     * template. Common to the generation of specs for both channels and packages 
     */
    var $_output = array(
        'channel_alias' => '',   // channel alias
        'master_server' => '',   // download server for package/channel
        'pear_rpm_name' => '',   // RPM name of the core PEAR package
        'possible_channel' => '',// channel name e.g. pear.example.com
        'release' => 1,          // RPM release number
        'release_license' => '', // license
        'rpm_package' => '',     // the output RPM package name (RPMified)
        'version' => '',         // the (source) package version
    );
    
    /**
     * Final output substitutions that are only used when generating specs for
     * packages (not channels)
     */
    var $_output_package = array(
        'arch_statement' => '',  // empty string, or "BuildArchitecture: noarch" if definitely a noarch package
        'bin_dir' => '',
        'customrole_files_statement' => '',// empty string, or list of files with custom roles
        'data_dir' => '',
        'data_files_statement' => '',// empty string, or list of data files
        'doc_dir' => '',
        'doc_files' => '',
        'doc_files_relocation_script' => '', // doc files relocation script, if needed
        'doc_files_statement' => '', // empty string, or list of doc files preceded with %doc
        'ext_dir' => '',
        'extra_config' => '',
        'extra_headers' => '',
        'files' => '',           // list of files in the package, newline-separated
        'package' => '',         // the (source) package name
        'package2xml' => '',     // either empty string, or number "2" if using package.xml v2
        'php_dir' => '',
        'php_files_statement' => '', // empty string, or list of php files
        'release_state' => '',   // stable, unstable etc
        'script_files_statement' => '',
        'summary' => '',
        'test_dir' => '',
        'test_files_statement' => '',// empty string, or list of test files
    );
    
    // The name of the template spec file to use
    var $_template_spec_name = '';
    
    /**
     * PEAR_Command_Packaging constructor.
     *
     * @access public
     */
    function PEAR_Command_Packaging(&$ui, &$config)
    {
        parent::PEAR_Command_Common($ui, $config);
    }

    /**
     * Get a PEAR_PackageFile object based on the provided config options
     */
    function &getPackageFile($config, $debug = false, $tmpdir = null)
    {
        if (!class_exists('PEAR_Common')) {
            require_once 'PEAR/Common.php';
        }
        if (!class_exists('PEAR_PackageFile')) {
            require_once 'PEAR/PackageFile.php';
        }
        $a = &new PEAR_PackageFile($config, $debug, $tmpdir);
        $common = new PEAR_Common;
        $common->ui = $this->ui;
        $a->setLogger($common);
        return $a;
    }

    /**
     * Abstraction for unit testing purposes
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
     * Abstraction for unit testing purposes
     */
    function makeTempDir()
    {
        return System::mktemp(array('-d', 'pear2rpm'));
    }

    /**
     * The make-rpm-spec command - create an RPM spec file from package source
     * @uses _doMakeRPMFromChannel() for channel sources
     * @uses _doMakeRPMFromPackage() for package sources
     */
    function doMakeRPM($command, $options, $params)
    {
        require_once 'System.php';
        require_once 'Archive/Tar.php';
        
        if (sizeof($params) != 1) {
            return $this->raiseError("Bad parameter(s); please try \"help $command\"");
        }
        
        $source_file = $params[0];
        
        // Check source file exists
        if (!file_exists($source_file)) {
            return $this->raiseError("Source file '$source_file' does not exist");
        }
        
        // Initialise the RPM package/dep naming format options
        $this->_initialiseNamingOptions($options);        
        
        // Set the RPM release version
        if (isset($options['rpm-release'])) {
            $this->_output['release'] = $options['rpm-release'];
        }
        
        // Set the PEAR RPM name for the PEAR core package
        $this->_output['pear_rpm_name'] = $this->_getRPMName('PEAR', 'pear.php.net', null, 'pkgdep');
        
        // If source file ends in ".xml" we assume we are creating an RPM spec
        // for a channel rather than an actual package
        if (substr(strtolower($source_file), -4) == '.xml') {
            $this->_doMakeRPMFromChannel($source_file, $options, $params);
            $type = 'chan';
        } else {
            $this->_doMakeRPMFromPackage($source_file, $command, $options, $params);
            $type = 'pkg';
        }
        
        // Work out where we are loading the specfile template from
        if (isset($options['spec-template'])) {
            $spec_template = $options['spec-template'];
        } else {
            $spec_template = '@DATA-DIR@/PEAR_Command_Packaging/' . $this->_template_spec_name;
        }
        
        // Open the template spec file
        $spec_contents = @file_get_contents($spec_template);
        if ($spec_contents == false) {
            return $this->raiseError("Could not open RPM spec file template '$spec_template': $php_errormsg");
        }
        
        // Do the actual macro substitutions
        $spec_contents = preg_replace_callback(
            '/@([a-z0-9_-]+)@/', 
            array($this, '_replaceOutputMacro'),
            $spec_contents
        );
        
        // Write the output spec file
        $this->_writeSpecFile($spec_contents, $type, $command);
        
        return true;
    }
    
    /**
     * Format an RPM header line to be added to the spec file
     * @param  string $header The name of the RPM header to be added 
     * @param  string $value  The contents of the RPM header
     * @return string
     */
    function _formatRpmHeader($header, $value)
    {
        return sprintf($this->_spec_line_format, $header, $value);
    }
    
    /**
     * Replace a macro in the output spec file
     * @return string
     */
    function _replaceOutputMacro($matches)
    {
        if (!isset($this->_output[$matches[1]])) {
            $this->raiseError("Replacement macro '$matches[1]' does not exist");
            return '@' . $matches[1] . '@'; // return original string
        }
        return $this->_output[$matches[1]];
    }
    
    /**
     * Write the output spec file
     * @return string
     */
    function _writeSpecFile($spec_contents, $type, $command)
    {
        if (isset($this->_output['package'])) {
            $package_name = $this->_output['package'];
        } else {
            $package_name = null;
        }
        
        // Work out the name of the output spec file
        $spec_file = $this->_getRPMNameFromFormat(
            $this->_rpm_specname_format[$type],
            $package_name,
            $this->_output['possible_channel'],
            $this->_output['channel_alias'],
            $this->_output['version']
        );
        
        // Write the actual file
        $wp = fopen($spec_file, 'wb');
        if (!$wp) {
            return $this->raiseError("Could not write RPM spec file '$spec_file': $php_errormsg");
        }
        fwrite($wp, $spec_contents);
        fclose($wp);
        
        // Notify user
        $this->ui->outputData("Wrote RPM spec file $spec_file", $command);
    }

    /**
     * Set the output macros based on a channel source
     */    
    function _doMakeRPMFromChannel($source_file, $options, $params)
    {
        // Set the name of the template spec file to use by default
        $this->_template_spec_name = 'template-channel.spec';
        
        // Create a PEAR_ChannelFile object
        if (!class_exists('PEAR_ChannelFile')) {
            require_once 'PEAR/ChannelFile.php';
        }
        $cf = new PEAR_ChannelFile();
        
        // Load in the channel.xml file from the source XML
        $cf->fromXmlFile($source_file);
        
        // Set the output macros
        $this->_output['channel_alias'] = $cf->getAlias();
        $this->_output['master_server'] = $cf->getName();
        $this->_output['possible_channel'] = $cf->getName();
        $this->_output['rpm_package'] = $this->_getRPMName(null, $cf->getName(), $cf->getAlias(), 'chan');
        
        // Channels don't really have version numbers; this will need to be
        // hand-maintained in the spec
        $this->_output['version'] = '1.0';
    }
    
    /**
     * Set the output macros based on a package source
     */ 
    function _doMakeRPMFromPackage($source_file, $command, $options, $params) {
        // Merge the "core" output macros with those for packages only
        $this->_output += $this->_output_package;
        
        // Set the name of the template spec file to use by default
        $this->_template_spec_name = 'template.spec';
    
        // Create a PEAR_PackageFile object and fill it with info from the
        // source package
        $reg = &$this->config->getRegistry();
        $pkg = &$this->getPackageFile($this->config, $this->_debug);
        $pf = &$pkg->fromAnyFile($source_file, PEAR_VALIDATE_NORMAL);
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
            return $this->raiseError("$source_file is not a valid package");
        }
        
        // Install the package into a temporary directory
        $tmpdir = $this->makeTempDir();
        $instroot = $this->makeTempDir();
        $tmp = $this->config->get('verbose');
        $this->config->set('verbose', 0);
        $installer = $this->getInstaller($this->ui);
        require_once 'PEAR/Downloader/Package.php';
        $pack = new PEAR_Downloader_Package($installer);
        $pack->setPackageFile($pf);
        $params[0] = &$pack;
        $installer->setOptions(array('packagingroot' => $instroot,
                                            'nodeps' => true, 'soft' => true));
        $installer->setDownloadedPackages($params);
        $package_info = $installer->install($source_file,
                                    array('packagingroot' => $instroot,
                                            'nodeps' => true, 'soft' => true));
        
        if (PEAR::isError($package_info)) {
            $this->ui->outputData($package_info->getMessage());
            return $this->raiseError('Failed to do a temporary installation of the package');
        }
        
        $pkgdir = $pf->getPackage() . '-' . $pf->getVersion();
        $this->config->set('verbose', $tmp);
        
        // Set up some of the basic macros
        $this->_output['rpm_package'] = $this->_getRPMName($pf->getPackage(), $pf->getChannel(), null, 'pkg');
        $this->_output['description'] = wordwrap($package_info['description']);
        $this->_output['summary'] = $package_info['summary'];
        $this->_output['possible_channel'] = $pf->getChannel();
        $this->_output['channel_alias'] = $this->_getChannelAlias($pf->getPackage(), $pf->getChannel());
        $this->_output['package'] = $pf->getPackage();
        $this->_output['version'] = $pf->getVersion();
        $this->_output['release_license'] = $pf->getLicense();
        $this->_output['release_state'] = $pf->getState();

        // Figure out the master server for the package's channel
        $chan = $reg->getChannel($pf->getChannel());
        $this->_output['master_server'] = $chan->getServer();

        // Put some standard PEAR config options into the output macros. These
        // will probably be deprecated in v0.2.x
        $cfg = array('php_dir', 'ext_dir', 'doc_dir', 'bin_dir', 'data_dir', 'test_dir');
        foreach ($cfg as $k) {
            $this->_output[$k] = $this->config->get($k);
        }

        // Generate the Requires and Conflicts for the RPM
        if ($pf->getDeps()) {
            $this->_generatePackageDeps($pf);
        }
    
        // Hook to support virtual Provides, where the dependency name differs
        // from the package name
        $rpmdep = $this->_getRPMName($pf->getPackage(), $pf->getChannel(), null, 'pkgdep');
        if (!empty($rpmdep) && $rpmdep != $this->_output['rpm_package']) {
            $this->_output['extra_headers'] .= $this->_formatRpmHeader('Provides', "$rpmdep = %{version}") . "\n";
        }
        
        // Create the list of files in the package
        foreach ($package_info['filelist'] as $filename => $attr) {
            // Ignore files with no role set
            if (!isset($attr['role'])) {
                continue;
            }        
            $role = $attr['role'];
            
            // Handle custom roles; set prefix
            if (!isset($this->_file_prefixes[$role])) {
                $this->_file_prefixes[$role] = $this->_file_prefixes['php'] . "/$role/%s";
                $this->_output['extra_config'] .=
                    "\n        -d ${role}_dir=" . $this->_file_prefixes[$role] . "\\";
                $this->ui->outputData("WARNING: role '$role' used, " .
                    'and will be installed in "' . $this->_file_prefixes[$role] .
                    ' - hand-edit the final .spec if this is wrong', $command);
            }
            
            // Some kind of cleanup? What's this for?    
            $filename = preg_replace('![/:\\\\]!', '/', $filename);
            $filename = str_replace('\\', '/', $filename);
            
            // Add to master file list
            $file_list[$role][] = $filename;
        }
        
        // Build the master file lists
        foreach ($file_list as $role => $files) {
            // Docs are handled separately below; 'src' shouldn't be in RPM
            if ($role == 'doc' || $role == 'src') continue; 
            
            // Get the prefix for the file
            $prefix = str_replace('%s', $pf->getPackage(), $this->_file_prefixes[$role]);
            
            // Master file list @files@ - recommended not to use
            $this->_output['files'] .= "$prefix/" . implode("\n$prefix/", $files) . "\n";
            
            // Handle other roles specially: if the role puts files in a subdir
            // dedicated to the package in question (i.e. the prefix ends with 
            // %s) we don't need to specify all the individual files
            if (in_array($role, array('php','test','data','script'))) {
                $macro_name = "${role}_files_statement";
            } else {
                $macro_name = 'customrole_files_statement';
            }
            if (substr($this->_file_prefixes[$role], -2) == '%s') {
                $this->_output[$macro_name] = $prefix;
            } else {
                $this->_output[$macro_name] = "$prefix/" . implode("\n$prefix/", $files);
            }
        }
        $this->_output['files'] = trim($this->_output['files']);
        
        // Handle doc files
        if (isset($file_list['doc'])) {
            $this->_output['doc_files'] = 'docs/' . $pf->getPackage() . '/*';
            $this->_output['doc_files_statement'] = '%doc ' . $this->_output['doc_files'];
            $this->_output['doc_files_relocation_script'] = "mv %{buildroot}/docs .\n";
        }
        
        // Work out architecture
        // If there are 1 or more files with role="src", something needs compiling
        // and this is not a noarch package
        if (!isset($file_list['src'])) {
            $this->_output['arch_statement'] = $this->_formatRpmHeader('BuildArch', 'noarch') . "\n";
        }
        
        
        // If package is not from pear.php.net or pecl.php.net, we will need
        // to BuildRequire/Require a channel RPM
        if (!empty($this->_output['possible_channel']) && !in_array($this->_output['possible_channel'], array('pear.php.net','pecl.php.net'))) {
            $channel_dep = $this->_getRPMName($this->_output['package'], $this->_output['possible_channel'], null, 'chandep');
            $this->_output['extra_headers'] .= $this->_formatRpmHeader('BuildRequires', $channel_dep) . "\n";
            $this->_output['extra_headers'] .= $this->_formatRpmHeader('Requires', $channel_dep) . "\n";
        }
        
        // Remove any trailing newline from extra_headers
        $this->_output['extra_headers'] = trim($this->_output['extra_headers']);
    }
    

    function _generatePackageDeps($pf)
    {
        $requires = $conflicts = array();
        if ($pf->getPackagexmlVersion() == '1.0') {
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
                if (!isset($dep['name'])) $dep['name'] = ''; //e.g. "php" dep
                
                // $package contains the *dependency name* here, which may or may
                // not be the same as the package name
                $package = $this->_getRPMName($dep['name'], $dep['channel'], null, $type);

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
                $this->_output['extra_headers'] .= $this->_formatRpmHeader('Requires', implode(', ', $requires)) . "\n";
            }
            if (count($conflicts)) {
                $this->_output['extra_headers'] .= $this->_formatRpmHeader('Conflicts', implode(', ', $conflicts)) . "\n";
            }
        } else {
            $this->_output['package2xml'] = '2'; // tell the spec to use package2.xml
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
                    $package = $this->_getRPMName($dep['name'], $dep['channel'], null, $type);
                    
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
                    $this->_output['package2xml'] = '';
                    // this doesn't have a package.xml version 1.0
                    $requires[$this->_output['pear_rpm_name']] = $this->_output['pear_rpm_name'] . ' >= ' .
                        $deps['required']['pearinstaller']['min'];
                }
                if (count($requires)) {
                    $this->_output['extra_headers'] .= $this->_formatRpmHeader('Requires', implode(', ', $requires)) . "\n";
                }
                if (count($conflicts)) {
                    $this->_output['extra_headers'] .= $this->_formatRpmHeader('Conflicts', implode(', ', $conflicts)) . "\n";
                }
            }
        }
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
            $this->_rpm_pkgname_format['pkg'] = $options['rpm-pkgname'];
        }
        
        if (isset($options['rpm-depname'])) {
            $this->_rpm_depname_format['pkg'] = $options['rpm-depname'];
        }
    }
    
    
    // }}}
    // {{{ _getRPMName()
    /*
     * Return an RPM name
     *
     * @param  string $package_name Package name
     * @param  string $chan_name    Optional channel name
     * @param  string $chang_alias  Optional channel alias
     * @param  string $type         Optional type (e.g. 'pkg', 'ext'). Defaults to 'pkg'.
     *                              'pkgdep' is a special case that means 'pkg', but it's a 
     *                              dependency rather than the package itself
     *
     * @return string RPM name. If empty, assume there is no equivalent in RPM namespace.
     */
    function _getRPMName($package_name, $chan_name=null, $chan_alias=null, $type='pkg')
    {
        if ($chan_alias === null) {
            $chan_alias = $this->_getChannelAlias($package_name, $chan_name);
        }
        
        switch ($type) {
            case 'chan':
                return $this->_getRPMNameFromFormat($this->_rpm_pkgname_format['chan'], null, $chan_name, $chan_alias);
            case 'chandep':
                return $this->_getRPMNameFromFormat($this->_rpm_depname_format['chan'], null, $chan_name, $chan_alias);
            case 'pkg':
                return $this->_getRPMNameFromFormat($this->_rpm_pkgname_format['pkg'], $package_name, $chan_name, $chan_alias);
            case 'pkgdep':
                $type = 'pkg';
                // let it drop through...
            default:
                if (isset($this->_rpm_depname_format[$type]) && !empty($this->_rpm_depname_format[$type])) {
                    return $this->_getRPMNameFromFormat($this->_rpm_depname_format[$type], $package_name, $chan_name, $chan_alias);
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

    function _getChannelAlias($package_name, $chan_name = null)
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
                $chan = &$reg->getChannel($chan_name);
                $alias = $chan->getAlias();
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
     * %n = channel name
     * %P = whatever the rpm pkgname format is set to be
     *
     * @param  string $format          Format string
     * @param  string $package_name    Package name (e.g. 'Example_Package')
     * @param  string $channel_alias   Channel alias (e.g. 'PEAR', 'PECL')
     * @param  string $version         Package version (e.g. '1.2.3')
     * @return string RPM package/dependency name
     */

    function _getRPMNameFromFormat($format, $package_name, $channel, $channel_alias, $version=null)
    {
        $name = $format;
        if (empty($channel_alias)) $channel_alias = $channel;
    
        // pkgname_format
        $name = str_replace('%P', $this->_rpm_pkgname_format['pkg'], $name);
    
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

        // Channel name, full
        $name = str_replace('%n', $channel, $name);

        // Version
        $name = str_replace('%v', $version, $name);
        
        return $name;
    }

}

?>
