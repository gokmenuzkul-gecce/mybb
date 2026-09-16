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
 * Last Update: 18.12.2024 (v1.8.38)
 */


$l['task_manager'] = "Görev Yöneticisi";
$l['add_new_task'] = "Yeni Görev Ekle";
$l['add_new_task_desc'] = "Burada forumunuzda otomatik olarak çalıştırılacak yeni zamanlanmış görevler oluşturabilirsiniz.";
$l['edit_task'] = "Görevi Düzenle";
$l['edit_task_desc'] = "Aşağıda bu zamanlanmış görev için çeşitli ayarları düzenleyebilirsiniz.";
$l['task_logs'] = "Görev Kayıtları";
$l['view_task_logs'] = "Görev Kayıtlarını Görüntüle";
$l['view_task_logs_desc'] = "Bir görev çalıştırıldığında ve görev kayıtları etkinleştirildiğinde, tüm sonuçlar veya hatalar aşağıda listelenecektir. 30 günden eski kayıtlar otomatik olarak silinir.";
$l['scheduled_tasks'] = "Zamanlanmış Görevler";
$l['scheduled_tasks_desc'] = "Burada forumunuzda otomatik olarak çalıştırılan görevleri yönetebilirsiniz. Bir görevi şimdi çalıştırmak için ilgili görevin sağ tarafından yer alan \"Şimdi Çalıştır\" simgesine tıklayın.";

$l['title'] = "Başlık";
$l['short_description'] = "Kısa Açıklama";
$l['task_file'] = "Görev Dosyası";
$l['task_file_desc'] = "Bu görevin çalıştırılmasını istediğiniz görev dosyasını seçin.";
$l['time_minutes'] = "Zaman: Dakika";
$l['time_minutes_desc'] = "Bu görevin çalışması gereken dakikaların (0-59) virgülle ayrılmış bir listesini girin. Bu görevin her dakika çalışması gerekiyorsa <strong>'*'</strong> değerini girin.";
$l['time_hours'] = "Zaman: Saatler";
$l['time_hours_desc'] = "Bu görevin çalışması gereken saatlerin (0-23) virgülle ayrılmış bir listesini girin. Bu görevin her saat çalışması gerekiyorsa <strong>'*'</strong> değerini girin.";
$l['time_days_of_month'] = "Zaman: Ayın Günleri";
$l['time_days_of_month_desc'] = "Bu görevin çalışması gereken günlerin (1-31) virgülle ayrılmış bir listesini girin. Bu görevin her gün çalışması gerekiyorsa veya aşağıda bir hafta içi gün belirtmek istiyorsanız <strong>'*'</strong> değerini girin.";
$l['every_weekday'] = "Hafta İçi Her Gün";
$l['sunday'] = "Pazar";
$l['monday'] = "Pazartesi";
$l['tuesday'] = "Salı";
$l['wednesday'] = "Çarşamba";
$l['thursday'] = "Perşembe";
$l['friday'] = "Cuma";
$l['saturday'] = "Cumartesi";
$l['time_weekdays'] = "Zaman: Hafta içi";
$l['time_weekdays_desc'] = "Bu görevin hangi hafta içi günlerde çalışması gerektiğini seçin. CTRL tuşunu basılı tutarak birden fazla hafta içi gün seçebilirsiniz. Bu görevin hafta içi her gün çalışmasını istiyorsanız veya yukarıda önceden tanımlanmış bir gün girdiyseniz 'Hafta İçi Her Gün' seçeneğini seçin.";
$l['every_month'] = "Her Ay";
$l['time_months'] = "Zaman: Aylar";
$l['time_months_desc'] = "Bu görevin hangi aylarda çalışması gerektiğini seçin. CTRL tuşunu basılı tutarak birden fazla ay seçebilirsiniz. Bu görevin her ay çalışmasını istiyorsanız 'Her Ay' seçeneğini seçin.";
$l['enabled'] = "Görev Etkinleştirilsin mi?";
$l['enable_logging'] = "Görev Kayıtları Kayıt Edilsin mi?";
$l['save_task'] = "Görevi Kaydet";
$l['task'] = "Görev";
$l['date'] = "Tarih";
$l['data'] = "Veri";
$l['no_task_logs'] = "Şu anda zamanlanmış görevlerin hiçbiri için günlük kaydı bulunmamaktadır.";
$l['next_run'] = "Çalışma Zamanı";
$l['run_task_now'] = "Bu Görevi Şimdi Çalıştır";
$l['disable_task'] = "Görevi Devredışı Bırak";
$l['run_task'] = "Görevi Çalıştır";
$l['enable_task'] = "Görevi Etkinleştir";
$l['delete_task'] = "Görevi Sil";

$l['error_invalid_task'] = "Belirtilen görev mevcut değil.";
$l['error_missing_title'] = "Bu zamanlanmış görev için bir başlık girmediniz.";
$l['error_missing_description'] = "Bu zamanlanmış görev için bir açıklama girmediniz.";
$l['error_invalid_task_file'] = "Seçtiğiniz görev dosyası mevcut değil.";
$l['error_invalid_minute'] = "Girmiş olduğunuz dakika geçersizdir.";
$l['error_invalid_hour'] = "Girmiş olduğunuz saat geçersizdir.";
$l['error_invalid_day'] = "Girdiğiniz gün geçersizdir.";
$l['error_invalid_weekday'] = "Seçtiğiniz hafta içi gün geçersizdir.";
$l['error_invalid_month'] = "Seçmiş olduğunuz ay geçersizdir.";

$l['success_task_created'] = "Görev başarıyla oluşturuldu.";
$l['success_task_updated'] = "Seçilen görev başarıyla güncellendi.";
$l['success_task_deleted'] = "Seçilen görev başarıyla silindi.";
$l['success_task_enabled'] = "Seçilen görev başarıyla etkinleştirildi.";
$l['success_task_disabled'] = "Seçilen görev başarıyla devredışı bırakıldı.";
$l['success_task_run'] = "Seçilen görev başarıyla çalıştırıldı.";

$l['confirm_task_deletion'] = "Bu zamanlanmış görevi silmek istediğinizden emin misiniz?";
$l['confirm_task_enable'] = "<strong>UYARI:</strong> Yalnızca cron aracılığıyla çalıştırılması gereken bir görevi etkinleştirmek üzeresiniz (Daha fazla bilgi için lütfen <a href=\"https://mybb.pro/forum-mybb-yardim-belgeleri\" target=\"_blank\" rel=\"noopener\">MyBB Yardım Belgeleri</a>'ne bakın). Devam edelim mi?";
$l['no_tasks'] = "Şu anda forumunuzda herhangi bir görev bulunmamaktadır.";

