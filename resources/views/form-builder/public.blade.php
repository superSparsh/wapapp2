<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $form->name }}</title>
    <style>
        :root {
            --green: #6dbb48;
            --green-dark: #4f9a32;
            --text: #1f2937;
            --muted: #6b7280;
            --border: #e5e7eb;
            --surface: #f3f4f6;
            --card: #ffffff;
            --danger: #dc2626;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            min-height: 100vh;
            font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            color: var(--text);
            background:
                radial-gradient(circle at top right, rgba(109, 187, 72, 0.18), transparent 40%),
                linear-gradient(180deg, #f8faf8 0%, var(--surface) 100%);
            padding: 24px 16px 48px;
        }
        .shell { max-width: 480px; margin: 0 auto; }
        .card {
            background: var(--card);
            border: 1px solid rgba(0,0,0,0.04);
            border-radius: 20px;
            padding: 28px 24px;
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.08);
        }
        .logo { text-align: center; margin-bottom: 18px; }
        .logo img { max-width: 160px; max-height: 72px; object-fit: contain; }
        .header {
            font-size: 22px; font-weight: 800; line-height: 1.3;
            text-align: center; margin-bottom: 8px; color: var(--text);
        }
        .paragraph {
            font-size: 14px; line-height: 1.55; color: var(--muted);
            text-align: center; margin-bottom: 22px;
        }
        .field { margin-bottom: 16px; }
        label {
            display: block; font-size: 13px; font-weight: 600;
            margin-bottom: 6px; color: var(--text);
        }
        .req { color: var(--danger); }
        input[type="text"], input[type="email"], input[type="tel"], select, textarea {
            width: 100%; padding: 12px 14px; border: 1px solid var(--border);
            border-radius: 12px; font-size: 14px; background: #fff; color: var(--text);
            outline: none; transition: border-color .15s ease, box-shadow .15s ease;
        }
        input:focus, select:focus, textarea:focus {
            border-color: var(--green);
            box-shadow: 0 0 0 3px rgba(109, 187, 72, 0.18);
        }
        .checkbox-row { display: flex; align-items: flex-start; gap: 10px; }
        .checkbox-row input { width: 18px; height: 18px; margin-top: 1px; accent-color: var(--green); }
        .checkbox-row label { margin: 0; font-weight: 500; }
        button[type="submit"] {
            width: 100%; margin-top: 8px; padding: 14px 16px;
            border: 0; border-radius: 12px; background: var(--green); color: #fff;
            font-size: 15px; font-weight: 700; cursor: pointer;
            transition: background .15s ease, transform .15s ease;
        }
        button[type="submit"]:hover { background: var(--green-dark); }
        button[type="submit"]:active { transform: translateY(1px); }
        .success {
            text-align: center; padding: 28px 12px;
        }
        .success-icon {
            width: 56px; height: 56px; margin: 0 auto 14px; border-radius: 999px;
            background: rgba(109, 187, 72, 0.15); color: var(--green-dark);
            display: grid; place-items: center; font-size: 28px; font-weight: 700;
        }
        .success h2 { font-size: 20px; margin-bottom: 6px; }
        .success p { color: var(--muted); font-size: 14px; }
        .error { color: var(--danger); font-size: 12px; margin-top: 6px; }
        .footer-note {
            margin-top: 18px; text-align: center; font-size: 13px; color: var(--muted);
            display: flex; align-items: center; justify-content: center; gap: 8px;
            font-weight: 600;
        }
        .footer-note .brand-dot { width: 8px; height: 8px; border-radius: 999px; background: var(--green); }
        @if (!empty($form->custom_css))
            {!! $form->custom_css !!}
        @endif
    </style>
