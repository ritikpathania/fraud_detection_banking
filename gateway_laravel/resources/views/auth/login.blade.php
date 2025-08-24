<x-guest-layout>
  <div class="w-full max-w-md mx-auto bg-white rounded-lg shadow p-6">
    <h1 class="text-xl font-semibold mb-4">Log in</h1>

    {{-- Session Status --}}
    @if (session('status'))
      <div class="mb-4 text-sm text-green-600">
        {{ session('status') }}
      </div>
    @endif

    {{-- Validation Errors --}}
    @if ($errors->any())
      <div class="mb-4 text-sm text-red-600">
        <ul class="list-disc pl-5">
          @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
      @csrf

      <div>
        <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
        <input id="email" name="email" type="email" required autofocus autocomplete="username"
               class="mt-1 block w-full border rounded px-3 py-2" value="{{ old('email') }}">
      </div>

      <div>
        <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
        <input id="password" name="password" type="password" required autocomplete="current-password"
               class="mt-1 block w-full border rounded px-3 py-2">
      </div>

      <div class="flex items-center justify-between">
        <label class="inline-flex items-center text-sm">
          <input type="checkbox" name="remember" class="mr-2">
          Remember me
        </label>

        @if (Route::has('password.request'))
          <a class="text-sm text-blue-600 hover:underline" href="{{ route('password.request') }}">
            Forgot your password?
          </a>
        @endif
      </div>

      <button class="w-full bg-gray-900 text-white rounded px-4 py-2 hover:bg-gray-800">
        LOG IN
      </button>
    </form>
  </div>
</x-guest-layout>
