#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
ACLP release builder — ساخت بسته‌های ZIP نسخه‌دار پلاگین و ایجنت (اصل اساسی ۳).

Usage:
    python3 tools/build_release.py [--out DIR]

- نسخه از هدر افزونه (Version:) خوانده می‌شود و با __VERSION__ ایجنت مقایسه می‌شود.
- خروجی: dist/ai-chatbot-link-to-pc-vX.Y.Z.zip و dist/aclp-agent-vX.Y.Z.zip + sha256
"""
import hashlib
import re
import shutil
import sys
import tempfile
import zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
PLUGIN_DIR = ROOT / "ai-chatbot-link-to-pc"
AGENT_DIR = ROOT / "agent"


def read_version() -> str:
    main = PLUGIN_DIR / "ai-chatbot-link-to-pc.php"
    m = re.search(r"^\s*\*\s*Version:\s*([0-9]+\.[0-9]+\.[0-9]+)", main.read_text(encoding="utf-8"), re.M)
    if not m:
        sys.exit("[error] Version not found in plugin header")
    plugin_ver = m.group(1)

    agent_src = (AGENT_DIR / "aclp_agent.py").read_text(encoding="utf-8")
    m2 = re.search(r'__VERSION__\s*=\s*"([0-9]+\.[0-9]+\.[0-9]+)"', agent_src)
    agent_ver = m2.group(1) if m2 else "?"

    if plugin_ver != agent_ver:
        sys.exit(f"[error] Version mismatch: plugin={plugin_ver} agent={agent_ver} — هماهنگشان کنید")
    return plugin_ver


def zip_dir(src_dir: Path, dest_zip: Path, top_folder: str, excludes=("__pycache__", ".installed", "config.json", "aclp_agent.log")):
    with zipfile.ZipFile(dest_zip, "w", zipfile.ZIP_DEFLATED) as zf:
        for path in sorted(src_dir.rglob("*")):
            rel = path.relative_to(src_dir)
            if any(part in excludes for part in rel.parts):
                continue
            if path.is_file():
                zf.write(path, str(Path(top_folder) / rel))
    return dest_zip


def sha256(path: Path) -> str:
    h = hashlib.sha256()
    with open(path, "rb") as fh:
        for chunk in iter(lambda: fh.read(65536), b""):
            h.update(chunk)
    return h.hexdigest()


def main():
    out_dir = Path(sys.argv[sys.argv.index("--out") + 1]) if "--out" in sys.argv else ROOT / "dist"
    out_dir.mkdir(parents=True, exist_ok=True)

    version = read_version()
    print(f"[ok] Release version: v{version} (plugin & agent in sync)")

    plugin_zip = out_dir / f"ai-chatbot-link-to-pc-v{version}.zip"
    agent_zip = out_dir / f"aclp-agent-v{version}.zip"

    zip_dir(PLUGIN_DIR, plugin_zip, top_folder=PLUGIN_DIR.name)
    zip_dir(AGENT_DIR, agent_zip, top_folder="aclp-agent")

    for z in (plugin_zip, agent_zip):
        print(f"[ok] {z.name}  ({z.stat().st_size / 1024:.1f} KB)  sha256={sha256(z)}")

    print(f"\n[done] Artifacts ready in {out_dir}")
    print(f"       Release tag: v{version}")


if __name__ == "__main__":
    main()
