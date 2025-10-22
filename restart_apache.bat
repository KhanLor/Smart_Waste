@echo off
echo ================================================
echo   Restarting XAMPP Apache to Load GD Extension
echo ================================================
echo.

echo Checking if Apache is running...
tasklist /FI "IMAGENAME eq httpd.exe" 2>NUL | find /I /N "httpd.exe">NUL
if "%ERRORLEVEL%"=="0" (
    echo Apache is running. Stopping it...
    taskkill /F /IM httpd.exe >NUL 2>&1
    timeout /t 2 >NUL
    echo Apache stopped.
) else (
    echo Apache is not running.
)

echo.
echo Starting Apache...
start "" "C:\xampp\apache_start.bat"
timeout /t 3 >NUL

echo.
echo Checking if Apache started successfully...
timeout /t 2 >NUL
tasklist /FI "IMAGENAME eq httpd.exe" 2>NUL | find /I /N "httpd.exe">NUL
if "%ERRORLEVEL%"=="0" (
    echo [SUCCESS] Apache is now running!
    echo.
    echo GD Extension should now be loaded.
    echo.
    echo Next steps:
    echo 1. Visit: http://localhost/smart_waste/phpinfo.php
    echo 2. Search for "gd" on that page
    echo 3. If you see GD information, it's working!
    echo 4. Try uploading your profile image again
    echo.
) else (
    echo [WARNING] Could not verify if Apache started.
    echo Please open XAMPP Control Panel and restart Apache manually.
    echo.
)

echo Press any key to exit...
pause >NUL
