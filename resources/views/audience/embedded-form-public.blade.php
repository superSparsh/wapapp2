<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $options['form_title'] ?: $list->name }}</title>
    {{-- Preview always loads stylesheet so admin sees the real form look --}}
    <link href="{{ url('/core/css/embedded.css') }}" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
    @if (filled($options['custom_css']))
        <style>{!! $options['custom_css'] !!}</style>
    @endif
</head>
<body style="margin:0;padding:16px;background:#fff;">
    <div class="subscribe-embedded-form">
        @if (filled($options['form_title']))
            <h2>{{ $options['form_title'] }}</h2>
        @endif
        <p class="text-sm text-end"><span class="text-danger">*</span> indicates required</p>

        <form
            action="{{ $action }}"
            method="POST"
            class="form-validate-jqueryz"
            novalidate="novalidate"
            @if ($preview) onsubmit="return false;" @endif
        >
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
@if (filled($options['redirect_url']))
<input type="hidden" name="redirect_url" value="{{ $options['redirect_url'] }}">
@endif
<div class="form-button">
<button class="btn btn-primary" @if ($preview) disabled @endif>Subscribe</button>
</div>
        </form>
    </div>

    <link href="{{ url('/core/css/app.css') }}?v={{ config('app.version', '1') }}" rel="stylesheet" type="text/css">

    <script type="text/javascript" src="{{ url('/core/js/jquery-3.6.0.min.js') }}"></script>
    <script type="text/javascript" src="{{ url('/core/validate/jquery.validate.min.js') }}"></script>
    <script type="text/javascript" src="{{ url('/core/datetime/anytime.min.js') }}"></script>
    <script type="text/javascript" src="{{ url('/core/datetime/moment.min.js') }}"></script>
    <script type="text/javascript" src="{{ url('/core/datetime/pickadate/picker.js') }}"></script>
    <script type="text/javascript" src="{{ url('/core/datetime/pickadate/picker.date.js') }}"></script>
    <script type="text/javascript" src="{{ url('/core/js/functions.js') }}"></script>
    <script>
        var APP_URL = @json(rtrim((string) config('app.url'), '/'));
        var LANG_OK = 'OK';
        var LANG_CONFIRM = 'Confirm';
        var LANG_YES = 'Yes';
        var LANG_NO = 'No';
        var LANG_ARE_YOU_SURE = 'Are you sure?';
        var LANG_CANCEL = 'Cancel';
        var LANG_DELETE_VALIDATE = 'Please enter the text exactly as it is displayed to confirm deletion.';
        var LANG_DATE_FORMAT = 'yyyy-mm-dd';
        var LANG_ANY_DATETIME_FORMAT = '%Z-%m-%d, %H:%i';
        var CSRF_TOKEN = @json((string) csrf_token());
        var LANG_SUCCESS = 'Success';
        var LANG_ALERT = 'Alert';
        var LANG_ERROR = 'Error';
        var LANG_CONFIRMATION = 'Confirmation';
        var LANG_NOTIFY = {'success': 'Success','error': 'Error','notice': 'Notice'};
        var LOADING_WAIT = 'Loading, please wait...';
    </script>
    <script>
        jQuery(document).ready(function ($) {
            $(".subscribe-embedded-form form").validate({});
            if (typeof initJs === 'function') {
                initJs($('.subscribe-embedded-form'));
            }
        });
    </script>
</body>
</html>
