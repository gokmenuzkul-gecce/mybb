<?php
/**
 * ACP: which icon and colour each forum row will render.
 *
 * The icon is derived from the forum name at render time, so the only thing an
 * admin can usefully see here is the resulting match: it makes an icon that
 * fell through to the generic fallback obvious, and shows which keyword in the
 * name won.
 */
if(!defined('IN_MYBB'))
{
	die('Direct initialization of this file is not allowed.');
}

$page->add_breadcrumb_item('Forum Kartları', 'index.php?module=crypto_forumcards');
$page->add_breadcrumb_item('İkon Eşleşmeleri', 'index.php?module=crypto_forumcards-icons');

$page->output_header('Forum İkon Eşleşmeleri');

$table = new Table;
$table->construct_header('Forum');
$table->construct_header('İkon', array('class' => 'align_center', 'width' => '10%'));
$table->construct_header('Renk', array('class' => 'align_center', 'width' => '12%'));
$table->construct_header('Eşleşme', array('class' => 'align_center', 'width' => '14%'));

$query = $db->simple_select('forums', 'fid, name, type, parentlist', '', array('order_by' => 'disporder, name'));

$count = 0;
$fallback = 0;
while($forum = $db->fetch_array($query))
{
	$count++;
	list($icon, $color) = crypto_forumcards_lookup($forum['name']);
	$is_fallback = ($icon === 'fa-solid fa-comments');

	if($is_fallback)
	{
		$fallback++;
	}

	$type = ($forum['type'] == 'c') ? '<span style="color:#64748b;">kategori</span>' : 'forum';

	$table->construct_cell(
		'<strong>'.htmlspecialchars_uni($forum['name']).'</strong><br /><small>fid: '.(int)$forum['fid'].' &middot; '.$type.'</small>'
	);
	$table->construct_cell('<i class="'.htmlspecialchars_uni($icon).'" aria-hidden="true"></i><br /><small>'.htmlspecialchars_uni($icon).'</small>', array('class' => 'align_center'));
	$table->construct_cell('<code>'.htmlspecialchars_uni($color).'</code><br /><span style="display:inline-block;width:14px;height:14px;border-radius:3px;background:'.htmlspecialchars_uni($color).';vertical-align:middle;"></span>', array('class' => 'align_center'));
	$table->construct_cell($is_fallback ? '<span style="color:#f59e0b;font-weight:600;">genel</span>' : '<span style="color:#22c55e;font-weight:600;">özel</span>', array('class' => 'align_center'));
	$table->construct_row();
}

if($count == 0)
{
	$table->construct_cell('Kayıtlı forum yok.', array('colspan' => 4, 'class' => 'align_center'));
	$table->construct_row();
}

$table->output('İkon Eşleşmeleri');

$table = new Table;
$table->construct_cell(
	'<p>İkon ve renk, forum adındaki anahtar kelimeden türetilir (<code>crypto_forumcards_lookup()</code>). '
	.'Adında tanınan bir kelime bulunamayan forumlar genel ikonu alır: şu anda <strong>'.$fallback.'</strong> forum genel ikonda.</p>'
	.'<p class="smalltext">İkonu değiştirmek için forum adını düzenleyin veya <code>inc/plugins/crypto_forumcards.php</code> içindeki kural listesine yeni bir anahtar kelime ekleyin.</p>',
	array('colspan' => 1)
);
$table->construct_row();
$table->output('Nasıl Çalışır');

$page->output_footer();