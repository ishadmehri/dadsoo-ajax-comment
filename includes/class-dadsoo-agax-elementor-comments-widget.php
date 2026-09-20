<?php
/**
 * Elementor widget for the Dadsoo Agax comments list.
 *
 * @package Dadsoo_Agax_Comment
 */

if (!defined('ABSPATH')) {
    exit;
}

class Dadsoo_Agax_Elementor_Comments_Widget extends \Elementor\Widget_Base
{
    public function get_name()
    {
        return 'dadsoo-agax-comments';
    }

    public function get_title()
    {
        return __('Dadsoo Agax Comments', 'dadsoo-agax-comment');
    }

    public function get_icon()
    {
        return 'eicon-comments';
    }

    public function get_categories()
    {
        return array('general');
    }

    public function get_keywords()
    {
        return array('comment', 'comments', 'dadsoo', 'agax', 'like', 'dislike');
    }

    public function get_style_depends()
    {
        return array('dadsoo-agax-comment');
    }

    public function get_script_depends()
    {
        return array('dadsoo-agax-comment');
    }

    protected function register_controls()
    {
        $this->start_controls_section('content_section', array(
            'label' => __('List Settings', 'dadsoo-agax-comment'),
            'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
        ));

        $this->add_control('items', array(
            'label' => __('Comments per initial load', 'dadsoo-agax-comment'),
            'type' => \Elementor\Controls_Manager::NUMBER,
            'default' => 5,
            'min' => 1,
            'max' => 100,
        ));

        $this->add_control('load_more_text', array(
            'label' => __('Load more button text', 'dadsoo-agax-comment'),
            'type' => \Elementor\Controls_Manager::TEXT,
            'default' => __('Load more comments', 'dadsoo-agax-comment'),
            'label_block' => true,
        ));

        $this->add_control('loading_text', array(
            'label' => __('Loading state text', 'dadsoo-agax-comment'),
            'type' => \Elementor\Controls_Manager::TEXT,
            'default' => __('Loading comments…', 'dadsoo-agax-comment'),
            'label_block' => true,
        ));

        $this->add_control('empty_text', array(
            'label' => __('Empty state text', 'dadsoo-agax-comment'),
            'type' => \Elementor\Controls_Manager::TEXT,
            'default' => __('No comments yet.', 'dadsoo-agax-comment'),
            'label_block' => true,
        ));

        $this->end_controls_section();

        $this->start_controls_section('icons_section', array(
            'label' => __('Vote Icons', 'dadsoo-agax-comment'),
            'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
        ));

        $this->add_control('like_outline_icon', array(
            'label' => __('Like, inactive state', 'dadsoo-agax-comment'),
            'type' => \Elementor\Controls_Manager::ICONS,
            'default' => array('value' => 'far fa-thumbs-up', 'library' => 'fa-regular'),
        ));
        $this->add_control('like_fill_icon', array(
            'label' => __('Like, active state', 'dadsoo-agax-comment'),
            'type' => \Elementor\Controls_Manager::ICONS,
            'default' => array('value' => 'fas fa-thumbs-up', 'library' => 'fa-solid'),
        ));
        $this->add_control('dislike_outline_icon', array(
            'label' => __('Dislike, inactive state', 'dadsoo-agax-comment'),
            'type' => \Elementor\Controls_Manager::ICONS,
            'default' => array('value' => 'far fa-thumbs-down', 'library' => 'fa-regular'),
        ));
        $this->add_control('dislike_fill_icon', array(
            'label' => __('Dislike, active state', 'dadsoo-agax-comment'),
            'type' => \Elementor\Controls_Manager::ICONS,
            'default' => array('value' => 'fas fa-thumbs-down', 'library' => 'fa-solid'),
        ));

        $this->end_controls_section();

        $this->start_controls_section('author_style_section', array(
            'label' => __('Author', 'dadsoo-agax-comment'),
            'tab' => \Elementor\Controls_Manager::TAB_STYLE,
        ));
        $this->add_responsive_control('avatar_size', array(
            'label' => __('Avatar size', 'dadsoo-agax-comment'),
            'type' => \Elementor\Controls_Manager::SLIDER,
            'range' => array('px' => array('min' => 20, 'max' => 160)),
            'selectors' => array('{{WRAPPER}} .dadsoo-comment-avatar' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};'),
        ));
        $this->add_responsive_control('avatar_radius', array(
            'label' => __('Avatar border radius', 'dadsoo-agax-comment'),
            'type' => \Elementor\Controls_Manager::DIMENSIONS,
            'size_units' => array('px', '%'),
            'selectors' => array('{{WRAPPER}} .dadsoo-comment-avatar' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'),
        ));
        $this->add_control('author_name_color', array(
            'label' => __('Author name color', 'dadsoo-agax-comment'),
            'type' => \Elementor\Controls_Manager::COLOR,
            'selectors' => array('{{WRAPPER}} .dadsoo-comment-author' => 'color: {{VALUE}};'),
        ));
        $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), array(
            'name' => 'author_name_typography',
            'selector' => '{{WRAPPER}} .dadsoo-comment-author',
        ));
        $this->end_controls_section();

