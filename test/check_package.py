#!/usr/bin/env python3
"""Check release contents and execute each shipped bootstrap in isolated layouts."""
import argparse
import hashlib
import os
from pathlib import Path, PurePosixPath
import re
import shutil
import subprocess
import uuid
import zipfile

ROOT = Path(__file__).resolve().parent.parent
MODULE = 'lmdbadvancedproject'


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('package', type=Path)
    args = parser.parse_args()
    package = args.package.resolve()
    match = re.fullmatch(r'module_lmdbadvancedproject-([0-9]+\.[0-9]+\.[0-9]+)\.zip', package.name)
    if not match:
        parser.error('Unexpected package filename.')
    version = match.group(1)
    cache = ROOT / 'test/.cache'
    cache.mkdir(exist_ok=True)
    php = os.environ.get('PHP_BINARY', 'php')
    assertions = 0
    with zipfile.ZipFile(package) as archive:
        names = archive.namelist()
        if len(names) != len(set(names)):
            raise RuntimeError('Duplicate archive entries.')
        for name in names:
            path = PurePosixPath(name)
            if path.parts[0] != MODULE or path.is_absolute() or '..' in path.parts:
                raise RuntimeError('Unexpected package path: ' + name)
            if path.parts[1] in ('test', 'build', 'tmp') or any(part.startswith('.') for part in path.parts):
                raise RuntimeError('Development content in package: ' + name)
        descriptor = archive.read(f'{MODULE}/core/modules/modLmdbAdvancedProject.class.php').decode()
        readme = archive.read(f'{MODULE}/README.md').decode()
        changelog = archive.read(f'{MODULE}/ChangeLog.md').decode()
        if f"$this->version = '{version}';" not in descriptor or f'Current version: **{version}**' not in readme or f'## {version} — ' not in changelog:
            raise RuntimeError('Version metadata mismatch.')
        archive.getinfo(f'{MODULE}/modulebuilder.txt')
        entrypoints = [name for name in names if name.endswith('.php') and re.search(rb'(?:main|master)\.inc\.php', archive.read(name))]
        for layout in ('htdocs', 'custom', 'absent'):
            # mkdir's default permissions also allow the sandbox identity on Windows.
            fixture = cache / ('package-bootstrap-' + uuid.uuid4().hex)
            fixture.mkdir()
            try:
                htdocs = fixture / 'htdocs'
                destination = htdocs / 'custom' if layout == 'custom' else htdocs
                archive.extractall(destination)
                if layout != 'absent':
                    (htdocs / 'main.inc.php').write_text("<?php echo 'NATIVE_ENVIRONMENT_FOUND'; exit(0);\n", encoding='utf-8')
                for name in entrypoints:
                    page = destination / name
                    # Remove detected web-root shortcuts to exercise the relative paths.
                    launcher = "$_SERVER['SCRIPT_FILENAME']=''; unset($_SERVER['CONTEXT_DOCUMENT_ROOT']); require $argv[1];"
                    result = subprocess.run([php, '-n', '-r', launcher, str(page)], cwd=page.parent, capture_output=True, text=True)
                    expected = 'Include of main fails' if layout == 'absent' else 'NATIVE_ENVIRONMENT_FOUND'
                    if result.returncode != 0 or result.stdout.strip() != expected or result.stderr:
                        raise RuntimeError(f'{layout}: {name}: {result.stdout} {result.stderr}')
                    assertions += 1
            finally:
                if fixture.is_symlink() or fixture.resolve().parent != cache.resolve():
                    raise RuntimeError('Refusing to remove a fixture outside the test cache.')
                shutil.rmtree(fixture)
    expected_checksum = package.with_suffix('.zip.sha256').read_text(encoding='ascii').split()[0]
    if expected_checksum != hashlib.sha256(package.read_bytes()).hexdigest():
        raise RuntimeError('Checksum mismatch.')
    print(f'Package contents, versions and checksum passed; {assertions} bootstrap checks ({len(entrypoints)} entry points, htdocs/custom/missing environment).')


if __name__ == '__main__':
    main()
