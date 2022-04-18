<?php
use Carbon_Fields\Container;
use Carbon_Fields\Field;
use SejoliSA\Admin\Product as AdminProduct;
use SejoliSA\JSON\Product;
use SejoliSA\Model\Affiliate;
use Illuminate\Database\Capsule\Manager as Capsule;

final class SejoliToyyibpay extends \SejoliSA\Payment{

    /**
     * Prevent double method calling
     * @since   1.0.0
     * @access  protected
     * @var     boolean
     */
    protected $is_called = false;

    /**
     * Redirect urls
     * @since   1.0.0
     * @var     array
     */
    public $base_url = array(
        'sandbox' => 'https://dev.toyyibpay.com/',
        'live'    => 'https://toyyibpay.com/'
    );

    /**
     * Order price
     * @since 1.0.0
     * @var float
     */
    protected $order_price = 0.0;

    /**
     * Method options
     * @since   1.0.0
     * @var     array
     */
    protected $channel_options = array();

    /**
     * Table name
     * @since 1.0.0
     * @var string
     */
    protected $table = 'sejolisa_toyyibpay_transaction';

    /**
     * Construction
     */
    public function __construct() {
        
        global $wpdb;

        $this->id          = 'toyyibpay';
        $this->name        = __( 'Toyyibpay', 'sejoli-toyyibpay' );
        $this->title       = __( 'Toyyibpay', 'sejoli-toyyibpay' );
        $this->description = __( 'Transaksi via Toyyibpay Payment Gateway.', 'sejoli-toyyibpay' );
        $this->table       = $wpdb->prefix . $this->table;

        $this->channel_options = array(
            '0' => __('FPX only', 'sejoli-toyyibpay'),
            '1' => __('Credit/Debit Card only', 'sejoli-toyyibpay'),
            '2' => __('FPX and Credit/Debit Card', 'sejoli-toyyibpay')
        );

        $this->transaction_charges = array(
            '0' => __('Charge included in bill amount', 'sejoli-toyyibpay'),
            '1' => __('Charge the FPX (online banking) charges to the customer', 'sejoli-toyyibay'),
            '2' => __('Charge the credit card charges to the customer', 'sejoli-toyyibpay'),
            '3' => __('Charge both FPX and credit card charges to the customer', 'sejoli-toyyibpay')
        );

        add_action('admin_init',                     [$this, 'register_trx_table'],  1);
        add_filter('sejoli/payment/payment-options', [$this, 'add_payment_options']);
        add_filter('query_vars',                     [$this, 'set_query_vars'],     999);
        add_action('sejoli/thank-you/render',        [$this, 'check_for_redirect'], 1);
        add_action('init',                           [$this, 'set_endpoint'],       1);
        add_action('parse_query',                    [$this, 'check_parse_query'],  100);

    }

    /**
     * Register transaction table
     * Hooked via action admin_init, priority 1
     * @since   1.0.0
     * @return  void
     */
    public function register_trx_table() {

        if( !Capsule::schema()->hasTable( $this->table ) ):

            Capsule::schema()->create( $this->table, function( $table ) {
                $table->increments('ID');
                $table->datetime('created_at');
                $table->datetime('last_check')->default('0000-00-00 00:00:00');
                $table->integer('order_id');
                $table->string('status');
                $table->text('detail')->nullable();
            });

        endif;

    }

    /**
     * Get duitku order data
     * @since   1.0.0
     * @param   int $order_id
     * @return  false|object
     */
    protected function check_data_table( int $order_id ) {

        return Capsule::table($this->table)
            ->where(array(
                'order_id'  => $order_id
            ))
            ->first();

    }

    /**
     * Add transaction data
     * @since   1.0.0
     * @param   integer $order_id Order ID
     * @return  void
     */
    protected function add_to_table( int $order_id ) {

        Capsule::table($this->table)
            ->insert([
                'created_at' => current_time('mysql'),
                'last_check' => '0000-00-00 00:00:00',
                'order_id'   => $order_id,
                'status'     => 'pending'
            ]);
    
    }

    /**
     * Update data status
     * @since   1.0.0
     * @param   integer $order_id [description]
     * @param   string $status [description]
     * @return  void
     */
    protected function update_status( $order_id, $status ) {
        
        Capsule::table($this->table)
            ->where(array(
                'order_id' => $order_id
            ))
            ->update(array(
                'status'    => $status,
                'last_check'=> current_time('mysql')
            ));

    }

