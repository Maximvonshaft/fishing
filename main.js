// =====================
// App Core + Modules
// =====================
const $=(s,r=document)=>r.querySelector(s); const $$=(s,r=document)=>Array.from(r.querySelectorAll(s));
const clamp=(v,a,b)=>Math.min(b,Math.max(a,v)); const lerp=(a,b,t)=>a+(b-a)*t; const easeInOutCubic=t=>t<.5?4*t*t*t:1-Math.pow(-2*t+2,3)/2;
const currentDPR=()=>{ const mob=matchMedia('(max-width: 768px)').matches; return Math.min((window.devicePixelRatio||1), mob?1.0:1.6); };

const Env={
  get(){ const nav=navigator; const conn=nav.connection||{};
    const isMobile= matchMedia('(max-width: 768px)').matches || /Mobi|Android|iPhone|iPad|iPod/i.test(nav.userAgent);
    const reduced= matchMedia('(prefers-reduced-motion: reduce)').matches; const saveData= !!conn.saveData; const lowMem= (nav.deviceMemory||8) <= 4;
    return { isMobile, reduced, saveData, lowMem, dpr: currentDPR(), heavyOK: !reduced && !saveData && !lowMem && !isMobile };
  }
};

const App={ state:{motion:true, env:Env.get(), webgl:false}, init(){
  Core.initTime(); Core.initAnchors(); NavSpy.init(); Cursor.init();
  Universe2D.init(); Globe2D.init(); TravelMap.observe(); Control.init();
  document.addEventListener('visibilitychange',()=>{ if(document.hidden){ App.state.motion=false; } else { App.state.motion=$('#fxToggle')?$('#fxToggle').checked:true; Universe2D.restart(); if(App.state.webgl && window.Globe3D) Globe3D.restart(); } });
  if(App.state.env.heavyOK){ loadThreeThenInit(); }
  addEventListener('mousemove',e=>Globe2D.parallax(e.clientX,e.clientY),{passive:true});
  // 设备倾斜交互交由按钮触发（iOS 权限）
}};

const Core={
  initTime(){
    const y=new Date().getFullYear(); $('#year').textContent=String(y); $('#footer-year').textContent=String(y);
    const fmt=new Intl.DateTimeFormat('zh-Hans',{timeZone:'Asia/Tokyo',hour:'2-digit',minute:'2-digit'});
    const upd=()=>$('#localtime')&&($('#localtime').textContent='Tokyo '+fmt.format(new Date())); upd(); setInterval(upd,60000);
  },
  // Smooth in-page anchors
  initAnchors(){
    document.addEventListener('click',(e)=>{
      const a=e.target.closest('#sidebar .nav a[href^="#"], #mobile-bar a[href^="#"], a.btn[href^="#"]');
      if(!a) return;
      const href=a.getAttribute('href'); if(!href||href==='#') return;
      e.preventDefault();
      const t=document.querySelector(href); if(!t) return;
      const y=t.getBoundingClientRect().top+scrollY;
      try{scrollTo({top:y,behavior:'smooth'})}catch{window.scrollTo(0,y)}
      try{history.pushState(null,'',href)}catch{}
    },{passive:true});
  }
};

