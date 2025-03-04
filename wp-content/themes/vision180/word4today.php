<?php
	
// 	Template name: Word 4 Today Page
	
?>

<?php include_once('header.php'); ?>

	<div class="container mainContent">
		<div class="primary">
			<?php 
			// the query
			$today = getdate();
			$the_query = new WP_Query( 
				array('post_type' => 'post', 
					'category_name' => 'word4today-devotional-content',
					'posts_per_page' => 1
					)	
				); ?>
			
			<?php if ( $the_query->have_posts() ) : ?>
			
				<!-- pagination here -->
			
				<!-- the loop -->
				<?php while ( $the_query->have_posts() ) : $the_query->the_post(); ?>
					<h1><?php the_title(); ?></h1>
					<?php the_content(); ?>
				<?php endwhile; ?>
				<!-- end of the loop -->
			
				<!-- pagination here -->
			
				<?php wp_reset_postdata(); ?>
			
			<?php else : ?>
				<p><?php _e( 'Sorry, no posts matched your criteria.' ); ?></p>
			<?php endif; ?>
		</div><!-- end primary -->

		<div class="secondary">
			
			<?php $dateObj = new DateTime('now'); ?>
			<h2><strong><?php echo $dateObj->format('D'); ?></strong> <?php echo $dateObj->format('d F, Y'); ?></h2>			
			
			<hr>
			
			<div class="dateMeta meta">
				
				<img src="<?php echo bloginfo('template_directory'); ?>/images/calendar-icon.jpg" width="45">
				<h2><strong>CALENDAR</strong><br>Select a specific day</h2>
			</div>
		</div>

		<?php get_template_part('sidebar'); ?>
		
		<div style="clear:both;"></div>
		
		<div class="secondary">
						
			<hr>
			
			<div class="mailMeta meta">
				
				<img src="<?php echo bloginfo('template_directory'); ?>/images/mail-icon.jpg" width="45">
				<h2><strong>SIGN UP</strong><br><span style="font-size: 95%;">Email or print subscriptions</span></h2>
				<div class="signupForm">
					<?php echo do_shortcode('[gravityform id="5" title=false description=false ajax=true]'); ?>
				</div>
			</div>
		</div>
		<div style="clear:both"></div>
	</div>

<?php include_once('footer.php'); ?>