    /**
     * Update data detail payload
     * @since   1.0.0
     * @param   integer $order_id [description]
     * @param   array $detail [description]
     * @return  void
     */
    protected function update_detail( $order_id, $detail ) {
        
        Capsule::table($this->table)
            ->where(array(
                'order_id' => $order_id
            ))
            ->update(array(
                'detail' => serialize($detail),
            ));

    }

    /**
     *  Set end point custom menu
     *  Hooked via action init, priority 999
     *  @since   1.0.0
     *  @access  public
     *  @return  void
     */
    public function set_endpoint() {
        
        add_rewrite_rule( '^toyyibpay/([^/]*)/?', 'index.php?toyyibpay-method=1&action=$matches[1]', 'top' );

        flush_rewrite_rules();
    
    }

    /**
     * Set custom query vars
     * Hooked via filter query_vars, priority 100
     * @since   1.0.0
     * @access  public
     * @param   array $vars
     * @return  array
     */
    public function set_query_vars( $vars ) {

        $vars[] = 'toyyibpay-method';

        return $vars;
    
    }

    /**
     * Check parse query and if duitku-method exists and process
     * Hooked via action parse_query, priority 999
     * @since 1.0.0
     * @access public
     * @return void
     */
    public function check_parse_query() {

        global $wp_query;

        if( is_admin() || $this->is_called ) :

            return;

        endif;

        if(
            isset( $wp_query->query_vars['toyyibpay-method'] ) &&
            isset( $wp_query->query_vars['action'] ) && !empty( $wp_query->query_vars['action'] )
        ) :

            if( 'process' === $wp_query->query_vars['action'] ) :

                $this->is_called = true;
                $this->process_callback();

            elseif( 'return' === $wp_query->query_vars['action'] ) :

                $this->is_called = true;
                $this->receive_return();

            endif;

        endif;

    }

    /**
     * Set option in Sejoli payment options, we use CARBONFIELDS for plugin options
     * Called from parent method
     * @since   1.0.0
     * @return  array
     */
    public function get_setup_fields() {

        return array(

            Field::make('separator', 'sep_toyyibpay_transaction_setting', __('Pengaturan Toyyibpay', 'sejoli-toyyibpay')),

            Field::make('checkbox', 'toyyibpay_active', __('Aktifkan pembayaran melalui Toyyibpay', 'sejoli-toyyibpay')),
            
            Field::make('select', 'toyyibpay_mode', __('Payment Mode', 'sejoli-toyyibpay'))
            ->set_options(array(
                'sandbox' => __('Sandbox', 'sejoli-toyyibpay'),
                'live'    => __('Live', 'sejoli-toyyibpay'),
            ))
            ->set_conditional_logic(array(
                array(
                    'field' => 'toyyibpay_active',
                    'value' => true
                )
            )),

            Field::make('text', 'toyyibpay_secreet_key_sandbox', __('Secreet Key Sandbox', 'sejoli-toyyibpay'))
            ->set_required(true)
            ->set_help_text(__('Obtain your secret key from your toyyibPay dashboard.', 'sejoli-toyyibpay'))
            ->set_conditional_logic(array(
                array(
                    'field' => 'toyyibpay_active',
                    'value' => true
                ),array(
                    'field' => 'toyyibpay_mode',
                    'value' => 'sandbox'
                )
            )),

            Field::make('text', 'toyyibpay_secreet_key_live', __('Secreet Key Live', 'sejoli-toyyibpay'))
            ->set_required(true)
            ->set_help_text(__('Obtain your secret key from your toyyibPay dashboard.', 'sejoli-toyyibpay'))
            ->set_conditional_logic(array(
                array(
                    'field' => 'toyyibpay_active',
                    'value' => true
                ),array(
                    'field' => 'toyyibpay_mode',
                    'value' => 'live'
                )
            )),

            Field::make('text', 'toyyibpay_category_code', __('Category Code', 'sejoli-toyyibpay'))
            ->set_required(true)
            ->set_help_text(__('Create a category at your toyyibPay dashboard and fill in your category code here.', 'sejoli-toyyibpay'))
            ->set_conditional_logic(array(
                array(
                    'field' => 'toyyibpay_active',
                    'value' => true
                )
            )),

            Field::make('select', 'toyyibpay_payment_channel', __('Payment Channel', 'sejoli-toyyibpay'))
            ->set_required(true)
            ->set_help_text(__('Choose your preferred payment channel - FPX and/or credit cards.', 'sejoli-toyyibpay'))
            ->set_options($this->channel_options)
            ->set_conditional_logic(array(
                array(
                    'field' => 'toyyibpay_active',
                    'value' => true
                )
            )),

            Field::make('select', 'toyyibpay_transaction_charges', __('Transaction Charges', 'sejoli-toyyibpay'))
            ->set_required(true)
            ->set_help_text(__('Choose payer for transaction charges.', 'sejoli-toyyibpay'))
            ->set_options($this->transaction_charges)
            ->set_conditional_logic(array(
                array(
                    'field' => 'toyyibpay_active',
                    'value' => true
                )
            )),

            Field::make('textarea', 'toyyibpay_extra_email_content', __('Extra e-mail content (Optional)', 'sejoli-toyyibpay'))
            ->set_required(false)
            ->set_help_text(__('Content of additional e-mail to be sent to your customers (Optional - leave this blank if you are not sure what to write).', 'sejoli-toyyibpay'))
            ->set_conditional_logic(array(
                array(
                    'field' => 'toyyibpay_active',
                    'value' => true
                )
            )),

            Field::make('text', 'toyyibpay_inv_prefix', __('Invoice Prefix', 'sejoli-toyyibpay'))
            ->set_required(true)
            ->set_default_value('sjl1')
            ->set_help_text(__('Maksimal 6 Karakter', 'sejoli-toyyibpay'))
            ->set_conditional_logic(array(
                array(
                    'field' => 'toyyibpay_active',
                    'value' => true
                )
            )),

        );

    }

