<?php
/*
 * Plugin Name: Buy It Installed
 * Plugin URI: http://buyitinstalled.com/
 * Description: Add a Buy It Installed button to your woocommerce shop.
 * Author: BuyItInstalled
 * Author URI: https://buyitinstalled.com
 * Version: 1.1.0
 * Text Domain: buy-it-installed
 * Domain Path: /languages
 *
 * Copyright (c) 2017 WooCommerce
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Required minimums and constants
 */
define( 'WC_BII_VERSION', '1.1.0' );
define( 'WC_BII_MIN_PHP_VER', '5.3.0' );
define( 'WC_BII_MIN_WC_VER', '3.0.0' );
define( 'WC_BII_MAIN_FILE', __FILE__ );

if ( ! class_exists( 'WC_Bii' ) ) :

class WC_Bii
{
    /**
     * @var Singleton The reference the *Singleton* instance of this class
     */
    private static $instance;

    /**
     * @var Reference to logging class.
     */
    private static $log;

    /**
     * Notices (array)
     * @var array
     */
    public $notices = array();

    /**
     * Returns the *Singleton* instance of this class.
     *
     * @return Singleton The *Singleton* instance.
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Protected constructor to prevent creating a new instance of the
     * *Singleton* via the `new` operator from outside of this class.
     */
    protected function __construct()
    {
        add_action( 'admin_init', array( $this, 'check_environment' ) ); // check if compatible environment
        add_action( 'admin_notices', array( $this, 'admin_notices' ), 15 ); // add the admin notices
        add_action( 'plugins_loaded', array( $this, 'init' ) ); // the main scripts
    }

    /**
     * The backup sanity check, in case the plugin is activated in a weird way,
     * or the environment changes after activation.
     */
    public function check_environment()
    {
        $environment_warning = self::get_environment_warning();

        if ( $environment_warning && is_plugin_active( plugin_basename( __FILE__ ) ) )
        {
            $this->add_admin_notice( 'bad_environment', 'error', $environment_warning );
        }

        // Check if API Key is inserted
        $biiSettings       	= get_option( 'bii_settings' );
        $apiKey           	= $biiSettings['bii_api_key'];

        if ( empty( $apiKey ) && ! ( isset( $_GET['page'] ) && 'bii_settings' == $_GET['page'] ) ) {
            $this->add_admin_notice( 'prompt_connect', 'notice notice-warning', 'Buy It Installed is almost ready. To get started, set your API key.' );
        }
    }

    /**
     * Checks the environment for compatibility problems.  Returns a string with the first incompatibility
     * found or false if the environment has no problems.
     */
    static function get_environment_warning()
    {
        if ( version_compare( phpversion(), WC_BII_MIN_PHP_VER, '<' ) )
        {
            $message = __( 'WooCommerce Bii - The minimum PHP version required for this plugin is %1$s. You are running %2$s.', 'woocommerce-buyitinstalled', 'woocommerce-buyitinstalled' );
            return sprintf( $message, WC_BII_MIN_PHP_VER, phpversion() );
        }

        if ( ! defined( 'WC_VERSION' ) )
        {
            return __( 'WooCommerce Bii requires WooCommerce to be activated to work.', 'woocommerce-buyitinstalled' );
        }

        if ( version_compare( WC_VERSION, WC_BII_MIN_WC_VER, '<' ) )
        {
            $message = __( 'WooCommerce Bii - The minimum WooCommerce version required for this plugin is %1$s. You are running %2$s. Please download an earlier release / contact us for help.', 'woocommerce-buyitinstalled', 'woocommerce-buyitinstalled' );
            return sprintf( $message, WC_BII_MIN_WC_VER, WC_VERSION );
        }

        return false;
    }

    /**
     * Allow this class and other classes to add slug keyed notices (to avoid duplication)
     */
    public function add_admin_notice( $slug, $class, $message )
    {
        $this->notices[ $slug ] = array(
            'class'   => $class,
            'message' => $message
        );
    }

    /**
     * Display any notices we've collected thus far (e.g. for connection, disconnection)
     */
    public function admin_notices()
    {
        foreach ( (array) $this->notices as $notice_key => $notice ) {
            echo "<div class='" . esc_attr( $notice['class'] ) . "'><p>";
            echo wp_kses( $notice['message'], array( 'a' => array( 'href' => array() ) ) );
            echo "</p></div>";
        }
    }

