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
 * Last Update: 27.11.2024 (v1.8.38)
 */

$l['click_no_wait'] = "Daha fazla beklemek istemiyorsanız buraya tıklayın.";
$l['redirect_return_forum'] = "<br /><br />Alternatif olarak, <a href=\"{1}\">foruma geri dönün</a>.";
$l['redirect_emailsent'] = "E-posta mesajınız başarıyla gönderildi.";
$l['redirect_loggedin'] = "Başarılı bir şekilde giriş yaptınız.<br />Şimdi geldiğiniz yere geri yönlendiriliyorsunuz.";
$l['update_confirm'] = "Aşağıdaki dosya(lar) zaten eklidir ve yeni seçilen dosya(lar) ile güncellenecek / değiştirilecektir. {1} Bundan emin misiniz?";
$l['error_invalidpworusername'] = "Geçersiz bir kullanıcı adı/şifre kombinasyonu girdiniz. <br /><br />Eğer şifrenizi unuttuysanız lütfen <a href=\"member.php?action=lostpw\">yeni bir şifre alın</a>.";
$l['error_invalidpworusername1'] = "Geçersiz bir e-posta/şifre kombinasyonu girdiniz. <br /><br />Eğer şifrenizi unuttuysanız lütfen <a href=\"member.php?action=lostpw\">yeni bir şifre alın</a>.";
$l['error_invalidpworusername2'] = "Geçersiz bir kullanıcı adı/şifre veya e-posta/şifre kombinasyonu girdiniz. <br /><br />Eğer şifrenizi unuttuysanız lütfen <a href=\"member.php?action=lostpw\">yeni bir şifre alın</a>.";
$l['error_incompletefields'] = "Bir veya daha fazla gerekli alanı boş bırakmışsınız gibi görünüyor. Lütfen geri dönün ve gerekli alanları doldurun.";
$l['error_alreadyuploaded'] = "Bu gönderi zaten \"{1}\" adında bir ek dosya içeriyor. Lütfen dosyayı yeniden adlandırın ve tekrar yükleyin. Alternatif olarak \"Güncelle\" seçeneğine tıklayabilirsiniz.";
$l['error_alreadyuploaded_perm'] = "Bu gönderi zaten aynı isimde bir ek dosya içermektedir. Lütfen, ya mevcut dosyayı kaldırın ya da dosyayı yeniden adlandırıp tekrar yükleyin.";
$l['error_updatefailed'] = "\"{1}\" Dosya zaten ekli değil ve güncellenemiyor.";
$l['error_nomessage'] = "Üzgünüz, geçerli bir mesaj girmediğiniz için devam edemiyoruz.Lütfen, geri dönün ve geçerli bir mesaj girin.";
$l['error_invalidemail'] = "Geçerli bir e-posta adresi girmediniz.";
$l['error_nomember'] = "Belirttiğiniz üye ya geçersiz ya da mevcut değil.";
$l['error_maxposts'] = "Üzgünüm ama günlük gönderi limitiniz aşıldı.  Daha fazla gönderi yapmak için lütfen yarına kadar bekleyin veya yöneticinizle iletişime geçin.<br /><br />Bir gün içinde yapabileceğiniz maksimum gönderi sayısı {1}'dir.";
$l['error_nohostname'] = "Girdiğiniz IP Adresi için ana bilgisayar adı bulunamadı.";
$l['error_invalidthread'] = "Belirtilen konu mevcut değil.";
$l['error_invalidpost'] = "Belirtilen gönderi mevcut değil.";
$l['error_invalidannouncement'] = "Belirtilen duyuru mevcut değil.";
$l['error_invalidattachment'] = "Belirtilen ek dosya(lar) mevcut değil.";
$l['error_invalidforum'] = "Geçersiz Forum";
$l['error_closedinvalidforum'] = "Forum kapalı olduğu, başka bir web sayfasına yönlendirmeli olduğu veya bir kategori olduğu için bu forumda gönderi paylaşamazsınız.";
$l['error_attachtype'] = "Eklediğiniz dosya türüne izin verilmiyor. Lütfen, eki kaldırın veya farklı bir izin verilen dosya türü seçin.";
$l['error_attachsize'] = "\"{1}\" dosyası çok büyük. Bu dosya türü için maksimum boyut {2} kilobayttır.";
$l['error_uploadempty'] = "\"{1}\" dosyası boş.";
$l['error_uploadsize'] = "Yüklenen dosyanın boyutu çok büyük.";
$l['error_uploadfailed'] = "Dosya yükleme başarısız oldu. Lütfen geçerli bir dosya seçin ve tekrar deneyin. ";
$l['error_uploadfailed_detail'] = "Hata Ayrıntıları: ";
$l['error_uploadfailed_php1'] = "PHP Hatası: Yüklenen dosya php.ini'deki upload_max_filesize yönergesini aştı. Lütfen, bu hatayla ilgili olarak forum yöneticinizle iletişime geçin.";
$l['error_uploadfailed_php2'] = "Yüklenen dosya belirtilen maksimum dosya boyutunu aştı.";
$l['error_uploadfailed_php3'] = "Yüklenen dosya yalnızca kısmen yüklendi.";
$l['error_uploadfailed_php4'] = "Hiçbir dosya yüklenmedi.";
$l['error_uploadfailed_php6'] = "PHP Hatası: Geçici (temporary) klasörü eksik. Lütfen, bu hata için forum yöneticinizle iletişime geçin.";
$l['error_uploadfailed_php7'] = "PHP Hatası: Dosya diske yazılamadı.\"upload\" klasörünün CHMOD değerleri yazdırılabilir değerde olması gerekiyor. Lütfen, bu hatayla ilgili olarak forum yöneticinizle iletişime geçin. (CHMOD 777 Hatası)";
$l['error_uploadfailed_phpx'] = "PHP Hata Kodu: {1}.  Lütfen, bu hatayla ilgili olarak forum yöneticinizle iletişime geçin.";
$l['error_uploadfailed_nothingtomove'] = "Geçersiz bir dosya belirtildi, bu nedenle yüklenen dosya hedefine taşınamadı.";
$l['error_uploadfailed_movefailed'] = "Yüklenen dosyayı hedefine taşırken bir sorun oluştu.";
$l['error_uploadfailed_lost'] = "Ek Dosya(lar), bu sunucuda bulunamadı.";
$l['error_emailmismatch'] = "Girdiğiniz e-posta adresleri eşleşmiyor. Lütfen geri dönün ve tekrar deneyin.";
$l['error_nopassword'] = "Geçerli bir şifre girmediniz.";
$l['error_usernametaken'] = "Seçmiş olduğunuz kullanıcı adı forumda zaten kayıtlıdır. Bu foruma daha önce kaydolduysanız, lütfen <a href=\"member.php?action=login\">giriş yapın</a>.";
$l['error_nousername'] = "Bir kullanıcı adı girmediniz.";
$l['error_invalidusername'] = "Girmiş olduğunuz kullanıcı adı geçersiz.";
$l['error_invalidpassword'] = "Girdiğiniz şifre yanlış. Eğer şifrenizi unuttuysanız, lütfen <a href=\"member.php?action=lostpw\">buraya</a> tıklayın. Aksi takdirde, geri dönün ve tekrar deneyin.";
$l['error_postflooding'] = "Üzgünüz ancak gönderinizi işleme alamıyoruz. Forum Yönetimi, yalnızca {1} saniyede bir gönderi yapmanıza izin verildiğini belirtti.";
$l['error_nopermission_guest_1'] = "Giriş yapmadınız veya bu sayfayı görüntülemek için izniniz yok. Bunun nedeni aşağıdaki nedenlerden biri olabilir:";
$l['error_nopermission_guest_2'] = "Giriş yapmadınız veya kayıtlı değilsiniz. Lütfen giriş yapın ve istediğiniz işlemi tekrar deneyin.";
$l['error_nopermission_guest_3'] = "Bu sayfaya erişmek için izniniz yok. Yönetici sayfalarına veya erişmemeniz gereken bir kaynağa erişmeye mi çalışıyorsunuz?  Forum kurallarından bu eylemi gerçekleştirmenize izin verilip verilmediğini kontrol edin.";
$l['error_nopermission_guest_4'] = "Hesabınız bir yönetici tarafından devre dışı bırakılmış olabilir veya hesap etkinleştirilmeyi bekliyor olabilir.";
$l['error_nopermission_guest_5'] = "Bu sayfaya uygun formları veya bağlantıları kullanmak yerine doğrudan eriştiniz.";
$l['login'] = "Giriş Yap";
$l['need_reg'] = "Kayıt Ol";
$l['forgot_password'] = "Şifrenizi mi unuttunuz?";
$l['error_nopermission_user_1'] = "Bu sayfaya erişmek için izniniz yok. Bunun nedeni aşağıdaki nedenlerden biri olabilir:";
$l['error_nopermission_user_ajax'] = "Bu sayfaya erişmek için izniniz yok.";
$l['error_nopermission_user_2'] = "Hesabınız ya askıya alındı ya da bu kaynağa erişiminiz yasaklandı.";
$l['error_nopermission_user_3'] = "Bu sayfaya erişmek için izniniz yok. Yönetici sayfalarına veya erişmemeniz gereken bir kaynağa erişmeye mi çalışıyorsunuz? Forum kurallarından bu eylemi gerçekleştirmenize izin verilip verilmediğini kontrol edin.";
$l['error_nopermission_user_4'] = "Hesabınız hala etkinleştirilmeyi veya yönetici onayı bekliyor olabilir.";
$l['error_nopermission_user_5'] = "Bu sayfaya uygun formları veya bağlantıyı kullanmak yerine doğrudan eriştiniz.";
$l['error_nopermission_user_resendactivation'] = "Etkinleştirme Kodunu Yeniden Gönder";
$l['error_nopermission_user_username'] = "Şu anda '{1}' kullanıcı adı ile giriş yapmış durumdasınız.";
$l['logged_in_user'] = "Oturum Açan Kullanıcı";
$l['error_too_many_images'] = "Çok fazla görsel var.";
$l['error_too_many_images2'] = "Üzgünüz, ancak çok fazla görsel içerdiği için gönderinizi işleyemiyoruz. Devam etmek için lütfen gönderinizden bazı resimleri kaldırın.";
$l['error_too_many_images3'] = "<b>Not:</b> Gönderi başına izin verilen maksimum resim sayısı";
$l['error_attach_file'] = "Dosya Eklenirken Hata Oluştu";
$l['please_correct_errors'] = "Lütfen, devam etmeden önce aşağıdaki hataları düzeltin:";
$l['error_reachedattachquota'] = "Üzgünüz, ancak {1} ek dosya kotanıza / limitinize ulaştığınız için ek dosya yükleyemezsiniz.";
$l['error_maxattachpost'] = "Üzgünüz, ancak bu dosyayı ekleyemezsiniz çünkü {1} gönderi başına izin verilen maksimum ek sayısına ulaştınız.";
$l['error_invaliduser'] = "Belirtilen kullanıcı geçersizdir veya mevcut değildir.";
$l['error_invalidaction'] = "Geçersiz Eylem";
$l['error_messagelength'] = "Üzgünüz, mesajınız çok uzun ve gönderilemiyor.Lütfen, mesajınızı kısaltıp tekrar deneyin.";
$l['error_message_too_short'] = "Üzgünüz, mesajınız çok kısa ve gönderilemiyor.";
$l['failed_login_wait'] = "İzin verilen giriş denemesi hakkınız bitti. Tekrar giriş yapabilmeniz için şimdi {1} saat {2} dakika {3} saniye beklemeniz gerekmektedir.";
$l['failed_login_again'] = "<br />Sadece <strong>{1}</strong> giriş denemesi hakkınız kaldı.";
$l['error_max_emails_day'] = "'Konuyu Arkadaşına Gönder' veya 'Kullanıcıya E-Posta Gönder' özelliklerini kullanamazsınız çünkü son 24 saat içinde {1} mesaj gönderme kotanızı zaten kullandınız.";
$l['attachments_disabled'] = "Forum Yönetimi tarafından ek dosya sistemi kullanıma kapatıldığı için ek dosya sistemini kullanamazsınız.";
$l['error_attach_filename_length'] = "'{1}' dosya adı maksimum dosya adı uzunluğu {2} değerini aşıyor. Lütfen, daha kısa dosya adına sahip bir dosya yükleyin veya dosya adını kısaltın.";