    /**
     * Display toyyibpay payment options in checkout page
     * Hooked via filter sejoli/payment/payment-options, priority 100
     * @since   1.0.0
     * @param   array $options
     * @return  array
     */
    public function add_payment_options( array $options ) {
        
        $active = boolval( carbon_get_theme_option('toyyibpay_active') );

        if( true === $active ) :

            $channels         = carbon_get_theme_option('toyyibpay_payment_channel');
            $image_source_url = plugin_dir_url(__FILE__);

            foreach( (array) $channels as $_channel ) :

                $key = 'toyyibpay:::'.$_channel;

                switch($_channel) :

                    case '0' :
                        $options[$key] = [
                            'label' => $this->channel_options[$_channel],
                            'image' => $image_source_url.'img/hor-fpx.png'
                        ];
                        break;

                    case '1' :
                        $options[$key] = [
                            'label' => $this->channel_options[$_channel],
                            'image' => $image_source_url.'img/hor-all.png'
                        ];
                        break;

                    case '2' :
                        $options[$key] = [
                            'label' => $this->channel_options[$_channel],
                            'image' => $image_source_url.'img/hor-all.png'
                        ];
                        break;

                endswitch;

            endforeach;

        endif;

        return $options;

    }

    /**
     * Set order price if there is any fee need to be added
     * @since   1.0.0
     * @param   float $price
     * @param   array $order_data
     * @return  float
     */
    public function set_price( float $price, array $order_data ) {

        if( 0.0 !== $price ) :

            $this->order_price = $price;

            return floatval( $this->order_price );

        endif;

        return $price;

    }

    /**
     * Get setup values
     * @return array
     */
    protected function get_setup_values() {

        $mode                = carbon_get_theme_option('toyyibpay_mode');
        $secret_key          = trim( carbon_get_theme_option('toyyibpay_secreet_key_'.$mode) );
        $category_code       = trim( carbon_get_theme_option('toyyibpay_category_code') );
        $payment_channels    = carbon_get_theme_option('toyyibpay_payment_channel');
        $transaction_charges = carbon_get_theme_option('toyyibpay_transaction_charges');
        $extra_email_content = carbon_get_theme_option('toyyibpay_extra_email_content');
        $base_url            = $this->base_url[$mode];

        return array(
            'mode'                => $mode,
            'secret_key'          => $secret_key,
            'category_code'       => $category_code,
            'payment_channels'    => $payment_channels,
            'transaction_charges' => $transaction_charges,
            'extra_email_content' => $extra_email_content,
            'base_url'            => $base_url
        );

    }

    /**
     * Set order meta data
     * @since   1.0.0
     * @param   array $meta_data
     * @param   array $order_data
     * @param   array $payment_subtype
     * @return  array
     */
    public function set_meta_data( array $meta_data, array $order_data, $payment_subtype ) {

        $meta_data['toyyibpay'] = [
            'trans_id'   => '',
            'unique_key' => substr( md5( rand( 0,1000 ) ), 0, 16 ),
            'method'     => $payment_subtype
        ];

        return $meta_data;

    }