    /**
     * Init the plugin after plugins_loaded so environment variables are set.
     */
    public function init()
    {
        // Don't hook anything else in the plugin if we're in an incompatible environment
        if ( self::get_environment_warning() ) {
            return;
        }

        $this->define_admin_hooks();
        $this->define_public_hooks();
    }

    /**
     * Register all of the hooks related to the admin area functionality of the plugin.
     */
    private function define_admin_hooks()
    {
        // THIS WILL ADD THE SERVICE PRODUCT OPTION FOR PRODUCTS IN ADMIN
        add_filter( 'woocommerce_product_write_panel_tabs', array($this, 'render_product_data_tab_markup') );
        add_filter( 'woocommerce_product_data_panels', array($this, 'product_data_tab_markup') );
        add_filter( 'woocommerce_process_product_meta', array($this, 'save_product_data_tab_fields') );

        // THIS WILL ADD THE ADMIN SETTINGS PAGE
        add_action( 'admin_menu', array($this, 'add_admin_menu') );
        add_action( 'admin_init', array($this, 'settings_init') );
    }

    /**
     * Register all of the hooks related to the public-facing functionality of the plugin.
     */
    private function define_public_hooks()
    {
        // PUBLIC HOOKS
        add_action( 'woocommerce_after_add_to_cart_button', array( $this, 'addBiiButton')); // ADD THE BUTTON
        //add_filter( 'the_content', array( $this, 'addBiiStatementOfWork') );                // ADD STATEMENT OF WORK
        add_filter( 'woocommerce_product_tabs', array($this, 'woo_new_product_tab'), 100 );    // ADD STATEMENT OF WORK TAB
        add_action( 'woocommerce_add_to_cart', array( $this, 'addServiceItemToCart'));      // ADD THE CORRESPONDING SERVICE PRODUCT IF BUTTON WAS CLICKED
        add_action( 'wp_enqueue_scripts', array( $this, 'add_scripts' ) );                  // ADD THE NECESSARY DEPENDENCIES

		add_filter( 'woocommerce_thankyou_order_received_text', array( $this, 'createWorkOrderAPICall') ); // CREATE WORKORDER API CALL

        add_action( 'wp_ajax_nopriv_bii_enabled', array($this, 'bii_button_enabled') );  // AJAX ENABLE/DISABLE BII CALL IF USER IS NOT LOGGED IN
        add_action( 'wp_ajax_bii_enabled', array($this, 'bii_button_enabled') );         // AJAX ENABLE/DISABLE BII CALL IF USER IS LOGGED IN (PREVENTS TESTING ISSUES)
    }

    /**
     * Register all of the necessary java/css scripts
     */
    public function add_scripts()
    {
        wp_enqueue_script( 'woocommerce_buyitinstalled_js', plugins_url( 'assets/js/bii.js',  __FILE__  ) );
        wp_enqueue_script( 'jquery-ui-core' );
        wp_enqueue_script( 'jquery-ui-dialog' );
        wp_enqueue_style('wp-jquery-ui-dialog');
    }

    /**
     * Add a new tab to the product data meta box. Render HTML markup
     */

    public function render_product_data_tab_markup()
    {
        echo '<li class=" wc-2-0-x"><a href="#bii_data">' . __('Woo Buy It Installed', 'woo-buyitinstalled') . '</a></li>';
    }

    /**
     * Render fields for our newly added tab.
     */
    public function product_data_tab_markup()
    {
        global $thepostid, $post;

        ?>
        <div id="bii_data" class="panel woocommerce_options_panel hidden">
            <div class="options_group">
                <p class="form-field">
                    <label for="_woo_service_product_id">Service Product</label>
                    <select class="wc-product-search" style="width: 50%;" id="_woo_service_product_id" name="_woo_service_product_id" data-placeholder="<?php esc_attr_e( 'Search for a product&hellip;', 'woocommerce' ); ?>" data-action="woocommerce_json_search_products_and_variations" data-exclude="<?php echo intval( $post->ID ); ?>">
                        <?php
                        $serviceProductId = get_post_meta($post->ID, '_woo_service_product_id', true);
                        if ( $serviceProductId )
                        {
                            $serviceProduct    = wc_get_product( $serviceProductId );
                            if ( is_object( $serviceProduct ) )
                            {
                                echo '<option value="' . esc_attr( $serviceProductId ) . '"' . selected( true, true, false ) . '>' . wp_kses_post( $serviceProduct->get_formatted_name() ) . '</option>';
                            }
                        }
                        ?>
                    </select> <?php echo wc_help_tip( __( 'Cross-sells are products which you promote in the cart, based on the current product.', 'woocommerce' ) ); ?>


                </p>
            </div>
        </div>
        <?php
    }


