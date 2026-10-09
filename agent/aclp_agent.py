#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
ACLP Agent — AI Chatbot Link to PC
==================================
Cross-platform agent (Windows / Linux) that connects the user's PC to the
"AI Chatbot Link to PC" WordPress plugin and executes commands issued by
AI chatbots: shell commands, file operations, software installation,
browser control, file transfers and more.

Version : 1.1.0
License : GPL-2.0-or-later
Repo    : https://github.com/Tobeseuss/ai-chatbot-link-to-pc

Highlights:
- Automatic HTTP fallback: if the site's HTTPS has problems (SSL errors, connection
  failures), the agent transparently retries over HTTP so everything keeps working.
- Optional privilege elevation: if the running user is not administrator/root, the user
  may provide sudo/su (Linux) credentials or accept the UAC prompt (Windows) so commands
  that need elevation can still run. NOTHING requires elevation — every action works
  normally with regular permissions; elevation is only used when explicitly requested
  (payload "elevated": true / privilege_run) or when the user enabled auto_elevate.

NOTE: console messages are in English on purpose so they render correctly on
Windows terminals with legacy codepages. Full Persian documentation lives in
the repository docs/ directory.
"""

import base64
import getpass
import json
import os
import shlex
import shutil
import socket
import subprocess
import sys
import tempfile
import time
import traceback
import uuid
import webbrowser

__VERSION__ = "1.1.0"

CONFIG_FILE = os.path.join(os.path.dirname(os.path.abspath(__file__)), "config.json")
LOG_FILE = os.path.join(os.path.dirname(os.path.abspath(__file__)), "aclp_agent.log")
MAX_OUTPUT_CHARS = 400_000
MAX_FILE_READ_BYTES = 8 * 1024 * 1024  # 8 MB per file_read call


# ---------------------------------------------------------------------------
# Bootstrap: make sure `requests` is available (auto-install on first run).
# ---------------------------------------------------------------------------
def _ensure_requests():
    try:
        import requests  # noqa: F401
        return True
    except ImportError:
        print("[setup] 'requests' library not found. Trying to install it...")
        try:
            subprocess.run(
                [sys.executable, "-m", "pip", "install", "--user", "requests"],
                check=True, capture_output=True, timeout=180,
            )
            import requests  # noqa: F401
            print("[setup] 'requests' installed successfully.")
            return True
        except Exception as exc:  # pragma: no cover
            print(f"[error] Could not install 'requests' automatically: {exc}")
            print("        Please run:  pip install requests")
            return False


if not _ensure_requests():
    sys.exit(1)

import requests  # noqa: E402


class ACLPError(Exception):
    """Raised by handlers to report a clean failure back to the chatbot."""


def log(message: str) -> None:
    line = f"[{time.strftime('%Y-%m-%d %H:%M:%S')}] {message}"
    print(line, flush=True)
    try:
        with open(LOG_FILE, "a", encoding="utf-8") as fh:
            fh.write(line + "\n")
    except OSError:
        pass


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
    cfg.setdefault("allow_http_fallback", True)   # HTTPS خراب بود -> خودکار روی HTTP ادامه بده
    cfg.setdefault("auto_elevate", False)         # تلاش خودکار برای دسترسی مدیر فقط با اجازه کاربر
    cfg.setdefault("elevation_user", "")          # اختیاری: کاربر دارای دسترسی sudo/runas
    cfg.setdefault("elevation_password", "")      # اختیاری: رمز همان کاربر (در فایل کانفیگ محلی می‌ماند)
    return cfg


def save_config(cfg: dict) -> None:
    with open(CONFIG_FILE, "w", encoding="utf-8") as fh:
        json.dump(cfg, fh, indent=2, ensure_ascii=False)
    try:
        if os.name != "nt":  # protect the API key on unix-like systems
            os.chmod(CONFIG_FILE, 0o600)
    except OSError:
        pass


def setup_wizard() -> dict:
    """Interactive first-run setup. Persian prompts are printed after an
    English hint so both render fine on any terminal."""
    print("=" * 62)
    print("  ACLP Agent setup  /  راه‌اندازی اولیه ایجنت")
    print("=" * 62)
    site = input("Site URL (آدرس سایت وردپرس، مثل https://example.com): ").strip()
    if not site:
        print("[error] Site URL is required.")
        sys.exit(1)
    if not site.startswith(("http://", "https://")):
        site = "https://" + site
    site = site.rstrip("/")

    api_key = input("API key (کلید API ساخته‌شده در پنل وردپرس): ").strip()
    if not api_key:
        print("[error] API key is required.")
        sys.exit(1)

    default_name = socket.gethostname()
    name = input(f"Client name (نام این سیستم) [{default_name}]: ").strip() or default_name

    cfg = {
        "site_url": site,
        "api_key": api_key,
        "client_name": name,
        "client_uid": str(uuid.uuid4()),
        "poll_interval": 5,
        "command_timeout": 300,
    }
    save_config(cfg)
    print(f"[ok] Config saved to {CONFIG_FILE}")

    # ------------------------------------------------------------------
    # Optional privilege elevation (اختیاری — اجباری نیست)
    # ------------------------------------------------------------------
    print("\n--- Admin/root elevation (optional) | دسترسی مدیر (اختیاری) ---")
    print("Everything works without this. Configure it only if you want the AI to be able")
    print("to run administrator/root commands when needed. برای کارکرد عادی نیازی به این بخش نیست.")
    if input("Configure elevation now? [y/N]: ").strip().lower() in ("y", "yes"):
        default_user = os.environ.get("USERNAME") or os.environ.get("USER", "")
        e_user = input(f"Elevation user (کاربر دارای sudo/runas) [{default_user}]: ").strip() or default_user
        e_pass = getpass.getpass("Elevation password (رمز — hidden, Enter to skip): ")
        auto = input("Auto-retry failed commands with elevation on permission errors? [y/N]: ").strip().lower() in ("y", "yes")
        cfg["elevation_user"] = e_user
        if e_pass:
            cfg["elevation_password"] = e_pass
        cfg["auto_elevate"] = auto
        save_config(cfg)
        print("[ok] Elevation settings saved (config.json is protected with 0600 on Linux).")
    else:
        print("[ok] Skipped. You can add elevation_user/elevation_password/auto_elevate to config.json anytime.")

    print("[ok] Starting agent now... (Ctrl+C to stop)")
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
# Privilege elevation (اختیاری) — Linux sudo/su + Windows UAC (runas)
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
        info["memory"] = "psutil not installed (pip install psutil)"
    return info, []


def platform_system() -> str:
    if os.name == "nt":
        return "Windows"
    import platform
    return platform.system()


def platform_release() -> str:
    import platform
    return platform.release()


@handler("shell")
def h_shell(agent, payload):
    command = (payload.get("command") or "").strip()
    if not command:
        raise ACLPError("payload.command is required")
    timeout = float(payload.get("timeout") or agent.cfg.get("command_timeout", 300))
    if payload.get("elevated") and not is_elevated():
        # درخواست صریح اجرای سطح بالا — فقط وقتی کاربر اعتبارها را پیکربندی کرده باشد ممکن است.
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

    resp = requests.request(method, url, headers=headers, data=body, timeout=timeout, stream=True)
    raw = resp.raw.read(max_bytes + 1, decode_content=True) or b""
    truncated = len(raw) > max_bytes
    content = raw[:max_bytes]
    try:
        text = content.decode("utf-8")
    except UnicodeDecodeError:
        text = content.decode("utf-8", errors="replace")
    return {
        "status": resp.status_code,
        "url": resp.url,
        "headers": dict(list(resp.headers.items())[:30]),
        "body": text,
        "truncated": truncated,
    }, []


@handler("screenshot")
def h_screenshot(agent, payload):
    try:
        import pyautogui  # noqa
    except ImportError:
        raise ACLPError("pyautogui is not installed. Run: pip install pyautogui pillow")
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
        # pip first when explicitly requested or nothing else fits.
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
                    # آخرین تلاش: sudo ساده (ممکن است NOPASSWD باشد)
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
# Privilege action handlers (اختیاری — مستند در docs/AGENT-API.md)
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
        site = cfg["site_url"].rstrip("/")
        if not site.startswith(("http://", "https://")):
            site = "https://" + site
        # کاندیدهای پایه: اگر HTTPS مشکل داشت (SSL/اتصال)، خودکار به HTTP سوییچ می‌کنیم.
        self.base_candidates = [site + "/wp-json/aclp/v1"]
        if site.startswith("https://"):
            self.base_candidates.append("http://" + site[len("https://"):] + "/wp-json/aclp/v1")
        elif site.startswith("http://"):
            self.base_candidates.append("https://" + site[len("http://"):] + "/wp-json/aclp/v1")
        self.base_index = 0
        self.base = self.base_candidates[0]
        self.session = requests.Session()
        self.session.headers.update({
            "X-ACLP-Key": cfg["api_key"],
            "X-ACLP-Client-UID": cfg["client_uid"],
            "User-Agent": f"ACLP-Agent/{__VERSION__} ({platform_system()})",
        })
        self.err_streak = 0
        self.registered = False

    # -- protocol fallback (پشتیبانی HTTP در صورت مشکل HTTPS) ---------------
    def _maybe_switch_protocol(self, exc) -> bool:
        """On TLS/connection failures, permanently switch to the next base URL
        candidate (https <-> http). Returns True when we switched."""
        if not self.cfg.get("allow_http_fallback", True):
            return False
        if self.base_index >= len(self.base_candidates) - 1:
            return False
        import requests.exceptions as rex
        if isinstance(exc, (rex.SSLError, rex.ConnectTimeout, rex.ConnectionError)):
            self.base_index += 1
            self.base = self.base_candidates[self.base_index]
            log(f"Connection problem ({type(exc).__name__}) — switching base URL to: {self.base}")
            return True
        return False

    # -- low level ---------------------------------------------------------
    def api(self, method: str, path: str, expect_json: bool = True, **kwargs):
        url = self.base + path
        attempt = 0
        while attempt < 4:
            try:
                resp = self.session.request(method, url, timeout=kwargs.pop("timeout", 30), **kwargs)
                if resp.status_code >= 400:
                    try:
                        msg = resp.json().get("message", resp.text[:300])
                    except (ValueError, KeyError):
                        msg = resp.text[:300]
                    log(f"API {method} {path} -> HTTP {resp.status_code}: {msg}")
                    return {"ok": False, "status_code": resp.status_code, "message": msg}
                if expect_json:
                    return resp.json()
                return resp
            except (requests.RequestException, ValueError) as exc:
                attempt += 1
                if self._maybe_switch_protocol(exc):
                    url = self.base + path
                    continue  # پروتکل عوض شد؛ این تلاش حساب نمی‌شود
                log(f"Network error on {method} {path} (attempt {attempt}/4): {exc}")
                time.sleep(2 * attempt)
        return {"ok": False, "message": "network failure after retries"}

    # -- lifecycle ---------------------------------------------------------
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
            "capabilities": sorted(HANDLERS.keys()),
        }
        resp = self.api("POST", "/agent/register", json=payload)
        if resp.get("ok"):
            self.registered = True
            self.err_streak = 0
            self.cfg["poll_interval"] = resp.get("poll_interval", self.cfg.get("poll_interval", 5))
            self.cfg["command_timeout"] = resp.get("command_timeout", self.cfg.get("command_timeout", 300))
            log(f"Registered as client #{resp.get('client_id')} (created={resp.get('created')}). "
                f"Poll interval: {self.cfg['poll_interval']}s")
        else:
            if resp.get("status_code") == 409:
                # duplicate UID under a different key → regenerate and retry once
                log("client_uid conflict — regenerating a new UID...")
                self.cfg["client_uid"] = str(uuid.uuid4())
                self.session.headers["X-ACLP-Client-UID"] = self.cfg["client_uid"]
                save_config(self.cfg)
                return self.register()
            self.registered = False
        return resp

    def download_file(self, file_id, save_path):
        resp = self.api("GET", f"/files/{int(file_id)}", expect_json=False, stream=True, timeout=600)
        if isinstance(resp, dict) and not resp.get("ok"):
            raise ACLPError(f"Download failed: {resp.get('message', 'unknown error')}")
        os.makedirs(os.path.dirname(os.path.abspath(save_path)) or ".", exist_ok=True)
        with open(save_path, "wb") as fh:
            for chunk in resp.iter_content(65536):
                if chunk:
                    fh.write(chunk)
        log(f"Downloaded file #{file_id} -> {save_path}")

    # -- command execution ---------------------------------------------------
    def execute(self, cmd: dict):
        uid = cmd.get("command_uid", "")
        ctype = cmd.get("type", "")
        payload = cmd.get("payload") or {}
        log(f"Executing {ctype} ({uid}) ...")
        self.api("POST", f"/agent/commands/{uid}/status", json={"status": "running"})

        started = time.time()
        produced_files = []
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

            self.api("POST", f"/agent/commands/{uid}/result", json={
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
        self.api("POST", f"/agent/commands/{uid}/result", json={
            "status": "failed",
            "result": None,
            "error": message,
            "duration_ms": int((time.time() - started) * 1000),
            "files": [],
        })
        log(f"Failed: {message}")

    def upload_file(self, command_uid: str, path: str) -> dict:
        try:
            with open(path, "rb") as fh:
                return self.api(
                    "POST", "/agent/files", timeout=900,
                    files={"file": (os.path.basename(path), fh)},
                    data={"command_uid": command_uid, "direction": "from_pc"},
                )
        except OSError as exc:
            return {"ok": False, "message": f"Cannot read file {path}: {exc}"}

    # -- privilege elevation (اختیاری) --------------------------------------
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
                # else: real command failure, return it as-is
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

        out = ""
        err = ""
        try:
            with open(out_path, "r", encoding="utf-16", errors="replace") as fh:
                out = fh.read()
        except OSError:
            try:
                with open(out_path, "r", encoding="utf-8", errors="replace") as fh:
                    out = fh.read()
            except OSError:
                pass
        try:
            with open(err_path, "r", encoding="utf-16", errors="replace") as fh:
                err = fh.read()
        except OSError:
            try:
                with open(err_path, "r", encoding="utf-8", errors="replace") as fh:
                    err = fh.read()
            except OSError:
                pass
        for p in (out_path, err_path):
            try:
                os.unlink(p)
            except OSError:
                pass
        return {
            "exit_code": proc.returncode,
            "stdout": out[:MAX_OUTPUT_CHARS],
            "stderr": (err + "\n[note: elevated via UAC prompt]").strip()[:MAX_OUTPUT_CHARS],
        }

    # -- main loop -----------------------------------------------------------
    def run(self, once: bool = False):
        log(f"ACLP Agent v{__VERSION__} starting on {platform_system()}...")
        resp = self.register()
        if not self.registered:
            msg = resp.get("message", "unknown error")
            log(f"FATAL: could not register with server: {msg}")
            if resp.get("status_code") in (401, 403):
                log("Check your API key in config.json (کلید API را بررسی کنید).")
            sys.exit(1)

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


def main():
    args = sys.argv[1:]
    if "--version" in args or "-v" in args:
        print(f"ACLP Agent v{__VERSION__}")
        return

    cfg = load_config()
    if "--setup" in args or not cfg.get("site_url") or not cfg.get("api_key"):
        cfg = setup_wizard()
        save_config(cfg)

    try:
        Agent(cfg).run(once="--once" in args)
    except KeyboardInterrupt:
        print("\n[ok] Agent stopped by user. خداحافظ!")


if __name__ == "__main__":
    main()
