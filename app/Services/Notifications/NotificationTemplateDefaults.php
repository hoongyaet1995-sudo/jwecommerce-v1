<?php

namespace App\Services\Notifications;

use App\Domain\Notifications\NotificationEventCatalog;
use App\Models\NotificationTemplate;

class NotificationTemplateDefaults
{
    public function synchronize(): void
    {
        foreach ($this->templates() as $template) {
            NotificationTemplate::query()->firstOrCreate(
                ['event_key' => $template['event_key']],
                $template,
            );
        }
    }

    private function templates(): array
    {
        return [
            $this->template('order_created', [
                'email_enabled' => true,
                'email_subject' => '[{shop_name}] Order #{order_number} Received - Please Complete Payment',
                'email_preheader' => 'Thank you for your order! Please complete payment and contact Support if you need help.',
                'email_heading' => 'Order Confirmation & Payment Instructions',
                'email_body' => <<<'BODY'
Dear {customer_name},

Thank you for choosing {shop_name}! We have received your order #{order_number} totaling {order_total}.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Order Summary:
• Order Number: #{order_number}
• Order Total: {order_total}
• Payment Method: {payment_method}
• Payment Reference: {payment_reference}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━

If you selected Bank In / DuitNow Transfer, please transfer the exact grand total using your payment reference.

Once payment is complete, you can review your order status below. If you need help with payment, please contact Support.

{staff_note}

Warm regards,
The {shop_name} Team
BODY,
                'email_cta_text' => 'View Order Status',
                'email_cta_url' => '{order_url}',
                'whatsapp_enabled' => true,
                'whatsapp_message' => <<<'MESSAGE'
Hi *{customer_name}*! 👋

Thank you for placing order *#{order_number}* at *{shop_name}*!

💰 *Amount Due:* {order_total}
💳 *Method:* {payment_method}
🔖 *Reference:* {payment_reference}

Once payment is complete, please contact Support if you need help confirming your order.

{staff_note}

_Need help? Reply directly to this chat._
MESSAGE,
                'whatsapp_cta_buttons' => [
                    ['id' => 'btn_1', 'text' => '📦 View Order', 'type' => 'url', 'value' => '{order_url}'],
                    ['id' => 'btn_2', 'text' => '💬 Chat Support', 'type' => 'url', 'value' => '{shop_whatsapp}'],
                ],
            ]),
            $this->template('payment_verified', [
                'email_enabled' => true,
                'email_subject' => '[{shop_name}] Payment Verified for Order #{order_number} - Preparing for Dispatch',
                'email_preheader' => 'Your payment was successfully verified! Our fulfillment team is now packing your order.',
                'email_heading' => 'Payment Verified & Preparing Shipment',
                'email_body' => <<<'BODY'
Dear {customer_name},

Great news! Your payment for order #{order_number} has been verified and captured successfully.

Our fulfillment warehouse has commenced picking, quality checking, and protective packing for your shipment.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━
• Order: #{order_number}
• Verified Amount: {order_total}
• Shipping Destination: {shipping_address}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━

{staff_note}

We will notify you with the courier airway bill and live tracking link as soon as your parcel is handed over.
BODY,
                'email_cta_text' => 'View Order Status',
                'email_cta_url' => '{order_url}',
                'whatsapp_enabled' => true,
                'whatsapp_message' => <<<'MESSAGE'
Hi *{customer_name}*! ✅

Your payment of *{order_total}* for order *#{order_number}* has been *verified and approved*!

📦 Our fulfillment team is now packing your order. We will send your tracking link as soon as it is picked up by courier.

{staff_note}

Track your order:
👉 {order_url}
MESSAGE,
                'whatsapp_cta_buttons' => [
                    ['id' => 'btn_1', 'text' => '📦 View Order', 'type' => 'url', 'value' => '{order_url}'],
                ],
            ]),
            $this->template('payment_proof_rejected', [
                'email_enabled' => true,
                'email_subject' => '[Action Required] {shop_name} - Payment Proof Issue for Order #{order_number}',
                'email_preheader' => 'We could not verify your transfer slip. Please contact Support for help resolving the issue.',
                'email_heading' => 'Payment Verification Unsuccessful',
                'email_body' => <<<'BODY'
Dear {customer_name},

We reviewed the payment proof submitted for order #{order_number}, but our accounting team was unable to verify the transaction.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Rejection Reason:
{payment_proof_reason}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━

{staff_note}

Please contact Support if you need help resolving this payment verification issue.
BODY,
                'email_cta_text' => 'View Order Status',
                'email_cta_url' => '{order_url}',
                'whatsapp_enabled' => true,
                'whatsapp_message' => <<<'MESSAGE'
Hi *{customer_name}*, ⚠️

We were unable to verify your payment receipt for order *#{order_number}*.

*Reason:* {payment_proof_reason}

{staff_note}

Please contact Support if you need help resolving this payment verification issue.
MESSAGE,
                'whatsapp_cta_buttons' => [
                    ['id' => 'btn_1', 'text' => '📦 View Order', 'type' => 'url', 'value' => '{order_url}'],
                    ['id' => 'btn_2', 'text' => '💬 Contact Admin', 'type' => 'url', 'value' => '{shop_whatsapp}'],
                ],
            ]),
            $this->template('ready_to_ship', [
                'email_enabled' => true,
                'email_subject' => '[{shop_name}] Order #{order_number} is Packed & Ready for Carrier Pickup',
                'email_preheader' => 'Your parcel is packed and labelled. Courier pickup scheduled.',
                'email_heading' => 'Parcel Packaged & Airway Bill Issued',
                'email_body' => <<<'BODY'
Dear {customer_name},

Your parcel for order #{order_number} has been securely packaged with bubble protection and tagged with a courier consignment barcode.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Shipment Details:
• Assigned Courier: {carrier_name}
• Airway Bill (AWB): {tracking_number}
• Destination: {shipping_address}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━

{staff_note}

Your tracking number will activate once scanned at the courier sorting hub.
BODY,
                'email_cta_text' => 'Check Tracking Status',
                'email_cta_url' => '{tracking_url}',
                'whatsapp_enabled' => true,
                'whatsapp_message' => <<<'MESSAGE'
Hi *{customer_name}*! 📦

Your parcel for order *#{order_number}* has been packed and labelled!

🚚 *Courier:* {carrier_name}
🏷️ *AWB / Tracking:* {tracking_number}

{staff_note}

Courier collection scheduled today. You will receive live updates when in transit!
MESSAGE,
                'whatsapp_cta_buttons' => [
                    ['id' => 'btn_1', 'text' => '🔍 Track Parcel', 'type' => 'url', 'value' => '{tracking_url}'],
                ],
            ]),
            $this->template('shipped', [
                'email_enabled' => true,
                'email_subject' => '[{shop_name}] Order #{order_number} Has Been Shipped via {carrier_name}!',
                'email_preheader' => 'Your order is on the move! Track your delivery progress in real time.',
                'email_heading' => 'Your Order Has Been Dispatched!',
                'email_body' => <<<'BODY'
Dear {customer_name},

Exciting news! Your order #{order_number} has been picked up by {carrier_name} and is on its way to you.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Delivery Details:
• Carrier: {carrier_name}
• Tracking Number: {tracking_number}
• Delivery Address: {shipping_address}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━

{staff_note}

You can track the live milestones of your delivery by clicking the button below:
BODY,
                'email_cta_text' => 'Track Shipment Live',
                'email_cta_url' => '{tracking_url}',
                'whatsapp_enabled' => true,
                'whatsapp_message' => <<<'MESSAGE'
Great news *{customer_name}*! 🚚💨

Your order *#{order_number}* was dispatched via *{carrier_name}*!

📍 *Tracking No:* {tracking_number}

Track your shipment in real time:
👉 {tracking_url}

{staff_note}

Thank you for shopping with *{shop_name}*!
MESSAGE,
                'whatsapp_cta_buttons' => [
                    ['id' => 'btn_1', 'text' => '🚚 Live Tracking', 'type' => 'url', 'value' => '{tracking_url}'],
                ],
            ]),
            $this->template('delivered', [
                'email_enabled' => true,
                'email_subject' => '[{shop_name}] Order #{order_number} Delivered - Thank You for Your Order!',
                'email_preheader' => 'Your parcel has been delivered. Share your experience with us!',
                'email_heading' => 'Delivered Successfully',
                'email_body' => <<<'BODY'
Dear {customer_name},

According to courier delivery confirmation, your package for order #{order_number} has been safely delivered to {shipping_address}.

We hope you are delighted with your items! If you have any inquiries regarding your purchase, our customer care team is always ready to assist.

{staff_note}

We would truly appreciate your rating and review to help us continue providing exceptional experiences.
BODY,
                'email_cta_text' => 'View Order Status',
                'email_cta_url' => '{order_url}',
                'whatsapp_enabled' => true,
                'whatsapp_message' => <<<'MESSAGE'
Hi *{customer_name}*! 🎉

Your order *#{order_number}* from *{shop_name}* has been successfully delivered!

We hope you love your new items! ✨

{staff_note}

⭐ If you have a moment, please leave us a review:
👉 {order_url}

Thank you for being our valued customer!
MESSAGE,
                'whatsapp_cta_buttons' => [
                    ['id' => 'btn_1', 'text' => '📦 View Order', 'type' => 'url', 'value' => '{order_url}'],
                ],
            ]),
            $this->template('cancelled', [
                'email_enabled' => true,
                'email_subject' => '[{shop_name}] Order #{order_number} Cancellation Notice',
                'email_preheader' => 'Notice regarding the cancellation and refund status of order #{order_number}.',
                'email_heading' => 'Order Cancellation Confirmation',
                'email_body' => <<<'BODY'
Dear {customer_name},

This notice is to confirm that order #{order_number} totaling {order_total} has been cancelled.

{staff_note}

If a payment was already captured, our finance department has initiated your refund process. Depending on your bank/payment provider, refunds typically reflect within 3-5 business days.

If you have any questions or did not request this cancellation, please contact our support team immediately.
BODY,
                'email_cta_text' => 'View Order Status',
                'email_cta_url' => '{order_url}',
                'whatsapp_enabled' => true,
                'whatsapp_message' => <<<'MESSAGE'
Hi *{customer_name}*,

Your order *#{order_number}* ({order_total}) has been cancelled.

{staff_note}

If you have any questions or require refund assistance, please reply directly to this message.

— {shop_name} Customer Care
MESSAGE,
                'whatsapp_cta_buttons' => [
                    ['id' => 'btn_1', 'text' => '💬 Support Chat', 'type' => 'url', 'value' => '{shop_whatsapp}'],
                ],
            ]),
            $this->template('on_hold', [
                'email_enabled' => false,
                'email_subject' => '[Action Required] {shop_name} - Urgent Update for Order #{order_number}',
                'email_preheader' => 'We need to verify some details regarding order #{order_number}.',
                'email_heading' => 'Order On Hold - Information Needed',
                'email_body' => <<<'BODY'
Dear {customer_name},

Your order #{order_number} is currently on hold while we verify some details with you.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Notice:
{staff_note}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Please reply to this email or reach us on WhatsApp at {shop_phone} so we can resume processing your shipment without further delay.
BODY,
                'email_cta_text' => 'View Order Status',
                'email_cta_url' => '{order_url}',
                'whatsapp_enabled' => true,
                'whatsapp_message' => <<<'MESSAGE'
Hi *{customer_name}*, ⚠️

Your order *#{order_number}* is currently on hold while our team verifies a few details with you.

{staff_note}

Please reply to this message as soon as possible so we can proceed with your order.

— {shop_name} Customer Support
MESSAGE,
                'whatsapp_cta_buttons' => [
                    ['id' => 'btn_1', 'text' => '💬 Reply to Support', 'type' => 'url', 'value' => '{shop_whatsapp}'],
                ],
            ]),
        ];
    }

    private function template(string $eventKey, array $values): array
    {
        $event = NotificationEventCatalog::get($eventKey);

        return [
            'event_key' => $eventKey,
            'status_name' => $event['status_name'],
            'color' => $event['color'],
            'version' => 1,
            ...$values,
        ];
    }
}
