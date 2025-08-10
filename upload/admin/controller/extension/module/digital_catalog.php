<?php
class ControllerExtensionModuleDigitalCatalog extends Controller
{
    private $error = array();

    public function index()
    {

        $this->load->language('extension/module/digital_catalog');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('setting/setting');

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $post_data = $this->request->post;

            $post_data['digital_catalog_show_id'] = isset($post_data['digital_catalog_show_id']) ? 1 : 0;
            $post_data['digital_catalog_show_name'] = isset($post_data['digital_catalog_show_name']) ? 1 : 0;
            $post_data['digital_catalog_show_image'] = isset($post_data['digital_catalog_show_image']) ? 1 : 0;
            $post_data['digital_catalog_show_price'] = isset($post_data['digital_catalog_show_price']) ? 1 : 0;
            $post_data['digital_catalog_show_model'] = isset($post_data['digital_catalog_show_model']) ? 1 : 0;
            $post_data['digital_catalog_show_attributes'] = isset($post_data['digital_catalog_show_attributes']) ? 1 : 0;
            $post_data['digital_catalog_show_color'] = isset($post_data['digital_catalog_show_color']) ? 1 : 0;
            $post_data['digital_catalog_show_description'] = isset($post_data['digital_catalog_show_description']) ? 1 : 0;
            $post_data['digital_catalog_image_limit'] = isset($post_data['digital_catalog_image_limit']) ? (int)$post_data['digital_catalog_image_limit'] : 3;

            $this->model_setting_setting->editSetting('digital_catalog', ['digital_catalog' => $post_data]);


            $this->session->data['success'] = $this->language->get('text_success');


            $action = isset($this->request->post["action"]) ? $this->request->post["action"] : "";

            $this->load->controller('extension/component/save_section/redirect', [
                'action' =>  $action,
                'save' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', 'SSL'),
                'save_edit' => $this->url->link('extension/module/digital_catalog', 'user_token=' . $this->session->data['user_token'], 'SSL'),
            ]);
        }

        if (isset($this->session->data['success'])) {
            $data['success'] = $this->session->data['success'];

            unset($this->session->data['success']);
        } else {
            $data['success'] = '';
        }

        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/module/digital_catalog', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['action'] = $this->url->link('extension/module/digital_catalog', 'user_token=' . $this->session->data['user_token'], true);

        if ($this->request->server['REQUEST_METHOD'] != 'POST') {
            $module_info = $this->config->get('digital_catalog');
        } else if ($this->request->server['REQUEST_METHOD'] == 'POST') {
            $module_info = $this->request->post;
        }

        if ($module_info == null) {
            $module_info = [];
        }

        $module_info['form_display_name'] = false;
        $module_info['form_display_title'] = false;
        $module_info['form_display_description'] = false;
        $module_info['form_display_class_suffix'] = false;
        $module_info['form_display_image'] = false;
        $module_info['form_display_module_image_width'] = false;
        $module_info['form_display_module_image_height'] = false;
        $data['main_sub_module_form'] = $this->load->controller('extension/component/main_sub_module_form', $module_info);

        $data['save_section'] = $this->load->controller('extension/component/save_section', [
            'form_id'         => "form-module",
            'save_new'         => false,
            'save_edit'     => true,
            'save'             => true,
            'cancel'         => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true),
        ]);

        // بارگذاری آپشن های نمایش
        $data['digital_catalog_show_id'] = isset($module_info['digital_catalog_show_id']) ? $module_info['digital_catalog_show_id'] : 0;
        $data['digital_catalog_show_name'] = isset($module_info['digital_catalog_show_name']) ? $module_info['digital_catalog_show_name'] : 0;
        $data['digital_catalog_show_image'] = isset($module_info['digital_catalog_show_image']) ? $module_info['digital_catalog_show_image'] : 0;
        $data['digital_catalog_show_price'] = isset($module_info['digital_catalog_show_price']) ? $module_info['digital_catalog_show_price'] : 0;
        $data['digital_catalog_show_model'] = isset($module_info['digital_catalog_show_model']) ? $module_info['digital_catalog_show_model'] : 0;
        $data['digital_catalog_show_attributes'] = isset($module_info['digital_catalog_show_attributes']) ? $module_info['digital_catalog_show_attributes'] : 0;
        $data['digital_catalog_show_color'] = isset($module_info['digital_catalog_show_color']) ? $module_info['digital_catalog_show_color'] : 0;
        $data['digital_catalog_show_description'] = isset($module_info['digital_catalog_show_description']) ? $module_info['digital_catalog_show_description'] : 0;

        $data['digital_catalog_image_limit'] = isset($module_info['digital_catalog_image_limit']) ? (int)$module_info['digital_catalog_image_limit'] : 3;

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/module/digital_catalog', $data));
    }

    protected function validate()
    {
        if (!$this->user->hasPermission('modify', 'extension/module/digital_catalog')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }
        return !$this->error;
    }
}
