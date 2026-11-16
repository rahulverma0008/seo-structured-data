<?php
namespace Opencart\Catalog\Controller\Extension\SeoStructuredData\Module;

/**
 * Adds Open Graph / Twitter Card meta tags to the <head> of the store.
 *
 * Registered as event catalog/view/common/header/after when the module is installed (see the admin controller).
 */
class SeoStructuredData extends \Opencart\System\Engine\Controller {

	/**
	 * @param string       $route
	 * @param array<mixed> $data
	 * @param string       $output html of the header
	 *
	 * @return string|null OpenCart before 4.1 uses the returned html, OpenCart 4.1 uses the $output reference
	 */
	public function render(&$route, &$data, &$output) {
		if (!$this->config->get('module_seo_structured_data_status') || !is_string($output)) {
			return null;
		}

		$tags = $this->getTags();

		if (!$tags || strpos($output, '</head>') === false) {
			return null;
		}

		$html = '';

		foreach ($tags as $name => $tag) {
			$html .= '<meta ' . $tag['type'] . '="' . $this->escape($name) . '" content="' . $this->escape($tag['content']) . '"/>' . PHP_EOL;
		}

		// only the first </head>
		$position = strpos($output, '</head>');

		$output = substr_replace($output, $html, $position, 0);

		return $output;
	}

	/**
	 * The values of OpenCart are already HTML encoded (O&#039;Neil), do not encode them twice
	 */
	private function escape(string $value): string {
		return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8', false);
	}

	/**
	 * @return array<string, array{type: string, content: string}>
	 */
	private function getTags(): array {
		// OpenCart 4.0.2.0 changed the separator between class and method from | to .
		$route = str_replace('|', '.', (string)($this->request->get['route'] ?? ''));

		$width = (int)$this->config->get('module_seo_structured_data_width') ?: 200;
		$height = (int)$this->config->get('module_seo_structured_data_height') ?: 200;

		$language = 'language=' . $this->config->get('config_language');

		$page = null;

		if ($route === '' || $route == 'common/home') {
			$image = '';

			if ($this->config->get('config_image') && is_file(DIR_IMAGE . $this->config->get('config_image'))) {
				$image = rtrim((string)$this->config->get('config_url'), '/') . '/image/' . $this->config->get('config_image');
			}

			$page = [
				'type'        => 'website',
				'title'       => (string)$this->config->get('config_meta_title'),
				'url'         => (string)$this->config->get('config_url'),
				'description' => (string)$this->config->get('config_meta_description'),
				'image'       => $image,
				'sized'       => false
			];
		} elseif ($route == 'product/product' && !empty($this->request->get['product_id'])) {
			$this->load->model('catalog/product');

			$info = $this->model_catalog_product->getProduct((int)$this->request->get['product_id']);

			if ($info) {
				$page = [
					'type'        => 'product',
					'title'       => $info['name'],
					'url'         => $this->url->link('product/product', $language . '&product_id=' . $info['product_id']),
					'description' => (string)($info['meta_description'] ?? ''),
					'image'       => $this->getImage($info['image'] ?? '', $width, $height),
					'sized'       => true
				];
			}
		} elseif ($route == 'product/category' && !empty($this->request->get['path'])) {
			$parts = explode('_', (string)$this->request->get['path']);

			$this->load->model('catalog/category');

			$info = $this->model_catalog_category->getCategory((int)end($parts));

			if ($info) {
				$page = [
					'type'        => 'website',
					'title'       => $info['name'],
					'url'         => $this->url->link('product/category', $language . '&path=' . $this->request->get['path']),
					'description' => (string)($info['meta_description'] ?? ''),
					'image'       => $this->getImage($info['image'] ?? '', $width, $height),
					'sized'       => true
				];
			}
		} elseif ($route == 'product/manufacturer.info' && !empty($this->request->get['manufacturer_id'])) {
			$this->load->model('catalog/manufacturer');

			$info = $this->model_catalog_manufacturer->getManufacturer((int)$this->request->get['manufacturer_id']);

			if ($info) {
				$page = [
					'type'        => 'website',
					'title'       => $info['name'],
					'url'         => $this->url->link('product/manufacturer.info', $language . '&manufacturer_id=' . $info['manufacturer_id']),
					'description' => (string)($info['meta_description'] ?? ''),
					'image'       => $this->getImage($info['image'] ?? '', $width, $height),
					'sized'       => true
				];
			}
		}

		if (!$page) {
			return [];
		}

		$tags = [
			'twitter:card'   => ['type' => 'name', 'content' => 'summary'],
			'twitter:title'  => ['type' => 'name', 'content' => $page['title']],
			'twitter:domain' => ['type' => 'name', 'content' => $page['url']],
			'og:type'        => ['type' => 'property', 'content' => $page['type']],
			'og:title'       => ['type' => 'property', 'content' => $page['title']],
			'og:url'         => ['type' => 'property', 'content' => $page['url']]
		];

		if ($page['description'] !== '') {
			$tags['twitter:description'] = ['type' => 'name', 'content' => $page['description']];
			$tags['og:description'] = ['type' => 'property', 'content' => $page['description']];
		}

		if ($page['image'] !== '') {
			$tags['twitter:image'] = ['type' => 'name', 'content' => $page['image']];
			$tags['og:image'] = ['type' => 'property', 'content' => $page['image']];
			$tags['og:image:url'] = ['type' => 'property', 'content' => $page['image']];

			if ($page['sized']) {
				$tags['twitter:image:width'] = ['type' => 'name', 'content' => (string)$width];
				$tags['twitter:image:height'] = ['type' => 'name', 'content' => (string)$height];
				$tags['og:image:width'] = ['type' => 'property', 'content' => (string)$width];
				$tags['og:image:height'] = ['type' => 'property', 'content' => (string)$height];
			}
		}

		if ($this->config->get('module_seo_structured_data_fb_app_id')) {
			$tags['fb:app_id'] = ['type' => 'property', 'content' => (string)$this->config->get('module_seo_structured_data_fb_app_id')];
		}

		if ($this->config->get('module_seo_structured_data_twitter_creator')) {
			$tags['twitter:creator'] = ['type' => 'name', 'content' => '@' . $this->config->get('module_seo_structured_data_twitter_creator')];
			$tags['twitter:site'] = ['type' => 'name', 'content' => '@' . $this->config->get('module_seo_structured_data_twitter_creator')];
		}

		return $tags;
	}

	private function getImage(string $image, int $width, int $height): string {
		if ($image === '' || !is_file(DIR_IMAGE . $image)) {
			return '';
		}

		$this->load->model('tool/image');

		return (string)$this->model_tool_image->resize($image, $width, $height);
	}
}
