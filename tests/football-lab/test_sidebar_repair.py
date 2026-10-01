import importlib.util
from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[2]
spec = importlib.util.spec_from_file_location('repair', ROOT / 'ops/repair-lab-sidebar.py')
module = importlib.util.module_from_spec(spec)
spec.loader.exec_module(module)


class SidebarRepairTest(unittest.TestCase):
    def setUp(self):
        sidebar = (ROOT / 'public_html/admin/sidebar.php').read_text()
        start = sidebar.index('    <div class="nav-group ', sidebar.index('<!-- Edge Finder'))
        end = sidebar.index(module.SLATE_LINK, start)
        self.lab = sidebar[start:end]
        self.conflict = '<<<<<<< Updated upstream\n' + self.lab + module.OLD_LINK + '=======\n' + module.SLATE_LINK + '>>>>>>> Stashed changes\n'

    def test_preserves_lab_slate_and_unrelated_local_content(self):
        before, after = 'LOCAL HEADER\n', '      <span>🎯</span> Edge Finder\n    </a>\nLOCAL FOOTER\n'
        self.assertEqual(module.repair(before + self.conflict + after), before + self.lab + module.SLATE_LINK + after)

    def test_refuses_other_conflicts_without_guessing(self):
        for text in [self.conflict.replace('/slate/', '/unknown/'), self.conflict * 2, '<<<<<<< unknown\n']:
            with self.assertRaises(ValueError):
                module.repair(text)

    def test_clean_file_is_unchanged(self):
        self.assertEqual(module.repair(self.lab), self.lab)

    def test_removes_only_the_reviewed_invalid_hidden_link(self):
        broken = '    <span class="nav-item" style="display:none">" style="color:#ff2d78;">\n      <span>🎾</span> Edge Finder Tennis\n    </a>\n'
        self.assertEqual(module.repair(self.conflict + broken), self.lab + module.SLATE_LINK)


if __name__ == '__main__':
    unittest.main()
