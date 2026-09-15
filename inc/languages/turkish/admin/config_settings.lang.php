<?php
/**
 * MyBB 1.8 English Language Pack
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 */

$l['board_settings'] = "Forum Ayarları";
$l['change_settings'] = "Ayarları Değiştir";
$l['change_settings_desc'] = "Bu bölüm forumunuzla ilgili tüm ayarları yönetmenizi sağlar. Başlamak için aşağıdan bir ayar grubu seçin.";
$l['add_new_setting'] = "Yeni Ayar Ekle";
$l['add_new_setting_desc'] = "Bu bölüm foruma yeni bir ayar eklemenizi sağlar.";
$l['modify_existing_settings'] = "Ayarları Düzenle";
$l['modify_existing_settings_desc'] = "Bu bölüm mevcut forum ayarlarını düzenlemenizi sağlar.";
$l['add_new_setting_group'] = "Yeni Ayar Grubu Ekle";
$l['add_new_setting_group_desc'] = "Bu bölüm ayarları kategorilere ayırmak için yeni bir ayar grubu oluşturmanızı sağlar.";
$l['edit_setting_group'] = "Ayar Grubunu Düzenle";
$l['edit_setting_group_desc'] = "Bu bölüm mevcut bir ayar grubunu düzenlemenizi sağlar.";

$l['title'] = "Başlık";
$l['description'] = "Açıklama";
$l['group'] = "Grup";
$l['display_order'] = "Görüntüleme Sırası";
$l['name'] = "Tanımlayıcı";
$l['name_desc'] = "This unique identifier is used in the settings array to reference this setting (in scripts, translations, and templates).";
$l['group_name_desc'] = "This unique identifier is used for the translation system.";
$l['text'] = "Metin";
$l['numeric_text'] = "Sayısal Metin";
$l['textarea'] = "Metin Alanı";
$l['yesno'] = "Evet / Hayır Seçimi";
$l['onoff'] = "Açık / Kapalı Seçimi";
$l['select'] = "Seçim Kutusu";
$l['radio'] = "Radio Buttons";
$l['checkbox'] = "Checkboxes";
$l['language_selection_box'] = "Language Selection Box";
$l['forum_selection_box'] = "Forum Selection Box";
$l['forum_selection_single'] = "Single Forum Selection Box";
$l['group_selection_box'] = "Group Selection Box";
$l['group_selection_single'] = "Single Group Selection Box";
$l['adminlanguage'] = "Administration Language Selection Box";
$l['cpstyle'] = "Yönetim Paneli Style Selection Box";
$l['prefix_selection_box'] = "Prefix Selection Box";
$l['php'] = "Evaluated PHP";
$l['type'] = "Tür";
$l['extra'] = "Extra";
$l['extra_desc'] = "If this setting is a select, radio or check box enter a key paired (key=Item) list of items to show. Separate items with a new line. If PHP, enter the PHP to be evaluated.";
$l['value'] = "Değer";
$l['insert_new_setting'] = "Insert New Setting";
$l['edit_setting'] = "Edit Setting";
$l['delete_setting'] = "Sil Setting";
$l['setting_configuration'] = "Setting Configuration";
$l['update_setting'] = "Güncelle Setting";
$l['save_settings'] = "Kaydet Settings";
$l['setting_groups'] = "Ayar Grupları";
$l['bbsettings'] = "Ayarlar";
$l['insert_new_setting_group'] = "Insert New Setting Group";
$l['setting_group_setting'] = "Setting Group / Setting";
$l['order'] = "Order";
$l['delete_setting_group'] = "Sil Setting Group";
$l['save_display_orders'] = "Kaydet Display Orders";
$l['update_setting_group'] = "Güncelle Setting Group";
$l['modify_setting'] = "Modify Setting";
$l['search'] = "Ara";
$l['plugin_settings'] = "Eklenti Ayarları";

$l['show_all_settings'] = "Tüm Ayarları Göster";
$l['settings_search'] = "Ayarlar İçinde Ara";

$l['confirm_setting_group_deletion'] = "Are you sure you wish to delete this setting group?";
$l['confirm_setting_deletion'] = "Are you sure you wish to delete this setting?";

