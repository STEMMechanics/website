"""Distribute PHPUnit's own test inventory without splitting test classes/files."""
import argparse
from collections import defaultdict
from pathlib import Path
import subprocess
import tempfile
import xml.etree.ElementTree as ET


def shards(inventory, count):
    weights = defaultdict(int)
    for test_class in ET.parse(inventory).getroot().iter():
        if test_class.tag.rsplit('}', 1)[-1] == 'testClass':
            weights[test_class.attrib['file']] += len(test_class)
    if not weights:
        raise ValueError('PHPUnit returned no test files')
    groups = [[] for _ in range(count)]
    totals = [0] * count
    for path, weight in sorted(weights.items(), key=lambda item: (-item[1], item[0])):
        index = min(range(count), key=lambda i: (totals[i], i))
        groups[index].append(path)
        totals[index] += weight
    return groups, totals


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--shard', type=int, required=True, help='One-based shard number')
    parser.add_argument('--count', type=int, default=4)
    parser.add_argument('--dry-run', action='store_true')
    args = parser.parse_args()
    if not 1 <= args.shard <= args.count:
        parser.error('shard must be between 1 and count')
    command = ['php', '-d', 'memory_limit=1G', 'vendor/bin/phpunit']
    with tempfile.TemporaryDirectory() as directory:
        inventory = str(Path(directory) / 'tests.xml')
        subprocess.run(command + ['--list-tests-xml', inventory], check=True)
        groups, totals = shards(inventory, args.count)
    files = sorted(groups[args.shard - 1])
    if not files:
        raise ValueError('Empty shard; reduce the shard count')
    print(f'Shard {args.shard}/{args.count}: {len(files)} files, {totals[args.shard - 1]} tests; all shard counts: {totals}', flush=True)
    if args.dry_run:
        print('\n'.join(files))
        return 0
    Path('storage/logs').mkdir(parents=True, exist_ok=True)
    return subprocess.run(command + ['--log-junit', 'storage/logs/phpunit.xml'] + files).returncode


if __name__ == '__main__':
    raise SystemExit(main())
