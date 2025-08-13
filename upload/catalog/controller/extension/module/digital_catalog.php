<?php

require_once(DIR_SYSTEM . 'library/jdf.php');
require_once(modification(DIR_SYSTEM . 'library/phpqrcode/qrlib.php'));
class ControllerExtensionModuleDigitalCatalog extends Controller
{

    public function generate_product_list_by_category()
    {
        ob_clean();

        $this->load->language('extension/module/digital_catalog');
        $this->load->model('catalog/product');
        $this->load->model('catalog/category');

        $settings = $this->config->get('digital_catalog');

        $product_id = isset($this->request->get['product_id']) ? (int) $this->request->get['product_id'] : 0;
        $category_id = isset($this->request->get['category_id']) ? (int) $this->request->get['category_id'] : 0;

        $product_info = $this->model_catalog_product->getProduct($product_id);
        $model = $product_info ? $product_info['model'] : '';


        $category_info = $this->model_catalog_category->getCategory($category_id);
        $products = $this->getProductsFromCategory($category_id);

        $base_data = [
            'category_name' => $category_info['name'],
            'base' => HTTP_SERVER,
            'title' => 'لیست محصولات ' . $category_info['name'],
            // تنظیمات نمایش
            'show_id' => !empty($settings['digital_catalog_show_id']),
            'show_name' => !empty($settings['digital_catalog_show_name']),
            'show_image' => !empty($settings['digital_catalog_show_image']),
            'show_price' => !empty($settings['digital_catalog_show_price']),
            'show_model' => !empty($settings['digital_catalog_show_model']),
            'show_attributes' => !empty($settings['digital_catalog_show_attributes']),
            'show_color' => !empty($settings['digital_catalog_show_color']),
            'show_description' => !empty($settings['digital_catalog_show_description']),
            'show_collection' => !empty($settings['digital_catalog_show_collection']),
            'show_sku' => !empty($settings['digital_catalog_show_sku']),
            'show_qrcode' => !empty($settings['digital_catalog_show_qrcode']),
            'show_address' => !empty($settings['digital_catalog_show_address']),
            'show_email' => !empty($settings['digital_catalog_show_email']),
            'show_phone' => !empty($settings['digital_catalog_show_phone'])
        ];

        //دریافت تاریخ
        $current_date = ($this->language->get('code') == 'fa')
            ? jdate('Y/m/d')
            : date('Y/m/d');


        $products_data = [];
        foreach ($products as $product) {
            // ساخت لینک محصول
            $product_url = $this->url->link('product/product', 'product_id=' . $product['product_id']);


            $qr_dir = DIR_IMAGE . 'qrcodes/';
            if (!is_dir($qr_dir)) {
                mkdir($qr_dir, 0755, true);
            }

            $qr_filename = 'product_' . $product['product_id'] . '.png';
            $qr_path = $qr_dir . $qr_filename;

            if (!file_exists($qr_path)) {
                QRcode::png($product_url, $qr_path, QR_ECLEVEL_L, 4);
            }

            // مسیر نمایش در مرورگر
            $product['qrcode'] = HTTP_SERVER . 'image/qrcodes/' . $qr_filename;



            //دریافت تصاویر
            if ($product['image']) {
                $product['main_image'] = HTTP_SERVER . 'image/' . $product['image'];
            } else {
                $product['main_image'] = '';
            }

            $image_limit = isset($settings['digital_catalog_image_limit']) ? (int) $settings['digital_catalog_image_limit'] : 3;

            $product_images = $this->model_catalog_product->getProductImages($product['product_id']);

            $limited_images = [];

            if ($product_images) {
                $count_images = count($product_images);
                if ($count_images >= $image_limit) {
                    $limited_images = array_slice($product_images, 0, $image_limit);
                } else {
                    while (count($limited_images) < $image_limit) {
                        foreach ($product_images as $img) {
                            if (count($limited_images) >= $image_limit)
                                break;
                            $limited_images[] = $img;
                        }
                    }
                }
            } else {
                $limited_images = [];
            }

            foreach ($limited_images as &$img) {
                $img['image'] = HTTP_SERVER . 'image/' . $img['image'];
            }


            // دریافت خصوصیات فعال محصول 
            $attributes = [];
            if ($base_data['show_attributes']) {
                $query = $this->db->query("
                    SELECT ad.name, pa.text
                    FROM " . DB_PREFIX . "product_attribute pa
                    JOIN " . DB_PREFIX . "attribute_description ad 
                    ON ad.attribute_id = pa.attribute_id 
                    AND ad.language_id = '" . (int) $this->config->get('config_language_id') . "'
                    WHERE pa.product_id = '" . (int) $product['product_id'] . "'
                    AND pa.is_brief = 1
                    AND TRIM(pa.text) != ''
                ");

                foreach ($query->rows as $result) {
                    $attributes[] = [
                        'name' => $result['name'],
                        'text' => html_entity_decode($result['text'], ENT_QUOTES, 'UTF-8')
                    ];
                }
            }

            // دریافت رنگ محصول
            if ($base_data['show_color']) {
                $rows = $this->db->query("
        SELECT color FROM " . DB_PREFIX . "product_image
        WHERE product_id = " . (int) $product['product_id'] . "
        AND color IS NOT NULL AND color != '' AND color != '0'
        ORDER BY sort_order ASC
    ")->rows;

                $product['color'] = '-';
                $product['color_data'] = []; // آرایه برای نگهداری نام و کد رنگ

                if ($rows) {
                    $ids = [];
                    foreach ($rows as $r) {
                        $id = trim($r['color']);
                        if ($id && $id !== '0' && !in_array($id, $ids, true)) {
                            $ids[] = $id;
                        }
                    }

                    $names = [];
                    $color_codes_map = [];

                    if ($num_ids = array_filter($ids, 'is_numeric')) {
                        $map = [];
                        $lang = (int) $this->config->get('config_language_id');
                        $in = implode(',', array_map('intval', $num_ids));

                        // گرفتن نام رنگ‌ها
                        foreach (
                            $this->db->query("
                    SELECT option_value_id, name
                    FROM " . DB_PREFIX . "option_value_description
                    WHERE option_value_id IN ($in) AND language_id = $lang
                ")->rows as $row
                        ) {
                            $map[$row['option_value_id']] = $row['name'];
                        }

                        // گرفتن کد رنگ‌ها
                        foreach (
                            $this->db->query("
                    SELECT option_value_id, color_code
                    FROM " . DB_PREFIX . "option_value_color_code
                    WHERE option_value_id IN ($in)
                ")->rows as $row
                        ) {
                            $color_codes_map[$row['option_value_id']] = $row['color_code'];
                        }

                        foreach ($ids as $id) {
                            $name = is_numeric($id) ? ($map[$id] ?? "رنگ (کد: $id)") : $id;
                            $code = $color_codes_map[$id] ?? '#cccccc'; // کد رنگ پیش‌فرض اگر موجود نبود

                            $names[] = $name;
                            $product['color_data'][] = [
                                'name' => $name,
                                'code' => $code
                            ];
                        }
                    } else {
                        $names = $ids;
                        foreach ($ids as $id) {
                            $product['color_data'][] = [
                                'name' => $id,
                                'code' => '#cccccc'
                            ];
                        }
                    }

                    $product['color'] = implode(', ', array_unique($names));
                }
            }
            $product['color'] = $product['color'] ?? '-';


            //دریافت توضیحات
            if ($base_data['show_description']) {
                $product_info = $this->model_catalog_product->getProduct($product['product_id']);
                if ($product_info) {
                    $product['description'] = html_entity_decode($product_info['description']);
                } else {
                    $product['description'] = '';
                }
            }

            //دریافت کد انبار
            if ($base_data['show_sku']) {
                $product_info = $this->model_catalog_product->getProduct($product['product_id']);
                if ($product_info) {
                    $product['sku'] = $product_info['sku'];
                } else {
                    $product['sku'] = '';
                }
            }

            // داده‌های این محصول
            $view_data = array_merge($base_data, [
                'product' => $product,
                'attributes' => $attributes,
                'color' => $product['color'],
                'color_data' => $product['color_data'],
                'description' => $product['description'] ?? '',
                'sku' => $product['sku'],
                'images' => $limited_images,
                'qrcode' => $product['qrcode'],
                'current_date' => $current_date
            ]);



            $products_data[] = $view_data;
        }
        $final_data = array_merge($base_data, [
            'products' => $products_data
        ]);

        $this->response->setOutput($this->load->view('extension/module/digital_catalog/generate_product_list_by_category', $final_data));
    }

    private function getProductsFromCategory($category_id)
    {
        $products = [];
        // دریافت محصولات برای دسته بندی اصلی
        $category_products = $this->model_catalog_product->getProducts(['filter_category_id' => $category_id]);
        $products = array_merge($products, $category_products);

        // دریافت زیرمجموعه‌ها
        $subcategories = $this->model_catalog_category->getCategories($category_id);

        foreach ($subcategories as $subcategory) {
            // دریافت محصولات برای زیرمجموعه‌ها به صورت بازگشتی
            $subcategory_products = $this->getProductsFromCategory($subcategory['category_id']);
            $products = array_merge($products, $subcategory_products);
        }

        return $products;
    }
}