const NavSpy={
  sections:[], ids:['home','work','travel','writing','contact'], sideLinks:[], rail:null, mobileLinks:[],
  measure(){ this.sections=$$('#home section[data-warp-min][data-warp-max]').map(el=>({el,min:+el.dataset.warpMin,max:+el.dataset.warpMax,top:0,h:0})); this.sideLinks=$$('#sidebar .nav a'); this.rail=$('#active-rail'); this.mobileLinks=$$('#mobile-bar a'); this.sections.forEach(s=>{const r=s.el.getBoundingClientRect(); s.top=r.top+scrollY; s.h=s.el.offsetHeight;}); },
  nearest(){ const center=scrollY+innerHeight/2; let best=null,dist=1e9; for(const id of this.ids){ const el=document.getElementById(id); if(!el) continue; const r=el.getBoundingClientRect(); const mid=r.top+scrollY+r.height/2; const d=Math.abs(mid-center); if(d<dist){dist=d;best=id;} } return best; },
  update(){ const best=this.nearest(); if(!best) return;
    this.sideLinks.forEach(l=>{ const on = l.getAttribute('href')==='#'+best; l.classList.toggle('active', on); l.setAttribute('aria-current', on?'page':'false'); });
    this.mobileLinks.forEach(l=>{ const on = l.dataset.id===best; l.classList.toggle('active', on); l.setAttribute('aria-current', on?'page':'false'); });
    if(this.rail){ const link=this.sideLinks.find(l=>l.getAttribute('href')==='#'+best); if(link){ const navBox=$('#sidebar .nav').getBoundingClientRect(); const box=link.getBoundingClientRect(); this.rail.style.transform=`translateY(${Math.max(8, box.top-navBox.top)}px)`; } }
  },
  onScroll(){ const center=scrollY+innerHeight/2; let best=null,dist=1e9; for(const s of this.sections){ const mid=s.top+s.h/2; const d=Math.abs(mid-center); if(d<dist){dist=d;best=s;} } if(best){ const p=clamp((center-best.top)/best.h,0,1); Universe2D.setTarget( lerp(best.min,best.max,easeInOutCubic(p)) ); } this.update(); },
  init(){ this.measure(); addEventListener('resize',()=>{ App.state.env=Env.get(); this.measure(); },{passive:true}); addEventListener('load',()=>{this.measure(); this.update(); this.onScroll();},{passive:true}); let raf=0; addEventListener('scroll',()=>{ if(!raf) raf=requestAnimationFrame(()=>{ raf=0; this.onScroll(); }); },{passive:true}); }
};

const Cursor={ init(){ const ring=$('#cursor-ring'), dot=$('#cursor-dot'), ripple=$('#cursor-ripple'); if(!ring||!dot||!ripple) return; if(!(matchMedia('(any-pointer:fine)').matches)) return; document.body.classList.add('custom-cursor');
    let rx=innerWidth/2, ry=innerHeight/2, dx=rx, dy=ry, raf=0, vx=0, vy=0;
    const move=(e)=>{ const nx=e.clientX, ny=e.clientY; vx=nx-dx; vy=ny-dy; dx=nx; dy=ny; dot.style.left=dx+'px'; dot.style.top=dy+'px'; if(!raf) raf=requestAnimationFrame(tick); };
    const tick=()=>{ rx+=(dx-rx)*0.2; ry+=(dy-ry)*0.2; const sp=Math.hypot(vx,vy); const sc=clamp(1+Math.min(sp/120,.35),1,1.35);
      ring.style.left=rx+'px'; ring.style.top=ry+'px'; ring.style.transform=`translate(-50%,-50%) scale(${sc.toFixed(3)})`;
      raf=(Math.abs(dx-rx)>.1||Math.abs(dy-ry)>.1)?requestAnimationFrame(tick):0;
    };
    const setActive=(on)=>ring.classList.toggle('active',on);

    addEventListener('mousemove',move,{passive:true});
    addEventListener('mousedown',()=>{ dot.style.transform='translate(-50%,-50%) scale(0.88)'; if(ring.classList.contains('active')){ ring.style.transform='translate(-50%,-50%) scale(0.96)'; } ripple.style.left=dx+'px'; ripple.style.top=dy+'px'; ripple.animate([{transform:'translate(-50%,-50%) scale(1)',opacity:.55},{transform:'translate(-50%,-50%) scale(12)',opacity:0}],{duration:420,easing:'cubic-bezier(.2,.8,.2,1)'}); },{passive:true});
    addEventListener('mouseup',()=>{ dot.style.transform='translate(-50%,-50%)'; },{passive:true});

    ['a','button','[role="button"]','.btn','#sidebar .nav a','#mobile-bar a','input','textarea','select','summary','[tabindex]:not([tabindex="-1"])'].forEach(sel=>{
      document.addEventListener('mouseover',e=>{ if(e.target.closest(sel)) setActive(true); },{passive:true});
      document.addEventListener('mouseout',e=>{ if(e.target.closest(sel)) setActive(false); },{passive:true});
      document.addEventListener('focusin',e=>{ if(e.target.closest(sel)) setActive(true); },{passive:true});
      document.addEventListener('focusout',e=>{ if(e.target.closest(sel)) setActive(false); },{passive:true});
    });

    if(matchMedia('(prefers-reduced-motion: reduce)').matches) $('#cursor-ripple')?.remove();
  } };

