<?php
/*
MarkePress Complete Gateway Plugin
Author: Gabriel Rios (gabrielfalcaorios@gmail.com);
*/
include 'usaepay/usaepay.php';

class MP_Complete_Gateway extends MP_Gateway_API {

  //private gateway slug. Lowercase alpha (a-z) and dashes (-) only please!
  var $plugin_name = 'Complete Gateway';

  //name of your gateway, for the admin side.
  var $admin_name = 'Complete Gateway';

  //public name of your gateway, for lists and such.
  var $public_name = 'Complete Gateway';

  //url for an image for your checkout method. Displayed on method form
  var $method_img_url = '';

  //url for an submit button image for your checkout method. Displayed on checkout form if set
  var $method_button_img_url = '';

  //whether or not ssl is needed for checkout page
  var $force_ssl = false;

  //always contains the url to send payment notifications to if needed by your gateway. Populated by the parent class
  var $ipn_url;

  //whether if this is the only enabled gateway it can skip the payment_form step
  var $skip_form = false;

  //only required for global capable gateways. The maximum stores that can checkout at once
  var $max_stores = 1;

  /****** Below are the public methods you may overwrite via a plugin ******/

  var $transaction_key, $usesandbox;

  /**
    * Runs when your class is instantiated. Use to setup your plugin instead of __construct()
    */
  function on_creation() {
    global $mp;
    $settings = get_option('mp_settings');

    $this->method_img_url = $mp->plugin_url . 'images/credit_card.png';
    $this->method_button_img_url = $mp->plugin_url . 'images/cc-button.png';

    if (isset($settings['gateways']['complete_gateway']['transaction_key'])) {
      $this->transaction_key = $settings['gateways']['complete_gateway']['transaction_key'];
    }

    if (isset($settings['gateways']['complete_gateway']['mode'])) {
      if ($settings['gateways']['complete_gateway']['mode'] == 'sandbox') {
        $this->usesandbox = true;
      } else {
        $this->usesandbox = false;
      }
    } else { $this->usesandbox = false; }
  }

