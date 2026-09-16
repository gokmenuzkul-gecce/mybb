# Orijinal 20 maddelik istek (kullanıcı, 2 kez yeniden gönderdi)

1.  VIP kategorisi ana sayfada görünsün; yetkisi olanlar girebilsin, normal
    kullanıcıyı VIP almaya teşvik eden planlar + görsel + göze çarpan başvuru
    yerleri.
2.  VIP onaylanan kullanıcıya tebrik mesajı: VIP Club'a hoş geldin + bundan
    sonra yararlanacağı şeyler.
3.  Ana sayfaya şık, modern, dinamik duyurular eklentisi.
4.  Uygun yerlere çeşitli boyut/şekillerde reklam alanları; "buraya reklam
    verebilirsiniz" placeholder; yazılı + görsel + butonlu; ACP'den yönetim.
5.  Sponsor iletişim formu; sponsor ulaşınca ACP'ye bildirim; ACP'den sponsor
    ekleme; affiliate yönlendirmenin dört dörtlük çalışması ve tıklama takibi.
6.  Giriş paneline Google, GitHub ve popüler medya ile kayıt/giriş.
7.  Ekranın sağındaki kayıt olma yeri demode; modernize et.
8.  Güvenlik: portal kısmını kaldır (güvenlik açığı riski) + Google SEO uyumu.
9.  "VIP Balina Sinyalleri ve Spot Sepetleri" formunun solunda ikon yok; ekle.
10. Ana sayfaya sırıtmayacak, yer kaplamayan, göz yormayan canlı borsa tablosu.
11. Konu açarken mesaj yazma rengi gri, görünmüyor; forum düzenine uygun yap.
12. Sağ üst profil ayarına kullanıcı rahat ulaşabilsin.
13. Ana sayfadaki "MyBB forum" yazısını kaldır; altındaki kutuya slayt menü ekle
    (mevcuda ek), ACP'den ekleme yapılabilsin, geçişli.
14. Sağ üst köşedeki kullanıcı paneli çok demode; modernize et.
15. "Hoşgeldin, Ziyaretçi: Giriş Yap Kayıt Ol" çok basit; modernize et.
16. Ana sayfadaki kategori estetiği forumlarda da olsun; konuların başına
    "kaç cevap gelmiş" gibi yeni nesil bilgiler ekle.
17. Hatalı girişte çıkan sarı alanı ve özel mesaj kutusundaki aynı sarı alanı
    modernize et.
18. Üyeliğe gerçek onay maili gitsin; maildeki aktivasyon linkine tıklayınca
    üyelik etkinleşsin, admin onayı beklenmesin.
19. VIP satın alanların cüzdan adresini girebileceği bir panel; çeşitli ağlar
    seçilebilsin; yönetici de kendi ağlarını girebilsin.
20. Besleme (RSS) robotu ayarları:
    - Çekilen haberde konu beyaz kutu içinde ve yazı beyaz olduğu için
      görünmüyor; düzelt.
    - Çekilen haber çok kısa; tamamını almasın ama biraz daha fazla alsın,
      gerçek konu gibi görünsün.
    - Çekilecek haberler forum başlıklarına göre ACP'den kategorilensin; toplu
      çekim otomatik olsun.
    - Manuel onay verilebilsin; toplu adresler girilebilsin; admin panelinde
      günlük çekilen haberler onaylanıp gönderilsin, üzerinde son bir çalışma
      yapılabilsin.

Durum (bu dosya güncel tutulmalı):

| # | Durum | Not |
|---|-------|-----|
| 1 | bitti | `vip_membership_home_showcase()` |
| 2 | bitti | `vip_membership_send_welcome()` + banner |
| 3 | bitti | `board_promos` duyuru şeridi |
| 4 | bitti | `board_promos` reklam slotları |
| 5 | bitti | `board_promos` sponsor + affiliate + tıklama takibi |
| 6 | YAPILMADI | sosyal giriş hiç yok — `social_login` eklentisi |
| 7 | kısmi | kayıt sayfası hâlâ stok düzen |
| 8 | bitti | `portal=0` kapatıldı |
| 9 | bitti | vip.php form ikonları var |
| 10 | YAPILMADI | canlı borsa tablosu hiç yok — `market_ticker` eklentisi |
| 11 | bitti | sceditor renk override |
| 12 | bitti | profil menüsü |
| 13 | bitti | hero + `nextgen-slider` |
| 14 | bitti | `nextgen-userpanel` |
| 15 | bitti | `nextgen-login` modal |
| 16 | kısmi | forum satırları modern; cevap sayısı göstergesi yok |
| 17 | YAPILMADI | `error_inline` hâlâ stok `.error` div'i |
| 18 | bitti | `regtype=verify` |
| 19 | bitti | `vip_networks` + `vip.php` |
| 20 | bitti | `rss_news_bot` |
