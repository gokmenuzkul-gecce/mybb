<?php

/**
 * MyBB 1.8 Türkçe Dil Paketi
 * Copyright © 2024 MyBB Pro, Tüm Hakları Saklıdır.
 * 
 * Website: https://mybb.pro
 * Lisans: https://mybb.pro/hakkimizda/lisans/
 * 
 * Support: https://mybb.pro/
 *
 * Last Update: 08.12.2024 (v1.8.38)
 */

// Sekmeler
$l['attachments'] = "Ek Dosyalar";
$l['stats'] = "İstatistikler";
$l['find_attachments'] = "Ek Dosyaları Bul";
$l['find_attachments_desc'] = "Ek dosya arama sistemini kullanarak kullanıcıların forumlarınıza ekledikleri belirli dosyaları arayabilirsiniz. Aşağıya bazı arama terimleri girerek başlayın. Tüm alanlar isteğe bağlıdır ve bir değer içermedikleri sürece arama kriterlerine dahil edilmezler.";
$l['find_orphans'] = "Sahipsiz Ek Dosyaları Bul";
$l['find_orphans_desc'] = "Sahipsiz ek dosyalar, veritabanında veya dosya sisteminde herhangi bir nedenle eksik/hatalı olan ek dosyalardır. Bu araç, bunları bulmanıza ve kaldırmanıza yardımcı olacaktır.";
$l['attachment_stats'] = "Ek Dosya İstatistikleri";
$l['attachment_stats_desc'] = "Aşağıda, şu anda forumunuzda bulunan ek dosyalar için bazı genel istatistikler yer almaktadır.";

// Hatalar
$l['error_nothing_selected'] = "Lütfen silmek için bir veya daha fazla ek dosya seçin.";
$l['error_no_attachments'] = "Forumunuzda henüz herhangi bir ek dosya yok. Bir ek dosya yüklendikten sonra bu bölüme erişebileceksiniz.";
$l['error_not_all_removed'] = "Sadece bazı sahipsiz ek dosyalar başarıyla silindi, diğerleri yükleme dizininden kaldırılamadı.";
$l['error_count'] = '{1} ek dosyaları kaldırılamıyor.';
$l['error_invalid_username'] = "Girdiğiniz kullanıcı adı geçersiz.";
$l['error_invalid_forums'] = "Seçtiğiniz bir veya daha fazla forum geçersiz.";
$l['error_no_results'] = "Belirtilen arama kriterlerine uygun hiçbir ek dosya bulunamadı.";
$l['error_not_found'] = "Ek dosya uploads dizininde bulunamadı.";
$l['error_not_attached'] = "Ek Dosya 24 saat önce yüklenmiş ancak herhangi bir gönderiye eklenmemiş.";
$l['error_does_not_exist'] = "Bu ek dosya için konu veya gönderi artık mevcut değil.";

// Başarılı Mesajları
$l['success_deleted'] = "Seçilen ek dosya(lar) başarıyla silindi.";
$l['success_orphan_deleted'] = "Seçilen sahipsiz ek dosya(lar) başarıyla silindi.";
$l['success_count'] = '{1} ek dosya(lar) başarıyla kaldırıldı.';
$l['success_no_orphans'] = "Forumunuzda sahipsiz ek dosya yok.";

// Onay
$l['confirm_delete'] = "Seçilen ek dosyaları silmek istediğinizden emin misiniz?";

// == Sayfalar
// = İstatistikler
$l['general_stats'] = "Genel İstatistikler";
$l['stats_attachment_stats'] = "Ek Dosyalar - Ek Dosya İstatistikleri";
$l['num_uploaded'] = "<strong>Hayır. Yüklenen Ek Dosyalar</strong>";
$l['space_used'] = "<strong>Kullanılan Ek Dosya Alanı</strong>";
$l['bandwidth_used'] = "<strong>Tahmini Bant Genişliği Kullanımı</strong>";
$l['average_size'] = "<strong>Ortalama Ek Dosya Boyutu</strong>";
$l['size'] = "Boyut";
$l['posted_by'] = "Yükleyen";
$l['thread'] = "Konu";
$l['downloads'] = "İndirmeler";
$l['date_uploaded'] = "Yüklendiği Tarih";
$l['popular_attachments'] = "En Popüler 5 Ek Dosya";
$l['largest_attachments'] = "Boyutu En Yüksek Olan 5 Ek Dosya";
$l['users_diskspace'] = "En Çok Disk Alanı Kullanan İlk 5 Kullanıcı";
$l['username'] = "Kullanıcı Adı";
$l['total_size'] = "Toplam Boyut";