    /**
     * Save the linked service product field
     */
    public function save_product_data_tab_fields($post_id)
    {
        if ('' !== $_POST['_woo_service_product_id']) {
            $value = stripslashes(woocommerce_clean($_POST['_woo_service_product_id']));
            // Strip out spaces.
            $value = str_replace(' ', '', $value);
            // Strip out the #, if it's at the front.
            $value = str_replace('#', '', $value);
            update_post_meta($post_id, '_woo_service_product_id', $value);
        } else {
            delete_post_meta($post_id, '_woo_service_product_id');
        }
    }

    /**
     * Add the Buy It Installed Button Onto the Website
     */
    public function addBiiButton()
    {
        global $post;

        // DON'T SHOW BUTTON IF IT DISABLED
        $options = get_option( 'bii_settings' );
        if(isset($options['bii_enabled']) && $options['bii_enabled'] == "off"){return;}

        // CHECK IF THIS PRODUCT EVEN HAS A SERVICE PRODUCT RELATED TO IT BEFORE ADDING BII BUTTON
        $serviceProductId = get_post_meta( $post->ID, '_woo_service_product_id' , true);
        $biiSettings = get_option( 'bii_settings' );
        if ($serviceProductId)
        {
            $serviceProduct         = wc_get_product( $serviceProductId );
            $skuCode                = $serviceProduct->get_sku();
            if (!empty($skuCode))
            {
                ?><button style="<?=$biiSettings['bii_button_styling'];?>" name="bii" id="bii" value="<?=$serviceProductId;?>" type="submit" class="single_add_to_cart_button button alt bii">Buy it Installed &trade;</button><?php
            }
        }
    }

    /**
     * Add the BII SOW to the content below main description
     * THIS IS NOT ON BY DEFAULT, I'M ADDING JUST IN CASE CLIENT PREFERS THIS
     */
    public function addBiiStatementOfWork($content)
    {
        global $post;

        // FIRST CHECK IF THIS PRODUCT EVEN HAS A SERVICE PRODUCT RELATED TO IT
        $serviceProductId = get_post_meta( $post->ID, '_woo_service_product_id' , true);
        if ($serviceProductId)
        {
            $statementOfWork = get_post_field('post_content', $serviceProductId);
            $content .= "
                            <div id=\"bii_statement_of_work\">
                                <h3>What's Included In Installation</h3>
                                $statementOfWork
                            </div>
                        ";
        }
        return $content;
    }

    /**
     * This hooks into Woocommerce's Product Tabs Functionality
     */
    function woo_new_product_tab( $tabs )
    {
        global $post;

        // FIRST CHECK IF THIS PRODUCT EVEN HAS A SERVICE PRODUCT RELATED TO IT
        $serviceProductId = get_post_meta( $post->ID, '_woo_service_product_id' , true);
        if ($serviceProductId)
        {
            $tabs['bii_tab'] = array(
                                        'title' => __("Installation", 'woocommerce'),
                                        'priority' => 50,
                                        'callback' => array($this, 'woo_new_product_tab_content')
                                    );
        }
        return $tabs;

    }

    function woo_new_product_tab_content()
    {
        global $post;
        $serviceProductId = get_post_meta( $post->ID, '_woo_service_product_id' , true);
        $statementOfWork = get_post_field('post_content', $serviceProductId);
        echo "
                 <div id=\"bii_statement_of_work\">
                     $statementOfWork
                 </div>
             ";
    }


    /**
     * The customer clicked on the buy it installed button, now add the corresponding service item
     */
    public function addServiceItemToCart()
    {
        global $addedServiceItemUponClick;
        if ($addedServiceItemUponClick == true){return;} // Means we already added the service item, we don't want to re-add it now under the service add to cart call
        if ( empty( $_REQUEST['bii'] ) || ! is_numeric( $_REQUEST['bii'] ) ){return;}
        $addedServiceItemUponClick = 1;
        $wc = $GLOBALS['woocommerce'];
        $wc->cart->add_to_cart( $_REQUEST['bii'] , $_REQUEST['quantity'] );
    }

