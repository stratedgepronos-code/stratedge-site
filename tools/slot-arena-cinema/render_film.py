import bpy, math, random, sys, os, time
from mathutils import Vector
from pathlib import Path
ROOT=Path(os.environ.get('CROWN_RENDER_DIR', str(Path(__file__).resolve().parent)))
(ROOT/'render').mkdir(parents=True,exist_ok=True)
random.seed(90)
bpy.ops.object.select_all(action='SELECT');bpy.ops.object.delete(use_global=False)
scene=bpy.context.scene;scene.render.engine='CYCLES';scene.cycles.device='CPU';scene.cycles.samples=8;scene.cycles.use_denoising=True;scene.cycles.max_bounces=5;scene.cycles.diffuse_bounces=2;scene.cycles.glossy_bounces=3;scene.cycles.transmission_bounces=2;scene.cycles.use_light_tree=True
scene.render.resolution_x=1280;scene.render.resolution_y=720;scene.render.resolution_percentage=100
scene.render.image_settings.file_format='PNG';scene.render.image_settings.color_mode='RGB';scene.render.image_settings.compression=15
scene.render.fps=24;scene.render.threads_mode='FIXED';scene.render.threads=8
scene.render.film_transparent=False;scene.render.use_persistent_data=True
scene.world.color=(.006,.009,.015)
scene.view_settings.view_transform='AgX';scene.view_settings.look='AgX - Medium High Contrast';scene.view_settings.exposure=-.15

def mat(name,color,metal=0,rough=.3,emission=0):
 m=bpy.data.materials.new(name);m.diffuse_color=(*color,1);m.use_nodes=True;p=m.node_tree.nodes.get('Principled BSDF');p.inputs['Base Color'].default_value=(*color,1);p.inputs['Metallic'].default_value=metal;p.inputs['Roughness'].default_value=rough
 if emission:p.inputs['Emission Color'].default_value=(*color,1);p.inputs['Emission Strength'].default_value=emission
 return m
GOLD=mat('Champagne gold / polished edges',(.72,.43,.13),.91,.19)
DARK=mat('Black polished stone',(.009,.014,.021),.65,.23)
BRUSH=mat('Brushed warm gold',(.5,.26,.075),.86,.26)
LIGHT=mat('Warm filament',(1,.58,.18),.4,.18,5)
SPARK=mat('Airborne gold',(1,.68,.31),.1,.2,3)
BLACK=mat('Architecture',(.012,.019,.03),.35,.35)
# Fine material variation, subtle enough to read as metal rather than noise.
n=BRUSH.node_tree.nodes;links=BRUSH.node_tree.links;tex=n.new('ShaderNodeTexNoise');tex.inputs['Scale'].default_value=140;tex.inputs['Detail'].default_value=2;bump=n.new('ShaderNodeBump');bump.inputs['Strength'].default_value=.065;bump.inputs['Distance'].default_value=.008;links.new(tex.outputs['Fac'],bump.inputs['Height']);links.new(bump.outputs['Normal'],n.get('Principled BSDF').inputs['Normal'])
def smooth(obj,material):
 obj.data.materials.append(material)
 if obj.type=='MESH':
  for p in obj.data.polygons:p.use_smooth=True
 return obj

def cylinder(name,r,depth,z,material,vertices=96):
 bpy.ops.mesh.primitive_cylinder_add(vertices=vertices,radius=r,depth=depth,location=(0,0,z));o=bpy.context.object;o.name=name;smooth(o,material);b=o.modifiers.new('Machined bevel','BEVEL');b.width=.035;b.segments=3;o.modifiers.new('Weighted normals','WEIGHTED_NORMAL');o.rotation_euler[2]=math.pi/8 if vertices==8 else 0;return o

def lathe(name,profile,material):
 N=96;verts=[(r*math.cos(i/N*math.tau),r*math.sin(i/N*math.tau),z) for r,z in profile for i in range(N)];faces=[]
 for j in range(len(profile)-1):
  for i in range(N):a=j*N+i;b=j*N+(i+1)%N;faces.append((a,b,b+N,a+N))
 mesh=bpy.data.meshes.new(name);mesh.from_pydata(verts,[],faces);mesh.update();o=bpy.data.objects.new(name,mesh);scene.collection.objects.link(o);smooth(o,material);sub=o.modifiers.new('Continuous sculpted surface','SUBSURF');sub.levels=1;return o

def curve(name,points,rad,material,cyclic=False):
 c=bpy.data.curves.new(name,'CURVE');c.dimensions='3D';c.resolution_u=2;c.bevel_depth=rad;c.bevel_resolution=3;s=c.splines.new('POLY');s.points.add(len(points)-1)
 for p,xyz in zip(s.points,points):p.co=(*xyz,1)
 s.use_cyclic_u=cyclic;o=bpy.data.objects.new(name,c);scene.collection.objects.link(o);o.data.materials.append(material);return o

