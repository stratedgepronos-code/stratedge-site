<?php // ── CSS partagé layout sidebar ── ?>
<style>
:root{--bg:#050810;--card:#111827;--card2:#0d1220;--pink:#ff2d78;--pink-dim:#d6245f;--blue:#00d4ff;--purple:#a855f7;--txt:#f0f4f8;--txt2:#b0bec9;--txt3:#8a9bb0;--border:rgba(255,45,120,0.15);--border-soft:rgba(255,255,255,0.07);--sidebar-w:270px;}
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Rajdhani',sans-serif;background:var(--bg);color:var(--txt);min-height:100vh;overflow-x:hidden;}

/* ── Top Nav ── */
.top-nav{background:rgba(5,8,16,0.95);backdrop-filter:blur(20px);border-bottom:1px solid var(--border);height:58px;display:flex;align-items:center;padding:0 1.5rem;position:sticky;top:0;z-index:200;justify-content:space-between;}
.nav-logo img{height:30px;}
.nav-logo-fb{font-family:'Orbitron',sans-serif;font-size:1rem;font-weight:900;color:#fff;text-decoration:none;}
.nav-logo-fb em{color:var(--pink);font-style:normal;}
.nav-acts{display:flex;align-items:center;gap:1rem;}
.nav-acts a{color:var(--txt2);text-decoration:none;font-size:0.9rem;font-weight:600;transition:color .2s;}
.nav-acts a:hover{color:var(--txt);}
.nav-btn{background:linear-gradient(135deg,var(--pink),var(--pink-dim));color:#fff!important;padding:0.4rem 1.1rem;border-radius:8px;font-weight:700;}
.nav-admin{background:rgba(255,193,7,0.12);border:1px solid rgba(255,193,7,0.3);color:#ffc107!important;padding:0.4rem 0.9rem;border-radius:8px;font-weight:700;}
.hamburger{display:none;flex-direction:column;gap:5px;cursor:pointer;padding:5px;background:none;border:none;}
.hamburger span{display:block;width:24px;height:2px;background:var(--txt);border-radius:2px;}
.mobile-menu{display:none;position:fixed;inset:0;top:58px;background:rgba(5,8,16,0.98);backdrop-filter:blur(20px);z-index:150;padding:1.5rem;flex-direction:column;}
.mobile-menu.open{display:flex;}
.mobile-menu a{color:var(--txt2);text-decoration:none;font-size:1.1rem;font-weight:600;padding:1rem 0;border-bottom:1px solid rgba(255,255,255,0.05);}

/* ── Layout ── */
.app{display:flex;min-height:calc(100vh - 58px);background:linear-gradient(to right, var(--card2) var(--sidebar-w), var(--bg) var(--sidebar-w));}

/* ── Sidebar ── */
.side{width:var(--sidebar-w);min-width:var(--sidebar-w);background:var(--card2);border-right:1px solid var(--border);padding:1.5rem 0;display:flex;flex-direction:column;position:sticky;top:58px;height:calc(100vh - 58px);overflow-y:auto;z-index:10;align-self:flex-start;}
.side-user{padding:0 1.3rem 1.3rem;border-bottom:1px solid var(--border-soft);margin-bottom:1rem;display:flex;align-items:center;gap:0.9rem;}
.side-av{width:48px;height:48px;border-radius:50%;overflow:hidden;flex-shrink:0;background:linear-gradient(135deg,var(--pink),var(--blue));display:flex;align-items:center;justify-content:center;font-family:'Orbitron',sans-serif;font-size:1.2rem;font-weight:900;color:#fff;border:2px solid rgba(255,45,120,0.4);}
.side-av img{width:100%;height:100%;object-fit:cover;}
.side-name{font-weight:700;font-size:1.15rem;line-height:1.2;}
.side-email{font-size:0.82rem;color:var(--txt3);word-break:break-all;}
.side-nav{flex:1;padding:0 0.7rem;}
.s-link{display:flex;align-items:center;gap:0.9rem;padding:0.9rem 1.1rem;border-radius:10px;cursor:pointer;font-size:1.2rem;font-weight:600;color:var(--txt2);transition:all .2s;text-decoration:none;border:1px solid transparent;margin-bottom:0.35rem;}
.s-link:hover{background:rgba(255,255,255,0.04);color:var(--txt);}
.s-link.active{background:rgba(255,45,120,0.1);color:var(--pink);border-color:rgba(255,45,120,0.2);}
.s-link .ico{font-size:1.35rem;width:28px;text-align:center;flex-shrink:0;}
.s-link .badge-n{margin-left:auto;background:var(--pink);color:#fff;font-size:0.72rem;font-weight:900;padding:0.2rem 0.55rem;border-radius:50px;}
.side-sep{height:1px;background:var(--border-soft);margin:0.8rem 1rem;}
.side-foot{padding:0.9rem 1.3rem;border-top:1px solid var(--border-soft);margin-top:auto;}
.side-foot a{color:var(--txt3);text-decoration:none;font-size:1rem;display:flex;align-items:center;gap:0.6rem;transition:color .2s;}
.side-foot a:hover{color:#ff6b9d;}

/* ── Mobile tabs ── */
.mob-tabs{display:none;background:var(--card2);border-bottom:1px solid var(--border);overflow-x:auto;white-space:nowrap;-webkit-overflow-scrolling:touch;}
.mob-tabs .s-link{display:inline-flex;border-radius:0;padding:0.85rem 1.1rem;margin:0;font-size:0.95rem;border-bottom:2px solid transparent;}
.mob-tabs .s-link.active{border-bottom-color:var(--pink);background:transparent;}

/* ── Content area ── */
.content{flex:1;min-width:0;padding:2.5rem 3rem;position:relative;display:flex;flex-direction:column;min-height:calc(100vh - 58px);}
.content-body{flex:1;}

/* Footer légal compact dans sidebar layout */
.content .legal-footer{background:transparent;border-top:1px solid var(--border-soft);padding:2.5rem 0 0.5rem;margin-top:3rem;}

/* ── Mascotte — div fixe indépendant, PAS dans un conteneur ── */
.mascotte-bg{position:fixed;right:-30px;top:58px;bottom:0;width:650px;pointer-events:none;z-index:50;opacity:0.14;}
.mascotte-bg img{width:100%;height:100%;object-fit:contain;object-position:bottom right;}
.content>*{position:relative;z-index:2;}

/* ── Full-bleed (bets hero etc) ── */
.full-bleed{margin-left:-3rem;margin-right:-3rem;padding-left:3rem;padding-right:3rem;}

@media(max-width:768px){
  .side{display:none;}
  .mob-tabs{display:flex;}
  .nav-acts{display:none;}
  .hamburger{display:flex;}
  .content{padding:1.3rem 1rem;}
  .mascotte-bg{width:300px;opacity:0.08;}
  .full-bleed{margin-left:-1rem;margin-right:-1rem;padding-left:1rem;padding-right:1rem;}
}
</style>