$l['emailsubject_lostpw'] = "Şifre Sıfırlama {1}";
$l['emailsubject_passwordreset'] = "Yenilenen Yeni Şifre {1}";
$l['emailsubject_subscription'] = "Yeni Yanıt {1}";
$l['emailsubject_randompassword'] = "{1} İçin Şifreniz";
$l['emailsubject_activateaccount'] = "Hesap Etkinleştirme {1}";
$l['emailsubject_forumsubscription'] = "{1}'de Yeni Konu";
$l['emailsubject_reportpost'] = "Rapor Edilen Gönderi {1}";
$l['emailsubject_reportprofile'] = "{1} Rapor Edilen Kullanıcı";
$l['emailsubject_reportreputation'] = "{1} Rapor Edilen Rep Puanı";
$l['emailsubject_reachedpmquota'] = "Özel Mesajlaşma Kotasına {1} Seviyesine Ulaştı";
$l['emailsubject_changeemail'] = "{1} E-Posta Değişikliği";
$l['emailsubject_newpm'] = "{1} - {2} Yeni Özel Mesaj";
$l['emailsubject_newjoinrequest'] = "{1} Yeni Üye Kaydı";
$l['emailsubject_sendtofriend'] = "Bu Konuya Bakmalısın: {1}";
$l['emailsubject_changepassword'] = "{1} Şifre Değişikliği";
$l['emailbit_viewthread'] = "... (daha fazlasını okumak için konuyu ziyaret edin...)";

