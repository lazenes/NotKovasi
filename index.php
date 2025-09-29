<?php
session_start();

// --- YENİ AYAR: GİRİŞ ZORUNLULUĞU ---
// true: Giriş zorunludur. Yönetim paneli ve paste oluşturma için giriş gerekir.
// false: Giriş zorunlu değildir. Herkes paste oluşturabilir. Yönetim/Silme/Düzenleme sadece giriş yapınca görünür.
$ENABLE_AUTH = true; 

// Kullanıcı girişi gerekli mi?
$auth_required = $ENABLE_AUTH && !isset($_SESSION['logged_in']);


// --- YAPILANDIRMA ---
$PASTE_DIR = 'pastes/'; // Pastelerin kaydedileceği klasör
$PASTE_LIST_FILE = 'pastes.json'; // Pastelerin meta verilerinin kaydedileceği dosya (JSON)
$CONFIG_FILE = 'config.json'; // Dinamik kullanıcı/ayarları saklamak için

$BASE_URL = 'http://xxx.ltd/'; // Kendi sitenizin URL'si ile değiştirin!

// Varsayılan sabit kullanıcı (ilk kurulum için) - daha sonra config.json üzerinden okunur
$default_username = 'admin';
$default_password_hash = '$2y$10$2QSo7hjVhEhODJDYKEA1tOLwpqw9b6xAg/D2FCZ16QtwwVF.z//iO'; //admin

// --- YAPILANDIRMA YÜKLE ---
function load_config($CONFIG_FILE, $default_username, $default_password_hash) {
    if (file_exists($CONFIG_FILE)) {
        $json = file_get_contents($CONFIG_FILE);
        $data = json_decode($json, true);
        if (is_array($data) && isset($data['username']) && isset($data['password_hash'])) {
            return $data;
        }
    }
    // Eğer config yoksa, varsayılanı döndür
    return ['username' => $default_username, 'password_hash' => $default_password_hash];
}

function save_config($CONFIG_FILE, $username, $password_hash) {
    $data = ['username' => $username, 'password_hash' => $password_hash];
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    // Eğer yazma başarısızsa false döndür
    return file_put_contents($CONFIG_FILE, $json) !== false;
}

$config = load_config($CONFIG_FILE, $default_username, $default_password_hash);
$username = $config['username'];
$password_hash = $config['password_hash'];

// Varsayılan Değerler
$current_paste_content = "";
$current_paste_title = "Yeni Paste Oluştur";
$current_paste_id = "";
$highlight_class = "language-text"; 
$theme_mode = isset($_SESSION['theme']) ? $_SESSION['theme'] : 'dark';
$body_class = ($theme_mode === 'dark') ? 'bg-dark text-white' : 'bg-light text-dark';
$navbar_class = ($theme_mode === 'dark') ? 'navbar-dark bg-dark' : 'navbar-light bg-light border-bottom';
$card_class = ($theme_mode === 'dark') ? 'bg-secondary text-white' : 'bg-white text-dark';
$view_mode = false; 
$edit_mode = false; // Yeni: Düzenleme modunda mıyız?

// --- JSON VERİSİNİ YÜKLEME ---
$pastes = [];
if (file_exists($PASTE_LIST_FILE)) {
    $json_data = file_get_contents($PASTE_LIST_FILE);
    $pastes = json_decode($json_data, true) ?: [];
    // ID'ye göre erişimi kolaylaştırmak için anahtarları ID yap
    $pastes = array_column($pastes, null, 'id');
}

// 1. KLASÖR VE JSON KONTROLLERİ
// Kontrol sadece giriş yapıldığında veya yetkilendirme kapalıysa yapılır
if (!$ENABLE_AUTH || isset($_SESSION['logged_in'])) {
    if (!is_dir($PASTE_DIR) && !mkdir($PASTE_DIR, 0777, true)) {
        $error_message = "Hata: 'pastes' klasörü oluşturulamadı. İzinleri kontrol edin.";
    }
    if (!file_exists($PASTE_LIST_FILE) && !file_put_contents($PASTE_LIST_FILE, json_encode([]))) {
         $error_message = "Hata: 'pastes.json' dosyası oluşturulamadı. İzinleri kontrol edin.";
    }
}

