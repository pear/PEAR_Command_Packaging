%define peardir %(pear config-get php_dir 2> /dev/null || echo %{_datadir}/pear)

Summary: PEAR: make-rpm-spec command for managing RPM .spec files for PEAR packages
Name: PEAR::PEAR_Command_Packaging
Version: 0.1.0
Release: 1
License: PHP License
Group: Development/Libraries
Source0: http://pear.php.net/get/PEAR_Command_Packaging-%{version}.tgz
BuildRoot: %{_tmppath}/%{name}-%{version}-root-%(%{__id_u} -n)
URL: http://pear.php.net/package/PEAR_Command_Packaging
BuildArchitectures: noarch
BuildRequires: PEAR::PEAR >= 1.4.7
Requires: PEAR::PEAR >= 1.4.7

%description
This command is an improved implementation of the standard makerpm command,
  and contains several enhancements that make it far more flexible. Similar 
  functions for other external packaging mechanisms may be added at a later date.

  Enhanced features over the original PEAR "makerpm" command include:
  
  - Ability to define a release on the command line
  - Allows more advanced customisation of the generated package name
  - Allows virtual Provides/Requires that differ in format from the package name
    format 
  - tries to intelligently distinguish between PEAR and PECL when generating 
    packages

%prep
%setup -c -T
# XXX Source files location is missing here in pear cmd
pear -v -c pearrc \
        -d php_dir=%{peardir} \
        -d doc_dir=/docs \
        -d bin_dir=%{_bindir} \
        -d data_dir=%{peardir}/data \
        -d test_dir=%{peardir}/tests \
        -d ext_dir=%{_libdir} \
        -s

%build

%install
rm -rf %{buildroot}
pear -c pearrc install --nodeps --packagingroot %{buildroot} %{SOURCE0}
        
# Clean up unnecessary files
rm -f pearrc
rm -f %{buildroot}/%{peardir}/.filemap
rm -f %{buildroot}/%{peardir}/.lock
rm -rf %{buildroot}/%{peardir}/.registry
rm -rf %{buildroot}%{peardir}/.channels
rm -rf %{buildroot}%{peardir}/.depdb*



# Install XML package description
mkdir -p %{buildroot}/var/lib/pear
tar -xzf %{SOURCE0} package.xml
cp -p package.xml %{buildroot}/var/lib/pear/PEAR_Command_Packaging.xml

%clean
rm -rf %{buildroot}

%post
pear install --nodeps --soft --force --register-only /var/lib/pear/PEAR_Command_Packaging.xml

%postun
if [ "$1" -eq "0" ]; then
    pear uninstall --nodeps --ignore-errors --register-only PEAR_Command_Packaging
fi

%files
%defattr(-,root,root)

%{peardir}/*
/var/lib/pear/PEAR_Command_Packaging.xml
