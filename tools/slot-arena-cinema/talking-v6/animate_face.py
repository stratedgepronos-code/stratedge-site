from pathlib import Path
import cv2,numpy as np,wave,subprocess,sys,math

root=Path(__file__).resolve().parent
who=sys.argv[1]
source=root.parent/'roast-v5'/f'{who}.webp'
opened=cv2.resize(cv2.imread(str(source)),(512,768)).astype(np.float32)
closed=cv2.resize(cv2.imread(str(root/f'closed-{who}.webp')),(512,768)).astype(np.float32)
# Only the generated mouth keyframe is used; the reference face stays intact.
cx,cy,rx,ry=(260,420,105,78) if who=='alex' else (220,390,108,80)
yy,xx=np.mgrid[0:768,0:512].astype(np.float32)
radius=((xx-cx)/rx)**2+((yy-cy)/ry)**2
mask=np.clip((1.2-radius)/.55,0,1)[...,None]
flow=cv2.calcOpticalFlowFarneback(cv2.cvtColor(closed.astype('uint8'),cv2.COLOR_BGR2GRAY),cv2.cvtColor(opened.astype('uint8'),cv2.COLOR_BGR2GRAY),None,.5,3,29,4,7,1.5,0)
with wave.open(str(root/f'voice-{who}.wav')) as wav:
 rate=wav.getframerate();sound=np.frombuffer(wav.readframes(wav.getnframes()),dtype=np.int16).astype(np.float32)/32768
fps=24;start=15.5;duration=len(sound)/rate
energies=np.array([np.sqrt(np.mean(sound[int(i*rate/fps):max(int((i+1)*rate/fps),int(i*rate/fps)+1)]**2)) for i in range(math.ceil(duration*fps))])
mouth=np.clip((energies/max(.001,np.percentile(energies,82)))**.75,0,1)
mouth=np.convolve(mouth,[.18,.64,.18],mode='same')
cmd=['ffmpeg','-v','error','-y','-f','rawvideo','-pix_fmt','bgr24','-s','512x768','-r','24','-i','-','-an','-c:v','libx264','-threads','4','-preset','fast','-crf','17','-pix_fmt','yuv420p',str(root/f'face-{who}.mp4')]
p=subprocess.Popen(cmd,stdin=subprocess.PIPE)
for n in range(34*fps):
 t=n/fps;relative=t-start;active=0<=relative<duration
 if active:
  a=float(mouth[min(len(mouth)-1,int(relative*fps))]);edge=min(1,relative/.12,(duration-relative)/.15);a=a*edge+(1-edge)
  wc=cv2.remap(closed,xx-a*flow[:,:,0],yy-a*flow[:,:,1],cv2.INTER_LINEAR,borderMode=cv2.BORDER_REFLECT)
  wo=cv2.remap(opened,xx+(1-a)*flow[:,:,0],yy+(1-a)*flow[:,:,1],cv2.INTER_LINEAR,borderMode=cv2.BORDER_REFLECT)
  frame=opened*(1-mask)+(wc*(1-a)+wo*a)*mask
  angle=2.3*math.sin(relative*4.4)*edge
  matrix=cv2.getRotationMatrix2D((256,380),angle,1)
  matrix[1,2]+=4*math.sin(relative*8)*edge
  frame=cv2.warpAffine(frame,matrix,(512,768),flags=cv2.INTER_LINEAR,borderMode=cv2.BORDER_CONSTANT)
 else:frame=opened
 p.stdin.write(np.clip(frame,0,255).astype('uint8').tobytes())
p.stdin.close()
if p.wait()!=0:raise SystemExit('Face encoder failed')
print('ANIMATED',who,'speech',round(duration,2),'seconds',flush=True)
