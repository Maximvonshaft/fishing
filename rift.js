// Time-space rift jump effect
const Rift=(()=>{
  const DURATION=1200; // ms
  let overlay,canvas,ctx,stars=[];

  function init(){
    overlay=document.createElement('div');
    overlay.id='rift-overlay';
    canvas=document.createElement('canvas');
    overlay.appendChild(canvas);
    document.body.appendChild(overlay);
    ctx=canvas.getContext('2d');
    resize();
    addEventListener('resize',resize);
  }

  function resize(){
    if(!canvas) return;
    canvas.width=innerWidth;
    canvas.height=innerHeight;
  }

  function runAnimation(done){
    overlay.classList.add('show');
    const count=120;
    stars=Array.from({length:count},()=>({x:(Math.random()-0.5)*2,y:(Math.random()-0.5)*2,z:Math.random()}));
    let start=null;
    function step(t){
      if(start===null) start=t;
      const elapsed=t-start;
      ctx.fillStyle='#000';
      ctx.fillRect(0,0,canvas.width,canvas.height);
      for(const s of stars){
        s.z-=0.02;
        if(s.z<=0){s.x=(Math.random()-0.5)*2; s.y=(Math.random()-0.5)*2; s.z=1;}
        const sx=(s.x/s.z)*canvas.width/2+canvas.width/2;
        const sy=(s.y/s.z)*canvas.height/2+canvas.height/2;
        const r=(1-s.z)*2;
        ctx.beginPath();
        ctx.fillStyle=`rgba(255,255,255,${1-s.z})`;
        ctx.arc(sx,sy,r,0,Math.PI*2);
        ctx.fill();
      }
      if(elapsed<DURATION) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
    setTimeout(()=>{
      overlay.classList.add('flash');
      setTimeout(()=>{
        overlay.classList.remove('show','flash');
        done();
      },100);
    },DURATION);
  }

  function start(href){
    if(!overlay) init();
    runAnimation(()=>{
      if(href.startsWith('#')){
        const target=document.querySelector(href);
        if(target){
          const y=target.getBoundingClientRect().top+scrollY;
          try{scrollTo({top:y,behavior:'smooth'});}catch{scrollTo(0,y);} 
          try{history.pushState(null,'',href);}catch{}
        }
      }else{
        location.href=href;
      }
    });
  }

  document.addEventListener('click',e=>{
    const a=e.target.closest('a');
    if(!a||a.target==='_blank'||e.metaKey||e.ctrlKey||e.shiftKey) return;
    const href=a.getAttribute('href');
    if(!href) return;
    e.preventDefault();
    e.stopPropagation();
    start(href);
  },true);

  return{start};
})();
export{};
