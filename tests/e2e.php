<?php
require __DIR__ . '/../../_oc-test-harness/lib.php';

$db->exec("SET SESSION sql_mode = ''");

function meta_tags(string $html): array {
	$tags = [];
	preg_match_all('#<meta (name|property)="([^"]+)" content="([^"]*)"/>#', $html, $m, PREG_SET_ORDER);
	foreach ($m as $t) { $tags[$t[2]] = ['type' => $t[1], 'content' => $t[3]]; }
	return $tags;
}
function settings(array $o = []): array {
	return $o + ['module_seo_structured_data_status' => 1, 'module_seo_structured_data_fb_app_id' => '123456', 'module_seo_structured_data_twitter_creator' => 'modulegarage', 'module_seo_structured_data_width' => '300', 'module_seo_structured_data_height' => '250'];
}
function save(Client $a, string $t, array $s): array {
	global $CODE;
	return $a->post('/admin/index.php?route=extension/' . $CODE . '/module/' . $CODE . '.save&user_token=' . $t, $s);
}

echo "SEO Structured Data end to end test (OpenCart $OC_VERSION)\n";
[$admin, $token] = admin_login();

if (wanted('install')) {
	section('1. Install');
	install_package($admin, $token);
	check('event for the header is registered', (int)sql_value("SELECT COUNT(*) FROM oc_event WHERE code = 'seo_structured_data' AND `trigger` = 'catalog/view/common/header/after' AND status = 1") == 1);
}

if (wanted('settings')) {
	section('2. Settings');
	$p = $admin->get('/admin/index.php?route=extension/' . $CODE . '/module/' . $CODE . '&user_token=' . $token);
	check('settings page renders', $p['status'] == 200 && strpos($p['body'], 'id="form-seo-structured-data"') !== false, short($p['body']));

	$r = save($admin, $token, settings(['module_seo_structured_data_twitter_creator' => 'bad name!', 'module_seo_structured_data_width' => '-5', 'module_seo_structured_data_height' => 'abc']));
	check('invalid twitter username / sizes are refused', isset($r['json']['error']['twitter_creator'], $r['json']['error']['width'], $r['json']['error']['height']), short($r['body']));

	$r = save($admin, $token, settings(['module_seo_structured_data_twitter_creator' => '  @modulegarage ']));
	check('valid settings are saved', !empty($r['json']['success']), short($r['body']));
	check('leading @ and spaces are removed from the twitter username', sql_value("SELECT `value` FROM oc_setting WHERE `key` = 'module_seo_structured_data_twitter_creator'") === 'modulegarage');

	$p = $admin->get('/admin/index.php?route=extension/' . $CODE . '/module/' . $CODE . '&user_token=' . $token);
	check('settings page shows the saved values', strpos($p['body'], 'value="123456"') !== false && strpos($p['body'], 'value="300"') !== false);

	// no modify permission
	sql("DELETE FROM oc_user WHERE username = 'viewer'");
	sql("INSERT INTO oc_user_group SET name = 'Viewer', permission = ?", [json_encode(['access' => ['extension/seo_structured_data/module/seo_structured_data'], 'modify' => []])]);
	$gid = (int)sql_value("SELECT MAX(user_group_id) FROM oc_user_group");
	sql("INSERT INTO oc_user SET user_group_id = ?, username = 'viewer', password = ?, firstname = 'V', lastname = 'O', email = 'v@example.com', image = '', ip = '', status = 1, date_added = NOW()", [$gid, password_hash('viewer123', PASSWORD_DEFAULT)]);
	[$viewer, $vt] = admin_login('viewer', 'viewer123');
	$r = save($viewer, $vt, settings(['module_seo_structured_data_fb_app_id' => 'hacked']));
	check('saving without modify permission is refused', !empty($r['json']['error']['warning']) && sql_value("SELECT `value` FROM oc_setting WHERE `key` = 'module_seo_structured_data_fb_app_id'") === '123456', short($r['body']));
}

