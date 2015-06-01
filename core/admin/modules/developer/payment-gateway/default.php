<?php $root = DEVELOPER_ROOT."payment-gateway/" ?>
<div class="table">
	<summary><h2>Configure</h2></summary>
	<section>
		<a class="box_select<?php if ($gateway->Service == "authorize.net") { ?> connected<?php } ?>" href="<?=$root?>authorize/">
			<span class="authorize"></span>
			<p>Authorize.Net</p>
		</a>
		<a class="box_select<?php if ($gateway->Service == "paypal-rest") { ?> connected<?php } ?>" href="<?=$root?>paypal-rest/">
			<span class="paypal"></span>
			<p>PayPal REST API</p>
		</a>
		<a class="box_select<?php if ($gateway->Service == "paypal") { ?> connected<?php } ?>" href="<?=$root?>paypal/">
			<span class="paypal"></span>
			<p>PayPal Payments Pro</p>
		</a>
		<a class="box_select<?php if ($gateway->Service == "payflow") { ?> connected<?php } ?>" href="<?=$root?>payflow/">
			<span class="payflow"></span>
			<p>PayPal Payflow Gateway</p>
		</a>
		<a class="box_select<?php if ($gateway->Service == "linkpoint") { ?> connected<?php } ?>" href="<?=$root?>linkpoint/">
			<span class="linkpoint"></span>
			<p>First Data / LinkPoint</p>
		</a>
	</section>
</div>