  /**
    * Return fields you need to add to the payment screen, like your credit card info fields.
    *  If you don't need to add form fields set $skip_form to true so this page can be skipped
    *  at checkout.
    *
    * @param array $cart. Contains the cart contents for the current blog, global cart if $mp->global_cart is true
    * @param array $shipping_info. Contains shipping info and email in case you need it
    */
  function payment_form($cart, $shipping_info) {
    global $mp;
    $settings = get_option('mp_settings');
    $meta     = get_user_meta($current_user->ID, 'mp_billing_info', true);

    if (isset($_GET['cancel'])) {
      $content .= '<div class="mp_checkout_error">' . __('Your credit card transaction has been canceled.', 'mp') . '</div>';
    }

    $email = (!empty($_SESSION['mp_billing_info']['email'])) ? $_SESSION['mp_billing_info']['email'] : (!empty($meta['email'])?$meta['email']:$_SESSION['mp_shipping_info']['email']);
    $name = (!empty($_SESSION['mp_billing_info']['name'])) ? $_SESSION['mp_billing_info']['name'] : (!empty($meta['name'])?$meta['name']:$_SESSION['mp_shipping_info']['name']);
    $address1 = (!empty($_SESSION['mp_billing_info']['address1'])) ? $_SESSION['mp_billing_info']['address1'] : (!empty($meta['address1'])?$meta['address1']:$_SESSION['mp_shipping_info']['address1']);
    $address2 = (!empty($_SESSION['mp_billing_info']['address2'])) ? $_SESSION['mp_billing_info']['address2'] : (!empty($meta['address2'])?$meta['address2']:$_SESSION['mp_shipping_info']['address2']);
    $city = (!empty($_SESSION['mp_billing_info']['city'])) ? $_SESSION['mp_billing_info']['city'] : (!empty($meta['city'])?$meta['city']:$_SESSION['mp_shipping_info']['city']);
    $state = (!empty($_SESSION['mp_billing_info']['state'])) ? $_SESSION['mp_billing_info']['state'] : (!empty($meta['state'])?$meta['state']:$_SESSION['mp_shipping_info']['state']);
    $zip = (!empty($_SESSION['mp_billing_info']['zip'])) ? $_SESSION['mp_billing_info']['zip'] : (!empty($meta['zip'])?$meta['zip']:$_SESSION['mp_shipping_info']['zip']);
    $country = (!empty($_SESSION['mp_billing_info']['country'])) ? $_SESSION['mp_billing_info']['country'] : (!empty($meta['country'])?$meta['country']:$_SESSION['mp_shipping_info']['country']);
    if (!$country)
      $country = $settings['base_country'];
    $phone = (!empty($_SESSION['mp_billing_info']['phone'])) ? $_SESSION['mp_billing_info']['phone'] : (!empty($meta['phone'])?$meta['phone']:$_SESSION['mp_shipping_info']['phone']);


    $content = '';
    $content .= '<table class="mp_cart_billing">
      <thead><tr>
        <th colspan="2">'.__('Enter Your Billing Information:', 'mp').'</th>
      </tr></thead>
      <tbody>
      <tr>
        <td align="right">'.__('Email:', 'mp').'*</td><td>
      '.apply_filters( 'mp_checkout_error_email', '' ).'
      <input size="35" name="email" type="text" value="'.esc_attr($email).'" /></td>
        </tr>

        <tr>
        <td align="right">'.__('Full Name:', 'mp').'*</td><td>
      '.apply_filters( 'mp_checkout_error_name', '' ).'
      <input size="35" name="name" type="text" value="'.esc_attr($name).'" /> </td>
        </tr>

        <tr>
        <td align="right">'.__('Address:', 'mp').'*</td><td>
      '.apply_filters( 'mp_checkout_error_address1', '' ).'
      <input size="45" name="address1" type="text" value="'.esc_attr($address1).'" /><br />
      <small><em>'.__('Street address, P.O. box, company name, c/o', 'mp').'</em></small>
      </td>
        </tr>

        <tr>
        <td align="right">'.__('Address 2:', 'mp').'&nbsp;</td><td>
      <input size="45" name="address2" type="text" value="'.esc_attr($address2).'" /><br />
      <small><em>'.__('Apartment, suite, unit, building, floor, etc.', 'mp').'</em></small>
      </td>
        </tr>

        <tr>
        <td align="right">'.__('City:', 'mp').'*</td><td>
      '.apply_filters( 'mp_checkout_error_city', '' ).'
      <input size="25" name="city" type="text" value="'.esc_attr($city).'" /></td>
        </tr>

        <tr>
        <td align="right">'.__('State/Province/Region:', 'mp').'*</td><td>
      '.apply_filters( 'mp_checkout_error_state', '' ).'
      <input size="15" name="state" type="text" value="'.esc_attr($state).'" /></td>
        </tr>

        <tr>
        <td align="right">'.__('Postal/Zip Code:', 'mp').'*</td><td>
      '.apply_filters( 'mp_checkout_error_zip', '' ).'
      <input size="10" id="mp_zip" name="zip" type="text" value="'.esc_attr($zip).'" /></td>
        </tr>

        <tr>
        <td align="right">'.__('Country:', 'mp').'*</td><td>
        '.apply_filters( 'mp_checkout_error_country', '' ).'
      <select id="mp_" name="country">';

        foreach ((array)$settings['shipping']['allowed_countries'] as $code) {
          $content .= '<option value="'.$code.'"'.selected($country, $code, false).'>'.esc_attr($mp->countries[$code]).'</option>';
        }

    $content .= '</select>
      </td>
        </tr>

        <tr>
        <td align="right">'.__('Phone Number:', 'mp').'</td><td>
        <input size="20" name="phone" type="text" value="'.esc_attr($phone).'" /></td>
        </tr>

        <tr>
          <td align="right">'.__('Credit Card Number:', 'mp').'*</td>
          <td>
            '.apply_filters( 'mp_checkout_error_card_num', '' ).'
            <input name="card_num" onkeyup="cc_card_pick(\'#cardimage\', \'#card_num\');"
              id="card_num" class="credit_card_number input_field noautocomplete"
              type="text" size="22" maxlength="22" />
          <div class="hide_after_success nocard cardimage"  id="cardimage" style="background: url('.$mp->plugin_url.'images/card_array.png) no-repeat;"></div></td>
        </tr>

        <tr>
          <td align="right">'.__('Expiration Date:', 'mp').'*</td>
          <td>
          '.apply_filters( 'mp_checkout_error_exp', '' ).'
          <label class="inputLabel" for="exp_month">'.__('Month', 'mp').'</label>
          <select name="exp_month" id="exp_month">
            '.$this->_print_month_dropdown().'
          </select>
          <label class="inputLabel" for="exp_year">'.__('Year', 'mp').'</label>
          <select name="exp_year" id="exp_year">
            '.$this->_print_year_dropdown('', true).'
          </select>
          </td>
        </tr>

        <tr>
          <td align="right">'.__('Security Code:', 'mp').'</td>
          <td>'.apply_filters( 'mp_checkout_error_card_code', '' ).'
          <input id="card_code" name="card_code" class="input_field noautocomplete"
              style="width: 70px;" type="text" size="4" maxlength="4" /></td>
        </tr>

      </tbody>
    </table>';

    return $content;

  }

function _print_year_dropdown($sel='', $pfp = false) {
  $localDate=getdate();
  $minYear = $localDate["year"];
  $maxYear = $minYear + 15;

  $output = "<option value=''>--</option>";
  for($i=$minYear; $i<$maxYear; $i++) {
    if ($pfp) {
      $output .= "<option value='". substr($i, 0, 4) ."'".($sel==(substr($i, 0, 4))?' selected':'').
      ">". $i ."</option>";
    } else {
      $output .= "<option value='". substr($i, 2, 2) ."'".($sel==(substr($i, 2, 2))?' selected':'').
      ">". $i ."</option>";
    }
  }
  return($output);
}

function _print_month_dropdown($sel='') {
  $output =  "<option value=''>--</option>";
  $output .=  "<option " . ($sel==1?' selected':'') . " value='01'>01 - Jan</option>";
  $output .=  "<option " . ($sel==2?' selected':'') . "  value='02'>02 - Feb</option>";
  $output .=  "<option " . ($sel==3?' selected':'') . "  value='03'>03 - Mar</option>";
  $output .=  "<option " . ($sel==4?' selected':'') . "  value='04'>04 - Apr</option>";
  $output .=  "<option " . ($sel==5?' selected':'') . "  value='05'>05 - May</option>";
  $output .=  "<option " . ($sel==6?' selected':'') . "  value='06'>06 - Jun</option>";
  $output .=  "<option " . ($sel==7?' selected':'') . "  value='07'>07 - Jul</option>";
  $output .=  "<option " . ($sel==8?' selected':'') . "  value='08'>08 - Aug</option>";
  $output .=  "<option " . ($sel==9?' selected':'') . "  value='09'>09 - Sep</option>";
  $output .=  "<option " . ($sel==10?' selected':'') . "  value='10'>10 - Oct</option>";
  $output .=  "<option " . ($sel==11?' selected':'') . "  value='11'>11 - Nov</option>";
  $output .=  "<option " . ($sel==12?' selected':'') . "  value='12'>12 - Dec</option>";

  return($output);
}
  /**
    * Use this to process any fields you added. Use the $_POST global,
    *  and be sure to save it to both the $_SESSION and usermeta if logged in.
    *  DO NOT save credit card details to usermeta as it's not PCI compliant.
    *  Call $mp->cart_checkout_error($msg, $context); to handle errors. If no errors
    *  it will redirect to the next step.
    *
    * @param array $cart. Contains the cart contents for the current blog, global cart if $mp->global_cart is true
    * @param array $shipping_info. Contains shipping info and email in case you need it
    */
  function process_payment_form($cart, $shipping_info) {
    global $mp;
    $settings = get_option('mp_settings');

    if (!is_email($_POST['email']))
      $mp->cart_checkout_error('Please enter a valid Email Address.', 'email');

    if (empty($_POST['name']))
      $mp->cart_checkout_error('Please enter your Full Name.', 'name');

    if (empty($_POST['address1']))
      $mp->cart_checkout_error('Please enter your Street Address.', 'address1');

    if (empty($_POST['city']))
      $mp->cart_checkout_error('Please enter your City.', 'city');

    if (($_POST['country'] == 'US' || $_POST['country'] == 'CA') && empty($_POST['state']))
      $mp->cart_checkout_error('Please enter your State/Province/Region.', 'state');

    if (empty($_POST['zip']))
      $mp->cart_checkout_error('Please enter your Zip/Postal Code.', 'zip');

    if (empty($_POST['country']) || strlen($_POST['country']) != 2)
      $mp->cart_checkout_error('Please enter your Country.', 'country');

    //for checkout plugins
    do_action( 'mp_billing_process' );

    //save to session
    global $current_user;
    $meta = get_user_meta($current_user->ID, 'mp_billing_info', true);
    $_SESSION['mp_billing_info']['email'] = ($_POST['email']) ? trim(stripslashes($_POST['email'])) : $current_user->user_email;
    $_SESSION['mp_billing_info']['name'] = ($_POST['name']) ? trim(stripslashes($_POST['name'])) : $current_user->user_firstname . ' ' . $current_user->user_lastname;
    $_SESSION['mp_billing_info']['address1'] = ($_POST['address1']) ? trim(stripslashes($_POST['address1'])) : $meta['address1'];
    $_SESSION['mp_billing_info']['address2'] = ($_POST['address2']) ? trim(stripslashes($_POST['address2'])) : $meta['address2'];
    $_SESSION['mp_billing_info']['city'] = ($_POST['city']) ? trim(stripslashes($_POST['city'])) : $meta['city'];
    $_SESSION['mp_billing_info']['state'] = ($_POST['state']) ? trim(stripslashes($_POST['state'])) : $meta['state'];
    $_SESSION['mp_billing_info']['zip'] = ($_POST['zip']) ? trim(stripslashes($_POST['zip'])) : $meta['zip'];
    $_SESSION['mp_billing_info']['country'] = ($_POST['country']) ? trim($_POST['country']) : $meta['country'];
    $_SESSION['mp_billing_info']['phone'] = ($_POST['phone']) ? preg_replace('/[^0-9-\(\) ]/', '', trim($_POST['phone'])) : $meta['phone'];

    //save to user meta
    if ($current_user->ID)
      update_user_meta($current_user->ID, 'mp_billing_info', $_SESSION['mp_billing_info']);

    if (!isset($_POST['exp_month']) || !isset($_POST['exp_year']) || empty($_POST['exp_month']) || empty($_POST['exp_year'])) {
      $mp->cart_checkout_error( __('Please select your credit card expiration date.', 'mp'), 'exp');
    }

    if (!isset($_POST['card_code']) || empty($_POST['card_code'])) {
      $mp->cart_checkout_error( __('Please enter your credit card security code', 'mp'), 'card_code');
    }

    if (!isset($_POST['card_num']) || empty($_POST['card_num'])) {
      $mp->cart_checkout_error( __('Please enter your credit card number', 'mp'), 'card_num');
    } else {
      if ($this->_get_card_type($_POST['card_num']) == "") {
        $mp->cart_checkout_error( __('Please enter a valid credit card number', 'mp'), 'card_num');
      }
    }

    if (!$mp->checkout_error) {
      if (
        ($this->_get_card_type($_POST['card_num']) == "American Express" && strlen($_POST['card_code']) != 4) ||
        ($this->_get_card_type($_POST['card_num']) != "American Express" && strlen($_POST['card_code']) != 3)
        ) {
        $mp->cart_checkout_error(__('Please enter a valid credit card security code', 'mp'), 'card_code');
      }
    }

    if (!$mp->checkout_error) {
      $_SESSION['card_num'] = $_POST['card_num'];
      $_SESSION['card_code'] = $_POST['card_code'];
      $_SESSION['exp_month'] = $_POST['exp_month'];
      $_SESSION['exp_year'] = $_POST['exp_year'];

      $mp->generate_order_id();
    }
  }

function _get_card_type($number) {
  $num_length = strlen($number);

  if ($num_length > 10 && preg_match('/[0-9]+/', $number) >= 1) {
    if((substr($number, 0, 1) == '4') && (($num_length == 13)||($num_length == 16))) {
      return "Visa";
    } else if((substr($number, 0, 1) == '5' && ((substr($number, 1, 1) >= '1') && (substr($number, 1, 1) <= '5'))) && ($num_length == 16)) {
      return "Mastercard";
    } else if(substr($number, 0, 4) == "6011" && ($num_length == 16)) {
      return "Discover Card";
    } else if((substr($number, 0, 1) == '3' && ((substr($number, 1, 1) == '4') || (substr($number, 1, 1) == '7'))) && ($num_length == 15)) {
      return "American Express";
    }
  }
  return "";
}

