"""Stage a reviewable GitHub PR diff from the WordPress FTP plugins directory."""

from __future__ import annotations

import filecmp
import json
import os
import posixpath
import shutil
import socket
import tempfile
import time
from ftplib import FTP, error_perm
from pathlib import Path


def _safe_name(name: str) -> bool:
    return bool(name) and name not in {".", ".."} and not any(
        char in name for char in ("/", "\\", "\0", "\r", "\n")
    )


def _entries(ftp: FTP) -> list[tuple[str, str]]:
    try:
        return [(name, facts.get("type", "")) for name, facts in ftp.mlsd()]
    except (AttributeError, error_perm):
        try:
            return [(posixpath.basename(name.rstrip("/")), "") for name in ftp.nlst()]
        except error_perm as exc:
            if str(exc).startswith("550"):
                return []
            raise


def _is_directory(ftp: FTP, name: str) -> bool:
    current = ftp.pwd()
    try:
        ftp.cwd(name)
    except error_perm:
        return False
    ftp.cwd(current)
    return True


def _skip(name: str, is_directory: bool) -> bool:
    if name in {".git", ".DS_Store"}:
        return True
    if is_directory:
        return False
    return name == ".env" or name.startswith(".env.") or name.endswith((".key", ".pem"))


def download_tree(ftp: FTP, destination: Path, depth: int = 0) -> int:
    """Download a complete snapshot before changing any repository files."""
    if depth > 20:
        raise RuntimeError("Plugin directory nesting exceeds 20 levels")

    count = 0
    for name, kind in _entries(ftp):
        if name in {".", ".."} or kind in {"cdir", "pdir"}:
            continue
        if not _safe_name(name):
            raise RuntimeError("Unsafe FTP file name")
        if kind.startswith("OS.unix=slink"):
            continue

        is_directory = kind == "dir" or (kind != "file" and _is_directory(ftp, name))
        if _skip(name, is_directory):
            continue

        target = destination / name
        if is_directory:
            target.mkdir()
            current = ftp.pwd()
            ftp.cwd(name)
            try:
                count += download_tree(ftp, target, depth + 1)
            finally:
                ftp.cwd(current)
        else:
            with target.open("wb") as output:
                ftp.retrbinary(f"RETR {name}", output.write)
            count += 1
    return count


def load_manifest(path: Path) -> set[str] | None:
    """A missing manifest means first sync: no repository files may be deleted."""
    if not path.exists():
        return None
    data = json.loads(path.read_text(encoding="utf-8"))
    if not isinstance(data, dict) or not isinstance(data.get("files"), list):
        raise RuntimeError("Invalid plugin sync manifest")
    files = data["files"]
    if any(not isinstance(item, str) or not all(_safe_name(part) for part in item.split("/")) for item in files):
        raise RuntimeError("Unsafe path in plugin sync manifest")
    return set(files)


def apply_snapshot(source: Path, destination: Path, previous: set[str] | None) -> tuple[int, int, set[str]]:
    """Copy remote changes and remove only files recorded by a prior sync."""
    current = {path.relative_to(source).as_posix() for path in source.rglob("*") if path.is_file()}
    if not current:
        raise RuntimeError("FTP returned an empty plugins directory; refusing to sync deletions")

    changed = 0
    for relative in sorted(current):
        source_file = source / relative
        target = destination / relative
        if not target.resolve().is_relative_to(destination.resolve()) or target.is_symlink():
            raise RuntimeError(f"Unsafe plugin path: {relative}")
        if target.exists() and filecmp.cmp(source_file, target, shallow=False):
            continue
        target.parent.mkdir(parents=True, exist_ok=True)
        shutil.copyfile(source_file, target)
        changed += 1

    removed = 0
    for relative in sorted((previous or set()) - current):
        target = destination / relative
        if not target.resolve().is_relative_to(destination.resolve()) or target.is_symlink():
            raise RuntimeError(f"Unsafe plugin path: {relative}")
        if target.is_file():
            target.unlink()
            removed += 1
            parent = target.parent
            while parent != destination and not any(parent.iterdir()):
                parent.rmdir()
                parent = parent.parent

    return changed, removed, current


def ftp_settings(environ: dict[str, str]) -> dict[str, str]:
    """Match the whitespace trimming used by the existing FTP deploy action."""
    required = ("FTP_SERVER", "FTP_USERNAME", "FTP_PASSWORD", "FTP_SERVER_DIR")
    settings = {name: environ.get(name, "").strip() for name in required}
    missing = [name for name, value in settings.items() if not value]
    if missing:
        raise RuntimeError("Missing FTP settings: " + ", ".join(missing))
    return settings


def connect_ftp(ftp: FTP, host: str) -> None:
    """Retry temporary DNS failures without retrying invalid hostnames."""
    for attempt in range(3):
        try:
            ftp.connect(host, 21)
            return
        except socket.gaierror as exc:
            if exc.errno != socket.EAI_AGAIN or attempt == 2:
                raise
            time.sleep(5 * (attempt + 1))


def main() -> None:
    settings = ftp_settings(os.environ)

    repo = Path(__file__).resolve().parents[2]
    destination = repo / "wp-content" / "plugins"
    manifest_path = repo / ".github" / "plugin-sync-state.json"
    remote = posixpath.join(settings["FTP_SERVER_DIR"].rstrip("/"), "plugins")

    with tempfile.TemporaryDirectory(prefix="velora-plugins-") as staging_path:
        staging = Path(staging_path)
        with FTP(timeout=60) as ftp:
            connect_ftp(ftp, settings["FTP_SERVER"])
            ftp.login(settings["FTP_USERNAME"], settings["FTP_PASSWORD"])
            ftp.cwd(remote)
            downloaded = download_tree(ftp, staging)
        changed, removed, current = apply_snapshot(staging, destination, load_manifest(manifest_path))

    manifest_path.write_text(json.dumps({"files": sorted(current)}, indent=2) + "\n", encoding="utf-8")
    print(f"Downloaded {downloaded} plugin files; {changed} added/updated, {removed} removed in PR diff.")


if __name__ == "__main__":
    main()
