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
 * Last Update: 17.12.2024 (v1.8.38)
 */


$l['database_backups'] = "Veritabanı Yedekleri";
$l['database_backups_desc'] = "Burada, şu anda web sunucunuzda <em>./admin/backups</em> dizininde depolanan veritabanı yedeklerinin bir listesini bulabilirsiniz.";
$l['new_database_backup'] = "Yeni Veritabanı Yedeği Oluşturma";
$l['new_backup'] = "Yeni Yedek";
$l['new_backup_desc'] = "Burada veritabanınızın bir kopyasını yedekleyebilirsiniz.";
$l['backups'] = "Yedekler";
$l['existing_database_backups'] = "Mevcut Veritabanı Yedekleri";

$l['backup_saved_to'] = "Yedek şuraya kaydedildi:";
$l['download'] = "İndir";
$l['table_selection'] = "Tablo Seçimi";
$l['backup_options'] = "Yedekleme Seçenekleri";
$l['table_select_desc'] = "Bu eylemi gerçekleştirmek istediğiniz veritabanı tablolarını buradan seçebilirsiniz. Birden fazla tablo seçmek için CTRL tuşunu basılı tutun.";
$l['select_all'] = "Tümünü Seç";
$l['deselect_all'] = "Tümünün Seçimini Kaldır";
$l['select_forum_tables'] = "Forum Tablolarını Seçin";
$l['file_type'] = "Dosya Türü";
$l['file_type_desc'] = "Veritabanı yedeğinin kaydedilmesini istediğiniz dosya türünü seçin.";
$l['gzip_compressed'] = "GZIP Sıkıştırılmış (Tavsiye Edilir)";
$l['plain_text'] = "Düz Metin (.sql - Sıkıştırılmamış)";
$l['save_method'] = "Kaydetme Yöntemi";
$l['save_method_desc'] = "Yedeklemeyi kaydetmek için kullanmak istediğiniz yöntemi seçin.";
$l['backup_directory'] = "Yedekleme Dizini <em>(./admin/backups)</em>";
$l['backup_contents'] = "Yedekleme İçeriği";
$l['backup_contents_desc'] = "Yedeklemeye dahil edilmesini istediğiniz bilgileri seçin.";
$l['structure_and_data'] = "Yapı ve Veriler (Tavsiye Edilir)";
$l['structure_only'] = "Sadece Yapı";
$l['data_only'] = "Yalnızca Veri";
$l['analyze_and_optimize'] = "Seçilen Tabloları Analiz ve Optimize Et";
$l['analyze_and_optimize_desc'] = "Seçilen tabloların yedekleme sırasında analiz edilmesini ve optimize edilmesini ister misiniz?";
$l['perform_backup'] = "Yedeği Oluştur";
$l['backup_filename'] = "Yedek Dosya Adı";
$l['file_size'] = "Dosya Boyutu";
$l['creation_date'] = "Oluşturulma Tarihi";
$l['no_backups'] = "Şu anda oluşturulmuş herhangi bir yedek bulunmuyor.";

$l['error_file_not_specified'] = "İndirilecek bir veritabanı yedeği belirtmediniz.";
$l['error_invalid_backup'] = "Seçtiğiniz yedekleme dosyası ya geçersiz ya da mevcut değil.";
$l['error_backup_doesnt_exist'] = "Belirtilen yedek mevcut değil.";
$l['error_backup_not_deleted'] = "Yedek silinemedi.";
$l['error_tables_not_selected'] = "Yedeklenecek herhangi bir tablo seçmediniz.";
$l['error_no_zlib'] = "PHP için zlib kütüphanesi etkin değil - GZIP ile sıkıştırılmış yedekler oluşturamazsınız. Lütfen, sunucu yöneticiniz ile iletişime geçin.";

$l['alert_not_writable'] = "Yedekleme klasörünüz olan <em>./admin/backups</em> dizini yazdırabilir değil. Lütfen, ilgili dizinin yazılabilir olduğundan emin olun. CHMOD: 777 olarak ayarlanmalı. Detaylı bilgi için <a href=\"https://mybb.pro/forum-mybb-yardim-belgeleri\" target=\"_blank\" rel=\"noopener\">tıklayınız.</a>";

$l['confirm_backup_deletion'] = "Bu yedeği silmek istediğinizden emin misiniz?";

$l['success_backup_deleted'] = "Yedek başarıyla silindi.";
$l['success_backup_created'] = "Yedek başarıyla oluşturuldu.";
