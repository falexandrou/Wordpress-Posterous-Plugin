<?php
/*
Plugin Name: Posterous Posts
Plugin URI: http://www.redmark.gr/posterous-plugin
Description: A plugin that displays your posterous blog posts into a wordpress page
Version: 1.0
Author: Fotis Alexandrou (Red Mark)
Author URI: http://www.redmark.gr
*/

$domain = get_option('posterous_domain');
$password = get_option('posterous_password');

if (!preg_match('/^http/iU', $domain) && $domain!='')
    $domain = 'http://'.$domain;
$email = get_option('posterous_email');

$posterous = null;

$preg_url = str_replace('/', '\/', get_option('posterous_page_url'));

if (preg_match('#' . $preg_url . '#', $_SERVER['REDIRECT_URL'])){
    include_once 'class.Posterous.php';
    if ($email!='' && $domain!='' && $password!='')
    $posterous = new Posterous($domain, $email, $password);
}

function posterous_pagination(){
    global $posterous;
    if ($posterous==null)
	return;
    return $posterous->getPagination();
}

function posterous_tags(){
    global $posterous;
    $page = get_option('posterous_page_url');
    if ($posterous==null)
	return;
    $tags = $posterous->getTags();
    $html = "<ul>\n";

    if (preg_match('/\?(.*)=/iU', $page))
	$union = '&';
    else
	$union = '?';
 
    if (stripos($_SERVER['REQUEST_URI'], $page))
	$page = '';

    foreach ($tags as $t){
	    $html .= "\t<li>";
	    $html .= '<a href="' . $page . $union . 'pgt=' . $t['id'] . '">' . $t['tag_string'] . '</a>';
	    $html .= "\t</li>";
    }
    $html .= "</ul>\n";
    return $html;
}

function posterous_widget(){
    global $posterous;
    if ($posterous==null)
	return;
    echo $before_widget;
    if ($before_title!='')
	echo $before_title;
    else
	echo '<h2 class="widget-title">';
    echo _e('Posterous Tags');
    if ($after_title!='')
	echo $after_title;
    else
	echo '</h2>';
    echo posterous_tags();
    echo $after_widget;
}

function posterous_init(){
  register_sidebar_widget(__('Posterous'), 'posterous_widget');
}
add_action("plugins_loaded", "posterous_init");

function posterous_posts(){
    global $posterous;
    if ($posterous==null)
	return;
    $posts = $posterous->getPosts(true);
    return $posts;
}

add_action('admin_menu', 'posterous_create_menu');

function posterous_create_menu() {

	//create new top-level menu
	add_menu_page('Posterous Posts Plugin Settings', 'Posterous Settings', 'administrator', __FILE__, 'posterous_settings_page',plugins_url('/images/icon.png', __FILE__));

	//call register settings function
	add_action( 'admin_init', 'register_mysettings' );
}


function register_mysettings() {
	//register our settings
	register_setting( 'posterous-settings-group', 'posterous_domain' );
	register_setting( 'posterous-settings-group', 'posterous_email' );
	register_setting( 'posterous-settings-group', 'posterous_password' );
	register_setting( 'posterous-settings-group', 'posterous_page_url' );
}

function posterous_settings_page() {
?>
<div class="wrap">
<h2>Posterous Posts Plugin</h2>

<form method="post" action="options.php">
    <?php settings_fields( 'posterous-settings-group' ); ?>
    <table class="form-table">
        <tr valign="top">
        <th scope="row"><?php _e("Your Posterous domain"); ?></th>
        <td><input type="text" name="posterous_domain" value="<?php echo get_option('posterous_domain'); ?>" /></td>
        </tr>

        <tr valign="top">
        <th scope="row"><?php _e('Your Posterous email (login)'); ?></th>
        <td><input type="text" name="posterous_email" value="<?php echo get_option('posterous_email'); ?>" /></td>
        </tr>

        <tr valign="top">
        <th scope="row"><?php _e('Your Posterous password');?></th>
        <td><input type="password" name="posterous_password" value="<?php echo get_option('posterous_password'); ?>" /></td>
        </tr>

	<tr valign="top">
        <th scope="row"><?php _e('Page url that contains Posterous');?></th>
        <td><input type="text" name="posterous_page_url" value="<?php echo get_option('posterous_page_url'); ?>" /></td>
        </tr>
    </table>

    <p class="submit">
    <input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
    </p>

</form>
</div>
<?php } ?>