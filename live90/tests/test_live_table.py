"""Display projection preserves labelled values without exposing raw collector payloads."""
import unittest
import copy
import test_decision_v5 as F
import live_v4 as V

class LiveTableTests(unittest.TestCase):
 setUp=F.DeskTests.setUp
 tearDown=F.DeskTests.tearDown
 prepare=F.DeskTests.prepare
 add=F.DeskTests.add
 bundle=F.DeskTests.bundle
 def test_board_preserves_display_order_and_unknowns_only(self):
  self.r['raw_cells']=[{'title':'Fautes','value':{'h':9,'a':0},'html':'private-html','text':'9–0','column':1}, {'title':'Tirs cadrés 10 dernières minutes','value':{'h':None,'a':2}}, {'title':None,'value':{'h':88,'a':88}}]
  self.prepare()
  r=V.board(self.c,self.now)['matches'][0]
  self.assertEqual(r['live_columns'],[{'title':'Fautes','value':{'h':9,'a':0}},{'title':'Tirs cadrés 10 dernières minutes','value':{'h':None,'a':2}}])
  self.assertNotIn('raw_cells',r)
  self.assertNotIn('private-html',V.enc(r))
 def test_table_membership_uses_latest_collector_cycle_without_deleting_imports(self):
  self.prepare()
  self.c.execute('INSERT INTO cycles(id,received_at,payload) VALUES(?,?,?)',('table-test',V.iso(self.now),V.enc({'rows':[]})))
  self.c.commit()
  rows=V.board(self.c,self.now)['matches']
  self.assertEqual(len(rows),1)
  self.assertFalse(rows[0]['on_packball_page'])
 def test_new_packball_fixture_is_displayed_without_analysis_or_alert(self):
  self.prepare()
  other=copy.deepcopy(self.r);other.update(packball_id='456',home='Nouveau match')
  self.c.execute('INSERT INTO samples(cycle_id,match_id,received_at,data) VALUES(?,?,?,?)',('new','456',V.iso(self.now),V.enc(other)))
  self.c.execute('INSERT INTO cycles(id,received_at,payload) VALUES(?,?,?)',('new',V.iso(self.now),V.enc({'rows':[other]})))
  self.c.commit()
  rows=V.board(self.c,self.now)['matches'];new=next(r for r in rows if r['packball_id']=='456')
  self.assertTrue(new['on_packball_page']);self.assertFalse(new['in_analysis_program'])
  self.assertIsNone(new['profile']);self.assertEqual(new['advice'],[])
  self.assertEqual(len(rows),2)
 def test_projection_is_bounded_and_malformed_values_remain_unknown(self):
  self.assertEqual(V.live_table_columns({'raw_cells':'not a list'}),[])
  rows=[{'title':'Tirs','value':{'h':'9','a':False}}]*45
  result=V.live_table_columns({'raw_cells':rows})
  self.assertEqual(len(result),40)
  self.assertEqual(result[0]['value'],{'h':None,'a':None})

if __name__=='__main__':unittest.main()