$l['email_lostpw'] = "{1},

Hesap şifrenizi {2} adresinde sıfırlama aşamasını tamamlamak için web tarayıcınızda aşağıdaki URL'ye gitmeniz gerekiyor.

{3}/member.php?action=resetpassword&uid={4}&code={5}

Yukarıdaki bağlantı düzgün çalışmazsa, şu adrese gidin

{3}/member.php?action=resetpassword

Aşağıdaki bilgileri girmeniz gerekecektir:
Kullanıcı Adı: {1}
Aktivasyon Kodu: {5}

Saygılarımızla,
{2} » Yönetimi";
$l['email_lostpw1'] = "{1},

Hesap şifrenizi {2} adresinde sıfırlama aşamasını tamamlamak için web tarayıcınızda aşağıdaki URL'ye gitmeniz gerekiyor.

{3}/member.php?action=resetpassword&uid={4}&code={5}

Yukarıdaki bağlantı düzgün çalışmazsa, şu adrese gidin

{3}/member.php?action=resetpassword

Aşağıdaki bilgileri girmeniz gerekecektir:
E-posta adresiniz
Aktivasyon Kodu: {5}

Saygılarımızla,
{2} » Yönetimi";
$l['email_lostpw2'] = "{1},

Hesap şifrenizi {2} adresinde sıfırlama aşamasını tamamlamak için web tarayıcınızda aşağıdaki URL'ye gitmeniz gerekiyor.