  /**
    * Return the chosen payment details here for final confirmation. You probably don't need
    *  to post anything in the form as it should be in your $_SESSION var already.
    *
    * @param array $cart. Contains the cart contents for the current blog, global cart if $mp->global_cart is true
    * @param array $shipping_info. Contains shipping info and email in case you need it
    */
  function confirm_payment_form($cart, $shipping_info) {
    global $mp;

    $settings = get_option('mp_settings');
    $meta = get_user_meta($current_user->ID, 'mp_billing_info', true);

    $email = (!empty($_SESSION['mp_billing_info']['email'])) ? $_SESSION['mp_billing_info']['email'] : (!empty($meta['email'])?$meta['email']:$_SESSION['mp_shipping_info']['email']);
    $name = (!empty($_SESSION['mp_billing_info']['name'])) ? $_SESSION['mp_billing_info']['name'] : (!empty($meta['name'])?$meta['name']:$_SESSION['mp_shipping_info']['name']);
    $address1 = (!empty($_SESSION['mp_billing_info']['address1'])) ? $_SESSION['mp_billing_info']['address1'] : (!empty($meta['address1'])?$meta['address1']:$_SESSION['mp_shipping_info']['address1']);
    $address2 = (!empty($_SESSION['mp_billing_info']['address2'])) ? $_SESSION['mp_billing_info']['address2'] : (!empty($meta['address2'])?$meta['address2']:$_SESSION['mp_shipping_info']['address2']);
    $city = (!empty($_SESSION['mp_billing_info']['city'])) ? $_SESSION['mp_billing_info']['city'] : (!empty($meta['city'])?$meta['city']:$_SESSION['mp_shipping_info']['city']);
    $state = (!empty($_SESSION['mp_billing_info']['state'])) ? $_SESSION['mp_billing_info']['state'] : (!empty($meta['state'])?$meta['state']:$_SESSION['mp_shipping_info']['state']);
    $zip = (!empty($_SESSION['mp_billing_info']['zip'])) ? $_SESSION['mp_billing_info']['zip'] : (!empty($meta['zip'])?$meta['zip']:$_SESSION['mp_shipping_info']['zip']);
    $country = (!empty($_SESSION['mp_billing_info']['country'])) ? $_SESSION['mp_billing_info']['country'] : (!empty($meta['country'])?$meta['country']:$_SESSION['mp_shipping_info']['country']);
    if (!$country)
      $country = $settings['base_country'];
    $phone = (!empty($_SESSION['mp_billing_info']['phone'])) ? $_SESSION['mp_billing_info']['phone'] : (!empty($meta['phone'])?$meta['phone']:$_SESSION['mp_shipping_info']['phone']);

    $content = '';

    $content .= '<table class="mp_cart_billing">';
    $content .= '<thead><tr>';
    $content .= '<th>'.__('Billing Information:', 'mp').'</th>';
    $content .= '<th align="right"><a href="'. mp_checkout_step_url('checkout').'">'.__('&laquo; Edit', 'mp').'</a></th>';
    $content .= '</tr></thead>';
    $content .= '<tbody>';
    $content .= '<tr>';
    $content .= '<td align="right">'.__('Email:', 'mp').'</td><td>';
    $content .= esc_attr($email).'</td>';
    $content .= '</tr>';
    $content .= '<tr>';
    $content .= '<td align="right">'.__('Full Name:', 'mp').'</td><td>';
    $content .= esc_attr($name).'</td>';
    $content .= '</tr>';
    $content .= '<tr>';
    $content .= '<td align="right">'.__('Address:', 'mp').'</td>';
    $content .= '<td>'.esc_attr($address1).'</td>';
    $content .= '</tr>';

    if ($address2) {
      $content .= '<tr>';
      $content .= '<td align="right">'.__('Address 2:', 'mp').'</td>';
      $content .= '<td>'.esc_attr($address2).'</td>';
      $content .= '</tr>';
    }

    $content .= '<tr>';
    $content .= '<td align="right">'.__('City:', 'mp').'</td>';
    $content .= '<td>'.esc_attr($city).'</td>';
    $content .= '</tr>';

    if ($state) {
      $content .= '<tr>';
      $content .= '<td align="right">'.__('State/Province/Region:', 'mp').'</td>';
      $content .= '<td>'.esc_attr($state).'</td>';
      $content .= '</tr>';
    }

    $content .= '<tr>';
    $content .= '<td align="right">'.__('Postal/Zip Code:', 'mp').'</td>';
    $content .= '<td>'.esc_attr($zip).'</td>';
    $content .= '</tr>';
    $content .= '<tr>';
    $content .= '<td align="right">'.__('Country:', 'mp').'</td>';
    $content .= '<td>'.$mp->countries[$country].'</td>';
    $content .= '</tr>';

    if ($phone) {
      $content .= '<tr>';
      $content .= '<td align="right">'.__('Phone Number:', 'mp').'</td>';
      $content .= '<td>'.esc_attr($phone).'</td>';
      $content .= '</tr>';
    }

    $content .= '<tr>';
    $content .= '<td align="right">'.__('Payment method:', 'mp').'</td>';
    $content .= '<td>'.$this->_get_card_type($_SESSION['card_num']).' ending in '. substr($_SESSION['card_num'], strlen($_SESSION['card_num'])-4, 4).'</td>';
    $content .= '</tr>';
    $content .= '</tbody>';
    $content .= '</table>';

    return $content;
  }

