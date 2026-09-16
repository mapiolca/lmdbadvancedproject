#!/usr/bin/env python3
"""Use an existing PHPStan and native core without installing a module dependency."""
import argparse
import json
import os
from pathlib import Path
import subprocess

root = Path(__file__).resolve().parent.parent
parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('--core', default=os.environ.get('DOLIBARR_ROOT', str(root.parent / 'dolibarr' / 'htdocs')))
parser.add_argument('--phar', default=str(root / 'test/.cache/phpstan.phar'))
parser.add_argument('--format', default='table')
args = parser.parse_args()
core = Path(args.core).resolve()
phar = Path(args.phar).resolve()
if not (core / 'core/class/commonobject.class.php').is_file() or not phar.is_file():
    parser.error('Provide --core pointing to htdocs and --phar pointing to an existing PHPStan PHAR.')
cache = root / 'test/.cache'
cache.mkdir(exist_ok=True)
quote = json.dumps
config = cache / 'phpstan-run.neon'
config.write_text('includes:\n    - ' + quote(str(root / 'phpstan.neon')) + '\nparameters:\n    tmpDir: ' + quote(str(cache / 'phpstan')) + '\n    scanDirectories:\n        - ' + quote(str(core)) + '\n    excludePaths:\n        analyseAndScan:\n            - ' + quote(str(core / 'install')) + '\n            - ' + quote(str(core / 'custom')) + '\n')
env = dict(os.environ, DOLIBARR_ROOT=str(core))
raise SystemExit(subprocess.call(['php', str(phar), 'analyse', '-c', str(config), '--no-progress', '--memory-limit=1G', '--error-format=' + args.format], cwd=root, env=env))
