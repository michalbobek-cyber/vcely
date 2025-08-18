<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config.php';
echo "<h1>_debug.php</h1>";
echo "<p>BASE_URL: <code>".htmlspecialchars(BASE_URL)."</code></p>";

echo "<h2>PHP info</h2>";
echo "<ul>";
echo "<li>PHP version: ".phpversion()."</li>";
echo "<li>Extensions: ".implode(', ', get_loaded_extensions())."</li>";
echo "</ul>";

echo "<h2>DB test</h2>";
try {
  require_once __DIR__ . '/lib/db.php';
  $pdo = db();
  $stmt = $pdo->query('SELECT 1');
  echo "<p>PDO OK</p>";
} catch (Throwable $e) {
  echo "<pre style='color:red'>DB ERROR: ".htmlspecialchars($e->getMessage())."</pre>";
}

echo "<h2>Session / user</h2>";
require_once __DIR__ . '/lib/auth.php';
var_dump($_SESSION);
$u = current_user();
echo "<p>current_user(): "; var_dump($u); echo "</p>";

if ($u) {
  echo "<h2>Vaše zařízení</h2>";
  try {
    $stmt = db()->prepare('SELECT id, name, location FROM vcely_devices WHERE user_id=? ORDER BY id DESC');
    $stmt->execute([$u['id']]);
    $rows = $stmt->fetchAll();
    echo "<pre>"; print_r($rows); echo "</pre>";
  } catch (Throwable $e) {
    echo "<pre style='color:red'>QUERY ERROR: ".htmlspecialchars($e->getMessage())."</pre>";
  }
} else {
  echo "<p>Nejste přihlášen.</p>";
}

echo "<hr><p>Po vyřešení smažte tento soubor.</p>";
?>