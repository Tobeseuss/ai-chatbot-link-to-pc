#!/usr/bin/env bash
# ACLP Agent launcher for Linux/macOS
# Python 3.8+ is the only requirement; NO pip install is needed
# (the agent uses only the Python standard library).
set -e
cd "$(dirname "$0")"

PY=python3
command -v $PY >/dev/null 2>&1 || { echo "[error] python3 not found. Install Python 3.8+ first."; exit 1; }

exec $PY aclp_agent.py
