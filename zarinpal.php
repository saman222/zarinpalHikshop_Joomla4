<?php

defined('_JEXEC') or die('Restricted access');

class plgHikashoppaymentZarinpal extends hikashopPaymentPlugin
{
    public $accepted_currencies = [
        'IRR', 'TOM', 'IRT'
    ];
    public $multiple = true;
    public $name = 'zarinpal';
    public $doc_form = 'zarinpal';

    public function __construct(&$subject, $config)
    {
        parent::__construct($subject, $config);
    }

    public function onBeforeOrderCreate(&$order, &$do)
    {
        if (parent::onBeforeOrderCreate($order, $do) === true) {
            return true;
        }
        if (empty($this->payment_params->merchant)) {
            $this->app->enqueueMessage('Please check your &quot;Zarinpal&quot; plugin configuration');
            $do = false;
        }
    }

    public function onAfterOrderConfirm(&$order, &$methods, $method_id)
    {
        parent::onAfterOrderConfirm($order, $methods, $method_id);

        $usergroupsids = "usergroup:" . json_encode(JAccess::getGroupsByUser($this->user->id));/*added by mjt to send more description to zarinpal logs*/
        if ($this->user) {
            if (is_array($usergroupsids))
                $customdesc = $this->user->name . " آیدی کاربر: " . $this->user->id . " نام کاربری:" . $this->user->username . " آیدی های گروه های کاربری " . json_encode($usergroupsids);
            else
                $customdesc = $this->user->name . " آیدی کاربر: " . $this->user->id . " نام کاربری:" . $this->user->username . " آیدی های گروه های کاربری " . $usergroupsids;
        } else {
            $customdesc = "کاربر میهمان";
        }
        /*added by mjt */
        $callBackUrl = HIKASHOP_LIVE . 'index.php?option=com_hikashop&ctrl=checkout&task=notify&notif_payment=' . $this->name . '&tmpl=component&lang=' . $this->locale . $this->url_itemid . '&orderID=' . $order->order_id;
        $Description = 'سفارش شماره: ' . $order->order_id . "\nبیشتر:" . "$customdesc";
        if (extension_loaded('mbstring'))
            $Description = (strlen($Description) > 490) ? mb_substr($Description, 0, 440) . 'طول اطلاعات زیاد است' : $Description;
        else
            $Description = (strlen($Description) > 490) ? substr($Description, 0, 440) . 'طول اطلاعات زیاد است' : $Description;
        $amount = round($order->cart->full_total->prices[0]->price_value_with_tax, (int)$this->currency->currency_locale['int_frac_digits']);
        if ($this->currency->currency_code== 'IRT' || $this->currency->currency_code== 'TOM')
            $amount= (int)$amount * 10;

        $Description .= "\n";
        $Description .= " محصول: ";
        $Description .= current($order->cart->products)->order_product_code;


        $user = JFactory::getUser();
        $email = $user->get('email', 'guest@gmail.com');

        $parameters = [
            'merchant_id' => $this->payment_params->merchant,
            'amount' => $amount,
            'description' => $Description,
            'callback_url' => $callBackUrl,
            'metadata' => ["email" => $email]
        ];
//        $username = $user->get('username', 'guest');
//        $parameters['metadata']['mobile']= $username;

        $jsonData = json_encode($parameters);
        $ch = curl_init('https://api.zarinpal.com/pg/v4/payment/request.json');
        curl_setopt($ch, CURLOPT_USERAGENT, 'ZarinPal Rest Api v1');
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($jsonData)
        ));
        $result = curl_exec($ch);
        $err = curl_error($ch);
        $result = json_decode($result, true, JSON_PRETTY_PRINT);
        curl_close($ch);

        if ($err) {
            echo "cURL Error #:" . $err;
        } else {
            if (empty($result['errors'])) {
                if ($result['data']['code'] == 100) {
                    header('Location: https://www.zarinpal.com/pg/StartPay/' . $result['data']["authority"]);
                }
            } else {
                echo 'Error Code: ' . $result['errors']['code'];
                echo 'message: ' . $result['errors']['message'];

            }
        }
    }

    public function onPaymentNotification(&$statuses)
    {
        $filter = JFilterInput::getInstance();

        $dbOrder = $this->getOrder($_REQUEST['orderID']);
        $this->loadPaymentParams($dbOrder);
        if (empty($this->payment_params)) {
            return false;
        }
        $this->loadOrderData($dbOrder);
        if (empty($dbOrder)) {
            echo 'Could not load any order for your notification ' . $_REQUEST['orderID'];

            return false;
        }
        $order_id = $dbOrder->order_id;

        $url = HIKASHOP_LIVE . 'administrator/index.php?option=com_hikashop&ctrl=order&task=edit&order_id=' . $order_id;

        if (!empty($this->payment_params->return_url))
            $return_url = $this->payment_params->return_url . '/?order_id=' . $order_id;
        else
            $return_url = HIKASHOP_LIVE . 'index.php?option=com_hikashop&ctrl=order&order_id=' . $order_id;

        if (!empty($this->payment_params->cancel_url))
            $cancel_url = $this->payment_params->cancel_url . '/?order_id=' . $order_id;
        else
            $cancel_url = HIKASHOP_LIVE . 'index.php?option=com_hikashop&ctrl=order&order_id=' . $order_id;

        
        $order_text = "\r\n" . JText::sprintf('NOTIFICATION_OF_ORDER_ON_WEBSITE', $dbOrder->order_number, HIKASHOP_LIVE);
        $order_text .= "\r\n" . str_replace('<br/>', "\r\n", JText::sprintf('ACCESS_ORDER_WITH_LINK', $url));

        if (!empty($_GET['Authority'])) {
            $history = new stdClass();
            $history->notified = 0;
            $history->amount = round($dbOrder->order_full_price, (int)$this->currency->currency_locale['int_frac_digits']);
            if ($this->currency->currency_code== 'IRT' || $this->currency->currency_code== 'TOM')
                $history->amount= (int)$history->amount * 10;
            $history->data = ob_get_clean();


            $msg = '';
            $Authority = $_GET['Authority'];
            $data = array("merchant_id" => $this->payment_params->merchant, "authority" => $Authority, "amount" => $history->amount);
            $jsonData = json_encode($data);
            $ch = curl_init('https://api.zarinpal.com/pg/v4/payment/verify.json');
            curl_setopt($ch, CURLOPT_USERAGENT, 'ZarinPal Rest Api v4');
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($jsonData)
            ));

            $result = curl_exec($ch);
            $err = curl_error($ch);
            curl_close($ch);
            $result = json_decode($result, true);

            if ($err) {
                echo "cURL Error #:" . $err;
            } else {
                if ($result['data']['code'] == 100) {
                    $order_status = $this->payment_params->verified_status;
                    $msg = 'پرداخت شما با موفقیت انجام شد.';
                    JFactory::getApplication()->enqueueMessage($msg, 'success');
                    $dest_url = $return_url;
                } else {
                    $order_status = $this->payment_params->invalid_status;
                    $order_text = JText::sprintf('CHECK_DOCUMENTATION', HIKASHOP_HELPURL . 'payment-zarinpal-error#verify') . "\r\n\r\n" . $order_text;
//                    $msg = 'code: ' . $result['errors']['code'].' message: ' .  $result['errors']['message'];
                    $msg= 'پرداخت شما انجام نشد';
                    JFactory::getApplication()->enqueueMessage($msg, 'error');
                    $dest_url = $cancel_url;
                }
            }

            $config = &hikashop_config();
            if ($config->get('order_confirmed_status', 'confirmed') == $order_status) {
                $history->notified = 1;
            }

            $email = new stdClass();
            $email->subject = JText::sprintf('PAYMENT_NOTIFICATION_FOR_ORDER', 'Zarinpal', $order_status, $dbOrder->order_number);
            $email->body = str_replace('<br/>', "\r\n", JText::sprintf('PAYMENT_NOTIFICATION_STATUS', 'Zarinpal', $order_status)) . ' ' . JText::sprintf('ORDER_STATUS_CHANGED', $order_status) . "\r\n\r\n" . $order_text;
            $this->modifyOrder($order_id, $order_status, $history, $email);
        } else {
            $order_status = $this->payment_params->invalid_status;
            $email = new stdClass();
            $email->subject = JText::sprintf('NOTIFICATION_REFUSED_FOR_THE_ORDER', 'Zarinpal') . 'invalid transaction';
            $email->body = JText::sprintf("Hello,\r\n A Zarinpal notification was refused because it could not be verified by the zarinpal server (or pay cenceled)") . "\r\n\r\n" . JText::sprintf('CHECK_DOCUMENTATION', HIKASHOP_HELPURL . 'payment-zarinpal-error#invalidtnx');
            $action = false;
            $this->modifyOrder($order_id, $order_status, null, $email);
            $dest_url = $cancel_url;
        }
        if (headers_sent()) {
            die('<script type="text/javascript">window.location.href="' . $dest_url . '";</script>');
        } else {
            header('location: ' . $dest_url);
            die();
        }
        exit;
    }

    public function getStatusMessage($status)
    {
        $status = (string)$status;
        $statusCode = [
            '-1' => 'اطلاعات ارسال شده ناقص است.',
            '-2' => 'IP و یا کد پذیرنده اشتباه است',
            '-3' => 'با توجه به محدودیت های شاپرک امکان پرداخت با رقم درخواست شده میسر نمی باشد',
            '-4' => 'سطح تایید پذیرنده پایین تر از سطح نقره ای است',
            '-11' => 'درخواست موردنظر یافت نشد',
            '-12' => 'امکان ویرایش درخواست میسر نمی باشد',
            '-21' => 'هیچ نوع عملیات مالی برای این تراکنش یافت نشد',
            '-22' => 'تراکنش ناموفق می باشد',
            '-33' => 'رقم تراکنش با رقم پرداخت شده مطابقت ندارد',
            '-34' => 'سقف تقسیم تراکنش از لحاظ تعداد یا رقم عبور نموده است',
            '-40' => 'اجازه دسترسی به متد مربوطه وجود ندارد',
            '-41' => 'اطلاعات ارسالی مربوط به اطلاعات اضافی غیرمعتبر می باشد',
            '-42' => 'مدت زمان معتبر طول عمر شناسه پرداخت باید بین ۳۰ دقیقه تا ۴۵ روز می باشد',
            '-54' => 'درخواست موردنظر آرشیو شده است',
            '100' => 'عملیات با موفقیت انجام شده است',
            '101' => 'عملیات پرداخت موفق بوده و قبلا اعتبارسنجی تراکنش انجام شده است',
        ];
        if (isset($statusCode[$status])) {
            return $statusCode[$status];
        }

        return 'خطای نامشخص. کد خطا: ' . $status;
    }

    public function onPaymentConfiguration(&$element)
    {
        $subtask = JFactory::getApplication()->input->get('subtask', '');

        parent::onPaymentConfiguration($element);
    }

    public function onPaymentConfigurationSave(&$element)
    {
        return true;
    }

    public function getPaymentDefaultValues(&$element)
    {
        $element->payment_name = 'درگاه پرداخت زرين پال';
        $element->payment_description = '';
        $element->payment_images = '';

        $element->payment_params->invalid_status = 'cancelled';
        $element->payment_params->pending_status = 'created';
        $element->payment_params->verified_status = 'confirmed';
    }

    //need to help in programing
    public function mjtTruncate($string, $length = 480, $append = "&hellip;")
    {
        $string = trim($string);

        if (strlen($string) > $length) {
            $string = wordwrap($string, $length);
            $string = explode("\n", $string, 2);
            $string = $string[0] . $append;
        }

        return $string;
    }

}
