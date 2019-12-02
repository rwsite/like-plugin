Wordpress Like Plugin
==========================

A simple and efficient post like system for WordPress. <a href="http://jonmasterson.com/post-like-demo/" target="_blank">View the demo.</a> 



Output the button by doing the following:
Add the button to any posts in your theme by adding the following function, <a href="https://developer.wordpress.org/themes/basics/the-loop/" target="_blank">within the loop</a> — <code>echo get_simple_likes_button( get_the_ID() );</code></li>
Add the button to any comments in your theme by making sure the second parameter in the button function is set to "1" — <code>echo get_simple_likes_button( get_comment_ID(), 1 );</code></li>
Include the [like] shortcode in your posts<