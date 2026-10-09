@echo off
title ACLP Agent - AI Chatbot Link to PC
cd /d "%~dp0"

rem Python 3.8+ is the only requirement. No pip install is needed:
rem the agent uses ONLY the Python standard library.

where py >nul 2>nul
if not errorlevel 1 (
    py -3 aclp_agent.py
    goto end
)

where python >nul 2>nul
if not errorlevel 1 (
    python aclp_agent.py
    goto end
)

echo [error] Python not found. Install Python 3.8+ from https://python.org
echo         and check "Add Python to PATH" during installation.

:end
echo.
pause