    /**
     * Prepare Paypal Data
     * @since   1.0.0
     * @return  array
     */
    public function prepare_toyyibpay_data( array $order ) {

        extract( $this->get_setup_values() );

        $redirect_link        = '';
        $request_to_toyyibpay = false;
        $data_order           = $this->check_data_table( $order['ID'] );
        $request_url          = $base_url.'index.php/api/createBill';
        $redirect_url         = $base_url;
        $payment_amount       = (int) $order['grand_total'];
        $merchant_order_ID    = $order['ID'];
        $signature            = md5( $order['ID'] . $merchant_order_ID . $payment_amount . $secret_key );

        if( NULL === $data_order ) :
            
            $request_to_toyyibpay = true;
        
        else :

            $detail = unserialize( $data_order->detail );

            if( !isset( $detail['url'] ) || empty( $detail['url'] ) ) :
                $request_to_toyyibpay = true;
            else :
                $redirect_link = $redirect_url.$detail['BillCode'];
            endif;

        endif;


        if( true === $request_to_toyyibpay ) :

            $this->add_to_table( $order['ID'] );

            if ( !empty( $secret_key ) ) {

                $params = array(
                    'userSecretKey'           => $secret_key,
                    'categoryCode'            => $category_code,
                    'billName'                => __('Order No ', 'sejoli-toyyibpay') . $order['ID'],
                    'billDescription'         => __('Payment for Order No ', 'sejoli-toyyibpay') . $order['ID'],
                    'billPriceSetting'        =>  1,
                    'billPayorInfo'           =>  1,
                    'billAmount'              =>  1 * 100, //$payment_amount * 100,
                    'billReturnUrl'           =>  add_query_arg(array(
                                                    'order_id'   => $order['ID'],
                                                    'unique_key' => $order['meta_data']['toyyibpay']['unique_key']
                                                ), site_url('/toyyibpay/return')),
                    'billCallbackUrl'         =>  add_query_arg(array(
                                                    'order_id'   => $order['ID'],
                                                    'unique_key' => $order['meta_data']['toyyibpay']['unique_key']
                                                ), site_url('/toyyibpay/process')),
                    'billExternalReferenceNo' =>  $order['ID'],
                    'billTo'                  =>  $order['user']->display_name,
                    'billEmail'               =>  $order['user']->user_email,
                    'billPhone'               =>  $order['user']->meta->phone,
                    'billPaymentChannel'      =>  $payment_channels,
                    'billDisplayMerchant'     =>  1,
                    'billContentEmail'        =>  $extra_email_content,
                    'billChargeToCustomer'    =>  $transaction_charges,
                    'billASPCode'             =>  'toyyibPay-V1-WCV1.3.1'
                );

                $executeTransaction = $this->executeTransaction( $request_url, $params );
                $billCode           = $executeTransaction[0]['BillCode'];

                if ( $billCode !== NULL ) {

                    $http_code = 200;

                } else {
                    
                    $http_code = 400;
                
                }

                if( 200 === $http_code ) :

                    do_action( 'sejoli/log/write', 'success-toyyibpay', $executeTransaction );

                    $this->update_detail( $order['ID'], $executeTransaction );
                    $redirect_link = $redirect_url.$billCode;

                else :

                    do_action( 'sejoli/log/write', 'error-toyyibpay', array( $executeTransaction, $http_code, $params ) );

                    $msg = $executeTransaction[0]['msg'];

                    if ( $msg === NULL ) {
                        
                        wp_die(
                            __('Error!<br>Please check the following : ' . $executeTransaction[0], 'sejoli-toyyibpay'),
                            __('Error!', 'sejoli-toyyibpay')
                        );

                    } else {
                        
                        wp_die(
                            __('Error!<br>Please check the following : ' . $msg, 'sejoli-toyyibpay'),
                            __('Error!', 'sejoli-toyyibpay')
                        );
                        
                    }

                    exit;
            
                endif;

            }

        endif;

        wp_redirect( $redirect_link );

        exit;

    }