  /**
    * Use this to do the final payment. Create the order then process the payment. If
    *  you know the payment is successful right away go ahead and change the order status
    *  as well.
    *  Call $mp->cart_checkout_error($msg, $context); to handle errors. If no errors
    *  it will redirect to the next step.
    *
    * @param array $cart. Contains the cart contents for the current blog, global cart if $mp->global_cart is true
    * @param array $shipping_info. Contains shipping info and email in case you need it
    */
  function process_payment($cart, $shipping_info) {
    global $mp;

    $settings = get_option('mp_settings');
    $billing_info = $_SESSION['mp_billing_info'];

    // Instantiate USAePay client object
    $tran = new umTransaction;

    // Merchants Source key must be generated within the console
    $tran->key = $this->transaction_key;

    // Send request to sandbox server not production.  Make sure to comment or remove this line before
    //  putting your code into production
    $tran->usesandbox = $this->usesandbox;

    $tran->card = $_SESSION['card_num'];
    $tran->exp = $_SESSION['exp_month'] . $_SESSION['exp_year'];

    $totals = array();
    foreach ($cart as $product_id => $variations) {
      foreach ($variations as $variation => $data) {
        $sku = empty($data['SKU']) ? "{$product_id}_{$variation}" : $data['SKU'];
        //total on tax excluded
        $totals[] = $mp->before_tax_price($data['price'], $product_id) * $data['quantity'];
        //display as tax inclusive
      }
    }
    $total = array_sum($totals);

    $tran->amount = $total;
    $tran->invoice = $_SESSION['mp_order'];
    $tran->cardholder = $billing_info['name'];

    $address = $billing_info['address1'];
    if (!empty($billing_info['address2'])) {
      $address .= "\n".$billing_info['address2'];
    }

    $tran->street      = $address;
    $tran->zip         = $billing_info['zip'];
    $tran->description = "Order ID: " . $_SESSION['mp_order'];
    $tran->cvv2        = $_SESSION['card_code'];

    if ($tran->Process()) {

      $status = __('The payment has been completed, and the funds have been added successfully to your account balance.', 'mp');
      $paid = true;

      $payment_info['gateway_public_name'] = $this->public_name;
      $payment_info['gateway_private_name'] = $this->admin_name;
      $payment_info['method'] = 'Credit Card';
      $payment_info['status'][$timestamp] = "paid";
      $payment_info['total'] = $total;
      $payment_info['currency'] = "USD"; // Authorize.net only supports USD transactions
      $payment_info['transaction_id'] = $tran->authcode;

      //succesful payment, create our order now
      $result = $mp->create_order($_SESSION['mp_order'], $cart, $shipping_info, $payment_info, $paid);
    } else {
      $error = $tran->error();
      $mp->cart_checkout_error( sprintf(__('There was a problem finalizing your purchase. %s Please <a href="%s">go back and try again</a>.', 'mp') , $error, mp_checkout_step_url('checkout')) );
    }
  }

