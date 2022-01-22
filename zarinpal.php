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
        try {
            if ($this->payment_params->sandbox)
                $client = new SoapClient('https://sandbox.zarinpal.com/pg/services/WebGate/wsdl', ['encoding' => 'UTF-8']);
            else
                $client = new SoapClient('https://zarinpal.com/pg/services/WebGate/wsdl', ['encoding' => 'UTF-8']);

        } catch (SoapFault $ex) {
            die('System Error1: constructor error');
        }
        try {
            // $usergroupsids=print_r(JAccess::getGroupsByUser($this->user->id),"usergroup", True);/*added by mjt to send more description to zarinpal logs*/
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
            $filename = __DIR__ . "/zarinlog.txt";
            $Description .= "\n";
            $Description .= " درس: ";
            $Description .= current($order->cart->products)->order_product_code;
            file_put_contents($filename, "Description = " . print_r($Description, true) . "\n", FILE_APPEND);

            $parameters = [
                'MerchantID' => $this->payment_params->merchant,
                'Amount' => $amount,
                'Email' => '',
                'Description' => $Description,
                'Mobile' => '',
                'CallbackURL' => $callBackUrl,
            ];

            $result = $client->PaymentRequest($parameters);

            if ($result->Status == 100) {
                if ($this->payment_params->sandbox)
                    $this->payment_params->url = 'https://sandbox.zarinpal.com/pg/StartPay/' . $result->Authority . '/ZarinGate';
                else
                    $this->payment_params->url = 'https://zarinpal.com/pg/StartPay/' . $result->Authority . '/ZarinGate';

                return $this->showPage('end');
            } else {
                echo "<p align=center>Bank Error $result->Status.<br />Order UNSUCCSESSFUL!</p>";
                exit;
                die;
            }
        } catch (SoapFault $ex) {

            die('System Error3: error in get data from bank');
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
            $history->data = ob_get_clean();

            try {
                $client = new SoapClient('https://sandbox.zarinpal.com/pg/services/WebGate/wsdl', ['encoding' => 'UTF-8']);
            } catch (SoapFault $ex) {
                die('System Error1: constructor error');
            }
            try {
                $msg = '';
                $parameters = [
                    'MerchantID' => $this->payment_params->merchant,
                    'Authority' => $_GET['Authority'],
                    'Amount' => $history->amount,
                ];
                $result = $client->PaymentVerification($parameters);
                if ($result->Status == 100) {
                    $order_status = $this->payment_params->verified_status;
                    $msg = 'پرداخت شما با موفقیت انجام شد.';
                    $dest_url = $return_url;
                } else {
                    $order_status = $this->payment_params->pending_status;
                    $order_text = JText::sprintf('CHECK_DOCUMENTATION', HIKASHOP_HELPURL . 'payment-zarinpal-error#verify') . "\r\n\r\n" . $order_text;
                    $msg = $this->getStatusMessage($result->Status);
                    $dest_url = $cancel_url;
                }
            } catch (SoapFault $ex) {
                die('System Error2: error in get data from bank');
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
