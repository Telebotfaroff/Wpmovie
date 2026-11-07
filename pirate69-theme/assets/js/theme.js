(()=>{'use strict';
const menuButton=document.querySelector('.menu-toggle');
const nav=document.querySelector('#primary-navigation');
if(menuButton&&nav){menuButton.addEventListener('click',()=>{const open=menuButton.getAttribute('aria-expanded')==='true';menuButton.setAttribute('aria-expanded',String(!open));nav.classList.toggle('is-open',!open);});}
document.querySelectorAll('.p69-download-section').forEach(section=>{
 const qualityButtons=[...section.querySelectorAll('[data-p69-quality]')];
 const typeButtons=[...section.querySelectorAll('[data-p69-server-type]')];
 const audioSelect=section.querySelector('[data-p69-audio-filter]');
 let selectedQuality=qualityButtons.find(b=>b.classList.contains('is-active'))?.dataset.p69Quality||'all';
 let selectedType='all';
 const selectedAudio=()=>audioSelect?audioSelect.value:'all';
 function applyFilters(){
  section.querySelectorAll('[data-p69-download-group]').forEach(group=>{
   const qualityMatch=selectedQuality==='all'||group.dataset.p69DownloadGroup===selectedQuality;
   group.hidden=!qualityMatch;
   if(!qualityMatch)return;
   let visible=0;
   group.querySelectorAll('.p69-download-card').forEach(card=>{
    const typeMatch=selectedType==='all'||card.dataset.p69ServerType===selectedType;
    const audioMatch=selectedAudio()==='all'||card.dataset.p69Audio===selectedAudio();
    const show=typeMatch&&audioMatch;
    card.hidden=!show;
    if(show)visible++;
   });
   const empty=group.querySelector('.p69-no-filter-results');
   if(empty)empty.hidden=visible>0;
  });
 }
 qualityButtons.forEach(button=>button.addEventListener('click',()=>{
  selectedQuality=button.dataset.p69Quality;
  qualityButtons.forEach(item=>{const active=item===button;item.classList.toggle('is-active',active);item.setAttribute('aria-pressed',String(active));});
  applyFilters();
 }));
 typeButtons.forEach(button=>button.addEventListener('click',()=>{
  selectedType=button.dataset.p69ServerType;
  typeButtons.forEach(item=>{const active=item===button;item.classList.toggle('is-active',active);item.setAttribute('aria-pressed',String(active));});
  applyFilters();
 }));
 if(audioSelect)audioSelect.addEventListener('change',applyFilters);
 applyFilters();
});
})();