{3}/member.php?action=resetpassword&uid={4}&code={5}

Yukarıdaki bağlantı düzgün çalışmazsa, şu adrese gidin

{3}/member.php?action=resetpassword

Aşağıdaki bilgileri girmeniz gerekecektir:
Kullanıcı Adı: {1} (Veya Kayıtlı E-Posta Adresiniz)
Aktivasyon Kodu: {5}

Saygılarımızla,
{2} » Yönetimi";

$l['email_reportpost'] = "Merhaba {1}, {2} adlı kullanıcı aşağıda ki konuyu rapor etti.

Rapor edilen konu:
{3}
{4}/{5}

Bu kullanıcının bu gönderiyi bildirmek için verdiği neden (Rapor Etme Sebebi):
{6}

Bu mesaj, bu forumun tüm moderatörlerine veya moderatör yoksa tüm yöneticilere ve süper moderatörlere gönderilmiştir.

Lütfen, bu gönderiyi mümkün olan en kısa sürede kontrol edin.";

$l['email_reportprofile'] = "Merhaba {1}, {2} adlı kullanıcı aşağıda ki kullanıcıyı / profili rapor etti.

Rapor edilen kullanıcı/profil:
{3}
{4}/{5}

Bu kullanıcının bu kullanıcıyı bildirmek için verdiği neden (Rapor Etme Sebebi):
{6}

