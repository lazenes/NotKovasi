# Basit PHP Pastebin / Kod Paylaşım Aracı

Bu proje, tek bir PHP dosyasıyla çalışan, **giriş zorunluluğu isteğe bağlı** olan, hafif ve kişisel kullanıma uygun bir kod/metin paylaşım (Pastebin) aracıdır. Yönetici paneli tek bir kullanıcı ile sınırlıdır ve dosyaları sunucuda güvenli bir şekilde saklar.

## Özellikler

* **Tek Dosya:** Tüm işlevsellik tek bir `index.php` dosyasında bulunur.
* **İsteğe Bağlı Giriş (Auth):**
    * `$ENABLE_AUTH = true;` ise, sadece giriş yapan kullanıcılar paste oluşturabilir.
    * `$ENABLE_AUTH = false;` ise, herkes paste oluşturabilir (anonim kullanım). Yönetim/silme/düzenleme işlemleri hala yönetici girişi gerektirir.
* **Tema Desteği:** Tek tıkla **Açık/Koyu Tema** geçişi.
* **Söz Dizimi Vurgulama:** [Prism.js](https://prismjs.com/) ile otomatik dil algılama ve renklendirme.
* **Satır Numaraları:** Kod bloklarında satır numarası gösterme.
* **Kalıcı Depolama:** Pasteler sunucu diskinde (`pastes/` klasöründe) saklanır, veritabanı gerektirmez.
* **Yönetici Fonksiyonları:** Kayıtları silme, düzenleme ve yönetici kullanıcı adı/şifresini güncelleme.

---

## Kurulum

### 1. Dosyaları Sunucuya Yükleme

1.  **`index.php`:** Sağlanan PHP kodunu ana dizininize yükleyin.
2.  **`config.json`:** (İlk çalıştırmada otomatik oluşturulacaktır, izin sorunu yaşarsanız el ile boş bir dosya oluşturun.)
3.  **`pastes.json`:** (İlk çalıştırmada otomatik oluşturulacaktır, izin sorunu yaşarsanız el ile boş bir dosya oluşturun.)

### 2. Klasör İzinleri

PHP'nin dosya oluşturabilmesi ve düzenleyebilmesi için aşağıdaki klasör ve dosyalara doğru izinleri (yetkileri) vermelisiniz:

| Öğe | Önerilen İzin | Açıklama |
| :--- | :--- | :--- |
| **`pastes/`** (Klasör) | `0755` veya `0777` | Pastelerin içeriği buraya kaydedilir. |
| **`pastes.json`** (Dosya) | `0644` veya `0666` | Tüm pastelerin meta verileri buraya kaydedilir. |
| **`config.json`** (Dosya) | `0644` veya `0666` | Yönetici kullanıcı bilgileri burada saklanır. |

### 3. Yönetici Şifresi

İlk çalıştırmada sistem otomatik olarak varsayılan kullanıcı adı/şifre ile başlar:

* **Kullanıcı Adı:** `admin`
* **Şifre:** `admin`

Giriş yaptıktan sonra, sağ taraftaki **Kullanıcı Ayarları** bölümünden şifrenizi **HEMEN** güncellemeyi unutmayın!

---

## Yapılandırma

`index.php` dosyasının en üstünde bulunan aşağıdaki değişkenleri ihtiyacınıza göre düzenleyin:

| Değişken | Varsayılan | Açıklama |
| :--- | :--- | :--- |
| `$ENABLE_AUTH` | `true` | `false` yaparsanız, giriş yapmadan da paste oluşturulabilir. |
| `$BASE_URL` | `'http://xxx.ltd/'` | **Kendi sitenizin URL'si** ile değiştirilmelidir. |
| `$PASTE_DIR` | `'pastes/'` | Paste dosyalarının kaydedileceği klasör adı. |

## Kullanım

1.  **Yeni Paste Oluşturma:** Ana sayfada içerik ve başlık girin, ardından **Kaydet** butonuna tıklayın.
2.  **Yönetici İşlemleri (Giriş Yapınca):**
    * Görüntüleme modunda iken **Düzenle** veya **Sil** butonlarını kullanabilirsiniz.
    * Sağ sütundaki **Kullanıcı Ayarları** ile yönetici bilgilerini güncelleyebilirsiniz.
3.  **İndirme:** Görüntülediğiniz paste'i `[İndir]` butonuyla orijinal dosya adı ve uzantısıyla (otomatik algılanan dil uzantısıyla) indirebilirsiniz.

---

## Telif Hakkı ve Lisans

Bu kod, Google'ın Gemini AI tarafından **Enes Biber**'in isteği üzerine oluşturulmuştur.

* Kodlama: Google Gemini
* Tasarım/Tema: [Bootstrap 5.3](https://getbootstrap.com/)
* Highlighting: [Prism.js](https://prismjs.com/)

### Geliştirici Notu

Bu proje basit yönetim ihtiyacı için tasarlanmıştır. Yüksek trafikli veya çok kullanıcılı ortamlar için uygun değildir. Güvenlik ve performans için kendi sunucunuzda test ederek kullanın.
