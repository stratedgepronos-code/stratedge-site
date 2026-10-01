import pathlib, sys, unittest
from unittest.mock import patch
sys.path.insert(0,str(pathlib.Path(__file__).resolve().parents[1]/'server'))
import context_worker
class NoApiTests(unittest.TestCase):
 def test_worker_disabled_even_with_key(self):
  with patch.dict('os.environ',{'OPENAI_API_KEY':'not-a-real-key','SE90_CONTEXT_ENABLED':'1'}),patch('urllib.request.urlopen',side_effect=AssertionError('Network forbidden')):
   self.assertEqual(context_worker.run('/absent'),'disabled_scenarios_only')