// = Sahipsizler
$l['orphan_results'] = "Sahipsiz Ek Dosyalar Arama - Sonuçlar";
$l['orphan_attachments_search'] = "Sahipsiz Ek Dosya Arama";
$l['reason_orphaned'] = "Sahipsiz Kalma Nedeni";
$l['reason_not_in_table'] = "Ek dosyalar tablosunda değil.";
$l['reason_file_missing'] = "Ek dosya eksik.";
$l['reason_thread_deleted'] = "Konusu silindi.";
$l['reason_post_never_made'] = "Gönderi hiç yapılmadı.";
$l['unknown'] = "Bilinmiyor";
$l['results'] = "Sonuçlar";
$l['step1'] = "Adım 1";
$l['step2'] = "Adım 2";
$l['step1of2'] = "Adım 1/2 - Dosya Sistemi Taraması";
$l['step2of2'] = "Adım 2/2 - Veritabanı Taraması";
$l['step1of2_line1'] = "Lütfen bekleyin, dosya sistemi şu anda sahipsiz ek dosyalar için taranıyor.";
$l['step2of2_line1'] = "Lütfen bekleyin, veritabanı şu anda sahipsiz ek dosyalar için taranıyor.";
$l['step_line2'] = "Bu işlem tamamlandığında otomatik olarak bir sonraki adıma yönlendirileceksiniz.";
$l['scanning'] = 'Taranıyor&hellip;';

// = Ek Dosyalar / Dizin (İndex)
$l['index_find_attachments'] = "Ek Dosyalar - Ek Dosyaları Bul";
$l['find_where'] = "Ek Dosyaları Nasıl Bulabiliriz&hellip;";
$l['name_contains'] = "Dosya Adıyla Ara";
$l['name_contains_desc'] = "Dosya adını içeren ek dosyaları arayın. Örneğin, .zip dosya uzantısını kullanan ekleri bulmak için .zip girin.";
$l['type_contains'] = "Dosya Türüne Göre Ara";
$l['forum_is'] = "Forumlarda Bul";
$l['username_is'] = "Yükleyen Kullanıcının Adı";
$l['poster_is'] = "Yükleyen Kişi";
$l['poster_is_either'] = "Kullanıcı veya Ziyaretçi";
$l['poster_is_user'] = "Yalnızca Kullanıcılar";
$l['poster_is_guest'] = "Sadece Ziyaretçiler";
$l['more_than'] = "Daha Fazla";
$l['greater_than'] = "Daha Büyük";
$l['is_exactly'] = "Tam Olarak";
$l['less_than'] = "Daha Az";
$l['date_posted_is'] = "Gönderildiği Tarih";
$l['days_ago'] = "Gün Önce";
$l['file_size_is'] = "Dosya Boyutu";
$l['kb'] = "KB";
$l['download_count_is'] = "İndirme Sayısı";
$l['display_options'] = "Görüntüleme Seçenekleri";
$l['filename'] = "Dosya Adı";
$l['filesize'] = "Dosya Boyutu";
$l['download_count'] = "İndirme Sayısı";
$l['post_username'] = "Yükleyen Kullanıcı Adı";
$l['asc'] = "Artan";
$l['desc'] = "Azalan";
$l['sort_results_by'] = "Sonuçları Şuna Göre Sırala";
$l['results_per_page'] = "Sayfa Başına Sonuç";
$l['in'] = "-";

// Buttonlar
$l['button_delete_orphans'] = "İşaretlenen Sahipsizleri Sil";
$l['button_delete_attachments'] = "Seçilen Ek Dosyaları Sil";
$l['button_find_attachments'] = "Ek Dosyaları Bul";