</head>
<body>
    <div class="shell">
        <div class="card">
            @if (session('status'))
                <div class="success">
                    <div class="success-icon">✓</div>
                    <h2>You're all set</h2>
                    <p>{{ session('status') }}</p>
                </div>
            @else
                <form method="post" action="{{ route('public.form.submit', ['tenant' => tenant('id'), 'slug' => $form->slug]) }}" novalidate>
                    @csrf

                    @foreach ($fields as $index => $field)
                        @php
                            $type = (string) ($field['type'] ?? 'input');
                            $label = (string) ($field['label'] ?? ucfirst(str_replace('_', ' ', $type)));
                            $required = (bool) ($field['required'] ?? false);
                            $placeholder = (string) ($field['placeholder'] ?? '');
                            $text = (string) ($field['text'] ?? '');
                            $displayText = $text !== '' ? $text : $placeholder;
                            $inputName = match ($type) {
                                'phone' => 'phone',
                                'first_name' => 'first_name',
                                'last_name' => 'last_name',
                                default => $type.'_'.$index,
                            };
                            $oldValue = old($inputName);
                        @endphp

                        @if ($type === 'logo')
                            @if (! empty($field['image_path']) || ! empty($form->logo_path))
                                <div class="logo">
                                    <img
                                        src="{{ asset('storage/' . ($field['image_path'] ?? $form->logo_path)) }}"
                                        alt="{{ $label }}"
                                    >
                                </div>
                            @endif
                        @elseif ($type === 'header')
                            <h1 class="header">{{ $displayText !== '' ? $displayText : $label }}</h1>
                        @elseif ($type === 'paragraph')
                            <p class="paragraph">{{ $displayText !== '' ? $displayText : $label }}</p>
                        @elseif ($type === 'checkbox')
                            <div class="field checkbox-row">
                                <input
                                    type="checkbox"
                                    id="field-{{ $index }}"
                                    name="{{ $inputName }}"
                                    value="1"
                                    @if ($required) required @endif
                                    @checked((string) $oldValue === '1')
                                >
                                <label for="field-{{ $index }}">
                                    {{ $label }}
                                    @if ($required) <span class="req">*</span> @endif
                                </label>
                            </div>
                            @error($inputName)<div class="error">{{ $message }}</div>@enderror
                        @elseif ($type === 'dropdown')
                            <div class="field">
                                <label for="field-{{ $index }}">
                                    {{ $label }}
                                    @if ($required) <span class="req">*</span> @endif
                                </label>
                                <select id="field-{{ $index }}" name="{{ $inputName }}" @if ($required) required @endif>
                                    <option value="" disabled @selected($oldValue === null)>{{ $placeholder !== '' ? $placeholder : 'Select an option' }}</option>
                                    @foreach ($field['options'] ?? [] as $option)
                                        <option value="{{ $option }}" @selected((string) $oldValue === (string) $option)>{{ $option }}</option>
                                    @endforeach
                                </select>
                                @error($inputName)<div class="error">{{ $message }}</div>@enderror
                            </div>
                        @elseif (in_array($type, ['phone', 'first_name', 'last_name', 'input'], true))
                            <div class="field">
                                <label for="field-{{ $index }}">
                                    {{ $label }}
                                    @if ($required || $type === 'phone') <span class="req">*</span> @endif
                                </label>
                                <input
                                    id="field-{{ $index }}"
                                    type="{{ $type === 'phone' ? 'tel' : 'text' }}"
                                    name="{{ $inputName }}"
                                    value="{{ $oldValue }}"
                                    placeholder="{{ $placeholder }}"
                                    @if ($required || $type === 'phone') required @endif
                                    @if ($type === 'phone') autocomplete="tel" @endif
                                >
                                @error($inputName)<div class="error">{{ $message }}</div>@enderror
                                @if ($type === 'phone')
                                    @error('phone')<div class="error">{{ $message }}</div>@enderror
                                @endif
                            </div>
                        @endif
                    @endforeach

                    <button type="submit">Submit</button>
                </form>
            @endif
        </div>

        <p class="footer-note"><span class="brand-dot"></span> Powered by WapApp</p>
    </div>
</body>
</html>