    /**
     * Receive return process
     * @since   1.0.0
     * @return  void
     */
    protected function receive_return() {

        $args = wp_parse_args($_GET, array(
            'status_id'      => NULL,
            'billcode'       => NULL,
            'order_id'       => NULL,
            'msg'            => NULL,
            'transaction_id' => NULL
        ));

        if(
            !empty( $args['status_id'] ) &&
            !empty( $args['billcode'] ) &&
            !empty( $args['order_id'] ) &&
            !empty( $args['msg'] ) &&
            !empty( $args['transaction_id'] )
        ) :

            $is_callback = isset( $args['order_id'] ) ? true : false;

            if( true === $is_callback ) :

                if ( 1 === absint($args['status_id']) ) {

                    $order_id = intval($args['order_id']);

                    sejolisa_update_order_meta_data($order_id, array(
                        'toyyibpay' => array(
                            'trans_id' => esc_attr($args['transaction_id']),
                            'billcode' => esc_attr($args['billcode'])
                        )
                    ));

                    wp_redirect(add_query_arg(array(
                        'order_id' => $order_id,
                        'status'   => "success"
                    ), site_url('checkout/thank-you')));

                    exit();

                } elseif ( 3 === absint($args['status_id']) ) {
                    
                    $order_id = intval($args['order_id']);

                    sejolisa_update_order_meta_data($order_id, array(
                        'toyyibpay' => array(
                            'trans_id' => esc_attr($args['transaction_id']),
                            'billcode' => esc_attr($args['billcode'])
                        )
                    ));

                    wp_redirect(add_query_arg(array(
                        'order_id' => $order_id,
                        'status'   => "failed"
                    ), site_url('checkout/thank-you')));
                    
                } else {
                    
                    $order_id = intval($args['order_id']);

                    sejolisa_update_order_meta_data($order_id, array(
                        'toyyibpay' => array(
                            'trans_id' => esc_attr($args['transaction_id']),
                            'billcode' => esc_attr($args['billcode'])
                        )
                    ));

                    wp_redirect(add_query_arg(array(
                        'order_id' => $order_id,
                        'status'   => "pending"
                    ), site_url('checkout/thank-you')));

                }

            endif;
        
        endif;

        exit;

    }
 
    /**
     * Process callback from toyyibpay
     * @since   1.0.0
     * @return  void
     */
    protected function process_callback() {

        extract( $this->get_setup_values() );

        $setup = $this->get_setup_values();

        $args = wp_parse_args($_GET, array(
            'refno'            => NULL,
            'status'           => NULL,
            'reason'           => NULL,
            'billcode'         => NULL,
            'order_id'         => NULL,
            'amount'           => NULL,
            'transaction_time' => NULL
        ));

        if(
            !empty( $args['refno'] ) &&
            !empty( $args['status'] ) &&
            !empty( $args['reason'] ) &&
            !empty( $args['billcode'] ) &&
            !empty( $args['order_id'] ) &&
            !empty( $args['amount'] ) &&
            !empty( $args['transaction_time'] )
        ) :

            $is_callback = isset( $args['order_id'] ) ? true : false;

            if( true === $is_callback ) :

                if ( 1 === absint($args['status']) ) :

                    $order_id = intval( $args['order_id'] );
                    $response = sejolisa_get_order( array( 'ID' => $order_id ) );

                    if( false !== $response['valid'] ) :

                        $order   = $response['orders'];
                        $product = $order['product'];

                        // if product is need of shipment
                        if( false !== $product->shipping['active'] ) :
                            $status = 'in-progress';
                        else :
                            $status = 'completed';
                        endif;

                        $update_status_order = wp_parse_args($args, [
                            'ID'     => $order_id,
                            'status' => $status
                        ]);

                        sejolisa_update_order_status($update_status_order);

                        $args['status'] = $status;

                        do_action( 'sejoli/log/write', 'toyyibpay-update-order', $args );

                    else :

                        do_action( 'sejoli/log/write', 'toyyibpay-wrong-order', $args );
                    
                    endif;

                elseif ( 3 === absint($args['status']) ) :

                    $order_id = intval( $args['order_id'] );
                    $response = sejolisa_get_order( array( 'ID' => $order_id ) );

                    if( false !== $response['valid'] ) :

                        $order   = $response['orders'];
                        $product = $order['product'];
                        $status  = 'cancelled';

                        $update_status_order = wp_parse_args($args, [
                            'ID'     => $order_id,
                            'status' => $status
                        ]);

                        sejolisa_update_order_status($update_status_order);

                        $args['status'] = $status;

                        do_action( 'sejoli/log/write', 'toyyibpay-update-order', $args );

                    else :

                        do_action( 'sejoli/log/write', 'toyyibpay-wrong-order', $args );
                    
                    endif;

                else:

                    $order_id = intval( $args['order_id'] );
                    $response = sejolisa_get_order( array( 'ID' => $order_id ) );

                    if( false !== $response['valid'] ) :

                        $order   = $response['orders'];
                        $product = $order['product'];
                        $status  = 'on-hold';

                        $update_status_order = wp_parse_args($args, [
                            'ID'     => $order_id,
                            'status' => $status
                        ]);

                        sejolisa_update_order_status($update_status_order);

                        $args['status'] = $status;

                        do_action( 'sejoli/log/write', 'toyyibpay-update-order', $args );

                    else :

                        do_action( 'sejoli/log/write', 'toyyibpay-wrong-order', $args );
                    
                    endif;

                endif;

            endif;

        else :

            wp_die(
                __('You don\'t have permission to access this page', 'sejoli-toyyibpay'),
                __('Forbidden access by SEJOLI', 'sejoli-toyyibpay')
            );
        
        endif;

        exit;

    }

