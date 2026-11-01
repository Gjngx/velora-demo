"""Offline checks for add, update, and delete proposals."""

import sys
import tempfile
import unittest
from ftplib import error_perm
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from sync_plugins import apply_snapshot, download_tree, load_manifest  # noqa: E402


class FakeFTP:
    def __init__(self, files):
        self.files = files
        self.path = []

    def pwd(self):
        return "/" + "/".join(self.path)

    def cwd(self, path):
        parts = [part for part in path.split("/") if part] if path.startswith("/") else self.path + [path]
        node = self.files
        try:
            for part in parts:
                node = node[part]
        except (KeyError, TypeError):
            raise error_perm("550 Not a directory")
        if not isinstance(node, dict):
            raise error_perm("550 Not a directory")
        self.path = parts

    def _current(self):
        node = self.files
        for part in self.path:
            node = node[part]
        return node

    def mlsd(self):
        return [(name, {"type": "dir" if isinstance(value, dict) else "file"})
                for name, value in self._current().items()]

    def retrbinary(self, command, callback):
        callback(self._current()[command.removeprefix("RETR ")])


class PluginSyncTests(unittest.TestCase):
    def test_adds_plugins_without_deleting_untracked_local_files_on_first_sync(self):
        ftp = FakeFTP({"index.php": b"index", "new-plugin": {"main.php": b"new", ".env": b"secret"}})
        with tempfile.TemporaryDirectory() as temp:
            staging, repo = Path(temp) / "staging", Path(temp) / "plugins"
            staging.mkdir()
            repo.mkdir()
            (repo / "local.php").write_bytes(b"keep")
            self.assertEqual(download_tree(ftp, staging), 2)
            changed, removed, current = apply_snapshot(staging, repo, None)
            self.assertEqual((changed, removed), (2, 0))
            self.assertEqual(current, {"index.php", "new-plugin/main.php"})
            self.assertTrue((repo / "local.php").exists())
            self.assertFalse((repo / "new-plugin" / ".env").exists())

    def test_deletes_only_files_in_previous_manifest(self):
        with tempfile.TemporaryDirectory() as temp:
            staging, repo = Path(temp) / "staging", Path(temp) / "plugins"
            staging.mkdir()
            (repo / "old-plugin").mkdir(parents=True)
            (staging / "index.php").write_bytes(b"index")
            (repo / "index.php").write_bytes(b"index")
            (repo / "old-plugin" / "main.php").write_bytes(b"old")
            (repo / "local.php").write_bytes(b"keep")
            changed, removed, current = apply_snapshot(
                staging, repo, {"index.php", "old-plugin/main.php"}
            )
            self.assertEqual((changed, removed), (0, 1))
            self.assertEqual(current, {"index.php"})
            self.assertFalse((repo / "old-plugin").exists())
            self.assertTrue((repo / "local.php").exists())

    def test_empty_ftp_snapshot_cannot_delete_plugins(self):
        with tempfile.TemporaryDirectory() as temp:
            staging, repo = Path(temp) / "staging", Path(temp) / "plugins"
            staging.mkdir()
            repo.mkdir()
            (repo / "plugin.php").write_bytes(b"keep")
            with self.assertRaisesRegex(RuntimeError, "empty plugins directory"):
                apply_snapshot(staging, repo, {"plugin.php"})
            self.assertTrue((repo / "plugin.php").exists())

    def test_rejects_unsafe_manifest_paths(self):
        with tempfile.TemporaryDirectory() as temp:
            manifest = Path(temp) / "state.json"
            manifest.write_text('{"files": ["../outside.php"]}')
            with self.assertRaisesRegex(RuntimeError, "Unsafe path"):
                load_manifest(manifest)


if __name__ == "__main__":
    unittest.main()