Bu mesaj, bu forumun tüm moderatörlerine veya moderatör yoksa tüm yöneticilere ve süper moderatörlere gönderilmiştir.

Lütfen, bu kullanıcıyı / profili mümkün olan en kısa sürede kontrol edin.";

$l['email_reportreputation'] = "Merhaba {1},  {2} bir rep puanı bildirimini rapor etti.

Kural dışı olduğu düşünülen rep puanı bildirimi:
{3}
{4}/{5}

Bu kullanıcının bu rep puanını bildirmek için verdiği neden (Rapor Etme Sebebi):
{6}

Bu mesaj, bu forumun tüm moderatörlerine veya moderatör yoksa tüm yöneticilere ve süper moderatörlere gönderilmiştir.

Lütfen, bu rep puanı bildirimini mümkün olan en kısa sürede kontrol edin.";

$l['email_report_comment_extra'] = "{1}: {2}";

$l['email_passwordreset'] = "Merhaba {1},

{2} adresindeki şifreniz sıfırlandı.

Yeni şifreniz: {3}

Foruma giriş yapmak için bu şifreye ihtiyacınız olacak, giriş yaptıktan sonra 'Kullanıcı Kontrol Panelinize' giderek 'Şifre Değiştir' menüsünden şifrenizi değiştirmelisiniz.

Saygılarımızla,
{2} » Yönetimi";

$l['email_randompassword'] = "Merhaba {1},

{2} adlı sitemize kaydolduğunuz için teşekkür ederiz. Kullanıcı adınız ve rastgele oluşturulan şifreniz aşağıdadır. {2} forum sitesine giriş yapmak için bu bilgilere ihtiyacınız olacak.

Kullanıcı Adı: {3}
Şifreniz: {4}

Giriş yaptıktan hemen sonra şifrenizi değiştirmeniz tavsiye ediyoruz. Bunu Kullanıcı KP'nize gidip sol menüdeki 'Şifre Değiştir' seçeneğine tıklayarak yapabilirsiniz.

Saygılarımızla,
{2} » Yönetimi";

$l['email_randompassword1'] = "Merhaba {1},

{2} adlı sitemize kaydolduğunuz için teşekkür ederiz. Kullanıcı adınız ve rastgele oluşturulan şifreniz aşağıdadır. {2} forum sitesine giriş yapmak için bu bilgilere ihtiyacınız olacak.

E-posta Adresiniz
Şifreniz: {4}

Giriş yaptıktan hemen sonra şifrenizi değiştirmeniz tavsiye ediyoruz. Bunu Kullanıcı KP'nize gidip sol menüdeki 'Şifre Değiştir' seçeneğine tıklayarak yapabilirsiniz.