// Oto Algılama Fonksiyonu (Aynı)
function detect_language($content) {
    if (preg_match('/<\?php|__FILE__|->|\$this/i', $content)) return "php";
    if (preg_match('/<\/?(html|body|div|a|p|script)/i', $content)) return "html"; 
    if (preg_match('/function\s|var\s|const\s|let\s|\{|\}|console\./', $content)) return "javascript"; 
    if (preg_match('/^\s*(select|insert|update|delete|from)\s/i', trim($content))) return "sql";
    if (preg_match('/^(\.|#|@media|:root)/m', $content)) return "css";
    if (preg_match('/^[\s]*\{[\s]*"[\s\S]*":[\s\S]*\}[\s]*$/', trim($content))) return "json";
    return "text";
}

// Dil adını dosya uzantısına dönüştürür (Yeni Yardımcı Fonksiyon)
function lang_to_ext($lang) {
    $map = [
        'php' => 'php',
        'html' => 'html',
        'javascript' => 'js',
        'css' => 'css',
        'sql' => 'sql',
        'json' => 'json',
        'text' => 'txt',
    ];
    return $map[$lang] ?? 'txt';
}


// 2. GİRİŞ/ÇIKIŞ/TEMA İŞLEMLERİ
if (isset($_POST['login'])) {
    // Kullanıcı adı/sifre dinamik config'ten geliyor
    if ($_POST['username'] === $username && password_verify($_POST['password'], $password_hash)) {
        $_SESSION['logged_in'] = true;
        $_SESSION['username'] = $username;
        // Başarılı girişte ana sayfaya yönlendir (GET değişkenlerini temizlemek için)
        header("Location: index.php");
        exit();
    } else {
        $login_error = "Hatalı kullanıcı adı veya şifre!";
    }
}
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_unset();
    session_destroy();
    header("Location: index.php");
    exit();
}
// Tema Değiştirme İşlemi (Aynı)
if (isset($_POST['toggle_theme'])) {
    $_SESSION['theme'] = ($_POST['toggle_theme'] === 'dark') ? 'dark' : 'light';
    $redirect_url = $_POST['current_url'] ?? 'index.php';
    header("Location: " . $redirect_url);
    exit();
}

// --- KULLANICI BİLGİLERİNİ GÜNCELLEME (YENİ) ---
// Sadece giriş yapılmışsa yapılabilir
if (isset($_POST['update_user']) && isset($_SESSION['logged_in'])) {
    $new_username = trim($_POST['new_username'] ?? '');
    $new_password = trim($_POST['new_password'] ?? '');
    $new_password_confirm = trim($_POST['new_password_confirm'] ?? '');

    if ($new_username === '') {
        $user_update_error = 'Kullanıcı adı boş olamaz.';
    } elseif ($new_password !== '' && $new_password !== $new_password_confirm) {
        $user_update_error = 'Parolalar eşleşmiyor.';
    } else {
        // Hazırla ve kaydet
        $save_username = $new_username !== '' ? $new_username : $username;
        if ($new_password !== '') {
            $save_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        } else {
            $save_password_hash = $password_hash; // değişiklik yok
        }

        if (save_config($CONFIG_FILE, $save_username, $save_password_hash)) {
            // Güncelleme başarılı: anlık konfigu güncelle ve session'ı güncelle
            $username = $save_username;
            $password_hash = $save_password_hash;
            $_SESSION['username'] = $username;
            $_SESSION['success_message'] = 'Kullanıcı bilgileri başarıyla güncellendi.';
            // Yeniden yönlendir, böylece form tekrar postalanmaz
            header('Location: index.php');
            exit();
        } else {
            $user_update_error = 'Ayarlar dosyası kaydedilemedi. İzinleri kontrol edin.';
        }
    }
}

// --- İŞLEM FONKSİYONLARI ---

// Paste dosyasını ve JSON kaydını siler (Aynı)
function delete_paste($paste_id, &$pastes, $PASTE_DIR, $PASTE_LIST_FILE) {
    if (!isset($pastes[$paste_id])) return false;
    $file_path = $PASTE_DIR . $paste_id . '.txt';
    
    if (file_exists($file_path)) {
        unlink($file_path);
    }
    
    unset($pastes[$paste_id]);
    file_put_contents($PASTE_LIST_FILE, json_encode(array_values($pastes), JSON_PRETTY_PRINT));
    return true;
}

