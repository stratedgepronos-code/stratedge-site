'use strict';
const $=s=>document.querySelector(s);
const esc=v=>String(v??'—').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const fmt=v=>typeof v==='number'?v.toLocaleString('fr-FR',{maximumFractionDigits:2}):'—';
const time=v=>v?new Date(v).toLocaleString('fr-FR',{timeZone:'Europe/Paris',day:'2-digit',month:'2-digit',hour:'2-digit',minute:'2-digit'}):'—';
const marketNames={goal_ht:'But avant la pause',goal_ft:'But avant la fin',card_ft:'Un carton supplémentaire'};
const states={waiting:'En attente',watch:'Sous surveillance',missing:'Donnée manquante',stale:'Collecte arrêtée',suspended:'Expulsion',covered:'Déjà atteint',price:'Cote à vérifier',candidate:'Concordant',confirming:'Confirmation',signal:'Signal enregistré'};
const deliveries={sent:'Envoyé',failed:'Échec certain',uncertain:'Livraison inconnue',filtered:'Cote non éligible',expired:'Expiré',queued:'En attente',sending:'En cours',disabled:'Désactivé',superseded:'Remplacé par une correction'};
const statisticalOutcomes={won:'Événement observé',lost:'Non observé',void:'Observation annulée',pending:'À confirmer'};
const noOdds=s=>s.odds===null;
const resultName=s=>(noOdds(s)?statisticalOutcomes:outcomes)[s.outcome]||s.outcome;
const priceName=s=>noOdds(s)?(s.data?.stake_quote?.odds?fmt(s.data.stake_quote.odds)+'*':'Sans cote'):fmt(s.odds);
const outcomes={won:'Gagnant',lost:'Perdant',void:'Annulé',pending:'À confirmer'};
const metricNames={referee_yellow_per_match:'Arbitre · jaunes / match',referee_fouls_per_match:'Arbitre · fautes / match',referee_sample:'Arbitre · matchs étudiés',shots:'Tirs cumulés',sot:'Cadrés cumulés',shots10:'Tirs / 10′',sot10:'Cadrés / 10′',shots5:'Tirs / 5′',sot5:'Cadrés / 5′',activity_ratio:'Rythme / avant-match',baseline_shots:'Tirs moyens / match',sample:'Échantillon',yellow_cards:'Jaunes',fouls:'Fautes cumulées',fouls10:'Fautes / 10′',possession:'Possession %',opponent_shots10:'Tirs adverses / 10′'};
let data={matches:[],signals:[],settings:{telegram_enabled:true,min_odds:1.65},totals:{},feed:{},telegram:{}},view='live',market='all',busy=false,moreHistory=[],analystFiles=[];
function notice(msg){$('#notice').textContent=msg;$('#notice').hidden=false}
async function request(payload,options={}){
 if(window.S90_DEMO){if(payload&&payload.action!=='board')throw Error('Aperçu visuel : action désactivée');return window.S90_DEMO}
 const controller=options.timeoutMs&&typeof AbortController==='function'?new AbortController():null;
 const timer=controller?setTimeout(()=>controller.abort(),options.timeoutMs):null;
 try{
  const r=await fetch('api.php',{method:payload?'POST':'GET',credentials:'same-origin',headers:payload?{'Content-Type':'application/json','X-CSRF-Token':$('meta[name=csrf-token]')?.content||''}:{},body:payload?JSON.stringify(payload):undefined,...(controller?{signal:controller.signal}:{})});
  let x;try{x=await r.json()}catch(e){if(e.name==='AbortError')throw e;throw Error(`Réponse illisible du serveur${r.status?' (HTTP '+r.status+')':''}. Recharge la page si ta session a expiré.`)}
  if(!r.ok||x.error&&x.ok!==false)throw Error(x.error||'Réponse invalide');return x;
 }catch(e){if(e.name==='AbortError')throw Error('L’export prend trop de temps. Réessaie ; les alertes live continuent.');throw e}
 finally{if(timer!==null)clearTimeout(timer)}
}
function download(name,x,result=null){
 const url=URL.createObjectURL(new Blob([JSON.stringify(x,null,2)],{type:'application/json;charset=utf-8'}));
 const a=document.createElement('a');a.href=url;a.download=name;
 if(result){a.textContent='Télécharger le JSON';a.className='button';result.appendChild(a)}
 else{a.hidden=true;document.body.appendChild(a)}
 try{a.click()}finally{if(!result)a.remove()}
 setTimeout(()=>{URL.revokeObjectURL(url);if(result&&a.isConnected){a.removeAttribute('href');a.textContent='Lien expiré : relance l’export.'}},result?600000:60000);
}
async function exportReport(buttonId,resultId,action,schema,name){
 const button=$(buttonId),result=$(resultId),label=button.textContent;
 if(button.disabled)return;
 button.disabled=true;button.textContent='Préparation…';result.hidden=false;result.textContent='Préparation du fichier…';
 try{
  const x=await request({action},{timeoutMs:45000});
  if(x?.schema!==schema)throw Error('Le serveur n’a pas renvoyé le bon fichier. Recharge la page puis réessaie.');
  result.textContent='Fichier prêt. Si le téléchargement ne démarre pas, clique ici : ';
  download(name,x,result);notice('Fichier prêt : '+name+'. Le lien de téléchargement reste disponible sous le bouton.');
 }catch(e){result.textContent=e.message;notice(e.message)}
 finally{button.disabled=false;button.textContent=label}
}
function referenceNow(){return (Date.parse(data.server_time)||boardReceivedAt)+(Date.now()-boardReceivedAt)}
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
 const currentMatches=data.matches.filter(r=>!tableFinished(r)&&(view!=='live'||(deskLayout==='table'?r.on_packball_page!==false:r.in_analysis_program!==false)));
 const feedAge=age(data.feed?.received_at),engineAge=age(data.engine?.at),live=currentMatches.filter(r=>['LIVE','HT'].includes(r.state)&&age(r.received_at)<=100);
 $('#feed-title').textContent=feedAge<=100?'Collecte connectée':'Collecte en attente';$('#feed-dot').classList.toggle('connected',feedAge<=100);
 $('#feed-detail').textContent=data.feed?.received_at?`${data.feed.rows??'—'} lignes reçues · ${time(data.feed.received_at)} · ${Math.round(feedAge)} s`:'Ouvre Packball avec le collecteur activé';
 const outside=currentMatches.filter(r=>r.in_analysis_program===false).length;
 if(outside)$('#feed-detail').textContent+=` · ${outside} matchs hors liste d’analyse : vérifier l’import des deux CSV du jour`;
 $('#engine-state').textContent=engineAge<=65?'Moteur actif · lecture toutes les 20 s':'Moteur · aucun cycle récent';$('#engine-state').style.color=engineAge<=65?'var(--cyan)':'var(--gold)';
 $('#kpi-matches').textContent=currentMatches.length;$('#kpi-live').textContent=live.length+' en cours · '+live.filter(r=>r.state==='HT').length+' à la pause';
 $('#kpi-context').textContent=currentMatches.filter(r=>r.analyst?.usable).length+' / '+currentMatches.length;
 $('#kpi-signals').textContent=currentMatches.reduce((n,r)=>n+(r.advice||[]).filter(d=>deskEffective(r,d).status==='active').length,0);$('#kpi-pending').textContent='Dynamiques concordantes à examiner';
 $('#kpi-results').textContent='Manuel';$('#kpi-units').textContent=(data.radar_telegram?.enabled?(data.radar_telegram.configured?'Radar Telegram actif':'Telegram à configurer'):'Radar Telegram arrêté')+' · aucun appel IA live';
 $('#live-counter').textContent=live.length;$('#history-counter').textContent=data.totals.total||0;
 for(const k of ['all',...Object.keys(deskNames)])$('#count-'+k).textContent=currentMatches.reduce((n,r)=>n+(r.advice||[]).filter(d=>(k==='all'||d.market===k)&&deskEffective(r,d).status==='active').length,0);
 const query=normSearch($('#search').value),matches=currentMatches.filter(r=>normSearch(`${r.home} ${r.away} ${r.league||''}`).includes(query));
 renderAttention(matches);renderCutsControl();renderLearning();
 $('#table-head').closest('table').classList.toggle('match-table',view==='live'&&deskLayout==='analysis');
 $('#table-head').closest('table').classList.toggle('pack-table',view==='live'&&deskLayout==='table');
 $('#table-controls').hidden=view!=='live';
 $('#export-history').hidden=view!=='history';$('#history-more').hidden=true;$('#legacy-history').hidden=view!=='history'||!data.legacy?.length;
 if(view==='history'){
  const rows=visibleSignals().filter(s=>(market==='all'||market==='goals'&&s.market.startsWith('goal_')||market==='cards'&&s.market==='card_ft')&&normSearch(s.data.home+' '+s.data.away).includes(query));
  $('#table-head').innerHTML='<tr><th>Rencontre / heure du signal</th><th>Marché exact</th><th>Cote</th><th>Résultat</th><th>Telegram</th><th></th></tr>';
  $('#match-rows').innerHTML=rows.map(s=>`<tr><td class="team-names">${esc(s.data.home)}<br>${esc(s.data.away)}<small>${esc(time(s.created_at))} · ${esc(s.data.minute)}′ · #${s.id}</small></td><td>${esc(s.data.label)}<small>Intensité ${esc(s.data.decision?.intensity)}/100 · observation</small></td><td class="mono">${priceName(s)}<small>${noOdds(s)?(s.data?.stake_quote?(s.data.stake_quote.unit==='stake_bookings'?'Stake Bookings · indicatif':'Stake · indicatif'):'Alerte statistique'):'bet365'}</small></td><td><button class="row-more" data-settle="${s.id}">${esc(resultName(s))}</button>${s.data.resolution?`<small>${s.data.resolution.mode==='automatic'?'Automatique · Packball':'Correction manuelle'}</small>`:''}${s.outcome==='pending'&&s.data.suggested_outcome?`<small>À vérifier : ${esc(outcomes[s.data.suggested_outcome])}</small>`:''}</td><td>Signal : ${esc(deliveries[s.delivery]||s.delivery)}<small>${esc(s.delivery_error||'')}</small>${s.result_notification?`<small>Résultat : ${esc(deliveries[s.result_notification.delivery]||s.result_notification.delivery)} · ${esc(s.result_notification.delivery_error||'')}</small>`:''}</td><td><button class="row-more" data-signal="${s.id}" aria-label="Lire le signal ${s.id}">↗</button></td></tr>`).join('');
  $('#empty').hidden=rows.length>0;$('#board-description').textContent='Chaque alerte conserve ses données et son résultat. * Cote Stake indicative conservée au signal ; les observations statistiques restent exclues du bilan financier.';
  $('#history-more').hidden=visibleSignals().length>=(data.history_total||0);
  $('#legacy-rows').innerHTML=(data.legacy||[]).map(s=>`<div>${esc(s.context?.home)} — ${esc(s.context?.away)} · ${esc(s.context?.market_label||s.market)}<br>${esc(time(s.created_at))} · cote ${fmt(s.odds)} · ${esc(resultName(s))} · ancienne version</div>`).join('');
 }else if(view==='observations'){renderObservations(query)}else{renderDesk(matches)}
 $('#signals-list').innerHTML=(data.observations||[]).slice(0,4).map(observationHTML).join('')||'<p class="help">Les prochaines mises en évidence seront conservées ici. Les cartes confirmées peuvent être envoyées sur Telegram.</p>';
 $('#updated').textContent='Actualisé · '+time(data.server_time);
}
function renderLearning(){
 const m=data.learning||{},el=$('#learning-summary');if(!el)return;
 el.textContent=m.error?'Bilan temporairement indisponible · les alertes live continuent.':m.revision?`Mémoire v${m.revision} · ${m.review_count} alertes relues · ${m.match_count} matchs · ${m.settled_count} résultats exploitables. Dernière mise à jour : ${time(m.generated_at)}.`:'En attente de résultats à relire. Aucun appel IA et aucun seuil modifié automatiquement.';
 $('#learning-prompt').textContent=m.prompt_addendum||'La prochaine mémoire sera intégrée automatiquement à ton export GPT.';
 $('#learning-reviews').innerHTML=(m.recent||[]).map(r=>`<details class="detail-box"><summary>#${esc(r.radar_id)} · ${esc(r.team==='h'?r.home:r.away)} · ${esc(r.market==='cards'?'Cartons':'Buts')} · ${esc(r.label)}</summary><ul>${(r.facts||[]).map(x=>`<li>${esc(x)}</li>`).join('')}</ul><b>Limites constatées</b><ul>${(r.flags||[]).map(x=>`<li>${esc(x.detail)}</li>`).join('')}</ul><b>À tester, sans cause présumée</b><p>${(r.hypotheses||[]).map(esc).join('<br>')}</p><p class="help">${esc(r.conclusion)} · Révision ${esc(r.revision)}</p></details>`).join('')||'<p class="help">Aucun résultat relu pour le moment.</p>';
}
function normSearch(v){return String(v||'').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase()}
async function refresh(){if(busy)return;busy=true;const requestedAt=Date.now();try{data=await request();boardReceivedAt=requestedAt;render();const modal=$('#detail-modal');if(modal.open&&modal.dataset.match){const scroll=modal.scrollTop;showMatch(modal.dataset.match);modal.scrollTop=scroll}}catch(e){notice(e.message)}finally{busy=false}}
function metrics(d){return Object.entries(d.metrics||{}).map(([k,v])=>`<span>${esc(metricNames[k]||k)} : <b>${fmt(v)}</b></span>`).join('')}
function refereeHTML(r){if(!r)return '<p>Arbitre : fiche statistique structurée absente. Une désignation peut être mentionnée dans les notes de l’analyste ci-dessous.</p>';const s=r.stats;return `<div class="detail-box"><h3>Arbitre · ${esc(r.name)}</h3><p>Désignation : ${esc(r.appointment)}<br>${esc(r.note)}</p>${s?`<p>${esc(s.sample_label)} · ${fmt(s.matches)} matchs<br>Jaunes / match : ${fmt(s.yellow_per_match)} · Rouges / match : ${fmt(s.red_per_match)}<br>Fautes / match : ${fmt(s.fouls_per_match)}</p>`:'<p>Statistiques indisponibles.</p>'}<small>Contexte descriptif ; aucune probabilité déduite de ces moyennes.</small></div>`}
function contextHTML(ctx){if(!ctx)return '<p>Contexte non importé : tous les matchs restent surveillés avec une lecture neutre.</p>';return `${refereeHTML(ctx.referee)}<p>${esc(ctx.summary)}</p>${['h','a'].map(t=>`<p><b>${t==='h'?'Domicile':'Extérieur'}</b> · ${esc(ctx.teams?.[t]?.note)}</p><ul>${(ctx.teams?.[t]?.flags||[]).map(f=>`<li>${esc(f.detail)} <small>${esc(f.certainty)} · ${esc(f.severity)}</small></li>`).join('')}</ul>`).join('')}<p>À vérifier : ${(ctx.unknowns||[]).map(esc).join(' · ')||'Aucune inconnue déclarée'}</p><p>${(ctx.sources||[]).map(s=>`<a href="${esc(s.url)}" target="_blank" rel="noopener noreferrer">${esc(s.title)} ↗</a>`).join('<br>')}</p>`}
function showMatch(id){const r=data.matches.find(m=>String(m.packball_id||m.fixture)===id);if(!r){$('#detail-modal').close();return}$('#detail-modal').dataset.match=id;const p=r.profile?.prematch||{};
 $('#detail-content').innerHTML=`<div class="eyebrow">LECTURE DU MATCH</div><h2>${esc(r.home)} — ${esc(r.away)}</h2><p class="help">${esc(time(r.kickoff_ts))} · ${esc(r.league||'')} · buts et cartons · lecture manuelle</p><div class="detail-grid"><div class="detail-box"><h3>Le repère avant-match</h3><p>Tirs moyens : ${fmt(p.shots_h)} / ${fmt(p.shots_a)}<br>Cadrés moyens : ${fmt(p.sot_h)} / ${fmt(p.sot_a)}<br>Buts marqués : ${fmt(p.gf_h)} / ${fmt(p.gf_a)}<br>Buts encaissés : ${fmt(p.ga_h)} / ${fmt(p.ga_a)}<br>Échantillon : ${fmt(p.n_h)} / ${fmt(p.n_a)}</p><p>H/A désigne les équipes de cette rencontre, pas nécessairement un échantillon séparé par localisation.</p></div><div class="detail-box"><h3>La collecte</h3><p>Dernier relevé : ${esc(time(r.received_at))}<br>État : ${esc(r.state==='HT'?'Pause':r.state==='FT'?'Terminé':r.state==='LIVE'?fmt(r.minute)+'′':r.state)} · score ${fmt(r.score?.h)}–${fmt(r.score?.a)}<br>Identifiant : ${esc(r.packball_id||'Association en attente')}<br>Tirs : ${fmt(r.stats?.shots?.h)} / ${fmt(r.stats?.shots?.a)}<br>Cadrés : ${fmt(r.stats?.sot?.h)} / ${fmt(r.stats?.sot?.a)}<br>Possession % : ${fmt(r.stats?.possession?.h)} / ${fmt(r.stats?.possession?.a)}<br>Tirs / 10′ : ${fmt(r.ind10?.shots10?.h)} / ${fmt(r.ind10?.shots10?.a)}<br>Cadrés / 10′ : ${fmt(r.ind10?.sot10?.h)} / ${fmt(r.ind10?.sot10?.a)}<br>Tirs bloqués : ${fmt(r.stats?.blocked_shots?.h)} / ${fmt(r.stats?.blocked_shots?.a)}<br>Tirs dans la surface : ${fmt(r.stats?.shots_inside?.h)} / ${fmt(r.stats?.shots_inside?.a)}<br>Remplacements : ${fmt(r.stats?.substitutions?.h)} / ${fmt(r.stats?.substitutions?.a)}<br>Expulsions : ${fmt(r.stats?.red_cards?.h)} / ${fmt(r.stats?.red_cards?.a)}<br>Jaunes : ${fmt(r.stats?.yellow_cards?.h)} / ${fmt(r.stats?.yellow_cards?.a)}<br>Fautes : ${fmt(r.stats?.fouls?.h)} / ${fmt(r.stats?.fouls?.a)}</p><p class="help">Actualisation automatique toutes les 15 secondes. « — » signifie donnée absente, pas zéro.</p></div><div class="detail-box"><h3>Cotes reçues · information uniquement</h3><p>Lecture statistique indépendante des prix. Une activité intéressante ne démontre pas un avantage de cote.</p><p>${(r.quotes||[]).filter(q=>q.verified&&['team_goals','team_cards'].includes(q.market)).map(q=>`${q.team==='h'?'D':'E'} · ${q.market==='team_cards'?'Cartons':'Buts'} ${esc(q.period)} · ${esc(q.side)} ${fmt(q.line)} @ ${fmt(q.odds)}`).join('<br>')||'Aucune cote reçue ; la lecture statistique reste disponible.'}</p></div></div>${(data.health?.issues||[]).filter(x=>String(x.match_id)===String(r.packball_id)).map(x=>`<p class="help">${esc(x.detail)}</p>`).join('')}${cutDetailHTML(r)}<div class="decision-list">${deskDetail(r)}</div><div class="detail-context"><h3>Le dossier de l’analyste</h3>${contextHTML(r.analyst)}</div>`;if(!$('#detail-modal').open)$('#detail-modal').showModal();
}
function showSignal(id){delete $('#detail-modal').dataset.match;const s=visibleSignals().find(x=>x.id===id);if(!s)return;const z=s.data,d=z.decision||{};$('#detail-content').innerHTML=`<div class="eyebrow">SIGNAL CONSERVÉ #${s.id}</div><h2>${esc(z.home)} — ${esc(z.away)}</h2><p>${esc(z.label)} · <b>${priceName(s)}</b></p><p class="help">${esc(time(s.created_at))} · ${esc(z.minute)}′ · score ${esc(z.score?.h)}–${esc(z.score?.a)} · ${noOdds(s)?(z.stake_quote?'Statistiques Packball · cote Stake indicative':'Statistiques Packball · sans cote'):'bet365 via Packball'}</p><div class="detail-box"><h3>Pourquoi ce signal ?</h3><p>Deux relevés concordants, une intensité de ${fmt(d.intensity)}/100. ${noOdds(s)?'Alerte statistique : aucun avantage de prix ni rendement calculé.':'Cote du marché exact vérifiée.'} Cette intensité n’est pas une probabilité.</p><div class="metric-lines">${metrics(d)}</div><p>${(d.context_notes||[]).map(esc).join('<br>')}</p></div>${stakeQuoteHTML(z)}${reviewHTML(z.review)}<div class="detail-context">${contextHTML(z.context)}</div><p>Telegram : ${esc(deliveries[s.delivery]||s.delivery)} · ${esc(s.delivery_error||'')}<br>Résultat : ${esc(resultName(s))}</p><button class="button primary" data-settle="${s.id}">Corriger le résultat</button>`;$('#detail-modal').showModal()}
let settingsLeagues=null;
function cardSelectionNotice(){const n=$('#card-leagues').value.split('\n').filter(v=>v.trim()).length;$('#card-selection-status').textContent=n?`${n} compétition(s) sélectionnée(s). Enregistre pour appliquer.`:'Aucune compétition : les recommandations cartons seront désactivées.';$('#disable-cards-label').hidden=n>0;if(n)$('#disable-cards').checked=false}
function showSettings(){const feed=data.feed||{};settingsLeagues=Array.isArray(data.radar_telegram?.card_leagues)?[...data.radar_telegram.card_leagues]:null;$('#telegram-enabled').checked=data.radar_telegram?.enabled===true;$('#radar-priced-only').checked=data.radar_telegram?.priced_only!==false;$('#radar-odds-auto').checked=data.radar_telegram?.odds_auto!==false;$('#card-leagues').value=(settingsLeagues||[]).join('\n');$('#disable-cards').checked=false;cardSelectionNotice();if(settingsLeagues===null)$('#card-selection-status').textContent='Sélection non reçue : recharge la page avant d’enregistrer.';$('#card-league-names').textContent='Noms Packball disponibles : '+[...new Set(data.matches.map(r=>r.league).filter(Boolean))].join(' · ');const marketCoverage=new Set(data.matches.flatMap(r=>(r.stake_cuts?.quotes||[]).filter(q=>q.odds>1).map(q=>q.kind+' '+q.period)));$('#diagnostics').innerHTML=`<div class="diag">Dernier collecteur reçu<b>${esc(feed.collector_version||'Aucun reçu')}</b>${esc(feed.rows??0)} lignes · ${esc(time(feed.received_at))}</div><div class="diag">Moteur<b>${esc(data.engine?.version||'En attente')}</b>${esc(time(data.engine?.at))}</div><div class="diag">Telegram<b>${data.telegram.configured?'Clés présentes':'Configuration manquante'}</b>${data.radar_telegram?.enabled?'Cartes confirmées : envoi activé.':'Radar Telegram désactivé.'}<br>${data.radar_telegram?.counts?.sent||0} envoyées · ${data.radar_telegram?.counts?.failed||0} refus · ${data.radar_telegram?.counts?.uncertain||0} livraisons inconnues${data.radar_telegram?.last?.delivery_error?'<br>'+esc(data.radar_telegram.last.delivery_error):''}</div><div class="diag">Marchés reçus<b>${marketCoverage.size} types / périodes</b>${[...marketCoverage].map(esc).join('<br>')||'Aucune cote reçue'}</div>`;$('#settings-result').textContent='';renderPulse();$('#settings-modal').showModal();loadCollector().catch(()=>{})}
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

