'use strict';
const $=s=>document.querySelector(s);
const esc=v=>String(v??'—').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const fmt=v=>typeof v==='number'?v.toLocaleString('fr-FR',{maximumFractionDigits:2}):'—';
const time=v=>v?new Date(v).toLocaleString('fr-FR',{timeZone:'Europe/Paris',day:'2-digit',month:'2-digit',hour:'2-digit',minute:'2-digit'}):'—';
const marketNames={goal_ht:'But avant la pause',goal_ft:'But avant la fin',card_ft:'Un carton supplémentaire'};
const states={waiting:'En attente',watch:'Sous surveillance',missing:'Donnée manquante',stale:'Collecte arrêtée',suspended:'Expulsion',covered:'Déjà atteint',price:'Cote à vérifier',candidate:'Concordant',confirming:'Confirmation',signal:'Signal enregistré'};
const deliveries={sent:'Envoyé',failed:'Échec certain',uncertain:'Livraison inconnue',expired:'Expiré',queued:'En attente',sending:'En cours',disabled:'Désactivé',superseded:'Remplacé par une correction'};
const statisticalOutcomes={won:'Événement observé',lost:'Non observé',void:'Observation annulée',pending:'À confirmer'};
const noOdds=s=>s.odds===null;
const resultName=s=>(noOdds(s)?statisticalOutcomes:outcomes)[s.outcome]||s.outcome;
const priceName=s=>noOdds(s)?(s.data?.stake_quote?.odds?fmt(s.data.stake_quote.odds)+'*':'Sans cote'):fmt(s.odds);
const outcomes={won:'Gagnant',lost:'Perdant',void:'Annulé',pending:'À confirmer'};
const metricNames={referee_yellow_per_match:'Arbitre · jaunes / match',referee_fouls_per_match:'Arbitre · fautes / match',referee_sample:'Arbitre · matchs étudiés',shots:'Tirs cumulés',sot:'Cadrés cumulés',shots10:'Tirs / 10′',sot10:'Cadrés / 10′',shots5:'Tirs / 5′',sot5:'Cadrés / 5′',activity_ratio:'Rythme / avant-match',baseline_shots:'Tirs moyens / match',sample:'Échantillon',yellow_cards:'Jaunes',fouls:'Fautes cumulées',fouls10:'Fautes / 10′',possession:'Possession %',opponent_shots10:'Tirs adverses / 10′'};
let data={matches:[],signals:[],settings:{telegram_enabled:true,min_odds:1.65},totals:{},feed:{},telegram:{}},view='live',market='all',busy=false,moreHistory=[],analystFiles=[];
function notice(msg){$('#notice').textContent=msg;$('#notice').hidden=false}
async function request(payload){
 if(window.S90_DEMO){if(payload&&payload.action!=='board')throw Error('Aperçu visuel : action désactivée');return window.S90_DEMO}
 const r=await fetch('api.php',{method:payload?'POST':'GET',credentials:'same-origin',headers:payload?{'Content-Type':'application/json','X-CSRF-Token':$('meta[name=csrf-token]')?.content||''}:{},body:payload?JSON.stringify(payload):undefined});
 let x;try{x=await r.json()}catch{throw Error('Session expirée ou service indisponible. Recharge la page après connexion.')}
 if(!r.ok||x.error&&x.ok!==false)throw Error(x.error||'Réponse invalide');return x;
}
function download(name,x){const url=URL.createObjectURL(new Blob([JSON.stringify(x,null,2)],{type:'application/json'}));const a=document.createElement('a');a.href=url;a.download=name;a.click();setTimeout(()=>URL.revokeObjectURL(url),1000)}
function referenceNow(){return Date.parse(data.server_time)||Date.now()}
function age(v){return v?Math.max(0,(referenceNow()-Date.parse(v))/1000):Infinity}
function effective(r,d){
 if(!r.received_at)return {status:'waiting',reason:'En attente de la collecte Packball'};
 if(age(r.received_at)>100)return {status:'stale',reason:'Le navigateur Packball n’envoie plus de données fraîches'};
 if(r.state!=='LIVE')return {status:'waiting',reason:r.state==='FT'?'Match terminé':r.state==='HT'?'Pause':'Coup d’envoi à venir'};
 if(!d||d.sample_id!==r.sample_id)return {status:'waiting',reason:'Prochain passage du moteur'};
 return d;
}
function visibleSignals(){const map=new Map([...moreHistory,...data.signals].map(s=>[s.id,s]));return [...map.values()].sort((a,b)=>b.id-a.id)}
function filteredDecisions(r){return (r.decisions||[]).filter(d=>market==='all'||d.market===market)}
function renderHealth(){const h=data.health;if(!h){$('#health-summary').textContent='Diagnostic indisponible pour ce relevé.';return}$('#health-summary').textContent=h.issues?.length?`${h.issues.length} points à vérifier · contrôle ${time(h.checked_at)}`:`Aucune anomalie détectée actuellement · ${time(h.checked_at)}`;$('#health-summary').textContent+=` · ${h.incidents_24h||0} épisodes enregistrés sur les dernières 24 h`;$('#health-issues').innerHTML=(h.issues||[]).map(x=>`<p><b>${esc(x.code)}</b> · ${esc(x.detail)}</p>`).join('')+`<p class="help">${esc(h.note)}</p>`}
function reviewHTML(r){if(!r)return '<p>Bilan disponible après un résultat, ou dans l’export du bilan pour GPT.</p>';return `<div class="detail-box"><h3>Bilan automatique du signal</h3><p>${esc(time(r.generated_at))}</p><h4>Faits observés</h4><ul>${(r.facts||[]).map(x=>`<li>${esc(x)}</li>`).join('')}</ul><h4>Limites des données</h4><ul>${(r.limits||[]).map(x=>`<li>${esc(x)}</li>`).join('')||'<li>Aucune limite repérée par ces contrôles ; cela ne prouve pas la qualité prédictive du signal.</li>'}</ul><p>${esc(r.conclusion)}</p><h4>À examiner</h4><ul>${(r.next_steps||[]).map(x=>`<li>${esc(x)}</li>`).join('')}</ul></div>`}
function render(){
 renderHealth();collectorVersionStatus();
 const feedAge=age(data.feed?.received_at),engineAge=age(data.engine?.at),live=data.matches.filter(r=>['LIVE','HT'].includes(r.state)&&age(r.received_at)<=100);
 $('#feed-title').textContent=feedAge<=100?'Collecte connectée':'Collecte en attente';$('#feed-dot').classList.toggle('connected',feedAge<=100);
 $('#feed-detail').textContent=data.feed?.received_at?`${data.feed.rows??'—'} lignes reçues · ${time(data.feed.received_at)} · ${Math.round(feedAge)} s`:'Ouvre Packball avec le collecteur activé';
 $('#engine-state').textContent=engineAge<=65?'Moteur actif · lecture toutes les 20 s':'Moteur · aucun cycle récent';$('#engine-state').style.color=engineAge<=65?'var(--cyan)':'var(--gold)';
 $('#kpi-matches').textContent=data.matches.length;$('#kpi-live').textContent=live.length+' en cours · '+live.filter(r=>r.state==='HT').length+' à la pause';
 $('#kpi-context').textContent=data.matches.filter(r=>r.analyst?.usable).length+' / '+data.matches.length;
 $('#kpi-signals').textContent=data.totals.total||0;$('#kpi-pending').textContent=(data.totals.pending||0)+' résultats à confirmer';
 $('#kpi-results').textContent=(data.totals.priced_won??data.totals.won??0)+' G / '+(data.totals.priced_lost??data.totals.lost??0)+' P';$('#kpi-units').textContent=(data.totals.priced_total===0?'Aucun signal coté':fmt(data.totals.units||0)+' u · simulation sur les signaux cotés uniquement')+' · Observations : '+(data.totals.statistical_won||0)+' observés / '+(data.totals.statistical_lost||0)+' non observés';
 $('#live-counter').textContent=live.length;$('#history-counter').textContent=data.totals.total||0;
 for(const k of ['all',...Object.keys(marketNames)])$('#count-'+k).textContent=data.matches.reduce((n,r)=>n+(r.decisions||[]).filter(d=>(k==='all'||d.market===k)&&['candidate','confirming','signal','price'].includes(effective(r,d).status)).length,0);
 const query=normSearch($('#search').value),matches=data.matches.filter(r=>normSearch(`${r.home} ${r.away} ${r.league||''}`).includes(query));
 $('#export-history').hidden=view!=='history';$('#history-more').hidden=true;$('#legacy-history').hidden=view!=='history'||!data.legacy?.length;
 if(view==='history'){
  const rows=visibleSignals().filter(s=>(market==='all'||s.market===market)&&normSearch(s.data.home+' '+s.data.away).includes(query));
  $('#table-head').innerHTML='<tr><th>Rencontre / heure du signal</th><th>Marché exact</th><th>Cote</th><th>Résultat</th><th>Telegram</th><th></th></tr>';
  $('#match-rows').innerHTML=rows.map(s=>`<tr><td class="team-names">${esc(s.data.home)}<br>${esc(s.data.away)}<small>${esc(time(s.created_at))} · ${esc(s.data.minute)}′ · #${s.id}</small></td><td>${esc(s.data.label)}<small>Intensité ${esc(s.data.decision?.intensity)}/100 · observation</small></td><td class="mono">${priceName(s)}<small>${noOdds(s)?(s.data?.stake_quote?(s.data.stake_quote.unit==='stake_bookings'?'Stake Bookings · indicatif':'Stake · indicatif'):'Alerte statistique'):'bet365'}</small></td><td><button class="row-more" data-settle="${s.id}">${esc(resultName(s))}</button>${s.data.resolution?`<small>${s.data.resolution.mode==='automatic'?'Automatique · Packball':'Correction manuelle'}</small>`:''}${s.outcome==='pending'&&s.data.suggested_outcome?`<small>À vérifier : ${esc(outcomes[s.data.suggested_outcome])}</small>`:''}</td><td>Signal : ${esc(deliveries[s.delivery]||s.delivery)}<small>${esc(s.delivery_error||'')}</small>${s.result_notification?`<small>Résultat : ${esc(deliveries[s.result_notification.delivery]||s.result_notification.delivery)} · ${esc(s.result_notification.delivery_error||'')}</small>`:''}</td><td><button class="row-more" data-signal="${s.id}" aria-label="Lire le signal ${s.id}">↗</button></td></tr>`).join('');
  $('#empty').hidden=rows.length>0;$('#board-description').textContent='Chaque alerte conserve ses données et son résultat. * Cote Stake indicative conservée au signal ; les observations statistiques restent exclues du bilan financier.';
  $('#history-more').hidden=visibleSignals().length>=(data.history_total||0);
  $('#legacy-rows').innerHTML=(data.legacy||[]).map(s=>`<div>${esc(s.context?.home)} — ${esc(s.context?.away)} · ${esc(s.context?.market_label||s.market)}<br>${esc(time(s.created_at))} · cote ${fmt(s.odds)} · ${esc(resultName(s))} · ancienne version</div>`).join('');
 }else{
  $('#table-head').innerHTML='<tr><th>Rencontre</th><th>Temps / score</th><th>Préparation</th><th>Lecture du direct</th><th></th></tr>';
  const priority=r=>Math.max(0,...filteredDecisions(r).map(d=>({signal:6,confirming:5,candidate:4,price:3,watch:2}[effective(r,d).status]||0)));
  matches.sort((a,b)=>priority(b)-priority(a)||Date.parse(a.kickoff_ts)-Date.parse(b.kickoff_ts));
  $('#match-rows').innerHTML=matches.map(r=>{const ds=filteredDecisions(r),shown=ds.length?ds:[null];return `<tr><td class="team-names">${esc(r.home)}<br>${esc(r.away)}<small>${esc(r.league||'Packball')} · ${esc(time(r.kickoff_ts))}</small></td><td><span class="score-box mono">${r.score?esc(r.score.h)+' – '+esc(r.score.a):'—'}</span><div class="state-text mono">${r.state==='LIVE'?esc(r.minute)+'′':esc({NS:'À venir',HT:'Mi-temps',FT:'Terminé'}[r.state]||r.state||'À venir')}</div></td><td><div class="context-dots"><span class="pill ${r.profile?.usable?'ok':'warn'}">${r.profile?.usable?'Stats ✓':r.profile?'Stats tardives':'Stats —'}</span><span class="pill ${r.analyst?.usable?'ok':'warn'}">${r.analyst?.usable?'GPT ✓':r.analyst?'GPT tardif':'GPT —'}</span></div></td><td><div class="signals-cell">${shown.map(d=>{const e=effective(r,d);return `<span class="state-chip ${esc(e.status)}" title="${esc(e.reason)}">${d?esc((d.team==='h'?'D':'E')+' · '+({goal_ht:'But HT',goal_ft:'But FT',card_ft:'Carton'}[d.market]||''))+' · ':''}${esc(states[e.status]||e.status)}${typeof e.intensity==='number'&&e.intensity>0?`<b>${e.intensity}</b>`:''}</span>`}).join('')}</div></td><td><button class="row-more" data-match="${esc(r.packball_id||r.fixture)}" aria-label="Analyser ${esc(r.home)} contre ${esc(r.away)}">↗</button></td></tr>`}).join('');
  $('#empty').hidden=matches.length>0;$('#board-description').textContent='D = domicile · E = extérieur · intensité /100, pas une probabilité.';
 }
 $('#signals-list').innerHTML=data.signals.slice(0,4).map(s=>`<article class="signal-card"><div class="eyebrow">#${s.id} / ${esc(s.data.minute)}′ · ${esc(time(s.created_at))}</div><h3>${esc(s.data.home)}<br>${esc(s.data.away)}</h3><div class="signal-price"><span>${esc(s.data.label)}</span><b>${priceName(s)}</b></div>${s.data.stake_quote?`<p>* Cote Stake ${s.data.stake_quote.unit==='stake_bookings'?'Bookings (règlement distinct) ':''}indicative au signal</p>`:''}<p>Telegram : ${esc(deliveries[s.delivery]||s.delivery)}<br>${esc(resultName(s))}</p><button data-signal="${s.id}">Lire les raisons ↗</button></article>`).join('')||'<div class="empty"><span>↗</span><h3>Le prochain signal<br>se construit ici.</h3><p>Une dynamique répétée.<br>Un contexte compris.<br>Buts et cartons : alertes sans cote.<br>Prix à vérifier chez ton bookmaker.</p></div>';
 $('#updated').textContent='Actualisé · '+time(data.server_time);
}
function normSearch(v){return String(v||'').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase()}
async function refresh(){if(busy)return;busy=true;try{data=await request();render();const modal=$('#detail-modal');if(modal.open&&modal.dataset.match){const scroll=modal.scrollTop;showMatch(modal.dataset.match);modal.scrollTop=scroll}}catch(e){notice(e.message)}finally{busy=false}}
function metrics(d){return Object.entries(d.metrics||{}).map(([k,v])=>`<span>${esc(metricNames[k]||k)} : <b>${fmt(v)}</b></span>`).join('')}
function refereeHTML(r){if(!r)return '<p>Arbitre : fiche statistique structurée absente. Une désignation peut être mentionnée dans les notes de l’analyste ci-dessous.</p>';const s=r.stats;return `<div class="detail-box"><h3>Arbitre · ${esc(r.name)}</h3><p>Désignation : ${esc(r.appointment)}<br>${esc(r.note)}</p>${s?`<p>${esc(s.sample_label)} · ${fmt(s.matches)} matchs<br>Jaunes / match : ${fmt(s.yellow_per_match)} · Rouges / match : ${fmt(s.red_per_match)}<br>Fautes / match : ${fmt(s.fouls_per_match)}</p>`:'<p>Statistiques indisponibles.</p>'}<small>Contexte descriptif ; aucune probabilité déduite de ces moyennes.</small></div>`}
function contextHTML(ctx){if(!ctx)return '<p>Contexte non importé : tous les matchs restent surveillés avec une lecture neutre.</p>';return `${refereeHTML(ctx.referee)}<p>${esc(ctx.summary)}</p>${['h','a'].map(t=>`<p><b>${t==='h'?'Domicile':'Extérieur'}</b> · ${esc(ctx.teams?.[t]?.note)}</p><ul>${(ctx.teams?.[t]?.flags||[]).map(f=>`<li>${esc(f.detail)} <small>${esc(f.certainty)} · ${esc(f.severity)}</small></li>`).join('')}</ul>`).join('')}<p>À vérifier : ${(ctx.unknowns||[]).map(esc).join(' · ')||'Aucune inconnue déclarée'}</p><p>${(ctx.sources||[]).map(s=>`<a href="${esc(s.url)}" target="_blank" rel="noopener noreferrer">${esc(s.title)} ↗</a>`).join('<br>')}</p>`}
function showMatch(id){const r=data.matches.find(m=>String(m.packball_id||m.fixture)===id);if(!r){$('#detail-modal').close();return}$('#detail-modal').dataset.match=id;const p=r.profile?.prematch||{};
 $('#detail-content').innerHTML=`<div class="eyebrow">LECTURE DU MATCH</div><h2>${esc(r.home)} — ${esc(r.away)}</h2><p class="help">${esc(time(r.kickoff_ts))} · ${esc(r.league||'')} · chaque marché est évalué pour les deux équipes</p><div class="detail-grid"><div class="detail-box"><h3>Le repère avant-match</h3><p>Tirs moyens : ${fmt(p.shots_h)} / ${fmt(p.shots_a)}<br>Cadrés moyens : ${fmt(p.sot_h)} / ${fmt(p.sot_a)}<br>Buts marqués : ${fmt(p.gf_h)} / ${fmt(p.gf_a)}<br>Buts encaissés : ${fmt(p.ga_h)} / ${fmt(p.ga_a)}<br>Échantillon : ${fmt(p.n_h)} / ${fmt(p.n_a)}</p><p>H/A désigne les équipes de cette rencontre, pas nécessairement un échantillon séparé par localisation.</p></div><div class="detail-box"><h3>La collecte</h3><p>Dernier relevé : ${esc(time(r.received_at))}<br>État : ${esc(r.state==='HT'?'Pause':r.state==='FT'?'Terminé':r.state==='LIVE'?fmt(r.minute)+'′':r.state)} · score ${fmt(r.score?.h)}–${fmt(r.score?.a)}<br>Identifiant : ${esc(r.packball_id||'Association en attente')}<br>Tirs : ${fmt(r.stats?.shots?.h)} / ${fmt(r.stats?.shots?.a)}<br>Cadrés : ${fmt(r.stats?.sot?.h)} / ${fmt(r.stats?.sot?.a)}<br>Possession % : ${fmt(r.stats?.possession?.h)} / ${fmt(r.stats?.possession?.a)}<br>Tirs / 10′ : ${fmt(r.ind10?.shots10?.h)} / ${fmt(r.ind10?.shots10?.a)}<br>Cadrés / 10′ : ${fmt(r.ind10?.sot10?.h)} / ${fmt(r.ind10?.sot10?.a)}<br>Expulsions : ${fmt(r.stats?.red_cards?.h)} / ${fmt(r.stats?.red_cards?.a)}<br>Jaunes : ${fmt(r.stats?.yellow_cards?.h)} / ${fmt(r.stats?.yellow_cards?.a)}<br>Fautes : ${fmt(r.stats?.fouls?.h)} / ${fmt(r.stats?.fouls?.a)}</p><p class="help">Actualisation automatique toutes les 15 secondes. « — » signifie donnée absente, pas zéro.</p></div><div class="detail-box"><h3>Cotes reçues · information uniquement</h3><p>Les alertes de buts et cartons reposent sur les statistiques. Vérifie toi-même le marché et la cote chez ton bookmaker.</p><p>${(r.quotes||[]).filter(q=>q.verified&&['team_goals','team_cards'].includes(q.market)).map(q=>`${q.team==='h'?'D':'E'} · ${q.market==='team_cards'?'Cartons':'Buts'} ${esc(q.period)} · ${esc(q.side)} ${fmt(q.line)} @ ${fmt(q.odds)}`).join('<br>')||'Aucune cote reçue : cela ne bloque aucune alerte statistique.'}</p></div></div>${(data.health?.issues||[]).filter(x=>String(x.match_id)===String(r.packball_id)).map(x=>`<p class="help">${esc(x.detail)}</p>`).join('')}<div class="decision-list">${(r.decisions||[]).map(d=>{const e=effective(r,d);return `<article class="decision-detail"><div class="eyebrow">${d.team==='h'?'DOMICILE':'EXTÉRIEUR'}</div><h3>${esc(marketNames[d.market])}</h3><span class="state-chip ${esc(e.status)}">${esc(states[e.status]||e.status)}</span><p>${esc(e.reason)}</p><div class="meter"><i style="width:${Math.max(0,Math.min(100,d.intensity||0))}%"></i></div><small class="help">Intensité ${fmt(d.intensity)} / 100 · seuil ${fmt(d.threshold)}</small><div class="metric-lines">${metrics(d)}</div><p>${(d.context_notes||[]).map(esc).join('<br>')}</p></article>`}).join('')||'<p class="help">La lecture détaillée apparaîtra à réception du live.</p>'}</div><div class="detail-context"><h3>Le dossier de l’analyste</h3>${contextHTML(r.analyst)}</div>`;if(!$('#detail-modal').open)$('#detail-modal').showModal();
}
function showSignal(id){delete $('#detail-modal').dataset.match;const s=visibleSignals().find(x=>x.id===id);if(!s)return;const z=s.data,d=z.decision||{};$('#detail-content').innerHTML=`<div class="eyebrow">SIGNAL CONSERVÉ #${s.id}</div><h2>${esc(z.home)} — ${esc(z.away)}</h2><p>${esc(z.label)} · <b>${priceName(s)}</b></p><p class="help">${esc(time(s.created_at))} · ${esc(z.minute)}′ · score ${esc(z.score?.h)}–${esc(z.score?.a)} · ${noOdds(s)?(z.stake_quote?'Statistiques Packball · cote Stake indicative':'Statistiques Packball · sans cote'):'bet365 via Packball'}</p><div class="detail-box"><h3>Pourquoi ce signal ?</h3><p>Deux relevés concordants, une intensité de ${fmt(d.intensity)}/100. ${noOdds(s)?'Alerte statistique : aucun avantage de prix ni rendement calculé.':'Cote du marché exact vérifiée.'} Cette intensité n’est pas une probabilité.</p><div class="metric-lines">${metrics(d)}</div><p>${(d.context_notes||[]).map(esc).join('<br>')}</p></div>${stakeQuoteHTML(z)}${reviewHTML(z.review)}<div class="detail-context">${contextHTML(z.context)}</div><p>Telegram : ${esc(deliveries[s.delivery]||s.delivery)} · ${esc(s.delivery_error||'')}<br>Résultat : ${esc(resultName(s))}</p><button class="button primary" data-settle="${s.id}">Corriger le résultat</button>`;$('#detail-modal').showModal()}
function showSettings(){const feed=data.feed||{};$('#telegram-enabled').checked=!!data.settings.telegram_enabled;const marketCoverage=new Set(data.matches.flatMap(r=>(r.quotes||[]).filter(q=>q.verified&&q.odds>1).map(q=>q.market+' '+q.period)));$('#diagnostics').innerHTML=`<div class="diag">Dernier collecteur reçu<b>${esc(feed.collector_version||'Aucun reçu')}</b>${esc(feed.rows??0)} lignes · ${esc(time(feed.received_at))}</div><div class="diag">Moteur<b>${esc(data.engine?.version||'En attente')}</b>${esc(time(data.engine?.at))}</div><div class="diag">Telegram<b>${data.telegram.configured?'Clés présentes':'Configuration manquante'}</b>Le bouton de test vérifie la livraison réelle.</div><div class="diag">Marchés reçus<b>${marketCoverage.size} types / périodes</b>${[...marketCoverage].map(esc).join('<br>')||'Aucune cote reçue'}</div>`;$('#settings-result').textContent='';renderPulse();$('#settings-modal').showModal();loadCollector().catch(()=>{})}
function settleOpen(id){const s=visibleSignals().find(x=>x.id===id);if(!s)return;const f=$('#settle-form');f.reset();for(const o of f.elements.outcome.options)o.textContent=(noOdds(s)?statisticalOutcomes:outcomes)[o.value]||o.value;f.elements.id.value=id;f.elements.outcome.value=s.outcome==='pending'?(s.data.suggested_outcome||'pending'):s.outcome;$('#settle-label').textContent=s.data.home+' — '+s.data.away+' · '+s.data.label;$('#settle-modal').showModal()}
document.addEventListener('click',e=>{const b=e.target.closest('button');if(!b)return;if(b.dataset.close)$('#'+b.dataset.close).close();if(b.dataset.view){view=b.dataset.view;document.querySelectorAll('[data-view]').forEach(x=>x.classList.toggle('active',x===b));render()}if(b.dataset.market){market=b.dataset.market;document.querySelectorAll('[data-market]').forEach(x=>x.classList.toggle('selected',x===b));render()}if(b.dataset.match)showMatch(b.dataset.match);if(b.dataset.signal)showSignal(+b.dataset.signal);if(b.dataset.settle)settleOpen(+b.dataset.settle)});
$('#open-packball').onclick=()=>$('#packball-modal').showModal();$('#open-analyst').onclick=()=>$('#analyst-modal').showModal();$('#settings-open').onclick=showSettings;$('#refresh').onclick=refresh;$('#search').oninput=render;

