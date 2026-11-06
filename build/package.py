#!/usr/bin/env python3
"""Build an installable ZIP from an exact committed module revision."""
import argparse
import hashlib
import io
from pathlib import Path, PurePosixPath
import re
import subprocess
import tarfile
import zipfile

ROOT = Path(__file__).resolve().parent.parent
MODULE = 'lmdbadvancedproject'


def git(*args):
    return subprocess.check_output(['git', *args], cwd=ROOT)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--ref', default='HEAD')
    parser.add_argument('--output-dir', default=str(ROOT / 'test/.cache/releases'))
    args = parser.parse_args()
    commit = git('rev-parse', '--verify', args.ref + '^{commit}').decode().strip()
    descriptor = git('show', commit + ':core/modules/modLmdbAdvancedProject.class.php').decode()
    version = re.search(r"\$this->version = '([0-9]+\.[0-9]+\.[0-9]+)';", descriptor)
    if not version:
        parser.error('The descriptor must declare a numeric X.Y.Z version.')
    version = version.group(1)
    output = Path(args.output_dir).resolve()
    output.mkdir(parents=True, exist_ok=True)
    archive = output / f'module_{MODULE}-{version}.zip'
    # Only committed source is eligible; development fixtures and tools are excluded.
    with tarfile.open(fileobj=io.BytesIO(git('archive', '--format=tar', commit))) as source:
        with zipfile.ZipFile(archive, 'w', compression=zipfile.ZIP_DEFLATED, compresslevel=9) as package:
            for member in sorted(source.getmembers(), key=lambda item: item.name):
                path = PurePosixPath(member.name)
                if path.parts[0] in ('test', 'build', 'tmp') or member.name in ('.gitignore', 'phpstan.neon'):
                    continue
                if not member.isfile():
                    if not member.isdir():
                        raise RuntimeError('Unsupported archive entry: ' + member.name)
                    continue
                if path.is_absolute() or '..' in path.parts or any(part.startswith('.') for part in path.parts):
                    raise RuntimeError('Unexpected archive path: ' + member.name)
                stream = source.extractfile(member)
                if stream is None:
                    raise RuntimeError('Cannot read ' + member.name)
                info = zipfile.ZipInfo(f'{MODULE}/{member.name}', (1980, 1, 1, 0, 0, 0))
                info.create_system = 3
                info.external_attr = 0o100644 << 16
                info.compress_type = zipfile.ZIP_DEFLATED
                package.writestr(info, stream.read(), compresslevel=9)
    digest = hashlib.sha256(archive.read_bytes()).hexdigest()
    archive.with_suffix('.zip.sha256').write_text(f'{digest}  {archive.name}\n', encoding='ascii', newline='\n')
    print(f'Commit: {commit}\nPackage: {archive}\nSHA-256: {digest}')


if __name__ == '__main__':
    main()
