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


$l['cache'] = "Önbellek:";
$l['cache_manager'] = "Önbellek Yöneticisi";
$l['cache_manager_description'] = "Burada MyBB'yi optimize etmek için bir yöntem olarak kullanılan önbellekleri yönetebilirsiniz. Bir önbelleği yeniden oluşturmak, önbelleği oluşturmak için kullanılan tüm gerekli verileri alacak ve yeniden senkronize edecektir. Bir önbelleği yeniden yüklemek, onu seçilen önbellek işleyicisine (disk, eaccelerator, memcache, vb.) yeniden yükleyecektir. Yeniden yükleme, veritabanı veya dosya sisteminden xcache, eaccelerator veya başka bir önbellek işleyicisine geçerken kullanışlıdır.";
$l['rebuild_cache'] = "Önbelleği Yeniden Oluştur";
$l['reload_cache'] = "Önbelleği Yeniden Yükle";
$l['rebuild_reload_all'] = "(Tümünü Yeniden Oluştur ve Yeniden Yükle)";

$l['error_cannot_rebuild'] = "Bu önbellek yeniden oluşturulamaz.";
$l['error_empty_cache'] = "Önbellek boş.";
$l['error_incorrect_cache'] = "Yanlış önbellek belirtildi.";
$l['error_no_cache_specified'] = "Görüntülemek için bir önbellek belirtmediniz.";

$l['success_cache_rebuilt'] = "Önbellek başarıyla yeniden oluşturuldu.";
$l['success_cache_reloaded'] = "Önbellek başarıyla yeniden yüklendi.";