def box(name,loc,scale,material,bevel=.04):
 bpy.ops.mesh.primitive_cube_add(size=1,location=loc);o=bpy.context.object;o.name=name;o.scale=scale;bpy.ops.object.transform_apply(location=False,rotation=False,scale=True);o.data.materials.append(material)
 if bevel:b=o.modifiers.new('Bevelled corners','BEVEL');b.width=bevel;b.segments=3;o.modifiers.new('Normals','WEIGHTED_NORMAL')
 return o
# Monumental tiered dais and reflective floor.
cylinder('Arena disc',3.7,.22,-.16,DARK);curve('Gold stage rim',[(3.73*math.cos(i/128*math.tau),3.73*math.sin(i/128*math.tau),-.04)for i in range(128)],.016,GOLD,True);cylinder('Upper dais',2.8,.18,.05,DARK);curve('Upper light rim',[(2.82*math.cos(i/128*math.tau),2.82*math.sin(i/128*math.tau),.14)for i in range(128)],.012,LIGHT,True)
bpy.ops.mesh.primitive_plane_add(size=160,location=(0,0,-.3));floor=bpy.context.object;floor.name='Reflective black floor';floor.data.materials.append(DARK)
for r in[4.5,6,8.5]:curve('Arena floor inlay',[(r*math.cos(i/128*math.tau),r*math.sin(i/128*math.tau),-.28)for i in range(128)],.009,BRUSH,True)
# Trophy architecture: stepped obsidian base, turned gold stem, deep sculpted cup.
cylinder('Base gold foundation',.95,.12,.27,GOLD,8);cylinder('Obsidian trophy plinth',.89,.55,.59,DARK,8);cylinder('Gold capital',.8,.07,.9,GOLD,8)
lathe('The stem',[(0,.94),(.57,.94),(.6,1.01),(.4,1.15),(.22,1.42),(.18,1.85),(.22,2.05),(.34,2.2)],GOLD)
lathe('The championship cup',[(.02,2.11),(.22,2.14),(.39,2.25),(.7,2.52),(1.03,2.95),(1.23,3.45),(1.36,4.04),(1.4,4.25),(1.39,4.33),(1.31,4.35),(1.27,4.24),(1.18,3.8),(.97,3.25),(.65,2.8),(.24,2.54),(0,2.51)],BRUSH)
for z,r in[(4.31,1.37),(4.18,1.36),(2.2,.3)]:curve('Polished gold rim',[(r*math.cos(i/96*math.tau),r*math.sin(i/96*math.tau),z)for i in range(96)],.035,GOLD,True)
for side in[-1,1]:
 points=[]
 for i in range(65):
  a=math.pi/2-i/64*math.pi;points.append((side*(1.01+.25*math.sin(a)+.91*math.cos(a)),0,3.33+.78*math.sin(a)))
 curve('Sweeping victory handle',points,.105,GOLD)
# Raised diamond medal, decorative ribs and tiny polished highlights.
verts=[(0,-1.235,3.86),(.26,-1.245,3.51),(0,-1.15,3.12),(-.26,-1.245,3.51),(0,-1.37,3.5)]
me=bpy.data.meshes.new('Medallion');me.from_pydata(verts,[],[(0,1,4),(1,2,4),(2,3,4),(3,0,4)]);o=bpy.data.objects.new('Diamond of victory',me);scene.collection.objects.link(o);o.data.materials.append(GOLD)
for a in[-.9,-.6,.6,.9]:
 pts=[(r*math.sin(a),-r*math.cos(a),z) for r,z in[(.45,2.3),(.8,2.65),(1.08,3.1),(1.27,3.65),(1.34,4.08)]];curve('Engraved gold rib',pts,.011,GOLD)
plate=box('Winner plaque',(0,-.835,.58),(.81,.025,.23),GOLD,.012)
font=bpy.data.curves.new('SLOT ARENA engraving','FONT');font.body='SLOT ARENA';font.align_x='CENTER';font.size=.102;font.extrude=.001;font.space_character=1.15;label=bpy.data.objects.new('SLOT ARENA',font);scene.collection.objects.link(label);label.location=(0,-.854,.55);label.rotation_euler=(math.pi/2,0,0);label.data.materials.append(DARK)
# A halo and a cathedral of vertical dark monoliths behind the stage.
curve('The halo',[(3.4*math.cos(i/192*math.tau),2.5,3.2+3.4*math.sin(i/192*math.tau))for i in range(192)],.026,LIGHT,True)
curve('Outer halo',[(3.52*math.cos(i/192*math.tau),2.53,3.2+3.52*math.sin(i/192*math.tau))for i in range(192)],.012,GOLD,True)
for i in range(17):
 a=math.radians(10+i*10);x=9*math.cos(a);y=9*math.sin(a);height=6+(i%3)*.8
 o=box('Monolith %02d'%i,(x,y,height/2-.3),(.55,.75,height),BLACK);o.rotation_euler[2]=-a
 curve('Architectural light %02d'%i,[(x-.2*math.sin(a),y+.2*math.cos(a),.2),(x-.2*math.sin(a),y+.2*math.cos(a),height-.4)],.008,LIGHT)
