<?
	include BigTree::path("admin/modules/developer/payment-gateway/_common.php");
?>
<h1><span class="icon_developer_payment_gateway"></span>Payment Gateway</h1>

<div class="table">
	<summary><h2>Currently Using<small><?=$currently?></small></h2></summary>
	<section>
		<p>Choose a service below to configure your payment gateway settings.</p>
		<a class="box_select" href="authorize/">
			<span class="icon_developer_payment_authorize"></span>
			<p>Authorize.Net</p>
		</a>
		<a class="box_select" href="paypal/">
			<span class="icon_developer_payment_paypal"></span>
			<p>PayPal Payments Pro</p>
		</a>
		<a class="box_select" href="payflow/">
			<span class="icon_developer_payment_payflow"></span>
			<p>PayPal Payflow Gateway</p>
		</a>
		<a class="box_select" href="linkpoint/">
			<span class="icon_developer_payment_linkpoint"></span>
			<p>First Data / LinkPoint</p>
		</a>
	</section>
</div>