$l['error_format_dimension'] = "Defined {1} format is invalid.";
$l['error_field_minnamelength'] = "Minimum name length can not be larger than maximum name length";
$l['error_field_minpasswordlength'] = "Minimum password length can not be larger than maximum password length";
$l['error_field_minpasswordlength_complex'] = "Minimum password length can not be less than 3 when using complex passwords";
$l['error_field_postmaxavatarsize'] = "Maximum Avatar Dimensions";
$l['error_field_useravatardims'] = "Default Avatar Dimensions";
$l['error_field_maxavatardims'] = "Maximum Avatar Dimensions";
$l['error_field_memberlistmaxavatarsize'] = "Maximum Display Avatar Dimensions";
$l['error_missing_title'] = "You did not enter a title for this setting";
$l['error_missing_group_title'] = "You did not enter a title for this setting group";
$l['error_invalid_gid'] = "You did not select a valid group to place this setting in";
$l['error_invalid_gid2'] = "You have followed a link to an invalid setting group. Please ensure it exists.";
$l['error_missing_name'] = "You did not enter an identifier for this setting";
$l['error_missing_group_name'] = "You did not enter an identifier for this setting group";
$l['error_invalid_type'] = "You did not select a valid type for this setting";
$l['error_invalid_sid'] = "The specified setting does not exist";
$l['error_duplicate_name'] = "The identifier specified has already been used for the \"{1}\" setting -- it must be unique";
$l['error_duplicate_group_name'] = "The identifier specified has already been used for the \"{1}\" setting group -- it must be unique";
$l['error_no_settings_found'] = "No settings were found for the specified search phrase.";
$l['error_cannot_edit_default'] = "Default settings and groups may not be edited or removed.";
$l['error_cannot_edit_php'] = "This is a special type of setting which cannot be edited.";
$l['error_ajax_search'] = "There was a problem searching for settings:";
$l['error_ajax_unknown'] = "An unknown error occurred while searching for settings.";
$l['error_chmod_settings_file'] = "The settings file \"./inc/settings.php\" isn't writable. Please CHMOD to 666.<br />For more information on CHMODing, see the <a href=\"https://docs.mybb.com/1.8/administration/security/file-permissions\" target=\"_blank\" rel=\"noopener\">MyBB Docs</a>.";
$l['error_admin_email_settings_empty'] = '<div class="smalltext" style="font-weight: normal;">Please note that the <strong>Admin Email</strong> was not saved as the setting can not be empty, and must be a valid email address.</div>';

$l['success_setting_added'] = "The setting has been created successfully.";
$l['success_setting_updated'] = "The setting has been updated successfully.";
$l['success_setting_deleted'] = "The selected setting has been deleted successfully.";
$l['success_settings_updated'] = "The settings have been updated successfully.";
$l['success_settings_updated_hiddencaptchaimage'] = '<div class="smalltext" style="font-weight: normal;">Please note that the <strong>Hidden CAPTCHA field</strong> setting was reverted to <strong>{1}</strong> due to a conflict with the <strong>{2}</strong> field in the registration form.</div>';
$l['success_settings_updated_username_method_conflict'] = '<div class="smalltext" style="font-weight: normal;">Please note that the <b>Allowed Login Methods</b> setting was not updated due to emails to be registered multiple times is allowed currently.</div>';
$l['success_settings_updated_username_method'] = '<div class="smalltext" style="font-weight: normal;">Please note that the <b>Allowed Login Methods</b> setting was not updated due to multiple users using the same e-mail address at this time.</div>';
$l['success_settings_updated_allowmultipleemails'] = '<div class="smalltext" style="font-weight: normal;">Please note that the <b>Allow emails to be registered multiple times?</b> setting can\'t be enabled because the <b>Allowed Login Methods</b> setting allows users to login by e-mail address.</div>';
$l['success_settings_updated_captchaimage'] = '<div class="smalltext" style="font-weight: normal;">Please note that the <strong>CAPTCHA Images for Registration &amp; Posting</strong> setting was reverted to <strong>MyBB Default Captcha</strong> due to the lack of public/private key(s).</div>';
$l['success_settings_updated_minsearchword'] = '<div class="smalltext" style="font-weight: normal;">Please note that the <strong>Minimum Search Word Length</strong> setting was set to match the database system configuration.</div>';
$l['success_display_orders_updated'] = "The setting display orders have been updated successfully.";
$l['success_setting_group_added'] = "The setting group has been created successfully.";
$l['success_setting_group_updated'] = "The setting group has been updated successfully.";
$l['success_setting_group_deleted'] = "The selected setting group has been deleted successfully.";
$l['success_duplicate_settings_deleted'] = "All duplicate setting groups have been deleted successfully.";

$l['searching'] = 'Searching&hellip;';
$l['search_error'] = 'There was an error fetching your search results:';
$l['search_done'] = 'Done!';