// 3. İNDİRME İŞLEMİ (Yeni)
// İndirme herkese açıktır
if (isset($_GET['action']) && $_GET['action'] === 'download' && isset($_GET['id'])) {
    $download_id = preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['id']);
    $file_path = $PASTE_DIR . $download_id . '.txt';

    if (isset($pastes[$download_id]) && file_exists($file_path)) {
        $content = file_get_contents($file_path);
        $lang = detect_language($content);
        $ext = lang_to_ext($lang);
        $title = $pastes[$download_id]['title'];
        
        // Dosya başlığını temizle
        $filename = preg_replace('/[^a-zA-Z0-9_-]/', '-', $title) . '.' . $ext;
        
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($file_path));
        readfile($file_path);
        exit;
    } else {
        $error_message = "Hata: İndirilecek dosya bulunamadı.";
    }
}

// 3. SİLME İŞLEMİ (Sadece giriş yapılmışsa)
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id']) && isset($_SESSION['logged_in'])) {
    $paste_to_delete = preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['id']);
    if (delete_paste($paste_to_delete, $pastes, $PASTE_DIR, $PASTE_LIST_FILE)) {
        $_SESSION['success_message'] = "Paste başarıyla silindi: " . htmlspecialchars($paste_to_delete);
    } else {
        $_SESSION['error_message'] = "Hata: Silinecek paste bulunamadı.";
    }
    header("Location: index.php");
    exit();
}

// 4. DÜZENLEME MODU (Sadece giriş yapılmışsa)
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id']) && isset($_SESSION['logged_in'])) {
    $current_paste_id = preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['id']);
    
    if (isset($pastes[$current_paste_id])) {
        $edit_mode = true;
        $current_paste_content = file_get_contents($PASTE_DIR . $current_paste_id . '.txt');
        $current_paste_title = $pastes[$current_paste_id]['title'];
        $current_paste_alias = $current_paste_id;
    } else {
        $error_message = "Hata: Düzenlenecek paste bulunamadı.";
    }
}

// 5. PASTE GÖRÜNTÜLEME İŞLEMİ (Aynı)
if (isset($_GET['id'])) {
    $current_paste_id = preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['id']);
    $file_path = $PASTE_DIR . $current_paste_id . '.txt';

    if (isset($pastes[$current_paste_id]) && file_exists($file_path) && !$edit_mode) {
        $view_mode = true;
        $current_paste_content = file_get_contents($file_path);
        
        $current_paste_title = $pastes[$current_paste_id]['title'];
        $detected_lang = detect_language($current_paste_content);
        $highlight_class = "language-" . $detected_lang;
    } else if (!$edit_mode) {
        $error_message = "Hata: İstenen paste ('" . htmlspecialchars($_GET['id']) . "') bulunamadı.";
    }
}

// 6. PASTE KAYDETME/GÜNCELLEME İŞLEMİ
// Giriş zorunluysa, sadece giriş yapılmışsa kaydet.
// Giriş zorunlu değilse, her zaman kaydet.
$can_save_paste = !$ENABLE_AUTH || isset($_SESSION['logged_in']);

if (isset($_POST['paste_submit']) && $can_save_paste) {
    if (!empty($_POST['paste_content'])) {
        $raw_content = $_POST['paste_content'];
        $title = trim($_POST['paste_title']);
        $alias = trim($_POST['paste_alias']);
        $is_update = isset($_POST['is_update']) && $_POST['is_update'] === 'true';

        if (empty($title)) $title = "Başlıksız Paste";

        if ($is_update) {
            // Güncelleme için, sadece giriş yapılmışsa izin ver
            if (!$ENABLE_AUTH || isset($_SESSION['logged_in'])) {
                 $paste_id = $_POST['original_id'];
            } else {
                 $error_message = "Bu pasteyi güncellemek için giriş yapmalısınız.";
            }
           
        } elseif (!empty($alias)) {
            $paste_id = preg_replace('/[^a-zA-Z0-9_-]/', '', $alias);
            if (empty($paste_id)) $error_message = "Alias sadece harf, rakam, tire veya alt çizgi içerebilir.";
            if (isset($pastes[$paste_id])) $error_message = "Hata: Bu alias zaten kullanımda. Başka bir değer girin.";
        } else {
            $paste_id = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyz"), 0, 6);
        }

        if (isset($error_message)) {
             $current_paste_content = $raw_content;
             $current_paste_title = $title;
             $current_paste_alias = $alias;
             $edit_mode = $is_update;
        } else {
            $file_path = $PASTE_DIR . $paste_id . '.txt';
        
            if (file_put_contents($file_path, $raw_content)) {
                
                $new_paste_meta = [
                    'id' => $paste_id,
                    'title' => $title,
                    'date' => date('Y-m-d H:i:s')
                ];

                $pastes[$paste_id] = $new_paste_meta;
                
                file_put_contents($PASTE_LIST_FILE, json_encode(array_values($pastes), JSON_PRETTY_PRINT));

                $saved_url = rtrim($BASE_URL, '/') . "/" . $paste_id;
                $_SESSION['success_message'] = $is_update ? "Paste başarıyla güncellendi!" : "Paste başarıyla kaydedildi! Adresi: <a href='" . $saved_url . "' class='alert-link'>" . $saved_url . "</a>";
                
                header("Location: " . $saved_url);
                exit();
            } else {
                $error_message = "Hata: Dosya kaydedilemedi. Klasör izinlerini kontrol edin.";
            }
        }
    } else {
        $error_message = "Lütfen yapıştırılacak içerik girin.";
    }
}

