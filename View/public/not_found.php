<?php Template::load('header'); ?>

<div>
<div>
<div class="broken_tape">
	<h1><span><?php $alerts = $site->renderAlerts();
		if($alerts) echo $alerts;
		else echo "Oh no! We can't find the page you're looking for (404).<br>";
		?> <a href="<?=WEBROOT?>">Maybe take it from the start?</a>.</span></h1>
</div>
<?php Template::load('footer');
