<?php // ── CSS partagé layout sidebar ── ?>
<style>
:root{
  --bg:#050810;--card:#111827;--card2:#0d1220;
  --pink:#ff2d78;--pink-dim:#d6245f;--blue:#00d4ff;--purple:#a855f7;
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
.top-nav{
  background:rgba(5,8,16,0.95);backdrop-filter:blur(20px);
  border-bottom:1px solid var(--border);
  height:62px;display:flex;align-items:center;
  padding:0 1.5rem;position:sticky;top:0;z-index:200;
  gap:1.5rem;
}
.nav-logo{flex-shrink:0;}
.nav-logo img{height:32px;display:block;}
.nav-logo-fb{font-family:'Orbitron',sans-serif;font-size:1rem;font-weight:900;color:#fff;text-decoration:none;}
.nav-logo-fb em{color:var(--pink);font-style:normal;}

.nav-center{display:flex;gap:0.3rem;align-items:center;flex:1;justify-content:center;}
.nav-lnk{
  color:var(--txt2);text-decoration:none;
  font-size:0.92rem;font-weight:600;
  padding:0.5rem 0.9rem;border-radius:8px;
  transition:all .2s;white-space:nowrap;
}
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
.nav-user{
  width:36px;height:36px;border-radius:50%;
  display:inline-flex;align-items:center;justify-content:center;
  background:linear-gradient(135deg,var(--pink),var(--blue));
  border:2px solid rgba(255,45,120,0.35);
  overflow:hidden;flex-shrink:0;
  font-family:'Orbitron',sans-serif;font-size:0.95rem;font-weight:900;color:#fff;
  transition:transform .2s,box-shadow .2s;text-decoration:none;
}
.nav-user:hover{transform:scale(1.05);box-shadow:0 0 14px rgba(255,45,120,0.4);}
.nav-user img{width:100%;height:100%;object-fit:cover;}
.nav-user span{line-height:1;}

/* Hamburger animé → X rose */
.hamburger{
  display:none;flex-direction:column;gap:5px;cursor:pointer;
  padding:10px 7px;background:none;border:none;
  position:relative;z-index:350;
}
.hamburger span{
  display:block;width:26px;height:2px;background:var(--txt);
  border-radius:2px;transition:all .35s cubic-bezier(0.4,0,0.2,1);
  transform-origin:center;
}
.hamburger.open span:nth-child(1){transform:translateY(7px) rotate(45deg);background:var(--pink);box-shadow:0 0 8px rgba(255,45,120,0.6);}
.hamburger.open span:nth-child(2){opacity:0;transform:scaleX(0);}
.hamburger.open span:nth-child(3){transform:translateY(-7px) rotate(-45deg);background:var(--pink);box-shadow:0 0 8px rgba(255,45,120,0.6);}

/* ══════════════════════════════════════════════
   MOBILE DRAWER — CYBERPUNK CUSTOM
══════════════════════════════════════════════ */
.menu-overlay{
  display:none;position:fixed;inset:0;
  background:rgba(0,0,0,0.6);backdrop-filter:blur(6px);
  z-index:250;opacity:0;transition:opacity .3s ease;
}
.menu-overlay.show{display:block;opacity:1;}

.mobile-menu{
  position:fixed;top:0;right:0;bottom:0;
  width:min(88vw,360px);
  background:linear-gradient(180deg,#0a0e17 0%,#0d1220 100%);
  border-left:1px solid rgba(255,45,120,0.2);
  z-index:300;
  transform:translateX(100%);
  transition:transform .5s cubic-bezier(0.16,1,0.3,1);
  display:flex;flex-direction:column;
  overflow-y:auto;overflow-x:hidden;
  padding-bottom:calc(1rem + var(--safe-b));
  box-shadow:-20px 0 60px rgba(0,0,0,0.6);
}
.mobile-menu.open{transform:translateX(0);}

/* Shimmer border top animé */
.mobile-menu::before{
  content:'';position:absolute;top:0;left:0;right:0;height:2px;
  background:linear-gradient(90deg,transparent 0%,#ff2d78 30%,#00d4ff 50%,#ff2d78 70%,transparent 100%);
  background-size:200% 100%;
  animation:mmShimmer 3s linear infinite;
  z-index:3;
}
@keyframes mmShimmer{
  0%{background-position:-200% 0;}
  100%{background-position:200% 0;}
}

/* Glow orbe dans le coin */
.mobile-menu::after{
  content:'';position:absolute;top:-60px;right:-60px;
  width:220px;height:220px;border-radius:50%;
  background:radial-gradient(circle,rgba(255,45,120,0.25),transparent 60%);
  filter:blur(30px);pointer-events:none;z-index:0;
}

/* Header drawer */
.mm-head{
  display:flex;align-items:center;justify-content:space-between;
  padding:1.3rem 1.3rem 1rem;
  border-bottom:1px solid rgba(255,255,255,0.06);
  margin-bottom:0.7rem;
  position:relative;z-index:2;
}
.mm-head-title{
  display:flex;align-items:center;gap:10px;
}
.mm-head-bar{
  width:4px;height:18px;border-radius:2px;
  background:linear-gradient(180deg,#ff2d78,#00d4ff);
  box-shadow:0 0 8px rgba(255,45,120,0.6);
}
.mm-head-txt{
  font-family:'Orbitron',sans-serif;font-size:0.72rem;font-weight:900;
  color:var(--pink);letter-spacing:3px;
}
.mm-close{
  background:rgba(255,45,120,0.08);border:1px solid rgba(255,45,120,0.2);
  width:34px;height:34px;border-radius:50%;
  display:flex;align-items:center;justify-content:center;
  color:var(--pink);font-size:0.95rem;cursor:pointer;
  transition:all .2s;
}
.mm-close:hover{background:rgba(255,45,120,0.15);box-shadow:0 0 12px rgba(255,45,120,0.3);}

/* User card glassy avec shimmer */
.mm-user{
  margin:0 14px 14px;padding:14px;border-radius:14px;
  background:linear-gradient(135deg,rgba(255,45,120,0.08),rgba(0,212,255,0.04));
  border:1px solid rgba(255,45,120,0.18);
  position:relative;overflow:hidden;z-index:2;
}
.mm-user::before{
  content:'';position:absolute;top:0;left:-50%;
  width:60%;height:100%;
  background:linear-gradient(90deg,transparent,rgba(255,255,255,0.05),transparent);
  animation:mmCardShimmer 4s ease-in-out infinite;
}
@keyframes mmCardShimmer{
  0%,100%{left:-60%;}
  50%{left:120%;}
}
.mm-user-row{display:flex;align-items:center;gap:12px;position:relative;}
.mm-av{
  width:44px;height:44px;border-radius:50%;
  background:linear-gradient(135deg,var(--pink),var(--blue));
  border:2px solid rgba(255,45,120,0.4);
  display:flex;align-items:center;justify-content:center;
  font-family:'Orbitron',sans-serif;font-size:1.05rem;font-weight:900;color:#fff;
  overflow:hidden;flex-shrink:0;
  box-shadow:0 0 14px rgba(255,45,120,0.3);
}
.mm-av img{width:100%;height:100%;object-fit:cover;}
.mm-info{flex:1;min-width:0;}
.mm-name{font-weight:700;font-size:0.95rem;color:#fff;line-height:1.1;}
.mm-status{font-size:0.7rem;color:var(--txt3);margin-top:3px;font-family:'Share Tech Mono',monospace;letter-spacing:0.3px;}
.mm-badge-vip{
  padding:3px 8px;border-radius:20px;
  background:linear-gradient(135deg,rgba(245,200,66,0.15),rgba(232,160,32,0.1));
  border:1px solid rgba(245,200,66,0.3);
  font-family:'Orbitron',sans-serif;font-size:0.65rem;font-weight:700;
  color:#f5c842;letter-spacing:1px;flex-shrink:0;
}
.mm-badge-tennis{
  padding:3px 8px;border-radius:20px;
  background:rgba(57,255,20,0.1);border:1px solid rgba(57,255,20,0.3);
  font-family:'Orbitron',sans-serif;font-size:0.65rem;font-weight:700;
  color:#39ff14;letter-spacing:1px;flex-shrink:0;
}
.mm-badge-fun{
  padding:3px 8px;border-radius:20px;
  background:rgba(168,85,247,0.1);border:1px solid rgba(168,85,247,0.3);
  font-family:'Orbitron',sans-serif;font-size:0.65rem;font-weight:700;
  color:#c084fc;letter-spacing:1px;flex-shrink:0;
}

/* Stats live en 3 colonnes */
.mm-stats{
  margin:0 14px 14px;display:grid;grid-template-columns:1fr 1fr 1fr;gap:6px;
  position:relative;z-index:2;
}
.mm-stat{
  padding:10px 6px;border-radius:10px;text-align:center;
  position:relative;overflow:hidden;
}
.mm-stat-win{background:rgba(0,212,106,0.08);border:1px solid rgba(0,212,106,0.2);}
.mm-stat-roi{background:rgba(0,212,255,0.08);border:1px solid rgba(0,212,255,0.2);}
.mm-stat-live{background:rgba(255,45,120,0.08);border:1px solid rgba(255,45,120,0.2);}
.mm-stat-val{
  font-family:'Orbitron',sans-serif;font-size:0.95rem;font-weight:900;line-height:1;
  display:flex;align-items:center;justify-content:center;gap:4px;
}
.mm-stat-win .mm-stat-val{color:#00d46a;}
.mm-stat-roi .mm-stat-val{color:#00d4ff;}
.mm-stat-live .mm-stat-val{color:var(--pink);}
.mm-stat-lbl{font-size:0.58rem;color:var(--txt3);margin-top:4px;letter-spacing:1px;text-transform:uppercase;}
.mm-live-dot{
  width:6px;height:6px;border-radius:50%;background:var(--pink);
  box-shadow:0 0 6px var(--pink);animation:mmPulse 1.2s ease-in-out infinite;
}
@keyframes mmPulse{
  0%,100%{opacity:1;transform:scale(1);}
  50%{opacity:0.5;transform:scale(1.3);}
}

/* Sections */
.mm-sect{
  font-family:'Orbitron',sans-serif;font-size:0.6rem;font-weight:700;
  letter-spacing:3px;color:rgba(255,255,255,0.3);
  padding:0.9rem 1.3rem 0.4rem;text-transform:uppercase;
  position:relative;z-index:2;
}

/* Liens drawer */
.mm-lnks{padding:0 10px;position:relative;z-index:2;}
.mm-lnk{
  display:flex;align-items:center;gap:12px;
  padding:10px 12px;border-radius:12px;margin-bottom:3px;
  color:var(--txt2);text-decoration:none;
  font-family:'Rajdhani',sans-serif;font-weight:600;font-size:0.93rem;
  transition:all .18s;position:relative;overflow:hidden;
}
.mm-lnk:hover{background:rgba(255,255,255,0.03);color:#fff;}
.mm-lnk.active{
  color:var(--pink);
  background:linear-gradient(90deg,rgba(255,45,120,0.12),rgba(255,45,120,0.02));
  border:1px solid rgba(255,45,120,0.25);
}
.mm-lnk.active::before{
  content:'';position:absolute;left:0;top:0;bottom:0;width:3px;
  background:linear-gradient(180deg,#ff2d78,#ff6ba1);
  box-shadow:0 0 8px var(--pink);
}
.mm-ico{
  width:32px;height:32px;border-radius:10px;
  display:flex;align-items:center;justify-content:center;
  font-size:0.95rem;background:rgba(255,255,255,0.04);
  flex-shrink:0;position:relative;
}
.mm-lnk.active .mm-ico{background:linear-gradient(135deg,rgba(255,45,120,0.25),rgba(255,45,120,0.05));}
.mm-lnk-lbl{flex:1;}
.mm-lnk-chev{color:var(--txt3);font-size:0.9rem;}
.mm-lnk-new{
  padding:2px 7px;border-radius:20px;
  background:rgba(255,45,120,0.2);
  font-family:'Orbitron',sans-serif;font-size:0.58rem;font-weight:900;
  color:var(--pink);letter-spacing:0.5px;
}
.mm-ico-dot{
  position:absolute;top:-2px;right:-2px;
  width:8px;height:8px;border-radius:50%;
  background:#00d46a;box-shadow:0 0 6px #00d46a;
  animation:mmPulse 1.5s ease-in-out infinite;
}
.mm-lnk.mm-admin{color:#ffc107;}
.mm-lnk.mm-admin .mm-ico{background:rgba(255,193,7,0.08);}

/* Footer drawer avec 2 boutons */
.mm-foot{
  margin:14px 14px 0;padding-top:14px;
  border-top:1px solid rgba(255,255,255,0.06);
  display:flex;gap:8px;position:relative;z-index:2;
}
.mm-cta{
  flex:1;display:flex;align-items:center;justify-content:center;gap:6px;
  padding:11px;border-radius:12px;text-decoration:none;
  font-family:'Orbitron',sans-serif;font-size:0.68rem;font-weight:700;
  letter-spacing:1.2px;transition:all .2s;
}
.mm-cta-out{background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);color:var(--txt3)!important;}
.mm-cta-out:hover{background:rgba(255,255,255,0.08);color:#fff!important;}
.mm-cta-login{background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);color:var(--txt)!important;}
.mm-cta-login:hover{background:rgba(255,255,255,0.08);}
.mm-cta-main{
  flex:1.3;background:linear-gradient(135deg,#ff2d78,#d6245f);color:#fff!important;
  font-weight:900;box-shadow:0 4px 16px rgba(255,45,120,0.35);
  position:relative;overflow:hidden;
}
.mm-cta-main::before{
  content:'';position:absolute;top:0;left:-100%;width:60%;height:100%;
  background:linear-gradient(90deg,transparent,rgba(255,255,255,0.25),transparent);
  animation:mmBtnShine 2.8s ease-in-out infinite;
}
@keyframes mmBtnShine{
  0%{left:-100%;}
  50%,100%{left:200%;}
}
.mm-cta-main:hover{box-shadow:0 6px 24px rgba(255,45,120,0.55);transform:translateY(-1px);}

/* ══════════════════════════════════════════════
   LAYOUT (Desktop sidebar)
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

/* Mobile bottom tab bar */
.mob-tabs{display:none;}

/* Content area */
.content{flex:1;min-width:0;min-height:0;padding:2.5rem 3rem;position:relative;display:flex;flex-direction:column;overflow-y:auto;-webkit-overflow-scrolling:touch;}
.content-body{flex:1;}
.content>div:not(.legal-footer){flex-shrink:0;}
.content .legal-footer{background:transparent;border-top:1px solid var(--border-soft);padding:2.5rem 0 0.5rem;margin-top:3rem;}

/* Mascotte */
.mascotte-bg{position:fixed;right:-30px;top:62px;bottom:0;width:650px;pointer-events:none;z-index:50;opacity:0.14;}
.mascotte-bg img{width:100%;height:100%;object-fit:contain;object-position:bottom right;}
.content>*{position:relative;z-index:2;}

.full-bleed{margin-left:-3rem;margin-right:-3rem;padding-left:3rem;padding-right:3rem;}

/* ══════════════════════════════════════════════
   TABLETTE & MOBILE BREAKPOINTS
══════════════════════════════════════════════ */
@media(max-width:1200px){
  .nav-center{gap:0.1rem;}
  .nav-lnk{padding:0.5rem 0.6rem;font-size:0.85rem;}
}
@media(max-width:1100px){
  .nav-center{display:none;}
  .hamburger{display:flex;}
}

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

  .mob-tabs{
    display:flex;position:fixed;bottom:0;left:0;right:0;z-index:280;
    background:rgba(5,8,16,0.97);backdrop-filter:blur(20px);
    border-top:1px solid var(--border);
    justify-content:space-around;align-items:stretch;
    height:calc(60px + var(--safe-b));
    padding-bottom:var(--safe-b);
    box-shadow:0 -4px 20px rgba(0,0,0,0.5);
  }
  .mob-tabs .s-link{
    display:flex;flex-direction:column;align-items:center;justify-content:center;
    gap:0.2rem;flex:1;padding:0.4rem 0;margin:0;
    border:none;border-radius:0;
    font-size:0.62rem;font-weight:700;letter-spacing:0.3px;
    color:var(--txt3);text-decoration:none;position:relative;
    min-height:52px;-webkit-tap-highlight-color:transparent;
  }
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
  .mm-stat-val{font-size:0.85rem;}
  .mm-stat-lbl{font-size:0.52rem;}
}
</style>
