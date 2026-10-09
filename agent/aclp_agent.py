#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
ACLP Agent — AI Chatbot Link to PC
==================================
Cross-platform agent (Windows / Linux) that connects the user's PC to the
"AI Chatbot Link to PC" WordPress plugin and executes commands issued by
AI chatbots: shell commands, file operations, software installation,
browser control, file transfers and more.

Version : 1.0.0
License : GPL-2.0-or-later
Repo    : https://github.com/Tobeseuss/ai-chatbot-link-to-pc

NOTE: console messages are in English on purpose so they render correctly on
Windows terminals with legacy codepages. Full Persian documentation lives in
the repository docs/ directory.
"""

import base64
import json
import os
import shutil
import socket
import subprocess
import sys
import tempfile
import time
import traceback
import uuid
import webbrowser

__VERSION__ = "1.0.0"

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
    """Install software using the platform package manager or pip."""
    packages = payload.get("packages") or payload.get("package")
    if not packages:
        raise ACLPError("payload.packages is required")
    if isinstance(packages, str):
        packages = [packages]
    manager = (payload.get("manager") or "auto").lower()
    timeout = float(payload.get("timeout") or 1800)

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
            if result.get("exit_code") == 0 or "not found" not in result.get("stderr", "").lower():
                return result, []
        if manager in ("auto", "choco"):
            cmd = "choco install -y " + " ".join(packages)
            return _run_shell(cmd, timeout), []
        raise ACLPError("No suitable Windows installer (winget/choco) succeeded")
    else:
        if manager in ("auto", "apt"):
            cmd = "apt-get install -y " + " ".join(packages)
            res = _run_shell(cmd, timeout)
            if res.get("exit_code") != 0:
                # retry with sudo
                res = _run_shell("sudo " + cmd, timeout)
            return res, []
        if manager == "dnf":
            return _run_shell("dnf install -y " + " ".join(packages), timeout), []
        if manager == "pacman":
            return _run_shell("pacman -S --noconfirm " + " ".join(packages), timeout), []
        raise ACLPError(f"Unknown package manager: {manager}")


# ---------------------------------------------------------------------------
# Agent
# ---------------------------------------------------------------------------
class Agent:
    def __init__(self, cfg: dict):
        self.cfg = cfg
        self.base = cfg["site_url"].rstrip("/") + "/wp-json/aclp/v1"
        self.session = requests.Session()
        self.session.headers.update({
            "X-ACLP-Key": cfg["api_key"],
            "X-ACLP-Client-UID": cfg["client_uid"],
            "User-Agent": f"ACLP-Agent/{__VERSION__} ({platform_system()})",
        })
        self.err_streak = 0
        self.registered = False

    # -- low level ---------------------------------------------------------
    def api(self, method: str, path: str, expect_json: bool = True, **kwargs):
        url = self.base + path
        for attempt in range(3):
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
                log(f"Network error on {method} {path} (attempt {attempt + 1}/3): {exc}")
                time.sleep(2 * (attempt + 1))
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
