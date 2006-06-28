%define peardir %(pear config-get php_dir 2> /dev/null || echo %{_datadir}/pear)
%define xmldir  /var/lib/pear

Summary: PEAR: An implementation of the SMTP protocol
Name: PEAR::Net_SMTP
Version: 1.2.8
Release: 2
License: PHP License
Group: Development/Libraries
Source0: http://pear.php.net/get/Net_SMTP-%{version}.tgz
BuildRoot: %{_tmppath}/%{name}-%{version}-root-%(%{__id_u} -n)
URL: http://pear.php.net/package/Net_SMTP
BuildArchitectures: noarch
BuildRequires: PEAR::PEAR >= 1.4.7
Requires: PEAR::Net_Socket

%description
Provides an implementation of the SMTP protocol using PEAR's Net_Socket
class.

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
rm pearrc
rm %{buildroot}/%{peardir}/.filemap
rm %{buildroot}/%{peardir}/.lock
rm -rf %{buildroot}/%{peardir}/.registry
rm -rf %{buildroot}%{peardir}/.channels
rm %{buildroot}%{peardir}/.depdb
rm %{buildroot}%{peardir}/.depdblock

mv %{buildroot}/docs .


# Install XML package description
mkdir -p %{buildroot}%{xmldir}
tar -xzf %{SOURCE0} package2.xml
cp -p package2.xml %{buildroot}%{xmldir}/Net_SMTP.xml

%clean
rm -rf %{buildroot}

%post
pear install --nodeps --soft --force --register-only %{xmldir}/Net_SMTP.xml

%postun
if [ "$1" -eq "0" ]; then
    pear uninstall --nodeps --ignore-errors --register-only Net_SMTP
fi

%files
%defattr(-,root,root)
%doc docs/Net_SMTP/{docs/examples/basic.php,docs/guide.txt}
%{peardir}/*
%{xmldir}/Net_SMTP.xml
