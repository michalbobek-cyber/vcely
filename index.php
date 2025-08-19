<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/auth.php';
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = substr($path, strlen(BASE_URL)); if ($path === false) $path = '/';
$path = rtrim($path, '/'); if ($path === '') $path = '/';
function header_html($title){ ?>
<!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= h($title) ?> – <?= h(APP_NAME) ?></title><link rel="stylesheet" href="https://unpkg.com/missing.css@1.1.2"><style>:root{--pad:12px}.container{max-width:1100px;margin:0 auto;padding:var(--pad)}.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:var(--pad)}.card{border:1px solid #e5e7eb;border-radius:14px;padding:var(--pad);background:#fff;box-shadow:0 1px 2px rgba(0,0,0,.04)}.muted{color:#666}nav a{margin-right:10px}.btn{display:inline-block;border:1px solid #d1d5db;border-radius:10px;padding:6px 10px;text-decoration:none}.btn.active{background:#111;color:#fff;border-color:#111}.row{display:flex;gap:8px;flex-wrap:wrap;align-items:center}details{border:1px dashed #ddd;border-radius:10px;padding:8px}label.inline{display:flex;align-items:center;gap:6px}@media (max-width:600px){nav a{display:inline-block;margin:6px 8px 6px 0}.hide-mobile{display:none}code{font-size:.85rem}}</style><link rel="stylesheet" href="<?= BASE_URL ?>/assets/mobile-contrast.css"></head><body><header class="container"><nav><a href="<?= BASE_URL ?>/" class="btn">BeeScale</a><?php if (current_user()): ?><a class="btn" href="<?= BASE_URL ?>/dashboard">Přehled</a><a class="btn" href="<?= BASE_URL ?>/devices">Moje váhy</a><a class="btn" href="<?= BASE_URL ?>/alerts">Alerty</a><a class="btn" href="<?= BASE_URL ?>/logout">Odhlásit</a><?php else: ?><a class="btn" href="<?= BASE_URL ?>/login">Přihlásit</a><a class="btn" href="<?= BASE_URL ?>/register">Registrovat</a><a class="btn" href="<?= BASE_URL ?>/forgot">Zapomenuté heslo</a><?php endif; ?></nav></header><main class="container">
<?php } function footer_html(){ echo '</main><footer class="container"><p class="muted">© '.date('Y').' BeeScale</p></footer></body></html>'; }
switch($path){
  case '/':            require __DIR__.'/pages/home.php'; break;
  case '/login':       require __DIR__.'/pages/login.php'; break;
  case '/register':    require __DIR__.'/pages/register.php'; break;
  case '/forgot':      require __DIR__.'/pages/forgot.php'; break;
  case '/reset':       require __DIR__.'/pages/reset.php'; break;
  case '/logout':      logout(); header('Location: '.BASE_URL.'/'); break;
  case '/devices':     require_login(); require __DIR__.'/pages/devices.php'; break;
  case '/alerts':      require_login(); require __DIR__.'/pages/alerts.php'; break;
  case '/device':      require_login(); require __DIR__.'/pages/device.php'; break;
  case '/device/shares': require_login(); require __DIR__.'/pages/device_shares.php'; break;
  case '/dashboard':   require_login(); require __DIR__.'/pages/dashboard.php'; break;
  default: http_response_code(404); echo 'Not found';
} ?>