if (wanted('front')) {
	section('3. Storefront');
	save($admin, $token, settings());

	// test data: a product with an image and a tricky description, a category, a manufacturer
	@mkdir($OC_DIR . '/image/catalog', 0777, true);
	$img = imagecreatetruecolor(600, 400); imagepng($img, $OC_DIR . '/image/catalog/seo-test.png');
	sql("UPDATE oc_product SET image = 'catalog/seo-test.png', manufacturer_id = 0 WHERE product_id = 100");
	sql("UPDATE oc_product_description SET meta_description = ?, name = ? WHERE product_id = 100", ['He said "hi" & left <b>now</b>', "D&#039;Arcy's product"]);
	sql("DELETE FROM oc_category WHERE category_id = 900"); sql("DELETE FROM oc_category_description WHERE category_id = 900"); sql("DELETE FROM oc_category_to_store WHERE category_id = 900"); sql("DELETE FROM oc_category_path WHERE category_id = 900");
	insert_row('oc_category', ['category_id' => 900, 'image' => 'catalog/seo-test.png', 'parent_id' => 0, 'top' => 1, 'column' => 1, 'sort_order' => 1, 'status' => 1, 'date_added' => date('Y-m-d H:i:s'), 'date_modified' => date('Y-m-d H:i:s')]);
	insert_row('oc_category_description', ['category_id' => 900, 'language_id' => 1, 'name' => 'SEO Category', 'description' => '', 'meta_title' => 'SEO Category', 'meta_description' => 'Category description', 'meta_keyword' => '']);
	sql("INSERT INTO oc_category_to_store SET category_id = 900, store_id = 0");
	sql("INSERT INTO oc_category_path SET category_id = 900, path_id = 900, level = 0");
	sql("DELETE FROM oc_manufacturer WHERE manufacturer_id = 900"); sql("DELETE FROM oc_manufacturer_to_store WHERE manufacturer_id = 900");
	insert_row('oc_manufacturer', ['manufacturer_id' => 900, 'name' => 'SEO Brand', 'image' => '', 'sort_order' => 0]);
	sql("INSERT INTO oc_manufacturer_to_store SET manufacturer_id = 900, store_id = 0");
	foreach (glob($OC_DIR . '/system/storage/cache/*') as $f) { @unlink($f); }

	$c = new_shopper();
	$home = $c->get('/index.php?route=common/home&language=en-gb');
	$t = meta_tags($home['body']);
	check('home: Open Graph and Twitter tags are in the head', isset($t['og:title'], $t['og:url'], $t['twitter:card'], $t['twitter:title']), short($home['body'], 150));
	check('home: og:type is website', ($t['og:type']['content'] ?? '') === 'website');
	check('home: facebook app id and twitter creator are added', ($t['fb:app_id']['content'] ?? '') === '123456' && ($t['twitter:creator']['content'] ?? '') === '@modulegarage' && ($t['twitter:site']['content'] ?? '') === '@modulegarage');
	check('home: tags are inside <head>', (bool)preg_match('#<head>.*og:title.*</head>#s', $home['body']));
	check('home: exactly one set of tags (no duplicates)', substr_count($home['body'], 'property="og:title"') == 1);

	$p = $c->get('/index.php?route=product/product&product_id=100&language=en-gb');
	$t = meta_tags($p['body']);
	check('product: title, url, description and image tags', isset($t['og:title'], $t['og:url'], $t['og:description'], $t['og:image'], $t['twitter:image']), short($p['body'], 150));
	check('product: og:type is product', ($t['og:type']['content'] ?? '') === 'product');
	check('product: image is resized to the configured size and reachable', ($t['og:image:width']['content'] ?? '') === '300' && ($t['og:image:height']['content'] ?? '') === '250' && $c->get($t['og:image']['content'] ?? '/nope')['status'] == 200, short($t['og:image'] ?? []));
	check('product: url points to the product page', strpos($t['og:url']['content'] ?? '', 'product_id=100') !== false);
	check('product: quotes / ampersands / tags in the description can not break the tag', strpos($p['body'], 'content="He said &quot;hi&quot; &amp; left &lt;b&gt;now&lt;/b&gt;"') !== false, short($t['og:description'] ?? []));
	check('product: names that OpenCart already encoded are not encoded twice', strpos($p['body'], 'content="D&#039;Arcy&#039;s product"') !== false && strpos($p['body'], '&amp;#039;') === false, short($t['og:title'] ?? []));

	$cat = $c->get('/index.php?route=product/category&path=900&language=en-gb');
	$t = meta_tags($cat['body']);
	check('category: tags with the category name and image', ($t['og:title']['content'] ?? '') === 'SEO Category' && isset($t['og:image']) && ($t['og:type']['content'] ?? '') === 'website', short($cat['body'], 150));

	$man = $c->get('/index.php?route=product/manufacturer.info&manufacturer_id=900&language=en-gb');
	$t = meta_tags($man['body']);
	check('manufacturer: tags with the brand name', ($t['og:title']['content'] ?? '') === 'SEO Brand', short($man['body'], 150));

	$other = $c->get('/index.php?route=information/contact&language=en-gb');
	check('other pages: no tags added', !meta_tags($other['body']) && $other['status'] == 200);

	$none = $c->get('/index.php?route=product/product&product_id=987654&language=en-gb');
	check('unknown product: no crash, no tags', !meta_tags($none['body']));

	// disabled
	save($admin, $token, settings(['module_seo_structured_data_status' => 0]));
	$p = $c->get('/index.php?route=product/product&product_id=100&language=en-gb');
	check('disabled: no tags are added', !meta_tags($p['body']) && $p['status'] == 200);
	save($admin, $token, settings());
}

if (wanted('uninstall')) {
	section('4. Uninstall');
	$u = $admin->get('/admin/index.php?route=extension/module.uninstall&user_token=' . $token . '&extension=' . $CODE . '&code=' . $CODE);
	check('uninstall succeeds', !empty($u['json']['success']), short($u['body']));
	check('uninstall removes the event', (int)sql_value("SELECT COUNT(*) FROM oc_event WHERE code = 'seo_structured_data'") == 0);
	$p = (new_shopper())->get('/index.php?route=product/product&product_id=100&language=en-gb');
	check('storefront still works after uninstall', $p['status'] == 200 && !meta_tags($p['body']));
}

finish();
