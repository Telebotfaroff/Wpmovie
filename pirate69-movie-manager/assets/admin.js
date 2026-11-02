(function () {
  'use strict';
  var selectAll = document.getElementById('p69-select-all-results');
  if (selectAll) {
    selectAll.addEventListener('change', function () {
      document.querySelectorAll('.p69-result-checkbox').forEach(function (checkbox) { checkbox.checked = selectAll.checked; });
    });
  }
  var worker = document.querySelector('[data-p69-worker]');
  if (!worker || typeof P69Importer === 'undefined') return;
  var ids = [];
  try { ids = JSON.parse(worker.getAttribute('data-job-ids') || '[]'); } catch (e) {}
  var index = 0;
  var busy = false;
  function step() {
    if (busy || index >= ids.length) {
      if (index >= ids.length) {
        worker.textContent = 'All active jobs have been checked. Refreshing the queue…';
        window.setTimeout(function () { window.location.reload(); }, 1400);
      }
      return;
    }
    busy = true;
    var body = new URLSearchParams({action:'pirate69_import_worker',nonce:P69Importer.workerNonce,job_id:String(ids[index])});
    fetch(P69Importer.ajaxUrl, {method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},body:body.toString()})
      .then(function (r) { return r.json(); })
      .then(function (r) {
        if (!r || !r.success) { index++; return; }
        var job = r.data;
        var row = document.querySelector('[data-p69-job="' + job.id + '"]');
        if (row) {
          var progress = row.querySelector('progress');
          var label = row.querySelector('.p69-progress-label');
          var status = row.querySelector('.p69-status');
          var pct = job.total ? Math.min(100, Math.floor(job.processed / job.total * 100)) : 0;
          if (progress) progress.value = pct;
          if (label) label.textContent = job.processed + ' / ' + (job.total || '—') + ' · ' + pct + '%';
          if (status) { status.textContent = job.status; status.className = 'p69-status p69-status-' + job.status; }
        }
        if (job.status !== 'pending' && job.status !== 'running') index++;
      })
      .catch(function () { worker.textContent = P69Importer.strings.failed; })
      .finally(function () { busy = false; window.setTimeout(step, index >= ids.length ? 200 : 650); });
  }
  step();
})();