// ===== Tiny hash-based noise + fBm =====
function _hash(x,y){ const s=Math.sin(x*127.1+y*311.7)*43758.5453123; return s-Math.floor(s); }
function noise2(x,y){ const xi=Math.floor(x), yi=Math.floor(y); const xf=x-xi, yf=y-yi;
  const a=_hash(xi,yi), b=_hash(xi+1,yi), c=_hash(xi,yi+1), d=_hash(xi+1,yi+1);
  const u=xf*xf*(3-2*xf), v=yf*yf*(3-2*yf);
  return lerp(lerp(a,b,u), lerp(c,d,u), v);
}
function fbm(x,y,oct=4, gain=0.5, lac=2.1){ let amp=1, freq=1, sum=0, norm=0; for(let i=0;i<oct;i++){ sum+=amp*noise2(x*freq,y*freq); norm+=amp; amp*=gain; freq*=lac; } return sum/norm; }

// ===== Universe 2D (mobile friendly) =====
const Universe2D=(()=>{ let canvas,ctx,width=0,height=0,dpr=1,stars=[],clouds=[],sparks=[],telemetry=[],base=0.006,speed=0.006,target=0.006,lastT=performance.now(); const env=Env.get(); const rm = env.reduced; const lightMobile = env.isMobile || env.saveData || env.lowMem; const pointer={x:innerWidth/2,y:innerHeight/2};
  const cs=()=>getComputedStyle(document.documentElement); const hsl=(h,s,l,a=1)=>`hsla(${h},${s}%,${l}%,${a})`; const rand=(a,b)=>a+Math.random()*(b-a);
  function newStar(){ const _cs=cs(); const acc=_cs.getPropertyValue('--star-cool').trim().split(/[\s/]+/).map((v,i)=>i?+v.replace('%',''):+v); const warm=_cs.getPropertyValue('--star-warm').trim().split(/[\s/]+/).map((v,i)=>i?+v.replace('%',''):+v); const z=rand(0.08,1.0); const hot=Math.random()<0.14; const [h,s,l]=(hot?warm:acc); return { x:rand(-1,1), y:rand(-1,1), z, r:rand(0.55,1.35), tw:rand(0,Math.PI*2), sp:rand(0.6,1.4), boost:Math.random()<0.02?rand(1.6,2.2):1.0, h,s,l }; }
  function newCloud(){ const _cs=cs(); const a1=_cs.getPropertyValue('--acc1').trim().split(/[\s/]+/).map((v,i)=>i?+v.replace('%',''):+v); const a2=_cs.getPropertyValue('--acc2').trim().split(/[\s/]+/).map((v,i)=>i?+v.replace('%',''):+v); const size=rand(0.45,1.05); const hue=Math.random()<0.5?a1[0]:a2[0]; const sat=Math.round(lerp(a1[1],a2[1],Math.random())*0.5); const lig=Math.round(lerp(a1[2],a2[2],Math.random())*0.32); return { x:rand(-0.7,0.7), y:rand(-0.6,0.6), s:size, a:rand(0.04,0.09), dx:rand(-0.005,0.005), dy:rand(-0.003,0.003), hue, sat, lig }; }
  function newSpark(){ const life=rand(0.25,0.6); return { x:rand(0,width), y:rand(0,height), r:rand(0.6,1.6), t:0, life }; }
  function newTelemetry(x){ const lines=Math.floor(rand(6,10)); let txt=''; for(let i=0;i<lines;i++){ txt += (Math.random()<0.5? Math.floor(rand(0,9)) : 'ABCDEF'[Math.floor(rand(0,6))]); txt+='\n'; } return { x:x??rand(0,width), y:rand(0,height), vy:-rand(12,24), size:rand(9,12), text:txt, a:rand(0.04,0.08) }; }
  function project(s,ox=0,oy=0){ const f=Math.min(width,height)*0.6; const sx=(s.x+ox)/s.z*f+width/2; const sy=(s.y+oy)/s.z*f+height/2; const depth=1-s.z; return { sx, sy, depth }; }

  function drawNebula(dt,ox,oy){
    ctx.save(); ctx.globalCompositeOperation='lighter';
    for(const c of clouds){
      c.x+=c.dx*dt*(0.5+speed*30); c.y+=c.dy*dt*(0.5+speed*30);
      if(c.x<-1.1)c.x=1.1; if(c.x>1.1)c.x=-1.1; if(c.y<-1.1)c.y=1.1; if(c.y>1.1)c.y=-1.1;
      const cxp=width/2+(c.x+ox*0.25)*width, cyp=height/2+(c.y+oy*0.25)*height;
      const rad=Math.min(width,height)*c.s;
      const g=ctx.createRadialGradient(cxp,cyp,0,cxp,cyp,rad);
      g.addColorStop(0,hsl(c.hue,c.sat,Math.min(100,c.lig+25),c.a));
      g.addColorStop(1,hsl(c.hue,c.sat,c.lig,0));
      ctx.fillStyle=g; ctx.beginPath(); ctx.arc(cxp,cyp,rad,0,Math.PI*2); ctx.fill();
    }
    ctx.restore();
  }

  function drawTelemetry(dt){ if(lightMobile) return; ctx.save(); ctx.globalCompositeOperation='screen'; ctx.textAlign='left'; ctx.textBaseline='top';
    for(const t of telemetry){ t.y+=t.vy*dt; if(t.y<-80){ t.y=height+Math.random()*80; t.x=Math.random()*width; t.text=newTelemetry(t.x).text; }
      ctx.globalAlpha=t.a; ctx.font=`${t.size}px ui-monospace,Menlo,Consolas,monospace`; const lines=t.text.split('\n'); for(let i=0;i<lines.length;i++) ctx.fillText(lines[i], t.x, t.y+i*(t.size*1.08));
    } ctx.restore();
  }

  function drawSparks(dt){ if(lightMobile) return; if(Math.random()<dt*(40*speed+2)) sparks.push(newSpark()); if(sparks.length>60) sparks.splice(0, sparks.length-60);
    ctx.save(); ctx.globalCompositeOperation='lighter';
    for(let i=sparks.length-1;i>=0;i--){ const sp=sparks[i]; sp.t+=dt; const k=1-sp.t/sp.life; if(k<=0){ sparks.splice(i,1); continue; }
      ctx.globalAlpha=0.35*k; ctx.beginPath(); ctx.arc(sp.x, sp.y-(1-k)*12, sp.r*(1+(1-k)*1.2), 0, Math.PI*2); ctx.fillStyle='rgba(255,255,255,1)'; ctx.fill();
    }
    ctx.restore();
  }

  function drawStars(dt,ox,oy){
    ctx.lineCap='round';
    for(let i=0;i<stars.length;i++){
      const s=stars[i]; const prevZ=s.z; s.z-=speed*s.boost*(rm?0.5:1); if(s.z<=0.01){ stars[i]=newStar(); continue; }
      const p=project(s,ox,oy); const q=project({x:s.x,y:s.y,z:prevZ+0.0001},ox,oy);
      const tailA=Math.min(1,0.12+p.depth*0.85);
      ctx.strokeStyle=hsl(s.h,s.s,s.l,tailA); ctx.lineWidth=Math.max(0.5, p.depth*(s.boost>1?3:2)*(1+speed*100));
      ctx.beginPath(); ctx.moveTo(q.sx,q.sy); ctx.lineTo(p.sx,p.sy); ctx.stroke();
      s.tw+=dt*s.sp; const tw=0.7+0.3*Math.sin(s.tw); const dotA=clamp(tw*(0.35+p.depth*0.75),0,1); ctx.fillStyle=`rgba(255,255,255,${dotA})`; const rr=Math.max(0.45, s.r*(0.6+p.depth*0.9));
      ctx.beginPath(); ctx.arc(p.sx,p.sy,rr,0,Math.PI*2); ctx.fill();
    }
  }

  function vignetteAndPorthole(){
    const cx=width/2, cy=height/2; const r=Math.min(width,height)*0.55;
    const g=ctx.createRadialGradient(cx,cy,r*0.9,cx,cy,Math.max(width,height)*0.7); g.addColorStop(0,'rgba(0,0,0,0)'); g.addColorStop(1,'rgba(0,0,0,0.62)'); ctx.fillStyle=g; ctx.beginPath(); ctx.rect(0,0,width,height); ctx.fill();
    ctx.save(); ctx.globalCompositeOperation='screen'; const css=getComputedStyle(document.documentElement); const port=css.getPropertyValue('--porthole').trim().split(/[\s/]+/).map((v,i)=>i?+v.replace('%',''):+v); const hud=css.getPropertyValue('--hud').trim().split(/[\s/]+/).map((v,i)=>i?+v.replace('%',''):+v);
    ctx.strokeStyle=`hsla(${port[0]},${port[1]}%,${port[2]}%,0.25)`; ctx.lineWidth=r*0.015; ctx.beginPath(); ctx.arc(cx,cy,r*0.98,0,Math.PI*2); ctx.stroke();
    let arcR=r*1.04; ctx.lineWidth=r*0.02; ctx.strokeStyle=`hsla(${hud[0]},${hud[1]}%,${Math.min(100,hud[2]+10)}%,0.10)`; ctx.beginPath(); ctx.arc(cx- r*0.12, cy- r*0.25, arcR, Math.PI*1.12, Math.PI*1.55); ctx.stroke(); ctx.beginPath(); ctx.arc(cx+ r*0.18, cy+ r*0.20, arcR*0.9, Math.PI*0.15, Math.PI*0.42); ctx.stroke();
    ctx.lineWidth=r*0.008; ctx.strokeStyle=`hsla(${hud[0]},${hud[1]}%,${hud[2]}%,0.18)`; for(let i=0;i<12;i++){ const a=i/12*Math.PI*2; const x1=cx+Math.cos(a)*r*0.94, y1=cy+Math.sin(a)*r*0.94; const x2=cx+Math.cos(a)*r*0.985, y2=cy+Math.sin(a)*r*0.985; ctx.beginPath(); ctx.moveTo(x1,y1); ctx.lineTo(x2,y2); ctx.stroke(); } ctx.restore();
  }

  function resizeAll(){ width=innerWidth; height=innerHeight; dpr=currentDPR(); canvas.width=Math.floor(width*dpr); canvas.height=Math.floor(height*dpr); canvas.style.width=width+'px'; canvas.style.height=height+'px'; ctx.setTransform(dpr,0,0,dpr,0,0);} 
  function resize(){ const area=width*height; const density= rm?0.00012 : lightMobile?0.00020:0.00030; const count=Math.min(1200,Math.max(180,Math.floor(area*density))); stars=new Array(count).fill(0).map(newStar); clouds=new Array(rm?4: lightMobile?10:16).fill(0).map(newCloud); telemetry= lightMobile? [] : new Array(rm?4:10).fill(0).map(()=>newTelemetry()); }
  function step(t){ if(!App.state.motion) return; const dt=Math.min(0.05,(t-lastT)/1000); lastT=t; speed+=(target-speed)*Math.min(1,dt*4); ctx.fillStyle='rgba(0,0,0,0.60)'; ctx.fillRect(0,0,width,height);
    const ox=(pointer.x-width/2)/width*0.18, oy=(pointer.y-height/2)/height*0.18; drawNebula(dt,ox,oy); drawStars(dt,ox,oy); drawSparks(dt); drawTelemetry(dt); vignetteAndPorthole(); requestAnimationFrame(step);
  }
  return { init(){ canvas=document.getElementById('universe'); if(!canvas||!canvas.getContext) return; ctx=canvas.getContext('2d',{alpha:true}); if(!ctx) return; resizeAll(); resize(); requestAnimationFrame(step);
      addEventListener('resize',()=>{ resizeAll(); resize(); App.state.env=Env.get(); },{passive:true});
      addEventListener('mousemove',e=>{ pointer.x=e.clientX; pointer.y=e.clientY; },{passive:true});
      addEventListener('touchmove',e=>{ if(e.touches&&e.touches[0]){ pointer.x=e.touches[0].clientX; pointer.y=e.touches[0].clientY; } },{passive:true});
    }, setTarget(v){ target=v; }, restart(){ if(!App.state.motion) return; requestAnimationFrame(step); } };
})();

