<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Book a Meeting – {{ $link->title }}</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gray-50 flex items-center justify-center p-4">
  <div class="w-full max-w-lg rounded-2xl bg-white shadow-lg p-8">
    <h1 class="text-2xl font-bold text-gray-900 mb-1">{{ $link->title }}</h1>
    <p class="text-sm text-gray-500 mb-6">{{ $link->duration_minutes }}-minute meeting</p>

    @if (session('success'))
      <div class="mb-4 rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>
    @endif
    @if (session('error'))
      <div class="mb-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
    @endif

    @if ($errors->any())
      <div class="mb-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">
        @foreach ($errors->all() as $err)<p>{{ $err }}</p>@endforeach
      </div>
    @endif

    <form method="POST" action="{{ route('google-calendar.booking.book', $link->slug) }}">
      @csrf
      <div class="flex flex-col gap-4">
        <div>
          <label class="mb-1 block text-sm font-medium text-gray-700">Your Name <span class="text-red-500">*</span></label>
          <input type="text" name="name" value="{{ old('name') }}" required
            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500" />
        </div>

        <div>
          <label class="mb-1 block text-sm font-medium text-gray-700">WhatsApp Number <span class="text-red-500">*</span></label>
          <input type="text" name="phone" value="{{ old('phone') }}" required placeholder="+911234567890"
            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500" />
        </div>

        <div>
          <label class="mb-1 block text-sm font-medium text-gray-700">Email (optional)</label>
          <input type="email" name="email" value="{{ old('email') }}"
            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500" />
        </div>

        <div>
          <label class="mb-1 block text-sm font-medium text-gray-700">Select Date</label>
          <input type="date" id="date-picker" min="{{ today()->toDateString() }}"
            value="{{ today()->toDateString() }}"
            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500" />
        </div>

        <div>
          <label class="mb-1 block text-sm font-medium text-gray-700">Available Slots</label>
          <div id="slots-container" class="flex flex-wrap gap-2 min-h-[40px]">
            <p class="text-xs text-gray-400">Select a date to see available slots.</p>
          </div>
          <input type="hidden" name="start_at" id="start_at_input" />
        </div>

        <button type="submit" id="book-btn" disabled
          class="mt-2 w-full rounded-lg bg-green-500 py-3 text-sm font-semibold text-white hover:opacity-90 disabled:opacity-40 disabled:cursor-not-allowed transition-opacity">
          Book Meeting
        </button>
      </div>
    </form>
  </div>

  <script>
    const datePicker = document.getElementById('date-picker');
    const slotsContainer = document.getElementById('slots-container');
    const startInput = document.getElementById('start_at_input');
    const bookBtn = document.getElementById('book-btn');
    const slug = '{{ $link->slug }}';

    async function loadSlots(date) {
      slotsContainer.innerHTML = '<p class="text-xs text-gray-400">Loading...</p>';
      bookBtn.disabled = true;
      startInput.value = '';

      try {
        const res = await fetch(`/book/google/${slug}/availability?date=${date}`);
        const data = await res.json();
        const slots = data.slots || [];

        if (!slots.length) {
          slotsContainer.innerHTML = '<p class="text-xs text-gray-400">No available slots for this date.</p>';
          return;
        }

        slotsContainer.innerHTML = '';
        slots.forEach(slot => {
          const btn = document.createElement('button');
          btn.type = 'button';
          btn.textContent = slot.label ?? slot;
          btn.className = 'rounded-lg border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:border-green-500 hover:text-green-600 transition-colors slot-btn';
          btn.dataset.value = slot.start ?? slot;
          btn.addEventListener('click', () => {
            document.querySelectorAll('.slot-btn').forEach(b => b.classList.remove('border-green-500', 'bg-green-50', 'text-green-600'));
            btn.classList.add('border-green-500', 'bg-green-50', 'text-green-600');
            startInput.value = btn.dataset.value;
            bookBtn.disabled = false;
          });
          slotsContainer.appendChild(btn);
        });
      } catch (e) {
        slotsContainer.innerHTML = '<p class="text-xs text-red-500">Failed to load slots.</p>';
      }
    }

    datePicker.addEventListener('change', e => loadSlots(e.target.value));
    loadSlots(datePicker.value);
  </script>
</body>
</html>
