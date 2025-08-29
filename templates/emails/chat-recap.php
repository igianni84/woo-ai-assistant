<?php
/**
 * Chat Recap Email Template
 *
 * This template is used to send a conversation recap to customers after
 * their chat session has been resolved by a human agent. It includes
 * the resolution summary and complete conversation history.
 *
 * @package WooAiAssistant
 * @subpackage Templates/Emails
 * @since 1.0.0
 * 
 * Available variables:
 * - $handoff_id: The handoff record ID
 * - $conversation_id: The conversation ID
 * - $user_email: Customer email address
 * - $user_name: Customer display name
 * - $started_at: Conversation start time
 * - $resolved_at: Resolution time
 * - $resolution_notes: Agent's resolution notes
 * - $transcript: Array containing conversation messages
 * - $agent_name: Name of the agent who resolved the issue
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Get site info
$site_name = get_bloginfo('name');
$site_url = get_site_url();
$logo_url = get_option('woo_ai_assistant_logo_url', '');
$support_email = get_option('woo_ai_assistant_support_email', get_option('admin_email'));

// Format timestamps
$started_at_formatted = wp_date('F j, Y \a\t g:i A', strtotime($started_at));
$resolved_at_formatted = isset($resolved_at) ? wp_date('F j, Y \a\t g:i A', strtotime($resolved_at)) : '';

// Count messages
$message_count = count($transcript['messages'] ?? []);

// Extract context
$context = $transcript['context'] ?? [];
$products_discussed = $context['products_viewed'] ?? [];
$coupons_applied = $context['coupons_applied'] ?? [];
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title><?php esc_html_e('Your Conversation Summary', 'woo-ai-assistant'); ?></title>
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
    <style type="text/css">
        /* Email Client Reset */
        body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { -ms-interpolation-mode: bicubic; border: 0; outline: none; text-decoration: none; }
        
        /* Remove default styling */
        body { margin: 0; padding: 0; width: 100% !important; min-width: 100%; }
        
        /* Mobile Responsive */
        @media screen and (max-width: 600px) {
            .container { width: 100% !important; max-width: 100% !important; }
            .content { padding: 20px !important; }
            .button { width: 100% !important; text-align: center !important; }
            .transcript-message { padding: 10px !important; }
            .two-column { width: 100% !important; display: block !important; }
        }
        
        /* Dark Mode Support */
        @media (prefers-color-scheme: dark) {
            body { background-color: #1a1a1a !important; }
            .email-body { background-color: #2a2a2a !important; color: #ffffff !important; }
            .header { background-color: #333333 !important; }
            .message-user { background-color: #404040 !important; }
            .message-assistant { background-color: #353535 !important; }
            .message-agent { background-color: #2d4a2b !important; }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; background-color: #f4f4f4; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;">
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #f4f4f4;">
        <tr>
            <td align="center" style="padding: 40px 10px;">
                
                <!-- Email Container -->
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="600" class="container" style="background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    
                    <!-- Header -->
                    <tr>
                        <td class="header" style="background-color: #27ae60; padding: 30px 40px; border-radius: 8px 8px 0 0;">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td align="left">
                                        <?php if ($logo_url): ?>
                                            <img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($site_name); ?>" style="height: 40px; width: auto;">
                                        <?php else: ?>
                                            <h1 style="margin: 0; color: #ffffff; font-size: 24px; font-weight: 600;">
                                                <?php echo esc_html($site_name); ?>
                                            </h1>
                                        <?php endif; ?>
                                    </td>
                                    <td align="right">
                                        <span style="color: #ffffff; font-size: 14px; opacity: 0.9;">
                                            <?php esc_html_e('Conversation Recap', 'woo-ai-assistant'); ?>
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                    <!-- Success Message -->
                    <tr>
                        <td style="padding: 30px 40px 20px 40px;">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td style="width: 50px; vertical-align: top;">
                                        <span style="font-size: 32px;">✅</span>
                                    </td>
                                    <td>
                                        <h2 style="margin: 0 0 10px 0; color: #333; font-size: 22px; font-weight: 600;">
                                            <?php echo sprintf(
                                                /* translators: %s: customer name */
                                                esc_html__('Hi %s,', 'woo-ai-assistant'),
                                                esc_html($user_name ?: __('there', 'woo-ai-assistant'))
                                            ); ?>
                                        </h2>
                                        <p style="margin: 0; color: #666; font-size: 15px; line-height: 1.5;">
                                            <?php esc_html_e('Your conversation has been resolved by our support team. Below is a complete summary of your chat session for your records.', 'woo-ai-assistant'); ?>
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                    <!-- Resolution Summary -->
                    <?php if (!empty($resolution_notes)): ?>
                    <tr>
                        <td style="padding: 0 40px 30px 40px;">
                            <div style="background-color: #e8f8f5; border-left: 4px solid #27ae60; padding: 15px; border-radius: 4px;">
                                <h3 style="margin: 0 0 10px 0; color: #27ae60; font-size: 16px; font-weight: 600;">
                                    <?php esc_html_e('Resolution Summary', 'woo-ai-assistant'); ?>
                                </h3>
                                <p style="margin: 0; color: #333; font-size: 14px; line-height: 1.5;">
                                    <?php echo wp_kses_post(nl2br(esc_html($resolution_notes))); ?>
                                </p>
                                <?php if (!empty($agent_name)): ?>
                                <p style="margin: 10px 0 0 0; color: #666; font-size: 13px; font-style: italic;">
                                    <?php echo sprintf(
                                        /* translators: %s: agent name */
                                        esc_html__('— Resolved by %s', 'woo-ai-assistant'),
                                        esc_html($agent_name)
                                    ); ?>
                                </p>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                    
                    <!-- Conversation Details -->
                    <tr>
                        <td class="content" style="padding: 0 40px 30px 40px;">
                            <h3 style="margin: 0 0 20px 0; color: #333; font-size: 16px; font-weight: 600; border-bottom: 2px solid #e0e0e0; padding-bottom: 10px;">
                                <?php esc_html_e('Conversation Details', 'woo-ai-assistant'); ?>
                            </h3>
                            
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td class="two-column" style="width: 50%; padding-right: 10px; vertical-align: top;">
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                            <tr>
                                                <td style="padding: 5px 0;">
                                                    <strong style="color: #666; font-size: 13px;">
                                                        <?php esc_html_e('Reference:', 'woo-ai-assistant'); ?>
                                                    </strong>
                                                    <div style="color: #333; font-size: 13px;">
                                                        #<?php echo esc_html($conversation_id); ?>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 5px 0;">
                                                    <strong style="color: #666; font-size: 13px;">
                                                        <?php esc_html_e('Started:', 'woo-ai-assistant'); ?>
                                                    </strong>
                                                    <div style="color: #333; font-size: 13px;">
                                                        <?php echo esc_html($started_at_formatted); ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                    <td class="two-column" style="width: 50%; padding-left: 10px; vertical-align: top;">
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                            <?php if ($resolved_at_formatted): ?>
                                            <tr>
                                                <td style="padding: 5px 0;">
                                                    <strong style="color: #666; font-size: 13px;">
                                                        <?php esc_html_e('Resolved:', 'woo-ai-assistant'); ?>
                                                    </strong>
                                                    <div style="color: #333; font-size: 13px;">
                                                        <?php echo esc_html($resolved_at_formatted); ?>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endif; ?>
                                            <tr>
                                                <td style="padding: 5px 0;">
                                                    <strong style="color: #666; font-size: 13px;">
                                                        <?php esc_html_e('Messages:', 'woo-ai-assistant'); ?>
                                                    </strong>
                                                    <div style="color: #333; font-size: 13px;">
                                                        <?php echo esc_html($message_count); ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                    <!-- Products Discussed -->
                    <?php if (!empty($products_discussed)): ?>
                    <tr>
                        <td style="padding: 0 40px 30px 40px;">
                            <h3 style="margin: 0 0 15px 0; color: #333; font-size: 16px; font-weight: 600;">
                                <?php esc_html_e('Products Discussed', 'woo-ai-assistant'); ?>
                            </h3>
                            
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="border: 1px solid #e0e0e0; border-radius: 5px;">
                                <?php 
                                $product_counter = 0;
                                foreach ($products_discussed as $product_id): 
                                    $product = wc_get_product($product_id);
                                    if ($product):
                                        $product_counter++;
                                ?>
                                <tr>
                                    <td style="padding: 12px 15px; <?php echo $product_counter > 1 ? 'border-top: 1px solid #e0e0e0;' : ''; ?>">
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                            <tr>
                                                <?php if ($product->get_image_id()): ?>
                                                <td style="width: 60px; vertical-align: top; padding-right: 15px;">
                                                    <img src="<?php echo esc_url(wp_get_attachment_image_url($product->get_image_id(), 'thumbnail')); ?>" alt="<?php echo esc_attr($product->get_name()); ?>" style="width: 60px; height: 60px; object-fit: cover; border-radius: 4px;">
                                                </td>
                                                <?php endif; ?>
                                                <td style="vertical-align: top;">
                                                    <a href="<?php echo esc_url($product->get_permalink()); ?>" style="color: #333; text-decoration: none; font-weight: 600; font-size: 14px;">
                                                        <?php echo esc_html($product->get_name()); ?>
                                                    </a>
                                                    <div style="margin-top: 5px;">
                                                        <span style="color: #27ae60; font-weight: 600; font-size: 14px;">
                                                            <?php echo wp_kses_post($product->get_price_html()); ?>
                                                        </span>
                                                        <?php if ($product->is_in_stock()): ?>
                                                            <span style="color: #666; font-size: 12px; margin-left: 10px;">
                                                                ✓ <?php esc_html_e('In Stock', 'woo-ai-assistant'); ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td style="width: 100px; text-align: right; vertical-align: middle;">
                                                    <a href="<?php echo esc_url(add_query_arg('add-to-cart', $product_id, $product->get_permalink())); ?>" style="display: inline-block; padding: 6px 12px; background-color: #3498db; color: #ffffff; text-decoration: none; border-radius: 3px; font-size: 12px;">
                                                        <?php esc_html_e('Add to Cart', 'woo-ai-assistant'); ?>
                                                    </a>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <?php 
                                    endif;
                                endforeach; 
                                ?>
                            </table>
                        </td>
                    </tr>
                    <?php endif; ?>
                    
                    <!-- Coupons Applied -->
                    <?php if (!empty($coupons_applied)): ?>
                    <tr>
                        <td style="padding: 0 40px 30px 40px;">
                            <h3 style="margin: 0 0 15px 0; color: #333; font-size: 16px; font-weight: 600;">
                                <?php esc_html_e('Coupons Applied', 'woo-ai-assistant'); ?>
                            </h3>
                            
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <?php foreach ($coupons_applied as $coupon_code): ?>
                                <tr>
                                    <td style="padding: 8px; background-color: #fff3cd; border: 1px dashed #ffc107; border-radius: 4px; margin-bottom: 8px;">
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                            <tr>
                                                <td style="width: 30px;">
                                                    <span style="font-size: 20px;">🎟️</span>
                                                </td>
                                                <td>
                                                    <strong style="color: #856404; font-size: 14px;">
                                                        <?php echo esc_html($coupon_code); ?>
                                                    </strong>
                                                    <?php 
                                                    $coupon = new WC_Coupon($coupon_code);
                                                    if ($coupon->get_id()):
                                                    ?>
                                                    <span style="color: #856404; font-size: 12px; margin-left: 10px;">
                                                        <?php 
                                                        if ($coupon->get_discount_type() === 'percent') {
                                                            echo sprintf(
                                                                /* translators: %s: discount percentage */
                                                                esc_html__('%s%% off', 'woo-ai-assistant'),
                                                                $coupon->get_amount()
                                                            );
                                                        } else {
                                                            echo wc_price($coupon->get_amount()) . ' ' . esc_html__('off', 'woo-ai-assistant');
                                                        }
                                                        ?>
                                                    </span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </table>
                        </td>
                    </tr>
                    <?php endif; ?>
                    
                    <!-- Conversation History -->
                    <tr>
                        <td style="padding: 0 40px 30px 40px;">
                            <h3 style="margin: 0 0 20px 0; color: #333; font-size: 16px; font-weight: 600; border-bottom: 2px solid #e0e0e0; padding-bottom: 10px;">
                                <?php esc_html_e('Complete Conversation', 'woo-ai-assistant'); ?>
                            </h3>
                            
                            <div style="border: 1px solid #e0e0e0; border-radius: 5px; padding: 15px; background-color: #f9f9f9; max-height: 400px; overflow-y: auto;">
                                <?php if (!empty($transcript['messages'])): ?>
                                    <?php foreach ($transcript['messages'] as $message): ?>
                                        <?php 
                                        $is_user = $message['role'] === 'user';
                                        $is_agent = $message['role'] === 'agent';
                                        $role_label = $is_user ? __('You', 'woo-ai-assistant') : 
                                                     ($is_agent ? __('Support Agent', 'woo-ai-assistant') : __('AI Assistant', 'woo-ai-assistant'));
                                        $message_time = wp_date('g:i A', strtotime($message['timestamp']));
                                        $bg_color = $is_user ? '#e3f2fd' : ($is_agent ? '#e8f5e9' : '#f5f5f5');
                                        $text_color = $is_user ? '#1976d2' : ($is_agent ? '#2e7d32' : '#666');
                                        ?>
                                        <div class="transcript-message <?php echo $is_user ? 'message-user' : ($is_agent ? 'message-agent' : 'message-assistant'); ?>" style="margin-bottom: 12px; padding: 10px; border-radius: 5px; background-color: <?php echo $bg_color; ?>;">
                                            <div style="margin-bottom: 5px;">
                                                <strong style="color: <?php echo $text_color; ?>; font-size: 13px;">
                                                    <?php echo esc_html($role_label); ?>
                                                </strong>
                                                <span style="color: #999; font-size: 11px; margin-left: 8px;">
                                                    <?php echo esc_html($message_time); ?>
                                                </span>
                                            </div>
                                            <div style="color: #333; font-size: 14px; line-height: 1.4;">
                                                <?php echo wp_kses_post(nl2br(esc_html($message['content']))); ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p style="color: #666; font-size: 14px; text-align: center;">
                                        <?php esc_html_e('No messages to display.', 'woo-ai-assistant'); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    
                    <!-- Call to Action -->
                    <tr>
                        <td style="padding: 0 40px 30px 40px;">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 8px; padding: 20px;">
                                <tr>
                                    <td align="center">
                                        <h3 style="margin: 0 0 10px 0; color: #ffffff; font-size: 18px; font-weight: 600;">
                                            <?php esc_html_e('Need More Help?', 'woo-ai-assistant'); ?>
                                        </h3>
                                        <p style="margin: 0 0 15px 0; color: #ffffff; font-size: 14px; opacity: 0.95;">
                                            <?php esc_html_e('We\'re always here to assist you with any questions or concerns.', 'woo-ai-assistant'); ?>
                                        </p>
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center">
                                            <tr>
                                                <td style="padding-right: 10px;">
                                                    <a href="<?php echo esc_url($site_url); ?>" class="button" style="display: inline-block; padding: 10px 20px; background-color: #ffffff; color: #764ba2; text-decoration: none; border-radius: 25px; font-weight: 600; font-size: 14px;">
                                                        <?php esc_html_e('Visit Our Store', 'woo-ai-assistant'); ?>
                                                    </a>
                                                </td>
                                                <td>
                                                    <a href="mailto:<?php echo esc_attr($support_email); ?>" class="button" style="display: inline-block; padding: 10px 20px; background-color: transparent; color: #ffffff; text-decoration: none; border: 2px solid #ffffff; border-radius: 25px; font-weight: 600; font-size: 14px;">
                                                        <?php esc_html_e('Contact Support', 'woo-ai-assistant'); ?>
                                                    </a>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                    <!-- Feedback Request -->
                    <tr>
                        <td style="padding: 0 40px 30px 40px;">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #f0f0f0; border-radius: 5px; padding: 15px;">
                                <tr>
                                    <td align="center">
                                        <p style="margin: 0 0 10px 0; color: #666; font-size: 14px;">
                                            <?php esc_html_e('How was your experience?', 'woo-ai-assistant'); ?>
                                        </p>
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center">
                                            <tr>
                                                <td style="padding: 0 5px;">
                                                    <a href="<?php echo esc_url(add_query_arg(['rating' => 5, 'conversation' => $conversation_id], $site_url . '/rate-experience')); ?>" style="font-size: 24px; text-decoration: none;">⭐</a>
                                                </td>
                                                <td style="padding: 0 5px;">
                                                    <a href="<?php echo esc_url(add_query_arg(['rating' => 4, 'conversation' => $conversation_id], $site_url . '/rate-experience')); ?>" style="font-size: 24px; text-decoration: none;">⭐</a>
                                                </td>
                                                <td style="padding: 0 5px;">
                                                    <a href="<?php echo esc_url(add_query_arg(['rating' => 3, 'conversation' => $conversation_id], $site_url . '/rate-experience')); ?>" style="font-size: 24px; text-decoration: none;">⭐</a>
                                                </td>
                                                <td style="padding: 0 5px;">
                                                    <a href="<?php echo esc_url(add_query_arg(['rating' => 2, 'conversation' => $conversation_id], $site_url . '/rate-experience')); ?>" style="font-size: 24px; text-decoration: none;">⭐</a>
                                                </td>
                                                <td style="padding: 0 5px;">
                                                    <a href="<?php echo esc_url(add_query_arg(['rating' => 1, 'conversation' => $conversation_id], $site_url . '/rate-experience')); ?>" style="font-size: 24px; text-decoration: none;">⭐</a>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="padding: 20px 40px; background-color: #f8f8f8; border-top: 1px solid #e0e0e0; border-radius: 0 0 8px 8px;">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td align="center">
                                        <p style="margin: 0 0 10px 0; color: #999; font-size: 12px;">
                                            <?php echo sprintf(
                                                /* translators: %s: site name */
                                                esc_html__('Thank you for shopping with %s', 'woo-ai-assistant'),
                                                esc_html($site_name)
                                            ); ?>
                                        </p>
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center">
                                            <tr>
                                                <td style="padding: 0 10px;">
                                                    <a href="<?php echo esc_url($site_url); ?>" style="color: #3498db; font-size: 12px; text-decoration: none;">
                                                        <?php esc_html_e('Visit Store', 'woo-ai-assistant'); ?>
                                                    </a>
                                                </td>
                                                <td style="color: #ccc;">|</td>
                                                <td style="padding: 0 10px;">
                                                    <a href="<?php echo esc_url($site_url . '/my-account'); ?>" style="color: #3498db; font-size: 12px; text-decoration: none;">
                                                        <?php esc_html_e('My Account', 'woo-ai-assistant'); ?>
                                                    </a>
                                                </td>
                                                <td style="color: #ccc;">|</td>
                                                <td style="padding: 0 10px;">
                                                    <a href="<?php echo esc_url($site_url . '/privacy-policy'); ?>" style="color: #3498db; font-size: 12px; text-decoration: none;">
                                                        <?php esc_html_e('Privacy Policy', 'woo-ai-assistant'); ?>
                                                    </a>
                                                </td>
                                            </tr>
                                        </table>
                                        <p style="margin: 10px 0 0 0; color: #999; font-size: 11px;">
                                            <?php echo sprintf(
                                                /* translators: %s: current year */
                                                esc_html__('© %s All rights reserved', 'woo-ai-assistant'),
                                                date('Y')
                                            ); ?>
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                </table>
                <!-- End Email Container -->
                
                <!-- Unsubscribe -->
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="600" align="center">
                    <tr>
                        <td align="center" style="padding: 20px 0;">
                            <p style="margin: 0; color: #999; font-size: 11px;">
                                <?php esc_html_e('You received this email because you had a support conversation on our website.', 'woo-ai-assistant'); ?>
                                <br>
                                <a href="<?php echo esc_url($site_url . '/email-preferences'); ?>" style="color: #999; text-decoration: underline;">
                                    <?php esc_html_e('Manage email preferences', 'woo-ai-assistant'); ?>
                                </a>
                            </p>
                        </td>
                    </tr>
                </table>
                
            </td>
        </tr>
    </table>
</body>
</html>