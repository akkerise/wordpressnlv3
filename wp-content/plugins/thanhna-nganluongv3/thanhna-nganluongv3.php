<?php
define('URL_API','https://www.nganluong.vn/checkout.api.nganluong.post.php'); // Đường dẫn gọi api
define('RECEIVER','demo@nganluong.vn'); // Email tài khoản ngân lượng
define('MERCHANT_ID', '36680'); // Mã merchant kết nối
define('MERCHANT_PASS', 'matkhauketnoi'); // Mật khẩu kết nối

//include(ABSPATH.'wp-content/plugins/woocommerce/includes/abstract-wc-payment-gateway.php');
if (  !defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Plugin Name: Ngan Luong payment gateway for WooCommerce
 * Plugin URI: https://www.nganluong.vn/
 * Description: Plugin tích hợp NgânLượng.vn được build trên WooCommerce 3.x
 * Version: 3.1
 * Author: AkKeRise - 0968381829
 * Author URI: http://www.webckk.com/
 */
ini_set('display_errors', true);
add_action('plugins_loaded', 'woocommerce_payment_nganluong_init', 0);
add_action('parse_request', array('WC_Gateway_NganLuongV3', 'nganluong_return_handler'));

function woocommerce_payment_nganluong_init(){
    if(!class_exists('WC_Gateway_NganLuongV3'))
        return;
    class WC_Gateway_NganLuongV3 extends WC_Payment_Gateway {

        // URL checkout của nganluong.vn - Checkout URL for Ngan Luong
        private $nganluong_url;
        // Mã merchant site code
        private $merchant_site_code;
        // Mật khẩu bảo mật - Secure password
        private $secure_pass;
        // Debug parameters
        private $debug_params;
        private $debug_md5;

        // Autoload
        function __construct() {

            $this->icon = $this->settings['icon']; // Icon URL
            $this->id = 'nganluong';
            $this->method_title = 'Ngân Lượng';
            $this->has_fields = false;

            $this->init_form_fields();
            $this->init_settings();

            $this->title = $this->settings['title'];
            $this->description = $this->settings['description'];
            $this->nganluong_url = $this->settings['nganluong_url'];
            $this->merchant_site_code = $this->settings['merchant_site_code'];
            $this->merchant_id = $this->settings['merchant_id'];
            $this->secure_pass = $this->settings['secure_pass'];
            $this->redirect_page_id = $this->settings['redirect_page_id'];

            $this->debug = $this->settings['debug'];
            $this->order_button_text = __('Proceed to Ngân Lượng', 'woocommerce');

            $this->msg['message'] = "";
            $this->msg['class'] = "";

            // Add the page after checkout to redirect to Ngan Luong
            add_action('woocommerce_receipt_NganLuong', array($this, 'receipt_page'));
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

            // add_action('woocommerce_thankyou_NganLuongVN', array($this, 'thankyou_page'));
        }

        // Write log
        public static function log($message)
        {
            $log = new WC_Logger();
            $log->add('nganluong', $message);
        }

        // Admin field - Woocommerce
        public function init_form_fields()
        {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Activate', 'woocommerce'),
                    'type' => 'checkbox',
                    'label' => __('Activate the payment gateway for Ngan Luong', 'woocommerce'),
                    'default' => 'yes'),
                'title' => array(
                    'title' => __('Name', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('Tên phương thức thanh toán ( khi khách hàng chọn phương thức thanh toán )', 'woocommerce'),
                    'default' => __('NganLuongVN', 'woocommerce')),
                'icon' => array(
                    'title' => __('Icon', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('Icon phương thức thanh toán', 'woocommerce'),
                    'default' => __('https://www.nganluong.vn/css/checkout/version20/images/logoNL.png', 'woocommerce')),
                'description' => array(
                    'title' => __('Mô tả', 'woocommerce'),
                    'type' => 'textarea',
                    'description' => __('Mô tả phương thức thanh toán.', 'woocommerce'),
                    'default' => __('Click place order and you will be directed to the Ngan Luong website in order to make payment', 'woocommerce')),
                'merchant_id' => array(
                    'title' => __('NganLuong.vn email address', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('Đây là tài khoản NganLuong.vn (Email) để nhận tiền')),
                'redirect_page_id' => array(
                    'title' => __('Return URL'),
                    'type' => 'select',
                    'options' => $this->get_pages('Hãy chọn...'),
                    'description' => __('Hãy chọn trang/url để chuyển đến sau khi khách hàng đã thanh toán tại NganLuong.vn thành công', 'woocommerce')
                ),
                'status_order' => array(
                    'title' => __('Trạng thái Order'),
                    'type' => 'select',
                    'options' => wc_get_order_statuses(),
                    'description' => __('Chọn trạng thái orders cập nhật', 'woocommerce')
                ),
                'nlcurrency' => array(
                    'title' => __('Currency', 'woocommerce'),
                    'type' => 'text',
                    'default' => 'vnd',
                    'description' => __('"vnd" or "usd"', 'woocommerce')
                ),
                'nganluong_url' => array(
                    'title' => __('Ngan Luong URL', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('"https://www.nganluong.vn/checkout.php"', 'woocommerce')
                ),
                'merchant_site_code' => array(
                    'title' => __('Merchant Site Code', 'woocommerce'),
                    'type' => 'text'
                ),
                'secure_pass' => array(
                    'title' => __('Secure Password', 'woocommerce'),
                    'type' => 'password'
                ),
            );
        }


//         There are no payment fields for NganLuongVN, but we want to show the description if set.

        function payment_fields()
        {
            if ($this->description)
                echo wpautop(wptexturize(__($this->description, 'woocommerce')));
        }

        public function process_payment($order_id)
        {
            $order = wc_get_order($order_id);
            $checkouturl = $this->generate_NganLuongV3_url($order_id);
            $this->log($checkouturl);
            return array(
                    'result' => 'success',
                    'redirect' => $checkouturl
            );
        }

        function generate_NganLuongV3_url($order_id){
            global $woocomerce;
            $order = new WC_Order($order_id);

            $nlcheckout= new NL_CheckOutV3(MERCHANT_ID,MERCHANT_PASS,RECEIVER,URL_API);

            // Tổng giá chưa trừ ship
            $total_amount = $order->get_total();
            // Order code
            $order_code = "macode_".time();
            $payment_type = '';
            $payment_method = 'VISA';
            $order_items = $order->get_item();

            $array_items[0]= array('item_name1' => 'Product name',
                             'item_quantity1' => 1,
                             'item_amount1' => $total_amount,
                             'item_url1' => 'http://nganluong.vn/');

            $array_items=array();
            $bank_code = @$_POST['bankcode'];
            $discount_amount = 0;
            $tax_amount=0;
            $fee_shipping= $order->get_total_shipping_refunded();
            $product_names = [];
            foreach ($order_items as $order_item){
                $product_names[] = $order_item['name'];
            }

            $order_description = implode(', ',$product_names);
            $price = $order->get_total() - ($tax_amount + $fee_shipping);

            $order_quantity = $order->get_item_count();
            $return_url = get_site_url(). '/nganluong_return?order_id=' .$order_id;
            $cancel_url =urlencode('http://localhost/nganluong.vn/checkoutv3?orderid='.$order_code) ;

            $buyer_fullname = $order->get_billing_last_name() . ' ' . $order->get_billing_first_name();
            $buyer_email = $order->get_billing_email();
            $buyer_mobile = $order->get_billing_phone();
            $buyer_address = '';
            if ($buyer_address == ''){
                $buyer_address = $order->get_billing_address_1();
                if (empty($buyer_address)){
                    $buyer_address = $order->get_address();
                }else{
                    $buyer_address = 'Ở Gầm Cầu Vĩnh Tuy';
                }
            }

            if($payment_method !='' && $buyer_email !="" && $buyer_mobile !="" && $buyer_fullname !="" && filter_var( $buyer_email, FILTER_VALIDATE_EMAIL )  ){
                if($payment_method =="VISA"){

                    $nl_result= $nlcheckout->VisaCheckout($order_code,$total_amount,$payment_type,$order_description,$tax_amount,
                        $fee_shipping,$discount_amount,$return_url,$cancel_url,$buyer_fullname,$buyer_email,$buyer_mobile,
                        $buyer_address,$array_items,$bank_code);

                }elseif($payment_method =="NL"){
                    $nl_result= $nlcheckout->NLCheckout($order_code,$total_amount,$payment_type,$order_description,$tax_amount,
                        $fee_shipping,$discount_amount,$return_url,$cancel_url,$buyer_fullname,$buyer_email,$buyer_mobile,
                        $buyer_address,$array_items);

                }elseif($payment_method =="ATM_ONLINE" && $bank_code !='' ){
                    $nl_result= $nlcheckout->BankCheckout($order_code,$total_amount,$bank_code,$payment_type,$order_description,$tax_amount,
                        $fee_shipping,$discount_amount,$return_url,$cancel_url,$buyer_fullname,$buyer_email,$buyer_mobile,
                        $buyer_address,$array_items) ;
                }
                elseif($payment_method =="NH_OFFLINE"){
                    $nl_result= $nlcheckout->officeBankCheckout($order_code, $total_amount, $bank_code, $payment_type, $order_description, $tax_amount, $fee_shipping, $discount_amount, $return_url, $cancel_url, $buyer_fullname, $buyer_email, $buyer_mobile, $buyer_address, $array_items);
                }
                elseif($payment_method =="ATM_OFFLINE"){
                    $nl_result= $nlcheckout->BankOfflineCheckout($order_code, $total_amount, $bank_code, $payment_type, $order_description, $tax_amount, $fee_shipping, $discount_amount, $return_url, $cancel_url, $buyer_fullname, $buyer_email, $buyer_mobile, $buyer_address, $array_items);

                }
                elseif($payment_method =="IB_ONLINE"){
                    $nl_result= $nlcheckout->IBCheckout($order_code, $total_amount, $bank_code, $payment_type, $order_description, $tax_amount, $fee_shipping, $discount_amount, $return_url, $cancel_url, $buyer_fullname, $buyer_email, $buyer_mobile, $buyer_address, $array_items);
                }
                elseif ($payment_method == "CREDIT_CARD_PREPAID") {

                    $nl_result = $nlcheckout->PrepaidVisaCheckout($order_code, $total_amount, $payment_type, $order_description, $tax_amount, $fee_shipping, $discount_amount, $return_url, $cancel_url, $buyer_fullname, $buyer_email, $buyer_mobile, $buyer_address, $array_items, $bank_code);
                }
//                echo "<pre>";var_dump($nl_result);echo "</pre>";exit();
                if ($nl_result->error_code =='00'){
                    echo "<pre>";var_dump($nl_result);echo "</pre>";exit();
                    return $nl_result;
                }else{
                    echo $nl_result->error_message;
                }

            }else{
                echo "<h3> Bạn chưa nhập đủ thông tin khách hàng </h3>";
            }
        }

        public function nganluong_return_handler($order_id){
            global $woocommerce;

            // This probably could be written better
            if (isset($_REQUEST['payment_id']) && !empty($_REQUEST['payment_id'])){
                self::log($_SERVER['REMOTE_ADDR']). json_encode(@$_REQUEST);
                $settings = $this->get_option('woocommerce_nganluong_settings',null);

                $order_id = $_REQUEST['order_id'];
                $order = new WC_Order($order_id);
                $transaction_info = '';
                $order_code = $_REQUEST['order_code'];
                $price = $_REQUEST['price'];
                $secure_code = $_REQUEST['secure_code'];
                $payment_type = $_REQUEST['payment_type'];

                // Make code authentication from site of merchant
                $str = '';
                $str .= ' ' . strval($transaction_info);
                $str .= ' ' . strval($order_code);
                $str .= ' ' . strval($price);
                $str .= ' ' . strval($_REQUEST['payment_id']);
                $str .= ' ' . strval($payment_type);
                $str .= ' ' . strval($_REQUEST['error_text']);
                $str .= ' ' . strval($settings['merchant_site_code']);
                $str .= ' ' . strval($settings['secure_pass']);

                // Mã hóa các tham số
//                $verify_secure_code = '';
                $verify_secure_code = md5($str);

                // Xác thực mã của chủ web với mã trả về từ Ngân Lượng
                if ($verify_secure_code === $secure_code){
                    $new_order_status = $settings['status_order'];
                    $old_status = 'wc-' . $order->get_status();

                    if ($new_order_status !== $old_status){
                        $note = 'Thanh toán trực tuyến qua Ngân Lượng.';
                        if ($payment_type == 2){
                            $note .= ' Với hình thức thanh toán tạm giữ';
                        }elseif ($payment_type == 1){
                            $note .= ' Với hình thức thanh toán ngay';
                        }
                        $note .= ' .Mã thanh toán : ' . $_REQUEST['payment_id'];
                        $order->update_status($new_order_status);
                        $order->add_order_note(sprintf(__('Cập nhật trạng thái từ %1$s thành %2$s.' . $note, 'woocommerce'), wc_get_order_status_name($old_status), wc_get_order_status_name($new_order_status)), 0, false);
                        self::log('Cập nhật đơn hàng ID: ' . $order_id . ' trạng thái ' . ((!empty($new_status)) ? $new_status : ''));
                    }

                    // Remove cart
                    $woocommerce->cart->empty_cart();
                    // Empty awaiting payment session
                    unset($_SESSION['order_awaiting_payment']);
                    wp_redirect(get_permalink($settings['redirect_page_id']));
                    exit;
                }else{
                    self::log('Thông tin giao dịch không chính xác');

                    echo '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" /><h3>Thông tin giao dịch không chính xác</h3>';
                    die();
                }
            }
        }

        // get all pages
        function get_pages($title = false, $indent = true)
        {
            $wp_pages = get_pages('sort_column=menu_order');
            $page_list = array();
            if ($title)
                $page_list[] = $title;
            foreach ($wp_pages as $page) {
                $prefix = '';
                // show indented child pages?
                if ($indent) {
                    $has_parent = $page->post_parent;
                    while ($has_parent) {
                        $prefix .= ' - ';
                        $next_page = get_page($has_parent);
                        $has_parent = $next_page->post_parent;
                    }
                }
                // add to page list array array
                $page_list[$page->ID] = $prefix . $page->post_title;
            }
            return $page_list;
        }
    }

    /*
    *  Class NganLuong provided
    */
    class NL_CheckOutV3
    {
        public $url_api ='https://www.nganluong.vn/checkout.api.nganluong.post.php';
        public $merchant_id = '';
        public $merchant_password = '';
        public $receiver_email = '';
        public $cur_code = 'vnd';



        function __construct($merchant_id, $merchant_password, $receiver_email,$url_api)
        {
            $this->version ='3.1';
            $this->url_api =$url_api;
            $this->merchant_id = $merchant_id;
            $this->merchant_password = $merchant_password;
            $this->receiver_email = $receiver_email;
        }

        function GetTransactionDetail($token){
            ###################### BEGIN #####################
            $params = array(
                'merchant_id'       => $this->merchant_id ,
                'merchant_password' => MD5($this->merchant_password),
                'version'           => $this->version,
                'function'          => 'GetTransactionDetail',
                'token'             => $token
            );

            $post_field = '';
            foreach ($params as $key => $value){
                if ($post_field != '') $post_field .= '&';
                $post_field .= $key."=".$value;
            }
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL,$this->url_api);
            curl_setopt($ch, CURLOPT_ENCODING , 'UTF-8');
            curl_setopt($ch, CURLOPT_VERBOSE, 1);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_field);
            $result = curl_exec($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);

            if ($result != '' && $status==200){
                $nl_result  = simplexml_load_string($result);
                return $nl_result;
            }

            return false;
            ###################### END #####################

        }


        /*

        Hàm lấy link thanh toán bằng thẻ visa
        ===============================
        Tham số truyền vào bắt buộc phải có
                    order_code
                    total_amount
                    payment_method

                    buyer_fullname
                    buyer_email
                    buyer_mobile
        ===============================
            $array_items mảng danh sách các item name theo quy tắc
            item_name1
            item_quantity1
            item_amount1
            item_url1
            .....
            payment_type Kiểu giao dịch: 1 - Ngay; 2 - Tạm giữ; Nếu không truyền hoặc bằng rỗng thì lấy theo chính sách của NganLuong.vn
         */
        function VisaCheckout($order_code,$total_amount,$payment_type,$order_description,$tax_amount,
                              $fee_shipping,$discount_amount,$return_url,$cancel_url,$buyer_fullname,$buyer_email,$buyer_mobile,
                              $buyer_address,$array_items,$bank_code)
        {
            $params = array(
                'cur_code'				=>	$this->cur_code,
                'function'				=> 'SetExpressCheckout',
                'version'				=> $this->version,
                'merchant_id'			=> $this->merchant_id, //Mã merchant khai báo tại NganLuong.vn
                'receiver_email'		=> $this->receiver_email,
                'merchant_password'		=> MD5($this->merchant_password), //MD5(Mật khẩu kết nối giữa merchant và NganLuong.vn)
                'order_code'			=> $order_code, //Mã hóa đơn do website bán hàng sinh ra
                'total_amount'			=> $total_amount, //Tổng số tiền của hóa đơn
                'payment_method'		=> 'VISA', //Phương thức thanh toán, nhận một trong các giá trị 'VISA','ATM_ONLINE', 'ATM_OFFLINE' hoặc 'NH_OFFLINE'
                'bank_code'				=> $bank_code, //Phương thức thanh toán, nhận một trong các giá trị 'VISA','ATM_ONLINE', 'ATM_OFFLINE' hoặc 'NH_OFFLINE'
                'payment_type'			=> $payment_type, //Kiểu giao dịch: 1 - Ngay; 2 - Tạm giữ; Nếu không truyền hoặc bằng rỗng thì lấy theo chính sách của NganLuong.vn
                'order_description'		=> $order_description, //Mô tả đơn hàng
                'tax_amount'			=> $tax_amount, //Tổng số tiền thuế
                'fee_shipping'			=> $fee_shipping, //Phí vận chuyển
                'discount_amount'		=> $discount_amount, //Số tiền giảm giá
                'return_url'			=> $return_url, //Địa chỉ website nhận thông báo giao dịch thành công
                'cancel_url'			=> $cancel_url, //Địa chỉ website nhận "Hủy giao dịch"
                'buyer_fullname'		=> $buyer_fullname, //Tên người mua hàng
                'buyer_email'			=> $buyer_email, //Địa chỉ Email người mua
                'buyer_mobile'			=> $buyer_mobile, //Điện thoại người mua
                'buyer_address'			=> $buyer_address, //Địa chỉ người mua hàng
                'total_item'			=> count($array_items)
            );
            $post_field = '';
            foreach ($params as $key => $value){
                if ($post_field != '') $post_field .= '&';
                $post_field .= $key."=".$value;
            }
            if(count($array_items)>0){
                foreach($array_items as $array_item){
                    foreach ($array_item as $key => $value){
                        if ($post_field != '') $post_field .= '&';
                        $post_field .= $key."=".$value;
                    }
                }
            }
            //die($post_field);

            $nl_result=$this->CheckoutCall($post_field);
            return $nl_result;
        }
        function PrepaidVisaCheckout($order_code, $total_amount, $payment_type, $order_description, $tax_amount, $fee_shipping, $discount_amount, $return_url, $cancel_url, $buyer_fullname, $buyer_email, $buyer_mobile, $buyer_address, $array_items, $bank_code) {
            $params = array(
                'cur_code' => $this->cur_code,
                'function' => Config::$_FUNCTION,
                'version' => Config::$_VERSION,
                'merchant_id' => $this->merchant_id, //Mã merchant khai báo tại NganLuong.vn
                'receiver_email' => $this->receiver_email,
                'merchant_password' => MD5($this->merchant_password), //MD5(Mật khẩu kết nối giữa merchant và NganLuong.vn)
                'order_code' => $order_code, //Mã hóa đơn do website bán hàng sinh ra
                'total_amount' => $total_amount, //Tổng số tiền của hóa đơn
                'payment_method' => 'CREDIT_CARD_PREPAID', //Phương thức thanh toán, nhận một trong các giá trị 'VISA','ATM_ONLINE', 'ATM_OFFLINE' hoặc 'NH_OFFLINE'
                'bank_code' => $bank_code, //Phương thức thanh toán, nhận một trong các giá trị 'VISA','ATM_ONLINE', 'ATM_OFFLINE' hoặc 'NH_OFFLINE'
                'payment_type' => $payment_type, //Kiểu giao dịch: 1 - Ngay; 2 - Tạm giữ; Nếu không truyền hoặc bằng rỗng thì lấy theo chính sách của NganLuong.vn
                'order_description' => $order_description, //Mô tả đơn hàng
                'tax_amount' => $tax_amount, //Tổng số tiền thuế
                'fee_shipping' => $fee_shipping, //Phí vận chuyển
                'discount_amount' => $discount_amount, //Số tiền giảm giá
                'return_url' => $return_url, //Địa chỉ website nhận thông báo giao dịch thành công
                'cancel_url' => $cancel_url, //Địa chỉ website nhận "Hủy giao dịch"
                'buyer_fullname' => $buyer_fullname, //Tên người mua hàng
                'buyer_email' => $buyer_email, //Địa chỉ Email người mua
                'buyer_mobile' => $buyer_mobile, //Điện thoại người mua
                'buyer_address' => $buyer_address, //Địa chỉ người mua hàng
                'total_item' => count($array_items)
            );
            //var_dump($params); exit;
            $post_field = '';
            foreach ($params as $key => $value) {
                if ($post_field != '')
                    $post_field .= '&';
                $post_field .= $key . "=" . $value;
            }
            if (count($array_items) > 0) {
                foreach ($array_items as $array_item) {
                    foreach ($array_item as $key => $value) {
                        if ($post_field != '')
                            $post_field .= '&';
                        $post_field .= $key . "=" . $value;
                    }
                }
            }
            //die($post_field);

            $nl_result = $this->CheckoutCall($post_field);
            return $nl_result;
        }
        /*
        Hàm lấy link thanh toán qua ngân hàng
        ===============================
        Tham số truyền vào bắt buộc phải có
                    order_code
                    total_amount
                    bank_code // Theo bảng mã ngân hàng

                    buyer_fullname
                    buyer_email
                    buyer_mobile
        ===============================

            $array_items mảng danh sách các item name theo quy tắc
            item_name1
            item_quantity1
            item_amount1
            item_url1
            .....
            payment_type Kiểu giao dịch: 1 - Ngay; 2 - Tạm giữ; Nếu không truyền hoặc bằng rỗng thì lấy theo chính sách của NganLuong.vn

        */
        function BankCheckout($order_code,$total_amount,$bank_code,$payment_type,$order_description,$tax_amount,
                              $fee_shipping,$discount_amount,$return_url,$cancel_url,$buyer_fullname,$buyer_email,$buyer_mobile,
                              $buyer_address,$array_items)
        {
            $params = array(
                'cur_code'				=>	$this->cur_code,
                'function'				=> 'SetExpressCheckout',
                'version'				=> $this->version,
                'merchant_id'			=> $this->merchant_id, //Mã merchant khai báo tại NganLuong.vn
                'receiver_email'		=> $this->receiver_email,
                'merchant_password'		=> MD5($this->merchant_password), //MD5(Mật khẩu kết nối giữa merchant và NganLuong.vn)
                'order_code'			=> $order_code, //Mã hóa đơn do website bán hàng sinh ra
                'total_amount'			=> $total_amount, //Tổng số tiền của hóa đơn
                'payment_method'		=> 'ATM_ONLINE', //Phương thức thanh toán, nhận một trong các giá trị 'ATM_ONLINE', 'ATM_OFFLINE' hoặc 'NH_OFFLINE'
                'bank_code'				=> $bank_code, //Mã Ngân hàng
                'payment_type'			=> $payment_type, //Kiểu giao dịch: 1 - Ngay; 2 - Tạm giữ; Nếu không truyền hoặc bằng rỗng thì lấy theo chính sách của NganLuong.vn
                'order_description'		=> $order_description, //Mô tả đơn hàng
                'tax_amount'			=> $tax_amount, //Tổng số tiền thuế
                'fee_shipping'			=> $fee_shipping, //Phí vận chuyển
                'discount_amount'		=> $discount_amount, //Số tiền giảm giá
                'return_url'			=> $return_url, //Địa chỉ website nhận thông báo giao dịch thành công
                'cancel_url'			=> $cancel_url, //Địa chỉ website nhận "Hủy giao dịch"
                'buyer_fullname'		=> $buyer_fullname, //Tên người mua hàng
                'buyer_email'			=> $buyer_email, //Địa chỉ Email người mua
                'buyer_mobile'			=> $buyer_mobile, //Điện thoại người mua
                'buyer_address'			=> $buyer_address, //Địa chỉ người mua hàng
                'total_item'			=> count($array_items)
            );

            $post_field = '';
            foreach ($params as $key => $value){
                if ($post_field != '') $post_field .= '&';
                $post_field .= $key."=".$value;
            }
            if(count($array_items)>0){
                foreach($array_items as $array_item){
                    foreach ($array_item as $key => $value){
                        if ($post_field != '') $post_field .= '&';
                        $post_field .= $key."=".$value;
                    }
                }
            }
            //$post_field="function=SetExpressCheckout&version=3.1&merchant_id=24338&receiver_email=payment@hellochao.com&merchant_password=5b39df2b8f3275d1c8d1ea982b51b775&order_code=macode_oerder123&total_amount=2000&payment_method=ATM_ONLINE&bank_code=ICB&payment_type=&order_description=&tax_amount=0&fee_shipping=0&discount_amount=0&return_url=http://localhost/testcode/nganluong.vn/checkoutv3/payment_success.php&cancel_url=http://nganluong.vn&buyer_fullname=Test&buyer_email=saritvn@gmail.com&buyer_mobile=0909224002&buyer_address=&total_item=1&item_name1=Product name&item_quantity1=1&item_amount1=2000&item_url1=http://nganluong.vn/"	;
            //echo $post_field;
            //die;
            $nl_result=$this->CheckoutCall($post_field);

            return $nl_result;
        }

        function BankOfflineCheckout($order_code,$total_amount,$bank_code,$payment_type,$order_description,$tax_amount,
                                     $fee_shipping,$discount_amount,$return_url,$cancel_url,$buyer_fullname,$buyer_email,$buyer_mobile,
                                     $buyer_address,$array_items)
        {
            $params = array(
                'cur_code'				=>	$this->cur_code,
                'function'				=> 'SetExpressCheckout',
                'version'				=> $this->version,
                'merchant_id'			=> $this->merchant_id, //Mã merchant khai báo tại NganLuong.vn
                'receiver_email'		=> $this->receiver_email,
                'merchant_password'		=> MD5($this->merchant_password), //MD5(Mật khẩu kết nối giữa merchant và NganLuong.vn)
                'order_code'			=> $order_code, //Mã hóa đơn do website bán hàng sinh ra
                'total_amount'			=> $total_amount, //Tổng số tiền của hóa đơn
                'payment_method'		=> 'ATM_OFFLINE', //Phương thức thanh toán, nhận một trong các giá trị 'ATM_ONLINE', 'ATM_OFFLINE' hoặc 'NH_OFFLINE'
                'bank_code'				=> $bank_code, //Mã Ngân hàng
                'payment_type'			=> $payment_type, //Kiểu giao dịch: 1 - Ngay; 2 - Tạm giữ; Nếu không truyền hoặc bằng rỗng thì lấy theo chính sách của NganLuong.vn
                'order_description'		=> $order_description, //Mô tả đơn hàng
                'tax_amount'			=> $tax_amount, //Tổng số tiền thuế
                'fee_shipping'			=> $fee_shipping, //Phí vận chuyển
                'discount_amount'		=> $discount_amount, //Số tiền giảm giá
                'return_url'			=> $return_url, //Địa chỉ website nhận thông báo giao dịch thành công
                'cancel_url'			=> $cancel_url, //Địa chỉ website nhận "Hủy giao dịch"
                'buyer_fullname'		=> $buyer_fullname, //Tên người mua hàng
                'buyer_email'			=> $buyer_email, //Địa chỉ Email người mua
                'buyer_mobile'			=> $buyer_mobile, //Điện thoại người mua
                'buyer_address'			=> $buyer_address, //Địa chỉ người mua hàng
                'total_item'			=> count($array_items)
            );

            $post_field = '';
            foreach ($params as $key => $value){
                if ($post_field != '') $post_field .= '&';
                $post_field .= $key."=".$value;
            }
            if(count($array_items)>0){
                foreach($array_items as $array_item){
                    foreach ($array_item as $key => $value){
                        if ($post_field != '') $post_field .= '&';
                        $post_field .= $key."=".$value;
                    }
                }
            }
            //$post_field="function=SetExpressCheckout&version=3.1&merchant_id=24338&receiver_email=payment@hellochao.com&merchant_password=5b39df2b8f3275d1c8d1ea982b51b775&order_code=macode_oerder123&total_amount=2000&payment_method=ATM_ONLINE&bank_code=ICB&payment_type=&order_description=&tax_amount=0&fee_shipping=0&discount_amount=0&return_url=http://localhost/testcode/nganluong.vn/checkoutv3/payment_success.php&cancel_url=http://nganluong.vn&buyer_fullname=Test&buyer_email=saritvn@gmail.com&buyer_mobile=0909224002&buyer_address=&total_item=1&item_name1=Product name&item_quantity1=1&item_amount1=2000&item_url1=http://nganluong.vn/"	;
            //echo $post_field;
            //die;
            $nl_result=$this->CheckoutCall($post_field);

            return $nl_result;
        }


        function officeBankCheckout($order_code,$total_amount,$bank_code,$payment_type,$order_description,$tax_amount,
                                    $fee_shipping,$discount_amount,$return_url,$cancel_url,$buyer_fullname,$buyer_email,$buyer_mobile,
                                    $buyer_address,$array_items)
        {
            $params = array(
                'cur_code'				=> $this->cur_code,
                'function'				=> 'SetExpressCheckout',
                'version'				=> $this->version,
                'merchant_id'			=> $this->merchant_id, //Mã merchant khai báo tại NganLuong.vn
                'receiver_email'		=> $this->receiver_email,
                'merchant_password'		=> MD5($this->merchant_password), //MD5(Mật khẩu kết nối giữa merchant và NganLuong.vn)
                'order_code'			=> $order_code, //Mã hóa đơn do website bán hàng sinh ra
                'total_amount'			=> $total_amount, //Tổng số tiền của hóa đơn
                'payment_method'		=> 'NH_OFFLINE', //Phương thức thanh toán, nhận một trong các giá trị 'ATM_ONLINE', 'ATM_OFFLINE' hoặc 'NH_OFFLINE'
                'bank_code'				=> $bank_code, //Mã Ngân hàng
                'payment_type'			=> $payment_type, //Kiểu giao dịch: 1 - Ngay; 2 - Tạm giữ; Nếu không truyền hoặc bằng rỗng thì lấy theo chính sách của NganLuong.vn
                'order_description'		=> $order_description, //Mô tả đơn hàng
                'tax_amount'			=> $tax_amount, //Tổng số tiền thuế
                'fee_shipping'			=> $fee_shipping, //Phí vận chuyển
                'discount_amount'		=> $discount_amount, //Số tiền giảm giá
                'return_url'			=> $return_url, //Địa chỉ website nhận thông báo giao dịch thành công
                'cancel_url'			=> $cancel_url, //Địa chỉ website nhận "Hủy giao dịch"
                'buyer_fullname'		=> $buyer_fullname, //Tên người mua hàng
                'buyer_email'			=> $buyer_email, //Địa chỉ Email người mua
                'buyer_mobile'			=> $buyer_mobile, //Điện thoại người mua
                'buyer_address'			=> $buyer_address, //Địa chỉ người mua hàng
                'total_item'			=> count($array_items)
            );

            $post_field = '';
            foreach ($params as $key => $value){
                if ($post_field != '') $post_field .= '&';
                $post_field .= $key."=".$value;
            }
            if(count($array_items)>0){
                foreach($array_items as $array_item){
                    foreach ($array_item as $key => $value){
                        if ($post_field != '') $post_field .= '&';
                        $post_field .= $key."=".$value;
                    }
                }
            }
            //$post_field="function=SetExpressCheckout&version=3.1&merchant_id=24338&receiver_email=payment@hellochao.com&merchant_password=5b39df2b8f3275d1c8d1ea982b51b775&order_code=macode_oerder123&total_amount=2000&payment_method=ATM_ONLINE&bank_code=ICB&payment_type=&order_description=&tax_amount=0&fee_shipping=0&discount_amount=0&return_url=http://localhost/testcode/nganluong.vn/checkoutv3/payment_success.php&cancel_url=http://nganluong.vn&buyer_fullname=Test&buyer_email=saritvn@gmail.com&buyer_mobile=0909224002&buyer_address=&total_item=1&item_name1=Product name&item_quantity1=1&item_amount1=2000&item_url1=http://nganluong.vn/"	;
            //echo $post_field;
            //die;
            $nl_result=$this->CheckoutCall($post_field);

            return $nl_result;
        }

        /*

        Hàm lấy link thanh toán tại văn phòng ngân lượng

        ===============================
        Tham số truyền vào bắt buộc phải có
                    order_code
                    total_amount
                    bank_code // HN hoặc HCM

                    buyer_fullname
                    buyer_email
                    buyer_mobile
        ===============================

            $array_items mảng danh sách các item name theo quy tắc
            item_name1
            item_quantity1
            item_amount1
            item_url1
            .....
            payment_type Kiểu giao dịch: 1 - Ngay; 2 - Tạm giữ; Nếu không truyền hoặc bằng rỗng thì lấy theo chính sách của NganLuong.vn

        */
        function TTVPCheckout($order_code,$total_amount,$bank_code,$payment_type,$order_description,$tax_amount,
                              $fee_shipping,$discount_amount,$return_url,$cancel_url,$buyer_fullname,$buyer_email,$buyer_mobile,
                              $buyer_address,$array_items)
        {
            $params = array(
                'cur_code'			=>	$this->cur_code,
                'function'				=> 'SetExpressCheckout',
                'version'				=> $this->version,
                'merchant_id'			=> $this->merchant_id, //Mã merchant khai báo tại NganLuong.vn
                'receiver_email'		=> $this->receiver_email,
                'merchant_password'		=> MD5($this->merchant_password), //MD5(Mật khẩu kết nối giữa merchant và NganLuong.vn)
                'order_code'			=> $order_code, //Mã hóa đơn do website bán hàng sinh ra
                'total_amount'			=> $total_amount, //Tổng số tiền của hóa đơn
                'payment_method'		=> 'ATM_ONLINE', //Phương thức thanh toán, nhận một trong các giá trị 'ATM_ONLINE', 'ATM_OFFLINE' hoặc 'NH_OFFLINE'
                'bank_code'				=> $bank_code, //Mã Ngân hàng
                'payment_type'			=> $payment_type, //Kiểu giao dịch: 1 - Ngay; 2 - Tạm giữ; Nếu không truyền hoặc bằng rỗng thì lấy theo chính sách của NganLuong.vn
                'order_description'		=> $order_description, //Mô tả đơn hàng
                'tax_amount'			=> $tax_amount, //Tổng số tiền thuế
                'fee_shipping'			=> $fee_shipping, //Phí vận chuyển
                'discount_amount'		=> $discount_amount, //Số tiền giảm giá
                'return_url'			=> $return_url, //Địa chỉ website nhận thông báo giao dịch thành công
                'cancel_url'			=> $cancel_url, //Địa chỉ website nhận "Hủy giao dịch"
                'buyer_fullname'		=> $buyer_fullname, //Tên người mua hàng
                'buyer_email'			=> $buyer_email, //Địa chỉ Email người mua
                'buyer_mobile'			=> $buyer_mobile, //Điện thoại người mua
                'buyer_address'			=> $buyer_address, //Địa chỉ người mua hàng
                'total_item'			=> count($array_items)
            );

            $post_field = '';
            foreach ($params as $key => $value){
                if ($post_field != '') $post_field .= '&';
                $post_field .= $key."=".$value;
            }
            if(count($array_items)>0){
                foreach($array_items as $array_item){
                    foreach ($array_item as $key => $value){
                        if ($post_field != '') $post_field .= '&';
                        $post_field .= $key."=".$value;
                    }
                }
            }

            $nl_result=$this->CheckoutCall($post_field);
            return $nl_result;
        }

        /*

        Hàm lấy link thanh toán dùng số dư ví ngân lượng
        ===============================
        Tham số truyền vào bắt buộc phải có
                    order_code
                    total_amount
                    payment_method

                    buyer_fullname
                    buyer_email
                    buyer_mobile
        ===============================
            $array_items mảng danh sách các item name theo quy tắc
            item_name1
            item_quantity1
            item_amount1
            item_url1
            .....

            payment_type Kiểu giao dịch: 1 - Ngay; 2 - Tạm giữ; Nếu không truyền hoặc bằng rỗng thì lấy theo chính sách của NganLuong.vn
         */
        function NLCheckout($order_code,$total_amount,$payment_type,$order_description,$tax_amount,
                            $fee_shipping,$discount_amount,$return_url,$cancel_url,$buyer_fullname,$buyer_email,$buyer_mobile,
                            $buyer_address,$array_items)
        {
            $params = array(
                'cur_code'				=> $this->cur_code,
                'function'				=> 'SetExpressCheckout',
                'version'				=> $this->version,
                'merchant_id'			=> $this->merchant_id, //Mã merchant khai báo tại NganLuong.vn
                'receiver_email'		=> $this->receiver_email,
                'merchant_password'		=> MD5($this->merchant_password), //MD5(Mật khẩu kết nối giữa merchant và NganLuong.vn)
                'order_code'			=> $order_code, //Mã hóa đơn do website bán hàng sinh ra
                'total_amount'			=> $total_amount, //Tổng số tiền của hóa đơn
                'payment_method'		=> 'NL', //Phương thức thanh toán
                'payment_type'			=> $payment_type, //Kiểu giao dịch: 1 - Ngay; 2 - Tạm giữ; Nếu không truyền hoặc bằng rỗng thì lấy theo chính sách của NganLuong.vn
                'order_description'		=> $order_description, //Mô tả đơn hàng
                'tax_amount'			=> $tax_amount, //Tổng số tiền thuế
                'fee_shipping'			=> $fee_shipping, //Phí vận chuyển
                'discount_amount'		=> $discount_amount, //Số tiền giảm giá
                'return_url'			=> $return_url, //Địa chỉ website nhận thông báo giao dịch thành công
                'cancel_url'			=> $cancel_url, //Địa chỉ website nhận "Hủy giao dịch"
                'buyer_fullname'		=> $buyer_fullname, //Tên người mua hàng
                'buyer_email'			=> $buyer_email, //Địa chỉ Email người mua
                'buyer_mobile'			=> $buyer_mobile, //Điện thoại người mua
                'buyer_address'			=> $buyer_address, //Địa chỉ người mua hàng
                'total_item'			=> count($array_items) //Tổng số sản phẩm trong đơn hàng
            );
            $post_field = '';
            foreach ($params as $key => $value){
                if ($post_field != '') $post_field .= '&';
                $post_field .= $key."=".$value;
            }
            if(count($array_items)>0){
                foreach($array_items as $array_item){
                    foreach ($array_item as $key => $value){
                        if ($post_field != '') $post_field .= '&';
                        $post_field .= $key."=".$value;
                    }
                }
            }

            //die($post_field);
            $nl_result=$this->CheckoutCall($post_field);
            return $nl_result;
        }

        function IBCheckout($order_code, $total_amount, $bank_code, $payment_type, $order_description, $tax_amount, $fee_shipping, $discount_amount, $return_url, $cancel_url, $buyer_fullname, $buyer_email, $buyer_mobile, $buyer_address, $array_items) {
            $params = array(
                'cur_code' => $this->cur_code,
                'function' => 'SetExpressCheckout',
                'version' => $this->version,
                'merchant_id' => $this->merchant_id, //Mã merchant khai báo tại NganLuong.vn
                'receiver_email' => $this->receiver_email,
                'merchant_password' => MD5($this->merchant_password), //MD5(Mật khẩu kết nối giữa merchant và NganLuong.vn)
                'order_code' => $order_code, //Mã hóa đơn do website bán hàng sinh ra
                'total_amount' => $total_amount, //Tổng số tiền của hóa đơn
                'payment_method' => 'IB_ONLINE', //Phương thức thanh toán
                'bank_code' => $bank_code,
                'payment_type' => $payment_type, //Kiểu giao dịch: 1 - Ngay; 2 - Tạm giữ; Nếu không truyền hoặc bằng rỗng thì lấy theo chính sách của NganLuong.vn
                'order_description' => $order_description, //Mô tả đơn hàng
                'tax_amount' => $tax_amount, //Tổng số tiền thuế
                'fee_shipping' => $fee_shipping, //Phí vận chuyển
                'discount_amount' => $discount_amount, //Số tiền giảm giá
                'return_url' => $return_url, //Địa chỉ website nhận thông báo giao dịch thành công
                'cancel_url' => $cancel_url, //Địa chỉ website nhận "Hủy giao dịch"
                'buyer_fullname' => $buyer_fullname, //Tên người mua hàng
                'buyer_email' => $buyer_email, //Địa chỉ Email người mua
                'buyer_mobile' => $buyer_mobile, //Điện thoại người mua
                'buyer_address' => $buyer_address, //Địa chỉ người mua hàng
                'total_item' => count($array_items) //Tổng số sản phẩm trong đơn hàng
            );
            $post_field = '';
            foreach ($params as $key => $value) {
                if ($post_field != '')
                    $post_field .= '&';
                $post_field .= $key . "=" . $value;
            }
            if (count($array_items) > 0) {
                foreach ($array_items as $array_item) {
                    foreach ($array_item as $key => $value) {
                        if ($post_field != '')
                            $post_field .= '&';
                        $post_field .= $key . "=" . $value;
                    }
                }
            }

            //die($post_field);
            $nl_result = $this->CheckoutCall($post_field);
            return $nl_result;
        }

        function CheckoutCall($post_field){

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL,$this->url_api);
            curl_setopt($ch, CURLOPT_ENCODING , 'UTF-8');
            curl_setopt($ch, CURLOPT_VERBOSE, 1);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_field);
            $result = curl_exec($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);

            if ($result != '' && $status==200){
                $xml_result = str_replace('&','&amp;',(string)$result);
                $nl_result  = simplexml_load_string($xml_result);
                $nl_result->error_message = $this->GetErrorMessage($nl_result->error_code);
            }
            else (isset($nl_result)) ? ($nl_result->error_message = $error) : '';
            return $nl_result;

        }

        function GetErrorMessage($error_code) {
            $arrCode = array(
                '00' => 'Thành công',
                '99' => 'Lỗi chưa xác minh',
                '06' => 'Mã merchant không tồn tại hoặc bị khóa',
                '02' => 'Địa chỉ IP truy cập bị từ chối',
                '03' => 'Mã checksum không chính xác, truy cập bị từ chối',
                '04' => 'Tên hàm API do merchant gọi tới không hợp lệ (không tồn tại)',
                '05' => 'Sai version của API',
                '07' => 'Sai mật khẩu của merchant',
                '08' => 'Địa chỉ email tài khoản nhận tiền không tồn tại',
                '09' => 'Tài khoản nhận tiền đang bị phong tỏa giao dịch',
                '10' => 'Mã đơn hàng không hợp lệ',
                '11' => 'Số tiền giao dịch lớn hơn hoặc nhỏ hơn quy định',
                '12' => 'Loại tiền tệ không hợp lệ',
                '29' => 'Token không tồn tại',
                '80' => 'Không thêm được đơn hàng',
                '81' => 'Đơn hàng chưa được thanh toán',
                '110' => 'Địa chỉ email tài khoản nhận tiền không phải email chính',
                '111' => 'Tài khoản nhận tiền đang bị khóa',
                '113' => 'Tài khoản nhận tiền chưa cấu hình là người bán nội dung số',
                '114' => 'Giao dịch đang thực hiện, chưa kết thúc',
                '115' => 'Giao dịch bị hủy',
                '118' => 'tax_amount không hợp lệ',
                '119' => 'discount_amount không hợp lệ',
                '120' => 'fee_shipping không hợp lệ',
                '121' => 'return_url không hợp lệ',
                '122' => 'cancel_url không hợp lệ',
                '123' => 'items không hợp lệ',
                '124' => 'transaction_info không hợp lệ',
                '125' => 'quantity không hợp lệ',
                '126' => 'order_description không hợp lệ',
                '127' => 'affiliate_code không hợp lệ',
                '128' => 'time_limit không hợp lệ',
                '129' => 'buyer_fullname không hợp lệ',
                '130' => 'buyer_email không hợp lệ',
                '131' => 'buyer_mobile không hợp lệ',
                '132' => 'buyer_address không hợp lệ',
                '133' => 'total_item không hợp lệ',
                '134' => 'payment_method, bank_code không hợp lệ',
                '135' => 'Lỗi kết nối tới hệ thống ngân hàng',
                '140' => 'Đơn hàng không hỗ trợ thanh toán trả góp',);

            return $arrCode[(string)$error_code];
        }
    }
    /*
     *  End Class NganLuong provided
    */
}


function woocommerce_add_NganLuong_gateway($methods) {
    $methods[] = 'WC_Gateway_NganLuongV3';
    return $methods;
}

add_filter('woocommerce_payment_gateways', 'woocommerce_add_NganLuong_gateway');