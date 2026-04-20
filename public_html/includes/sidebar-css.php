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

/* Nav center (liens desktop) */
.nav-center{display:flex;gap:0.3rem;align-items:center;flex:1;justify-content:center;}
.nav-lnk{
  color:var(--txt2);text-decoration:none;
  font-size:0.92rem;font-weight:600;
  padding:0.5rem 0.9rem;border-radius:8px;
  transition:all .2s;white-space:nowrap;position:relative;
}
.nav-lnk:hover{color:#fff;background:rgba(255,255,255,0.04);}
.nav-lnk.active{color:var(--pink);background:rgba(255,45,120,0.08);}

/* Nav actions (droite) */
.nav-acts{display:flex;align-items:center;gap:0.8rem;flex-shrink:0;}
.nav-acts a{color:var(--txt2);text-decoration:none;font-size:0.9rem;font-weight:600;transition:color .2s;}
.nav-acts a:hover{color:var(--txt);}
.nav-x{display:inline-flex;align-items:center;justify-content:center;color:var(--txt2);padding:0.35rem;}
.nav-x:hover{color:var(--txt);}
.nav-x svg{display:block;}
.nav-login{padding:0.3rem 0.5rem;}
.nav-btn{
  background:rgba(255,255,255,0.05);
  border:1px solid rgba(255,255,255,0.1);
  color:var(--txt)!important;padding:0.45rem 1rem;border-radius:8px;
  font-weight:700;font-size:0.88rem;transition:all .2s;
}
.nav-btn:hover{background:rgba(255,255,255,0.1);border-color:rgba(255,255,255,0.2);}
.nav-btn-pink{
  background:linear-gradient(135deg,var(--pink),var(--pink-dim))!important;
  color:#fff!important;border:none;box-shadow:0 2px 10px rgba(255,45,120,0.25);
}
.nav-btn-pink:hover{box-shadow:0 4px 18px rgba(255,45,120,0.45);transform:translateY(-1px);}
.nav-admin{
  background:rgba(255,193,7,0.12)!important;
  border:1px solid rgba(255,193,7,0.3);
  color:#ffc107!important;padding:0.4rem 0.9rem;border-radius:8px;font-weight:700;
  font-size:0.88rem;
}
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

/* Hamburger - animé */
.hamburger{
  display:none;flex-direction:column;gap:5px;cursor:pointer;
  padding:10px 7px;background:none;border:none;
  position:relative;z-index:350;
}
.hamburger span{
  display:block;width:26px;height:2px;background:var(--txt);
  border-radius:2px;transition:all .3s cubic-bezier(0.4,0,0.2,1);
  transform-origin:center;
}
.hamburger.open span:nth-child(1){transform:translateY(7px) rotate(45deg);background:var(--pink);}
.hamburger.open span:nth-child(2){opacity:0;transform:scaleX(0);}
.hamburger.open span:nth-child(3){transform:translateY(-7px) rotate(-45deg);background:var(--pink);}

/* ══════════════════════════════════════════════
   MOBILE DRAWER (slide depuis la droite + overlay)
══════════════════════════════════════════════ */
.menu-overlay{
  display:none;position:fixed;inset:0;
  background:rgba(0,0,0,0.6);backdrop-filter:blur(4px);
  z-index:250;
  opacity:0;transition:opacity .3s ease;
}
.menu-overlay.show{display:block;opacity:1;}

.mobile-menu{
  position:fixed;top:0;right:0;bottom:0;
  width:min(85vw,340px);
  background:linear-gradient(180deg,#0d1220 0%,#0a0e17 100%);
  border-left:1px solid var(--border);
  z-index:300;
  transform:translateX(100%);
  transition:transform .35s cubic-bezier(0.4,0,0.2,1);
  display:flex;flex-direction:column;
  overflow-y:auto;
  padding-bottom:calc(1rem + var(--safe-b));
  box-shadow:-10px 0 40px rgba(0,0,0,0.5);
}
.mobile-menu.open{transform:translateX(0);}

/* Header drawer avec bouton fermer */
.mm-head{
  display:flex;align-items:center;justify-content:space-between;
  padding:1.2rem 1.3rem 0.8rem;
  border-bottom:1px solid var(--border-soft);
  position:sticky;top:0;z-index:2;
  background:linear-gradient(180deg,#0d1220 0%,#0d1220 80%,transparent 100%);
}
.mm-head-title{
  font-family:'Orbitron',sans-serif;font-size:0.85rem;font-weight:900;
  color:var(--pink);letter-spacing:2px;
}
.mm-close{
  background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);
  width:36px;height:36px;border-radius:50%;
  display:flex;align-items:center;justify-content:center;
  color:var(--txt);font-size:1.1rem;cursor:pointer;
  transition:all .2s;
}
.mm-close:hover{background:rgba(255,45,120,0.15);border-color:rgba(255,45,120,0.3);color:var(--pink);}

/* User card drawer */
.mm-user{
  display:flex;align-items:center;gap:0.9rem;
  padding:0.9rem 1.3rem 1.2rem;
  border-bottom:1px solid var(--border-soft);
  margin-bottom:0.4rem;
}
.mm-av{
  width:46px;height:46px;border-radius:50%;
  background:linear-gradient(135deg,var(--pink),var(--blue));
  border:2px solid rgba(255,45,120,0.35);
  display:flex;align-items:center;justify-content:center;
  font-family:'Orbitron',sans-serif;font-size:1.15rem;font-weight:900;color:#fff;
  overflow:hidden;flex-shrink:0;
}
.mm-av img{width:100%;height:100%;object-fit:cover;}
.mm-info{flex:1;min-width:0;}
.mm-name{font-weight:700;font-size:1.05rem;color:#fff;line-height:1.2;}
.mm-email{font-size:0.78rem;color:var(--txt3);word-break:break-all;margin-top:2px;}

/* Sections du drawer */
.mm-sect{
  font-family:'Orbitron',sans-serif;font-size:0.62rem;font-weight:700;
  letter-spacing:2.5px;text-transform:uppercase;
  color:rgba(255,255,255,0.35);
  padding:1rem 1.3rem 0.4rem;
}

/* Liens du drawer */
.mm-lnk{
  display:flex;align-items:center;gap:0.9rem;
  padding:0.78rem 1.3rem;
  color:var(--txt2);text-decoration:none;
  font-size:0.98rem;font-weight:600;
  border-left:3px solid transparent;
  transition:all .15s;
}
.mm-lnk:hover{color:#fff;background:rgba(255,255,255,0.03);}
.mm-lnk.active{
  color:var(--pink);background:rgba(255,45,120,0.08);
  border-left-color:var(--pink);
}
.mm-ico{
  width:34px;height:34px;border-radius:9px;
  display:flex;align-items:center;justify-content:center;
  font-size:1.05rem;background:rgba(255,255,255,0.04);
  flex-shrink:0;
}
.mm-lnk.active .mm-ico{background:rgba(255,45,120,0.15);}
.mm-lnk.mm-admin{color:#ffc107;}
.mm-lnk.mm-admin .mm-ico{background:rgba(255,193,7,0.1);}

/* Footer drawer */
.mm-foot{
  margin-top:auto;padding:1.2rem 1.3rem 0.6rem;
  border-top:1px solid var(--border-soft);
  display:flex;flex-direction:column;gap:0.6rem;
}
.mm-cta{
  display:block;text-align:center;
  padding:0.85rem 1rem;border-radius:10px;
  font-family:'Orbitron',sans-serif;font-size:0.78rem;font-weight:700;
  letter-spacing:1.5px;text-transform:uppercase;text-decoration:none;
  transition:all .2s;
}
.mm-cta-out{background:rgba(255,255,255,0.05);color:var(--txt2)!important;border:1px solid rgba(255,255,255,0.1);}
.mm-cta-out:hover{background:rgba(255,255,255,0.08);color:#fff!important;}
.mm-cta-login{background:rgba(255,255,255,0.05);color:var(--txt)!important;border:1px solid rgba(255,255,255,0.1);}
.mm-cta-login:hover{background:rgba(255,255,255,0.08);}
.mm-cta-register{background:linear-gradient(135deg,var(--pink),var(--pink-dim));color:#fff!important;box-shadow:0 4px 16px rgba(255,45,120,0.3);}
.mm-cta-register:hover{box-shadow:0 6px 20px rgba(255,45,120,0.5);transform:translateY(-1px);}

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
.side-sect{
  font-family:'Orbitron',sans-serif;font-size:0.62rem;font-weight:700;
  letter-spacing:2.5px;color:rgba(255,255,255,0.3);
  padding:0.9rem 0.9rem 0.4rem;text-transform:uppercase;
}
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

/* Full-bleed */
.full-bleed{margin-left:-3rem;margin-right:-3rem;padding-left:3rem;padding-right:3rem;}

/* ══════════════════════════════════════════════
   TABLETTE (≤1200px)
══════════════════════════════════════════════ */
@media(max-width:1200px){
  .nav-center{gap:0.1rem;}
  .nav-lnk{padding:0.5rem 0.6rem;font-size:0.85rem;}
}
@media(max-width:1100px){
  .nav-center{display:none;}
  .hamburger{display:flex;}
}

/* ══════════════════════════════════════════════
   MOBILE ≤ 768px
══════════════════════════════════════════════ */
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
    gap:0.2rem;flex:1;
    padding:0.4rem 0;margin:0;
    border:none;border-radius:0;
    font-size:0.62rem;font-weight:700;letter-spacing:0.3px;
    color:var(--txt3);text-decoration:none;
    position:relative;min-height:52px;
    -webkit-tap-highlight-color:transparent;
  }
  .mob-tabs .s-link .ico{font-size:1.25rem;width:auto;display:block;line-height:1;}
  .mob-tabs .s-link.active{color:var(--pink);background:transparent;border-bottom:none;}
  .mob-tabs .s-link.active::before{
    content:'';position:absolute;top:0;left:25%;right:25%;
    height:2px;background:var(--pink);border-radius:0 0 2px 2px;
  }

  .content table{display:block;max-width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch;}
  .content .tabs-bar,
  .content .filters,
  .content .stats-dashboard{max-width:100%;}
}

@media(max-width:380px){
  .content{padding:0.7rem 0.5rem 5.2rem;}
  .mob-tabs{height:54px;}
  .mob-tabs .s-link{font-size:0.56rem;gap:0.15rem;}
  .mob-tabs .s-link .ico{font-size:1.1rem;}
  .mobile-menu{width:92vw;}
}
</style>
