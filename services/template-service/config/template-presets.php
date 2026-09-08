<?php

declare(strict_types=1);

return [
    'utility' => [
        [
            'id' => 'finalize_account',
            'title' => 'Finalize account set-up',
            'name' => 'finalize_account_setup',
            'body' => "Hi \$(first_name),\nYour new account has been created successfully.\nPlease verify \$(email) to complete your profile.",
        ],
        [
            'id' => 'address_update',
            'title' => 'Address update',
            'name' => 'address_update',
            'body' => "Hi \$(first_name), your delivery address has been successfully updated to \$(address).\nContact \$(support_phone) for any inquiries.",
        ],
        [
            'id' => 'technician_visit',
            'title' => 'Technician visit',
            'name' => 'technician_visit',
            'body' => "Hi \$(first_name), we're scheduling a technician visit for your \$(service_type) on \$(visit_date) between \$(time_start) and \$(time_end).\nPlease confirm if this time slot works for you.",
        ],
        [
            'id' => 'auto_payment',
            'title' => 'Upcoming automatic payment',
            'name' => 'upcoming_auto_payment',
            'body' => "Hi \$(first_name),\nYour automatic payment for \$(service_name) is scheduled on \$(payment_date) for \$(amount).\nKindly ensure your balance is sufficient to avoid late fees.",
        ],
        [
            'id' => 'order_delivered',
            'title' => 'Order delivered',
            'name' => 'order_delivered',
            'body' => "Hi \$(first_name), your order \$(order_id) was successfully delivered on \$(delivery_date).\nThank you for your purchase!",
        ],
        [
            'id' => 'payment_reminder',
            'title' => 'Payment reminder',
            'name' => 'payment_reminder',
            'body' => "Hi \$(first_name), this is a reminder that your payment of \$(amount) for \$(invoice_id) is due on \$(due_date).\nPay now to avoid service interruption.",
        ],
        [
            'id' => 'shipping_update',
            'title' => 'Shipping update',
            'name' => 'shipping_update',
            'body' => "Hi \$(first_name), your order \$(order_id) has shipped.\nTrack it here: \$(tracking_url)",
        ],
    ],
];
