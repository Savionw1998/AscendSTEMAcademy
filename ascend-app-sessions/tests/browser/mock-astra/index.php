<?php get_header(); ?>
<div id="primary" class="content-area primary"><main id="main" class="site-main">
<?php while ( have_posts() ) : the_post(); ?>
<article <?php post_class(); ?>><div class="entry-content"><?php the_content(); ?></div></article>
<?php endwhile; ?>
</main></div>
<?php get_footer(); ?>
