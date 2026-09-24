#!/usr/bin/env python3
"""V3 production entry point: local scenarios only, no OpenAI calls."""
import argparse, os, time
from scenario_engine import run
if __name__=='__main__':
    ap=argparse.ArgumentParser();ap.add_argument('--once',action='store_true');ap.add_argument('--db',default=os.environ.get('SE90_DB','/var/lib/stratedge/live90.sqlite'));args=ap.parse_args()
    while True:
        try:run(args.db,os.environ.get('SE90_TELEGRAM','0')=='1')
        except Exception as e:print('Live90 cycle failed:',type(e).__name__,flush=True)
        if args.once:break
        time.sleep(30)
