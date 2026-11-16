# Makale İstatistik Gösterimi Eklentisi - OJS 3.3

Open Journal Systems web sitenizde makale görüntülenme sayılarını ve galley indirme istatistiklerini görüntüleyin.

## Açıklama

Bu eklenti, makale istatistiklerini (özet görüntülenme ve PDF/galley indirme sayıları) temiz ve modern bir tasarımla gösterir. İstatistikler makale detay sayfalarında, sayı içindekiler listesinde ve anasayfada görüntülenebilir.

### Özellikler

- **Çoklu Gösterim Konumları**: İstatistikleri makale sayfalarında, sayı içindekilerde ve anasayfada gösterin
- **İki Görsel Stil**: Rozet stili (renkli kartlar) veya Satır içi stil (temiz metin)
- **Üç Pozisyon Seçeneği**: Başlık altı, özet altı veya makale yan çubuğu
- **Çoklu Tema Desteği**: Default, Manuscript, Bootstrap3, Health Sciences ve diğer OJS temalarıyla çalışır
- **Duyarlı Tasarım**: Hem masaüstü hem de mobil cihazlar için optimize edilmiş
- **İki Dil Desteği**: İngilizce ve Türkçe dil desteği

## Gereksinimler

- OJS 3.3.x
- Kullanım İstatistikleri Eklentisi etkinleştirilmiş olmalı

## Kurulum

1. En son sürümü indirin (`articleStatisticsDisplay.tar.gz`)
2. **Ayarlar → Web Sitesi → Eklentiler → Yeni Eklenti Yükle** bölümüne gidin
3. İndirdiğiniz dosyayı yükleyin
4. **Genel Eklentiler** altında eklentiyi etkinleştirin
5. Görüntüleme seçeneklerini yapılandırmak için **Ayarlar**'a tıklayın

## Yapılandırma

### Genel Ayarlar

- **Gösterim Konumları**: İstatistiklerin nerede gösterileceğini seçin
  - Makale detay sayfası
  - Sayı içindekiler listesi
  - Anasayfa (güncel sayı)

- **İstatistik Türleri**: Hangi istatistiklerin gösterileceğini seçin
  - Özet/makale görüntülenme sayısı
  - Galley (PDF, HTML vb.) indirme sayısı

### Makale Detay Sayfası Ayarları

- **Gösterim Stili**:
  - Rozet Stili: Gradyan arka planlı renkli rozetler
  - Satır İçi Stil: Sol kenarlık vurgulu temiz metin

- **Pozisyon**:
  - Makale başlığının altında
  - Özet metninin altında
  - Makale yan çubuğu (dikey düzen)

## Ekran Görüntüleri

### Rozet Stili
```
┌─────────────────────────────────────────────┐
│ [👁 Özet Görüntüleme: 338] [⬇ PDF İndirme: 173] │
└─────────────────────────────────────────────┘
```

### Satır İçi Stil
```
┃ 👁 Özet Görüntüleme: 338 | ⬇ PDF İndirme: 173
```

### Liste Görünümü (Sayı İçindekiler / Anasayfa)
```
[PDF] [👁 366] [⬇ 177]
```

## Uyumluluk

Aşağıdaki OJS 3.3 temalarıyla test edilmiştir:
- Default Tema
- Manuscript Tema
- Bootstrap3 Tema
- Health Sciences Tema

## Lisans

Bu eklenti GNU Genel Kamu Lisansı v3.0 ile lisanslanmıştır.

## Yazar

**Kerim Sarıgül**

## Destek

Hata raporları ve özellik istekleri için lütfen GitHub Issues sayfasını kullanın.
