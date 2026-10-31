
(() => {
  const body = document.body;

  // IMPORTANT: keep the sticky navigation outside the <header>.
  // A position:sticky element is constrained by its parent. When the nav
  // lives inside the header, it stops sticking as soon as the header ends.
  const header = document.querySelector('header');
  const nav = document.querySelector('.main-nav');
  if (header && nav && nav.parentElement === header) {
    header.after(nav);
  }

  const drawer = document.querySelector('.drawer');
  const overlay = document.querySelector('.drawer-overlay');
  const menuBtn = document.querySelector('.menu-btn');
  const closeBtn = document.querySelector('.drawer-close');

  const openDrawer = () => {
    if(!drawer) return;
    drawer.classList.add('open');
    overlay?.classList.add('open');
    body.classList.add('drawer-open');
    drawer.setAttribute('aria-hidden','false');
    menuBtn?.setAttribute('aria-expanded','true');
  };
  const closeDrawer = () => {
    if(!drawer) return;
    drawer.classList.remove('open');
    overlay?.classList.remove('open');
    body.classList.remove('drawer-open');
    drawer.setAttribute('aria-hidden','true');
    menuBtn?.setAttribute('aria-expanded','false');
  };
  menuBtn?.addEventListener('click',openDrawer);
  closeBtn?.addEventListener('click',closeDrawer);
  overlay?.addEventListener('click',closeDrawer);
  document.addEventListener('keydown',e=>{if(e.key==='Escape')closeDrawer()});
  drawer?.querySelectorAll('a').forEach(a=>{
    a.addEventListener('click',e=>{
      if(a.parentElement?.classList.contains('drawer-group')) return;
      closeDrawer();
    });
  });

  const syncNav = () => {
    if(!nav) return;
    // Desktop: show the compact school logo inside the sticky menu only
    // after the page has started scrolling. Mobile: keep it visible always.
    const mobile = window.innerWidth <= 760;
    nav.classList.toggle('scrolled', mobile || window.scrollY > 40);
  };
  window.addEventListener('scroll',syncNav,{passive:true});
  window.addEventListener('resize',syncNav);
  syncNav();

  const current = location.pathname.split('/').pop() || 'index.html';
  document.querySelectorAll('.nav-links a,.drawer-nav a,.side-menu a').forEach(a=>{
    const href=(a.getAttribute('href')||'').split('#')[0];
    if(href && href===current) a.classList.add('active');
  });

  const observer = new IntersectionObserver(entries=>{
    entries.forEach(e=>{if(e.isIntersecting)e.target.classList.add('show')});
  },{threshold:.08});
  document.querySelectorAll('.reveal').forEach(el=>observer.observe(el));

  // Hero slider
  const slides=[...document.querySelectorAll('.hero-slide')];
  const dots=[...document.querySelectorAll('.hero-dot')];
  let index=0;
  const showSlide=i=>{
    slides.forEach((s,n)=>s.classList.toggle('active',n===i));
    dots.forEach((d,n)=>d.classList.toggle('active',n===i));
    index=i;
  };
  dots.forEach((d,i)=>d.addEventListener('click',()=>showSlide(i)));
  if(slides.length>1) setInterval(()=>showSlide((index+1)%slides.length),5000);
})();

document.querySelectorAll('[data-demo-login]').forEach(form=>form.addEventListener('submit',e=>{e.preventDefault();const m=form.querySelector('.form-message');if(m)m.textContent='ডেমো লগইন সফল। Dashboard-এ নেওয়া হচ্ছে...';setTimeout(()=>location.href='student-dashboard.html',700)}));
document.querySelectorAll('[data-demo-register]').forEach(form=>form.addEventListener('submit',e=>{e.preventDefault();const m=form.querySelector('.form-message');if(m){m.textContent='ডেমো রেজিস্ট্রেশন সম্পন্ন হয়েছে।';m.style.color='#28723a'}}));
document.querySelectorAll('.show-pass').forEach(btn=>btn.addEventListener('click',()=>{const i=document.getElementById(btn.dataset.target);if(i){i.type=i.type==='password'?'text':'password';btn.textContent=i.type==='password'?'দেখুন':'লুকান'}}));


/* V5.8 mobile submenu accordion */
document.addEventListener("DOMContentLoaded",function(){
  document.querySelectorAll(".drawer-nav .drawer-group > a").forEach(function(btn){
    btn.addEventListener("click",function(e){
      e.preventDefault();
      e.stopPropagation();
      var group=btn.parentElement;
      document.querySelectorAll(".drawer-nav .drawer-group.open").forEach(function(x){
        if(x!==group) x.classList.remove("open");
      });
      group.classList.toggle("open");
    });
  });
});

/* V5.8 demo management workflows */
document.addEventListener("DOMContentLoaded",function(){
  const admissionForm=document.getElementById("admissionForm");
  if(admissionForm){
    admissionForm.addEventListener("submit",function(e){
      e.preventDefault();
      const id="ADM-2026-"+String(Math.floor(1000+Math.random()*8999));
      localStorage.setItem("school_last_application",JSON.stringify({id:id,status:"Submitted",name:admissionForm.elements.name?.value||"Applicant",time:new Date().toLocaleString()}));
      const msg=document.getElementById("admissionMsg");
      if(msg){msg.innerHTML="আবেদন সংরক্ষিত হয়েছে। আপনার Application ID: <b>"+id+"</b> <a href='payment.html' style='color:#673095'>পেমেন্ট করুন →</a>";msg.style.color="#28723a";}
    });
  }
  const statusForm=document.getElementById("statusForm");
  if(statusForm){
    statusForm.addEventListener("submit",function(e){
      e.preventDefault();
      const id=document.getElementById("applicationId").value.trim();
      const saved=JSON.parse(localStorage.getItem("school_last_application")||"null");
      const box=document.getElementById("statusResult");
      if(saved && saved.id===id){
        box.innerHTML="<div class='payment-box'><strong>"+saved.id+"</strong><div style='margin-top:7px'><span class='badge green'>"+saved.status+"</span></div><p style='margin:8px 0 0'>Applicant: "+saved.name+"</p><small>Submitted: "+saved.time+"</small></div>";
      }else{
        box.innerHTML="<div class='portal-note'>এই Application ID-এর কোনো demo record পাওয়া যায়নি। নতুন আবেদন করলে এই browser-এ status সংরক্ষিত হবে।</div>";
      }
    });
  }
  document.querySelectorAll("[data-gateway]").forEach(function(btn){
    btn.addEventListener("click",function(){
      document.querySelectorAll("[data-gateway]").forEach(x=>x.classList.remove("selected"));
      btn.classList.add("selected");
      const area=document.getElementById("paymentArea");
      if(area) area.innerHTML="<div class='payment-box'><b>"+btn.dataset.gateway+"</b><p style='margin:6px 0'>Demo payment checkout ready.</p><button class='btn btn-purple' type='button' onclick=\"alert('Demo payment successful. Real gateway API integration required for live transactions.')\">Proceed to Pay ৳ ১,৫০০</button></div>";
    });
  });
  document.querySelectorAll("[data-admin-login]").forEach(function(form){
    form.addEventListener("submit",function(e){e.preventDefault();location.href="admin-dashboard.html";});
  });
  document.querySelectorAll("[data-portal-login]").forEach(function(form){
    form.addEventListener("submit",function(e){e.preventDefault();location.href=form.dataset.target||"portal-login.html";});
  });
});
