<?php

defined('_JEXEC') or die('Restricted access');
?>
<div align="center" class="hikashop_zarinpal_end" id="hikashop_zarinpal_end">
	<span id="hikashop_zarinpal_end_message" class="hikashop_zarinpal_end_message">
		<?php echo JText::sprintf('PLEASE_WAIT_BEFORE_REDIRECTION_TO_X', $this->payment_name).'<br/>'.JText::_('CLICK_ON_BUTTON_IF_NOT_REDIRECTED'); ?>
	</span>
	<span id="hikashop_zarinpal_end_spinner" class="hikashop_zarinpal_end_spinner hikashop_checkout_end_spinner">
	</span>
	<br/>
	در صورت عدم انتقال <a href="<?php echo $this->payment_params->url; ?>">اينجا</a> کليک کنيد.
	<script type="text/javascript">
		<!--
		window.location="<?php echo $this->payment_params->url; ?>";
		//-->
	</script>
</div>