    // START: ADD THE ADMIN SETTINGS SECTION

    function add_admin_menu()
    {
        add_menu_page('Buy It Installed Settings', 'Buy It Installed &trade;', 'manage_options', 'bii_settings', array($this,'options_page'));
    }

    function options_page()
    {
        ?>
        <form action='options.php' method='post'>

            <h2>Buy It Installed &trade; Settings</h2>

            <?php
            settings_fields( 'bii_settings_group' );
            do_settings_sections( 'biiSettingsPage' );
            submit_button();
            ?>

        </form>
        <?php
    }

    function settings_init()
    {
        register_setting( 'bii_settings_group', 'bii_settings' );
        add_settings_section(
            'bii_settings_section',
            null,
            null,
            'biiSettingsPage'
        );

        add_settings_field(
            'bii_enabled',
            'Enabled ',
            array($this, 'bii_enabled_button_render'),
            'biiSettingsPage',
            'bii_settings_section'
        );

       add_settings_field(
            'bii_api_key',
            'API Key',
            array($this, 'bii_api_key_text_field_render'),
            'biiSettingsPage',
            'bii_settings_section'
        );

       add_settings_field(
            'bii_button_styling',
            'Buy It Installed &trade; Button CSS Properties',
            array($this, 'bii_css_customization_textarea_field_render'),
            'biiSettingsPage',
            'bii_settings_section'
        );
    }

    function bii_enabled_button_render()
    {
        $options = get_option( 'bii_settings' );
        if(!isset($options['bii_enabled']) || $options['bii_enabled'] == "on"){$enabled=true;}else{$enabled=false;}
        ?><input type="radio" name="bii_settings[bii_enabled]" value="on"  <?php if($enabled) {echo 'checked';}?>> On
          <input type="radio" name="bii_settings[bii_enabled]" value="off" <?php if(!$enabled){echo 'checked';}?>> Off <?php
    }

    function bii_api_key_text_field_render()
    {
        $options = get_option( 'bii_settings' );
        ?><input size=80 type='text' name='bii_settings[bii_api_key]' value='<?php echo $options['bii_api_key']; ?>'> (API Key is needed to automatically create a work order upon purchase in WooCommerce)<?php
    }