// ===== 2D CSS Globe (default on mobile) =====
const Globe2D=(()=>{ let root; return {
  init(){ root=$('#globe2d'); if(!root) return; },
  hide(){ root && (root.style.display='none'); },
  show(){ root && (root.style.display='grid'); },
  parallax(x,y){ const el=$('.g2d-wrap'); if(!el) return; const dx=(x/innerWidth-0.5)*10; const dy=(y/innerHeight-0.5)*8; el.style.transform=`translate(${10+dx}%, ${10+dy}%)`; },
  tilt(beta,gamma){ const sphere=$('.g2d-sphere'); if(!sphere) return; sphere.style.transform=`rotateX(${clamp(beta,-10,10)}deg) rotateY(${clamp(gamma,-10,10)}deg)`; }
}})();

// ===== 3D WebGL Globe (lazy opt-in) =====
const Globe3D=(()=>{ let canvas,renderer,scene,camera,earth,clouds,atmo,width=0,height=0; function ensure(){ return canvas && renderer && scene; } return { init(){ canvas=document.getElementById('globe'); if(!canvas||typeof THREE==='undefined') return; canvas.classList.remove('hidden'); Globe2D.hide(); const env=App.state.env; renderer=new THREE.WebGLRenderer({canvas,antialias:true,alpha:true,powerPreference: env.isMobile? 'low-power' : 'high-performance'}); renderer.setPixelRatio(currentDPR()); width=innerWidth;height=innerHeight; renderer.setSize(width,height,false); scene=new THREE.Scene(); camera=new THREE.PerspectiveCamera(55,width/height,0.1,100); camera.position.set(0,0,7); scene.add(new THREE.AmbientLight(0xffffff, env.isMobile?0.5:0.6)); const dir=new THREE.DirectionalLight(0xffffff, env.isMobile?1.0:1.2); dir.position.set(-5,2,5); scene.add(dir); const loader=new THREE.TextureLoader(); const texColor=loader.load( env.isMobile? 'https://threejs.org/examples/textures/planets/earth_atmos_1024.jpg' : 'https://threejs.org/examples/textures/planets/earth_atmos_2048.jpg'); const texNormal=loader.load('https://threejs.org/examples/textures/planets/earth_normal_1024.jpg'); const texSpec=loader.load('https://threejs.org/examples/textures/planets/earth_specular_1024.jpg'); const texCloud=loader.load('https://threejs.org/examples/textures/planets/earth_clouds_1024.png'); const geo=new THREE.SphereGeometry(env.isMobile? 1.9:2.2, env.isMobile? 48:64, env.isMobile? 32:64); const mat=new THREE.MeshPhongMaterial({map:texColor,normalMap:texNormal,specularMap:texSpec,specular:new THREE.Color(0x333333),shininess:12}); earth=new THREE.Mesh(geo,mat); earth.position.set(2.0,-1.0,-1.8); earth.scale.set(env.isMobile?1.9:2.1, env.isMobile?1.9:2.1, env.isMobile?1.9:2.1); scene.add(earth); const cloudGeo=new THREE.SphereGeometry(earth.geometry.parameters.radius*1.015,48,48); const cloudMat=new THREE.MeshLambertMaterial({map:texCloud,transparent:true,opacity: env.isMobile?0.40:0.5,depthWrite:false}); clouds=new THREE.Mesh(cloudGeo,cloudMat); clouds.position.copy(earth.position); clouds.scale.copy(earth.scale).multiplyScalar(1.006); scene.add(clouds); const atmoGeo=new THREE.SphereGeometry(earth.geometry.parameters.radius*1.035,48,48); const atmoMat=new THREE.MeshBasicMaterial({color:0xff8a33,transparent:true,opacity: env.isMobile?0.14:0.18,blending:THREE.AdditiveBlending,side:THREE.BackSide}); atmo=new THREE.Mesh(atmoGeo,atmoMat); atmo.position.copy(earth.position); atmo.scale.copy(earth.scale).multiplyScalar(1.04); scene.add(atmo); let last=performance.now(), spin=0, px=0.0, py=0.0, raf=0; const tick=(t)=>{ if(!App.state.motion) { raf=requestAnimationFrame(tick); return; } const dt=Math.min(0.05,(t-last)/1000); last=t; spin+=dt*0.06; const ox=(px/width-0.5), oy=(py/height-0.5); const parY=ox*0.6, parX=oy*0.25; earth.rotation.x+=(parX-earth.rotation.x)*0.06; earth.rotation.y=spin+parY; clouds.rotation.x=earth.rotation.x*1.05; clouds.rotation.y=spin*1.15+parY; atmo.rotation.copy(earth.rotation); renderer.render(scene,camera); raf=requestAnimationFrame(tick); }; addEventListener('mousemove',e=>{ px=e.clientX; py=e.clientY; },{passive:true}); addEventListener('touchmove',e=>{ if(e.touches&&e.touches[0]){ px=e.touches[0].clientX; py=e.touches[0].clientY; } },{passive:true}); const onResize=()=>{ width=innerWidth;height=innerHeight; renderer.setSize(width,height,false); renderer.setPixelRatio(currentDPR()); camera.aspect=width/height; camera.updateProjectionMatrix(); }; addEventListener('resize',onResize,{passive:true}); requestAnimationFrame(tick); App.state.webgl=true; }, restart(){ if(ensure()) requestAnimationFrame(()=>renderer && renderer.render(scene,camera)); } } })();

