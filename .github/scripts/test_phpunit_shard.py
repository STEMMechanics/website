import tempfile
from pathlib import Path
import unittest

from phpunit_shard import shards


class ShardTests(unittest.TestCase):
    def inventory(self, classes):
        directory = tempfile.TemporaryDirectory()
        self.addCleanup(directory.cleanup)
        path = Path(directory.name) / 'tests.xml'
        path.write_text('<testSuite xmlns="https://xml.phpunit.de/testSuite"><tests>' + ''.join(
            f'<testClass file="{file}">' + '<testMethod/>' * count + '</testClass>'
            for file, count in classes
        ) + '</tests></testSuite>')
        return path

    def test_every_file_is_assigned_once_and_multiple_classes_stay_together(self):
        inventory = self.inventory([('a.php', 5), ('b.php', 3), ('a.php', 2), ('c.php', 4), ('d.php', 2)])
        groups, totals = shards(inventory, 2)
        self.assertEqual(sorted(file for group in groups for file in group), ['a.php', 'b.php', 'c.php', 'd.php'])
        self.assertEqual(sum(totals), 16)
        self.assertEqual(totals, [9, 7])
        self.assertEqual(shards(inventory, 2), (groups, totals))

    def test_balances_test_counts_instead_of_file_counts(self):
        groups, totals = shards(self.inventory([('large.php', 6), ('a.php', 2), ('b.php', 2), ('c.php', 2)]), 2)
        self.assertEqual(totals, [6, 6])
        self.assertEqual(groups[0], ['large.php'])

    def test_empty_inventory_is_an_error(self):
        with self.assertRaises(ValueError):
            shards(self.inventory([]), 4)