Saygılarımızla,
{2} » Yönetimi";

$l['email_randompassword2'] = "Merhaba {1},

{2} adlı sitemize kaydolduğunuz için teşekkür ederiz. Kullanıcı adınız ve rastgele oluşturulan şifreniz aşağıdadır. {2} forum sitesine giriş yapmak için bu bilgilere ihtiyacınız olacak.

Kullanıcı Adı: {3} (Veya E-Posta Adresiniz)
Şifreniz: {4}

Giriş yaptıktan hemen sonra şifrenizi değiştirmeniz tavsiye ediyoruz. Bunu Kullanıcı KP'nize gidip sol menüdeki 'Şifre Değiştir' seçeneğine tıklayarak yapabilirsiniz.

Saygılarımızla,
{2} » Yönetimi";

$l['email_sendtofriend'] = "Merhaba {1},

{2} sitesinde paylaşılan aşağıda ki içeriğin ilginizi çekeceğini düşündüm. Bir göz atmak ister misin? 

İlgili Konu: {3}

{1}, bu mesajı almanızın sebebi aşağıda belirtilmiştir.

------------------------------------------
{4}
------------------------------------------

Saygılarımızla,
{2} » Yönetimi";

$l['email_forumsubscription'] = "Merhaba {1},

{2} az önce {3} adresinde yeni bir konu başlattı. Bu yeni konu takip ettiğiniz / abone olduğunuz {4} forumda paylaşılmıştır.

Konu Başlığı: {5}

İşte konudan kısa bir alıntı:
--
{6}
--

Konuyu görüntülemek için aşağıdaki URL'ye gidebilirsiniz:
{7}/{8}

Başka yeni konu başlıkları ve yanıtlar da olabilir, ancak bu forumu tekrar ziyaret edene kadar başka bir bildirim almayacaksınız.

Saygılarımızla,
{3} » Yönetimi

------------------------------------------
Abonelikten / Takipten Nasıl Çıkabilirim?

Bu forumdaki yeni konularla ilgili daha fazla bildirim almak istemiyorsanız, tarayıcınızda aşağıdaki URL'yi ziyaret edin:
{7}/usercp.php?action=removesubscription&type=forum&fid={9}

------------------------------------------";

$l['email_activateaccount'] = "Merhaba {1},

{2} adresinde ki kayıt işlemini tamamlamak için web tarayıcınızda aşağıdaki URL'ye gitmeniz gerekecektir.

{3}/member.php?action=activate&uid={4}&code={5}

Yukarıdaki bağlantı düzgün çalışmazsa, şu adrese gidin

{3}/member.php?action=activate

Aşağıdaki bilgileri girmeniz gerekecektir:
Kullanıcı Adı: {1}
Aktivasyon Kodu: {5}

Saygılarımızla,
{2} » Yönetimi";

$l['email_activateaccount1'] = "Merhaba {1},

{2} adresinde ki kayıt işlemini tamamlamak için web tarayıcınızda aşağıdaki URL'ye gitmeniz gerekecektir.

{3}/member.php?action=activate&uid={4}&code={5}

Yukarıdaki bağlantı düzgün çalışmazsa, şu adrese gidin

{3}/member.php?action=activate

Aşağıdaki bilgileri girmeniz gerekecektir:
E-Posta Adresiniz
Aktivasyon Kodu: {5}

Saygılarımızla,
{2} » Yönetimi";

$l['email_activateaccount2'] = "Merhaba {1},

{2} adresinde ki kayıt işlemini tamamlamak için web tarayıcınızda aşağıdaki URL'ye gitmeniz gerekecektir.

{3}/member.php?action=activate&uid={4}&code={5}

Yukarıdaki bağlantı düzgün çalışmazsa, şu adrese gidin

{3}/member.php?action=activate

