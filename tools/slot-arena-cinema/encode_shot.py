from pathlib import Path
import subprocess,sys,os
root=Path(os.environ.get('CROWN_RENDER_DIR', str(Path(__file__).resolve().parent)));shot=sys.argv[1]
(root/'assets').mkdir(parents=True,exist_ok=True)
duration={'macro':4,'approach':5,'orbit':5,'hero':20}[shot]
frames={'macro':24,'approach':30,'orbit':30,'hero':36}[shot]
rate=frames/duration
# Smooth camera interpolation in postproduction; the championship shot moves slowly beneath live titles.
filter='minterpolate=fps=24:mi_mode=mci:mc_mode=aobmc:me_mode=bidir:vsbmc=1,tpad=stop_mode=clone:stop_duration=2,eq=contrast=1.035:saturation=0.94,vignette=angle=PI/5'
cmd=['ffmpeg','-v','error','-y','-framerate',str(rate),'-i',str(root/'render'/shot/'%04d.png'),'-vf',filter,'-t',str(duration),'-c:v','libx264','-threads','2','-preset','medium','-crf','24','-pix_fmt','yuv420p','-movflags','+faststart',str(root/'assets'/f'{shot}-raw.mp4')]
subprocess.run(cmd,check=True)
# Normalize duration after interpolation (its time-base can shorten the tail).
subprocess.run(['ffmpeg','-v','error','-y','-i',str(root/'assets'/f'{shot}-raw.mp4'),'-vf','tpad=stop_mode=clone:stop_duration=3','-t',str(duration),'-c:v','libx264','-threads','4','-preset','fast','-crf','22','-pix_fmt','yuv420p','-movflags','+faststart',str(root/'assets'/f'{shot}.mp4')],check=True)
print('ENCODED',shot,(root/'assets'/f'{shot}.mp4').stat().st_size,flush=True)
