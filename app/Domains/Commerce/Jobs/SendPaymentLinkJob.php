<?php

declare(strict_types=1);

namespace App\Domains\Commerce\Jobs;

use App\Domains\Commerce\Enums\PaymentLinkStatus;
use App\Domains\Commerce\Models\CommercePayment;
use App\Domains\Commerce\Models\PaymentConfig;
use App\Domains\WhatsApp\Services\AlibabaCamsClient;
use App\Models\Template;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Dispatched after a CommercePayment is created.
 * Sends the payment link via WhatsApp using the configured payment template.
 */
class SendPaymentLinkJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries   = 3;
    public int $timeout = 30;

    public function __construct(
        private readonly CommercePayment $payment,
        private readonly string $tenantId,
    ) {}

    public function handle(AlibabaCamsClient $camsClient): void
    {
        // Re-initialize tenant context (jobs run outside HTTP cycle)
        /** @var \App\Models\Tenant|null $tenant */
        $tenant = tenancy()->central(fn () => \App\Models\Tenant::query()->find($this->tenantId));

        if (! $tenant) {
            Log::error('SendPaymentLinkJob: tenant not found', ['tenant_id' => $this->tenantId]);

            return;
        }

        tenancy()->initialize($tenant);

        try {
            $config = PaymentConfig::query()->first();

            if (! $config || ! $config->isConfigured() || ! $config->payment_template_id) {
                Log::warning('SendPaymentLinkJob: payment config or template not configured', [
                    'payment_id' => $this->payment->id,
                ]);

                return;
            }

            $template = Template::query()->find($config->payment_template_id);

            if (! $template) {
                Log::warning('SendPaymentLinkJob: payment template not found', [
                    'template_id' => $config->payment_template_id,
                ]);

                return;
            }

            // Get default WhatsApp line
            $line = \App\Models\WhatsappLine::query()
                ->where('is_default', true)
                ->first();

            if (! $line) {
                Log::warning('SendPaymentLinkJob: no default WhatsApp line found');

                return;
            }

            if (! $camsClient->isConfigured()) {
                Log::warning('SendPaymentLinkJob: CAMS client not configured');

                return;
            }

            // Build template message payload
            $params = array_filter([
                'From'         => $line->phone,
                'To'           => $this->payment->customer_phone,
                'Language'     => config('whatsapp.alibaba.default_language', 'en_GB'),
                'TemplateCode' => $template->code ?? $template->name,
                'TemplateParam' => json_encode([
                    'customer_name' => $this->payment->customer_name,
                    'amount'        => '₹'.(string) $this->payment->amount,
                    'payment_link'  => $this->payment->payment_link,
                    'order_ref'     => $this->payment->internal_order_ref,
                ]),
                'CustSpaceId'  => $line->alibaba_cust_space_id,
            ]);

            $response = $camsClient->sendChatappMessage($params);

            if ($response->successful()) {
                $this->payment->update(['status' => PaymentLinkStatus::Sent]);
                Log::info('SendPaymentLinkJob: payment link sent', [
                    'payment_id' => $this->payment->id,
                    'phone'      => $this->payment->customer_phone,
                ]);
            } else {
                Log::error('SendPaymentLinkJob: CAMS send failed', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
            }
        } finally {
            tenancy()->end();
        }
    }
}