  /**
    * Runs before page load incase you need to run any scripts before loading the success message page
    */
  function order_confirmation($order) {
    // wp_die( __("You must override the order_confirmation() method in your {$this->admin_name} payment gateway plugin!", 'mp') );
  }

  /**
    * Filters the order confirmation email message body. You may want to append something to
    *  the message. Optional
    *
    * Don't forget to return!
    */
  function order_confirmation_email($msg, $order) {
    return $msg;
  }

  /**
    * Return any html you want to show on the confirmation screen after checkout. This
    *  should be a payment details box and message.
    *
    * Don't forget to return!
    */
  function order_confirmation_msg($content, $order) {

    global $mp;
    if ($order->post_status == 'order_received') {
      $content .= '<p>' . sprintf(__('Your credit card payment for this order totaling %s is not yet complete. Here is the latest status:', 'mp'), $mp->format_currency($order->mp_payment_info['currency'], $order->mp_payment_info['total'])) . '</p>';
      $statuses = $order->mp_payment_info['status'];
      krsort($statuses); //sort with latest status at the top
      $status = reset($statuses);
      $timestamp = key($statuses);
      $content .= '<p><strong>' . date(get_option('date_format') . ' - ' . get_option('time_format'), $timestamp) . ':</strong> ' . htmlentities($status) . '</p>';
    } else {
      $content .= '<p>' . sprintf(__('Your credit card payment for this order totaling %s is complete. The credit card transaction number is <strong>%s</strong>.', 'mp'), $mp->format_currency($order->mp_payment_info['currency'], $order->mp_payment_info['total']), $order->mp_payment_info['transaction_id']) . '</p>';
    }
    return $content;
  }

