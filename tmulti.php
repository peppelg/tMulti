<?php
require ('vendor/autoload.php');
use PhpSchool\CliMenu\CliMenu;
use PhpSchool\CliMenu\CliMenuBuilder;
use Distill\Distill;
if (stripos(php_uname('s') , 'Linux') === false) exit;
if (strpos(__DIR__, 'phar://') !== false) {
  $tmultifolder = str_replace('phar://', '', pathinfo(__DIR__) ['dirname']) . '/.tmulti';
}
else {
  $tmultifolder = '.tmulti';
}
if (!file_exists($tmultifolder)) {
  mkdir($tmultifolder);
  mkdir($tmultifolder . '/bin');
  mkdir($tmultifolder . '/accounts');
  mkdir($tmultifolder . '/temp');
}
if (!file_exists($tmultifolder . '/conf.json')) {
  $userlang = strtolower(trim(@shell_exec('locale | grep LANGUAGE | cut -d= -f2 | cut -d_ -f1')));
  if ($userlang !== 'it') $userlang = 'en';
  file_put_contents($tmultifolder . '/conf.json', json_encode(array('colour' => 'blue', 'hide' => true, 'lang' => $userlang)));
  unset($userlang);
}
$conf = json_decode(file_get_contents($tmultifolder . '/conf.json'), true);
if ($conf['lang'] == 'it') $strings = json_decode(file_get_contents('strings_it.json'));
else $strings = json_decode(file_get_contents('strings_en.json'));
if (!function_exists('progress')) {
  function progress($resource, $download_size, $downloaded, $upload_size, $uploaded) {
    global $strings;
    if ($download_size > 0) {
      $percentage = round($downloaded / $download_size * 100, PHP_ROUND_HALF_UP);
      if ($percentage != 100) echo $strings->downloading . $percentage . "%\r";
    }
  }
}
if (!function_exists('download')) {
  function download($url, $file) {
    global $strings;
    echo PHP_EOL . $strings->downloading . "0%\r";
    $fp = fopen($file, 'w+');
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, 'progress');
    curl_setopt($ch, CURLOPT_NOPROGRESS, false);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_exec($ch);
    curl_close($ch);
    fclose($fp);
    echo $strings->done;
    return true;
  }
}
if (!function_exists('download_tdesk')) {
  function download_tdesk($version = 'stable') {
    global $tmultifolder;
    global $strings;
    if ($version == 'stable') $url = 'https://telegram.org/dl/desktop/linux';
    if ($version == 'stable32') $url = 'https://telegram.org/dl/desktop/linux32';
    if ($version == 'alpha') $url = 'https://tdesktop.com/linux/current?alpha=1';
    if ($version == 'alpha32') $url = 'https://tdesktop.com/linux32/current?alpha=1';
    if (!isset($url)) die('Error');
    download($url, $tmultifolder . '/temp/Telegram.tar.xz');
    system('clear');
    echo $strings->extracting . PHP_EOL;
    $distill = new Distill();
    $distill->extract($tmultifolder . '/temp/Telegram.tar.xz', $tmultifolder . '/bin');
    chmod($tmultifolder . '/bin/Telegram/Telegram', 0770);
    echo $strings->done . PHP_EOL;
    sleep(3);
    require (__FILE__);
    exit;
  }
}
if (!function_exists('start')) {
  function start($account) {
    global $tmultifolder;
    global $conf;
    if (!file_exists($tmultifolder . '/accounts/' . $account . '_telegram')) mkdir($tmultifolder . '/accounts/' . $account . '_telegram');
    if ($conf['hide'] and function_exists('pcntl_fork')) {
      $pid = pcntl_fork();
      if ($pid == -1) {
        die('could not fork');
      } elseif ($pid) {
      } else {
        shell_exec(escapeshellarg($tmultifolder . '/bin/Telegram/Telegram') . ' -many -workdir ' . escapeshellarg($tmultifolder . '/accounts/' . $account . '_telegram'));
        exit;
      }
    } else {
      shell_exec(escapeshellarg($tmultifolder . '/bin/Telegram/Telegram') . ' -many -workdir ' . escapeshellarg($tmultifolder . '/accounts/' . $account . '_telegram'));
    }
    exit;
  }
}
if (!function_exists('rrmdir')) {
  function rrmdir($dir) {
    if (is_dir($dir)) {
      $objects = scandir($dir);
      foreach ($objects as $object) {
        if ($object != '.' && $object != '..') {
          if (is_dir($dir . '/' . $object)) {
            rrmdir($dir . '/' . $object);
            rmdir($dir . '/' . $object);
          }
          else unlink($dir . '/' . $object);
        }
      }
    }
  }
}
rrmdir($tmultifolder . '/temp');
if (!file_exists($tmultifolder . '/bin/Telegram/Telegram')) {
  $menu = new CliMenuBuilder;
  $menu->setTitle($strings->w_tdesk)->addItem('Telegram Stable', function () {
    download_tdesk('stable');
  })->addItem('Telegram Alpha', function () {
    download_tdesk('alpha');
  })->addItem('Telegram Stable (32bit)', function () {
    download_tdesk('stable32');
  })->addItem('Telegram Alpha (32bit)', function () {
    download_tdesk('alpha32');
  })
    ->addLineBreak(' ')
    ->setExitButtonText($strings->exit)
    ->build()
    ->open();
  exit;
}
$accounts = array_diff(scandir($tmultifolder . '/accounts') , array('.', '..'));
$menu = new CliMenuBuilder;
$menu = $menu->setTitle('Telegram account manager')
->setBackgroundColour($conf['colour']);
foreach ($accounts as $accountN => $account) {
  if (substr($account, -9) === '_telegram') {
    $aname = str_replace('_telegram', '', $account);
    $menu->addSubMenu($aname)->setTitle('Account > ' . $aname)->addLineBreak(' ')->addItem($strings->start . ' [' . $accountN . ']', function (CliMenu $menu) {
      global $accounts;
      $account = str_replace('_telegram', '', $accounts[filter_var($menu->getSelectedItem()
        ->getText() , FILTER_SANITIZE_NUMBER_INT) ]);
      $menu->close();
      start($account);
    })->addItem($strings->delete . ' [' . $accountN . ']', function (CliMenu $menu) {
      global $accounts;
      global $tmultifolder;
      global $strings;
      $aname = str_replace('_telegram', '', $accounts[filter_var($menu->getSelectedItem()
        ->getText() , FILTER_SANITIZE_NUMBER_INT) ]);
      $account = $tmultifolder . '/accounts/' . $accounts[filter_var($menu->getSelectedItem()
        ->getText() , FILTER_SANITIZE_NUMBER_INT) ];
      if (readline($strings->a_s . '[' . $aname . ']: ') == $aname) {
        rrmdir($account);
        rmdir($account);
        $menu->flash($strings->deleted)
          ->display();
        $menu->close();
        require (__FILE__);
        exit;
      }
      else {
        $menu->close();
        $menu->open();
      }
    })
      ->addLineBreak(' ')
      ->setGoBackButtonText($strings->go_back)
      ->setExitButtonText($strings->exit)
      ->end();
  }
}
$menu = $menu->addLineBreak(' ')->addItem($strings->add_account, function () {
  global $strings;
  $name = readline($strings->t_a);
  if ($name != '') {
    system('clear');
    start($name);
    exit;
  }
  else {
    require (__FILE__);
    exit;
  }
})->addSubMenu($strings->u_tdesk)
  ->setTitle($strings->w_tdesk)->addItem('Telegram Stable', function () {
  download_tdesk('stable');
})->addItem('Telegram Alpha', function () {
  download_tdesk('alpha');
})->addItem('Telegram Stable (32bit)', function () {
  download_tdesk('stable32');
})->addItem('Telegram Alpha (32bit)', function () {
  download_tdesk('alpha32');
})
  ->addLineBreak(' ')
  ->setGoBackButtonText($strings->go_back)
  ->setExitButtonText($strings->exit)
  ->end()
  ->addSubMenu($strings->settings)
  ->setTitle($strings->wdywtd)
  ->addSubMenu($strings->cl)
  ->setTitle($strings->sl)
  ->addItem('Italiano', function(CliMenu $menu) {
    global $conf;
    global $tmultifolder;
    $conf['lang'] = 'it';
    file_put_contents($tmultifolder . '/conf.json', json_encode($conf));
    $menu->flash('Fatto.')
    ->display();
    $menu->close();
    require(__FILE__);
    exit;
  })
  ->addItem('English', function(CliMenu $menu) {
    global $conf;
    global $tmultifolder;
    $conf['lang'] = 'en';
    file_put_contents($tmultifolder . '/conf.json', json_encode($conf));
    $menu->flash('Done.')
    ->display();
    $menu->close();
    require(__FILE__);
    exit;
  })
  ->addLineBreak(' ')
  ->setGoBackButtonText($strings->go_back)
  ->setExitButtonText($strings->exit)
  ->end()
  ->addSubMenu($strings->change_colour)
  ->setTitle($strings->select_colour);
  foreach (array('blue', 'cyan', 'red', 'yellow', 'green', 'black', 'magenta') as $colour) {
    $menu->addItem($colour, function(CliMenu $menu) use($colour) {
      global $conf;
      global $tmultifolder;
      global $strings;
      $conf['colour'] = $colour;
      file_put_contents($tmultifolder . '/conf.json', json_encode($conf));
      $menu->flash($strings->done)
      ->display();
      $menu->close();
      require(__FILE__);
      exit;
    });
  }
  $menu->addLineBreak(' ')
  ->setGoBackButtonText($strings->go_back)
  ->setExitButtonText($strings->exit)
  ->end()
  ->addItem($strings->hide, function(CliMenu $menu) {
    global $conf;
    global $tmultifolder;
    global $strings;
    $conf['hide'] = !$conf['hide'];
    file_put_contents($tmultifolder . '/conf.json', json_encode($conf));
    if ($conf['hide']) $f = $strings->h_y; else $f = $strings->h_n;
    $menu->flash($f)
    ->display();
    $menu->close();
    require(__FILE__);
    exit;
  })
  ->addLineBreak(' ')
  ->setGoBackButtonText($strings->go_back)
  ->setExitButtonText($strings->exit)
  ->end()
  ->setExitButtonText($strings->exit)
  ->build()
  ->open();
