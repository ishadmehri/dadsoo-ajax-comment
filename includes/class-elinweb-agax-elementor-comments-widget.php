<?php
/**
 * Elementor widget for the Elin Agax comments list.
 *
 * @package Elinweb_Agax_Comment
 */

if (!defined('ABSPATH')) {
    exit;
}

class Elinweb_Agax_Elementor_Comments_Widget extends \Elementor\Widget_Base
{
    public function get_name()
    {
        return 'elinweb-agax-comments';
    }

    public function get_title()
    {
        return __('Elin Agax Comments', 'elin-agax-comment');
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
        return array('comment', 'comments', 'elinweb', 'agax', 'like', 'dislike');
    }

    protected function register_controls()
    {
        $this->start_controls_section('content_section', array(
            'label' => __('تنظیمات فهرست', 'elin-agax-comment'),
            'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
        ));

        $this->add_control('items', array(
            'label' => __('تعداد نظرات در بارگذاری اولیه', 'elin-agax-comment'),
            'type' => \Elementor\Controls_Manager::NUMBER,
            'default' => 5,
            'min' => 1,
            'max' => 100,
        ));

        $this->end_controls_section();

        $this->start_controls_section('icons_section', array(
            'label' => __('آیکون‌های رأی', 'elin-agax-comment'),
            'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
        ));

        $this->add_control('like_outline_icon', array(
            'label' => __('لایک، حالت غیرفعال', 'elin-agax-comment'),
            'type' => \Elementor\Controls_Manager::ICONS,
            'default' => array('value' => 'far fa-thumbs-up', 'library' => 'fa-regular'),
        ));
        $this->add_control('like_fill_icon', array(
            'label' => __('لایک، حالت فعال', 'elin-agax-comment'),
            'type' => \Elementor\Controls_Manager::ICONS,
            'default' => array('value' => 'fas fa-thumbs-up', 'library' => 'fa-solid'),
        ));
        $this->add_control('dislike_outline_icon', array(
            'label' => __('دیسلایک، حالت غیرفعال', 'elin-agax-comment'),
            'type' => \Elementor\Controls_Manager::ICONS,
            'default' => array('value' => 'far fa-thumbs-down', 'library' => 'fa-regular'),
        ));
        $this->add_control('dislike_fill_icon', array(
            'label' => __('دیسلایک، حالت فعال', 'elin-agax-comment'),
            'type' => \Elementor\Controls_Manager::ICONS,
            'default' => array('value' => 'fas fa-thumbs-down', 'library' => 'fa-solid'),
        ));

        $this->end_controls_section();

        $this->start_controls_section('author_style_section', array(
            'label' => __('نویسنده', 'elin-agax-comment'),
            'tab' => \Elementor\Controls_Manager::TAB_STYLE,
        ));
        $this->add_responsive_control('avatar_size', array(
            'label' => __('اندازه تصویر نویسنده', 'elin-agax-comment'),
            'type' => \Elementor\Controls_Manager::SLIDER,
            'range' => array('px' => array('min' => 20, 'max' => 160)),
            'selectors' => array('{{WRAPPER}} .dadsoo-comment-avatar' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};'),
        ));
        $this->add_responsive_control('avatar_radius', array(
            'label' => __('گردی تصویر نویسنده', 'elin-agax-comment'),
            'type' => \Elementor\Controls_Manager::DIMENSIONS,
            'size_units' => array('px', '%'),
            'selectors' => array('{{WRAPPER}} .dadsoo-comment-avatar' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'),
        ));
        $this->add_control('author_name_color', array(
            'label' => __('رنگ نام نویسنده', 'elin-agax-comment'),
            'type' => \Elementor\Controls_Manager::COLOR,
            'selectors' => array('{{WRAPPER}} .dadsoo-comment-author' => 'color: {{VALUE}};'),
        ));
        $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), array(
            'name' => 'author_name_typography',
            'selector' => '{{WRAPPER}} .dadsoo-comment-author',
        ));
        $this->end_controls_section();

        $this->start_controls_section('comment_style_section', array(
            'label' => __('متن نظر', 'elin-agax-comment'),
            'tab' => \Elementor\Controls_Manager::TAB_STYLE,
        ));
        $this->add_control('comment_text_color', array(
            'label' => __('رنگ متن', 'elin-agax-comment'),
            'type' => \Elementor\Controls_Manager::COLOR,
            'selectors' => array('{{WRAPPER}} .dadsoo-comment-text' => 'color: {{VALUE}};'),
        ));
        $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), array(
            'name' => 'comment_text_typography',
            'selector' => '{{WRAPPER}} .dadsoo-comment-text',
        ));
        $this->end_controls_section();

        $this->register_vote_style_controls('like', __('لایک', 'elin-agax-comment'));
        $this->register_vote_style_controls('dislike', __('دیسلایک', 'elin-agax-comment'));
    }

    private function register_vote_style_controls($vote, $label)
    {
        $this->start_controls_section($vote . '_style_section', array(
            'label' => sprintf(__('دکمه %s', 'elin-agax-comment'), $label),
            'tab' => \Elementor\Controls_Manager::TAB_STYLE,
        ));

        // بدون فاصلهٔ داخلی، رنگ پس‌زمینه و کادر به آیکون و شمارنده می‌چسبند.
        $this->add_responsive_control($vote . '_padding', array(
            'label' => __('فاصلهٔ داخلی', 'elin-agax-comment'),
            'type' => \Elementor\Controls_Manager::DIMENSIONS,
            'size_units' => array('px', 'em', '%'),
            'default' => array('top' => '6', 'right' => '10', 'bottom' => '6', 'left' => '10', 'unit' => 'px', 'isLinked' => false),
            'selectors' => array(
                '{{WRAPPER}} .dadsoo-vote-btn[data-vote="' . $vote . '"]' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ),
        ));

        $this->add_responsive_control($vote . '_border_radius', array(
            'label' => __('گردی گوشه‌ها', 'elin-agax-comment'),
            'type' => \Elementor\Controls_Manager::DIMENSIONS,
            'size_units' => array('px', '%'),
            'selectors' => array(
                '{{WRAPPER}} .dadsoo-vote-btn[data-vote="' . $vote . '"]' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ),
        ));

        $this->start_controls_tabs($vote . '_state_tabs');
        foreach (array('outline' => __('حالت غیرفعال (Outline)', 'elin-agax-comment'), 'fill' => __('حالت فعال (Fill)', 'elin-agax-comment')) as $state => $state_label) {
            $is_active = 'fill' === $state;
            $selector = '{{WRAPPER}} .dadsoo-vote-btn[data-vote="' . $vote . '"]' . ($is_active ? '.active' : ':not(.active)');
            $this->start_controls_tab($vote . '_' . $state . '_tab', array('label' => $state_label));
            $this->add_control($vote . '_' . $state . '_icon_color', array(
                'label' => __('رنگ آیکون', 'elin-agax-comment'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => array($selector . ' .icon' => 'color: {{VALUE}};'),
            ));
            $this->add_control($vote . '_' . $state . '_background_color', array(
                'label' => __('رنگ پس‌زمینه', 'elin-agax-comment'),
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

        echo elinweb_agax_comment()->render_comments_list(array(
            'items' => max(1, min(100, absint($settings['items']))),
            'vote_icons' => $vote_icons,
            'avatar_size' => $avatar_size,
        )); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }
}