  /**
    * Echo a settings meta box with whatever settings you need for you gateway.
    *  Form field names should be prefixed with mp[gateways][plugin_name], like "mp[gateways][plugin_name][mysetting]".
    *  You can access saved settings via $settings array.
    */
  function gateway_settings_box($settings) {
    global $mp;
    ?>
    <div id="mp_complete_gateway_express" class="postbox">
      <h3 class='hndle'><span><?php _e('Complete Gateway Settings', 'mp'); ?></span></h3>
      <div class="inside">
        <table class="form-table">
          <tr>
            <th scope="row"><?php _e('Mode', 'mp') ?></th>
            <td>
              <p>
                <select name="mp[gateways][complete_gateway][mode]">
                  <option value="sandbox" <?php selected($settings['gateways']['complete_gateway']['mode'], 'sandbox') ?>><?php _e('Sandbox', 'mp') ?></option>
                  <option value="live" <?php selected($settings['gateways']['complete_gateway']['mode'], 'live') ?>><?php _e('Live', 'mp') ?></option>
                </select>
              </p>
            </td>
          </tr>
          <tr>
            <th scope="row"><?php _e('Gateway Credentials', 'mp') ?></th>
            <td>
              <label><?php _e('Transaction Key', 'mp') ?><br />
                <input value="<?php echo esc_attr($settings['gateways']['complete_gateway']['transaction_key']); ?>" size="30" name="mp[gateways][complete_gateway][transaction_key]" type="text" />
              </label>
              </p>
            </td>
          </tr>
        </table>
      </div>
    </div>
    <?php
  }

  /**
    * Filters posted data from your settings form. Do anything you need to the $settings['gateways']['plugin_name']
    *  array. Don't forget to return!
    */
  function process_gateway_settings($settings) {

    return $settings;
  }

  /**
    * Use to handle any payment returns to the ipn_url. Do not display anything here. If you encounter errors
    *  return the proper headers. Exits after.
    */
  function process_ipn_return() {

  }

  /****** Do not override any of these private methods please! ******/

  //populates ipn_url var
  function _generate_ipn_url() {
    global $mp;
    $this->ipn_url = home_url($mp->get_setting('slugs->store') . '/payment-return/' . $this->plugin_name);
  }

  //populates ipn_url var
  function _payment_form_skip($var) {
    return $this->skip_form;
  }
}

mp_register_gateway_plugin( 'MP_Complete_Gateway' , 'complete_gateway', __('Complete Gateway Checkout', 'mp') );

?>
