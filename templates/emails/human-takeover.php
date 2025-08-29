<?php
/**
 * Human Takeover Email Template
 *
 * This template is used to notify administrators when a conversation requires
 * human intervention. It includes the full conversation transcript and quick
 * action buttons for the admin to respond.
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
 * - $transcript: Array containing conversation messages
 * - $admin_url: Direct link to admin handoff management page
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Get site info
$site_name = get_bloginfo('name');
$site_url = get_site_url();
$logo_url = get_option('woo_ai_assistant_logo_url', '');

// Format timestamps
$started_at_formatted = wp_date('F j, Y \a\t g:i A', strtotime($started_at));
$current_time = wp_date('F j, Y \a\t g:i A');

// Count messages
$message_count = count($transcript['messages'] ?? []);
$conversation_duration = isset($transcript['duration']) ? round($transcript['duration'] / 60) : 0;

// Extract context
$context = $transcript['context'] ?? [];
$current_page = $context['current_page'] ?? '';
$cart_value = $context['cart_value'] ?? 0;
$products_viewed = $context['products_viewed'] ?? [];
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title><?php esc_html_e('Human Assistance Required', 'woo-ai-assistant'); ?></title>
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
        }
        
        /* Dark Mode Support */
        @media (prefers-color-scheme: dark) {
            body { background-color: #1a1a1a !important; }
            .email-body { background-color: #2a2a2a !important; color: #ffffff !important; }
            .header { background-color: #333333 !important; }
            .message-user { background-color: #404040 !important; }
            .message-assistant { background-color: #353535 !important; }
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
                        <td class="header" style="background-color: #e74c3c; padding: 30px 40px; border-radius: 8px 8px 0 0;">
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
                                            <?php esc_html_e('URGENT', 'woo-ai-assistant'); ?>
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                    <!-- Alert Banner -->
                    <tr>
                        <td style="padding: 20px 40px; background-color: #fff5f5; border-bottom: 1px solid #fddede;">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td style="width: 40px; vertical-align: top;">
                                        <span style="font-size: 24px;">⚠️</span>
                                    </td>
                                    <td>
                                        <h2 style="margin: 0 0 5px 0; color: #c0392b; font-size: 18px; font-weight: 600;">
                                            <?php esc_html_e('Human Assistance Required', 'woo-ai-assistant'); ?>
                                        </h2>
                                        <p style="margin: 0; color: #666; font-size: 14px;">
                                            <?php echo sprintf(
                                                /* translators: %s: conversation ID */
                                                esc_html__('The AI assistant needs help with conversation #%s', 'woo-ai-assistant'),
                                                esc_html($conversation_id)
                                            ); ?>
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                    <!-- Customer Information -->
                    <tr>
                        <td class="content" style="padding: 30px 40px;">
                            <h3 style="margin: 0 0 20px 0; color: #333; font-size: 16px; font-weight: 600; border-bottom: 2px solid #e0e0e0; padding-bottom: 10px;">
                                <?php esc_html_e('Customer Information', 'woo-ai-assistant'); ?>
                            </h3>
                            
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td style="padding: 5px 0;">
                                        <strong style="color: #666; font-size: 14px; display: inline-block; width: 120px;">
                                            <?php esc_html_e('Name:', 'woo-ai-assistant'); ?>
                                        </strong>
                                        <span style="color: #333; font-size: 14px;">
                                            <?php echo esc_html($user_name ?: __('Guest User', 'woo-ai-assistant')); ?>
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 5px 0;">
                                        <strong style="color: #666; font-size: 14px; display: inline-block; width: 120px;">
                                            <?php esc_html_e('Email:', 'woo-ai-assistant'); ?>
                                        </strong>
                                        <a href="mailto:<?php echo esc_attr($user_email); ?>" style="color: #3498db; font-size: 14px; text-decoration: none;">
                                            <?php echo esc_html($user_email); ?>
                                        </a>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 5px 0;">
                                        <strong style="color: #666; font-size: 14px; display: inline-block; width: 120px;">
                                            <?php esc_html_e('Started:', 'woo-ai-assistant'); ?>
                                        </strong>
                                        <span style="color: #333; font-size: 14px;">
                                            <?php echo esc_html($started_at_formatted); ?>
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 5px 0;">
                                        <strong style="color: #666; font-size: 14px; display: inline-block; width: 120px;">
                                            <?php esc_html_e('Duration:', 'woo-ai-assistant'); ?>
                                        </strong>
                                        <span style="color: #333; font-size: 14px;">
                                            <?php echo sprintf(
                                                /* translators: %1$d: duration in minutes, %2$d: message count */
                                                esc_html__('%1$d minutes (%2$d messages)', 'woo-ai-assistant'),
                                                $conversation_duration,
                                                $message_count
                                            ); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php if ($current_page): ?>
                                <tr>
                                    <td style="padding: 5px 0;">
                                        <strong style="color: #666; font-size: 14px; display: inline-block; width: 120px;">
                                            <?php esc_html_e('Current Page:', 'woo-ai-assistant'); ?>
                                        </strong>
                                        <span style="color: #333; font-size: 14px;">
                                            <?php echo esc_html($current_page); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endif; ?>
                                <?php if ($cart_value > 0): ?>
                                <tr>
                                    <td style="padding: 5px 0;">
                                        <strong style="color: #666; font-size: 14px; display: inline-block; width: 120px;">
                                            <?php esc_html_e('Cart Value:', 'woo-ai-assistant'); ?>
                                        </strong>
                                        <span style="color: #27ae60; font-size: 14px; font-weight: 600;">
                                            <?php echo wc_price($cart_value); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </table>
                        </td>
                    </tr>
                    
                    <!-- Action Buttons -->
                    <tr>
                        <td style="padding: 0 40px 30px 40px;">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td align="center">
                                        <a href="<?php echo esc_url($admin_url); ?>" class="button" style="display: inline-block; padding: 14px 30px; background-color: #3498db; color: #ffffff; text-decoration: none; border-radius: 5px; font-weight: 600; font-size: 14px;">
                                            <?php esc_html_e('Take Over Conversation', 'woo-ai-assistant'); ?> →
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                    <!-- Conversation Transcript -->
                    <tr>
                        <td style="padding: 0 40px 30px 40px;">
                            <h3 style="margin: 0 0 20px 0; color: #333; font-size: 16px; font-weight: 600; border-bottom: 2px solid #e0e0e0; padding-bottom: 10px;">
                                <?php esc_html_e('Conversation Transcript', 'woo-ai-assistant'); ?>
                            </h3>
                            
                            <div style="max-height: 500px; overflow-y: auto; border: 1px solid #e0e0e0; border-radius: 5px; padding: 15px; background-color: #f9f9f9;">
                                <?php if (!empty($transcript['messages'])): ?>
                                    <?php foreach ($transcript['messages'] as $message): ?>
                                        <?php 
                                        $is_user = $message['role'] === 'user';
                                        $role_label = $is_user ? __('Customer', 'woo-ai-assistant') : __('AI Assistant', 'woo-ai-assistant');
                                        $message_time = wp_date('g:i A', strtotime($message['timestamp']));
                                        ?>
                                        <div class="transcript-message <?php echo $is_user ? 'message-user' : 'message-assistant'; ?>" style="margin-bottom: 15px; padding: 12px; border-radius: 5px; background-color: <?php echo $is_user ? '#e3f2fd' : '#f5f5f5'; ?>;">
                                            <div style="margin-bottom: 5px;">
                                                <strong style="color: <?php echo $is_user ? '#1976d2' : '#666'; ?>; font-size: 13px;">
                                                    <?php echo esc_html($role_label); ?>
                                                </strong>
                                                <span style="color: #999; font-size: 12px; margin-left: 10px;">
                                                    <?php echo esc_html($message_time); ?>
                                                </span>
                                            </div>
                                            <div style="color: #333; font-size: 14px; line-height: 1.5;">
                                                <?php echo wp_kses_post(nl2br(esc_html($message['content']))); ?>
                                            </div>
                                            <?php if (!empty($message['metadata'])): ?>
                                                <div style="margin-top: 8px; padding-top: 8px; border-top: 1px solid #ddd;">
                                                    <small style="color: #999; font-size: 11px;">
                                                        <?php 
                                                        if (isset($message['metadata']['intent'])) {
                                                            echo sprintf(
                                                                /* translators: %s: detected intent */
                                                                esc_html__('Intent: %s', 'woo-ai-assistant'),
                                                                esc_html($message['metadata']['intent'])
                                                            );
                                                        }
                                                        if (isset($message['metadata']['sentiment'])) {
                                                            echo ' | ' . sprintf(
                                                                /* translators: %s: sentiment score */
                                                                esc_html__('Sentiment: %s', 'woo-ai-assistant'),
                                                                esc_html($message['metadata']['sentiment'])
                                                            );
                                                        }
                                                        ?>
                                                    </small>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p style="color: #666; font-size: 14px; text-align: center;">
                                        <?php esc_html_e('No messages in this conversation.', 'woo-ai-assistant'); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    
                    <!-- Additional Context -->
                    <?php if (!empty($products_viewed)): ?>
                    <tr>
                        <td style="padding: 0 40px 30px 40px;">
                            <h4 style="margin: 0 0 10px 0; color: #666; font-size: 14px; font-weight: 600;">
                                <?php esc_html_e('Products Viewed:', 'woo-ai-assistant'); ?>
                            </h4>
                            <ul style="margin: 0; padding: 0 0 0 20px; color: #666; font-size: 14px;">
                                <?php foreach ($products_viewed as $product_id): ?>
                                    <?php 
                                    $product = wc_get_product($product_id);
                                    if ($product): 
                                    ?>
                                    <li style="margin-bottom: 5px;">
                                        <a href="<?php echo esc_url($product->get_permalink()); ?>" style="color: #3498db; text-decoration: none;">
                                            <?php echo esc_html($product->get_name()); ?>
                                        </a>
                                        <span style="color: #27ae60; margin-left: 5px;">
                                            (<?php echo $product->get_price_html(); ?>)
                                        </span>
                                    </li>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </ul>
                        </td>
                    </tr>
                    <?php endif; ?>
                    
                    <!-- Quick Actions -->
                    <tr>
                        <td style="padding: 0 40px 30px 40px;">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #f0f0f0; border-radius: 5px; padding: 15px;">
                                <tr>
                                    <td>
                                        <h4 style="margin: 0 0 10px 0; color: #333; font-size: 14px; font-weight: 600;">
                                            <?php esc_html_e('Quick Actions:', 'woo-ai-assistant'); ?>
                                        </h4>
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                                            <tr>
                                                <td style="padding-right: 10px;">
                                                    <a href="mailto:<?php echo esc_attr($user_email); ?>?subject=<?php echo rawurlencode(sprintf(__('Re: Your conversation on %s', 'woo-ai-assistant'), $site_name)); ?>" style="display: inline-block; padding: 8px 16px; background-color: #27ae60; color: #ffffff; text-decoration: none; border-radius: 3px; font-size: 13px;">
                                                        <?php esc_html_e('Email Customer', 'woo-ai-assistant'); ?>
                                                    </a>
                                                </td>
                                                <td style="padding-right: 10px;">
                                                    <a href="<?php echo esc_url(admin_url('admin.php?page=woo-ai-assistant-conversations')); ?>" style="display: inline-block; padding: 8px 16px; background-color: #95a5a6; color: #ffffff; text-decoration: none; border-radius: 3px; font-size: 13px;">
                                                        <?php esc_html_e('View All Conversations', 'woo-ai-assistant'); ?>
                                                    </a>
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
                                                /* translators: %s: plugin name */
                                                esc_html__('This is an automated notification from %s', 'woo-ai-assistant'),
                                                'Woo AI Assistant'
                                            ); ?>
                                        </p>
                                        <p style="margin: 0; color: #999; font-size: 12px;">
                                            <a href="<?php echo esc_url($site_url); ?>" style="color: #3498db; text-decoration: none;">
                                                <?php echo esc_html($site_name); ?>
                                            </a>
                                            <?php if (get_option('woo_ai_assistant_support_email')): ?>
                                            | <a href="mailto:<?php echo esc_attr(get_option('woo_ai_assistant_support_email')); ?>" style="color: #3498db; text-decoration: none;">
                                                <?php esc_html_e('Support', 'woo-ai-assistant'); ?>
                                            </a>
                                            <?php endif; ?>
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                </table>
                <!-- End Email Container -->
                
            </td>
        </tr>
    </table>
</body>
</html>