    /**
     * Check if current order is using toyyibpay and will be redirected to toyyibpay payment channel options
     * Hooked via action sejoli/thank-you/render, priority 100
     * @since   1.0.0
     * @param   array  $order Order data
     * @return  void
     */
    public function check_for_redirect( array $order ) {

        extract( $this->get_setup_values() );
        
        $redirect_url = $base_url.$order['meta_data']['toyyibpay']['billcode'];

        if(
            isset( $order['payment_info']['bank'] ) &&
            'TOYYIBPAY' === strtoupper( $order['payment_info']['bank'] )
        ) :

            if( 'on-hold' === $order['status'] ) :
                
                if( !isset( $order['meta_data']['toyyibpay']['billcode'] ) ){
                 
                    $this->prepare_toyyibpay_data( $order );
                
                } else {

                    wp_redirect( $redirect_url );
                    
                    exit;
                
                }

            elseif( in_array( $order['status'], array( 'refunded', 'cancelled' ) ) ) :

                $title = __('Order telah dibatalkan', 'sejoli-toyyibpay');
                require 'template/checkout/order-cancelled.php';

            else :

                $title = __('Order sudah diproses', 'sejoli-toyyibpay');
                require 'template/checkout/order-processed.php';

            endif;

            exit;

        endif;
    
    }

    /**
     * Display payment instruction in notification
     * @since   1.0.0
     * @param   array    $invoice_data
     * @param   string   $media email,whatsapp,sms
     * @return  string
     */
    public function display_payment_instruction( $invoice_data, $media = 'email' ) {
        
        if( 'on-hold' !== $invoice_data['order_data']['status'] ) :

            return;

        endif;

        $content = sejoli_get_notification_content(
                        'toyyibpay',
                        $media,
                        array(
                            'order' => $invoice_data['order_data']
                        )
                    );

        return $content;
    
    }

    /**
     * Display simple payment instruction in notification
     * @since   1.0.0
     * @param   array    $invoice_data
     * @param   string   $media
     * @return  string
     */
    public function display_simple_payment_instruction( $invoice_data, $media = 'email' ) {

        if( 'on-hold' !== $invoice_data['order_data']['status'] ) :
            return;
        endif;

        $content = __('via Toyyibpay', 'sejoli-toyyibpay');

        return $content;

    }

    /**
     * Set payment info to order data
     * @since   1.0.0
     * @param   array $order_data
     * @return  array
     */
    public function set_payment_info( array $order_data ) {

        $trans_data = [
            'bank' => 'Toyyibpay'
        ];

        return $trans_data;

    }

    /**
     * Excecute Transaction
     * @since   1.0.0
     * @return  array
     */
    private function executeTransaction( $request_url, $params ) {
        
        $result = wp_remote_post($request_url, array(
            'headers' => array(
                            "Content-type" => "application/x-www-form-urlencoded;charset=UTF-8",
                        ),
            'body'    => $params,
            'timeout' => 300
        ));

        if( is_wp_error( $result ) ){
            
            return [
                'success' => 0
            ];
        
        }

        $resBody = wp_remote_retrieve_body( $result );

        $resBody = json_decode( ( $resBody ), true );

        return $resBody;

    }

    /**
     * Paypal Generate Iso Time
     * @since   1.0.0
     * @return  time
     */
    private function toyyibpay_generate_isotime() {
        
        date_default_timezone_set("Asia/Kuala_Lumpur");
        $fmt  = date( 'Y-m-d\TH:i:s' );
        $time = sprintf( "$fmt.%s%s", substr( microtime(), 2, 3 ), date( 'P' ) );

        return $time;

    }

}