let collectorBundle=null,collectorLoading=null;
function collectorVersionStatus(){
 if(!collectorBundle)return;
 const received=data.feed?.collector_version;
 $('#collector-version').textContent=`Disponible : ${collectorBundle.version} · Dernier collecteur reçu : ${received||'aucun'}. `+(received===collectorBundle.version?'Cette version a bien envoyé un relevé.':received?'Les versions diffèrent. Mets à jour le script, puis attends son prochain relevé.':'La version installée sera confirmée après son premier relevé.');
 $('#collector-download').textContent='Télécharger le fichier '+collectorBundle.version;
 $('#collector-copy').textContent='Copier le code '+collectorBundle.version;
}
async function loadCollector(){
 if(collectorBundle){collectorVersionStatus();return collectorBundle}
 if(collectorLoading)return collectorLoading;
 collectorLoading=(async()=>{
  const controller=new AbortController(),timer=setTimeout(()=>controller.abort(),12000);
  try{
   const response=await fetch('download.php?file=collector',{credentials:'same-origin',cache:'no-store',signal:controller.signal});
   const code=await response.text();
   if(!response.ok)throw Error('Collecteur indisponible (HTTP '+response.status+'). Recharge la page et réessaie.');
   const version=code.match(/^\/\/\s*@version\s+(\d+\.\d+\.\d+)\s*$/m)?.[1];
   if(!code.startsWith('// ==UserScript==')||!code.includes('// ==/UserScript==')||!version)throw Error('Le serveur ne renvoie pas le collecteur attendu. Reconnecte-toi au panel, puis réessaie.');
   collectorBundle={code,version};collectorVersionStatus();return collectorBundle;
  }catch(e){const message=e.name==='AbortError'?'Le chargement du collecteur a dépassé 12 secondes. Réessaie.':e.message;$('#collector-version').textContent=message;throw Error(message)}
  finally{clearTimeout(timer)}
 })();
 try{return await collectorLoading}finally{collectorLoading=null}
}
$('#collector-open').onclick=showSettings;
$('#collector-copy').onclick=async()=>{
 const b=$('#collector-copy');b.disabled=true;
 try{
  const {code,version}=await loadCollector();
  $('#collector-code').value=code;$('#collector-code-label').hidden=false;
  try{await navigator.clipboard.writeText(code);$('#collector-result').textContent=`Code ${version} copié. Colle-le dans le script Tampermonkey existant, puis Ctrl+S et recharge Packball.`}
  catch{$('#collector-code').focus();$('#collector-code').select();$('#collector-result').textContent='Copie automatique bloquée par le navigateur. Le code complet est sélectionné ci-dessous : Ctrl+C, puis colle-le dans Tampermonkey.'}
 }catch(e){$('#collector-result').textContent=e.message}finally{b.disabled=false}
};
$('#collector-download').onclick=async()=>{
 const b=$('#collector-download');b.disabled=true;
 try{
  const {code,version}=await loadCollector(),url=URL.createObjectURL(new Blob([code],{type:'text/javascript;charset=utf-8'}));
  const a=document.createElement('a');a.href=url;a.download='live90-packball.user.js';document.body.appendChild(a);a.click();a.remove();setTimeout(()=>URL.revokeObjectURL(url),60000);
  $('#collector-result').textContent=`Téléchargement demandé pour la version ${version}. Si aucun fichier n’apparaît, utilise « Copier le code » juste à côté.`;
 }catch(e){$('#collector-result').textContent=e.message}finally{b.disabled=false}
};

