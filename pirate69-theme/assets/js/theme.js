(()=>{'use strict';const button=document.querySelector('.menu-toggle');const nav=document.querySelector('#primary-navigation');if(!button||!nav)return;button.addEventListener('click',()=>{const open=button.getAttribute('aria-expanded')==='true';button.setAttribute('aria-expanded',String(!open));nav.classList.toggle('is-open',!open);});})();
  document.querySelectorAll('.p69-quality-filters').forEach(function (filters) {
    filters.addEventListener('click', function (event) {
      var button = event.target.closest('button[data-p69-quality]');
      if (!button) return;
      var selected = button.getAttribute('data-p69-quality');
      filters.querySelectorAll('button').forEach(function (item) { item.classList.toggle('is-active', item === button); });
      var section = filters.closest('.p69-download-section');
      if (!section) return;
      section.querySelectorAll('[data-p69-download-group]').forEach(function (group) {
        group.hidden = selected !== 'all' && group.getAttribute('data-p69-download-group') !== selected;
      });
    });
  });
