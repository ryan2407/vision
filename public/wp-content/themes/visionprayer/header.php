<!doctype html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Vision Prayer</title>
	<link rel="stylesheet" href="<?php echo bloginfo('template_directory'); ?>/styles.css">
	<link rel="stylesheet" href="http://dev.vision.org.au/wp-content/themes/globalelements/globalStyles.css">
	<script src="http://cdnjs.cloudflare.com/ajax/libs/less.js/2.5.1/less.min.js"></script>
	<?php wp_enqueue_script('jquery'); ?>
	<?php wp_head(); ?>
	<script src="/wp-content/themes/globalelements/globalscripts.js"></script>
</head>
<body>

<?php include_once(get_theme_root().'/globalelements/globalBar.php'); ?>

<div class="header">
	<div class="container">
		<div class="padding">
			<div class="logo left">
				<a href="<?php echo bloginfo('url'); ?>">
					<img src="<?php echo bloginfo('template_directory'); ?>/images/vision-logo.png" width="421">
				</a>
			</div><!-- end logo -->
			<div class="tag right">
				<img src="<?php echo bloginfo('template_directory'); ?>/images/tag.png" width="229">
			</div><!-- end tag -->
		</div><!-- end logos -->

		<div style="clear:both;"></div>

		<?php include_once(get_theme_root().'/globalelements/headerNav.php'); ?>

	</div><!-- end container -->
</div><!-- end header -->