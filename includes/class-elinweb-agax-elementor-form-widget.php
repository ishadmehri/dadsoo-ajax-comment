<?php
/**
 * Elementor widget for the Elin Agax comment form.
 *
 * @package Elinweb_Agax_Comment
 */

if (!defined('ABSPATH')) {
    exit;
}

class Elinweb_Agax_Elementor_Form_Widget extends \Elementor\Widget_Base
{
    public function get_name()
    {
        return 'elinweb-agax-comment-form';
    }

    public function get_title()
    {
        return __('Elin Agax Comment Form', 'elin-agax-comment');
    }

    public function get_icon()
    {
        return 'eicon-form-horizontal';
    }

    public function get_categories()
    {
        return array('general');
    }

    public function get_keywords()
    {
        return array('comment', 'form', 'elinweb', 'agax');
    }

    protected function render()
    {
        echo elinweb_agax_comment()->render_comment_form(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }
}
