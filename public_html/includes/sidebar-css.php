<?php // ── CSS partagé layout sidebar ── ?>
<style>
:root{
  --bg:#050810;--card:#111827;--card2:#0d1220;
  --pink:#ff2d78;--pink-dim:#d6245f;--blue:#00d4ff;--purple:#a855f7;--neon-green:#00d46a;
  --txt:#f0f4f8;--txt2:#b0bec9;--txt3:#8a9bb0;
  --border:rgba(255,45,120,0.15);--border-soft:rgba(255,255,255,0.07);
  --sidebar-w:270px;--safe-b:env(safe-area-inset-bottom,0px);
}
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Rajdhani',sans-serif;background:var(--bg);color:var(--txt);min-height:100vh;overflow-x:hidden;}
body.menu-open{overflow:hidden;}

/* ══════════════════════════════════════════════
   TOP NAV
══════════════════════════════════════════════ */
.top-nav{background:rgba(5,8,16,0.95);backdrop-filter:blur(20px);border-bottom:1px solid var(--border);height:62px;display:flex;align-items:center;padding:0 1.5rem;position:sticky;top:0;z-index:200;gap:1.5rem;}
.nav-logo{flex-shrink:0;}
.nav-logo img{height:32px;display:block;}
.nav-logo-fb{font-family:'Orbitron',sans-serif;font-size:1rem;font-weight:900;color:#fff;text-decoration:none;}
.nav-logo-fb em{color:var(--pink);font-style:normal;}
.nav-center{display:flex;gap:0.3rem;align-items:center;flex:1;justify-content:center;}
.nav-lnk{color:var(--txt2);text-decoration:none;font-size:0.92rem;font-weight:600;padding:0.5rem 0.9rem;border-radius:8px;transition:all .2s;white-space:nowrap;}
.nav-lnk:hover{color:#fff;background:rgba(255,255,255,0.04);}
.nav-lnk.active{color:var(--pink);background:rgba(255,45,120,0.08);}
.nav-acts{display:flex;align-items:center;gap:0.8rem;flex-shrink:0;}
.nav-acts a{color:var(--txt2);text-decoration:none;font-size:0.9rem;font-weight:600;transition:color .2s;}
.nav-acts a:hover{color:var(--txt);}
.nav-x{display:inline-flex;align-items:center;justify-content:center;color:var(--txt2);padding:0.35rem;}
.nav-x:hover{color:var(--txt);}
.nav-x svg{display:block;}
.nav-login{padding:0.3rem 0.5rem;}
.nav-btn{background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);color:var(--txt)!important;padding:0.45rem 1rem;border-radius:8px;font-weight:700;font-size:0.88rem;transition:all .2s;}
.nav-btn:hover{background:rgba(255,255,255,0.1);border-color:rgba(255,255,255,0.2);}
.nav-btn-pink{background:linear-gradient(135deg,var(--pink),var(--pink-dim))!important;color:#fff!important;border:none;box-shadow:0 2px 10px rgba(255,45,120,0.25);}
.nav-btn-pink:hover{box-shadow:0 4px 18px rgba(255,45,120,0.45);transform:translateY(-1px);}
.nav-admin{background:rgba(255,193,7,0.12)!important;border:1px solid rgba(255,193,7,0.3);color:#ffc107!important;padding:0.4rem 0.9rem;border-radius:8px;font-weight:700;font-size:0.88rem;}
.nav-user{width:36px;height:36px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;background:linear-gradient(135deg,var(--pink),var(--blue));border:2px solid rgba(255,45,120,0.35);overflow:hidden;flex-shrink:0;font-family:'Orbitron',sans-serif;font-size:0.95rem;font-weight:900;color:#fff;transition:transform .2s,box-shadow .2s;text-decoration:none;}
.nav-user:hover{transform:scale(1.05);box-shadow:0 0 14px rgba(255,45,120,0.4);}
.nav-user img{width:100%;height:100%;object-fit:cover;}
.nav-user span{line-height:1;}
.hamburger{display:none;flex-direction:column;gap:5px;cursor:pointer;padding:10px 7px;background:none;border:none;position:relative;z-index:350;}
.hamburger span{display:block;width:26px;height:2px;background:var(--txt);border-radius:2px;transition:all .35s cubic-bezier(0.4,0,0.2,1);transform-origin:center;}
.hamburger.open span:nth-child(1){transform:translateY(7px) rotate(45deg);background:var(--pink);box-shadow:0 0 8px rgba(255,45,120,0.6);}
.hamburger.open span:nth-child(2){opacity:0;transform:scaleX(0);}
.hamburger.open span:nth-child(3){transform:translateY(-7px) rotate(-45deg);background:var(--pink);box-shadow:0 0 8px rgba(255,45,120,0.6);}

/* ══════════════════════════════════════════════
   MOBILE DRAWER — CYBERPUNK NEON
══════════════════════════════════════════════ */
.menu-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.65);backdrop-filter:blur(6px);z-index:250;opacity:0;transition:opacity .3s ease;}
.menu-overlay.show{display:block;opacity:1;}

.mobile-menu{
  position:fixed;top:0;right:0;bottom:0;
  width:min(88vw,360px);
  background:#0a0e17;
  border-left:1px solid rgba(255,45,120,0.2);
  z-index:300;
  transform:translateX(100%);
  transition:transform .55s cubic-bezier(0.16,1,0.3,1);
  display:flex;flex-direction:column;
  overflow-y:auto;overflow-x:hidden;
  padding:0 0 calc(1rem + var(--safe-b));
  box-shadow:-20px 0 60px rgba(0,0,0,0.7);
}
.mobile-menu.open{transform:translateX(0);}

/* Scanline top (reste sur la viewport via fixed) */
.mobile-menu::after{
  content:'';position:absolute;top:-4px;left:0;right:0;height:2px;
  background:linear-gradient(90deg,transparent 0%,#ff2d78 20%,#00d4ff 50%,#a855f7 80%,transparent 100%);
  background-size:200% 100%;
  animation:mmScanTop 4s linear infinite;
  z-index:5;pointer-events:none;
  box-shadow:0 0 12px rgba(255,45,120,0.4),0 0 20px rgba(0,212,255,0.3);
}
@keyframes mmScanTop{
  0%{background-position:-200% 0;}
  100%{background-position:200% 0;}
}

/* Wrapper pour scroll interne — PORTE le background ambiant (couvre toute la hauteur scrollable) */
.mm-inner{
  position:relative;z-index:2;
  display:flex;flex-direction:column;
  padding-top:1rem;
  min-height:100%;
}
.mm-inner::before{
  content:'';position:absolute;inset:0;
  background:
    radial-gradient(ellipse 400px 280px at 100% 0%, rgba(255,45,120,0.22), transparent 60%),
    radial-gradient(ellipse 500px 380px at 0% 15%, rgba(0,212,255,0.14), transparent 60%),
    radial-gradient(ellipse 380px 320px at 100% 30%, rgba(168,85,247,0.12), transparent 60%),
    radial-gradient(ellipse 440px 340px at 0% 45%, rgba(255,45,120,0.14), transparent 60%),
    radial-gradient(ellipse 420px 300px at 100% 60%, rgba(0,212,255,0.13), transparent 60%),
    radial-gradient(ellipse 380px 280px at 0% 75%, rgba(168,85,247,0.11), transparent 60%),
    radial-gradient(ellipse 420px 300px at 100% 90%, rgba(255,45,120,0.13), transparent 60%),
    radial-gradient(ellipse 440px 320px at 50% 100%, rgba(0,212,255,0.1), transparent 60%),
    linear-gradient(180deg, transparent 0%, rgba(255,45,120,0.02) 25%, rgba(168,85,247,0.04) 50%, rgba(255,45,120,0.03) 75%, rgba(0,212,255,0.03) 100%);
  pointer-events:none;z-index:-1;
  animation:mmAmbient 12s ease-in-out infinite alternate;
}
@keyframes mmAmbient{
  0%{filter:hue-rotate(0deg);}
  100%{filter:hue-rotate(-25deg);}
}

/* Header */
.mm-head{
  display:flex;align-items:center;justify-content:space-between;
  padding:0.8rem 1.3rem 1rem;
  margin-bottom:0.2rem;
  border-bottom:1px solid rgba(255,45,120,0.1);
}
.mm-head-title{display:flex;align-items:center;gap:10px;}
.mm-head-bar{
  width:4px;height:20px;border-radius:2px;
  background:linear-gradient(180deg,#00d4ff 0%,#ff2d78 100%);
  box-shadow:0 0 10px rgba(255,45,120,0.6),0 0 16px rgba(0,212,255,0.4);
  animation:mmBarPulse 2.5s ease-in-out infinite;
}
@keyframes mmBarPulse{
  0%,100%{box-shadow:0 0 10px rgba(255,45,120,0.6),0 0 16px rgba(0,212,255,0.4);}
  50%{box-shadow:0 0 14px rgba(255,45,120,0.9),0 0 24px rgba(0,212,255,0.6);}
}
.mm-head-txt{
  font-family:'Orbitron',sans-serif;font-size:0.75rem;font-weight:900;letter-spacing:3px;
  background:linear-gradient(90deg,#ff2d78,#ff6ba1,#ff2d78);
  background-size:200% auto;
  -webkit-background-clip:text;-webkit-text-fill-color:transparent;
  background-clip:text;color:transparent;
  animation:mmTxtGradient 3s linear infinite;
}
@keyframes mmTxtGradient{
  0%{background-position:0 0;}
  100%{background-position:200% 0;}
}
.mm-close{
  background:rgba(255,45,120,0.08);border:1px solid rgba(255,45,120,0.25);
  width:36px;height:36px;border-radius:50%;
  display:flex;align-items:center;justify-content:center;
  color:var(--pink);font-size:1rem;cursor:pointer;
  transition:all .2s;position:relative;
}
.mm-close::before{
  content:'';position:absolute;inset:-3px;border-radius:50%;
  background:conic-gradient(from 0deg,transparent,rgba(255,45,120,0.3),transparent);
  animation:mmCloseRotate 3s linear infinite;
  z-index:-1;opacity:0;transition:opacity .2s;
}
.mm-close:hover::before{opacity:1;}
.mm-close:hover{background:rgba(255,45,120,0.15);box-shadow:0 0 16px rgba(255,45,120,0.5);}
@keyframes mmCloseRotate{
  to{transform:rotate(360deg);}
}

/* User card : plus d'air + border animé */
.mm-user{
  margin:0.6rem 14px 14px;padding:16px 14px;border-radius:16px;
  background:linear-gradient(135deg,rgba(255,45,120,0.08) 0%,rgba(0,212,255,0.06) 100%);
  position:relative;overflow:hidden;
  border:1px solid transparent;
  background-clip:padding-box;
}
/* Border gradient animé qui tourne */
.mm-user::before{
  content:'';position:absolute;inset:-1px;border-radius:16px;
  padding:1px;
  background:linear-gradient(90deg,#ff2d78,#00d4ff,#a855f7,#ff2d78);
  background-size:300% 100%;
  -webkit-mask:linear-gradient(#fff 0 0) content-box,linear-gradient(#fff 0 0);
  -webkit-mask-composite:xor;mask-composite:exclude;
  animation:mmUserBorder 4s linear infinite;
  z-index:0;
}
@keyframes mmUserBorder{
  0%{background-position:0 0;}
  100%{background-position:300% 0;}
}
/* Shimmer qui traverse */
.mm-user::after{
  content:'';position:absolute;top:0;left:-50%;
  width:60%;height:100%;
  background:linear-gradient(90deg,transparent,rgba(255,255,255,0.08),transparent);
  animation:mmCardShimmer 4s ease-in-out infinite;
  pointer-events:none;
}
@keyframes mmCardShimmer{
  0%,100%{left:-60%;}
  50%{left:120%;}
}
.mm-user-row{display:flex;align-items:center;gap:12px;position:relative;z-index:2;}
.mm-av{
  width:48px;height:48px;border-radius:50%;
  background:linear-gradient(135deg,#ff2d78,#00d4ff);
  display:flex;align-items:center;justify-content:center;
  font-family:'Orbitron',sans-serif;font-size:1.15rem;font-weight:900;color:#fff;
  overflow:hidden;flex-shrink:0;
  position:relative;
  box-shadow:0 0 18px rgba(255,45,120,0.4),0 0 6px rgba(0,212,255,0.3);
}
.mm-av::before{
  content:'';position:absolute;inset:-2px;border-radius:50%;
  background:conic-gradient(from 0deg,#ff2d78,#00d4ff,#a855f7,#ff2d78);
  z-index:-1;
  animation:mmAvRotate 3s linear infinite;
}
@keyframes mmAvRotate{
  to{transform:rotate(360deg);}
}
.mm-av img{width:100%;height:100%;object-fit:cover;border-radius:50%;}
.mm-info{flex:1;min-width:0;}
.mm-name{font-weight:700;font-size:1rem;color:#fff;line-height:1.1;text-shadow:0 0 8px rgba(0,212,255,0.2);}
.mm-status{font-size:0.72rem;color:var(--txt3);margin-top:3px;font-family:'Share Tech Mono',monospace;letter-spacing:0.3px;}
.mm-badge-vip,.mm-badge-tennis,.mm-badge-fun{
  padding:4px 9px;border-radius:20px;
  font-family:'Orbitron',sans-serif;font-size:0.66rem;font-weight:700;letter-spacing:1px;
  flex-shrink:0;position:relative;
}
.mm-badge-vip{background:linear-gradient(135deg,rgba(245,200,66,0.15),rgba(232,160,32,0.1));border:1px solid rgba(245,200,66,0.35);color:#f5c842;box-shadow:0 0 10px rgba(245,200,66,0.2);}
.mm-badge-tennis{background:rgba(57,255,20,0.1);border:1px solid rgba(57,255,20,0.3);color:#39ff14;box-shadow:0 0 10px rgba(57,255,20,0.2);}
.mm-badge-fun{background:rgba(168,85,247,0.1);border:1px solid rgba(168,85,247,0.3);color:#c084fc;box-shadow:0 0 10px rgba(168,85,247,0.2);}

/* Stats par TIPSTER (3 cards) */
.mm-tipsters{
  margin:0 14px 10px;display:grid;grid-template-columns:1fr 1fr 1fr;gap:7px;
  position:relative;z-index:2;
}
.mm-tipster{
  padding:10px 6px 11px;border-radius:12px;text-align:center;
  position:relative;overflow:hidden;
  transition:transform .2s;
}
.mm-tipster::before{
  content:'';position:absolute;top:0;left:-100%;width:100%;height:100%;
  background:linear-gradient(90deg,transparent,rgba(255,255,255,0.05),transparent);
  animation:mmStatShimmer 5s linear infinite;
}
@keyframes mmStatShimmer{
  0%,100%{left:-100%;}
  50%{left:100%;}
}
.mm-t-multi{background:rgba(0,212,255,0.08);border:1px solid rgba(0,212,255,0.25);box-shadow:0 0 12px rgba(0,212,255,0.1) inset;}
.mm-t-tennis{background:rgba(57,255,20,0.08);border:1px solid rgba(57,255,20,0.25);box-shadow:0 0 12px rgba(57,255,20,0.1) inset;}
.mm-t-fun{background:rgba(168,85,247,0.08);border:1px solid rgba(168,85,247,0.25);box-shadow:0 0 12px rgba(168,85,247,0.1) inset;}
.mm-t-head{display:flex;align-items:center;justify-content:center;gap:4px;margin-bottom:6px;position:relative;z-index:2;}
.mm-t-emoji{font-size:0.78rem;line-height:1;}
.mm-t-name{
  font-family:'Orbitron',sans-serif;font-size:0.58rem;font-weight:900;letter-spacing:1.5px;
  color:rgba(255,255,255,0.5);
}
.mm-t-multi .mm-t-name{color:rgba(0,212,255,0.8);}
.mm-t-tennis .mm-t-name{color:rgba(57,255,20,0.8);}
.mm-t-fun .mm-t-name{color:rgba(168,85,247,0.9);}
.mm-t-stats{position:relative;z-index:2;}
.mm-t-val{
  font-family:'Orbitron',sans-serif;font-size:1.05rem;font-weight:900;line-height:1;
}
.mm-t-multi .mm-t-val{color:#00d4ff;text-shadow:0 0 8px rgba(0,212,255,0.5);}
.mm-t-tennis .mm-t-val{color:#39ff14;text-shadow:0 0 8px rgba(57,255,20,0.5);}
.mm-t-fun .mm-t-val{color:#c084fc;text-shadow:0 0 8px rgba(168,85,247,0.5);}
.mm-t-roi{
  font-family:'Share Tech Mono',monospace;font-size:0.6rem;font-weight:600;
  margin-top:3px;color:rgba(255,255,255,0.45);
  letter-spacing:0.3px;
}

/* Bets en cours — bloc dédié avec dot pulse */
.mm-live{
  display:flex;align-items:center;justify-content:space-between;
  margin:0 14px 14px;padding:13px 14px;border-radius:12px;
  background:linear-gradient(135deg,rgba(255,45,120,0.1) 0%,rgba(255,45,120,0.03) 100%);
  border:1px solid rgba(255,45,120,0.25);
  position:relative;overflow:hidden;z-index:2;
  text-decoration:none;
  box-shadow:0 0 16px rgba(255,45,120,0.12) inset;
  transition:all .2s;
}
.mm-live:hover{background:linear-gradient(135deg,rgba(255,45,120,0.14) 0%,rgba(255,45,120,0.05) 100%);box-shadow:0 0 20px rgba(255,45,120,0.2) inset,0 0 20px rgba(255,45,120,0.15);}
.mm-live::before{
  content:'';position:absolute;top:0;left:-100%;width:60%;height:100%;
  background:linear-gradient(90deg,transparent,rgba(255,45,120,0.15),transparent);
  animation:mmLiveShimmer 3.5s ease-in-out infinite;
}
@keyframes mmLiveShimmer{
  0%,100%{left:-60%;}
  50%{left:120%;}
}
.mm-live-left{display:flex;align-items:center;gap:11px;position:relative;z-index:2;}
.mm-live-pulse{
  width:12px;height:12px;border-radius:50%;background:#ff2d78;
  box-shadow:0 0 12px #ff2d78,0 0 4px rgba(255,255,255,0.4) inset;
  animation:mmLivePulse 1.2s ease-in-out infinite;
  position:relative;flex-shrink:0;
}
.mm-live-pulse::before{
  content:'';position:absolute;inset:-4px;border-radius:50%;
  background:rgba(255,45,120,0.3);
  animation:mmLiveRing 1.2s ease-out infinite;
}
@keyframes mmLivePulse{
  0%,100%{transform:scale(1);}
  50%{transform:scale(1.15);}
}
@keyframes mmLiveRing{
  0%{transform:scale(0.8);opacity:0.6;}
  100%{transform:scale(2);opacity:0;}
}
.mm-live-txt{display:flex;flex-direction:column;gap:2px;}
.mm-live-lbl{
  font-family:'Orbitron',sans-serif;font-size:0.68rem;font-weight:900;letter-spacing:2px;
  color:#ff2d78;text-shadow:0 0 6px rgba(255,45,120,0.4);
}
.mm-live-sub{font-size:0.72rem;color:var(--txt3);font-weight:500;}
.mm-live-count{
  font-family:'Orbitron',sans-serif;font-size:1.6rem;font-weight:900;
  color:#ff2d78;line-height:1;text-shadow:0 0 10px rgba(255,45,120,0.5);
  position:relative;z-index:2;
}

/* Dot pulse générique (pour icons) */
.mm-live-dot{
  width:6px;height:6px;border-radius:50%;background:var(--pink);
  box-shadow:0 0 8px var(--pink);animation:mmPulse 1.2s ease-in-out infinite;
}
@keyframes mmPulse{
  0%,100%{opacity:1;transform:scale(1);}
  50%{opacity:0.5;transform:scale(1.4);}
}

/* Sections */
.mm-sect{
  font-family:'Orbitron',sans-serif;font-size:0.62rem;font-weight:700;
  letter-spacing:3px;color:rgba(255,255,255,0.3);
  padding:0.9rem 1.3rem 0.5rem;text-transform:uppercase;
  position:relative;z-index:2;
  display:flex;align-items:center;gap:8px;
}
.mm-sect::after{
  content:'';flex:1;height:1px;
  background:linear-gradient(90deg,rgba(255,255,255,0.1),transparent);
}

/* Liens drawer avec effet scan hover */
.mm-lnks{padding:0 10px;position:relative;z-index:2;}
.mm-lnk{
  display:flex;align-items:center;gap:12px;
  padding:11px 12px;border-radius:12px;margin-bottom:3px;
  color:var(--txt2);text-decoration:none;
  font-family:'Rajdhani',sans-serif;font-weight:600;font-size:0.95rem;
  transition:all .2s;position:relative;overflow:hidden;
  border:1px solid transparent;
}
.mm-lnk::before{
  content:'';position:absolute;top:0;left:-100%;
  width:100%;height:100%;
  background:linear-gradient(90deg,transparent,rgba(255,45,120,0.08),transparent);
  transition:left .5s ease;
}
.mm-lnk:hover{color:#fff;background:rgba(255,255,255,0.03);border-color:rgba(255,255,255,0.05);}
.mm-lnk:hover::before{left:100%;}
.mm-lnk.active{
  color:var(--pink);
  background:linear-gradient(90deg,rgba(255,45,120,0.15) 0%,rgba(255,45,120,0.05) 50%,rgba(0,212,255,0.05) 100%);
  border:1px solid rgba(255,45,120,0.3);
  box-shadow:0 0 20px rgba(255,45,120,0.15),0 0 4px rgba(255,45,120,0.2) inset;
}
.mm-lnk.active::after{
  content:'';position:absolute;left:0;top:0;bottom:0;width:3px;
  background:linear-gradient(180deg,#00d4ff 0%,#ff2d78 100%);
  box-shadow:0 0 10px var(--pink),0 0 4px var(--blue);
}
.mm-ico{
  width:36px;height:36px;border-radius:10px;
  display:flex;align-items:center;justify-content:center;
  font-size:1rem;background:rgba(255,255,255,0.04);
  flex-shrink:0;position:relative;
  transition:all .2s;
}
.mm-lnk:hover .mm-ico{background:rgba(255,255,255,0.07);transform:scale(1.05);}
.mm-lnk.active .mm-ico{
  background:linear-gradient(135deg,rgba(255,45,120,0.3) 0%,rgba(255,45,120,0.08) 100%);
  box-shadow:0 0 12px rgba(255,45,120,0.3);
}
.mm-lnk-lbl{flex:1;}
.mm-lnk-chev{color:var(--txt3);font-size:1rem;transition:transform .2s;}
.mm-lnk:hover .mm-lnk-chev{transform:translateX(3px);color:var(--pink);}
.mm-lnk-new{
  padding:3px 8px;border-radius:20px;
  background:linear-gradient(135deg,#ff2d78,#d6245f);
  font-family:'Orbitron',sans-serif;font-size:0.58rem;font-weight:900;
  color:#fff;letter-spacing:0.5px;
  box-shadow:0 0 10px rgba(255,45,120,0.4);
  animation:mmNewPulse 2s ease-in-out infinite;
}
@keyframes mmNewPulse{
  0%,100%{box-shadow:0 0 10px rgba(255,45,120,0.4);}
  50%{box-shadow:0 0 16px rgba(255,45,120,0.7);}
}
.mm-ico-dot{
  position:absolute;top:-2px;right:-2px;
  width:9px;height:9px;border-radius:50%;
  background:#00d46a;
  box-shadow:0 0 8px #00d46a,0 0 2px #fff inset;
  animation:mmPulse 1.5s ease-in-out infinite;
}
.mm-lnk.mm-admin{color:#ffc107;}
.mm-lnk.mm-admin .mm-ico{background:rgba(255,193,7,0.08);}
.mm-lnk.mm-admin:hover .mm-ico{background:rgba(255,193,7,0.15);}

/* Footer CTAs */
.mm-foot{
  margin:16px 14px 0;padding-top:16px;
  border-top:1px solid rgba(255,255,255,0.06);
  display:flex;gap:8px;position:relative;z-index:2;
}
.mm-cta{
  flex:1;display:flex;align-items:center;justify-content:center;gap:6px;
  padding:12px 8px;border-radius:12px;text-decoration:none;
  font-family:'Orbitron',sans-serif;font-size:0.7rem;font-weight:700;
  letter-spacing:1.2px;transition:all .2s;
  white-space:nowrap;
}
.mm-cta-out{background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);color:var(--txt3)!important;}
.mm-cta-out:hover{background:rgba(255,255,255,0.08);color:#fff!important;}
.mm-cta-login{background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);color:var(--txt)!important;}
.mm-cta-login:hover{background:rgba(255,255,255,0.08);}
.mm-cta-main{
  flex:1.3;
  background:linear-gradient(135deg,#ff2d78,#d6245f);
  color:#fff!important;font-weight:900;
  box-shadow:0 4px 16px rgba(255,45,120,0.35),0 0 1px rgba(255,255,255,0.2) inset;
  position:relative;overflow:hidden;
}
.mm-cta-main::before{
  content:'';position:absolute;top:0;left:-100%;width:70%;height:100%;
  background:linear-gradient(90deg,transparent,rgba(255,255,255,0.3),transparent);
  animation:mmBtnShine 2.8s ease-in-out infinite;
}
@keyframes mmBtnShine{
  0%{left:-100%;}
  60%,100%{left:200%;}
}
.mm-cta-main:hover{box-shadow:0 6px 24px rgba(255,45,120,0.6);transform:translateY(-1px);}

/* ══════════════════════════════════════════════
   LAYOUT DESKTOP (inchangé)
══════════════════════════════════════════════ */
.app{display:flex;min-height:calc(100vh - 62px);max-height:calc(100vh - 62px);background:linear-gradient(to right, var(--card2) var(--sidebar-w), var(--bg) var(--sidebar-w));overflow:hidden;}
.side{width:var(--sidebar-w);min-width:var(--sidebar-w);flex-shrink:0;background:var(--card2);border-right:1px solid var(--border);padding:1.2rem 0;display:flex;flex-direction:column;position:sticky;top:62px;height:calc(100vh - 62px);overflow-y:auto;z-index:10;align-self:flex-start;}
.side-user{padding:0 1.2rem 1.2rem;border-bottom:1px solid var(--border-soft);margin-bottom:0.8rem;display:flex;align-items:center;gap:0.9rem;}
.side-av{width:44px;height:44px;border-radius:50%;overflow:hidden;flex-shrink:0;background:linear-gradient(135deg,var(--pink),var(--blue));display:flex;align-items:center;justify-content:center;font-family:'Orbitron',sans-serif;font-size:1.1rem;font-weight:900;color:#fff;border:2px solid rgba(255,45,120,0.4);}
.side-av img{width:100%;height:100%;object-fit:cover;}
.side-name{font-weight:700;font-size:1.05rem;line-height:1.2;}
.side-email{font-size:0.78rem;color:var(--txt3);word-break:break-all;}
.side-user-guest{padding:0 1rem 1.3rem;}
.side-av-guest{background:linear-gradient(135deg,var(--pink),var(--blue));border:2px solid rgba(255,45,120,0.4);box-shadow:0 0 12px rgba(255,45,120,0.25);}
.side-guest-actions{display:flex;flex-direction:column;gap:0.4rem;flex:1;}
.side-guest-btn{display:block;text-align:center;padding:0.5rem 0.8rem;border-radius:8px;font-family:'Orbitron',sans-serif;font-size:0.7rem;font-weight:700;letter-spacing:1px;text-transform:uppercase;text-decoration:none;transition:all 0.2s;}
.side-guest-btn-login{background:rgba(255,255,255,0.05);color:var(--txt2);border:1px solid rgba(255,255,255,0.1);}
.side-guest-btn-login:hover{background:rgba(255,255,255,0.1);color:#fff;border-color:rgba(255,255,255,0.25);}
.side-guest-btn-register{background:linear-gradient(135deg,var(--pink),#c4185a);color:#fff;border:1px solid var(--pink);box-shadow:0 0 12px rgba(255,45,120,0.3);}
.side-guest-btn-register:hover{box-shadow:0 0 18px rgba(255,45,120,0.55);transform:translateY(-1px);}
.side-nav{flex:1;padding:0 0.6rem;}
.side-sect{font-family:'Orbitron',sans-serif;font-size:0.62rem;font-weight:700;letter-spacing:2.5px;color:rgba(255,255,255,0.3);padding:0.9rem 0.9rem 0.4rem;text-transform:uppercase;}
.s-link{display:flex;align-items:center;gap:0.85rem;padding:0.7rem 0.9rem;border-radius:9px;cursor:pointer;font-size:1rem;font-weight:600;color:var(--txt2);transition:all .2s;text-decoration:none;border:1px solid transparent;margin-bottom:0.2rem;min-height:42px;}
.s-link:hover{background:rgba(255,255,255,0.04);color:var(--txt);}
.s-link.active{background:rgba(255,45,120,0.1);color:var(--pink);border-color:rgba(255,45,120,0.2);}
.s-link .ico{font-size:1.15rem;width:24px;text-align:center;flex-shrink:0;line-height:1;}
.s-link .ico svg{width:1.1em;height:1.1em;display:block;margin:0 auto;}
.s-link .badge-n{margin-left:auto;background:var(--pink);color:#fff;font-size:0.72rem;font-weight:900;padding:0.2rem 0.55rem;border-radius:50px;}
.side-sep{height:1px;background:var(--border-soft);margin:0.8rem 1rem;}
.side-foot{padding:0.9rem 1.3rem;border-top:1px solid var(--border-soft);margin-top:auto;}
.side-foot a{color:var(--txt3);text-decoration:none;font-size:0.95rem;display:flex;align-items:center;gap:0.6rem;transition:color .2s;}
.side-foot a:hover{color:#ff6b9d;}

.mob-tabs{display:none;}
.content{flex:1;min-width:0;min-height:0;padding:2.5rem 3rem;position:relative;display:flex;flex-direction:column;overflow-y:auto;-webkit-overflow-scrolling:touch;}
.content-body{flex:1;}
.content>div:not(.legal-footer){flex-shrink:0;}
.content .legal-footer{background:transparent;border-top:1px solid var(--border-soft);padding:2.5rem 0 0.5rem;margin-top:3rem;}
.mascotte-bg{position:fixed;right:-30px;top:62px;bottom:0;width:650px;pointer-events:none;z-index:50;opacity:0.14;}
.mascotte-bg img{width:100%;height:100%;object-fit:contain;object-position:bottom right;}
.content>*{position:relative;z-index:2;}
.full-bleed{margin-left:-3rem;margin-right:-3rem;padding-left:3rem;padding-right:3rem;}

/* BREAKPOINTS */
@media(max-width:1200px){.nav-center{gap:0.1rem;}.nav-lnk{padding:0.5rem 0.6rem;font-size:0.85rem;}}
@media(max-width:1100px){.nav-center{display:none;}.hamburger{display:flex;}}

@media(max-width:768px){
  html{-webkit-text-size-adjust:100%;overflow-x:hidden;}
  body{overflow-x:hidden;}
  .side{display:none;}
  .nav-center{display:none;}
  .nav-acts{gap:0.5rem;}
  .nav-acts .nav-btn:not(.nav-user),.nav-acts .nav-login,.nav-acts .nav-admin,.nav-acts .nav-x{display:none;}
  .nav-user{display:inline-flex;}
  .hamburger{display:flex;margin-left:auto;}
  .top-nav{padding:0 0.8rem;height:54px;gap:0.5rem;}
  .nav-logo img{height:26px;}
  .app{background:var(--bg);flex-direction:column;max-height:calc(100dvh - 54px);}
  .content{padding:1rem 0.8rem calc(5.8rem + var(--safe-b));min-height:0;}
  .content .legal-footer{padding:1.5rem 0 0.5rem;margin-top:2rem;}
  .mascotte-bg{display:none;}
  .full-bleed{margin-left:-0.8rem;margin-right:-0.8rem;padding-left:0.8rem;padding-right:0.8rem;}

  .mob-tabs{display:flex;position:fixed;bottom:0;left:0;right:0;z-index:280;background:rgba(5,8,16,0.97);backdrop-filter:blur(20px);border-top:1px solid var(--border);justify-content:space-around;align-items:stretch;height:calc(60px + var(--safe-b));padding-bottom:var(--safe-b);box-shadow:0 -4px 20px rgba(0,0,0,0.5);}
  .mob-tabs .s-link{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:0.2rem;flex:1;padding:0.4rem 0;margin:0;border:none;border-radius:0;font-size:0.62rem;font-weight:700;letter-spacing:0.3px;color:var(--txt3);text-decoration:none;position:relative;min-height:52px;-webkit-tap-highlight-color:transparent;}
  .mob-tabs .s-link .ico{font-size:1.25rem;width:auto;display:block;line-height:1;}
  .mob-tabs .s-link.active{color:var(--pink);background:transparent;border-bottom:none;}
  .mob-tabs .s-link.active::before{content:'';position:absolute;top:0;left:25%;right:25%;height:2px;background:var(--pink);border-radius:0 0 2px 2px;}
  .content table{display:block;max-width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch;}
  .content .tabs-bar,.content .filters,.content .stats-dashboard{max-width:100%;}
}

@media(max-width:380px){
  .content{padding:0.7rem 0.5rem 5.2rem;}
  .mob-tabs{height:54px;}
  .mob-tabs .s-link{font-size:0.56rem;gap:0.15rem;}
  .mob-tabs .s-link .ico{font-size:1.1rem;}
  .mobile-menu{width:94vw;}
  .mm-t-val{font-size:0.95rem;}
  .mm-t-name{font-size:0.54rem;}
  .mm-t-roi{font-size:0.55rem;}
  .mm-live-count{font-size:1.4rem;}
}
</style>
