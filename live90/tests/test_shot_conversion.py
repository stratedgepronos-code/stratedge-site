"""Historical conversion is auditable context, never a due-goal counter."""
import copy, datetime as dt, unittest
import test_decision_v5 as fixtures
import live_v4 as V

class ShotConversionTests(unittest.TestCase):
 setUp=fixtures.DeskTests.setUp
 tearDown=fixtures.DeskTests.tearDown
 bundle=fixtures.DeskTests.bundle
 add=fixtures.DeskTests.add
 prepare=fixtures.DeskTests.prepare
 reading=fixtures.DeskTests.reading

 def test_same_profile_ft_ratios_and_live_counts(self):
  self.profile['prematch'].update(shots_h=14.25,sot_h=4,gf_h=1.75)
  self.r['stats']['shots']['h']=7;self.r['stats']['sot']['h']=3
  self.profile['packball']={'sample':{'venue_scope':'home_away','settings_status':'confirmed'}}
  c=V.shot_conversion_context(self.r,self.profile,'h')
  self.assertAlmostEqual(c['shots_per_goal'],8.142857,places=5)
  self.assertAlmostEqual(c['sot_per_goal'],4/1.75)
  self.assertEqual((c['live_shots'],c['live_sot'],c['live_goals']),(7,3,0))
  self.assertEqual(c['period'],'FT');self.assertEqual(c['usage'],'context_only')
  self.assertIn('domicile',c['lines'][0]);self.assertIn('aucun but',c['caution'])
  self.r['score']['h']=1
  after=V.shot_conversion_context(self.r,self.profile,'h')
  self.assertEqual(after['live_shots'],7) # No fictional subtraction/reset.
  self.assertEqual(after['live_goals'],1)

 def test_unknown_scope_not_inferred_from_home_side(self):
  c=V.shot_conversion_context(self.r,self.profile,'h')
  self.assertEqual(c['venue_scope'],'unknown');self.assertIn('localisation non documentée',c['lines'][0])
  self.profile['packball']={'sample':{'venue_scope':'all'}}
  self.assertIn('toutes localisations',V.shot_conversion_context(self.r,self.profile,'h')['lines'][0])
  self.assertAlmostEqual(V.shot_conversion_context(self.r,self.profile,'a')['shots_per_goal'],10/1.4)

 def test_unusable_zero_missing_and_incoherent_values(self):
  for key,value in [('gf_h',0),('gf_h',None),('gf_h',float('nan')),('n_h',0),('n_h',None)]:
   p=copy.deepcopy(self.profile);p['prematch'][key]=value
   c=V.shot_conversion_context(self.r,p,'h');self.assertEqual(c['status'],'missing');self.assertIsNone(c['shots_per_goal'])
  p=dict(self.profile,usable=False)
  self.assertEqual(V.shot_conversion_context(self.r,p,'h')['status'],'missing')
  p=copy.deepcopy(self.profile);p['prematch']['sot_h']=20
  self.assertIsNone(V.shot_conversion_context(self.r,p,'h')['sot_per_goal'])
  self.r['stats']['sot']['h']=99
  self.assertNotIn('live_line',V.shot_conversion_context(self.r,self.profile,'h'))

 def test_active_telegram_and_snapshot_use_ft_context_even_in_first_half(self):
  self.prepare();d=self.reading();self.assertEqual(d['status'],'active')
  self.assertEqual(d['period'],'HT');self.assertEqual(d['conversion']['period'],'FT')
  self.assertAlmostEqual(d['metrics']['shots_per_goal'],12/1.5)
  self.assertNotEqual(d['metrics']['shots_per_goal'],12/.8)
  r=V.sample_data(V.latest(self.c)[0]);message=V.radar_message(r,d,{},7)
  self.assertIn('Efficacité historique',message);self.assertIn('tirs/but',message);self.assertIn('cadrés/but',message)
  self.assertIn('cumul FT',message);self.assertNotIn('but attendu',message)
  snap=V.learning_snapshot(r,d,self.profile,self.ctx,self.now)
  self.assertEqual(snap['reading']['conversion'],d['conversion'])
  old=copy.deepcopy(d);old.pop('conversion')
  self.assertNotIn('Efficacité historique',V.radar_message(r,old,{},7))

 def test_ratio_neither_creates_nor_blocks_alerts_and_is_archived_without_alert(self):
  self.prepare();self.assertEqual(self.reading()['status'],'active')
  for gf in (0.01,50):
   p=copy.deepcopy(self.profile);p['prematch']['gf_h']=gf
   ctx=dict(self.ctx,usable=True);r=V.sample_data(V.latest(self.c)[0])
   d=V.desk_reading(r,p,ctx,'h','goals',self.now,[])
   self.assertEqual(d['status'],'candidate') # The ratio is not an extra gate.
  self.c.execute('DELETE FROM samples');self.c.commit()
  self.r['stats']['shots']['h']=20;self.r['stats']['sot']['h']=10
  self.r['ind10']['shots10']['h']=1;self.r['ind10']['sot10']['h']=0
  self.r['ind5']['shots5']['h']=1;self.r['ind5']['sot5']['h']=0
  self.add(self.r,self.now-dt.timedelta(seconds=30));self.add(self.r,self.now)
  d=self.reading();self.assertEqual(d['status'],'neutral')
  r=V.sample_data(V.latest(self.c)[0]);V.record_shadow(self.c,r,d,self.now)
  row=self.c.execute("SELECT data FROM v5_shadow_readings WHERE team='h' AND market='goals'").fetchone()
  self.assertEqual(V.dec(row['data'])['conversion']['live_shots'],20)

if __name__=='__main__':unittest.main()