$('#packball-day').value=new Intl.DateTimeFormat('sv-SE',{timeZone:'Europe/Paris'}).format(new Date());
$('#packball-files').onchange=e=>{const fs=[...e.target.files],dates=fs.map(f=>f.name.match(/(\d{2})-(\d{2})-(\d{4})/)).filter(Boolean);if(dates.length===2&&dates[0][0]===dates[1][0])$('#packball-day').value=`${dates[0][3]}-${dates[0][2]}-${dates[0][1]}`;$('#packball-preview').textContent=fs.map(f=>f.name).join('\n')};
$('#packball-save').onclick=async()=>{const b=$('#packball-save');b.disabled=true;try{const files=[...$('#packball-files').files];if(files.length!==2)throw Error('Choisis exactement les deux exports complémentaires Packball.');if(files.some(f=>f.size>900000))throw Error('Chaque fichier est limité à 900 Ko.');const tables=pbOrderTables(await Promise.all(files.map(async f=>parseCSV(await f.text()))));const matches=pbJoin(...tables,$('#packball-day').value,'Europe/Paris');const bundle={schema:'stratedge.packball.v4',exported_at:new Date().toISOString(),timezone:'Europe/Paris',headers:{export_a:tables[0][0],export_b:tables[1][0]},matches};const x=await request({action:'packball',bundle});$('#packball-preview').textContent=x.duplicate?'Dossier déjà enregistré.':`${x.imported} profils importés. ${x.preserved} profils avant-match conservés. ${x.late} matchs déjà commencés : visibles, sans antidatation.`;const notes=[...new Set(matches.flatMap(m=>m.issues||[]))];if(notes.length)$('#packball-preview').textContent+='\nÀ vérifier : '+notes.join(' · ');await refresh();notice('Packball importé. Clique sur 2 · Exporter pour GPT pour préparer ton analyse.')}catch(e){$('#packball-preview').textContent=e.message}finally{b.disabled=false}};
$('#scenario-file').onchange=e=>{analystFiles=[...(e.target.files||[])];$('#analyst-preview').textContent=analystFiles.map(f=>f.name).join('\n')};
$('#analyst-save').onclick=async()=>{const b=$('#analyst-save');b.disabled=true;const done=[];try{if(!analystFiles.length)throw Error('Choisis le fichier JSON fourni par la conversation V4.');for(const f of analystFiles){if(f.size>1900000)throw Error('Dossier limité à 1,9 Mo : '+f.name);let bundle;try{bundle=JSON.parse(await f.text())}catch{throw Error('JSON illisible : '+f.name)}if(bundle.schema!=='stratedge.context.v4')throw Error('Ce dossier utilise un ancien format. Télécharge le nouveau prompt V4 et demande un fichier stratedge.context.v4.');const x=await request({action:'analyst',bundle});done.push(f.name+' : '+(x.duplicate?'déjà importé':`${x.imported} contextes, ${x.preserved} versions avant-match conservées`));$('#analyst-preview').textContent=done.join('\n')}await refresh();notice('Contextes importés : tous les matchs restent sous surveillance.')}catch(e){$('#analyst-preview').textContent=[...done,e.message].join('\n')}finally{b.disabled=false}};
async function exportAnalysis(){
 const buttons=[$('#export-analysis'),$('#packball-export')],result=$('#packball-export-result');
 if(buttons.some(b=>b.disabled))return;
 buttons.forEach(b=>b.disabled=true);result.textContent='Préparation du fichier pour GPT…';
 try{
  const x=await request({action:'export_analysis'});
  if(!x.matches?.length)throw Error('Importe les CSV Packball pour préparer le fichier des matchs à transmettre à GPT.');
  download('StratEdge-a-analyser-V4.json',x);
  const message=`Téléchargement lancé : ${x.matches.length} matchs dans StratEdge-a-analyser-V4.json. À joindre à ta conversation avec le prompt analyste V4.`;
  result.textContent=message;notice(message);
 }catch(e){result.textContent=e.message;notice(e.message)}
 finally{buttons.forEach(b=>b.disabled=false)}
}
$('#export-analysis').onclick=exportAnalysis;
$('#packball-export').onclick=exportAnalysis;
$('#export-review').onclick=async()=>{const b=$('#export-review');b.disabled=true;try{download('StratEdge-bilan-pour-GPT.json',await request({action:'export_review'}));notice('Bilan téléchargé : joins ce JSON à une conversation GPT. Les consignes d’audit sont incluses.')}catch(e){notice(e.message)}finally{b.disabled=false}};
$('#export-history').onclick=async()=>{try{download('StratEdge-historique-V4.json',await request({action:'export_history'}))}catch(e){notice(e.message)}};
$('#history-more').onclick=async()=>{try{const x=await request({action:'history',offset:visibleSignals().length});moreHistory=[...visibleSignals(),...x.signals];render()}catch(e){notice(e.message)}};
function stakeQuoteHTML(z){
 const q=z.stake_quote,l=z.stake_lookup;if(!q&&!l)return '';
 return `<div class="detail-box"><h3>Cote Stake indicative</h3>${q?`<p>${esc(q.market_name)} · Over ${esc(q.line)} · ${fmt(q.odds)}<br>Reçue le ${esc(time(q.received_at))} · ${esc(q.source)}<br>Prix conservé au signal, à vérifier sur stake.bet. Fraîcheur du flux source non garantie. Aucun rendement calculé à partir de cette cote indicative.${q.settlement_note?'<br>'+esc(q.settlement_note):''}</p>`:`<p>${esc(l?.reason||'Consultation inachevée')} · L’alerte statistique reste indépendante.</p>`}</div>`;
}
function pulseReadingHTML(m){
 const r=m.reading;if(!r)return '';
 if(!r.recognized)return r.reason?`<p class="help">Non associé : ${esc(r.reason)}</p>`:'';
 return `<p><strong>${r.team==='h'?'Domicile':'Extérieur'} · ${r.market==='card_ft'?'cartons Bookings sur le match entier':r.period==='HT'?'buts avant la pause':'buts sur le match entier'}</strong>${r.period_corrected?'<br>Période corrigée d’après le libellé « Half Time » ; le fournisseur indique FULL_TIME.':''}</p>`+
 (r.selections||[]).map(s=>`<p>${s.compatible_line?(r.market==='card_ft'?'Ligne en demi-carton':'Ligne en demi-but'):'Ligne asiatique / entière'} · +${esc(s.line)} · ${esc(s.note)}${!s.active_confirmed?' · Disponibilité non confirmée ou marché suspendu':''}</p>`).join('');
}
function renderPulse(){
 const p=data.pulsescore||{},r=p.last_test;
 $('#pulse-auto').checked=p.auto_enabled!==false;
 $('#pulse-status').textContent=`${p.configured?'Clé enregistrée':'Aucune clé enregistrée'} · ${p.attempts_31d||0} / ${p.local_limit||500} tentatives locales sur 31 jours · Auto aujourd’hui : ${p.auto_today||0} / ${p.auto_daily_limit||10} (Paris)`;
 $('#pulse-test').disabled=!p.configured||p.attempts_31d>=500;
 $('#pulse-remove').disabled=!p.configured;
 $('#pulse-report').innerHTML=!r?'':!r.ok?`<p class="help">Dernier test · ${esc(time(r.at))} · ${esc(r.error)}</p>`:
 `<p class="help">Réponse reçue le ${esc(time(r.at))} · ${esc(r.returned)} matchs sur cette page · total annoncé ${esc(r.total)}.${r.has_next_page?' D’autres pages existent ; elles ne sont pas téléchargées.':''} Il s’agit d’un instantané de test, pas de cotes actualisées. Les marchés reconnus sont annotés ci-dessous. Les prix sur stake.bet restent à vérifier ; aucune cote de ce test ne déclenche ni ne bloque une alerte. Aperçu limité à 200 marchés et 6 sélections par marché.</p>`+
 (r.events||[]).map(e=>`<details><summary>${esc(e.home)} — ${esc(e.away)} · ${esc(e.market_count)} marchés</summary>${e.markets.map(m=>`<div class="pulse-market"><b>${esc(m.name)}</b> · ${esc(m.period)}${m.active===false?' · Suspendu':''}<small>${esc(m.canonical)}</small>${pulseReadingHTML(m)}<p>${m.selections.map(o=>`${esc(o.name)}${o.line!==null?' · ligne '+esc(o.line):''} : ${fmt(o.odds)}${o.active===false?' (suspendu)':''}`).join('<br>')||'Aucune sélection interprétable'}</p></div>`).join('')}</details>`).join('')+(r.returned===0?'<p>Aucun match live renvoyé : cela ne permet pas encore de vérifier les marchés.</p>':'');
}
$('#pulse-auto').onchange=async()=>{
 const b=$('#pulse-auto');b.disabled=true;
 try{const x=await request({action:'pulsescore_auto',enabled:b.checked});data.pulsescore=x.pulsescore;renderPulse();$('#pulse-message').textContent=b.checked?'Consultation ponctuelle activée, uniquement après un signal de but ou de carton confirmé.':'Consultation automatique désactivée. Les alertes statistiques continuent.'}
 catch(err){b.checked=!b.checked;$('#pulse-message').textContent=err.message}finally{b.disabled=false}
};
$('#pulse-form').onsubmit=async e=>{
 e.preventDefault();const b=$('#pulse-save');b.disabled=true;
 try{const x=await request({action:'pulsescore_key',key:$('#pulse-key').value.trim()});$('#pulse-key').value='';data.pulsescore=x.pulsescore;renderPulse();$('#pulse-message').textContent='Clé enregistrée. Aucune requête consommée.'}
 catch(err){$('#pulse-message').textContent=err.message}finally{b.disabled=false}
};
$('#pulse-remove').onclick=async()=>{
 const b=$('#pulse-remove');b.disabled=true;
 try{const x=await request({action:'pulsescore_remove'});$('#pulse-key').value='';data.pulsescore=x.pulsescore;renderPulse();$('#pulse-message').textContent='Clé retirée. Le compteur est conservé.'}
 catch(err){$('#pulse-message').textContent=err.message;b.disabled=false}
};
$('#pulse-test').onclick=async()=>{
 const b=$('#pulse-test');b.disabled=true;$('#pulse-message').textContent='Test en cours · une seule requête…';
 try{const x=await request({action:'pulsescore_test'});data.pulsescore=x.pulsescore;renderPulse();$('#pulse-message').textContent=x.ok?'Connexion réussie. Ouvre les matchs ci-dessous pour consulter les marchés.':x.pulsescore.last_test.error}
 catch(err){$('#pulse-message').textContent=err.message}finally{b.disabled=!data.pulsescore?.configured||data.pulsescore?.attempts_31d>=500}
};
$('#settings-form').onsubmit=async e=>{e.preventDefault();try{await request({action:'settings',settings:{telegram_enabled:$('#telegram-enabled').checked,min_odds:data.settings.min_odds??1.65}});$('#settings-result').textContent='Paramètres enregistrés.';await refresh()}catch(err){$('#settings-result').textContent=err.message}};
$('#telegram-test').onclick=async()=>{const b=$('#telegram-test');b.disabled=true;try{const x=await request({action:'telegram_test'});$('#settings-result').textContent=x.ok?'Test envoyé : vérifie ton canal Telegram.':`${deliveries[x.state]||x.state} : ${x.error||'Vérifier la configuration'}`}catch(e){$('#settings-result').textContent=e.message}finally{b.disabled=false}};
$('#settle-form').onsubmit=async e=>{e.preventDefault();try{const f=e.target.elements;const x=await request({action:'settle',id:Number(f.id.value),outcome:f.outcome.value,source:f.source.value.trim()});$('#settle-modal').close();moreHistory=[];await refresh();notice(x.unchanged?'Résultat déjà enregistré : aucune nouvelle notification.':x.result_delivery==='queued'?'Résultat enregistré. Notification Telegram au prochain passage du moteur.':'Résultat enregistré. Envoi Telegram désactivé.')}catch(err){notice(err.message)}};
setInterval(()=>{$('#clock').textContent=new Date().toLocaleTimeString('fr-FR',{timeZone:'Europe/Paris'})},1000);
setInterval(refresh,15000);refresh();
