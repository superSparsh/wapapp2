<?php

declare(strict_types=1);

namespace App\Domains\Audience\Services;

use App\Domains\Audience\Models\ListField;
use App\Models\MailList;
use Illuminate\Support\Collection;

/**
 * Builds the pasteable Embedded Form HTML (fixed fields), separate from Form Builder.
 */
final class EmbeddedFormService
{
    /**
     * @return array{
     *   form_title: string,
     *   redirect_url: string,
     *   show_required_only: bool,
     *   include_js: bool,
     *   include_css: bool,
     *   show_invisible_fields: bool,
     *   custom_css: string
     * }
     */
    public function defaultOptions(): array
    {
        return [
            'form_title' => 'Subscribe to our WhatsApp list',
            'redirect_url' => '',
            'show_required_only' => false,
            'include_js' => true,
            'include_css' => true,
            'show_invisible_fields' => false,
            'custom_css' => ".subscribe-embedded-form {\n    color: #333\n}\n.subscribe-embedded-form label {\n    color: #555\n}",
        ];
    }

    /**
     * @return array{
     *   form_title: string,
     *   redirect_url: string,
     *   show_required_only: bool,
     *   include_js: bool,
     *   include_css: bool,
     *   show_invisible_fields: bool,
     *   custom_css: string
     * }
     */
    public function optionsFor(MailList $list): array
    {
        $stored = is_array($list->embedded_form_options) ? $list->embedded_form_options : [];

        return array_merge($this->defaultOptions(), [
            'form_title' => (string) ($stored['form_title'] ?? $this->defaultOptions()['form_title']),
            'redirect_url' => (string) ($stored['redirect_url'] ?? ''),
            'show_required_only' => (bool) ($stored['show_required_only'] ?? false),
            'include_js' => (bool) ($stored['include_js'] ?? true),
            'include_css' => (bool) ($stored['include_css'] ?? true),
            'show_invisible_fields' => (bool) ($stored['show_invisible_fields'] ?? false),
            'custom_css' => (string) ($stored['custom_css'] ?? $this->defaultOptions()['custom_css']),
        ]);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{
     *   form_title: string,
     *   redirect_url: string,
     *   show_required_only: bool,
     *   include_js: bool,
     *   include_css: bool,
     *   show_invisible_fields: bool,
     *   custom_css: string
     * }
     */
    public function normalizeInput(array $input): array
    {
        return [
            'form_title' => trim((string) ($input['form_title'] ?? $input['name'] ?? $this->defaultOptions()['form_title'])),
            'redirect_url' => trim((string) ($input['redirect_url'] ?? '')),
            'show_required_only' => filter_var($input['show_required_only'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'include_js' => filter_var($input['include_js'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'include_css' => filter_var($input['include_css'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'show_invisible_fields' => filter_var($input['show_invisible_fields'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'custom_css' => (string) ($input['custom_css'] ?? ''),
        ];
    }

    public function saveOptions(MailList $list, array $input): MailList
    {
        $list->forceFill([
            'embedded_form_options' => $this->normalizeInput($input),
        ])->save();

        return $list->refresh();
    }

    public function previewUrl(MailList $list): string
    {
        return $this->publicBase($list).'/embedded-form-preview';
    }

    public function subscribeUrl(MailList $list): string
    {
        return $this->publicBase($list).'/embedded-form-subscribe-captcha';
    }

    public function publicFormUrl(MailList $list): string
    {
        return $this->publicBase($list).'/embedded-form';
    }

    private function publicBase(MailList $list): string
    {
        $tenantId = (string) (tenant('id') ?? '');

        return rtrim(url('/lists/'.$tenantId.'/'.$list->uuid), '/');
    }

    private function assetUrl(string $path): string
    {
        return rtrim((string) config('app.url'), '/').'/'.ltrim($path, '/');
    }

    /**
     * Kept for preview/controllers that still call it.
     *
     * @return Collection<int, ListField>
     */
    public function visibleFields(MailList $list, ?array $options = null): Collection
    {
        return collect();
    }

    /**
     * Exact embed form HTML — only the form action URL is dynamic.
     * Fixed fields: country_code, phone_number, FIRST_NAME, LAST_NAME.
     */
    public function generateEmbedHtml(MailList $list, bool $forPreview = false): string
    {
        $options = $this->optionsFor($list);
        $action = $this->subscribeUrl($list);
        $html = '';

        // Match legacy embed assets (paths under /core/…, URLs dynamic)
        if ($options['include_css']) {
            $html .= '<link href="'.$this->assetUrl('core/css/embedded.css').'" rel="stylesheet" type="text/css">';
            $html .= '<link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">';
        }

        if (trim($options['custom_css']) !== '') {
            $html .= '<style>'.trim($options['custom_css']).'</style>';
        }

        $html .= '<div class="subscribe-embedded-form">';

        if (trim($options['form_title']) !== '') {
            $html .= '<h2>'.e($options['form_title']).'</h2>';
        }

        $html .= '<p class="text-sm text-end"><span class="text-danger">*</span> indicates required</p>';

        if ($forPreview) {
            $html .= $this->fixedFormFieldsHtml();
        } else {
            $html .= '<form action="'.e($action).'" method="POST" class="form-validate-jqueryz" novalidate="novalidate">'."\n";
            $html .= $this->fixedFormFieldsHtml();
            if (trim($options['redirect_url']) !== '') {
                $html .= '<input type="hidden" name="redirect_url" value="'.e($options['redirect_url']).'">'."\n";
            }
            $html .= '</form>';
        }

        $html .= '</div>';

        if ($options['include_css']) {
            $html .= '<link href="'.$this->assetUrl('core/css/app.css').'?v='.urlencode((string) config('app.version', '1')).'" rel="stylesheet" type="text/css">';
        }

        if ($options['include_js'] && ! $forPreview) {
            $appUrl = rtrim((string) config('app.url'), '/');
            $html .= '<script type="text/javascript" src="'.$this->assetUrl('core/js/jquery-3.6.0.min.js').'"></script>';
            $html .= '<script type="text/javascript" src="'.$this->assetUrl('core/validate/jquery.validate.min.js').'"></script>';
            $html .= '<script type="text/javascript" src="'.$this->assetUrl('core/datetime/anytime.min.js').'"></script>';
            $html .= '<script type="text/javascript" src="'.$this->assetUrl('core/datetime/moment.min.js').'"></script>';
            $html .= '<script type="text/javascript" src="'.$this->assetUrl('core/datetime/pickadate/picker.js').'"></script>';
            $html .= '<script type="text/javascript" src="'.$this->assetUrl('core/datetime/pickadate/picker.date.js').'"></script>';
            $html .= '<script type="text/javascript" src="'.$this->assetUrl('core/js/functions.js').'"></script>';
            $html .= '<script>'
                .'var APP_URL = '.json_encode($appUrl).';'
                .'var LANG_OK = \'OK\';'
                .'var LANG_CONFIRM = \'Confirm\';'
                .'var LANG_YES = \'Yes\';'
                .'var LANG_NO = \'No\';'
                .'var LANG_ARE_YOU_SURE = \'Are you sure?\';'
                .'var LANG_CANCEL = \'Cancel\';'
                .'var LANG_DELETE_VALIDATE = \'Please enter the text exactly as it is displayed to confirm deletion.\';'
                .'var LANG_DATE_FORMAT = \'yyyy-mm-dd\';'
                .'var LANG_ANY_DATETIME_FORMAT = \'%Z-%m-%d, %H:%i\';'
                .'var CSRF_TOKEN = '.json_encode((string) csrf_token()).';'
                .'var LANG_SUCCESS = \'Success\';'
                .'var LANG_ALERT = \'Alert\';'
                .'var LANG_ERROR = \'Error\';'
                .'var LANG_CONFIRMATION = \'Confirmation\';'
                .'var LANG_NOTIFY = {\'success\': \'Success\',\'error\': \'Error\',\'notice\': \'Notice\'};'
                .'var LOADING_WAIT = \'Loading, please wait...\';'
                .'</script>';
            $html .= '<script>jQuery( document ).ready(function( $ ) {'
                .'$(".subscribe-embedded-form form").validate({});'
                .'initJs($(\'.subscribe-embedded-form\'));'
                .'});</script>';
        }

        return $html;
    }

    /**
     * Same-to-same field markup as provided — no list-field generation.
     */
    private function fixedFormFieldsHtml(): string
    {
        return <<<'HTML'
<div class="form-group control-text">
<label> Country Code <span class="text-danger">*</span>
</label>
<input id="country_code" placeholder="" value="" type="text" name="country_code" class="form-control required ">
</div>
<div class="form-group control-text">
<label> WHATSAPP NUMBER <span class="text-danger">*</span>
</label>
<input id="phone_number" placeholder="" value="" type="text" name="phone_number" class="form-control required ">
</div>
<div class="form-group control-text">
<label> First name </label>
<input id="FIRST_NAME" placeholder="" value="" type="text" name="FIRST_NAME" class="form-control ">
</div>
<div class="form-group control-text">
<label> Last name </label>
<input id="LAST_NAME" placeholder="" value="" type="text" name="LAST_NAME" class="form-control ">
</div>
<div class="form-button">
<button class="btn btn-primary">Subscribe</button>
</div>

HTML;
    }

    public function fieldName(ListField $field): string
    {
        $tag = trim((string) ($field->tag ?? ''));
        if ($tag !== '') {
            return $tag;
        }

        return 'field_'.$field->id;
    }
}
