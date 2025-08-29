<?php
require_once __DIR__.'/../lib/db.php';
$uid = current_user()['id'];
$id  = (int)($_GET['id'] ?? 0);
if (!user_can_access_device($uid,$id,'viewer')){ http_response_code(403); echo 'Forbidden'; exit; }

$s = db()->prepare('SELECT * FROM vcely_devices WHERE id=?'); $s->execute([$id]); $dev=$s->fetch();
if(!$dev){ http_response_code(404); echo 'Not found'; exit; }

$st = db()->prepare('SELECT 1 FROM vcely_alert_subscriptions WHERE device_id=? AND user_id=?');
$st->execute([$id,$uid]); $is_sub = (bool)$st->fetchColumn();

$canEdit = user_can_access_device($uid,$id,'editor');

$set = db()->prepare('SELECT enable_alerts, min_drop_g_24h, min_rise_g_24h,
                             instant_alert_enabled, instant_delta_g, instant_window_min, instant_cooldown_min
                        FROM vcely_device_settings
                       WHERE device_id=?');
$set->execute([$id]); $settings = $set->fetch();
if (!$settings){
  $ins = db()->prepare('INSERT INTO vcely_device_settings (device_id, enable_alerts, min_drop_g_24h, min_rise_g_24h) VALUES (?,?,?,?)');
  $ins->execute([$id, 1, 800, 500]);
  $settings = ['enable_alerts'=>1, 'min_drop_g_24h'=>800, 'min_rise_g_24h'=>500];
}

header_html('Zařízení '.$dev['name']);
?>
<style>
/* Responsivní výška grafu – mobil/tablet/desktop/wide */
:root{
  --chart-h-mobile: 280px;
  --chart-h-tablet: 360px;
  --chart-h-desktop: 440px;
  --chart-h-wide: 520px;
}
.chart-box{position:relative; height:var(--chart-h-mobile); max-height:70vh; min-height:220px; width:100%;}
@media (min-width: 600px){ .chart-box{ height:var(--chart-h-tablet); } }
@media (min-width: 992px){ .chart-box{ height:var(--chart-h-desktop); } }
@media (min-width: 1280px){ .chart-box{ height:var(--chart-h-wide); } }
/* V landscape na menších zařízeních zvedneme limit, ale držíme rozumné maximum */
@media (orientation: landscape) and (max-width: 820px){
  .chart-box{ height:320px; max-height:75vh; }
}
#chart{display:block; width:100% !important; height:100% !important;}
</style>

<h2><?= h($dev['name']) ?> <small>#<?= h($dev['id']) ?></small></h2>

<div class="row">
  <a class="btn" href="<?= BASE_URL ?>/api/export_csv.php?device_id=<?= $dev['id'] ?>&range=7d">CSV 7 dní</a>
  <a class="btn" href="<?= BASE_URL ?>/api/export_csv.php?device_id=<?= $dev['id'] ?>&range=30d">CSV 30 dní</a>
  <label class="inline"><input type="checkbox" id="subChk" <?= $is_sub?'checked':''; ?>> E‑mail notifikace</label>
</div>

<div class="row" style="margin-top:8px">
  <span>Dataset:</span>
  <button class="btn active" data-dataset="abs">Absolutní</button>
  <button class="btn" data-dataset="d24">Δ 24 h</button>
  <button class="btn" data-dataset="d7">Δ 7 dní</button>
  <button class="btn" data-dataset="daily">Denní průměr</button>
</div>

<div class="row" style="margin-top:8px">
  <span>Rozsah grafu:</span>
  <button class="btn" data-range="24h">24 h</button>
  <button class="btn" data-range="7d">7 dní</button>
  <button class="btn" data-range="30d">30 dní</button>
  <button class="btn active" data-range="all">Vše</button>
</div>

<div class="row" style="margin-top:8px">
  <span>Měřítko Y:</span>
  <button class="btn active" data-yscale="robust">Stabilní</button>
  <button class="btn" data-yscale="auto">Auto</button>
  <button class="btn" data-yscale="fixed">Pevné</button>
  <label class="inline" id="fixedInputs" style="display:none">
    Min <input type="number" id="yMin" step="100" style="max-width:120px">
    Max <input type="number" id="yMax" step="100" style="max-width:120px">
  </label>
  <label class="inline" id="clampWrap">
    <input type="checkbox" id="clampZero" checked>
    Nezáporné (jen hmotnost)
  </label>
</div>

<div class="chart-box">
  <canvas id="chart"></canvas>
</div>
<p id="deltas" class="muted"></p>

<?php if ($canEdit): ?>
<div class="card" style="margin-top:10px">
  <h3>Nastavení alertů</h3>
  <form id="alertForm" class="row">
    <label class="inline">
      <input type="checkbox" name="enable_alerts" <?= !empty($settings['enable_alerts'])?'checked':''; ?>>
      Aktivní
    </label>
    <label>Prahový úbytek za 24 h [g]
      <input type="number" name="min_drop_g_24h" min="0" step="1"
             value="<?= is_null($settings['min_drop_g_24h']) ? '' : h($settings['min_drop_g_24h']) ?>"
             placeholder="např. 800">
    </label>
    <label>Prahový nárůst za 24 h [g]
      <input type="number" name="min_rise_g_24h" min="0" step="1"
             value="<?= is_null($settings['min_rise_g_24h']) ? '' : h($settings['min_rise_g_24h']) ?>"
             placeholder="např. 500">
    </label>
	<label><input type="checkbox" name="instant_alert_enabled" value="1" <?= !empty($settings['instant_alert_enabled'])?'checked':''; ?>> Okamžité alerty rychlého skoku</label>

<label>Práh skoku [g]
	<input type="number" name="instant_delta_g" value="<?= isset($settings['instant_delta_g']) ? (int)$settings['instant_delta_g'] : 3000 ?>" min="100" step="50">
</label>

<label>Okno [min]
 <input type="number" name="instant_window_min" value="<?= isset($settings['instant_window_min']) ? (int)$settings['instant_window_min'] : 10 ?>" min="1" step="1">
</label>

<label>Cooldown [min]
<input type="number" name="instant_cooldown_min" value="<?= isset($settings['instant_cooldown_min']) ? (int)$settings['instant_cooldown_min'] : 60 ?>" min="0" step="1">
</label>

    <input type="hidden" name="device_id" value="<?= (int)$dev['id'] ?>">
    <button class="btn">Uložit</button>
    <span id="alertSaveMsg" class="muted"></span>
  </form>
</div>
<?php endif; ?>

<div class="card" style="margin-top:10px">
  <h3>Poslední alerty</h3>
  <ul id="alertsList" class="muted"></ul>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(function(){
  const datasetBtns=[...document.querySelectorAll('button[data-dataset]')];
  const rangeBtns=[...document.querySelectorAll('button[data-range]')];
  const scaleBtns=[...document.querySelectorAll('button[data-yscale]')];
  let allData=[], dailyData=[], activeRange='all', chart, yScaleMode='robust', datasetMode='abs';
  let yMinFixed=null, yMaxFixed=null, clampZero=true;

  function toggleButtons(list, target){ list.forEach(x=>x.classList.remove('active')); target.classList.add('active'); }
  function showClamp(){ document.getElementById('clampWrap').style.display = (datasetMode==='abs') ? '' : 'none'; }
  function updateFixedInputs(){ document.getElementById('fixedInputs').style.display = (yScaleMode==='fixed') ? '' : 'none'; }

  function percentile(arr, p){
    const a = arr.filter(x => Number.isFinite(x)).slice().sort((x,y)=>x-y);
    if (!a.length) return null;
    const idx = (p/100)*(a.length-1);
    const lo = Math.floor(idx), hi = Math.ceil(idx);
    if (lo===hi) return a[lo];
    return a[lo] + (a[hi]-a[lo])*(idx-lo);
  }
  function robustBoundsAbs(values){
    const w = values.filter(x => Number.isFinite(x));
    if (!w.length) return {};
    const p25 = percentile(w,25), p50 = percentile(w,50), p75 = percentile(w,75);
    const iqr = (p75 - p25) || 0;
    const span = Math.max(3*iqr, 1200);
    const last = w[w.length-1];
    let min = Math.min(p50 - span, last - span);
    let max = Math.max(p50 + span, last + span);
    if (clampZero) min = Math.max(0, min);
    if (min >= max) max = min + 100;
    return {min, max};
  }
  function robustBoundsDelta(values){
    const v = values.filter(x=>Number.isFinite(x)).map(Math.abs);
    if (!v.length) return {};
    const p95 = percentile(v,95) || 0;
    const r = Math.max(p95 * 1.15, 300);
    return {min: -r, max: r};
  }
  function filterByRange(data, range){
    if(!data.length) return data;
    if(range==='all') return data;
    const hours = range==='24h'?24:(range==='7d'?168:720);
    const lastTs = new Date(data[data.length-1].ts || (data[data.length-1].day+'T00:00:00Z'));
    const cutoff = new Date(lastTs.getTime() - hours*3600000);
    return data.filter(x => new Date(x.ts || (x.day+'T00:00:00Z')) >= cutoff);
  }
  function computeDelta(series, hours){
    let res=[]; let j=0;
    for(let i=0;i<series.length;i++){
      const t = new Date(series[i].ts);
      const target = new Date(t.getTime() - hours*3600000);
      while (j < i && new Date(series[j].ts) < target) j++;
      const base = series[j] || series[0];
      const d = (series[i].weight_g != null && base.weight_g != null) ? (series[i].weight_g - base.weight_g) : null;
      res.push({ts: series[i].ts, delta: d, temp_c: series[i].temp_c, hum_pct: series[i].hum_pct});
    }
    return res;
  }

  async function loadData(){
    const r = await fetch("<?= BASE_URL ?>/api/readings.php?device_id=<?= (int)$dev['id'] ?>&limit=5000");
    let arr = await r.json();
    if (arr.length >= 2 && new Date(arr[0].ts) > new Date(arr[arr.length-1].ts)) arr = arr.reverse();
    allData = arr;

    const r2 = await fetch("<?= BASE_URL ?>/api/readings_daily.php?device_id=<?= (int)$dev['id'] ?>");
    dailyData = await r2.json();

    render();
  }
  async function loadAlerts(){
    const r = await fetch("<?= BASE_URL ?>/api/alerts.php?device_id=<?= (int)$dev['id'] ?>");
    const arr = await r.json();
    const ul=document.getElementById('alertsList'); ul.innerHTML='';
    if(!arr.length){ ul.innerHTML='<li>Žádné alerty</li>'; return; }
    arr.forEach(a=>{ const li=document.createElement('li'); li.textContent=a.created_at+' – '+a.message; ul.appendChild(li); });
  }

  function render(){
    let data, labels, mainValues, yLabel, t, h, bounds;
    if (datasetMode==='abs'){
      data = filterByRange(allData, activeRange);
      labels = data.map(x=>x.ts);
      mainValues = data.map(x=>x.weight_g);
      yLabel = 'Hmotnost [g]';
      bounds = robustBoundsAbs(mainValues);
      t = data.map(x=>x.temp_c);
      h = data.map(x=>x.hum_pct);
    } else if (datasetMode==='d24' || datasetMode==='d7'){
      const series = filterByRange(allData, activeRange);
      const deltas = computeDelta(series, datasetMode==='d24' ? 24 : 24*7);
      data = deltas;
      labels = deltas.map(x=>x.ts);
      mainValues = deltas.map(x=>x.delta);
      yLabel = (datasetMode==='d24' ? 'Δ 24 h [g]' : 'Δ 7 dní [g]');
      bounds = robustBoundsDelta(mainValues);
      t = deltas.map(x=>x.temp_c);
      h = deltas.map(x=>x.hum_pct);
    } else {
      data = filterByRange(dailyData, activeRange);
      labels = data.map(x=>x.day);
      mainValues = data.map(x=>x.weight_avg_g);
      yLabel = 'Denní průměr [g]';
      bounds = robustBoundsAbs(mainValues);
      t = data.map(x=>x.temp_avg_c);
      h = data.map(x=>x.hum_avg_pct);
    }

    const ctx = document.getElementById('chart').getContext('2d');
    if(chart) chart.destroy();
    chart = new Chart(ctx, {
      type:'line',
      data:{ labels, datasets:[
        {label:yLabel, data:mainValues, yAxisID:'y', pointRadius:0, borderWidth:2, tension:0.2},
        {label:(datasetMode==='daily'?'Teplota průměr [°C]':'Teplota [°C]'), data:t, yAxisID:'y1', pointRadius:0, borderWidth:1},
        {label:(datasetMode==='daily'?'Vlhkost průměr [%]':'Vlhkost [%]'), data:h, yAxisID:'y1', pointRadius:0, borderWidth:1}
      ]},
      options:{
        responsive:true,
        maintainAspectRatio:false,
        animation:false,
        interaction:{mode:'index', intersect:false},
        scales:{
          y:{type:'linear', position:'left'},
          y1:{type:'linear', position:'right', grid:{drawOnChartArea:false}, suggestedMin:0, suggestedMax:100}
        },
        plugins:{legend:{display:true}}
      }
    });
    if (yScaleMode==='fixed' && yMinFixed!=null && yMaxFixed!=null){
      chart.options.scales.y.min = yMinFixed;
      chart.options.scales.y.max = yMaxFixed;
    } else if (yScaleMode==='robust' && bounds){
      if (bounds.min!=null) chart.options.scales.y.min = bounds.min;
      if (bounds.max!=null) chart.options.scales.y.max = bounds.max;
    } else {
      chart.options.scales.y.min = undefined;
      chart.options.scales.y.max = undefined;
    }
    chart.update('none');

    const info = document.getElementById('deltas');
    if (datasetMode==='abs' && allData.length>1){
      const last = allData[allData.length-1];
      const lastTs = new Date(last.ts);
      const first24 = allData.find(x => (lastTs - new Date(x.ts))/3600000 >= 24) || allData[0];
      const first7  = allData.find(x => (lastTs - new Date(x.ts))/3600000 >= 24*7) || allData[0];
      let s="";
      if(first24){ s+='Změna 24 h: '+(last.weight_g - first24.weight_g).toFixed(1)+' g. '; }
      if(first7){ s+='Změna 7 dní: '+(last.weight_g - first7.weight_g).toFixed(1)+' g.'; }
      info.textContent = s;
    } else { info.textContent=''; }
  }

  datasetBtns.forEach(b=>b.addEventListener('click', ev=>{ toggleButtons(datasetBtns, ev.target); datasetMode = ev.target.dataset.dataset; showClamp(); render(); }));
  rangeBtns.forEach(b=>b.addEventListener('click', ev=>{ toggleButtons(rangeBtns, ev.target); activeRange=ev.target.dataset.range; render(); }));
  scaleBtns.forEach(b=>b.addEventListener('click', ev=>{ toggleButtons(scaleBtns, ev.target); yScaleMode = ev.target.dataset.yscale; updateFixedInputs(); render(); }));
  document.getElementById('yMin')?.addEventListener('input', (e)=>{ yMinFixed = e.target.value? Number(e.target.value) : null; render(); });
  document.getElementById('yMax')?.addEventListener('input', (e)=>{ yMaxFixed = e.target.value? Number(e.target.value) : null; render(); });
  document.getElementById('clampZero')?.addEventListener('change', (e)=>{ clampZero = e.target.checked; render(); });

  document.getElementById('subChk')?.addEventListener('change', async (ev)=>{
    const form=new FormData();
    form.append('device_id','<?= (int)$dev['id'] ?>');
    form.append('subscribe', ev.target.checked ? '1' : '0');
    await fetch("<?= BASE_URL ?>/api/subscribe_alerts.php", { method:'POST', body:form });
  });

  <?php if ($canEdit): ?>
  document.getElementById('alertForm')?.addEventListener('submit', async (ev)=>{
    ev.preventDefault();
    const f = ev.target;
    const fd = new FormData(f);
    const r = await fetch("<?= BASE_URL ?>/api/device_settings.php", { method:'POST', body:fd });
    const j = await r.json().catch(()=>({}));
    document.getElementById('alertSaveMsg').textContent = (j && j.ok) ? 'Uloženo.' : (j.error || 'Chyba při ukládání');
    setTimeout(()=>{ document.getElementById('alertSaveMsg').textContent=''; }, 2500);
  });
  <?php endif; ?>

  function init(){ loadData(); loadAlerts(); setInterval(loadData, 60000); setInterval(loadAlerts, 60000); updateFixedInputs(); showClamp(); }
  init();
})();
</script>
<?php footer_html(); ?>