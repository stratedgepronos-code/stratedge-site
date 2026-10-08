import copy
import unittest
import test_v4
import live_v4 as V

class ContextLengths(unittest.TestCase):
 def setUp(self):
  self.base=test_v4.LiveV4Tests();self.base.setUp()
 def tearDown(self):self.base.tearDown()
 def test_long_notes_preserved_and_boundary_accepted(self):
  for n in (2175,5000):
   m=copy.deepcopy(self.base.ctx);m['home']+=' '+str(n)
   for t in m['teams'].values():t['note']='é'*n
   result=V.import_bundle(self.base.c,'analyst',self.base.bundle('analyst',[m]),self.base.before)
   self.assertEqual(result['imported'],1)
   row=self.base.c.execute('SELECT data FROM v4_contexts WHERE fixture=?',(V.key(m),)).fetchone()
   self.assertEqual(V.dec(row['data'])['teams']['h']['note'],'é'*n)
 def test_oversize_error_is_precise_and_import_atomic(self):
  good=copy.deepcopy(self.base.ctx);bad=copy.deepcopy(good);bad['home']='Autre équipe';bad['teams']['a']['note']='x'*5001
  with self.assertRaisesRegex(ValueError,'Autre équipe.*extérieur.*5001.*5000'):
   V.import_bundle(self.base.c,'analyst',self.base.bundle('analyst',[good,bad]),self.base.before)
  self.assertEqual(self.base.c.execute('SELECT COUNT(*) FROM v4_contexts').fetchone()[0],0)
  self.assertEqual(self.base.c.execute('SELECT COUNT(*) FROM v4_imports').fetchone()[0],0)
 def test_empty_note_still_rejected(self):
  m=copy.deepcopy(self.base.ctx);m['teams']['h']['note']=' '
  with self.assertRaisesRegex(ValueError,'domicile.*note requise'):V.validate_context(m,self.base.before)

if __name__=='__main__':unittest.main()
