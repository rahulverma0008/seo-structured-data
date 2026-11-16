<?php
namespace Opencart\Admin\Controller\Extension\SeoStructuredData\Module;

class SeoStructuredData extends \Opencart\System\Engine\Controller {

	private const EVENT_CODE = 'seo_structured_data';

	/**
	 * Separator between the class and the method in a route: OpenCart 4.0.2.0 changed it from | to .
	 */
	private function separator(): string {
		return version_compare(VERSION, '4.0.2.0', '>=') ? '.' : '|';
	}

	/**
	 * Register the event which adds the tags to the header. Plain SQL because the addEvent() method of the
	 * setting/event model differs between OpenCart versions. Safe to call more than once.
	 */
	private function addEvent(): void {
		$this->deleteEvent();

		$this->db->query("INSERT INTO `" . DB_PREFIX . "event` SET `code` = '" . self::EVENT_CODE . "', `description` = 'SEO Structured Data: meta tags in the header', `trigger` = 'catalog/view/common/header/after', `action` = '" . $this->db->escape('extension/seo_structured_data/module/seo_structured_data' . $this->separator() . 'render') . "', `status` = '1', `sort_order` = '0'");
	}

	private function deleteEvent(): void {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "event` WHERE `code` = '" . self::EVENT_CODE . "'");
	}

	public function install(): void {
		$this->addEvent();
	}

	public function uninstall(): void {
		$this->deleteEvent();
	}

	public function index(): void {
		$this->load->language('extension/seo_structured_data/module/seo_structured_data');

		$this->document->setTitle($this->language->get('heading_title'));

		// the event is missing when the files have been replaced without reinstalling the extension
		if (!$this->db->query("SELECT `event_id` FROM `" . DB_PREFIX . "event` WHERE `code` = '" . self::EVENT_CODE . "' AND `action` = '" . $this->db->escape('extension/seo_structured_data/module/seo_structured_data' . $this->separator() . 'render') . "'")->num_rows) {
			$this->addEvent();
		}

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module')
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/seo_structured_data/module/seo_structured_data', 'user_token=' . $this->session->data['user_token'])
		];

		$data['save'] = $this->url->link('extension/seo_structured_data/module/seo_structured_data' . $this->separator() . 'save', 'user_token=' . $this->session->data['user_token']);
		$data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module');
		$data['support_email'] = $this->language->get('support_email');

		$data['module_seo_structured_data_status'] = (int)$this->config->get('module_seo_structured_data_status');
		$data['module_seo_structured_data_fb_app_id'] = $this->config->get('module_seo_structured_data_fb_app_id');
		$data['module_seo_structured_data_twitter_creator'] = $this->config->get('module_seo_structured_data_twitter_creator');
		$data['module_seo_structured_data_width'] = $this->config->get('module_seo_structured_data_width');
		$data['module_seo_structured_data_height'] = $this->config->get('module_seo_structured_data_height');

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/seo_structured_data/module/seo_structured_data', $data));
	}

	public function save(): void {
		$this->load->language('extension/seo_structured_data/module/seo_structured_data');

		$json = [];

		if (!$this->user->hasPermission('modify', 'extension/seo_structured_data/module/seo_structured_data')) {
			$json['error']['warning'] = $this->language->get('error_permission');
		}

		$post = $this->request->post;

		$creator = ltrim(trim((string)($post['module_seo_structured_data_twitter_creator'] ?? '')), '@');

		if ($creator !== '' && !preg_match('/^\w{1,15}$/', $creator)) {
			$json['error']['twitter_creator'] = $this->language->get('error_twitter_creator');
		}

		foreach (['width', 'height'] as $key) {
			$value = trim((string)($post['module_seo_structured_data_' . $key] ?? ''));

			if ($value !== '' && (!ctype_digit($value) || (int)$value < 1 || (int)$value > 2000)) {
				$json['error'][$key] = $this->language->get('error_size');
			}
		}

		if (isset($json['error']) && !isset($json['error']['warning'])) {
			$json['error']['warning'] = $this->language->get('error_warning');
		}

		if (!$json) {
			$settings = [
				'module_seo_structured_data_status'          => (int)!empty($post['module_seo_structured_data_status']),
				'module_seo_structured_data_fb_app_id'       => trim((string)($post['module_seo_structured_data_fb_app_id'] ?? '')),
				'module_seo_structured_data_twitter_creator' => $creator,
				'module_seo_structured_data_width'           => trim((string)($post['module_seo_structured_data_width'] ?? '')),
				'module_seo_structured_data_height'          => trim((string)($post['module_seo_structured_data_height'] ?? ''))
			];

			$this->load->model('setting/setting');

			$this->model_setting_setting->editSetting('module_seo_structured_data', $settings);

			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
