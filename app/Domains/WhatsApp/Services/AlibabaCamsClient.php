<?php

declare(strict_types=1);

namespace App\Domains\WhatsApp\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class AlibabaCamsClient
{
    public function isConfigured(): bool
    {
        return filled(config('whatsapp.alibaba.access_key_id'))
            && filled(config('whatsapp.alibaba.access_key_secret'));
    }

    /**
     * @param  array<string, string|null>  $params
     */
    public function sendChatappMessage(array $params): Response
    {
        return $this->signedRequest(array_merge([
            'Action' => 'SendChatappMessage',
            'ChannelType' => 'whatsapp',
        ], $params));
    }

    /**
     * Bulk WhatsApp template send (CAMS marketing/mass API).
     * Up to 1,000 recipients per request, 10 QPS.
     *
     * @param  array<string, mixed>  $params
     */
    public function sendChatappMassMessage(array $params): Response
    {
        return $this->signedRequest(array_merge([
            'Action' => 'SendChatappMassMessage',
            'ChannelType' => 'whatsapp',
            'Type' => 'template',
        ], $params));
    }

    /**
     * @param  array<string, string|null>  $params
     */
    public function listTemplates(array $params = []): Response
    {
        return $this->signedRequest(array_merge([
            'Action' => 'ListChatappTemplate',
            'PageSize' => '50',
            'PageIndex' => '1',
        ], $params));
    }

    /**
     * Submit a new template to WhatsApp for approval.
     *
     * @param  array<string, mixed>  $components  Template component JSON
     * @param  array<string, string|null>  $params  Extra query params (CustSpaceId, etc.)
     */
    public function createChatappTemplate(string $name, string $language, string $category, array $components, array $params = []): Response
    {
        return $this->signedRequest(array_merge([
            'Action' => 'CreateChatappTemplate',
            'Name' => $name,
            'Language' => $language,
            'Category' => $category,
            'TemplateType' => 'WHATSAPP',
            'Components' => json_encode($components, JSON_THROW_ON_ERROR),
            'AllowCategoryChange' => 'false',
        ], $params));
    }

    /**
     * Modify an existing template (for edited template resubmission).
     *
     * @param  array<string, mixed>  $components
     * @param  array<string, string|null>  $params
     */
    public function modifyChatappTemplate(string $templateCode, string $name, string $language, string $category, array $components, array $params = []): Response
    {
        return $this->signedRequest(array_merge([
            'Action' => 'ModifyChatappTemplate',
            'TemplateCode' => $templateCode,
            'Name' => $name,
            'Language' => $language,
            'Category' => $category,
            'TemplateType' => 'WHATSAPP',
            'Components' => json_encode($components, JSON_THROW_ON_ERROR),
            'AllowCategoryChange' => 'false',
        ], $params));
    }

    /**
     * Get the approval status and detail of a template from WhatsApp.     *
     * @param  array<string, string|null>  $params
     */
    public function getChatappTemplateDetail(array $params): Response
    {
        return $this->signedRequest(array_merge([
            'Action' => 'GetChatappTemplateDetail',
        ], $params));
    }

    /**
     * Delete a template from WhatsApp.
     *
     * @param  array<string, string|null>  $params
     */
    public function deleteChatappTemplate(array $params): Response
    {
        return $this->signedRequest(array_merge([
            'Action' => 'DeleteChatappTemplate',
        ], $params));
    }

    /**
     * List Facebook product catalogs linked to the WABA.
     *
     * @param  array<string, string|null>  $params  Must include CustSpaceId
     */
    public function listProductCatalogs(array $params = []): Response
    {
        return $this->signedRequest(array_merge([
            'Action' => 'ListProductCatalog',
        ], $params));
    }

    /**
     * List products for a specific catalog.
     *
     * @param  array<string, string|null>  $params  Must include CustSpaceId, CatalogId
     */
    public function listProducts(array $params = []): Response
    {
        return $this->signedRequest(array_merge([
            'Action' => 'ListProduct',
        ], $params));
    }

    /**
     * @param  array<string, string|null>  $params
     */
    public function createFlow(array $params): Response
    {
        return $this->signedRequest(array_merge([
            'Action' => 'CreateFlow',
        ], $params));
    }

    /**
     * @param  array<string, string|null>  $params
     */
    public function updateFlowJsonAsset(array $params): Response
    {
        return $this->signedRequest(array_merge([
            'Action' => 'UpdateFlowJSONAsset',
        ], $params));
    }

    /**
     * @param  array<string, string|null>  $params
     */
    public function publishFlow(array $params): Response
    {
        return $this->signedRequest(array_merge([
            'Action' => 'PublishFlow',
        ], $params));
    }

    /**
     * @param  array<string, string|null>  $params
     */
    public function listFlows(array $params = []): Response
    {
        return $this->signedRequest(array_merge([
            'Action' => 'ListFlow',
            'Page.Size' => '100',
            'Page.Index' => '1',
        ], $params));
    }

    /**
     * @param  array<string, string|null>  $params
     */
    public function getFlowPreviewUrl(array $params): Response
    {
        return $this->signedRequest(array_merge([
            'Action' => 'GetFlowPreviewUrl',
        ], $params));
    }

    /**
     * @param  array<string, string|null>  $params
     */
    public function getFlowJsonAsset(array $params): Response
    {
        return $this->signedRequest(array_merge([
            'Action' => 'GetFlowJSONAssest',
        ], $params));
    }

