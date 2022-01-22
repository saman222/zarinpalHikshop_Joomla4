<?php

defined('_JEXEC') or die('Restricted access');
?>
<tr>
	<td class="key">
		<label for="data[payment][payment_params][merchant]"><?php
            echo JText::_('API زرین پال');
        ?></label>
	</td>
	<td>
		<input type="text" name="data[payment][payment_params][merchant]" value="<?php echo $this->escape(@$this->element->payment_params->merchant); ?>" />
	</td>
</tr>
<tr>
	<td class="key">
		<label for="data[payment][payment_params][cancel_url]"><?php
			echo JText::_('CANCEL_URL');
		?></label>
	</td>
	<td>
		<input type="text" name="data[payment][payment_params][cancel_url]" value="<?php echo $this->escape(@$this->element->payment_params->cancel_url); ?>" />
	</td>
</tr>
<tr>
	<td class="key">
		<label for="data[payment][payment_params][return_url]"><?php
			echo JText::_('RETURN_URL');
		?></label>
	</td>
	<td>
		<input type="text" name="data[payment][payment_params][return_url]" value="<?php echo $this->escape(@$this->element->payment_params->return_url); ?>" />
	</td>
</tr>
<tr>
	<td class="key">
		<label for="data[payment][payment_params][invalid_status]"><?php
            echo JText::_('INVALID_STATUS');
        ?></label>
	</td>
	<td><?php
        echo $this->data['order_statuses']->display('data[payment][payment_params][invalid_status]', @$this->element->payment_params->invalid_status);
    ?></td>
</tr>
<tr>
	<td class="key">
		<label for="data[payment][payment_params][pending_status]"><?php
            echo JText::_('PENDING_STATUS');
        ?></label>
	</td>
	<td><?php
        echo $this->data['order_statuses']->display('data[payment][payment_params][pending_status]', @$this->element->payment_params->pending_status);
    ?></td>
</tr>
<tr>
	<td class="key">
		<label for="data[payment][payment_params][verified_status]"><?php
            echo JText::_('VERIFIED_STATUS');
        ?></label>
	</td>
	<td><?php
        echo $this->data['order_statuses']->display('data[payment][payment_params][verified_status]', @$this->element->payment_params->verified_status);
    ?></td>
</tr>
<tr>
    <td colspan="2">
        <p class="alert alert-info">do you want to use zarinpal sandbox?</p>
    </td>
</tr>
<tr>
    <td class="key">
        <label for="data[payment][payment_params][sandbox]"><?php
            echo JText::_('sandbox ');
            ?></label>
    </td>
    <td>
        <input type="checkbox" name="data[payment][payment_params][sandbox]" <?php echo $this->escape(@$this->element->payment_params->sandbox)? 'checked' : ''; ?> value="1" />
    </td>
</tr>