        $this->start_controls_section('comment_style_section', array(
            'label' => __('Comment Text', 'dadsoo-agax-comment'),
            'tab' => \Elementor\Controls_Manager::TAB_STYLE,
        ));
        $this->add_control('comment_text_color', array(
            'label' => __('Text color', 'dadsoo-agax-comment'),
            'type' => \Elementor\Controls_Manager::COLOR,
            'selectors' => array('{{WRAPPER}} .dadsoo-comment-text' => 'color: {{VALUE}};'),
        ));
        $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), array(
            'name' => 'comment_text_typography',
            'selector' => '{{WRAPPER}} .dadsoo-comment-text',
        ));
        $this->end_controls_section();

        $this->register_vote_style_controls('like', __('Like', 'dadsoo-agax-comment'));
        $this->register_vote_style_controls('dislike', __('Dislike', 'dadsoo-agax-comment'));
        $this->register_loading_style_controls();
    }

    /**
     * دکمهٔ بارگذاری بیشتر، پیام‌های حالت بارگذاری/خالی و رنگ فلَش دستهٔ تازه.
     */
    private function register_loading_style_controls()
    {
        $this->start_controls_section('loading_style_section', array(
            'label' => __('Load More', 'dadsoo-agax-comment'),
            'tab' => \Elementor\Controls_Manager::TAB_STYLE,
        ));

        $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), array(
            'name' => 'load_more_typography',
            'selector' => '{{WRAPPER}} .dadsoo-load-more',
        ));

        $this->start_controls_tabs('load_more_state_tabs');

        $this->start_controls_tab('load_more_normal_tab', array('label' => __('Normal', 'dadsoo-agax-comment')));
        $this->add_control('load_more_color', array(
            'label' => __('Text color', 'dadsoo-agax-comment'),
            'type' => \Elementor\Controls_Manager::COLOR,
            'selectors' => array('{{WRAPPER}} .dadsoo-load-more' => 'color: {{VALUE}};'),
        ));
        $this->add_control('load_more_background', array(
            'label' => __('Background color', 'dadsoo-agax-comment'),
            'type' => \Elementor\Controls_Manager::COLOR,
            'selectors' => array('{{WRAPPER}} .dadsoo-load-more' => 'background-color: {{VALUE}};'),
        ));
        $this->end_controls_tab();

        $this->start_controls_tab('load_more_hover_tab', array('label' => __('Hover', 'dadsoo-agax-comment')));
        $this->add_control('load_more_color_hover', array(
            'label' => __('Text color', 'dadsoo-agax-comment'),
            'type' => \Elementor\Controls_Manager::COLOR,
            'selectors' => array('{{WRAPPER}} .dadsoo-load-more:hover' => 'color: {{VALUE}};'),
        ));
        $this->add_control('load_more_background_hover', array(
            'label' => __('Background color', 'dadsoo-agax-comment'),
            'type' => \Elementor\Controls_Manager::COLOR,
            'selectors' => array('{{WRAPPER}} .dadsoo-load-more:hover' => 'background-color: {{VALUE}};'),
        ));
        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->add_group_control(\Elementor\Group_Control_Border::get_type(), array(
            'name' => 'load_more_border',
            'selector' => '{{WRAPPER}} .dadsoo-load-more',
            'separator' => 'before',
        ));

        $this->add_responsive_control('load_more_padding', array(
            'label' => __('Padding', 'dadsoo-agax-comment'),
            'type' => \Elementor\Controls_Manager::DIMENSIONS,
            'size_units' => array('px', 'em', '%'),
            'selectors' => array(
                '{{WRAPPER}} .dadsoo-load-more' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ),
        ));

        $this->add_responsive_control('load_more_border_radius', array(
            'label' => __('Border radius', 'dadsoo-agax-comment'),
            'type' => \Elementor\Controls_Manager::DIMENSIONS,
            'size_units' => array('px', '%'),
            'selectors' => array(
                '{{WRAPPER}} .dadsoo-load-more' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ),
        ));

        $this->add_control('status_heading', array(
            'label' => __('Loading & empty state message', 'dadsoo-agax-comment'),
            'type' => \Elementor\Controls_Manager::HEADING,
            'separator' => 'before',
        ));

        $this->add_control('status_color', array(
            'label' => __('Text & spinner color', 'dadsoo-agax-comment'),
            'type' => \Elementor\Controls_Manager::COLOR,
            'selectors' => array('{{WRAPPER}} .dadsoo-comments-status' => 'color: {{VALUE}};'),
        ));

        $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), array(
            'name' => 'status_typography',
            'selector' => '{{WRAPPER}} .dadsoo-comments-status',
        ));

        $this->add_control('flash_heading', array(
            'label' => __('New comments indicator', 'dadsoo-agax-comment'),
            'type' => \Elementor\Controls_Manager::HEADING,
            'separator' => 'before',
        ));

        $this->add_control('flash_color', array(
            'label' => __('Flash color', 'dadsoo-agax-comment'),
            'type' => \Elementor\Controls_Manager::COLOR,
            'default' => '',
            'selectors' => array('{{WRAPPER}}' => '--dadsoo-flash-color: {{VALUE}};'),
        ));

        // اگر قالب هدر چسبان دارد، بالای نظر زیر هدر پنهان می‌شود؛ این مقدار آن را جبران می‌کند.
        $this->add_control('scroll_offset', array(
            'label' => __('Scroll offset from top', 'dadsoo-agax-comment'),
            'type' => \Elementor\Controls_Manager::SLIDER,
            'size_units' => array('px'),
            'range' => array('px' => array('min' => 0, 'max' => 300)),
            'default' => array('unit' => 'px', 'size' => 90),
            'selectors' => array('{{WRAPPER}}' => '--dadsoo-scroll-offset: {{SIZE}}{{UNIT}};'),
        ));

        $this->end_controls_section();
    }

    private function register_vote_style_controls($vote, $label)
    {
        $this->start_controls_section($vote . '_style_section', array(
            /* translators: %s: like or dislike. */
            'label' => sprintf(__('%s Button', 'dadsoo-agax-comment'), $label),
            'tab' => \Elementor\Controls_Manager::TAB_STYLE,
        ));

        // بدون فاصلهٔ داخلی، رنگ پس‌زمینه و کادر به آیکون و شمارنده می‌چسبند.
        $this->add_responsive_control($vote . '_padding', array(
            'label' => __('Padding', 'dadsoo-agax-comment'),
            'type' => \Elementor\Controls_Manager::DIMENSIONS,
            'size_units' => array('px', 'em', '%'),
            'default' => array('top' => '6', 'right' => '10', 'bottom' => '6', 'left' => '10', 'unit' => 'px', 'isLinked' => false),
            'selectors' => array(
                '{{WRAPPER}} .dadsoo-vote-btn[data-vote="' . $vote . '"]' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ),
        ));

        $this->add_responsive_control($vote . '_border_radius', array(
            'label' => __('Border radius', 'dadsoo-agax-comment'),
            'type' => \Elementor\Controls_Manager::DIMENSIONS,
            'size_units' => array('px', '%'),
            'selectors' => array(
                '{{WRAPPER}} .dadsoo-vote-btn[data-vote="' . $vote . '"]' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ),
        ));

        $this->start_controls_tabs($vote . '_state_tabs');
        foreach (array('outline' => __('Inactive (Outline)', 'dadsoo-agax-comment'), 'fill' => __('Active (Fill)', 'dadsoo-agax-comment')) as $state => $state_label) {
            $is_active = 'fill' === $state;
            $selector = '{{WRAPPER}} .dadsoo-vote-btn[data-vote="' . $vote . '"]' . ($is_active ? '.active' : ':not(.active)');
            $this->start_controls_tab($vote . '_' . $state . '_tab', array('label' => $state_label));
            $this->add_control($vote . '_' . $state . '_icon_color', array(
                'label' => __('Icon color', 'dadsoo-agax-comment'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => array($selector . ' .icon' => 'color: {{VALUE}};'),
            ));
            $this->add_control($vote . '_' . $state . '_background_color', array(
                'label' => __('Background color', 'dadsoo-agax-comment'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => array($selector => 'background-color: {{VALUE}};'),
            ));
            $this->add_group_control(\Elementor\Group_Control_Border::get_type(), array(
                'name' => $vote . '_' . $state . '_border',
                'selector' => $selector,
            ));
            $this->end_controls_tab();
        }
        $this->end_controls_tabs();
        $this->end_controls_section();
    }

    protected function render()
    {
        $settings = $this->get_settings_for_display();

        // The comments themselves arrive through AJAX, so load Font Awesome before that request.
        \Elementor\Icons_Manager::enqueue_shim();

        $vote_icons = array(
            'like_outline' => $settings['like_outline_icon'],
            'like_fill' => $settings['like_fill_icon'],
            'dislike_outline' => $settings['dislike_outline_icon'],
            'dislike_fill' => $settings['dislike_fill_icon'],
        );

        // آواتار با همان اندازه‌ای که در استایل تعیین شده درخواست می‌شود تا بزرگ‌کردن آن تصویر را تار نکند.
        $avatar_size = 42;
        $avatar_setting = isset($settings['avatar_size']) ? $settings['avatar_size'] : array();
        if (!empty($avatar_setting['size']) && (empty($avatar_setting['unit']) || 'px' === $avatar_setting['unit'])) {
            $avatar_size = (int) $avatar_setting['size'];
        }

        $list_args = array(
            'items' => max(1, min(100, absint($settings['items']))),
            'vote_icons' => $vote_icons,
            'avatar_size' => $avatar_size,
            'load_more_text' => isset($settings['load_more_text']) ? $settings['load_more_text'] : '',
            'loading_text' => isset($settings['loading_text']) ? $settings['loading_text'] : '',
            'empty_text' => isset($settings['empty_text']) ? $settings['empty_text'] : '',
        );

        // render_comments_list() تمام مقادیر را داخل خودش با esc_attr/esc_html/wp_json_encode چاپ می‌کند.
        echo dadsoo_agax_comment()->render_comments_list($list_args); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }
}
