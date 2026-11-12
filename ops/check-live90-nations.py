#!/usr/bin/env python3
"""Read-only verification of the owner-requested Nations League activation."""
import datetime
import importlib.util
import json
import sqlite3
import sys
from pathlib import Path

runtime = Path(sys.argv[1] if len(sys.argv)>1 else '/opt/stratedge/live90')
sys.path.insert(0, str(runtime))
spec=importlib.util.spec_from_file_location('live_v4', runtime/'live_v4.py')
engine=importlib.util.module_from_spec(spec)
spec.loader.exec_module(engine)
c=sqlite3.connect('file:/var/lib/stratedge/live90.sqlite?mode=ro', uri=True)
c.row_factory=sqlite3.Row
try:
    assert engine.setting(c,'card_selection_nations_20261112',False), 'Nations League migration not applied'
    selected='UEFA · Nations League' in engine.setting(c,'card_leagues',[])
    fixture={'country':'EUR','league':'UEFA Nations League'}
    for side in ('h','a'):
        assert engine.selected_card_competition(fixture)=='UEFA · Nations League', 'Nations League alias unrecognized'
        if selected: assert engine.card_market_allowed(c,fixture,side,datetime.datetime.now(datetime.timezone.utc)), 'Nations League denied'
    print('NATIONS_LEAGUE_CARDS_OK '+json.dumps({
        'competition_allowed':selected,
        'both_teams':True,
        'telegram_enabled':engine.radar_enabled(c),
        'min_goal_half_rate':engine.GOAL_HALF_MIN_RATE,
        'stop_minute':engine.ALERT_STOP_MINUTE,
        'test_notifications_sent':False
    }))
finally:
    c.close()
