@if ($errors->any())
  <div {{ $attributes->merge(['class' => 'rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700']) }} role="alert" data-validation-summary>
    <p class="font-semibold">Please fix the following:</p>
    <ul class="mt-2 list-inside list-disc space-y-1">
      @foreach ($errors->all() as $error)
        <li>{{ $error }}</li>
      @endforeach
    </ul>
  </div>
@endif