# Cinematic floating bokeh. Instances share a single mesh.
bpy.ops.mesh.primitive_ico_sphere_add(subdivisions=1,radius=1);proto=bpy.context.object;proto.name='Particle master';proto.data.materials.append(SPARK);proto.hide_render=True
for i in range(90):
 o=bpy.data.objects.new('Gold dust %03d'%i,proto.data);scene.collection.objects.link(o);o.location=(random.uniform(-7,7),random.uniform(-4,7),random.uniform(.4,7));o.scale=(.008,)*3
 if i%5==0:o.scale=(.015,)*3
# Broad physical lights give metallic reflections and soft shadows.
def area(name,loc,power,color,size,target=(0,0,2.7),shape='DISK',size_y=None):
 d=bpy.data.lights.new(name,'AREA');d.energy=power*.55;d.color=color;d.shape=shape;d.size=size
 if size_y and shape=='RECTANGLE':d.size_y=size_y
 o=bpy.data.objects.new(name,d);scene.collection.objects.link(o);o.location=loc;o.rotation_euler=(Vector(target)-o.location).to_track_quat('-Z','Y').to_euler();return o
key=area('Key / champagne softbox',(-4,-4,7),1900,(1,.8,.53),5,shape='RECTANGLE',size_y=2)
area('Silver strip',(4,-1,5),1400,(.75,.86,1),3,shape='RECTANGLE',size_y=.8)
area('Back rim',(0,4,6),2200,(1,.53,.2),4)
area('Top reflection',(0,0,9),1600,(1,.9,.67),3)
area('Front silk',(0,-8,4),600,(.6,.72,1),5)
# Cinema camera with shallow focus; no text baked into the winner shot.
bpy.ops.object.camera_add();cam=bpy.context.object;scene.camera=cam;cam.data.sensor_width=36;cam.data.lens=50;cam.data.dof.use_dof=True;cam.data.dof.aperture_fstop=2.8
focus=bpy.data.objects.new('Focus plane',None);scene.collection.objects.link(focus);cam.data.dof.focus_object=focus
# Optical bloom from actual highlights, no screen-filling flashes.
scene.use_nodes=True;n=scene.node_tree.nodes;n.clear();rl=n.new('CompositorNodeRLayers');glare=n.new('CompositorNodeGlare');glare.glare_type='FOG_GLOW';glare.quality='MEDIUM';glare.threshold=2.2;glare.size=7;glare.mix=-.93;out=n.new('CompositorNodeComposite');scene.node_tree.links.new(rl.outputs['Image'],glare.inputs[0]);scene.node_tree.links.new(glare.outputs['Image'],out.inputs[0])

def pose(shot,u):
 u=max(0,min(1,u));u=u*u*(3-2*u)
 if shot=='macro':
  cam.location=(2.25-.7*u,-3.25,4.7-.22*u);target=(.65-.4*u,-.5,3.95);cam.data.lens=82;cam.data.dof.aperture_fstop=2.1
 elif shot=='approach':
  cam.location=(-3.4+2.4*u,-13+4*u,1.25+1.4*u);target=(0,0,2.65);cam.data.lens=43;cam.data.dof.aperture_fstop=3.2
 elif shot=='orbit':
  a=-math.pi/2-.65+.75*u;cam.location=(7.5*math.cos(a),7.5*math.sin(a),3.6+.8*u);target=(0,0,2.6);cam.data.lens=48;cam.data.dof.aperture_fstop=3.6
 else:
  cam.location=(.9-.8*u,-19.8-1.3*u,6.5+.5*u);target=(0,0,1.2);cam.data.lens=42;cam.data.dof.aperture_fstop=5.6
 focus.location=target;cam.rotation_euler=(Vector(target)-cam.location).to_track_quat('-Z','Y').to_euler()
 # A light sweep grazes the engraved metal during each camera move.
 key.location.x=-4+u*2;key.rotation_euler=(Vector((0,0,2.7))-key.location).to_track_quat('-Z','Y').to_euler()

args=sys.argv[sys.argv.index('--')+1:] if '--' in sys.argv else []
shot=args[0] if args else 'hero';mode=args[1] if len(args)>1 else 'still'
if mode=='still':
 pose(shot,.5);scene.cycles.samples=24;scene.render.filepath=str(ROOT/'render'/f'{shot}-still.png');t=time.time();bpy.ops.render.render(write_still=True);print('STILL_SECONDS',time.time()-t,flush=True)
else:
 N={'macro':24,'approach':30,'orbit':30,'hero':36}[shot];dest=ROOT/'render'/shot;dest.mkdir(exist_ok=True)
 for i in range(N):
  file=dest/f'{i:04d}.png'
  if file.exists():continue
  pose(shot,i/(N-1));scene.render.filepath=str(file);t=time.time();bpy.ops.render.render(write_still=True);print('FRAME',shot,i,N,round(time.time()-t,2),flush=True)
