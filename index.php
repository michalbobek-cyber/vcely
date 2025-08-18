<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/auth.php';

// --- Router ---
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = substr($path, strlen(BASE_URL));
if ($path === false) $path = '/';
$path = rtrim($path, '/');
if ($path === '') $path = '/';

// --- Helpers ---
function header_html($title){
  ?>
  <!doctype html>
  <html lang="cs">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= h($title) ?> – <?= h(APP_NAME) ?></title>
    <link rel="stylesheet" href="https://unpkg.com/missing.css@1.1.2">
    <style>
      .card{border:1px solid #ddd;border-radius:12px;padding:12px;margin:8px 0}
      .grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:12px}
      .muted{color:#666}
      .container{max-width:1100px;margin:0 auto;padding:16px}
      nav a{margin-right:8px}
      code{word-break:break-all}
    </style>
  </head>
  <body>
    <header>
      <nav>
        <a href="<?= BASE_URL ?>/">BeeScale</a>
        <?php if (current_user()): ?>
          | <a href="<?= BASE_URL ?>/dashboard">Přehled</a>
          | <a href="<?= BASE_URL ?>/devices">Moje váhy</a>
          | <a href="<?= BASE_URL ?>/logout">Odhlásit</a>
        <?php else: ?>
          | <a href="<?= BASE_URL ?>/login">Přihlásit</a>
          | <a href="<?= BASE_URL ?>/register">Registrovat</a>
          | <a href="<?= BASE_URL ?>/forgot">Zapomenuté heslo</a>
        <?php endif; ?>
      </nav>
    </header>
    <main class="container">
  <?php
}
function footer_html(){
  ?>
    </main>
    <footer><p class="muted">© <?= date('Y') ?> BeeScale</p></footer>
  </body>
  </html>
  <?php
}

// ==== Pages ====
switch ($path) {

case '/':
  header_html('Domů');
  ?>
  <h1>BeeScale – váhy pod úly</h1>
  <p>Víceuživatelský monitoring hmotnosti, teploty a vlhkosti s grafy, exportem a alerty.</p>
  <?php footer_html(); break;

case '/register':
  if ($_SERVER['REQUEST_METHOD']==='POST'){
    $email=trim($_POST['email']??''); $pass=$_POST['password']??'';
    if (filter_var($email, FILTER_VALIDATE_EMAIL) && strlen($pass)>=6){
      try { register_user($email,$pass); login($email,$pass); header('Location: '.BASE_URL.'/dashboard'); exit; }
      catch (Throwable $e) { $err='Email už existuje.'; }
    } else { $err='Zadejte platný email a heslo min. 6 znaků.'; }
  }
  header_html('Registrace'); ?>
  <h2>Registrace</h2>
  <?php if (!empty($err)): ?><p class="danger"><?= h($err) ?></p><?php endif; ?>
  <form method="post">
    <label>Email <input type="email" name="email" required></label>
    <label>Heslo <input type="password" name="password" required></label>
    <button>Registrovat</button>
  </form>
  <?php footer_html(); break;

case '/login':
  if ($_SERVER['REQUEST_METHOD']==='POST'){
    $email=trim($_POST['email']??''); $pass=$_POST['password']??'';
    if (login($email,$pass)) { header('Location: '.BASE_URL.'/dashboard'); exit; }
    else { $err='Neplatné přihlašovací údaje.'; }
  }
  header_html('Přihlášení'); ?>
  <h2>Přihlášení</h2>
  <?php if (!empty($err)): ?><p class="danger"><?= h($err) ?></p><?php endif; ?>
  <form method="post">
    <label>Email <input type="email" name="email" required></label>
    <label>Heslo <input type="password" name="password" required></label>
    <button>Přihlásit</button>
  </form>
  <?php footer_html(); break;

case '/forgot':
  if ($_SERVER['REQUEST_METHOD']==='POST'){
    $email=trim($_POST['email']??'');
    if (filter_var($email,FILTER_VALIDATE_EMAIL)){ create_reset_token($email); $msg='Pokud e-mail existuje, poslali jsme odkaz pro obnovu hesla.'; }
    else { $msg='Zadej platný e-mail.'; }
  }
  header_html('Zapomenuté heslo'); ?>
  <h2>Zapomenuté heslo</h2>
  <?php if (!empty($msg)): ?><p class="notice"><?= h($msg) ?></p><?php endif; ?>
  <form method="post">
    <label>Email <input type="email" name="email" required></label>
    <button>Odeslat odkaz</button>
  </form>
  <?php footer_html(); break;

case '/reset':
  $email = $_GET['email'] ?? ($_POST['email'] ?? '');
  $token = $_GET['token'] ?? ($_POST['token'] ?? '');
  if ($_SERVER['REQUEST_METHOD']==='POST'){
    $p=$_POST['password']??''; $p2=$_POST['password2']??'';
    if ($p===$p2 && strlen($p)>=6){ $res=reset_password_with_token($email,$token,$p); if($res===true){ $ok='Heslo změněno. Můžeš se přihlásit.'; } else { $err=$res; } }
    else { $err='Hesla se neshodují nebo jsou krátká (min. 6 znaků).'; }
  }
  header_html('Obnovení hesla'); ?>
  <h2>Obnovení hesla</h2>
  <?php if (!empty($ok)): ?><p class="success"><?= h($ok) ?></p><?php endif; ?>
  <?php if (!empty($err)): ?><p class="danger"><?= h($err) ?></p><?php endif; ?>
  <form method="post">
    <input type="hidden" name="email" value="<?= h($email) ?>">
    <input type="hidden" name="token" value="<?= h($token) ?>">
    <label>Nové heslo <input type="password" name="password" required></label>
    <label>Potvrzení hesla <input type="password" name="password2" required></label>
    <button>Změnit heslo</button>
  </form>
  <?php footer_html(); break;

case '/logout':
  logout(); header('Location: '.BASE_URL.'/'); break;

case '/devices':
  require_login();
  $uid=current_user()['id'];
  if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action'] ?? '')==='create'){
    $name=trim($_POST['name']??''); $loc=trim($_POST['location']??'');
    if ($name) {
      $s=db()->prepare('INSERT INTO vcely_devices (user_id,name,location) VALUES (?,?,?)'); $s->execute([$uid,$name,$loc]);
      $did=db()->lastInsertId();
      $key=bin2hex(random_bytes(16));
      $s=db()->prepare('INSERT INTO vcely_device_keys (device_id,api_key) VALUES (?,?)'); $s->execute([$did,$key]);
      $s=db()->prepare('INSERT INTO vcely_device_settings (device_id,enable_alerts,min_drop_g_24h,min_rise_g_24h) VALUES (?,1,500,500)'); $s->execute([$did]);
      header('Location: '.BASE_URL.'/devices'); exit;
    }
  }
  $s=db()->prepare('SELECT d.id,d.name,d.location,dk.api_key FROM vcely_devices d LEFT JOIN vcely_device_keys dk ON dk.device_id=d.id WHERE d.user_id=?');
  $s->execute([$uid]); $rows=$s->fetchAll();
  header_html('Moje váhy'); ?>
  <h2>Moje váhy</h2>
  <form method="post">
    <input type="hidden" name="action" value="create">
    <label>Název <input name="name" required></label>
    <label>Lokalita <input name="location"></label>
    <button>Přidat váhu</button>
  </form>
  <div class="grid">
  <?php foreach ($rows as $r): ?>
    <div class="card">
      <h3><?= h($r['name']) ?> <small>#<?= h($r['id']) ?></small></h3>
      <p class="muted"><?= h($r['location']) ?></p>
      <p>HTTP ingest: <code><?= BASE_URL ?>/api/ingest?key=<?= h($r['api_key']) ?></code></p>
      <p>
        <a class="button" href="<?= BASE_URL ?>/device?id=<?= $r['id'] ?>">Detail</a>
        <a class="button" href="<?= BASE_URL ?>/device/shares?id=<?= $r['id'] ?>">Sdílení</a>
      </p>
    </div>
  <?php endforeach; ?>
  </div>
  <?php footer_html(); break;

case '/device':
  require_login();
  $uid=current_user()['id']; $id=(int)($_GET['id']??0);
  if (!user_can_access_device($uid,$id,'viewer')){ http_response_code(403); echo 'Forbidden'; exit; }
  $s=db()->prepare('SELECT * FROM vcely_devices WHERE id=?'); $s->execute([$id]); $dev=$s->fetch(); if(!$dev){ http_response_code(404); echo 'Not found'; exit; }
  header_html('Zařízení '.$dev['name']); ?>
  <h2><?= h($dev['name']) ?> <small>#<?= h($dev['id']) ?></small></h2>
  <p>
    <a class="button" href="<?= BASE_URL ?>/api/export_csv?device_id=<?= $dev['id'] ?>&range=7d">Export CSV (7 dní)</a>
    <a class="button" href="<?= BASE_URL ?>/api/export_csv?device_id=<?= $dev['id'] ?>&range=30d">Export CSV (30 dní)</a>
  </p>
  <canvas id="chart" height="140"></canvas>
  <p id="deltas" class="muted"></p>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script>
    async function load(){
      const url = "<?= BASE_URL ?>/api/readings?device_id=<?= (int)$dev['id'] ?>&limit=2000";
      const r = await fetch(url);
      const data = await r.json();
      const labels = data.map(x=>x.ts).reverse();
      const w = data.map(x=>x.weight_g).reverse();
      const t = data.map(x=>x.temp_c).reverse();
      const h = data.map(x=>x.hum_pct).reverse();
      const ctx = document.getElementById("chart").getContext("2d");
      new Chart(ctx, { type:"line", data:{ labels, datasets:[
        {label:"Hmotnost [g]", data:w, yAxisID:"y"},
        {label:"Teplota [°C]", data:t, yAxisID:"y1"},
        {label:"Vlhkost [%]", data:h, yAxisID:"y1"}
      ]}, options:{ interaction:{mode:"index",intersect:false}, stacked:false, scales:{ y:{type:"linear",position:"left"}, y1:{type:"linear",position:"right", grid:{drawOnChartArea:false}}}}});
      if (data.length>1){
        const last = data[0], last_ts = new Date(last.ts);
        let ref24=null, ref7=null;
        for (const x of data){
          const dtH = (last_ts - new Date(x.ts)) / 3600000;
          if (!ref24 && dtH >= 24) ref24 = x;
          if (!ref7 && dtH >= 24*7) { ref7 = x; break; }
        }
        let s=""; if (ref24) s+='Změna 24 h: '+(last.weight_g - ref24.weight_g).toFixed(1)+' g. '; if (ref7) s+='Změna 7 dní: '+(last.weight_g - ref7.weight_g).toFixed(1)+' g.';
        document.getElementById("deltas").textContent = s;
      }
    }
    load(); setInterval(load, 60000);
  </script>
  <?php footer_html(); break;

case '/device/shares':
  require_login();
  $uid=current_user()['id']; $id=(int)($_GET['id']??0);
  $s=db()->prepare('SELECT * FROM vcely_devices WHERE id=? AND user_id=?'); $s->execute([$id,$uid]); $dev=$s->fetch();
  if (!$dev){ http_response_code(403); echo 'Jen vlastník může spravovat sdílení.'; exit; }
  if ($_SERVER['REQUEST_METHOD']==='POST'){
    $email=trim($_POST['email']??''); $role=$_POST['role']??'viewer';
    if (!in_array($role,['viewer','editor'], true)) $role='viewer';
    if (filter_var($email, FILTER_VALIDATE_EMAIL)){
      $s=db()->prepare('SELECT id FROM vcely_users WHERE email=?'); $s->execute([$email]); $u=$s->fetch();
      if ($u){
        $s=db()->prepare('INSERT INTO vcely_device_shares (device_id,user_id,role) VALUES (?,?,?) ON DUPLICATE KEY UPDATE role=VALUES(role)');
        $s->execute([$id,(int)$u['id'],$role]);
        $msg='Sdílení nastaveno.';
      } else { $msg='Uživatel neexistuje.'; }
    } else { $msg='Zadej platný e-mail.'; }
  }
  $s=db()->prepare('SELECT u.email, s.role FROM vcely_device_shares s JOIN vcely_users u ON u.id=s.user_id WHERE s.device_id=?'); $s->execute([$id]); $shares=$s->fetchAll();
  header_html('Sdílení'); ?>
  <h2>Sdílení: <?= h($dev['name']) ?> <small>#<?= h($dev['id']) ?></small></h2>
  <?php if (!empty($msg)): ?><p class="notice"><?= h($msg) ?></p><?php endif; ?>
  <form method="post">
    <label>Email uživatele <input name="email" type="email" required></label>
    <label>Role
      <select name="role">
        <option value="viewer">Viewer</option>
        <option value="editor">Editor</option>
      </select>
    </label>
    <button>Uložit</button>
  </form>
  <h3>Aktuální přístupy</h3>
  <ul>
    <?php foreach ($shares as $srow): ?>
      <li><?= h($srow['email']) ?> – <?= h($srow['role']) ?></li>
    <?php endforeach; ?>
  </ul>
  <?php footer_html(); break;

case '/dashboard':
  require_login();
  $uid=current_user()['id'];
  $s=db()->prepare('SELECT id,name,location FROM vcely_devices WHERE user_id=? ORDER BY id'); $s->execute([$uid]); $devices=$s->fetchAll();
  header_html('Přehled'); ?>
  <h2>Přehled</h2>
  <div class="grid">
  <?php foreach ($devices as $d): ?>
    <?php
      $st=db()->prepare('SELECT weight_g,temp_c,hum_pct,ts FROM vcely_readings WHERE device_id=? ORDER BY id DESC LIMIT 1'); $st->execute([$d['id']]); $last=$st->fetch();
      $r24=db()->prepare('SELECT weight_g FROM vcely_readings WHERE device_id=? AND ts <= NOW() - INTERVAL 24 HOUR ORDER BY id DESC LIMIT 1'); $r24->execute([$d['id']]); $x24=$r24->fetch();
      $r7=db()->prepare('SELECT weight_g FROM vcely_readings WHERE device_id=? AND ts <= NOW() - INTERVAL 7 DAY ORDER BY id DESC LIMIT 1'); $r7->execute([$d['id']]); $x7=$r7->fetch();
      $d24 = ($last && $x24) ? number_format($last['weight_g'] - $x24['weight_g'], 1) : null;
      $d7  = ($last && $x7) ? number_format($last['weight_g'] - $x7['weight_g'], 1) : null;
    ?>
    <div class="card">
      <h3><?= h($d['name']) ?> <small>#<?= h($d['id']) ?></small></h3>
      <p class="muted"><?= h($d['location']) ?></p>
      <p><b><?= h($last['weight_g'] ?? '-') ?> g</b></p>
      <p>Teplota: <?= h($last['temp_c'] ?? '-') ?> °C | Vlhkost: <?= h($last['hum_pct'] ?? '-') ?> %</p>
      <p class="muted"><?= h($last['ts'] ?? '-') ?></p>
      <p>Změna 24 h: <?= $d24 ?? '-' ?> g | 7 dní: <?= $d7 ?? '-' ?> g</p>
      <p><a class="button" href="<?= BASE_URL ?>/device?id=<?= $d['id'] ?>">Detail</a></p>
    </div>
  <?php endforeach; ?>
  </div>
  <?php footer_html(); break;

default:
  http_response_code(404); echo 'Not found';
}
?>