Aşağıdaki bilgileri girmeniz gerekecektir:
Kullanıcı Adı: {1} (Veya e-posta adresiniz)
Aktivasyon Kodu: {5}

Saygılarımızla,
{2} » Yönetimi";

$l['email_subscription'] = "Merhaba {1},

{2} az önce {3} adresinde abone olduğunuz bir konuya yanıt verdi. 

Yanıtın yapıldığı konu başlığı: {4}

İşte ilgili yanıttan kısa bir alıntı / özet:
------------------------------------------
{5}
------------------------------------------

Konuyu görüntülemek için aşağıdaki URL'ye gidebilirsiniz:
{6}/{7}

Bu konuya başka yanıtlar da gelmiş olabilir, ancak konuyu/forumu tekrar ziyaret edene kadar başka bir bildirim almayacaksınız.

Saygılarımızla,
{3} » Yönetimi

------------------------------------------
Abonelikten / Takipten Nasıl Çıkabilirim?

Takip ettiğiniz konularla ilgili daha fazla bildirim almak istemiyorsanız, tarayıcınızda aşağıdaki URL'yi ziyaret edin:
{6}/usercp.php?action=removesubscription&tid={8}

------------------------------------------";
$l['email_reachedpmquota'] = "Merhaba {1},

Bu, Özel Mesajlaşma sisteminde ki Gelen Mesajlar Kutunuzun kapasitesine ulaştığını bildirmek için {2} tarafından gönderilen otomatik bir e-postadır.

Bir veya daha fazla kullanıcı size özel mesaj göndermeye çalışmış ve bu nedenle başarısız olmuş olabilir.

Lütfen şu anda sakladığınız bazı özel mesajlarınızı silin ve sildiğiniz mesajları 'Çöp Kutusundan' da silmeyi unutmayın.Çöp kutusunda duran mesajlarınızda özel mesaj kotası sınırları içerisinde sayılmaktadır.

Saygılarımızla,
{2} » Yönetimi
{3}";

$l['email_changeemail'] = "Merhaba {1},

E-posta adresinizi değiştirmek için {2} tarihinde bir değiştirme talebi aldık (aşağıdaki ayrıntılara bakın).

Eski E-posta Adresi: {3}
Yeni E-posta Adresi: {4}

Bu değişiklikler doğruysa, lütfen web tarayıcınızda aşağıdaki URL'ye giderek {2} üzerinde doğrulama işlemini tamamlayın.

{5}/member.php?action=activate&uid={8}&code={6}

Yukarıdaki bağlantı düzgün çalışmazsa, şu adrese gidin

{5}/member.php?action=activate

Aşağıdaki bilgileri girmeniz gerekecektir:
Kullanıcı Adı: {7}
Aktivasyon Kodu: {6}

Yeni e-posta adresinizi doğrulamamayı seçerseniz profiliniz güncellenmeyecek ve mevcut e-posta adresiniz kullanılmaya devam edecektir.

Saygılarımızla,
{2} » Yönetimi
{5}";

$l['email_changeemail_noactivation'] = "Merhaba {1},

E-posta adresinizi değiştirmek için {2} tarihinde bir talep aldık (aşağıdaki ayrıntılara bakın).

Eski E-posta Adresi: {3}
Yeni E-posta Adresi: {4}

Bu değişiklik otomatik olarak işlenmiştir. Bu değişikliği talep etmediyseniz, lütfen hemen bir 'Forum Yöneticisi' ile iletişime geçin.

Saygılarımızla,
{2} » Yönetimi
{5}";

$l['email_changepassword'] = "Merhaba {1},

Bu e-postayı almanızın nedeni, sizin veya şifrenize erişimi olan birinin kısa süre önce hesap şifrenizi değiştirmiş olmasıdır.

Kullanıcı Adı: {1}
E-Posta Adresi: {2}

Bu değişikliği siz talep etmediyseniz, lütfen hemen bir 'Forum Yöneticisi' ile iletişime geçin.

