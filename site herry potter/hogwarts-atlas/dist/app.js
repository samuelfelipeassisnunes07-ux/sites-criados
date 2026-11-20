const $=s=>document.querySelector(s);
const $$=s=>[...document.querySelectorAll(s)];
let world,worldPromise,places,current='exterior',transition=0,returnFocus=null,scrollBefore=0;
let tour=null,tourPaused=false,tourTime=0,tourIndex=0,toastTimer,audioContext,audioGain,soundEnabled=false;
const tourOrder=['exterior','hall','gryffindor','slytherin','ravenclaw','hufflepuff','library'];
const labels={exterior:['O castelo','Torres, pontes e o Lago Negro'],hall:['Grande Salão','Sob um céu encantado'],gryffindor:['Grifinória','Coragem, vermelho e dourado'],slytherin:['Sonserina','O mistério do Lago Negro'],ravenclaw:['Corvinal','Sabedoria e um céu de estrelas'],hufflepuff:['Lufa-Lufa','O calor de pertencer'],library:['Biblioteca','Um mundo em cada estante']};

function toast(message){$('#toast').textContent=message;$('#toast').classList.add('show');clearTimeout(toastTimer);toastTimer=setTimeout(()=>$('#toast').classList.remove('show'),3800);}
function setModes(walk){$('#orbit-mode').classList.toggle('selected',!walk);$('#walk-mode').classList.toggle('selected',walk);$('#orbit-mode').setAttribute('aria-pressed',String(!walk));$('#walk-mode').setAttribute('aria-pressed',String(walk));$('.walk-controls').hidden=!walk;$('#control-help').innerHTML=walk?'W A S D ou setas para caminhar <span>·</span> Arraste para olhar':'Arraste para olhar ao redor <span>·</span> Role para aproximar';}
function renderHotspots(hotspots){$('#hotspots').replaceChildren();hotspots.forEach((spot,i)=>{const button=document.createElement('button');button.id=`hotspot-${i}`;button.className='hotspot';button.hidden=true;const plus=document.createElement('span');plus.textContent='+';const text=document.createElement('span');text.textContent=spot.title;button.append(plus,text);button.setAttribute('aria-label',`${spot.destination?'Visitar':'Descobrir'}: ${spot.title}`);button.onclick=()=>{if(spot.destination){stopTour();enterWorld(spot.destination);return;}$('#discovery h3').textContent=spot.title;$('#discovery p').textContent=spot.text;$('#discovery').hidden=false;$('.close-discovery').focus();};$('#hotspots').append(button);});}
async function ensureWorld(){if(!worldPromise){worldPromise=import('./world.js').then(module=>{places=module.places;world=new module.CastleWorld($('#world'),renderHotspots);return world;}).catch(error=>{worldPromise=null;throw error;});}return worldPromise;}
async function enterWorld(id='exterior',fromTour=false){
  if(!labels[id])id='exterior';const ticket=++transition;current=id;if(!$('#map-dialog').open&&!fromTour)returnFocus=document.activeElement;
  if($('#experience').hidden){scrollBefore=scrollY;$('#landing').hidden=true;$('#experience').hidden=false;document.body.classList.add('exploring');}
  if($('#map-dialog').open)$('#map-dialog').close();$('#loading-world').hidden=false;$('#world-error').hidden=true;$('#discovery').hidden=true;
  history.replaceState(null,'',`#visitar/${id}`);
  try{await new Promise(r=>requestAnimationFrame(()=>requestAnimationFrame(r)));const castle=await ensureWorld();if(ticket!==transition)return;castle.active=true;castle.load(id);const p=places[id];$('#scene-title').textContent=p.name;$('#scene-eyebrow').textContent=p.eyebrow;$('#scene-description').textContent=p.description;$('#scene-description').style.color='';$('#walk-mode').disabled=id==='exterior';$('#walk-mode').title=id==='exterior'?'Entre em um ambiente para caminhar':'Caminhar por este ambiente';$$('[data-scene]').forEach(b=>{b.classList.toggle('active',b.dataset.scene===id);b.setAttribute('aria-current',b.dataset.scene===id?'location':'false');});setModes(false);$('#loading-world').hidden=true;$('#experience').dataset.ready=id;if(fromTour)castle.controls.autoRotate=id==='exterior'&&!castle.reduced;else $('#leave-world').focus({preventScroll:true});}
  catch(error){console.error('Não foi possível iniciar o castelo:',error);if(ticket===transition){$('#loading-world').hidden=true;$('#world-error').hidden=false;$('#experience').dataset.ready='error';}}
}
function leaveWorld(){transition++;stopTour();if(world){world.active=false;world.keys.clear();}$('#experience').hidden=true;$('#landing').hidden=false;document.body.classList.remove('exploring');history.replaceState(null,'',location.pathname+location.search);scrollTo(0,scrollBefore);if(returnFocus?.isConnected&&returnFocus.closest('#landing'))returnFocus.focus({preventScroll:true});else $('.brand').focus({preventScroll:true});}
$$('[data-enter]').forEach(button=>button.addEventListener('click',()=>{stopTour();enterWorld(button.dataset.enter);}));
$$('[data-scene]').forEach(button=>button.addEventListener('click',()=>{stopTour();enterWorld(button.dataset.scene);}));
$('#leave-world').onclick=leaveWorld;$('#retry-world').onclick=()=>enterWorld(current);
$('.close-discovery').onclick=()=>{$('#discovery').hidden=true;$('#hotspots button:not([hidden])')?.focus();};
$('#orbit-mode').onclick=()=>{if(world){world.setWalking(false);setModes(false);}};
$('#walk-mode').onclick=()=>{if(world&&current!=='exterior'){stopTour();world.setWalking(true);setModes(true);toast('Use W A S D, as setas ou os botões na tela. Arraste para olhar.');}};
$('#reset-camera').onclick=()=>{if(world){world.resetCamera();setModes(false);toast('Câmera restaurada.');}};
$('#day-toggle').onclick=()=>{if(world){world.setNight(!world.night);$('#day-toggle').textContent=world.night?'☾':'☀';$('#day-toggle').setAttribute('aria-label',world.night?'Mudar para o dia':'Mudar para a noite');toast(world.night?'A noite chegou a Hogwarts.':'A luz do dia revela novos detalhes.');}};
$('#quality-toggle').onclick=()=>{const high=$('#quality-toggle').getAttribute('aria-pressed')!=='true';$('#quality-toggle').setAttribute('aria-pressed',String(high));$('#quality-toggle').textContent=high?'HD':'ECO';$('#quality-toggle').setAttribute('aria-label',`Qualidade gráfica: ${high?'alta':'econômica'}`);world?.setQuality(high);toast(high?'Qualidade alta ativada.':'Modo leve ativado: menos sombras e menor resolução.');};
$('#fullscreen').onclick=async()=>{try{if(document.fullscreenElement)await document.exitFullscreen();else if($('#experience').requestFullscreen)await $('#experience').requestFullscreen();else toast('Tela cheia indisponível neste navegador.');}catch{toast('A tela cheia não está disponível nesta janela.');}};
$('#scene-info-toggle').onclick=()=>{const collapsed=$('.scene-heading').classList.toggle('collapsed');$('#scene-info-toggle').textContent=collapsed?'Mostrar descrição':'Ocultar descrição';$('#scene-info-toggle').setAttribute('aria-expanded',String(!collapsed));};
$$('[data-move]').forEach(button=>{button.addEventListener('pointerdown',e=>{e.preventDefault();button.setPointerCapture(e.pointerId);world?.move(button.dataset.move,true);});for(const event of ['pointerup','pointercancel','lostpointercapture'])button.addEventListener(event,()=>world?.move(button.dataset.move,false));});

