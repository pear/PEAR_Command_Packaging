%define peardir %(pear config-get php_dir 2> /dev/null || echo %{_datadir}/pear)
%define xmldir  /var/lib/pear

Summary: PEAR: Generating CHAP packets.
Name: PEAR::Crypt_CHAP
Version: 1.0.0
Release: 1
License: BSD
Group: Development/Libraries
Source0: http://pear.php.net/get/Crypt_CHAP-%{version}.tgz
BuildRoot: %{_tmppath}/%{name}-%{version}-root-%(%{__id_u} -n)
URL: http://pear.php.net/package/Crypt_CHAP
BuildArchitectures: noarch
BuildRequires: PEAR::PEAR >= 1.4.7
Requires: php-mhash, php-mcrypt

%description
This package provides Classes for generating CHAP packets.
Currently these types of CHAP are supported:
* CHAP-MD5
* MS-CHAPv1
* MS-CHAPv2
For MS-CHAP the mhash and mcrypt extensions must be loaded.


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



# Install XML package description
mkdir -p %{buildroot}%{xmldir}
tar -xzf %{SOURCE0} package.xml
cp -p package.xml %{buildroot}%{xmldir}/Crypt_CHAP.xml

%clean
rm -rf %{buildroot}

%post
pear install --nodeps --soft --force --register-only %{xmldir}/Crypt_CHAP.xml

%postun
if [ "$1" -eq "0" ]; then
    pear uninstall --nodeps --ignore-errors --register-only Crypt_CHAP
fi

%files
%defattr(-,root,root)

%{peardir}/*
%{xmldir}/Crypt_CHAP.xml
