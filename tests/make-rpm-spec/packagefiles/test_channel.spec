%define peardir %(pear config-get php_dir 2> /dev/null || echo %{_datadir}/pear)
%define pear_xmldir  /var/lib/pear

Name:           php-channel-example
Version:        1.0
Release:        1
Summary:        Adds pear.example.com channel to PEAR

Group:          Development/Languages
License:        N/A
URL:            http://pear.example.com/
Source0:        http://pear.example.com/channel.xml
BuildRoot:      %{_tmppath}/%{name}-%{version}-%{release}-root-%(%{__id_u} -n)

BuildArch:      noarch
BuildRequires:  PEAR::PEAR >= 1.4.7
Requires:       PEAR::PEAR
Provides:       php-channel(pear.example.com)

%description
This package adds the pear.example.com channel which allows PEAR packages
from this channel to be installed.


%prep
%setup -q -c -T


%build
# Empty build section, nothing to build


%install
rm -rf $RPM_BUILD_ROOT
mkdir -p $RPM_BUILD_ROOT%{pear_xmldir}
install -pm 644 %{SOURCE0} $RPM_BUILD_ROOT%{pear_xmldir}/pear.example.com.xml


%clean
rm -rf $RPM_BUILD_ROOT


%post
if [ $1 -eq  1 ] ; then
   %{__pear} channel-add %{pear_xmldir}/pear.example.com.xml > /dev/null || :
else
   %{__pear} channel-update %{pear_xmldir}/pear.example.com.xml > /dev/null ||:
fi


%postun
if [ $1 -eq 0 ] ; then
   %{__pear} channel-delete pear.example.com > /dev/null || :
fi


%files
%defattr(-,root,root,-)
%{pear_xmldir}/*

