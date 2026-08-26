@echo off
title MediCare HMS — PC E:\HM DATA to Render Cloud Auto-Sync
color 0A

echo =========================================================================
echo  MEDICARE HMS — E:\HM DATA LOCAL PC TO RENDER CLOUD AUTO-SYNC
echo =========================================================================
echo.
echo  Local DB Path: E:\HM DATA\hms.db
echo  Cloud Server:  https://medicare-hms-public-gateway.onrender.com
echo.

php "e:\hospital real one\scripts\sync_pc_to_render.php"

echo.
echo =========================================================================
echo  Sync completed! Press any key to close.
echo =========================================================================
pause > nul
