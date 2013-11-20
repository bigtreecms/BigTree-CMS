<? $root = DEVELOPER_ROOT."payment-gateway/" ?>
<div class="table">
	<summary><h2>Configure</h2></summary>
	<section>
		<a class="box_select<? if ($gateway->Service == "authorize.net") { ?> connected<? } ?>" href="<?=$root?>authorize/">
			<span class="authorize"></span>
			<p>Authorize.Net</p>
		</a>
		<a class="box_select<? if ($gateway->Service == "paypal-rest") { ?> connected<? } ?>" href="<?=$root?>paypal-rest/">
			<span class="paypal"></span>
			<p>PayPal REST API</p>
		</a>
		<a class="box_select<? if ($gateway->Service == "paypal") { ?> connected<? } ?>" href="<?=$root?>paypal/">
			<span class="paypal"></span>
			<p>PayPal Payments Pro</p>
		</a>
		<a class="box_select<? if ($gateway->Service == "payflow") { ?> connected<? } ?>" href="<?=$root?>payflow/">
			<span class="payflow"></span>
			<p>PayPal Payflow Gateway</p>
		</a>
		<a class="box_select<? if ($gateway->Service == "linkpoint") { ?> connected<? } ?>" href="<?=$root?>linkpoint/">
			<span class="linkpoint"></span>
			<p>First Data / LinkPoint</p>
		</a>
	</section>
</div>