#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
ACLP Agent — AI Chatbot Link to PC
==================================
Cross-platform agent (Windows / Linux) that connects the user's PC to the
"AI Chatbot Link to PC" WordPress plugin and executes commands issued by
AI chatbots: shell commands, file operations, software installation,
browser control, file transfers and more.

Version : 1.4.0
License : GPL-2.0-or-later
Repo    : https://github.com/Tobeseuss/ai-chatbot-link-to-pc

Highlights in 1.4.0:
- Version aligned with plugin v1.4.0: the plugin's AI onboarding text (principle 4,
  GET /agent-prompt) was rewritten as a neutral, professional API-integration guide
  ("job-processing service": POST a JSON job -> read the result in the response or
  poll the result endpoint every 3-5 seconds). No agent-side behavior change.

Highlights in 1.3.1:
- SITE URL AUTO-FIX: pasting the full REST endpoint (e.g. the
  https://example.com/wp-json/aclp/v1 shown in the WordPress admin panel)
  no longer breaks registration with HTTP 404. The agent extracts the site
  root automatically — both during setup AND on every start (an existing
  config.json with the doubled path is repaired in place, no re-setup).

Highlights in 1.3.0:
- ZERO EXTERNAL DEPENDENCIES. The agent runs on a plain Python 3.8+ install
  (standard library only — urllib replaces `requests`). Nothing is downloaded,
  nothing is pip-installed: the folder contains everything that is needed.
- ALL-ENGLISH interface. Persian/RTL text in legacy Windows terminals rendered
  unpredictably, so every agent message is now pure English (AI command output
  is still passed through unchanged, byte-safe UTF-8).
- AUTH: the agent sends `Authorization: Bearer <key>` FIRST. Some web hosts
  strip custom headers like `X-ACLP-Key` (which caused instant 401 exit on
  those hosts); Bearer is a standard header and always survives. The custom
  header is still sent too for backward compatibility.
- CRASH-PROOF: any fatal error is printed, written to aclp_agent.log and the
  window STAYS OPEN ("Press Enter to close") so double-clicked launchers no
  longer vanish before the reason can be read.
- CHAT MODE: `python aclp_agent.py chat` lets the user chat directly with the
  AI/LLM(s) connected to the same API key, send messages and files. Files are
  uploaded to the WordPress site and the AI receives a direct download link,
  so even AI front-ends that cannot upload files themselves still get them.
- One-shot relay mode for TEXT-ONLY chatbots: python aclp_agent.py relay ...
- Automatic HTTP fallback: if HTTPS fails (SSL/connection), the agent retries
  over HTTP transparently.
- Optional privilege elevation (sudo/su/UAC). Nothing requires elevation.
"""

import base64
import getpass
import json
import os
import shlex
import shutil
import socket
import ssl
import subprocess
import sys
import tempfile
import threading
import time
import traceback
import urllib.error
import urllib.parse
import urllib.request
import uuid
import webbrowser

__VERSION__ = "1.4.0"

CONFIG_FILE = os.path.join(os.path.dirname(os.path.abspath(__file__)), "config.json")
LOG_FILE = os.path.join(os.path.dirname(os.path.abspath(__file__)), "aclp_agent.log")
MAX_OUTPUT_CHARS = 400_000
MAX_FILE_READ_BYTES = 8 * 1024 * 1024  # 8 MB per file_read call
UA = f"ACLP-Agent/{__VERSION__}"


# ---------------------------------------------------------------------------
# Console bootstrap: UTF-8 everywhere (agent text is English; command OUTPUT
# may contain any language and must never crash the console).
# ---------------------------------------------------------------------------
def _console_setup():
    for stream in (sys.stdout, sys.stderr):
        try:
            stream.reconfigure(encoding="utf-8", errors="replace")
        except (AttributeError, ValueError):
            pass
    if os.name == "nt":
        try:
            import ctypes
            ctypes.windll.kernel32.SetConsoleOutputCP(65001)
            ctypes.windll.kernel32.SetConsoleCP(65001)
        except Exception:
            pass


_console_setup()


def out(message: str = "") -> None:
    """Print a user-facing line (English only)."""
    try:
        print(message, flush=True)
    except UnicodeEncodeError:
        print(message.encode("ascii", errors="replace").decode("ascii"), flush=True)


def log(message: str) -> None:
    """Print a timestamped line and append it to aclp_agent.log."""
    line = f"[{time.strftime('%Y-%m-%d %H:%M:%S')}] {message}"
    out(line)
    try:
        with open(LOG_FILE, "a", encoding="utf-8") as fh:
            fh.write(line + "\n")
    except OSError:
        pass


def pause_before_exit() -> None:
    """Keep the console window open on fatal errors so the user can read the
    reason (matters when the agent was started by double-clicking the .bat).
    Skipped automatically when stdin is not a TTY (piped/automated runs)."""
    try:
        if sys.stdin is not None and sys.stdin.isatty():
            out("")
            out("Press Enter to close this window... ")
            sys.stdin.readline()
    except Exception:
        pass


# ---------------------------------------------------------------------------
# Minimal HTTP layer built ONLY on the standard library (replaces `requests`).
# ---------------------------------------------------------------------------
class NetworkError(Exception):
    """Connection-level failure. kind: 'ssl' | 'timeout' | 'connection'."""

    def __init__(self, kind: str, detail: str):
        super().__init__(f"{kind}: {detail}")
        self.kind = kind
        self.detail = detail


def _http_request(method: str, url: str, headers=None, data=None, timeout: float = 30):
    """One HTTP call via urllib. Returns (status:int, headers:dict, body:bytes).
    HTTP 4xx/5xx are RETURNED (not raised). Connection-level problems raise
    NetworkError so the caller can trigger the HTTP fallback logic."""
    req = urllib.request.Request(url, data=data, method=method.upper())
    req.add_header("User-Agent", UA)
    for k, v in (headers or {}).items():
        req.add_header(k, v)
    try:
        with urllib.request.urlopen(req, timeout=timeout) as resp:
            return int(resp.status), dict(resp.headers.items()), resp.read()
    except urllib.error.HTTPError as exc:
        try:
            body = exc.read()
        except Exception:
            body = b""
        hdrs = {}
        try:
            hdrs = dict(exc.headers.items()) if exc.headers else {}
        except Exception:
            pass
        return int(exc.code), hdrs, body
    except (urllib.error.URLError, ssl.SSLError, ConnectionError, TimeoutError,
            OSError, ValueError) as exc:
        reason = getattr(exc, "reason", exc)
        text = f"{reason}" if reason is not None else f"{exc}"
        lowered = (text + " " + str(exc)).lower()
        if isinstance(reason, ssl.SSLError) or isinstance(exc, ssl.SSLError) \
                or "certificate" in lowered or "ssl" in lowered:
            raise NetworkError("ssl", text)
        if isinstance(exc, (TimeoutError,)) or "timed out" in lowered or "timeout" in lowered:
            raise NetworkError("timeout", text)
        raise NetworkError("connection", text)


def _http_download(url: str, headers, save_path: str, timeout: float = 600) -> int:
    """Stream a URL to a file. Returns the HTTP status; raises NetworkError on
    connection-level failures."""
    req = urllib.request.Request(url, method="GET")
    req.add_header("User-Agent", UA)
    for k, v in (headers or {}).items():
        req.add_header(k, v)
    try:
        with urllib.request.urlopen(req, timeout=timeout) as resp:
            os.makedirs(os.path.dirname(os.path.abspath(save_path)) or ".", exist_ok=True)
            with open(save_path, "wb") as fh:
                shutil.copyfileobj(resp, fh, 65536)
            return int(resp.status)
    except urllib.error.HTTPError as exc:
        return int(exc.code)
    except (urllib.error.URLError, ssl.SSLError, ConnectionError, TimeoutError,
            OSError) as exc:
        reason = getattr(exc, "reason", exc)
        text = f"{reason}" if reason is not None else f"{exc}"
        if isinstance(reason, ssl.SSLError) or isinstance(exc, ssl.SSLError):
            raise NetworkError("ssl", text)
        raise NetworkError("connection", text)


def _multipart(fields: dict, files: list) -> tuple:
    """Build a multipart/form-data body. files: list of (field, filename, bytes).
    Returns (content_type, body_bytes)."""
    boundary = "----ACLPBoundary" + uuid.uuid4().hex
    out_buf = bytearray()

    def add(s: str):
        out_buf.extend(s.encode("utf-8"))

    for name, value in (fields or {}).items():
        add(f"--{boundary}\r\n")
        add(f'Content-Disposition: form-data; name="{name}"\r\n\r\n')
        add(f"{value}\r\n")
    for name, filename, content in (files or []):
        add(f"--{boundary}\r\n")
        add(f'Content-Disposition: form-data; name="{name}"; filename="{filename}"\r\n')
        add("Content-Type: application/octet-stream\r\n\r\n")
        out_buf.extend(content if isinstance(content, bytes) else str(content).encode("utf-8"))
        add("\r\n")
    add(f"--{boundary}--\r\n")
    return f"multipart/form-data; boundary={boundary}", bytes(out_buf)


class ACLPError(Exception):
    """Raised by handlers to report a clean failure back to the chatbot."""


# ---------------------------------------------------------------------------
# Configuration
# ---------------------------------------------------------------------------
def load_config() -> dict:
    if not os.path.exists(CONFIG_FILE):
        return {}
    with open(CONFIG_FILE, encoding="utf-8") as fh:
        cfg = json.load(fh)
    cfg.setdefault("poll_interval", 5)
    cfg.setdefault("command_timeout", 300)
    cfg.setdefault("server_command_timeout", 300)
    cfg.setdefault("allow_http_fallback", True)  # if HTTPS breaks -> continue on HTTP
    cfg.setdefault("auto_elevate", False)        # auto elevation only with user consent
    cfg.setdefault("elevation_user", "")         # optional sudo/runas user
    cfg.setdefault("elevation_password", "")     # stays in the local config file only
    cfg.setdefault("ai_model", "")               # name of the AI that controls this PC
    return cfg


def save_config(cfg: dict) -> None:
    with open(CONFIG_FILE, "w", encoding="utf-8") as fh:
        json.dump(cfg, fh, indent=2, ensure_ascii=False)
    try:
        if os.name != "nt":  # protect the API key on unix-like systems
            os.chmod(CONFIG_FILE, 0o600)
    except OSError:
        pass


def _normalize_site_url(raw: str):
    """Extract the plain SITE ROOT from whatever URL the user pasted.

    Users very often copy the full REST endpoint shown in the WordPress
    admin panel or in the AI onboarding prompt, e.g.:

        https://example.com/wp-json/aclp/v1                  (REST root)
        https://example.com/wp-json/aclp/v1/                 (trailing /)
        https://example.com/wp-json/aclp/v1/agent/register   (full endpoint)
        https://example.com/wp-json/                         (generic REST)

    The agent always appends "/wp-json/aclp/v1" itself, so keeping any of
    those suffixes would double the path and every API call would fail with
    HTTP 404 "rest_no_route" (live-tested failure report from v1.3.0).
    Returns (clean_url, changed: bool).
    """
    url = (raw or "").strip().strip("\"").strip("'").rstrip("/")
    if not url:
        return raw, False
    if not url.lower().startswith(("http://", "https://")):
        url = "https://" + url
    changed = False
    low = url.lower()
    # cut everything from "/wp-json" onward (handles all pasted variants)
    pos = low.find("/wp-json")
    if pos > 0:
        url = url[:pos].rstrip("/")
        low = url.lower()
        changed = True
    # also tolerate a pasted "/aclp/v1" without the wp-json part
    if low.endswith("/aclp/v1"):
        url = url[: -len("/aclp/v1")].rstrip("/")
        changed = True
    if not url or url.lower() in ("http://", "https://"):
        return raw, False
    return url, changed


def setup_wizard() -> dict:
    """Interactive first-run setup. English-only by design (v1.3.0)."""
    out("=" * 62)
    out("  ACLP Agent setup  (AI Chatbot Link to PC)")
    out("=" * 62)
    out("")
    site = input("WordPress site URL (site root, e.g. https://example.com): ").strip()
    if not site:
        out("[error] Site URL is required.")
        raise SystemExit(1)
    site, url_fixed = _normalize_site_url(site)
    if url_fixed:
        out(f"[note] Full REST endpoint detected — using the site root instead: {site}")
        out("       (the agent adds /wp-json/aclp/v1 to this root by itself)")

    api_key = input("API key (from WordPress admin -> AI-PC Link -> API Keys): ").strip()
    if not api_key:
        out("[error] API key is required.")
        raise SystemExit(1)

    default_name = socket.gethostname()
    name = input(f"Name of this computer [{default_name}]: ").strip() or default_name

    ai_model = input(
        "Name of the AI/chatbot that will control this PC (optional, can be changed later): "
    ).strip()

    cfg = {
        "site_url": site,
        "api_key": api_key,
        "client_name": name,
        "client_uid": str(uuid.uuid4()),
        "poll_interval": 5,
        "command_timeout": 300,
        "ai_model": ai_model,
    }
    save_config(cfg)
    out(f"[ok] Config saved to: {CONFIG_FILE}")

    # ------------------------------------------------------------------
    # Optional privilege elevation (NOT required — everything works without)
    # ------------------------------------------------------------------
    out("")
    out("--- Administrator/root access (optional) ---")
    out("Everything works without this step. Configure it ONLY if you want the AI")
    out("to be able to run administrator/root commands when it asks to.")
    try:
        want = input("Configure admin elevation now? [y/N]: ").strip().lower() in ("y", "yes")
    except EOFError:
        want = False
    if want:
        default_user = os.environ.get("USERNAME") or os.environ.get("USER", "")
        e_user = input(f"User with sudo/runas rights [{default_user}]: ").strip() or default_user
        e_pass = getpass.getpass("Password of that user (hidden - Enter to skip): ")
        auto = input("Automatically retry failed commands with elevation? [y/N]: ").strip().lower() in ("y", "yes")
        cfg["elevation_user"] = e_user
        if e_pass:
            cfg["elevation_password"] = e_pass
        cfg["auto_elevate"] = auto
        save_config(cfg)
        out("[ok] Elevation settings saved (config.json is chmod 600 on Linux/macOS).")
    else:
        out("[ok] Skipped. You can add elevation_user / elevation_password / auto_elevate")
        out("     to config.json at any time.")

    out("")
    out("[ok] Starting the agent now... (press Ctrl+C to stop)")
    return cfg


# ---------------------------------------------------------------------------
# Handlers registry — each handler returns (result_dict, [produced_files])
# ---------------------------------------------------------------------------
HANDLERS = {}


def handler(name):
    def deco(fn):
        HANDLERS[name] = fn
        return fn
    return deco


def _run_shell(command: str, timeout: float) -> dict:
    """Run a shell command cross-platform and capture output."""
    try:
        proc = subprocess.run(
            command,
            shell=True,
            capture_output=True,
            timeout=timeout,
            cwd=None,
        )
        stdout = proc.stdout.decode("utf-8", errors="replace")
        stderr = proc.stderr.decode("utf-8", errors="replace")
        return {
            "exit_code": proc.returncode,
            "stdout": stdout[:MAX_OUTPUT_CHARS],
            "stderr": stderr[:MAX_OUTPUT_CHARS],
        }
    except subprocess.TimeoutExpired:
        raise ACLPError(f"Command timed out after {int(timeout)} seconds")


# ---------------------------------------------------------------------------
# Privilege elevation (optional) — Linux sudo/su + Windows UAC (runas).
# Nothing in the agent REQUIRES elevation; these helpers are used only when
# explicitly requested ("elevated": true / privilege_run) or when the user
# enabled auto_elevate in config.json.
# ---------------------------------------------------------------------------
def is_elevated() -> bool:
    """True if the current process already runs as root/administrator."""
    if os.name == "nt":
        try:
            import ctypes
            return bool(ctypes.windll.shell32.IsUserAnAdmin())
        except Exception:
            return False
    return hasattr(os, "geteuid") and os.geteuid() == 0


def _shell_result(proc) -> dict:
    return {
        "exit_code": proc.returncode,
        "stdout": (proc.stdout or b"").decode("utf-8", errors="replace")[:MAX_OUTPUT_CHARS],
        "stderr": (proc.stderr or b"").decode("utf-8", errors="replace")[:MAX_OUTPUT_CHARS],
    }


def _looks_like_permission_error(result: dict) -> bool:
    """Heuristic: did this failure happen because of missing privileges?"""
    if not isinstance(result, dict) or result.get("exit_code", 1) == 0:
        return False
    text = ((result.get("stdout") or "") + " " + (result.get("stderr") or "")).lower()
    markers = (
        "permission denied", "access is denied", "are you root?", "could not open lock file",
        "operation not permitted", "e: unable to acquire", "requires elevation",
        "administrator", "access denied", "not in the sudoers file", "no tty present",
        "epm", "0x8007", "must be root",
    )
    return any(m in text for m in markers)


def _pw_bytes(password: str):
    return (password + "\n").encode("utf-8") if password else None


def platform_system() -> str:
    if os.name == "nt":
        return "Windows"
    import platform
    return platform.system()


def platform_release() -> str:
    import platform
    return platform.release()


@handler("ping")
def h_ping(agent, payload):
    return {"pong": True, "time": time.strftime("%Y-%m-%d %H:%M:%S"), "agent_version": __VERSION__}, []


@handler("sysinfo")
def h_sysinfo(agent, payload):
    info = {
        "platform": sys.platform,
        "system": platform_system(),
        "os_release": platform_release(),
        "hostname": socket.gethostname(),
        "python_version": sys.version.split()[0],
        "cpu_count": os.cpu_count(),
        "cwd": os.getcwd(),
        "user": os.environ.get("USERNAME") or os.environ.get("USER", ""),
        "agent_version": __VERSION__,
    }
    try:
        usage = shutil.disk_usage(os.path.expanduser("~") or "/")
        info["disk_total_gb"] = round(usage.total / 1024**3, 2)
        info["disk_free_gb"] = round(usage.free / 1024**3, 2)
    except OSError:
        pass
    try:
        import psutil
        mem = psutil.virtual_memory()
        info["memory_total_gb"] = round(mem.total / 1024**3, 2)
        info["memory_available_gb"] = round(mem.available / 1024**3, 2)
        info["boot_time"] = time.strftime("%Y-%m-%d %H:%M:%S", time.localtime(psutil.boot_time()))
    except Exception:
        info["memory"] = "psutil not installed (optional): memory info unavailable"
    return info, []


@handler("shell")
def h_shell(agent, payload):
    command = (payload.get("command") or "").strip()
    if not command:
        raise ACLPError("payload.command is required")
    timeout = float(payload.get("timeout") or agent.cfg.get("command_timeout", 300))
    if payload.get("elevated") and not is_elevated():
        # explicit elevated run — possible only when the user configured credentials
        return agent.run_privileged(command, timeout), []
    return _run_shell(command, timeout), []


@handler("run_python")
def h_run_python(agent, payload):
    code = payload.get("code") or ""
    if not code:
        raise ACLPError("payload.code is required")
    timeout = float(payload.get("timeout") or agent.cfg.get("command_timeout", 300))
    tmp = tempfile.NamedTemporaryFile("w", suffix=".py", delete=False, encoding="utf-8")
    tmp.write(code)
    tmp.close()
    try:
        return _run_shell(f'"{sys.executable}" "{tmp.name}"', timeout), []
    finally:
        try:
            os.unlink(tmp.name)
        except OSError:
            pass


@handler("process_list")
def h_process_list(agent, payload):
    try:
        import psutil
        procs = []
        for p in psutil.process_iter(["pid", "name", "username", "cpu_percent", "memory_info"]):
            try:
                info = p.info
                procs.append({
                    "pid": info["pid"],
                    "name": info["name"],
                    "user": info["username"],
                    "memory_mb": round((info["memory_info"].rss or 0) / 1024**2, 1) if info["memory_info"] else 0,
                })
            except (psutil.NoSuchProcess, psutil.AccessDenied):
                continue
        return {"count": len(procs), "processes": procs[:500]}, []
    except ImportError:
        if os.name == "nt":
            out = _run_shell("tasklist", 60)
        else:
            out = _run_shell("ps aux", 60)
        return {"raw": out.get("stdout", "")[:MAX_OUTPUT_CHARS]}, []


@handler("kill_process")
def h_kill_process(agent, payload):
    pid = payload.get("pid")
    name = payload.get("name")
    try:
        import psutil
        killed = []
        if pid:
            psutil.Process(int(pid)).terminate()
            killed.append(int(pid))
        elif name:
            for p in psutil.process_iter(["pid", "name"]):
                if name.lower() in (p.info["name"] or "").lower():
                    try:
                        psutil.Process(p.info["pid"]).terminate()
                        killed.append(p.info["pid"])
                    except (psutil.NoSuchProcess, psutil.AccessDenied):
                        continue
        else:
            raise ACLPError("payload.pid or payload.name is required")
        return {"terminated": killed}, []
    except ImportError:
        if os.name == "nt":
            cmd = f"taskkill /F /PID {pid}" if pid else f"taskkill /F /IM {name}"
        else:
            cmd = f"kill {pid}" if pid else f"pkill -f {name}"
        return _run_shell(cmd, 60), []


# ---------------------------------------------------------------------------
# File handlers
# ---------------------------------------------------------------------------
@handler("file_read")
def h_file_read(agent, payload):
    path = os.path.expanduser(payload.get("path") or "")
    if not path:
        raise ACLPError("payload.path is required")
    if not os.path.isfile(path):
        raise ACLPError(f"File not found: {path}")
    size = os.path.getsize(path)
    max_bytes = int(payload.get("max_bytes") or MAX_FILE_READ_BYTES)
    with open(path, "rb") as fh:
        data = fh.read(max_bytes)
    return {
        "path": path,
        "size": size,
        "truncated": size > len(data),
        "content_base64": base64.b64encode(data).decode("ascii"),
    }, []


@handler("file_write")
def h_file_write(agent, payload):
    path = os.path.expanduser(payload.get("path") or "")
    if not path:
        raise ACLPError("payload.path is required")
    content = payload.get("content_base64") or ""
    append = bool(payload.get("append"))
    os.makedirs(os.path.dirname(path) or ".", exist_ok=True)
    mode = "ab" if append else "wb"
    data = base64.b64decode(content) if content else b""
    with open(path, mode) as fh:
        fh.write(data)
    return {"path": path, "bytes_written": len(data), "size": os.path.getsize(path)}, []


@handler("file_list")
def h_file_list(agent, payload):
    path = os.path.expanduser(payload.get("path") or ".")
    if not os.path.exists(path):
        raise ACLPError(f"Path not found: {path}")
    entries = []
    limit = int(payload.get("limit") or 1000)
    for name in sorted(os.listdir(path))[:limit]:
        full = os.path.join(path, name)
        try:
            st = os.stat(full)
            entries.append({
                "name": name,
                "is_dir": os.path.isdir(full),
                "size": st.st_size,
                "modified": time.strftime("%Y-%m-%d %H:%M:%S", time.localtime(st.st_mtime)),
            })
        except OSError:
            continue
    return {"path": path, "count": len(entries), "entries": entries}, []


@handler("file_delete")
def h_file_delete(agent, payload):
    path = os.path.expanduser(payload.get("path") or "")
    if not path:
        raise ACLPError("payload.path is required")
    if os.path.isdir(path) and payload.get("recursive"):
        shutil.rmtree(path)
    elif os.path.isdir(path):
        raise ACLPError("Path is a directory; send recursive=true to remove it")
    elif os.path.isfile(path):
        os.remove(path)
    else:
        raise ACLPError(f"Path not found: {path}")
    return {"deleted": path}, []


@handler("file_mkdir")
def h_file_mkdir(agent, payload):
    path = os.path.expanduser(payload.get("path") or "")
    if not path:
        raise ACLPError("payload.path is required")
    os.makedirs(path, exist_ok=True)
    return {"created": path}, []


@handler("file_move")
def h_file_move(agent, payload):
    src = os.path.expanduser(payload.get("src") or "")
    dst = os.path.expanduser(payload.get("dst") or "")
    if not src or not dst:
        raise ACLPError("payload.src and payload.dst are required")
    os.makedirs(os.path.dirname(dst) or ".", exist_ok=True)
    if payload.get("copy"):
        shutil.copy2(src, dst)
        return {"copied": src, "to": dst}, []
    shutil.move(src, dst)
    return {"moved": src, "to": dst}, []


@handler("upload_file")
def h_upload_file(agent, payload):
    """Send a file from this PC to the chatbot (stored on the WordPress site)."""
    path = os.path.expanduser(payload.get("path") or "")
    if not path:
        raise ACLPError("payload.path is required")
    if not os.path.isfile(path):
        raise ACLPError(f"File not found: {path}")
    return {"uploading": path, "size": os.path.getsize(path)}, [path]


@handler("file_download")
def h_file_download(agent, payload):
    """Download a file delivered by the chatbot (to_pc attachment)."""
    file_id = payload.get("file_id")
    save_path = os.path.expanduser(payload.get("save_path") or "")
    if not file_id or not save_path:
        raise ACLPError("payload.file_id and payload.save_path are required")
    agent.download_file(file_id, save_path)
    return {"saved": save_path, "size": os.path.getsize(save_path)}, []


# ---------------------------------------------------------------------------
# Desktop / browser / install handlers
# ---------------------------------------------------------------------------
@handler("open_url")
def h_open_url(agent, payload):
    url = (payload.get("url") or "").strip()
    if not url:
        raise ACLPError("payload.url is required")
    ok = webbrowser.open(url)
    return {"opened": url, "success": bool(ok)}, []


@handler("http_request")
def h_http_request(agent, payload):
    """Fetch a URL from this PC (lets the chatbot browse the web through it)."""
    url = (payload.get("url") or "").strip()
    if not url:
        raise ACLPError("payload.url is required")
    method = (payload.get("method") or "GET").upper()
    headers = payload.get("headers") or {}
    body = payload.get("body")
    timeout = float(payload.get("timeout") or 60)
    max_bytes = int(payload.get("max_bytes") or 2_000_000)

    data = None
    if body is not None:
        data = body.encode("utf-8") if isinstance(body, str) else body
    status, rheaders, raw = _http_request(method, url, headers=headers, data=data, timeout=timeout)
    truncated = len(raw) > max_bytes
    content = raw[:max_bytes]
    try:
        text = content.decode("utf-8")
    except UnicodeDecodeError:
        text = content.decode("utf-8", errors="replace")
    return {
        "status": status,
        "url": url,
        "headers": dict(list(rheaders.items())[:30]),
        "body": text,
        "truncated": truncated,
    }, []


@handler("screenshot")
def h_screenshot(agent, payload):
    try:
        import pyautogui  # noqa
    except ImportError:
        raise ACLPError("pyautogui is not installed (optional). Run: pip install pyautogui pillow")
    path = os.path.join(tempfile.gettempdir(), f"aclp_screenshot_{int(time.time())}.png")
    shot = pyautogui.screenshot()
    shot.save(path)
    return {"format": "png", "size": os.path.getsize(path)}, [path]


@handler("install")
def h_install(agent, payload):
    """Install software using the platform package manager or pip.
    If a plain attempt fails with a permission error, the agent retries with
    elevation ONLY when the user configured credentials or enabled auto_elevate;
    otherwise it falls back to plain `sudo` (works on NOPASSWD setups)."""
    packages = payload.get("packages") or payload.get("package")
    if not packages:
        raise ACLPError("payload.packages is required")
    if isinstance(packages, str):
        packages = [packages]
    manager = (payload.get("manager") or "auto").lower()
    timeout = float(payload.get("timeout") or 1800)
    elevate_allowed = bool(agent.cfg.get("auto_elevate")) or bool(agent.cfg.get("elevation_password"))

    system = platform_system()
    if manager in ("auto", "pip"):
        if manager == "pip":
            cmd = f'"{sys.executable}" -m pip install {" ".join(packages)}'
            return _run_shell(cmd, timeout), []

    if system == "Windows":
        if manager in ("auto", "winget"):
            cmd = "winget install --accept-package-agreements --accept-source-agreements " + " ".join(packages)
            result = _run_shell(cmd, timeout)
            if result.get("exit_code") == 0:
                return result, []
            if elevate_allowed and not is_elevated() and _looks_like_permission_error(result):
                return agent.run_privileged(cmd, timeout), []
        if manager in ("auto", "choco"):
            cmd = "choco install -y " + " ".join(packages)
            result = _run_shell(cmd, timeout)
            if result.get("exit_code") == 0:
                return result, []
            if elevate_allowed and not is_elevated() and _looks_like_permission_error(result):
                return agent.run_privileged(cmd, timeout), []
            return result, []
        raise ACLPError("No suitable Windows installer (winget/choco) succeeded")
    else:
        if manager in ("auto", "apt"):
            cmd = "apt-get install -y " + " ".join(packages)
            res = _run_shell(cmd, timeout)
            if res.get("exit_code") != 0:
                if elevate_allowed:
                    try:
                        res2 = agent.run_privileged(cmd, timeout)
                        if res2.get("exit_code") == 0:
                            return res2, []
                        res = res2
                    except ACLPError:
                        pass
                if res.get("exit_code") != 0:
                    # last try: plain sudo (may be NOPASSWD)
                    res = _run_shell("sudo " + cmd, timeout)
            return res, []
        if manager == "dnf":
            cmd = "dnf install -y " + " ".join(packages)
            res = _run_shell(cmd, timeout)
            if res.get("exit_code") != 0 and elevate_allowed and _looks_like_permission_error(res):
                try:
                    res = agent.run_privileged(cmd, timeout)
                except ACLPError:
                    pass
            return res, []
        if manager == "pacman":
            cmd = "pacman -S --noconfirm " + " ".join(packages)
            res = _run_shell(cmd, timeout)
            if res.get("exit_code") != 0 and elevate_allowed and _looks_like_permission_error(res):
                try:
                    res = agent.run_privileged(cmd, timeout)
                except ACLPError:
                    pass
            return res, []
        raise ACLPError(f"Unknown package manager: {manager}")


# ---------------------------------------------------------------------------
# Privilege action handlers (optional — documented in docs/AGENT-API.md)
# ---------------------------------------------------------------------------
@handler("privilege_status")
def h_privilege_status(agent, payload):
    """Report whether the agent runs elevated and how elevation can be gained.
    Lets the chatbot know in advance whether admin/root commands are possible."""
    info = {
        "elevated": is_elevated(),
        "platform": platform_system(),
        "user": os.environ.get("USERNAME") or os.environ.get("USER", ""),
        "auto_elevate": bool(agent.cfg.get("auto_elevate", False)),
        "credentials_configured": bool(agent.cfg.get("elevation_user") or agent.cfg.get("elevation_password")),
        "elevation_user": agent.cfg.get("elevation_user", ""),
    }
    if os.name == "nt":
        info["method"] = "UAC prompt (PowerShell Start-Process -Verb RunAs) when credentials/auto_elevate configured; run the agent as Administrator to avoid prompts"
    else:
        info["sudo_available"] = shutil.which("sudo") is not None
        info["su_available"] = shutil.which("su") is not None
        info["method"] = "sudo -S with configured password, fallback to plain sudo (NOPASSWD), fallback to su -c"
    info["note"] = "Elevation is optional; all normal actions work without it."
    return info, []


@handler("privilege_run")
def h_privilege_run(agent, payload):
    """Run ONE shell command with administrator/root privileges (when possible)."""
    command = (payload.get("command") or "").strip()
    if not command:
        raise ACLPError("payload.command is required")
    timeout = float(payload.get("timeout") or agent.cfg.get("command_timeout", 300))
    return agent.run_privileged(command, timeout), []


# ---------------------------------------------------------------------------
# Agent
# ---------------------------------------------------------------------------
class Agent:
    def __init__(self, cfg: dict):
        self.cfg = cfg
        # v1.3.1 self-heal: repair configs saved by older versions where the
        # user pasted the full REST endpoint (caused HTTP 404 on every call).
        site, url_fixed = _normalize_site_url(cfg.get("site_url", ""))
        if url_fixed:
            cfg["site_url"] = site
            try:
                save_config(cfg)
            except OSError:
                pass
            log(f"[fix] config.json site_url contained the REST path — "
                f"normalized to: {site}")
        # Base URL candidates: if HTTPS has problems (SSL/connection), we
        # automatically switch to the next candidate (HTTP) and keep going.
        self.base_candidates = [site + "/wp-json/aclp/v1"]
        if site.startswith("https://"):
            self.base_candidates.append("http://" + site[len("https://"):] + "/wp-json/aclp/v1")
        elif site.startswith("http://"):
            self.base_candidates.append("https://" + site[len("http://"):] + "/wp-json/aclp/v1")
        self.base_index = 0
        self.base = self.base_candidates[0]
        self.err_streak = 0
        self.registered = False
        key = cfg["api_key"]
        # AUTH ORDER MATTERS: `Authorization: Bearer` first because some web
        # hosts strip unknown/custom headers (X-ACLP-Key) — Bearer is standard
        # and always reaches WordPress. X-ACLP-Key is still sent for older
        # plugin versions and normal hosts.
        self.headers = {
            "Authorization": f"Bearer {key}",
            "X-ACLP-Key": key,
            "X-ACLP-Client-UID": cfg["client_uid"],
            "Accept": "application/json",
        }

    # -- protocol fallback (HTTP support when HTTPS breaks) ------------------
    def _maybe_switch_protocol(self, kind: str) -> bool:
        """On SSL/connection failures, permanently switch to the next base URL
        candidate (https <-> http). Returns True when we switched."""
        if not self.cfg.get("allow_http_fallback", True):
            return False
        if self.base_index >= len(self.base_candidates) - 1:
            return False
        if kind in ("ssl", "connection", "timeout"):
            self.base_index += 1
            self.base = self.base_candidates[self.base_index]
            log(f"Connection problem ({kind}) — switching base URL to: {self.base}")
            return True
        return False

    # -- low level -----------------------------------------------------------
    def api(self, method: str, path: str, expect_json: bool = True,
            json_body=None, data=None, files=None, timeout: float = 30):
        """One authenticated API call. Returns parsed JSON dict (or raw bytes
        when expect_json=False). Never raises on HTTP 4xx/5xx — returns
        {"ok": False, "status_code": ..., "message": ...} instead."""
        url = self.base + path
        headers = dict(self.headers)
        body = None
        if files is not None:
            ctype, body = _multipart(data or {}, files)
            headers["Content-Type"] = ctype
        elif json_body is not None:
            body = json.dumps(json_body).encode("utf-8")
            headers["Content-Type"] = "application/json"
        elif data is not None:
            body = urllib.parse.urlencode(data).encode("utf-8")
            headers["Content-Type"] = "application/x-www-form-urlencoded"

        attempt = 0
        while attempt < 4:
            try:
                status, _rh, raw = _http_request(method, url, headers=headers,
                                                 data=body, timeout=timeout)
                if status >= 400:
                    text = raw[:400].decode("utf-8", errors="replace")
                    try:
                        msg = json.loads(text).get("message", text[:300])
                    except Exception:
                        msg = text[:300]
                    log(f"API {method} {path} -> HTTP {status}: {msg}")
                    return {"ok": False, "status_code": status, "message": msg}
                if expect_json:
                    return json.loads(raw.decode("utf-8", errors="replace") or "{}")
                return raw
            except NetworkError as exc:
                attempt += 1
                if self._maybe_switch_protocol(exc.kind):
                    url = self.base + path
                    continue  # protocol changed — this attempt does not count
                log(f"Network error on {method} {path} (attempt {attempt}/4): {exc}")
                time.sleep(2 * attempt)
        return {"ok": False, "message": "network failure after retries"}

    # -- lifecycle ------------------------------------------------------------
    def register(self):
        import platform
        payload = {
            "client_uid": self.cfg["client_uid"],
            "name": self.cfg.get("client_name") or socket.gethostname(),
            "os": platform.system(),
            "os_version": platform.release(),
            "hostname": socket.gethostname(),
            "python_version": platform.python_version(),
            "agent_version": __VERSION__,
            "ai_model": self.cfg.get("ai_model", ""),
            "capabilities": sorted(HANDLERS.keys()),
        }
        resp = self.api("POST", "/agent/register", json_body=payload)
        if resp.get("ok"):
            self.registered = True
            self.err_streak = 0
            self.cfg["poll_interval"] = resp.get("poll_interval", self.cfg.get("poll_interval", 5))
            self.cfg["command_timeout"] = resp.get("command_timeout", self.cfg.get("command_timeout", 300))
            log(f"Registered as client #{resp.get('client_id')} (created={resp.get('created')}). "
                f"Poll interval: {self.cfg['poll_interval']}s")
        else:
            if resp.get("status_code") == 409:
                # duplicate UID under a different key -> regenerate and retry once
                log("client_uid conflict — regenerating a new UID...")
                self.cfg["client_uid"] = str(uuid.uuid4())
                self.headers["X-ACLP-Client-UID"] = self.cfg["client_uid"]
                save_config(self.cfg)
                return self.register()
            self.registered = False
        return resp

    # -- one-shot relay mode (for text-only chatbots) --------------------------
    def relay(self, ctype: str, payload: dict, timeout: float = 120) -> int:
        """Submit ONE action to the bridge, execute it on this PC through the
        normal queue, then print a single JSON block with the final result.
        Designed for text-only chatbots: the chatbot prints this command, the
        user copies it into a terminal, then pastes the JSON back to the chatbot.
        Returns a process exit code."""
        reg = self.register()
        if not self.registered:
            log(f"ERROR: could not register with server: {reg.get('message', 'unknown')}")
            return 1

        # 1) create the command through the same path chatbots use (full history)
        created = self.api("POST", "/commands", json_body={
            "type": ctype,
            "payload": payload,
            "client_uid": self.cfg["client_uid"],
            "source": self.cfg.get("ai_model") or "text-chatbot-relay",
        })
        if not created.get("ok"):
            log(f"ERROR: could not create command: {created.get('message', 'unknown')}")
            return 1
        cmds = created.get("commands") or []
        if not cmds:
            log("ERROR: server returned no command UID.")
            return 1
        target_uid = cmds[0]["command_uid"]
        log(f"Command queued: {target_uid}")

        # 2) take the command from this PC's queue and run it (normal path)
        deadline = time.time() + timeout
        done = False
        while time.time() < deadline and not done:
            pending = self.api("GET", "/agent/commands/pending?limit=10")
            if pending.get("ok"):
                for cmd in pending.get("commands", []):
                    self.execute(cmd)
                    if cmd.get("command_uid") == target_uid:
                        done = True
                if not done:
                    # maybe another agent instance (poll mode) already took it
                    st = self.api("GET", f"/commands/{target_uid}")
                    if st.get("status") in ("completed", "failed"):
                        done = True
            else:
                time.sleep(2)
            if not done:
                time.sleep(1)

        # 3) fetch the final result and print ONE clean JSON block
        final = self.api("GET", f"/commands/{target_uid}")
        out()
        out("=" * 62)
        out("RESULT JSON (copy this back to the chatbot):")
        out("=" * 62)
        print(json.dumps(final, ensure_ascii=False, indent=2))
        status = final.get("status")
        return 0 if status in ("completed", "failed") else 2

    # -- chat mode (user <-> AI through the bridge) -----------------------------
    def chat_loop(self) -> int:
        """Interactive chat between the USER and the AI/LLM(s) connected to the
        same API key. Messages and files go through the WordPress plugin, so
        the AI receives the file as a direct download link on the site."""
        reg = self.register()
        if not self.registered:
            log(f"ERROR: could not register with server: {reg.get('message', 'unknown')}")
            return 1

        out("=" * 62)
        out(f"ACLP Chat v{__VERSION__} — connected to {self.base}")
        out("=" * 62)
        out("Type a message and press Enter to send it to the AI(s) that use your")
        out("API key. Commands:")
        out("  /file <path>   send a file from this PC (uploaded to the site; the AI")
        out("                 gets a direct download link — no AI-side upload needed)")
        out("  /status        show connection status")
        out("  /help          show this help again")
        out("  /exit          leave the chat")
        out("")

        print_lock = threading.Lock()
        stop = threading.Event()
        state = {"last_id": int(self.cfg.get("chat_last_id", 0))}

        def fetch_history():
            """On start, show the last few AI replies so context is not lost."""
            replies = self.api("GET", "/chat/replies?limit=5&order=desc")
            if replies.get("ok"):
                msgs = list(reversed(replies.get("messages", [])))
                if msgs:
                    state["last_id"] = max(state["last_id"], max(m["id"] for m in msgs))
                    with print_lock:
                        out("--- last AI messages (history) ---")
                        for m in msgs:
                            _print_chat_message(m, "(history)")
                        out("----------------------------------")

        def poll_replies():
            while not stop.is_set():
                try:
                    replies = self.api("GET", f"/chat/replies?since={state['last_id']}&limit=50")
                    if replies.get("ok"):
                        msgs = replies.get("messages", [])
                        if msgs:
                            state["last_id"] = max(state["last_id"], max(m["id"] for m in msgs))
                            self.cfg["chat_last_id"] = state["last_id"]
                            save_config(self.cfg)
                            with print_lock:
                                for m in msgs:
                                    _print_chat_message(m)
                                print("You> ", end="", flush=True)
                except Exception as exc:
                    log(f"chat poll error: {exc}")
                stop.wait(2.0)

        def _print_chat_message(m, tag=""):
            source = (m.get("source") or "AI").strip() or "AI"
            out(f"[{source}]{(' ' + tag) if tag else ''} {m.get('body', '')}")
            for f in (m.get("files") or []):
                out(f"    [file] {f.get('filename', 'file')} -> {f.get('url', '')}")

        fetch_history()
        worker = threading.Thread(target=poll_replies, daemon=True)
        worker.start()

        while True:
            try:
                line = input("You> ").strip()
            except (EOFError, KeyboardInterrupt):
                out("")
                break
            if not line:
                continue
            low = line.lower()
            if low in ("/exit", "/quit", "/q"):
                break
            if low in ("/help", "/?"):
                out("  /file <path>  send a file   |  /status  connection status")
                out("  /help  help                  |  /exit    leave the chat")
                continue
            if low == "/status":
                out(f"  server : {self.base}")
                out(f"  client : {self.cfg.get('client_name', '')} (uid {self.cfg.get('client_uid', '')[:8]}...)")
                out(f"  registered: {'yes' if self.registered else 'no'}")
                continue

            files_payload = []
            text = line
            if low.startswith("/file "):
                path = os.path.expanduser(line[6:].strip().strip('"'))
                if not os.path.isfile(path):
                    out(f"[error] File not found: {path}")
                    continue
                try:
                    with open(path, "rb") as fh:
                        content = fh.read()
                except OSError as exc:
                    out(f"[error] Cannot read file: {exc}")
                    continue
                up = self.upload_bytes(os.path.basename(path), content)
                if not up.get("ok"):
                    out(f"[error] Upload failed: {up.get('message', 'unknown')}")
                    if up.get("status_code") == 404:
                        out("        Your WordPress plugin is older than 1.3.0 — update it.")
                    continue
                link = up.get("download_url") or up.get("url")
                files_payload = [{"file_id": up.get("file_id"),
                                  "url": link,
                                  "filename": up.get("filename", os.path.basename(path))}]
                text = f"[file] {up.get('filename', os.path.basename(path))} — download link attached"
                out(f"[ok] File uploaded to the site: {link}")

            send = self.api("POST", "/chat/send", json_body={"text": text, "files": files_payload})
            if not send.get("ok"):
                out(f"[error] Could not send message: {send.get('message', 'unknown')}")
                if send.get("status_code") == 404:
                    out("        Chat endpoints not found — your WordPress plugin is older")
                    out("        than 1.3.0. Update the plugin, then restart the agent.")

        stop.set()
        out("[ok] Chat closed.")
        return 0

    def download_file(self, file_id, save_path):
        status = _http_download(f"{self.base}/files/{int(file_id)}", self.headers,
                                save_path, timeout=600)
        if status >= 400:
            raise ACLPError(f"Download failed with HTTP {status} (file #{file_id})")
        log(f"Downloaded file #{file_id} -> {save_path}")

    def upload_bytes(self, filename: str, content: bytes, command_uid: str = "") -> dict:
        """Upload one file to the WordPress site (direction: from PC)."""
        ctype, body = _multipart(
            {"command_uid": command_uid, "direction": "from_pc"},
            [("file", filename, content)],
        )
        headers = dict(self.headers)
        headers["Content-Type"] = ctype
        url = self.base + "/agent/files"
        attempt = 0
        while attempt < 3:
            try:
                status, _rh, raw = _http_request("POST", url, headers=headers, data=body, timeout=900)
                if status >= 400:
                    text = raw[:400].decode("utf-8", errors="replace")
                    try:
                        msg = json.loads(text).get("message", text[:300])
                    except Exception:
                        msg = text[:300]
                    log(f"Upload -> HTTP {status}: {msg}")
                    return {"ok": False, "status_code": status, "message": msg}
                return json.loads(raw.decode("utf-8", errors="replace") or "{}")
            except NetworkError as exc:
                attempt += 1
                if self._maybe_switch_protocol(exc.kind):
                    url = self.base + "/agent/files"
                    continue
                log(f"Upload network error (attempt {attempt}/3): {exc}")
                time.sleep(2 * attempt)
        return {"ok": False, "message": "upload failed after retries (network)"}

    def upload_file(self, command_uid: str, path: str) -> dict:
        try:
            with open(path, "rb") as fh:
                return self.upload_bytes(os.path.basename(path), fh.read(), command_uid)
        except OSError as exc:
            return {"ok": False, "message": f"Cannot read file {path}: {exc}"}

    # -- command execution ------------------------------------------------------
    def execute(self, cmd: dict):
        uid = cmd.get("command_uid", "")
        ctype = cmd.get("type", "")
        payload = cmd.get("payload") or {}
        log(f"Executing {ctype} ({uid}) ...")
        self.api("POST", f"/agent/commands/{uid}/status", json_body={"status": "running"})

        started = time.time()
        try:
            fn = HANDLERS.get(ctype)
            if fn is None:
                raise ACLPError(f"Unknown action type: {ctype}")
            result, produced_files = fn(self, payload)

            file_refs = []
            for fp in produced_files:
                up = self.upload_file(uid, fp)
                if up.get("ok"):
                    file_refs.append({"file_id": up["file_id"], "filename": up["filename"], "size": up["size"]})
                try:
                    os.unlink(fp)
                except OSError:
                    pass

            self.api("POST", f"/agent/commands/{uid}/result", json_body={
                "status": "completed",
                "result": result,
                "error": None,
                "duration_ms": int((time.time() - started) * 1000),
                "files": file_refs,
            })
            log(f"Completed {ctype} in {time.time() - started:.2f}s")
        except ACLPError as exc:
            self._fail(uid, str(exc), started)
        except subprocess.TimeoutExpired:
            self._fail(uid, "Command timed out", started)
        except Exception as exc:  # unexpected
            log("Unexpected error:\n" + traceback.format_exc(limit=3))
            self._fail(uid, f"{type(exc).__name__}: {exc}", started)

    def _fail(self, uid, message, started):
        self.api("POST", f"/agent/commands/{uid}/result", json_body={
            "status": "failed",
            "result": None,
            "error": message,
            "duration_ms": int((time.time() - started) * 1000),
            "files": [],
        })
        log(f"Failed: {message}")

    # -- privilege elevation (optional) ------------------------------------------
    def run_privileged(self, command: str, timeout: float) -> dict:
        """Run a shell command with admin/root privileges when possible.
        - Already elevated -> run directly.
        - Linux -> sudo -S with configured password, fallback to plain sudo
          (NOPASSWD), fallback to su -c with password.
        - Windows -> PowerShell Start-Process -Verb RunAs (UAC prompt appears;
          the user clicks Yes). If elevation is impossible, a clear ACLPError is
          raised so the chatbot can tell the user — nothing breaks silently.
        """
        if is_elevated():
            return _run_shell(command, timeout)
        if platform_system() == "Windows":
            return self._run_elevated_windows(command, timeout)
        return self._run_elevated_unix(command, timeout)

    def _run_elevated_unix(self, command: str, timeout: float) -> dict:
        password = self.cfg.get("elevation_password") or ""
        sudo = shutil.which("sudo")
        su = shutil.which("su")

        if sudo:
            cmd = ["sudo", "-S", "-p", "", "-H", "bash", "-lc", command]
            try:
                proc = subprocess.run(cmd, input=_pw_bytes(password), capture_output=True, timeout=timeout)
                result = _shell_result(proc)
                if proc.returncode == 0:
                    return result
                if not password and _looks_like_permission_error(result):
                    log("sudo needs a password — configure elevation_password in config.json")
                return result
            except subprocess.TimeoutExpired:
                raise ACLPError(f"Elevated command timed out after {int(timeout)} seconds")

        if su:
            cmd = ["su", "-c", "bash -lc " + shlex.quote(command)]
            try:
                proc = subprocess.run(cmd, input=_pw_bytes(password), capture_output=True, timeout=timeout)
                return _shell_result(proc)
            except subprocess.TimeoutExpired:
                raise ACLPError(f"Elevated command timed out after {int(timeout)} seconds")

        raise ACLPError(
            "Elevation unavailable: no sudo/su found, or provide elevation_user/elevation_password "
            "in config.json. Normal (non-elevated) actions still work fine."
        )

    def _run_elevated_windows(self, command: str, timeout: float) -> dict:
        """Elevate via PowerShell Start-Process -Verb RunAs.
        A UAC consent dialog appears on the user's screen — they must click Yes.
        All quoting problems are avoided by passing scripts as -EncodedCommand."""
        out_path = os.path.join(tempfile.gettempdir(), f"aclp_elev_out_{int(time.time())}.txt")
        err_path = os.path.join(tempfile.gettempdir(), f"aclp_elev_err_{int(time.time())}.txt")
        inner = (
            "$c = '" + command.replace("'", "''") + "'\n"
            "$o = '" + out_path.replace("'", "''") + "'\n"
            "$e = '" + err_path.replace("'", "''") + "'\n"
            "& cmd.exe /c $c 1> $o 2> $e\n"
            "exit $LASTEXITCODE"
        )
        outer = (
            "$inner = '" + inner.replace("'", "''") + "'\n"
            "$b = [Convert]::ToBase64String([Text.Encoding]::Unicode.GetBytes($inner))\n"
            "try {\n"
            "  $p = Start-Process -FilePath 'powershell.exe' -ArgumentList @('-NoProfile','-ExecutionPolicy','Bypass','-EncodedCommand',$b) -Verb RunAs -PassThru -Wait\n"
            "  exit $p.ExitCode\n"
            "} catch {\n"
            "  [Console]::Error.WriteLine('UAC-ELEVATION-DECLINED: ' + $_.Exception.Message)\n"
            "  exit 2147942584\n"
            "}"
        )
        outer_b64 = base64.b64encode(outer.encode("utf-16-le")).decode("ascii")
        try:
            proc = subprocess.run(
                ["powershell", "-NoProfile", "-EncodedCommand", outer_b64],
                capture_output=True, timeout=timeout,
            )
        except subprocess.TimeoutExpired:
            raise ACLPError(f"Elevated command timed out after {int(timeout)} seconds")
        except FileNotFoundError:
            raise ACLPError("powershell.exe not found — cannot elevate on this system")

        if proc.returncode == 2147942584 or b"UAC-ELEVATION-DECLINED" in (proc.stderr or b""):
            raise ACLPError("UAC elevation was declined or failed. Click Yes on the UAC prompt, or run the agent as Administrator.")

        raw_out, raw_err = b"", b""
        try:
            with open(out_path, "rb") as fh:
                raw_out = fh.read()
        except OSError:
            pass
        try:
            with open(err_path, "rb") as fh:
                raw_err = fh.read()
        except OSError:
            pass
        for p in (out_path, err_path):
            try:
                os.unlink(p)
            except OSError:
                pass

        def _decode(b):
            for enc in ("utf-16", "utf-8"):
                try:
                    return b.decode(enc)
                except UnicodeDecodeError:
                    continue
            return b.decode("utf-8", errors="replace")

        return {
            "exit_code": proc.returncode,
            "stdout": _decode(raw_out)[:MAX_OUTPUT_CHARS],
            "stderr": (_decode(raw_err) + "\n[note: elevated via UAC prompt]").strip()[:MAX_OUTPUT_CHARS],
        }

    # -- main loop ----------------------------------------------------------------
    def run(self, once: bool = False):
        out(f"ACLP Agent v{__VERSION__} running on {platform_system()}...")
        resp = self.register()
        if not self.registered:
            msg = resp.get("message", "unknown error")
            code = resp.get("status_code")
            log(f"FATAL: could not register with server: {msg}")
            out("")
            out("=" * 62)
            out("REGISTRATION FAILED — the agent cannot connect to the bridge.")
            out(f"  Server answer: HTTP {code if code else '?'}: {msg}")
            out("")
            out("Checklist:")
            out("  1) HTTP 404 usually means the plugin route was not found: check that")
            out("     the plugin 'AI Chatbot Link to PC' v1.1+ is installed AND active.")
            out("  2) The site URL in config.json must be the SITE ROOT, for example")
            out("     https://example.com — NOT the /wp-json/... REST endpoint.")
            out("     (Since v1.3.1 the agent repairs this automatically on start.)")
            out("  3) The API key must be ACTIVE (WordPress admin -> AI-PC Link -> API Keys).")
            out("  4) If the site uses HTTPS and this error is an SSL/connection error,")
            out("     the agent automatically tried HTTP as well — check the site is reachable.")
            out("  5) Full details are in aclp_agent.log (same folder).")
            out("=" * 62)
            raise SystemExit(1)

        while True:
            try:
                pending = self.api("GET", "/agent/commands/pending?limit=10")
                if pending.get("ok"):
                    self.err_streak = 0
                    cmds = pending.get("commands", [])
                    if cmds:
                        log(f"Got {len(cmds)} command(s)")
                    for cmd in cmds:
                        self.execute(cmd)
                else:
                    self.err_streak += 1
                    if pending.get("status_code") in (401, 403):
                        log("Authentication rejected — re-registering...")
                        self.registered = False
                        self.register()
            except KeyboardInterrupt:
                raise
            except Exception as exc:
                self.err_streak += 1
                log(f"Loop error: {exc}")

            if once:
                log("--once mode: exiting after one cycle.")
                break

            delay = self.cfg.get("poll_interval", 5)
            if self.err_streak > 0:
                delay = max(delay, min(60, 2 ** min(self.err_streak, 6)))
            time.sleep(delay)


# ---------------------------------------------------------------------------
# CLI helpers
# ---------------------------------------------------------------------------
def _relay_usage() -> None:
    out("=" * 62)
    out("Relay mode — for TEXT-ONLY chatbots")
    out("=" * 62)
    out("Runs ONE action on this PC and prints the JSON result.")
    out("Usage examples:")
    out("  python aclp_agent.py relay shell dir")
    out("  python aclp_agent.py relay shell git status")
    out("  python aclp_agent.py relay sysinfo")
    out("  python aclp_agent.py relay ping")
    out('  python aclp_agent.py relay file_list --json {"path": "C:/Users"}')
    out('  python aclp_agent.py relay shell --json {"command": "whoami", "timeout": 60}')
    out("<action> is any action documented in docs/AGENT-API.md.")


def _ensure_config() -> dict:
    cfg = load_config()
    if not cfg.get("site_url") or not cfg.get("api_key"):
        cfg = setup_wizard()
        save_config(cfg)
    return cfg


def main():
    args = sys.argv[1:]

    if "--version" in args or "-v" in args:
        out(f"ACLP Agent v{__VERSION__}")
        return

    # -- relay: one-shot execution for text-only chatbots ----------------------
    if args and args[0] == "relay":
        rest = args[1:]
        if not rest or rest[0] in ("-h", "--help", "help"):
            _relay_usage()
            return
        ctype = rest[0].strip().lower()
        payload = {}
        if "--json" in rest:
            i = rest.index("--json")
            if i + 1 >= len(rest):
                out("ERROR: --json needs a one-line JSON payload after it.")
                raise SystemExit(1)
            try:
                payload = json.loads(" ".join(rest[i + 1:]))
            except ValueError as exc:
                out(f"ERROR: invalid JSON: {exc}")
                raise SystemExit(1)
        elif ctype == "shell" and len(rest) > 1:
            payload = {"command": " ".join(rest[1:])}
        elif len(rest) > 1:
            out(f"ERROR: use --json for action '{ctype}' or pass only the action name.")
            raise SystemExit(1)
        raise SystemExit(Agent(_ensure_config()).relay(ctype, payload))

    # -- chat: interactive user <-> AI chat through the bridge ------------------
    if args and args[0] == "chat":
        raise SystemExit(Agent(_ensure_config()).chat_loop())

    if "-h" in args or "--help" in args:
        out(f"ACLP Agent v{__VERSION__}")
        out("Usage:")
        out("  python aclp_agent.py                 # run the agent (poll loop)")
        out("  python aclp_agent.py --setup         # re-run first-time setup")
        out("  python aclp_agent.py chat            # chat with the AI(s) using your key,")
        out("                                       #   send messages and files")
        out("  python aclp_agent.py relay <action>  # one-shot command for text-only chatbots")
        out("  python aclp_agent.py relay shell dir # example: run 'dir' and print JSON result")
        out("  python aclp_agent.py --once          # run one poll cycle and exit")
        out("  python aclp_agent.py --version")
        out("")
        out("No external dependencies are needed — plain Python 3.8+ is enough.")
        return

    cfg = load_config()
    if "--setup" in args or not cfg.get("site_url") or not cfg.get("api_key"):
        cfg = setup_wizard()
        save_config(cfg)

    Agent(cfg).run(once="--once" in args)


if __name__ == "__main__":
    _EXIT_CODE = 0
    try:
        main()
    except KeyboardInterrupt:
        out("")
        log("Stopped by user. Bye!")
        _EXIT_CODE = 130
    except SystemExit as exc:
        _EXIT_CODE = int(exc.code) if isinstance(exc.code, int) else (1 if exc.code else 0)
    except Exception:
        out("")
        out("=" * 62)
        out("UNEXPECTED FATAL ERROR — the window stays open so you can read it.")
        out("Please copy the text below when reporting the problem.")
        out("=" * 62)
        traceback.print_exc()
        try:
            with open(LOG_FILE, "a", encoding="utf-8") as fh:
                fh.write(f"[{time.strftime('%Y-%m-%d %H:%M:%S')}] FATAL\n")
                traceback.print_exc(file=fh)
        except OSError:
            pass
        _EXIT_CODE = 1
    if _EXIT_CODE != 0:
        pause_before_exit()
    sys.exit(_EXIT_CODE)

