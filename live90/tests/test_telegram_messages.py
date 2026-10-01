"""Telegram presentation: precise markets, short copy and safe HTML, no live sends."""
import datetime as dt
import io
import json
import unittest
from html.parser import HTMLParser
from unittest.mock import patch
import live_v4 as V


class TelegramMessages(unittest.TestCase):
    def setUp(self):
        self.now=dt.datetime(2026,9,30,20,tzinfo=dt.timezone.utc)
        self.signal={'id':42,'market':'goal_ft','team':'h','line':1.5,'odds':None}
        self.saved={'home':'A & B <FC>','away':'Équipe B','minute':63,'score':{'h':1,'a':1},
                    'decision':{'metrics':{'shots10':6,'sot10':3,'activity_ratio':1.456},
                                'context_notes':['Long internal detail '*100]},
                    'stake_lookup':{'reason':'Budget quotidien atteint'}}

    def test_short_alert_preserves_exact_target_and_french_evidence(self):
        text=V.alert_message(self.signal,self.saved,self.now)
        self.assertIn('plus de 1,5 buts · match entier',text)
        self.assertIn('63′ · Score 1–1',text)
        self.assertIn('6 tirs dont 3 cadrés sur 10 min',text)
        self.assertIn('×1,5 le repère avant-match',text)
        self.assertIn('Cote : à vérifier sur Stake',text)
        for private in ('activity_ratio','Budget quotidien','Long internal','Intensité'):
            self.assertNotIn(private,text)
        self.assertLess(len(text),700)

    def test_period_and_additional_yellow_remain_explicit(self):
        self.signal.update(market='goal_ht',team='a',line=.5)
        self.assertIn('Équipe B · plus de 0,5 buts · 1re mi-temps',V.message_target(self.signal,self.saved))
        self.signal.update(market='card_ft',line=2.5)
        self.saved['decision']['metrics']={'fouls10':4,'possession':40}
        text=V.alert_message(self.signal,self.saved,self.now)
        self.assertIn('Équipe B · plus de 2,5 jaunes · match entier',text)
        self.assertIn('4 fautes sur les 10 dernières minutes',text)
        self.assertIn('Possession : 40 %',text)
        self.assertNotIn('cadrés',text)

    def test_only_recent_valid_price_is_displayed(self):
        self.saved['stake_quote']={'odds':1.88,'received_at':V.iso(self.now)}
        self.assertIn('Cote indicative : 1,88 · Stake',V.alert_message(self.signal,self.saved,self.now))
        for seconds in (-1,31):
            text=V.alert_message(self.signal,self.saved,self.now+dt.timedelta(seconds=seconds))
            self.assertIn('Cote : à vérifier sur Stake',text)
            self.assertNotIn('1,88',text)
        self.saved['stake_quote']['odds']=float('nan')
        self.assertNotIn('nan',V.alert_message(self.signal,self.saved,self.now))

    def test_wire_payload_escapes_names_and_applies_bold_without_preview(self):
        text=V.alert_message(self.signal,self.saved,self.now)
        with patch.dict('os.environ',{'TELEGRAM_BOT_TOKEN':'fake','TELEGRAM_CHAT_ID':'fake'}),patch.object(V.urllib.request,'urlopen',return_value=io.StringIO('{"ok":true,"result":{"message_id":7}}')) as request:
            self.assertEqual(V.notify(text),('sent',None,7))
        payload=json.loads(request.call_args.args[0].data)
        self.assertEqual(payload['parse_mode'],'HTML')
        self.assertEqual(payload['link_preview_options'],{'is_disabled':True})
        self.assertIn('A &amp; B &lt;FC&gt;',payload['text'])
        self.assertIn('<b>🎯 ',payload['text'])
        self.assertNotIn('<FC>',payload['text'])

    def test_old_plain_outbox_and_unicode_limit_stay_valid(self):
        rendered=V.telegram_html('STRATEDGE · ancien résultat\nA < B & C\n'+'⚽'*5000)
        class Reader(HTMLParser):
            def __init__(self):super().__init__();self.text='';self.tags=[]
            def handle_data(self,data):self.text+=data
            def handle_starttag(self,tag,attrs):self.tags.append(tag)
        parsed=Reader();parsed.feed(rendered)
        self.assertIn('A < B & C',parsed.text)
        self.assertLessEqual(len(parsed.text.encode('utf-16-le'))//2,3900)
        self.assertEqual(set(parsed.tags),{'b'})
        self.assertEqual(rendered.count('<b>'),rendered.count('</b>'))

    def test_result_short_and_no_financial_win_inferred_from_indicative_price(self):
        self.saved['stake_quote']={'odds':1.88,'received_at':V.iso(self.now)}
        self.signal['data']=V.enc(self.saved)
        text=V.result_message(self.signal,'pending','won','raw technical source '*100,automatic=True)
        self.assertIn('ÉVÉNEMENT OBSERVÉ',text)
        self.assertIn('1,88',text)
        self.assertNotIn('GAGNANT',text)
        self.assertNotIn('Bilan simulé',text)
        self.assertNotIn('raw technical',text)
        self.assertLess(len(text),500)
        correction=V.result_message(self.signal,'won','pending','VAR',automatic=True)
        self.assertIn('CORRECTION',correction)
        self.assertIn('validation retirée',correction)
        self.assertIn('Ancien résultat',correction)


if __name__=='__main__':unittest.main()