// Başarı/Hata Mesajlarını temizle (Aynı)
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// Listeyi tersine çevir (en yeniler üstte)
$pastes_list = array_reverse($pastes); 
?>

<!DOCTYPE html>
<html lang="tr" data-bs-theme="<?php echo $theme_mode; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EnesBiBER.Com.TR <?php echo $view_mode ? ' | ' . $current_paste_title : ($edit_mode ? ' | Düzenle' : ''); ?></title>
     <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <?php if ($theme_mode === 'dark'): ?>
        <link href="https://cdnjs.cloudflare.com/ajax/libs/prism-themes/1.9.0/prism-atom-dark.min.css" rel="stylesheet" />
    <?php else: ?>
        <link href="https://cdnjs.cloudflare.com/ajax/libs/prism-themes/1.9.0/prism-vs.min.css" rel="stylesheet" />
    <?php endif; ?>
    
    <link href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/plugins/line-numbers/prism-line-numbers.min.css" rel="stylesheet" />
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
       /* Mevcut kodlarınıza ekleyin */
body {
    min-height: 100vh;
    display: flex;
    flex-direction: column; /* Sayfa içeriğini dikey sırala */
}
.content-wrapper {
    flex-grow: 1; /* İçeriğin footer'ı aşağı itmesini sağlar */
}
/* Footer'a özel stil */
.app-footer {
    padding: 1rem 0;
    margin-top: auto; /* Footer'ı alt kenara sabitler */
    border-top: 1px solid rgba(0, 0, 0, 0.1);
}
        
        /* Satır Numaraları için zorunlu stil */
        .line-numbers-rows { 
            position: absolute; 
            pointer-events: none;
            top: 0;
            font-size: 100%;
            left: 0;
            width: 100%;
            height: 100%;
        }

        /* YATAY KAYDIRMAYI KALDIRMA ve KODU OTOMATİK SARMA */
        pre[class*="language-"] { 
            padding: 1em; 
            margin: 0; 
            border-radius: 0; 
            overflow-x: hidden !important; /* YATAY KAYDIRMAYI KESİNLİKLE KALDIR */
            overflow-y: auto; /* Dikey kaydırmayı otomatik olarak göster */
            max-height: 70vh; /* Ekran yüksekliğinin %70'ini geçince scroll çıksın */
            white-space: pre-wrap !important; 
            word-break: break-word; 
        }
        
        pre.line-numbers {
            padding-left: 3.8em; 
        }
    </style>
</head>
<body class="<?php echo $body_class; ?>">

    <nav class="navbar <?php echo $navbar_class; ?> mb-4 shadow-sm">
        <div class="container-fluid">
            <a class="navbar-brand mb-0 h1" href="index.php">EnesBiBER.CoM.TR</a>
            <div>
               <form method="post" class="d-inline me-3">
    <input type="hidden" name="toggle_theme" value="<?php echo ($theme_mode === 'dark' ? 'light' : 'dark'); ?>">
    
    <input type="hidden" name="current_url" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>">

    <button type="submit" class="btn btn-sm btn-outline-<?php echo ($theme_mode === 'dark' ? 'light' : 'dark'); ?>">
        <i class="bi bi-sun-fill me-1"></i> <?php echo ($theme_mode === 'dark' ? 'Açık Tema' : 'Koyu Tema'); ?>
    </button>