// The map uses the same destinations as the actual 3D navigation.
Object.entries(labels).forEach(([id,[title,subtitle]],i)=>{const button=document.createElement('button');button.className='map-destination';button.innerHTML=`<span>0${i+1}</span><span>${title}<small>${subtitle}</small></span><svg><use href="#i-arrow"/></svg>`;button.onclick=()=>{stopTour();enterWorld(id);};$('#map-destinations').append(button);});
let pausedForMap=false;
$$('[data-map]').forEach(button=>button.onclick=()=>{pausedForMap=!!tour&&!tourPaused;if(pausedForMap)pauseTour();world?.keys.clear();$('#map-dialog').showModal();});
$('#close-map').onclick=()=>$('#map-dialog').close();
$('#map-dialog').addEventListener('click',e=>{const r=e.currentTarget.getBoundingClientRect();if(e.clientX<r.left||e.clientX>r.right||e.clientY<r.top||e.clientY>r.bottom)e.currentTarget.close();});
$('#map-dialog').addEventListener('close',()=>{if(pausedForMap&&tour&&tourPaused)pauseTour();pausedForMap=false;});
document.addEventListener('keydown',e=>{if(e.key==='Escape'&&!$('#map-dialog').open&&!$('#experience').hidden){if(!$('#discovery').hidden)$('#discovery').hidden=true;else if(world?.walking){world.setWalking(false);setModes(false);}else leaveWorld();}});

