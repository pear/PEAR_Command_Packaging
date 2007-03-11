%define peardir %(pear config-get php_dir 2> /dev/null || echo %{_datadir}/pear)
%define pear_xmldir  /var/lib/pear

Name:           @rpm_package@
Version:        @version@
Release:        @release@
Summary:        Adds @possible_channel@ channel to PEAR

Group:          Development/Languages
License:        N/A
URL:            http://@master_server@/
Source0:        http://@master_server@/channel.xml
BuildRoot:      %{_tmppath}/%{name}-%{version}-%{release}-root-%(%{__id_u} -n)

BuildArch:      noarch
BuildRequires:  @pear_rpm_name@ >= 1.4.7
Requires:       @pear_rpm_name@
Provides:       php-channel(@possible_channel@)

%description
This package adds the @possible_channel@ channel which allows PEAR packages
from this channel to be installed.


%prep
%setup -q -c -T


%build
# Empty build section, nothing to build


%install
rm -rf $RPM_BUILD_ROOT
mkdir -p $RPM_BUILD_ROOT%{pear_xmldir}
install -pm 644 %{SOURCE0} $RPM_BUILD_ROOT%{pear_xmldir}/@possible_channel@.xml


%clean
rm -rf $RPM_BUILD_ROOT


%post
if [ $1 -eq  1 ] ; then
   %{__pear} channel-add %{pear_xmldir}/@possible_channel@.xml > /dev/null || :
else
   %{__pear} channel-update %{pear_xmldir}/@possible_channel@.xml > /dev/null ||:
fi


%postun
if [ $1 -eq 0 ] ; then
   %{__pear} channel-delete @possible_channel@ > /dev/null || :
fi


%files
%defattr(-,root,root,-)
%{pear_xmldir}/*

