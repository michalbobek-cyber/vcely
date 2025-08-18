<?php
require_once __DIR__.'/../lib/db.php';
$uid=current_user()['id']; $id=(int)($_GET['id']??0);
if (!user_can_access_device($uid,$id,'viewer')){ http_response_code(403); echo 'Forbidden'; exit; }
$s=db()->prepare('SELECT * FROM vcely_devices WHERE id=?'); $s->execute([$id]); $dev=$s->fetch(); if(!$dev){ http_response_code(404); echo 'Not found'; exit; }
// email subscribe stav (pro aktuálního uživatele)
$st=db()->prepare('SELECT 1 FROM vcely_alert_subscriptions WHERE device_id=? AND user_id=?'); $st->execute([$id,$uid]); $is_sub=(bool)$st->fetchColumn();
// načti device settings
$setst=db()->prepare('SELECT enable_alerts,min_drop_g_24h,min_rise_g_24h FROM vcely_device_settings WHERE device_id=?');
$setst->execute([$id]); $set=$setst->fetch() ?: ['enable_alerts'=>1,'min_drop_g_24h'=>500,'min_rise_g_24h'=>500];

header_html('Zařízení '.$dev['name']); ?>
<h2><?= h($dev['name']) ?> <small>#<?= h($dev['id']) ?></small></h2>
<div class="row">
  <a class="btn" href="<?= BASE_URL ?>/api/export_csv?device_id=<?= $dev['id'] ?>&range=7d">CSV 7 dní</a>
  <a class="btn" href="<?= BASE_URL ?>/api/export_csv?device_id=<?= $dev['id'] ?>&range=30d">CSV 30 dní</a>
  <label class="inline"><input type="checkbox" id="subChk" <?= $is_sub?'checked':''; ?>> E‑mail notifikace</label>
</div>

<?php if (user_can_access_device($uid,$id,'editor')): ?>
<div class="card" style="margin-top:10px">
  <h3>Nastavení alertů</h3>
  <form method="post" action="<?= BASE_URL ?>/api/device_settings.php" class="row">
    <input type="hidden" name="device_id" value="<?= (int)$dev['id'] ?>">
    <input type="hidden" name="redirect" value="<?= BASE_URL ?>/device?id=<?= (int)$dev['id'] ?>">
    <label class="inline">
      <input type="checkbox" name="enable_alerts" <?= !empty($set['enable_alerts'])?'checked':''; ?>>
      Povolit generování alertů
    </label>
    <label>Úbytek za 24 h [g]
      <input type="number" name="min_drop_g_24h" step="1" min="0" value="<?= isset($set['min_drop_g_24h']) ? h($set['min_drop_g_24h']) : '' ?>" placeholder="např. 500 (prázdné = vypnuto)">
    </label>
    <label>Nárůst za 24 h [g]
      <input type="number" name="min_rise_g_24h" step="1" min="0" value="<?= isset($set['min_rise_g_24h']) ? h($set['min_rise_g_24h']) : '' ?>" placeholder="např. 500 (prázdné = vypnuto)">
    </label>
    <button class="btn">Uložit</button>
  </form>
  <p class="muted">Tip: prázdné pole = typ alertu se nevytváří. E‑maily chodí jen odběratelům (checkbox výše).</p>
</div>
<?php endif; ?>

<div class="row" style="margin-top:8px">
  <span>Rozsah grafu:</span>
  <button class="btn" data-range="24h">24 h</button>
  <button class="btn" data-range="7d">7 dní</button>
  <button class="btn" data-range="30d">30 dní</button>
  <button class="btn active" data-range="all">Vše</button>
</div>
<canvas id="chart" height="180" style="margin-top:10px"></canvas>
<p id="deltas" class="muted"></p>
<div class="card">
  <h3>Poslední alerty</h3>
  <ul id="alertsList" class="muted"></ul>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const rangeBtns=[...document.querySelectorAll('button[data-range]')];
let allData=[], activeRange='all', chart;
function filterByRange(data, range){ if(range==='all') return data; const hours=range==='24h'?24:(range==='7d'?168:720); const lastTs=new Date(data[data.length-1].ts); return data.filter(x => ((lastTs - new Date(x.ts))/3600000) <= hours); }
async function loadData(){ const r=await fetch("<?= BASE_URL ?>/api/readings?device_id=<?= (int)$dev['id'] ?>&limit=5000"); allData=await r.json(); render(); }
async function loadAlerts(){ const r=await fetch("<?= BASE_URL ?>/api/alerts?device_id=<?= (int)$dev['id'] ?>"); const arr=await r.json(); const ul=document.getElementById('alertsList'); ul.innerHTML=''; if(!arr.length){ ul.innerHTML='<li>Žádné alerty</li>'; return; } arr.forEach(a=>{ const li=document.createElement('li'); li.textContent=a.created_at+' – '+a.message; ul.appendChild(li); }); }
function render(){ if(!allData.length) return; const data=filterByRange(allData, activeRange); const labels=data.map(x=>x.ts); const w=data.map(x=>x.weight_g); const t=data.map(x=>x.temp_c); const h=data.map(x=>x.hum_pct); const ctx=document.getElementById('chart').getContext('2d'); if(chart) chart.destroy(); chart=new Chart(ctx,{type:'line',data:{labels,datasets:[{label:'Hmotnost [g]',data:w,yAxisID:'y'},{label:'Teplota [°C]',data:t,yAxisID:'y1'},{label:'Vlhkost [%]',data:h,yAxisID:'y1'}]},options:{interaction:{mode:'index',intersect:false},maintainAspectRatio:false,scales:{y:{type:'linear',position:'left'},y1:{type:'linear',position:'right',grid:{drawOnChartArea:false}}}}}); if(data.length>1){ const last=data[data.length-1], first24=data.find(x=> (new Date(last.ts)-new Date(x.ts))/3600000>=24); const first7=data.find(x=> (new Date(last.ts)-new Date(x.ts))/3600000>=168); let s=''; if(first24){ s+='Změna 24 h: '+(last.weight_g-first24.weight_g).toFixed(1)+' g. '; } if(first7){ s+='Změna 7 dní: '+(last.weight_g-first7.weight_g).toFixed(1)+' g.'; } document.getElementById('deltas').textContent=s; } }
rangeBtns.forEach(b=>b.addEventListener('click', ev=>{ rangeBtns.forEach(x=>x.classList.remove('active')); ev.target.classList.add('active'); activeRange=ev.target.dataset.range; render(); }));
document.getElementById('subChk')?.addEventListener('change', async (ev)=>{ const f=new FormData(); f.append('device_id','<?= (int)$dev['id'] ?>'); f.append('subscribe', ev.target.checked ? '1' : '0'); await fetch("<?= BASE_URL ?>/api/subscribe_alerts.php", { method:'POST', body:f }); });
loadData(); loadAlerts(); setInterval(loadData,60000); setInterval(loadAlerts,60000);
</script>
<?php footer_html(); ?>