// A finite guided visit. Its clock pauses while loading, hidden, or in the map.
async function startTour(){stopTour();tourIndex=0;tourTime=0;tourPaused=false;$('.tour-controls').hidden=false;$('#tour-pause').textContent='Ⅱ';await enterWorld(tourOrder[0],true);if($('#experience').hidden)return;tour=setInterval(()=>{if(tourPaused||document.hidden||!$('#loading-world').hidden||$('#map-dialog').open)return;tourTime+=100;$('#tour-progress').style.width=`${tourTime/15000*100}%`;if(tourTime>=15000)nextTour();},100);updateTourLabel();}
function updateTourLabel(){$('#tour-label').textContent=`VISITA GUIADA · ${tourIndex+1} / ${tourOrder.length}`;}
function nextTour(){if(tourIndex>=tourOrder.length-1){stopTour();toast('Visita concluída. Continue explorando no seu ritmo.');return;}tourIndex++;tourTime=0;$('#tour-progress').style.width='0%';updateTourLabel();enterWorld(tourOrder[tourIndex],true);}
function pauseTour(){tourPaused=!tourPaused;$('#tour-pause').textContent=tourPaused?'▷':'Ⅱ';$('#tour-pause').setAttribute('aria-label',tourPaused?'Retomar visita guiada':'Pausar visita guiada');if(world)world.controls.autoRotate=!tourPaused&&current==='exterior'&&!world.reduced;}
function stopTour(){clearInterval(tour);tour=null;tourPaused=false;$('.tour-controls').hidden=true;if(world)world.controls.autoRotate=false;}
$('#start-tour').onclick=startTour;$('#tour-pause').onclick=pauseTour;$('#tour-next').onclick=nextTour;$('#tour-stop').onclick=stopTour;

// Quiet original ambience, generated locally. Audio starts only after a click.
async function toggleSound(){
  try{
    if(!audioContext){const AudioContext=window.AudioContext||window.webkitAudioContext;if(!AudioContext){toast('Som ambiente indisponível neste navegador.');return;}audioContext=new AudioContext();audioGain=audioContext.createGain();audioGain.gain.value=0;audioGain.connect(audioContext.destination);const size=audioContext.sampleRate*5,buffer=audioContext.createBuffer(1,size,audioContext.sampleRate),data=buffer.getChannelData(0);let previous=0;for(let i=0;i<size;i++){previous=(previous+(Math.random()*2-1)*.025)/1.025;data[i]=previous*3;}const noise=audioContext.createBufferSource();noise.buffer=buffer;noise.loop=true;const filter=audioContext.createBiquadFilter();filter.type='lowpass';filter.frequency.value=480;noise.connect(filter);filter.connect(audioGain);noise.start();for(const frequency of [130.81,196,261.63]){const oscillator=audioContext.createOscillator(),gain=audioContext.createGain();oscillator.type='sine';oscillator.frequency.value=frequency;gain.gain.value=.012;oscillator.connect(gain);gain.connect(audioGain);oscillator.start();}}
    await audioContext.resume();soundEnabled=!soundEnabled;audioGain.gain.setTargetAtTime(soundEnabled?.3:0,audioContext.currentTime,.6);$$('.sound-toggle').forEach(b=>{b.setAttribute('aria-pressed',String(soundEnabled));b.setAttribute('aria-label',soundEnabled?'Desativar som ambiente':'Ativar som ambiente');b.querySelector('use').setAttribute('href',soundEnabled?'#i-sound':'#i-muted');});toast(soundEnabled?'Som ambiente ativado.':'Som ambiente desativado.');
  }catch(error){console.warn('Áudio indisponível:',error);toast('Não foi possível ativar o som neste navegador.');}
}
$$('.sound-toggle').forEach(button=>button.onclick=toggleSound);
document.addEventListener('visibilitychange',()=>{if(audioContext&&soundEnabled)audioGain.gain.setTargetAtTime(document.hidden?0:.3,audioContext.currentTime,.5);});

if(!matchMedia('(prefers-reduced-motion: reduce)').matches){for(let i=0;i<22;i++){const ember=document.createElement('i');ember.className='ember';ember.style.cssText=`left:${Math.random()*100}%;top:${25+Math.random()*70}%;--duration:${8+Math.random()*12}s;--delay:-${Math.random()*20}s`;$('.embers').append(ember);}}
const entry=location.hash.match(/^#visitar\/(\w+)$/);if(entry&&labels[entry[1]])enterWorld(entry[1]);
