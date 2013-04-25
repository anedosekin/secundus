REM Usage:
REM set PHP_FROM_PATH to 1 for loading php from path environment variable
REM set NGINX_FROM_PATH to 1 for loading nginx from path environment variable

rem COPY --------->
if %COMPUTERNAME%==NAY2 goto NAY
rem <--------------
goto END
rem COPY --------->
:NAY
set DIST_ROOT=%~dp0
set EXT_ROOT=%DIST_ROOT%..\ext\
set WWW_ROOT=%DIST_ROOT%www
set NGINX_CONF=%DIST_ROOT%cfg\nginx\nay\nginx.conf
set PHP_CONF=%DIST_ROOT%cfg\php\nay\php.ini
goto END
rem <--------------
:END
rem defaults
if -%DIST_ROOT%==- set DIST_ROOT=%~dp0
if -%EXT_ROOT%==- set EXT_ROOT=%DIST_ROOT%..\ext\
if -%WWW_ROOT%==- set WWW_ROOT=%DIST_ROOT%www\
if -%NGINX_CONF%==- set NGINX_CONF=%DIST_ROOT%cfg\nginx\nginx.conf
if -%PHP_CONF%==- set PHP_CONF=%DIST_ROOT%cfg\php\php.ini
rem read from path
if -%PHP_FROM_PATH%==- set PHP_PATH=%EXT_ROOT%php\
if -%NGINX_FROM_PATH%==- set NGINX_PATH=%EXT_ROOT%nginx\

rem cd %EXT_ROOT%nginx
rem nginx -s stop
taskkill /f /IM nginx.exe
start /B %NGINX_PATH%nginx -c %NGINX_CONF%

rem cd ..\php
set PHP_FCGI_MAX_REQUESTS=0
taskkill /f /IM php-cgi.exe

start /B %PHP_PATH%php-cgi -b 127.0.0.1:9000 -c %PHP_CONF% -d doc_root=%WWW_ROOT% -d error_log=%DIST_ROOT%\logs\php\php_errors.log

cd ..
