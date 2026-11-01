from pathlib import Path
import subprocess,sys

root=Path(__file__).resolve().parent
out=Path(__file__).resolve().parents[3]/'public_html/tournoi-slots/assets'
who=sys.argv[1]
assert who in ['alex','marc']
# All motion and compositing are baked into the MP4. The browser only decodes it.
portrait="[1:v]scale=1536:2304,zoompan=z='1+0.10*exp(-max(0,on-336)/90)':x='iw/2-iw/zoom/2':y='0':d=1:s=416x624:fps=24,format=rgba,geq=r='r(X,Y)':g='g(X,Y)':b='b(X,Y)':a='255*min(1,min(X/52,(W-1-X)/52))*min(1,(H-1-Y)/115)*min(1,Y/20)',fade=t=in:st=14:d=0.32:alpha=1[p]"
background="[0:v]drawbox=x=0:y=0:w=iw:h=ih:color=black@0.68:t=fill:enable='gte(t,13.65)'[bg]"
composition="[bg][p]overlay=x='(W-w)/2':y='12+8*sin(max(0,t-14)/5)':enable='gte(t,14)':shortest=1[v0]"
sparks=[]
for i in range(18):
    # Gold points confined to the sides, so faces and live result text stay readable.
    x=100+(i*79)%1080
    if 420<x<860:x=160 if i%2 else 1110
    y=f"mod({i*91}+t*{12+i%4*5},640)"
    sparks.append(f"drawbox=x={x}:y='{y}':w=2:h=2:color=0xe8c681@0.65:t=fill:enable='gte(t,14)'")
finish="[v0]"+','.join(sparks)+",format=yuv420p[v]"
# A tiny cinematic pause before the visual punchline, followed by the original orchestra.
sound="[0:a]volume='if(between(t,13.35,13.95),0.12,1)':eval=frame[a]"
subprocess.run(['ffmpeg','-v','error','-y','-i',str(out/'crown-film-v4.mp4'),'-loop','1','-framerate','24','-i',str(root/f'{who}.webp'),'-filter_complex_threads','2','-filter_complex',';'.join([portrait,background,composition,finish,sound]),'-map','[v]','-map','[a]','-t','34','-r','24','-c:v','libx264','-threads','4','-preset','fast','-crf','22','-pix_fmt','yuv420p','-c:a','aac','-b:a','160k','-movflags','+faststart',str(out/f'crown-roast-{who}-v5.mp4')],check=True)
subprocess.run(['ffmpeg','-v','error','-y','-ss','23','-i',str(out/f'crown-roast-{who}-v5.mp4'),'-frames:v','1','-q:v','2',str(out/f'crown-roast-{who}-v5.jpg')],check=True)
print('RENDERED',who,flush=True)
