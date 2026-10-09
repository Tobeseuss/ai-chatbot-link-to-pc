@echo off
chcp 65001 >nul 2>nul
title ACLP Agent - AI Chatbot Link to PC
cd /d "%~dp0"

where python >nul 2>nul
if errorlevel 1 (
    echo [error] Python not found. Install Python 3.9+ from https://python.org
    echo         and check "Add Python to PATH" during installation.
    pause
    exit /b 1
)

if not exist ".installed" (
    echo [setup] Installing dependencies (first run only)...
    python -m pip install --user -r requirements.txt
    echo ok> .installed
)

python aclp_agent.py
pause
