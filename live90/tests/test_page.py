"""La console ne doit charger aucun fichier statique depuis assets/.

Sur le serveur de production, le bloc nginx /panel-x9k3m/ est un bloc de
préfixe sans ^~ : les règles de cache par expression régulière l'emportent
et répondent 404 sur tout .css ou .js placé sous le panel. Constaté le 24/09,
puis réintroduit par la V2 et la V3. Ce test empêche une quatrième fois.
"""
import pathlib, re, unittest
PAGE=pathlib.Path(__file__).resolve().parents[1]/'public/admin/slate/live90/index.php'
ASSETS=PAGE.parent/'assets'
class PageTests(unittest.TestCase):
    def setUp(self):self.src=PAGE.read_text(encoding='utf-8')
    def test_no_external_asset_reference(self):
        self.assertIsNone(re.search(r'(?:href|src)\s*=\s*["\']?assets/',self.src),'la page charge un fichier depuis assets/')
    def test_every_asset_is_inlined(self):
        for name in sorted(p.name for p in ASSETS.glob('*.css'))+sorted(p.name for p in ASSETS.glob('*.js')):
            self.assertIn(f"'{name}'",self.src,f'{name} n’est pas intégré à la page')
    def test_closing_tags_are_neutralised(self):
        self.assertIn("'</style'",self.src);self.assertIn("'</script'",self.src)
    def test_sidebar_included_with_live_key(self):
        # la page doit inclure la sidebar du panel, avec son propre état actif
        self.assertIn("$pageActive = 'live90'",self.src)
        self.assertIn("/sidebar.php'",self.src)
    def test_content_wrapped_in_main(self):
        # la sidebar est fixe à gauche : sans .main, elle recouvrirait la console
        self.assertRegex(self.src,r'<div class="main">\s*<\?php readfile')
    def test_sidebar_failure_does_not_blank_the_page(self):
        self.assertIn('catch (Throwable',self.src);self.assertIn('ob_end_clean()',self.src)
    def test_scenario_import_accepts_several_files(self):
        # plusieurs dossiers JSON le même jour : un par championnat, par exemple
        shell=(ASSETS/'shell.html').read_text(encoding='utf-8')
        self.assertRegex(shell,r'<input[^>]*id="scenario-file"[^>]*\bmultiple\b')
        ctx=(ASSETS/'context.js').read_text(encoding='utf-8')
        self.assertIn('[...(e.target.files||[])]',ctx)
        self.assertNotIn("e.target.files[0]",ctx.split("scenario-file')")[1].split("save-scenarios")[0])
if __name__=='__main__':unittest.main()