    /**
     * @param  array<string, string|null>  $params
     */
    public function deprecateFlow(array $params): Response
    {
        return $this->signedRequest(array_merge([
            'Action' => 'DeprecateFlow',
        ], $params));
    }

    /**
     * @param  array<string, string|null>  $params
     */
    public function deleteFlow(array $params): Response
    {
        return $this->signedRequest(array_merge([
            'Action' => 'DeleteFlow',
        ], $params));
    }

    /**
     * Bind / refresh customer space for a WABA (returns CustSpaceId in body.data).
     *
     * @param  array<string, string|null>  $params  Must include WabaId
     */
    public function chatappBindWaba(array $params): Response
    {
        return $this->signedRequest(array_merge([
            'Action' => 'ChatappBindWaba',
        ], $params));
    }

    /**
     * Sync phone numbers under a customer space.
     *
     * @param  array<string, string|null>  $params  Must include CustSpaceId
     */
    public function chatappSyncPhoneNumber(array $params): Response
    {
        return $this->signedRequest(array_merge([
            'Action' => 'ChatappSyncPhoneNumber',
        ], $params));
    }

    /**
     * Query WABA business info.
     *
     * @param  array<string, string|null>  $params  Must include CustSpaceId, WabaId
     */
    public function queryWabaBusinessInfo(array $params): Response
    {
        return $this->signedRequest(array_merge([
            'Action' => 'QueryWabaBusinessInfo',
        ], $params));
    }

    /**
     * Query WhatsApp business profile for a phone number.
     *
     * @param  array<string, string|null>  $params  Must include CustSpaceId, PhoneNumber
     */
    public function queryPhoneBusinessProfile(array $params): Response
    {
        return $this->signedRequest(array_merge([
            'Action' => 'QueryPhoneBusinessProfile',
        ], $params));
    }

    /**
     * Push WhatsApp business profile updates for a phone number.
     *
     * @param  array<string, mixed>  $params
     */
    public function modifyPhoneBusinessProfile(array $params): Response
    {
        return $this->signedRequest(array_merge([
            'Action' => 'ModifyPhoneBusinessProfile',
        ], $params));
    }

    /**
     * Register status / uplink webhooks for a phone number.
     *
     * @param  array<string, string|null>  $params
     */
    public function updatePhoneWebhook(array $params): Response
    {
        return $this->signedRequest(array_merge([
            'Action' => 'UpdatePhoneWebhook',
            'HttpFlag' => 'Y',
            'QueueFlag' => 'N',
        ], $params));
    }

    /**
     * Register an additional WhatsApp number under a customer space.
     *
     * @param  array<string, string|null>  $params  CustSpaceId, PhoneNumber (national), Cc, VerifiedName
     */
    public function addChatappPhoneNumber(array $params): Response
    {
        return $this->signedRequest(array_merge([
            'Action' => 'AddChatappPhoneNumber',
        ], $params));
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function signedRequest(array $params): Response
    {
        $accessKeyId = (string) config('whatsapp.alibaba.access_key_id');
        $accessKeySecret = (string) config('whatsapp.alibaba.access_key_secret');
        $endpoint = (string) config('whatsapp.alibaba.endpoint', 'cams.ap-southeast-1.aliyuncs.com');

        $common = [
            'Format' => 'JSON',
            'Version' => '2020-06-06',
            'AccessKeyId' => $accessKeyId,
            'SignatureMethod' => 'HMAC-SHA1',
            'Timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'SignatureVersion' => '1.0',
            'SignatureNonce' => bin2hex(random_bytes(8)),
        ];

        $query = $this->flattenQuery(array_merge($common, $params));
        ksort($query);

        $canonicalized = collect($query)
            ->map(fn (string $value, string $key) => $this->percentEncode($key).'='.$this->percentEncode($value))
            ->implode('&');

        $stringToSign = 'GET&%2F&'.$this->percentEncode($canonicalized);
        $signature = base64_encode(hash_hmac('sha1', $stringToSign, $accessKeySecret.'&', true));
        $query['Signature'] = $signature;

        return Http::timeout(20)->get("https://{$endpoint}/", $query);
    }

    /**
     * Flatten nested RPC params (SenderList.1.To, …).
     *
     * @param  array<string, mixed>  $params
     * @return array<string, string>
     */
    private function flattenQuery(array $params, string $prefix = ''): array
    {
        $flat = [];

        foreach ($params as $key => $value) {
            $name = $prefix === '' ? (string) $key : $prefix.'.'.$key;

            if (is_array($value)) {
                if ($value === []) {
                    continue;
                }

                if (array_is_list($value)) {
                    foreach ($value as $index => $item) {
                        $indexed = $name.'.'.($index + 1);
                        if (is_array($item)) {
                            $flat = array_merge($flat, $this->flattenQuery($item, $indexed));
                        } elseif ($item !== null && $item !== '') {
                            $flat[$indexed] = (string) $item;
                        }
                    }

                    continue;
                }

                $flat = array_merge($flat, $this->flattenQuery($value, $name));

                continue;
            }

            if ($value === null || $value === '') {
                continue;
            }

            $flat[$name] = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
        }

        return $flat;
    }

    private function percentEncode(string $value): string
    {
        return str_replace(['+', '*', '%7E'], ['%20', '%2A', '~'], rawurlencode($value));
    }
}
