rem set your computer name
if %COMPUTERNAME%==NAY2 goto NAY
goto END
:NAY
set EXT_ROOT=..\ext\
set HTML_ROOT=%~dp0html
set NGINX_CONF=-c %~dp0conf\nginx\nay\nginx.conf
:END
rem defaults
if -%EXT_ROOT%==- set EXT_ROOT=..\ext\
if -%HTML_ROOT%==- set HTML_ROOT=%~dp0html

cd %EXT_ROOT%nginx
rem nginx -s stop
taskkill /f /IM nginx.exe
start /B nginx %NGINX_CONF%

cd ..\php
set PHP_FCGI_MAX_REQUESTS=0
taskkill /f /IM php-cgi.exe
rem set DOCROOT=%~dp0
rem doc_root = D:\ias-portal\html
start /B php-cgi -b 127.0.0.1:9000 -c php.ini -d doc_root=%HTML_ROOT%

cd ..