    function bii_css_customization_textarea_field_render()
    {
        $options = get_option( 'bii_settings' );
        ?><textarea cols='120' rows='5' name='bii_settings[bii_button_styling]'><?php echo $options['bii_button_styling']; ?></textarea><br />
        <h4>To have your button be inline to the right of the add to cart we recommend</h4>
        margin-left:30px; <br /><br />
        <h4>To have your button go underneath the add to cart button </h4>
        clear:left; margin-top:10px;<br /><br />
        <h4>To have your button use the Buy It Installed &trade; logo use the following in addition to the above</h4>
        background:url(../wp-content/plugins/woocommerce-buyitinstalled/assets/images/buy-it-installed-logo.png);<br />
        height:49px;<br />
        width:214px;<br />
        text-align: left;<br />
        text-indent: -9999px;<br />
        white-space: nowrap;<br />
        overflow: hidden;<br />
        <br /><br />
        (You can also customize your templates css and create a #bii class)

         <?php
    }
    // END: ADD THE ADMIN SETTINGS SECTION


    function bii_button_enabled()
    {
        $enabled = $_GET['enabled'];

        // Handle request then generate response using WP_Ajax_Response
        $options = get_option( 'bii_settings' );

        if(!isset($enabled) || !in_array($enabled,array("on","off")))
        {
            $message = "Must provide parameter 'enabled' with values of on|off"; $success = false;
        }
        elseif ($options['bii_api_key']!=$_GET['api_key'])
        {
            $message = "Invalid API Key Provided"; $success = false;
        }
        elseif(isset($options['bii_enabled']) && $options['bii_enabled'] == $enabled)
        {
            $message = "Buy It Installed Button Status is Already: $enabled"; $success = false;
        }
        elseif (update_option('bii_settings', array_merge($options, array('bii_enabled'=>$enabled)) ))
        {
            $message = "Successfully Change Buy It Installed Button Status To: $enabled"; $success = true;
        }
        else
        {
            $message = "Failed Updating Buy It Installed Button Status To: $enabled"; $success = false;
        }

        wp_send_json(array(
            'success' => $success,
            'message' => $message
        ));

        wp_die();
    }


    // THE API PORTION
    function createWorkOrderAPICall($contents)
    {
		// VARIABLES NEEDED
        $biiSettings       	= get_option( 'bii_settings' );
        $apiKey           	= $biiSettings['bii_api_key'];
		$order_id 			= $GLOBALS['order-received'];
		$order              = wc_get_order( $order_id );
		$items 				= $order->get_items();
		$itemsArray			= array();
		
        // ORDER DETAILS
        $workOrderNumber    = "woo_order_".$order_id;
        $order              = wc_get_order( $order_id );
        $customerNote       = $order->get_customer_note();

        // ADDRESS
        $billingAddress1   = $order->get_billing_address_1();
        $billingAddress2   = $order->get_billing_address_2();
        $billingState      = $order->get_billing_state();
        $billingCity       = $order->get_billing_city();
        $billingPostcode   = $order->get_billing_postcode();
        $billingCountry    = $order->get_billing_country();

        // CONTACT
        $billingFirstName  = $order->get_billing_first_name();
        $billingLastName   = $order->get_billing_last_name();
        $billingPhone      = $order->get_billing_phone();
        $billingEmail      = $order->get_billing_email();

        $billingFullName   = $billingFirstName." ".$billingLastName;

		// INITIALIZE A TABLE OF PRODUCT IDS 
		foreach($items as $item)
		{
			$itemsArray[$item['product_id']] = $item['qty'];
		}
		
		foreach($itemsArray as $productId=>$quantity)
		{
			$serviceProductId = get_post_meta( $productId, '_woo_service_product_id' , true); // DOES THE PRODUCT HAVE A SERVICE PRODUCT ATTACHED TO IT
			if ($serviceProductId && isset($itemsArray[$serviceProductId])) // WE HAVE A SERVICE PRODUCT IN CART WE NEED TO CREATE A WORK ORDER FOR, PER PRODUCT
			{
				// SERVICE PRODUCT DETAILS
				$serviceProduct         = wc_get_product( $serviceProductId );
				$skuCode                = $serviceProduct->get_sku();
				$serviceName            = $serviceProduct->get_title();
				$serviceCost            = $serviceProduct->get_price();
                $serviceDescription     = ($serviceProduct->get_data())['description'];

				if(empty($skuCode)){continue;}

				// DO THE CALL
				$body = array
				(
					'access_token' => $apiKey,
					'workorder_number' => $workOrderNumber,
					'address' => array
					(
						'street_address1'   => $billingAddress1,
						'street_address2'   => $billingAddress2,
						'city'              => $billingCity,
						'state'             => $billingState,
						'zipcode'           => $billingPostcode,
						'country'           => $billingCountry,
					),
					'contact' => array
					(
						'name'  => $billingFullName,
						'phone' => $billingPhone,
						'cell'  => $billingPhone,
						'email' => $billingEmail,
					),
					'description' => array
					(
						'name'              => $serviceName,
						'customer_notes'    => $customerNote,
						'description'       => $serviceDescription
					),
					'pricing' => array
					(
						'pay_rate' => $serviceCost*$quantity,
                        'fund_source'=> 'ACH',
                    ),
					'schedule' => array
					(
						'start_date' => array
						(
							'service_date' => null,
							'service_time' => null,
						),
					),	
					'skus' => array
					(
						0 => array
						(
							'sku_code' => $skuCode,
							'quantity' => $quantity,
						),
					)
				);
				$url = "https://www.buyitinstalled.com/api/1.0/workorder";
				$response = wp_remote_post(
				                               $url,
                                               array
                                               (
                                                   'method' => 'POST',
                                                   'timeout' => 45,
                                                   'body' => $body
                                               )
                                           );
				$responseDecoded = json_decode($response['body']);
				if (isset($responseDecoded->Status) && $responseDecoded->Status == 'Success' )
                {
                    $contents .= "<br /><br />A contractor will be in contact with you shortly to arrange for the following service: '<b>$serviceName</b>'";
                }
                else
                {
                    $contents .= "<br /><br />Please contact us regarding your service for: '<b>$serviceName</b>'";
                }
			}
		}
        return $contents;
    }
}

$GLOBALS['wc_buyitinstalled'] = WC_Bii::get_instance();

endif;
