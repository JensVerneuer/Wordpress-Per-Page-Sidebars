<?php
/*
Plugin Name: Paulund Per Page Sidebar
Plugin URI: http://www.paulund.co.uk
Description: This plugin will allow you to create new sidebars to override existing site wide sidebars
Version: 1
Author: Paul Underwood
Author URI: http://www.paulund.co.uk/
*/
new Paulund_Per_Page_Sidebar();

class Paulund_Per_Page_Sidebar
{
    private $sidebarPrefix = 'paulund-sidebars';

    private $postDataMeta = 'paulund-override-sidebars';

    public function __construct()
    {
        if(!empty($_GET['action']) && $_GET['action'] == 'edit')
        {
            add_action('add_meta_boxes', array(&$this, 'link_to_change_widgets') );
            add_action('save_post', array(&$this, 'save_sidebar_setting') );
        }

        add_action('sidebar_admin_setup', array(&$this, 'alter_page_widgets'));
        add_filter('sidebars_widgets', array(&$this, 'change_displayed_widgets'), 10);
    }

    /**
     * Add link to change the widgets
     *
     * @return void
     */
    public function link_to_change_widgets()
    {
        global $sidebars_widgets;

        if(!empty($_GET['new_sidebar_page_id']) && !empty($_GET['remove_sidebar']) && $_GET['remove_sidebar'] == 1)
        {
            $sidebarName = $this->sidebarPrefix.$_GET['new_sidebar_page_id'];

            // Unregister all sidebar widgets
            if(isset($sidebars_widgets[$sidebarName]))
            {
                foreach($sidebars_widgets[$sidebarName] as $k => $widgetId)
                {
                    wp_unregister_sidebar_widget($k);
                }
            }

            // Unregister sidebar
            unset($sidebars_widgets[$sidebarName]);
            unregister_sidebar($sidebarName);

            // Save the widget
            wp_set_sidebars_widgets( $sidebars_widgets );

            add_action('admin_notices', array(&$this, 'show_deleted_sidebar_message'));
        }

        add_meta_box( 'custom_page_widget', __('Page Widgets'), array(&$this, 'display_widget_links'), 'page', 'normal', 'high' );
        add_meta_box( 'custom_post_widget', __('Post Widgets'), array(&$this, 'display_widget_links'), 'post', 'normal', 'high' );
    }

    /**
     * Show a message to say the sidebar has been deleted
     *
     * @return Deleted message
     */
    public function show_deleted_sidebar_message()
    {
        echo '<div id="message" class="updated"><p>Custom Sidebar Deleted</p></div>';
    }

    /**
     * Display links to the add a new override sidebar or delete existing sidebar
     */
    public function display_widget_links()
    {
        global $post, $sidebars_widgets, $wp_registered_sidebars;

        $sidebarName = $this->sidebarPrefix.$post->ID;

        $currentSelectedSidebar = get_post_meta($post->ID, $this->postDataMeta, true);
        ?>
            <h2>Select A Sidebar For This Page</h2>
            <input type="hidden" name="selectSidebarNonce" value="<?php echo wp_create_nonce(basename(__FILE__)); ?>" />
            <p><label for="selectSidebar">Sidebar To Override: </label>
                <select id="selectSidebar" name="selectSidebar">
                    <option value="">Select A Sidebar To Override</option>
                    <?php
                        if(!empty($wp_registered_sidebars))
                        {
                            foreach($wp_registered_sidebars as $sidebar)
                            {
                                $id = $sidebar['id'];
                                printf('<option value="%s" %s>%s</option>', $id, selected($currentSelectedSidebar, $id, false), $sidebar['name']);
                            }
                        }
                    ?>
                </select>
            </p>

            <p><a href="widgets.php?new_sidebar_page_id=<?php echo $post->ID; ?>" target="_blank">Override Sidebar Widgets</a></p>
            <?php
                if(isset($sidebars_widgets[$sidebarName]))
                {
                    ?><p><a href="?post=<?php echo $post->ID; ?>&action=edit&new_sidebar_page_id=<?php echo $post->ID; ?>&remove_sidebar=1">Remove This Page Widgets</a></p><?php
                }
            ?>
        <?php
    }

    /**
     * Save the sidebar settings when the page is saved
     *
     * @param  INT $post_id - The post ID
     */
    public function save_sidebar_setting($post_id)
    {
        if (!wp_verify_nonce($_POST['selectSidebarNonce'], basename(__FILE__)))
            return $post_id;

        // check autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
        {
            return $post_id;
        }

        // check permissions
        if ('page' == $_POST['post_type'])
        {
            if (!current_user_can('edit_page', $post_id))
            {
                return $post_id;
            }
            elseif (!current_user_can('edit_post', $post_id))
            {
                return $post_id;
            }
        }

        $old = get_post_meta($post_id, $this->postDataMeta, true);

        $new = $_POST["selectSidebar"];

        if ($new && $new != $old)
        {
            update_post_meta($post_id, $this->postDataMeta, $new);
        }
        elseif ('' == $new && $old)
        {
            delete_post_meta($post_id, $this->postDataMeta, $old);
        }
    }

    /**
     * Register a new sidebar for your page ID
     * Display new sidebar on the widget page
     *
     * @return Void
     */
    public function alter_page_widgets()
    {
        global $wp_registered_sidebars;

        if(!empty($_GET['new_sidebar_page_id']) && empty($_GET['remove_sidebar']))
        {
            $sidebarName = $this->sidebarPrefix.$_GET['new_sidebar_page_id'];

            if(empty($wp_registered_sidebars[$sidebarName]))
            {
                $args = array(
                    'name'          => __( 'Page Override Sidebar'),
                    'id'            => $sidebarName,
                    'description'   => '',
                    'class'         => '',
                    'before_widget' => '<li id="%1$s" class="widget %2$s">',
                    'after_widget'  => '</li>',
                    'before_title'  => '<h2 class="widgettitle">',
                    'after_title'   => '</h2>' );

                register_sidebar( $args );
            }
        }
    }

    /**
     * Change the widgets that we display in the sidebar
     *
     * @param  Array $widgets - Sidebar widgets
     *
     * @return Widgets to display
     */
    public function change_displayed_widgets($widgets)
    {
        global $post;

        if (isset($post) && is_numeric($post->ID) && is_singular())
        {
            $sidebarName = $this->sidebarPrefix.$post->ID;

            $sidebarToReplace = get_post_meta($post->ID, $this->postDataMeta, true);

            // Check for page override
            if(!empty($widgets[$sidebarName]) && !empty($sidebarToReplace))
            {
                $widgets[$sidebarToReplace] = $widgets[$sidebarName];
            }
        }

        return $widgets;
    }
}
?>