%define peardir %(pear config-get php_dir 2> /dev/null || echo %{_datadir}/pear)

Summary: PEAR: @summary@
Name: @rpm_package@
Version: @version@
Release: @release@
License: @release_license@
Group: Development/Libraries
Source0: http://@master_server@/get/@package@-%{version}.tgz
BuildRoot: %{_tmppath}/%{name}-%{version}-root-%(%{__id_u} -n)
URL: http://@master_server@/package/@package@
BuildArchitectures: @arch@
BuildRequires: @pear_rpm_name@
@extra_headers@

%description
@description@

%prep
%setup -c -T
# XXX Source files location is missing here in pear cmd
pear -v -c pearrc \
        -d php_dir=%{peardir} \
        -d doc_dir=/docs \
        -d bin_dir=%{_bindir} \
        -d data_dir=%{peardir}/data \
        -d test_dir=%{peardir}/tests \
        -d ext_dir=%{_libdir} \@extra_config@
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

# Sort out documentation
if [ "@doc_files@" != "" ]; then
     mv %{buildroot}/docs/@package@/* .
     rm -rf %{buildroot}/docs
fi

# Install XML package description
mkdir -p %{buildroot}@rpm_xml_dir@
tar -xzf %{SOURCE0} package@package2xml@.xml
cp -p package@package2xml@.xml %{buildroot}@rpm_xml_dir@/@package@.xml

%clean
rm -rf %{buildroot}

%post
pear install --nodeps --soft --force --register-only @rpm_xml_dir@/@package@.xml

%postun
if [ "$1" -eq "0" ]; then
    pear uninstall --nodeps --ignore-errors --register-only @possible_channel@@package@
fi

%files
%defattr(-,root,root)
%doc @doc_files@
/
