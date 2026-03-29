<?php

return [
    'stripe_not_configured' => 'Stripe is not configured. Set STRIPE_SECRET in .env',
    'webhook_not_configured' => 'Stripe webhook is not configured. Set STRIPE_WEBHOOK_SECRET in .env',
    'webhook_invalid_payload' => 'Invalid webhook payload',
    'webhook_invalid_signature' => 'Invalid webhook signature',
];