// ===== Travel Map (world + table) =====
const TRAVEL_DATA=[
  { code:'JP', country:'日本', visits:[ {city:'东京', date:'2024-08'}, {city:'京都', date:'2023-04'} ] },
  { code:'CN', country:'中国', visits:[ {city:'上海', date:'2019-05'} ] },
  { code:'US', country:'美国', visits:[ {city:'旧金山', date:'2018-09'} ] },
  { code:'FR', country:'法国', visits:[ {city:'巴黎', date:'2022-07'} ] }
];
const CITY_COORDS={ '东京':[35.6762,139.6503], '京都':[35.0116,135.7681], '上海':[31.2304,121.4737], '旧金山':[37.7749,-122.4194], '巴黎':[48.8566,2.3522] };

const TravelMap={ map:null, observer:null,
  observe(){ const target=$('#travel'); if(!('IntersectionObserver' in window) || !target){ this.init(); return; }
    this.observer=new IntersectionObserver((entries)=>{ entries.forEach(entry=>{ if(entry.isIntersecting && !this.map){ this.init(); this.observer.disconnect(); } }); },{root:null,rootMargin:'200px',threshold:0.01}); this.observer.observe(target); },
  async init(){ try{
    if(typeof jsVectorMap==='undefined'){
      await injectScript('https://cdn.jsdelivr.net/npm/jsvectormap/dist/js/jsvectormap.min.js');
      await injectScript('https://cdn.jsdelivr.net/npm/jsvectormap/dist/maps/world-merc.js');
    }
    // 若包含 CN，则同步选中 TW（视觉一致）
    const set=new Set(TRAVEL_DATA.map(d=>d.code));
    if(set.has('CN')) set.add('TW');
    const visited=[...set];

    const markers=TRAVEL_DATA.flatMap(d=> d.visits.map(v=>({ name:`${d.country} · ${v.city} · ${v.date}`, coords: CITY_COORDS[v.city]||null }))).filter(m=>Array.isArray(m.coords));

    this.map=new jsVectorMap({
      selector:'#worldmap',
      map:'world_merc',
      backgroundColor:'transparent',
      zoomOnScroll:false,
      regionsSelectable:true,
      selectedRegions: visited,
      regionStyle:{
        initial:{ fill:'rgba(255,255,255,0.06)' },
        hover:{ fill:'rgba(255,255,255,0.22)' },
        selected:{ fill:'hsla(28,100%,56%,.88)' }
      },
      markers,
      markerStyle:{
        initial:{ r:4, fill:'hsla(28,100%,56%,.9)', stroke:'rgba(0,0,0,.4)', strokeWidth:2 },
        hover:{ fill:'#fff' }
      }
    });
    this.renderTable();
    if('ResizeObserver' in window){ const ro=new ResizeObserver(()=>this.map&&this.map.updateSize()); ro.observe($('#worldmap')); }
  }catch(e){ console.warn('TravelMap init skipped', e); } },
  renderTable(){ const tbody=$('#travel-table'); const stats=$('#travel-stats'); if(!tbody||!stats) return; tbody.innerHTML=''; let i=1,total=0; const sorted=TRAVEL_DATA.slice().sort((a,b)=> a.country.localeCompare(b.country,'zh-Hans')); for(const d of sorted){ const last=d.visits.slice(-1)[0]?.date||''; const cities=d.visits.map(v=>v.city).join('、'); total+=d.visits.length; const tr=document.createElement('tr'); tr.className='hover:bg-white/5 cursor-pointer'; tr.innerHTML=`<td class="py-2 pr-2 opacity-70">${i++}</td><td class="py-2 pr-2">${d.country}</td><td class="py-2 pr-2">${cities}</td><td class="py-2">${last}</td>`;
      tr.addEventListener('click',()=>{ try{ this.map && (this.map.setSelected ? this.map.setSelected({regions:[d.code]}) : this.map.setSelectedRegions([d.code])); }catch{} });
      tbody.appendChild(tr);
    }
    stats.textContent=`国家 ${new Set(TRAVEL_DATA.map(x=>x.code)).size} · 城市 ${total}`;
  }
};

