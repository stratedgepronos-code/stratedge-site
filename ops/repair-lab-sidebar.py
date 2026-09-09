#!/usr/bin/env python3
"""Repair only the reviewed September Lab/slate autostash conflict, preserving local edits."""
import argparse
import datetime
import os
from pathlib import Path
import re
import shutil
import subprocess
import tempfile

MARKER = re.compile(r'^(?:<{7}|={7}|>{7})(?: |$)', re.M)
CONFLICT = re.compile(r'^<<<<<<< Updated upstream\n(.*?)^=======\n(.*?)^>>>>>>> Stashed changes\n', re.M | re.S)
OLD_LINK = '    <a href="/panel-x9k3m/edge-finder/" class="nav-item <?= ($pageActive===\'edge-finder\') ?\'active\':\'\' ?>" style="color:#ff2d78;">\n'
SLATE_LINK = '    <a href="/panel-x9k3m/slate/" class="nav-item <?= ($pageActive===\'slate\') ?\'active\':\'\' ?>" style="color:#ff2d78;">\n'


def repair(text):
    if not MARKER.search(text):
        return text
    conflicts = list(CONFLICT.finditer(text))
    if len(conflicts) != 1:
        raise ValueError('Unreviewed sidebar conflict; no file changed')
    match = conflicts[0]
    ours, theirs = match.groups()
    # Strictly recognize the already inspected insertion against a local route change.
    if (theirs != SLATE_LINK or not ours.endswith(OLD_LINK)
            or ours.count('data-group="football-lab"') != 1
            or ours.count('/panel-x9k3m/football-lab/') != 3
            or ours.count('<a ') != 4):
        raise ValueError('Unreviewed sidebar conflict; no file changed')
    text = text[:match.start()] + ours[:-len(OLD_LINK)] + theirs + text[match.end():]
    if MARKER.search(text):
        raise ValueError('Remaining sidebar markers; no file changed')
    # Remove the exact malformed hidden legacy Tennis link seen in the same VPS diff.
    text = text.replace('    <span class="nav-item" style="display:none">" style="color:#ff2d78;">\n      <span>🎾</span> Edge Finder Tennis\n    </a>\n', '')
    return text


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--root', required=True)
    args = parser.parse_args()
    root = Path(args.root).resolve()
    relative = 'public_html/admin/sidebar.php'
    sidebar = root / relative
    original = sidebar.read_text()
    fixed = repair(original)
    if fixed == original:
        print('SIDEBAR_CLEAN')
        return
    backup_parent = Path.home() / 'stratedge-backups'
    backup_parent.mkdir(mode=0o700, exist_ok=True)
    backup = Path(tempfile.mkdtemp(prefix='lab-sidebar-' + datetime.datetime.now().strftime('%Y%m%d-'), dir=backup_parent))
    shutil.copy2(sidebar, backup / 'sidebar.php')
    for stage in (1, 2, 3):
        data = subprocess.run(['git', '-C', str(root), 'show', f':{stage}:{relative}'], capture_output=True)
        if data.returncode == 0:
            (backup / f'stage-{stage}.php').write_bytes(data.stdout)
    fd, temporary = tempfile.mkstemp(prefix='.lab-menu-', suffix='.php', dir=sidebar.parent)
    try:
        with os.fdopen(fd, 'w') as stream:
            stream.write(fixed)
        shutil.copymode(sidebar, temporary)
        subprocess.run(['php', '-l', temporary], check=True)
        os.replace(temporary, sidebar)
        # Clear only this resolved index entry; preserve the local delta for autostash.
        subprocess.run(['git', '-C', str(root), 'add', '--', relative], check=True)
        subprocess.run(['git', '-C', str(root), 'reset', '-q', 'HEAD', '--', relative], check=True)
    finally:
        if os.path.exists(temporary):
            os.unlink(temporary)
    print(f'SIDEBAR_REPAIRED backup={backup}')


if __name__ == '__main__':
    main()
