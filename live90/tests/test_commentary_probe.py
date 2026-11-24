import contextlib
import datetime as dt
import io
import json
from pathlib import Path
import sqlite3
import sys
import tempfile
import unittest
from unittest.mock import patch
from zoneinfo import ZoneInfo
sys.path.insert(0, str(Path(__file__).resolve().parents[1]/'server'))
import commentary_probe as cp

KO=dt.datetime(2026,11,25,19,tzinfo=dt.timezone.utc)
FIX=('Ligue 1','Équipe Rouge','Équipe Bleue',KO)
ROW=dict(match_id='42',match_date='2026-11-25',match_time='20:00',match_hometeam_name='Equipe Rouge',match_awayteam_name='Équipe Bleue')
def summary(comments,changes=None,fixtures=None):
    m={**ROW,'live_comments':comments,**(changes or {})}
    return cp.report(fixtures or [FIX],{'42':m},ZoneInfo('Europe/Paris'))['Ligue 1']

class Probe(unittest.TestCase):
    def test_coverage_no_wrong_author_or_warning(self):
        lines=["Foul by Jean Dupont (Équipe Rouge).","Foul by Jean Dupont (Équipe Rouge).",
               "Jean Dupont (Équipe Rouge) wins a free kick.","Coup franc pour Jean Dupont.",
               "Foul by Joe Bloggs (Other Team).","Jean Dupont (Équipe Rouge) receives a yellow card.",
               "Jean Dupont (Équipe Rouge) receives a verbal warning from the referee."]
        r=summary([dict(time='21:55',text=x) for x in lines])
        self.assertEqual((r['commentaires_horodates'],r['fautes_nominatives'],r['rappels_verbaux_explicites']),(6,1,1))
    def test_no_empty_text_or_duplicate_or_swapped_match(self):
        self.assertEqual(summary([])['sans_texte'],1)
        self.assertEqual(summary([dict(time=None,text='test')])['sans_commentaire_horodate_lisible'],1)
        self.assertEqual(summary([],dict(match_hometeam_name=ROW['match_awayteam_name']))['non_apparies'],1)
        self.assertEqual(summary([],fixtures=[FIX,FIX])['non_apparies'],2)
        self.assertEqual(cp.report([FIX],{'1':ROW,'2':{**ROW,'match_id':'2'}},ZoneInfo('Europe/Paris'))['Ligue 1']['non_apparies'],1)
        with self.assertRaises(ValueError): cp.report([FIX],{'error':429},ZoneInfo('UTC'))
    def test_dst_abstention_and_clock(self):
        for day in ('2026-03-29','2026-10-25'):
            self.assertIsNone(cp.local_kickoff(dict(match_date=day,match_time='02:30'),ZoneInfo('Europe/Paris')))
        self.assertEqual(summary([],dict(match_time='19:00'))['non_apparies'],1)
    def test_readonly_scope_and_cutoff_and_no_request_when_idle(self):
        with tempfile.TemporaryDirectory() as td:
            path=Path(td)/'test.sqlite'; db=sqlite3.connect(path)
            db.executescript("""CREATE TABLE v4_runtime(key,value);
             CREATE TABLE v4_fixtures(fixture,home,away,kickoff,league,match_id);
             CREATE TABLE v5_readings(match_id,team,market,sample_id,data);
             CREATE TABLE samples(id INTEGER PRIMARY KEY,match_id,data,received_at);""")
            db.execute('INSERT INTO v4_runtime VALUES(?,?)',('active_packball_fixtures','["m"]'))
            db.execute('INSERT INTO v4_fixtures VALUES(?,?,?,?,?,?)',('m',FIX[1],FIX[2],KO.isoformat(),FIX[0],'99'))
            for side in ('h','a'):
                db.execute('INSERT INTO v5_readings VALUES(?,?,?,?,?)',('99',side,'cards',1,'{"recommendation_allowed":true}'))
            t=KO+dt.timedelta(minutes=79);sample={'state':'LIVE','minute':79,'collected_at':t.isoformat()}
            db.execute('INSERT INTO samples VALUES(?,?,?,?)',(1,'99',json.dumps(sample),t.isoformat()))
            self.assertEqual(cp.eligible(db,t+dt.timedelta(seconds=59)),[FIX])
            self.assertEqual(cp.eligible(db,t+dt.timedelta(seconds=60)),[])
            db.commit()
            from argparse import Namespace
            with patch.dict(cp.os.environ,{'SE90_APIFOOTBALL_COM_KEY':'local-test'}), patch.object(cp,'fetch',return_value={'42':dict(ROW,live_comments=[dict(time='12:03',text='Foul by Jean Dupont (Equipe Rouge).')])}) as fetch, patch.object(cp.dt,'datetime',wraps=dt.datetime) as clock, contextlib.redirect_stdout(io.StringIO()) as stdout:
                clock.now.return_value=t
                cp.once(Namespace(db=str(path),timezone='Europe/Paris'))
                fetch.assert_called_once_with('local-test')
                self.assertEqual(json.loads(stdout.getvalue())['ligues']['Ligue 1']['matchs_avec_fautif_nomme'],1)
            db.execute("UPDATE v5_readings SET data='{}'")
            self.assertEqual(cp.eligible(db,t),[])
            db.execute("UPDATE v4_runtime SET value='[]'");db.commit();db.close()
            from argparse import Namespace
            with patch.object(cp,'fetch') as fetch, contextlib.redirect_stdout(io.StringIO()) as stdout:
                cp.once(Namespace(db=str(path),timezone=None))
                fetch.assert_not_called(); self.assertEqual(json.loads(stdout.getvalue())['appels'],0)

if __name__=='__main__': unittest.main()