// ===== Controls (仅动效 + 3D & 陀螺仪启用) =====
const Control={ init(){ const fx=$('#fxToggle'); const btn=$('#enable3d');
    if(fx){ fx.checked=!matchMedia('(prefers-reduced-motion: reduce)').matches; App.state.motion=fx.checked; fx.addEventListener('change',()=>{ App.state.motion=fx.checked; if(fx.checked){ Universe2D.restart(); if(App.state.webgl) Globe3D.restart(); } }); }
    if(btn){ btn.addEventListener('click', async ()=>{ await loadThreeThenInit(); await enableMotionTilt(); btn.closest('#opt3d')?.remove(); }); }
  } };

// ===== Helpers =====
function injectScript(src){ return new Promise((resolve,reject)=>{ const s=document.createElement('script'); s.src=src; s.async=true; s.onload=resolve; s.onerror=reject; document.head.appendChild(s); }); }
async function loadThreeThenInit(){ if(window.THREE){ Globe3D.init(); return; } try{ await injectScript('https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.min.js'); Globe3D.init(); }catch(e){ console.warn('three.js 失败，保留 2D 备用', e); } }

// iOS 陀螺仪权限（与 3D 按钮一起触发）
async function enableMotionTilt(){
  try{
    if('DeviceOrientationEvent' in window){
      if(typeof DeviceOrientationEvent.requestPermission==='function'){
        const p=await DeviceOrientationEvent.requestPermission().catch(()=>null);
        if(p!=='granted') return;
      }
      const handler=(e)=>Globe2D.tilt(e.beta||0,e.gamma||0);
      window.addEventListener('deviceorientation',handler,{passive:true});
    }
  }catch{}
}

// Bootstrap
(function(){ try{ App.init(); }catch(e){ console.error(e); } })();
