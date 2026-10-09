#!/usr/bin/env bash
# ACLP Agent launcher for Linux
set -e
cd "$(dirname "$0")"

PY=python3
command -v $PY >/dev/null 2>&1 || { echo "[error] python3 not found. Install Python 3.9+ first."; exit 1; }

if [ ! -f ".installed" ]; then
    echo "[setup] Installing dependencies (first run only)..."
    $PY -m pip install --user -r requirements.txt || $PY -m pip install -r requirements.txt
    touch .installed
fi

exec $PY aclp_agent.py
