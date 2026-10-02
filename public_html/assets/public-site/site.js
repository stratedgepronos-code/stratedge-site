(() => {
 'use strict';
 const body=document.body, menu=document.querySelector('.menu-toggle'), nav=document.querySelector('.public-nav');
 const mobile=matchMedia('(max-width: 860px)'), reduced=matchMedia('(prefers-reduced-motion: reduce)');
 function closeMenu(focus=false){body.classList.remove('nav-open');menu?.setAttribute('aria-expanded','false');if(nav)nav.inert=mobile.matches;if(focus)menu?.focus()}
 function syncMenu(){closeMenu();if(nav)nav.inert=mobile.matches} syncMenu(); mobile.addEventListener('change',syncMenu);
 menu?.addEventListener('click',()=>{const open=!body.classList.contains('nav-open');body.classList.toggle('nav-open',open);menu.setAttribute('aria-expanded',String(open));nav.inert=!open&&mobile.matches;if(open)nav.querySelector('a')?.focus()});
 document.addEventListener('keydown',e=>{if(e.key==='Escape')closeMenu(true);if(e.key==='Tab'&&body.classList.contains('nav-open')){const nodes=[menu,...nav.querySelectorAll('a')],first=nodes[0],last=nodes.at(-1);if(e.shiftKey&&document.activeElement===first){e.preventDefault();last.focus()}else if(!e.shiftKey&&document.activeElement===last){e.preventDefault();first.focus()}}});
 const toggle=document.querySelector('.motion-toggle');let paused=reduced.matches;
 function motion(){body.classList.toggle('motion-paused',paused);toggle?.setAttribute('aria-pressed',String(paused));if(toggle)toggle.textContent=paused?'Activer les animations':'Mettre les animations en pause'}motion();toggle?.addEventListener('click',()=>{paused=!paused;motion()});reduced.addEventListener('change',()=>{paused=reduced.matches;motion()});
 let tick=false;const progress=document.querySelector('.read-progress');
 function update(){const max=document.documentElement.scrollHeight-innerHeight;if(progress)progress.style.transform=`scaleX(${max>0?Math.min(scrollY/max,1):0})`;tick=false}update();addEventListener('scroll',()=>{if(!tick){tick=true;requestAnimationFrame(update)}},{passive:true});
 const hero=document.querySelector('[data-lens]'),lens=hero?.querySelector('.lens-console');
 if(hero&&lens){
  lens.hidden=false;
  lens.querySelectorAll('[data-view]').forEach(button=>button.addEventListener('click',()=>{
   const reading=button.dataset.view==='lecture';hero.dataset.lens=reading?'lecture':'terrain';
   lens.querySelectorAll('[data-view]').forEach(b=>b.setAttribute('aria-pressed',String(b===button)));
   lens.querySelector('.lens-explanation').textContent=reading?'Tendances, contexte, prix : trois regards avant de décider.':'Un match se regarde. Une décision se construit.';
  }));
 }
 if('IntersectionObserver'in window){const observer=new IntersectionObserver(entries=>{for(const e of entries)if(e.isIntersecting){e.target.classList.add('seen');observer.unobserve(e.target)}},{threshold:.08});document.querySelectorAll('[data-reveal]').forEach(e=>observer.observe(e))}
 document.querySelectorAll('[data-copy]').forEach(button=>button.addEventListener('click',async()=>{const text=document.getElementById(button.dataset.copy)?.value||button.dataset.text;if(!text)return;try{await navigator.clipboard.writeText(text);const status=button.closest('.share-tools')?.querySelector('[role=status]');if(status)status.textContent='Copié. Prêt à partager.';else button.textContent='Copié ✓'}catch(e){const field=document.getElementById(button.dataset.copy);if(field){field.hidden=false;field.focus();field.select()}else{button.textContent='Copiez l’adresse de cette page'}}}));
 document.querySelectorAll('[data-slug-from]').forEach(input=>{const target=document.getElementById(input.dataset.slugFrom);let touched=Boolean(target?.value);target?.addEventListener('input',()=>touched=true);input.addEventListener('input',()=>{if(!touched&&target)target.value=input.value.normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-|-$/g,'')})});
})();