Saygılarımızla,
{3} » Yönetimi
{4}";

$l['email_newpm'] = "Merhaba {1},

{3} sitesinden yeni bir özel mesaj aldınız. Size {2} kullanıcısı tarafından özel mesaj gönderildi.

Gönderilen özel mesajın içeriğinin bir kısmını aşağıdan görebilirsiniz.
------------------------------------------
{5}
------------------------------------------

Bu mesajı görüntülemek, yanıtlamak veya bir başkasına iletmek için bu bağlantıyı takip edebilirsiniz:

{4}/private.php

Lütfen {3} adresini ziyaret edene kadar yeni mesajlarla ilgili başka bildirim almayacağınızı unutmayın.

Hesap Ayarları sayfanızdan yeni mesaj bildirimlerini devre dışı bırakabilirsiniz:

{4}/usercp.php?action=options

Saygılarımızla,
{3} » Yönetimi
{4}";

$l['email_emailuser'] = "Merhaba {1},

{3} sitesinden yeni bir özel mesaj aldınız. Size {2} kullanıcısı tarafından özel mesaj gönderildi.

Gönderilen özel mesajın içeriğinin bir kısmını aşağıdan görebilirsiniz.
------------------------------------------
{5}
------------------------------------------

Saygılarımızla,
{3} » Yönetimi
{4}

------------------------------------------
Diğer üyelerden e-posta mesajları almak istemiyor musunuz?

Diğer üyelerin size e-posta göndermesini istemiyorsanız lütfen 'Kullanıcı Kontrol Panelinizi' ziyaret edin ve 'E-Postamı Diğer Üyelerden Gizle' seçeneğini etkinleştirin:
{4}/usercp.php?action=options

------------------------------------------";

$l['email_groupleader_joinrequest'] = "Merhaba {1},

{2} adresinden, {3} adlı kullanıcı {4} grubuna katılma talebinde bulundu. (Detaylar aşağıdadır.)

İstek Sebebi: {5}

Bu talebi kabul etmek veya reddetmek için şu adrese gidin

{6}/managegroup.php?gid={7}

Saygılarımızla,
{4} » Yönetimi";

$l['email_contact_subject'] = "İletişim: {1}";
$l['email_contact'] = "E-Posta: {1}
Forum Profili: {2}
IP Adresi: {3}
Mesaj:
{4}";

$l['pmsubject_subscription'] = "{1} - Yeni Yanıt(lar) Mevcut!";
$l['pm_subscription'] = "Merhaba {1},

{2} az önce abone olduğunuz / takip ettiğiniz bir konuya yanıt verdi. İlgili konu: {3}

Abone olduğunuz / takip ettiğiniz konuya gelen yeni mesajdan kısa bir alıntı:
------------------------------------------
{4}
------------------------------------------

Konuyu görüntülemek için aşağıdaki URL'ye gidebilirsiniz:
[url]{5}/{6}[/url]

Bu başlığa başka yanıtlar da gelmiş olabilir, ancak başlığı tekrar ziyaret edene kadar bu konudan başka bir bildirim almayacaksınız.

------------------------------------------
Abonelikten / Takipten Nasıl Çıkabilirim?

Bu konuya verilen yanıtlarla ilgili daha fazla bildirim almak istemiyorsanız, tarayıcınızda aşağıdaki URL'yi ziyaret edin:
[url]{5}/usercp.php?action=removesubscription&tid={7}[/url]

------------------------------------------";

$l['email_broken_task_subject'] = "{1} - MyBB Görev Hatası Oluştu!";
$l['email_broken_task'] = "

{1} sitesinde çalışan ({2}) MyBB görev aracından bir hata meydana geldi.

Eksik veya hataya sebep olan görev dosyası: {3}

Siz bu sorunu çözene kadar MyBB tarafından ilgili görev aracı devre dışı bırakılmıştır.";