</form>
                <?php if (isset($_SESSION['logged_in'])): ?>
                    <span class="navbar-text me-2 d-none d-sm-inline">Hoş Geldiniz, <?php echo htmlspecialchars($_SESSION['username']); ?>!</span>
                    <a href="?action=logout" class="btn btn-sm btn-danger">Çıkış Yap</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <div class="container-fluid content-wrapper">
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger mx-3"><?php echo $error_message; ?></div>
        <?php endif; ?>
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success mx-3"><?php echo $success_message; ?></div>
        <?php endif; ?>

        <?php if ($auth_required): // Giriş zorunlu ve giriş yapılmamışsa ?>
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <div class="card <?php echo $card_class; ?> shadow">
                        <div class="card-header">Tek Kullanıcı Girişi (Paste Oluşturmak İçin)</div>
                        <div class="card-body">
                            <?php if (isset($login_error)): ?>
                                <div class="alert alert-danger"><?php echo $login_error; ?></div>
                            <?php endif; ?>
                            <form method="post">
                                <div class="mb-3">
                                    <label for="username" class="form-label">Kullanıcı Adı</label>
                                    <input type="text" class="form-control" id="username" name="username" required>
                                </div>
                                <div class="mb-3">
                                    <label for="password" class="form-label">Şifre</label>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                </div>
                                <button type="submit" name="login" class="btn btn-primary">Giriş Yap</button>
                                <p class="mt-2 small text-muted">cODe By GoogleGemini</p>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

        <?php else: // Giriş zorunlu değilse VEYA giriş yapılmışsa ?>
            <div class="row">
                <div class="col-lg-8 mb-4">
                    <div class="card <?php echo $card_class; ?> shadow">
                        <div class="card-header h5">
                            <?php echo $edit_mode ? 'Paste Düzenleniyor' : ($view_mode ? $current_paste_title : 'Yeni İçerik Kaydet'); ?>
                            <?php if ($view_mode): ?>
                                <span class="badge bg-primary float-end">Dil: <?php echo strtoupper(str_replace('language-', '', $highlight_class)); ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($view_mode && isset($_SESSION['logged_in'])): // Yönetim butonları (sadece giriş yapınca) ?>
                            <div class="card-body py-2 px-3 border-bottom d-flex justify-content-between">
                                <div>
                                    <a href="?action=download&id=<?php echo htmlspecialchars($current_paste_id); ?>" class="btn btn-sm btn-success me-2">
                                        <i class="bi bi-cloud-arrow-down me-1"></i> Kaydet/İndir
                                    </a>
                                    <a href="?action=edit&id=<?php echo htmlspecialchars($current_paste_id); ?>" class="btn btn-sm btn-warning me-2">
                                        <i class="bi bi-pencil me-1"></i> Düzenle
                                    </a>
                                    <a href="?action=delete&id=<?php echo htmlspecialchars($current_paste_id); ?>" class="btn btn-sm btn-danger" onclick="return confirm('Bu pasteyi silmek istediğinizden emin misiniz?');">
                                        <i class="bi bi-trash me-1"></i> Sil
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="card-body p-0">
                            
                            <?php if ($view_mode): // Görüntüleme Modu ?>
                                <pre class="line-numbers <?php echo $highlight_class; ?>"><code class="<?php echo $highlight_class; ?>"><?php echo htmlspecialchars($current_paste_content); ?></code></pre>

                            <?php else: // Oluşturma veya Düzenleme Modu ?>
                                <form method="post" class="p-3">
                                    
                                    <?php if ($edit_mode): ?>
                                        <input type="hidden" name="is_update" value="true">
                                        <input type="hidden" name="original_id" value="<?php echo htmlspecialchars($current_paste_id); ?>">
                                    <?php endif; ?>

                                    <div class="mb-3">
                                        <label for="paste_title" class="form-label">Başlık:</label>
                                        <input type="text" class="form-control" id="paste_title" name="paste_title" maxlength="50" placeholder="Paste Başlığı" value="<?php echo htmlspecialchars($current_paste_title ?? ''); ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="paste_alias" class="form-label">Özel URL (Alias):</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><?php echo rtrim($BASE_URL, '/'); ?>/</span>
                                            <input type="text" class="form-control" id="paste_alias" name="paste_alias" placeholder="Girilen-Deger (Boş bırakırsanız rastgele oluşturulur)" 
                                            value="<?php echo htmlspecialchars($current_paste_alias ?? ''); ?>" 
                                            <?php echo $edit_mode ? 'disabled' : ''; ?>>
                                        </div>
                                        <?php if ($edit_mode): ?>
                                             <div class="form-text text-warning">Düzenleme modunda Alias değiştirilemez.</div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="mb-3">
                                        <textarea class="form-control" name="paste_content" rows="15" placeholder="Kodunuzu veya metninizi buraya yapıştırın..." required><?php echo htmlspecialchars($current_paste_content ?? ''); ?></textarea>
                                    </div>
                                    <button type="submit" name="paste_submit" class="btn btn-<?php echo $edit_mode ? 'warning' : 'success'; ?>">
                                        <?php echo $edit_mode ? 'Güncelle' : 'Kaydet ve URL Oluştur'; ?>
                                    </button>
                                    
                                    <?php if ($ENABLE_AUTH && !isset($_SESSION['logged_in'])): ?>
                                        <div class="alert alert-info mt-3">Sistemde giriş zorunluluğu (Login) **AÇIK**. Paste oluşturabilmek için lütfen **giriş yapın**.</div>
                                    <?php endif; ?>
                                </form>
                            <?php endif; ?>
                        </div>
                        
                        <div class="card-footer text-muted d-flex justify-content-between">
                            <a href="index.php" class="btn btn-sm btn-info">Yeni Paste Oluştur</a>
                            <?php if ($view_mode && isset($_SESSION['logged_in'])): ?>
                                <span class="text-secondary small">ID: <?php echo htmlspecialchars($current_paste_id); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 mb-4">
                    <div class="card <?php echo $card_class; ?> shadow">
                        <div class="card-header h5">Önceki Kayıtlar (<?php echo count($pastes); ?>)</div>
                        <div class="card-body p-0">
                            <?php if (!empty($pastes_list)): ?>
                                <div class="list-group list-group-flush">
                                    <?php 
                                    $i = 0;
                                    foreach ($pastes_list as $paste): 
                                        if ($i >= 15) break; 
                                        $is_active = $current_paste_id === $paste['id'] ? 'active' : '';
                                    ?>
                                        <a href="<?php echo rtrim($BASE_URL, '/') . '/' . htmlspecialchars($paste['id']); ?>" class="list-group-item list-group-item-action <?php echo $is_active; ?> <?php echo ($theme_mode === 'dark' ? 'list-group-item-secondary' : ''); ?>">
                                            <div class="fw-bold"><?php echo htmlspecialchars($paste['title']); ?></div>
                                            <small class="text-muted">#<?php echo htmlspecialchars($paste['id']); ?> - <?php echo date('d.m.Y H:i', strtotime($paste['date'])); ?></small>
                                        </a>
                                    <?php 
                                        $i++;
                                    endforeach; 
                                    ?>
                                </div>
                            <?php else: ?>
                                <div class="p-3 text-muted">Henüz hiç kayıt yok.</div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (isset($_SESSION['logged_in'])): // Kullanıcı ayarları sadece giriş yapınca görünür ?>
                            <div class="card-footer">
                                <div class="mb-2">
                                    <strong>Kullanıcı Ayarları</strong>
                                </div>
                                <?php if (isset($user_update_error)): ?>
                                    <div class="alert alert-danger small"><?php echo $user_update_error; ?></div>
                                <?php endif; ?>
                                <form method="post" class="row g-2">
                                    <div class="col-12">
                                        <label class="form-label small">Kullanıcı Adı</label>
                                        <input type="text" name="new_username" class="form-control form-control-sm" value="<?php echo htmlspecialchars($username); ?>" required>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small">Yeni Şifre <span class="text-muted small">(boş bırakırsanız değişmez)</span></label>
                                        <input type="password" name="new_password" class="form-control form-control-sm" autocomplete="new-password">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small">Yeni Şifre (Tekrar)</label>
                                        <input type="password" name="new_password_confirm" class="form-control form-control-sm" autocomplete="new-password">
                                    </div>
                                    <div class="col-12">
                                        <button type="submit" name="update_user" class="btn btn-sm btn-outline-primary">Güncelle</button>
                                    </div>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        <?php endif; ?>
    </div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/prism.min.js"></script>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/plugins/line-numbers/prism-line-numbers.min.js"></script>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/plugins/autoloader/prism-autoloader.min.js"></script>
    
    <script>
        if (document.querySelector('pre[class*="language-"]')) {
             Prism.highlightAll();
        }
    </script>
</div>
<footer class="app-footer <?php echo ($theme_mode === 'dark' ? 'bg-dark text-muted' : 'bg-light text-secondary'); ?>">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center small">
            <span>&copy; <?php echo date('Y'); ?> **EnesBiBER.CoM.TR**. Tüm hakları saklıdır.</span>
            <span class="d-none d-sm-inline">
                Kodlama: <a href="https://github.com/lazenes" class="text-<?php echo ($theme_mode === 'dark' ? 'light' : 'dark'); ?> text-decoration-none">EnesBİBER</a> 
            </span>
        </div>
    </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>