function importCoverageText(x){
 const c=x.coverage;if(!c)return '';
 const parts=[`${c.ready}/${c.total} matchs avec profil et contexte avant-match utilisables dans la liste active (conditions live à vérifier)`];
 if(c.outside_program)parts.push(`${c.outside_program} hors liste active : importe les deux CSV correspondant à cette journée`);
 if(c.missing_profile)parts.push(`${c.missing_profile} sans profil CSV correspondant`);
 if(c.missing_context)parts.push(`${c.missing_context} sans contexte analyste correspondant`);
 if(c.unusable_prematch)parts.push(`${c.unusable_prematch} dossiers présents mais tardifs ou trop anciens pour les signaux`);
 return '\n'+parts.join(' · ');
}
$('#packball-day').value=new Intl.DateTimeFormat('sv-SE',{timeZone:'Europe/Paris'}).format(new Date());
$('#packball-files').onchange=e=>{const fs=[...e.target.files],dates=fs.map(f=>f.name.match(/(\d{2})-(\d{2})-(\d{4})/)).filter(Boolean);if(dates.length===2&&dates[0][0]===dates[1][0])$('#packball-day').value=`${dates[0][3]}-${dates[0][2]}-${dates[0][1]}`;$('#packball-preview').textContent=fs.map(f=>f.name).join('\n')};
$('#packball-save').onclick=async()=>{const b=$('#packball-save');b.disabled=true;try{const files=[...$('#packball-files').files];if(files.length!==2)throw Error('Choisis exactement les deux exports complémentaires Packball.');if(files.some(f=>f.size>900000))throw Error('Chaque fichier est limité à 900 Ko.');const tables=pbOrderTables(await Promise.all(files.map(async f=>parseCSV(await f.text()))));const matches=pbJoin(...tables,$('#packball-day').value,'Europe/Paris');const bundle={schema:'stratedge.packball.v4',exported_at:new Date().toISOString(),timezone:'Europe/Paris',headers:{export_a:tables[0][0],export_b:tables[1][0]},matches};const x=await request({action:'packball',bundle});$('#packball-preview').textContent=x.duplicate?'Dossier déjà enregistré.':`${x.imported} profils importés. ${x.preserved} profils avant-match conservés. ${x.late} matchs déjà commencés : visibles, sans antidatation.`;$('#packball-preview').textContent+=importCoverageText(x);const notes=[...new Set(matches.flatMap(m=>m.issues||[]))];if(notes.length)$('#packball-preview').textContent+='\nÀ vérifier : '+notes.join(' · ');await refresh();notice('Packball importé. Clique sur 2 · Exporter pour GPT pour préparer ton analyse.')}catch(e){$('#packball-preview').textContent=e.message}finally{b.disabled=false}};
$('#scenario-file').onchange=e=>{analystFiles=[...(e.target.files||[])];$('#analyst-preview').textContent=analystFiles.map(f=>f.name).join('\n')};
// The input file may be pretty-printed. Limit the UTF-8 request sent to PHP,
// including its envelope, rather than rejecting harmless formatting on disk.
function analystImportPayload(bundle,name){
 if(bundle?.schema!=='stratedge.context.v4')throw Error('Ce dossier utilise un ancien format. Télécharge le nouveau prompt V4 et demande un fichier stratedge.context.v4.');
 const payload={action:'analyst',bundle};
 if(new TextEncoder().encode(JSON.stringify(payload)).byteLength>1900000)throw Error('Dossier trop volumineux après préparation : '+name+' (limite de 1,9 Mo envoyés). Aucune donnée importée pour ce fichier.');
 return payload;
}
$('#analyst-save').onclick=async()=>{
 const b=$('#analyst-save');b.disabled=true;const done=[];
 try{
  if(!analystFiles.length)throw Error('Choisis le fichier JSON fourni par la conversation V4.');
  for(const f of analystFiles){
   if(f.size>10000000)throw Error('Fichier JSON limité à 10 Mo : '+f.name);
   let bundle;try{bundle=JSON.parse(await f.text())}catch{throw Error('JSON illisible : '+f.name)}
   const x=await request(analystImportPayload(bundle,f.name));
   done.push(f.name+' : '+(x.duplicate?'déjà importé':`${x.imported} contextes, ${x.preserved} versions avant-match conservées`)+importCoverageText(x));
   $('#analyst-preview').textContent=done.join('\n');
  }
  await refresh();notice('Import vérifié. Consulte le bilan de rattachement affiché.');
 }catch(e){$('#analyst-preview').textContent=[...done,e.message].join('\n')}finally{b.disabled=false}
};

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
$('#export-learning').onclick=()=>exportReport('#export-learning','#learning-export-result','export_learning','stratedge.learning.v1','StratEdge-memoire-pour-GPT.json');
$('#export-review').onclick=()=>exportReport('#export-review','#review-export-result','export_review','stratedge.audit.v4','StratEdge-bilan-pour-GPT.json');
$('#export-history').onclick=async()=>{try{download(view==='observations'?'StratEdge-observations-V5.json':'StratEdge-historique-V4.json',await request({action:view==='observations'?'export_observations':'export_history'}))}catch(e){notice(e.message)}};
$('#history-more').onclick=async()=>{try{const x=await request({action:'history',offset:visibleSignals().length});moreHistory=[...visibleSignals(),...x.signals];render()}catch(e){notice(e.message)}};
function stakeQuoteHTML(z){
 const q=z.stake_quote,l=z.stake_lookup;if(!q&&!l)return '';
 return `<div class="detail-box"><h3>Cote Stake indicative</h3>${q?`<p>${esc(q.market_name)} · Over ${esc(q.line)} · ${fmt(q.odds)}<br>Reçue le ${esc(time(q.received_at))} · ${esc(q.source)}<br>Prix conservé au signal, à vérifier sur stake.bet. Fraîcheur du flux source non garantie. Aucun rendement calculé à partir de cette cote indicative.${q.settlement_note?'<br>'+esc(q.settlement_note):''}</p>`:`<p>${esc(l?.reason||'Consultation inachevée')} · L’alerte statistique reste indépendante.</p>`}</div>`;
}
function pulseReadingHTML(m){
 const r=m.reading;if(!r)return '';
 if(!r.recognized)return r.reason?`<p class="help">Non associé : ${esc(r.reason)}</p>`:'';
 return `<p><strong>${r.team==='h'?'Domicile':'Extérieur'} · ${r.market==='card_ft'?'cartons Bookings sur le match entier':r.market==='corner_ft'?'corners sur le match entier':r.period==='HT'?'buts avant la pause':'buts sur le match entier'}</strong>${r.period_corrected?'<br>Période corrigée d’après le libellé « Half Time » ; le fournisseur indique FULL_TIME.':''}</p>`+
 (r.selections||[]).map(s=>`<p>${s.compatible_line?(r.market==='card_ft'?'Ligne en demi-carton':r.market==='corner_ft'?'Ligne en demi-corner':'Ligne en demi-but'):'Ligne asiatique / entière'} · +${esc(s.line)} · ${esc(s.note)}${!s.active_confirmed?' · Disponibilité non confirmée ou marché suspendu':''}</p>`).join('');
}
function renderPulse(){
 const p=data.pulsescore||{},r=p.last_test;
 $('#pulse-auto').checked=false;
 $('#pulse-status').textContent=`${p.configured?'Clé enregistrée':'Aucune clé enregistrée'} · ${p.attempts_31d||0} / ${p.local_limit||500} tentatives locales sur 31 jours · ${p.auto_enabled?'Consultation sur dynamique confirmée : '+(p.auto_today||0)+' / 10 aujourd’hui':'Consultation automatique désactivée'}`;
 $('#pulse-test').disabled=!p.configured||p.attempts_31d>=500;
 $('#pulse-remove').disabled=!p.configured;
 $('#pulse-report').innerHTML=!r?'':!r.ok?`<p class="help">Dernier test · ${esc(time(r.at))} · ${esc(r.error)}</p>`:
 `<p class="help">Réponse reçue le ${esc(time(r.at))} · ${esc(r.returned)} matchs sur cette page · total annoncé ${esc(r.total)}.${r.has_next_page?' D’autres pages existent ; elles ne sont pas téléchargées.':''} Il s’agit d’un instantané de test, pas de cotes actualisées. Les marchés reconnus sont annotés ci-dessous. Les prix sur stake.bet restent à vérifier ; le moteur peut utiliser cet instantané pour une dynamique déjà confirmée. Aperçu limité à 200 marchés et 6 sélections par marché.</p>`+
 (r.events||[]).map(e=>`<details><summary>${esc(e.home)} — ${esc(e.away)} · ${esc(e.market_count)} marchés</summary>${e.markets.map(m=>`<div class="pulse-market"><b>${esc(m.name)}</b> · ${esc(m.period)}${m.active===false?' · Suspendu':''}<small>${esc(m.canonical)}</small>${pulseReadingHTML(m)}<p>${m.selections.map(o=>`${esc(o.name)}${o.line!==null?' · ligne '+esc(o.line):''} : ${fmt(o.odds)}${o.active===false?' (suspendu)':''}`).join('<br>')||'Aucune sélection interprétable'}</p></div>`).join('')}</details>`).join('')+(r.returned===0?'<p>Aucun match live renvoyé : cela ne permet pas encore de vérifier les marchés.</p>':'');
}
$('#pulse-auto').onchange=async()=>{
 const b=$('#pulse-auto');b.disabled=true;
 try{const x=await request({action:'pulsescore_auto',enabled:b.checked});data.pulsescore=x.pulsescore;renderPulse();$('#pulse-message').textContent=b.checked?'Consultation ponctuelle activée, uniquement après un signal de but ou de carton confirmé.':'Consultation automatique désactivée. L’analyse continue sur le site ; aucun nouveau pari Telegram sans cote éligible.'}
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
let cutsBusy=false;
function renderCutsControl(){
 const p=data.pulsescore||{},elapsed=age(p.last_test?.at),cooldown=elapsed<60;
 $('#cuts-refresh').disabled=cutsBusy||!p.configured||p.attempts_31d>=500||cooldown;
 $('#cuts-state').textContent=cutsBusy?'Consultation en cours…':!p.configured?'Clé PulseScore à renseigner dans Réglages':`${p.attempts_31d||0} / ${p.local_limit||500} requêtes · ${cooldown?'nouvel appel dans '+Math.ceil(60-elapsed)+' s':p.auto_enabled?'auto sur signal · '+(p.auto_today||0)+'/10 appels aujourd’hui':'actualisation manuelle'}${p.auto_today>=10?' · plafond automatique du jour atteint':''}${p.last_lookup?.reason?' · '+p.last_lookup.reason:''}`;
}
async function refreshCuts(){
 if(cutsBusy)return;cutsBusy=true;renderCutsControl();$('#pulse-test').disabled=true;$('#pulse-message').textContent='Consultation en cours · une seule requête…';
 try{
  const x=await request({action:'pulsescore_test'});data.pulsescore=x.pulsescore;renderPulse();
  const message=x.ok?`Connexion réussie. Cuts reçus pour ${x.pulsescore.last_test.returned} matchs sur cette page ; prix à vérifier sur Stake.`:x.pulsescore.last_test.error;
  $('#pulse-message').textContent=message;notice(message);await refresh();
 }catch(err){$('#pulse-message').textContent=err.message;notice(err.message)}
 finally{cutsBusy=false;renderCutsControl();$('#pulse-test').disabled=!data.pulsescore?.configured||data.pulsescore?.attempts_31d>=500}
}
$('#pulse-test').onclick=refreshCuts;
$('#cuts-refresh').onclick=refreshCuts;
$('#card-leagues').oninput=cardSelectionNotice;
$('#card-leagues-restore').onclick=()=>{const defaults=data.radar_telegram?.default_card_leagues;if(!Array.isArray(defaults)||!defaults.length){$('#settings-result').textContent='Liste par défaut indisponible : recharge la page.';return}$('#card-leagues').value=defaults.join('\n');cardSelectionNotice();$('#settings-result').textContent=defaults.length+' compétitions sont prêtes. Clique sur Enregistrer.'};
$('#settings-form').onsubmit=async e=>{e.preventDefault();try{if(settingsLeagues===null)throw Error('Sélection non reçue : recharge la page avant d’enregistrer.');const leagues=[...new Set($('#card-leagues').value.split('\n').map(s=>s.trim()).filter(Boolean))];if(!leagues.length&&!$('#disable-cards').checked)throw Error('Confirme la désactivation des cartons ou restaure la sélection par défaut.');const x=await request({action:'radar_telegram',enabled:$('#telegram-enabled').checked,priced_only:$('#radar-priced-only').checked,odds_auto:$('#radar-odds-auto').checked,card_leagues:leagues,expected_card_leagues:settingsLeagues,disable_cards:!leagues.length&&$('#disable-cards').checked});data.radar_telegram=x.radar_telegram;settingsLeagues=[...x.radar_telegram.card_leagues];$('#settings-result').textContent='Paramètres enregistrés · '+settingsLeagues.length+' compétition(s) cartons.';await refresh()}catch(err){$('#settings-result').textContent=err.message}};
$('#telegram-test').onclick=async()=>{const b=$('#telegram-test');b.disabled=true;try{const x=await request({action:'telegram_test'});$('#settings-result').textContent=x.ok?'Test envoyé : vérifie ton canal Telegram.':`${deliveries[x.state]||x.state} : ${x.error||'Vérifier la configuration'}`}catch(e){$('#settings-result').textContent=e.message}finally{b.disabled=false}};
$('#settle-form').onsubmit=async e=>{e.preventDefault();try{const f=e.target.elements;const x=await request({action:'settle',id:Number(f.id.value),outcome:f.outcome.value,source:f.source.value.trim()});$('#settle-modal').close();moreHistory=[];await refresh();notice(x.unchanged?'Résultat déjà enregistré : aucune nouvelle notification.':x.result_delivery==='queued'?'Résultat enregistré. Notification Telegram au prochain passage du moteur.':'Résultat enregistré. Envoi Telegram désactivé.')}catch(err){notice(err.message)}};
setInterval(()=>{$('#clock').textContent=new Date().toLocaleTimeString('fr-FR',{timeZone:'Europe/Paris'})},1000);
const deskNames={goals:'Buts',cards:'Cartons jaunes'};
const deskStates={context_wait:'Avant-match incomplet',context_conflict:'Concordance insuffisante',active:'À regarder',confirming:'À confirmer',neutral:'Observer',missing:'À compléter',stale:'Données anciennes',waiting:'En attente',caution:'Prudence'};
let boardReceivedAt=Date.now();
const seenHighlights=new Set();
function alertCutoffReached(r){return typeof r.minute==='number'&&r.minute+Math.max(0,age(r.collected_at||r.received_at))/60>=80;}
function deskEffective(r,d){
 if(r.state==='LIVE'&&d?.market==='goals'&&d.period==='HT'&&r.minute+Math.max(0,age(r.collected_at||r.received_at))/60>=45)return {...d,status:'waiting',title:'Pause proche',reason:'Pas de nouvelle alerte avant la pause à partir de 45:00.'};
 if(r.state==='LIVE'&&alertCutoffReached(r))return {...d,status:'waiting',title:'Alertes arrêtées · 80′',reason:'Aucune nouvelle alerte dès la 80e minute ; suivi des résultats maintenu.'};
 if(d?.market==='cards'&&(d.recommendation_allowed===false||d.telegram_allowed===false||d.status==='excluded'))return {...d,status:'excluded',title:'Hors sélection cartons',reason:'Cartons désactivés pour ce championnat.'};
 if(d&&['goals','cards'].includes(d.market)&&['active','confirming'].includes(d.status)&&(d.coherence?.status!=='confirmed'||d.coherence?.policy!=='prematch_live_context_v4'))return {...d,status:'context_wait',title:'Avant-match incomplet',reason:'Concordance avant-match/live non confirmée.'};
 if(age(r.received_at)>100)return {...d,status:r.received_at?'stale':'waiting',title:r.received_at?'Données anciennes':'En attente',reason:'Aucune lecture active sans relevé récent.'};
 if(age(data.engine?.at)>65||data.engine?.version!=='decision5.0')return {...d,status:'waiting',title:'Moteur en attente',reason:'Le dernier cycle du moteur est trop ancien.'};
 if(r.state!=='LIVE')return {...d,status:'waiting',title:({HT:'Mi-temps',FT:'Terminé',NS:'À venir'}[r.state]||'En attente'),reason:'Aucune lecture active hors des périodes de jeu.'};
 return d||{status:'waiting',title:'En attente',reason:'Le moteur prépare cette lecture.'};
}

function coherenceHTML(d){const c=d.coherence;if(!c)return '';const reasons=[...(c.missing||[]),...(c.conflicts||[])];return `<div class="reading-evidence"><b>${c.status==='confirmed'?'Avant-match + live concordants':c.status==='missing'?'Avant-match à compléter':'Concordance insuffisante'}</b>${(reasons.length?reasons:c.lines||[]).slice(0,3).map(x=>`<small>${esc(x)}</small>`).join('')}${(c.cautions||[]).filter(x=>x.startsWith('Échantillon limité')).map(x=>`<small>${esc(x)}</small>`).join('')}</div>`}
function evidenceHTML(d){const e=d.context_evidence,s=d.situation,c=d.conversion;const ratio=d.market==='goals'&&c?.status==='available'?`<div class="reading-evidence"><b>Efficacité historique · contexte FT</b>${(c.lines||[]).slice(0,2).map(x=>`<small>${esc(x)}</small>`).join('')}<small>${esc(c.caution||'')}</small></div>`:'';const live=ratio+(s?`<div class="reading-evidence"><b>${esc(s.clock_line)}</b><small>${esc(s.lines?.[0]||'')}</small></div>`:'');if(!e)return live+coherenceHTML(d);return live+coherenceHTML(d)+`<div class="reading-evidence"><b>${e.support==='concordant'?'Références de la période documentées':'Repères historiques'}</b>${(e.lines||[]).slice(0,3).map(x=>`<small>${esc(x)}</small>`).join('')}${d.market==='cards'&&d.telegram_allowed===false?'<small class="help">Cartons hors sélection : aucune recommandation ni alerte</small>':''}</div>`}

function deskCell(r,d,kind){
 const x=deskEffective(r,d),number=d?.observed??(kind==='goals'?r.score?.[d?.team]:r.stats?.[{corners:'corners',cards:'yellow_cards'}[kind]]?.[d?.team]);
 if(x.status==='excluded')return `<div class="reading excluded"><div class="reading-owner">Cartons de ${esc(d?.team==='h'?r.home:r.away)}</div><div class="reading-top"><b>${fmt(number)} <small>jaune(s)</small></b><span>Hors sélection</span></div><p>Aucune recommandation cartons pour ce championnat.</p></div>`;
 const unit={goals:'but(s)',corners:'corner(s)',cards:'jaune(s)'}[kind];
 const owner=d?.team==='h'?r.home:d?.team==='a'?r.away:'cette équipe';
 const key=[r.packball_id,d?.team,kind,x.status,d?.observed].join(':');
 const animate=x.status==='active'&&!seenHighlights.has(key);if(x.status==='active')seenHighlights.add(key);
 return `<div class="reading ${esc(x.status)} ${animate?'reading-new':''}"><div class="reading-owner">${esc(deskNames[kind])} de ${esc(owner)}${kind==='goals'?(d?.period==='HT'?' · avant la pause':' · avant la fin'):''}</div><div class="reading-top"><b>${fmt(number)} <small>${unit}</small></b><span>${esc(x.title||deskStates[x.status]||'Observer')}</span></div><p>${esc(x.reason)}</p><div class="reading-reference">${typeof x.baseline==='number'?`Moyenne de cette équipe : <b>${fmt(x.baseline)}</b> / match · ${fmt(x.sample)} matchs · ${esc(x.baseline_origin||'Packball')}`:'Repère avant-match indisponible'}</div>${kind==='goals'&&typeof x.metrics?.sot_per_goal==='number'?`<div class="reading-reference">Repère : ${fmt(x.metrics.sot_per_goal)} cadrés / but · aucune garantie</div>`:''}${evidenceHTML(x)}${x.status==='active'?'<small class="reading-action">Regarder le match et vérifier le marché</small>':''}</div>`;
}
function deskDetail(r){
 return ['h','a'].map(t=>`<section class="desk-detail-team"><h3>${esc(t==='h'?r.home:r.away)}</h3><div class="desk-detail-grid">${Object.keys(deskNames).map(k=>{
 const d=(r.advice||[]).find(x=>x.team===t&&x.market===k),e=deskEffective(r,d);
 return `<article>${deskCell(r,d,k)}<h4>Ce qui aide à lire</h4><ul>${(e.facts||[]).map(v=>`<li>${esc(v)}</li>`).join('')||'<li>Pas de complément documenté.</li>'}</ul><h4>Ce qui limite la lecture</h4><ul>${(e.limits||[]).map(v=>`<li>${esc(v)}</li>`).join('')}</ul>${typeof e.opponent_baseline==='number'?`<p>Adversaire : ${fmt(e.opponent_baseline)} ${k==='goals'?'buts':'corners'} concédés / match.</p>`:''}<details><summary>Règle de mise en évidence</summary><p>${esc(e.criteria||'Attente de données exploitables.')}</p><p>Deux relevés concordants espacés de 20 à 100 s. Aucune probabilité ni pari automatique.</p></details></article>`;
 }).join('')}</div></section>`).join('');
}
// Attention cards describe current facts; they do not create bets or API calls.
let attentionExpanded=false;
const seenAttention=new Set();
const attentionNumber=v=>typeof v==='number'&&Number.isFinite(v)&&v>=0;
function freshCuts(r){
 const c=r.stake_cuts;
 return c?.state==='received'&&age(c.at)<=60&&r.state==='LIVE'&&age(r.received_at)<=100?(c.quotes||[]).filter(q=>age(q.received_at)<=60&&q.score?.h===r.score?.h&&q.score?.a===r.score?.a):[];
}
function cutGap(q){
 if(q.needed===null||q.needed===undefined)return q.half_line?'Décompte Stake à vérifier':'Ligne asiatique / entière · règlement spécifique';
 if(q.needed===0)return 'Cut déjà dépassé au compteur Packball · vérifier le marché';
 return `${fmt(q.needed)} de plus nécessaire${q.needed===1?'':'s'}${q.indicative_counter?' si même décompte*':''}`;
}
function cutCardHTML(r,team,kind){
 const reading=(r.advice||[]).find(x=>x.team===team&&x.market===kind),period=kind==='goals'&&reading?.period==='HT'?'HT':'FT';
 const options=freshCuts(r).filter(q=>q.team===team&&q.kind===kind&&q.period===period&&q.half_line&&q.needed!==0);
 options.sort((a,b)=>a.line-b.line);
 const q=options[0],d=(r.advice||[]).find(x=>x.team===team&&x.market===kind),g=d?.stake_gate;
 const gateNote=g?`<small>${esc(g.reason)}</small>`:'';
 if(!q){const stale=r.stake_cuts?.state==='stale'||age(r.stake_cuts?.at)>60&&r.stake_cuts?.at;return `<div class="cut-card unavailable">${stale?'Cuts à actualiser':'Cut '+period+' non reçu'} · observation seule${gateNote}</div>`}
 return `<div class="cut-card ${q.needed>1?'multiple':''}"><b>Cut +${fmt(q.line)} · cote ${fmt(q.odds)}${q.odds<1.5?' · &lt; 1,50':''}</b><span>Actuel ${fmt(q.current)} · ${esc(cutGap(q))}</span>${gateNote}${q.needed>1?'<small>Marché distinct : ce signal suit un événement supplémentaire, pas ce cut.</small>':''}${q.indicative_counter?'<small>* Bookings Stake ≠ jaunes Packball</small>':''}</div>`;
}
function cutDetailHTML(r){
 const all=freshCuts(r).filter(q=>Object.hasOwn(deskNames,q.kind)),c=r.stake_cuts;
 return `<section class="detail-box cut-detail"><h3>Les cuts réellement reçus · Stake</h3><p class="help">${esc(c?.reason||'Utilise « Cuts Stake » au-dessus des matchs pour consulter les marchés.')} Réception : ${esc(time(c?.at))}. Instantané valable 60 s pour l’affichage et 30 s maximum avant un envoi Telegram ; fraîcheur du flux source non garantie.</p>${all.length?`<div class="cut-list">${all.map(q=>`<article><b>${esc(q.team==='h'?r.home:r.away)} · ${esc(deskNames[q.kind])} · ${q.period==='HT'?'1re mi-temps':'match entier'}</b><p>Over ${fmt(q.line)} · cote ${fmt(q.odds)} · compteur Packball ${fmt(q.current)}<br><strong>${esc(cutGap(q))}</strong></p><small>${esc(q.note)}</small></article>`).join('')}</div>`:'<p>Aucun cut actuel associé sans ambiguïté. Cela ne prouve pas que le marché est absent sur Stake.</p>'}<p class="help">La carte affiche la plus petite ligne en demi-unité reçue pour la période suivie (HT ou FT), non dépassée. Un écart au cut n’est pas une prévision : une dynamique pour un événement ne valide pas un pari qui en exige plusieurs. Les jaunes Packball ne certifient pas le décompte Bookings de Stake.</p></section>`;
}
function attentionItems(matches){
 const items=[];
 for(const r of matches){
  if(r.state!=='LIVE'||!attentionNumber(r.minute)||r.minute<=0||alertCutoffReached(r)||r.minute_extra||r.quality_errors?.length)continue;
  for(const d of r.advice||[]){
   if((d.coherence?.status!=='confirmed'||d.coherence?.policy!=='prematch_live_context_v4'))continue;
   if(market!=='all'&&market!==d.market)continue;
   if(!['h','a'].includes(d.team)||!Object.hasOwn(deskNames,d.market)||d.sample_id!==r.sample_id)continue;
   const x=deskEffective(r,d),m=d.metrics||{};
   if(!['active','confirming','neutral','missing'].includes(x.status))continue;
   const confirmed=x.status==='active',observed=d.observed;
   let headline='',detail='',reserve='',notable=false;
   if(d.market==='cards'&&attentionNumber(m.fouls)&&m.fouls>=8&&observed===0){
    headline=`${fmt(m.fouls)} fautes, aucun jaune`;
    detail=attentionNumber(m.fouls10)?`${fmt(m.fouls10)} fautes sur les 10 dernières minutes`:'Rythme des fautes sur 10 minutes encore indisponible';
    const ref=r.analyst?.usable?r.analyst?.referee:null,rs=ref?.stats;
    if(attentionNumber(rs?.yellow_per_match))detail+=` · ${ref.name} : ${fmt(rs.yellow_per_match)} jaunes / match, deux équipes réunies`;
    reserve='La gravité des fautes compte ; aucun carton n’est automatique.';notable=true;
   }else if(d.market==='goals'&&attentionNumber(m.sot)&&attentionNumber(observed)&&attentionNumber(m.sot_per_goal)&&m.sot_per_goal>0&&m.sot>=(observed+1)*m.sot_per_goal){
    headline=`${fmt(m.sot)} cadrés, ${fmt(observed)} but${observed===1?'':'s'}`;
    detail=`Repère historique : ${fmt(m.sot_per_goal)} cadrés / but`;
    if(attentionNumber(m.sot10))detail+=` · ${fmt(m.sot10)} cadrés en 10 min`;
    reserve='Conversion sous le repère historique, sans garantie de but.';notable=true;
   }else if(d.market==='corners'&&attentionNumber(m.shots10)&&m.shots10>=4&&attentionNumber(m.corners10)&&m.corners10<=1){
    headline=`${fmt(m.shots10)} tirs, ${fmt(m.corners10)} corner${m.corners10===1?'':'s'} en 10 min`;
    detail=attentionNumber(m.blocked_shots10)?`${fmt(m.blocked_shots10)} tirs bloqués sur la même période`:'Tirs bloqués récents non renseignés';
    reserve='Du volume offensif, mais les tirs ne garantissent pas des corners.';notable=true;
   }
   if(d.context_evidence?.support==='concordant')detail+=' · références '+({first_half:'1re mi-temps',second_half:'2e mi-temps',full_time:'match entier'}[d.context_evidence.period]||'de la période')+' documentées';
   if(!notable&&!confirmed)continue;
   if(!notable){headline={goals:'Pression offensive soutenue',corners:'Activité autour de la surface',cards:'Fautes récentes en série'}[d.market];detail=d.reason;reserve=(d.limits||[])[0]||'Situation à examiner dans le détail du match.'}
   if(d.market==='goals')headline+=(d.period==='HT'?' · avant la pause':' · avant la fin');
   if(d.situation){detail+=' · '+(d.situation.lines?.[0]||'');reserve=d.situation.clock_line+' · Un événement supplémentaire à surveiller.'}
   const id=String(r.packball_id||r.fixture),key=[id,d.team,d.market].join(':');
   items.push({key,match:id,team:d.team==='h'?r.home:r.away,opponent:d.team==='h'?r.away:r.home,minute:r.minute,score:r.score,kind:d.market,cutHTML:cutCardHTML(r,d.team,d.market),confirmed,notable,headline,detail,reserve,clockLine:d.situation?.clock_line||'',priority:confirmed?(d.context_evidence?.support==='concordant'?3:2):x.status==='confirming'?1:0});
  }
 }
 return [...new Map(items.map(x=>[x.key,x])).values()].sort((a,b)=>b.priority-a.priority||b.minute-a.minute||a.key.localeCompare(b.key));
}
function renderAttention(matches){
 const panel=$('#live-attention');panel.hidden=view!=='live';if(panel.hidden)return;
 const all=attentionItems(matches),visible=attentionExpanded?all:all.slice(0,6);
 $('#attention-count').textContent=all.length;
 $('#attention-empty').hidden=all.length>0;
 $('#attention-more').hidden=all.length<=6;
 $('#attention-more').textContent=attentionExpanded?'Réduire à 6 situations':`Voir les ${all.length} situations`;
 $('#attention-cards').innerHTML=visible.map(x=>{
  const fresh=!seenAttention.has(x.key);seenAttention.add(x.key);
  const shortDetail=x.kind==='cards'?x.detail.split(' · ')[0]:x.detail;
  const verdict={goals:'But à surveiller',corners:'Corners à surveiller',cards:'Carton à surveiller'}[x.kind];
  const headline=x.confirmed?verdict:x.headline;
  const evidence=x.confirmed&&x.notable?`${x.headline} · ${shortDetail}`:shortDetail;
  return `<article class="attention-card ${x.kind} ${x.confirmed?'confirmed':''} ${fresh?'attention-new':''}"><div class="attention-card-top"><span class="attention-category">${esc(deskNames[x.kind])}</span><span class="attention-state">${x.confirmed?'Dynamique confirmée':'À surveiller'}</span></div><div class="attention-team-line"><div class="attention-team">${esc(x.team)}</div><b class="attention-minute">${fmt(x.minute)}′</b></div><p class="attention-match">contre ${esc(x.opponent)} · ${fmt(x.score?.h)}–${fmt(x.score?.a)}${x.clockLine?` · ${esc(x.clockLine)}`:''}</p><h3>${esc(headline)}</h3><p class="attention-detail">${esc(evidence)}</p>${x.cutHTML}<button class="attention-open" data-match="${esc(x.match)}" aria-label="Voir le match de ${esc(x.team)}" title="Ouvrir le match et ses détails"><span aria-hidden="true">↗</span></button></article>`;
 }).join('');
}
$('#attention-more').onclick=()=>{attentionExpanded=!attentionExpanded;render()};

// Compact Packball mirror. The received column titles define the order.
// All labels/values are escaped; missing cells are never filled from a neighbour.
let deskLayout='table',tableScope='all';
function readLocalSet(key){try{const a=JSON.parse(localStorage.getItem(key)||'[]');return new Set(Array.isArray(a)?a.filter(x=>typeof x==='string'):[])}catch{return new Set()}}
const tableFavorites=readLocalSet('s90.table.favorites'),tablePins=readLocalSet('s90.table.pins'),tableValues=new Map();
function saveLocalSet(key,set){try{localStorage.setItem(key,JSON.stringify([...set].slice(-1000)))}catch{}}
const tableKey=r=>String(r.fixture||r.packball_id||[r.kickoff_ts,r.home,r.away].join('|'));
const tableFallback=[['Buts','stats','goals'],['Total des tirs','stats','shots'],['Tirs cadrés','stats','sot'],['Possession','stats','possession'],['Cartons jaunes','stats','yellow_cards'],['Cartons rouges','stats','red_cards'],['Cartons jaunes-rouges','stats','second_yellow'],['Fautes','stats','fouls'],['Total des tirs 10 dernières minutes','ind10','shots10'],['Tirs cadrés 10 dernières minutes','ind10','sot10'],['Total des tirs 5 dernières minutes','ind5','shots5'],['Tirs cadrés 5 dernières minutes','ind5','sot5'],['Tirs bloqués','stats','blocked_shots'],["Photos à l’intérieur de la boîte",'stats','shots_inside'],['Substitutions','stats','substitutions'],['Buts 5 dernières minutes','ind5','goals5'],['Tirs hors cible','stats','shots_off_target'],['Tirs hors boîte','stats','shots_outside'],['Tacles','stats','tackles'],['Attaques dangereuses','stats','da'],['Attaques dangereuses 10 dernières minutes','ind10','da10'],['Attaques dangereuses 5 dernières minutes','ind5','da5']];
function tableColumns(matches){
 const headers=Array.isArray(data.feed?.headers)?data.feed.headers.filter(x=>typeof x==='string'&&x.trim()).slice(0,40):[];
 if(headers.length)return orderTableColumns(headers.map(title=>({title})));
 const recent=[...matches].sort((a,b)=>(Date.parse(b.received_at)||0)-(Date.parse(a.received_at)||0)).find(r=>r.live_columns?.length);
 return orderTableColumns(recent?recent.live_columns.slice(0,40).map(c=>({title:c.title})):tableFallback.map(([title,group,key])=>({title,group,key})));
}
function orderTableColumns(columns){
 const out=[...columns],plain=title=>normSearch(title).replace(/temps plein/g,'').trim();
 const tackles=out.findIndex(c=>plain(c.title)==='tacles');
 if(tackles<0||!out.some(c=>plain(c.title)==='fautes'))return out;
 const [column]=out.splice(tackles,1);out.splice(out.findIndex(c=>plain(c.title)==='fautes')+1,0,column);return out;
}
// Per-value emphasis lasts 60 seconds after an observed change, not after each render.
function tableEmphasis(r,key,value){
 const id=tableKey(r)+'|'+key,old=tableValues.get(id),now=Date.now();
 const until=value===null?0:old&&old.value!==null&&old.value!==value?now+60000:old?.until||0;
 tableValues.set(id,{value,until});
 return until>now?` data-change-until="${until}" class="value-updated"`:'';
}
function expireTableEmphasis(){document.querySelectorAll('[data-change-until]').forEach(el=>{if(Number(el.dataset.changeUntil)<=Date.now()){el.classList.remove('value-updated');el.removeAttribute('data-change-until')}})}
setInterval(expireTableEmphasis,1000);
function tableColumnInfo(title){
 const n=normSearch(title),window=/\b10\b/.test(n)?'10′':/\b5\b/.test(n)?'5′':'';
 let icon='◫',label=title.replace(/(?:10|5) dernieres? minutes?/ig,'').trim(),kind='stat';
 if(/jaunes.?rouges|second.*jaune/.test(n)){icon='▰';label='2e J.';kind='second-yellow'}
 else if(/cartons jaunes/.test(n)){icon='▰';label='Jaunes';kind='yellow'}
 else if(/cartons rouges/.test(n)){icon='▰';label='Rouges';kind='red'}
 else if(/buts/.test(n)){icon='<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.4"><circle cx="12" cy="12" r="9"/><path d="m12 7 4.8 3.5-1.8 5.6H9l-1.8-5.6ZM12 7V3m4.8 7.5 3.7-1.3M15 16.1l2.3 3.1M9 16.1l-2.3 3.1m.5-8.7L3.5 9.2"/></svg>';label=/attendus/.test(n)?'ExG':'Buts'}
 else if(/tirs cadres/.test(n)){icon='⊡';label='Cadrés'}
 else if(/total des tirs/.test(n)){icon='◎';label='Tirs'}
 else if(/hors cible/.test(n)){icon='↗';label='Non cadr.'}
 else if(/bloques/.test(n)){icon='⊟';label='Bloqués'}
 else if(/interieur/.test(n)){icon='⊓';label='Surface'}
 else if(/hors boite/.test(n)){icon='⊏';label='Hors surf.'}
 else if(/possession/.test(n)){icon='%';label='Poss.'}
 else if(/fautes/.test(n)){icon='⚑';label='Fautes'}
 else if(/corners/.test(n)){icon='⚐';label='Corners'}
 else if(/substitutions/.test(n)){icon='↕';label='Chang.'}
 else if(/tacles/.test(n)){icon='⋈';label='Tacles'}
 else if(/attaques dangereuses/.test(n)){icon='➟';label=/par minute|avg/.test(n)?'Att.d./m':'Att. dang.'}
 else if(/attaques/.test(n)){icon='→';label='Attaques'}
 else if(/pression/.test(n)){icon='◴';label=/difference/.test(n)?'Δ pression':'Pression'}
 return {icon,label,window,kind};
}
function tableCellValue(r,c){
 if(Array.isArray(r.live_columns)&&r.live_columns.length){const found=r.live_columns.filter(x=>x.title===c.title);return found.length===1?found[0].value:null}
 if(c.group)return c.key==='goals'?r.score:r[c.group]?.[c.key];
 // Compatibility for a board without display projection; exact known labels only.
 const f=tableFallback.find(([title])=>title===c.title);
 return f?(f[2]==='goals'?r.score:r[f[1]]?.[f[2]]):null;
}
function tablePair(r,c){
 const p=tableCellValue(r,c),h=typeof p?.h==='number'&&Number.isFinite(p.h)?p.h:null,a=typeof p?.a==='number'&&Number.isFinite(p.a)?p.a:null;
 const fresh=age(r.received_at)<=100,colour=(v,o)=>v===null?'unknown':!fresh?'old':o===null||v===o?'equal':v>o?'higher':'lower';
 const tint=fresh&&h!==null&&a!==null&&h!==a?(h>a?'home-more':'away-more'):'';
 return `<td class="pack-stat ${tint} " title="${esc(c.title)} · ${esc(r.home)} : ${fmt(h)} / ${esc(r.away)} : ${fmt(a)}"><span class="${colour(h,a)}"><span${tableEmphasis(r,c.title+':h',h)}>${fmt(h)}</span></span><i>–</i><span class="${colour(a,h)}"><span${tableEmphasis(r,c.title+':a',a)}>${fmt(a)}</span></span></td>`;
}
const countryCodes={france:'FR',fra:'FR',england:'GB-ENG',eng:'GB-ENG',angleterre:'GB-ENG',spain:'ES',espagne:'ES',esp:'ES',italy:'IT',italie:'IT',ita:'IT',germany:'DE',allemagne:'DE',ger:'DE',deu:'DE',netherlands:'NL',ned:'NL',portugal:'PT',por:'PT',belgium:'BE',belgique:'BE',bel:'BE',turkey:'TR',turkiye:'TR',tur:'TR',sweden:'SE',swe:'SE',norway:'NO',nor:'NO',austria:'AT',aut:'AT',scotland:'GB-SCT',sco:'GB-SCT',usa:'US','united states':'US',brazil:'BR',bra:'BR',argentina:'AR',arg:'AR',colombia:'CO',col:'CO',chile:'CL',chi:'CL',ecuador:'EC',ecu:'EC','saudi arabia':'SA','arabie saoudite':'SA',ksa:'SA',sau:'SA',australia:'AU',aus:'AU',japan:'JP',jpn:'JP','south korea':'KR',kor:'KR',europe:'EU',eur:'EU',uefa:'EU'};
function countryFlag(code){
 const bands=(colors,vertical=false)=>colors.map((color,i)=>`<rect x="${vertical?i*30/colors.length:0}" y="${vertical?0:i*20/colors.length}" width="${vertical?30/colors.length:30}" height="${vertical?20:20/colors.length}" fill="${color}"/>`).join('');
 const flag={FR:bands(['#244894','#fff','#ed3346'],true),IT:bands(['#008b53','#fff','#ed3346'],true),DE:bands(['#222','#d82732','#f5c53b']),ES:'<path fill="#c92636" d="M0 0h30v20H0z"/><path fill="#ffc947" d="M0 5h30v10H0z"/>',NL:bands(['#be3040','#fff','#254c99']),BE:bands(['#222','#f7ce3e','#e63b42'],true),AT:bands(['#db3040','#fff','#db3040']),AR:bands(['#85cce9','#fff','#85cce9']),CO:'<path fill="#fbd438" d="M0 0h30v20H0z"/><path fill="#224da6" d="M0 10h30v5H0z"/><path fill="#ca3142" d="M0 15h30v5H0z"/>',SE:'<path fill="#176795" d="M0 0h30v20H0z"/><path stroke="#f2ce42" stroke-width="4" d="M10 0v20M0 10h30"/>','GB-ENG':'<path fill="#fff" d="M0 0h30v20H0z"/><path stroke="#d82c42" stroke-width="4" d="M15 0v20M0 10h30"/>','GB-SCT':'<path fill="#1261a3" d="M0 0h30v20H0z"/><path stroke="#fff" stroke-width="3" d="m0 0 30 20M30 0 0 20"/>',BR:'<path fill="#27854a" d="M0 0h30v20H0z"/><path fill="#ffda3a" d="m15 2 12 8-12 8L3 10Z"/><circle fill="#294e94" cx="15" cy="10" r="5"/>',JP:'<path fill="#fff" d="M0 0h30v20H0z"/><circle fill="#d4304c" cx="15" cy="10" r="6"/>'}[code];
 return flag?`<svg viewBox="0 0 30 20" aria-hidden="true">${flag}</svg>`:`<span class="country-code">${esc(code||'—')}</span>`;
}
function tableCountry(r){const country=r.profile?.packball?.country||r.profile?.packball?.export_a?.[0]||r.profile?.country||r.country||'',code=countryCodes[normSearch(country)]||(/^[A-Z]{2}$/.test(country)?country:'');return `<span class="country-flag" title="${esc(country||'Pays non transmis')}">${countryFlag(code)}</span><small>${esc(country.length<=3?country:code||'')}</small>`}
const tablePinIcon='<svg viewBox="0 0 20 20" width="14" height="14" aria-hidden="true" fill="currentColor"><path d="m13 2 5 5-2 1-3 3v3l-2 1-3-3-5 5-1-1 5-5-3-3 1-2h3l3-3Z"/></svg>';
function tableFinished(r){return r.state==='FT'||(['OTHER','NS',undefined,null].includes(r.state)&&/^(?:termin[eé]|fini|ft\b|fin\b|finished|full[ -]?time)/i.test(String(r.status_raw||'').trim()))}
function tableAddedClock(r){
 if(!['LIVE','OTHER','NS',undefined,null].includes(r.state))return null;
 // Old collectors reject 45′ + 3′. Recover only an explicit added-time clock;
 // never interpret extra-time, a score, or an announced duration as the clock.
 const m=String(r.status_raw||'').trim().match(/^(45|90)\s*['′’]?\s*[+＋]\s*(\d{1,2})\s*['′’]?$/);
 return m?{minute:Number(m[1]),extra:Number(m[2])}:null;
}
function tableClock(r){
 if(r.state==='HT')return 'MT';
 if(tableFinished(r))return 'Terminé';
 const added=tableAddedClock(r);
 if(added)return `${added.minute} + ${added.extra}′`;
 if(r.state==='LIVE'&&typeof r.minute==='number')return fmt(r.minute)+(r.minute_extra?' + '+fmt(r.minute_extra):'')+'′';
 return r.state==='NS'?'—':'À vérifier';
}
function tableReadings(r){return ['h','a'].map(side=>{const ds=(r.advice||[]).filter(d=>d.team===side&&(market==='all'||market===d.market));const active=ds.filter(d=>deskEffective(r,d).status==='active');const confirm=ds.some(d=>deskEffective(r,d).status==='confirming');const title=ds.map(d=>`${deskNames[d.market]||d.market} : ${deskEffective(r,d).reason||d.reason||'En attente'}`).join('\n');return `<span class="pack-reading ${active.length?'active':confirm?'confirming':''}" title="${esc(title)}">${side==='h'?'D':'E'} ${active.length?active.map(d=>d.market==='goals'?'B':'J').join(' '):confirm?'◔':'·'}</span>`}).join('')}
function renderDesk(matches){
 const selected=matches.filter(r=>tableScope==='live'?(['LIVE','HT'].includes(r.state)||!!tableAddedClock(r)):tableScope==='favorites'?tableFavorites.has(tableKey(r)):true);
 $('#pack-row-count').textContent=selected.length+' matchs';
 if(deskLayout==='analysis'){renderAnalysisDesk(selected);return}
 const cols=tableColumns(matches),ordered=[...selected].sort((a,b)=>Number(tablePins.has(tableKey(b)))-Number(tablePins.has(tableKey(a)))||(Date.parse(a.kickoff_ts)||0)-(Date.parse(b.kickoff_ts)||0)||(a.league||'').localeCompare(b.league||'')||a.home.localeCompare(b.home));
 $('#table-head').innerHTML='<tr><th class="pack-favorite">★</th><th class="pack-pin">'+tablePinIcon+'</th><th class="pack-country">Pays</th><th class="pack-league">Ligue</th><th class="pack-start">Heure</th><th class="pack-clock">Live</th><th class="pack-fixture">Domicile <span>Score</span> Visiteurs</th><th class="pack-ht" title="Score à la pause, uniquement si transmis par la collecte">HT</th>'+cols.map(c=>{const i=tableColumnInfo(c.title);return `<th class="pack-stat-head ${i.kind}" title="${esc(c.title)}" aria-label="${esc(c.title)}"><sup>${i.window||'&nbsp;'}</sup><b aria-hidden="true">${i.icon}</b><small>${esc(i.label)}</small></th>`}).join('')+'<th class="pack-reading-head">Radar<br><small>D / E</small></th></tr>';
 $('#match-rows').innerHTML=ordered.map(r=>{const key=tableKey(r),stale=age(r.received_at)>100,ht=r.ht_score,score=r.score;const target=String(r.packball_id||r.fixture);return `<tr class="pack-row ${stale?'pack-stale':''}" data-fixture-key="${esc(key)}"><td class="pack-favorite"><button type="button" data-favorite="${esc(key)}" aria-pressed="${tableFavorites.has(key)}" aria-label="Favori : ${esc(r.home)} — ${esc(r.away)}">★</button></td><td class="pack-pin"><button type="button" data-pin="${esc(key)}" aria-pressed="${tablePins.has(key)}" aria-label="Épingler : ${esc(r.home)} — ${esc(r.away)}">${tablePinIcon}</button></td><td class="pack-country">${tableCountry(r)}</td><td class="pack-league" title="${esc(r.league)}">${esc(r.league||'—')}</td><td class="pack-start">${r.kickoff_ts?esc(new Date(r.kickoff_ts).toLocaleTimeString('fr-FR',{timeZone:'Europe/Paris',hour:'2-digit',minute:'2-digit'})):'—'}</td><td class="pack-clock ${r.state==='HT'?'pause':''}" title="${esc(r.received_at?'Relevé du '+time(r.received_at):'Aucun relevé')}${stale?' · données anciennes':''}">${tableClock(r)}${stale&&r.received_at?'<span class="stale-mark" title="Relevé ancien">!</span>':''}</td><td class="pack-fixture"><button type="button" class="pack-match-button" data-match="${esc(target)}" aria-label="Analyser ${esc(r.home)} — ${esc(r.away)}"><span class="pack-home" title="${esc(r.home)}">${esc(r.home)}</span><span class="pack-score"><span${tableEmphasis(r,'$score:h',score?.h??null)}>${fmt(score?.h)}</span>–<span${tableEmphasis(r,'$score:a',score?.a??null)}>${fmt(score?.a)}</span></span><span class="pack-away" title="${esc(r.away)}">${esc(r.away)}</span></button></td><td class="pack-ht" title="${ht?'Score à la pause':'Score HT non transmis ; aucune valeur déduite du score actuel'}">${ht?fmt(ht.h)+'–'+fmt(ht.a):'—'}</td>${cols.map(c=>tablePair(r,c)).join('')}<td class="pack-reading-cell">${tableReadings(r)}</td></tr>`}).join('');
 $('#empty').hidden=ordered.length>0;
 if(!ordered.length){$('#empty h3').textContent=tableScope==='favorites'?'Aucun favori affiché.':$('#search').value?'Aucun match ne correspond.':'En attente du tableau Packball.';$('#empty p').textContent=tableScope==='favorites'?'Ajoute une étoile sur les rencontres à suivre.':$('#search').value?'Modifie ta recherche ou le filtre de matchs.':'Ouvre Packball avec le collecteur activé. Le programme importé reste accessible dans Lecture d’analyse.';}

 $('#board-description').textContent='Valeurs domicile–extérieur · Tacles après Fautes · valeurs modifiées en gras pendant 1 min · couleurs : comparaison des compteurs, pas une probabilité · — = non transmis · liste du dernier relevé Packball · relevés ≈ 30 s, lecture serveur toutes les 15 s.';
 // Bound memory when the slate changes. Keep change highlights only for current rows.
 if(tableValues.size>50000){const keys=new Set(matches.map(tableKey));for(const key of tableValues.keys())if(![...keys].some(id=>key.startsWith(id+'|')))tableValues.delete(key)}
}
document.addEventListener('click',e=>{const b=e.target.closest('button');if(!b)return;
 if(b.dataset.layout){deskLayout=b.dataset.layout;document.querySelectorAll('[data-layout]').forEach(x=>x.classList.toggle('selected',x===b));render()}
 if(b.dataset.scope){tableScope=b.dataset.scope;document.querySelectorAll('[data-scope]').forEach(x=>x.classList.toggle('selected',x===b));render()}
 for(const [attr,set,key] of [['favorite',tableFavorites,'s90.table.favorites'],['pin',tablePins,'s90.table.pins']])if(b.dataset[attr]){const id=b.dataset[attr];set.has(id)?set.delete(id):set.add(id);saveLocalSet(key,set);render()}
});
$('#table-focus').onclick=()=>{const on=document.body.classList.toggle('table-focus');$('#table-focus').setAttribute('aria-pressed',String(on));$('#table-focus').textContent=on?'↙ Navigation':'⛶ Pleine largeur'};
function renderAnalysisDesk(matches){
 const kinds=market==='all'?Object.keys(deskNames):[market];
 const priority=r=>(r.advice||[]).some(d=>kinds.includes(d.market)&&deskEffective(r,d).status==='active');
 matches.sort((a,b)=>Number(priority(b))-Number(priority(a))||Date.parse(a.kickoff_ts)-Date.parse(b.kickoff_ts));
 $('#table-head').innerHTML='<tr><th>Rencontre</th>'+kinds.map(k=>`<th>${esc(deskNames[k])} par équipe</th>`).join('')+'</tr>';
 $('#match-rows').innerHTML=matches.map(r=>`<tr class="desk-row match-row match-start"><td class="desk-team"><span class="desk-match">${esc(r.state==='LIVE'?fmt(r.minute)+'′':({HT:'Pause',FT:'Terminé',NS:'À venir'}[r.state]||'En attente'))}</span><div class="match-identity"><div><span><small>Domicile</small><strong>${esc(r.home)}</strong></span><b>${fmt(r.score?.h)}</b></div><div><span><small>Extérieur</small><strong>${esc(r.away)}</strong></span><b>${fmt(r.score?.a)}</b></div></div><small>${esc(r.league||'')} · ${esc(time(r.kickoff_ts))}</small><div class="desk-context">${r.profile?.usable?'Stats ✓':'Stats —'} · ${r.analyst?.usable?'Contexte ✓':'Contexte —'}</div><button class="row-more match-open" data-match="${esc(r.packball_id||r.fixture)}">Comprendre le match ↗</button></td>${kinds.map(k=>`<td data-label="${esc(deskNames[k])} par équipe"><div class="match-market-pair">${['h','a'].map(t=>`<section class="match-side" data-side="${t}"><div class="match-side-label">${t==='h'?'Domicile':'Visiteurs'}</div>${deskCell(r,(r.advice||[]).find(d=>d.team===t&&d.market===k)||{team:t,status:'waiting',reason:'Le moteur prépare cette lecture.'},k)}</section>`).join('')}</div></td>`).join('')}</tr>`).join('');
 $('#empty').hidden=matches.length>0;$('#board-description').textContent='Une ligne par match · domicile à gauche, visiteurs à droite dans chaque carte · compteurs séparés par équipe · matchs terminés retirés automatiquement.';
}
function radarResultHTML(o){
 const r=o.result;if(!r)return o.notification?.delivery==='sent'?'<small>Résultat non disponible pour cette alerte.</small>':'';
 const t=r.data?.target||{},labels={pending:'⏳ En attente',observed:'✅ Seuil atteint',not_observed:t.period==='HT'?'❌ Seuil non atteint à la pause':'❌ Seuil non atteint à la fin',unknown:'⚪ À vérifier · données finales manquantes'};
 const team=t.team==='h'?o.data?.home:o.data?.away;
 return `<div class="observation-result"><b>${esc(labels[r.outcome]||'À vérifier')} · Packball</b><small>${esc(team)} · +${fmt(t.line)} ${t.market==='cards'?'jaunes':'buts'} · ${t.period==='HT'?'première mi-temps':'match entier'}${typeof r.data?.current==='number'?' · compteur '+fmt(r.data.current):''}</small>${t.bookings_proxy?'<small>Bookings Stake : règlement du pari non certifié par les jaunes Packball.</small>':''}${r.notification?`<small>Telegram résultat : ${esc(deliveries[r.notification.delivery]||r.notification.delivery)}${r.notification.delivery_error?' · '+esc(r.notification.delivery_error):''}</small>`:''}</div>`;
}
function observationHTML(o){const x=o.data||{},d=x.reading||{};return `<article class="observation"><small>${esc(time(o.created_at))} · ${esc(x.minute)}′</small><b>${esc(o.team==='h'?x.home:x.away)} · ${esc(deskNames[o.market]||'Corners · archive')}</b><p>${esc(d.reason)}</p><small>Observation conservée${o.notification?' · Telegram : '+esc(deliveries[o.notification.delivery]||o.notification.delivery):''}</small>${radarResultHTML(o)}</article>`;}
function renderObservations(query){
 const rows=(data.observations||[]).filter(o=>(market==='all'||market===o.market)&&normSearch(o.data?.home+' '+o.data?.away).includes(query));
 $('#table-head').innerHTML='<tr><th>Observation</th><th>Pourquoi regarder ?</th><th>Réserves au moment du relevé</th><th>Résultat Packball</th></tr>';
 $('#match-rows').innerHTML=rows.map(o=>`<tr><td><b>${esc(o.team==='h'?o.data.home:o.data.away)}</b><small>${esc(deskNames[o.market]||'Corners · archive')} · ${esc(time(o.created_at))} · ${fmt(o.data.minute)}′</small>${o.notification?`<small>Telegram : ${esc(deliveries[o.notification.delivery]||o.notification.delivery)}${o.notification.delivery_error?' · '+esc(o.notification.delivery_error):''}</small>`:''}</td><td>${esc(o.data.reading.reason)}</td><td>${(o.data.reading.limits||[]).slice(0,2).map(esc).join('<br>')}</td><td>${radarResultHTML(o)||'Observation sans alerte envoyée'}</td></tr>`).join('');
 $('#empty').hidden=rows.length>0;$('#export-history').hidden=false;$('#board-description').textContent='100 dernières mises en évidence · suivi automatique des nouvelles alertes Telegram · seuils Packball, sans règlement financier. Export complet disponible.';
}

setInterval(()=>{render();},10000);setInterval(refresh,15000);refresh();



$('#window-audit-day').value=new Intl.DateTimeFormat('sv-SE',{timeZone:'Europe/Paris'}).format(new Date());
$('#export-windows').onclick=async()=>{
 const b=$('#export-windows'),result=$('#window-export-result'),day=$('#window-audit-day').value;
 b.disabled=true;result.hidden=false;result.textContent='Préparation des fenêtres du '+day+'…';
 try{
  let cursor=0,ceiling=null,bundle=null;const ids=new Set();
  do{
   const page=await request({action:'export_window_audit',day,cursor,...(ceiling===null?{}:{ceiling})},{timeoutMs:45000});
   if(page.schema!=='stratedge.windows.v1'||!Array.isArray(page.readings))throw Error('Réponse de fenêtres invalide.');
   if(!bundle){ceiling=page.ceiling;bundle={...page,readings:[],samples:{},inputs:{}}}
   if(page.ceiling!==ceiling||page.total!==bundle.total)throw Error('Export modifié pendant la lecture : réessaie.');
   for(const row of page.readings){if(ids.has(row.id))throw Error('Doublon dans les fenêtres.');ids.add(row.id);bundle.readings.push(row)}
   Object.assign(bundle.samples,page.samples);Object.assign(bundle.inputs,page.inputs);
   result.textContent=bundle.readings.length+' / '+bundle.total+' lectures récupérées…';
   if(page.next_cursor!==null&&(!Number.isInteger(page.next_cursor)||page.next_cursor<=cursor))throw Error('Pagination de fenêtres invalide.');
   cursor=page.next_cursor;
  }while(cursor!==null);
  if(bundle.readings.length!==bundle.total)throw Error('Export incomplet : réessaie.');
  bundle.complete=true;bundle.next_cursor=null;bundle.returned=bundle.readings.length;
  result.textContent=bundle.returned+' lectures exportées pour le '+day+' (Europe/Paris). Archives disponibles depuis cette mise à jour uniquement. ';
  download('StratEdge-fenetres-'+day+'.json',bundle,result);
 }catch(e){result.textContent=e.message}finally{b.disabled=false}
};
