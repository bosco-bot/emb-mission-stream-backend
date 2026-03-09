<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Monitoring système - EMB Mission</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body{font-family:system-ui,sans-serif;background:#f1f5f9;color:#1e293b;min-height:100vh}
        .card{background:#fff;border:1px solid #e2e8f0;border-radius:12px}
        .card-header{background:#e2e8f0;color:#1e293b;font-weight:600;border-radius:12px 12px 0 0;padding:.75rem 1rem}
        .badge-ok{background:#22c55e}
        .badge-ko{background:#ef4444}
        .table{color:#334155}
        .table td,.table th{border-color:#e2e8f0}
        .table-hover tbody tr:hover{background:#f1f5f9}
        .refresh-info{font-size:.875rem;color:#64748b}
    </style>
</head>
<body>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h1 class="h3 mb-0"><i class="fas fa-server me-2"></i>Monitoring système</h1>
        <div class="d-flex align-items-center gap-3">
            <button type="button" class="btn btn-sm d-none" id="btnWebTVPauseToggle">
                <i class="fas fa-pause"></i> <span id="webtvPauseText">Suspendre WebTV</span>
            </button>
            <span class="refresh-info" id="lastUpdate">—</span>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="btnRefresh"><i class="fas fa-sync-alt"></i> Rafraîchir</button>
        </div>
    </div>
    <div id="loading" class="text-center py-5">
        <div class="spinner-border text-primary" role="status"></div>
        <p class="mt-2 text-secondary">Chargement…</p>
    </div>
    <div id="error" class="alert alert-danger d-none" role="alert"></div>
    <div id="content" class="d-none">
        <div class="row g-3">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">Services système</div>
                    <div class="card-body"><div class="row g-2" id="servicesList"></div></div>
                </div>
            </div>
            <div class="col-12">
                <div class="card">
                    <div class="card-header">Workers Laravel</div>
                    <div class="card-body"><div class="row g-2" id="workersList"></div></div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header"><i class="fas fa-hdd me-2"></i>Espace disque</div>
                    <div class="card-body"><ul class="list-unstyled mb-0 small" id="diskList"></ul></div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header"><i class="fas fa-code-branch me-2"></i>Versions</div>
                    <div class="card-body"><ul class="list-unstyled mb-0" id="versionsList"></ul></div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header"><i class="fas fa-link me-2"></i>Liens de stockage</div>
                    <div class="card-body"><ul class="list-unstyled mb-0 small" id="storageLinksList"></ul></div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header"><i class="fas fa-exclamation-triangle me-2"></i>Dernières erreurs Laravel</div>
                    <div class="card-body"><ul class="list-unstyled mb-0 small" id="logErrorsList"></ul></div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>Jobs (file d'attente)</span>
                        <button type="button" class="btn btn-sm btn-warning d-none" id="btnRetryAll"><i class="fas fa-redo me-1"></i>Relancer tout</button>
                    </div>
                    <div class="card-body">
                        <p class="mb-2">En attente: <strong id="jobsPending">0</strong> — En échec: <strong id="jobsFailed">0</strong></p>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0">
                                <thead><tr><th>Queue</th><th>Exception</th><th></th></tr></thead>
                                <tbody id="failedJobsList"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">Tâches cron</div>
                    <div class="card-body"><ul class="list-unstyled mb-0" id="cronList"></ul></div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">Jobs audio / radio</div>
                    <div class="card-body"><ul class="list-unstyled mb-0" id="audioJobsList"></ul></div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">Alertes RTMP</div>
                    <div class="card-body"><ul class="list-unstyled mb-0 small" id="rtmpAlertsList"></ul></div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
(function(){
    var statusUrl = '{{ route("system-monitoring.status") }}';
    var restartUrl = '{{ route("system-monitoring.restart-service") }}';
    var stopUrl = '{{ route("system-monitoring.stop-service") }}';
    var retryJobUrl = '{{ route("system-monitoring.retry-job") }}';
    var retryAllUrl = '{{ route("system-monitoring.retry-all-jobs") }}';
    var csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    var serviceToSystemd = {'nginx':'nginx','php-fpm':'php8.2-fpm','mysql':'mysql','antmedia':'antmedia','ffmpeg-live-transcode':'ffmpeg-live-transcode','supervisor':'supervisor','cron':'cron','docker':'docker','queue-worker':'laravel-queue-worker','unified-stream':'unified-stream','reverb':'laravel-reverb'};
    function headers(){ return {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf,'X-Requested-With':'XMLHttpRequest'}; }
    function showLoading(s){ document.getElementById('loading').classList.toggle('d-none',!s); document.getElementById('content').classList.toggle('d-none',s); document.getElementById('error').classList.add('d-none'); }
    function showError(m){ document.getElementById('error').textContent=m; document.getElementById('error').classList.remove('d-none'); }
    function esc(s){ var d=document.createElement('div'); d.textContent=s; return d.innerHTML; }
    function renderServices(svc,desc){
        var el=document.getElementById('servicesList'); el.innerHTML='';
        for(var k in svc){ var s=svc[k], name=s.name||k, d=(desc&&desc[name])||(desc&&desc[k])||'', sys=serviceToSystemd[k]||name;
            el.innerHTML+='<div class="col-12 col-md-6 col-lg-4"><div class="border rounded p-2" style="background:#f8fafc;border-color:#e2e8f0!important;color:#334155"><span class="badge '+(s.active?'badge-ok':'badge-ko')+' me-2">'+(s.active?'Actif':'Inactif')+'</span><strong>'+esc(name)+'</strong>'+(d?'<br><small class="text-secondary">'+esc(d)+'</small>':'')+'<div class="mt-2"><button type="button" class="btn btn-outline-secondary btn-sm restart-svc" data-service="'+esc(sys)+'">Redémarrer</button> <button type="button" class="btn btn-outline-danger btn-sm ms-1 stop-svc" data-service="'+esc(sys)+'">Arrêter</button></div></div></div>';
        }
        el.querySelectorAll('.restart-svc').forEach(function(b){ b.onclick=function(){ if(confirm('Redémarrer '+this.dataset.service+' ?')) fetch(restartUrl,{method:'POST',headers:headers(),body:JSON.stringify({service:this.dataset.service})}).then(function(r){return r.json();}).then(function(d){ alert(d.success?d.message:d.error||'Erreur'); if(d.success) loadStatus(); }).catch(function(e){ alert(e.message); }); }; });
        el.querySelectorAll('.stop-svc').forEach(function(b){ b.onclick=function(){ if(confirm('Arrêter '+this.dataset.service+' ?')) fetch(stopUrl,{method:'POST',headers:headers(),body:JSON.stringify({service:this.dataset.service})}).then(function(r){return r.json();}).then(function(d){ alert(d.success?d.message:d.error||'Erreur'); if(d.success) loadStatus(); }).catch(function(e){ alert(e.message); }); }; });
    }
    function renderWorkers(w,desc){
        var el=document.getElementById('workersList'); el.innerHTML='';
        var labels={'queue-worker':'Queue worker','unified-stream':'Unified Stream','reverb':'Reverb'};
        for(var k in w){ var s=w[k], run=s.running!==false&&s.active, name=s.name||serviceToSystemd[k]||k, d=(desc&&desc[name])||(desc&&desc[k])||'', sys=serviceToSystemd[k]||name;
            el.innerHTML+='<div class="col-12 col-md-4"><div class="border rounded p-2" style="background:#f8fafc;border-color:#e2e8f0!important;color:#334155"><span class="badge '+(run?'badge-ok':'badge-ko')+' me-2">'+(run?'En cours':'Arrêté')+'</span><strong>'+esc(labels[k]||name)+'</strong>'+(d?'<br><small class="text-secondary">'+esc(d)+'</small>':'')+'<div class="mt-2"><button type="button" class="btn btn-outline-secondary btn-sm restart-svc" data-service="'+esc(sys)+'">Redémarrer</button> <button type="button" class="btn btn-outline-danger btn-sm ms-1 stop-svc" data-service="'+esc(sys)+'">Arrêter</button></div></div></div>';
        }
        el.querySelectorAll('.restart-svc').forEach(function(b){ b.onclick=function(){ if(confirm('Redémarrer '+this.dataset.service+' ?')) fetch(restartUrl,{method:'POST',headers:headers(),body:JSON.stringify({service:this.dataset.service})}).then(function(r){return r.json();}).then(function(d){ alert(d.success?d.message:d.error||'Erreur'); if(d.success) loadStatus(); }).catch(function(e){ alert(e.message); }); }; });
        el.querySelectorAll('.stop-svc').forEach(function(b){ b.onclick=function(){ if(confirm('Arrêter '+this.dataset.service+' ?')) fetch(stopUrl,{method:'POST',headers:headers(),body:JSON.stringify({service:this.dataset.service})}).then(function(r){return r.json();}).then(function(d){ alert(d.success?d.message:d.error||'Erreur'); if(d.success) loadStatus(); }).catch(function(e){ alert(e.message); }); }; });
    }
    function renderJobs(j){
        document.getElementById('jobsPending').textContent=j.pending||0;
        document.getElementById('jobsFailed').textContent=j.failed||0;
        var list=document.getElementById('failedJobsList'), recent=j.recent_failed||[];
        list.innerHTML='';
        document.getElementById('btnRetryAll').classList.toggle('d-none',!recent.length);
        recent.forEach(function(job){ var exc=(job.exception||'').substring(0,80);
            list.innerHTML+='<tr><td>'+esc(job.queue||job.connection||'—')+'</td><td class="small" title="'+esc(job.exception||'')+'">'+esc(exc)+(job.exception&&job.exception.length>80?'…':'')+'</td><td><button type="button" class="btn btn-sm btn-outline-warning retry-one" data-id="'+job.id+'">Relancer</button></td></tr>';
        });
        list.querySelectorAll('.retry-one').forEach(function(b){ b.onclick=function(){ fetch(retryJobUrl,{method:'POST',headers:headers(),body:JSON.stringify({job_id:parseInt(this.dataset.id,10)})}).then(function(r){return r.json();}).then(function(d){ if(d.success) loadStatus(); else alert(d.error); }).catch(function(e){ alert(e.message); }); }; });
    }
    function renderCron(t){ var el=document.getElementById('cronList'); el.innerHTML=(t||[]).map(function(x){ return '<li class="mb-2"><strong>'+esc(x.name)+'</strong> — '+esc(x.schedule)+'<br><small class="text-secondary">'+esc(x.description)+'</small></li>'; }).join(''); }
    function renderAudio(a){ var el=document.getElementById('audioJobsList'); el.innerHTML=(a||[]).map(function(x){ return '<li class="mb-2"><strong>'+esc(x.name)+'</strong> <span class="badge bg-secondary">'+esc(x.type)+'</span><br><small class="text-secondary">'+esc(x.description)+'</small></li>'; }).join(''); }
    function renderRtmp(al){ var el=document.getElementById('rtmpAlertsList'); if(!al||!al.length){ el.innerHTML='<li class="text-secondary">Aucune alerte récente.</li>'; return; } el.innerHTML=al.map(function(a){ return '<li class="mb-1">'+esc(a.timestamp)+' | '+esc(a.message)+'</li>'; }).join(''); }
    function renderDisk(disk){
        var el=document.getElementById('diskList');
        if(!disk||!disk.length){ el.innerHTML='<li class="text-secondary">—</li>'; return; }
        el.innerHTML=disk.map(function(d){
            if(d.error) return '<li class="mb-2"><strong>'+esc(d.label)+'</strong><br><span class="text-danger">'+esc(d.error)+'</span></li>';
            var pct=d.used_percent||0, ok=d.ok!==false;
            return '<li class="mb-2"><strong>'+esc(d.label)+'</strong> <span class="badge '+(ok?'badge-ok':'badge-ko')+'">'+pct+'% utilisé</span><br><small>'+d.used_gb+' Go / '+d.total_gb+' Go (libre: '+d.free_gb+' Go)</small></li>';
        }).join('');
    }
    function renderVersions(v){
        var el=document.getElementById('versionsList');
        if(!v||typeof v!='object'){ el.innerHTML='<li>—</li>'; return; }
        el.innerHTML=['php','laravel','mysql'].map(function(k){ return '<li class="mb-1"><strong>'+k+'</strong>: '+(v[k]||'N/A')+'</li>'; }).join('');
    }
    function renderStorageLinks(links){
        var el=document.getElementById('storageLinksList');
        if(!links||!links.length){ el.innerHTML='<li>—</li>'; return; }
        el.innerHTML=links.map(function(l){
            var ok=l.exists;
            return '<li class="mb-2"><span class="badge '+(ok?'badge-ok':'badge-ko')+' me-2">'+(ok?'OK':'Manquant')+'</span> '+esc(l.name)+(l.target?' → '+esc(l.target):'')+'</li>';
        }).join('');
    }
    function renderLogErrors(data){
        var el=document.getElementById('logErrorsList');
        if(data&&data.error){ el.innerHTML='<li class="text-secondary">'+esc(data.error)+'</li>'; return; }
        var lines=(data&&data.lines)||[];
        if(!lines.length){ el.innerHTML='<li class="text-success">Aucune erreur récente.</li>'; return; }
        el.innerHTML=lines.map(function(l){ return '<li class="mb-1 text-danger" title="'+esc(l.text)+'">'+esc(l.text)+'</li>'; }).join('');
    }
    function loadStatus(){
        showLoading(true);
        fetch(statusUrl).then(function(r){ return r.json(); }).then(function(data){
            showLoading(false);
            if(!data.success){ showError(data.error||'Erreur'); return; }
            var d=data.data;
            document.getElementById('lastUpdate').textContent='Mis à jour: '+(d.timestamp||'');
            
            var btnToggle = document.getElementById('btnWebTVPauseToggle');
            var txtToggle = document.getElementById('webtvPauseText');
            btnToggle.classList.remove('d-none');
            if (d.webtv_system_paused) {
                btnToggle.className = 'btn btn-sm btn-success';
                btnToggle.innerHTML = '<i class="fas fa-play"></i> Reprendre WebTV';
                btnToggle.onclick = function() {
                    if(!confirm('Êtes-vous sûr de vouloir reprendre la diffusion WebTV ?')) return;
                    btnToggle.disabled = true;
                    fetch('/api/webtv-auto-playlist/resume', {method:'POST', headers:headers()})
                        .then(r => r.json()).then(res => { btnToggle.disabled=false; alert(res.message||'OK'); loadStatus(); })
                        .catch(e => { btnToggle.disabled=false; alert(e.message); });
                };
            } else {
                btnToggle.className = 'btn btn-sm btn-danger';
                btnToggle.innerHTML = '<i class="fas fa-pause"></i> Suspendre WebTV';
                btnToggle.onclick = function() {
                    if(!confirm('Êtes-vous sûr de vouloir suspendre la diffusion WebTV ? (Coupure du direct et VOD)')) return;
                    btnToggle.disabled = true;
                    fetch('/api/webtv-auto-playlist/stop', {method:'POST', headers:headers()})
                        .then(r => r.json()).then(res => { btnToggle.disabled=false; alert(res.message||'OK'); loadStatus(); })
                        .catch(e => { btnToggle.disabled=false; alert(e.message); });
                };
            }

            renderServices(d.services||{},d.service_descriptions||{});
            renderWorkers(d.workers||{},d.service_descriptions||{});
            renderDisk(d.disk);
            renderVersions(d.versions);
            renderStorageLinks(d.storage_links);
            renderLogErrors(d.laravel_log_errors);
            renderJobs(d.jobs||{});
            renderCron(d.cron_tasks);
            renderAudio(d.audio_jobs);
            renderRtmp(d.rtmp_alerts);
        }).catch(function(err){ showLoading(false); showError('Chargement: '+err.message); });
    }
    document.getElementById('btnRefresh').onclick=loadStatus;
    document.getElementById('btnRetryAll').onclick=function(){ fetch(retryAllUrl,{method:'POST',headers:headers(),body:'{}'}).then(function(r){return r.json();}).then(function(d){ alert(d.success?d.message:d.error||'Erreur'); if(d.success) loadStatus(); }).catch(function(e){ alert(e.message); }); };
    loadStatus();
    setInterval(loadStatus,30000);
})();
</script>
</body>
</html>
